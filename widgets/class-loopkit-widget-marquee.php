<?php
/**
 * Loopkit Marquee.
 *
 * A scrolling ticker for text, images, or both — the kind of "infinite scroll" strip used for
 * logos, testimonials, offers and big outline headlines. Elementor has no marquee widget, so
 * this one covers the cases people normally reach for a separate plugin to get:
 *
 *   • Items: text with optional icon and link, or images (logos), or both
 *   • Direction: left, right, up, down
 *   • Real speed in pixels per second, measured from the rendered width, so every marquee on
 *     the page moves at the same pace no matter how much content it holds
 *   • Pause on hover / on focus, so links stay clickable
 *   • Seamless loop (the track is duplicated, the copy is hidden from screen readers)
 *   • Multiple rows, each row reversing direction
 *   • Fading edges with a colour of your choice
 *   • Outline (stroke) text style, separators between items, grayscale logos that colour on hover
 *   • Respects prefers-reduced-motion: the strip stops and scrolls manually instead
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Marquee extends Loopkit_Widget_Base {

    public function get_name() { return 'marquee'; }
    public function get_title() { return esc_html__( 'Marquee', 'loopkit' ); }
    public function get_icon() { return 'dashicons-controls-repeat'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'marquee', 'ticker', 'scroll', 'slider', 'logos', 'infinite', 'news' ); }

    /**
     * How many rows the widget offers.
     */
    const MAX_ROWS = 5;

    /**
     * The rows a setting applies to, as a condition value list ("show this from row N on").
     *
     * @param int $row Row number.
     * @return array
     */
    private static function rows_from( $row ) {
        $values = array();
        for ( $i = $row; $i <= self::MAX_ROWS; $i++ ) {
            $values[] = $i;
            $values[] = (string) $i;
        }
        return $values;
    }

    /**
     * The name of a row's control: row 1 keeps the plain name, so nothing breaks for
     * marquees that were built before rows existed.
     *
     * @param string $base Base name, e.g. mq_items.
     * @param int    $row  Row number.
     * @return string
     */
    public static function row_key( $base, $row ) {
        return ( 1 === (int) $row ) ? $base : $base . '_' . (int) $row;
    }

    /**
     * An Items repeater for every row. Adding a row in Motion reveals the next one here.
     *
     * @return array
     */
    private static function row_item_controls() {
        $controls = array();

        for ( $row = 1; $row <= self::MAX_ROWS; $row++ ) {
            $control = array(
                'name'        => self::row_key( 'mq_items', $row ),
                /* translators: %d: row number */
                'label'       => sprintf( esc_html__( 'Row %d — items', 'loopkit' ), $row ),
                'type'        => 'repeater',
                'title_field' => 'text',
                'default'     => 1 === $row ? array(
                    array( 'text' => esc_html__( 'Web design', 'loopkit' ) ),
                    array( 'text' => esc_html__( 'Development', 'loopkit' ) ),
                    array( 'text' => esc_html__( 'Branding', 'loopkit' ) ),
                    array( 'text' => esc_html__( 'SEO', 'loopkit' ) ),
                ) : array(
                    array( 'text' => esc_html__( 'Item', 'loopkit' ) ),
                ),
                'fields'      => array(
                    array( 'name' => 'text', 'label' => __( 'Text', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Item', 'loopkit' ) ),
                    array( 'name' => 'image', 'label' => __( 'Image', 'loopkit' ), 'type' => 'media' ),
                    array( 'name' => 'icon', 'label' => __( 'Icon', 'loopkit' ), 'type' => 'icon' ),
                    array( 'name' => 'link', 'label' => __( 'Link', 'loopkit' ), 'type' => 'url' ),
                ),
            );

            if ( $row > 1 ) {
                $control['condition'] = array( 'mq_rows' => self::rows_from( $row ) );
            }

            $controls[] = $control;
        }

        return $controls;
    }

    /**
     * Direction and speed for every row.
     *
     * @return array
     */
    private static function row_motion_controls() {
        $directions = array(
            'left'  => esc_html__( 'Left', 'loopkit' ),
            'right' => esc_html__( 'Right', 'loopkit' ),
            'up'    => esc_html__( 'Up', 'loopkit' ),
            'down'  => esc_html__( 'Down', 'loopkit' ),
        );

        $controls = array();
        for ( $row = 1; $row <= self::MAX_ROWS; $row++ ) {
            $direction = array(
                'name'    => self::row_key( 'mq_direction', $row ),
                /* translators: %d: row number */
                'label'   => sprintf( esc_html__( 'Row %d — direction', 'loopkit' ), $row ),
                'type'    => 'select',
                'default' => ( $row % 2 ) ? 'left' : 'right',
                'options' => $directions,
            );
            $speed = array(
                'name'        => 'mq_speed_' . $row,
                /* translators: %d: row number */
                'label'       => sprintf( esc_html__( 'Row %d — speed (empty = the speed above)', 'loopkit' ), $row ),
                'type'        => 'number',
                'default'     => '',
            );

            if ( $row > 1 ) {
                $direction['condition'] = array( 'mq_rows' => self::rows_from( $row ) );
                $speed['condition']     = array( 'mq_rows' => self::rows_from( $row ) );
            }

            $controls[] = $direction;
            $controls[] = $speed;
        }

        return $controls;
    }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_mq_items',
                    'title'    => __( 'Items', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array_merge(
                        array(
                            array(
                                'name'    => 'mq_type',
                                'label'   => __( 'Content', 'loopkit' ),
                                'type'    => 'select',
                                'default' => 'text',
                                'options' => array(
                                    'text'  => esc_html__( 'Text', 'loopkit' ),
                                    'image' => esc_html__( 'Images / logos', 'loopkit' ),
                                    'both'  => esc_html__( 'Image and text', 'loopkit' ),
                                ),
                            ),
                        ),
                        self::row_item_controls(),
                        array(
                            array(
                                'name'    => 'mq_separator',
                                'label'   => __( 'Separator Between Items', 'loopkit' ),
                                'type'    => 'text',
                                'default' => '•',
                            ),
                        )
                    ),
                ),
                array(
                    'id'       => 'section_mq_motion',
                    'title'    => __( 'Motion', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array_merge(
                        array(
                            array(
                                'name'        => 'mq_rows',
                                'label'       => __( 'Rows', 'loopkit' ),
                                'type'        => 'number',
                                'default'     => 1,
                                'min'         => 1,
                                'max'         => 5,
                                'description' => __( 'Add a row here and its own Items group appears above, under Items.', 'loopkit' ),
                            ),
                            array(
                                'name'        => 'mq_speed',
                                'label'       => __( 'Speed (pixels per second)', 'loopkit' ),
                                'type'        => 'slider',
                                'default'     => array( 'size' => 70, 'unit' => 'px' ),
                                'responsive'  => true,
                                'min'         => 5,
                                'max'         => 500,
                                'description' => __( 'Used by any row that has no speed of its own.', 'loopkit' ),
                            ),
                            array( 'name' => 'mq_pause_hover', 'label' => __( 'Pause on Hover', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                            array(
                                'name'        => 'mq_fill',
                                'label'       => __( 'Fill the width (no empty gap)', 'loopkit' ),
                                'type'        => 'switcher',
                                'default'     => 'yes',
                                'description' => __( 'Repeats the items as many times as the row needs, so the next copy follows straight on.', 'loopkit' ),
                            ),
                        ),
                        self::row_motion_controls(),
                        array(
                            array(
                                'name'       => 'mq_height',
                                'label'      => __( 'Height (rows that scroll up or down)', 'loopkit' ),
                                'type'       => 'slider',
                                'default'    => array( 'size' => 260, 'unit' => 'px' ),
                                'responsive' => true,
                                'min'        => 60,
                                'max'        => 900,
                            ),
                        )
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_mq_style',
                    'title'    => __( 'Marquee', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'mq_gap', 'label' => __( 'Space Between Items', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 48, 'unit' => 'px' ), 'responsive' => true, 'min' => 0, 'max' => 200 ),
                        array( 'name' => 'mq_row_gap', 'label' => __( 'Space Between Rows', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 16, 'unit' => 'px' ), 'responsive' => true, 'min' => 0, 'max' => 120 ),
                        array( 'name' => 'mq_bg', 'label' => __( 'Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'mq_padding', 'label' => __( 'Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'mq_radius', 'label' => __( 'Border Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'mq_text_color', 'label' => __( 'Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'mq_hover_color', 'label' => __( 'Text Hover Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'mq_typography', 'label' => __( 'Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'mq_stroke', 'label' => __( 'Outline Text', 'loopkit' ), 'type' => 'popover', 'popoverType' => 'text_stroke', 'responsive' => true ),
                        array( 'name' => 'mq_sep_color', 'label' => __( 'Separator Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'mq_icon_size', 'label' => __( 'Icon Size', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 18, 'unit' => 'px' ), 'responsive' => true, 'min' => 6, 'max' => 120 ),
                    ),
                ),
                array(
                    'id'       => 'section_mq_images',
                    'title'    => __( 'Images', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'mq_image_height', 'label' => __( 'Image Height', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 48, 'unit' => 'px' ), 'responsive' => true, 'min' => 10, 'max' => 400 ),
                        array( 'name' => 'mq_image_radius', 'label' => __( 'Image Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'mq_grayscale', 'label' => __( 'Grayscale Until Hover', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                        array( 'name' => 'mq_image_opacity', 'label' => __( 'Image Opacity', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 1, 'unit' => 'px' ), 'min' => 0, 'max' => 1, 'step' => 0.05 ),
                    ),
                ),
                array(
                    'id'       => 'section_mq_fade',
                    'title'    => __( 'Fading Edges', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'mq_fade', 'label' => __( 'Fade Edges', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                        array( 'name' => 'mq_fade_width', 'label' => __( 'Fade Width', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 80, 'unit' => 'px' ), 'responsive' => true, 'min' => 10, 'max' => 400, 'condition' => array( 'mq_fade' => 'yes' ) ),
                        array( 'name' => 'mq_fade_color', 'label' => __( 'Fade Colour', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff', 'condition' => array( 'mq_fade' => 'yes' ) ),
                    ),
                ),
            ),
        );
    }

    /**
     * One item.
     *
     * @param array  $item     Repeater row.
     * @param string $type     text | image | both.
     * @param string $separator Separator text.
     * @return string
     */
    private function item_html( $item, $type, $separator ) {
        $text  = (string) ( $item['text'] ?? '' );
        $image = $item['image']['url'] ?? '';
        $icon  = $item['icon'] ?? array();
        $link  = $item['link']['url'] ?? '';

        $inner = '';
        if ( 'text' !== $type && $image ) {
            $inner .= '<img class="lk-marquee-img" src="' . esc_url( $image ) . '" alt="' . esc_attr( $text ) . '" loading="lazy" decoding="async">';
        }
        if ( ! empty( $icon['value'] ) && 'image' !== $type ) {
            $library = $icon['library'] ?? 'fa-solid';
            $value   = $icon['value'];
            $classes = ( false !== strpos( $value, 'fa-' ) ? $library . ' ' . $value : 'fa-solid fa-' . $value );
            $inner  .= '<i class="' . esc_attr( $classes ) . ' lk-marquee-icon" aria-hidden="true"></i>';
        }
        if ( 'image' !== $type && '' !== $text ) {
            $inner .= '<span class="lk-marquee-text">' . esc_html( $text ) . '</span>';
        }
        if ( '' === $inner ) {
            return '';
        }

        if ( $link ) {
            $target = ! empty( $item['link']['is_external'] ) ? ' target="_blank"' : '';
            $rel    = ! empty( $item['link']['nofollow'] ) ? ' rel="nofollow"' : '';
            $inner  = '<a class="lk-marquee-link" href="' . esc_url( $link ) . '"' . $target . $rel . '>' . $inner . '</a>';
        }

        $html = '<div class="lk-marquee-item">' . $inner . '</div>';
        if ( '' !== trim( (string) $separator ) ) {
            $html .= '<div class="lk-marquee-sep" aria-hidden="true">' . esc_html( $separator ) . '</div>';
        }
        return $html;
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $type      = (string) ( $settings['mq_type'] ?? 'text' );
        $type      = in_array( $type, array( 'text', 'image', 'both' ), true ) ? $type : 'text';
        // Fall back to the control's own declared default rather than a literal here, so the
        // rendered separator can never drift from what the panel shows.
        $separator = (string) ( $settings['mq_separator'] ?? '•' );
        $row_count = max( 1, min( self::MAX_ROWS, (int) ( $settings['mq_rows'] ?? 1 ) ) );

        $speed_raw = $settings['mq_speed'] ?? array( 'size' => 70 );
        $speed     = is_array( $speed_raw ) ? (float) ( $speed_raw['size'] ?? 70 ) : (float) $speed_raw;
        $speed     = $speed > 0 ? $speed : 70;

        // Each row has its own items and its own direction; a row left empty falls back to
        // the first row's items so the strip is never blank.
        $first_items = $settings[ self::row_key( 'mq_items', 1 ) ] ?? array();
        $rows        = array();

        for ( $index = 1; $index <= $row_count; $index++ ) {
            $items = $settings[ self::row_key( 'mq_items', $index ) ] ?? array();
            if ( ! is_array( $items ) || ! $items ) {
                $items = $first_items;
            }
            if ( ! is_array( $items ) || ! $items ) {
                continue;
            }

            $html = '';
            foreach ( $items as $item ) {
                $html .= $this->item_html( $item, $type, $separator );
            }
            if ( '' === $html ) {
                continue;
            }

            $direction = (string) ( $settings[ self::row_key( 'mq_direction', $index ) ] ?? ( ( $index % 2 ) ? 'left' : 'right' ) );
            $direction = in_array( $direction, array( 'left', 'right', 'up', 'down' ), true ) ? $direction : 'left';

            $row_speed = $settings[ 'mq_speed_' . $index ] ?? '';

            $rows[] = array(
                'html'      => $html,
                'direction' => $direction,
                'speed'     => '' === $row_speed ? 0 : max( 1, (float) $row_speed ),
            );
        }

        if ( empty( $rows ) ) {
            return $editor_mode ? '<div class="lk-marquee-empty">' . esc_html__( 'Add items to the Marquee.', 'loopkit' ) . '</div>' : '';
        }

        $classes = array( 'lk-marquee' );
        if ( $this->switcher_on( $settings, 'mq_pause_hover', true ) ) {
            $classes[] = 'lk-marquee-pause';
        }
        if ( $this->switcher_on( $settings, 'mq_fade', false ) ) {
            $classes[] = 'lk-marquee-faded';
        }
        if ( $this->switcher_on( $settings, 'mq_grayscale', false ) ) {
            $classes[] = 'lk-marquee-grayscale';
        }
        if ( $this->switcher_on( $settings, 'mq_fill', true ) ) {
            $classes[] = 'lk-marquee-fill';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-lk-marquee-speed="<?php echo esc_attr( $speed ); ?>">
            <?php foreach ( $rows as $row ) : ?>
                <div class="lk-marquee-row lk-marquee-<?php echo esc_attr( $row['direction'] ); ?><?php echo in_array( $row['direction'], array( 'up', 'down' ), true ) ? ' lk-marquee-vertical-row' : ''; ?>"
                     <?php echo $row['speed'] ? 'data-lk-row-speed="' . esc_attr( $row['speed'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                    <div class="lk-marquee-track">
                        <div class="lk-marquee-group"><?php echo $row['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="lk-marquee-group" aria-hidden="true"><?php echo $row['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

}
