<?php
/**
 * Loopkit Form Submissions.
 *
 * Every Contact Form entry is stored in its own table so nothing is lost when an email
 * fails to send, and so entries can be read, searched, exported and used by automations.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Submissions {

    const DB_VERSION = '1.1';

    /**
     * Table name.
     *
     * @return string
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'loopkit_submissions';
    }

    /**
     * Create or upgrade the table. Runs on activation and when the stored version differs.
     */
    public static function install() {
        global $wpdb;
        if ( get_option( 'loopkit_submissions_db' ) === self::DB_VERSION ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $table   = self::table();

        dbDelta( "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            element_id varchar(64) NOT NULL DEFAULT '',
            form_name varchar(191) NOT NULL DEFAULT '',
            source varchar(40) NOT NULL DEFAULT 'form',
            ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
            fields longtext NOT NULL,
            page_url text NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_ip varchar(100) NOT NULL DEFAULT '',
            mail_sent tinyint(1) NOT NULL DEFAULT 0,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY source (source),
            KEY created_at (created_at)
        ) {$charset};" );

        update_option( 'loopkit_submissions_db', self::DB_VERSION );
    }

    /**
     * Hook up: store every submission, then let automations run on it.
     */
    public static function init() {
        add_action( 'loopkit_form_submitted', array( __CLASS__, 'store' ), 10, 4 );
    }

    /**
     * Save one submission.
     *
     * @param int   $post_id Page the form is on.
     * @param string $el_id  Form element ID.
     * @param array $lines   "Label:\nvalue" lines from the form handler.
     * @param bool  $sent    Whether the notification email went out.
     */
    public static function store( $post_id, $el_id, $lines, $sent ) {
        global $wpdb;
        self::install();

        // "Label:\nvalue" back into label => value
        $fields = array();
        foreach ( (array) $lines as $line ) {
            $parts = explode( ":\n", (string) $line, 2 );
            $label = trim( $parts[0] ?? '' );
            if ( '' === $label ) {
                continue;
            }
            $fields[ $label ] = trim( $parts[1] ?? '' );
        }

        $data = array(
            'source'     => 'form',
            'ref_id'     => 0,
            'post_id'    => (int) $post_id,
            'element_id' => (string) $el_id,
            'form_name'  => get_the_title( $post_id ),
            'fields'     => wp_json_encode( $fields ),
            'page_url'   => (string) get_permalink( $post_id ),
            'user_id'    => get_current_user_id(),
            'user_ip'    => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
            'mail_sent'  => $sent ? 1 : 0,
            'is_read'    => 0,
            'created_at' => current_time( 'mysql' ),
        );

        $wpdb->insert( self::table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $data['id']     = (int) $wpdb->insert_id;
        $data['fields'] = $fields;

        /**
         * A submission has been stored. Automations listen here.
         *
         * @param array $data Submission row with fields as an array.
         */
        do_action( 'loopkit_submission_stored', $data );
    }

    /**
     * Record something that is not a Contact Form entry — a shop checkout, for example.
     *
     * @param string $source    Where it came from ("shop").
     * @param array  $fields    Label => value.
     * @param array  $meta      form_name, page_url, post_id, ref_id.
     * @return int Row id.
     */
    public static function record( $source, $fields, $meta = array() ) {
        global $wpdb;
        self::install();

        $row = array(
            'source'     => sanitize_key( $source ),
            'ref_id'     => (int) ( $meta['ref_id'] ?? 0 ),
            'post_id'    => (int) ( $meta['post_id'] ?? 0 ),
            'element_id' => (string) ( $meta['element_id'] ?? '' ),
            'form_name'  => (string) ( $meta['form_name'] ?? '' ),
            'fields'     => wp_json_encode( (array) $fields ),
            'page_url'   => (string) ( $meta['page_url'] ?? '' ),
            'user_id'    => get_current_user_id(),
            'user_ip'    => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
            'mail_sent'  => 1,
            'is_read'    => 0,
            'created_at' => current_time( 'mysql' ),
        );

        $wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->insert_id;
    }

    /**
     * Read submissions.
     *
     * @param array $args post_id, search, per_page, page.
     * @return array
     */
    public static function get_submissions( $args = array() ) {
        global $wpdb;
        $args = wp_parse_args( $args, array(
            'post_id'  => 0,
            'search'   => '',
            'source'   => 'form',
            'per_page' => 20,
            'page'     => 1,
        ) );

        $table  = self::table();
        $where  = 'WHERE 1=1';
        $params = array();

        if ( $args['post_id'] ) {
            $where   .= ' AND post_id = %d';
            $params[] = (int) $args['post_id'];
        }
        if ( '' !== $args['source'] && 'any' !== $args['source'] ) {
            $where   .= ' AND source = %s';
            $params[] = (string) $args['source'];
        }
        if ( '' !== $args['search'] ) {
            $where   .= ' AND fields LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }

        $per_page = max( 1, (int) $args['per_page'] );
        $offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

        $sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
        foreach ( $rows as &$row ) {
            $row['fields'] = json_decode( $row['fields'], true ) ?: array();
            $row['source'] = $row['source'] ?? 'form';
            $row['ref_id'] = (int) ( $row['ref_id'] ?? 0 );
        }
        return $rows ?: array();
    }

    /**
     * How many submissions match.
     *
     * @param array $args post_id, search.
     * @return int
     */
    public static function count_submissions( $args = array() ) {
        global $wpdb;
        $table  = self::table();
        $where  = 'WHERE 1=1';
        $params = array();
        if ( ! empty( $args['post_id'] ) ) {
            $where   .= ' AND post_id = %d';
            $params[] = (int) $args['post_id'];
        }
        $source = $args['source'] ?? 'form';
        if ( '' !== $source && 'any' !== $source ) {
            $where   .= ' AND source = %s';
            $params[] = (string) $source;
        }
        if ( ! empty( $args['search'] ) ) {
            $where   .= ' AND fields LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }
        $sql = "SELECT COUNT(*) FROM {$table} {$where}";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
        return (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql ) );
    }

    /**
     * Delete one submission.
     *
     * @param int $id Submission ID.
     * @return bool
     */
    public static function delete( $id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    /**
     * All submissions as CSV.
     *
     * @param array $args Same as get_submissions().
     * @return string
     */
    public static function to_csv( $args = array() ) {
        $args['per_page'] = 10000;
        $rows             = self::get_submissions( $args );

        $labels = array();
        foreach ( $rows as $row ) {
            foreach ( array_keys( $row['fields'] ) as $label ) {
                $labels[ $label ] = true;
            }
        }
        $labels = array_keys( $labels );

        $out = fopen( 'php://temp', 'r+' );
        fputcsv( $out, array_merge( array( 'ID', 'Date', 'Page', 'URL' ), $labels ) );
        foreach ( $rows as $row ) {
            $line = array( $row['id'], $row['created_at'], $row['form_name'], $row['page_url'] );
            foreach ( $labels as $label ) {
                $line[] = $row['fields'][ $label ] ?? '';
            }
            fputcsv( $out, $line );
        }
        rewind( $out );
        $csv = stream_get_contents( $out );
        fclose( $out );
        return (string) $csv;
    }
}
