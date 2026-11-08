<?php
/**
 * Klaviyo connection: the queue and the log.
 *
 * A form or a checkout never waits on Klaviyo. It writes one row and carries
 * on, and a background run does the talking and the retrying. If Klaviyo is
 * down the person never knows, and the work waits for it to come back.
 *
 * Each job is its own option row rather than an item in one big array, because
 * two people acting at the same moment would otherwise overwrite each other's
 * work. A repeat of the same job key lands on the same row, so it cannot run
 * twice.
 *
 * A job holds either 'plans', ready to send, or a 'resolver' with 'data' for
 * work that has to be looked up first, in the background, through the
 * blueworx_klaviyo_resolve_job filter.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many sends the log keeps.
 */
const BLUEWORX_KLAVIYO_LOG_LIMIT = 100;

/**
 * The option the log lives in.
 */
const BLUEWORX_KLAVIYO_LOG_OPTION = 'blueworx_klaviyo_log';

/**
 * Start of every queued job's option name.
 */
const BLUEWORX_KLAVIYO_JOB_PREFIX = 'blueworx_klaviyo_job_';

/**
 * The wait before each retry, in seconds. Its length sets the number of tries.
 *
 * @return int[]
 */
function blueworx_klaviyo_retry_delays() {
	return array( 60, 300, 900, 3600, 21600 );
}

/**
 * Adds a job to the queue. Never throws, and never talks to Klaviyo itself.
 *
 * @param string $key Identifies the job, so a repeat lands on the same row.
 * @param array  $job The job: label, email when known, and either 'plans' or a 'resolver' with 'data'.
 * @return bool Whether it was queued.
 */
function blueworx_klaviyo_queue_job( $key, $job ) {
	if ( ! blueworx_klaviyo_enabled() ) {
		return false;
	}

	if ( '' === blueworx_klaviyo_private_key() ) {
		blueworx_klaviyo_log( isset( $job['label'] ) ? $job['label'] : '', isset( $job['email'] ) ? $job['email'] : '', 'failed', __( 'There is no private API key yet, so this was not sent.', 'blueworx-labs-wordpress' ) );
		return false;
	}

	$job['attempts'] = 0;
	$job['next_at']  = 0;

	add_option( BLUEWORX_KLAVIYO_JOB_PREFIX . md5( (string) $key ), $job, '', 'no' );

	blueworx_klaviyo_wake_queue();

	return true;
}

/**
 * Asks WordPress to run the queue as soon as it can, in a separate request.
 *
 * The event's argument changes each minute because WordPress drops a repeat of
 * the same event within ten minutes, which would hold this job up that long.
 *
 * @return void
 */
function blueworx_klaviyo_wake_queue() {
	wp_schedule_single_event( time(), 'blueworx_klaviyo_run_queue', array( (int) floor( time() / MINUTE_IN_SECONDS ) ) );

	if ( function_exists( 'spawn_cron' ) ) {
		spawn_cron();
	}
}

/**
 * Reads the waiting jobs straight from the table, oldest first.
 *
 * Straight from the table because these rows are written by other requests, and
 * the option cache in this one would not know about them.
 *
 * @return array Jobs keyed by option name.
 */
function blueworx_klaviyo_waiting_jobs() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- See above: the cache cannot be trusted for rows other requests write.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT 200",
			$wpdb->esc_like( BLUEWORX_KLAVIYO_JOB_PREFIX ) . '%'
		)
	);

	$jobs = array();

	foreach ( (array) $rows as $row ) {
		$job = maybe_unserialize( $row->option_value );

		if ( is_array( $job ) ) {
			$jobs[ $row->option_name ] = $job;
		}
	}

	return $jobs;
}

/**
 * The background run: works through whatever is due.
 *
 * Two runs overlapping would only repeat work that is safe to repeat, so the
 * lock is there to save requests rather than to keep anything correct.
 *
 * @return void
 */
function blueworx_klaviyo_run_queue() {
	if ( ! blueworx_klaviyo_connected() ) {
		return;
	}

	if ( get_transient( 'blueworx_klaviyo_lock' ) ) {
		return;
	}

	set_transient( 'blueworx_klaviyo_lock', 1, 3 * MINUTE_IN_SECONDS );

	$deadline = time() + 45;

	foreach ( blueworx_klaviyo_waiting_jobs() as $name => $job ) {
		if ( time() >= $deadline ) {
			break;
		}

		if ( ! empty( $job['next_at'] ) && (int) $job['next_at'] > time() ) {
			continue;
		}

		blueworx_klaviyo_process_job( $name, $job );
	}

	delete_transient( 'blueworx_klaviyo_lock' );
}
add_action( 'blueworx_klaviyo_run_queue', 'blueworx_klaviyo_run_queue' );

/**
 * Works out the plans a job carries.
 *
 * @param array $job The job.
 * @return array|WP_Error Plans, possibly none, or an error worth a retry.
 */
function blueworx_klaviyo_job_plans( $job ) {
	if ( isset( $job['plans'] ) && is_array( $job['plans'] ) ) {
		return $job['plans'];
	}

	if ( empty( $job['resolver'] ) ) {
		return array();
	}

	/**
	 * Turns a job that needs looking up into plans, in the background.
	 *
	 * Return an array of plans (possibly empty), or a WP_Error to have the job
	 * tried again later. Anything else means "not mine".
	 *
	 * @param null|array|WP_Error $plans Nothing yet.
	 * @param string              $resolver The job's resolver name.
	 * @param array               $data     What the job was queued with.
	 */
	$plans = apply_filters( 'blueworx_klaviyo_resolve_job', null, (string) $job['resolver'], isset( $job['data'] ) ? (array) $job['data'] : array() );

	if ( is_wp_error( $plans ) ) {
		return $plans;
	}

	if ( ! is_array( $plans ) ) {
		return new WP_Error( 'blueworx_klaviyo_no_resolver', sprintf( 'Nothing knows how to work out a "%s" job.', (string) $job['resolver'] ) );
	}

	// Plans worked out here have not been past the plan filter yet, so a
	// site's own rules see these sends the same as every other.
	$source = isset( $job['source'] ) && '' !== (string) $job['source'] ? (string) $job['source'] : (string) $job['resolver'];

	return array_values(
		array_filter(
			array_map(
				static function ( $plan ) use ( $source ) {
					$plan = blueworx_klaviyo_normalize_plan( $plan );

					return $plan ? blueworx_klaviyo_normalize_plan( apply_filters( 'blueworx_klaviyo_plan', $plan, $source ) ) : null;
				},
				$plans
			)
		)
	);
}

/**
 * Handles one job from start to finish.
 *
 * @param string $name Option name.
 * @param array  $job  The job.
 * @return void
 */
function blueworx_klaviyo_process_job( $name, $job ) {
	$label = isset( $job['label'] ) ? (string) $job['label'] : '';
	$email = isset( $job['email'] ) ? (string) $job['email'] : '';

	try {
		$plans = blueworx_klaviyo_job_plans( $job );
	} catch ( Throwable $thrown ) {
		$plans = new WP_Error( 'blueworx_klaviyo_exception', $thrown->getMessage() );
	}

	if ( is_wp_error( $plans ) ) {
		blueworx_klaviyo_retry_job( $name, $job, $label, $email, $plans->get_error_message() );
		return;
	}

	foreach ( $plans as $plan ) {
		$plan = blueworx_klaviyo_normalize_plan( $plan );

		if ( ! $plan ) {
			continue;
		}

		$outcome = blueworx_klaviyo_send_plan( $plan );

		if ( ! $outcome['ok'] ) {
			if ( in_array( isset( $outcome['status'] ) ? (int) $outcome['status'] : 0, array( 401, 403 ), true ) ) {
				/**
				 * Fires when Klaviyo refuses the private API key.
				 *
				 * @param string $error What Klaviyo said.
				 */
				do_action( 'blueworx_klaviyo_key_refused', $outcome['error'] );
			}

			if ( $outcome['retry'] ) {
				blueworx_klaviyo_retry_job( $name, $job, $plan['label'], $plan['email'], $outcome['error'] );
				return;
			}

			blueworx_klaviyo_give_up( $plan['label'], $plan['email'], $outcome['error'] );
			continue;
		}

		blueworx_klaviyo_log( $plan['label'], $plan['email'], 'sent', '' );

		/**
		 * Fires once a plan has reached Klaviyo.
		 *
		 * @param array $plan The plan that went through.
		 */
		do_action( 'blueworx_klaviyo_sent', $plan );
	}

	delete_option( $name );
}

/**
 * Sets a job to try again later, or gives up on it out loud.
 *
 * @param string $name    Option name.
 * @param array  $job     The job.
 * @param string $label   What the log calls it.
 * @param string $email   Who it is about.
 * @param string $message What went wrong.
 * @return void
 */
function blueworx_klaviyo_retry_job( $name, $job, $label, $email, $message ) {
	$delays   = blueworx_klaviyo_retry_delays();
	$attempts = isset( $job['attempts'] ) ? (int) $job['attempts'] : 0;

	if ( $attempts >= count( $delays ) ) {
		blueworx_klaviyo_give_up( $label, $email, $message );
		delete_option( $name );
		return;
	}

	$job['attempts'] = $attempts + 1;
	$job['next_at']  = time() + $delays[ $attempts ];

	update_option( $name, $job, 'no' );

	blueworx_klaviyo_log( $label, $email, 'retrying', $message );
}

/**
 * Writes a send off: a failed line in the log, and word to whoever listens.
 *
 * @param string $label   What it was.
 * @param string $email   Who it was about.
 * @param string $message What went wrong.
 * @return void
 */
function blueworx_klaviyo_give_up( $label, $email, $message ) {
	blueworx_klaviyo_log( $label, $email, 'failed', $message );

	/**
	 * Fires when a send has failed for good and will not be tried again.
	 *
	 * @param string $label   What it was.
	 * @param string $email   Who it was about.
	 * @param string $message What went wrong.
	 */
	do_action( 'blueworx_klaviyo_gave_up', $label, $email, $message );
}

/**
 * Adds a line to the log.
 *
 * @param string $label   What it was.
 * @param string $email   Who it was about.
 * @param string $outcome sent, retrying or failed.
 * @param string $detail  What went wrong, if anything.
 * @return void
 */
function blueworx_klaviyo_log( $label, $email, $outcome, $detail ) {
	$log = get_option( BLUEWORX_KLAVIYO_LOG_OPTION, array() );
	$log = is_array( $log ) ? $log : array();

	array_unshift(
		$log,
		array(
			'time'    => time(),
			'label'   => (string) $label,
			'email'   => (string) $email,
			'outcome' => (string) $outcome,
			'detail'  => substr( (string) $detail, 0, 300 ),
		)
	);

	update_option( BLUEWORX_KLAVIYO_LOG_OPTION, array_slice( $log, 0, BLUEWORX_KLAVIYO_LOG_LIMIT ), 'no' );
}

/**
 * The log, newest first.
 *
 * @return array
 */
function blueworx_klaviyo_log_lines() {
	$log = get_option( BLUEWORX_KLAVIYO_LOG_OPTION, array() );

	return is_array( $log ) ? $log : array();
}

/**
 * Keeps a slow run going every five minutes while the function is on, so
 * retries happen and nothing waits on a request that happens to wake the queue.
 *
 * @param array $schedules WordPress's schedules.
 * @return array
 */
function blueworx_klaviyo_cron_schedule( $schedules ) {
	$schedules['blueworx_klaviyo_five_minutes'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => 'Every five minutes (BlueWorx Klaviyo)',
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'blueworx_klaviyo_cron_schedule' ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Retries need to run sooner than the fifteen minutes the check asks for.

/**
 * Starts or stops the regular run to match the function's switch.
 *
 * @return void
 */
function blueworx_klaviyo_sync_cron() {
	$scheduled = wp_next_scheduled( 'blueworx_klaviyo_tick' );

	if ( blueworx_klaviyo_enabled() ) {
		if ( ! $scheduled ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'blueworx_klaviyo_five_minutes', 'blueworx_klaviyo_tick' );
		}
	} elseif ( $scheduled ) {
		wp_clear_scheduled_hook( 'blueworx_klaviyo_tick' );
	}
}
add_action( 'init', 'blueworx_klaviyo_sync_cron' );
add_action( 'blueworx_klaviyo_tick', 'blueworx_klaviyo_run_queue' );
