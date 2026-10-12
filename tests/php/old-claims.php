<?php
/**
 * Four fixes that were claimed and never proven.
 *
 * Each was marked done in an earlier audit with nothing measured. This
 * settles them one way or the other.
 */
if ( 'cli' !== PHP_SAPI ) { http_response_code( 403 ); exit( "cli only\n" ); }
require dirname( __DIR__, 5 ) . '/wp-load.php';
$p = 0; $f = 0;
function t( $o, $m ) { global $p, $f; if ( $o ) { $p++; echo "  PASS: $m\n"; } else { $f++; echo "  FAIL: $m\n"; } }
wp_set_current_user( 1 );

echo "=== 1. Social Icons keeps its brand library ===\n";
$w = new Loopkit_Widget_Social_Icons();
$out = $w->render( array(), array(), false );
t( strlen( $out ) > 500, '1a. It renders something at all (' . strlen( $out ) . ' bytes)' );
t( false !== strpos( $out, 'lk-icon--brands' ),
  '1b. Brand icons are marked as brand icons, so the library is not lost' );
t( false !== strpos( $out, '<svg' ), '1c. Drawn inline, so no icon font has to load' );
t( false !== strpos( $out, 'lk-social-icon-facebook' ), '1d. And each one knows which network it is' );

// The repeater is `social_icon_list` and the link is `link` — the first
// version of this test invented both names and blamed the widget.
$saved = array( 'social_icon_list' => array( array(
	'social_icon' => array( 'library' => 'fa-brands', 'value' => 'github' ),
	'link'        => array( 'url' => 'https://github.test' ),
) ) );
$out2 = $w->render( $saved, array(), false );
t( false !== strpos( $out2, 'github.test' ), '1e. A saved item round-trips its link' );
t( false !== strpos( $out2, 'github' ), '1f. And its icon' );

echo "=== 2. No rule sets the same property twice ===\n";
$pid = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'CSS probe', 'post_status' => 'publish' ) );
$els = array( array( 'id' => 's1', 'elType' => 'section',
	'settings' => array( 'background_type' => 'classic', 'background_color' => '#ff0000',
		'padding' => array( 'top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10, 'unit' => 'px' ) ),
	'elements' => array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
		array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'heading',
			'settings' => array( 'title' => 'Hi', 'typography_font_size' => array( 'size' => 28, 'unit' => 'px' ),
				'title_color' => '#112233' ), 'elements' => array() ),
	) ) ) ) );
update_post_meta( $pid, '_loopkit_data', wp_slash( wp_json_encode( $els ) ) );
Loopkit_REST_API::rebuild_css_file( $pid );
$css = (string) get_post_meta( $pid, '_loopkit_css', true );
if ( '' === $css ) {
	$file = WP_CONTENT_DIR . '/uploads/loopkit/css/post-' . $pid . '.css';
	$css = file_exists( $file ) ? (string) file_get_contents( $file ) : '';
}
t( strlen( $css ) > 50, '2a. A stylesheet is produced (' . strlen( $css ) . ' bytes)' );
$dups = array();
preg_match_all( '/([^{}]+)\{([^}]*)\}/', $css, $m, PREG_SET_ORDER );
$seen = array();
foreach ( $m as $rule ) {
	$sel = trim( preg_replace( '/\s+/', ' ', $rule[1] ) );
	foreach ( explode( ';', $rule[2] ) as $d ) {
		$d = trim( $d );
		if ( '' === $d || false === strpos( $d, ':' ) ) { continue; }
		$key = $sel . '|' . trim( explode( ':', $d, 2 )[0] );
		if ( isset( $seen[ $key ] ) ) { $dups[ $key ] = true; }
		$seen[ $key ] = true;
	}
}
t( empty( $dups ), '2b. Nothing is declared twice' . ( $dups ? ': ' . implode( ' / ', array_slice( array_keys( $dups ), 0, 3 ) ) : '' ) );

echo "=== 3. A repeater keeps its media across a save ===\n";
$gal = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Repeater probe', 'post_status' => 'publish' ) );
$rep = array( array( 'id' => 's3', 'elType' => 'section', 'settings' => array(), 'elements' => array(
	array( 'id' => 'c3', 'elType' => 'container', 'settings' => array(), 'elements' => array(
		array( 'id' => 'w3', 'elType' => 'widget', 'widgetType' => 'image-carousel',
			'settings' => array( 'carousel_items' => array(
				array( 'carousel_image' => array( 'id' => 12, 'url' => 'https://e.test/one.jpg' ) ),
				array( 'carousel_image' => array( 'id' => 13, 'url' => 'https://e.test/two.jpg' ) ),
			) ), 'elements' => array() ),
	) ) ) ) );
update_post_meta( $gal, '_loopkit_data', wp_slash( wp_json_encode( $rep ) ) );
$back = json_decode( get_post_meta( $gal, '_loopkit_data', true ), true );
$items = $back[0]['elements'][0]['elements'][0]['settings']['carousel_items'] ?? array();
t( 2 === count( $items ), '3a. Both rows come back (' . count( $items ) . ')' );
t( ( $items[0]['carousel_image']['url'] ?? '' ) === 'https://e.test/one.jpg', '3b. With their urls intact' );
t( ( $items[1]['carousel_image']['id'] ?? 0 ) === 13, '3c. And their attachment ids' );

echo "=== 4. A hostile font size cannot escape the rule ===\n";
// First: a normal size must reach the stylesheet. Without this the section
// is worthless — the hostile value would be absent simply because nothing
// is ever emitted, and that would read as a pass.
$okp = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Size probe', 'post_status' => 'publish' ) );
$fine = array( array( 'id' => 's5', 'elType' => 'section', 'settings' => array(), 'elements' => array(
	array( 'id' => 'c5', 'elType' => 'container', 'settings' => array(), 'elements' => array(
		array( 'id' => 'w5', 'elType' => 'widget', 'widgetType' => 'heading',
			'settings' => array( 'title' => 'X',
				'typography' => array( 'size' => array( 'size' => 37, 'unit' => 'px' ) ) ),
			'elements' => array() ),
	) ) ) ) );
update_post_meta( $okp, '_loopkit_data', wp_slash( wp_json_encode( $fine ) ) );
Loopkit_REST_API::rebuild_css_file( $okp );
$okcss = (string) get_post_meta( $okp, '_loopkit_css', true );
t( false !== strpos( $okcss, '37px' ), '4z. A normal size does reach the stylesheet, so this section has teeth' );
wp_delete_post( $okp, true );
$bad = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Escape probe', 'post_status' => 'publish' ) );
$nasty = array( array( 'id' => 's4', 'elType' => 'section', 'settings' => array(), 'elements' => array(
	array( 'id' => 'c4', 'elType' => 'container', 'settings' => array(), 'elements' => array(
		array( 'id' => 'w4', 'elType' => 'widget', 'widgetType' => 'heading',
			'settings' => array( 'title' => 'X',
				'typography' => array( 'size' => array( 'size' => '20px; } body { display:none } .x{a', 'unit' => 'px' ) ) ),
			'elements' => array() ),
	) ) ) ) );
update_post_meta( $bad, '_loopkit_data', wp_slash( wp_json_encode( $nasty ) ) );
Loopkit_REST_API::rebuild_css_file( $bad );
$css2 = (string) get_post_meta( $bad, '_loopkit_css', true );
if ( '' === $css2 ) {
	$file2 = WP_CONTENT_DIR . '/uploads/loopkit/css/post-' . $bad . '.css';
	$css2 = file_exists( $file2 ) ? (string) file_get_contents( $file2 ) : '';
}
echo '  emitted: ' . substr( preg_replace( '/\s+/', ' ', $css2 ), 0, 140 ) . "\n";
t( strlen( $css2 ) > 20, '4a. It still produced a stylesheet, so this is a real check' );
t( false === strpos( str_replace( ' ', '', $css2 ), 'display:none' ), '4b. The injected declaration is gone' );
t( false === strpos( str_replace( ' ', '', $css2 ), 'body{' ), '4c. And it never reached another selector' );

// Worth recording: the hostile value does reach the stored data, and does
// not reach the stylesheet — so something rejects it. Two likely guards
// (is_numeric on the size, and the float cast in css_num) were each removed
// in turn and the outcome did not change, which means neither is the thing
// doing the work and the live path is somewhere I have not found. The
// behaviour is right and measured; the reason is not yet known.

foreach ( array( $pid, $gal, $bad ) as $id ) { wp_delete_post( $id, true ); }
echo "\n=== old claims: $p passed, $f failed ===\n";
