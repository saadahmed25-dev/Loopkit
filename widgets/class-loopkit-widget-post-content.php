<?php
/**
 * Loopkit Post Content Widget.
 *
 * Only available when editing a Single template (context 'single'): inside a post's own
 * layout it would render itself. A recursion guard in Loopkit_Post_Data::content_html()
 * also protects against that on the front end.
 *
 * Markup (identical in loopkit-canvas.js renderPostContent): <div class="lk-post-content">…</div>
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Post_Content extends Loopkit_Widget_Base {

    public function get_name() {
        return 'post-content';
    }

    public function get_title() {
        return esc_html__( 'Post Content', 'loopkit' );
    }

    public function get_icon() {
        return 'dashicons-media-document';
    }

    public function get_category() {
        return 'post';
    }

    public function get_keywords() {
        return array( 'post', 'content', 'body', 'text', 'single' );
    }

    public function get_context() {
        return 'single';
    }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_post_content',
                    'title'    => __( 'Content', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name' => 'post_content_note',
                            'type' => 'html',
                            'raw'  => '<p class="lk-control-description">' . esc_html__( 'Shows the full content of the post being viewed. The editor previews the post chosen in Template Settings > Preview With.', 'loopkit' ) . '</p>',
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_post_content',
                    'title'    => __( 'Content', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'       => 'align',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'options'    => Loopkit_Widget_Post_Title::align_options( true ),
                        ),
                        array(
                            'name'  => 'text_color',
                            'label' => __( 'Text Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'link_color',
                            'label' => __( 'Link Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'link_hover_color',
                            'label' => __( 'Link Hover Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'       => 'typography',
                            'label'      => __( 'Typography', 'loopkit' ),
                            'type'       => 'typography',
                            'responsive' => true,
                        ),
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
        $html    = $post_id ? Loopkit_Post_Data::content_html( $post_id ) : '';

        if ( '' === trim( $html ) ) {
            if ( ! $editor_mode ) {
                return '';
            }
            $sample = Loopkit_Post_Data::get_sample_data();
            $html   = $sample['content_html'];
        }

        return '<div class="lk-post-content">' . $html . '</div>';
    }
}
