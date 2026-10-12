<?php
// Command line only. These load WordPress and some of them delete things —
// one of them wipes the site to prove the wipe works. Reachable over the web,
// that is a URL that destroys a site.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "Loopkit tests run from the command line only.\n" );
}

$php = file_get_contents('includes/class-loopkit-rest-api.php');
$js  = file_get_contents('editor/js/loopkit-css.js');
function grab($body,$open,$entry){
  $out=array(); $cur=null;
  foreach(preg_split("/\r?\n/",$body) as $l){
    if(preg_match($open,$l,$g)){$cur=$g[1];continue;}
    if($cur && preg_match($entry,$l,$g)){
      $out[]=$cur.'|'.$g[1].'|'.$g[2].'|'.trim($g[3]).'|'.trim($g[4]??'').'|'.trim($g[5]??'');
    }
  }
  return $out;
}
preg_match("/post_widget_css_rules\(\)\s*\{(.*?)\n    \}/s",$php,$m);
$a = grab($m[1],
  "/^\s*'([a-z0-9\-]+)'\s*=>\s*array\(\s*$/",
  "/^\s*array\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']*)'(?:\s*,\s*'([^']*)')?(?:\s*,\s*'([^']*)')?/");
$jl = preg_split("/\r?\n/",$js); $body = implode("\n", array_slice($jl, 72, 866-72));
$b = grab($body,
  "/^\s*'([a-z0-9\-]+)'\s*:\s*\[\s*$/",
  "/^\s*\[\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']*)'(?:\s*,\s*'([^']*)')?(?:\s*,\s*'([^']*)')?/");
echo "PHP rules: ".count($a)."   JS rules: ".count($b)."\n";
$oa=array_values(array_diff($a,$b)); $ob=array_values(array_diff($b,$a));
echo "in PHP not JS: ".count($oa)."\n"; foreach(array_slice($oa,0,5) as $x) echo "   $x\n";
echo "in JS not PHP: ".count($ob)."\n"; foreach(array_slice($ob,0,5) as $x) echo "   $x\n";
$ca=count(array_filter($a,fn($x)=>strpos($x,'collection-list|')===0));
$cb=count(array_filter($b,fn($x)=>strpos($x,'collection-list|')===0));
echo "collection-list: PHP $ca, JS $cb\n";
echo (count($oa)===0 && count($ob)===0) ? "PARITY: OK\n" : "PARITY: MISMATCH\n";
