<?php
/**
 * Loopkit Post Excerpt Widget.
 *
 * Markup (identical in loopkit-canvas.js renderPostExcerpt):
 * <div class="lk-post-excerpt">Text…</div>
 *
 * Text: the manual excerpt when the post has one (trimmed only if "Apply to Manual
 * Excerpt" is on), otherwise the first N words of the post content.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Post_Excerpt extends Loopkit_Widget_Base {

    public function get_name() {
        return 'post-excerpt';
    }

    public function get_title() {
        return esc_html__( 'Post Excerpt', 'loopkit' );
    }

    public function get_icon() {
        return 'dashicons-text';
    }

    public function get_category() {
        return 'post';
    }

    public function get_keywords() {
        return array( 'post', 'excerpt', 'summary', 'description', 'single' );
    }

    public function get_context() {
        return 'all';
    }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_post_excerpt',
                    'title'    => __( 'Excerpt', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'excerpt_length',
                            'label'   => __( 'Excerpt Length (words)', 'loopkit' ),
                            'type'    => 'number',
                            'default' => 25,
                            'min'     => 1,
                            'max'     => 300,
                        ),
                        array(
                            'name'        => 'apply_to_custom',
                            'label'       => __( 'Apply to Manual Excerpt', 'loopkit' ),
                            'type'        => 'switcher',
                            'default'     => 'no',
                            'description' => __( 'Also shorten excerpts written in the post editor.', 'loopkit' ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_post_excerpt',
                    'title'    => __( 'Excerpt', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'       => 'align',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'options'    => class_exists( 'Loopkit_Widget_Post_Title' ) ? Loopkit_Widget_Post_Title::align_options( true ) : array(),
                        ),
                        array(
                            'name'  => 'text_color',
                            'label' => __( 'Text Color', 'loopkit' ),
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

    /**
     * Excerpt text for a data array. Same logic as the canvas.
     *
     * @param array $data     Post data (Loopkit_Post_Data::get_data()).
     * @param array $settings Widget settings.
     * @return string
     */
    public static function get_excerpt_text( $data, $settings ) {
        $length = isset( $settings['excerpt_length'] ) && is_numeric( $settings['excerpt_length'] ) ? (int) $settings['excerpt_length'] : 25;
        $length = max( 1, $length );
        $custom = (string) ( $data['custom_excerpt'] ?? '' );

        if ( '' !== trim( $custom ) ) {
            if ( self::switcher_on( $settings, 'apply_to_custom', false ) ) {
                return Loopkit_Post_Data::trim_words( $custom, $length );
            }
            return trim( $custom );
        }

        return Loopkit_Post_Data::trim_words( (string) ( $data['content_text'] ?? '' ), $length );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $data = class_exists( 'Loopkit_Post_Data' ) ? Loopkit_Post_Data::get_render_data( $editor_mode ) : null;
        if ( ! $data ) {
            return '';
        }

        $text = self::get_excerpt_text( $data, $settings );
        if ( '' === $text ) {
            if ( ! $editor_mode ) {
                return '';
            }
            return '<div class="lk-post-excerpt lk-post-excerpt-empty">' . esc_html__( 'This post has no excerpt or text content.', 'loopkit' ) . '</div>';
        }

        return '<div class="lk-post-excerpt">' . esc_html( $text ) . '</div>';
    }
}
