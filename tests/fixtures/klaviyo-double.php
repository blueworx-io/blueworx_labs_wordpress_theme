<?php
/**
 * Plugin Name: BlueWorx Klaviyo test double
 * Description: Answers for Klaviyo on a test site, so no test can ever reach the real service, and behaves like a plugin built for the site. Test fixture only.
 *
 * Copied into mu-plugins by tests/fixtures/klaviyo.php for the length of the spec.
 *
 * @package BlueWorxLabsTests
 */

// A test double WordPress loads only on a test site.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.DB.DirectDatabaseQuery

/**
 * Answers every request bound for Klaviyo, and writes down what was asked.
 *
 * One row per call rather than one growing list: the queue can be running in a
 * background request at the same time as the fixture.
 *
 * Behaviour is steered by two options:
 *   blueworx_klaviyo_double_fail      How many more requests answer 503.
 *   blueworx_klaviyo_double_profiles  Profiles Klaviyo "holds", keyed by email.
 */
add_filter(
	'pre_http_request',
	static function ( $short, $args, $url ) {
		if ( false === strpos( $url, 'a.klaviyo.com' ) ) {
			return $short;
		}

		$body = isset( $args['body'] ) ? json_decode( $args['body'], true ) : null;

		add_option(
			'blueworx_klaviyo_double_call_' . str_pad( (string) hrtime( true ), 24, '0', STR_PAD_LEFT ),
			wp_json_encode(
				array(
					'method'   => $args['method'],
					'url'      => $url,
					'revision' => isset( $args['headers']['revision'] ) ? $args['headers']['revision'] : '',
					'auth'     => isset( $args['headers']['Authorization'] ) ? $args['headers']['Authorization'] : '',
					'body'     => $body,
				)
			),
			'',
			'no'
		);

		$answer = static function ( $code, $payload = array() ) {
			return array(
				'response' => array(
					'code'    => $code,
					'message' => '',
				),
				'body'     => wp_json_encode( $payload ),
				'headers'  => array(),
				'cookies'  => array(),
			);
		};

		$fail = (int) get_option( 'blueworx_klaviyo_double_fail', 0 );

		if ( $fail > 0 ) {
			update_option( 'blueworx_klaviyo_double_fail', $fail - 1, 'no' );

			return $answer( 503, array( 'errors' => array( array( 'detail' => 'The double is down' ) ) ) );
		}

		$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
		$query    = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$profiles = get_option( 'blueworx_klaviyo_double_profiles', array() );
		$profiles = is_array( $profiles ) ? $profiles : array();

		if ( '/api/lists' === $path ) {
			return $answer(
				200,
				array(
					'data' => array(
						array(
							'id'         => 'Y5fFQA',
							'attributes' => array( 'name' => 'Newsletter' ),
						),
						array(
							'id'         => 'XRYaZh',
							'attributes' => array( 'name' => 'Members' ),
						),
					),
				)
			);
		}

		if ( '/api/profiles' === $path ) {
			parse_str( $query, $params );
			$email = isset( $params['filter'] ) && preg_match( '/equals\(email,"(.+)"\)/', $params['filter'], $m ) ? strtolower( $m[1] ) : '';

			return $answer(
				200,
				array(
					'data' => isset( $profiles[ $email ] ) ? array( array( 'attributes' => $profiles[ $email ] ) ) : array(),
				)
			);
		}

		if ( '/api/profile-import' === $path ) {
			$attributes = isset( $body['data']['attributes'] ) ? (array) $body['data']['attributes'] : array();
			$email      = isset( $attributes['email'] ) ? strtolower( (string) $attributes['email'] ) : '';
			$held       = isset( $profiles[ $email ] ) ? (array) $profiles[ $email ] : array( 'properties' => array() );
			$props      = isset( $attributes['properties'] ) ? (array) $attributes['properties'] : array();
			unset( $attributes['properties'] );

			$held               = array_merge( $held, $attributes );
			$held['properties'] = array_merge( isset( $held['properties'] ) ? (array) $held['properties'] : array(), $props );

			foreach ( isset( $body['data']['meta']['patch_properties']['unset'] ) ? (array) $body['data']['meta']['patch_properties']['unset'] : array() as $name ) {
				unset( $held['properties'][ $name ] );
			}

			$profiles[ $email ] = $held;
			update_option( 'blueworx_klaviyo_double_profiles', $profiles, 'no' );

			return $answer( 200, array( 'data' => array( 'id' => 'prof_' . md5( $email ) ) ) );
		}

		if ( '/api/events' === $path || '/api/profile-subscription-bulk-create-jobs' === $path ) {
			return $answer( 202 );
		}

		return $answer( 404, array( 'errors' => array( array( 'detail' => 'The double does not know ' . $path ) ) ) );
	},
	10,
	3
);

/**
 * Behaves like a plugin built for the site: adds a property to everything it
 * sends, and protects one property the marketing team manage by hand.
 */
add_filter(
	'blueworx_klaviyo_plan',
	static function ( $plan, $source ) {
		if ( 'fixture' === $source ) {
			$plan['profile']['seen_by_site_plugin'] = true;
		}

		if ( 'fixture-blocked' === $source ) {
			return null;
		}

		return $plan;
	},
	10,
	2
);

add_filter(
	'blueworx_klaviyo_protected_properties',
	static function ( $names ) {
		$names[] = 'member_number';
		return $names;
	}
);

/**
 * Stands in for SureCart's records, from whatever the fixture has seeded, and
 * for the two look-ups that need a SureCart account.
 */
add_filter(
	'blueworx_klaviyo_surecart_record',
	static function ( $short, $type, $id ) {
		$records = get_option( 'blueworx_klaviyo_double_records', array() );

		return isset( $records[ $type ][ $id ] ) ? $records[ $type ][ $id ] : new WP_Error( 'not_found', 'No such record.' );
	},
	10,
	3
);

add_filter(
	'blueworx_klaviyo_payment_number',
	static function () {
		return 2;
	}
);

add_filter(
	'blueworx_klaviyo_subscription_for_order',
	static function ( $short, $order ) {
		return isset( $order['subscription_for_test'] ) ? $order['subscription_for_test'] : '';
	},
	10,
	2
);

// SureCart is not on the test site, so its product type is stood in for and
// the price comes from the filter a site plugin would use.
add_action(
	'init',
	static function () {
		if ( ! post_type_exists( 'sc_product' ) ) {
			register_post_type(
				'sc_product',
				array(
					'public'  => true,
					'label'   => 'Products (test double)',
					'rewrite' => array( 'slug' => 'test-products' ),
				)
			);
		}
	}
);

add_filter(
	'blueworx_klaviyo_viewed_product',
	static function ( $details ) {
		$details['Price']    = 129.5;
		$details['Currency'] = 'AUD';
		return $details;
	}
);

/**
 * Stands in for Elementor's form record, which has the two methods the
 * connection reads.
 */
if ( ! class_exists( 'Klaviyo_Double_Record' ) ) {
	// phpcs:ignore Squiz.Commenting.ClassComment.Missing -- Described above.
	class Klaviyo_Double_Record {
		/**
		 * Form name.
		 *
		 * @var string
		 */
		private $name;

		/**
		 * Fields keyed by id.
		 *
		 * @var array
		 */
		private $fields;

		/**
		 * Constructor.
		 *
		 * @param string $name   Form name.
		 * @param array  $fields Fields keyed by id.
		 */
		public function __construct( $name, $fields ) {
			$this->name   = $name;
			$this->fields = $fields;
		}

		/**
		 * A form setting.
		 *
		 * @param string $setting Setting name.
		 * @return string
		 */
		public function get_form_settings( $setting ) {
			return 'form_name' === $setting ? $this->name : '';
		}

		/**
		 * A record property.
		 *
		 * @param string $property Property name.
		 * @return array
		 */
		public function get( $property ) {
			return 'fields' === $property ? $this->fields : array();
		}
	}
}

add_filter(
	'blueworx_klaviyo_sources',
	static function ( $sources ) {
		$sources[] = array(
			'label' => 'Test double',
			'text'  => 'Sends whatever the spec asks it to.',
		);
		return $sources;
	}
);
