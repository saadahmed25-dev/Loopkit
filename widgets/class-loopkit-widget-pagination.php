<?php
/**
 * Loopkit Pagination Widget — page links for the archive / blog / search being viewed.
 *
 * Markup: <nav class="lk-pagination">…page-numbers…</nav> (same links as the Posts widget).
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Pagination extends Loopkit_Widget_Base {

    public function get_name() {
        return 'pagination';
    }

    public function get_title() {
        return esc_html__( 'Pagination', 'loopkit' );
    }

    public function get_icon() {
        return 'dashicons-ellipsis';
    }

    public function get_category() {
        return 'archive';
    }

    public function get_keywords() {
        return array( 'pagination', 'pages', 'next', 'previous', 'archive' );
    }

    public function get_context() {
        return 'archive';
    }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_pagination',
                    'title'    => __( 'Pagination', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'pagination',
                            'label'   => __( 'Type', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'numbers_prev_next',
                            'options' => array(
                                'numbers'           => esc_html__( 'Numbers', 'loopkit' ),
                                'prev_next'         => esc_html__( 'Previous / Next', 'loopkit' ),
                                'numbers_prev_next' => esc_html__( 'Numbers + Previous / Next', 'loopkit' ),
                            ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_pagination',
                    'title'    => __( 'Pagination', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'align', 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'responsive' => true, 'options' => Loopkit_Widget_Post_Title::align_options( false ) ),
                        array( 'name' => 'space', 'label' => __( 'Space Between', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'range' => array( 'min' => 0, 'max' => 50 ), 'units' => array( 'px' ) ),
                        array( 'name' => 'color', 'label' => __( 'Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'active_color', 'label' => __( 'Active Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'typography', 'label' => __( 'Typography', 'loopkit' ), 'type' => 'typography' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        global $wp_query;
        $type    = $settings['pagination'] ?? 'numbers_prev_next';
        $current = $editor_mode ? 1 : Loopkit_Widget_Posts::current_page();
        $total   = $editor_mode ? 3 : (int) $wp_query->max_num_pages;
        return Loopkit_Widget_Posts::pagination_html( $current, $total, $type, 'lk-pagination' );
    }
}
