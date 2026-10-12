<?php
/**
 * Template Name: Loopkit Full Width
 * Description: A full width template for Loopkit pages with theme header and footer.
 *
 * If the active theme already opens its own <main> in header.php (the Loopkit
 * companion theme declares this with add_theme_support( 'loopkit-theme' )),
 * a <div> is used instead so the page never has two nested <main id="primary">.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$loopkit_theme_has_main = current_theme_supports( 'loopkit-theme' );
$loopkit_wrapper_tag    = $loopkit_theme_has_main ? 'div' : 'main';
$loopkit_wrapper_id     = $loopkit_theme_has_main ? '' : ' id="primary"';

get_header();
?>
<<?php echo $loopkit_wrapper_tag . $loopkit_wrapper_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="site-main loopkit-full-width-template loopkit-template-full-width" style="width:100%;max-width:100%;margin:0;padding:0;">
<?php
while ( have_posts() ) :
    the_post();
    the_content();
endwhile;
?>
</<?php echo $loopkit_wrapper_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
<?php
get_footer();
