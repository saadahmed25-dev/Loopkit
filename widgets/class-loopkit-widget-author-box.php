<?php
/**
 * Loopkit Author Box Widget.
 *
 * Markup (identical in loopkit-canvas.js renderAuthorBox):
 * <div class="lk-author-box lk-author-box-{left|above}">
 *   [<div class="lk-author-box-avatar">[<a>]<img>[</a>]</div>]
 *   <div class="lk-author-box-text">[<{tag} class="lk-author-box-name">[<a>]Name[</a>]</{tag}>][<div class="lk-author-box-bio">…</div>][<a class="lk-author-box-link">…</a>]</div>
 * </div>
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Author_Box extends Loopkit_Widget_Base {

    public function get_name() {
        return 'author-box';
    }

    public function get_title() {
        return esc_html__( 'Author Box', 'loopkit' );
    }

    public function get_icon() {
        return 'dashicons-id';
    }

    public function get_category() {
        return 'post';
    }

    public function get_keywords() {
        return array( 'author', 'box', 'bio', 'avatar', 'about', 'post', 'single' );
    }

    public function get_context() {
        return 'all';
    }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_author_box',
                    'title'    => __( 'Author Info', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'show_avatar', 'label' => __( 'Profile Picture', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'show_name', 'label' => __( 'Display Name', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array(
                            'name'    => 'name_tag',
                            'label'   => __( 'Name HTML Tag', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'h4',
                            'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'div', 'p' => 'p' ),
                        ),
                        array(
                            'name'    => 'link_to',
                            'label'   => __( 'Link', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'none',
                            'options' => array(
                                'none'    => esc_html__( 'None', 'loopkit' ),
                                'posts'   => esc_html__( 'Posts Archive', 'loopkit' ),
                                'website' => esc_html__( 'Website', 'loopkit' ),
                            ),
                            'description' => __( 'Applies to the picture and the name.', 'loopkit' ),
                        ),
                        array( 'name' => 'show_bio', 'label' => __( 'Biography', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'show_link', 'label' => __( 'Archive Button', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                        array(
                            'name'      => 'link_text',
                            'label'     => __( 'Button Text', 'loopkit' ),
                            'type'      => 'text',
                            'default'   => esc_html__( 'All Posts', 'loopkit' ),
                            'condition' => array( 'show_link' => 'yes' ),
                        ),
                        array(
                            'name'    => 'layout',
                            'label'   => __( 'Picture Position', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'left',
                            'options' => array(
                                'left'  => esc_html__( 'Left', 'loopkit' ),
                                'above' => esc_html__( 'Above', 'loopkit' ),
                            ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_author_box',
                    'title'    => __( 'Box', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'       => 'align',
                            'label'      => __( 'Text Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'options'    => Loopkit_Widget_Post_Title::align_options( false ),
                        ),
                        array( 'name' => 'avatar_size', 'label' => __( 'Picture Size', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'range' => array( 'min' => 20, 'max' => 300 ), 'units' => array( 'px' ) ),
                        array( 'name' => 'avatar_radius', 'label' => __( 'Picture Radius', 'loopkit' ), 'type' => 'slider', 'range' => array( 'min' => 0, 'max' => 200 ), 'units' => array( '%', 'px' ) ),
                        array( 'name' => 'gap', 'label' => __( 'Gap', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'range' => array( 'min' => 0, 'max' => 100 ), 'units' => array( 'px' ) ),
                    ),
                ),
                array(
                    'id'       => 'section_style_author_text',
                    'title'    => __( 'Text', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'name_color', 'label' => __( 'Name Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'name_typography', 'label' => __( 'Name Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'bio_color', 'label' => __( 'Biography Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'bio_typography', 'label' => __( 'Biography Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'button_color', 'label' => __( 'Button Text Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'button_bg', 'label' => __( 'Button Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'button_typography', 'label' => __( 'Button Typography', 'loopkit' ), 'type' => 'typography' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $data = class_exists( 'Loopkit_Post_Data' ) ? Loopkit_Post_Data::get_render_data( $editor_mode ) : null;
        if ( ! $data || empty( $data['author']['name'] ) ) {
            return '';
        }
        $a = $data['author'];

        $link_to = $settings['link_to'] ?? 'none';
        $href    = ( 'posts' === $link_to ) ? $a['url'] : ( ( 'website' === $link_to ) ? $a['website'] : '' );

        $tag = $settings['name_tag'] ?? 'h4';
        if ( ! in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' ), true ) ) {
            $tag = 'h4';
        }
        $layout = ( 'above' === ( $settings['layout'] ?? 'left' ) ) ? 'above' : 'left';

        $html = '<div class="lk-author-box lk-author-box-' . $layout . '">';

        if ( $this->switcher_on( $settings, 'show_avatar', true ) && '' !== $a['avatar'] ) {
            $img   = '<img src="' . esc_url( $a['avatar'] ) . '" alt="' . esc_attr( $a['name'] ) . '">';
            $html .= '<div class="lk-author-box-avatar">' . ( $href ? '<a href="' . esc_url( $href ) . '">' . $img . '</a>' : $img ) . '</div>';
        }

        $html .= '<div class="lk-author-box-text">';
        if ( $this->switcher_on( $settings, 'show_name', true ) ) {
            $name  = esc_html( $a['name'] );
            $html .= '<' . $tag . ' class="lk-author-box-name">' . ( $href ? '<a href="' . esc_url( $href ) . '">' . $name . '</a>' : $name ) . '</' . $tag . '>';
        }
        if ( $this->switcher_on( $settings, 'show_bio', true ) && '' !== $a['bio'] ) {
            $html .= '<div class="lk-author-box-bio">' . esc_html( $a['bio'] ) . '</div>';
        }
        if ( $this->switcher_on( $settings, 'show_link', false ) && '' !== $a['url'] ) {
            $text  = ( isset( $settings['link_text'] ) && '' !== $settings['link_text'] ) ? $settings['link_text'] : __( 'All Posts', 'loopkit' );
            $html .= '<a class="lk-author-box-link" href="' . esc_url( $a['url'] ) . '">' . esc_html( $text ) . '</a>';
        }
        $html .= '</div></div>';

        return $html;
    }
}
