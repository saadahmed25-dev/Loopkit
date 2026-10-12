<?php
/**
 * Loopkit Post Title Widget.
 *
 * Markup (identical in loopkit-canvas.js renderPostTitle):
 * <div class="lk-post-title-wrapper [lk-size-{size}]"><{tag} class="lk-post-title">[<a href="{permalink}">]Title[</a>]</{tag}></div>
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Post_Title extends Loopkit_Widget_Base {

    public function get_name() {
        return 'post-title';
    }

    public function get_title() {
        return esc_html__( 'Post Title', 'loopkit' );
    }

    public function get_icon() {
        return 'dashicons-editor-textcolor';
    }

    public function get_category() {
        return 'post';
    }

    public function get_keywords() {
        return array( 'post', 'title', 'heading', 'single', 'name' );
    }

    public function get_context() {
        return 'all';
    }

    /**
     * Blend mode options (shared with other text widgets).
     *
     * @return array
     */
    public static function blend_mode_options() {
        return array(
            'normal'      => esc_html__( 'Normal', 'loopkit' ),
            'multiply'    => esc_html__( 'Multiply', 'loopkit' ),
            'screen'      => esc_html__( 'Screen', 'loopkit' ),
            'overlay'     => esc_html__( 'Overlay', 'loopkit' ),
            'darken'      => esc_html__( 'Darken', 'loopkit' ),
            'lighten'     => esc_html__( 'Lighten', 'loopkit' ),
            'color-dodge' => esc_html__( 'Color Dodge', 'loopkit' ),
            'saturation'  => esc_html__( 'Saturation', 'loopkit' ),
            'color'       => esc_html__( 'Color', 'loopkit' ),
            'difference'  => esc_html__( 'Difference', 'loopkit' ),
            'exclusion'   => esc_html__( 'Exclusion', 'loopkit' ),
            'hue'         => esc_html__( 'Hue', 'loopkit' ),
            'luminosity'  => esc_html__( 'Luminosity', 'loopkit' ),
        );
    }

    /**
     * Left / Center / Right / Justified choose options.
     *
     * @param bool $justify Include justify.
     * @return array
     */
    public static function align_options( $justify = true ) {
        $opts = array(
            'left'   => array( 'title' => __( 'Left', 'loopkit' ), 'icon' => 'dashicons-editor-alignleft' ),
            'center' => array( 'title' => __( 'Center', 'loopkit' ), 'icon' => 'dashicons-editor-aligncenter' ),
            'right'  => array( 'title' => __( 'Right', 'loopkit' ), 'icon' => 'dashicons-editor-alignright' ),
        );
        if ( $justify ) {
            $opts['justify'] = array( 'title' => __( 'Justified', 'loopkit' ), 'icon' => 'dashicons-editor-justify' );
        }
        return $opts;
    }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_post_title',
                    'title'    => __( 'Title', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'header_size',
                            'label'   => __( 'HTML Tag', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'h1',
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
                        array(
                            'name'    => 'size',
                            'label'   => __( 'Size', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'default',
                            'options' => array(
                                'default' => esc_html__( 'Default', 'loopkit' ),
                                'small'   => esc_html__( 'Small', 'loopkit' ),
                                'medium'  => esc_html__( 'Medium', 'loopkit' ),
                                'large'   => esc_html__( 'Large', 'loopkit' ),
                                'xl'      => esc_html__( 'XL', 'loopkit' ),
                                'xxl'     => esc_html__( 'XXL', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'        => 'link_to_post',
                            'label'       => __( 'Link to Post', 'loopkit' ),
                            'type'        => 'switcher',
                            'default'     => 'no',
                            'description' => __( 'Useful when the title is used in a header or a list.', 'loopkit' ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_post_title',
                    'title'    => __( 'Title', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'       => 'align',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'options'    => self::align_options( true ),
                        ),
                        array(
                            'name'  => 'title_color',
                            'label' => __( 'Text Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'      => 'title_hover_color',
                            'label'     => __( 'Link Hover Color', 'loopkit' ),
                            'type'      => 'color',
                            'condition' => array( 'link_to_post' => 'yes' ),
                        ),
                        array(
                            'name'       => 'typography',
                            'label'      => __( 'Typography', 'loopkit' ),
                            'type'       => 'typography',
                            'responsive' => true,
                        ),
                        array(
                            'name'        => 'text_stroke',
                            'label'       => __( 'Text Stroke', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'text_stroke',
                            'responsive'  => true,
                        ),
                        array(
                            'name'        => 'text_shadow',
                            'label'       => __( 'Text Shadow', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'text_shadow',
                        ),
                        array(
                            'name'    => 'blend_mode',
                            'label'   => __( 'Blend Mode', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'normal',
                            'options' => self::blend_mode_options(),
                        ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $data = class_exists( 'Loopkit_Post_Data' ) ? Loopkit_Post_Data::get_render_data( $editor_mode ) : null;
        if ( ! $data ) {
            return '';
        }

        $tag = $settings['header_size'] ?? 'h1';
        if ( ! in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), true ) ) {
            $tag = 'h1';
        }

        $size       = $settings['size'] ?? 'default';
        $size_class = ( $size && 'default' !== $size ) ? ' lk-size-' . sanitize_html_class( $size ) : '';
        $link       = ( $this->switcher_on( $settings, 'link_to_post', false ) );

        $inner = esc_html( $data['title'] );
        if ( $link ) {
            $inner = '<a href="' . esc_url( $data['permalink'] ) . '">' . $inner . '</a>';
        }

        return '<div class="lk-post-title-wrapper' . esc_attr( $size_class ) . '"><' . $tag . ' class="lk-post-title">' . $inner . '</' . $tag . '></div>';
    }
}
