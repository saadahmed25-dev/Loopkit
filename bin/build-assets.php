<?php
/**
 * Rebuild every minified asset.
 *
 *   php bin/build-assets.php          rebuild what is out of date
 *   php bin/build-assets.php --check  report staleness and exit 1 if any
 *   php bin/build-assets.php --all    rebuild everything regardless
 *
 * --check is the one to put in front of a release. Editing a source file and
 * forgetting its .min twin is silent: the site loads the old file and nothing
 * looks wrong. That has already happened once.
 *
 * @package Loopkit
 */

$root = dirname( __DIR__ );
$args = array_slice( $argv, 1 );
$check = in_array( '--check', $args, true );
$all   = in_array( '--all', $args, true );

/**
 * Minify CSS: comments out, whitespace collapsed, last semicolons dropped.
 *
 * Kept deliberately conservative. A clever minifier that breaks one rule in
 * a 76KB stylesheet costs more than the bytes it saves.
 *
 * @param string $css Source.
 * @return string
 */
function lk_min_css( $css ) {
	// Comments out. The delimiter is ~ because the pattern is full of slashes.
	$css = preg_replace( '~/\*.*?\*/~s', '', $css );
	$css = preg_replace( '/\s*([{}:;,>~+])\s*/', '$1', $css );
	$css = preg_replace( '/;\}/', '}', $css );
	$css = preg_replace( '/\s+/', ' ', $css );
	return trim( $css );
}

/**
 * Minify JS: line comments and indentation only.
 *
 * Nothing that rewrites the code. Without a real parser, anything more
 * ambitious eventually eats a regex literal or a template string, and the
 * failure shows up as a blank editor rather than an error.
 *
 * @param string $js Source.
 * @return string
 */
function lk_min_js( $js ) {
	$out   = array();
	$lines = preg_split( '/\r\n|\r|\n/', $js );
	foreach ( $lines as $line ) {
		$trimmed = trim( $line );
		if ( '' === $trimmed ) {
			continue;
		}
		// Only a line that is nothing but a comment. A // inside a string or
		// a URL is left alone, which is the whole reason for being this dull.
		if ( 0 === strpos( $trimmed, '//' ) ) {
			continue;
		}
		$out[] = $trimmed;
	}
	return implode( "\n", $out );
}

$pairs = array();
$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	$path = $file->getPathname();
	if ( false !== strpos( $path, '/node_modules/' ) || false !== strpos( $path, '/.git/' ) ) {
		continue;
	}
	if ( ! preg_match( '/\.min\.(css|js)$/', $path, $m ) ) {
		continue;
	}
	$src = preg_replace( '/\.min\.' . $m[1] . '$/', '.' . $m[1], $path );
	if ( file_exists( $src ) ) {
		$pairs[ $src ] = array( $path, $m[1] );
	}
}
ksort( $pairs );

$stale = array();
$built = 0;

foreach ( $pairs as $src => $info ) {
	list( $min, $ext ) = $info;
	$rel     = str_replace( $root . '/', '', $min );
	$is_old  = filemtime( $src ) > filemtime( $min );

	if ( $check ) {
		if ( $is_old ) {
			$stale[] = $rel;
		}
		continue;
	}
	if ( ! $all && ! $is_old ) {
		continue;
	}

	$source = file_get_contents( $src );
	$out    = ( 'css' === $ext ) ? lk_min_css( $source ) : lk_min_js( $source );

	// A minifier that produces more than the source, or almost nothing, has
	// gone wrong. Leave the old file rather than ship the result.
	$len = strlen( $out );
	if ( $len < 1 || $len > strlen( $source ) ) {
		echo "  SKIPPED (output looks wrong): {$rel}\n";
		continue;
	}
	file_put_contents( $min, $out );
	printf( "  built %-52s %6d -> %6d\n", $rel, strlen( $source ), $len );
	$built++;
}

if ( $check ) {
	if ( $stale ) {
		echo "Out of date — the site is serving the old copy of:\n";
		foreach ( $stale as $s ) {
			echo "  {$s}\n";
		}
		echo "\nRun: php bin/build-assets.php\n";
		exit( 1 );
	}
	echo "All " . count( $pairs ) . " minified assets are current.\n";
	exit( 0 );
}

echo $built ? "\nRebuilt {$built} file(s).\n" : "Nothing to rebuild.\n";
