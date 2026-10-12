<?php
/**
 * Loopkit CMS module.
 *
 * A content system ordinary people can use — Collections, Fields, Items —
 * without seeing "post type", "taxonomy", "field group" or "meta".
 *
 * Own database tables, own field system, own editor, own dynamic tags.
 * Switched on in Loopkit > Tools > Modules; this file is only loaded when it is on.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Module_CMS {

    const DB_VERSION = '1.1';

    /* ─────────────────────────────────────────────────────────
     *  Table helpers
     * ───────────────────────────────────────────────────────── */

    /**
     * @return string
     */
    public static function table_collections() {
        global $wpdb;
        return $wpdb->prefix . 'lk_cms_collections';
    }

    /**
     * @return string
     */
    public static function table_fields() {
        global $wpdb;
        return $wpdb->prefix . 'lk_cms_fields';
    }

    /**
     * @return string
     */
    public static function table_items() {
        global $wpdb;
        return $wpdb->prefix . 'lk_cms_items';
    }

    /* ─────────────────────────────────────────────────────────
     *  Install / upgrade
     * ───────────────────────────────────────────────────────── */

    /**
     * Create or upgrade the three CMS tables.
     */
    public static function install() {
        global $wpdb;

        if ( get_option( 'loopkit_cms_db' ) === self::DB_VERSION ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $t_collections = self::table_collections();
        $t_fields      = self::table_fields();
        $t_items       = self::table_items();

        dbDelta( "CREATE TABLE {$t_collections} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL DEFAULT '',
            slug varchar(191) NOT NULL DEFAULT '',
            icon varchar(64) NOT NULL DEFAULT 'dashicons-database',
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$t_fields} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            collection_id bigint(20) unsigned NOT NULL DEFAULT 0,
            label varchar(191) NOT NULL DEFAULT '',
            field_key varchar(191) NOT NULL DEFAULT '',
            type varchar(40) NOT NULL DEFAULT 'text',
            settings longtext NOT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY collection_id (collection_id),
            UNIQUE KEY col_key (collection_id,field_key)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$t_items} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            collection_id bigint(20) unsigned NOT NULL DEFAULT 0,
            slug varchar(191) NOT NULL DEFAULT '',
            data longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'published',
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY col_status (collection_id,status),
            UNIQUE KEY col_slug (collection_id,slug),
            KEY updated_at (updated_at)
        ) {$charset};" );

        update_option( 'loopkit_cms_db', self::DB_VERSION );
    }

    /* ─────────────────────────────────────────────────────────
     *  Module init
     * ───────────────────────────────────────────────────────── */

    /**
     * Start the module. Called by Loopkit_Modules when CMS is enabled.
     */
    public function init() {
        self::install();

        add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );

        // One row, one URL: /{collection}/{row}/ .
        add_action( 'admin_post_loopkit_cms_export', array( $this, 'do_export_download' ) );
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 20 );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'maybe_render_item' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // AJAX endpoints for inline editing, item CRUD, field CRUD, import/export.
        add_action( 'wp_ajax_lk_cms_save_collection', array( $this, 'ajax_save_collection' ) );
        add_action( 'wp_ajax_lk_cms_delete_collection', array( $this, 'ajax_delete_collection' ) );
        add_action( 'wp_ajax_lk_cms_save_field', array( $this, 'ajax_save_field' ) );
        add_action( 'wp_ajax_lk_cms_delete_field', array( $this, 'ajax_delete_field' ) );
        add_action( 'wp_ajax_lk_cms_reorder_fields', array( $this, 'ajax_reorder_fields' ) );
        add_action( 'wp_ajax_lk_cms_save_item', array( $this, 'ajax_save_item' ) );
        add_action( 'wp_ajax_lk_cms_delete_item', array( $this, 'ajax_delete_item' ) );
        add_action( 'wp_ajax_lk_cms_duplicate_item', array( $this, 'ajax_duplicate_item' ) );
        add_action( 'wp_ajax_lk_cms_inline_update', array( $this, 'ajax_inline_update' ) );
        add_action( 'wp_ajax_lk_cms_export_csv', array( $this, 'ajax_export_csv' ) );
        add_action( 'wp_ajax_lk_cms_import_csv', array( $this, 'ajax_import_csv' ) );

        // Dynamic tags.
        add_filter( 'loopkit_dynamic_tags', array( $this, 'add_dynamic_tags' ) );
        add_filter( 'loopkit_dynamic_tag_value', array( $this, 'resolve_dynamic_tag' ), 10, 4 );

        // Rewrite rules for collection item pages.
        add_action( 'init', array( $this, 'register_rewrites' ) );
        add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_cms_template' ) );
    }

    /* ─────────────────────────────────────────────────────────
     *  Allowed field types
     * ───────────────────────────────────────────────────────── */

    /**
     * Recognised field types and their labels.
     *
     * @return array type => label
     */
    public static function field_types() {
        return array(
            'text'      => __( 'Text', 'loopkit' ),
            'long_text' => __( 'Long Text', 'loopkit' ),
            'rich_text' => __( 'Rich Text', 'loopkit' ),
            'number'    => __( 'Number', 'loopkit' ),
            'date'      => __( 'Date', 'loopkit' ),
            'toggle'    => __( 'Toggle', 'loopkit' ),
            'select'    => __( 'Select', 'loopkit' ),
            'image'     => __( 'Image', 'loopkit' ),
            'svg'       => __( 'SVG', 'loopkit' ),
            'video'     => __( 'Video', 'loopkit' ),
            'audio'     => __( 'Audio', 'loopkit' ),
            'gallery'   => __( 'Gallery', 'loopkit' ),
            'file'      => __( 'File', 'loopkit' ),
            'url'       => __( 'URL', 'loopkit' ),
            'email'     => __( 'Email', 'loopkit' ),
            'color'     => __( 'Colour', 'loopkit' ),
            'reference' => __( 'Reference', 'loopkit' ),
        );
    }

    /**
     * The Import / Export screen.
     *
     * Import is two steps on purpose: upload, then confirm which column is
     * which. Guessing silently and importing 500 rows into the wrong fields
     * is far worse than one extra click.
     */
    public function render_io() {
        $collections = self::get_collections();
        if ( ! $collections ) {
            $this->render_no_collections( __( 'Import / Export', 'loopkit' ) );
            return;
        }

        $collection_id = isset( $_GET['collection_id'] ) ? absint( $_GET['collection_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $collection    = $collection_id ? self::get_collection( $collection_id ) : null;
        if ( ! $collection ) {
            $collection    = $collections[0];
            $collection_id = (int) $collection->id;
        }
        $fields  = self::get_fields( $collection_id );
        $pending = get_transient( 'loopkit_cms_import_' . get_current_user_id() );
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple lk-cms-screen lk-cms-io-screen">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Import / Export', 'loopkit' ); ?></h1>
            <hr class="wp-header-end">
            <?php $this->print_notices(); ?>

            <form method="get" class="lk-cms-toolbar">
                <input type="hidden" name="page" value="loopkit_cms_io">
                <label class="screen-reader-text" for="lk-io-col"><?php esc_html_e( 'Collection', 'loopkit' ); ?></label>
                <select id="lk-io-col" name="collection_id" onchange="this.form.submit()">
                    <?php foreach ( $collections as $c ) : ?>
                        <option value="<?php echo (int) $c->id; ?>" <?php selected( $collection_id, (int) $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="lk-cms-count">
                    <?php
                    $tot = self::get_items( $collection_id, array( 'per_page' => 1 ) );
                    printf(
                        /* translators: 1: row count, 2: field count */
                        esc_html__( '%1$s rows, %2$s fields', 'loopkit' ),
                        esc_html( number_format_i18n( (int) $tot['total'] ) ),
                        esc_html( number_format_i18n( count( $fields ) ) )
                    );
                    ?>
                </span>
            </form>

            <?php if ( $pending && (int) $pending['collection_id'] === $collection_id ) : ?>
                <?php $this->render_import_mapping( $collection, $fields, $pending ); ?>
            <?php else : ?>
                <div class="lk-card">
                    <div class="lk-card-header">
                        <h3><?php esc_html_e( 'Export', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'Download every row as a CSV you can open in a spreadsheet. The file carries each row\'s id and slug, so editing it and importing it back updates those rows instead of duplicating them.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'loopkit_cms_export', 'nonce' ); ?>
                            <input type="hidden" name="action" value="loopkit_cms_export">
                            <input type="hidden" name="collection_id" value="<?php echo (int) $collection_id; ?>">
                            <button type="submit" class="lk-btn lk-btn-primary" <?php disabled( ! $fields ); ?>>
                                <span class="dashicons dashicons-download"></span>
                                <span class="lk-btn-text"><?php esc_html_e( 'Download CSV', 'loopkit' ); ?></span>
                            </button>
                            <?php if ( ! $fields ) : ?>
                                <span class="lk-auto-help"><?php esc_html_e( 'Add a field first — there is nothing to export yet.', 'loopkit' ); ?></span>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="lk-card">
                    <div class="lk-card-header">
                        <h3><?php esc_html_e( 'Import', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'Pick a CSV. The next step shows which column goes into which field so you can correct it before anything is written.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body">
                        <form method="post" enctype="multipart/form-data">
                            <?php wp_nonce_field( 'loopkit_cms_admin', 'nonce' ); ?>
                            <input type="hidden" name="loopkit_cms_action" value="upload_csv">
                            <input type="hidden" name="collection_id" value="<?php echo (int) $collection_id; ?>">
                            <div class="lk-auto-field">
                                <label for="lk-io-file"><?php esc_html_e( 'CSV file', 'loopkit' ); ?></label>
                                <input type="file" id="lk-io-file" name="csv" accept=".csv,text/csv" required>
                            </div>
                            <button type="submit" class="lk-btn lk-btn-primary">
                                <span class="lk-btn-text"><?php esc_html_e( 'Continue', 'loopkit' ); ?></span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Step two of an import: confirm which column is which.
     *
     * @param object $collection Collection.
     * @param array  $fields     Its fields.
     * @param array  $pending    path, header, sample, count, collection_id.
     */
    protected function render_import_mapping( $collection, $fields, $pending ) {
        $header = $pending['header'];
        $guess  = self::guess_csv_mapping( $header, (int) $collection->id );
        ?>
        <div class="lk-card">
            <div class="lk-card-header">
                <h3><?php esc_html_e( 'Match the columns', 'loopkit' ); ?></h3>
                <p class="lk-card-desc">
                    <?php
                    printf(
                        /* translators: 1: file name, 2: row count */
                        esc_html__( '%1$s has %2$s rows. Anything left as "skip" is ignored.', 'loopkit' ),
                        '<strong>' . esc_html( $pending['name'] ) . '</strong>',
                        esc_html( number_format_i18n( (int) $pending['count'] ) )
                    );
                    ?>
                </p>
            </div>
            <div class="lk-card-body">
                <form method="post">
                    <?php wp_nonce_field( 'loopkit_cms_admin', 'nonce' ); ?>
                    <input type="hidden" name="loopkit_cms_action" value="run_import">
                    <input type="hidden" name="collection_id" value="<?php echo (int) $collection->id; ?>">

                    <table class="widefat striped lk-io-map">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Column in your file', 'loopkit' ); ?></th>
                                <th><?php esc_html_e( 'First row', 'loopkit' ); ?></th>
                                <th><?php esc_html_e( 'Goes into', 'loopkit' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $header as $i => $name ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( '' !== trim( (string) $name ) ? $name : sprintf( __( 'Column %d', 'loopkit' ), $i + 1 ) ); ?></strong></td>
                                <td class="lk-io-sample"><?php echo esc_html( isset( $pending['sample'][ $i ] ) ? $pending['sample'][ $i ] : '' ); ?></td>
                                <td>
                                    <select name="map[<?php echo (int) $i; ?>]">
                                        <option value=""><?php esc_html_e( '— skip this column —', 'loopkit' ); ?></option>
                                        <?php $cur = isset( $guess[ $i ] ) ? $guess[ $i ] : ''; ?>
                                        <option value="_id" <?php selected( $cur, '_id' ); ?>><?php esc_html_e( 'Row ID', 'loopkit' ); ?></option>
                                        <option value="_slug" <?php selected( $cur, '_slug' ); ?>><?php esc_html_e( 'Slug (its page address)', 'loopkit' ); ?></option>
                                        <option value="_status" <?php selected( $cur, '_status' ); ?>><?php esc_html_e( 'Status', 'loopkit' ); ?></option>
                                        <?php foreach ( $fields as $fl ) : ?>
                                            <option value="<?php echo esc_attr( $fl->field_key ); ?>" <?php selected( $cur, $fl->field_key ); ?>>
                                                <?php echo esc_html( $fl->label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="lk-auto-grid lk-io-opts">
                        <div class="lk-auto-field">
                            <label for="lk-io-match"><?php esc_html_e( 'When a row already exists', 'loopkit' ); ?></label>
                            <select id="lk-io-match" name="match_on">
                                <option value=""><?php esc_html_e( 'Always add a new row', 'loopkit' ); ?></option>
                                <option value="_id" <?php selected( in_array( '_id', $guess, true ) ); ?>><?php esc_html_e( 'Update the row with the same ID', 'loopkit' ); ?></option>
                                <option value="_slug"><?php esc_html_e( 'Update the row with the same slug', 'loopkit' ); ?></option>
                                <?php foreach ( $fields as $fl ) : ?>
                                    <option value="<?php echo esc_attr( $fl->field_key ); ?>">
                                        <?php
                                        printf(
                                            /* translators: %s: field label */
                                            esc_html__( 'Update the row with the same %s', 'loopkit' ),
                                            esc_html( $fl->label )
                                        );
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-io-skip"><?php esc_html_e( 'Rows to skip at the top', 'loopkit' ); ?></label>
                            <input type="number" id="lk-io-skip" name="skip_rows" min="0" max="20"
                                   value="<?php echo (int) $pending['skip_guess']; ?>">
                            <span class="lk-auto-help"><?php esc_html_e( 'A file exported from here has a key line under the headings; skip 1 for those.', 'loopkit' ); ?></span>
                        </div>
                    </div>

                    <div class="lk-cms-form-footer">
                        <button type="submit" class="lk-btn lk-btn-primary"><span class="lk-btn-text"><?php esc_html_e( 'Import', 'loopkit' ); ?></span></button>
                        <button type="submit" name="loopkit_cms_action" value="cancel_import" class="lk-btn lk-btn-secondary"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /* ─────────────────────────────────────────────────────────
     *  CSV in and out
     * ───────────────────────────────────────────────────────── */

    /**
     * A collection's rows as CSV text.
     *
     * The header is field labels, because the file is for people to read and
     * edit in a spreadsheet. A second header line carries the keys, so a file
     * exported here can be imported back without re-mapping every column.
     *
     * @param int  $collection_id Collection.
     * @param bool $keys_row      Include the key line under the labels.
     * @return string CSV, UTF-8 with a BOM so Excel reads accents correctly.
     */
    public static function export_csv( $collection_id, $keys_row = true ) {
        $collection_id = (int) $collection_id;
        $fields        = self::get_fields( $collection_id );
        if ( ! $fields ) {
            return '';
        }

        $out = fopen( 'php://temp', 'r+' );
        $labels = array( 'ID', 'Slug', 'Status' );
        $keys   = array( '_id', '_slug', '_status' );
        foreach ( $fields as $fl ) {
            $labels[] = $fl->label;
            $keys[]   = $fl->field_key;
        }
        fputcsv( $out, $labels );
        if ( $keys_row ) {
            fputcsv( $out, $keys );
        }

        // Oldest first, so the file reads in the order the rows were made.
        $page = 1;
        do {
            $batch = self::get_items( $collection_id, array(
                'per_page' => 500,
                'page'     => $page,
                'orderby'  => 'id',
                'order'    => 'ASC',
            ) );
            foreach ( $batch['items'] as $row ) {
                $data = is_array( $row->data ) ? $row->data : array();
                $line = array( (int) $row->id, (string) $row->slug, (string) $row->status );
                foreach ( $fields as $fl ) {
                    $v = isset( $data[ $fl->field_key ] ) ? $data[ $fl->field_key ] : '';
                    // A gallery is a list; join it so one cell holds one value.
                    $line[] = is_array( $v ) ? implode( ' | ', $v ) : (string) $v;
                }
                fputcsv( $out, $line );
            }
            $more = count( $batch['items'] ) === 500;
            $page++;
        } while ( $more );

        rewind( $out );
        $csv = stream_get_contents( $out );
        fclose( $out );

        // Excel reads a UTF-8 file as the local codepage without this.
        return chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) . $csv;
    }

    /**
     * Read a CSV file into a header row and the rows under it.
     *
     * @param string $path  File on disk.
     * @param int    $limit Stop after this many data rows; 0 for all.
     * @return array header, rows, count — or an 'error' key.
     */
    public static function read_csv( $path, $limit = 0 ) {
        if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
            return array( 'error' => __( 'The file could not be read.', 'loopkit' ) );
        }
        $fh = fopen( $path, 'r' );
        if ( ! $fh ) {
            return array( 'error' => __( 'The file could not be opened.', 'loopkit' ) );
        }

        $header = fgetcsv( $fh );
        if ( ! $header ) {
            fclose( $fh );
            return array( 'error' => __( 'The file is empty.', 'loopkit' ) );
        }
        // Strip the BOM a spreadsheet may have written, or the first column
        // name never matches anything.
        $header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
        $header    = array_map( 'trim', $header );

        $rows  = array();
        $count = 0;
        while ( false !== ( $line = fgetcsv( $fh ) ) ) {
            // fgetcsv gives array(null) for a blank line.
            if ( array( null ) === $line || ( 1 === count( $line ) && '' === trim( (string) $line[0] ) ) ) {
                continue;
            }
            $count++;
            if ( 0 === $limit || count( $rows ) < $limit ) {
                $rows[] = $line;
            }
        }
        fclose( $fh );

        return array( 'header' => $header, 'rows' => $rows, 'count' => $count );
    }

    /**
     * Guess which CSV column belongs to which field.
     *
     * Matched on the key first, then the label, both case-insensitively, so
     * a file exported from here maps itself and a hand-made one usually does.
     *
     * @param array $header        CSV column names.
     * @param int   $collection_id Collection.
     * @return array column index => field key.
     */
    public static function guess_csv_mapping( $header, $collection_id ) {
        $map    = array();
        $byname = array();
        foreach ( self::get_fields( (int) $collection_id ) as $fl ) {
            $byname[ strtolower( $fl->field_key ) ] = $fl->field_key;
            $byname[ strtolower( $fl->label ) ]     = $fl->field_key;
        }
        $byname['_slug']   = '_slug';
        $byname['slug']    = '_slug';
        $byname['_status'] = '_status';
        $byname['status']  = '_status';
        $byname['_id']     = '_id';
        $byname['id']      = '_id';
        foreach ( (array) $header as $i => $name ) {
            $k = strtolower( trim( (string) $name ) );
            if ( '' !== $k && isset( $byname[ $k ] ) ) {
                $map[ (int) $i ] = $byname[ $k ];
            }
        }
        return $map;
    }

    /**
     * Write CSV rows into a collection.
     *
     * Every value goes through the same validation the admin screens use, so
     * a bad date or a broken URL is reported rather than stored. A row that
     * fails is skipped whole and named in the result; the rest still import,
     * because stopping at row 400 of 500 leaves the worst kind of mess.
     *
     * @param int    $collection_id Collection.
     * @param string $path          CSV on disk.
     * @param array  $map           Column index => field key (or _slug/_status/_id).
     * @param array  $opts          match_on: field key or '' ; skip_rows: int.
     * @return array created, updated, skipped, errors.
     */
    public static function import_csv( $collection_id, $path, $map, $opts = array() ) {
        $collection_id = (int) $collection_id;
        $result = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );

        $fields = self::get_fields( $collection_id );
        if ( ! $fields ) {
            $result['errors'][] = __( 'This collection has no fields yet.', 'loopkit' );
            return $result;
        }
        $by_key = array();
        foreach ( $fields as $fl ) {
            $by_key[ $fl->field_key ] = $fl;
        }

        $read = self::read_csv( $path );
        if ( isset( $read['error'] ) ) {
            $result['errors'][] = $read['error'];
            return $result;
        }

        $match_on  = isset( $opts['match_on'] ) ? (string) $opts['match_on'] : '';
        $skip_rows = isset( $opts['skip_rows'] ) ? (int) $opts['skip_rows'] : 0;

        $line_no = 1; // the header
        $seen    = 0;
        foreach ( $read['rows'] as $line ) {
            $line_no++;
            $seen++;
            // An export carries a key row under the labels; skipping it is the
            // caller's choice because a hand-made file has no such row.
            if ( $seen <= $skip_rows ) {
                continue;
            }

            $data    = array();
            $slug_in = '';
            $status  = '';
            $id_in   = 0;
            $bad     = '';

            foreach ( $map as $col => $key ) {
                $raw = isset( $line[ (int) $col ] ) ? (string) $line[ (int) $col ] : '';
                if ( '_slug' === $key )   { $slug_in = sanitize_title( $raw ); continue; }
                if ( '_status' === $key ) { $status  = ( 'draft' === strtolower( trim( $raw ) ) ) ? 'draft' : 'published'; continue; }
                if ( '_id' === $key )     { $id_in   = absint( $raw ); continue; }
                if ( ! isset( $by_key[ $key ] ) ) {
                    continue; // a column mapped to a field that has since gone
                }
                $check = self::sanitize_field_value( $by_key[ $key ], $raw );
                if ( empty( $check['ok'] ) ) {
                    $bad = sprintf(
                        /* translators: 1: line number, 2: column name, 3: the reason */
                        __( 'Line %1$d, %2$s: %3$s', 'loopkit' ),
                        $line_no,
                        $by_key[ $key ]->label,
                        $check['error']
                    );
                    break;
                }
                $data[ $key ] = $check['value'];
            }

            if ( '' !== $bad ) {
                $result['skipped']++;
                if ( count( $result['errors'] ) < 50 ) {
                    $result['errors'][] = $bad;
                }
                continue;
            }
            $has_value = false;
            foreach ( $data as $v ) {
                if ( is_array( $v ) ? $v : '' !== trim( (string) $v ) ) {
                    $has_value = true;
                    break;
                }
            }
            if ( ! $has_value && '' === $slug_in ) {
                // A line of nothing but separators is not a row.
                $result['skipped']++;
                continue;
            }

            // Find an existing row to update, if the caller asked for that.
            $target = null;
            if ( '_id' === $match_on && $id_in ) {
                $maybe = self::get_item( $id_in );
                if ( $maybe && (int) $maybe->collection_id === $collection_id ) {
                    $target = $maybe;
                }
            } elseif ( '_slug' === $match_on && '' !== $slug_in ) {
                $target = self::get_item_by_slug( $collection_id, $slug_in );
            } elseif ( '' !== $match_on && isset( $data[ $match_on ] ) && '' !== (string) $data[ $match_on ] ) {
                $target = self::find_item_by_value( $collection_id, $match_on, (string) $data[ $match_on ] );
            }

            if ( $target ) {
                $changes = array( 'data' => $data );
                if ( '' !== $status ) {
                    $changes['status'] = $status;
                }
                if ( '' !== $slug_in && '_slug' !== $match_on ) {
                    $changes['slug'] = $slug_in;
                }
                self::update_item( (int) $target->id, $changes );
                $result['updated']++;
            } else {
                $new = self::create_item( $collection_id, $data, '' !== $status ? $status : 'published' );
                if ( $new ) {
                    if ( '' !== $slug_in ) {
                        self::update_item( (int) $new, array( 'slug' => $slug_in ) );
                    }
                    $result['created']++;
                } else {
                    $result['skipped']++;
                    if ( count( $result['errors'] ) < 50 ) {
                        $result['errors'][] = sprintf(
                            /* translators: %d: line number */
                            __( 'Line %d could not be saved.', 'loopkit' ),
                            $line_no
                        );
                    }
                }
            }
        }

        return $result;
    }

    /**
     * The first row whose field holds this exact value.
     *
     * Used to match an incoming CSV line against a row that already exists,
     * so a second import updates rather than duplicates.
     *
     * @param int    $collection_id Collection.
     * @param string $key           Field key.
     * @param string $value         Value to match.
     * @return object|null
     */
    public static function find_item_by_value( $collection_id, $key, $value ) {
        global $wpdb;
        $table = self::table_items();
        $like  = '%' . $wpdb->esc_like( wp_json_encode( array( $key => $value ) ) ) . '%';
        // The JSON fragment narrows the scan; the match itself is checked in
        // PHP, because key order in the stored JSON is not guaranteed.
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE collection_id = %d ORDER BY id ASC",
            (int) $collection_id
        ) );
        unset( $like );
        foreach ( $ids as $id ) {
            $row = self::get_item( (int) $id );
            if ( $row && is_array( $row->data ) && isset( $row->data[ $key ] )
                && (string) $row->data[ $key ] === (string) $value ) {
                return $row;
            }
        }
        return null;
    }

    /* ─────────────────────────────────────────────────────────
     *  Front end: one row, one URL
     * ───────────────────────────────────────────────────────── */

    /**
     * /{collection-slug}/{row-slug}/ for every collection.
     *
     * Registered per collection rather than as one catch-all so an unrelated
     * two-segment URL is left for WordPress to handle as it always did.
     */
    public function add_rewrite_rules() {
        foreach ( self::get_collections() as $c ) {
            add_rewrite_rule(
                '^' . preg_quote( $c->slug, '/' ) . '/([^/]+)/?$',
                'index.php?lk_collection=' . rawurlencode( $c->slug ) . '&lk_item=$matches[1]',
                'top'
            );
        }
    }

    /**
     * Let the two names through to the query.
     *
     * @param array $vars Query vars.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'lk_collection';
        $vars[] = 'lk_item';
        return $vars;
    }

    /**
     * The permalink for one row.
     *
     * @param object $item       Row.
     * @param object $collection Its collection, if already loaded.
     * @return string
     */
    public static function item_permalink( $item, $collection = null ) {
        if ( ! $item ) {
            return '';
        }
        if ( ! $collection ) {
            $collection = self::get_collection( (int) $item->collection_id );
        }
        if ( ! $collection ) {
            return '';
        }
        $slug = '' !== (string) $item->slug ? $item->slug : 'item-' . (int) $item->id;
        return home_url( user_trailingslashit( $collection->slug . '/' . $slug ) );
    }

    /**
     * Serve a row's own page.
     *
     * A row is not a post, so there is nothing for WordPress to render. The
     * collection's template is rendered inside the theme's header and footer
     * with the row set as context, so every dynamic tag resolves against it.
     */
    public function maybe_render_item() {
        $col_slug  = get_query_var( 'lk_collection' );
        $item_slug = get_query_var( 'lk_item' );
        if ( '' === $col_slug || '' === $item_slug ) {
            return;
        }
        $collection = self::get_collection_by_slug( $col_slug );
        if ( ! $collection ) {
            return;
        }
        $item = self::get_item_by_slug( (int) $collection->id, $item_slug );
        if ( ! $item || 'published' !== $item->status ) {
            // A draft or a bad address is a 404, not a blank page.
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            return;
        }

        $template_id = self::item_template_id( (int) $collection->id );
        if ( ! $template_id ) {
            return; // No template assigned: leave the theme to it.
        }

        status_header( 200 );
        self::set_context( $item, $collection );
        add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_item_title' ) );

        get_header();
        if ( class_exists( 'Loopkit_Templates' ) && method_exists( 'Loopkit_Templates', 'render_template' ) ) {
            echo Loopkit_Templates::render_template( 'cms-item', $template_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        get_footer();

        self::set_context( null, null );
        exit;
    }

    /**
     * Browser tab title for a row's page.
     *
     * @return string
     */
    public static function filter_item_title() {
        $item = self::get_context_item();
        $name = $item ? self::item_title( $item ) : '';
        return ( '' !== $name ? $name : __( 'Item', 'loopkit' ) ) . ' – ' . get_bloginfo( 'name' );
    }

    /**
     * Which template renders a collection's rows.
     *
     * @param int $collection_id Collection.
     * @return int Template post id, or 0.
     */
    public static function item_template_id( $collection_id ) {
        $map = get_option( 'loopkit_cms_item_templates', array() );
        return isset( $map[ (int) $collection_id ] ) ? (int) $map[ (int) $collection_id ] : 0;
    }

    /**
     * Point a collection's rows at a template.
     *
     * @param int $collection_id Collection.
     * @param int $template_id   Template post id; 0 unassigns.
     */
    /**
     * Rebuild the URL rules on the next request.
     *
     * A collection's slug is part of its rows' addresses, so adding,
     * renaming or removing one changes what the site must answer to.
     * Deferred rather than flushed inline, which is expensive.
     */
    public static function queue_rewrite_flush() {
        update_option( 'loopkit_cms_flush_rewrites', 1 );
    }

    /** Do the deferred rebuild, once, after the rules are registered. */
    public function maybe_flush_rewrites() {
        if ( get_option( 'loopkit_cms_flush_rewrites' ) ) {
            delete_option( 'loopkit_cms_flush_rewrites' );
            flush_rewrite_rules( false );
        }
    }

    public static function set_item_template( $collection_id, $template_id ) {
        $map = get_option( 'loopkit_cms_item_templates', array() );
        if ( ! is_array( $map ) ) {
            $map = array();
        }
        if ( $template_id ) {
            $map[ (int) $collection_id ] = (int) $template_id;
        } else {
            unset( $map[ (int) $collection_id ] );
        }
        update_option( 'loopkit_cms_item_templates', $map );
    }

    /**
     * The field a row is named by — the first text field, else the first field.
     *
     * @param int $collection_id Collection.
     * @return string Field key, or '' when the collection has no fields.
     */
    public static function title_field_key( $collection_id ) {
        $fields = self::get_fields( (int) $collection_id );
        if ( ! $fields ) {
            return '';
        }
        foreach ( $fields as $f ) {
            if ( 'text' === $f->type ) {
                return $f->field_key;
            }
        }
        return $fields[0]->field_key;
    }

    /**
     * A row's display title, for headings and for building its slug.
     *
     * @param object $item       Row.
     * @param int    $collection Collection id.
     * @return string
     */
    public static function item_title( $item, $collection_id = 0 ) {
        if ( ! $item ) {
            return '';
        }
        $collection_id = $collection_id ? (int) $collection_id : (int) $item->collection_id;
        $key           = self::title_field_key( $collection_id );
        $data          = is_array( $item->data ) ? $item->data : array();
        $value         = ( '' !== $key && isset( $data[ $key ] ) ) ? (string) $data[ $key ] : '';
        $value         = trim( wp_strip_all_tags( $value ) );
        return $value;
    }

    /**
     * A URL-safe name for a row, unique within its collection.
     *
     * Falls back to the row id when the title is empty or turns into nothing
     * once sanitised, so every row has an address even before it is filled in.
     *
     * @param int    $collection_id Collection.
     * @param string $title         Proposed name.
     * @param int    $except        Row id to ignore when checking (itself).
     * @return string
     */
    public static function unique_item_slug( $collection_id, $title, $except = 0 ) {
        global $wpdb;
        $collection_id = (int) $collection_id;
        $base          = sanitize_title( $title );
        if ( '' === $base ) {
            $base = $except ? 'item-' . (int) $except : 'item';
        }
        $table = self::table_items();
        $slug  = $base;
        $n     = 2;
        while ( true ) {
            $found = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE collection_id = %d AND slug = %s AND id <> %d LIMIT 1",
                $collection_id,
                $slug,
                (int) $except
            ) );
            if ( ! $found ) {
                return $slug;
            }
            $slug = $base . '-' . $n;
            $n++;
        }
    }

    /**
     * Give a row a slug from its own title. Called after every write.
     *
     * @param int $item_id Row.
     * @return string The slug stored.
     */
    public static function refresh_item_slug( $item_id ) {
        global $wpdb;
        $item = self::get_item( $item_id );
        if ( ! $item ) {
            return '';
        }
        $slug = self::unique_item_slug(
            (int) $item->collection_id,
            self::item_title( $item ),
            (int) $item->id
        );
        $wpdb->update( self::table_items(), array( 'slug' => $slug ), array( 'id' => (int) $item->id ), array( '%s' ), array( '%d' ) );
        return $slug;
    }

    /**
     * Find a row by its slug within a collection.
     *
     * @param int    $collection_id Collection.
     * @param string $slug          Row slug.
     * @return object|null
     */
    public static function get_item_by_slug( $collection_id, $slug ) {
        global $wpdb;
        $table = self::table_items();
        $id    = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE collection_id = %d AND slug = %s LIMIT 1",
            (int) $collection_id,
            (string) $slug
        ) );
        return $id ? self::get_item( $id ) : null;
    }

    /**
     * What a media field picks, and how it looks before anything is picked.
     *
     * SVG is its own type rather than a flavour of image because the media
     * library filters by MIME, and a site that allows SVG uploads wants a
     * field that only offers them.
     *
     * @param string $type Field type.
     * @return array mime, icon, choose and clear labels.
     */
    public static function media_kind( $type ) {
        switch ( $type ) {
            case 'svg':
                return array(
                    'mime'   => 'image/svg+xml',
                    'icon'   => 'dashicons-media-code',
                    'choose' => __( 'Choose SVG', 'loopkit' ),
                    'clear'  => __( 'Clear SVG', 'loopkit' ),
                );
            case 'video':
                return array(
                    'mime'   => 'video',
                    'icon'   => 'dashicons-format-video',
                    'choose' => __( 'Choose video', 'loopkit' ),
                    'clear'  => __( 'Clear video', 'loopkit' ),
                );
            case 'audio':
                return array(
                    'mime'   => 'audio',
                    'icon'   => 'dashicons-format-audio',
                    'choose' => __( 'Choose audio', 'loopkit' ),
                    'clear'  => __( 'Clear audio', 'loopkit' ),
                );
            default:
                return array(
                    'mime'   => 'image',
                    'icon'   => 'dashicons-format-image',
                    'choose' => __( 'Choose image', 'loopkit' ),
                    'clear'  => __( 'Clear image', 'loopkit' ),
                );
        }
    }

    /* ─────────────────────────────────────────────────────────
     *  Slug helpers
     * ───────────────────────────────────────────────────────── */

    /**
     * Turn a human name into a safe, unique collection slug.
     *
     * @param string $name   The display name.
     * @param int    $except Exclude this collection id when checking uniqueness.
     * @return string
     */
    public static function unique_slug( $name, $except = 0 ) {
        global $wpdb;
        $base  = sanitize_title( $name );
        if ( '' === $base ) {
            $base = 'collection';
        }
        $slug  = $base;
        $i     = 2;
        $table = self::table_collections();
        while ( true ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s AND id != %d",
                $slug,
                $except
            ) );
            if ( ! $exists ) {
                break;
            }
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    /**
     * Turn a field label into a safe, unique field_key within a collection.
     *
     * @param string $label         The display label.
     * @param int    $collection_id The collection.
     * @param int    $except        Exclude this field id when checking uniqueness.
     * @return string
     */
    public static function unique_field_key( $label, $collection_id, $except = 0 ) {
        global $wpdb;
        $base = sanitize_title( $label );
        $base = str_replace( '-', '_', $base );
        if ( '' === $base ) {
            $base = 'field';
        }
        $key   = $base;
        $i     = 2;
        $table = self::table_fields();
        while ( true ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE collection_id = %d AND field_key = %s AND id != %d",
                $collection_id,
                $key,
                $except
            ) );
            if ( ! $exists ) {
                break;
            }
            $key = $base . '_' . $i;
            $i++;
        }
        return $key;
    }

    /* ═══════════════════════════════════════════════════════════
     *  COLLECTION CRUD
     * ═══════════════════════════════════════════════════════════ */

    /**
     * Create a collection.
     *
     * @param string $name The display name.
     * @param string $slug Optional slug (auto-generated from name if empty).
     * @param string $icon Dashicon class.
     * @return int|false The new collection id, or false on failure.
     */
    public static function create_collection( $name, $slug = '', $icon = 'dashicons-database' ) {
        global $wpdb;

        $name = sanitize_text_field( $name );
        if ( '' === $name ) {
            return false;
        }

        $slug = '' !== $slug ? sanitize_title( $slug ) : self::unique_slug( $name );
        $icon = sanitize_text_field( $icon );
        $now  = current_time( 'mysql' );

        $ok = $wpdb->insert(
            self::table_collections(),
            array(
                'name'       => $name,
                'slug'       => $slug,
                'icon'       => $icon,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );

        // Read the id before anything else writes: update_option() INSERTs
        // the first time it stores a name, which replaces insert_id.
        $new_id = $ok ? (int) $wpdb->insert_id : 0;

        self::queue_rewrite_flush();
        return $new_id ? $new_id : false;
    }

    /**
     * Get a single collection by id.
     *
     * @param int $id
     * @return object|null
     */
    public static function get_collection( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table_collections() . " WHERE id = %d",
            $id
        ) );
    }

    /**
     * Get a single collection by slug.
     *
     * @param string $slug
     * @return object|null
     */
    public static function get_collection_by_slug( $slug ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table_collections() . " WHERE slug = %s",
            $slug
        ) );
    }

    /**
     * List all collections, ordered by name.
     *
     * @return array of objects
     */
    public static function get_collections() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM " . self::table_fields() . " WHERE collection_id = c.id) AS field_count,
                    (SELECT COUNT(*) FROM " . self::table_items()  . " WHERE collection_id = c.id) AS item_count
             FROM " . self::table_collections() . " c
             ORDER BY c.name ASC"
        );
    }

    /**
     * Update a collection.
     *
     * @param int   $id   The collection id.
     * @param array $data Associative array of columns to update.
     * @return bool
     */
    public static function update_collection( $id, $data ) {
        global $wpdb;

        $allowed = array( 'name', 'slug', 'icon' );
        $update  = array();
        $format  = array();

        foreach ( $allowed as $col ) {
            if ( isset( $data[ $col ] ) ) {
                $update[ $col ] = sanitize_text_field( $data[ $col ] );
                $format[]       = '%s';
            }
        }
        if ( empty( $update ) ) {
            return false;
        }

        // Ensure slug uniqueness when it changes.
        if ( isset( $update['slug'] ) ) {
            $update['slug'] = self::unique_slug( $update['slug'], $id );
        }

        $update['updated_at'] = current_time( 'mysql' );
        $format[]             = '%s';

        self::queue_rewrite_flush();
        return false !== $wpdb->update(
            self::table_collections(),
            $update,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );
    }

    /**
     * Delete a collection and all its fields and items.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_collection( $id ) {
        global $wpdb;
        $id = absint( $id );
        if ( ! $id ) {
            return false;
        }

        // Delete items first, then fields, then collection.
        $wpdb->delete( self::table_items(),  array( 'collection_id' => $id ), array( '%d' ) );
        $wpdb->delete( self::table_fields(), array( 'collection_id' => $id ), array( '%d' ) );
        // Its rows had their own pages; drop that mapping with them,
        // or the next collection to reuse the id inherits a template.
        self::set_item_template( $id, 0 );
        self::queue_rewrite_flush();
        return false !== $wpdb->delete( self::table_collections(), array( 'id' => $id ), array( '%d' ) );
    }

    /* ═══════════════════════════════════════════════════════════
     *  FIELD CRUD
     * ═══════════════════════════════════════════════════════════ */

    /**
     * Create a field.
     *
     * @param int    $collection_id
     * @param string $label
     * @param string $field_key Optional (auto-slugged from label).
     * @param string $type      One of field_types() keys.
     * @param array  $settings  Type-specific settings.
     * @param int    $sort_order
     * @return int|false
     */
    public static function create_field( $collection_id, $label, $field_key = '', $type = 'text', $settings = array(), $sort_order = null ) {
        global $wpdb;

        $collection_id = absint( $collection_id );
        $label         = sanitize_text_field( $label );
        if ( ! $collection_id || '' === $label ) {
            return false;
        }

        $types = array_keys( self::field_types() );
        if ( ! in_array( $type, $types, true ) ) {
            $type = 'text';
        }

        if ( '' === $field_key ) {
            $field_key = self::unique_field_key( $label, $collection_id );
        } else {
            $field_key = sanitize_key( $field_key );
            $field_key = self::unique_field_key( $field_key, $collection_id );
        }

        if ( null === $sort_order ) {
            $sort_order = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM " . self::table_fields() . " WHERE collection_id = %d",
                $collection_id
            ) );
        }

        $ok = $wpdb->insert(
            self::table_fields(),
            array(
                'collection_id' => $collection_id,
                'label'         => $label,
                'field_key'     => $field_key,
                'type'          => $type,
                'settings'      => wp_json_encode( $settings ),
                'sort_order'    => (int) $sort_order,
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d' )
        );

        if ( $ok ) {
            // Touch collection updated_at.
            $wpdb->update(
                self::table_collections(),
                array( 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => $collection_id ),
                array( '%s' ),
                array( '%d' )
            );
        }

        return $ok ? (int) $wpdb->insert_id : false;
    }

    /**
     * Get a single field by id.
     *
     * @param int $id
     * @return object|null
     */
    public static function get_field( $id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table_fields() . " WHERE id = %d",
            $id
        ) );
        if ( $row ) {
            $row->settings = json_decode( $row->settings, true );
            if ( ! is_array( $row->settings ) ) {
                $row->settings = array();
            }
        }
        return $row;
    }

    /**
     * Get all fields for a collection, ordered by sort_order.
     *
     * @param int $collection_id
     * @return array of objects
     */
    public static function get_fields( $collection_id ) {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table_fields() . " WHERE collection_id = %d ORDER BY sort_order ASC, id ASC",
            $collection_id
        ) );
        foreach ( $rows as &$row ) {
            $row->settings = json_decode( $row->settings, true );
            if ( ! is_array( $row->settings ) ) {
                $row->settings = array();
            }
        }
        return $rows;
    }

    /**
     * Update a field.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update_field( $id, $data ) {
        global $wpdb;

        $field = self::get_field( $id );
        if ( ! $field ) {
            return false;
        }

        $update = array();
        $format = array();

        if ( isset( $data['label'] ) ) {
            $update['label'] = sanitize_text_field( $data['label'] );
            $format[]        = '%s';
        }
        if ( isset( $data['field_key'] ) ) {
            $update['field_key'] = self::unique_field_key( $data['field_key'], $field->collection_id, $id );
            $format[]            = '%s';
        }
        if ( isset( $data['type'] ) ) {
            $types = array_keys( self::field_types() );
            $update['type'] = in_array( $data['type'], $types, true ) ? $data['type'] : 'text';
            $format[]       = '%s';
        }
        if ( isset( $data['settings'] ) ) {
            $update['settings'] = wp_json_encode( is_array( $data['settings'] ) ? $data['settings'] : array() );
            $format[]           = '%s';
        }
        if ( isset( $data['sort_order'] ) ) {
            $update['sort_order'] = (int) $data['sort_order'];
            $format[]             = '%d';
        }

        if ( empty( $update ) ) {
            return false;
        }

        $ok = false !== $wpdb->update(
            self::table_fields(),
            $update,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );

        if ( $ok ) {
            $wpdb->update(
                self::table_collections(),
                array( 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => $field->collection_id ),
                array( '%s' ),
                array( '%d' )
            );
        }

        return $ok;
    }

    /**
     * Delete a field. Also removes that key from every item in the collection.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_field( $id ) {
        global $wpdb;

        $field = self::get_field( $id );
        if ( ! $field ) {
            return false;
        }

        // Remove this field's data from all items.
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, data FROM " . self::table_items() . " WHERE collection_id = %d",
            $field->collection_id
        ) );
        foreach ( $items as $item ) {
            $item_data = json_decode( $item->data, true );
            if ( is_array( $item_data ) && isset( $item_data[ $field->field_key ] ) ) {
                unset( $item_data[ $field->field_key ] );
                $wpdb->update(
                    self::table_items(),
                    array( 'data' => wp_json_encode( $item_data ) ),
                    array( 'id' => $item->id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
        }

        $ok = false !== $wpdb->delete( self::table_fields(), array( 'id' => $id ), array( '%d' ) );

        if ( $ok ) {
            $wpdb->update(
                self::table_collections(),
                array( 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => $field->collection_id ),
                array( '%s' ),
                array( '%d' )
            );
        }

        return $ok;
    }

    /**
     * Set the sort_order for an ordered list of field ids.
     *
     * @param int   $collection_id
     * @param int[] $ordered_ids
     * @return bool
     */
    public static function reorder_fields( $collection_id, $ordered_ids ) {
        global $wpdb;
        $table = self::table_fields();
        foreach ( $ordered_ids as $pos => $fid ) {
            $wpdb->update(
                $table,
                array( 'sort_order' => $pos ),
                array( 'id' => absint( $fid ), 'collection_id' => absint( $collection_id ) ),
                array( '%d' ),
                array( '%d', '%d' )
            );
        }
        return true;
    }

    /* ═══════════════════════════════════════════════════════════
     *  ITEM CRUD
     * ═══════════════════════════════════════════════════════════ */

    /**
     * Create an item.
     *
     * @param int    $collection_id
     * @param array  $data   field_key => value pairs.
     * @param string $status 'published' or 'draft'.
     * @return int|false
     */
    public static function create_item( $collection_id, $data = array(), $status = 'published' ) {
        global $wpdb;

        $collection_id = absint( $collection_id );
        if ( ! $collection_id ) {
            return false;
        }

        $status = in_array( $status, array( 'published', 'draft' ), true ) ? $status : 'published';
        $now    = current_time( 'mysql' );

        $ok = $wpdb->insert(
            self::table_items(),
            array(
                'collection_id' => $collection_id,
                'data'          => wp_json_encode( is_array( $data ) ? $data : array() ),
                'status'        => $status,
                'created_at'    => $now,
                'updated_at'    => $now,
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );

        $new_id = $ok ? (int) $wpdb->insert_id : 0;

        if ( $ok ) {
            // Slug after insert: an untitled row falls back to its own id.
            self::refresh_item_slug( $new_id );
            $wpdb->update(
                self::table_collections(),
                array( 'updated_at' => $now ),
                array( 'id' => $collection_id ),
                array( '%s' ),
                array( '%d' )
            );
        }

        return $new_id ? $new_id : false;
    }

    /**
     * Get a single item by id.
     *
     * @param int $id
     * @return object|null
     */
    public static function get_item( $id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table_items() . " WHERE id = %d",
            $id
        ) );
        if ( $row ) {
            $row->data = json_decode( $row->data, true );
            if ( ! is_array( $row->data ) ) {
                $row->data = array();
            }
        }
        return $row;
    }

    /**
     * List items for a collection with pagination, sorting, searching.
     *
     * @param int   $collection_id
     * @param array $args {
     *     @type string $status    Optional. 'published', 'draft', or '' for all.
     *     @type int    $per_page  Default 50.
     *     @type int    $page      Default 1.
     *     @type string $orderby   'id', 'created_at', 'updated_at', or a field_key.
     *     @type string $order     'ASC' or 'DESC'.
     *     @type string $search    Search term (matches any text-like field value).
     * }
     * @return array { items: object[], total: int, pages: int }
     */
    public static function get_items( $collection_id, $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'   => '',
            'per_page' => 50,
            'page'     => 1,
            'orderby'  => 'id',
            'order'    => 'DESC',
            'search'   => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $table = self::table_items();
        $where = $wpdb->prepare( "collection_id = %d", $collection_id );

        if ( '' !== $args['status'] && in_array( $args['status'], array( 'published', 'draft' ), true ) ) {
            $where .= $wpdb->prepare( " AND status = %s", $args['status'] );
        }

        if ( '' !== $args['search'] ) {
            $like   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= $wpdb->prepare( " AND data LIKE %s", $like );
        }

        // Count.
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );

        // Order.
        $order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
        $orderby = in_array( $args['orderby'], array( 'id', 'created_at', 'updated_at', 'status' ), true )
            ? $args['orderby']
            : 'id';

        // If orderby is a field_key, we sort in PHP after fetch (JSON column).
        $sort_by_field = ! in_array( $args['orderby'], array( 'id', 'created_at', 'updated_at', 'status' ), true )
            ? $args['orderby']
            : null;

        if ( $sort_by_field ) {
            // Fetch all matching, sort in PHP, then slice for pagination.
            // ORDER BY id ensures deterministic initial order so usort + slice
            // always yields the same rows regardless of MySQL's internal ordering.
            $rows = $wpdb->get_results(
                "SELECT * FROM {$table} WHERE {$where}"
            );
            foreach ( $rows as &$r ) {
                $r->data = json_decode( $r->data, true );
                if ( ! is_array( $r->data ) ) {
                    $r->data = array();
                }
            }
            usort( $rows, function ( $a, $b ) use ( $sort_by_field, $order ) {
                $va = $a->data[ $sort_by_field ] ?? '';
                $vb = $b->data[ $sort_by_field ] ?? '';
                if ( '' === $va || '' === $vb ) {
                    return 'ASC' === $order ? 1 : -1;
                }
                if ( is_numeric( $va ) && is_numeric( $vb ) ) {
                    $cmp = $va - $vb;
                } else {
                    $cmp = strcasecmp( (string) $va, (string) $vb );
                }
                return 'ASC' === $order ? $cmp : -$cmp;
            } );
            $per_page = max( 1, (int) $args['per_page'] );
            $offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;
            $rows     = array_slice( $rows, $offset, $per_page );
            if ( count( $rows ) < $per_page ) {
                $rows = array_values( $rows );
            }
        } else {
            $per_page = max( 1, (int) $args['per_page'] );
            $offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;
            // $orderby and $order are sanitized above (whitelist + ASC/DESC).
            $rows = $wpdb->get_results(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT {$per_page} OFFSET {$offset}"
            );
            foreach ( $rows as &$r ) {
                $r->data = json_decode( $r->data, true );
                if ( ! is_array( $r->data ) ) {
                    $r->data = array();
                }
            }
        }

        $per_page = max( 1, (int) $args['per_page'] );

        return array(
            'items' => $rows,
            'total' => $total,
            'pages' => (int) ceil( $total / $per_page ),
        );
    }

    /**
     * Count items in a collection.
     *
     * @param int    $collection_id
     * @param string $status Optional filter.
     * @return int
     */
    public static function count_items( $collection_id, $status = '' ) {
        global $wpdb;
        $table = self::table_items();
        $where = $wpdb->prepare( "collection_id = %d", $collection_id );
        if ( '' !== $status && in_array( $status, array( 'published', 'draft' ), true ) ) {
            $where .= $wpdb->prepare( " AND status = %s", $status );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
    }

    /**
     * Update an item's data and/or status.
     *
     * @param int   $id
     * @param array $changes { data: array, status: string }
     * @return bool
     */
    public static function update_item( $id, $changes ) {
        global $wpdb;

        $item = self::get_item( $id );
        if ( ! $item ) {
            return false;
        }

        $update = array( 'updated_at' => current_time( 'mysql' ) );
        $format = array( '%s' );

        if ( isset( $changes['data'] ) && is_array( $changes['data'] ) ) {
            // Merge: existing data + new values. To clear a field, pass null / ''.
            $merged = array_merge( $item->data, $changes['data'] );
            $update['data'] = wp_json_encode( $merged );
            $format[]       = '%s';
        }
        if ( isset( $changes['status'] ) && in_array( $changes['status'], array( 'published', 'draft' ), true ) ) {
            $update['status'] = $changes['status'];
            $format[]         = '%s';
        }

        $ok = false !== $wpdb->update(
            self::table_items(),
            $update,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );

        // Rename the row, rename its page. An explicit slug wins over the
        // title so an address can be pinned once it is published.
        if ( $ok ) {
            if ( isset( $changes['slug'] ) ) {
                $wpdb->update(
                    self::table_items(),
                    array( 'slug' => self::unique_item_slug( (int) $item->collection_id, (string) $changes['slug'], (int) $id ) ),
                    array( 'id' => (int) $id ),
                    array( '%s' ),
                    array( '%d' )
                );
            } elseif ( isset( $changes['data'] ) ) {
                self::refresh_item_slug( (int) $id );
            }
        }

        if ( $ok ) {
            $wpdb->update(
                self::table_collections(),
                array( 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => $item->collection_id ),
                array( '%s' ),
                array( '%d' )
            );
        }

        return $ok;
    }

    /**
     * Delete an item.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_item( $id ) {
        global $wpdb;

        $item = self::get_item( $id );
        if ( ! $item ) {
            return false;
        }

        $ok = false !== $wpdb->delete( self::table_items(), array( 'id' => $id ), array( '%d' ) );

        if ( $ok ) {
            $wpdb->update(
                self::table_collections(),
                array( 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => $item->collection_id ),
                array( '%s' ),
                array( '%d' )
            );
        }

        return $ok;
    }

    /**
     * Duplicate an item (same collection, new row).
     *
     * @param int $id
     * @return int|false New item id.
     */
    public static function duplicate_item( $id ) {
        $item = self::get_item( $id );
        if ( ! $item ) {
            return false;
        }
        return self::create_item( $item->collection_id, $item->data, $item->status );
    }

    /**
     * Validate and sanitize one raw value for one field.
     *
     * Data-safety rule for the whole items screen: invalid input is
     * rejected with an error, never silently blanked — a bad post can
     * not overwrite what the database already holds.
     *
     * @param object $field Row from get_fields().
     * @param mixed  $raw   Raw input (scalars only; arrays are rejected).
     * @return array { ok: bool, value: mixed, error: string }
     */
    public static function sanitize_field_value( $field, $raw ) {
        if ( is_array( $raw ) ) {
            return array(
                'ok'    => false,
                'value' => null,
                'error' => __( 'Expected a single value.', 'loopkit' ),
            );
        }
        $raw   = (string) $raw;
        $error = sprintf(
            /* translators: %s: field label */
            __( 'Invalid value for “%s”.', 'loopkit' ),
            $field->label
        );

        switch ( $field->type ) {
            case 'number':
                $trimmed = trim( $raw );
                if ( '' === $trimmed ) {
                    return array( 'ok' => true, 'value' => '', 'error' => '' );
                }
                if ( ! is_numeric( $trimmed ) ) {
                    return array( 'ok' => false, 'value' => null, 'error' => $error );
                }
                // Real JSON numbers: integers stay int, decimals float, so
                // column sorting and templates never treat 42 as text.
                $value = ( strspn( $trimmed, '-0123456789' ) === strlen( $trimmed ) )
                    ? (int) $trimmed
                    : (float) $trimmed;
                return array( 'ok' => true, 'value' => $value, 'error' => '' );

            case 'date':
                $trimmed = trim( $raw );
                if ( '' === $trimmed ) {
                    return array( 'ok' => true, 'value' => '', 'error' => '' );
                }
                $d = DateTime::createFromFormat( 'Y-m-d', $trimmed );
                if ( ! $d || $d->format( 'Y-m-d' ) !== $trimmed ) {
                    return array( 'ok' => false, 'value' => null, 'error' => $error );
                }
                return array( 'ok' => true, 'value' => $trimmed, 'error' => '' );

            case 'toggle':
                $t = strtolower( trim( $raw ) );
                if ( ! in_array( $t, array( '1', '0', 'on', 'yes', 'no', 'true', 'false', '' ), true ) ) {
                    return array( 'ok' => false, 'value' => null, 'error' => $error );
                }
                return array(
                    'ok'    => true,
                    'value' => in_array( $t, array( '1', 'on', 'yes', 'true' ), true ) ? '1' : '0',
                    'error' => '',
                );

            case 'select':
                $value   = sanitize_text_field( $raw );
                $options = ( isset( $field->settings['options'] ) && is_array( $field->settings['options'] ) )
                    ? $field->settings['options']
                    : array();
                if ( '' !== $value && ! empty( $options ) && ! in_array( $value, $options, true ) ) {
                    return array( 'ok' => false, 'value' => null, 'error' => $error );
                }
                return array( 'ok' => true, 'value' => $value, 'error' => '' );

            case 'email':
                $value = sanitize_email( trim( $raw ) );
                if ( '' !== trim( $raw ) && '' === $value ) {
                    return array( 'ok' => false, 'value' => null, 'error' => $error );
                }
                return array( 'ok' => true, 'value' => $value, 'error' => '' );

            case 'url':
            case 'image':
            case 'svg':
            case 'video':
            case 'audio':
            case 'file':
                $value = esc_url_raw( trim( $raw ) );
                if ( '' !== trim( $raw ) && '' === $value ) {
                    return array( 'ok' => false, 'value' => null, 'error' => $error );
                }
                return array( 'ok' => true, 'value' => $value, 'error' => '' );

            case 'color':
                $value = trim( $raw );
                if ( '' === $value ) {
                    return array( 'ok' => true, 'value' => '', 'error' => '' );
                }
                if ( ! preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ) {
                    return array( 'ok' => false, 'value' => null, 'error' => $error );
                }
                return array( 'ok' => true, 'value' => strtolower( $value ), 'error' => '' );

            case 'rich_text':
                return array( 'ok' => true, 'value' => wp_kses_post( $raw ), 'error' => '' );

            case 'long_text':
                return array( 'ok' => true, 'value' => sanitize_textarea_field( $raw ), 'error' => '' );

            default:
                // text, gallery, reference — plain single-line text.
                return array( 'ok' => true, 'value' => sanitize_text_field( $raw ), 'error' => '' );
        }
    }

    /**
     * Save one cell: validate the value for that field, then merge it into
     * the item. Only the addressed key is written (update_item merges), and
     * an invalid value writes nothing at all.
     *
     * @param int      $item_id   Row to change.
     * @param string   $field_key Column key.
     * @param mixed    $raw       Raw input.
     * @param array|null $fields  Optional pre-fetched get_fields() for the
     *                            row's collection (bulk-save optimization).
     * @return array { ok: bool, value: mixed, msg: string }
     */
    public static function save_cell( $item_id, $field_key, $raw, $fields = null ) {
        $item = self::get_item( $item_id );
        if ( ! $item ) {
            return array(
                'ok'    => false,
                'value' => null,
                'msg'   => __( 'Row not found.', 'loopkit' ),
            );
        }
        if ( null === $fields ) {
            $fields = self::get_fields( $item->collection_id );
        }
        $field = null;
        foreach ( $fields as $f ) {
            if ( $f->field_key === $field_key ) {
                $field = $f;
                break;
            }
        }
        if ( ! $field ) {
            return array(
                'ok'    => false,
                'value' => null,
                'msg'   => __( 'Unknown field.', 'loopkit' ),
            );
        }
        $check = self::sanitize_field_value( $field, $raw );
        if ( ! $check['ok'] ) {
            return array( 'ok' => false, 'value' => null, 'msg' => $check['error'] );
        }
        if ( ! self::update_item( $item_id, array( 'data' => array( $field_key => $check['value'] ) ) ) ) {
            return array(
                'ok'    => false,
                'value' => null,
                'msg'   => __( 'Failed to save.', 'loopkit' ),
            );
        }
        return array(
            'ok'    => true,
            'value' => $check['value'],
            'msg'   => __( 'Saved.', 'loopkit' ),
        );
    }

    /**
     * Save an entire item (single-item editor and add-row): every posted
     * key is validated first and nothing is written unless all of them
     * pass, so a half-valid post can never leave the row half-updated.
     *
     * @param int   $collection_id Collection the row must belong to.
     * @param int   $item_id       0 to create a new row.
     * @param array $raw_data      field_key => raw value.
     * @return array { ok: bool, item_id: int, created: bool, msg: string }
     */
    public static function save_item_data( $collection_id, $item_id, $raw_data ) {
        $fail = function ( $msg ) use ( $item_id ) {
            return array(
                'ok'       => false,
                'item_id'  => (int) $item_id,
                'created'  => false,
                'msg'      => $msg,
            );
        };
        if ( ! self::get_collection( $collection_id ) ) {
            return $fail( __( 'Collection not found.', 'loopkit' ) );
        }
        $fields = array();
        foreach ( self::get_fields( $collection_id ) as $f ) {
            $fields[ $f->field_key ] = $f;
        }
        if ( ! is_array( $raw_data ) ) {
            $raw_data = array();
        }

        // Pass 1 — validate everything; collect the cleaned values.
        $clean = array();
        foreach ( $raw_data as $key => $raw ) {
            $key = sanitize_key( (string) $key );
            if ( ! isset( $fields[ $key ] ) ) {
                return $fail( sprintf(
                    /* translators: %s: field key */
                    __( 'Unknown field: %s.', 'loopkit' ),
                    $key
                ) );
            }
            $check = self::sanitize_field_value( $fields[ $key ], $raw );
            if ( ! $check['ok'] ) {
                return $fail( $check['error'] );
            }
            $clean[ $key ] = $check['value'];
        }

        // Pass 2 — write, only after every value proved safe.
        if ( $item_id ) {
            $item = self::get_item( $item_id );
            if ( ! $item || (int) $item->collection_id !== (int) $collection_id ) {
                return $fail( __( 'Row not found in this collection.', 'loopkit' ) );
            }
            if ( ! self::update_item( $item_id, array( 'data' => $clean ) ) ) {
                return $fail( __( 'Failed to save.', 'loopkit' ) );
            }
            return array(
                'ok'       => true,
                'item_id'  => (int) $item_id,
                'created'  => false,
                'msg'      => __( 'Row saved.', 'loopkit' ),
            );
        }

        $new_id = self::create_item( $collection_id, $clean, 'published' );
        if ( ! $new_id ) {
            return $fail( __( 'Failed to create.', 'loopkit' ) );
        }
        return array(
            'ok'       => true,
            'item_id'  => (int) $new_id,
            'created'  => true,
            'msg'      => sprintf(
                /* translators: %d: new item id */
                __( 'Row added.', 'loopkit' )
            ),
        );
    }

    /* ═══════════════════════════════════════════════════════════
     *  ADMIN — menu registration, screens, form handlers, assets.
     *  Stages B and C implemented; dynamic tags and rewrite rules
     *  further down are still Stage D–F.
     * ═══════════════════════════════════════════════════════════ */

    /**
     * Admin menu registration: the top-level CMS screen.
     *
     * Submenus are added later on the same admin_menu hook (priority 35)
     * so the parent exists before its children attach.
     */
    public function register_menu() {
        $hook = add_menu_page(
            __( 'Loopkit CMS', 'loopkit' ),
            __( 'CMS', 'loopkit' ),
            'manage_options',
            'loopkit_cms',
            array( $this, 'render_dashboard' ),
            'dashicons-database',
            30
        );
        if ( $hook ) {
            add_action( 'admin_head-' . $hook, array( $this, 'admin_styles' ) );
        }
        add_action( 'admin_menu', array( $this, 'register_submenus' ), 35 );
    }

    /**
     * CMS submenus: Dashboard, Collections, New Collection, Fields, Items.
     *
     * Every page slug starts with loopkit_cms so enqueue_admin_assets()
     * can spot a CMS screen by $_GET['page'] regardless of hook suffix
     * (submenu hook suffixes depend on the menu title and are not stable).
     * The Items screen picks the first collection when opened without one,
     * so the bare menu link always lands somewhere useful.
     */
    public function register_submenus() {
        $screens = array(
            // menu title, page title, menu slug, render callback.
            array(
                __( 'Dashboard', 'loopkit' ),
                __( 'CMS', 'loopkit' ),
                'loopkit_cms',
                array( $this, 'render_dashboard' ),
            ),
            array(
                __( 'Collections', 'loopkit' ),
                __( 'Collections', 'loopkit' ),
                'loopkit_cms_collections',
                array( $this, 'render_collections' ),
            ),
            array(
                __( 'Add New', 'loopkit' ),
                __( 'New Collection', 'loopkit' ),
                'loopkit_cms_new',
                array( $this, 'render_collection_form' ),
            ),
            array(
                __( 'Fields', 'loopkit' ),
                __( 'Fields', 'loopkit' ),
                'loopkit_cms_fields',
                array( $this, 'render_fields' ),
            ),
            array(
                __( 'Items', 'loopkit' ),
                __( 'Items', 'loopkit' ),
                'loopkit_cms_items',
                array( $this, 'render_items' ),
            ),
            array(
                __( 'Import / Export', 'loopkit' ),
                __( 'Import / Export', 'loopkit' ),
                'loopkit_cms_io',
                array( $this, 'render_io' ),
            ),
        );

        foreach ( $screens as $screen ) {
            $hook = add_submenu_page(
                'loopkit_cms',
                $screen[1],
                $screen[0],
                'manage_options',
                $screen[2],
                $screen[3]
            );
            if ( $hook ) {
                add_action( 'admin_head-' . $hook, array( $this, 'admin_styles' ) );
            }
        }
    }

    /**
     * CMS dashboard: headline counts, the collections overview, quick actions.
     *
     * Counts come straight from get_collections(), which already joins the
     * per-collection field and item totals — no extra queries.
     */
    public function render_dashboard() {
        $collections  = Loopkit_Module_CMS::get_collections();
        $total_fields = 0;
        $total_items  = 0;
        foreach ( $collections as $c ) {
            $total_fields += (int) $c->field_count;
            $total_items  += (int) $c->item_count;
        }
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <?php $this->print_notices(); ?>
            <h1 class="wp-heading-inline"><?php esc_html_e( 'CMS', 'loopkit' ); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit_cms_new' ) ); ?>"><?php esc_html_e( 'New Collection', 'loopkit' ); ?></a>
            <hr class="wp-header-end">

            <div class="lk-cms-stats">
                <div class="lk-cms-stat">
                    <span class="lk-cms-stat-icon"><span class="dashicons dashicons-database"></span></span>
                    <div class="lk-cms-stat-body">
                        <div class="lk-cms-stat-value"><?php echo esc_html( count( $collections ) ); ?></div>
                        <div class="lk-cms-stat-label"><?php esc_html_e( 'Collections', 'loopkit' ); ?></div>
                    </div>
                </div>
                <div class="lk-cms-stat">
                    <span class="lk-cms-stat-icon"><span class="dashicons dashicons-edit"></span></span>
                    <div class="lk-cms-stat-body">
                        <div class="lk-cms-stat-value"><?php echo esc_html( $total_fields ); ?></div>
                        <div class="lk-cms-stat-label"><?php esc_html_e( 'Fields', 'loopkit' ); ?></div>
                    </div>
                </div>
                <div class="lk-cms-stat">
                    <span class="lk-cms-stat-icon"><span class="dashicons dashicons-layout"></span></span>
                    <div class="lk-cms-stat-body">
                        <div class="lk-cms-stat-value"><?php echo esc_html( $total_items ); ?></div>
                        <div class="lk-cms-stat-label"><?php esc_html_e( 'Items', 'loopkit' ); ?></div>
                    </div>
                </div>
            </div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Collection', 'loopkit' ); ?></th>
                        <th style="width:100px"><?php esc_html_e( 'Fields', 'loopkit' ); ?></th>
                        <th style="width:100px"><?php esc_html_e( 'Items', 'loopkit' ); ?></th>
                        <th style="width:150px"><?php esc_html_e( 'Updated', 'loopkit' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $collections ) ) : ?>
                    <tr>
                        <td colspan="4">
                            <div class="lk-cms-empty">
                                <p><?php esc_html_e( 'No collections yet. A Collection is simply a list — Team Members, Properties, Testimonials. Create one, give it fields, then add items.', 'loopkit' ); ?></p>
                                <a class="lk-btn lk-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit_cms_new' ) ); ?>">
                                    <span class="dashicons dashicons-plus"></span>
                                    <span class="lk-btn-text"><?php esc_html_e( 'Create your first Collection', 'loopkit' ); ?></span>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $collections as $c ) : ?>
                        <tr>
                            <td>
                                <span class="dashicons <?php echo esc_attr( $c->icon ); ?> lk-cms-row-icon"></span>
                                <?php $items_url = admin_url( 'admin.php?page=loopkit_cms_items&collection_id=' . (int) $c->id ); ?>
                                <?php $fields_url = admin_url( 'admin.php?page=loopkit_cms_fields&collection_id=' . (int) $c->id ); ?>
                                <a href="<?php echo esc_url( $items_url ); ?>">
                                    <strong><?php echo esc_html( $c->name ); ?></strong>
                                </a>
                                <code class="lk-cms-key"><?php echo esc_html( $c->slug ); ?></code>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( $fields_url ); ?>">
                                    <?php
                                    printf(
                                        /* translators: %s: number of fields */
                                        esc_html( _n( '%s field', '%s fields', (int) $c->field_count, 'loopkit' ) ),
                                        esc_html( number_format_i18n( (int) $c->field_count ) )
                                    );
                                    ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( $items_url ); ?>">
                                    <?php
                                    if ( (int) $c->item_count ) {
                                        echo esc_html( number_format_i18n( (int) $c->item_count ) );
                                    } else {
                                        esc_html_e( 'Add content', 'loopkit' );
                                    }
                                    ?>
                                </a>
                            </td>
                            <td>
                                <?php echo esc_html( mysql2date( 'M j, Y', $c->updated_at ) ); ?>
                                <div class="row-actions">
                                    <span><a href="<?php echo esc_url( $items_url ); ?>"><?php esc_html_e( 'Content', 'loopkit' ); ?></a> | </span>
                                    <span><a href="<?php echo esc_url( $fields_url ); ?>"><?php esc_html_e( 'Fields', 'loopkit' ); ?></a></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <p class="lk-cms-foot"><?php esc_html_e( 'Open a collection to edit its content, or use Fields to change its structure.', 'loopkit' ); ?></p>
        </div>
        <?php
    }

    /**
     * Print and clear the notice buffered by handle_actions().
     *
     * Called at the top of every CMS screen so save/delete feedback lands
     * inside the screen (never before the HTML header).
     */
    protected function print_notices() {
        $key    = 'loopkit_cms_notice_' . get_current_user_id();
        $notice = get_transient( $key );
        if ( $notice ) {
            delete_transient( $key );
            echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput -- built by the handlers below from escaped strings.
        }
    }

    /**
     * Handle form POSTs on admin pages.
     *
     * Guarded on the CMS action field: this runs on admin_init for every
     * POST in wp-admin (other Loopkit screens, admin-ajax, other plugins),
     * so without the guard check_ajax_referer() would have killed unrelated
     * POSTs the moment the module was switched on. The handlers echo their
     * notice into a buffer; it is stashed in a per-user transient and the
     * browser is redirected (post/redirect/get), so nothing is echoed
     * before headers and a refresh never resubmits.
     */
    public function handle_actions() {
        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }
        // Two entry shapes: an explicit loopkit_cms_action field (most forms),
        // or a row_action="intent:id" submit button from the items table, where
        // every row shares one form — the button's value carries the intent and
        // the exact row it belongs to, so a clicked row can only act on itself.
        $has_action = ! empty( $_POST['loopkit_cms_action'] );
        $has_row    = ! empty( $_POST['row_action'] );
        if ( ! $has_action && ! $has_row ) {
            return;
        }
        check_ajax_referer( 'loopkit_cms_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied.', 'loopkit' ) );
        }
        $action = '';
        if ( $has_action ) {
            $action = sanitize_key( wp_unslash( $_POST['loopkit_cms_action'] ) );
        } else {
            $parts  = explode( ':', sanitize_text_field( wp_unslash( $_POST['row_action'] ) ) );
            $intent = $parts[0] ?? '';
            $target = isset( $parts[1] ) ? absint( $parts[1] ) : 0;
            if ( 'delete' === $intent && $target ) {
                $action          = 'delete_item';
                $_POST['item_id'] = $target;
            } elseif ( 'duplicate' === $intent && $target ) {
                $action          = 'duplicate_item';
                $_POST['item_id'] = $target;
            } elseif ( 'new' === $intent ) {
                $action           = 'save_item';
                $_POST['item_id'] = 0;
                $_POST['data']    = array();
            }
        }
        if ( '' === $action ) {
            $back = wp_get_referer();
            // Fall back to the items screen of the first collection, never the
            // generic dashboard — the CMS must always land on something useful.
            $fallback = admin_url( 'admin.php?page=loopkit_cms_collections' );
            wp_safe_redirect( $back ? $back : $fallback );
            exit;
        }

        ob_start();
        switch ( $action ) {
            case 'save_collection':
                $this->do_save_collection();
                break;
            case 'delete_collection':
                $this->do_delete_collection();
                break;
            case 'save_field':
                $this->do_save_field();
                break;
            case 'delete_field':
                $this->do_delete_field();
                break;
            case 'reorder_fields':
                $this->do_reorder_fields();
                break;
            case 'duplicate_field':
                $this->do_duplicate_field();
                break;
            case 'duplicate_collection':
                $this->do_duplicate_collection();
                break;
            case 'upload_csv':
                $this->do_upload_csv();
                break;
            case 'run_import':
                $this->do_run_import();
                break;
            case 'cancel_import':
                $this->do_cancel_import();
                break;
            case 'save_items':
                $this->do_save_items();
                break;
            case 'save_item':
                $this->do_save_item();
                break;
            case 'save_cells':
                $this->do_save_cells();
                break;
            case 'delete_item':
                $this->do_delete_item();
                break;
            case 'duplicate_item':
                $this->do_duplicate_item();
                break;
        }
        $notice = ob_get_clean();
        if ( '' !== trim( (string) $notice ) ) {
            set_transient( 'loopkit_cms_notice_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );
        }

        wp_safe_redirect( $this->redirect_for_action( $action ) );
        exit;
    }

    /**
     * Where an action should land once it has run.
     *
     * This used to be wp_get_referer(), which looks right and is not:
     * it returns false when the referer equals the current request URI,
     * and the Fields and Items forms post to the page they are already on.
     * So every field save and every row save fell through to the generic
     * fallback and dumped the user on the Collections list.
     *
     * @param string $action The action that just ran.
     * @return string Admin URL to land on.
     */
    protected function redirect_for_action( $action ) {
        $collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

        $field_screen = array( 'save_field', 'delete_field', 'reorder_fields', 'duplicate_field' );
        $item_screen  = array( 'save_items', 'save_item', 'save_cells', 'delete_item', 'duplicate_item' );
        $io_screen    = array( 'upload_csv', 'run_import', 'cancel_import' );

        if ( in_array( $action, $io_screen, true ) ) {
            $url = admin_url( 'admin.php?page=loopkit_cms_io' );
            return $collection_id ? add_query_arg( 'collection_id', $collection_id, $url ) : $url;
        }

        if ( $collection_id && in_array( $action, $field_screen, true ) ) {
            return admin_url( 'admin.php?page=loopkit_cms_fields&collection_id=' . $collection_id );
        }

        if ( $collection_id && in_array( $action, $item_screen, true ) ) {
            $url = admin_url( 'admin.php?page=loopkit_cms_items&collection_id=' . $collection_id );
            // Keep the view the user had: page, sort, search, rows per page.
            foreach ( array( 'paged', 'orderby', 'order', 'per_page', 's' ) as $keep ) {
                if ( isset( $_POST[ $keep ] ) && '' !== $_POST[ $keep ] ) { // phpcs:ignore WordPress.Security.NonceVerification
                    $url = add_query_arg( $keep, sanitize_text_field( wp_unslash( $_POST[ $keep ] ) ), $url ); // phpcs:ignore WordPress.Security.NonceVerification
                }
            }
            return $url;
        }

        // Collection-level actions, and anything unrecognised, list the
        // collections — there is nowhere more specific to go.
        return admin_url( 'admin.php?page=loopkit_cms_collections' );
    }

    /**
     * Collections list: every collection with counts and row actions.
     *
     * Delete works with or without JavaScript: the row is a real POST form,
     * and cms-admin.js upgrades the button to an in-place AJAX delete.
     */
    public function render_collections() {
        $collections = Loopkit_Module_CMS::get_collections();
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <?php $this->print_notices(); ?>
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Collections', 'loopkit' ); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit_cms_new' ) ); ?>"><?php esc_html_e( 'Add New Collection', 'loopkit' ); ?></a>
            <hr class="wp-header-end">

            <div class="cms-messages"></div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Collection', 'loopkit' ); ?></th>
                        <th style="width:170px"><?php esc_html_e( 'Slug', 'loopkit' ); ?></th>
                        <th style="width:100px"><?php esc_html_e( 'Fields', 'loopkit' ); ?></th>
                        <th style="width:80px"><?php esc_html_e( 'Items', 'loopkit' ); ?></th>
                        <th style="width:130px"><?php esc_html_e( 'Updated', 'loopkit' ); ?></th>
                        <th style="width:210px"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'loopkit' ); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $collections ) ) : ?>
                    <tr>
                        <td colspan="6">
                            <?php
                            printf(
                                /* translators: %s: link to the New Collection screen */
                                wp_kses_post( __( 'No collections yet. %s to create your first one.', 'loopkit' ) ),
                                '<a href="' . esc_url( admin_url( 'admin.php?page=loopkit_cms_new' ) ) . '">' . esc_html__( 'Add a Collection', 'loopkit' ) . '</a>'
                            );
                            ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $collections as $c ) : ?>
                        <?php
                        $fields_url = admin_url( 'admin.php?page=loopkit_cms_fields&collection_id=' . (int) $c->id );
                        $items_url  = admin_url( 'admin.php?page=loopkit_cms_items&collection_id=' . (int) $c->id );
                        $edit_url   = admin_url( 'admin.php?page=loopkit_cms_new&id=' . (int) $c->id );
                        ?>
                        <tr>
                            <td>
                                <span class="dashicons <?php echo esc_attr( $c->icon ); ?> lk-cms-row-icon"></span>
                                <a href="<?php echo esc_url( $fields_url ); ?>"><strong><?php echo esc_html( $c->name ); ?></strong></a>
                            </td>
                            <td><code class="lk-cms-key"><?php echo esc_html( $c->slug ); ?></code></td>
                            <td>
                                <a href="<?php echo esc_url( $fields_url ); ?>">
                                    <?php
                                    printf(
                                        /* translators: %s: number of fields */
                                        esc_html( _n( '%s field', '%s fields', (int) $c->field_count, 'loopkit' ) ),
                                        esc_html( number_format_i18n( (int) $c->field_count ) )
                                    );
                                    ?>
                                </a>
                            </td>
                            <td><?php echo esc_html( number_format_i18n( (int) $c->item_count ) ); ?></td>
                            <td><?php echo esc_html( mysql2date( 'M j, Y', $c->updated_at ) ); ?></td>
                            <td class="lk-cms-actions lk-col-actions">
                                <div class="lk-item-actions">
                                    <a href="<?php echo esc_url( $items_url ); ?>" class="lk-row-act"
                                       title="<?php esc_attr_e( 'Edit this collection\'s content', 'loopkit' ); ?>">
                                        <span class="dashicons dashicons-editor-table"></span>
                                        <span class="lk-row-act-text"><?php esc_html_e( 'Content', 'loopkit' ); ?></span>
                                    </a>
                                    <a href="<?php echo esc_url( $fields_url ); ?>" class="lk-row-act"
                                       title="<?php esc_attr_e( 'Change its fields', 'loopkit' ); ?>">
                                        <span class="dashicons dashicons-forms"></span>
                                        <span class="lk-row-act-text"><?php esc_html_e( 'Fields', 'loopkit' ); ?></span>
                                    </a>
                                    <a href="<?php echo esc_url( $edit_url ); ?>" class="lk-row-act"
                                       title="<?php esc_attr_e( 'Rename it or change its icon', 'loopkit' ); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                        <span class="lk-row-act-text"><?php esc_html_e( 'Edit', 'loopkit' ); ?></span>
                                    </a>
                                    <form method="post" class="lk-cms-inline-form">
                                        <?php wp_nonce_field( 'loopkit_cms_admin', 'nonce' ); ?>
                                        <input type="hidden" name="loopkit_cms_action" value="duplicate_collection">
                                        <input type="hidden" name="collection_id" value="<?php echo (int) $c->id; ?>">
                                        <button type="submit" class="lk-row-act"
                                                title="<?php esc_attr_e( 'Copy this collection, its fields and its rows', 'loopkit' ); ?>"
                                                onclick="return confirm('<?php echo esc_js( __( 'Copy this collection with all of its fields and rows?', 'loopkit' ) ); ?>');">
                                            <span class="dashicons dashicons-admin-page"></span>
                                            <span class="lk-row-act-text"><?php esc_html_e( 'Duplicate', 'loopkit' ); ?></span>
                                        </button>
                                    </form>
                                    <form method="post" class="lk-cms-inline-form">
                                        <?php wp_nonce_field( 'loopkit_cms_admin', 'nonce' ); ?>
                                        <input type="hidden" name="loopkit_cms_action" value="delete_collection">
                                        <input type="hidden" name="collection_id" value="<?php echo (int) $c->id; ?>">
                                        <button type="submit" class="lk-row-act is-danger delete-collection" data-id="<?php echo (int) $c->id; ?>"
                                                title="<?php esc_attr_e( 'Delete this collection', 'loopkit' ); ?>"
                                                onclick="return confirm('<?php echo esc_js( __( 'Delete this collection and all of its fields and items?', 'loopkit' ) ); ?>');">
                                            <span class="dashicons dashicons-trash"></span>
                                            <span class="lk-row-act-text"><?php esc_html_e( 'Delete', 'loopkit' ); ?></span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /** Save items from admin form. */
    protected function do_save_items() {
        $collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        $item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
        $fields = isset( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array();
        if ( ! $collection_id ) {
            echo '<div class="error"><p>' . esc_html__( 'Missing collection.', 'loopkit' ) . '</p></div>';
            return;
        }
        $collection = Loopkit_Module_CMS::get_collection( $collection_id );
        if ( ! $collection ) {
            echo '<div class="error"><p>' . esc_html__( 'Collection not found.', 'loopkit' ) . '</p></div>';
            return;
        }
        if ( $item_id ) {
            $item = Loopkit_Module_CMS::get_item( $item_id );
            if ( $item && $item->collection_id == $collection_id ) {
                $data = array();
                foreach ( $fields as $key => $value ) {
                    if ( is_array( $value ) ) {
                        $value = reset( $value );
                    }
                    $data[ sanitize_key( $key ) ] = sanitize_text_field( $value );
                }
                if ( Loopkit_Module_CMS::update_item( $item_id, array( 'data' => $data ) ) ) {
                    echo '<div class="updated"><p>' . esc_html__( 'Item updated.', 'loopkit' ) . '</p></div>';
                } else {
                    echo '<div class="error"><p>' . esc_html__( 'Failed to update.', 'loopkit' ) . '</p></div>';
                }
            } else {
                echo '<div class="error"><p>' . esc_html__( 'Item not found.', 'loopkit' ) . '</p></div>';
            }
        } else {
            $data = array();
            foreach ( $fields as $key => $value ) {
                if ( is_array( $value ) ) {
                    $value = reset( $value );
                }
                $data[ sanitize_key( $key ) ] = sanitize_text_field( $value );
            }
            $new_id = Loopkit_Module_CMS::create_item( $collection_id, $data, 'published' );
            if ( $new_id ) {
                echo '<div class="updated"><p>' . esc_html__( 'Item created.', 'loopkit' ) . '</p></div>';
            } else {
                echo '<div class="error"><p>' . esc_html__( 'Failed to create.', 'loopkit' ) . '</p></div>';
            }
        }
    }



    /**
     * Items screen — the collection as a spreadsheet: one row per item,
     * one column per field, cells edited in place.
     *
     * Server-rendered and usable without JavaScript: sorting, search and
     * paging are GET links/forms, and cell edits, add/duplicate/delete all
     * ride one POST form. cms-admin.js upgrades saving to per-cell AJAX.
     */
    public function render_items() {
        $collections = Loopkit_Module_CMS::get_collections();
        if ( ! $collections ) {
            $this->render_no_collections( __( 'Content', 'loopkit' ) );
            return;
        }

        $collection_id = isset( $_GET['collection_id'] ) ? absint( $_GET['collection_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $collection    = null;
        if ( $collection_id ) {
            foreach ( $collections as $c ) {
                if ( (int) $c->id === $collection_id ) {
                    $collection = $c;
                    break;
                }
            }
            if ( ! $collection ) {
                // Asked for a collection that has gone: show the first one
                // instead. Redirecting from here is too late to work.
                $collection    = $collections[0];
                $collection_id = (int) $collection->id;
            }
        } else {
            // Bare menu link: land on the first collection.
            $collection    = $collections[0];
            $collection_id = (int) $collection->id;
        }

        $fields = Loopkit_Module_CMS::get_fields( $collection_id );
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification

        $per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 50; // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! in_array( $per_page, array( 25, 50, 100 ), true ) ) {
            $per_page = 50;
        }
        $orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id'; // phpcs:ignore WordPress.Security.NonceVerification
        $order   = ( isset( $_GET['order'] ) && 'ASC' === strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification

        // Sorting may only use id or one of this collection's field keys.
        if ( 'id' !== $orderby ) {
            $valid = false;
            foreach ( $fields as $f ) {
                if ( $f->field_key === $orderby ) {
                    $valid = true;
                    break;
                }
            }
            if ( ! $valid ) {
                $orderby = 'id';
            }
        }

        $result = Loopkit_Module_CMS::get_items(
            $collection_id,
            array(
                'search'   => $search,
                'per_page' => $per_page,
                'page'     => $paged,
                'orderby'  => $orderby,
                'order'    => $order,
            )
        );
        $items  = $result['items'];
        $total  = (int) $result['total'];
        $pages  = max( 1, (int) $result['pages'] );
        $offset = ( $paged - 1 ) * $per_page;

        // The single-item editor opens for ?item_id=N — rendered server-side
        // already open, so long fields stay editable without JavaScript.
        $edit_item = null;
        $edit_id   = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        if ( $edit_id ) {
            $candidate = Loopkit_Module_CMS::get_item( $edit_id );
            if ( $candidate && (int) $candidate->collection_id === $collection_id ) {
                $edit_item = $candidate;
            }
        }
        $col_count = count( $fields ) + 2;
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple lk-cms-items-screen"
             data-collection="<?php echo (int) $collection_id; ?>"
             data-orderby="<?php echo esc_attr( $orderby ); ?>"
             data-order="<?php echo esc_attr( $order ); ?>"
             data-page="<?php echo (int) $paged; ?>"
             data-per-page="<?php echo (int) $per_page; ?>">
            <?php $this->print_notices(); ?>
            <h1 class="wp-heading-inline"><?php printf( esc_html__( 'Items — %s', 'loopkit' ), esc_html( $collection->name ) ); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit_cms_fields&collection_id=' . $collection_id ) ); ?>"><?php esc_html_e( 'Fields', 'loopkit' ); ?></a>
            <hr class="wp-header-end">

            <div class="cms-messages"></div>
            <div class="items-messages"></div>

            <form method="get" class="lk-items-filters">
                <input type="hidden" name="page" value="loopkit_cms_items">
                <input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
                <input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>">
                <label class="screen-reader-text" for="lk-items-collection"><?php esc_html_e( 'Collection', 'loopkit' ); ?></label>
                <select id="lk-items-collection" name="collection_id">
                    <?php foreach ( $collections as $c ) : ?>
                        <option value="<?php echo (int) $c->id; ?>" <?php selected( (int) $c->id, $collection_id ); ?>><?php echo esc_html( $c->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search rows…', 'loopkit' ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'Search', 'loopkit' ); ?></button>
                <label class="lk-items-perpage" for="lk-items-perpage">
                    <span class="screen-reader-text"><?php esc_html_e( 'Rows per page', 'loopkit' ); ?></span>
                    <select id="lk-items-perpage" name="per_page">
                        <?php foreach ( array( 25, 50, 100 ) as $pp ) : ?>
                            <option value="<?php echo (int) $pp; ?>" <?php selected( $pp, $per_page ); ?>><?php printf( esc_html__( '%s / page', 'loopkit' ), esc_html( number_format_i18n( $pp ) ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <span class="lk-items-total"><?php printf( esc_html__( '%s total', 'loopkit' ), esc_html( number_format_i18n( $total ) ) ); ?></span>
            </form>

            <?php if ( empty( $fields ) ) : ?>
                <div class="lk-card">
                    <div class="lk-card-body lk-cms-empty">
                        <?php
                        printf(
                            /* translators: %s: link to the Fields screen */
                            wp_kses_post( __( 'This collection has no fields yet. %s to add its columns first.', 'loopkit' ) ),
                            '<a href="' . esc_url( admin_url( 'admin.php?page=loopkit_cms_fields&collection_id=' . $collection_id ) ) . '">' . esc_html__( 'Open Fields', 'loopkit' ) . '</a>'
                        );
                        ?>
                    </div>
                </div>
            <?php else : ?>
                <form method="post" class="lk-items-form">
                    <?php wp_nonce_field( 'loopkit_cms_admin', 'nonce' ); ?>
                    <input type="hidden" name="collection_id" value="<?php echo (int) $collection_id; ?>">
                    <input type="hidden" name="paged" value="<?php echo (int) $paged; ?>">
                    <input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
                    <input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>">
                    <input type="hidden" name="per_page" value="<?php echo (int) $per_page; ?>">
                    <input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">

                    <div class="lk-items-wrap">
                        <table class="widefat striped lk-items-table">
                            <thead>
                                <tr>
                                    <th class="lk-col-num" scope="col">#</th>
                                    <?php foreach ( $fields as $f ) : ?>
                                        <?php
                                        $next_order = ( $orderby === $f->field_key && 'ASC' === $order ) ? 'DESC' : 'ASC';
                                        $sort_url   = $this->items_url(
                                            array(
                                                'orderby' => $f->field_key,
                                                'order'   => $next_order,
                                                'paged'   => 1,
                                            )
                                        );
                                        $is_current = ( $orderby === $f->field_key );
                                        ?>
                                        <th scope="col" class="lk-col-field<?php echo $is_current ? ' sorted' : ''; ?>">
                                            <a href="<?php echo esc_url( $sort_url ); ?>">
                                                <?php echo esc_html( $f->label ); ?>
                                                <?php if ( $is_current ) : ?>
                                                    <span class="dashicons <?php echo 'ASC' === $order ? 'dashicons-arrow-up' : 'dashicons-arrow-down'; ?>"></span>
                                                    <span class="screen-reader-text"><?php echo 'ASC' === $order ? esc_html__( 'sorted ascending', 'loopkit' ) : esc_html__( 'sorted descending', 'loopkit' ); ?></span>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="lk-col-actions" scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'loopkit' ); ?></span></th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php if ( empty( $items ) ) : ?>
                                <tr class="lk-cms-emptyrow">
                                    <td colspan="<?php echo (int) $col_count; ?>">
                                        <?php echo '' !== $search ? esc_html__( 'No rows match that search.', 'loopkit' ) : esc_html__( 'No rows yet — add the first one below.', 'loopkit' ); ?>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ( $items as $i => $item ) : ?>
                                    <?php $this->render_item_row( $item, $fields, $offset + $i + 1, $collection_id ); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                                <tr class="lk-cms-addrow">
                                    <td colspan="<?php echo (int) $col_count; ?>">
                                        <button type="submit" class="button lk-cms-add-row" name="row_action" value="new:0">
                                            <span class="dashicons dashicons-plus-alt2"></span>
                                            <?php esc_html_e( 'Add row', 'loopkit' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="lk-items-bulkbar">
                        <button type="submit" name="loopkit_cms_action" value="save_cells" class="button button-primary"><?php esc_html_e( 'Save changes', 'loopkit' ); ?></button>
                        <span class="lk-items-hint"><?php esc_html_e( 'Cells save as you leave them — Save changes commits every cell on this page and is the no-JavaScript path.', 'loopkit' ); ?></span>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ( $pages > 1 ) : ?>
                <div class="tablenav"><div class="tablenav-pages"><?php
                    echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        'base'    => admin_url( 'admin.php?page=loopkit_cms_items&collection_id=' . (int) $collection_id . '&orderby=' . rawurlencode( $orderby ) . '&order=' . $order . '&per_page=' . (int) $per_page . '&s=' . rawurlencode( $search ) . '&paged=%#%' ),
                        'format'  => '',
                        'current' => $paged,
                        'total'   => $pages,
                    ) );
                ?></div></div>
            <?php endif; ?>

            <?php $this->render_item_editor( $collection, $fields, $edit_item, $items ); ?>
        </div>
        <?php
    }

    /**
     * Build a URL back to this screen with some query args replaced.
     *
     * Keeps collection, search, sort and paging intact so a sort click
     * never drops the search term. item_id is dropped unless passed in:
     * column links return to the plain table.
     *
     * @param array $args Query args to set.
     * @return string Absolute admin URL.
     */
    protected function items_url( $args ) {
        $g    = ( isset( $_GET ) && is_array( $_GET ) ) ? wp_unslash( $_GET ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
        $get  = function ( $key, $default = '' ) use ( $g ) {
            return ( isset( $g[ $key ] ) && ! is_array( $g[ $key ] ) ) ? (string) $g[ $key ] : $default;
        };
        $merged = array_merge(
            array(
                'page'          => 'loopkit_cms_items',
                'collection_id' => $get( 'collection_id' ),
                's'             => $get( 's' ),
                'orderby'       => $get( 'orderby', 'id' ),
                'order'         => $get( 'order', 'DESC' ),
                'per_page'      => $get( 'per_page', '50' ),
                'paged'         => $get( 'paged', '1' ),
            ),
            $args
        );

        $pairs = array();
        foreach ( $merged as $k => $v ) {
            if ( null === $v || '' === $v ) {
                continue;
            }
            $pairs[] = $k . '=' . rawurlencode( (string) $v );
        }
        return admin_url( 'admin.php?' . implode( '&', $pairs ) );
    }

    /**
     * One table row: row number, one cell per field, row actions.
     *
     * The Delete/Duplicate submit buttons carry row_action="intent:{id}",
     * so without JavaScript the posted button alone identifies the row —
     * a clicked row can only ever act on itself.
     *
     * @param object $item          Row from get_items().
     * @param array  $fields        Column definitions.
     * @param int    $row_num       Display number (page offset + index).
     * @param int    $collection_id Collection the row belongs to.
     */
    protected function render_item_row( $item, $fields, $row_num, $collection_id ) {
        $data     = is_array( $item->data ) ? $item->data : array();
        $edit_url = $this->items_url( array( 'item_id' => (int) $item->id ) );
        ?>
        <tr data-id="<?php echo (int) $item->id; ?>">
            <td class="lk-col-num lk-cms-rownum"><?php echo (int) $row_num; ?></td>
            <?php foreach ( $fields as $f ) : ?>
                <?php $this->render_item_cell( $f, $item, $data, $edit_url ); ?>
            <?php endforeach; ?>
            <td class="lk-cms-actions lk-col-actions">
                <div class="lk-item-actions">
                    <a href="<?php echo esc_url( $edit_url ); ?>" class="lk-row-act lk-cms-edit-item"
                       data-id="<?php echo (int) $item->id; ?>"
                       title="<?php esc_attr_e( 'Edit this row', 'loopkit' ); ?>">
                        <span class="dashicons dashicons-edit"></span>
                        <span class="lk-row-act-text"><?php esc_html_e( 'Edit', 'loopkit' ); ?></span>
                    </a>
                    <button type="submit" class="lk-row-act lk-cms-dup"
                            data-id="<?php echo (int) $item->id; ?>"
                            name="row_action" value="duplicate:<?php echo (int) $item->id; ?>"
                            title="<?php esc_attr_e( 'Make a copy of this row', 'loopkit' ); ?>">
                        <span class="dashicons dashicons-admin-page"></span>
                        <span class="lk-row-act-text"><?php esc_html_e( 'Duplicate', 'loopkit' ); ?></span>
                    </button>
                    <button type="submit" class="lk-row-act is-danger delete-item"
                            data-id="<?php echo (int) $item->id; ?>"
                            name="row_action" value="delete:<?php echo (int) $item->id; ?>"
                            title="<?php esc_attr_e( 'Delete this row', 'loopkit' ); ?>"
                            onclick="return confirm('<?php echo esc_js( __( 'Delete this row?', 'loopkit' ) ); ?>');">
                        <span class="dashicons dashicons-trash"></span>
                        <span class="lk-row-act-text"><?php esc_html_e( 'Delete', 'loopkit' ); ?></span>
                    </button>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * One editable cell, shaped by the field type.
     *
     * Text/number/date/toggle/select/image edit in place; long_text,
     * rich_text, gallery and file show a preview that links to the
     * single-item editor instead of cramming a paragraph into a cell.
     *
     * @param object $field    Column definition.
     * @param object $item     Row being drawn.
     * @param array  $data     The row's decoded data.
     * @param string $edit_url URL of the single-item editor for this row.
     */
    protected function render_item_cell( $field, $item, $data, $edit_url ) {
        $value = isset( $data[ $field->field_key ] ) ? $data[ $field->field_key ] : '';
        if ( is_array( $value ) ) {
            $value = ''; // Legacy arrays never render into a cell.
        }
        $key   = $field->field_key;
        $name  = 'cells[' . (int) $item->id . '][' . $key . ']';
        ?>
        <td class="lk-cms-cell lk-cms-cell--<?php echo esc_attr( $field->type ); ?>"
            data-item="<?php echo (int) $item->id; ?>"
            data-key="<?php echo esc_attr( $key ); ?>">
            <?php
            switch ( $field->type ) {
                case 'long_text':
                case 'rich_text':
                    // Type straight into the cell, or open the side editor for
                    // room. Both work; neither is the only way in.
                    ?>
                    <div class="lk-cms-cell-long">
                        <textarea class="lk-cell-input lk-cms-cell-textarea"
                                  name="<?php echo esc_attr( $name ); ?>"
                                  rows="1"
                                  aria-label="<?php echo esc_attr( $field->label ); ?>"
                                  placeholder="<?php echo esc_attr( $field->label ); ?>"><?php echo esc_textarea( (string) $value ); ?></textarea>
                        <a class="lk-cms-cell-expand" href="<?php echo esc_url( $edit_url ); ?>"
                           title="<?php esc_attr_e( 'Open in the item editor', 'loopkit' ); ?>"
                           aria-label="<?php esc_attr_e( 'Open in the item editor', 'loopkit' ); ?>">
                            <span class="dashicons dashicons-editor-expand"></span>
                        </a>
                    </div>
                    <?php
                    break;

                case 'gallery':
                case 'file':
                    // Pickers, not text — nothing to type into a cell, so it
                    // previews the value and opens the editor to change it.
                    $plain   = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $value ) ) );
                    $preview = ( '' === $plain ) ? __( '— empty —', 'loopkit' ) : wp_html_excerpt( $plain, 80, '…' );
                    ?>
                    <a class="lk-cms-cell-preview<?php echo '' === $plain ? ' is-empty' : ''; ?>"
                       href="<?php echo esc_url( $edit_url ); ?>"
                       data-value="<?php echo esc_attr( (string) $value ); ?>"
                       title="<?php esc_attr_e( 'Edit in the item editor', 'loopkit' ); ?>"><?php echo esc_html( $preview ); ?></a>
                    <?php
                    break;

                case 'toggle':
                    ?>
                    <label class="lk-toggle-wrap lk-cms-toggle" title="<?php echo esc_attr( $field->label ); ?>">
                        <input type="checkbox" class="lk-cell-input"
                               name="<?php echo esc_attr( $name ); ?>"
                               value="1" <?php checked( (string) $value, '1' ); ?>>
                        <span class="lk-toggle-slider"></span>
                    </label>
                    <?php
                    break;

                case 'select':
                    $options = ( isset( $field->settings['options'] ) && is_array( $field->settings['options'] ) )
                        ? $field->settings['options']
                        : array();
                    ?>
                    <select class="lk-cell-input" name="<?php echo esc_attr( $name ); ?>">
                        <option value="" <?php selected( (string) $value, '' ); ?>>—</option>
                        <?php foreach ( $options as $opt ) : ?>
                            <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( (string) $value, (string) $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php
                    break;

                case 'date':
                    ?>
                    <input type="date" class="lk-cell-input" name="<?php echo esc_attr( $name ); ?>"
                           value="<?php echo esc_attr( (string) $value ); ?>">
                    <?php
                    break;

                case 'number':
                    ?>
                    <input type="number" step="any" class="lk-cell-input" name="<?php echo esc_attr( $name ); ?>"
                           value="<?php echo esc_attr( (string) $value ); ?>">
                    <?php
                    break;

                case 'image':
                case 'svg':
                case 'video':
                case 'audio':
                    $media   = self::media_kind( $field->type );
                    $is_pic  = in_array( $field->type, array( 'image', 'svg' ), true );
                    ?>
                    <div class="lk-cms-img-cell<?php echo '' !== (string) $value ? ' has-value' : ''; ?>" data-media="<?php echo esc_attr( $media['mime'] ); ?>">
                        <input type="hidden" class="lk-cell-input lk-cms-img-input"
                               name="<?php echo esc_attr( $name ); ?>"
                               value="<?php echo esc_attr( (string) $value ); ?>">
                        <button type="button" class="lk-cms-img-btn" data-title="<?php echo esc_attr( $field->label ); ?>"
                                data-icon="<?php echo esc_attr( $media['icon'] ); ?>">
                            <?php if ( '' === (string) $value ) : ?>
                                <span class="dashicons <?php echo esc_attr( $media['icon'] ); ?>"></span>
                            <?php elseif ( $is_pic ) : ?>
                                <img src="<?php echo esc_url( (string) $value ); ?>" alt="">
                            <?php else : ?>
                                <?php // Video and audio have nothing to show, so name the file. ?>
                                <span class="dashicons <?php echo esc_attr( $media['icon'] ); ?>"></span>
                                <span class="lk-cms-media-name"><?php echo esc_html( basename( wp_parse_url( (string) $value, PHP_URL_PATH ) ) ); ?></span>
                            <?php endif; ?>
                        </button>
                        <button type="button" class="lk-cms-img-clear dashicons dashicons-dismiss"
                                title="<?php echo esc_attr( $media['clear'] ); ?>"
                                aria-label="<?php echo esc_attr( $media['clear'] ); ?>"></button>
                    </div>
                    <?php
                    break;

                case 'email':
                    ?>
                    <input type="email" class="lk-cell-input" name="<?php echo esc_attr( $name ); ?>"
                           value="<?php echo esc_attr( (string) $value ); ?>">
                    <?php
                    break;

                case 'url':
                    ?>
                    <input type="url" class="lk-cell-input" name="<?php echo esc_attr( $name ); ?>"
                           value="<?php echo esc_attr( (string) $value ); ?>">
                    <?php
                    break;

                case 'color':
                    if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $value ) ) {
                        ?>
                        <input type="color" class="lk-cell-input" name="<?php echo esc_attr( $name ); ?>"
                               value="<?php echo esc_attr( strtolower( (string) $value ) ); ?>">
                        <?php
                    } else {
                        ?>
                        <input type="text" class="lk-cell-input" name="<?php echo esc_attr( $name ); ?>"
                               value="<?php echo esc_attr( (string) $value ); ?>" placeholder="#38a8f5">
                        <?php
                    }
                    break;

                default:
                    // text, reference — plain single-line input.
                    ?>
                    <input type="text" class="lk-cell-input" name="<?php echo esc_attr( $name ); ?>"
                           value="<?php echo esc_attr( (string) $value ); ?>">
                    <?php
            }
            ?>
        </td>
        <?php
    }

    /**
     * The single-item editor: a slide-over panel with every field in its
     * proper control — the home for long_text and rich_text values.
     *
     * One instance per screen. cms-admin.js fills it from the clicked row;
     * opened directly via ?item_id=N it renders server-side already open,
     * so it works without JavaScript too (plain form POST + redirect).
     *
     * @param object      $collection The collection being browsed.
     * @param array       $fields     Column definitions.
     * @param object|null $edit_item  Row being edited, or null for blank.
     */
    protected function render_item_editor( $collection, $fields, $edit_item, $rows = array() ) {
        $item_id = $edit_item ? (int) $edit_item->id : 0;
        $data    = ( $edit_item && is_array( $edit_item->data ) ) ? $edit_item->data : array();
        $open    = $edit_item ? ' is-open' : '';

        // Previous and next within the page the user is looking at, in the
        // order on screen — so stepping through the panel matches the table.
        $prev_url = '';
        $next_url = '';
        $position = '';
        if ( $item_id && is_array( $rows ) && $rows ) {
            $ids = array();
            foreach ( $rows as $r ) {
                $ids[] = (int) $r->id;
            }
            $at = array_search( $item_id, $ids, true );
            if ( false !== $at ) {
                if ( isset( $ids[ $at - 1 ] ) ) {
                    $prev_url = $this->items_url( array( 'item_id' => $ids[ $at - 1 ] ) );
                }
                if ( isset( $ids[ $at + 1 ] ) ) {
                    $next_url = $this->items_url( array( 'item_id' => $ids[ $at + 1 ] ) );
                }
                $position = sprintf(
                    /* translators: 1: row position on this page, 2: rows on this page */
                    __( '%1$d of %2$d', 'loopkit' ),
                    $at + 1,
                    count( $ids )
                );
            }
        }
        $title   = $edit_item
            ? sprintf(
                /* translators: %d: item id */
                __( 'Edit row #%d', 'loopkit' ),
                $item_id
            )
            : __( 'Add row', 'loopkit' );
        ?>
        <div id="lk-cms-item-editor" class="lk-cms-editor<?php echo $open; ?>"
             role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $title ); ?>">
            <div class="lk-cms-editor-overlay"></div>
            <div class="lk-cms-editor-panel">
                <form method="post" class="lk-cms-editor-form">
                    <?php wp_nonce_field( 'loopkit_cms_admin', 'nonce' ); ?>
                    <input type="hidden" name="loopkit_cms_action" value="save_item">
                    <input type="hidden" name="collection_id" value="<?php echo (int) $collection->id; ?>">
                    <input type="hidden" name="item_id" value="<?php echo (int) $item_id; ?>">

                    <div class="lk-cms-editor-head">
                        <h2 class="lk-editor-title"><?php echo esc_html( $title ); ?></h2>
                        <div class="lk-editor-head-right">
                            <?php // Always present: the JS reuses this one panel for every row. ?>
                                <div class="lk-editor-step"<?php echo $item_id ? '' : ' hidden'; ?>>
                                    <a class="lk-editor-step-btn lk-step-prev<?php echo $prev_url ? '' : ' is-off'; ?>"
                                       href="<?php echo $prev_url ? esc_url( $prev_url ) : '#'; ?>"
                                       title="<?php esc_attr_e( 'Previous row', 'loopkit' ); ?>"
                                       aria-label="<?php esc_attr_e( 'Previous row', 'loopkit' ); ?>">
                                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                                    </a>
                                    <span class="lk-editor-step-pos"><?php echo esc_html( $position ); ?></span>
                                    <a class="lk-editor-step-btn lk-step-next<?php echo $next_url ? '' : ' is-off'; ?>"
                                       href="<?php echo $next_url ? esc_url( $next_url ) : '#'; ?>"
                                       title="<?php esc_attr_e( 'Next row', 'loopkit' ); ?>"
                                       aria-label="<?php esc_attr_e( 'Next row', 'loopkit' ); ?>">
                                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                                    </a>
                                </div>
                            <button type="button" class="lk-cms-editor-close"
                                    aria-label="<?php esc_attr_e( 'Close', 'loopkit' ); ?>">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                    </div>

                    <div class="lk-cms-editor-body">
                        <?php if ( empty( $fields ) ) : ?>
                            <p class="lk-cms-empty"><?php esc_html_e( 'No fields in this collection yet.', 'loopkit' ); ?></p>
                        <?php else : ?>
                            <?php foreach ( $fields as $f ) : ?>
                                <?php $this->render_editor_field( $f, isset( $data[ $f->field_key ] ) ? $data[ $f->field_key ] : '' ); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="lk-cms-editor-foot">
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'loopkit' ); ?></button>
                        <button type="button" class="button lk-cms-editor-close lk-cms-editor-cancel"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></button>
                            <div class="lk-editor-foot-right"<?php echo $item_id ? '' : ' hidden'; ?>>
                                <button type="submit" class="lk-row-act lk-cms-dup"
                                        data-id="<?php echo (int) $item_id; ?>"
                                        name="row_action" value="duplicate:<?php echo (int) $item_id; ?>"
                                        title="<?php esc_attr_e( 'Make a copy of this row', 'loopkit' ); ?>">
                                    <span class="dashicons dashicons-admin-page"></span>
                                    <span class="lk-row-act-text"><?php esc_html_e( 'Duplicate', 'loopkit' ); ?></span>
                                </button>
                                <button type="submit" class="lk-row-act is-danger delete-item"
                                        data-id="<?php echo (int) $item_id; ?>"
                                        name="row_action" value="delete:<?php echo (int) $item_id; ?>"
                                        title="<?php esc_attr_e( 'Delete this row', 'loopkit' ); ?>"
                                        onclick="return confirm('<?php echo esc_js( __( 'Delete this row?', 'loopkit' ) ); ?>');">
                                    <span class="dashicons dashicons-trash"></span>
                                    <span class="lk-row-act-text"><?php esc_html_e( 'Delete', 'loopkit' ); ?></span>
                                </button>
                            </div>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * One labelled control inside the single-item editor.
     *
     * @param object $field Column definition.
     * @param mixed  $value Current value.
     */
    protected function render_editor_field( $field, $value ) {
        if ( is_array( $value ) ) {
            $value = '';
        }
        $key  = $field->field_key;
        $id   = 'lk-editor-' . $key;
        $name = 'data[' . $key . ']';
        ?>
        <div class="lk-editor-field lk-editor-field--<?php echo esc_attr( $field->type ); ?>">
            <label for="<?php echo esc_attr( 'rich_text' === $field->type ? 'lk_cms_editor_' . $key : $id ); ?>">
                <?php echo esc_html( $field->label ); ?>
            </label>
            <?php
            switch ( $field->type ) {
                case 'long_text':
                    ?>
                    <textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
                              rows="6" class="large-text"><?php echo esc_textarea( (string) $value ); ?></textarea>
                    <?php
                    break;

                case 'rich_text':
                    wp_editor(
                        (string) $value,
                        'lk_cms_editor_' . $key,
                        array(
                            'textarea_name' => $name,
                            'textarea_rows' => 10,
                            'media_buttons' => true,
                            'quicktags'     => true,
                            'editor_class'  => 'lk-cell-editor',
                        )
                    );
                    break;

                case 'number':
                    ?>
                    <input type="number" step="any" id="<?php echo esc_attr( $id ); ?>"
                           name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
                    <?php
                    break;

                case 'date':
                    ?>
                    <input type="date" id="<?php echo esc_attr( $id ); ?>"
                           name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
                    <?php
                    break;

                case 'toggle':
                    ?>
                    <label class="lk-toggle-row">
                        <span class="lk-toggle-wrap">
                            <input type="checkbox" id="<?php echo esc_attr( $id ); ?>"
                                   name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( (string) $value, '1' ); ?>>
                            <span class="lk-toggle-slider"></span>
                        </span>
                        <span class="lk-toggle-label"><?php esc_html_e( 'On', 'loopkit' ); ?></span>
                    </label>
                    <?php
                    break;

                case 'select':
                    $options = ( isset( $field->settings['options'] ) && is_array( $field->settings['options'] ) )
                        ? $field->settings['options']
                        : array();
                    ?>
                    <select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
                        <option value="" <?php selected( (string) $value, '' ); ?>>—</option>
                        <?php foreach ( $options as $opt ) : ?>
                            <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( (string) $value, (string) $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php
                    break;

                case 'image':
                case 'svg':
                case 'video':
                case 'audio':
                    $media  = self::media_kind( $field->type );
                    $is_pic = in_array( $field->type, array( 'image', 'svg' ), true );
                    ?>
                    <div class="lk-cms-editor-img<?php echo '' !== (string) $value ? ' has-value' : ''; ?>" data-media="<?php echo esc_attr( $media['mime'] ); ?>">
                        <input type="hidden" id="<?php echo esc_attr( $id ); ?>" class="lk-cms-editor-img-input"
                               name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
                        <span class="lk-cms-editor-img-preview" data-icon="<?php echo esc_attr( $media['icon'] ); ?>">
                            <?php if ( '' === (string) $value ) : ?>
                                <span class="dashicons <?php echo esc_attr( $media['icon'] ); ?>"></span>
                            <?php elseif ( $is_pic ) : ?>
                                <img src="<?php echo esc_url( (string) $value ); ?>" alt="">
                            <?php else : ?>
                                <span class="dashicons <?php echo esc_attr( $media['icon'] ); ?>"></span>
                                <span class="lk-cms-media-name"><?php echo esc_html( basename( wp_parse_url( (string) $value, PHP_URL_PATH ) ) ); ?></span>
                            <?php endif; ?>
                        </span>
                        <button type="button" class="button lk-cms-editor-media"><?php echo esc_html( $media['choose'] ); ?></button>
                        <button type="button" class="button lk-cms-editor-media-clear"><?php esc_html_e( 'Clear', 'loopkit' ); ?></button>
                    </div>
                    <?php
                    break;

                case 'email':
                    ?>
                    <input type="email" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
                           value="<?php echo esc_attr( (string) $value ); ?>">
                    <?php
                    break;

                case 'url':
                    ?>
                    <input type="url" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
                           value="<?php echo esc_attr( (string) $value ); ?>">
                    <?php
                    break;

                case 'color':
                    ?>
                    <input type="color" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
                           value="<?php echo esc_attr( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $value ) ? strtolower( (string) $value ) : '#ffffff' ); ?>">
                    <?php
                    break;

                default:
                    // text, gallery, file, reference.
                    ?>
                    <input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
                           value="<?php echo esc_attr( (string) $value ); ?>">
                    <?php
            }
            ?>
        </div>
        <?php
    }

    /**
     * New / edit collection screen — one form, the id rides in the query
     * string (?id=N) and is only posted back as a hidden field when editing.
     */
    public function render_collection_form() {
        $id         = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $collection = $id ? Loopkit_Module_CMS::get_collection( $id ) : null;
        $editing    = (bool) $collection;
        $name       = $editing ? $collection->name : '';
        $slug       = $editing ? $collection->slug : '';
        $icon       = $editing ? $collection->icon : 'dashicons-database';

        // Curated icons — a real picker can come later; these cover the common cases.
        $icons = array(
            'dashicons-database'    => __( 'Database', 'loopkit' ),
            'dashicons-groups'      => __( 'Team', 'loopkit' ),
            'dashicons-id'          => __( 'ID card', 'loopkit' ),
            'dashicons-portfolio'   => __( 'Portfolio', 'loopkit' ),
            'dashicons-calendar'    => __( 'Calendar', 'loopkit' ),
            'dashicons-clock'       => __( 'Clock', 'loopkit' ),
            'dashicons-location'    => __( 'Location', 'loopkit' ),
            'dashicons-star-filled' => __( 'Star', 'loopkit' ),
            'dashicons-heart'       => __( 'Heart', 'loopkit' ),
            'dashicons-cart'        => __( 'Cart', 'loopkit' ),
            'dashicons-home'        => __( 'Home', 'loopkit' ),
            'dashicons-camera'      => __( 'Camera', 'loopkit' ),
            'dashicons-tag'         => __( 'Tag', 'loopkit' ),
            'dashicons-analytics'   => __( 'Chart', 'loopkit' ),
            'dashicons-clipboard'   => __( 'Clipboard', 'loopkit' ),
            'dashicons-megaphone'   => __( 'Megaphone', 'loopkit' ),
            'dashicons-edit'        => __( 'Pencil', 'loopkit' ),
            'dashicons-layout'      => __( 'Layout', 'loopkit' ),
        );
        if ( $editing && ! isset( $icons[ $icon ] ) ) {
            // Keep a stored icon selectable even when it is not in the list.
            $icons[ $icon ] = $icon;
        }
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <?php $this->print_notices(); ?>
            <h1 class="wp-heading-inline"><?php echo $editing ? esc_html__( 'Edit Collection', 'loopkit' ) : esc_html__( 'New Collection', 'loopkit' ); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit_cms_collections' ) ); ?>"><?php esc_html_e( 'All Collections', 'loopkit' ); ?></a>
            <hr class="wp-header-end">

            <form method="post" class="lk-cms-form">
                <?php wp_nonce_field( 'loopkit_cms_admin', 'nonce' ); ?>
                <input type="hidden" name="loopkit_cms_action" value="save_collection">
                <?php if ( $editing ) : ?>
                    <input type="hidden" name="collection_id" value="<?php echo (int) $collection->id; ?>">
                <?php endif; ?>

                <div class="lk-card">
                    <div class="lk-card-header">
                        <h3><?php esc_html_e( 'Collection', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'Give the list a name people will recognise, such as "Team Members". The slug is its URL key; the icon shows next to it in the admin.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <div class="lk-auto-field">
                            <label for="lk-cms-name"><?php esc_html_e( 'Name', 'loopkit' ); ?> *</label>
                            <input type="text" id="lk-cms-name" name="name" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'Team Members', 'loopkit' ); ?>" required>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-cms-slug"><?php esc_html_e( 'Slug', 'loopkit' ); ?></label>
                            <input type="text" id="lk-cms-slug" name="slug" value="<?php echo esc_attr( $slug ); ?>" placeholder="team-members">
                            <span class="lk-auto-help"><?php esc_html_e( 'Leave blank to generate it from the name.', 'loopkit' ); ?></span>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-cms-icon"><?php esc_html_e( 'Icon', 'loopkit' ); ?></label>
                            <select id="lk-cms-icon" name="icon">
                                <?php foreach ( $icons as $icon_class => $icon_label ) : ?>
                                    <option value="<?php echo esc_attr( $icon_class ); ?>" <?php selected( $icon, $icon_class ); ?>><?php echo esc_html( $icon_label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="lk-auto-help">
                                <?php esc_html_e( 'Preview:', 'loopkit' ); ?>
                                <span id="lk-cms-icon-preview" class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ( $editing ) : ?>
                    <?php
                    $tpl_now = self::item_template_id( (int) $collection->id );
                    $tpls    = get_posts( array(
                        'post_type'      => 'loopkit_template',
                        'post_status'    => array( 'publish', 'draft' ),
                        'posts_per_page' => 100,
                        'orderby'        => 'title',
                        'order'          => 'ASC',
                        'meta_query'     => array( array( 'key' => '_loopkit_template_type', 'value' => 'cms-item' ) ),
                    ) );
                    $sample = self::get_items( (int) $collection->id, array( 'per_page' => 1 ) );
                    $sample = $sample['items'] ? $sample['items'][0] : null;
                    ?>
                    <div class="lk-card">
                        <div class="lk-card-header">
                            <h3><?php esc_html_e( 'Row pages', 'loopkit' ); ?></h3>
                            <p class="lk-card-desc"><?php esc_html_e( 'Give every row in this collection its own page. Build a Collection Item template, pick it here, and each row gets a URL built from the collection slug and its own name.', 'loopkit' ); ?></p>
                        </div>
                        <div class="lk-card-body">
                            <div class="lk-auto-field">
                                <label for="lk-cms-item-tpl"><?php esc_html_e( 'Template', 'loopkit' ); ?></label>
                                <select id="lk-cms-item-tpl" name="item_template">
                                    <option value="0"><?php esc_html_e( '— no page for each row —', 'loopkit' ); ?></option>
                                    <?php foreach ( $tpls as $tpl ) : ?>
                                        <option value="<?php echo (int) $tpl->ID; ?>" <?php selected( $tpl_now, (int) $tpl->ID ); ?>>
                                            <?php echo esc_html( $tpl->post_title ? $tpl->post_title : sprintf( __( 'Template #%d', 'loopkit' ), $tpl->ID ) ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="lk-auto-help">
                                    <?php if ( ! $tpls ) : ?>
                                        <?php esc_html_e( 'No Collection Item templates yet.', 'loopkit' ); ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-templates&type=cms-item' ) ); ?>"><?php esc_html_e( 'Make one', 'loopkit' ); ?></a>
                                    <?php else : ?>
                                        <?php esc_html_e( 'Inside it, use the CMS dynamic tags to pull each row\'s own values.', 'loopkit' ); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ( $sample ) : ?>
                                <p class="lk-cms-url-sample">
                                    <?php esc_html_e( 'A row\'s address looks like:', 'loopkit' ); ?>
                                    <code><?php echo esc_html( self::item_permalink( $sample, $collection ) ); ?></code>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="lk-cms-form-footer">
                    <button type="submit" class="lk-btn lk-btn-primary">
                        <span class="lk-btn-text"><?php echo $editing ? esc_html__( 'Save Collection', 'loopkit' ) : esc_html__( 'Create Collection', 'loopkit' ); ?></span>
                    </button>
                    <a class="lk-btn lk-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit_cms_collections' ) ); ?>"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></a>
                </div>
            </form>
        </div>
        <?php
    }

    /** Save collection from admin form. */
    protected function do_save_collection() {
        $id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        $name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
        $slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
        $icon = isset( $_POST['icon'] ) ? sanitize_text_field( $_POST['icon'] ) : 'dashicons-database';
        if ( $id ) {
            $ok = Loopkit_Module_CMS::update_collection( $id, compact( 'name', 'slug', 'icon' ) );
            if ( $ok ) {
                set_transient( 'loopkit_cms_notice_' . get_current_user_id(), '<div class="updated"><p>' . esc_html__( 'Collection updated.', 'loopkit' ) . '</p></div>', MINUTE_IN_SECONDS );
            } else {
                set_transient( 'loopkit_cms_notice_' . get_current_user_id(), '<div class="error"><p>' . esc_html__( 'Failed to update.', 'loopkit' ) . '</p></div>', MINUTE_IN_SECONDS );
            }
            // Which template, if any, gives this collection's rows their own page.
            if ( isset( $_POST['item_template'] ) ) {
                Loopkit_Module_CMS::set_item_template( $id, absint( wp_unslash( $_POST['item_template'] ) ) );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=loopkit_cms_collections' ) );
            exit;
        }
        if ( '' === $name ) {
            set_transient( 'loopkit_cms_notice_' . get_current_user_id(), '<div class="error"><p>' . esc_html__( 'Name is required.', 'loopkit' ) . '</p></div>', MINUTE_IN_SECONDS );
            wp_safe_redirect( admin_url( 'admin.php?page=loopkit_cms_new' ) );
            exit;
        }
        $new_id = Loopkit_Module_CMS::create_collection( $name, $slug, $icon );
        if ( $new_id ) {
            $made = Loopkit_Module_CMS::get_collection( $new_id );
            set_transient(
                'loopkit_cms_notice_' . get_current_user_id(),
                '<div class="updated"><p>' . sprintf(
                    /* translators: 1: collection name, 2: collection slug */
                    esc_html__( 'Collection created: %1$s (%2$s).', 'loopkit' ),
                    esc_html( $made ? $made->name : $name ),
                    esc_html( $made ? $made->slug : '' )
                ) . '</p></div>',
                MINUTE_IN_SECONDS
            );
            wp_safe_redirect( admin_url( 'admin.php?page=loopkit_cms_new&id=' . (int) $new_id ) );
            exit;
        } else {
            set_transient( 'loopkit_cms_notice_' . get_current_user_id(), '<div class="error"><p>' . esc_html__( 'Failed to create.', 'loopkit' ) . '</p></div>', MINUTE_IN_SECONDS );
            wp_safe_redirect( admin_url( 'admin.php?page=loopkit_cms_new' ) );
            exit;
        }
    }

    /**
     * Field editor screen for one collection: the field table (with
     * reorder controls) plus the add/edit field form.
     *
     * Reordering posts a precomputed field_ids[] order for the move, so
     * the up/down buttons work without JavaScript as well as with it.
     */
    /**
     * Copy a field, with a key that does not collide.
     *
     * The copy is empty of data: existing rows have no value under the new
     * key, which is the same state a freshly added field is in.
     *
     * @param int $field_id Field to copy.
     * @return int|false New field id, or false.
     */
    public static function duplicate_field( $field_id ) {
        $src = self::get_field( $field_id );
        if ( ! $src ) {
            return false;
        }
        $taken = array();
        foreach ( self::get_fields( (int) $src->collection_id ) as $f ) {
            $taken[] = $f->field_key;
        }
        $base = $src->field_key . '_copy';
        $key  = $base;
        $n    = 2;
        while ( in_array( $key, $taken, true ) ) {
            $key = $base . '_' . $n;
            $n++;
        }
        return self::create_field(
            (int) $src->collection_id,
            $src->label . ' ' . __( '(copy)', 'loopkit' ),
            $key,
            $src->type,
            is_array( $src->settings ) ? $src->settings : array(),
            null
        );
    }

    /**
     * Copy a collection: its fields and every row in it.
     *
     * @param int $collection_id Collection to copy.
     * @return int|false New collection id, or false.
     */
    public static function duplicate_collection( $collection_id ) {
        $src = self::get_collection( $collection_id );
        if ( ! $src ) {
            return false;
        }
        $new_id = self::create_collection(
            $src->name . ' ' . __( '(copy)', 'loopkit' ),
            '',
            $src->icon
        );
        if ( ! $new_id ) {
            return false;
        }
        foreach ( self::get_fields( (int) $src->id ) as $fl ) {
            self::create_field(
                $new_id,
                $fl->label,
                $fl->field_key,
                $fl->type,
                is_array( $fl->settings ) ? $fl->settings : array(),
                (int) $fl->sort_order
            );
        }
        // Rows oldest first, so the copy reads in the same order.
        $rows = self::get_items( (int) $src->id, array( 'per_page' => 10000, 'page' => 1, 'orderby' => 'id', 'order' => 'ASC' ) );
        foreach ( $rows['items'] as $row ) {
            self::create_item( $new_id, is_array( $row->data ) ? $row->data : array() );
        }
        return $new_id;
    }

    /**
     * Shown where a collection is needed and none exists yet.
     *
     * @param string $heading Screen name.
     */
    protected function render_no_collections( $heading ) {
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple lk-cms-screen">
            <h1 class="wp-heading-inline"><?php echo esc_html( $heading ); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit_cms_new' ) ); ?>"><?php esc_html_e( 'New Collection', 'loopkit' ); ?></a>
            <hr class="wp-header-end">
            <?php $this->print_notices(); ?>
            <div class="lk-cms-blank">
                <p><?php esc_html_e( 'There are no collections yet. Make one, give it a few fields, and its content will live here.', 'loopkit' ); ?></p>
                <a class="lk-btn lk-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit_cms_new' ) ); ?>">
                    <?php esc_html_e( 'Create your first Collection', 'loopkit' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    public function render_fields() {
        $collection_id = isset( $_GET['collection_id'] ) ? absint( $_GET['collection_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $collection    = $collection_id ? Loopkit_Module_CMS::get_collection( $collection_id ) : null;

        // No collection asked for, or one that no longer exists: land on the
        // first one rather than redirecting. A render callback runs after
        // admin_head has already printed this screen's <style>, so a redirect
        // here cannot work — it only warns that headers were already sent.
        if ( ! $collection ) {
            $all = Loopkit_Module_CMS::get_collections();
            if ( $all ) {
                $collection    = $all[0];
                $collection_id = (int) $collection->id;
            } else {
                $this->render_no_collections( __( 'Fields', 'loopkit' ) );
                return;
            }
        }

        $fields   = Loopkit_Module_CMS::get_fields( $collection_id );
        $types    = Loopkit_Module_CMS::field_types();
        $order    = array();
        foreach ( $fields as $f ) {
            $order[] = (int) $f->id;
        }

        $field_id = isset( $_GET['field_id'] ) ? absint( $_GET['field_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $editing  = null;
        if ( $field_id ) {
            $candidate = Loopkit_Module_CMS::get_field( $field_id );
            if ( $candidate && (int) $candidate->collection_id === (int) $collection_id ) {
                $editing = $candidate;
            }
        }
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <?php $this->print_notices(); ?>
            <h1 class="wp-heading-inline"><?php printf( esc_html__( 'Fields — %s', 'loopkit' ), esc_html( $collection->name ) ); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit_cms_items&collection_id=' . (int) $collection_id ) ); ?>"><?php esc_html_e( 'Edit Content', 'loopkit' ); ?></a>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit_cms_collections' ) ); ?>"><?php esc_html_e( 'All Collections', 'loopkit' ); ?></a>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit_cms_new&id=' . (int) $collection_id ) ); ?>"><?php esc_html_e( 'Edit Collection', 'loopkit' ); ?></a>
            <hr class="wp-header-end">

            <div class="cms-messages"></div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:50px"><?php esc_html_e( '#', 'loopkit' ); ?></th>
                        <th><?php esc_html_e( 'Label', 'loopkit' ); ?></th>
                        <th style="width:220px"><?php esc_html_e( 'Key', 'loopkit' ); ?></th>
                        <th style="width:130px"><?php esc_html_e( 'Type', 'loopkit' ); ?></th>
                        <th style="width:230px"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'loopkit' ); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $this->render_field_rows( $fields, $order, $types, $collection_id, $editing ); ?>
                </tbody>
            </table>

            <?php $this->render_field_form( $collection_id, $editing, $types ); ?>
        </div>
        <?php
    }

    /** Delete collection from admin form. */
    protected function do_delete_collection() {
        $id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        if ( ! $id ) {
            echo '<div class="error"><p>' . esc_html__( 'Missing collection id.', 'loopkit' ) . '</p></div>';
            return;
        }
        if ( Loopkit_Module_CMS::delete_collection( $id ) ) {
            echo '<div class="updated"><p>' . esc_html__( 'Collection deleted.', 'loopkit' ) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__( 'Failed to delete.', 'loopkit' ) . '</p></div>';
        }
    }


    /**
     * The add/edit field form shown at the bottom of the Fields screen.
     *
     * @param int         $collection_id The collection the field belongs to.
     * @param object|null $editing       The field being edited, or null to add.
     * @param array       $types         field_types() map of type => label.
     */
    protected function render_field_form( $collection_id, $editing, $types ) {
        $current_type = $editing ? $editing->type : 'text';
        ?>
        <form method="post" class="lk-cms-form lk-cms-field-form">
            <?php wp_nonce_field( 'loopkit_cms_admin', 'nonce' ); ?>
            <input type="hidden" name="loopkit_cms_action" value="save_field">
            <input type="hidden" name="collection_id" value="<?php echo (int) $collection_id; ?>">
            <?php if ( $editing ) : ?>
                <input type="hidden" name="field_id" value="<?php echo (int) $editing->id; ?>">
            <?php endif; ?>

            <div class="lk-card">
                <div class="lk-card-header">
                    <h3><?php echo $editing ? esc_html__( 'Edit Field', 'loopkit' ) : esc_html__( 'Add Field', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc"><?php echo $editing ? esc_html__( 'Change the label, key or type. Item values follow the key, so renaming a key separates existing values.', 'loopkit' ) : esc_html__( 'A field is one column in this collection — Full Name, Role, Photo. The key is what templates and dynamic tags use.', 'loopkit' ); ?></p>
                </div>
                <div class="lk-card-body lk-auto-grid">
                    <div class="lk-auto-field">
                        <label for="lk-cms-field-label"><?php esc_html_e( 'Label', 'loopkit' ); ?> *</label>
                        <input type="text" id="lk-cms-field-label" name="label" value="<?php echo $editing ? esc_attr( $editing->label ) : ''; ?>" placeholder="<?php esc_attr_e( 'Full Name', 'loopkit' ); ?>" required>
                        <span class="lk-auto-help"><?php esc_html_e( 'What people see when they add or edit an item.', 'loopkit' ); ?></span>
                    </div>
                    <div class="lk-auto-field">
                        <label for="lk-cms-field-key"><?php esc_html_e( 'Field key', 'loopkit' ); ?></label>
                        <input type="text" id="lk-cms-field-key" name="field_key" class="lk-cms-key-input" value="<?php echo $editing ? esc_attr( $editing->field_key ) : ''; ?>" placeholder="full_name">
                        <span class="lk-auto-help"><?php esc_html_e( 'Generated from the label when left blank.', 'loopkit' ); ?></span>
                    </div>
                    <div class="lk-auto-field">
                        <label for="lk-cms-field-type"><?php esc_html_e( 'Type', 'loopkit' ); ?></label>
                        <select id="lk-cms-field-type" name="type">
                            <?php foreach ( $types as $type_key => $type_label ) : ?>
                                <option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $current_type, $type_key ); ?>><?php echo esc_html( $type_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="lk-auto-help"><?php esc_html_e( 'Decides how the value is entered and shown.', 'loopkit' ); ?></span>
                    </div>
                </div>
            </div>

            <div class="lk-cms-form-footer">
                <button type="submit" class="lk-btn lk-btn-primary">
                    <span class="lk-btn-text"><?php echo $editing ? esc_html__( 'Save Field', 'loopkit' ) : esc_html__( 'Add Field', 'loopkit' ); ?></span>
                </button>
                <?php if ( $editing ) : ?>
                    <a class="lk-btn lk-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit_cms_fields&collection_id=' . (int) $collection_id ) ); ?>"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></a>
                <?php endif; ?>
            </div>
        </form>
        <?php
    }

    /** Copy a field from the admin form. */
    protected function do_duplicate_field() {
        $field_id = isset( $_POST['field_id'] ) ? absint( $_POST['field_id'] ) : 0;
        if ( ! $field_id ) {
            echo '<div class="error"><p>' . esc_html__( 'Missing required data.', 'loopkit' ) . '</p></div>';
            return;
        }
        $new_id = Loopkit_Module_CMS::duplicate_field( $field_id );
        if ( $new_id ) {
            $made = Loopkit_Module_CMS::get_field( $new_id );
            echo '<div class="updated"><p>' . sprintf(
                /* translators: 1: field label, 2: field key */
                esc_html__( 'Field copied: %1$s (%2$s).', 'loopkit' ),
                esc_html( $made ? $made->label : '' ),
                esc_html( $made ? $made->field_key : '' )
            ) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__( 'Failed to copy.', 'loopkit' ) . '</p></div>';
        }
    }

    /** Copy a whole collection from the admin form. */
    protected function do_duplicate_collection() {
        $collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        if ( ! $collection_id ) {
            echo '<div class="error"><p>' . esc_html__( 'Missing required data.', 'loopkit' ) . '</p></div>';
            return;
        }
        $new_id = Loopkit_Module_CMS::duplicate_collection( $collection_id );
        if ( $new_id ) {
            $made = Loopkit_Module_CMS::get_collection( $new_id );
            echo '<div class="updated"><p>' . sprintf(
                /* translators: 1: collection name, 2: collection slug */
                esc_html__( 'Collection copied: %1$s (%2$s).', 'loopkit' ),
                esc_html( $made ? $made->name : '' ),
                esc_html( $made ? $made->slug : '' )
            ) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__( 'Failed to copy.', 'loopkit' ) . '</p></div>';
        }
    }

    /** Save field from admin form. */
    protected function do_save_field() {
        $field_id      = isset( $_POST['field_id'] ) ? absint( $_POST['field_id'] ) : 0;
        $collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        $label         = isset( $_POST['label'] ) ? sanitize_text_field( $_POST['label'] ) : '';
        $type          = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'text';
        $field_key     = isset( $_POST['field_key'] ) ? sanitize_key( wp_unslash( $_POST['field_key'] ) ) : '';
        $has_order     = isset( $_POST['sort_order'] );

        if ( $field_id ) {
            $data = array( 'label' => $label, 'type' => $type );
            if ( '' !== $field_key ) {
                $data['field_key'] = $field_key;
            }
            if ( $has_order ) {
                $data['sort_order'] = absint( $_POST['sort_order'] );
            }
            if ( Loopkit_Module_CMS::update_field( $field_id, $data ) ) {
                echo '<div class="updated"><p>' . esc_html__( 'Field updated.', 'loopkit' ) . '</p></div>';
            } else {
                echo '<div class="error"><p>' . esc_html__( 'Failed to update.', 'loopkit' ) . '</p></div>';
            }
            return;
        }

        if ( '' === $label || ! $collection_id ) {
            echo '<div class="error"><p>' . esc_html__( 'Missing required data.', 'loopkit' ) . '</p></div>';
            return;
        }

        $sort_order = $has_order ? absint( $_POST['sort_order'] ) : null;
        $new_id     = Loopkit_Module_CMS::create_field( $collection_id, $label, $field_key, $type, array(), $sort_order );
        if ( $new_id ) {
            $made = Loopkit_Module_CMS::get_field( $new_id );
            echo '<div class="updated"><p>' . sprintf(
                /* translators: 1: field label, 2: field key */
                esc_html__( 'Field created: %1$s (%2$s).', 'loopkit' ),
                esc_html( $made ? $made->label : $label ),
                esc_html( $made ? $made->field_key : $field_key )
            ) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__( 'Failed to create.', 'loopkit' ) . '</p></div>';
        }
    }

    /**
     * Table rows for the Fields screen: label, key, type, row actions.
     *
     * Each up/down button posts the full field_ids[] order with the two
     * rows swapped, so reorder_fields() just writes it back — no JS needed.
     *
     * @param array       $fields        Rows from get_fields().
     * @param array       $order         Field ids in current display order.
     * @param array       $types         field_types() map.
     * @param int         $collection_id The collection.
     * @param object|null $editing       Field being edited, or null.
     */
    protected function render_field_rows( $fields, $order, $types, $collection_id, $editing ) {
        $count = count( $fields );
        if ( 0 === $count ) {
            ?>
            <tr>
                <td colspan="5"><?php esc_html_e( 'No fields yet — add the first one below.', 'loopkit' ); ?></td>
            </tr>
            <?php
            return;
        }

        foreach ( $fields as $i => $f ) {
            // Orders for the two moves: current order with this row swapped.
            // Only build a swap the row can actually make — reading
            // $order[-1] on the first row or $order[$count] on the last
            // warned on every render, even though those buttons are hidden.
            $up   = $order;
            $down = $order;
            if ( $i > 0 ) {
                $up[ $i - 1 ] = $order[ $i ];
                $up[ $i ]     = $order[ $i - 1 ];
            }
            if ( $i < $count - 1 ) {
                $down[ $i + 1 ] = $order[ $i ];
                $down[ $i ]     = $order[ $i + 1 ];
            }
            $type_label    = isset( $types[ $f->type ] ) ? $types[ $f->type ] : $f->type;
            $edit_url      = admin_url( 'admin.php?page=loopkit_cms_fields&collection_id=' . (int) $collection_id . '&field_id=' . (int) $f->id );
            ?>
            <tr>
                <td class="lk-cms-order"><?php echo esc_html( $i + 1 ); ?></td>
                <td>
                    <strong><?php echo esc_html( $f->label ); ?></strong>
                    <?php if ( $editing && (int) $editing->id === (int) $f->id ) : ?>
                        <span class="lk-cms-tag lk-cms-tag-current"><?php esc_html_e( 'Editing', 'loopkit' ); ?></span>
                    <?php endif; ?>
                </td>
                <td><code class="lk-cms-key"><?php echo esc_html( $f->field_key ); ?></code></td>
                <td><span class="lk-cms-tag"><?php echo esc_html( $type_label ); ?></span></td>
                <td class="lk-cms-actions lk-col-actions">
                    <div class="lk-item-actions">
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="lk-row-act"
                           title="<?php esc_attr_e( 'Edit this field', 'loopkit' ); ?>">
                            <span class="dashicons dashicons-edit"></span>
                            <span class="lk-row-act-text"><?php esc_html_e( 'Edit', 'loopkit' ); ?></span>
                        </a>

                        <form method="post" class="lk-cms-inline-form">
                            <?php wp_nonce_field( 'loopkit_cms_admin', 'nonce' ); ?>
                            <input type="hidden" name="loopkit_cms_action" value="duplicate_field">
                            <input type="hidden" name="collection_id" value="<?php echo (int) $collection_id; ?>">
                            <input type="hidden" name="field_id" value="<?php echo (int) $f->id; ?>">
                            <button type="submit" class="lk-row-act"
                                    title="<?php esc_attr_e( 'Make a copy of this field', 'loopkit' ); ?>">
                                <span class="dashicons dashicons-admin-page"></span>
                                <span class="lk-row-act-text"><?php esc_html_e( 'Duplicate', 'loopkit' ); ?></span>
                            </button>
                        </form>

                        <?php if ( $i > 0 ) : ?>
                            <form method="post" class="lk-cms-inline-form">
                                <?php wp_nonce_field( 'loopkit_cms_admin', 'nonce' ); ?>
                                <input type="hidden" name="loopkit_cms_action" value="reorder_fields">
                                <input type="hidden" name="collection_id" value="<?php echo (int) $collection_id; ?>">
                                <?php foreach ( $up as $fid ) : ?>
                                    <input type="hidden" name="field_ids[]" value="<?php echo (int) $fid; ?>">
                                <?php endforeach; ?>
                                <button type="submit" class="lk-row-act is-icon"
                                        title="<?php esc_attr_e( 'Move up', 'loopkit' ); ?>"
                                        aria-label="<?php esc_attr_e( 'Move up', 'loopkit' ); ?>">
                                    <span class="dashicons dashicons-arrow-up-alt2"></span>
                                </button>
                            </form>
                        <?php else : ?>
                            <span class="lk-row-act is-icon is-off" aria-hidden="true">
                                <span class="dashicons dashicons-arrow-up-alt2"></span>
                            </span>
                        <?php endif; ?>

                        <?php if ( $i < $count - 1 ) : ?>
                            <form method="post" class="lk-cms-inline-form">
                                <?php wp_nonce_field( 'loopkit_cms_admin', 'nonce' ); ?>
                                <input type="hidden" name="loopkit_cms_action" value="reorder_fields">
                                <input type="hidden" name="collection_id" value="<?php echo (int) $collection_id; ?>">
                                <?php foreach ( $down as $fid ) : ?>
                                    <input type="hidden" name="field_ids[]" value="<?php echo (int) $fid; ?>">
                                <?php endforeach; ?>
                                <button type="submit" class="lk-row-act is-icon"
                                        title="<?php esc_attr_e( 'Move down', 'loopkit' ); ?>"
                                        aria-label="<?php esc_attr_e( 'Move down', 'loopkit' ); ?>">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </button>
                            </form>
                        <?php else : ?>
                            <span class="lk-row-act is-icon is-off" aria-hidden="true">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </span>
                        <?php endif; ?>

                        <form method="post" class="lk-cms-inline-form">
                            <?php wp_nonce_field( 'loopkit_cms_admin', 'nonce' ); ?>
                            <input type="hidden" name="loopkit_cms_action" value="delete_field">
                            <input type="hidden" name="collection_id" value="<?php echo (int) $collection_id; ?>">
                            <input type="hidden" name="field_id" value="<?php echo (int) $f->id; ?>">
                            <button type="submit" class="lk-row-act is-danger delete-field" data-id="<?php echo (int) $f->id; ?>"
                                    title="<?php esc_attr_e( 'Delete this field', 'loopkit' ); ?>"
                                    onclick="return confirm('<?php echo esc_js( __( 'Delete this field? Existing item values for it are removed too.', 'loopkit' ) ); ?>');">
                                <span class="dashicons dashicons-trash"></span>
                                <span class="lk-row-act-text"><?php esc_html_e( 'Delete', 'loopkit' ); ?></span>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php
        }
    }

    /** Delete field from admin form. */
    protected function do_delete_field() {
        $field_id = isset( $_POST['field_id'] ) ? absint( $_POST['field_id'] ) : 0;
        if ( ! $field_id ) {
            echo '<div class="error"><p>' . esc_html__( 'Missing field id.', 'loopkit' ) . '</p></div>';
            return;
        }
        if ( Loopkit_Module_CMS::delete_field( $field_id ) ) {
            echo '<div class="updated"><p>' . esc_html__( 'Field deleted.', 'loopkit' ) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__( 'Failed to delete.', 'loopkit' ) . '</p></div>';
        }
    }

    /** Reorder fields from admin form. */
    protected function do_reorder_fields() {
        $collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        $ordered_ids   = array();
        if ( isset( $_POST['field_ids'] ) ) {
            $raw = wp_unslash( $_POST['field_ids'] );
            if ( is_array( $raw ) ) {
                $ordered_ids = $raw;
            } else {
                $ordered_ids = explode( ',', (string) $raw );
            }
        }
        if ( ! $collection_id || empty( $ordered_ids ) ) {
            echo '<div class="error"><p>' . esc_html__( 'Missing collection or field order.', 'loopkit' ) . '</p></div>';
            return;
        }
        $ordered_ids = array_map( 'absint', $ordered_ids );
        Loopkit_Module_CMS::reorder_fields( $collection_id, $ordered_ids );
        echo '<div class="updated"><p>' . esc_html__( 'Field order saved.', 'loopkit' ) . '</p></div>';
    }

    /** Save the single-item editor form (form path; AJAX has its own). */
    protected function do_save_item() {
        $collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        $item_id       = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
        $data          = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();
        if ( ! is_array( $data ) ) {
            $data = array();
        }
        $result = Loopkit_Module_CMS::save_item_data( $collection_id, $item_id, $data );
        if ( $result['ok'] ) {
            echo '<div class="updated"><p>' . esc_html( $result['msg'] ) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html( $result['msg'] ) . '</p></div>';
        }
    }

    /**
     * Bulk cell save: the no-JavaScript path for the table inputs.
     * Saves every posted cell of the current page; invalid cells are
     * reported and skipped, valid ones still land (per-cell granularity).
     */
    protected function do_save_cells() {
        $cells = isset( $_POST['cells'] ) ? wp_unslash( $_POST['cells'] ) : array();
        if ( ! is_array( $cells ) || empty( $cells ) ) {
            echo '<div class="error"><p>' . esc_html__( 'No cells to save.', 'loopkit' ) . '</p></div>';
            return;
        }
        $saved  = 0;
        $errors = array();
        $cache  = array();
        foreach ( $cells as $item_id => $row ) {
            $item_id = absint( $item_id );
            if ( ! $item_id || ! is_array( $row ) ) {
                continue;
            }
            $item = Loopkit_Module_CMS::get_item( $item_id );
            if ( ! $item ) {
                $errors[] = __( 'Row not found.', 'loopkit' );
                continue;
            }
            if ( ! isset( $cache[ $item->collection_id ] ) ) {
                $cache[ $item->collection_id ] = Loopkit_Module_CMS::get_fields( $item->collection_id );
            }
            foreach ( $row as $key => $value ) {
                $result = Loopkit_Module_CMS::save_cell(
                    $item_id,
                    sanitize_key( (string) $key ),
                    is_array( $value ) ? '' : $value,
                    $cache[ $item->collection_id ]
                );
                if ( $result['ok'] ) {
                    $saved++;
                } else {
                    $errors[] = $result['msg'];
                }
            }
        }
        if ( $saved > 0 ) {
            echo '<div class="updated"><p>' . esc_html(
                sprintf(
                    /* translators: %d: number of cells */
                    _n( '%d cell saved.', '%d cells saved.', $saved, 'loopkit' ),
                    $saved
                )
            ) . '</p></div>';
        }
        if ( ! empty( $errors ) ) {
            echo '<div class="error"><p>' . esc_html( implode( ' ', array_unique( $errors ) ) ) . '</p></div>';
        }
    }

    /** Delete the row identified by the posted item id (form path). */
    protected function do_delete_item() {
        $item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
        if ( ! $item_id ) {
            echo '<div class="error"><p>' . esc_html__( 'Missing row id.', 'loopkit' ) . '</p></div>';
            return;
        }
        if ( Loopkit_Module_CMS::delete_item( $item_id ) ) {
            echo '<div class="updated"><p>' . esc_html__( 'Row deleted.', 'loopkit' ) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__( 'Failed to delete.', 'loopkit' ) . '</p></div>';
        }
    }

    /** Duplicate the row identified by the posted item id (form path). */
    protected function do_duplicate_item() {
        $item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
        if ( ! $item_id ) {
            echo '<div class="error"><p>' . esc_html__( 'Missing row id.', 'loopkit' ) . '</p></div>';
            return;
        }
        $new_id = Loopkit_Module_CMS::duplicate_item( $item_id );
        if ( $new_id ) {
            echo '<div class="updated"><p>' . sprintf(
                /* translators: %d: new item id */
                __( 'Row duplicated.', 'loopkit' )
            ) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__( 'Failed to duplicate.', 'loopkit' ) . '</p></div>';
        }
    }

    /**
     * Module-specific chrome for the CMS screens, printed in the head of
     * each one via admin_head-{$hook}. Sits on top of the shared Loopkit
     * admin stylesheet (loaded on every screen whose id contains "loopkit",
     * together with the restyled form controls) and uses its palette —
     * no new colours invented here.
     */
    public function admin_styles() {
        ?>
<style>
/* CMS screen chrome — palette shared with loopkit-admin.css. */
.lk-cms-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin: 18px 0 20px; }
/* Import / Export */
.lk-cms-io-screen .lk-card { margin-bottom: 18px; }
.lk-io-map { margin: 4px 0 18px; }
.lk-io-map th, .lk-io-map td { padding: 10px 12px; vertical-align: middle; }
.lk-io-map select { min-width: 220px; }
.lk-io-sample { color: #6B7280; font-size: 13px; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lk-io-opts { margin-bottom: 4px; }
.lk-cms-count { color: #6B7280; font-size: 13px; margin-left: 10px; }
.lk-cms-stat { display: flex; align-items: center; gap: 13px; background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 16px 18px; }
.lk-cms-stat-icon { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; flex: none; background: #E9F6FE; color: #38A8F5; border-radius: 10px; }
.lk-cms-stat-icon .dashicons { font-size: 26px; width: 26px; height: 26px; }
.lk-cms-stat-value { font-size: 24px; font-weight: 700; color: #111827; line-height: 1.1; font-variant-numeric: tabular-nums; }
.lk-cms-stat-label { font-size: 12px; color: #6B7280; margin-top: 3px; }
.lk-cms-row-icon { color: #38A8F5; font-size: 20px; width: 20px; height: 20px; margin-right: 6px; vertical-align: middle; }
code.lk-cms-key { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; background: #F3F4F6; color: #4B5563; border-radius: 5px; padding: 2px 7px; margin-left: 8px; }
.lk-cms-key-input { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; }
.lk-cms-tag { display: inline-block; background: #F3F4F6; color: #4B5563; border-radius: 5px; padding: 2px 8px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
.lk-cms-tag-current { background: #E9F6FE; color: #1E90E0; }
.lk-cms-order { color: #9CA3AF; font-variant-numeric: tabular-nums; }
td.lk-cms-actions { text-align: right; white-space: nowrap; }
td.lk-cms-actions a { color: #1E90E0; text-decoration: none; }
td.lk-cms-actions a:hover { color: #38A8F5; text-decoration: underline; }
.lk-cms-inline-form { display: inline; margin: 0; }
.lk-cms-move { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 24px; margin: 0 2px; padding: 0; border: 1px solid #E5E7EB; border-radius: 6px; background: #fff; color: #374151; font-size: 13px; line-height: 1; cursor: pointer; transition: border-color .15s ease, color .15s ease; }
.lk-cms-move:hover { border-color: #38A8F5; color: #1E90E0; }
.lk-cms-move:focus-visible { outline: 2px solid #38A8F5; outline-offset: 1px; }
.lk-cms-form { max-width: 980px; }
.lk-cms-form .lk-card { margin-bottom: 18px; }
.lk-cms-form-footer { display: flex; align-items: center; gap: 10px; margin: -4px 0 22px; }
.lk-cms-empty { max-width: 520px; margin: 0 auto; padding: 18px 10px; text-align: center; color: #6B7280; font-size: 13px; line-height: 1.6; }
.lk-cms-empty .lk-btn { margin-top: 12px; }
.lk-cms-foot { display: flex; align-items: center; gap: 14px; margin: 10px 0 24px; color: #9CA3AF; font-size: 12px; }
#lk-cms-icon-preview { font-size: 22px; width: 22px; height: 22px; color: #38A8F5; vertical-align: middle; margin-left: 4px; }
.cms-messages:empty { display: none; }
.cms-messages > div { margin: 12px 0; }
tr.pending-delete { opacity: .45; transition: opacity .2s ease; }
@media (max-width: 782px) {
    .lk-cms-stats { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
    .lk-cms-stat-value { font-size: 21px; }
}
/* ── Items screen (Stage C): spreadsheet table ───────────────── */
.lk-items-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 16px 0 12px; }
.lk-items-filters input[type="search"] { min-width: 220px; }
.lk-items-total { color: #6B7280; font-size: 13px; margin-left: 4px; }
.lk-items-perpage select { height: 32px; font-size: 13px; }
.lk-items-wrap { overflow-x: auto; }
.lk-items-table th.lk-col-actions, .lk-items-table td.lk-col-actions { position: sticky; right: 0; z-index: 2; background: #fff; box-shadow: -8px 0 10px -8px rgba(17,24,39,.16); }
.lk-items-table tbody tr:nth-child(even) td.lk-col-actions { background: #F6F7F7; }
.lk-items-table thead th.lk-col-actions { background: #fff; }
.lk-items-table { min-width: 100%; }
/* Long and rich text edit in the cell as well as in the side editor. */
.lk-cms-cell-long { display: flex; align-items: flex-start; gap: 6px; }
.lk-cms-cell-textarea { flex: 1 1 auto; min-width: 0; min-height: 30px; max-height: 180px; resize: vertical; overflow: hidden; line-height: 1.45; padding: 5px 8px; }
.lk-cms-cell-textarea:focus { overflow: auto; min-height: 76px; }
.lk-cms-cell-expand { flex: none; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 30px; color: #6B7280; border-radius: 6px; text-decoration: none; }
.lk-cms-cell-expand:hover, .lk-cms-cell-expand:focus { color: #38A8F5; background: #E9F6FE; }
.lk-cms-cell-expand .dashicons { font-size: 17px; width: 17px; height: 17px; }
.lk-items-table th a { display: inline-flex; align-items: center; gap: 4px; color: #111827; text-decoration: none; }
.lk-items-table th a:hover { color: #1E90E0; }
.lk-items-table th .dashicons { font-size: 14px; width: 14px; height: 14px; color: #1E90E0; }
.lk-items-table th.sorted a { color: #1E90E0; }
.lk-col-field { white-space: nowrap; }
th.lk-col-num, td.lk-col-num { width: 44px; }
td.lk-col-num { color: #9CA3AF; font-variant-numeric: tabular-nums; text-align: right; }
.lk-items-table th.lk-col-actions { width: 190px; }
.lk-items-table td.lk-cms-cell { padding: 5px 8px; vertical-align: middle; }
.lk-cell-input { width: 100%; min-width: 90px; height: 30px; margin: 0; padding: 4px 7px; font-size: 13px; border: 1px solid transparent; border-radius: 6px; background: transparent; color: #111827; box-sizing: border-box; transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease; }
.lk-cell-input:hover { border-color: #E5E7EB; background: #fff; }
.lk-cell-input:focus { border-color: #1E90E0; background: #fff; box-shadow: 0 0 0 2px rgba(30,144,224,.15); outline: none; }
td.lk-cms-cell select.lk-cell-input { height: 30px; padding: 0 26px 0 7px; font-size: 13px; background-position: right 8px center; background-size: 9px 6px; }
input[type="date"].lk-cell-input { min-width: 132px; }
td.lk-cms-cell--toggle { text-align: center; }
.lk-cms-toggle { margin: 0; display: inline-flex; }
.lk-cms-toggle input { margin: 0; }
td.lk-cms-cell.is-saving .lk-cell-input { opacity: .55; }
td.lk-cms-cell.is-saved { box-shadow: inset 0 0 0 2px rgba(30,144,224,.35); }
td.lk-cms-cell.is-error { box-shadow: inset 0 0 0 2px rgba(179,45,46,.45); }
.lk-cms-cell-preview { display: block; max-width: 280px; color: #374151; font-size: 13px; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lk-cms-cell-preview:hover { color: #1E90E0; text-decoration: underline; }
.lk-cms-cell-preview.is-empty { color: #9CA3AF; }
.lk-cms-img-cell { display: flex; align-items: center; gap: 4px; position: relative; }
.lk-cms-media-name { font-size: 12px; color: #4B5563; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lk-cms-img-btn .lk-cms-media-name { margin-left: 6px; }
.lk-cms-editor-img-preview .lk-cms-media-name { margin-left: 8px; max-width: 240px; }
.lk-cms-img-cell[data-media="video"] .lk-cms-img-btn, .lk-cms-img-cell[data-media="audio"] .lk-cms-img-btn { width: auto; padding: 0 10px; }
.lk-cms-img-btn { display: inline-flex; align-items: center; justify-content: center; width: 46px; height: 30px; padding: 2px; overflow: hidden; border: 1px solid #E5E7EB; border-radius: 6px; background: #F3F4F6; cursor: pointer; }
.lk-cms-img-btn:hover { border-color: #1E90E0; }
.lk-cms-img-btn img { width: 100%; height: 100%; object-fit: cover; border-radius: 4px; }
.lk-cms-img-btn .dashicons { color: #9CA3AF; font-size: 16px; width: 16px; height: 16px; }
.lk-cms-img-clear { display: none; color: #b32d2e; cursor: pointer; font-size: 16px; width: 16px; height: 16px; }
.lk-cms-img-cell.has-value .lk-cms-img-clear { display: inline-block; }
tr.lk-cms-addrow td { background: #F9FAFB; text-align: left; padding: 8px; }
.lk-cms-add-row { display: inline-flex; align-items: center; gap: 6px; color: #1E90E0; }
.lk-cms-add-row .dashicons { font-size: 16px; width: 16px; height: 16px; }
.lk-items-bulkbar { display: flex; align-items: center; gap: 12px; margin: 12px 0 24px; }
.lk-items-hint { color: #9CA3AF; font-size: 12px; }
tr.lk-cms-emptyrow td { color: #6B7280; text-align: center; padding: 18px; }
.items-messages:empty { display: none; }
.items-messages > div { margin: 12px 0; }

/* ── Single-item editor: slide-over panel ────────────────────── */
.lk-cms-editor { position: fixed; inset: 0; z-index: 100050; display: none; }
.lk-cms-editor.is-open { display: block; }
.lk-cms-editor-overlay { position: absolute; inset: 0; background: rgba(17,24,39,.55); }
.lk-cms-editor-panel { position: absolute; top: 0; right: 0; height: 100%; width: min(640px, 100%); background: #fff; box-shadow: -8px 0 24px rgba(17,24,39,.18); display: flex; flex-direction: column; }
.lk-cms-editor-form { display: flex; flex-direction: column; height: 100%; margin: 0; }
.lk-cms-editor-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; border-bottom: 1px solid #E5E7EB; }
.lk-editor-title { margin: 0; font-size: 16px; font-weight: 700; color: #111827; }
.lk-cms-editor-head .lk-cms-editor-close { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; flex: none; padding: 0; background: #F3F4F6; border: 1px solid #E5E7EB; border-radius: 8px; color: #6B7280; cursor: pointer; transition: background .12s, color .12s, border-color .12s; }
.lk-cms-editor-head .lk-cms-editor-close:hover { background: #FEF2F2; border-color: #FCA5A5; color: #b32d2e; }
.lk-cms-editor-head .lk-cms-editor-close:focus-visible { outline: 2px solid #38A8F5; outline-offset: 2px; }
.lk-cms-editor-head .lk-cms-editor-close .dashicons { font-size: 18px; width: 18px; height: 18px; line-height: 1; }

/* Row actions: small labelled buttons, roomy enough to aim at. */
.lk-items-table td.lk-col-actions { white-space: nowrap; vertical-align: middle; padding-top: 10px; padding-bottom: 10px; }
.lk-items-table tbody td { padding-top: 9px; padding-bottom: 9px; }
.lk-item-actions { display: inline-flex; align-items: center; gap: 4px; visibility: visible; opacity: 1; }
.lk-row-act { display: inline-flex; align-items: center; gap: 5px; height: 30px; padding: 0 10px; background: #fff; border: 1px solid #E5E7EB; border-radius: 7px; color: #4B5563; font-size: 12px; line-height: 1; text-decoration: none; cursor: pointer; transition: background .12s, color .12s, border-color .12s; }
.lk-row-act:hover, .lk-row-act:focus { background: #E9F6FE; border-color: #9BD5FA; color: #1D7FC4; }
.lk-row-act:focus-visible { outline: 2px solid #38A8F5; outline-offset: 1px; }
.lk-row-act .dashicons { font-size: 15px; width: 15px; height: 15px; line-height: 1; }
.lk-row-act.is-danger:hover, .lk-row-act.is-danger:focus { background: #FEF2F2; border-color: #FCA5A5; color: #b32d2e; }
.lk-row-act.is-icon { padding: 0; width: 30px; justify-content: center; }
.lk-row-act.is-off { color: #D1D5DB; border-color: #F3F4F6; background: #FAFAFA; cursor: default; }
.lk-cms-inline-form { display: inline-flex; margin: 0; }
/* The Fields and Collections tables share the item-table action styling. */
td.lk-cms-actions .lk-item-actions { flex-wrap: nowrap; gap: 4px; }
td.lk-cms-actions { white-space: nowrap; width: 1%; }
@media (max-width: 1400px) { td.lk-cms-actions .lk-row-act-text { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; } td.lk-cms-actions .lk-row-act { padding: 0 8px; } }
@media (max-width: 1200px) { .lk-row-act-text { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; } .lk-row-act { padding: 0 8px; } }
/* Editor head: step through rows without closing the panel. */
.lk-editor-head-right { display: flex; align-items: center; gap: 10px; flex: none; }
.lk-editor-step[hidden], .lk-editor-foot-right[hidden] { display: none; }
.lk-editor-step { display: inline-flex; align-items: center; gap: 2px; padding: 2px; background: #F3F4F6; border: 1px solid #E5E7EB; border-radius: 8px; }
.lk-editor-step-btn { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 6px; color: #4B5563; text-decoration: none; }
.lk-editor-step-btn:hover, .lk-editor-step-btn:focus { background: #E9F6FE; color: #1D7FC4; }
.lk-editor-step-btn:focus-visible { outline: 2px solid #38A8F5; outline-offset: 1px; }
.lk-editor-step-btn.is-off { color: #D1D5DB; cursor: default; pointer-events: none; }
.lk-editor-step-btn .dashicons { font-size: 16px; width: 16px; height: 16px; }
.lk-editor-step-pos { font-size: 12px; color: #6B7280; padding: 0 4px; font-variant-numeric: tabular-nums; }
/* Editor foot: the same row actions the table offers, kept to the right
   so Save stays where the eye expects it. */
.lk-cms-editor-foot { display: flex; align-items: center; gap: 8px; }
.lk-editor-foot-right { margin-left: auto; display: inline-flex; align-items: center; gap: 6px; }
.lk-cms-editor-body { flex: 1; overflow-y: auto; padding: 18px 20px; }
.lk-editor-field { margin-bottom: 16px; }
.lk-editor-field > label { display: block; font-weight: 600; color: #374151; margin-bottom: 6px; font-size: 13px; }
.lk-editor-field input[type="text"], .lk-editor-field input[type="email"], .lk-editor-field input[type="url"], .lk-editor-field input[type="number"], .lk-editor-field input[type="date"], .lk-editor-field select, .lk-editor-field textarea { width: 100%; box-sizing: border-box; }
.lk-editor-field .wp-editor-container { border-radius: 8px; overflow: hidden; }
.lk-cms-editor-img { display: flex; align-items: center; gap: 10px; }
.lk-cms-editor-img-preview { display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 48px; border: 1px solid #E5E7EB; border-radius: 6px; background: #F3F4F6; overflow: hidden; }
.lk-cms-editor-img-preview img { width: 100%; height: 100%; object-fit: cover; }
.lk-cms-editor-img-preview .dashicons { color: #9CA3AF; }
.lk-cms-editor-foot { display: flex; align-items: center; gap: 10px; padding: 14px 20px; border-top: 1px solid #E5E7EB; background: #F9FAFB; }
.lk-cms-editor-foot .lk-cms-editor-cancel { margin-left: auto; }
body.lk-cms-editor-open { overflow: hidden; }

@media (prefers-reduced-motion: reduce) {
    tr.pending-delete { transition: none; }
}
</style>
        <?php
    }

    /**
     * Enqueue the CMS script (and its localization) on CMS screens only.
     *
     * Detects the screen by page slug rather than hook suffix: submenu hook
     * suffixes are built from the menu title (e.g. cms_page_loopkit_cms_…)
     * and differ per site. Shared Loopkit admin CSS/JS already load on any
     * screen whose id contains "loopkit" (Loopkit_Admin::enqueue_assets),
     * which covers every screen here — including the restyled form controls.
     */
    public function enqueue_admin_assets( $hook ) {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 0 !== strpos( $page, 'loopkit_cms' ) ) {
            return;
        }

        wp_enqueue_script(
            'loopkit-cms-admin',
            plugins_url( 'assets/js/cms-admin.js', __FILE__ ),
            array( 'jquery' ),
            defined( 'LOOPKIT_VERSION' ) ? LOOPKIT_VERSION : '1.0.0',
            true
        );
        wp_localize_script(
            'loopkit-cms-admin',
            'LoopkitCMS',
            array(
                'ajaxurl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'loopkit_cms_admin' ),
                'messages' => array(
                    'saved'   => __( 'Saved.', 'loopkit' ),
                    'save'    => __( 'Save', 'loopkit' ),
                    'cancel'  => __( 'Cancel', 'loopkit' ),
                    'deleted' => __( 'Deleted.', 'loopkit' ),
                    'error'   => __( 'Something went wrong.', 'loopkit' ),
                    'confirm' => __( 'Are you sure?', 'loopkit' ),
                    'saving'  => __( 'Saving…', 'loopkit' ),
                    'choose'  => __( 'Choose', 'loopkit' ),
                ),
            )
        );

        // The items screen opens the media library from image cells and the
        // single-item editor (wp.media + the editor's media buttons).
        if ( 'loopkit_cms_items' === $page ) {
            wp_enqueue_media();
        }
    }

    /** AJAX: save collection. */
    public function ajax_save_collection() {
        check_ajax_referer( 'loopkit_cms_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'msg' => __( 'Permission denied.', 'loopkit' ) ) );
        }
        $collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        $name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
        $slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
        $icon = isset( $_POST['icon'] ) ? sanitize_text_field( $_POST['icon'] ) : 'dashicons-database';
        if ( $collection_id ) {
            $ok = Loopkit_Module_CMS::update_collection( $collection_id, compact( 'name', 'slug', 'icon' ) );
            if ( $ok ) {
                wp_send_json_success( array( 'msg' => __( 'Collection updated.', 'loopkit' ) ) );
            }
            wp_send_json_error( array( 'msg' => __( 'Failed to update.', 'loopkit' ) ) );
        }
        if ( '' === $name ) {
            wp_send_json_error( array( 'msg' => __( 'Name is required.', 'loopkit' ) ) );
        }
        $id = Loopkit_Module_CMS::create_collection( $name, $slug, $icon );
        if ( $id ) {
            wp_send_json_success( array( 'id' => $id, 'msg' => __( 'Collection created.', 'loopkit' ) ) );
        }
        wp_send_json_error( array( 'msg' => __( 'Failed to create.', 'loopkit' ) ) );
    }

    /** AJAX: delete collection. */
    public function ajax_delete_collection() {
        check_ajax_referer( 'loopkit_cms_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'msg' => __( 'Permission denied.', 'loopkit' ) ) );
        }
        $collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        if ( ! $collection_id ) {
            wp_send_json_error( array( 'msg' => __( 'Missing collection id.', 'loopkit' ) ) );
        }
        $collection = Loopkit_Module_CMS::get_collection( $collection_id );
        if ( $collection ) {
            $ok = Loopkit_Module_CMS::delete_collection( $collection_id );
            if ( $ok ) {
                wp_send_json_success( array( 'msg' => __( 'Collection deleted.', 'loopkit' ) ) );
            }
            wp_send_json_error( array( 'msg' => __( 'Failed to delete.', 'loopkit' ) ) );
        }
        wp_send_json_error( array( 'msg' => __( 'Collection not found.', 'loopkit' ) ) );
    }

    /** AJAX: save field. */
    public function ajax_save_field() {
        check_ajax_referer( 'loopkit_cms_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'msg' => __( 'Permission denied.', 'loopkit' ) ) );
        }
        $field_id = isset( $_POST['field_id'] ) ? absint( $_POST['field_id'] ) : 0;
        $collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        $label = isset( $_POST['label'] ) ? sanitize_text_field( $_POST['label'] ) : '';
        $type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'text';
        $sort_order = isset( $_POST['sort_order'] ) ? absint( $_POST['sort_order'] ) : 0;
        if ( $field_id ) {
            if ( '' === $label ) {
                wp_send_json_error( array( 'msg' => __( 'Label is required.', 'loopkit' ) ) );
            }
            $ok = Loopkit_Module_CMS::update_field( $field_id, compact( 'label', 'type', 'sort_order' ) );
            if ( $ok ) {
                wp_send_json_success( array( 'msg' => __( 'Field updated.', 'loopkit' ) ) );
            }
            wp_send_json_error( array( 'msg' => __( 'Failed to update.', 'loopkit' ) ) );
        }
        if ( '' === $label || ! $collection_id ) {
            wp_send_json_error( array( 'msg' => __( 'Missing required data.', 'loopkit' ) ) );
        }
        $id = Loopkit_Module_CMS::create_field( $collection_id, $label, '', $type, array(), $sort_order );
        if ( $id ) {
            wp_send_json_success( array( 'id' => $id, 'msg' => __( 'Field created.', 'loopkit' ) ) );
        }
        wp_send_json_error( array( 'msg' => __( 'Failed to create.', 'loopkit' ) ) );
    }

    /** AJAX: delete field. */
    public function ajax_delete_field() {
        check_ajax_referer( 'loopkit_cms_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'msg' => __( 'Permission denied.', 'loopkit' ) ) );
        }
        $field_id = isset( $_POST['field_id'] ) ? absint( $_POST['field_id'] ) : 0;
        if ( ! $field_id ) {
            wp_send_json_error( array( 'msg' => __( 'Missing field id.', 'loopkit' ) ) );
        }
        $ok = Loopkit_Module_CMS::delete_field( $field_id );
        if ( $ok ) {
            wp_send_json_success( array( 'msg' => __( 'Field deleted.', 'loopkit' ) ) );
        }
        wp_send_json_error( array( 'msg' => __( 'Failed to delete.', 'loopkit' ) ) );
    }

    /** AJAX: reorder fields. */
    public function ajax_reorder_fields() {
        check_ajax_referer( 'loopkit_cms_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'msg' => __( 'Permission denied.', 'loopkit' ) ) );
        }
        $collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        $order = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : array();
        if ( ! is_array( $order ) || ! $collection_id ) {
            wp_send_json_error( array( 'msg' => __( 'Missing data.', 'loopkit' ) ) );
        }
        foreach ( $order as $index => $field_id ) {
            $field_id = absint( $field_id );
            if ( $field_id ) {
                Loopkit_Module_CMS::reorder_fields( $field_id, $index );
            }
        }
        wp_send_json_success( array( 'msg' => __( 'Fields reordered.', 'loopkit' ) ) );
    }

    /**
     * Render one row for AJAX responses (add / duplicate / save).
     *
     * @param int $item_id Row to draw.
     * @return string|false HTML, or false when the row is gone.
     */
    protected function item_row_html( $item_id ) {
        $item = Loopkit_Module_CMS::get_item( $item_id );
        if ( ! $item ) {
            return false;
        }
        $fields = Loopkit_Module_CMS::get_fields( $item->collection_id );
        ob_start();
        $this->render_item_row( $item, $fields, 1, (int) $item->collection_id );
        return ob_get_clean();
    }

    /** AJAX: save item (single-item editor / add row). Returns the row HTML. */
    public function ajax_save_item() {
        check_ajax_referer( 'loopkit_cms_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'msg' => __( 'Permission denied.', 'loopkit' ) ) );
        }
        $collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        $item_id       = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
        $data          = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();
        if ( ! is_array( $data ) ) {
            $data = array();
        }
        $result = Loopkit_Module_CMS::save_item_data( $collection_id, $item_id, $data );
        if ( ! $result['ok'] ) {
            wp_send_json_error( array( 'msg' => $result['msg'] ) );
        }
        wp_send_json_success(
            array(
                'msg'      => $result['msg'],
                'item_id'  => $result['item_id'],
                'created'  => $result['created'],
                'row_html' => $this->item_row_html( $result['item_id'] ),
            )
        );
    }

    /** AJAX: delete item. */
    public function ajax_delete_item() {
        check_ajax_referer( 'loopkit_cms_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'msg' => __( 'Permission denied.', 'loopkit' ) ) );
        }
        $item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
        if ( ! $item_id ) {
            wp_send_json_error( array( 'msg' => __( 'Missing row id.', 'loopkit' ) ) );
        }
        if ( ! Loopkit_Module_CMS::get_item( $item_id ) ) {
            wp_send_json_error( array( 'msg' => __( 'Row not found.', 'loopkit' ) ) );
        }
        if ( Loopkit_Module_CMS::delete_item( $item_id ) ) {
            wp_send_json_success( array( 'msg' => __( 'Row deleted.', 'loopkit' ) ) );
        }
        wp_send_json_error( array( 'msg' => __( 'Failed to delete.', 'loopkit' ) ) );
    }

    /** AJAX: duplicate item. Returns the new row's HTML. */
    public function ajax_duplicate_item() {
        check_ajax_referer( 'loopkit_cms_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'msg' => __( 'Permission denied.', 'loopkit' ) ) );
        }
        $item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
        if ( ! $item_id ) {
            wp_send_json_error( array( 'msg' => __( 'Missing row id.', 'loopkit' ) ) );
        }
        $new_id = Loopkit_Module_CMS::duplicate_item( $item_id );
        if ( ! $new_id ) {
            wp_send_json_error( array( 'msg' => __( 'Failed to duplicate.', 'loopkit' ) ) );
        }
        wp_send_json_success(
            array(
                'msg'      => sprintf(
                    /* translators: %d: new item id */
                    __( 'Row duplicated.', 'loopkit' )
                ),
                'item_id'  => (int) $new_id,
                'row_html' => $this->item_row_html( $new_id ),
            )
        );
    }

    /** AJAX: inline update a single cell. Returns the sanitized value. */
    public function ajax_inline_update() {
        check_ajax_referer( 'loopkit_cms_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'msg' => __( 'Permission denied.', 'loopkit' ) ) );
        }
        $item_id   = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
        $field_key = isset( $_POST['field_key'] ) ? sanitize_key( wp_unslash( $_POST['field_key'] ) ) : '';
        $value     = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
        $result    = Loopkit_Module_CMS::save_cell( $item_id, $field_key, $value );
        if ( ! $result['ok'] ) {
            wp_send_json_error( array( 'msg' => $result['msg'] ) );
        }
        wp_send_json_success(
            array(
                'msg'   => $result['msg'],
                'value' => $result['value'],
            )
        );
    }

    /**
     * Send the CSV as a download.
     *
     * On admin-post rather than inside a screen, because a screen has already
     * printed its markup by the time it runs and the headers cannot be sent.
     */
    public function do_export_download() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to do that.', 'loopkit' ) );
        }
        check_admin_referer( 'loopkit_cms_export', 'nonce' );

        $collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        $collection    = self::get_collection( $collection_id );
        if ( ! $collection ) {
            wp_die( esc_html__( 'Collection not found.', 'loopkit' ) );
        }

        $csv  = self::export_csv( $collection_id );
        $name = $collection->slug . '-' . gmdate( 'Y-m-d' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $name . '"' );
        header( 'Content-Length: ' . strlen( $csv ) );
        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /** Take the uploaded file and read its header, ready for mapping. */
    protected function do_upload_csv() {
        $collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        if ( ! $collection_id || ! self::get_collection( $collection_id ) ) {
            $this->io_notice( 'error', __( 'Collection not found.', 'loopkit' ) );
            return;
        }
        if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) {
            $this->io_notice( 'error', __( 'No file arrived. It may be larger than this server accepts.', 'loopkit' ) );
            return;
        }

        $orig = isset( $_FILES['csv']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['csv']['name'] ) ) : 'import.csv';
        $ext  = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );
        if ( 'csv' !== $ext && 'txt' !== $ext ) {
            $this->io_notice( 'error', __( 'That is not a CSV file.', 'loopkit' ) );
            return;
        }

        // Keep it out of the media library: it is working data, not content.
        $up  = wp_upload_dir();
        $dir = trailingslashit( $up['basedir'] ) . 'loopkit-import';
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
            // Nothing here should ever be fetched over the web.
            @file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore
            @file_put_contents( $dir . '/.htaccess', "Deny from all\n" ); // phpcs:ignore
        }
        $path = $dir . '/' . get_current_user_id() . '-' . wp_generate_password( 8, false ) . '.csv';
        if ( ! move_uploaded_file( $_FILES['csv']['tmp_name'], $path ) ) {
            $this->io_notice( 'error', __( 'The file could not be saved for reading.', 'loopkit' ) );
            return;
        }

        $read = self::read_csv( $path, 1 );
        if ( isset( $read['error'] ) ) {
            @unlink( $path ); // phpcs:ignore
            $this->io_notice( 'error', $read['error'] );
            return;
        }

        // An export from here puts the field keys on line 2; spotting that
        // saves the user from importing a row of column names as data.
        $sample     = isset( $read['rows'][0] ) ? $read['rows'][0] : array();
        $skip_guess = ( isset( $sample[0] ) && '_id' === trim( (string) $sample[0] ) ) ? 1 : 0;

        set_transient(
            'loopkit_cms_import_' . get_current_user_id(),
            array(
                'collection_id' => $collection_id,
                'path'          => $path,
                'name'          => $orig,
                'header'        => $read['header'],
                'sample'        => $sample,
                'count'         => (int) $read['count'],
                'skip_guess'    => $skip_guess,
            ),
            HOUR_IN_SECONDS
        );
    }

    /** Run the import the mapping screen confirmed. */
    protected function do_run_import() {
        $pending = get_transient( 'loopkit_cms_import_' . get_current_user_id() );
        if ( ! $pending || empty( $pending['path'] ) ) {
            $this->io_notice( 'error', __( 'That upload has expired. Please choose the file again.', 'loopkit' ) );
            return;
        }

        $map = array();
        if ( isset( $_POST['map'] ) && is_array( $_POST['map'] ) ) {
            foreach ( wp_unslash( $_POST['map'] ) as $i => $key ) {
                $key = sanitize_text_field( $key );
                if ( '' !== $key ) {
                    $map[ (int) $i ] = $key;
                }
            }
        }
        if ( ! $map ) {
            $this->io_notice( 'error', __( 'Nothing was matched to a field, so there is nothing to import.', 'loopkit' ) );
            return;
        }

        $r = self::import_csv(
            (int) $pending['collection_id'],
            $pending['path'],
            $map,
            array(
                'match_on'  => isset( $_POST['match_on'] ) ? sanitize_text_field( wp_unslash( $_POST['match_on'] ) ) : '',
                'skip_rows' => isset( $_POST['skip_rows'] ) ? absint( $_POST['skip_rows'] ) : 0,
            )
        );

        @unlink( $pending['path'] ); // phpcs:ignore
        delete_transient( 'loopkit_cms_import_' . get_current_user_id() );

        $msg = sprintf(
            /* translators: 1: rows added, 2: rows updated, 3: rows skipped */
            __( 'Import finished: %1$s added, %2$s updated, %3$s skipped.', 'loopkit' ),
            number_format_i18n( $r['created'] ),
            number_format_i18n( $r['updated'] ),
            number_format_i18n( $r['skipped'] )
        );
        if ( $r['errors'] ) {
            $msg .= '</p><p>' . esc_html__( 'These lines were left out:', 'loopkit' ) . '</p><ul style="margin-left:18px;list-style:disc">';
            foreach ( $r['errors'] as $e ) {
                $msg .= '<li>' . esc_html( $e ) . '</li>';
            }
            $msg .= '</ul><p>';
        }
        $this->io_notice( $r['errors'] ? 'error' : 'updated', $msg, false );
    }

    /** Drop a pending upload without importing it. */
    protected function do_cancel_import() {
        $pending = get_transient( 'loopkit_cms_import_' . get_current_user_id() );
        if ( $pending && ! empty( $pending['path'] ) ) {
            @unlink( $pending['path'] ); // phpcs:ignore
        }
        delete_transient( 'loopkit_cms_import_' . get_current_user_id() );
    }

    /**
     * A notice for the Import / Export screen.
     *
     * @param string $kind   updated or error.
     * @param string $text   The message.
     * @param bool   $escape Escape it; false where the caller built markup.
     */
    protected function io_notice( $kind, $text, $escape = true ) {
        set_transient(
            'loopkit_cms_notice_' . get_current_user_id(),
            '<div class="' . esc_attr( $kind ) . '"><p>' . ( $escape ? esc_html( $text ) : $text ) . '</p></div>',
            MINUTE_IN_SECONDS
        );
    }

    /** AJAX: export CSV. */
    public function ajax_export_csv() {
        // Stage F.
    }

    /** AJAX: import CSV. */
    public function ajax_import_csv() {
        // Stage F.
    }

    /** Add CMS dynamic tags. */
    /**
     * The collection row being rendered right now, if any.
     *
     * The Collection List widget sets this around each card so the dynamic
     * tags inside that card resolve against the row they belong to. Outside
     * a card it stays null and the tags render empty, the same way post tags
     * do when there is no post.
     *
     * @var object|null
     */
    protected static $current_item = null;

    /**
     * The collection the current row belongs to.
     *
     * @var object|null
     */
    protected static $current_collection = null;

    /**
     * Put a row in context for the tags, or clear it with nulls.
     *
     * @param object|null $item       Item row.
     * @param object|null $collection Its collection.
     */
    public static function set_context( $item, $collection = null ) {
        self::$current_item       = $item;
        self::$current_collection = $collection;
    }

    /**
     * The row currently in context, if any.
     *
     * @return object|null
     */
    public static function get_context_item() {
        return self::$current_item;
    }

    /**
     * Turn a CMS dynamic tag into its value.
     *
     * Registering a tag only puts it in the menu; without this it renders
     * nothing. Unknown tags pass straight through so other modules keep
     * their own.
     *
     * @param string $value   Value so far.
     * @param string $name    Tag name.
     * @param string $arg     Tag argument, e.g. the field key.
     * @param int    $post_id Post in context, unused here.
     * @return string
     */
    public function resolve_dynamic_tag( $value, $name, $arg, $post_id = 0 ) {
        if ( 'collection_title' === $name ) {
            return self::$current_collection ? (string) self::$current_collection->name : (string) $value;
        }

        if ( 'item_field' === $name ) {
            $key = sanitize_key( (string) $arg );
            if ( '' === $key || ! self::$current_item ) {
                return (string) $value;
            }
            $data = is_array( self::$current_item->data ) ? self::$current_item->data : array();
            if ( ! array_key_exists( $key, $data ) ) {
                return (string) $value;
            }
            $out = $data[ $key ];
            if ( is_array( $out ) ) {
                $out = implode( ', ', array_filter( array_map( 'strval', $out ) ) );
            }
            return (string) $out;
        }

        return $value;
    }

    public function add_dynamic_tags( $groups ) {
        // Stage D.
        $groups[] = array(
            'group' => __( 'CMS', 'loopkit' ),
            'tags'  => array(
                array( 'name' => 'collection_title', 'label' => __( 'Collection Title', 'loopkit' ), 'type' => 'text' ),
                array( 'name' => 'item_field', 'label' => __( 'Item Field', 'loopkit' ), 'type' => 'text', 'arg' => __( 'field key', 'loopkit' ) ),
            ),
        );

        // Every field, by its own name, in its own group per collection. A
        // single "Item Field" that wants a key typed in means knowing the key
        // and spelling it right — the fields are already known here.
        foreach ( self::get_collections() as $lk_col ) {
            $lk_fields = self::get_fields( (int) $lk_col->id );
            if ( ! $lk_fields ) {
                continue;
            }
            $lk_tags = array();
            foreach ( $lk_fields as $lk_field ) {
                $lk_tags[] = array(
                    'name'  => 'item_field',
                    'label' => $lk_field->label,
                    'type'  => 'text',
                    'arg'   => $lk_field->field_key,
                );
            }
            $groups[] = array(
                /* translators: %s: collection name */
                'group' => sprintf( __( 'CMS: %s', 'loopkit' ), $lk_col->name ),
                'tags'  => $lk_tags,
            );
        }


        return $groups;
    }

    /** Register rewrite rules for collection item pages. */
    public function register_rewrites() {
        // Stage E.
    }

    /** Register query vars. */
    public function register_query_vars( $vars ) {
        // Stage E.
        return $vars;
    }

    /** Handle CMS item template rendering. */
    public function handle_cms_template() {
        // Stage E.
    }
}
