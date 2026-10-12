<?php
// Command line only. These load WordPress and some of them delete things —
// one of them wipes the site to prove the wipe works. Reachable over the web,
// that is a URL that destroys a site.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "Loopkit tests run from the command line only.\n" );
}

define('WP_ADMIN',true);
function do_action_css(){ global $cmsx; ob_start(); $cmsx->admin_styles(); return ob_get_clean(); } // Find WordPress from this file, not from wherever the suite was started.
$lk_wp_load = dirname( __DIR__, 5 ) . '/wp-load.php';
if ( ! file_exists( $lk_wp_load ) ) { $lk_wp_load = getenv( 'LK_WP_LOAD' ); }
require $lk_wp_load;
require_once ABSPATH.'wp-admin/includes/admin.php'; wp_set_current_user(1);
$p=0;$fl=0; function t($o,$m){global $p,$fl; if($o){$p++;echo "  PASS: $m\n";}else{$fl++;echo "  FAIL: $m\n";}}
foreach(Loopkit_Module_CMS::get_collections() as $c) Loopkit_Module_CMS::delete_collection((int)$c->id);
$cid=Loopkit_Module_CMS::create_collection('My Collection','my-collection','dashicons-database');
foreach(array(array('Title','title','text'),array('Title 2','title_2','long_text'),array('Order','ordr','number'),array('Link','link','url')) as $f)
  Loopkit_Module_CMS::create_field($cid,$f[0],$f[1],$f[2],array(),0);
$i1=Loopkit_Module_CMS::create_item($cid,array('title'=>'Row one'));
$i2=Loopkit_Module_CMS::create_item($cid,array('title'=>'Row two'));
$i3=Loopkit_Module_CMS::create_item($cid,array('title'=>'Row three'));
$cms=null; foreach($GLOBALS['wp_filter']['admin_menu'] as $cbs) foreach($cbs as $cb){$x=$cb['function'];
 if(is_array($x)&&$x[0] instanceof Loopkit_Module_CMS)$cms=$x[0];}
$GLOBALS['cmsx']=$cms;

echo "=== 1. Row actions are in the table and cannot scroll away ===\n";
$_GET=array('collection_id'=>$cid); ob_start(); $cms->render_items(); $h=ob_get_clean();
t(substr_count($h,'lk-item-actions')===3,'1a. Every row has its actions (3 of 3)');
// The class name matters: lk-row-actions already means hidden-until-hover in
// loopkit-admin.css, scoped to .lk-table, so reusing it here made the buttons
// render and stay invisible. Assert we are not on that name again.
t(strpos($h,'class="lk-row-actions"')===false,
  '1f. The actions do not reuse the hide-on-hover class from loopkit-admin.css');
$acss=file_get_contents(WP_PLUGIN_DIR.'/loopkit/admin/css/loopkit-admin.css');
t(strpos($acss,'.lk-row-actions')!==false,
  '1g. (that rule does still exist, so the clash was real)');
t(substr_count($h,'lk-cms-edit-item')===3,'1b. Edit on every row');
// The panel carries its own copy of each action now, so count the table's.
$tbl = substr($h, 0, strpos($h,'id="lk-cms-item-editor"'));
t(substr_count($tbl,'value="duplicate:')===3,'1c. Duplicate on every row');
t(substr_count($tbl,'value="delete:')===3,'1d. Delete on every row');
ob_start(); $cms->admin_styles(); $css=ob_get_clean();
t(strpos($css,'td.lk-col-actions { position: sticky')!==false,
  '1e. The actions column is pinned so sideways scroll cannot hide it');

echo "=== 2. The side panel offers the same actions ===\n";
$_GET=array('collection_id'=>$cid,'item_id'=>$i2); ob_start(); $cms->render_items(); $e=ob_get_clean();
$panel = substr($e, strpos($e,'id="lk-cms-item-editor"'));
t(strpos($panel,'value="duplicate:'.$i2.'"')!==false,'2a. Duplicate in the panel, for this row');
t(strpos($panel,'value="delete:'.$i2.'"')!==false,'2b. Delete in the panel, for this row');
t(strpos($panel,'lk-editor-foot-right')!==false,'2c. They sit apart from Save');

echo "=== 3. Previous / next step through the rows ===\n";
t(strpos($panel,'lk-editor-step')!==false,'3a. The stepper is there');
t(strpos($panel,'item_id='.$i1)!==false,'3b. Previous points at the row above');
t(strpos($panel,'item_id='.$i3)!==false,'3c. Next points at the row below');
t(strpos($panel,'2 of 3')!==false,'3d. It says where you are');
// Rows list newest first, so the row created last sits at the top.
$_GET=array('collection_id'=>$cid,'item_id'=>$i3); ob_start(); $cms->render_items(); $e1=ob_get_clean();
$p1=substr($e1,strpos($e1,'id="lk-cms-item-editor"'));
t(strpos($p1,'lk-step-prev is-off')!==false && strpos($p1,'lk-step-next is-off')===false
  && strpos($p1,'1 of 3')!==false, '3e. On the top row the back arrow is off');
$_GET=array('collection_id'=>$cid,'item_id'=>$i1); ob_start(); $cms->render_items(); $e3=ob_get_clean();
$p3=substr($e3,strpos($e3,'id="lk-cms-item-editor"'));
t(strpos($p3,'lk-step-next is-off')!==false && strpos($p3,'lk-step-prev is-off')===false
  && strpos($p3,'3 of 3')!==false, '3f. On the bottom row the forward arrow is off');
// add-row panel has no stepper and no destructive actions
$_GET=array('collection_id'=>$cid); ob_start(); $cms->render_items(); $e0=ob_get_clean();
$p0=substr($e0,strpos($e0,'id="lk-cms-item-editor"'));
// One panel serves every row, so these are always in the markup and the
// hidden attribute is what keeps them off a brand new row. Asserting they
// were absent is what let the click path ship broken.
t(strpos($p0,'class="lk-editor-step" hidden')!==false,'3g. A brand new row has its stepper hidden');
t(strpos($p0,'class="lk-editor-foot-right" hidden')!==false,'3h. A brand new row has its delete hidden');
t(strpos($p0,'[hidden] { display: none; }')!==false
  || strpos(do_action_css(),'lk-editor-step[hidden], .lk-editor-foot-right[hidden] { display: none; }')!==false,
  '3i. And hidden actually hides them, beating the display on the class');

echo "=== 4. The panel buttons work without JavaScript ===\n";
$js=file_get_contents(WP_PLUGIN_DIR.'/loopkit/modules/cms/assets/js/cms-admin.js');
t(strpos($js,"'.lk-items-table .delete-item'")!==false,'4a. The AJAX delete is scoped to the table');
t(strpos($js,"'.lk-items-table .lk-cms-dup'")!==false,'4b. The AJAX duplicate is scoped to the table');
t(strpos($js,"'.delete-item', function")===false,'4c. Nothing still catches panel clicks and swallows them');

echo "=== 5. Nothing regressed in the row itself ===\n";
$_GET=array('collection_id'=>$cid); ob_start(); $cms->render_items(); $h2=ob_get_clean();
t(strpos($h2,'Row one')!==false && strpos($h2,'Row three')!==false,'5a. Rows still render');
t(substr_count($h2,'lk-cms-cell-textarea')===3,'5b. Long text still types inline');
t(strpos($h2,'lk-cms-cell-expand')!==false,'5c. The expand control is still there');
foreach(Loopkit_Module_CMS::get_collections() as $c) Loopkit_Module_CMS::delete_collection((int)$c->id);
echo "\n=== $p passed, $fl failed ===\n";
