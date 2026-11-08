<?php
/**
 * Klaviyo connection: a product feed for Klaviyo's catalogue.
 *
 * Klaviyo can show real products in emails, with live prices and images, and
 * recommend them, once it has a catalogue. It fills one from a feed it fetches
 * on a schedule. This is that feed: every published SureCart product, in the
 * shape Klaviyo's custom catalogue import expects, at an address with a
 * secret in it.
 *
 * The feed is built in the background and kept, so a fetch from Klaviyo never
 * touches SureCart or the database for products. It is rebuilt every hour,
 * and the moment a product is saved.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How old the kept feed may get before the tick rebuilds it.
 */
const BLUEWORX_KLAVIYO_FEED_MAX_AGE = HOUR_IN_SECONDS;

/**
 * How many products the feed carries at most.
 */
const BLUEWORX_KLAVIYO_FEED_LIMIT = 2000;

/**
 * The secret in the feed's address, made the first time it is needed.
 *
 * @return string
 */
function blueworx_klaviyo_feed_secret() {
	$secret = (string) blueworx_klaviyo_setting( 'feed_secret', '' );

	if ( '' === $secret ) {
		$secret = blueworx_klaviyo_feed_new_secret();
	}

	return $secret;
}

/**
 * Makes a fresh secret, so the old address stops working.
 *
 * @return string
 */
function blueworx_klaviyo_feed_new_secret() {
	$secret = wp_generate_password( 32, false, false );

	blueworx_klaviyo_update_setting( 'feed_secret', $secret );

	return $secret;
}

/**
 * The address to give Klaviyo.
 *
 * @return string
 */
function blueworx_klaviyo_feed_url() {
	return add_query_arg( 'key', blueworx_klaviyo_feed_secret(), rest_url( 'blueworx/v1/klaviyo/feed' ) );
}

/**
 * Whether a key is the feed's. Pure.
 *
 * @param string $given  The key in the request.
 * @param string $secret The feed's secret.
 * @return bool
 */
function blueworx_klaviyo_feed_key_valid( $given, $secret ) {
	return '' !== (string) $secret && '' !== (string) $given && hash_equals( (string) $secret, (string) $given );
}

/**
 * One product, in the names Klaviyo's catalogue import expects. Pure.
 *
 * Klaviyo's own fields start with a dollar sign; anything else rides along
 * as a custom field. A product without a name is not an item.
 *
 * @param array $details From blueworx_klaviyo_viewed_product_details(): ProductName, ProductID, URL, ImageURL, Price, Currency.
 * @param array $extra   Optional: description, in_stock (bool|null), categories (string[]).
 * @return array|null
 */
function blueworx_klaviyo_feed_item( $details, $extra = array() ) {
	if ( empty( $details['ProductName'] ) || empty( $details['ProductID'] ) ) {
		return null;
	}

	$item = array(
		'$id'          => (string) $details['ProductID'],
		'$title'       => (string) $details['ProductName'],
		'$link'        => isset( $details['URL'] ) ? (string) $details['URL'] : '',
		'$image_link'  => isset( $details['ImageURL'] ) ? (string) $details['ImageURL'] : '',
		'$description' => isset( $extra['description'] ) ? (string) $extra['description'] : '',
		'$price'       => isset( $details['Price'] ) && '' !== $details['Price'] ? (float) $details['Price'] : null,
		'currency'     => isset( $details['Currency'] ) ? (string) $details['Currency'] : '',
	);

	if ( isset( $extra['in_stock'] ) && null !== $extra['in_stock'] ) {
		$item['$inventory_policy']   = 1;
		$item['$inventory_quantity'] = $extra['in_stock'] ? 1 : 0;
	}

	if ( ! empty( $extra['categories'] ) && is_array( $extra['categories'] ) ) {
		$item['$categories'] = array_values( array_filter( array_map( 'strval', $extra['categories'] ) ) );
	}

	return blueworx_klaviyo_clean( $item );
}

/**
 * The extra bits of a product the viewed-product details leave out.
 *
 * @param int $post_id The product's post ID.
 * @return array description, in_stock and categories.
 */
function blueworx_klaviyo_feed_product_extra( $post_id ) {
	$post  = get_post( $post_id );
	$extra = array(
		'description' => $post ? wp_strip_all_tags( has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( (string) $post->post_content, 55, '…' ) ) : '',
		'in_stock'    => null,
		'categories'  => array(),
	);

	if ( function_exists( 'sc_get_product' ) ) {
		$product = sc_get_product( $post_id );

		if ( is_object( $product ) && ! empty( $product->stock_enabled ) && isset( $product->available_stock ) ) {
			$extra['in_stock'] = (int) $product->available_stock > 0 || ! empty( $product->allow_out_of_stock_purchases );
		}

		if ( is_object( $product ) && ! empty( $product->description ) && '' === $extra['description'] ) {
			$extra['description'] = wp_strip_all_tags( (string) $product->description );
		}
	}

	foreach ( (array) wp_get_object_terms( $post_id, array( 'sc_collection' ), array( 'fields' => 'names' ) ) as $term ) {
		if ( is_string( $term ) ) {
			$extra['categories'][] = $term;
		}
	}

	return $extra;
}

/**
 * Builds the feed from the published products.
 *
 * @return array Items.
 */
function blueworx_klaviyo_feed_items() {
	$items = array();

	if ( post_type_exists( 'sc_product' ) ) {
		$ids = get_posts(
			array(
				'post_type'      => 'sc_product',
				'post_status'    => 'publish',
				'posts_per_page' => BLUEWORX_KLAVIYO_FEED_LIMIT,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( (array) $ids as $post_id ) {
			$item = blueworx_klaviyo_feed_item( blueworx_klaviyo_viewed_product_details( $post_id ), blueworx_klaviyo_feed_product_extra( $post_id ) );

			if ( $item ) {
				$items[] = $item;
			}
		}
	}

	/**
	 * The products the Klaviyo feed lists.
	 *
	 * Add an item, drop one, or change a price. Each item carries $id, $title,
	 * $link, $image_link, $description, $price and currency, and may carry
	 * $inventory_quantity, $inventory_policy and $categories.
	 *
	 * @param array $items Items.
	 */
	$items = apply_filters( 'blueworx_klaviyo_feed_items', $items );

	return array_values(
		array_filter(
			(array) $items,
			static function ( $item ) {
				return is_array( $item ) && ! empty( $item['$id'] ) && ! empty( $item['$title'] );
			}
		)
	);
}

/**
 * Builds the feed and keeps it for Klaviyo to fetch.
 *
 * @return array built (timestamp) and items.
 */
function blueworx_klaviyo_feed_rebuild() {
	$kept = array(
		'built' => time(),
		'items' => blueworx_klaviyo_feed_items(),
	);

	blueworx_klaviyo_update_setting( 'feed_cache', $kept );

	return $kept;
}

/**
 * The kept feed, or nothing yet.
 *
 * @return array built and items, or an empty array.
 */
function blueworx_klaviyo_feed_kept() {
	$kept = blueworx_klaviyo_setting( 'feed_cache', array() );

	return is_array( $kept ) && isset( $kept['items'] ) && is_array( $kept['items'] ) ? $kept : array();
}

/**
 * Rebuilds the feed on the tick once it is an hour old.
 *
 * @return void
 */
function blueworx_klaviyo_feed_refresh() {
	if ( ! blueworx_klaviyo_enabled() ) {
		return;
	}

	$kept = blueworx_klaviyo_feed_kept();

	if ( ! $kept || (int) $kept['built'] < time() - BLUEWORX_KLAVIYO_FEED_MAX_AGE ) {
		blueworx_klaviyo_feed_rebuild();
	}
}
add_action( 'blueworx_klaviyo_tick', 'blueworx_klaviyo_feed_refresh', 40 );

/**
 * A saved product makes the kept feed stale, so the next tick rebuilds it.
 *
 * @return void
 */
function blueworx_klaviyo_feed_stale() {
	$kept = blueworx_klaviyo_feed_kept();

	if ( $kept ) {
		$kept['built'] = 0;
		blueworx_klaviyo_update_setting( 'feed_cache', $kept );
		blueworx_klaviyo_wake_queue();
	}
}
add_action( 'save_post_sc_product', 'blueworx_klaviyo_feed_stale' );
add_action( 'deleted_post', 'blueworx_klaviyo_feed_stale' );

/**
 * Adds the feed's address, only while the function is on.
 *
 * @return void
 */
function blueworx_klaviyo_feed_register_route() {
	if ( ! blueworx_klaviyo_enabled() ) {
		return;
	}

	register_rest_route(
		'blueworx/v1',
		'/klaviyo/feed',
		array(
			'methods'             => 'GET',
			'callback'            => 'blueworx_klaviyo_feed_serve',
			// The secret in the address is the permission: Klaviyo has no
			// WordPress account. A wrong one gets a 404 in the callback.
			'permission_callback' => '__return_true',
			'args'                => array(
				'key' => array(
					'type'     => 'string',
					'required' => false,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'blueworx_klaviyo_feed_register_route' );

/**
 * Serves the kept feed to Klaviyo.
 *
 * @param WP_REST_Request $request The request.
 * @return WP_REST_Response|WP_Error
 */
function blueworx_klaviyo_feed_serve( $request ) {
	if ( ! blueworx_klaviyo_feed_key_valid( (string) $request->get_param( 'key' ), (string) blueworx_klaviyo_setting( 'feed_secret', '' ) ) ) {
		return new WP_Error( 'rest_no_route', 'No route was found matching the URL and request method.', array( 'status' => 404 ) );
	}

	$kept = blueworx_klaviyo_feed_kept();

	if ( ! $kept ) {
		$kept = blueworx_klaviyo_feed_rebuild();
	}

	$response = new WP_REST_Response( $kept['items'], 200 );
	$response->header( 'Cache-Control', 'private, max-age=300' );
	$response->header( 'X-Robots-Tag', 'noindex' );

	return $response;
}

// --- The screen ---------------------------------------------------------------

/**
 * The feed card on the Klaviyo screen.
 *
 * @return void
 */
function blueworx_klaviyo_feed_card() {
	$kept = blueworx_klaviyo_feed_kept();
	$body = '';

	if ( ! blueworx_klaviyo_surecart_active() ) {
		$body .= wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'info',
					'text' => __( 'SureCart is not installed on this site, so the feed lists whatever a plugin adds to it, or nothing.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	$body .= blueworx_ds_field(
		array(
			'label'   => __( 'Feed address', 'blueworx-labs-wordpress' ),
			'help'    => __( 'In Klaviyo, under Content, Products, add a custom catalogue source with this address. Klaviyo fetches it on its own schedule. Anyone with the address can read your product list, so treat it like a password.', 'blueworx-labs-wordpress' ),
			'control' => '<code data-testid="blueworx-klaviyo-feed-url">' . esc_html( blueworx_klaviyo_feed_url() ) . '</code>',
		)
	);

	$body .= blueworx_ds_field(
		array(
			'label' => __( 'What it lists', 'blueworx-labs-wordpress' ),
			'help'  => $kept
				? sprintf(
					/* translators: 1: number of products, 2: date and time. */
					_n( '%1$s product, built %2$s. Rebuilt every hour and whenever a product is saved.', '%1$s products, built %2$s. Rebuilt every hour and whenever a product is saved.', count( $kept['items'] ), 'blueworx-labs-wordpress' ),
					number_format_i18n( count( $kept['items'] ) ),
					(int) $kept['built'] > 0 ? wp_date( 'j M Y H:i', (int) $kept['built'] ) : __( 'a while ago', 'blueworx-labs-wordpress' )
				)
				: __( 'Not built yet. It is built the first time Klaviyo asks for it, or on the next five-minute tick.', 'blueworx-labs-wordpress' ),
		)
	);

	$body .= blueworx_ds_checkbox(
		array(
			'name'  => 'blueworx_klaviyo_feed_new_secret',
			'value' => '1',
			'label' => __( 'Make a new address', 'blueworx-labs-wordpress' ),
			'help'  => __( 'The old address stops working at once. Paste the new one into Klaviyo.', 'blueworx-labs-wordpress' ),
		)
	);

	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The design system helpers escape everything they emit.
		array(
			'title' => __( 'Product feed', 'blueworx-labs-wordpress' ),
			'body'  => $body,
		)
	);
}
add_action( 'blueworx_klaviyo_screen_cards', 'blueworx_klaviyo_feed_card', 6 );

/**
 * Makes a new secret when the box was ticked.
 *
 * @param string[] $refused Labels refused so far.
 * @return string[]
 */
function blueworx_klaviyo_feed_save( $refused ) {
	// The caller has verified the nonce and the capability.
	if ( ! empty( $_POST['blueworx_klaviyo_feed_new_secret'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		blueworx_klaviyo_feed_new_secret();
	}

	return (array) $refused;
}
add_filter( 'blueworx_klaviyo_save_settings', 'blueworx_klaviyo_feed_save' );
