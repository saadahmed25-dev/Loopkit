<?php
/**
 * Loopkit Post Navigation Widget (previous / next post).
 *
 * Markup (identical in loopkit-canvas.js renderPostNavigation):
 * <nav class="lk-post-navigation" aria-label="Posts">
 *   <div class="lk-post-nav-prev">[<a class="lk-post-nav-link" href>[arrow]<span class="lk-post-nav-text">[label][title]</span></a>]</div>
 *   <div class="lk-post-nav-next">[<a class="lk-post-nav-link" href><span class="lk-post-nav-text">[label][title]</span>[arrow]</a>]</div>
 * </nav>
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Post_Navigation extends Loopkit_Widget_Base {

    /**
     * Chevron icons (same strings in loopkit-canvas.js).
     */
    const ARROW_PREV = '<svg aria-hidden="true" viewBox="0 0 320 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M41.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l160 160c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L109.3 256 246.6 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-160 160z"/></svg>';
    const ARROW_NEXT = '<svg aria-hidden="true" viewBox="0 0 320 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M278.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-160 160c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L210.7 256 73.4 118.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l160 160z"/></svg>';

    public function get_name() {
        return 'post-navigation';
    }

    public function get_title() {
        return esc_html__( 'Post Navigation', 'loopkit' );
    }

    public function get_icon() {
        return 'dashicons-leftright';
    }

    public function get_category() {
        return 'post';
    }

    public function get_keywords() {
        return array( 'post', 'navigation', 'previous', 'next', 'pagination', 'single' );
    }

    public function get_context() {
        return 'all';
    }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_post_navigation',
                    'title'    => __( 'Post Navigation', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'show_label', 'label' => __( 'Label', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'prev_label', 'label' => __( 'Previous Label', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Previous', 'loopkit' ), 'condition' => array( 'show_label' => 'yes' ) ),
                        array( 'name' => 'next_label', 'label' => __( 'Next Label', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Next', 'loopkit' ), 'condition' => array( 'show_label' => 'yes' ) ),
                        array( 'name' => 'show_title', 'label' => __( 'Post Title', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'show_arrow', 'label' => __( 'Arrows', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array(
                            'name'        => 'in_same_term',
                            'label'       => __( 'In Same Category', 'loopkit' ),
                            'type'        => 'switcher',
                            'default'     => 'no',
                            'description' => __( 'Only link to posts in the same category.', 'loopkit' ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_post_navigation',
                    'title'    => __( 'Post Navigation', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'label_color', 'label' => __( 'Label Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'label_typography', 'label' => __( 'Label Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'title_color', 'label' => __( 'Title Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'title_hover_color', 'label' => __( 'Title Hover Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'title_typography', 'label' => __( 'Title Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'arrow_color', 'label' => __( 'Arrow Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'arrow_size', 'label' => __( 'Arrow Size', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'range' => array( 'min' => 6, 'max' => 60 ), 'units' => array( 'px' ) ),
                    ),
                ),
            ),
        );
    }

    /**
     * One side of the navigation. Same logic as the canvas.
     *
     * @param array|null $item     { title, url } or null.
     * @param string     $dir      'prev' or 'next'.
     * @param array      $settings Settings.
     * @return string
     */
    public static function side( $item, $dir, $settings ) {
        $html = '<div class="lk-post-nav-' . $dir . '">';
        if ( $item && ! empty( $item['url'] ) ) {
            $text = '';
            if ( self::switcher_on( $settings, 'show_label', true ) ) {
                $key   = $dir . '_label';
                $label = ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) ? $settings[ $key ] : ( 'prev' === $dir ? __( 'Previous', 'loopkit' ) : __( 'Next', 'loopkit' ) );
                $text .= '<span class="lk-post-nav-label">' . esc_html( $label ) . '</span>';
            }
            if ( self::switcher_on( $settings, 'show_title', true ) ) {
                $text .= '<span class="lk-post-nav-title">' . esc_html( $item['title'] ) . '</span>';
            }
            $arrow = ( self::switcher_on( $settings, 'show_arrow', true ) )
                ? '<span class="lk-post-nav-arrow">' . ( 'prev' === $dir ? self::ARROW_PREV : self::ARROW_NEXT ) . '</span>'
                : '';
            $inner = '<span class="lk-post-nav-text">' . $text . '</span>';
            $inner = ( 'prev' === $dir ) ? $arrow . $inner : $inner . $arrow;
            $html .= '<a class="lk-post-nav-link" href="' . esc_url( $item['url'] ) . '" rel="' . $dir . '">' . $inner . '</a>';
        }
        return $html . '</div>';
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $data = class_exists( 'Loopkit_Post_Data' ) ? Loopkit_Post_Data::get_render_data( $editor_mode ) : null;
        if ( ! $data || empty( $data['nav'] ) ) {
            return '';
        }
        $suffix = ( $this->switcher_on( $settings, 'in_same_term', false ) ) ? '_same' : '_all';
        $prev   = $data['nav'][ 'prev' . $suffix ] ?? null;
        $next   = $data['nav'][ 'next' . $suffix ] ?? null;
        if ( ! $prev && ! $next && ! $editor_mode ) {
            return '';
        }
        return '<nav class="lk-post-navigation" aria-label="' . esc_attr__( 'Posts', 'loopkit' ) . '">' . self::side( $prev, 'prev', $settings ) . self::side( $next, 'next', $settings ) . '</nav>';
    }
}
