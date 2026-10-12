<?php
/**
 * Loopkit Image Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Image extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'image';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Image', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-format-image';
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
        return array( 'image', 'photo', 'picture', 'media' );
    }

    /**
     * Register controls.
     *
     * @return array
     */
    protected function register_controls() {
        $default_placeholder = LOOPKIT_PLUGIN_URL . 'assets/images/placeholder.svg';
        $default_caption_typo = ! $this->is_default_fonts_disabled() ? 'globals/typography?id=text' : '';

        return array(
            'content' => array(
                array(
                    'id'       => 'section_image',
                    'title'    => __( 'Image', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'       => 'image',
                            'label'      => __( 'Choose Image', 'loopkit' ),
                            'type'       => 'media',
                            'responsive' => true,
                            'default'    => array(
                                'url' => $default_placeholder,
                                'id'  => '',
                            ),
                        ),
                        array(
                            'name'    => 'image_size',
                            'label'   => __( 'Image Resolution', 'loopkit' ),
                            'type'    => 'image_size',
                            'default' => 'large',
                        ),
                        array(
                            'name'    => 'caption_source',
                            'label'   => __( 'Caption', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'none',
                            'options' => array(
                                'none'       => esc_html__( 'None', 'loopkit' ),
                                'attachment' => esc_html__( 'Attachment Caption', 'loopkit' ),
                                'custom'     => esc_html__( 'Custom Caption', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'      => 'caption',
                            'label'     => __( 'Custom Caption', 'loopkit' ),
                            'type'      => 'text',
                            'default'   => '',
                            'condition' => array(
                                'caption_source' => 'custom',
                            ),
                        ),
                        array(
                            'name'    => 'link_to',
                            'label'   => __( 'Link', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'none',
                            'options' => array(
                                'none'   => esc_html__( 'None', 'loopkit' ),
                                'file'   => esc_html__( 'Media File', 'loopkit' ),
                                'custom' => esc_html__( 'Custom URL', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'      => 'link',
                            'label'     => __( 'Link', 'loopkit' ),
                            'type'      => 'url',
                            'default'   => array( 'url' => '' ),
                            'condition' => array(
                                'link_to' => 'custom',
                            ),
                        ),
                        array(
                            'name'      => 'open_lightbox',
                            'label'     => __( 'Lightbox', 'loopkit' ),
                            'type'      => 'select',
                            'default'   => 'default',
                            'options'   => array(
                                'default' => esc_html__( 'Default', 'loopkit' ),
                                'yes'     => esc_html__( 'Yes', 'loopkit' ),
                                'no'      => esc_html__( 'No', 'loopkit' ),
                            ),
                            'condition' => array(
                                'link_to' => 'file',
                            ),
                        ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_style_image',
                    'title'    => __( 'Image', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'       => 'align',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
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
                            'name'       => 'width',
                            'label'      => __( 'Width', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 1,
                                'max' => 1000,
                            ),
                            'units'      => array( 'px', '%', 'em', 'rem', 'vw', 'vh', 'custom' ),
                        ),
                        array(
                            'name'       => 'max_width',
                            'label'      => __( 'Max Width', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 1,
                                'max' => 1000,
                            ),
                            'units'      => array( 'px', '%', 'em', 'rem', 'vw', 'vh', 'custom' ),
                        ),
                        array(
                            'name'       => 'height',
                            'label'      => __( 'Height', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 1,
                                'max' => 1000,
                            ),
                            'units'      => array( 'px', '%', 'em', 'rem', 'vw', 'vh', 'custom' ),
                        ),
                        array(
                            'name'       => 'object_fit',
                            'label'      => __( 'Object Fit', 'loopkit' ),
                            'type'       => 'select',
                            'responsive' => true,
                            'condition'  => array(
                                'height[size]!' => '',
                            ),
                            'default'    => '',
                            'options'    => array(
                                ''           => esc_html__( 'Default', 'loopkit' ),
                                'fill'       => esc_html__( 'Fill', 'loopkit' ),
                                'cover'      => esc_html__( 'Cover', 'loopkit' ),
                                'contain'    => esc_html__( 'Contain', 'loopkit' ),
                                'scale-down' => esc_html__( 'Scale Down', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'       => 'object_position',
                            'label'      => __( 'Object Position', 'loopkit' ),
                            'type'       => 'select',
                            'responsive' => true,
                            'condition'  => array(
                                'object_fit' => 'cover',
                            ),
                            'default'    => 'center center',
                            'options'    => array(
                                'center center' => esc_html__( 'Center Center', 'loopkit' ),
                                'center left'   => esc_html__( 'Center Left', 'loopkit' ),
                                'center right'  => esc_html__( 'Center Right', 'loopkit' ),
                                'top center'    => esc_html__( 'Top Center', 'loopkit' ),
                                'top left'      => esc_html__( 'Top Left', 'loopkit' ),
                                'top right'     => esc_html__( 'Top Right', 'loopkit' ),
                                'bottom center' => esc_html__( 'Bottom Center', 'loopkit' ),
                                'bottom left'   => esc_html__( 'Bottom Left', 'loopkit' ),
                                'bottom right'  => esc_html__( 'Bottom Right', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'  => 'opacity',
                            'label' => __( 'Opacity', 'loopkit' ),
                            'type'  => 'slider',
                            'range' => array(
                                'min'  => 0,
                                'max'  => 1,
                                'step' => 0.05,
                            ),
                        ),
                        array(
                            'name'        => 'css_filters',
                            'label'       => __( 'CSS Filters', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'css_filters',
                        ),
                        array(
                            'name'  => 'opacity_hover',
                            'label' => __( 'Opacity (Hover)', 'loopkit' ),
                            'type'  => 'slider',
                            'range' => array(
                                'min'  => 0,
                                'max'  => 1,
                                'step' => 0.05,
                            ),
                        ),
                        array(
                            'name'        => 'css_filters_hover',
                            'label'       => __( 'CSS Filters (Hover)', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'css_filters',
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
                        array(
                            'name'    => 'border_type',
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
                            'name'       => 'border_width',
                            'label'      => __( 'Border Width', 'loopkit' ),
                            'type'       => 'dimensions',
                            'responsive' => true,
                            'condition'  => array(
                                'border_type!' => array( '', 'none' ),
                            ),
                        ),
                        array(
                            'name'      => 'border_color',
                            'label'     => __( 'Border Colour', 'loopkit' ),
                            'type'      => 'color',
                            'condition' => array(
                                'border_type!' => array( '', 'none' ),
                            ),
                        ),
                        array(
                            'name'       => 'border_radius',
                            'label'      => __( 'Border Radius', 'loopkit' ),
                            'type'       => 'dimensions',
                            'responsive' => true,
                        ),
                        array(
                            'name'        => 'box_shadow',
                            'label'       => __( 'Box Shadow', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'box_shadow',
                        ),
                    ),
                ),
                array(
                    'id'        => 'section_style_caption',
                    'title'     => __( 'Caption', 'loopkit' ),
                    'tab'       => 'style',
                    'condition' => array(
                        'caption_source!' => 'none',
                    ),
                    'controls'  => array(
                        array(
                            'name'       => 'caption_align',
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
                            'name'  => 'caption_text_color',
                            'label' => __( 'Text Colour', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'caption_background_color',
                            'label' => __( 'Background Colour', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'    => 'caption_typography',
                            'label'   => __( 'Typography', 'loopkit' ),
                            'type'    => 'typography',
                            'default' => $default_caption_typo,
                        ),
                        array(
                            'name'        => 'caption_text_shadow',
                            'label'       => __( 'Text Shadow', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'text_shadow',
                        ),
                        array(
                            'name'       => 'caption_space',
                            'label'      => __( 'Spacing', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min'  => 0,
                                'max'  => 100,
                                'step' => 1,
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
        $image_data = $settings['image'] ?? array();
        $image_url  = '';
        $image_id   = 0;

        if ( is_array( $image_data ) ) {
            $image_url = $image_data['url'] ?? '';
            $image_id  = absint( $image_data['id'] ?? 0 );
        } elseif ( is_string( $image_data ) ) {
            $image_url = $image_data;
        }

        if ( empty( $image_url ) ) {
            $image_url = LOOPKIT_PLUGIN_URL . 'assets/images/placeholder.svg';
        }

        $size = $settings['image_size'] ?? 'large';
        if ( is_array( $size ) ) {
            $size = $size['size'] ?? 'large';
        }

        $alt_text = '';
        $srcset   = '';
        $sizes    = '';
        $width    = '';
        $height   = '';

        if ( $image_id > 0 ) {
            $src_data = wp_get_attachment_image_src( $image_id, $size );
            if ( $src_data ) {
                $image_url = $src_data[0];
                $width     = $src_data[1];
                $height    = $src_data[2];
            }
            $alt_text = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
            $srcset   = wp_get_attachment_image_srcset( $image_id, $size );
            $sizes    = wp_get_attachment_image_sizes( $image_id, $size );
        }

        if ( empty( $alt_text ) ) {
            $alt_text = esc_attr__( 'Image', 'loopkit' );
        }

        // Caption
        $caption_source = $settings['caption_source'] ?? 'none';
        $caption_text   = '';
        if ( 'attachment' === $caption_source && $image_id > 0 ) {
            $caption_text = wp_get_attachment_caption( $image_id );
        } elseif ( 'custom' === $caption_source ) {
            $caption_text = $settings['caption'] ?? '';
        }

        $has_caption = ! empty( $caption_text );

        // Link
        $link_to   = $settings['link_to'] ?? 'none';
        $link_url  = '';
        $link_attr = '';

        if ( 'file' === $link_to ) {
            if ( $image_id > 0 ) {
                $full_src = wp_get_attachment_image_src( $image_id, 'full' );
                $link_url = $full_src ? $full_src[0] : $image_url;
            } else {
                $link_url = $image_url;
            }
            $lightbox = $settings['open_lightbox'] ?? 'default';
            if ( 'no' !== $lightbox ) {
                $link_attr .= ' data-loopkit-lightbox="yes"';
            }
        } elseif ( 'custom' === $link_to ) {
            $link_obj = $settings['link'] ?? array();
            $link_url = is_array( $link_obj ) ? ( $link_obj['url'] ?? '' ) : (string) $link_obj;
            if ( is_array( $link_obj ) ) {
                if ( ! empty( $link_obj['is_external'] ) ) {
                    $link_attr .= ' target="_blank"';
                }
                if ( ! empty( $link_obj['nofollow'] ) ) {
                    $link_attr .= ' rel="nofollow"';
                }
            }
        }

        $has_link = ! empty( $link_url );

        // Hover animation class on img
        $hover_anim = $settings['hover_animation'] ?? '';
        $img_classes = 'attachment-' . esc_attr( is_string( $size ) ? $size : 'large' ) . ' size-' . esc_attr( is_string( $size ) ? $size : 'large' );
        if ( ! empty( $hover_anim ) ) {
            $img_classes .= ' lk-animation-' . esc_attr( $hover_anim );
        }

        $img_attrs = ' src="' . esc_url( $image_url ) . '" class="' . esc_attr( $img_classes ) . '" alt="' . esc_attr( $alt_text ) . '" loading="lazy"';
        if ( ! empty( $width ) ) $img_attrs .= ' width="' . esc_attr( $width ) . '"';
        if ( ! empty( $height ) ) $img_attrs .= ' height="' . esc_attr( $height ) . '"';
        if ( ! empty( $srcset ) ) $img_attrs .= ' srcset="' . esc_attr( $srcset ) . '"';
        if ( ! empty( $sizes ) ) $img_attrs .= ' sizes="' . esc_attr( $sizes ) . '"';

        $img_html = '<img' . $img_attrs . ' />';

        if ( $has_link ) {
            $img_html = '<a href="' . esc_url( $link_url ) . '"' . $link_attr . '>' . $img_html . '</a>';
        }

        $html = '';
        if ( $has_caption ) {
            $html .= '<figure class="wp-caption lk-widget-image">';
            $html .= $img_html;
            $html .= '<figcaption class="widget-image-caption wp-caption-text lk-image-caption">' . wp_kses_post( $caption_text ) . '</figcaption>';
            $html .= '</figure>';
        } else {
            $html .= $img_html;
        }

        return $html;
    }
}
