<?php
/**
 * The Product Grid can render each card through a Loop Item template.
 */
if ( 'cli' !== PHP_SAPI ) { http_response_code( 403 ); exit( "cli only\n" ); }
require dirname( __DIR__, 5 ) . '/wp-load.php';

$lk_mods = get_option( 'loopkit_modules', array() );
if ( ! is_array( $lk_mods ) || empty( $lk_mods['woocommerce'] ) ) {
	$lk_mods = is_array( $lk_mods ) ? $lk_mods : array();
	$lk_mods['woocommerce'] = 1;
	update_option( 'loopkit_modules', $lk_mods );
	echo "  (shop module was off; switched on — re-run)\n\n=== product card template: 0 passed, 1 failed ===\n";
	exit( 1 );
}

$p = 0; $f = 0;
function t( $o, $m ) { global $p, $f; if ( $o ) { $p++; echo "  PASS: $m\n"; } else { $f++; echo "  FAIL: $m\n"; } }

wp_set_current_user( 1 );

// Two products and a Loop Item template that prints the title.
$a = wp_insert_post( array( 'post_type' => 'loopkit_product', 'post_title' => 'Blue Mug', 'post_status' => 'publish' ) );
$b = wp_insert_post( array( 'post_type' => 'loopkit_product', 'post_title' => 'Red Mug', 'post_status' => 'publish' ) );
foreach ( array( $a, $b ) as $id ) { update_post_meta( $id, '_loopkit_regular_price', '10' ); }

$tpl = wp_insert_post( array( 'post_type' => 'loopkit_template', 'post_title' => 'Card', 'post_status' => 'publish' ) );
update_post_meta( $tpl, '_loopkit_template_type', 'loop-item' );
$elements = array( array( 'id' => 's1', 'elType' => 'section', 'settings' => array(), 'elements' => array(
	array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
		array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'post-title', 'settings' => array(), 'elements' => array() ),
	) ),
) ) );
update_post_meta( $tpl, '_loopkit_data', wp_slash( wp_json_encode( $elements ) ) );
update_post_meta( $tpl, '_loopkit_css', '.lk-loop-item{border:1px solid red}' );

$w = new Loopkit_Widget_Product_Grid();
$base = array( 'pgrid_source' => 'custom', 'pgrid_count' => 10 );

echo "=== 1. Without a template, nothing changes ===\n";
// render() returns its markup; it does not echo. Wrapping it in ob_start
// hid an open buffer the templated path was leaking.
$plain = $w->render( $base, array(), false );
t( false !== strpos( $plain, 'lk-product-card' ), '1a. The built-in card still renders' );
t( false === strpos( $plain, 'lk-loop-item' ), '1b. And no template markup leaks in' );

echo "=== 2. With one, the card is the template ===\n";
$out = $w->render( $base + array( 'pgrid_template' => $tpl ), array(), false );
t( false !== strpos( $out, 'lk-loop-item' ), '2a. Each product is wrapped as a loop item' );
t( false !== strpos( $out, 'loopkit-' . $tpl ), '2b. Tagged with the template, so its CSS applies' );
t( false === strpos( $out, 'lk-product-card' ), '2c. The built-in card is not also rendered' );
t( substr_count( $out, 'lk-loop-item' ) >= 2, '2d. One per product (' . substr_count( $out, 'lk-loop-item' ) . ')' );

echo "=== 3. The template sees each product ===\n";
t( false !== strpos( $out, 'Blue Mug' ) && false !== strpos( $out, 'Red Mug' ),
  '3a. Post Title resolves per product, not once' );
t( false !== strpos( $out, 'post-' . $a ), '3b. Each item carries its own product id' );

echo "=== 4. Its CSS goes out, once ===\n";
t( 1 === substr_count( $out, 'loopkit-loop-css-' . $tpl ), '4a. The template CSS is printed exactly once' );
$second = $w->render( $base + array( 'pgrid_template' => $tpl ), array(), false );
t( false === strpos( $second, 'loopkit-loop-css-' . $tpl ), '4b. A second grid on the page does not repeat it' );

echo "=== 5. It refuses what is not a Loop Item ===\n";
$wrong = wp_insert_post( array( 'post_type' => 'loopkit_template', 'post_title' => 'Header', 'post_status' => 'publish' ) );
update_post_meta( $wrong, '_loopkit_template_type', 'header' );
// Give it real content, or it falls through on being empty and the type
// check is never the thing doing the work.
update_post_meta( $wrong, '_loopkit_data', wp_slash( wp_json_encode( $elements ) ) );
$o5 = $w->render( $base + array( 'pgrid_template' => $wrong ), array(), false );
t( false !== strpos( $o5, 'lk-product-card' ), '5a. A header template is ignored, built-in card used' );
$o6 = $w->render( $base + array( 'pgrid_template' => 999999 ), array(), false );
t( false !== strpos( $o6, 'lk-product-card' ), '5b. So is a template that does not exist' );

echo "=== 6. The control offers the right templates ===\n";
$r = new ReflectionMethod( 'Loopkit_Widget_Product_Grid', 'loop_item_options' );
$r->setAccessible( true );
$opts = $r->invoke( null );
t( isset( $opts[ (string) $tpl ] ), '6a. Loop Item templates are listed' );
t( ! isset( $opts[ (string) $wrong ] ), '6b. Header templates are not' );
t( isset( $opts[''] ), '6c. And the built-in card stays an option' );

foreach ( array( $a, $b, $tpl, $wrong ) as $id ) { wp_delete_post( $id, true ); }
echo "\n=== product card template: $p passed, $f failed ===\n";
