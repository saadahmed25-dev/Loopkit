<?php
/**
 * Loopkit Post Comments Widget.
 *
 * Renders the comment list (wp_list_comments) and the comment form (comment_form)
 * directly, so it looks the same on classic and block themes.
 *
 * Markup (identical in loopkit-canvas.js renderPostComments):
 * <div class="lk-post-comments">[<{tag} class="lk-comments-title">N Comments</{tag}>]<ol class="lk-comment-list">…</ol>[form]</div>
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Post_Comments extends Loopkit_Widget_Base {

    public function get_name() {
        return 'post-comments';
    }

    public function get_title() {
        return esc_html__( 'Comments', 'loopkit' );
    }

    public function get_icon() {
        return 'dashicons-admin-comments';
    }

    public function get_category() {
        return 'post';
    }

    public function get_keywords() {
        return array( 'comments', 'discussion', 'reply', 'form', 'post', 'single' );
    }

    public function get_context() {
        return 'all';
    }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_post_comments',
                    'title'    => __( 'Comments', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'show_title', 'label' => __( 'Title', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array(
                            'name'      => 'title_tag',
                            'label'     => __( 'Title HTML Tag', 'loopkit' ),
                            'type'      => 'select',
                            'default'   => 'h3',
                            'options'   => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'div' ),
                            'condition' => array( 'show_title' => 'yes' ),
                        ),
                        array( 'name' => 'show_form', 'label' => __( 'Comment Form', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_post_comments',
                    'title'    => __( 'Comments', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'title_color', 'label' => __( 'Title Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'title_typography', 'label' => __( 'Title Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'text_color', 'label' => __( 'Text Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'link_color', 'label' => __( 'Link Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'typography', 'label' => __( 'Typography', 'loopkit' ), 'type' => 'typography' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        if ( ! class_exists( 'Loopkit_Post_Data' ) ) {
            return '';
        }
        $post_id = Loopkit_Post_Data::get_render_post_id( $editor_mode );
        $parts   = $post_id ? Loopkit_Post_Data::comments_parts( $post_id ) : array( 'list' => '', 'form' => '', 'open' => false, 'count' => 0 );

        $show_form = ( $this->switcher_on( $settings, 'show_form', true ) );
        $body      = $parts['list'] . ( $show_form ? $parts['form'] : '' );

        if ( '' === trim( $body ) ) {
            if ( ! $editor_mode ) {
                return '';
            }
            $body = '<p class="lk-post-comments-empty">' . esc_html__( 'Comments are closed for this post and it has no comments.', 'loopkit' ) . '</p>';
        }

        $title = '';
        if ( $this->switcher_on( $settings, 'show_title', true ) && $parts['count'] > 0 ) {
            $tag = $settings['title_tag'] ?? 'h3';
            if ( ! in_array( $tag, array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), true ) ) {
                $tag = 'h3';
            }
            $title = '<' . $tag . ' class="lk-comments-title">' . esc_html( Loopkit_Post_Data::comments_text( $parts['count'] ) ) . '</' . $tag . '>';
        }

        return '<div class="lk-post-comments">' . $title . $body . '</div>';
    }
}
