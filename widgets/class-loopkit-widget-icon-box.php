<?php
/**
 * Loopkit Icon Box Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Icon_Box extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'icon-box';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Icon Box', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-art';
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
        return array( 'icon box', 'feature', 'service', 'card', 'blurb', 'perk', 'benefit' );
    }

    /**
     * Register controls.
     *
     * @return array
     */
    protected function register_controls() {
        $default_primary   = ! $this->is_default_colors_disabled() ? 'globals/colors?id=primary' : '#38A8F5';
        $default_text      = ! $this->is_default_colors_disabled() ? 'globals/colors?id=text' : '#333333';
        $default_pri_typo  = ! $this->is_default_fonts_disabled() ? 'globals/typography?id=primary' : '';
        $default_text_typo = ! $this->is_default_fonts_disabled() ? 'globals/typography?id=text' : '';

        return array(
            'content' => array(
                array(
                    'id'       => 'section_icon_box',
                    'title'    => __( 'Icon Box', 'loopkit' ),
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
                                'circle' => esc_html__( 'Circle', 'loopkit' ),
                                'square' => esc_html__( 'Square', 'loopkit' ),
                            ),
                            'condition' => array(
                                'view!' => 'default',
                            ),
                        ),
                        array(
                            'name'    => 'title_text',
                            'label'   => __( 'Title', 'loopkit' ),
                            'type'    => 'text',
                            'default' => esc_html__( 'This is the heading', 'loopkit' ),
                        ),
                        array(
                            'name'    => 'description_text',
                            'label'   => __( 'Description', 'loopkit' ),
                            'type'    => 'textarea',
                            'default' => esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.', 'loopkit' ),
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
                            'name'       => 'icon_position',
                            'label'      => __( 'Icon Position', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'default'    => 'top',
                            'options'    => array(
                                'left'  => array(
                                    'title' => __( 'Left', 'loopkit' ),
                                    'icon'  => 'dashicons-editor-alignleft',
                                ),
                                'top'   => array(
                                    'title' => __( 'Top', 'loopkit' ),
                                    'icon'  => 'dashicons-arrow-up-alt2',
                                ),
                                'right' => array(
                                    'title' => __( 'Right', 'loopkit' ),
                                    'icon'  => 'dashicons-editor-alignright',
                                ),
                            ),
                        ),
                        array(
                            'name'    => 'title_size',
                            'label'   => __( 'Title HTML Tag', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'h3',
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
                            'name'       => 'icon_space',
                            'label'      => __( 'Spacing', 'loopkit' ),
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
                        ),
                        array(
                            'name'       => 'icon_size',
                            'label'      => __( 'Size', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 6,
                                'max' => 100,
                            ),
                            'default'    => array(
                                'size' => 30,
                                'unit' => 'px',
                            ),
                        ),
                        array(
                            'name'       => 'icon_padding',
                            'label'      => __( 'Padding', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 0,
                                'max' => 50,
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
                            'name'       => 'icon_rotate',
                            'label'      => __( 'Rotate', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 0,
                                'max' => 360,
                            ),
                            'default'    => 0,
                        ),
                        array(
                            'name'       => 'icon_border_width',
                            'label'      => __( 'Border Width', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 1,
                                'max' => 20,
                            ),
                            'default'    => array(
                                'size' => 2,
                                'unit' => 'px',
                            ),
                            'condition'  => array(
                                'view' => 'framed',
                            ),
                        ),
                        array(
                            'name'       => 'icon_border_radius',
                            'label'      => __( 'Border Radius', 'loopkit' ),
                            'type'       => 'dimensions',
                            'responsive' => true,
                            'condition'  => array(
                                'view!' => 'default',
                            ),
                        ),
                    ),
                ),
                array(
                    'id'       => 'section_style_box',
                    'title'    => __( 'Box', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'       => 'alignment',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'default'    => 'center',
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
                        array(
                            'name'       => 'vertical_alignment',
                            'label'      => __( 'Vertical Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'default'    => 'top',
                            'condition'  => array(
                                'position!' => 'top',
                            ),
                            'options'    => array(
                                'top'    => array(
                                    'title' => __( 'Top', 'loopkit' ),
                                    'icon'  => 'dashicons-arrow-up-alt2',
                                ),
                                'middle' => array(
                                    'title' => __( 'Middle', 'loopkit' ),
                                    'icon'  => 'dashicons-minus',
                                ),
                                'bottom' => array(
                                    'title' => __( 'Bottom', 'loopkit' ),
                                    'icon'  => 'dashicons-arrow-down-alt2',
                                ),
                            ),
                        ),
                    ),
                ),
                array(
                    'id'       => 'section_style_content',
                    'title'    => __( 'Content', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'       => 'title_space',
                            'label'      => __( 'Title Spacing', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 0,
                                'max' => 50,
                            ),
                            'default'    => array(
                                'size' => 10,
                                'unit' => 'px',
                            ),
                        ),
                        array(
                            'name'    => 'title_color',
                            'label'   => __( 'Title Colour', 'loopkit' ),
                            'type'    => 'color',
                            'default' => $default_primary,
                        ),
                        array(
                            'name'    => 'title_typography',
                            'label'   => __( 'Title Typography', 'loopkit' ),
                            'type'    => 'typography',
                            'default' => $default_pri_typo,
                        ),
                        array(
                            'name'    => 'description_color',
                            'label'   => __( 'Description Colour', 'loopkit' ),
                            'type'    => 'color',
                            'default' => $default_text,
                        ),
                        array(
                            'name'    => 'description_typography',
                            'label'   => __( 'Description Typography', 'loopkit' ),
                            'type'    => 'typography',
                            'default' => $default_text_typo,
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
        $icon_val   = $settings['selected_icon'] ?? array( 'library' => 'fa-solid', 'value' => 'star' );
        $view       = $settings['view'] ?? 'default';
        $shape      = $settings['shape'] ?? 'circle';
        $title_text = $settings['title_text'] ?? esc_html__( 'This is the heading', 'loopkit' );
        $desc_text  = $settings['description_text'] ?? esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.', 'loopkit' );
        $tag        = sanitize_key( $settings['title_size'] ?? 'h3' );
        if ( ! in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), true ) ) {
            $tag = 'h3';
        }
        $pos     = ( $settings['icon_position'] ?? $settings['position'] ?? null ) ?? 'top';
        $align   = $settings['alignment'] ?? 'center';
        $v_align = $settings['vertical_alignment'] ?? 'top';
        $hover   = $settings['hover_animation'] ?? '';

        $rendered_svg = class_exists( 'Loopkit_Icons' ) ? Loopkit_Icons::render( $icon_val ) : '';
        if ( empty( $rendered_svg ) ) {
            $rendered_svg = '<svg aria-hidden="true" class="lk-svg-icon" viewBox="0 0 576 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M259.3 17.8L194 150.2 47.9 171.5c-26.2 3.8-36.7 36.1-17.7 54.6l105.7 103-25 145.5c-4.5 26.3 23.2 46 46.4 33.7L288 439.6l130.7 68.7c23.2 12.2 50.9-7.4 46.4-33.7l-25-145.5 105.7-103c19-18.5 8.5-50.8-17.7-54.6L382 150.2 316.7 17.8c-11.7-23.6-45.6-23.9-57.4 0z"></path></svg>';
        }

        $link_obj = $settings['link'] ?? array();
        $url      = '';
        $attrs    = '';
        if ( is_array( $link_obj ) && ! empty( $link_obj['url'] ) ) {
            $url = $link_obj['url'];
            if ( ! empty( $link_obj['is_external'] ) ) {
                $attrs .= ' target="_blank" rel="noopener noreferrer"';
            }
        } elseif ( is_string( $link_obj ) && ! empty( $link_obj ) ) {
            $url = $link_obj;
        }

        $icon_classes = array( 'lk-icon-box-icon', 'lk-icon', 'lk-icon-view-' . esc_attr( $view ) );
        if ( 'default' !== $view ) {
            $icon_classes[] = 'lk-icon-shape-' . esc_attr( $shape );
        }
        if ( ! empty( $hover ) ) {
            $icon_classes[] = 'lk-animation-' . esc_attr( $hover );
        }

        $icon_html = '<div class="' . esc_attr( implode( ' ', $icon_classes ) ) . '"><span class="lk-icon-wrap">' . $rendered_svg . '</span></div>';

        $inline_title_attr = $editor_mode ? ' data-lk-inline-edit="title_text"' : '';
        $inline_desc_attr  = $editor_mode ? ' data-lk-inline-edit="description_text"' : '';

        $content_html = '<div class="lk-icon-box-content">';
        $content_html .= '<' . $tag . ' class="lk-icon-box-title"' . $inline_title_attr . '>';
        if ( ! empty( $url ) ) {
            $content_html .= '<a href="' . esc_url( $url ) . '"' . $attrs . '>' . esc_html( $title_text ) . '</a>';
        } else {
            $content_html .= esc_html( $title_text );
        }
        $content_html .= '</' . $tag . '>';
        $content_html .= '<p class="lk-icon-box-description"' . $inline_desc_attr . '>' . esc_html( $desc_text ) . '</p>';
        $content_html .= '</div>';

        $wrapper_classes = 'lk-icon-box-wrapper lk-icon-box-position-' . esc_attr( $pos ) . ' lk-position-' . esc_attr( $pos ) . ' lk-align-' . esc_attr( $align );
        if ( 'top' !== $pos ) {
            $wrapper_classes .= ' lk-valign-' . esc_attr( $v_align );
        }

        $html = '<div class="' . esc_attr( $wrapper_classes ) . '">';
        $html .= $icon_html;
        $html .= $content_html;
        $html .= '</div>';

        return $html;
    }
}
