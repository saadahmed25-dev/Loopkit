<?php
/**
 * Loopkit Shortcode Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Shortcode extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'shortcode';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Shortcode', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-shortcode';
    }

    /**
     * Get widget category.
     *
     * @return string
     */
    public function get_category() {
        return 'general';
    }

    /**
     * Get search keywords.
     *
     * @return array
     */
    public function get_keywords() {
        return array( 'shortcode', 'plugin', 'wordpress', 'form', 'contact', 'dynamic', 'snippet' );
    }

    /**
     * Register controls.
     *
     * @return array
     */
    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_shortcode',
                    'title'    => __( 'Shortcode', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'shortcode',
                            'label'       => __( 'Enter your shortcode', 'loopkit' ),
                            'type'        => 'textarea',
                            'placeholder' => '[gallery id="123" size="medium"]',
                            'default'     => '',
                        ),
                    ),
                ),
            ),
            'style'   => array(),
        );
    }

    /**
     * Render widget output.
     *
     * @param array $settings    Element settings.
     * @param array $el          Full element array.
     * @param bool  $editor_mode Whether rendering in editor/canvas mode.
     * @return string HTML output.
     */
    public function render( $settings, $el, $editor_mode = false ) {
        $shortcode = trim( (string) ( $settings['shortcode'] ?? '' ) );

        if ( empty( $shortcode ) ) {
            return '<div class="lk-shortcode-placeholder"><span class="dashicons dashicons-shortcode"></span><span>' . esc_html__( 'Enter your shortcode in the settings panel', 'loopkit' ) . '</span></div>';
        }

        // Check if shortcode tag exists
        preg_match( '/\[([a-zA-Z0-9_\-]+)/', $shortcode, $matches );
        $tag_exists = ! empty( $matches[1] ) && shortcode_exists( $matches[1] );

        $output = do_shortcode( $shortcode );

        if ( ! $tag_exists || empty( trim( $output ) ) || trim( $output ) === trim( $shortcode ) ) {
            return '<div class="lk-shortcode-placeholder"><span class="dashicons dashicons-shortcode"></span><span>' . esc_html( $shortcode ) . '</span></div>';
        }

        return '<div class="lk-widget-shortcode">' . $output . '</div>';
    }
}
