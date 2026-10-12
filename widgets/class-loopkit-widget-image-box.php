<?php
/**
 * Loopkit Image Box Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Image_Box extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'image-box';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Image Box', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-format-gallery';
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
        return array( 'image box', 'card', 'media', 'feature', 'promo', 'photo', 'banner' );
    }

    /**
     * Register controls.
     *
     * @return array
     */
    protected function register_controls() {
        $placeholder       = LOOPKIT_PLUGIN_URL . 'assets/images/placeholder.svg';
        $default_primary   = ! $this->is_default_colors_disabled() ? 'globals/colors?id=primary' : '#38A8F5';
        $default_text      = ! $this->is_default_colors_disabled() ? 'globals/colors?id=text' : '#333333';
        $default_pri_typo  = ! $this->is_default_fonts_disabled() ? 'globals/typography?id=primary' : '';
        $default_text_typo = ! $this->is_default_fonts_disabled() ? 'globals/typography?id=text' : '';

        return array(
            'content' => array(
                array(
                    'id'       => 'section_image_box',
                    'title'    => __( 'Image Box', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'       => 'image',
                            'label'      => __( 'Choose Image', 'loopkit' ),
                            'type'       => 'media',
                            'responsive' => true,
                            'default'    => array(
                                'url' => $placeholder,
                                'id'  => '',
                            ),
                        ),
                        array(
                            'name'    => 'image_size',
                            'label'   => __( 'Image Resolution', 'loopkit' ),
                            'type'    => 'image_size',
                            'default' => 'full',
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
                            'name'       => 'image_position',
                            'label'      => __( 'Image Position', 'loopkit' ),
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
                    'id'       => 'section_style_image',
                    'title'    => __( 'Image', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'       => 'image_space',
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
                            'name'       => 'image_width',
                            'label'      => __( 'Width', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 5,
                                'max' => 100,
                            ),
                            'units'      => array( '%', 'px' ),
                            'default'    => array(
                                'size' => 30,
                                'unit' => '%',
                            ),
                        ),
                        array(
                            'name'       => 'image_border_radius',
                            'label'      => __( 'Border Radius', 'loopkit' ),
                            'type'       => 'dimensions',
                            'responsive' => true,
                        ),
                        array(
                            'name'    => 'image_hover_animation',
                            'label'   => __( 'Hover Animation', 'loopkit' ),
                            'type'    => 'hover_animation',
                            'default' => '',
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
        $img_data = $settings['image'] ?? array();
        $default_placeholder = LOOPKIT_PLUGIN_URL . 'assets/images/placeholder.svg';
        $img_url  = ( is_array( $img_data ) && ! empty( $img_data['url'] ) ) ? $img_data['url'] : ( ( is_string( $img_data ) && ! empty( $img_data ) ) ? $img_data : $default_placeholder );

        $title_text = $settings['title_text'] ?? esc_html__( 'This is the heading', 'loopkit' );
        $desc_text  = $settings['description_text'] ?? esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.', 'loopkit' );
        $tag        = sanitize_key( $settings['title_size'] ?? 'h3' );
        if ( ! in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), true ) ) {
            $tag = 'h3';
        }
        $pos   = ( $settings['image_position'] ?? $settings['position'] ?? null ) ?? 'top';
        $align = $settings['alignment'] ?? 'center';
        $v_align = $settings['vertical_alignment'] ?? 'top';
        $hover = $settings['image_hover_animation'] ?? '';

        $img_classes = 'lk-image-box-img';
        if ( ! empty( $hover ) ) {
            $img_classes .= ' lk-animation-' . esc_attr( $hover );
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

        $image_html = '<figure class="' . esc_attr( $img_classes ) . '"><img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $title_text ) . '" loading="lazy" /></figure>';

        $inline_title_attr = $editor_mode ? ' data-lk-inline-edit="title_text"' : '';
        $inline_desc_attr  = $editor_mode ? ' data-lk-inline-edit="description_text"' : '';

        $content_html = '<div class="lk-image-box-content">';
        $content_html .= '<' . $tag . ' class="lk-image-box-title"' . $inline_title_attr . '>';
        if ( ! empty( $url ) ) {
            $content_html .= '<a href="' . esc_url( $url ) . '"' . $attrs . '>' . esc_html( $title_text ) . '</a>';
        } else {
            $content_html .= esc_html( $title_text );
        }
        $content_html .= '</' . $tag . '>';
        $content_html .= '<p class="lk-image-box-description"' . $inline_desc_attr . '>' . esc_html( $desc_text ) . '</p>';
        $content_html .= '</div>';

        $wrapper_classes = 'lk-image-box-wrapper lk-image-box-position-' . esc_attr( $pos ) . ' lk-position-' . esc_attr( $pos ) . ' lk-align-' . esc_attr( $align );
        if ( 'top' !== $pos ) {
            $wrapper_classes .= ' lk-valign-' . esc_attr( $v_align );
        }

        $html = '<div class="' . esc_attr( $wrapper_classes ) . '">';
        $html .= $image_html;
        $html .= $content_html;
        $html .= '</div>';

        return $html;
    }
}
