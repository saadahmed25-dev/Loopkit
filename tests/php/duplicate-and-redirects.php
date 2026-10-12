<?php
// Command line only. These load WordPress and some of them delete things —
// one of them wipes the site to prove the wipe works. Reachable over the web,
// that is a URL that destroys a site.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "Loopkit tests run from the command line only.\n" );
}

define('WP_ADMIN',true); $_SERVER['REQUEST_METHOD']='POST';
// Find WordPress from this file, not from wherever the suite was started.
$lk_wp_load = dirname( __DIR__, 5 ) . '/wp-load.php';
if ( ! file_exists( $lk_wp_load ) ) { $lk_wp_load = getenv( 'LK_WP_LOAD' ); }
require $lk_wp_load; require_once ABSPATH.'wp-admin/includes/admin.php'; wp_set_current_user(1);
$p=0;$fl=0; function t($o,$m){global $p,$fl; if($o){$p++;echo "  PASS: $m\n";}else{$fl++;echo "  FAIL: $m\n";}}
foreach(Loopkit_Module_CMS::get_collections() as $c) Loopkit_Module_CMS::delete_collection((int)$c->id);
$cms=null; foreach($GLOBALS['wp_filter']['admin_menu'] as $cbs) foreach($cbs as $cb){$x=$cb['function'];
 if(is_array($x)&&$x[0] instanceof Loopkit_Module_CMS)$cms=$x[0];}
function call($cms,$m,$a=array()){$x=new ReflectionMethod($cms,$m);$x->setAccessible(true);return $x->invokeArgs($cms,$a);}

$cid=Loopkit_Module_CMS::create_collection('Team','team','dashicons-groups');
$f1=Loopkit_Module_CMS::create_field($cid,'Name','name','text',array(),0);
Loopkit_Module_CMS::create_field($cid,'Bio','bio','long_text',array(),1);
Loopkit_Module_CMS::create_item($cid,array('name'=>'Ayesha','bio'=>'one'));
Loopkit_Module_CMS::create_item($cid,array('name'=>'Bilal','bio'=>'two'));

echo "=== 1. The impossible redirects are gone ===\n";
$src=file_get_contents(WP_PLUGIN_DIR.'/loopkit/modules/cms/class-loopkit-module-cms.php');
foreach(array('render_fields','render_items','render_collections') as $fn){
  $at=strpos($src,'function '.$fn.'(');
  $body=substr($src,$at, strpos($src,"\n    }",$at)-$at );
  t(strpos($body,'wp_safe_redirect')===false, "1. $fn no longer redirects from a render callback");
}
echo "=== 2. Those screens cope on their own ===\n";
$_GET=array(); ob_start(); $cms->render_fields(); $h=ob_get_clean();
t(strpos($h,'Fields')!==false && strpos($h,'Team')!==false,'2a. Fields with no id lands on the first collection');
$_GET=array('collection_id'=>999999); ob_start(); $cms->render_fields(); $h2=ob_get_clean();
t(strpos($h2,'Team')!==false,'2b. Fields with a dead id lands on the first collection');
$_GET=array('collection_id'=>999999); ob_start(); $cms->render_items(); $h3=ob_get_clean();
t(strpos($h3,'Team')!==false,'2c. Items with a dead id does the same');
foreach(Loopkit_Module_CMS::get_collections() as $c) Loopkit_Module_CMS::delete_collection((int)$c->id);
$_GET=array(); ob_start(); $cms->render_fields(); $h4=ob_get_clean();
t(strpos($h4,'no collections yet')!==false,'2d. With none at all it says so and offers to make one');
ob_start(); $cms->render_items(); $h5=ob_get_clean();
t(strpos($h5,'no collections yet')!==false,'2e. Items too');

echo "=== 3. Duplicate a field ===\n";
$cid=Loopkit_Module_CMS::create_collection('Team','team','dashicons-groups');
$f1=Loopkit_Module_CMS::create_field($cid,'Name','name','text',array(),0);
$new=Loopkit_Module_CMS::duplicate_field($f1);
t($new>0,'3a. It returns a new field');
$c1=Loopkit_Module_CMS::get_field($new);
t($c1 && $c1->field_key==='name_copy','3b. The key does not collide');
t($c1 && strpos($c1->label,'copy')!==false,'3c. The label says it is a copy');
t($c1 && $c1->type==='text','3d. The type carries over');
t((int)$c1->collection_id===$cid,'3e. It lands in the same collection');
$again=Loopkit_Module_CMS::duplicate_field($f1);
t(Loopkit_Module_CMS::get_field($again)->field_key==='name_copy_2','3f. A second copy does not collide either');

echo "=== 4. Duplicate a collection ===\n";
Loopkit_Module_CMS::create_field($cid,'Bio','bio','long_text',array(),1);
Loopkit_Module_CMS::create_item($cid,array('name'=>'Ayesha','bio'=>'one'));
Loopkit_Module_CMS::create_item($cid,array('name'=>'Bilal','bio'=>'two'));
$ncid=Loopkit_Module_CMS::duplicate_collection($cid);
t($ncid>0,'4a. It returns a new collection');
$nc=Loopkit_Module_CMS::get_collection($ncid);
t($nc && strpos($nc->name,'copy')!==false,'4b. The name says it is a copy');
t($nc && $nc->slug!=='team','4c. The slug is its own');
$nf=Loopkit_Module_CMS::get_fields($ncid);
t(count($nf)===count(Loopkit_Module_CMS::get_fields($cid)),'4d. Every field came across');
$ni=Loopkit_Module_CMS::get_items($ncid,array('per_page'=>50,'orderby'=>'id','order'=>'ASC'));
t($ni['total']===2,'4e. Both rows came across');
t($ni['items'][0]->data['name']==='Ayesha','4f. With their values, in order');
Loopkit_Module_CMS::update_item($ni['items'][0]->id, array('data'=>array('name'=>'Changed')));
$orig=Loopkit_Module_CMS::get_items($cid,array('per_page'=>50,'orderby'=>'id','order'=>'ASC'));
t($orig['items'][0]->data['name']==='Ayesha','4g. Editing the copy leaves the original alone');

echo "=== 5. Wired into the screens and the handlers ===\n";
$_GET=array('collection_id'=>$cid); ob_start(); $cms->render_fields(); $fh=ob_get_clean();
t(strpos($fh,'value="duplicate_field"')!==false,'5a. Fields screen offers Duplicate');
t(substr_count($fh,'lk-row-act')>=5,'5b. Its actions are the styled buttons');
ob_start(); $cms->render_collections(); $ch=ob_get_clean();
t(strpos($ch,'value="duplicate_collection"')!==false,'5c. Collections screen offers Duplicate');
t(strpos($ch,'lk-item-actions')!==false,'5d. And uses the same button styling');
$_POST=array('collection_id'=>$cid,'field_id'=>$f1);
t(strpos(call($cms,'redirect_for_action',array('duplicate_field')),'loopkit_cms_fields')!==false,
  '5e. Copying a field returns to Fields');
t(strpos(call($cms,'redirect_for_action',array('duplicate_collection')),'loopkit_cms_collections')!==false,
  '5f. Copying a collection returns to Collections');
foreach(Loopkit_Module_CMS::get_collections() as $c) Loopkit_Module_CMS::delete_collection((int)$c->id);
echo "\n=== $p passed, $fl failed ===\n";
