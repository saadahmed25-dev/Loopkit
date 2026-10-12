<?php
/**
 * Translations — the plugin names a text domain, so it has to honour it.
 */
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "Loopkit tests run from the command line only.\n" );
}

$lk_wp_load = dirname( __DIR__, 5 ) . '/wp-load.php';
require $lk_wp_load;

$p = 0; $f = 0;
function t( $o, $m ) { global $p, $f; if ( $o ) { $p++; echo "  PASS: $m\n"; } else { $f++; echo "  FAIL: $m\n"; } }

$dir = WP_PLUGIN_DIR . '/loopkit/';

echo "=== 1. The plumbing exists ===\n";
$main = file_get_contents( $dir . 'loopkit.php' );
t( false !== strpos( $main, 'load_plugin_textdomain' ),
  '1a. Something actually loads a translation file' );
t( false !== strpos( $main, "add_action( 'init', 'loopkit_load_textdomain'" ),
  '1b. On init — earlier than that raises a notice since WP 6.7' );
t( file_exists( $dir . 'languages/loopkit.pot' ),
  '1c. There is a template for translators' );
t( false !== strpos( $main, 'Text Domain:       loopkit' ),
  '1d. The header names the same domain the code uses' );

echo "=== 2. The template is complete enough to be useful ===\n";
$pot = file_get_contents( $dir . 'languages/loopkit.pot' );
$count = substr_count( $pot, "\nmsgid " );
t( $count > 1500, '2a. It carries the strings (' . $count . ')' );
foreach ( array( 'Collections', 'Add New Collection', 'Import / Export', 'Quick view' ) as $s ) {
	t( false !== strpos( $pot, '"' . $s . '"' ), '2b. Including "' . $s . '"' );
}

echo "=== 3. A translation actually reaches the screen ===\n";
// Build a throwaway .mo rather than depending on one being there. The suite
// failed the moment the demo file was tidied away, which means it was
// testing my machine, not the plugin.
function lk_write_mo( $path, array $pairs ) {
	// A .mo must carry a metadata entry under an empty msgid. Without it
	// WordPress' parser reads past the end of the file and the translation
	// silently does not apply — it loads, and then does nothing.
	$pairs = array( '' => "Content-Type: text/plain; charset=UTF-8\n" ) + $pairs;
	$keys = array_keys( $pairs );
	sort( $keys );
	$ids = ''; $strs = ''; $io = array(); $so = array();
	foreach ( $keys as $k ) { $io[] = array( strlen( $ids ), strlen( $k ) ); $ids .= $k . "\0"; }
	foreach ( $keys as $k ) { $v = $pairs[ $k ]; $so[] = array( strlen( $strs ), strlen( $v ) ); $strs .= $v . "\0"; }
	$n = count( $keys );
	$oTable = 28; $tTable = $oTable + $n * 8;
	$idsAt = $tTable + $n * 8; $strsAt = $idsAt + strlen( $ids );
	$out = pack( 'V', 0x950412de ) . pack( 'V', 0 ) . pack( 'V', $n )
		. pack( 'V', $oTable ) . pack( 'V', $tTable ) . pack( 'V', 0 ) . pack( 'V', 0 );
	foreach ( $io as $o ) { $out .= pack( 'V', $o[1] ) . pack( 'V', $idsAt + $o[0] ); }
	foreach ( $so as $o ) { $out .= pack( 'V', $o[1] ) . pack( 'V', $strsAt + $o[0] ); }
	file_put_contents( $path, $out . $ids . $strs );
}
$lk_mo = $dir . 'languages/loopkit-ur.mo';
$lk_had_mo = file_exists( $lk_mo );
if ( ! $lk_had_mo ) {
	lk_write_mo( $lk_mo, array( 'Collections' => 'مجموعے' ) );
}
add_filter( 'locale', function () { return 'ur'; } );
add_filter( 'plugin_locale', function () { return 'ur'; } );
// The plugin's own init already loaded the domain for the English locale
// during wp-load, and a second load on top of that is a no-op. Unload first.
unload_textdomain( 'loopkit' );
$loaded = load_plugin_textdomain( 'loopkit', false, 'loopkit/languages' );
t( $loaded, '3a. The loader finds the file' );
t( 'Collections' !== __( 'Collections', 'loopkit' ), '3b. And the string comes back translated' );
t( 'مجموعے' === __( 'Collections', 'loopkit' ), '3c. Correctly: ' . __( 'Collections', 'loopkit' ) );
t( 'Fields' === __( 'Fields', 'loopkit' ),
  '3d. An untranslated string falls back to English rather than going blank' );

if ( ! $lk_had_mo ) {
	unlink( $lk_mo ); // leave the folder as it was found
}

echo "=== 4. Nothing user-facing is left hardcoded in the JS ===\n";
$js = '';
foreach ( glob( $dir . 'editor/js/*.js' ) as $file ) { $js .= file_get_contents( $file ); }
$alerts = preg_match_all( "/(?:alert|confirm)\(\s*'[A-Z][^']{10,}'/", $js );
t( 0 === $alerts, '4a. No hardcoded alert or confirm text (' . $alerts . ')' );

echo "\n=== i18n: $p passed, $f failed ===\n";
