<?php
/**
 * Loopkit Search Form Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Search_Form extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'search-form';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Search Form', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-search';
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
        return array( 'search', 'form', 'find', 'query', 'input', 'header', 'site' );
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
        $default_accent_color = ! $this->is_default_colors_disabled() ? 'globals/colors?id=accent' : '#38A8F5';

        return array(
            'content' => array(
                array(
                    'id'       => 'section_search_content',
                    'title'    => __( 'Search Form', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'placeholder',
                            'label'   => __( 'Placeholder', 'loopkit' ),
                            'type'    => 'text',
                            'default' => esc_html__( 'Search...', 'loopkit' ),
                        ),
                        array(
                            'name'    => 'button_type',
                            'label'   => __( 'Button Type', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'icon',
                            'options' => array(
                                'icon'      => esc_html__( 'Icon only', 'loopkit' ),
                                'text'      => esc_html__( 'Text only', 'loopkit' ),
                                'icon_text' => esc_html__( 'Icon + Text', 'loopkit' ),
                                'none'      => esc_html__( 'None', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'      => 'button_text',
                            'label'     => __( 'Button Text', 'loopkit' ),
                            'type'      => 'text',
                            'default'   => esc_html__( 'Search', 'loopkit' ),
                            'condition' => array(
                                'button_type' => array( 'text', 'icon_text' ),
                            ),
                        ),
                        array(
                            'name'      => 'icon',
                            'label'     => __( 'Icon', 'loopkit' ),
                            'type'      => 'text',
                            'default'   => 'dashicons-search',
                            'condition' => array(
                                'button_type' => array( 'icon', 'icon_text' ),
                            ),
                        ),
                        array(
                            'name'       => 'layout',
                            'label'      => __( 'Layout', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'default'    => 'row',
                            'options'    => array(
                                'row'    => array(
                                    'title' => __( 'Side by side', 'loopkit' ),
                                    'icon'  => 'dashicons-leftright',
                                ),
                                'column' => array(
                                    'title' => __( 'Stacked', 'loopkit' ),
                                    'icon'  => 'dashicons-arrow-down-alt2',
                                ),
                            ),
                        ),
                        array(
                            'name'       => 'align',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'default'    => 'left',
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
                    'id'       => 'section_input_style',
                    'title'    => __( 'Input', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'  => 'input_typography',
                            'label' => __( 'Typography', 'loopkit' ),
                            'type'  => 'typography',
                        ),
                        array(
                            'name'  => 'input_text_color',
                            'label' => __( 'Text Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'input_placeholder_color',
                            'label' => __( 'Placeholder Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'input_bg_color',
                            'label' => __( 'Background Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'input_border',
                            'label' => __( 'Border', 'loopkit' ),
                            'type'  => 'border',
                        ),
                        array(
                            'name'       => 'input_border_radius',
                            'label'      => __( 'Border Radius', 'loopkit' ),
                            'type'       => 'dimensions',
                            'responsive' => true,
                            'units'      => array( 'px', '%', 'em', 'rem' ),
                        ),
                        array(
                            'name'       => 'input_padding',
                            'label'      => __( 'Padding', 'loopkit' ),
                            'type'       => 'dimensions',
                            'responsive' => true,
                            'units'      => array( 'px', 'em', 'rem' ),
                        ),
                        array(
                            'name'  => 'input_box_shadow',
                            'label' => __( 'Box Shadow', 'loopkit' ),
                            'type'  => 'box_shadow',
                        ),
                    ),
                ),
                array(
                    'id'       => 'section_button_style',
                    'title'    => __( 'Button', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'  => 'button_typography',
                            'label' => __( 'Typography', 'loopkit' ),
                            'type'  => 'typography',
                        ),
                        array(
                            'name'  => 'button_text_color',
                            'label' => __( 'Text Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'button_text_color_hover',
                            'label' => __( 'Hover Text Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'    => 'button_bg_color',
                            'label'   => __( 'Background Color', 'loopkit' ),
                            'type'    => 'color',
                            'default' => $default_accent_color,
                        ),
                        array(
                            'name'  => 'button_bg_color_hover',
                            'label' => __( 'Hover Background Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'button_border',
                            'label' => __( 'Border', 'loopkit' ),
                            'type'  => 'border',
                        ),
                        array(
                            'name'       => 'button_border_radius',
                            'label'      => __( 'Border Radius', 'loopkit' ),
                            'type'       => 'dimensions',
                            'responsive' => true,
                            'units'      => array( 'px', '%', 'em', 'rem' ),
                        ),
                        array(
                            'name'       => 'button_padding',
                            'label'      => __( 'Padding', 'loopkit' ),
                            'type'       => 'dimensions',
                            'responsive' => true,
                            'units'      => array( 'px', 'em', 'rem' ),
                        ),
                        array(
                            'name'       => 'button_icon_size',
                            'label'      => __( 'Icon Size', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'px' => array(
                                    'min'  => 10,
                                    'max'  => 100,
                                    'step' => 1,
                                ),
                            ),
                        ),
                        array(
                            'name'  => 'button_box_shadow',
                            'label' => __( 'Box Shadow', 'loopkit' ),
                            'type'  => 'box_shadow',
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
        $placeholder = ! empty( $settings['placeholder'] ) ? $settings['placeholder'] : esc_html__( 'Search...', 'loopkit' );
        $button_type = ! empty( $settings['button_type'] ) ? $settings['button_type'] : 'icon';
        $button_text = ! empty( $settings['button_text'] ) ? $settings['button_text'] : esc_html__( 'Search', 'loopkit' );
        $icon        = ! empty( $settings['icon'] ) ? $settings['icon'] : 'dashicons-search';

        $form_classes = array( 'lk-search-form' );
        if ( ! empty( $settings['layout'] ) && 'column' === $settings['layout'] ) {
            $form_classes[] = 'lk-search-form--stacked';
        }

        $onsubmit = $editor_mode ? ' onsubmit="return false;"' : '';

        $html  = '<form class="' . esc_attr( implode( ' ', $form_classes ) ) . '" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '"' . $onsubmit . '>';
        $html .= '<div class="lk-search-form__input-wrapper">';
        $html .= '<input type="search" class="lk-search-form__input" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( get_search_query() ) . '" name="s" aria-label="' . esc_attr( $placeholder ) . '" />';
        $html .= '</div>';

        if ( 'none' !== $button_type ) {
            $html .= '<button type="submit" class="lk-search-form__submit lk-search-form__submit--' . esc_attr( $button_type ) . '" aria-label="' . esc_attr( $button_text ) . '">';
            if ( 'icon' === $button_type || 'icon_text' === $button_type ) {
                $html .= '<span class="lk-search-form__icon dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
            }
            if ( 'text' === $button_type || 'icon_text' === $button_type ) {
                $html .= '<span class="lk-search-form__text">' . esc_html( $button_text ) . '</span>';
            }
            $html .= '</button>';
        }

        $html .= '</form>';

        return $html;
    }
}
