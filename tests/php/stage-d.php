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

foreach(Loopkit_Module_CMS::get_collections() as $c) Loopkit_Module_CMS::delete_collection((int)$c->id);
$cid=Loopkit_Module_CMS::create_collection('Team','team','dashicons-groups');
Loopkit_Module_CMS::create_field($cid,'Name','name','text',array(),0);
Loopkit_Module_CMS::create_field($cid,'Role','role','text',array(),1);
Loopkit_Module_CMS::create_field($cid,'Bio','bio','long_text',array(),2);
Loopkit_Module_CMS::create_field($cid,'Photo','photo','image',array(),3);
Loopkit_Module_CMS::create_field($cid,'Link','link','url',array(),4);
$a=Loopkit_Module_CMS::create_item($cid,array('name'=>'Ayesha Khan','role'=>'Designer','bio'=>'Leads the design work.','photo'=>'https://example.com/a.jpg','link'=>'https://example.com/ayesha'));
$b=Loopkit_Module_CMS::create_item($cid,array('name'=>'Bilal Ahmed','role'=>'Engineer','bio'=>'Builds the thing.','photo'=>'https://example.com/b.jpg','link'=>'https://example.com/bilal'));

echo "=== D1. The widget is registered in all three places ===\n";
$reg = Loopkit_Widgets_Registry::instance();
$w = $reg->get_widget('collection-list');
t($w instanceof Loopkit_Widget_Collection_List, 'D1a. Registered in the widget registry');
$rest = file_get_contents(WP_PLUGIN_DIR.'/loopkit/includes/class-loopkit-rest-api.php');
t(strpos($rest,"'collection-list' )")!==false || strpos($rest,"'collection-list',")!==false, 'D1b. In the REST server-rendered allowlist');
$canvas = file_get_contents(WP_PLUGIN_DIR.'/loopkit/editor/js/loopkit-canvas.js');
t(strpos($canvas,"'collection-list': function")!==false, 'D1c. In the canvas renderer map');

echo "=== D2. Dynamic tags actually resolve ===\n";
$item = Loopkit_Module_CMS::get_item($a);
$col  = Loopkit_Module_CMS::get_collection($cid);
Loopkit_Module_CMS::set_context($item,$col);
t(Loopkit_Dynamic::resolve_string('{{lk:item_field:name}}')==='Ayesha Khan','D2a. item_field resolves to the row value');
t(Loopkit_Dynamic::resolve_string('{{lk:collection_title}}')==='Team','D2b. collection_title resolves');
t(Loopkit_Dynamic::resolve_string('{{lk:item_field:nope}}')==='','D2c. An unknown field resolves empty, not an error');
Loopkit_Module_CMS::set_context(null,null);
t(Loopkit_Dynamic::resolve_string('{{lk:item_field:name}}')==='','D2d. Outside a card it resolves empty');

echo "=== D3. The widget renders the collection ===\n";
$s = array('cl_collection'=>(string)$cid,'cl_count'=>10,'cl_orderby'=>'id_asc',
  'cl_title_field'=>'name','cl_meta_field'=>'role','cl_text_field'=>'bio',
  'cl_image_field'=>'photo','cl_link_field'=>'link','cl_link_text'=>'Profile','cl_title_tag'=>'h3');
$html = $w->render($w->with_defaults($s), array('id'=>'x','settings'=>$s), false);
t(strpos($html,'Ayesha Khan')!==false && strpos($html,'Bilal Ahmed')!==false,'D3a. Both rows render');
t(strpos($html,'Designer')!==false,'D3b. The meta field renders');
t(strpos($html,'Leads the design work.')!==false,'D3c. The description renders');
t(strpos($html,'https://example.com/a.jpg')!==false,'D3d. The image renders');
t(strpos($html,'https://example.com/ayesha')!==false && strpos($html,'Profile')!==false,'D3e. The link renders with its text');
t(strpos($html,'<h3 class="lk-cl-title">')!==false,'D3f. The title uses the chosen tag');
t(strpos($html,'lk-cl--grid')!==false,'D3g. Grid layout by default');
t(strpos($html,'Ayesha')<strpos($html,'Bilal'),'D3h. Order by oldest first is respected');

echo "=== D4. Ordering, filtering, limits, empty ===\n";
$s2=$s; $s2['cl_orderby']='field'; $s2['cl_order_field']='name'; $s2['cl_order_dir']='DESC';
$h2=$w->render($w->with_defaults($s2),array('id'=>'x','settings'=>$s2),false);
t(strpos($h2,'Bilal')<strpos($h2,'Ayesha'),'D4a. Order by a field, descending');
$s3=$s; $s3['cl_count']=1;
$h3=$w->render($w->with_defaults($s3),array('id'=>'x','settings'=>$s3),false);
t(substr_count($h3,'lk-cl-card')===1,'D4b. How-many is respected');
$s4=$s; $s4['cl_filter_field']='role'; $s4['cl_filter_value']='engineer';
$h4=$w->render($w->with_defaults($s4),array('id'=>'x','settings'=>$s4),false);
t(strpos($h4,'Bilal')!==false && strpos($h4,'Ayesha')===false,'D4c. Filter keeps only matching rows');
$s5=$s; $s5['cl_filter_field']='role'; $s5['cl_filter_value']='zzz'; $s5['cl_empty_text']='Nothing here yet.';
$h5=$w->render($w->with_defaults($s5),array('id'=>'x','settings'=>$s5),false);
t(strpos($h5,'Nothing here yet.')!==false,'D4d. Empty state shows when nothing matches');
$s6=$s; $s6['cl_card_links']='yes';
$h6=$w->render($w->with_defaults($s6),array('id'=>'x','settings'=>$s6),false);
t(strpos($h6,'<a class="lk-cl-card is-linked"')!==false,'D4e. Whole card can be the link');

echo "=== D5. Behaves when nothing is set up ===\n";
$h7=$w->render($w->with_defaults(array()),array('id'=>'x','settings'=>array()),true);
t(strpos($h7,'Pick a collection')!==false,'D5a. Editor says what to do when no collection is chosen');
$h8=$w->render($w->with_defaults(array()),array('id'=>'x','settings'=>array()),false);
t($h8==='','D5b. Renders nothing on the front end when unset');
$s9=array('cl_collection'=>(string)$cid);
$h9=$w->render($w->with_defaults($s9),array('id'=>'x','settings'=>$s9),true);
t(strpos($h9,'Choose which field goes where')!==false,'D5c. Editor says what to do when no field is bound');
t(!preg_match('/Warning|Notice|Fatal/',$html.$h2.$h4.$h7),'D5d. No PHP warnings in any render');

echo "=== D6. CSS generators agree ===\n";
$ph=file_get_contents(WP_PLUGIN_DIR.'/loopkit/includes/class-loopkit-rest-api.php');
$jsf=file_get_contents(WP_PLUGIN_DIR.'/loopkit/editor/js/loopkit-css.js');
t(substr_count($ph,"'cl_")===substr_count($jsf,"'cl_"),'D6a. Same number of cl_ rules in both generators ('.substr_count($ph,"'cl_").')');
foreach(Loopkit_Module_CMS::get_collections() as $c) Loopkit_Module_CMS::delete_collection((int)$c->id);
echo "\n=== Stage D: $p passed, $fl failed ===\n";
