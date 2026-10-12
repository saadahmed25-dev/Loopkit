<?php
/** Collection List: video and audio bindings. */
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
require $lk_wp_load; global $wpdb;
$p=0;$f=0; function t($o,$m){global $p,$f; if($o){$p++;echo "  PASS: $m\n";}else{$f++;echo "  FAIL: $m\n";}}
foreach(Loopkit_Module_CMS::get_collections() as $c) Loopkit_Module_CMS::delete_collection((int)$c->id);
$cid=Loopkit_Module_CMS::create_collection('Reels','reels','dashicons-video-alt3');
Loopkit_Module_CMS::create_field($cid,'Title','title','text',array(),0);
Loopkit_Module_CMS::create_field($cid,'Cover','cover','image',array(),1);
Loopkit_Module_CMS::create_field($cid,'Logo','logo','svg',array(),2);
Loopkit_Module_CMS::create_field($cid,'Clip','clip','video',array(),3);
Loopkit_Module_CMS::create_field($cid,'Track','track','audio',array(),4);
Loopkit_Module_CMS::create_item($cid,array('title'=>'One','cover'=>'https://e.test/c.jpg',
  'clip'=>'https://e.test/v.mp4','track'=>'https://e.test/a.mp3'));

$w = new Loopkit_Widget_Collection_List();
$r = new ReflectionClass($w);
$fo = $r->getMethod('field_options'); $fo->setAccessible(true);
$ft = $r->getMethod('field_type');    $ft->setAccessible(true);

echo "=== 1. Each slot offers only what it can show ===\n";
$pics = $fo->invoke($w, array('image','svg'));
$play = $fo->invoke($w, array('video','audio'));
$all  = $fo->invoke($w);
t(isset($pics['cover']) && isset($pics['logo']),'1a. The picture slot offers image and SVG');
t(!isset($pics['clip']) && !isset($pics['track']),'1b. And not video or audio — this was the bug');
t(isset($play['clip']) && isset($play['track']),'1c. The player slot offers video and audio');
t(!isset($play['cover']),'1d. And not the photo');
t(count($all)>count($pics),'1e. An unfiltered list still returns everything');

echo "=== 1b. The controls are actually wired to those lists ===\n";
// Testing field_options() alone passed while the Image control was still
// bound to the unfiltered list — the half the user sees. Read the controls.
$rc = $r->getMethod('register_controls'); $rc->setAccessible(true);
$ctrls = $rc->invoke($w);
$find = function($name) use ($ctrls) {
  foreach ($ctrls as $tab) { foreach ($tab as $sec) {
    foreach (($sec['controls'] ?? array()) as $c) { if (($c['name'] ?? '') === $name) return $c; }
  }}
  return null;
};
$imgc = $find('cl_image_field');
$medc = $find('cl_media_field');
t($imgc && !isset($imgc['options']['clip']) && !isset($imgc['options']['track']),
  '1f. The Image control itself offers no video or audio');
t($imgc && isset($imgc['options']['cover']),'1g. It does offer the image field');
t($medc && isset($medc['options']['clip']) && isset($medc['options']['track']),
  '1h. The media control offers both players');
t($medc && !isset($medc['options']['cover']),'1i. And not the photo');
t($find('cl_media_controls') !== null,'1j. The controls switch is registered');

echo "=== 2. Knowing which is which ===\n";
t($ft->invoke($w,'clip')==='video','2a. A clip is a video');
t($ft->invoke($w,'track')==='audio','2b. A track is audio');
t($ft->invoke($w,'nope')==='','2c. An unknown key is nothing, not a guess');

echo "=== 3. What a card renders ===\n";
$render = $r->getMethod('render'); $render->setAccessible(true);
$base = array('cl_collection'=>$cid,'cl_count'=>5,'cl_title_field'=>'title');

$h = $render->invoke($w, $base + array('cl_media_field'=>'clip', false), array(), false);
t(strpos($h,'<video')!==false,'3a. A video field renders a <video>');
t(strpos($h,'<img')===false,'3b. Not a broken <img> — the bug this fixes');
t(strpos($h,'preload="none"')!==false,'3c. It does not download before anyone presses play');
t(strpos($h,'playsinline')!==false,'3d. And will not hijack the screen on a phone');

$h2 = $render->invoke($w, $base + array('cl_media_field'=>'track', false), array(), false);
t(strpos($h2,'<audio')!==false,'3e. An audio field renders an <audio>');
t(strpos($h2,'<video')===false,'3f. Not a video element');

$h3 = $render->invoke($w, $base + array('cl_media_field'=>'clip','cl_image_field'=>'cover', false), array(), false);
t(strpos($h3,'poster="https://e.test/c.jpg"')!==false,'3g. With an image bound too, it becomes the poster');

$h4 = $render->invoke($w, $base + array('cl_media_field'=>'clip','cl_media_controls'=>'no', false), array(), false);
t(strpos($h4,' controls')===false,'3h. Controls can be turned off');
t(strpos($render->invoke($w, $base+array('cl_media_field'=>'clip', false), array(), false),' controls')!==false,'3i. And are on by default', array(), false);

$h5 = $render->invoke($w, $base + array('cl_image_field'=>'cover', false), array(), false);
t(strpos($h5,'<img')!==false && strpos($h5,'<video')===false,'3j. An image-only card is unchanged');

echo "=== 4. Both CSS generators agree ===\n";
$php = file_get_contents(WP_PLUGIN_DIR.'/loopkit/includes/class-loopkit-rest-api.php');
$js  = file_get_contents(WP_PLUGIN_DIR.'/loopkit/editor/js/loopkit-css.js');
t(substr_count($php,'.lk-cl-video video')===substr_count($js,'.lk-cl-video video'),
  '4a. The video rules match in both ('.substr_count($php,'.lk-cl-video video').')');
$css = file_get_contents(WP_PLUGIN_DIR.'/loopkit/assets/css/loopkit-frontend.css');
t(strpos($css,'.lk-cl-video video')!==false && strpos($css,'.lk-cl-audio audio')!==false,
  '4b. Both wrappers have base styling');
foreach(Loopkit_Module_CMS::get_collections() as $c) Loopkit_Module_CMS::delete_collection((int)$c->id);
echo "\n=== Collection List media: $p passed, $f failed ===\n";
