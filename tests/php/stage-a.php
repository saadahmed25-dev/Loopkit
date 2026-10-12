<?php
/**
 * Stage A verification script.
 *
 * Exercises every CRUD method in Loopkit_Module_CMS and confirms the three
 * tables exist and behave correctly.
 *
 * Run:  c:\xampp\php\php.exe verify-stage-a.php
 */

// Command line only. These load WordPress and some of them delete things —
// one of them wipes the site to prove the wipe works. Reachable over the web,
// that is a URL that destroys a site.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "Loopkit tests run from the command line only.\n" );
}

// Bootstrap WordPress.
// ABSPATH is WordPress' own root, not this folder. Defining it here used
// to work only because the suite lived in the site root.
define( 'ABSPATH', dirname( __DIR__, 5 ) . '/' );
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
// Find WordPress from this file, not from where the suite was started.
require_once dirname( __DIR__, 5 ) . '/wp-load.php';

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

echo "=== Stage A: CMS Module Verification ===\n\n";

// 1. Enable the CMS module.
echo "--- Enabling CMS module ---\n";
Loopkit_Modules::set_enabled( 'cms', true );
assert_true( Loopkit_Modules::is_enabled( 'cms' ), 'CMS module is enabled' );

// Load the module class if not already loaded.
if ( ! class_exists( 'Loopkit_Module_CMS' ) ) {
    require_once LOOPKIT_PLUGIN_DIR . 'modules/cms/class-loopkit-module-cms.php';
}

// 2. Trigger install (table creation).
echo "\n--- Installing tables ---\n";
// Force re-install by clearing version.
delete_option( 'loopkit_cms_db' );
Loopkit_Module_CMS::install();

global $wpdb;
$t_col   = Loopkit_Module_CMS::table_collections();
$t_field = Loopkit_Module_CMS::table_fields();
$t_item  = Loopkit_Module_CMS::table_items();

assert_true( $wpdb->get_var( "SHOW TABLES LIKE '{$t_col}'" ) === $t_col, "Table {$t_col} exists" );
assert_true( $wpdb->get_var( "SHOW TABLES LIKE '{$t_field}'" ) === $t_field, "Table {$t_field} exists" );
assert_true( $wpdb->get_var( "SHOW TABLES LIKE '{$t_item}'" ) === $t_item, "Table {$t_item} exists" );
assert_true( get_option( 'loopkit_cms_db' ) === Loopkit_Module_CMS::DB_VERSION, 'DB version stored as ' . Loopkit_Module_CMS::DB_VERSION );

// Re-running install should be a no-op.
Loopkit_Module_CMS::install();
assert_true( true, 'Re-install is a no-op (no error)' );

// 3. Collection CRUD.
echo "\n--- Collection CRUD ---\n";
$col_id = Loopkit_Module_CMS::create_collection( 'Team Members', '', 'dashicons-groups' );
assert_true( is_int( $col_id ) && $col_id > 0, "Created collection id={$col_id}" );

$col = Loopkit_Module_CMS::get_collection( $col_id );
assert_true( $col && $col->name === 'Team Members', 'get_collection returns correct name' );
assert_true( $col->slug === 'team-members', 'Slug auto-generated: team-members' );
assert_true( $col->icon === 'dashicons-groups', 'Icon stored correctly' );

// Create a second collection with same name to test slug uniqueness.
$col_id2 = Loopkit_Module_CMS::create_collection( 'Team Members' );
$col2    = Loopkit_Module_CMS::get_collection( $col_id2 );
assert_true( $col2->slug === 'team-members-2', 'Duplicate name gets slug: team-members-2' );
Loopkit_Module_CMS::delete_collection( $col_id2 );

// Update.
Loopkit_Module_CMS::update_collection( $col_id, array( 'name' => 'Staff', 'icon' => 'dashicons-businessman' ) );
$col = Loopkit_Module_CMS::get_collection( $col_id );
assert_true( $col->name === 'Staff', 'Collection renamed to Staff' );
assert_true( $col->icon === 'dashicons-businessman', 'Icon updated' );

// Get by slug.
$col_by_slug = Loopkit_Module_CMS::get_collection_by_slug( 'team-members' );
assert_true( $col_by_slug && (int) $col_by_slug->id === $col_id, 'get_collection_by_slug works' );

// List.
$all = Loopkit_Module_CMS::get_collections();
assert_true( count( $all ) >= 1, 'get_collections returns at least 1' );
assert_true( isset( $all[0]->field_count ), 'get_collections includes field_count' );
assert_true( isset( $all[0]->item_count ), 'get_collections includes item_count' );

// 4. Field CRUD.
echo "\n--- Field CRUD ---\n";
$f1 = Loopkit_Module_CMS::create_field( $col_id, 'Full Name', '', 'text' );
$f2 = Loopkit_Module_CMS::create_field( $col_id, 'Role', '', 'select', array( 'options' => array( 'Developer', 'Designer', 'Manager' ) ) );
$f3 = Loopkit_Module_CMS::create_field( $col_id, 'Bio', '', 'long_text' );
$f4 = Loopkit_Module_CMS::create_field( $col_id, 'Photo', '', 'image' );
$f5 = Loopkit_Module_CMS::create_field( $col_id, 'Active', '', 'toggle' );
$f6 = Loopkit_Module_CMS::create_field( $col_id, 'Email', '', 'email' );
$f7 = Loopkit_Module_CMS::create_field( $col_id, 'Hire Date', '', 'date' );

assert_true( $f1 && $f2 && $f3 && $f4 && $f5 && $f6 && $f7, 'Created 7 fields' );

$field1 = Loopkit_Module_CMS::get_field( $f1 );
assert_true( $field1->label === 'Full Name', 'Field label correct' );
assert_true( $field1->field_key === 'full_name', 'Field key auto-slugged: full_name' );
assert_true( $field1->type === 'text', 'Field type: text' );

$field2 = Loopkit_Module_CMS::get_field( $f2 );
assert_true( is_array( $field2->settings ) && isset( $field2->settings['options'] ), 'Field settings JSON decoded' );
assert_true( count( $field2->settings['options'] ) === 3, 'Select field has 3 options' );

$fields = Loopkit_Module_CMS::get_fields( $col_id );
assert_true( count( $fields ) === 7, 'get_fields returns 7' );
assert_true( (int) $fields[0]->sort_order < (int) $fields[6]->sort_order, 'Fields are sorted by sort_order' );

// Update field.
Loopkit_Module_CMS::update_field( $f3, array( 'label' => 'Biography', 'type' => 'rich_text' ) );
$f3_updated = Loopkit_Module_CMS::get_field( $f3 );
assert_true( $f3_updated->label === 'Biography', 'Field label updated' );
assert_true( $f3_updated->type === 'rich_text', 'Field type changed to rich_text' );

// Reorder.
$new_order = array( $f7, $f6, $f5, $f4, $f3, $f2, $f1 );
Loopkit_Module_CMS::reorder_fields( $col_id, $new_order );
$fields_reordered = Loopkit_Module_CMS::get_fields( $col_id );
assert_true( (int) $fields_reordered[0]->id === $f7, 'After reorder, first field is f7' );
assert_true( (int) $fields_reordered[6]->id === $f1, 'After reorder, last field is f1' );

// Restore original order.
Loopkit_Module_CMS::reorder_fields( $col_id, array( $f1, $f2, $f3, $f4, $f5, $f6, $f7 ) );

// Duplicate field key test.
$f_dup = Loopkit_Module_CMS::create_field( $col_id, 'Full Name' );
$f_dup_obj = Loopkit_Module_CMS::get_field( $f_dup );
assert_true( $f_dup_obj->field_key === 'full_name_2', 'Duplicate label gets key: full_name_2' );
Loopkit_Module_CMS::delete_field( $f_dup );

// 5. Item CRUD.
echo "\n--- Item CRUD ---\n";
$item1_id = Loopkit_Module_CMS::create_item( $col_id, array(
    'full_name' => 'Alice Johnson',
    'role'      => 'Developer',
    'bio'       => 'Backend engineer with 10 years of experience.',
    'active'    => true,
    'email'     => 'alice@example.com',
    'hire_date' => '2020-03-15',
) );
assert_true( is_int( $item1_id ) && $item1_id > 0, "Created item 1 id={$item1_id}" );

$item1 = Loopkit_Module_CMS::get_item( $item1_id );
assert_true( is_array( $item1->data ), 'Item data is decoded array' );
assert_true( $item1->data['full_name'] === 'Alice Johnson', 'Item data: full_name correct' );
assert_true( $item1->data['role'] === 'Developer', 'Item data: role correct' );
assert_true( $item1->status === 'published', 'Item status: published' );

// Create more items.
$item2_id = Loopkit_Module_CMS::create_item( $col_id, array(
    'full_name' => 'Bob Smith',
    'role'      => 'Designer',
    'email'     => 'bob@example.com',
    'active'    => true,
) );
$item3_id = Loopkit_Module_CMS::create_item( $col_id, array(
    'full_name' => 'Charlie Brown',
    'role'      => 'Manager',
    'email'     => 'charlie@example.com',
    'active'    => false,
), 'draft' );

assert_true( $item2_id && $item3_id, 'Created items 2 and 3' );

// Count.
$total = Loopkit_Module_CMS::count_items( $col_id );
assert_true( $total === 3, "count_items total = {$total} (expected 3)" );
$published = Loopkit_Module_CMS::count_items( $col_id, 'published' );
assert_true( $published === 2, "count_items published = {$published} (expected 2)" );
$drafts = Loopkit_Module_CMS::count_items( $col_id, 'draft' );
assert_true( $drafts === 1, "count_items draft = {$drafts} (expected 1)" );

// List with default args.
$result = Loopkit_Module_CMS::get_items( $col_id );
assert_true( count( $result['items'] ) === 3, 'get_items returns 3 items' );
assert_true( $result['total'] === 3, 'get_items total = 3' );
assert_true( $result['pages'] === 1, 'get_items pages = 1' );

// Filter by status.
$pub_result = Loopkit_Module_CMS::get_items( $col_id, array( 'status' => 'published' ) );
assert_true( count( $pub_result['items'] ) === 2, 'Status filter: 2 published' );

// Search.
$search_result = Loopkit_Module_CMS::get_items( $col_id, array( 'search' => 'Alice' ) );
assert_true( count( $search_result['items'] ) === 1, "Search 'Alice' returns 1 item" );
assert_true( $search_result['items'][0]->data['full_name'] === 'Alice Johnson', "Search result is Alice" );

// Sort by field_key.
$sorted = Loopkit_Module_CMS::get_items( $col_id, array( 'orderby' => 'full_name', 'order' => 'ASC' ) );
assert_true( $sorted['items'][0]->data['full_name'] === 'Alice Johnson', 'Sort ASC by full_name: first is Alice' );
assert_true( $sorted['items'][2]->data['full_name'] === 'Charlie Brown', 'Sort ASC by full_name: last is Charlie' );

// Pagination.
$page1 = Loopkit_Module_CMS::get_items( $col_id, array( 'per_page' => 2, 'page' => 1 ) );
$page2 = Loopkit_Module_CMS::get_items( $col_id, array( 'per_page' => 2, 'page' => 2 ) );
assert_true( count( $page1['items'] ) === 2, 'Page 1: 2 items' );
assert_true( count( $page2['items'] ) === 1, 'Page 2: 1 item' );
assert_true( $page1['pages'] === 2, 'Total pages = 2' );

// Update item.
Loopkit_Module_CMS::update_item( $item1_id, array(
    'data'   => array( 'role' => 'Senior Developer', 'new_field' => 'extra' ),
    'status' => 'draft',
) );
$item1_upd = Loopkit_Module_CMS::get_item( $item1_id );
assert_true( $item1_upd->data['role'] === 'Senior Developer', 'Item data merged: role updated' );
assert_true( $item1_upd->data['full_name'] === 'Alice Johnson', 'Item data merged: full_name preserved' );
assert_true( $item1_upd->data['new_field'] === 'extra', 'Item data merged: new field added' );
assert_true( $item1_upd->status === 'draft', 'Item status changed to draft' );

// Duplicate.
$dup_id = Loopkit_Module_CMS::duplicate_item( $item2_id );
assert_true( is_int( $dup_id ) && $dup_id > 0, "Duplicated item id={$dup_id}" );
$dup = Loopkit_Module_CMS::get_item( $dup_id );
assert_true( $dup->data['full_name'] === 'Bob Smith', 'Duplicate has same data' );
assert_true( (int) $dup->id !== $item2_id, 'Duplicate has different id' );

// Delete item.
$del_ok = Loopkit_Module_CMS::delete_item( $dup_id );
assert_true( $del_ok, 'delete_item returns true' );
$del_check = Loopkit_Module_CMS::get_item( $dup_id );
assert_true( $del_check === null, 'Deleted item is null' );

// 6. Delete field — should strip key from items.
echo "\n--- Delete field strips data ---\n";
$items_before = Loopkit_Module_CMS::get_items( $col_id );
$has_email_before = isset( $items_before['items'][0]->data['email'] );
assert_true( $has_email_before, 'Before delete_field(email): items have email key' );

Loopkit_Module_CMS::delete_field( $f6 ); // email field
$items_after = Loopkit_Module_CMS::get_items( $col_id );
$has_email_after = false;
foreach ( $items_after['items'] as $it ) {
    if ( isset( $it->data['email'] ) ) {
        $has_email_after = true;
    }
}
assert_true( ! $has_email_after, 'After delete_field(email): no items have email key' );

$fields_after = Loopkit_Module_CMS::get_fields( $col_id );
assert_true( count( $fields_after ) === 6, 'Field count is now 6' );

// 7. Delete collection cascades.
echo "\n--- Delete collection cascade ---\n";
$del_col = Loopkit_Module_CMS::delete_collection( $col_id );
assert_true( $del_col, 'delete_collection returns true' );
assert_true( Loopkit_Module_CMS::get_collection( $col_id ) === null, 'Collection gone' );
$orphan_fields = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$t_field} WHERE collection_id = %d", $col_id
) );
assert_true( (int) $orphan_fields === 0, 'All fields deleted' );
$orphan_items = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$t_item} WHERE collection_id = %d", $col_id
) );
assert_true( (int) $orphan_items === 0, 'All items deleted' );

// 8. Edge cases.
echo "\n--- Edge cases ---\n";
$bad_create = Loopkit_Module_CMS::create_collection( '' );
assert_true( $bad_create === false, 'Empty name returns false' );

$bad_field = Loopkit_Module_CMS::create_field( 0, 'Test' );
assert_true( $bad_field === false, 'Field with collection_id=0 returns false' );

$bad_item = Loopkit_Module_CMS::create_item( 0 );
assert_true( $bad_item === false, 'Item with collection_id=0 returns false' );

$bad_delete = Loopkit_Module_CMS::delete_item( 999999 );
assert_true( $bad_delete === false, 'Delete non-existent item returns false' );

$bad_update = Loopkit_Module_CMS::update_item( 999999, array( 'status' => 'draft' ) );
assert_true( $bad_update === false, 'Update non-existent item returns false' );

// Summary.
echo "\n=== Results: {$pass} passed, {$fail} failed ===\n";
exit( $fail > 0 ? 1 : 0 );
