<?php
/**
 * Run every Loopkit suite and report.
 *
 *   php tests/run.php                 everything
 *   php tests/run.php --php           the PHP suites only
 *   php tests/run.php --browser       the browser suites only
 *   php tests/run.php stage-e mascot  only suites whose name contains these
 *
 * Exits non-zero if anything failed, so it can gate a release.
 *
 * Twenty-two suites run by hand is twenty-two chances to skip the one that
 * would have caught the thing. Several of them also only passed in a
 * particular order, or on a clean database — the fixtures are built here
 * once, up front, so that cannot quietly decide the result.
 *
 * @package Loopkit
 */

// Command line only. These load WordPress and some of them delete things —
// one of them wipes the site to prove the wipe works. Reachable over the web,
// that is a URL that destroys a site.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "Loopkit tests run from the command line only.\n" );
}

$dir  = __DIR__;
$root = dirname( $dir );
$site = dirname( $root, 3 );
$wp   = $site . '/wp-load.php';

$args    = array_slice( $argv, 1 );
$only_php     = in_array( '--php', $args, true );
$only_browser = in_array( '--browser', $args, true );
$filters      = array_values( array_filter( $args, function ( $a ) {
	return 0 !== strpos( $a, '--' );
} ) );

$base = getenv( 'LK_TEST_URL' ) ? getenv( 'LK_TEST_URL' ) : 'http://127.0.0.1:8080';

echo "\n  Loopkit test run\n";
echo "  " . str_repeat( '─', 64 ) . "\n";

/* ---- the site has to be there before anything else ------------------- */

if ( ! file_exists( $wp ) ) {
	fwrite( STDERR, "  wp-load.php not found at {$wp}\n" );
	exit( 2 );
}

// Always seed, even for --php. The fixtures also switch the modules on,
// and the uninstall suite leaves them off — so a --php run straight after
// one used to fail on "class not found" with nothing to explain it.
$seed = array();
{
	$cmd = 'php ' . escapeshellarg( $dir . '/seed.php' ) . ' ' . escapeshellarg( $wp ) . ' 2>&1';
	exec( $cmd, $out, $code );
	if ( 3 === $code ) {
		// Exit 3 means it switched the modules on; they only take effect on
		// the next load, so run it again rather than stopping.
		echo "  modules were off; switched on\n";
		$out = array();
		exec( $cmd, $out, $code );
	}
	if ( 0 !== $code ) {
		fwrite( STDERR, "  Could not build the fixtures:\n    " . implode( "\n    ", $out ) . "\n" );
		exit( 2 );
	}
	foreach ( $out as $line ) {
		if ( preg_match( '/^([A-Z_]+)=(.*)$/', trim( $line ), $m ) ) {
			$seed[ $m[1] ] = $m[2];
		}
	}
	echo "  fixtures built\n";
}

if ( ! $only_php ) {
	$ch = @file_get_contents( $base . '/wp-login.php' );
	if ( false === $ch ) {
		fwrite( STDERR, "\n  No site answering at {$base}.\n"
			. "  Start one:  cd " . $site . " && php -S 127.0.0.1:8080\n"
			. "  Or set LK_TEST_URL, or run with --php to skip the browser suites.\n\n" );
		exit( 2 );
	}
	echo "  site responding at {$base}\n";
}
echo "  " . str_repeat( '─', 64 ) . "\n";

/* ---- what each browser suite needs on its command line --------------- */

$browser_args = array(
	'a11y'              => array( 'PAGE_ID' ),
	'canvas-handles'    => array( 'PAGE_ID' ),
	'canvas-events'     => array( 'PAGE_ID' ),
	'media-types'       => array( 'MEDIA_COLLECTION' ),
	'item-editor'       => array( 'ROWS_COLLECTION' ),
	'import-export'     => array( 'IMPORT_CSV', 'TEAM_COLLECTION' ),
);

/* ---- collect the suites ---------------------------------------------- */

$suites = array();
if ( ! $only_browser ) {
	foreach ( glob( $dir . '/php/*.php' ) as $file ) {
		$name = basename( $file, '.php' );
		if ( 'index' === $name || 0 === strpos( $name, '_' ) ) {
			continue; // the silence-is-golden guard, not a suite
		}
		$suites[] = array( 'php', $name, $file );
	}
}
if ( ! $only_php ) {
	foreach ( glob( $dir . '/browser/*.js' ) as $file ) {
		if ( 0 === strpos( basename( $file ), '_' ) ) {
			continue; // shared helpers, not suites
		}
		$suites[] = array( 'browser', basename( $file, '.js' ), $file );
	}
}
// The uninstall suite wipes the site to prove the wipe works. It restores
// itself afterwards, but anything running after it still starts from a
// rebuilt database — so it goes last by intent rather than by alphabet.
// PHP suites first, browser suites after — the PHP ones clear the database
// between sections, which used to leave the browser suites clicking at a
// site with no collections in it. The fixtures are rebuilt in between.
// uninstall goes last of all: it wipes the site to prove the wipe works.
usort( $suites, function ( $a, $b ) {
	$rank = function ( $s ) {
		if ( 'uninstall' === $s[1] ) { return 2; }
		return 'browser' === $s[0] ? 1 : 0;
	};
	return $rank( $a ) - $rank( $b ) ?: strcmp( $a[1], $b[1] );
} );

if ( $filters ) {
	$suites = array_values( array_filter( $suites, function ( $s ) use ( $filters ) {
		foreach ( $filters as $f ) {
			if ( false !== strpos( $s[1], $f ) ) {
				return true;
			}
		}
		return false;
	} ) );
}

/* ---- run them -------------------------------------------------------- */

$total_pass = 0;
$total_fail = 0;
$broken     = array();
$started    = microtime( true );

$reseeded = false;
foreach ( $suites as $suite ) {
	list( $kind, $name, $file ) = $suite;

	if ( 'browser' === $kind && ! $reseeded ) {
		$reseeded = true;
		$o = array();
		exec( 'php ' . escapeshellarg( $dir . '/seed.php' ) . ' ' . escapeshellarg( $wp ) . ' 2>&1', $o, $c2 );
		foreach ( $o as $line ) {
			if ( preg_match( '/^([A-Z_]+)=(.*)$/', trim( $line ), $m2 ) ) {
				$seed[ $m2[1] ] = $m2[2];
			}
		}
		echo "  " . str_repeat( '·', 64 ) . "\n  fixtures rebuilt for the browser suites\n";
	}

	$cmd = 'php ' . escapeshellarg( $file );
	if ( 'browser' === $kind ) {
		$cmd = 'node ' . escapeshellarg( $file );
		foreach ( ( $browser_args[ $name ] ?? array() ) as $key ) {
			$cmd .= ' ' . escapeshellarg( $seed[ $key ] ?? '' );
		}
	}

	$out = array();
	$t0  = microtime( true );
	exec( $cmd . ' 2>&1', $out, $code );
	$secs = microtime( true ) - $t0;
	$text = implode( "\n", $out );

	// Every suite ends with "=== something: N passed, M failed ==="
	$pass = 0;
	$fail = 0;
	if ( preg_match( '/(\d+)\s+passed,\s+(\d+)\s+failed/', $text, $m ) ) {
		$pass = (int) $m[1];
		$fail = (int) $m[2];
	} elseif ( preg_match( '/PARITY:\s*OK/', $text ) ) {
		$pass = 1; // the parity check reports differently
	} else {
		$broken[ $name ] = $text;
		printf( "  %-10s %-30s  %s\n", $kind, $name, 'NO RESULT' );
		continue;
	}

	$total_pass += $pass;
	$total_fail += $fail;
	if ( $fail ) {
		$broken[ $name ] = $text;
	}
	printf(
		"  %-10s %-30s  %3d passed  %s   %4.1fs\n",
		$kind,
		$name,
		$pass,
		$fail ? sprintf( '%d FAILED', $fail ) : '         ',
		$secs
	);
}

/* ---- what went wrong ------------------------------------------------- */

echo "  " . str_repeat( '─', 64 ) . "\n";
if ( $broken ) {
	echo "\n";
	foreach ( $broken as $name => $text ) {
		echo "  {$name}\n";
		foreach ( explode( "\n", $text ) as $line ) {
			if ( false !== strpos( $line, 'FAIL' ) || false !== stripos( $line, 'error' )
				|| false !== stripos( $line, 'fatal' ) ) {
				echo "    " . trim( $line ) . "\n";
			}
		}
		echo "\n";
	}
}

printf(
	"  %d passed, %d failed across %d suites in %.0fs\n\n",
	$total_pass,
	$total_fail,
	count( $suites ),
	microtime( true ) - $started
);

exit( ( $total_fail || $broken ) ? 1 : 0 );
