<?php
/**
 * Loopkit Archive Title Widget.
 *
 * Markup (identical in loopkit-canvas.js renderArchiveTitle):
 * <div class="lk-archive-title-wrapper"><{tag} class="lk-archive-title">[<span class="lk-archive-title-prefix">Category: </span>]<span class="lk-archive-title-name">News</span></{tag}></div>
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Archive_Title extends Loopkit_Widget_Base {

    public function get_name() {
        return 'archive-title';
    }

    public function get_title() {
        return esc_html__( 'Archive Title', 'loopkit' );
    }

    public function get_icon() {
        return 'dashicons-editor-textcolor';
    }

    public function get_category() {
        return 'archive';
    }

    public function get_keywords() {
        return array( 'archive', 'title', 'category', 'tag', 'heading', 'search' );
    }

    public function get_context() {
        return 'archive';
    }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_archive_title',
                    'title'    => __( 'Title', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'header_size',
                            'label'   => __( 'HTML Tag', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'h1',
                            'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'div', 'p' => 'p' ),
                        ),
                        array(
                            'name'        => 'show_prefix',
                            'label'       => __( 'Show Prefix', 'loopkit' ),
                            'type'        => 'switcher',
                            'default'     => 'yes',
                            'description' => __( 'e.g. "Category:" or "Tag:" before the name.', 'loopkit' ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_archive_title',
                    'title'    => __( 'Title', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'align', 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'responsive' => true, 'options' => Loopkit_Widget_Post_Title::align_options( true ) ),
                        array( 'name' => 'title_color', 'label' => __( 'Text Color', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'prefix_color', 'label' => __( 'Prefix Color', 'loopkit' ), 'type' => 'color', 'condition' => array( 'show_prefix' => 'yes' ) ),
                        array( 'name' => 'typography', 'label' => __( 'Typography', 'loopkit' ), 'type' => 'typography', 'responsive' => true ),
                        array( 'name' => 'text_shadow', 'label' => __( 'Text Shadow', 'loopkit' ), 'type' => 'popover', 'popoverType' => 'text_shadow' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $data = class_exists( 'Loopkit_Archive_Data' ) ? Loopkit_Archive_Data::get_render_data( $editor_mode ) : null;
        if ( ! $data || '' === $data['title'] ) {
            return '';
        }
        $tag = $settings['header_size'] ?? 'h1';
        if ( ! in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' ), true ) ) {
            $tag = 'h1';
        }
        $inner = '';
        if ( $this->switcher_on( $settings, 'show_prefix', true ) && '' !== $data['prefix'] ) {
            $inner .= '<span class="lk-archive-title-prefix">' . esc_html( $data['prefix'] ) . ' </span>';
        }
        $inner .= '<span class="lk-archive-title-name">' . esc_html( $data['title'] ) . '</span>';
        return '<div class="lk-archive-title-wrapper"><' . $tag . ' class="lk-archive-title">' . $inner . '</' . $tag . '></div>';
    }
}
