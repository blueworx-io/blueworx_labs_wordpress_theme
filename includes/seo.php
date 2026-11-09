<?php
/**
 * Basic SEO: the description, share card and canonical tags for every page.
 *
 * Off by default. This is the safety net for a site without an SEO plugin: it
 * writes the handful of tags a page needs to show properly in search results
 * and when it is shared, from three site-wide settings and whatever the page
 * already has (its title, excerpt and featured image).
 *
 * When an SEO plugin is running it writes these tags itself, so this writes
 * nothing at all rather than a second copy. SureRank is the plugin BlueWorx
 * sites use; the other common ones are recognised too.
 *
 * Per-page fields are deliberately not here, see issue #64.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The SEO plugin that is running, if any.
 *
 * @return string The plugin's name, or an empty string when there is none.
 */
function blueworx_seo_plugin() {
	$plugins = array(
		'SureRank'          => array( 'SURERANK_VERSION', 'SURERANK_FILE' ),
		'Yoast SEO'         => array( 'WPSEO_VERSION' ),
		'Rank Math'         => array( 'RANK_MATH_VERSION' ),
		'All in One SEO'    => array( 'AIOSEO_VERSION' ),
		'SEOPress'          => array( 'SEOPRESS_VERSION' ),
		'The SEO Framework' => array( 'THE_SEO_FRAMEWORK_VERSION' ),
	);

	$found = '';

	foreach ( $plugins as $name => $constants ) {
		foreach ( $constants as $constant ) {
			if ( defined( $constant ) ) {
				$found = $name;
				break 2;
			}
		}
	}

	/**
	 * Filters the name of the SEO plugin this theme stands down for.
	 *
	 * @param string $found The plugin's name, or an empty string for none.
	 */
	return (string) apply_filters( 'blueworx_seo_plugin', $found );
}

/**
 * Whether this theme writes the SEO tags on this request.
 *
 * @return bool
 */
function blueworx_seo_active() {
	return blueworx_feature_enabled( 'seo' ) && '' === blueworx_seo_plugin();
}

/**
 * Flattens text to one plain line, fit for a tag's content.
 *
 * @param string $text Raw text.
 * @return string
 */
function blueworx_seo_plain( $text ) {
	return trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
}

/**
 * The saved site-wide settings.
 *
 * @return array With site_name, description and image (an attachment id).
 */
function blueworx_seo_settings() {
	return array(
		'site_name'   => blueworx_seo_plain( get_option( 'blueworx_seo_site_name', '' ) ),
		'description' => blueworx_seo_plain( get_option( 'blueworx_seo_description', '' ) ),
		'image'       => absint( get_option( 'blueworx_seo_image', 0 ) ),
	);
}

/**
 * Saves the settings from the SEO screen.
 *
 * The caller has already verified the nonce and the capability.
 *
 * @param array $posted Raw $_POST.
 * @return void
 */
function blueworx_seo_save( $posted ) {
	foreach ( array( 'site_name', 'description' ) as $key ) {
		$field = 'blueworx_seo_' . $key;
		$raw   = isset( $posted[ $field ] ) ? (string) wp_unslash( $posted[ $field ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Flattened to plain text on the next line.

		update_option( $field, blueworx_seo_plain( $raw ) );
	}

	$image = isset( $posted['blueworx_seo_image'] ) ? absint( wp_unslash( $posted['blueworx_seo_image'] ) ) : 0;

	// Only an image from this site's own library is kept.
	update_option( 'blueworx_seo_image', $image && wp_attachment_is_image( $image ) ? $image : 0 );
}

/**
 * The page being viewed, when it is a single page or post.
 *
 * @return WP_Post|null
 */
function blueworx_seo_current_post() {
	if ( ! is_singular() ) {
		return null;
	}

	$post = get_queried_object();

	return $post instanceof WP_Post ? $post : null;
}

/**
 * The name the site goes by on a share card.
 *
 * @return string
 */
function blueworx_seo_site_name() {
	$settings = blueworx_seo_settings();

	return '' !== $settings['site_name'] ? $settings['site_name'] : blueworx_seo_plain( get_bloginfo( 'name' ) );
}

/**
 * This page's title, for the share card.
 *
 * A page or post uses its own title. The home page and everything else use the
 * title the browser tab shows.
 *
 * @return string
 */
function blueworx_seo_title() {
	$post = blueworx_seo_current_post();

	if ( $post && ! is_front_page() ) {
		$title = blueworx_seo_plain( get_the_title( $post ) );

		if ( '' !== $title ) {
			return $title;
		}
	}

	return blueworx_seo_plain( wp_get_document_title() );
}

/**
 * This page's description.
 *
 * The page's own excerpt when it has one, then the site default, then the
 * site's tagline.
 *
 * @return string
 */
function blueworx_seo_description() {
	$post = blueworx_seo_current_post();

	if ( $post && '' !== trim( (string) $post->post_excerpt ) && ! post_password_required( $post ) ) {
		return blueworx_seo_plain( $post->post_excerpt );
	}

	$settings = blueworx_seo_settings();

	if ( '' !== $settings['description'] ) {
		return $settings['description'];
	}

	return blueworx_seo_plain( get_bloginfo( 'description' ) );
}

/**
 * This page's share image.
 *
 * The page's featured image when it has one, then the site default.
 *
 * @return array|null With url, width, height and alt, or null when there is none.
 */
function blueworx_seo_image() {
	$post = blueworx_seo_current_post();
	$ids  = array();

	if ( $post && has_post_thumbnail( $post ) && ! post_password_required( $post ) ) {
		$ids[] = (int) get_post_thumbnail_id( $post );
	}

	$ids[] = blueworx_seo_settings()['image'];

	foreach ( array_filter( $ids ) as $id ) {
		$src = wp_get_attachment_image_src( $id, 'full' );

		if ( $src && ! empty( $src[0] ) ) {
			return array(
				'url'    => (string) $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
				'alt'    => blueworx_seo_plain( get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
			);
		}
	}

	return null;
}

/**
 * This page's one true address.
 *
 * @return string A URL, or an empty string where there is no single address
 *                (search results, a 404).
 */
function blueworx_seo_canonical() {
	if ( is_front_page() ) {
		return home_url( '/' );
	}

	$post = blueworx_seo_current_post();

	if ( $post ) {
		return (string) wp_get_canonical_url( $post );
	}

	if ( is_home() ) {
		$posts_page = (int) get_option( 'page_for_posts' );

		return $posts_page ? (string) get_permalink( $posts_page ) : home_url( '/' );
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$link = get_term_link( get_queried_object() );

		return is_wp_error( $link ) ? '' : (string) $link;
	}

	if ( is_post_type_archive() ) {
		return (string) get_post_type_archive_link( get_query_var( 'post_type' ) );
	}

	if ( is_author() ) {
		return (string) get_author_posts_url( (int) get_queried_object_id() );
	}

	return '';
}

/**
 * Builds this page's tags.
 *
 * An empty value is left out rather than written as an empty tag: a share
 * checker reads an empty image tag as a broken one.
 *
 * @return string HTML.
 */
function blueworx_seo_head_html() {
	$title       = blueworx_seo_title();
	$description = blueworx_seo_description();
	$canonical   = blueworx_seo_canonical();
	$image       = blueworx_seo_image();

	$tags = array(
		array( 'name', 'description', $description ),
		array( 'property', 'og:url', $canonical ),
		array( 'property', 'og:type', is_singular( 'post' ) ? 'article' : 'website' ),
		array( 'property', 'og:site_name', blueworx_seo_site_name() ),
		array( 'property', 'og:title', $title ),
		array( 'property', 'og:description', $description ),
	);

	if ( $image ) {
		$tags[] = array( 'property', 'og:image', $image['url'] );
		$tags[] = array( 'property', 'og:image:width', $image['width'] ? (string) $image['width'] : '' );
		$tags[] = array( 'property', 'og:image:height', $image['height'] ? (string) $image['height'] : '' );
		$tags[] = array( 'property', 'og:image:alt', $image['alt'] );
	}

	$tags[] = array( 'name', 'twitter:card', $image ? 'summary_large_image' : 'summary' );
	$tags[] = array( 'name', 'twitter:title', $title );
	$tags[] = array( 'name', 'twitter:description', $description );

	if ( $image ) {
		$tags[] = array( 'name', 'twitter:image', $image['url'] );
	}

	$html = "<!-- BlueWorx SEO -->\n";

	if ( '' !== $canonical ) {
		$html .= '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
	}

	foreach ( $tags as $tag ) {
		list( $attr, $key, $value ) = $tag;

		if ( '' === (string) $value ) {
			continue;
		}

		$is_url = in_array( $key, array( 'og:url', 'og:image', 'twitter:image' ), true );

		$html .= sprintf(
			'<meta %1$s="%2$s" content="%3$s" />' . "\n",
			$attr,
			esc_attr( $key ),
			$is_url ? esc_url( $value ) : esc_attr( $value )
		);
	}

	return $html . "<!-- End BlueWorx SEO -->\n";
}

/**
 * Prints the tags.
 *
 * WordPress prints a canonical link of its own for single pages. This prints
 * one for every page, so core's is taken out rather than left to double up.
 *
 * @return void
 */
function blueworx_seo_print_head() {
	if ( ! blueworx_seo_active() || is_admin() ) {
		return;
	}

	remove_action( 'wp_head', 'rel_canonical' );

	echo blueworx_seo_head_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup; every value in it is escaped where it is built.
}
add_action( 'wp_head', 'blueworx_seo_print_head', 1 );

/**
 * Clears cached pages, so a change shows straight away.
 *
 * @return void
 */
function blueworx_seo_refresh_cache() {
	if ( function_exists( 'blueworx_purge_site_cache' ) ) {
		blueworx_purge_site_cache( 'SEO' );
	}
}
add_action( 'update_option_blueworx_feature_seo', 'blueworx_seo_refresh_cache' );

/**
 * Saves the SEO screen.
 *
 * @return void
 */
function blueworx_seo_handle_save() {
	blueworx_require_post_request();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'blueworx-labs-wordpress' ) );
	}

	check_admin_referer( 'blueworx_save_seo' );

	blueworx_seo_save( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran above; the callee sanitizes every field.

	blueworx_seo_refresh_cache();

	set_transient( 'blueworx_seo_notice', '1', 30 );

	wp_safe_redirect( admin_url( 'admin.php?page=blueworx-seo' ) );
	exit;
}
add_action( 'admin_post_blueworx_save_seo', 'blueworx_seo_handle_save' );

/**
 * Renders the SEO screen.
 *
 * @return void
 */
function blueworx_render_seo_page() {
	$enabled = blueworx_feature_enabled( 'seo' );
	$plugin  = blueworx_seo_plugin();
	$live    = $enabled && '' === $plugin;

	$form = $live
		? sprintf(
			'<form method="post" action="%1$s"><input type="hidden" name="action" value="blueworx_save_seo" />%2$s',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'blueworx_save_seo', '_wpnonce', true, false )
		)
		: '';

	blueworx_open_admin_page(
		array(
			'title'   => __( 'SEO', 'blueworx-labs-wordpress' ),
			'lede'    => __( 'How the site appears in search results and when a page is shared. Each page uses its own title, excerpt and featured image first, then what is set here.', 'blueworx-labs-wordpress' ),
			'actions' => blueworx_ds_badge(
				$live ? __( 'Live', 'blueworx-labs-wordpress' ) : __( 'Not live', 'blueworx-labs-wordpress' ),
				$live ? 'success' : 'neutral',
				true
			),
			'form'    => $form,
		)
	);

	if ( ! $enabled ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone'    => 'info',
					'title'   => __( 'SEO is switched off', 'blueworx-labs-wordpress' ),
					'text'    => __( 'The theme adds no SEO tags while this is off, and this page is not listed in the menu. Switch it on under Enhancements to set it up.', 'blueworx-labs-wordpress' ),
					'actions' => blueworx_ds_button(
						array(
							'label' => __( 'Go to Enhancements', 'blueworx-labs-wordpress' ),
							'icon'  => 'arrow-right',
							'href'  => admin_url( 'admin.php?page=blueworx-labs-wordpress&section=content' ),
						)
					),
				)
			),
			blueworx_ds_allowed_html()
		);

		blueworx_close_admin_page();
		return;
	}

	if ( '' !== $plugin ) {
		$notice = array(
			'tone'  => 'info',
			/* translators: %s: an SEO plugin's name, e.g. "SureRank". */
			'title' => sprintf( __( '%s is handling SEO', 'blueworx-labs-wordpress' ), $plugin ),
			/* translators: %s: an SEO plugin's name, e.g. "SureRank". */
			'text'  => sprintf( __( 'The theme adds no SEO tags of its own while %s is active, so nothing is written twice. Set titles, descriptions and share images there.', 'blueworx-labs-wordpress' ), $plugin ),
		);

		if ( 'SureRank' === $plugin ) {
			$notice['actions'] = blueworx_ds_button(
				array(
					'label' => __( 'Open SureRank', 'blueworx-labs-wordpress' ),
					'icon'  => 'arrow-right',
					'href'  => admin_url( 'admin.php?page=surerank' ),
				)
			);
		}

		echo wp_kses( blueworx_ds_notice( $notice ), blueworx_ds_allowed_html() );

		blueworx_close_admin_page();
		return;
	}

	if ( get_transient( 'blueworx_seo_notice' ) ) {
		delete_transient( 'blueworx_seo_notice' );

		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'success',
					'text' => __( 'Settings saved.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	if ( ! get_option( 'blog_public' ) ) {
		echo wp_kses(
			blueworx_ds_notice(
				array(
					'tone' => 'warning',
					'text' => __( 'This site is set to discourage search engines, under Settings > Reading. It will stay out of search results until that is unticked.', 'blueworx-labs-wordpress' ),
				)
			),
			blueworx_ds_allowed_html()
		);
	}

	$settings = blueworx_seo_settings();

	$fields = blueworx_ds_field(
		array(
			'label'   => __( 'Site name', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_seo_site_name',
			'help'    => __( 'Shown on share cards beside the page title. Leave empty to use the site title.', 'blueworx-labs-wordpress' ),
			'control' => blueworx_ds_input(
				array(
					'name'  => 'blueworx_seo_site_name',
					'id'    => 'blueworx_seo_site_name',
					'value' => $settings['site_name'],
					'attrs' => array( 'placeholder' => get_bloginfo( 'name' ) ),
				)
			),
		)
	)
	. blueworx_ds_field(
		array(
			'label'   => __( 'Default description', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_seo_description',
			'help'    => __( 'Used for the home page and any page without an excerpt. One or two sentences, around 150 characters.', 'blueworx-labs-wordpress' ),
			'control' => blueworx_ds_textarea(
				array(
					'name'  => 'blueworx_seo_description',
					'id'    => 'blueworx_seo_description',
					'value' => $settings['description'],
					'rows'  => 3,
				)
			),
		)
	)
	. blueworx_ds_field(
		array(
			'label'   => __( 'Default share image', 'blueworx-labs-wordpress' ),
			'for'     => 'blueworx_seo_image',
			'control' => blueworx_design_media_control( 'blueworx_seo_image', 'blueworx_seo_image', $settings['image'], __( 'Used when a page has no featured image. 1200 by 630 pixels works everywhere.', 'blueworx-labs-wordpress' ) ),
		)
	);

	// Not wp_kses(): the media picker's buttons carry data attributes the
	// allow-list drops in silence, and every helper here escapes what it emits.
	echo blueworx_ds_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'title' => __( 'Site defaults', 'blueworx-labs-wordpress' ),
			'body'  => $fields,
		)
	);

	echo wp_kses(
		blueworx_ds_notice(
			array(
				'tone' => 'info',
				'text' => __( 'The theme writes the description, share card and canonical tags on every page. If an SEO plugin such as SureRank is switched on later, the theme steps back and lets it take over.', 'blueworx-labs-wordpress' ),
			)
		),
		blueworx_ds_allowed_html()
	);

	$savebar = '<div class="bw-savebar"><span class="bw-savebar__hint">'
		. esc_html__( 'Nothing changes until you save.', 'blueworx-labs-wordpress' )
		. '</span>'
		. blueworx_ds_button(
			array(
				'label'   => __( 'Save changes', 'blueworx-labs-wordpress' ),
				'variant' => 'primary',
				'type'    => 'submit',
			)
		)
		. '</div></form>';

	blueworx_close_admin_page( $savebar );
}
