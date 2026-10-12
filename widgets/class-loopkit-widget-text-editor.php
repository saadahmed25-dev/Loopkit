<?php
/**
 * Loopkit Text Editor Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Text_Editor extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'text-editor';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Text Editor', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-editor-paragraph';
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
        return array( 'text', 'editor', 'paragraph', 'content', 'wysiwyg', 'typography' );
    }

    /**
     * Register controls.
     *
     * @return array
     */
    protected function register_controls() {
        $default_color = ! $this->is_default_colors_disabled() ? 'globals/colors?id=text' : '';
        $default_typo  = ! $this->is_default_fonts_disabled() ? 'globals/typography?id=text' : '';

        return array(
            'content' => array(
                array(
                    'id'       => 'section_editor',
                    'title'    => __( 'Text Editor', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'editor',
                            'label'   => __( 'Text', 'loopkit' ),
                            'type'    => 'wysiwyg',
                            'default' => '<p>' . esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.', 'loopkit' ) . '</p>',
                        ),
                        array(
                            'name'         => 'drop_cap',
                            'label'        => __( 'Drop Cap', 'loopkit' ),
                            'type'         => 'switcher',
                            'default'      => '',
                            'return_value' => 'yes',
                        ),
                        array(
                            'name'       => 'columns',
                            'label'      => __( 'Columns', 'loopkit' ),
                            'type'       => 'select',
                            'responsive' => true,
                            'default'    => '1',
                            'options'    => array(
                                '1'  => '1',
                                '2'  => '2',
                                '3'  => '3',
                                '4'  => '4',
                                '5'  => '5',
                                '6'  => '6',
                                '7'  => '7',
                                '8'  => '8',
                                '9'  => '9',
                                '10' => '10',
                            ),
                        ),
                        array(
                            'name'       => 'columns_gap',
                            'label'      => __( 'Columns Gap', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'condition'  => array(
                                'columns!' => '1',
                            ),
                            'range'      => array(
                                'min'  => 0,
                                'max'  => 100,
                                'step' => 1,
                            ),
                            'default'    => array(
                                'size' => 30,
                                'unit' => 'px',
                            ),
                        ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_style_text',
                    'title'    => __( 'Text Editor', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
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
                            'name'    => 'text_color',
                            'label'   => __( 'Text Colour', 'loopkit' ),
                            'type'    => 'color',
                            'default' => $default_color,
                        ),
                        array(
                            'name'    => 'typography',
                            'label'   => __( 'Typography', 'loopkit' ),
                            'type'    => 'typography',
                            'default' => $default_typo,
                        ),
                        array(
                            'name'        => 'text_shadow',
                            'label'       => __( 'Text Shadow', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'text_shadow',
                        ),
                        array(
                            'name'       => 'space',
                            'label'      => __( 'Paragraph Spacing', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min'  => 0,
                                'max'  => 100,
                                'step' => 1,
                            ),
                            'units'      => array( 'px', 'em', 'rem' ),
                        ),
                        array(
                            'name'    => 'link_color',
                            'label'   => __( 'Link Colour', 'loopkit' ),
                            'type'    => 'color',
                        ),
                        array(
                            'name'    => 'link_hover_color',
                            'label'   => __( 'Link Hover Colour', 'loopkit' ),
                            'type'    => 'color',
                        ),
                    ),
                ),
                array(
                    'id'        => 'section_drop_cap',
                    'title'     => __( 'Drop Cap', 'loopkit' ),
                    'tab'       => 'style',
                    'condition' => array(
                        'drop_cap' => 'yes',
                    ),
                    'controls'  => array(
                        array(
                            'name'    => 'drop_cap_view',
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
                            'name'  => 'drop_cap_primary_color',
                            'label' => __( 'Primary Colour', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'      => 'drop_cap_secondary_color',
                            'label'     => __( 'Secondary Colour', 'loopkit' ),
                            'type'      => 'color',
                            'condition' => array(
                                'drop_cap_view!' => 'default',
                            ),
                        ),
                        array(
                            'name'    => 'drop_cap_size',
                            'label'   => __( 'Size', 'loopkit' ),
                            'type'    => 'slider',
                            'range'   => array(
                                'min'  => 0,
                                'max'  => 30,
                                'step' => 0.1,
                            ),
                            'default' => array(
                                'size' => 5,
                                'unit' => 'em',
                            ),
                        ),
                        array(
                            'name'    => 'drop_cap_space',
                            'label'   => __( 'Space', 'loopkit' ),
                            'type'    => 'slider',
                            'range'   => array(
                                'min'  => 0,
                                'max'  => 50,
                                'step' => 1,
                            ),
                            'default' => array(
                                'size' => 10,
                                'unit' => 'px',
                            ),
                        ),
                        array(
                            'name'      => 'drop_cap_border_radius',
                            'label'     => __( 'Border Radius', 'loopkit' ),
                            'type'      => 'slider',
                            'condition' => array(
                                'drop_cap_view!' => 'default',
                            ),
                            'range'     => array(
                                'min'  => 0,
                                'max'  => 100,
                                'step' => 1,
                            ),
                        ),
                        array(
                            'name'      => 'drop_cap_border_width',
                            'label'     => __( 'Border Width', 'loopkit' ),
                            'type'      => 'slider',
                            'condition' => array(
                                'drop_cap_view' => 'framed',
                            ),
                            'range'     => array(
                                'min'  => 1,
                                'max'  => 20,
                                'step' => 1,
                            ),
                        ),
                        array(
                            'name'  => 'drop_cap_typography',
                            'label' => __( 'Typography', 'loopkit' ),
                            'type'  => 'typography',
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
        $editor_content = $settings['editor'] ?? '<p>' . esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.', 'loopkit' ) . '</p>';

        $drop_cap = ! empty( $settings['drop_cap'] ) && 'yes' === $settings['drop_cap'];
        $view     = $settings['drop_cap_view'] ?? 'default';

        if ( $drop_cap && ! empty( $editor_content ) ) {
            $pattern = '/(<p[^>]*>)\s*([^<>\s&])/u';
            $replacement = '$1<span class="lk-drop-cap lk-drop-cap-view-' . esc_attr( $view ) . '">$2</span>';
            $editor_content = preg_replace( $pattern, $replacement, $editor_content, 1 );
        }

        $inline_attr    = $editor_mode ? ' data-lk-inline-edit="editor"' : '';
        $drop_cap_class = $drop_cap ? ' lk-has-drop-cap' : '';

        $html = '<div class="lk-text-editor lk-clearfix' . $drop_cap_class . '"' . $inline_attr . '>';
        $html .= $editor_content;
        $html .= '</div>';

        return $html;
    }
}
