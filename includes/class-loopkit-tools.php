<?php
/**
 * Loopkit Tools class.
 * Handles Tools functionality: clear cache, replace URL, maintenance mode.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Tools {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_loopkit_clear_cache', array( $this, 'ajax_clear_cache' ) );
        add_action( 'wp_ajax_loopkit_replace_url', array( $this, 'ajax_replace_url' ) );
    }

    /**
     * Register maintenance settings.
     */
    public function register_settings() {
        register_setting( 'loopkit_maintenance', 'loopkit_maintenance_settings', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_maintenance' ),
            'default'           => array(
                'mode'        => 'disabled',
                'template_id' => '',
                'who_access'  => 'logged_in',
            ),
        ) );
    }

    /**
     * Sanitize maintenance settings.
     *
     * @param array $input Raw input.
     * @return array Sanitized input.
     */
    public function sanitize_maintenance( $input ) {
        $sanitized = array();

        $allowed_modes = array( 'disabled', 'coming_soon', 'maintenance' );
        $sanitized['mode'] = in_array( $input['mode'] ?? '', $allowed_modes, true )
            ? $input['mode']
            : 'disabled';

        $sanitized['template_id'] = absint( $input['template_id'] ?? 0 );

        $allowed_access = array( 'logged_in', 'admins_only' );
        $sanitized['who_access'] = in_array( $input['who_access'] ?? '', $allowed_access, true )
            ? $input['who_access']
            : 'logged_in';

        return $sanitized;
    }

    /**
     * AJAX handler: clear CSS cache.
     */
    public function ajax_clear_cache() {
        check_ajax_referer( 'loopkit_tools_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'loopkit' ) ) );
        }

        $upload_dir = wp_upload_dir();
        $css_dir    = $upload_dir['basedir'] . '/loopkit/css/';

        $count = 0;
        if ( is_dir( $css_dir ) ) {
            $files = glob( $css_dir . '*.css' );
            if ( $files ) {
                foreach ( $files as $file ) {
                    if ( wp_delete_file( $file ) || @unlink( $file ) ) {
                        $count++;
                    }
                }
            }
        }

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %d: number of files deleted */
                esc_html__( 'Cache cleared. %d file(s) deleted.', 'loopkit' ),
                $count
            ),
        ) );
    }

    /**
     * AJAX handler: replace URL in all Loopkit data.
     */
    public function ajax_replace_url() {
        check_ajax_referer( 'loopkit_tools_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'loopkit' ) ) );
        }

        $old_url = esc_url_raw( wp_unslash( $_POST['old_url'] ?? '' ) );
        $new_url = esc_url_raw( wp_unslash( $_POST['new_url'] ?? '' ) );

        if ( empty( $old_url ) || empty( $new_url ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Both URLs are required.', 'loopkit' ) ) );
        }

        global $wpdb;

        // Replace in loopkit_data post meta
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key = '_loopkit_data'",
                $old_url,
                $new_url
            )
        );

        $count = $result !== false ? $result : 0;

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %d: number of rows updated */
                esc_html__( 'URL replacement complete. %d row(s) updated.', 'loopkit' ),
                $count
            ),
        ) );
    }

    /**
     * Get maintenance settings.
     *
     * @return array
     */
    public static function get_maintenance() {
        return get_option( 'loopkit_maintenance_settings', array(
            'mode'        => 'disabled',
            'template_id' => '',
            'who_access'  => 'logged_in',
        ) );
    }
}

new Loopkit_Tools();
