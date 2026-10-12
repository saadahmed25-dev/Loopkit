<?php
/**
 * Loopkit Divider Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Divider extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'divider';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Divider', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-minus';
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
        return array( 'divider', 'line', 'separator', 'rule', 'hr', 'border', 'break' );
    }

    /**
     * Register controls.
     *
     * @return array
     */
    protected function register_controls() {
        $default_secondary = ! $this->is_default_colors_disabled() ? 'globals/colors?id=secondary' : '#54595F';

        return array(
            'content' => array(
                array(
                    'id'       => 'section_divider',
                    'title'    => __( 'Divider', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'style',
                            'label'   => __( 'Style', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'solid',
                            'options' => array(
                                'solid'   => esc_html__( 'Solid', 'loopkit' ),
                                'double'  => esc_html__( 'Double', 'loopkit' ),
                                'dotted'  => esc_html__( 'Dotted', 'loopkit' ),
                                'dashed'  => esc_html__( 'Dashed', 'loopkit' ),
                                'curly'   => esc_html__( 'Curly', 'loopkit' ),
                                'curved'  => esc_html__( 'Curved', 'loopkit' ),
                                'slashes' => esc_html__( 'Slashes', 'loopkit' ),
                                'squared' => esc_html__( 'Squared', 'loopkit' ),
                                'wavy'    => esc_html__( 'Wavy', 'loopkit' ),
                                'zigzag'  => esc_html__( 'Zigzag', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'       => 'width',
                            'label'      => __( 'Width', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 1,
                                'max' => 100,
                            ),
                            'units'      => array( '%', 'px', 'vw' ),
                            'default'    => array(
                                'size' => 100,
                                'unit' => '%',
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
                        array(
                            'name'    => 'add_element',
                            'label'   => __( 'Add Element', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'none',
                            'options' => array(
                                'none' => esc_html__( 'None', 'loopkit' ),
                                'text' => esc_html__( 'Text', 'loopkit' ),
                                'icon' => esc_html__( 'Icon', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'      => 'text',
                            'label'     => __( 'Text', 'loopkit' ),
                            'type'      => 'text',
                            'default'   => esc_html__( 'Divider', 'loopkit' ),
                            'condition' => array(
                                'add_element' => 'text',
                            ),
                        ),
                        array(
                            'name'      => 'html_tag',
                            'label'     => __( 'HTML Tag', 'loopkit' ),
                            'type'      => 'select',
                            'default'   => 'span',
                            'options'   => array(
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
                            'condition' => array(
                                'add_element' => 'text',
                            ),
                        ),
                        array(
                            'name'      => 'selected_icon',
                            'label'     => __( 'Icon', 'loopkit' ),
                            'type'      => 'icon',
                            'default'   => array(
                                'library' => 'fa-solid',
                                'value'   => 'star',
                            ),
                            'condition' => array(
                                'add_element' => 'icon',
                            ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_divider',
                    'title'    => __( 'Divider', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'    => 'color',
                            'label'   => __( 'Colour', 'loopkit' ),
                            'type'    => 'color',
                            'default' => $default_secondary,
                        ),
                        array(
                            'name'    => 'weight',
                            'label'   => __( 'Weight', 'loopkit' ),
                            'type'    => 'slider',
                            'range'   => array(
                                'min'  => 1,
                                'max'  => 20,
                                'step' => 1,
                            ),
                            'default' => array(
                                'size' => 1,
                                'unit' => 'px',
                            ),
                        ),
                        array(
                            'name'       => 'gap',
                            'label'      => __( 'Gap', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 1,
                                'max' => 50,
                            ),
                            'default'    => array(
                                'size' => 15,
                                'unit' => 'px',
                            ),
                        ),
                    ),
                ),
                array(
                    'id'        => 'section_style_text',
                    'title'     => __( 'Text', 'loopkit' ),
                    'tab'       => 'style',
                    'condition' => array(
                        'add_element' => 'text',
                    ),
                    'controls'  => array(
                        array(
                            'name'  => 'text_color',
                            'label' => __( 'Colour', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'text_typography',
                            'label' => __( 'Typography', 'loopkit' ),
                            'type'  => 'typography',
                        ),
                        array(
                            'name'    => 'text_position',
                            'label'   => __( 'Position', 'loopkit' ),
                            'type'    => 'choose',
                            'default' => 'center',
                            'options' => array(
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
                        array(
                            'name'       => 'text_spacing',
                            'label'      => __( 'Spacing', 'loopkit' ),
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
                    ),
                ),
                array(
                    'id'        => 'section_style_icon',
                    'title'     => __( 'Icon', 'loopkit' ),
                    'tab'       => 'style',
                    'condition' => array(
                        'add_element' => 'icon',
                    ),
                    'controls'  => array(
                        array(
                            'name'    => 'icon_view',
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
                            'name'  => 'icon_primary_color',
                            'label' => __( 'Primary Colour', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'      => 'icon_secondary_color',
                            'label'     => __( 'Secondary Colour', 'loopkit' ),
                            'type'      => 'color',
                            'condition' => array(
                                'icon_view!' => 'default',
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
                                'size' => 14,
                                'unit' => 'px',
                            ),
                        ),
                        array(
                            'name'    => 'icon_position',
                            'label'   => __( 'Position', 'loopkit' ),
                            'type'    => 'choose',
                            'default' => 'center',
                            'options' => array(
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
                        array(
                            'name'       => 'icon_spacing',
                            'label'      => __( 'Spacing', 'loopkit' ),
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
                    ),
                ),
            ),
        );
    }

    /**
     * Generate inline SVG pattern for custom divider styles.
     *
     * @param string $style Pattern style name.
     * @param string $color Stroke/fill color.
     * @param int    $weight Line weight.
     * @return string SVG markup.
     */
    public static function get_divider_pattern_svg( $style, $color = '#54595F', $weight = 1 ) {
        $weight = max( 1, intval( $weight ) );
        $color = esc_attr( $color );

        switch ( $style ) {
            case 'curly':
                return '<svg viewBox="0 0 20 10" preserveAspectRatio="none" style="width:100%;height:10px;stroke:' . $color . ';fill:none;stroke-width:' . $weight . ';"><path d="M0,5 Q5,0 10,5 T20,5"></path></svg>';
            case 'curved':
                return '<svg viewBox="0 0 20 8" preserveAspectRatio="none" style="width:100%;height:8px;stroke:' . $color . ';fill:none;stroke-width:' . $weight . ';"><path d="M0,8 C5,0 15,0 20,8"></path></svg>';
            case 'slashes':
                return '<svg viewBox="0 0 20 10" preserveAspectRatio="none" style="width:100%;height:10px;stroke:' . $color . ';stroke-width:' . $weight . ';"><line x1="0" y1="10" x2="10" y2="0"></line><line x1="10" y1="10" x2="20" y2="0"></line></svg>';
            case 'squared':
                return '<svg viewBox="0 0 20 10" preserveAspectRatio="none" style="width:100%;height:10px;stroke:' . $color . ';fill:none;stroke-width:' . $weight . ';"><polyline points="0,10 0,2 10,2 10,10 20,10 20,2"></polyline></svg>';
            case 'wavy':
                return '<svg viewBox="0 0 20 10" preserveAspectRatio="none" style="width:100%;height:10px;stroke:' . $color . ';fill:none;stroke-width:' . $weight . ';"><path d="M0,5 Q5,0 10,5 T20,5"></path></svg>';
            case 'zigzag':
                return '<svg viewBox="0 0 20 10" preserveAspectRatio="none" style="width:100%;height:10px;stroke:' . $color . ';fill:none;stroke-width:' . $weight . ';"><polyline points="0,8 5,2 10,8 15,2 20,8"></polyline></svg>';
            default:
                return '';
        }
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
        $style       = $settings['style'] ?? 'solid';
        $align       = $settings['align'] ?? 'center';
        $add_element = $settings['add_element'] ?? 'none';
        $color       = $settings['color'] ?? '#54595F';
        $weight      = $settings['weight']['size'] ?? ( is_numeric( $settings['weight'] ?? null ) ? $settings['weight'] : 1 );

        $is_pattern = in_array( $style, array( 'curly', 'curved', 'slashes', 'squared', 'wavy', 'zigzag' ), true );

        $sep_html = '';
        if ( $is_pattern ) {
            $pattern_svg = self::get_divider_pattern_svg( $style, $color, $weight );
            $sep_html = '<span class="lk-divider-separator lk-divider-separator--pattern">' . $pattern_svg . '</span>';
        } else {
            $sep_html = '<span class="lk-divider-separator lk-divider-separator--' . esc_attr( $style ) . '"></span>';
        }

        $content_html = '';
        if ( 'text' === $add_element ) {
            $text = $settings['text'] ?? esc_html__( 'Divider', 'loopkit' );
            $tag  = sanitize_key( $settings['html_tag'] ?? 'span' );
            if ( ! in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), true ) ) {
                $tag = 'span';
            }
            $inline_attr = $editor_mode ? ' data-lk-inline-edit="text"' : '';
            $content_html = '<' . $tag . ' class="lk-divider__text"' . $inline_attr . '>' . esc_html( $text ) . '</' . $tag . '>';
        } elseif ( 'icon' === $add_element ) {
            $icon_val  = $settings['selected_icon'] ?? array( 'library' => 'fa-solid', 'value' => 'star' );
            $icon_view = $settings['icon_view'] ?? 'default';
            $icon_svg  = class_exists( 'Loopkit_Icons' ) ? Loopkit_Icons::render( $icon_val ) : '';
            if ( empty( $icon_svg ) ) {
                $icon_svg = '<svg aria-hidden="true" class="lk-svg-icon" viewBox="0 0 576 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M259.3 17.8L194 150.2 47.9 171.5c-26.2 3.8-36.7 36.1-17.7 54.6l105.7 103-25 145.5c-4.5 26.3 23.2 46 46.4 33.7L288 439.6l130.7 68.7c23.2 12.2 50.9-7.4 46.4-33.7l-25-145.5 105.7-103c19-18.5 8.5-50.8-17.7-54.6L382 150.2 316.7 17.8c-11.7-23.6-45.6-23.9-57.4 0z"></path></svg>';
            }
            $content_html = '<span class="lk-divider__icon lk-icon-view-' . esc_attr( $icon_view ) . '">' . $icon_svg . '</span>';
        }

        $pos = ( 'text' === $add_element ) ? ( $settings['text_position'] ?? 'center' ) : ( $settings['icon_position'] ?? 'center' );

        $classes = 'lk-divider lk-align-' . esc_attr( $align );
        if ( 'none' !== $add_element ) {
            $classes .= ' lk-divider--has-element lk-divider--pos-' . esc_attr( $pos );
        }

        $html = '<div class="' . esc_attr( $classes ) . '">';
        if ( 'none' === $add_element ) {
            $html .= $sep_html;
        } elseif ( 'left' === $pos ) {
            $html .= '<span class="lk-divider__content">' . $content_html . '</span>';
            $html .= $sep_html;
        } elseif ( 'right' === $pos ) {
            $html .= $sep_html;
            $html .= '<span class="lk-divider__content">' . $content_html . '</span>';
        } else {
            // Center
            $html .= $sep_html;
            $html .= '<span class="lk-divider__content">' . $content_html . '</span>';
            $html .= $sep_html;
        }
        $html .= '</div>';

        return $html;
    }
}
