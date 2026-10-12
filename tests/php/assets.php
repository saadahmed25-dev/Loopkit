<?php
/** Minified assets: never silently serve the old copy. */
// Command line only. These load WordPress and some of them delete things —
// one of them wipes the site to prove the wipe works. Reachable over the web,
// that is a URL that destroys a site.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "Loopkit tests run from the command line only.\n" );
}

// Find WordPress from this file, not from wherever the suite was started.
$lk_wp_load = dirname( __DIR__, 5 ) . '/wp-load.php';
if ( ! file_exists( $lk_wp_load ) ) { $lk_wp_load = getenv( 'LK_WP_LOAD' ); }
require $lk_wp_load;
$p=0;$f=0; function t($o,$m){global $p,$f; if($o){$p++;echo "  PASS: $m\n";}else{$f++;echo "  FAIL: $m\n";}}
$dir = WP_PLUGIN_DIR.'/loopkit/';
$src = 'assets/css/loopkit-frontend.css';
$min = 'assets/css/loopkit-frontend.min.css';

$adv = get_option('loopkit_advanced_settings',array()); $adv['optimize_assets']='yes';
update_option('loopkit_advanced_settings',$adv);

echo "=== 1. With a current .min file ===\n";
touch($dir.$min, time()); touch($dir.$src, time()-60); clearstatcache();
t(strpos(Loopkit_Assets::get_asset_url($src),'.min.css')!==false,'1a. The small file is served');
t(strpos(Loopkit_Assets::get_asset_path($src),'.min.css')!==false,'1b. And resolved on disk');

echo "=== 2. When the source is newer — the bug this guards ===\n";
// Clearly newer, not the same second: a rebuild always writes the .min after
// the source, so equal timestamps mean "just built", not "stale".
touch($dir.$min, time()-60);
touch($dir.$src, time());
clearstatcache();
t(strpos(Loopkit_Assets::get_asset_url($src),'.min.css')===false,
  '2a. The source is served instead, not yesterday\'s code');
t(strpos(Loopkit_Assets::get_asset_url($src),'loopkit-frontend.css')!==false,'2b. And it is the right file');

echo "=== 3. Optimisation off ===\n";
touch($dir.$min, time()); clearstatcache();
$adv['optimize_assets']='no'; update_option('loopkit_advanced_settings',$adv);
t(strpos(Loopkit_Assets::get_asset_url($src),'.min.css')===false,'3a. Never the minified copy');
$adv['optimize_assets']='yes'; update_option('loopkit_advanced_settings',$adv);

echo "=== 4. A file with no minified twin ===\n";
t(strpos(Loopkit_Assets::get_asset_url('editor/css/loopkit-editor.css'),'loopkit-editor.css')!==false,
  '4a. Served as-is, no guessing');

echo "=== 5. Every shipped .min file is current and lossless ===\n";
exec('cd '.escapeshellarg($dir).' && php bin/build-assets.php --check 2>&1', $out, $code);
t(0===$code,'5a. Nothing is out of date ('.trim(implode(' ',$out)).')');
$braces = function($file){ return substr_count(file_get_contents($file),'{'); };
$pairs = glob($dir.'assets/css/*.min.css');
$bad = array();
foreach($pairs as $m){
  $s = str_replace('.min.css','.css',$m);
  if (file_exists($s) && $braces($s) !== $braces($m)) { $bad[] = basename($m); }
}
t(!$bad,'5b. No stylesheet lost a rule in minification'.($bad?' ('.implode(', ',$bad).')':''));

// Leave the pair in the state a release expects: min newer than source.
touch($dir.$src, time()-5); touch($dir.$min, time()); clearstatcache();
echo "\n=== assets: $p passed, $f failed ===\n";
