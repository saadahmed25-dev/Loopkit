<?php
/**
 * Loopkit Archive Description Widget.
 *
 * Markup (identical in loopkit-canvas.js renderArchiveDescription):
 * <div class="lk-archive-description">…</div>
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Archive_Description extends Loopkit_Widget_Base {

    public function get_name() {
        return 'archive-description';
    }

    public function get_title() {
        return esc_html__( 'Archive Description', 'loopkit' );
    }

    public function get_icon() {
        return 'dashicons-text';
    }

    public function get_category() {
        return 'archive';
    }

    public function get_keywords() {
        return array( 'archive', 'description', 'category', 'tag', 'text' );
    }

    public function get_context() {
        return 'archive';
    }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_archive_description',
                    'title'    => __( 'Description', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name' => 'archive_description_note',
                            'type' => 'html',
                            'raw'  => '<p class="lk-control-description">' . esc_html__( 'Shows the description of the category, tag or author being viewed. Nothing is shown when it has none.', 'loopkit' ) . '</p>',
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_archive_description',
                    'title'    => __( 'Description', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'align', 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'responsive' => true, 'options' => Loopkit_Widget_Post_Title::align_options( true ) ),
                        array( 'name' => 'text_color', 'label' => __( 'Text Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'link_color', 'label' => __( 'Link Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'typography', 'label' => __( 'Typography', 'loopkit' ), 'type' => 'typography', 'responsive' => true ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $data = class_exists( 'Loopkit_Archive_Data' ) ? Loopkit_Archive_Data::get_render_data( $editor_mode ) : null;
        $desc = $data ? $data['description'] : '';
        if ( '' === $desc ) {
            if ( ! $editor_mode ) {
                return '';
            }
            return '<div class="lk-archive-description lk-archive-description-empty">' . esc_html__( 'This archive has no description.', 'loopkit' ) . '</div>';
        }
        return '<div class="lk-archive-description">' . $desc . '</div>';
    }
}
