<?php
/**
 * Loopkit Archive template wrapper (classic themes).
 *
 * Loaded by Loopkit_Theme_Builder::apply_single_template() when a published Archive
 * template's Display Conditions match the current archive, blog index or search page.
 * Keeps the theme's header and footer and prints the Archive template in between.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// The Loopkit companion theme already opens <main id="primary"> in header.php.
$loopkit_theme_has_main = current_theme_supports( 'loopkit-theme' );
$loopkit_wrapper_tag    = $loopkit_theme_has_main ? 'div' : 'main';
$loopkit_wrapper_id     = $loopkit_theme_has_main ? '' : ' id="primary"';

get_header();
?>
<<?php echo $loopkit_wrapper_tag . $loopkit_wrapper_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="site-main loopkit-archive-template-wrap">
<?php
if ( class_exists( 'Loopkit_Templates' ) && method_exists( 'Loopkit_Templates', 'render_template' ) ) {
    echo Loopkit_Templates::render_template( 'archive' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
?>
</<?php echo $loopkit_wrapper_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
<?php
get_footer();
