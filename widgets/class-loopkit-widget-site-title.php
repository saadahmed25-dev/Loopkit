<?php
/**
 * Loopkit Site Title Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Site_Title extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'site-title';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Site Title', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-admin-site';
    }

    /**
     * Get widget category.
     *
     * @return string
     */
    public function get_category() {
        return 'site';
    }

    /**
     * Get search keywords.
     *
     * @return array
     */
    public function get_keywords() {
        return array( 'site', 'title', 'name', 'bloginfo', 'heading', 'brand', 'identity' );
    }

    /**
     * Get widget context.
     *
     * @return string
     */
    public function get_context() {
        return 'all';
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
                    'id'       => 'section_site_title',
                    'title'    => __( 'Site Title', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'title',
                            'label'       => __( 'Title', 'loopkit' ),
                            'type'        => 'text',
                            'default'     => '',
                            'placeholder' => get_bloginfo( 'name' ),
                            'description' => __( 'Leave blank to use the default site name.', 'loopkit' ),
                        ),
                        array(
                            'name'    => 'link_to_home',
                            'label'   => __( 'Link to Home', 'loopkit' ),
                            'type'    => 'switcher',
                            'default' => 'yes',
                        ),
                        array(
                            'name'    => 'header_size',
                            'label'   => __( 'HTML Tag', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'h1',
                            'options' => array(
                                'h1'   => 'H1',
                                'h2'   => 'H2',
                                'h3'   => 'H3',
                                'h4'   => 'H4',
                                'h5'   => 'H5',
                                'h6'   => 'H6',
                                'div'  => 'div',
                                'span' => 'span',
                                'p'    => 'p',
                            ),
                        ),
                        array(
                            'name'    => 'size',
                            'label'   => __( 'Size', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'default',
                            'options' => array(
                                'default' => esc_html__( 'Default', 'loopkit' ),
                                'small'   => esc_html__( 'Small', 'loopkit' ),
                                'medium'  => esc_html__( 'Medium', 'loopkit' ),
                                'large'   => esc_html__( 'Large', 'loopkit' ),
                                'xl'      => esc_html__( 'XL', 'loopkit' ),
                                'xxl'     => esc_html__( 'XXL', 'loopkit' ),
                            ),
                        ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_style_site_title',
                    'title'    => __( 'Title', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'       => 'align',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'options'    => array(
                                'left'    => array( 'title' => __( 'Left', 'loopkit' ), 'icon' => 'dashicons-editor-alignleft' ),
                                'center'  => array( 'title' => __( 'Center', 'loopkit' ), 'icon' => 'dashicons-editor-aligncenter' ),
                                'right'   => array( 'title' => __( 'Right', 'loopkit' ), 'icon' => 'dashicons-editor-alignright' ),
                                'justify' => array( 'title' => __( 'Justified', 'loopkit' ), 'icon' => 'dashicons-editor-justify' ),
                            ),
                        ),
                        array(
                            'name'  => 'title_color',
                            'label' => __( 'Text Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'typography',
                            'label' => __( 'Typography', 'loopkit' ),
                            'type'  => 'typography',
                        ),
                        array(
                            'name'  => 'title_hover_color',
                            'label' => __( 'Link Hover Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'        => 'text_shadow',
                            'label'       => __( 'Text Shadow', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'text_shadow',
                        ),
                        array(
                            'name'        => 'text_stroke',
                            'label'       => __( 'Text Stroke', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'text_stroke',
                            'responsive'  => true,
                        ),
                        array(
                            'name'    => 'blend_mode',
                            'label'   => __( 'Blend Mode', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'normal',
                            'options' => array(
                                'normal'      => esc_html__( 'Normal', 'loopkit' ),
                                'multiply'    => esc_html__( 'Multiply', 'loopkit' ),
                                'screen'      => esc_html__( 'Screen', 'loopkit' ),
                                'overlay'     => esc_html__( 'Overlay', 'loopkit' ),
                                'darken'      => esc_html__( 'Darken', 'loopkit' ),
                                'lighten'     => esc_html__( 'Lighten', 'loopkit' ),
                                'color-dodge' => esc_html__( 'Color Dodge', 'loopkit' ),
                                'saturation'  => esc_html__( 'Saturation', 'loopkit' ),
                                'color'       => esc_html__( 'Color', 'loopkit' ),
                                'difference'  => esc_html__( 'Difference', 'loopkit' ),
                                'exclusion'   => esc_html__( 'Exclusion', 'loopkit' ),
                                'hue'         => esc_html__( 'Hue', 'loopkit' ),
                                'luminosity'  => esc_html__( 'Luminosity', 'loopkit' ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Render widget output.
     *
     * @param array $settings    Widget settings.
     * @param array $el          Element data.
     * @param bool  $editor_mode Whether rendering in editor mode.
     * @return string HTML output.
     */
    public function render( $settings, $el, $editor_mode = false ) {
        $title = ! empty( $settings['title'] ) ? $settings['title'] : get_bloginfo( 'name' );
        $tag   = ! empty( $settings['header_size'] ) ? $settings['header_size'] : 'h1';
        $size  = ! empty( $settings['size'] ) && 'default' !== $settings['size'] ? ' lk-size-' . sanitize_html_class( $settings['size'] ) : '';

        $allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );
        if ( ! in_array( $tag, $allowed_tags, true ) ) {
            $tag = 'h1';
        }

        $link_home = ! empty( $settings['link_to_home'] ) && ( 'yes' === $settings['link_to_home'] || true === $settings['link_to_home'] );

        $inner_html = esc_html( $title );
        if ( $link_home ) {
            $inner_html = '<a href="' . esc_url( home_url( '/' ) ) . '">' . $inner_html . '</a>';
        }

        // Same markup as the canvas (loopkit-canvas.js renderSiteTitle) so saved CSS applies to both.
        return '<div class="lk-site-title-wrapper' . esc_attr( $size ) . '"><' . $tag . ' class="lk-site-title-heading" data-lk-inline-edit="title">' . $inner_html . '</' . $tag . '></div>';
    }
}
