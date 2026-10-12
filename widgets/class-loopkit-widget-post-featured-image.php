<?php
/**
 * Loopkit Featured Image Widget.
 *
 * Markup (identical in loopkit-canvas.js renderPostFeaturedImage):
 * <figure class="lk-post-featured-image">[<a href>]<img class="lk-post-featured-image-img" src alt>[</a>][<figcaption class="lk-post-featured-image-caption">…</figcaption>]</figure>
 * No featured image: nothing on the front end; a placeholder in the editor.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Post_Featured_Image extends Loopkit_Widget_Base {

    public function get_name() {
        return 'post-featured-image';
    }

    public function get_title() {
        return esc_html__( 'Featured Image', 'loopkit' );
    }

    public function get_icon() {
        return 'dashicons-format-image';
    }

    public function get_category() {
        return 'post';
    }

    public function get_keywords() {
        return array( 'post', 'featured', 'image', 'thumbnail', 'single', 'photo' );
    }

    public function get_context() {
        return 'all';
    }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_featured_image',
                    'title'    => __( 'Image', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'image_size',
                            'label'   => __( 'Image Size', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'large',
                            'options' => array(
                                'thumbnail'    => esc_html__( 'Thumbnail', 'loopkit' ),
                                'medium'       => esc_html__( 'Medium', 'loopkit' ),
                                'medium_large' => esc_html__( 'Medium Large', 'loopkit' ),
                                'large'        => esc_html__( 'Large', 'loopkit' ),
                                'full'         => esc_html__( 'Full', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'    => 'link_to',
                            'label'   => __( 'Link', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'none',
                            'options' => array(
                                'none' => esc_html__( 'None', 'loopkit' ),
                                'post' => esc_html__( 'Post', 'loopkit' ),
                                'file' => esc_html__( 'Media File', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'        => 'show_caption',
                            'label'       => __( 'Caption', 'loopkit' ),
                            'type'        => 'switcher',
                            'default'     => 'no',
                            'description' => __( 'Shows the image caption set in the Media Library.', 'loopkit' ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_featured_image',
                    'title'    => __( 'Image', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'       => 'align',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'options'    => class_exists( 'Loopkit_Widget_Post_Title' ) ? Loopkit_Widget_Post_Title::align_options( false ) : array(),
                        ),
                        array(
                            'name'       => 'width',
                            'label'      => __( 'Width', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array( 'min' => 1, 'max' => 1600 ),
                            'units'      => array( '%', 'px', 'vw' ),
                        ),
                        array(
                            'name'       => 'max_width',
                            'label'      => __( 'Max Width', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array( 'min' => 1, 'max' => 1600 ),
                            'units'      => array( '%', 'px', 'vw' ),
                        ),
                        array(
                            'name'       => 'height',
                            'label'      => __( 'Height', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array( 'min' => 1, 'max' => 1000 ),
                            'units'      => array( 'px', 'vh' ),
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
                            'name'       => 'image_border_radius',
                            'label'      => __( 'Border Radius', 'loopkit' ),
                            'type'       => 'dimensions',
                            'responsive' => true,
                        ),
                        array(
                            'name'       => 'opacity',
                            'label'      => __( 'Opacity', 'loopkit' ),
                            'type'       => 'slider',
                            'unitless'   => true,
                            'range'      => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ),
                        ),
                    ),
                ),
                array(
                    'id'        => 'section_style_featured_caption',
                    'title'     => __( 'Caption', 'loopkit' ),
                    'tab'       => 'style',
                    'condition' => array( 'show_caption' => 'yes' ),
                    'controls'  => array(
                        array(
                            'name'       => 'caption_align',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'options'    => class_exists( 'Loopkit_Widget_Post_Title' ) ? Loopkit_Widget_Post_Title::align_options( false ) : array(),
                        ),
                        array(
                            'name'  => 'caption_color',
                            'label' => __( 'Text Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'caption_typography',
                            'label' => __( 'Typography', 'loopkit' ),
                            'type'  => 'typography',
                        ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $data  = class_exists( 'Loopkit_Post_Data' ) ? Loopkit_Post_Data::get_render_data( $editor_mode ) : null;
        $image = $data['image'] ?? null;

        if ( empty( $image ) || empty( $image['sizes'] ) ) {
            if ( ! $editor_mode ) {
                return '';
            }
            return '<figure class="lk-post-featured-image"><div class="lk-post-featured-image-placeholder"><img class="lk-post-featured-image-img" src="' . esc_url( LOOPKIT_PLUGIN_URL . 'assets/images/placeholder.svg' ) . '" alt=""><span>' . esc_html__( 'This post has no featured image', 'loopkit' ) . '</span></div></figure>';
        }

        $size = $settings['image_size'] ?? 'large';
        $src  = $image['sizes'][ $size ] ?? ( $image['sizes']['large'] ?? ( $image['sizes']['full'] ?? reset( $image['sizes'] ) ) );

        $extra = '';
        if ( ! $editor_mode && ! empty( $image['id'] ) ) {
            $srcset = wp_get_attachment_image_srcset( $image['id'], $size );
            $sizes  = wp_get_attachment_image_sizes( $image['id'], $size );
            if ( $srcset ) {
                $extra .= ' srcset="' . esc_attr( $srcset ) . '"';
            }
            if ( $sizes ) {
                $extra .= ' sizes="' . esc_attr( $sizes ) . '"';
            }
            $extra .= ' loading="lazy" decoding="async"';
        }

        $img = '<img class="lk-post-featured-image-img" src="' . esc_url( $src ) . '" alt="' . esc_attr( $image['alt'] ) . '"' . $extra . '>';

        $link_to = $settings['link_to'] ?? 'none';
        $href    = '';
        if ( 'post' === $link_to ) {
            $href = $data['permalink'];
        } elseif ( 'file' === $link_to ) {
            $href = $image['sizes']['full'] ?? $src;
        }
        if ( $href ) {
            $img = '<a href="' . esc_url( $href ) . '">' . $img . '</a>';
        }

        $caption = '';
        if ( $this->switcher_on( $settings, 'show_caption', false ) && '' !== $image['caption'] ) {
            $caption = '<figcaption class="lk-post-featured-image-caption">' . esc_html( $image['caption'] ) . '</figcaption>';
        }

        return '<figure class="lk-post-featured-image">' . $img . $caption . '</figure>';
    }
}
