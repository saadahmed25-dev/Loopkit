<?php
/**
 * Loopkit Icon List Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Icon_List extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'icon-list';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Icon List', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-list-view';
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
        return array( 'icon list', 'bullet', 'items', 'check', 'checklist', 'ordered', 'unordered' );
    }

    /**
     * Register controls.
     *
     * @return array
     */
    protected function register_controls() {
        $default_primary   = ! $this->is_default_colors_disabled() ? 'globals/colors?id=primary' : '#38A8F5';
        $default_text      = ! $this->is_default_colors_disabled() ? 'globals/colors?id=text' : '#333333';
        $default_text_typo = ! $this->is_default_fonts_disabled() ? 'globals/typography?id=text' : '';

        return array(
            'content' => array(
                array(
                    'id'       => 'section_icon_list',
                    'title'    => __( 'Icon List', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'layout',
                            'label'   => __( 'Layout', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'default',
                            'options' => array(
                                'default' => esc_html__( 'Default', 'loopkit' ),
                                'inline'  => esc_html__( 'Inline', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'       => 'icon_list',
                            'label'      => __( 'Items', 'loopkit' ),
                            'type'       => 'repeater',
                            'title_field' => 'text',
                            'default'    => array(
                                array(
                                    '_id'           => 'item1',
                                    'text'          => esc_html__( 'List Item #1', 'loopkit' ),
                                    'selected_icon' => array(
                                        'library' => 'fa-solid',
                                        'value'   => 'check',
                                    ),
                                    'link'          => array( 'url' => '' ),
                                ),
                                array(
                                    '_id'           => 'item2',
                                    'text'          => esc_html__( 'List Item #2', 'loopkit' ),
                                    'selected_icon' => array(
                                        'library' => 'fa-solid',
                                        'value'   => 'check',
                                    ),
                                    'link'          => array( 'url' => '' ),
                                ),
                                array(
                                    '_id'           => 'item3',
                                    'text'          => esc_html__( 'List Item #3', 'loopkit' ),
                                    'selected_icon' => array(
                                        'library' => 'fa-solid',
                                        'value'   => 'check',
                                    ),
                                    'link'          => array( 'url' => '' ),
                                ),
                            ),
                            'fields'     => array(
                                array(
                                    'name'    => 'text',
                                    'label'   => __( 'Text', 'loopkit' ),
                                    'type'    => 'text',
                                    'default' => esc_html__( 'List Item', 'loopkit' ),
                                ),
                                array(
                                    'name'    => 'selected_icon',
                                    'label'   => __( 'Icon', 'loopkit' ),
                                    'type'    => 'icon',
                                    'default' => array(
                                        'library' => 'fa-solid',
                                        'value'   => 'check',
                                    ),
                                ),
                                array(
                                    'name'    => 'link',
                                    'label'   => __( 'Link', 'loopkit' ),
                                    'type'    => 'url',
                                    'default' => array( 'url' => '' ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_list',
                    'title'    => __( 'List', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'       => 'space_between',
                            'label'      => __( 'Space Between', 'loopkit' ),
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
                            'name'       => 'alignment',
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
                        array(
                            'name'    => 'divider',
                            'label'   => __( 'Divider', 'loopkit' ),
                            'type'    => 'switcher',
                            'default' => 'no',
                        ),
                        array(
                            'name'      => 'divider_style',
                            'label'     => __( 'Style', 'loopkit' ),
                            'type'      => 'select',
                            'default'   => 'solid',
                            'condition' => array(
                                'divider' => 'yes',
                            ),
                            'options'   => array(
                                'solid'  => esc_html__( 'Solid', 'loopkit' ),
                                'double' => esc_html__( 'Double', 'loopkit' ),
                                'dotted' => esc_html__( 'Dotted', 'loopkit' ),
                                'dashed' => esc_html__( 'Dashed', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'      => 'divider_color',
                            'label'     => __( 'Colour', 'loopkit' ),
                            'type'      => 'color',
                            'default'   => '#dddddd',
                            'condition' => array(
                                'divider' => 'yes',
                            ),
                        ),
                    ),
                ),
                array(
                    'id'       => 'section_style_icon',
                    'title'    => __( 'Icon', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'    => 'icon_color',
                            'label'   => __( 'Colour', 'loopkit' ),
                            'type'    => 'color',
                            'default' => $default_primary,
                        ),
                        array(
                            'name'  => 'icon_hover_color',
                            'label' => __( 'Hover Colour', 'loopkit' ),
                            'type'  => 'color',
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
                    ),
                ),
                array(
                    'id'       => 'section_style_text',
                    'title'    => __( 'Text', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'    => 'text_color',
                            'label'   => __( 'Text Colour', 'loopkit' ),
                            'type'    => 'color',
                            'default' => $default_text,
                        ),
                        array(
                            'name'  => 'text_hover_color',
                            'label' => __( 'Hover Colour', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'       => 'text_indent',
                            'label'      => __( 'Text Indent', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 0,
                                'max' => 50,
                            ),
                            'default'    => array(
                                'size' => 8,
                                'unit' => 'px',
                            ),
                        ),
                        array(
                            'name'    => 'text_typography',
                            'label'   => __( 'Typography', 'loopkit' ),
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
        $layout  = $settings['layout'] ?? 'default';
        $align   = $settings['alignment'] ?? 'left';
        $divider = ( $settings['divider'] ?? 'no' ) === 'yes';

        $items = $settings['icon_list'] ?? array(
            array( 'text' => 'List Item #1', 'selected_icon' => array( 'library' => 'fa-solid', 'value' => 'check' ) ),
            array( 'text' => 'List Item #2', 'selected_icon' => array( 'library' => 'fa-solid', 'value' => 'check' ) ),
            array( 'text' => 'List Item #3', 'selected_icon' => array( 'library' => 'fa-solid', 'value' => 'check' ) ),
        );

        $list_classes = 'lk-icon-list-items lk-align-' . esc_attr( $align );
        if ( 'inline' === $layout ) {
            $list_classes .= ' lk-inline-items';
        }
        if ( $divider ) {
            $list_classes .= ' lk-has-divider';
        }

        $html = '<ul class="' . esc_attr( $list_classes ) . '">';
        foreach ( $items as $idx => $item ) {
            $text = $item['text'] ?? '';
            $icon = $item['selected_icon'] ?? array();
            $link = $item['link'] ?? array();

            // Rows are commonly filled from dynamic tags, so a row whose value resolves to nothing
            // (an unfilled custom field, a blank repeater row) would otherwise render as a bare
            // icon with no text. Skip it here, before the markup is built, so the :last-child
            // divider rules below still see a correctly trimmed list.
            if ( '' === trim( wp_strip_all_tags( (string) $text ) ) ) {
                continue;
            }

            $icon_svg = '';
            if ( ! empty( $icon ) && class_exists( 'Loopkit_Icons' ) ) {
                $icon_svg = Loopkit_Icons::render( $icon );
            }
            if ( empty( $icon_svg ) ) {
                $icon_svg = '<svg aria-hidden="true" class="lk-svg-icon" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.205 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.206-.001z"></path></svg>';
            }

            $url = is_array( $link ) ? ( $link['url'] ?? '' ) : ( is_string( $link ) ? $link : '' );

            $item_content = '<span class="lk-icon-list-icon">' . $icon_svg . '</span>';
            $item_content .= '<span class="lk-icon-list-text">' . esc_html( $text ) . '</span>';

            $html .= '<li class="lk-icon-list-item">';
            if ( ! empty( $url ) ) {
                $target = ( is_array( $link ) && ! empty( $link['is_external'] ) ) ? ' target="_blank" rel="noopener noreferrer"' : '';
                $html .= '<a href="' . esc_url( $url ) . '"' . $target . '>' . $item_content . '</a>';
            } else {
                $html .= $item_content;
            }
            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
