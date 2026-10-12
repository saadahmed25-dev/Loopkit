<?php
/** uninstall.php — does deleting the plugin actually clean up? */
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

function seed(){
  global $wpdb;
  // Build the CMS tables with plain SQL rather than through the module.
  // A previous uninstall removes the option that loads the module, so the
  // class is gone on the second run — the test must not depend on it.
  $pfx = $wpdb->prefix;
  foreach ( array('lk_cms_collections','lk_cms_fields','lk_cms_items','loopkit_submissions') as $tbl ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$pfx}{$tbl}" );
    $wpdb->query( "CREATE TABLE {$pfx}{$tbl} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, payload longtext, PRIMARY KEY (id))" );
    $wpdb->query( "INSERT INTO {$pfx}{$tbl} (payload) VALUES ('x')" );
  }
  $tpl=wp_insert_post(array('post_type'=>'loopkit_template','post_title'=>'T','post_status'=>'publish'));
  update_post_meta($tpl,'_loopkit_template_type','cms-item');
  $prod=wp_insert_post(array('post_type'=>'loopkit_product','post_title'=>'P','post_status'=>'publish'));
  $ord=wp_insert_post(array('post_type'=>'loopkit_order','post_title'=>'O','post_status'=>'publish'));
  update_post_meta($ord,'_lk_total','99');
  $page=wp_insert_post(array('post_type'=>'page','post_title'=>'Built page','post_status'=>'publish'));
  update_post_meta($page,'_loopkit_data','[]');
  update_post_meta($page,'_loopkit_css','body{}');
  update_user_meta(1,'_loopkit_cart','x');
  update_option('loopkit_global_css','body{}');
  update_option('loopkit_mascot','1');
  set_transient('loopkit_rate_test','x',300);
  $up=wp_upload_dir();
  wp_mkdir_p($up['basedir'].'/loopkit/css'); file_put_contents($up['basedir'].'/loopkit/css/post-1.css','a{}');
  wp_mkdir_p($up['basedir'].'/loopkit-import'); file_put_contents($up['basedir'].'/loopkit-import/x.csv','a');
  return array('page'=>$page,'tpl'=>$tpl,'ord'=>$ord,'prod'=>$prod);
}
function counts(){
  global $wpdb; $up=wp_upload_dir();
  return array(
   'tables'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('{$wpdb->prefix}lk_cms_items','{$wpdb->prefix}lk_cms_fields','{$wpdb->prefix}lk_cms_collections','{$wpdb->prefix}loopkit_submissions')"),
   'posts'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type LIKE 'loopkit\_%'"),
   'postmeta'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_loopkit\_%' OR meta_key LIKE '\_lk\_%'"),
   'usermeta'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE '\_loopkit\_%'"),
   'options'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'loopkit\_%' OR option_name LIKE '\_transient\_loopkit\_%'"),
   'files'=>(is_dir($up['basedir'].'/loopkit')?1:0)+(is_dir($up['basedir'].'/loopkit-import')?1:0),
  );
}
function run_uninstall(){
  define('WP_UNINSTALL_PLUGIN', 'loopkit/loopkit.php');
  include WP_PLUGIN_DIR.'/loopkit/uninstall.php';
}

echo "=== 1. With the switch OFF, deleting keeps everything ===\n";
$ids=seed();
$adv=get_option('loopkit_advanced_settings',array()); $adv['remove_data_on_uninstall']=''; update_option('loopkit_advanced_settings',$adv);
$before=counts();
run_uninstall();
$after=counts();
t($after['tables']===$before['tables'] && $after['posts']===$before['posts'],
  '1a. Nothing is removed unless the site asked for it');
t(get_post($ids['tpl']) !== null,'1b. Templates survive');
t((int)$after['options']>0,'1c. Settings survive');

echo "=== 2. With it ON, everything of ours goes ===\n";
$adv['remove_data_on_uninstall']='1'; update_option('loopkit_advanced_settings',$adv);
$ids=seed();
$seeded=counts();
t($seeded['tables']===4 && $seeded['posts']>=3 && $seeded['files']===2,
  '2a. (the fixture really is there: '.json_encode($seeded).')');
loopkit_uninstall_remove_all();
$gone=counts();
echo "  after: ".json_encode($gone)."\n";
t($gone['tables']===0,'2b. Our four tables are dropped');
t($gone['posts']===0,'2c. Templates, products and orders are gone');
t($gone['postmeta']===0,'2d. Our meta is stripped from pages too');
t($gone['usermeta']===0,'2e. And our user meta');
t($gone['options']===0,'2f. Every option and transient');
t($gone['files']===0,'2g. The generated CSS and import folders');

echo "=== 3. What must NOT be touched ===\n";
t(get_post($ids['page']) !== null,'3a. The page itself is kept — it is their content, not ours');
t((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page'")>0,'3b. All pages kept');
t((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}")>0,'3c. Users kept');
t(get_option('siteurl')!==false,'3d. WordPress\' own settings untouched');
$up=wp_upload_dir();
t(is_dir($up['basedir']),'3e. The uploads folder itself is kept');

echo "=== 4. Safe to run twice ===\n";
loopkit_uninstall_remove_all();
t(true,'4a. A second run does not fatal');

// This suite is destructive by design: it wipes the site to prove the wipe
// works. Put the site back, or every suite run after it starts from rubble.
if ( class_exists( 'Loopkit_Module_CMS' ) ) {
  Loopkit_Module_CMS::install();
} else {
  // The option that loads the module was removed with everything else.
  echo "  (reinstall the plugin tables on next load)\n";
}
echo "\n=== uninstall: $p passed, $f failed ===\n";
