<?php
/**
 * Loopkit Heading Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Heading extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'heading';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Heading', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-heading';
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
        return array( 'heading', 'title', 'text' );
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
                    'id'       => 'section_title',
                    'title'    => __( 'Title', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'title',
                            'label'       => __( 'Title', 'loopkit' ),
                            'type'        => 'textarea',
                            'default'     => esc_html__( 'Add Your Heading Text Here', 'loopkit' ),
                            'placeholder' => __( 'Enter your title', 'loopkit' ),
                        ),
                        array(
                            'name'        => 'link',
                            'label'       => __( 'Link', 'loopkit' ),
                            'type'        => 'url',
                            'placeholder' => 'https://your-link.com',
                            'default'     => array( 'url' => '' ),
                        ),
                        array(
                            'name'    => 'header_size',
                            'label'   => __( 'HTML Tag', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'h2',
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
                            'name'       => 'align',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'default'    => 'left',
                            'options'    => array(
                                'left'    => array(
                                    'title' => __( 'Left', 'loopkit' ),
                                    'icon'  => 'dashicons-editor-alignleft',
                                ),
                                'center'  => array(
                                    'title' => __( 'Center', 'loopkit' ),
                                    'icon'  => 'dashicons-editor-aligncenter',
                                ),
                                'right'   => array(
                                    'title' => __( 'Right', 'loopkit' ),
                                    'icon'  => 'dashicons-editor-alignright',
                                ),
                                'justify' => array(
                                    'title' => __( 'Justified', 'loopkit' ),
                                    'icon'  => 'dashicons-editor-justify',
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_heading_style',
                    'title'    => __( 'Title', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
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
                            'name'        => 'text_stroke',
                            'label'       => __( 'Text Stroke', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'text_stroke',
                            'responsive'  => true,
                        ),
                        array(
                            'name'        => 'text_shadow',
                            'label'       => __( 'Text Shadow', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'text_shadow',
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
     * @param array $settings    Element settings.
     * @param array $el          Full element array.
     * @param bool  $editor_mode Whether rendering in editor/canvas mode.
     * @return string HTML output.
     */
    public function render( $settings, $el, $editor_mode = false ) {
        $title = $settings['title'] ?? esc_html__( 'Add Your Heading Text Here', 'loopkit' );
        $tag   = $settings['header_size'] ?? ( $settings['tag'] ?? 'h2' );

        $allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );
        if ( ! in_array( $tag, $allowed_tags, true ) ) {
            $tag = 'h2';
        }

        $link_obj   = $settings['link'] ?? array();
        $has_link   = ! empty( $link_obj['url'] );
        $link_html  = '';
        $link_close = '';

        if ( $has_link ) {
            $url      = esc_url( $link_obj['url'] );
            $target   = ! empty( $link_obj['is_external'] ) ? ' target="_blank"' : '';
            $nofollow = ! empty( $link_obj['nofollow'] ) ? ' rel="nofollow"' : '';
            $link_html = '<a href="' . $url . '"' . $target . $nofollow . '>';
            $link_close = '</a>';
        }

        $inline_attr = $editor_mode ? ' data-lk-inline-edit="title"' : '';

        $html = '<' . $tag . ' class="lk-heading-title"' . $inline_attr . '>';
        $html .= $link_html;
        $html .= wp_kses_post( $title );
        $html .= $link_close;
        $html .= '</' . $tag . '>';

        return $html;
    }
}
