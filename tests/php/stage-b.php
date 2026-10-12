<?php
/**
 * Stage B verification: CMS admin screens.
 *
 * Boots WordPress, registers the CMS menu (the three callbacks that used to
 * fatal when the module was enabled), renders every Stage B screen, and
 * exercises the form handlers against the database.
 *
 * Run:  c:\xampp\php\php.exe verify-stage-b.php
 *       c:\xampp\php\php.exe verify-stage-b.php --missing-collection
 */

// Command line only. These load WordPress and some of them delete things —
// one of them wipes the site to prove the wipe works. Reachable over the web,
// that is a URL that destroys a site.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "Loopkit tests run from the command line only.\n" );
}

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=loopkit_cms';
// Find WordPress from this file, not from where the suite was started.
require_once dirname( __DIR__, 5 ) . '/wp-load.php';
// This suite drives form handlers, and some end in exit() after a redirect,
// so it can stop part-way and never reach its own summary. Print it from a
// shutdown handler and say plainly when that happened, rather than letting a
// half-finished run look like a clean one.
register_shutdown_function( function () {
	global $pass, $fail, $lk_reached_end;
	if ( ! empty( $lk_reached_end ) ) {
		return;
	}
	echo "\n=== Results: " . (int) $pass . " passed, " . (int) $fail . " failed"
		. " (ended early at a redirect) ===\n";
} );

// Its empty-state assertions need an empty site. It used to get one only by
// running after stage-a, which cleared up after itself — an order dependency
// nobody had written down, and the suite failed on its own.
if ( class_exists( 'Loopkit_Module_CMS' ) ) {
	foreach ( Loopkit_Module_CMS::get_collections() as $lk_c ) {
		Loopkit_Module_CMS::delete_collection( (int) $lk_c->id );
	}
}

require_once ABSPATH . 'wp-admin/includes/plugin.php'; // add_menu_page() etc.

$pass = 0;
$fail = 0;

function assert_true( $cond, $label ) {
    global $pass, $fail;
    if ( $cond ) {
        echo "  PASS: {$label}\n";
        $pass++;
    } else {
        echo "  FAIL: {$label}\n";
        $fail++;
    }
}

echo "=== Stage B: CMS Admin Screens Verification ===\n\n";

// ── Module state ───────────────────────────────────────────────────────
echo "--- Module ---\n";
Loopkit_Modules::set_enabled( 'cms', true );
assert_true( Loopkit_Modules::is_enabled( 'cms' ), 'CMS module is enabled' );

if ( ! class_exists( 'Loopkit_Module_CMS' ) ) {
    require_once LOOPKIT_PLUGIN_DIR . 'modules/cms/class-loopkit-module-cms.php';
}
$cms = Loopkit_Modules::get( 'cms' );
if ( ! $cms ) {
    $cms = new Loopkit_Module_CMS();
    $cms->init();
}
assert_true( $cms instanceof Loopkit_Module_CMS, 'CMS module instance available' );
Loopkit_Module_CMS::install();

// Menus register against the current user's capabilities — use an admin.
$admin = get_user_by( 'id', 1 );
if ( ! $admin ) {
    $found = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
    $admin = $found ? $found[0] : null;
}
if ( $admin ) {
    wp_set_current_user( $admin->ID );
}
assert_true( current_user_can( 'manage_options' ), 'Admin user set (needed for menu registration)' );

// Missing-collection mode: render_fields must redirect and exit, never fatal.
if ( isset( $argv[1] ) && '--missing-collection' === $argv[1] ) {
    $_GET = array( 'page' => 'loopkit_cms_fields', 'collection_id' => 99999999 );
    echo "BEFORE\n";
    $cms->render_fields(); // exits via wp_safe_redirect.
    echo "AFTER\n";
    exit( 2 );
}

// ── Menu registration (the callbacks that used to fatal) ───────────────
echo "\n--- Menu registration ---\n";
assert_true( is_callable( array( $cms, 'register_submenus' ) ), 'register_submenus exists' );
assert_true( is_callable( array( $cms, 'render_dashboard' ) ), 'render_dashboard exists' );
assert_true( is_callable( array( $cms, 'admin_styles' ) ), 'admin_styles exists' );

global $menu, $submenu;
$menu    = is_array( $menu ) ? $menu : array();
$submenu = is_array( $submenu ) ? $submenu : array();

$cms->register_menu();
$cms->register_submenus();

$top = false;
foreach ( $menu as $item ) {
    if ( isset( $item[2] ) && 'loopkit_cms' === $item[2] ) {
        $top = true;
    }
}
assert_true( $top, 'Top-level CMS menu registered' );

$subs = array();
foreach ( isset( $submenu['loopkit_cms'] ) ? $submenu['loopkit_cms'] : array() as $item ) {
    $subs[] = $item[2];
}
foreach ( array( 'loopkit_cms', 'loopkit_cms_collections', 'loopkit_cms_new', 'loopkit_cms_fields' ) as $slug ) {
    assert_true( in_array( $slug, $subs, true ), "Submenu {$slug} registered" );
}
assert_true(
    false !== has_action( 'admin_head-toplevel_page_loopkit_cms', array( $cms, 'admin_styles' ) ),
    'admin_styles hooked for the dashboard screen'
);

// ── Assets ─────────────────────────────────────────────────────────────
echo "\n--- Assets ---\n";
$_GET['page'] = 'loopkit_cms_fields';
$cms->enqueue_admin_assets( 'any-hook' );
assert_true( wp_script_is( 'loopkit-cms-admin', 'enqueued' ), 'cms-admin.js enqueued on a CMS screen' );
$loc = wp_scripts()->get_data( 'loopkit-cms-admin', 'data' );
assert_true( is_string( $loc ) && false !== strpos( $loc, 'LoopkitCMS' ), 'LoopkitCMS object localized' );
foreach ( array( 'saved', 'save', 'cancel', 'deleted', 'error', 'confirm', 'saving' ) as $key ) {
    assert_true( is_string( $loc ) && false !== strpos( $loc, '"' . $key . '"' ), "message key '{$key}' present" );
}
wp_dequeue_script( 'loopkit-cms-admin' );
$_GET['page'] = 'loopkit';
$cms->enqueue_admin_assets( 'other-hook' );
assert_true( ! wp_script_is( 'loopkit-cms-admin', 'enqueued' ), 'not enqueued on non-CMS screens' );

// ── admin_styles ───────────────────────────────────────────────────────
echo "\n--- admin_styles ---\n";
ob_start();
$cms->admin_styles();
$css = ob_get_clean();
assert_true( false !== strpos( $css, '.lk-cms-stats' ), 'admin_styles prints CMS css' );
assert_true( false !== strpos( $css, '</style>' ), 'admin_styles outputs a closed style block' );

// ── Screens render (empty state) ───────────────────────────────────────
echo "\n--- Screens (empty state) ---\n";
$_GET = array( 'page' => 'loopkit_cms' );
ob_start();
$cms->render_dashboard();
$html = ob_get_clean();
assert_true( false !== strpos( $html, 'lk-cms-stats' ), 'Dashboard renders stats' );
assert_true( false !== strpos( $html, 'No collections yet' ), 'Dashboard shows empty state' );

$_GET = array( 'page' => 'loopkit_cms_collections' );
ob_start();
$cms->render_collections();
$html = ob_get_clean();
assert_true( false !== strpos( $html, 'Add New Collection' ), 'Collections list renders with Add New action' );
assert_true( false !== strpos( $html, 'No collections yet' ), 'Collections list shows empty state' );

$_GET = array( 'page' => 'loopkit_cms_new' );
ob_start();
$cms->render_collection_form();
$html = ob_get_clean();
assert_true( false !== strpos( $html, 'New Collection' ), 'New Collection form renders' );
assert_true( false !== strpos( $html, 'save_collection' ), 'Form posts save_collection' );
assert_true( false !== strpos( $html, 'name="nonce"' ), 'Form carries the nonce field' );

// ── Form handlers against the database ─────────────────────────────────
echo "\n--- Form handlers (database) ---\n";
$invoke = function ( $method ) use ( $cms ) {
    $m = new ReflectionMethod( $cms, $method );
    $m->setAccessible( true );
    return $m;
};

// Create a collection.
$_POST = array( 'loopkit_cms_action' => 'save_collection', 'name' => 'Stage B Smoke', 'slug' => '', 'icon' => 'dashicons-groups' );
ob_start();
$invoke( 'do_save_collection' )->invoke( $cms );
$out = ob_get_clean();
assert_true( false !== strpos( $out, 'Collection created' ), 'do_save_collection echoes success' );
$col = Loopkit_Module_CMS::get_collection_by_slug( 'stage-b-smoke' );
assert_true( $col && 'Stage B Smoke' === $col->name, 'Collection row in DB with auto slug' );
assert_true( $col && 'dashicons-groups' === $col->icon, 'Icon stored' );

// Create two fields.
foreach ( array( 'Full Name', 'Role' ) as $label ) {
    $_POST = array( 'collection_id' => $col->id, 'label' => $label );
    ob_start();
    $invoke( 'do_save_field' )->invoke( $cms );
    ob_get_clean();
}
$fields = Loopkit_Module_CMS::get_fields( $col->id );
assert_true( 2 === count( $fields ), 'Two field rows in DB' );
$ids = array();
foreach ( $fields as $f ) {
    $ids[] = (int) $f->id;
}

// Reorder: reverse the two fields (the exact payload the screen posts).
$_POST = array( 'collection_id' => $col->id, 'field_ids' => array_reverse( $ids ) );
ob_start();
$invoke( 'do_reorder_fields' )->invoke( $cms );
$out = ob_get_clean();
assert_true( false !== strpos( $out, 'Field order saved' ), 'do_reorder_fields echoes success' );
$got = array();
foreach ( Loopkit_Module_CMS::get_fields( $col->id ) as $f ) {
    $got[] = (int) $f->id;
}
assert_true( array_reverse( $ids ) === $got, 'Field order reversed in DB' );

// Update the collection.
$_POST = array( 'collection_id' => $col->id, 'name' => 'Stage B Renamed', 'slug' => 'stage-b-smoke', 'icon' => 'dashicons-tag' );
ob_start();
$invoke( 'do_save_collection' )->invoke( $cms );
$out = ob_get_clean();
assert_true( false !== strpos( $out, 'Collection updated' ), 'do_save_collection update echoes success' );
$col = Loopkit_Module_CMS::get_collection( $col->id );
assert_true( $col && 'Stage B Renamed' === $col->name && 'dashicons-tag' === $col->icon, 'Collection updated in DB' );

// ── Screens render with data ───────────────────────────────────────────
echo "\n--- Screens (with data) ---\n";
$_GET = array( 'page' => 'loopkit_cms_new', 'id' => $col->id );
ob_start();
$cms->render_collection_form();
$html = ob_get_clean();
assert_true( false !== strpos( $html, 'Edit Collection' ), 'Edit screen heading' );
assert_true( false !== strpos( $html, 'value="Stage B Renamed"' ), 'Edit screen prefills the name' );
assert_true( false !== strpos( $html, 'value="dashicons-tag"' ), 'Edit screen preselects the icon' );

$_GET = array( 'page' => 'loopkit_cms_fields', 'collection_id' => $col->id );
ob_start();
$cms->render_fields();
$html = ob_get_clean();
assert_true( false !== strpos( $html, 'Stage B Renamed' ), 'Fields screen names the collection' );
assert_true( false !== strpos( $html, 'Full Name' ), 'Fields table lists field labels' );
assert_true( false !== strpos( $html, 'field_ids[]' ), 'Reorder forms post field_ids' );
assert_true( false !== strpos( $html, 'Add Field' ), 'Add-field form renders' );

$_GET = array( 'page' => 'loopkit_cms_fields', 'collection_id' => $col->id, 'field_id' => $ids[0] );
ob_start();
$cms->render_fields();
$html = ob_get_clean();
assert_true( false !== strpos( $html, 'Edit Field' ), 'Field edit form renders for field_id' );
assert_true( false !== strpos( $html, 'name="field_id"' ), 'Field edit form carries field_id' );

$_GET = array( 'page' => 'loopkit_cms' );
ob_start();
$cms->render_dashboard();
$html = ob_get_clean();
assert_true( false !== strpos( $html, 'Stage B Renamed' ), 'Dashboard lists the collection' );

$_GET = array( 'page' => 'loopkit_cms_collections' );
ob_start();
$cms->render_collections();
$html = ob_get_clean();
assert_true( false !== strpos( $html, 'Stage B Renamed' ), 'Collections list shows the collection' );
assert_true( false !== strpos( $html, 'delete_collection' ), 'Row delete forms carry the action field' );
assert_true( false !== strpos( $html, 'name="nonce"' ), 'Row delete forms carry a nonce' );

// Delete one field, then the collection (cascade).
$_POST = array( 'field_id' => $ids[0] );
ob_start();
$invoke( 'do_delete_field' )->invoke( $cms );
$out = ob_get_clean();
assert_true( false !== strpos( $out, 'Field deleted' ), 'do_delete_field echoes success' );
assert_true( 1 === count( Loopkit_Module_CMS::get_fields( $col->id ) ), 'Field removed from DB' );

$_POST = array( 'collection_id' => $col->id );
ob_start();
$invoke( 'do_delete_collection' )->invoke( $cms );
$out = ob_get_clean();
assert_true( false !== strpos( $out, 'Collection deleted' ), 'do_delete_collection echoes success' );
assert_true( null === Loopkit_Module_CMS::get_collection( $col->id ), 'Collection removed from DB' );
global $wpdb;
$t_fields = Loopkit_Module_CMS::table_fields();
$orphans  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_fields} WHERE collection_id = %d", $col->id ) );
assert_true( 0 === $orphans, 'No orphaned field rows' );

// ── handle_actions safety ──────────────────────────────────────────────
echo "\n--- handle_actions safety ---\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST                     = array( 'something' => 'else' );
$cms->handle_actions();
assert_true( true, 'Non-CMS POST returns quietly (guard runs before the nonce check)' );
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST                     = array();

$lk_reached_end = true;
echo "\n=== Results: {$pass} passed, {$fail} failed ===\n";
exit( $fail > 0 ? 1 : 0 );
