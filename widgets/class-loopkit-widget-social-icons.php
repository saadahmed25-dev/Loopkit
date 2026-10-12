<?php
/**
 * Loopkit Social Icons Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Social_Icons extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'social-icons';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Social Icons', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-share';
    }

    /**
     * Get widget category.
     *
     * @return string
     */
    public function get_category() {
        return 'general';
    }

    /**
     * Get search keywords.
     *
     * @return array
     */
    public function get_keywords() {
        return array( 'social icons', 'share', 'facebook', 'twitter', 'x', 'youtube', 'instagram', 'linkedin', 'follow' );
    }

    /**
     * Official brand color mapping.
     */
    public static function get_brand_colors() {
        return array(
            'facebook'    => '#1877F2',
            'facebook-f'  => '#1877F2',
            'twitter'     => '#1DA1F2',
            'x-twitter'   => '#000000',
            'youtube'     => '#CD201F',
            'instagram'   => '#E4405F',
            'linkedin'    => '#0A66C2',
            'linkedin-in' => '#0A66C2',
            'pinterest'   => '#BD081C',
            'pinterest-p' => '#BD081C',
            'github'      => '#333333',
            'whatsapp'    => '#25D366',
            'tiktok'      => '#000000',
            'telegram'    => '#24A1DE',
            'discord'     => '#5865F2',
            'reddit'      => '#FF4500',
            'spotify'     => '#1DB954',
            'apple'       => '#000000',
            'google'      => '#4285F4',
        );
    }

    /**
     * Register controls.
     *
     * @return array
     */
    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_social_icons',
                    'title'    => __( 'Social Icons', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'social_icon_list',
                            'label'       => __( 'Social Icons', 'loopkit' ),
                            'type'        => 'repeater',
                            'title_field' => 'social_icon',
                            'default'     => array(
                                array(
                                    '_id'         => 'soc1',
                                    'social_icon' => array(
                                        'library' => 'fa-brands',
                                        'value'   => 'facebook-f',
                                    ),
                                    'link'        => array( 'url' => 'https://facebook.com' ),
                                    'item_color'  => 'default',
                                ),
                                array(
                                    '_id'         => 'soc2',
                                    'social_icon' => array(
                                        'library' => 'fa-brands',
                                        'value'   => 'x-twitter',
                                    ),
                                    'link'        => array( 'url' => 'https://x.com' ),
                                    'item_color'  => 'default',
                                ),
                                array(
                                    '_id'         => 'soc3',
                                    'social_icon' => array(
                                        'library' => 'fa-brands',
                                        'value'   => 'youtube',
                                    ),
                                    'link'        => array( 'url' => 'https://youtube.com' ),
                                    'item_color'  => 'default',
                                ),
                            ),
                            'fields'      => array(
                                array(
                                    'name'    => 'social_icon',
                                    'label'   => __( 'Icon', 'loopkit' ),
                                    'type'    => 'icon',
                                    'default' => array(
                                        'library' => 'fa-brands',
                                        'value'   => 'facebook-f',
                                    ),
                                ),
                                array(
                                    'name'    => 'link',
                                    'label'   => __( 'Link', 'loopkit' ),
                                    'type'    => 'url',
                                    'default' => array( 'url' => '#' ),
                                ),
                                array(
                                    'name'    => 'item_color',
                                    'label'   => __( 'Colour', 'loopkit' ),
                                    'type'    => 'select',
                                    'default' => 'default',
                                    'options' => array(
                                        'default' => esc_html__( 'Official Colour', 'loopkit' ),
                                        'custom'  => esc_html__( 'Custom', 'loopkit' ),
                                    ),
                                ),
                                array(
                                    'name'      => 'item_primary_color',
                                    'label'     => __( 'Primary Colour', 'loopkit' ),
                                    'type'      => 'color',
                                    'condition' => array(
                                        'item_color' => 'custom',
                                    ),
                                ),
                                array(
                                    'name'      => 'item_secondary_color',
                                    'label'     => __( 'Secondary Colour', 'loopkit' ),
                                    'type'      => 'color',
                                    'condition' => array(
                                        'item_color' => 'custom',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'name'    => 'shape',
                            'label'   => __( 'Shape', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'rounded',
                            'options' => array(
                                'rounded' => esc_html__( 'Rounded', 'loopkit' ),
                                'square'  => esc_html__( 'Square', 'loopkit' ),
                                'circle'  => esc_html__( 'Circle', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'       => 'columns',
                            'label'      => __( 'Columns', 'loopkit' ),
                            'type'       => 'select',
                            'responsive' => true,
                            'default'    => '0',
                            'options'    => array(
                                '0' => esc_html__( 'Auto', 'loopkit' ),
                                '1' => '1',
                                '2' => '2',
                                '3' => '3',
                                '4' => '4',
                                '5' => '5',
                                '6' => '6',
                            ),
                        ),
                        array(
                            'name'       => 'align',
                            'label'      => __( 'Alignment', 'loopkit' ),
                            'type'       => 'choose',
                            'responsive' => true,
                            'default'    => 'center',
                            'options'    => array(
                                'left'   => array(
                                    'title' => __( 'Left', 'loopkit' ),
                                    'icon'  => 'dashicons-editor-alignleft',
                                ),
                                'center' => array(
                                    'title' => __( 'Center', 'loopkit' ),
                                    'icon'  => 'dashicons-editor-aligncenter',
                                ),
                                'right'  => array(
                                    'title' => __( 'Right', 'loopkit' ),
                                    'icon'  => 'dashicons-editor-alignright',
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_social_icons',
                    'title'    => __( 'Icon', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'    => 'color_type',
                            'label'   => __( 'Colour', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'default',
                            'options' => array(
                                'default' => esc_html__( 'Official Colour', 'loopkit' ),
                                'custom'  => esc_html__( 'Custom', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'      => 'primary_color',
                            'label'     => __( 'Primary Colour', 'loopkit' ),
                            'type'      => 'color',
                            'default'   => '#38A8F5',
                            'condition' => array(
                                'color_type' => 'custom',
                            ),
                        ),
                        array(
                            'name'      => 'secondary_color',
                            'label'     => __( 'Secondary Colour', 'loopkit' ),
                            'type'      => 'color',
                            'default'   => '#FFFFFF',
                            'condition' => array(
                                'color_type' => 'custom',
                            ),
                        ),
                        array(
                            'name'       => 'icon_size',
                            'label'      => __( 'Size', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 6,
                                'max' => 100,
                            ),
                            'default'    => array(
                                'size' => 18,
                                'unit' => 'px',
                            ),
                        ),
                        array(
                            'name'       => 'icon_padding',
                            'label'      => __( 'Padding', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 0,
                                'max' => 50,
                            ),
                            'default'    => array(
                                'size' => 10,
                                'unit' => 'px',
                            ),
                        ),
                        array(
                            'name'       => 'icon_spacing',
                            'label'      => __( 'Spacing', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 0,
                                'max' => 50,
                            ),
                            'default'    => array(
                                'size' => 10,
                                'unit' => 'px',
                            ),
                        ),
                        array(
                            'name'       => 'rows_gap',
                            'label'      => __( 'Rows Gap', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 0,
                                'max' => 50,
                            ),
                            'default'    => array(
                                'size' => 10,
                                'unit' => 'px',
                            ),
                        ),
                        array(
                            'name'    => 'hover_animation',
                            'label'   => __( 'Hover Animation', 'loopkit' ),
                            'type'    => 'hover_animation',
                            'default' => '',
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Give every icon the `fa-` prefix its library needs.
     *
     * Earlier releases stored the library as a bare word ('brands'), while the rest of
     * the builder stores 'fa-brands'. The editor's icon preview only strips the prefix,
     * so a bare value made the preview fall back to a generic glyph. Normalising here
     * means rows saved by any version render and preview correctly without a migration.
     *
     * @param array $items Repeater rows.
     * @return array
     */
    private static function normalise_items( $items ) {
        $out = array();
        foreach ( (array) $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $icon = $item['social_icon'] ?? array();
            if ( is_array( $icon ) && isset( $icon['library'] ) && is_string( $icon['library'] ) ) {
                $icon['library'] = 0 === strpos( $icon['library'], 'fa-' )
                    ? $icon['library']
                    : 'fa-' . ltrim( $icon['library'], '-' );
                $item['social_icon'] = $icon;
            }
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Render widget output.
     *
     * @param array $settings    Element settings.
     * @param array $el          Full element array.
     * @param bool  $editor_mode Whether rendering in editor/canvas mode.
     * @return string HTML output.
     */
    public function render( $settings, $el, $editor_mode = false ) {
        $shape        = $settings['shape'] ?? 'rounded';
        $align        = $settings['align'] ?? 'center';
        $columns      = intval( $settings['columns'] ?? 0 );
        $hover_anim   = $settings['hover_animation'] ?? '';
        $global_color = $settings['color_type'] ?? 'default';

        $items = $settings['social_icon_list'] ?? array(
            array( 'social_icon' => array( 'library' => 'fa-brands', 'value' => 'facebook-f' ), 'link' => array( 'url' => 'https://facebook.com' ) ),
            array( 'social_icon' => array( 'library' => 'fa-brands', 'value' => 'x-twitter' ), 'link' => array( 'url' => 'https://x.com' ) ),
            array( 'social_icon' => array( 'library' => 'fa-brands', 'value' => 'youtube' ), 'link' => array( 'url' => 'https://youtube.com' ) ),
        );
        $items = self::normalise_items( $items );

        $brand_map = self::get_brand_colors();

        $classes = 'lk-social-icons-wrapper lk-align-' . esc_attr( $align ) . ' lk-shape-' . esc_attr( $shape );
        if ( $columns > 0 ) {
            $classes .= ' lk-grid-columns-' . $columns;
        }

        $html = '<div class="' . esc_attr( $classes ) . '">';
        foreach ( $items as $idx => $item ) {
            $icon = $item['social_icon'] ?? array();
            $link = $item['link'] ?? array();

            $icon_val = is_array( $icon ) ? ( $icon['value'] ?? '' ) : $icon;
            $network  = str_replace( array( 'fa-', '-f', '-square', '-in' ), '', $icon_val );
            if ( empty( $network ) ) {
                $network = 'social';
            }

            $icon_svg = '';
            if ( ! empty( $icon ) && class_exists( 'Loopkit_Icons' ) ) {
                $icon_svg = Loopkit_Icons::render( $icon );
            }
            if ( empty( $icon_svg ) ) {
                $icon_svg = '<svg aria-hidden="true" class="lk-svg-icon" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><circle cx="256" cy="256" r="200" fill="currentColor"></circle></svg>';
            }

            $url = is_array( $link ) ? ( $link['url'] ?? '' ) : ( is_string( $link ) ? $link : '' );
            $url = ! empty( $url ) ? $url : '#';

            $item_color_mode = $item['item_color'] ?? 'default';
            $official_bg = $brand_map[ $icon_val ] ?? ( $brand_map[ $network ] ?? '#38A8F5' );

            $inline_style = '';
            if ( 'default' === $global_color && 'default' === $item_color_mode ) {
                $inline_style = ' style="--lk-social-bg:' . esc_attr( $official_bg ) . ';background-color:' . esc_attr( $official_bg ) . ';color:#ffffff;fill:#ffffff;"';
            } elseif ( 'custom' === $item_color_mode ) {
                $bg = $item['item_primary_color'] ?? '#38A8F5';
                $fg = $item['item_secondary_color'] ?? '#FFFFFF';
                $inline_style = ' style="background-color:' . esc_attr( $bg ) . ';color:' . esc_attr( $fg ) . ';fill:' . esc_attr( $fg ) . ';"';
            }

            $anim_class = ! empty( $hover_anim ) ? ' lk-animation-' . esc_attr( $hover_anim ) : '';
            $target = ( is_array( $link ) && ! empty( $link['is_external'] ) ) ? ' target="_blank" rel="noopener noreferrer"' : '';

            $html .= '<span class="lk-grid-item">';
            $html .= '<a class="lk-icon lk-social-icon lk-social-icon-' . esc_attr( $network ) . $anim_class . '" href="' . esc_url( $url ) . '"' . $target . $inline_style . '>';
            $html .= '<span class="lk-screen-only">' . esc_html( ucfirst( $network ) ) . '</span>';
            $html .= $icon_svg;
            $html .= '</a>';
            $html .= '</span>';
        }
        $html .= '</div>';

        return $html;
    }
}
