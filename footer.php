<?php
/**
 * The bottom of every public page: the site footer, then the document's end.
 *
 * See header.php: the footer is drawn by includes/frame.php only with Site
 * styles on.
 *
 * @package BlueWorxLabs
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main>
<?php blueworx_frame_footer(); ?>
<?php wp_footer(); ?>
</body>
</html>
