<?php
/**
 * Loopkit Button Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Button extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'button';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Button', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-button';
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
        return array( 'button', 'link', 'cta', 'call to action' );
    }

    /**
     * Register controls.
     *
     * @return array
     */
    protected function register_controls() {
        $default_accent_color = ! $this->is_default_colors_disabled() ? 'globals/colors?id=accent' : '#38A8F5';
        $default_accent_typo  = ! $this->is_default_fonts_disabled() ? 'globals/typography?id=accent' : '';

        return array(
            'content' => array(
                array(
                    'id'       => 'section_button',
                    'title'    => __( 'Button', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'text',
                            'label'   => __( 'Text', 'loopkit' ),
                            'type'    => 'text',
                            'default' => esc_html__( 'Click here', 'loopkit' ),
                        ),
                        array(
                            'name'    => 'link',
                            'label'   => __( 'Link', 'loopkit' ),
                            'type'    => 'url',
                            'default' => array(
                                'url' => '#',
                            ),
                        ),
                        array(
                            'name'       => 'align',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
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
                            'name'    => 'button_size',
                            'label'   => __( 'Size', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'sm',
                            'options' => array(
                                'xs' => esc_html__( 'Extra Small', 'loopkit' ),
                                'sm' => esc_html__( 'Small', 'loopkit' ),
                                'md' => esc_html__( 'Medium', 'loopkit' ),
                                'lg' => esc_html__( 'Large', 'loopkit' ),
                                'xl' => esc_html__( 'Extra Large', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'    => 'selected_icon',
                            'label'   => __( 'Icon', 'loopkit' ),
                            'type'    => 'icon',
                            'default' => array(),
                        ),
                        array(
                            'name'      => 'icon_align',
                            'label'     => __( 'Icon Position', 'loopkit' ),
                            'type'      => 'select',
                            'default'   => 'left',
                            'options'   => array(
                                'left'  => esc_html__( 'Before', 'loopkit' ),
                                'right' => esc_html__( 'After', 'loopkit' ),
                            ),
                            'condition' => array(
                                'selected_icon[value]!' => '',
                            ),
                        ),
                        array(
                            'name'       => 'icon_indent',
                            'label'      => __( 'Icon Spacing', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min'  => 0,
                                'max'  => 50,
                                'step' => 1,
                            ),
                            'default'    => array(
                                'size' => 8,
                                'unit' => 'px',
                            ),
                            'condition'  => array(
                                'selected_icon[value]!' => '',
                            ),
                        ),
                        array(
                            'name'    => 'button_css_id',
                            'label'   => __( 'Button ID', 'loopkit' ),
                            'type'    => 'text',
                            'default' => '',
                        ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_style_button',
                    'title'    => __( 'Button', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'    => 'typography',
                            'label'   => __( 'Typography', 'loopkit' ),
                            'type'    => 'typography',
                            'default' => $default_accent_typo,
                        ),
                        array(
                            'name'        => 'text_shadow',
                            'label'       => __( 'Text Shadow', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'text_shadow',
                        ),
                        // Normal tab controls
                        array(
                            'name'    => 'button_text_color',
                            'label'   => __( 'Text Colour', 'loopkit' ),
                            'type'    => 'color',
                            'default' => '#FFFFFF',
                        ),
                        array(
                            'name'    => 'button_background_color',
                            'label'   => __( 'Background Colour', 'loopkit' ),
                            'type'    => 'color',
                            'default' => $default_accent_color,
                        ),
                        array(
                            'name'    => 'button_background_type',
                            'label'   => __( 'Background Type', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'classic',
                            'options' => array(
                                'classic'  => esc_html__( 'Classic', 'loopkit' ),
                                'gradient' => esc_html__( 'Gradient', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'      => 'button_background_gradient_color',
                            'label'     => __( 'First Colour', 'loopkit' ),
                            'type'      => 'color',
                            'condition' => array(
                                'button_background_type' => 'gradient',
                            ),
                        ),
                        array(
                            'name'      => 'button_background_gradient_second_color',
                            'label'     => __( 'Second Colour', 'loopkit' ),
                            'type'      => 'color',
                            'condition' => array(
                                'button_background_type' => 'gradient',
                            ),
                        ),
                        array(
                            'name'      => 'button_background_gradient_type',
                            'label'     => __( 'Gradient Type', 'loopkit' ),
                            'type'      => 'select',
                            'default'   => 'linear',
                            'options'   => array(
                                'linear' => esc_html__( 'Linear', 'loopkit' ),
                                'radial' => esc_html__( 'Radial', 'loopkit' ),
                            ),
                            'condition' => array(
                                'button_background_type' => 'gradient',
                            ),
                        ),
                        array(
                            'name'      => 'button_background_gradient_angle',
                            'label'     => __( 'Gradient Angle', 'loopkit' ),
                            'type'      => 'slider',
                            'range'     => array(
                                'min' => 0,
                                'max' => 360,
                            ),
                            'default'   => 180,
                            'condition' => array(
                                'button_background_type'          => 'gradient',
                                'background_gradient_type' => 'linear',
                            ),
                        ),
                        // Hover tab controls
                        array(
                            'name'  => 'button_hover_text_color',
                            'label' => __( 'Text Colour (Hover)', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'button_background_hover_color',
                            'label' => __( 'Background Colour (Hover)', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'button_hover_border_color',
                            'label' => __( 'Border Colour (Hover)', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'    => 'hover_transition_duration',
                            'label'   => __( 'Transition Duration', 'loopkit' ),
                            'type'    => 'slider',
                            'range'   => array(
                                'min'  => 0,
                                'max'  => 3,
                                'step' => 0.1,
                            ),
                            'default' => array(
                                'size' => 0.3,
                                'unit' => 's',
                            ),
                        ),
                        array(
                            'name'    => 'hover_animation',
                            'label'   => __( 'Hover Animation', 'loopkit' ),
                            'type'    => 'hover_animation',
                            'default' => '',
                        ),
                        // Border & Box Shadow
                        array(
                            'name'    => 'button_border_type',
                            'label'   => __( 'Border Type', 'loopkit' ),
                            'type'    => 'select',
                            'default' => '',
                            'options' => array(
                                ''       => esc_html__( 'Default', 'loopkit' ),
                                'none'   => esc_html__( 'None', 'loopkit' ),
                                'solid'  => esc_html__( 'Solid', 'loopkit' ),
                                'double' => esc_html__( 'Double', 'loopkit' ),
                                'dotted' => esc_html__( 'Dotted', 'loopkit' ),
                                'dashed' => esc_html__( 'Dashed', 'loopkit' ),
                                'groove' => esc_html__( 'Groove', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'       => 'button_border_width',
                            'label'      => __( 'Border Width', 'loopkit' ),
                            'type'       => 'dimensions',
                            'responsive' => true,
                            'condition'  => array(
                                'border_type!' => array( '', 'none' ),
                            ),
                        ),
                        array(
                            'name'      => 'button_border_color',
                            'label'     => __( 'Border Colour', 'loopkit' ),
                            'type'      => 'color',
                            'condition' => array(
                                'border_type!' => array( '', 'none' ),
                            ),
                        ),
                        array(
                            'name'       => 'button_border_radius',
                            'label'      => __( 'Border Radius', 'loopkit' ),
                            'type'       => 'dimensions',
                            'responsive' => true,
                        ),
                        array(
                            'name'        => 'button_box_shadow',
                            'label'       => __( 'Box Shadow', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'box_shadow',
                        ),
                        array(
                            'name'       => 'button_padding',
                            'label'      => __( 'Padding', 'loopkit' ),
                            'type'       => 'dimensions',
                            'responsive' => true,
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
        $text = $settings['text'] ?? esc_html__( 'Click here', 'loopkit' );

        $link_obj = $settings['link'] ?? array();
        $url      = '#';
        $attrs    = '';

        if ( is_array( $link_obj ) ) {
            $url = ! empty( $link_obj['url'] ) ? $link_obj['url'] : '#';
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

        $size       = ( $settings['button_size'] ?? $settings['size'] ?? null ) ?? 'sm';
        $align      = $settings['align'] ?? 'left';
        $hover_anim = $settings['hover_animation'] ?? '';
        $css_id     = $settings['button_css_id'] ?? '';

        $btn_classes = 'lk-button lk-size-' . esc_attr( $size );
        if ( ! empty( $hover_anim ) ) {
            $btn_classes .= ' lk-animation-' . esc_attr( $hover_anim );
        }

        $id_attr = ! empty( $css_id ) ? ' id="' . esc_attr( $css_id ) . '"' : '';

        // Icon rendering
        $icon_val = $settings['selected_icon'] ?? array();
        $has_icon = ! empty( $icon_val ) && ( ( is_array( $icon_val ) && ! empty( $icon_val['value'] ) ) || ( is_string( $icon_val ) && ! empty( $icon_val ) ) );
        $icon_align = $settings['icon_align'] ?? 'left';

        $icon_html = '';
        if ( $has_icon && class_exists( 'Loopkit_Icons' ) ) {
            $rendered_icon = Loopkit_Icons::render( $icon_val );
            if ( ! empty( $rendered_icon ) ) {
                $icon_html = '<span class="lk-button-icon lk-align-icon-' . esc_attr( $icon_align ) . '">' . $rendered_icon . '</span>';
            }
        }

        $inline_attr = $editor_mode ? ' data-lk-inline-edit="text"' : '';

        $content_inner = '';
        if ( 'left' === $icon_align && ! empty( $icon_html ) ) {
            $content_inner .= $icon_html;
        }
        $content_inner .= '<span class="lk-button-text"' . $inline_attr . '>' . esc_html( $text ) . '</span>';
        if ( 'right' === $icon_align && ! empty( $icon_html ) ) {
            $content_inner .= $icon_html;
        }

        $html = '<div class="lk-button-wrapper lk-align-' . esc_attr( $align ) . '">';
        $html .= '<a class="' . esc_attr( $btn_classes ) . '" href="' . esc_url( $url ) . '" role="button"' . $id_attr . $attrs . '>';
        $html .= '<span class="lk-button-content-wrapper">';
        $html .= $content_inner;
        $html .= '</span>';
        $html .= '</a>';
        $html .= '</div>';

        return $html;
    }
}
