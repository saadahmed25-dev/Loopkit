<?php
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
$cms=null; foreach($GLOBALS['wp_filter']['admin_menu'] as $cbs) foreach($cbs as $cb){$x=$cb['function'];
 if(is_array($x)&&$x[0] instanceof Loopkit_Module_CMS)$cms=$x[0];}
function call($cms,$m,$a=array()){$x=new ReflectionMethod($cms,$m);$x->setAccessible(true);return $x->invokeArgs($cms,$a);}

// own fixture, every run
foreach(Loopkit_Module_CMS::get_collections() as $c) Loopkit_Module_CMS::delete_collection((int)$c->id);
$cid = Loopkit_Module_CMS::create_collection('My Collection','my-collection','dashicons-database');
if(!$cid){ echo "FIXTURE FAILED\n"; exit(1); }

echo "=== 1. Notices name the thing ===\n";
$_POST=array('collection_id'=>$cid,'label'=>'Full Name','type'=>'text','field_key'=>'');
ob_start(); call($cms,'do_save_field'); $n2=ob_get_clean();
t(strpos($n2,'full_name')!==false && !preg_match('/id:\s*\d+/',$n2), '1a. Field notice shows the key, no numeric id');
$src=file_get_contents(WP_PLUGIN_DIR.'/loopkit/modules/cms/class-loopkit-module-cms.php');
t(substr_count($src,'id: %d')===0, '1b. No (id: %d) notices left in the module');

echo "=== 2+3. Actions land back where they came from ===\n";
$_POST=array('collection_id'=>$cid);
foreach(array('save_field','delete_field','reorder_fields') as $a)
  t(strpos(call($cms,'redirect_for_action',array($a)),'loopkit_cms_fields&collection_id='.$cid)!==false,"2. $a returns to Fields");
$_POST=array('collection_id'=>$cid,'paged'=>'3','orderby'=>'full_name','order'=>'ASC','per_page'=>'25','s'=>'ali');
$u=call($cms,'redirect_for_action',array('save_cells'));
t(strpos($u,'loopkit_cms_items&collection_id='.$cid)!==false,'3a. save_cells returns to Items');
t(strpos($u,'paged=3')!==false&&strpos($u,'s=ali')!==false,'3b. keeps page, sort and search');
$_POST=array('collection_id'=>$cid);
t(strpos(call($cms,'redirect_for_action',array('delete_item')),'loopkit_cms_items')!==false,'3c. delete_item returns to Items');
t(strpos(call($cms,'redirect_for_action',array('delete_collection')),'loopkit_cms_collections')!==false,'3d. delete_collection lists collections');

echo "=== 4. Inline input for every field + editor for every field ===\n";
$_POST=array('collection_id'=>$cid,'label'=>'Bio','type'=>'long_text','field_key'=>'');
ob_start(); call($cms,'do_save_field'); ob_end_clean();
$iid=Loopkit_Module_CMS::create_item($cid,array('full_name'=>'Ayesha','bio'=>"Line one\nLine two"));
$_POST=array(); $_GET=array('collection_id'=>$cid);
ob_start(); $cms->render_items(); $it=ob_get_clean();
t(strpos($it,'lk-cms-cell-textarea')!==false,'4a. Long text has an inline textarea in the cell');
t(strpos($it,'Line one')!==false,'4b. It is filled with the stored value');
t(strpos($it,'lk-cms-cell-expand')!==false,'4c. The same cell opens the side editor');
t(strpos($it,'cells['.$iid.'][bio]')!==false,'4d. It posts through the normal cell path');
$r=Loopkit_Module_CMS::save_cell($iid,'bio',"Typed inline\nsecond line");
$back=Loopkit_Module_CMS::get_item($iid);
t(!empty($r['ok']) && strpos($back->data['bio'],'Typed inline')===0,'4e. Typing into the long-text cell saves');
t(strpos($back->data['bio'],"\n")!==false,'4f. Its line breaks survive');
$_GET=array('collection_id'=>$cid,'item_id'=>$iid);
ob_start(); $cms->render_items(); $ed=ob_get_clean();
t(substr_count($ed,'<textarea')>=2,'4g. The side editor still offers the same field');

foreach(Loopkit_Module_CMS::get_collections() as $c) Loopkit_Module_CMS::delete_collection((int)$c->id);
echo "\n=== $p passed, $fl failed ===\n";
