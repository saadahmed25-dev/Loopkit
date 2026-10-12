<?php
/** Stage E — one row, one URL. */
// Command line only. These load WordPress and some of them delete things —
// one of them wipes the site to prove the wipe works. Reachable over the web,
// that is a URL that destroys a site.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "Loopkit tests run from the command line only.\n" );
}

define('WP_ADMIN',true); // Find WordPress from this file, not from wherever the suite was started.
$lk_wp_load = dirname( __DIR__, 5 ) . '/wp-load.php';
if ( ! file_exists( $lk_wp_load ) ) { $lk_wp_load = getenv( 'LK_WP_LOAD' ); }
require $lk_wp_load;
require_once ABSPATH.'wp-admin/includes/admin.php'; wp_set_current_user(1);
$p=0;$fl=0; function t($o,$m){global $p,$fl; if($o){$p++;echo "  PASS: $m\n";}else{$fl++;echo "  FAIL: $m\n";}}
$cms=null; foreach($GLOBALS['wp_filter']['init'] as $cbs) foreach($cbs as $cb){$x=$cb['function'];
 if(is_array($x)&&$x[0] instanceof Loopkit_Module_CMS)$cms=$x[0];}
foreach(Loopkit_Module_CMS::get_collections() as $c) Loopkit_Module_CMS::delete_collection((int)$c->id);

echo "=== 1. Every row gets its own name ===\n";
$cid=Loopkit_Module_CMS::create_collection('Team','team','dashicons-groups');
Loopkit_Module_CMS::create_field($cid,'Name','name','text',array(),0);
Loopkit_Module_CMS::create_field($cid,'Role','role','text',array(),1);
$a=Loopkit_Module_CMS::create_item($cid,array('name'=>'Ayesha Khan','role'=>'Designer'));
$b=Loopkit_Module_CMS::create_item($cid,array('name'=>'Ayesha Khan','role'=>'Engineer'));
$c3=Loopkit_Module_CMS::create_item($cid,array());
t(Loopkit_Module_CMS::get_item($a)->slug==='ayesha-khan','1a. Named from its title');
t(Loopkit_Module_CMS::get_item($b)->slug==='ayesha-khan-2','1b. A clash gets a number, not an overwrite');
t(Loopkit_Module_CMS::get_item($c3)->slug==='item-'.$c3,'1c. An empty row still has an address');

echo "=== 1b. Each creator hands back its own id ===\n";
// queue_rewrite_flush() calls update_option(), which INSERTs into wp_options
// the first time and replaces $wpdb->insert_id. create_collection was handing
// back an options row id, and every id downstream was wrong.
global $wpdb;
// The bug only bit the FIRST time the option was written, because
// update_option() only INSERTs when the name is new. Reproduce that.
delete_option('loopkit_cms_flush_rewrites');
$pid = Loopkit_Module_CMS::create_collection('Probe','probe','dashicons-database');
$real = (int) $wpdb->get_var("SELECT id FROM ".Loopkit_Module_CMS::table_collections()." WHERE slug='probe'");
t($pid === $real, '1d. create_collection returns the collection id, not an options row id');
$pf = Loopkit_Module_CMS::create_field($pid,'N','n','text',array(),0);
t($pf === (int) $wpdb->get_var("SELECT id FROM ".Loopkit_Module_CMS::table_fields()." WHERE collection_id=".(int)$pid),
  '1e. create_field returns the field id');
$pi = Loopkit_Module_CMS::create_item($pid,array('n'=>'x'));
t($pi === (int) $wpdb->get_var("SELECT id FROM ".Loopkit_Module_CMS::table_items()." WHERE collection_id=".(int)$pid),
  '1f. create_item returns the row id');
Loopkit_Module_CMS::delete_collection($pid);

echo "=== 2. Renaming a row renames its page ===\n";
Loopkit_Module_CMS::update_item($a,array('data'=>array('name'=>'Ayesha Siddiqui')));
t(Loopkit_Module_CMS::get_item($a)->slug==='ayesha-siddiqui','2a. The slug follows the title');
Loopkit_Module_CMS::update_item($a,array('slug'=>'the-boss'));
t(Loopkit_Module_CMS::get_item($a)->slug==='the-boss','2b. An explicit slug can be set');
Loopkit_Module_CMS::update_item($a,array('data'=>array('role'=>'Lead')));
t(Loopkit_Module_CMS::get_item($a)->slug==='ayesha-siddiqui','2c. ...but a later edit re-derives it from the title');

echo "=== 3. Looking a row up by its address ===\n";
$f=Loopkit_Module_CMS::get_item_by_slug($cid,'ayesha-siddiqui');
t($f && (int)$f->id===$a,'3a. Found by slug');
t(null===Loopkit_Module_CMS::get_item_by_slug($cid,'nobody'),'3b. A bad slug finds nothing');
$other=Loopkit_Module_CMS::create_collection('Blog','blog','dashicons-edit');
Loopkit_Module_CMS::create_field($other,'Name','name','text',array(),0);
Loopkit_Module_CMS::create_item($other,array('name'=>'Ayesha Siddiqui'));
t(Loopkit_Module_CMS::get_item_by_slug($other,'ayesha-siddiqui')->collection_id==$other,
  '3c. The same name in two collections stays two rows');

echo "=== 4. The URL ===\n";
$perm=Loopkit_Module_CMS::item_permalink(Loopkit_Module_CMS::get_item($a));
t(strpos($perm,'/team/ayesha-siddiqui')!==false,'4a. Built from the collection slug and the row slug');
$cms->add_rewrite_rules();
global $wp_rewrite;
$rules=(array)$wp_rewrite->extra_rules_top;
t(isset($rules['^team/([^/]+)/?$']),'4b. A rule is registered for the collection');
t(isset($rules['^blog/([^/]+)/?$']),'4c. One per collection, not a catch-all');
t(strpos($rules['^team/([^/]+)/?$'],'lk_collection=team')!==false,'4d. It carries the collection through');
$vars=$cms->add_query_vars(array());
t(in_array('lk_collection',$vars,true) && in_array('lk_item',$vars,true),'4e. Both names reach the query');

echo "=== 5. Which template renders a row ===\n";
$tid=wp_insert_post(array('post_type'=>'loopkit_template','post_title'=>'Member Page','post_status'=>'publish'));
update_post_meta($tid,'_loopkit_template_type','cms-item');
t(Loopkit_Module_CMS::item_template_id($cid)===0,'5a. None assigned to begin with');
Loopkit_Module_CMS::set_item_template($cid,$tid);
t(Loopkit_Module_CMS::item_template_id($cid)===$tid,'5b. Assigning one sticks');
t(Loopkit_Module_CMS::item_template_id($other)===0,'5c. Per collection, not global');
Loopkit_Module_CMS::set_item_template($cid,0);
t(Loopkit_Module_CMS::item_template_id($cid)===0,'5d. And can be removed');
Loopkit_Module_CMS::set_item_template($cid,$tid);

echo "=== 6. A row's values inside its page ===\n";
$col=Loopkit_Module_CMS::get_collection($cid);
Loopkit_Module_CMS::set_context(Loopkit_Module_CMS::get_item($a),$col);
t($cms->resolve_dynamic_tag('','item_field','name',0)==='Ayesha Siddiqui','6a. item_field reads this row');
t($cms->resolve_dynamic_tag('','item_field','role',0)==='Lead','6b. Every field, not just the title');
t($cms->resolve_dynamic_tag('','collection_title','',0)==='Team','6c. collection_title reads the collection');
t(Loopkit_Module_CMS::item_title(Loopkit_Module_CMS::get_item($a))==='Ayesha Siddiqui','6d. The row has a display title');
Loopkit_Module_CMS::set_context(null,null);
t($cms->resolve_dynamic_tag('','item_field','name',0)==='','6e. Outside a row it resolves to nothing');

echo "=== 7. The admin can wire it up ===\n";
$src=file_get_contents(WP_PLUGIN_DIR.'/loopkit/admin/class-loopkit-admin.php');
t(strpos($src,"'cms-item'")!==false,'7a. Collection Item is a template type');
t(strpos($src,'value="cms-item"')!==false,'7b. And is offered in the type dropdown');
$_GET=array('id'=>$cid); ob_start(); $cms->render_collection_form(); $h=ob_get_clean();
t(strpos($h,'name="item_template"')!==false,'7c. The collection form has a template picker');
t(strpos($h,'Member Page')!==false,'7d. Listing the cms-item templates');
t(strpos($h,'/team/')!==false,'7e. And shows what a row URL will look like');

echo "=== 8. Changing a collection rebuilds the URLs ===\n";
delete_option('loopkit_cms_flush_rewrites');
Loopkit_Module_CMS::create_collection('Cases','cases','dashicons-portfolio');
t((int)get_option('loopkit_cms_flush_rewrites')===1,'8a. Creating one queues a rebuild');
delete_option('loopkit_cms_flush_rewrites');
Loopkit_Module_CMS::update_collection($cid,array('name'=>'Team','slug'=>'crew','icon'=>'dashicons-groups'));
t((int)get_option('loopkit_cms_flush_rewrites')===1,'8b. Renaming one does too — the URLs changed');
delete_option('loopkit_cms_flush_rewrites');
Loopkit_Module_CMS::delete_collection($other);
t((int)get_option('loopkit_cms_flush_rewrites')===1,'8c. And removing one');
$cms->maybe_flush_rewrites();
t(false===get_option('loopkit_cms_flush_rewrites'),'8d. The queue clears once done');

echo "=== 9. Deleting a collection takes its page mapping with it ===\n";
$z = Loopkit_Module_CMS::create_collection('Zed','zed','dashicons-database');
Loopkit_Module_CMS::set_item_template($z,$tid);
t(Loopkit_Module_CMS::item_template_id($z)===$tid,'9a. Mapped');
Loopkit_Module_CMS::delete_collection($z);
t(Loopkit_Module_CMS::item_template_id($z)===0,
  '9b. Gone with it, so a reused id does not inherit a template');

foreach(Loopkit_Module_CMS::get_collections() as $c) Loopkit_Module_CMS::delete_collection((int)$c->id);
wp_delete_post($tid,true);
echo "\n=== Stage E: $p passed, $fl failed ===\n";
