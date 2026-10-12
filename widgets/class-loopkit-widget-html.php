<?php
/**
 * Loopkit HTML Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_HTML extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'html';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'HTML', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-html';
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
        return array( 'html', 'code', 'custom', 'raw', 'embed', 'iframe', 'script', 'tag' );
    }

    /**
     * Register controls.
     *
     * @return array
     */
    protected function register_controls() {
        $can_unfiltered = current_user_can( 'unfiltered_html' );
        return array(
            'content' => array(
                array(
                    'id'       => 'section_title',
                    'title'    => __( 'HTML Code', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'html',
                            'label'       => __( 'HTML Code', 'loopkit' ),
                            'type'        => 'code',
                            'mode'        => 'html',
                            'readonly'    => ! $can_unfiltered,
                            'default'     => '',
                            'description' => $can_unfiltered
                                ? esc_html__( 'Note: Scripts run on the front end and in Preview.', 'loopkit' )
                                : esc_html__( 'You do not have permission to edit custom HTML (unfiltered_html capability required). This field is read-only.', 'loopkit' ),
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
        $raw_html = $settings['html'] ?? '';

        if ( empty( trim( $raw_html ) ) ) {
            if ( $editor_mode ) {
                return '<div class="lk-html-placeholder"><span class="dashicons dashicons-html"></span><span>' . esc_html__( 'Custom HTML widget - Enter your HTML in the settings panel', 'loopkit' ) . '</span></div>';
            }
            return '';
        }

        if ( $editor_mode ) {
            $has_script = (bool) preg_match( '/<script\b[^>]*>/i', $raw_html );
            $safe_html  = preg_replace( '/<script\b([^>]*)>/i', '<script type="text/plain"$1>', $raw_html );
            $notice     = $has_script ? '<div class="lk-html-script-notice"><span class="dashicons dashicons-info"></span> ' . esc_html__( 'Scripts run on the front end and in Preview.', 'loopkit' ) . '</div>' : '';
            return '<div class="lk-html-container">' . $safe_html . $notice . '</div>';
        }

        // Frontend output: exactly as written
        return $raw_html;
    }
}
