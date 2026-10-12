<?php
/** Quick view — the endpoint and what it renders. */
if ( 'cli' !== PHP_SAPI ) { http_response_code(403); exit("cli only\n"); }
// Find WordPress from this file, not from where the suite was started.
$lk_wp_load = dirname( __DIR__, 5 ) . '/wp-load.php';
require $lk_wp_load;

// The shop module may be off — a previous suite can switch it back off.
$lk_mods = get_option( 'loopkit_modules', array() );
if ( ! is_array( $lk_mods ) || empty( $lk_mods['woocommerce'] ) ) {
    $lk_mods = is_array( $lk_mods ) ? $lk_mods : array();
    $lk_mods['woocommerce'] = 1;
    update_option( 'loopkit_modules', $lk_mods );
    echo "  (shop module was off; switched on — re-run)\n";
    echo "\n=== quick view: 0 passed, 1 failed ===\n";
    exit( 1 );
}
$p=0;$f=0; function t($o,$m){global $p,$f; if($o){$p++;echo "  PASS: $m\n";}else{$f++;echo "  FAIL: $m\n";}}
wp_set_current_user(1);

$pid = wp_insert_post(array('post_type'=>'loopkit_product','post_title'=>'Test Mug',
  'post_excerpt'=>'A mug for testing.','post_status'=>'publish'));
foreach (array('type'=>'simple','regular_price'=>'20','sale_price'=>'15',
  'stock_status'=>'instock','manage_stock'=>'1','stock_qty'=>'3') as $k=>$v) {
  update_post_meta($pid, '_loopkit_'.$k, $v);
}

echo "=== 1. The endpoint is registered ===\n";
t(has_action('wp_ajax_loopkit_quick_view')!==false,'1a. For logged-in visitors');
t(has_action('wp_ajax_nopriv_loopkit_quick_view')!==false,'1b. And for everyone else — a shop is mostly logged out');

echo "=== 2. What it renders ===\n";
// wp_send_json ends the process, so each call runs in its own.
function qv($pid, $nonce='good'){
  $n = ('good'===$nonce) ? "wp_create_nonce('loopkit_quick_view')" : "'wrong'";
  // The user has to be set before the nonce is made: a nonce is tied to the
  // user, so building it first and switching after never validates.
  $code = 'require "' . dirname( __DIR__, 5 ) . '/wp-load.php"; wp_set_current_user(1);'
        . '$_POST=array("product"=>'.(int)$pid.',"nonce"=>'.$n.'); $_REQUEST=$_POST;'
        . 'Loopkit_Module_WooCommerce::ajax_quick_view();';
  $out = shell_exec('php -r ' . escapeshellarg($code) . ' 2>/dev/null');
  return json_decode((string)$out, true);
}
$json = qv($pid);
t(is_array($json) && !empty($json['success']),'2a. It answers');
$html = $json['data']['html'] ?? '';
t(strpos($html,'Test Mug')!==false,'2b. The product title');
t(strpos($html,'lk-add-to-cart')!==false,'2c. An add-to-cart button');
t(strpos($html,'lk-qv-full')!==false,'2d. A link to the full page — quick view is a shortcut, not a replacement');
t(strpos($html,'Only 3 left')!==false,'2e. Low stock, because it changes whether someone buys now');
t(strpos($html,'screen-reader-text')!==false,'2f. The quantity field is labelled');

echo "=== 3. What it refuses ===\n";
$_POST=array('product'=>$pid,'nonce'=>'wrong');
$bad = qv($pid, 'bad');
t(!is_array($bad) || empty($bad['success']), '3a. A bad nonce is refused');

$draft = wp_insert_post(array('post_type'=>'loopkit_product','post_title'=>'Hidden','post_status'=>'draft'));
$j2 = qv($draft);
t(is_array($j2) && empty($j2['success']),'3b. A draft product is not served');

$j3 = qv(999999);
t(is_array($j3) && empty($j3['success']),'3c. Nor is a product that does not exist');

echo "=== 4. Sold out ===\n";
update_post_meta($pid, '_loopkit_stock_status', 'outofstock');
$j4 = qv($pid); $h4 = $j4['data']['html'] ?? '';
t(strpos($h4,'Sold out')!==false,'4a. Says so');
t(strpos($h4,'lk-add-to-cart')===false,'4b. And offers no add-to-cart button');

echo "=== 5. The grid offers it, off by default ===\n";
$src=file_get_contents(WP_PLUGIN_DIR.'/loopkit/modules/woocommerce/class-loopkit-shop-widgets.php');
t(strpos($src,"'pgrid_quick_view'")!==false,'5a. There is a switch');
t(strpos($src,"'type' => 'switcher', 'default' => 'no'")!==false,'5b. Off unless asked for');
t(strpos($src,'data-lk-quick-view')!==false,'5c. The card carries the button');
$at=strpos($src,'lk-quick-view-btn'); $before=substr($src,max(0,$at-400),400);
t(strpos($before,'</a>')!==false,'5d. The button sits outside the link, not nested in it');

wp_delete_post($pid,true); wp_delete_post($draft,true);
echo "\n=== quick view: $p passed, $f failed ===\n";
