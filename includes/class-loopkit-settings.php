<?php
/**
 * Loopkit Settings class.
 * Registers settings via WordPress Settings API.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Settings {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Register plugin settings with the Settings API.
     */
    public function register_settings() {
        // General settings
        register_setting( 'loopkit_general', 'loopkit_general_settings', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_general' ),
            'default'           => array(
                'post_types'             => array( 'page', 'post' ),
                'disable_default_colors' => '',
                'disable_default_fonts'  => '',
            ),
        ) );

        // Advanced settings
        register_setting( 'loopkit_advanced', 'loopkit_advanced_settings', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_advanced' ),
            'default'           => array(
                'css_print_method'    => 'external',
                'load_font_awesome'   => 'yes',
                'editor_loader_style' => 'default',
                'optimize_assets'     => 'yes',
            ),
        ) );
    }

    /**
     * Sanitize general settings.
     *
     * @param array $input Raw input.
     * @return array Sanitized input.
     */
    public function sanitize_general( $input ) {
        $sanitized = array();

        // Post types
        $sanitized['post_types'] = array();
        if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
            foreach ( $input['post_types'] as $pt ) {
                $sanitized['post_types'][] = sanitize_key( $pt );
            }
        }

        // Checkboxes
        $sanitized['disable_default_colors'] = ! empty( $input['disable_default_colors'] ) ? '1' : '';
        $sanitized['disable_default_fonts']  = ! empty( $input['disable_default_fonts'] ) ? '1' : '';

        return $sanitized;
    }

    /**
     * Sanitize advanced settings.
     *
     * @param array $input Raw input.
     * @return array Sanitized input.
     */
    public function sanitize_advanced( $input ) {
        $sanitized = array();

        // CSS print method
        $allowed_methods = array( 'external', 'internal' );
        $sanitized['css_print_method'] = in_array( $input['css_print_method'] ?? '', $allowed_methods, true )
            ? $input['css_print_method']
            : 'external';

        // Load Font Awesome
        $sanitized['load_font_awesome'] = in_array( $input['load_font_awesome'] ?? '', array( 'yes', 'no' ), true )
            ? $input['load_font_awesome']
            : 'yes';

        // Editor loader style
        $allowed_loaders = array( 'default', 'minimal', 'none' );
        $sanitized['editor_loader_style'] = in_array( $input['editor_loader_style'] ?? '', $allowed_loaders, true )
            ? $input['editor_loader_style']
            : 'default';

        // Optimize Assets (Minify & Combine)
        $sanitized['optimize_assets'] = in_array( $input['optimize_assets'] ?? '', array( 'yes', 'no' ), true )
            ? $input['optimize_assets']
            : 'yes';

        // Remove all data on uninstall. sanitize_advanced builds a fresh
        // array and keeps only the keys it knows, so a new setting that is
        // not listed here is silently dropped on every save.
        $sanitized['remove_data_on_uninstall'] = ! empty( $input['remove_data_on_uninstall'] ) ? '1' : '';

        return $sanitized;
    }

    /**
     * Get general settings.
     *
     * @return array
     */
    public static function get_general() {
        return get_option( 'loopkit_general_settings', array(
            'post_types'             => array( 'page', 'post' ),
            'disable_default_colors' => '',
            'disable_default_fonts'  => '',
        ) );
    }

    /**
     * Get advanced settings.
     *
     * @return array
     */
    public static function get_advanced() {
        return get_option( 'loopkit_advanced_settings', array(
            'css_print_method'    => 'external',
            'load_font_awesome'   => 'yes',
            'editor_loader_style' => 'default',
            'optimize_assets'     => 'yes',
        ) );
    }
}

new Loopkit_Settings();
