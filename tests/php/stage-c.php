<?php
/**
 * Stage C verification: the CMS items table (spreadsheet screen).
 *
 * Boots WordPress, renders the Items screen, and mutation-verifies the
 * save path: every write is re-read from the database afterwards, and
 * row delete/duplicate are proven (from the rendered markup) to act on
 * exactly the row that was clicked.
 *
 * Run:  c:\xampp\php\php.exe verify-stage-c.php
 */

// Command line only. These load WordPress and some of them delete things —
// one of them wipes the site to prove the wipe works. Reachable over the web,
// that is a URL that destroys a site.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "Loopkit tests run from the command line only.\n" );
}

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=loopkit_cms_items';
// Find WordPress from this file, not from where the suite was started.
require_once dirname( __DIR__, 5 ) . '/wp-load.php';
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

function has( $haystack, $needle, $label ) {
    assert_true( false !== strpos( (string) $haystack, $needle ), $label );
}

/**
 * Pull one row's HTML out of the rendered table by a marker value that
 * only that row contains — the identity check for delete/duplicate.
 */
function row_html_for( $html, $marker ) {
    $pos = strpos( $html, $marker );
    if ( false === $pos ) {
        return '';
    }
    $start = strrpos( substr( $html, 0, $pos ), '<tr data-id="' );
    if ( false === $start ) {
        return '';
    }
    $end = strpos( $html, '</tr>', $pos );
    if ( false === $end ) {
        return '';
    }
    return substr( $html, $start, $end - $start + 5 );
}

echo "=== Stage C: CMS Items Table Verification ===\n\n";

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

$admin = get_user_by( 'id', 1 );
if ( ! $admin ) {
    $found = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
    $admin = $found ? $found[0] : null;
}
if ( $admin ) {
    wp_set_current_user( $admin->ID );
}
assert_true( current_user_can( 'manage_options' ), 'Admin user set' );

// ── Registration: menu, callbacks, assets ──────────────────────────────
echo "\n--- Registration ---\n";
$cms->register_menu();
$cms->register_submenus();
global $menu, $submenu;
$subs = array();
foreach ( isset( $submenu['loopkit_cms'] ) ? $submenu['loopkit_cms'] : array() as $item ) {
    $subs[] = $item[2];
}
assert_true( in_array( 'loopkit_cms_items', $subs, true ), 'Submenu loopkit_cms_items registered' );
foreach ( array( 'render_items', 'render_item_row', 'render_item_cell', 'render_item_editor', 'ajax_save_item', 'ajax_delete_item', 'ajax_duplicate_item', 'ajax_inline_update', 'save_cell', 'save_item_data', 'sanitize_field_value' ) as $fn ) {
    assert_true( method_exists( 'Loopkit_Module_CMS', $fn ), "{$fn} exists" );
}

$_GET['page'] = 'loopkit_cms_items';
$cms->enqueue_admin_assets( 'any-hook' );
assert_true( wp_script_is( 'loopkit-cms-admin', 'enqueued' ), 'cms-admin.js enqueued on the items screen' );
assert_true(
    wp_script_is( 'media-views', 'enqueued' ) || wp_script_is( 'media-editor', 'enqueued' ),
    'media library enqueued on the items screen'
);
$loc = wp_scripts()->get_data( 'loopkit-cms-admin', 'data' );
assert_true( is_string( $loc ) && false !== strpos( $loc, '"choose"' ), 'messages include choose (media frame)' );
wp_dequeue_script( 'loopkit-cms-admin' );

// ── Fixture: one collection covering every cell-editable type ──────────
echo "\n--- Fixture collection + fields ---\n";

// Clean up any leftover fixtures first.
$cleanup_ids = array();
$cleanup_cols = Loopkit_Module_CMS::get_collections();
foreach ( $cleanup_cols as $c ) {
    $cleanup_ids[] = (int) $c->id;
}
foreach ( $cleanup_ids as $id ) {
    Loopkit_Module_CMS::delete_collection( $id );
}

$col_id = Loopkit_Module_CMS::create_collection( 'Stage C Verify', 'stage-c-verify-' . uniqid(), 'dashicons-id' );
assert_true( null !== $col_id && (int) $col_id > 0, 'Verify collection created' );

$field_ids = array();
$field_ids['full_name'] = Loopkit_Module_CMS::create_field( $col_id, 'Full Name', 'full_name', 'text' );
$field_ids['age']       = Loopkit_Module_CMS::create_field( $col_id, 'Age', 'age', 'number' );
$field_ids['joined']    = Loopkit_Module_CMS::create_field( $col_id, 'Joined', 'joined', 'date' );
$field_ids['active']    = Loopkit_Module_CMS::create_field( $col_id, 'Active', 'active', 'toggle' );
$field_ids['team']      = Loopkit_Module_CMS::create_field( $col_id, 'Team', 'team', 'select', array( 'options' => array( 'Red', 'Blue', 'Green' ) ) );
$field_ids['photo']     = Loopkit_Module_CMS::create_field( $col_id, 'Photo', 'photo', 'image' );
$field_ids['bio']       = Loopkit_Module_CMS::create_field( $col_id, 'Bio', 'bio', 'long_text' );
$field_ids['notes']     = Loopkit_Module_CMS::create_field( $col_id, 'Notes', 'notes', 'rich_text' );
assert_true( count( array_filter( $field_ids ) ) === 8, 'All 8 field types created' );

$x_data = array(
    'full_name' => 'Alpha marker X',
    'age'       => 30,
    'joined'    => '2024-01-15',
    'active'    => '1',
    'team'      => 'Red',
    'photo'     => 'https://example.test/a.png',
    'bio'       => 'Alpha long bio',
    'notes'     => '<p>Alpha notes</p>',
);
$y_data = array(
    'full_name' => 'Beta marker Y',
    'age'       => 41,
    'joined'    => '2023-11-02',
    'active'    => '0',
    'team'      => 'Blue',
    'photo'     => 'https://example.test/b.png',
    'bio'       => 'Beta long bio',
    'notes'     => '<p>Beta notes</p>',
);
$x = Loopkit_Module_CMS::create_item( $col_id, $x_data );
$y = Loopkit_Module_CMS::create_item( $col_id, $y_data );
assert_true( (bool) $x && (bool) $y && $x !== $y, 'Two seed items created' );
assert_true( Loopkit_Module_CMS::count_items( $col_id ) === 2, 'Item count is 2' );

// ── Mutation: single-cell saves survive a reload ───────────────────────
echo "\n--- Cell save (mutation + reload) ---\n";

// 1. Text edit → fresh read proves it survived.
$r = Loopkit_Module_CMS::save_cell( $x, 'full_name', 'Alpha renamed 1' );
assert_true( $r['ok'], 'Text cell save returns ok' );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( $fresh && 'Alpha renamed 1' === $fresh->data['full_name'], 'Reload shows Alpha renamed 1' );

// 2. Edit again — same row updates in place, nothing else moves.
$r = Loopkit_Module_CMS::save_cell( $x, 'full_name', 'Alpha renamed 2' );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( $r['ok'] && 'Alpha renamed 2' === $fresh->data['full_name'], 'Second save updates (id unchanged)' );
assert_true( Loopkit_Module_CMS::count_items( $col_id ) === 2, 'Cell saves never create rows' );
assert_true( Loopkit_Module_CMS::get_item( $y )->data == $y_data, 'The other row is untouched by cell saves' );

// 3. Merge safety: every other key of X still holds its original value.
$fresh = Loopkit_Module_CMS::get_item( $x );
$others_ok = 'Alpha long bio' === $fresh->data['bio']
    && '<p>Alpha notes</p>' === $fresh->data['notes']
    && 'https://example.test/a.png' === $fresh->data['photo']
    && 'Red' === $fresh->data['team'];
assert_true( $others_ok, 'Only the addressed key changed (merge, not replace)' );

// 4. Number: valid → stored as a real JSON number; invalid → rejected, unchanged.
$r  = Loopkit_Module_CMS::save_cell( $x, 'age', '42.7' );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( $r['ok'] && is_float( $fresh->data['age'] ) && 42.7 == $fresh->data['age'], 'Decimal stored as number after reload' );
$r  = Loopkit_Module_CMS::save_cell( $x, 'age', '7' );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( $r['ok'] && 7 === $fresh->data['age'], 'Integer stored as int after reload' );
$before = $fresh->data;
$r = Loopkit_Module_CMS::save_cell( $x, 'age', 'abc' );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( ! $r['ok'], 'Non-numeric input rejected' );
assert_true( $fresh->data == $before, 'Rejected number left the row unchanged' );

// 5. Date: valid round-trip; impossible date rejected with no write.
$r = Loopkit_Module_CMS::save_cell( $x, 'joined', '2026-04-01' );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( $r['ok'] && '2026-04-01' === $fresh->data['joined'], 'Date saved and reloaded' );
$before = $fresh->data;
$r = Loopkit_Module_CMS::save_cell( $x, 'joined', '2026-99-99' );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( ! $r['ok'] && $fresh->data == $before, 'Impossible date rejected, row unchanged' );

// 6. Toggle: 0/1 round-trip; garbage rejected.
$r = Loopkit_Module_CMS::save_cell( $x, 'active', '0' );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( $r['ok'] && '0' === $fresh->data['active'], 'Toggle off saved and reloaded' );
$before = $fresh->data;
$r = Loopkit_Module_CMS::save_cell( $x, 'active', 'maybe' );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( ! $r['ok'] && $fresh->data == $before, 'Garbage toggle rejected, row unchanged' );

// 7. Select: allowed option sticks; unknown option rejected.
$r = Loopkit_Module_CMS::save_cell( $x, 'team', 'Blue' );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( $r['ok'] && 'Blue' === $fresh->data['team'], 'Select option saved and reloaded' );
$before = $fresh->data;
$r = Loopkit_Module_CMS::save_cell( $x, 'team', 'Purple' );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( ! $r['ok'] && $fresh->data == $before, 'Unknown option rejected, row unchanged' );

// 8. Image: URL sticks; a value esc_url_raw would blank out is REJECTED,
//    not stored as empty — an invalid post can never erase the photo.
$r = Loopkit_Module_CMS::save_cell( $x, 'photo', 'https://example.test/new.png' );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( $r['ok'] && 'https://example.test/new.png' === $fresh->data['photo'], 'Image URL saved and reloaded' );
$before = $fresh->data;
$r = Loopkit_Module_CMS::save_cell( $x, 'photo', 'javascript:alert(1)' );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( ! $r['ok'] && $fresh->data == $before, 'Unsafe URL rejected, photo NOT erased' );

// 9. Unknown key / unknown row → rejected without writes.
$before = Loopkit_Module_CMS::get_item( $x )->data;
$r = Loopkit_Module_CMS::save_cell( $x, 'ghost_key', 'x' );
assert_true( ! $r['ok'] && Loopkit_Module_CMS::get_item( $x )->data == $before, 'Unknown field key rejected' );
$r = Loopkit_Module_CMS::save_cell( 99999999, 'full_name', 'x' );
assert_true( ! $r['ok'], 'Unknown row id rejected' );

// ── Mutation: full-row save (single-item editor path) ──────────────────
echo "\n--- Full row save (mutation + reload) ---\n";

$final = array(
    'full_name' => 'Alpha final',
    'age'       => 55,
    'joined'    => '2025-06-01',
    'active'    => '0',
    'team'      => 'Green',
    'photo'     => 'https://example.test/a2.png',
    'bio'       => "Line one of bio\nLine two of bio",
    'notes'     => '<p><strong>Rich</strong> saved</p>',
);
$r     = Loopkit_Module_CMS::save_item_data( $col_id, $x, $final );
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( $r['ok'] && $fresh, 'Full save returns ok' );
assert_true( 'Alpha final' === $fresh->data['full_name'] && 55 === $fresh->data['age'], 'Text + number survive reload' );
assert_true( "Line one of bio\nLine two of bio" === $fresh->data['bio'], 'Long text (newlines) survives reload' );
assert_true( '<p><strong>Rich</strong> saved</p>' === $fresh->data['notes'], 'Rich text HTML survives reload (wp_kses_post keeps <strong>)' );
assert_true( '0' === $fresh->data['active'] && 'Green' === $fresh->data['team'], 'Toggle + select survive reload' );

// All-or-nothing: one bad value among valid ones writes NOTHING.
$before = $fresh->data;
$r      = Loopkit_Module_CMS::save_item_data(
    $col_id,
    $x,
    array(
        'full_name' => 'Should not persist',
        'team'      => 'Purple-not-an-option',
    )
);
$fresh = Loopkit_Module_CMS::get_item( $x );
assert_true( ! $r['ok'], 'Mixed valid/invalid full save rejected' );
assert_true( 'Alpha final' === $fresh->data['full_name'] && $fresh->data == $before, 'Nothing written on partial failure (all-or-nothing)' );

// Unknown key and wrong collection are both refused.
$before = $fresh->data;
$r      = Loopkit_Module_CMS::save_item_data( $col_id, $x, array( 'ghost' => 'x' ) );
assert_true( ! $r['ok'] && Loopkit_Module_CMS::get_item( $x )->data == $before, 'Unknown key refused with no write' );
$other_col = Loopkit_Module_CMS::create_collection( 'Stage C Other', 'stage-c-other', 'dashicons-tag' );
$r         = Loopkit_Module_CMS::save_item_data( $other_col, $x, array( 'full_name' => 'Cross-write' ) );
assert_true( ! $r['ok'] && Loopkit_Module_CMS::get_item( $x )->data == $before, 'Row cannot be saved through another collection' );

// Create path (the add-row button).
$r     = Loopkit_Module_CMS::save_item_data( $col_id, 0, array( 'full_name' => 'Gamma created', 'age' => 10 ) );
$gamma = Loopkit_Module_CMS::get_item( $r['item_id'] );
assert_true( $r['ok'] && $r['created'] && $gamma && 'Gamma created' === $gamma->data['full_name'], 'Add-row creates a readable row' );
assert_true( Loopkit_Module_CMS::count_items( $col_id ) === 3, 'Count now 3' );
Loopkit_Module_CMS::delete_item( $r['item_id'] ); // tidy up before identity tests

// ── Row identity: the rendered button names exactly one row ────────────
echo "\n--- Row identity (rendered markup → database) ---\n";
$_GET = array( 'page' => 'loopkit_cms_items', 'collection_id' => $col_id );
ob_start();
$cms->render_items();
$html = ob_get_clean();

$row_x = row_html_for( $html, 'Alpha final' );
assert_true( '' !== $row_x, 'Alpha row found in rendered table' );
preg_match( '#data-id="(\d+)"#', $row_x, $m_id );
preg_match( '#value="delete:(\d+)"#', $row_x, $m_row );
preg_match( '#value="duplicate:(\d+)"#', $row_x, $m_dup );
assert_true( isset( $m_id[1] ) && (int) $m_id[1] === $x, 'Row data-id matches item X' );
assert_true( isset( $m_row[1] ) && (int) $m_row[1] === $x, 'Delete button on Alpha row carries the Alpha id only' );
assert_true( isset( $m_dup[1] ) && (int) $m_dup[1] === $x, 'Duplicate button on Alpha row carries the Alpha id only' );

$row_y = row_html_for( $html, 'Beta marker Y' );
preg_match( '#value="delete:(\d+)"#', $row_y, $m_del_y );
assert_true( isset( $m_del_y[1] ) && (int) $m_del_y[1] === $y, 'Beta row delete button carries the Beta id' );

// Delete EXACTLY the id the Alpha button carries.
$y_before = Loopkit_Module_CMS::get_item( $y )->data;
Loopkit_Module_CMS::delete_item( (int) $m_row[1] );
assert_true( null === Loopkit_Module_CMS::get_item( $x ), 'Clicked row (X) is gone' );
assert_true( Loopkit_Module_CMS::get_item( $y ) && Loopkit_Module_CMS::get_item( $y )->data == $y_before, 'The other row survives with full data' );
assert_true( Loopkit_Module_CMS::count_items( $col_id ) === 1, 'Count dropped by exactly one' );

// Duplicate EXACTLY the id the Beta button carries.
$new_id = Loopkit_Module_CMS::duplicate_item( (int) $m_del_y[1] );
$dup    = Loopkit_Module_CMS::get_item( $new_id );
assert_true( (bool) $new_id && $new_id !== $y && $dup->data == $y_before, 'Duplicate: new id, identical data, original untouched' );
assert_true( Loopkit_Module_CMS::count_items( $col_id ) === 2, 'Count back to 2 after duplicate' );
Loopkit_Module_CMS::delete_item( $new_id );

// ── Screen structure (rendered markup) ─────────────────────────────────
echo "\n--- Screen render ---\n";
$_GET = array( 'page' => 'loopkit_cms_items', 'collection_id' => $col_id );
ob_start();
$cms->render_items();
$html = ob_get_clean();

has( $html, 'lk-cms-items-screen', 'Screen root class present' );
has( $html, 'data-collection="' . $col_id . '"', 'Screen carries its collection id' );
has( $html, 'Items — Stage C Verify', 'Title shows collection' );
has( $html, 'page=loopkit_cms_fields', 'Fields link in the header' );
has( $html, 'name="s"', 'Search input present' );
has( $html, 'name="per_page"', 'Rows-per-page select present' );
has( $html, 'value="100"', 'Per-page choice 100 present' );
has( $html, 'orderby=full_name', 'Full Name column is sortable' );
has( $html, 'orderby=age', 'Age column is sortable' );
has( $html, 'lk-items-table', 'Table present' );
has( $html, '<tr data-id="' . $y . '">', 'Beta row rendered' );
has( $html, 'lk-cms-rownum">1<', 'Row number starts at 1' );

// Cells by type.
has( $html, 'name="cells[' . $y . '][full_name]"', 'Text cell input present' );
has( $html, 'value="Beta marker Y"', 'Text cell holds the stored value' );
has( $html, 'type="number"', 'Number cell uses a number input' );
has( $html, 'name="cells[' . $y . '][joined]"', 'Date cell input present' );
has( $html, 'type="date"', 'Date cell uses a date picker input' );
has( $html, 'lk-cms-cell--toggle', 'Toggle cell present' );
has( $html, 'lk-toggle-wrap', 'Toggle cell uses the restyled switch' );
assert_true( 1 === preg_match( '#<option value="Blue"\s+selected#', $html ), 'Select cell preselects the stored option' );
has( $html, 'lk-cms-img-btn', 'Image cell has the media button' );
has( $html, 'src="https://example.test/b.png"', 'Image cell previews the stored photo' );
assert_true( 2 <= substr_count( $html, 'lk-cms-cell-textarea' ), 'Long/rich cells type inline (2+ textareas)' );
has( $html, '&lt;p&gt;Beta notes&lt;/p&gt;', 'Rich cell carries the full value escaped' );

$row_y2 = row_html_for( $html, 'Beta marker Y' );
assert_true( false !== strpos( $row_y2, '<textarea', 0 ), 'Long fields type inline in the row' );
assert_true( false !== strpos( $row_y2, 'lk-cms-cell-expand' ), 'Those cells still open the item editor' );

// Row actions + bulk fallback.
has( $html, 'lk-cms-edit-item', 'Edit link per row' );
has( $html, 'name="row_action" value="duplicate:', 'Duplicate button per row' );
has( $html, 'name="row_action" value="delete:', 'Delete button per row' );
has( $html, 'value="new:0"', 'Add-row button at the bottom' );
has( $html, 'value="save_cells"', 'Bulk Save-changes button (no-JS path)' );
has( $html, 'name="nonce"', 'Table form carries the nonce' );

// Single-item editor panel (blank template, closed).
has( $html, 'id="lk-cms-item-editor"', 'Item editor panel present' );
assert_true( false !== strpos( $html, 'id="lk-cms-item-editor" class="lk-cms-editor"' ), 'Editor starts closed on a plain table load' );
has( $html, 'name="loopkit_cms_action" value="save_item"', 'Editor form posts save_item' );
has( $html, 'name="data[bio]"', 'Long text has a textarea in the editor' );
has( $html, 'name="data[notes]"', 'Rich text has an editor textarea in the editor' );
has( $html, 'lk-cms-editor-media', 'Editor image field has the media button' );
has( $html, 'lk-toggle-row', 'Editor toggle uses the switch row' );

// Direct ?item_id link: panel renders server-side already open (no-JS path).
$_GET = array( 'page' => 'loopkit_cms_items', 'collection_id' => $col_id, 'item_id' => $y );
ob_start();
$cms->render_items();
$html_open = ob_get_clean();
has( $html_open, 'class="lk-cms-editor is-open"', 'Direct item_id renders the editor open' );
has( $html_open, 'Edit row #' . $y, 'Editor titled for the row' );
has( $html_open, 'name="item_id" value="' . $y . '"', 'Editor hidden field targets that row' );
assert_true( false !== strpos( $html_open, '>Beta long bio</textarea>' ), 'Editor textarea holds the stored long text' );

// ── Paging, sorting, searching with 60 rows ────────────────────────────
echo "\n--- Paging / sorting / searching (60 rows) ---\n";
$pg_id = Loopkit_Module_CMS::create_collection( 'Stage C Pager', 'stage-c-pager-' . uniqid(), 'dashicons-portfolio' );
assert_true( null !== $pg_id && (int) $pg_id > 0, 'Pager collection created' );
Loopkit_Module_CMS::create_field( $pg_id, 'Label', 'label', 'text' );
for ( $i = 1; $i <= 60; $i++ ) {
    $label = sprintf( 'Row %03d', $i );
    if ( 13 === $i || 47 === $i ) {
        $label .= ' ZEBRA99';
    }
    Loopkit_Module_CMS::create_item( $pg_id, array( 'label' => $label ) );
}
assert_true( Loopkit_Module_CMS::count_items( $pg_id ) === 60, '60 pager rows created' );

$render = function ( $args ) use ( $cms ) {
    $_GET = array_merge( array( 'page' => 'loopkit_cms_items' ), $args );
    ob_start();
    $cms->render_items();
    return ob_get_clean();
};

// Default: newest first, 50 per page (the actual default).
$h = $render( array( 'collection_id' => $pg_id ) );
assert_true( false !== strpos( $h, 'Row 060' ), 'Default page 1 shows the newest row' );
assert_true( false === strpos( $h, 'Row 001' ), 'Default page 1 hides the oldest row' );
// Default is 50 per page, so page 1 shows 50 rows.
assert_true( 50 === substr_count( $h, '<tr data-id=' ), '50 data rows at default per_page 50' );
assert_true( false !== strpos( $h, 'tablenav-pages' ), 'Pagination block present (3 pages)' );
assert_true( false !== strpos( $h, 'paged=2' ), 'Page 2 link present' );
assert_true( false !== strpos( $h, 'collection_id=' . $pg_id ), 'Pagination keeps the collection id' );

// Page 2 with default per_page=50: rows 51-60.
$h = $render( array( 'collection_id' => $pg_id, 'paged' => 2 ) );
assert_true( false !== strpos( $h, 'Row 010' ), 'Page 2 starts at row 51 (id DESC, per_page 50)' );
assert_true( false === strpos( $h, 'Row 060' ), 'Page 2 does not repeat page 1 rows' );
assert_true( false !== strpos( $h, 'Row 001' ), 'Page 2 includes oldest rows' );
assert_true( false !== strpos( $h, 'lk-cms-rownum">51<' ), 'Row numbers continue at 51 on page 2' );

// Sort by the Label column both ways.
$h = $render( array( 'collection_id' => $pg_id, 'orderby' => 'label', 'order' => 'ASC' ) );
has( $h, 'Row 001', 'ASC by Label puts Row 001 on page 1' );
assert_true( false === strpos( $h, 'Row 060' ), 'ASC by Label omits Row 060 from page 1' );
assert_true( strpos( $h, 'Row 001' ) < strpos( $h, 'Row 002' ), 'ASC rows appear in ascending order' );

$h = $render( array( 'collection_id' => $pg_id, 'orderby' => 'label', 'order' => 'DESC' ) );
has( $h, 'Row 060', 'DESC by Label puts Row 060 on page 1' );
assert_true( false === strpos( $h, 'Row 001' ), 'DESC by Label omits Row 001 from page 1' );

// Search narrows to the matching rows only, and sort links keep the term.
$h = $render( array( 'collection_id' => $pg_id, 's' => 'ZEBRA99' ) );
assert_true( 2 === substr_count( $h, '<tr data-id=' ), 'Search narrows 60 rows to the 2 matches' );
has( $h, 'Row 013 ZEBRA99', 'First ZEBRA row present' );
has( $h, 'Row 047 ZEBRA99', 'Second ZEBRA row present' );
assert_true( false === strpos( $h, 'Row 001' ), 'Non-matching row absent from search' );
assert_true( 1 === preg_match( '#href="[^"]*s=ZEBRA99[^"]*orderby=label#', $h ), 'Sort links preserve the search term' );

// Page size 100 shows everything.
$h = $render( array( 'collection_id' => $pg_id, 'per_page' => 100 ) );
assert_true( 60 === substr_count( $h, '<tr data-id=' ), 'per_page=100 returns all 60 rows' );
has( $h, 'Row 001', 'per_page=100 includes the oldest row' );

// A hostile/unknown orderby falls back to id instead of raw SQL.
$h = $render( array( 'collection_id' => $pg_id, 'orderby' => 'rm_inject', 'order' => 'ASC' ) );
has( $h, 'Row 001', 'Unknown orderby fallback is deterministic' );

// ── Cleanup ────────────────────────────────────────────────────────────
echo "\n--- Cleanup ---\n";
assert_true( Loopkit_Module_CMS::delete_collection( $col_id ), 'Verify collection deleted' );
assert_true( Loopkit_Module_CMS::delete_collection( $other_col ), 'Other collection deleted' );
assert_true( Loopkit_Module_CMS::delete_collection( $pg_id ), 'Pager collection deleted' );
assert_true( null === Loopkit_Module_CMS::get_collection( $col_id ), 'Collection row gone' );
assert_true( Loopkit_Module_CMS::count_items( $pg_id ) === 0, 'No orphan items left behind' );

echo "\n=== Stage C: {$pass} passed, {$fail} failed ===\n";
exit( $fail > 0 ? 1 : 0 );




