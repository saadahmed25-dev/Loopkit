<?php
/**
 * Loopkit Spacer Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Spacer extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'spacer';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Spacer', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-sort';
    }

    /**
     * Get widget category.
     *
     * @return string
     */
    public function get_category() {
        return 'basic';
    }

    /**
     * Get search keywords.
     *
     * @return array
     */
    public function get_keywords() {
        return array( 'spacer', 'space', 'gap', 'blank', 'empty', 'height', 'margin', 'padding' );
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
                    'id'       => 'section_spacer',
                    'title'    => __( 'Spacer', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'       => 'space',
                            'label'      => __( 'Space', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min'  => 10,
                                'max'  => 600,
                                'step' => 1,
                            ),
                            'units'      => array( 'px', 'em', 'rem', 'vh', 'custom' ),
                            'default'    => array(
                                'size' => 50,
                                'unit' => 'px',
                            ),
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
        $space = $settings['space'] ?? array( 'size' => 50, 'unit' => 'px' );
        $size  = is_array( $space ) ? ( $space['size'] ?? 50 ) : $space;
        $unit  = is_array( $space ) ? ( $space['unit'] ?? 'px' ) : 'px';

        $style_attr = ' style="height:' . esc_attr( $size . $unit ) . ';"';

        return '<div class="lk-spacer"' . $style_attr . '><div class="lk-spacer-inner"></div></div>';
    }
}
