<?php
/** Stage F — CSV in and out. */
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
global $wpdb;
$wpdb->query("DELETE FROM wp_lk_cms_items"); $wpdb->query("DELETE FROM wp_lk_cms_collections"); $wpdb->query("DELETE FROM wp_lk_cms_fields");
$cid=Loopkit_Module_CMS::create_collection('Team','team','dashicons-groups');
Loopkit_Module_CMS::create_field($cid,'Name','name','text',array(),0);
Loopkit_Module_CMS::create_field($cid,'Role','role','text',array(),1);
Loopkit_Module_CMS::create_field($cid,'Site','site','url',array(),2);
$a=Loopkit_Module_CMS::create_item($cid,array('name'=>'Ayesha Khan','role'=>'Designer','site'=>'https://a.example'));
$b=Loopkit_Module_CMS::create_item($cid,array('name'=>'Bilal, Jr.','role'=>'Engineer "Lead"','site'=>''));
Loopkit_Module_CMS::create_field($cid,'Bio','bio','long_text',array(),3);
$c=Loopkit_Module_CMS::create_item($cid,array('name'=>'Multi Line','role'=>'QA','site'=>'','bio'=>"Line\nBreak"));

echo "=== 1. Export ===\n";
$csv=Loopkit_Module_CMS::export_csv($cid);
t(substr($csv,0,3)===chr(0xEF).chr(0xBB).chr(0xBF),'1a. Starts with a BOM so Excel reads accents right');
$body=substr($csv,3); $lines=explode("\n",$body);
t(strpos($lines[0],'ID,Slug,Status,Name,Role,Site,Bio')===0,'1b. Header is the labels people recognise');
t(strpos($lines[1],'_id,_slug,_status,name,role,site,bio')===0,'1c. A key line underneath, so a re-import maps itself');
t(strpos($body,'"Bilal, Jr."')!==false,'1d. A comma in a value is quoted, not a new column');
t(strpos($body,'"Engineer ""Lead"""')!==false,'1e. A quote in a value is escaped');
t(strpos($body,"\"Line\nBreak\"")!==false,'1f. A newline in a value stays inside its cell');
t(''===Loopkit_Module_CMS::export_csv(Loopkit_Module_CMS::create_collection('Empty','empty','dashicons-database')),
  '1g. A collection with no fields exports nothing rather than a broken file');

echo "=== 2. Reading a file back ===\n";
file_put_contents('/tmp/t-f.csv',$csv);
$r=Loopkit_Module_CMS::read_csv('/tmp/t-f.csv');
t(!isset($r['error']),'2a. It reads');
t($r['header'][0]==='ID','2b. The BOM is stripped, so the first column name matches');
t($r['count']===4,'2c. Four data lines: the key line plus three rows');
$bad=Loopkit_Module_CMS::read_csv('/tmp/does-not-exist.csv');
t(isset($bad['error']),'2d. A missing file is reported, not fatal');
file_put_contents('/tmp/t-empty.csv','');
t(isset(Loopkit_Module_CMS::read_csv('/tmp/t-empty.csv')['error']),'2e. So is an empty one');

echo "=== 3. Matching columns to fields ===\n";
$m=Loopkit_Module_CMS::guess_csv_mapping($r['header'],$cid);
t($m===array(0=>'_id',1=>'_slug',2=>'_status',3=>'name',4=>'role',5=>'site',6=>'bio'),'3a. An export maps itself');
$m2=Loopkit_Module_CMS::guess_csv_mapping(array('NAME','role','Nothing'),$cid);
t($m2===array(0=>'name',1=>'role'),'3b. Case does not matter, and an unknown column is left unmapped');

echo "=== 4. Round trip ===\n";
$before=Loopkit_Module_CMS::get_items($cid,array('per_page'=>99))['total'];
$res=Loopkit_Module_CMS::import_csv($cid,'/tmp/t-f.csv',$m,array('match_on'=>'_id','skip_rows'=>1));
t($res['created']===0 && $res['updated']===3,'4a. Re-importing an export updates, it does not duplicate');
t(Loopkit_Module_CMS::get_items($cid,array('per_page'=>99))['total']===$before,'4b. The row count is unchanged');
$kept=Loopkit_Module_CMS::get_item($b);
t($kept->data['name']==='Bilal, Jr.' && $kept->data['role']==='Engineer "Lead"','4c. Commas and quotes survive the trip');
// A newline survives the CSV layer; a single-line Text field strips it, as it
// should, so the case to assert is a Long Text one.
t(Loopkit_Module_CMS::get_item($c)->data['bio']==="Line\nBreak",
  '4d. A newline survives the trip in a field that allows one');
$raw = Loopkit_Module_CMS::read_csv('/tmp/t-f.csv');
$found = false;
foreach ($raw['rows'] as $line) { foreach ($line as $cell) { if ("Line\nBreak" === $cell) { $found = true; } } }
t($found, '4e. And the CSV layer itself hands the newline back intact');

echo "=== 5. Importing a new file ===\n";
file_put_contents('/tmp/t-new.csv',"Name,Role,Site\nSana Malik,Writer,https://sana.example\nHina Raza,Manager,https://hina.example\n,,\nAyesha Khan,Lead Designer,https://a.example\n");
$m3=Loopkit_Module_CMS::guess_csv_mapping(array('Name','Role','Site'),$cid);
$res2=Loopkit_Module_CMS::import_csv($cid,'/tmp/t-new.csv',$m3,array('match_on'=>'name'));
t($res2['created']===2,'5a. Two new people added');
t($res2['updated']===1,'5b. The one already there was updated, not duplicated');
t($res2['skipped']===1,'5c. A line of nothing but commas is skipped, not stored as a blank row');
t(Loopkit_Module_CMS::get_item($a)->data['role']==='Lead Designer','5d. The update actually changed the value');

echo "=== 6. Bad input is reported, not swallowed ===\n";
Loopkit_Module_CMS::create_field($cid,'Joined','joined','date',array(),3);
file_put_contents('/tmp/t-bad.csv',"Name,Joined\nValid Person,2026-01-15\nBroken Person,not-a-date\nOther Person,2026-02-01\n");
$m4=Loopkit_Module_CMS::guess_csv_mapping(array('Name','Joined'),$cid);
$res3=Loopkit_Module_CMS::import_csv($cid,'/tmp/t-bad.csv',$m4,array('match_on'=>'name'));
t($res3['skipped']===1,'6a. The bad line is skipped');
t($res3['created']===2,'6b. The good lines still import — one bad row does not abort the file');
t(count($res3['errors'])===1 && strpos($res3['errors'][0],'Line 3')!==false,'6c. The error names the line number');
t(strpos($res3['errors'][0],'Joined')!==false,'6d. And the column');
t(null===Loopkit_Module_CMS::find_item_by_value($cid,'name','Broken Person'),'6e. The bad row was not stored');

echo "=== 7. Only what was mapped is touched ===\n";
$only=array(1=>'role');
file_put_contents('/tmp/t-part.csv',"Name,Role\nAyesha Khan,Principal\n");
$was=Loopkit_Module_CMS::get_item($a)->data['name'];
Loopkit_Module_CMS::import_csv($cid,'/tmp/t-part.csv',$only,array('match_on'=>''));
t(Loopkit_Module_CMS::get_item($a)->data['name']===$was,'7a. An unmapped column does not overwrite anything');
$none=Loopkit_Module_CMS::import_csv($cid,'/tmp/t-part.csv',array(),array());
t($none['created']===0 && $none['updated']===0,'7b. Mapping nothing imports nothing');

echo "=== 8. The screen ===\n";
$cms=null; foreach($GLOBALS['wp_filter']['init'] as $cbs) foreach($cbs as $cb){$x=$cb['function'];
 if(is_array($x)&&$x[0] instanceof Loopkit_Module_CMS)$cms=$x[0];}
delete_transient('loopkit_cms_import_'.get_current_user_id());
$_GET=array('collection_id'=>$cid); ob_start(); $cms->render_io(); $h=ob_get_clean();
t(strpos($h,'loopkit_cms_export')!==false,'8a. Export posts to admin-post, where headers can still be sent');
t(strpos($h,'name="csv"')!==false,'8b. There is a file field');
t(strpos($h,'enctype="multipart/form-data"')!==false,'8c. ...on a form that can carry it');
$_POST=array('collection_id'=>$cid);
foreach(array('upload_csv','run_import','cancel_import') as $act){
  $x=new ReflectionMethod($cms,'redirect_for_action'); $x->setAccessible(true);
  t(strpos($x->invoke($cms,$act),'loopkit_cms_io')!==false,"8d. $act returns to Import / Export");
}
$wpdb->query("DELETE FROM wp_lk_cms_items"); $wpdb->query("DELETE FROM wp_lk_cms_collections"); $wpdb->query("DELETE FROM wp_lk_cms_fields");
echo "\n=== Stage F: $p passed, $fl failed ===\n";
