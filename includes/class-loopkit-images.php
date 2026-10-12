<?php
/**
 * Loopkit Images Manager.
 * Handles image sizing, custom dimensions using wp_get_image_editor, and source generation.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Images {

    /**
     * Get all registered image sizes with human-readable labels.
     *
     * @return array
     */
    public static function get_image_sizes() {
        global $_wp_additional_image_sizes;

        $sizes = array(
            'thumbnail'    => esc_html__( 'Thumbnail - 150 x 150', 'loopkit' ),
            'medium'       => esc_html__( 'Medium - 300 x 300', 'loopkit' ),
            'medium_large' => esc_html__( 'Medium Large - 768 x 0', 'loopkit' ),
            'large'        => esc_html__( 'Large - 1024 x 1024', 'loopkit' ),
            'full'         => esc_html__( 'Full', 'loopkit' ),
        );

        // Get intermediate sizes
        $additional = get_intermediate_image_sizes();
        if ( is_array( $additional ) ) {
            foreach ( $additional as $s ) {
                if ( isset( $sizes[ $s ] ) ) {
                    continue;
                }
                $w = intval( get_option( "{$s}_size_w" ) );
                $h = intval( get_option( "{$s}_size_h" ) );
                if ( isset( $_wp_additional_image_sizes[ $s ] ) ) {
                    $w = intval( $_wp_additional_image_sizes[ $s ]['width'] );
                    $h = intval( $_wp_additional_image_sizes[ $s ]['height'] );
                }
                $label = ucwords( str_replace( array( '-', '_' ), ' ', $s ) );
                if ( $w || $h ) {
                    $label .= " - {$w} x {$h}";
                }
                $sizes[ $s ] = $label;
            }
        }

        $sizes['custom'] = esc_html__( 'Custom', 'loopkit' );

        return $sizes;
    }
}
