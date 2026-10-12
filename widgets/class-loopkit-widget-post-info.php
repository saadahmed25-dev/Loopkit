<?php
/**
 * Loopkit Post Info Widget.
 *
 * Markup (identical in loopkit-canvas.js renderPostInfo):
 * <ul class="lk-post-info-items lk-post-info-{inline|list}[ lk-post-info-has-divider]">
 *   <li class="lk-post-info-item lk-post-info-item-{type}">
 *     [<a href>]<span class="lk-post-info-icon">svg</span>[<span class="lk-post-info-before">Before</span>]<span class="lk-post-info-text">Text</span>[</a>]
 *   </li>
 * </ul>
 * Terms items link each term separately inside .lk-post-info-text.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Post_Info extends Loopkit_Widget_Base {

    public function get_name() {
        return 'post-info';
    }

    public function get_title() {
        return esc_html__( 'Post Info', 'loopkit' );
    }

    public function get_icon() {
        return 'dashicons-info-outline';
    }

    public function get_category() {
        return 'post';
    }

    public function get_keywords() {
        return array( 'post', 'info', 'meta', 'date', 'author', 'time', 'comments', 'categories', 'tags', 'single' );
    }

    public function get_context() {
        return 'all';
    }

    /**
     * Default icon per item type.
     *
     * @param string $type Item type.
     * @return array
     */
    public static function default_icon( $type ) {
        $map = array(
            'author'   => array( 'library' => 'fa-regular', 'value' => 'user' ),
            'date'     => array( 'library' => 'fa-regular', 'value' => 'calendar' ),
            'time'     => array( 'library' => 'fa-regular', 'value' => 'clock' ),
            'comments' => array( 'library' => 'fa-regular', 'value' => 'comment' ),
            'terms'    => array( 'library' => 'fa-regular', 'value' => 'folder' ),
            'modified' => array( 'library' => 'fa-regular', 'value' => 'pen-to-square' ),
        );
        return $map[ $type ] ?? $map['date'];
    }

    protected function register_controls() {
        $date_options = array(
            'default' => esc_html__( 'Default (site setting)', 'loopkit' ),
            'F j, Y'  => date_i18n( 'F j, Y' ),
            'Y-m-d'   => date_i18n( 'Y-m-d' ),
            'm/d/Y'   => date_i18n( 'm/d/Y' ),
            'd/m/Y'   => date_i18n( 'd/m/Y' ),
        );
        $time_options = array(
            'default' => esc_html__( 'Default (site setting)', 'loopkit' ),
            'g:i a'   => date_i18n( 'g:i a' ),
            'g:i A'   => date_i18n( 'g:i A' ),
            'H:i'     => date_i18n( 'H:i' ),
        );

        $default_items = array();
        foreach ( array( 'author', 'date', 'time', 'comments' ) as $i => $type ) {
            $default_items[] = array(
                '_id'           => 'info' . ( $i + 1 ),
                'type'          => $type,
                'date_format'   => 'default',
                'time_format'   => 'default',
                'taxonomy'      => 'category',
                'before'        => '',
                'link'          => 'yes',
                'selected_icon' => self::default_icon( $type ),
            );
        }

        return array(
            'content' => array(
                array(
                    'id'       => 'section_post_info',
                    'title'    => __( 'Meta Data', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'layout',
                            'label'   => __( 'Layout', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'inline',
                            'options' => array(
                                'inline' => esc_html__( 'Inline', 'loopkit' ),
                                'list'   => esc_html__( 'List', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'        => 'info_items',
                            'label'       => __( 'Items', 'loopkit' ),
                            'type'        => 'repeater',
                            'title_field' => 'type',
                            'default'     => $default_items,
                            'fields'      => array(
                                array(
                                    'name'    => 'type',
                                    'label'   => __( 'Type', 'loopkit' ),
                                    'type'    => 'select',
                                    'default' => 'date',
                                    'options' => array(
                                        'author'   => esc_html__( 'Author', 'loopkit' ),
                                        'date'     => esc_html__( 'Date', 'loopkit' ),
                                        'time'     => esc_html__( 'Time', 'loopkit' ),
                                        'comments' => esc_html__( 'Comments', 'loopkit' ),
                                        'terms'    => esc_html__( 'Terms', 'loopkit' ),
                                        'modified' => esc_html__( 'Last Modified', 'loopkit' ),
                                    ),
                                ),
                                array(
                                    'name'        => 'date_format',
                                    'label'       => __( 'Date Format', 'loopkit' ),
                                    'type'        => 'select',
                                    'default'     => 'default',
                                    'options'     => $date_options,
                                    'description' => __( 'Used by Date and Last Modified.', 'loopkit' ),
                                ),
                                array(
                                    'name'        => 'time_format',
                                    'label'       => __( 'Time Format', 'loopkit' ),
                                    'type'        => 'select',
                                    'default'     => 'default',
                                    'options'     => $time_options,
                                    'description' => __( 'Used by Time.', 'loopkit' ),
                                ),
                                array(
                                    'name'        => 'taxonomy',
                                    'label'       => __( 'Taxonomy', 'loopkit' ),
                                    'type'        => 'select',
                                    'default'     => 'category',
                                    'options'     => array(
                                        'category' => esc_html__( 'Categories', 'loopkit' ),
                                        'post_tag' => esc_html__( 'Tags', 'loopkit' ),
                                    ),
                                    'description' => __( 'Used by Terms.', 'loopkit' ),
                                ),
                                array(
                                    'name'    => 'before',
                                    'label'   => __( 'Before', 'loopkit' ),
                                    'type'    => 'text',
                                    'default' => '',
                                ),
                                array(
                                    'name'    => 'link',
                                    'label'   => __( 'Link', 'loopkit' ),
                                    'type'    => 'switcher',
                                    'default' => 'yes',
                                ),
                                array(
                                    'name'    => 'selected_icon',
                                    'label'   => __( 'Icon', 'loopkit' ),
                                    'type'    => 'icon',
                                    'default' => self::default_icon( 'date' ),
                                ),
                            ),
                        ),
                        array(
                            'name'    => 'divider',
                            'label'   => __( 'Divider', 'loopkit' ),
                            'type'    => 'switcher',
                            'default' => 'no',
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_post_info_list',
                    'title'    => __( 'List', 'loopkit' ),
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
                            'name'       => 'space_between',
                            'label'      => __( 'Space Between', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array( 'min' => 0, 'max' => 100 ),
                            'units'      => array( 'px', 'em' ),
                        ),
                        array(
                            'name'      => 'divider_color',
                            'label'     => __( 'Divider Color', 'loopkit' ),
                            'type'      => 'color',
                            'condition' => array( 'divider' => 'yes' ),
                        ),
                    ),
                ),
                array(
                    'id'       => 'section_style_post_info_icon',
                    'title'    => __( 'Icon', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'  => 'icon_color',
                            'label' => __( 'Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'       => 'icon_size',
                            'label'      => __( 'Size', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array( 'min' => 6, 'max' => 60 ),
                            'units'      => array( 'px', 'em' ),
                        ),
                    ),
                ),
                array(
                    'id'       => 'section_style_post_info_text',
                    'title'    => __( 'Text', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'  => 'text_color',
                            'label' => __( 'Text Color', 'loopkit' ),
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

    /**
     * Build the <li> list for one post. Same logic as the canvas.
     *
     * @param array $data     Post data.
     * @param array $settings Widget settings.
     * @return string HTML of <li> items.
     */
    public static function build_items( $data, $settings ) {
        $items = $settings['info_items'] ?? null;
        if ( ! is_array( $items ) ) {
            $items = array(
                array( 'type' => 'author', 'link' => 'yes' ),
                array( 'type' => 'date', 'link' => 'yes' ),
                array( 'type' => 'time', 'link' => 'yes' ),
                array( 'type' => 'comments', 'link' => 'yes' ),
            );
        }

        $html = '';
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $type    = $item['type'] ?? 'date';
            $link_on = ( 'no' !== ( $item['link'] ?? 'yes' ) );
            $text    = '';
            $url     = '';
            $is_html = false;

            switch ( $type ) {
                case 'author':
                    $text = $data['author']['name'] ?? '';
                    $url  = $data['author']['url'] ?? '';
                    break;
                case 'date':
                    $fmt  = $item['date_format'] ?? 'default';
                    $text = $data['dates'][ $fmt ] ?? ( $data['dates']['default'] ?? '' );
                    $url  = $data['day_archive_url'] ?? '';
                    break;
                case 'modified':
                    $fmt  = $item['date_format'] ?? 'default';
                    $text = $data['modified'][ $fmt ] ?? ( $data['modified']['default'] ?? '' );
                    break;
                case 'time':
                    $fmt  = $item['time_format'] ?? 'default';
                    $text = $data['times'][ $fmt ] ?? ( $data['times']['default'] ?? '' );
                    break;
                case 'comments':
                    $text = $data['comments']['text'] ?? '';
                    $url  = $data['comments']['url'] ?? '';
                    break;
                case 'terms':
                    $tax   = ( 'post_tag' === ( $item['taxonomy'] ?? 'category' ) ) ? 'post_tag' : 'category';
                    $terms = $data['terms'][ $tax ] ?? array();
                    if ( empty( $terms ) ) {
                        continue 2;
                    }
                    $parts = array();
                    foreach ( $terms as $term ) {
                        $parts[] = ( $link_on && ! empty( $term['url'] ) )
                            ? '<a href="' . esc_url( $term['url'] ) . '">' . esc_html( $term['name'] ) . '</a>'
                            : esc_html( $term['name'] );
                    }
                    $text    = implode( ', ', $parts );
                    $is_html = true;
                    break;
                default:
                    continue 2;
            }

            if ( '' === $text ) {
                continue;
            }

            $icon     = ! empty( $item['selected_icon']['value'] ) ? $item['selected_icon'] : self::default_icon( $type );
            $icon_svg = class_exists( 'Loopkit_Icons' ) ? Loopkit_Icons::render( $icon ) : '';

            $inner  = '<span class="lk-post-info-icon">' . $icon_svg . '</span>';
            $before = trim( (string) ( $item['before'] ?? '' ) );
            if ( '' !== $before ) {
                $inner .= '<span class="lk-post-info-before">' . esc_html( $before ) . '</span>';
            }
            $inner .= '<span class="lk-post-info-text">' . ( $is_html ? $text : esc_html( $text ) ) . '</span>';

            if ( ! $is_html && $link_on && '' !== $url ) {
                $inner = '<a href="' . esc_url( $url ) . '">' . $inner . '</a>';
            }

            $html .= '<li class="lk-post-info-item lk-post-info-item-' . esc_attr( $type ) . '">' . $inner . '</li>';
        }

        return $html;
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $data = class_exists( 'Loopkit_Post_Data' ) ? Loopkit_Post_Data::get_render_data( $editor_mode ) : null;
        if ( ! $data ) {
            return '';
        }

        $items_html = self::build_items( $data, $settings );
        if ( '' === $items_html && ! $editor_mode ) {
            return '';
        }

        $layout  = ( 'list' === ( $settings['layout'] ?? 'inline' ) ) ? 'list' : 'inline';
        $classes = 'lk-post-info-items lk-post-info-' . $layout;
        if ( $this->switcher_on( $settings, 'divider', false ) ) {
            $classes .= ' lk-post-info-has-divider';
        }

        return '<ul class="' . esc_attr( $classes ) . '">' . $items_html . '</ul>';
    }
}
