<?php
/**
 * Template Name: Loopkit Canvas
 * Description: A blank canvas template for Loopkit pages (no header, no footer).
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'loopkit-canvas-template loopkit-template-canvas' ); ?>>
<?php
wp_body_open();

while ( have_posts() ) :
    the_post();
    the_content();
endwhile;

wp_footer();
?>
</body>
</html>
