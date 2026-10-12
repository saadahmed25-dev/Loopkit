<?php
/**
 * Loopkit Icon Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Icon extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'icon';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Icon', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-star-filled';
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
        return array( 'icon', 'star', 'symbol', 'fontawesome', 'glyph', 'mark' );
    }

    /**
     * Register controls.
     *
     * @return array
     */
    protected function register_controls() {
        $default_primary = ! $this->is_default_colors_disabled() ? 'globals/colors?id=primary' : '#38A8F5';

        return array(
            'content' => array(
                array(
                    'id'       => 'section_icon',
                    'title'    => __( 'Icon', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'selected_icon',
                            'label'   => __( 'Icon', 'loopkit' ),
                            'type'    => 'icon',
                            'default' => array(
                                'library' => 'fa-solid',
                                'value'   => 'star',
                            ),
                        ),
                        array(
                            'name'    => 'view',
                            'label'   => __( 'View', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'default',
                            'options' => array(
                                'default' => esc_html__( 'Default', 'loopkit' ),
                                'stacked' => esc_html__( 'Stacked', 'loopkit' ),
                                'framed'  => esc_html__( 'Framed', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'      => 'shape',
                            'label'     => __( 'Shape', 'loopkit' ),
                            'type'      => 'select',
                            'default'   => 'circle',
                            'options'   => array(
                                'circle'  => esc_html__( 'Circle', 'loopkit' ),
                                'square'  => esc_html__( 'Square', 'loopkit' ),
                                'rounded' => esc_html__( 'Rounded', 'loopkit' ),
                            ),
                            'condition' => array(
                                'view!' => 'default',
                            ),
                        ),
                        array(
                            'name'    => 'link',
                            'label'   => __( 'Link', 'loopkit' ),
                            'type'    => 'url',
                            'default' => array(
                                'url' => '',
                            ),
                        ),
                        array(
                            'name'       => 'align',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'default'    => 'center',
                            'options'    => array(
                                'left'   => array(
                                    'title' => __( 'Left', 'loopkit' ),
                                    'icon'  => 'dashicons-editor-alignleft',
                                ),
                                'center' => array(
                                    'title' => __( 'Center', 'loopkit' ),
                                    'icon'  => 'dashicons-editor-aligncenter',
                                ),
                                'right'  => array(
                                    'title' => __( 'Right', 'loopkit' ),
                                    'icon'  => 'dashicons-editor-alignright',
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_icon',
                    'title'    => __( 'Icon', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'    => 'primary_color',
                            'label'   => __( 'Primary Colour', 'loopkit' ),
                            'type'    => 'color',
                            'default' => $default_primary,
                        ),
                        array(
                            'name'      => 'secondary_color',
                            'label'     => __( 'Secondary Colour', 'loopkit' ),
                            'type'      => 'color',
                            'default'   => '#FFFFFF',
                            'condition' => array(
                                'view!' => 'default',
                            ),
                        ),
                        array(
                            'name'  => 'hover_primary_color',
                            'label' => __( 'Primary Colour (Hover)', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'      => 'hover_secondary_color',
                            'label'     => __( 'Secondary Colour (Hover)', 'loopkit' ),
                            'type'      => 'color',
                            'condition' => array(
                                'view!' => 'default',
                            ),
                        ),
                        array(
                            'name'    => 'hover_animation',
                            'label'   => __( 'Hover Animation', 'loopkit' ),
                            'type'    => 'hover_animation',
                            'default' => '',
                        ),
                        array(
                            'name'       => 'icon_size',
                            'label'      => __( 'Size', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 6,
                                'max' => 300,
                            ),
                            'default'    => array(
                                'size' => 50,
                                'unit' => 'px',
                            ),
                        ),
                        array(
                            'name'      => 'fit_to_size',
                            'label'     => __( 'Fit to Size', 'loopkit' ),
                            'type'      => 'switcher',
                            'default'   => 'no',
                            'condition' => array(
                                'view!' => 'default',
                            ),
                        ),
                        array(
                            'name'       => 'icon_padding',
                            'label'      => __( 'Padding', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 0,
                                'max' => 100,
                            ),
                            'default'    => array(
                                'size' => 15,
                                'unit' => 'px',
                            ),
                            'condition'  => array(
                                'view!' => 'default',
                            ),
                        ),
                        array(
                            'name'       => 'rotate',
                            'label'      => __( 'Rotate', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min'  => 0,
                                'max'  => 360,
                                'step' => 1,
                            ),
                            'default'    => 0,
                        ),
                        array(
                            'name'       => 'border_width',
                            'label'      => __( 'Border Width', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 1,
                                'max' => 20,
                            ),
                            'default'    => array(
                                'size' => 3,
                                'unit' => 'px',
                            ),
                            'condition'  => array(
                                'view' => 'framed',
                            ),
                        ),
                        array(
                            'name'       => 'border_radius',
                            'label'      => __( 'Border Radius', 'loopkit' ),
                            'type'       => 'dimensions',
                            'responsive' => true,
                            'condition'  => array(
                                'view!' => 'default',
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
        $icon_val = $settings['selected_icon'] ?? array( 'library' => 'fa-solid', 'value' => 'star' );
        $view     = $settings['view'] ?? 'default';
        $shape    = $settings['shape'] ?? 'circle';
        $align    = $settings['align'] ?? 'center';
        $hover    = $settings['hover_animation'] ?? '';

        $rendered_svg = '';
        if ( class_exists( 'Loopkit_Icons' ) ) {
            $rendered_svg = Loopkit_Icons::render( $icon_val );
        }

        if ( empty( $rendered_svg ) ) {
            // Default fallback star SVG
            $rendered_svg = '<svg aria-hidden="true" class="lk-svg-icon" viewBox="0 0 576 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M259.3 17.8L194 150.2 47.9 171.5c-26.2 3.8-36.7 36.1-17.7 54.6l105.7 103-25 145.5c-4.5 26.3 23.2 46 46.4 33.7L288 439.6l130.7 68.7c23.2 12.2 50.9-7.4 46.4-33.7l-25-145.5 105.7-103c19-18.5 8.5-50.8-17.7-54.6L382 150.2 316.7 17.8c-11.7-23.6-45.6-23.9-57.4 0z"></path></svg>';
        }

        $classes = array( 'lk-icon' );
        $classes[] = 'lk-icon-view-' . esc_attr( $view );
        if ( 'default' !== $view ) {
            $classes[] = 'lk-icon-shape-' . esc_attr( $shape );
        }
        if ( ! empty( $hover ) ) {
            $classes[] = 'lk-animation-' . esc_attr( $hover );
        }

        $link_obj = $settings['link'] ?? array();
        $url      = '';
        $attrs    = '';

        if ( is_array( $link_obj ) ) {
            $url = ! empty( $link_obj['url'] ) ? $link_obj['url'] : '';
            if ( ! empty( $link_obj['is_external'] ) ) {
                $attrs .= ' target="_blank"';
                $rel    = ! empty( $link_obj['nofollow'] ) ? 'nofollow noopener noreferrer' : 'noopener noreferrer';
                $attrs .= ' rel="' . esc_attr( $rel ) . '"';
            } elseif ( ! empty( $link_obj['nofollow'] ) ) {
                $attrs .= ' rel="nofollow"';
            }
        } elseif ( is_string( $link_obj ) && ! empty( $link_obj ) ) {
            $url = $link_obj;
        }

        $tag = ! empty( $url ) ? 'a' : 'div';
        $href_attr = ! empty( $url ) ? ' href="' . esc_url( $url ) . '"' : '';

        $html = '<div class="lk-icon-wrapper lk-align-' . esc_attr( $align ) . '">';
        $html .= '<' . $tag . ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $href_attr . $attrs . '>';
        $html .= '<span class="lk-icon-wrap">' . $rendered_svg . '</span>';
        $html .= '</' . $tag . '>';
        $html .= '</div>';

        return $html;
    }
}
