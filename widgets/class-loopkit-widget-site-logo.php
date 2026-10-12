<?php
/**
 * Loopkit Site Logo Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Site_Logo extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'site-logo';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Site Logo', 'loopkit' );
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
        return 'site';
    }

    /**
     * Get search keywords.
     *
     * @return array
     */
    public function get_keywords() {
        return array( 'site', 'logo', 'brand', 'header', 'identity', 'custom' );
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
        return array(
            'content' => array(
                array(
                    'id'       => 'section_site_logo',
                    'title'    => __( 'Site Logo', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'custom_logo_override',
                            'label'       => __( 'Override Site Logo', 'loopkit' ),
                            'type'        => 'switcher',
                            'default'     => 'no',
                            'description' => __( 'Enable to select a custom image instead of the theme logo.', 'loopkit' ),
                        ),
                        array(
                            'name'       => 'image',
                            'label'      => __( 'Choose Image', 'loopkit' ),
                            'type'       => 'media',
                            'condition'  => array( 'custom_logo_override' => 'yes' ),
                            'default'    => array(
                                'url' => '',
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
                            'name'    => 'caption_source',
                            'label'   => __( 'Caption', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'none',
                            'options' => array(
                                'none'        => esc_html__( 'None', 'loopkit' ),
                                'title'       => __( 'Title', 'loopkit' ),
                                'description' => __( 'Description', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'    => 'link',
                            'label'   => __( 'Link', 'loopkit' ),
                            'type'    => 'url',
                            'default' => array(
                                'url'         => home_url( '/' ),
                                'is_external' => false,
                                'nofollow'    => false,
                            ),
                        ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_style_logo',
                    'title'    => __( 'Logo', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'       => 'align',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'default'    => 'left',
                            'options'    => array(
                                'left'   => array( 'title' => __( 'Left', 'loopkit' ), 'icon' => 'dashicons-editor-alignleft' ),
                                'center' => array( 'title' => __( 'Center', 'loopkit' ), 'icon' => 'dashicons-editor-aligncenter' ),
                                'right'  => array( 'title' => __( 'Right', 'loopkit' ), 'icon' => 'dashicons-editor-alignright' ),
                            ),
                        ),
                        array(
                            'name'       => 'width',
                            'label'      => __( 'Width', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'px' => array( 'min' => 10, 'max' => 1200, 'step' => 1 ),
                                '%'  => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
                                'vw' => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
                            ),
                            'default'    => array( 'size' => '', 'unit' => 'px' ),
                        ),
                        array(
                            'name'       => 'max_width',
                            'label'      => __( 'Max Width', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'px' => array( 'min' => 10, 'max' => 1200, 'step' => 1 ),
                                '%'  => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
                                'vw' => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
                            ),
                            'default'    => array( 'size' => '', 'unit' => 'px' ),
                        ),
                        array(
                            'name'       => 'height',
                            'label'      => __( 'Height', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'px' => array( 'min' => 10, 'max' => 800, 'step' => 1 ),
                                'vh' => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
                            ),
                            'default'    => array( 'size' => '', 'unit' => 'px' ),
                        ),
                        array(
                            'name'       => 'object_fit',
                            'label'      => __( 'Object Fit', 'loopkit' ),
                            'type'       => 'select',
                            'responsive' => true,
                            'default'    => '',
                            'options'    => array(
                                ''        => esc_html__( 'Default', 'loopkit' ),
                                'fill'    => esc_html__( 'Fill', 'loopkit' ),
                                'cover'   => esc_html__( 'Cover', 'loopkit' ),
                                'contain' => esc_html__( 'Contain', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'    => 'opacity',
                            'label'   => __( 'Opacity', 'loopkit' ),
                            'type'    => 'slider',
                            'range'   => array(
                                'px' => array( 'min' => 0.1, 'max' => 1, 'step' => 0.05 ),
                            ),
                            'default' => array( 'size' => '', 'unit' => 'px' ),
                        ),
                        array(
                            'name'       => 'border_radius',
                            'label'      => __( 'Border Radius', 'loopkit' ),
                            'type'       => 'dimension',
                            'responsive' => true,
                            'default'    => array( 'top' => '', 'right' => '', 'bottom' => '', 'left' => '', 'unit' => 'px', 'isLinked' => true ),
                        ),
                        array(
                            'name'  => 'box_shadow',
                            'label' => __( 'Box Shadow', 'loopkit' ),
                            'type'  => 'box_shadow',
                        ),
                        array(
                            'name'  => 'css_filters',
                            'label' => __( 'CSS Filters', 'loopkit' ),
                            'type'  => 'css_filters',
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
        $override   = ! empty( $settings['custom_logo_override'] ) && 'yes' === $settings['custom_logo_override'];
        $image_id   = 0;
        $image_url  = '';
        $alt_text   = '';
        $size       = ! empty( $settings['image_size'] ) ? $settings['image_size'] : 'full';

        if ( $override && ! empty( $settings['image'] ) ) {
            if ( is_array( $settings['image'] ) ) {
                $image_id  = intval( $settings['image']['id'] ?? 0 );
                $image_url = esc_url( $settings['image']['url'] ?? '' );
            } else {
                $image_url = esc_url( (string) $settings['image'] );
            }
        } else {
            // Read WordPress custom logo
            $custom_logo_id = get_theme_mod( 'custom_logo' );
            if ( $custom_logo_id ) {
                $image_id = intval( $custom_logo_id );
            }
        }

        if ( $image_id > 0 ) {
            $src_data = wp_get_attachment_image_src( $image_id, $size );
            if ( $src_data ) {
                $image_url = $src_data[0];
            }
            $alt_text = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
        }

        if ( empty( $alt_text ) ) {
            $alt_text = get_bloginfo( 'name', 'display' );
        }

        // Empty state
        if ( empty( $image_url ) ) {
            if ( $editor_mode ) {
                return '<div class="lk-site-logo-placeholder">' .
                       '  <span class="dashicons dashicons-format-image"></span>' .
                       '  <span class="lk-placeholder-text">' . esc_html__( 'Choose Site Logo', 'loopkit' ) . '</span>' .
                       '</div>';
            }
            return '';
        }

        // Caption
        $caption_source = $settings['caption_source'] ?? 'none';
        $caption_text   = '';
        if ( $image_id > 0 ) {
            $attachment = get_post( $image_id );
            if ( 'title' === $caption_source && $attachment ) {
                $caption_text = $attachment->post_title;
            } elseif ( 'description' === $caption_source && $attachment ) {
                $caption_text = $attachment->post_excerpt ? $attachment->post_excerpt : $attachment->post_content;
            }
        }

        $img_attrs = ' src="' . esc_url( $image_url ) . '" class="lk-site-logo-img" alt="' . esc_attr( $alt_text ) . '"';
        $img_html  = '<img' . $img_attrs . ' />';

        // Link
        $link_url = '';
        $link_attrs = '';
        if ( isset( $settings['link'] ) && is_array( $settings['link'] ) && ! empty( $settings['link']['url'] ) ) {
            $link_url = $settings['link']['url'];
            if ( ! empty( $settings['link']['is_external'] ) ) {
                $link_attrs .= ' target="_blank"';
            }
            if ( ! empty( $settings['link']['nofollow'] ) ) {
                $link_attrs .= ' rel="nofollow"';
            }
        } else {
            $link_url = home_url( '/' );
        }

        if ( ! empty( $link_url ) ) {
            $img_html = '<a href="' . esc_url( $link_url ) . '" class="lk-site-logo-link"' . $link_attrs . '>' . $img_html . '</a>';
        }

        if ( ! empty( $caption_text ) ) {
            return '<figure class="lk-site-logo-wrap">' . $img_html . '<figcaption class="lk-site-logo-caption">' . esc_html( $caption_text ) . '</figcaption></figure>';
        }

        return '<div class="lk-site-logo-wrap">' . $img_html . '</div>';
    }
}
