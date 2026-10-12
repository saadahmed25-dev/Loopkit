<?php
/**
 * Loopkit interactive widgets (Part 9):
 * Accordion, Toggle, Tabs, Counter, Progress Bar, Alert, Star Rating, Testimonial.
 *
 * The editor canvas renders these on the server (POST loopkit/v1/render-widget) with the
 * same classes, so canvas and front end markup are identical. Behaviour (open/close, tabs,
 * dismiss, count-up, bar fill) lives in assets/js/loopkit-widgets.js.
 *
 * Text fields named "title" and "content" keep basic HTML and line breaks when saved.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared helpers for the interactive widgets.
 */
abstract class Loopkit_Interactive_Widget extends Loopkit_Widget_Base {

    public function get_category() {
        return 'general';
    }

    public function get_context() {
        return 'all';
    }

    /**
     * Rich text (title/content fields): allowed HTML, paragraphs from line breaks.
     *
     * @param string $text Text.
     * @param bool   $autop Add paragraphs.
     * @return string
     */
    protected static function rich( $text, $autop = true ) {
        $text = wp_kses_post( (string) $text );
        return $autop ? wpautop( $text ) : $text;
    }

    protected static function c( $name, $label ) {
        return array( 'name' => $name, 'label' => $label, 'type' => 'color' );
    }

    protected static function t( $name, $label ) {
        return array( 'name' => $name, 'label' => $label, 'type' => 'typography' );
    }

    protected static function align( $name = 'align' ) {
        return array( 'name' => $name, 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'responsive' => true, 'options' => Loopkit_Widget_Post_Title::align_options( false ) );
    }

    protected static function slider( $name, $label, $min, $max, $units = array( 'px' ), $responsive = true ) {
        return array( 'name' => $name, 'label' => $label, 'type' => 'slider', 'responsive' => $responsive, 'range' => array( 'min' => $min, 'max' => $max ), 'units' => $units );
    }

    protected static function style_section( $id, $title, $controls, $condition = null ) {
        $s = array( 'id' => $id, 'title' => $title, 'tab' => 'style', 'controls' => $controls );
        if ( $condition ) {
            $s['condition'] = $condition;
        }
        return $s;
    }
}

/**
 * Accordion: one item open at a time.
 */
class Loopkit_Widget_Accordion extends Loopkit_Interactive_Widget {

    public function get_name() { return 'accordion'; }
    public function get_title() { return esc_html__( 'Accordion', 'loopkit' ); }
    public function get_icon() { return 'dashicons-list-view'; }
    public function get_keywords() { return array( 'accordion', 'faq', 'collapse', 'expand', 'toggle' ); }

    /** Whether several items may be open at once (Toggle). */
    protected function allows_multiple() { return false; }

    protected function register_controls() {
        $items = array();
        foreach ( array( 1, 2, 3 ) as $i ) {
            /* translators: %d: item number */
            $items[] = array( '_id' => 'item' . $i, 'title' => sprintf( esc_html__( 'Item #%d', 'loopkit' ), $i ), 'content' => esc_html__( 'Add your content here. Click the edit button to change this text.', 'loopkit' ) );
        }
        return array(
            'content' => array(
                array(
                    'id'       => 'section_items',
                    'title'    => __( 'Items', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'items',
                            'label'       => __( 'Items', 'loopkit' ),
                            'type'        => 'repeater',
                            'title_field' => 'title',
                            'default'     => $items,
                            'fields'      => array(
                                array( 'name' => 'title', 'label' => __( 'Title', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Item Title', 'loopkit' ) ),
                                array( 'name' => 'content', 'label' => __( 'Content', 'loopkit' ), 'type' => 'textarea', 'default' => esc_html__( 'Item content.', 'loopkit' ) ),
                            ),
                        ),
                        array(
                            'name'    => 'default_open',
                            'label'   => __( 'Open by Default', 'loopkit' ),
                            'type'    => 'select',
                            'default' => $this->allows_multiple() ? 'none' : 'first',
                            'options' => array( 'first' => esc_html__( 'First item', 'loopkit' ), 'none' => esc_html__( 'None', 'loopkit' ) ),
                        ),
                        array(
                            'name'    => 'title_tag',
                            'label'   => __( 'Title HTML Tag', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'div',
                            'options' => array( 'div' => 'div', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6' ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                self::style_section( 'section_style_items', esc_html__( 'Items', 'loopkit' ), array(
                    self::slider( 'space_between', esc_html__( 'Space Between', 'loopkit' ), 0, 60 ),
                    self::c( 'border_color', esc_html__( 'Border Color', 'loopkit' ) ),
                    self::slider( 'radius', esc_html__( 'Border Radius', 'loopkit' ), 0, 40, array( 'px' ), false ),
                ) ),
                self::style_section( 'section_style_title', esc_html__( 'Title', 'loopkit' ), array(
                    self::c( 'title_color', esc_html__( 'Color', 'loopkit' ) ),
                    self::c( 'title_bg', esc_html__( 'Background', 'loopkit' ) ),
                    self::c( 'title_active_color', esc_html__( 'Active Color', 'loopkit' ) ),
                    self::c( 'title_active_bg', esc_html__( 'Active Background', 'loopkit' ) ),
                    self::t( 'title_typography', esc_html__( 'Typography', 'loopkit' ) ),
                    array( 'name' => 'title_padding', 'label' => __( 'Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                ) ),
                self::style_section( 'section_style_content', esc_html__( 'Content', 'loopkit' ), array(
                    self::c( 'content_color', esc_html__( 'Color', 'loopkit' ) ),
                    self::c( 'content_bg', esc_html__( 'Background', 'loopkit' ) ),
                    self::t( 'content_typography', esc_html__( 'Typography', 'loopkit' ) ),
                    array( 'name' => 'content_padding', 'label' => __( 'Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                ) ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $items = $settings['items'] ?? $this->get_default_settings()['items'];
        if ( ! is_array( $items ) || ! $items ) {
            return '';
        }
        $tag = $settings['title_tag'] ?? 'div';
        if ( ! in_array( $tag, array( 'div', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
            $tag = 'div';
        }
        $open_first = ( 'first' === ( $settings['default_open'] ?? ( $this->allows_multiple() ? 'none' : 'first' ) ) );
        $uid        = sanitize_html_class( $el['id'] ?? wp_rand() );

        $html = '<div class="lk-accordion" data-lk-accordion data-multiple="' . ( $this->allows_multiple() ? 'yes' : 'no' ) . '">';
        foreach ( array_values( $items ) as $i => $item ) {
            $open  = ( 0 === $i && $open_first );
            $pid   = 'lk-acc-' . $uid . '-' . $i;
            $html .= '<div class="lk-accordion-item' . ( $open ? ' lk-open' : '' ) . '">';
            $html .= '<' . $tag . ' class="lk-accordion-heading"><button type="button" class="lk-accordion-title" aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $pid ) . '">';
            $html .= '<span class="lk-accordion-title-text">' . self::rich( $item['title'] ?? '', false ) . '</span><span class="lk-accordion-icon" aria-hidden="true"></span></button></' . $tag . '>';
            $html .= '<div class="lk-accordion-content" id="' . esc_attr( $pid ) . '"' . ( $open ? '' : ' hidden' ) . '>' . self::rich( $item['content'] ?? '' ) . '</div>';
            $html .= '</div>';
        }
        return $html . '</div>';
    }
}

/**
 * Toggle: like Accordion, but any number of items can be open.
 */
class Loopkit_Widget_Toggle extends Loopkit_Widget_Accordion {
    public function get_name() { return 'toggle'; }
    public function get_title() { return esc_html__( 'Toggle', 'loopkit' ); }
    public function get_icon() { return 'dashicons-arrow-down-alt2'; }
    public function get_keywords() { return array( 'toggle', 'faq', 'collapse', 'expand', 'accordion' ); }
    protected function allows_multiple() { return true; }
}

/**
 * Tabs.
 */
class Loopkit_Widget_Tabs extends Loopkit_Interactive_Widget {

    public function get_name() { return 'tabs'; }
    public function get_title() { return esc_html__( 'Tabs', 'loopkit' ); }
    public function get_icon() { return 'dashicons-index-card'; }
    public function get_keywords() { return array( 'tabs', 'tab', 'panels', 'switch' ); }

    protected function register_controls() {
        $items = array();
        foreach ( array( 1, 2, 3 ) as $i ) {
            /* translators: %d: tab number */
            $items[] = array( '_id' => 'tab' . $i, 'title' => sprintf( esc_html__( 'Tab #%d', 'loopkit' ), $i ), 'content' => esc_html__( 'Add your content here. Click the edit button to change this text.', 'loopkit' ) );
        }
        return array(
            'content' => array(
                array(
                    'id'       => 'section_tabs',
                    'title'    => __( 'Tabs', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'items',
                            'label'       => __( 'Tabs', 'loopkit' ),
                            'type'        => 'repeater',
                            'title_field' => 'title',
                            'default'     => $items,
                            'fields'      => array(
                                array( 'name' => 'title', 'label' => __( 'Title', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Tab Title', 'loopkit' ) ),
                                array( 'name' => 'content', 'label' => __( 'Content', 'loopkit' ), 'type' => 'textarea', 'default' => esc_html__( 'Tab content.', 'loopkit' ) ),
                            ),
                        ),
                        array( 'name' => 'layout', 'label' => __( 'Position', 'loopkit' ), 'type' => 'select', 'default' => 'horizontal', 'options' => array( 'horizontal' => esc_html__( 'Horizontal', 'loopkit' ), 'vertical' => esc_html__( 'Vertical', 'loopkit' ) ) ),
                    ),
                ),
            ),
            'style'   => array(
                self::style_section( 'section_style_tabs', esc_html__( 'Tabs', 'loopkit' ), array(
                    self::c( 'border_color', esc_html__( 'Border Color', 'loopkit' ) ),
                    self::c( 'title_color', esc_html__( 'Title Color', 'loopkit' ) ),
                    self::c( 'title_active_color', esc_html__( 'Active Title Color', 'loopkit' ) ),
                    self::c( 'title_active_bg', esc_html__( 'Active Title Background', 'loopkit' ) ),
                    self::t( 'title_typography', esc_html__( 'Title Typography', 'loopkit' ) ),
                    self::c( 'content_color', esc_html__( 'Content Color', 'loopkit' ) ),
                    self::c( 'content_bg', esc_html__( 'Content Background', 'loopkit' ) ),
                    self::t( 'content_typography', esc_html__( 'Content Typography', 'loopkit' ) ),
                    array( 'name' => 'content_padding', 'label' => __( 'Content Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                ) ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $items = $settings['items'] ?? $this->get_default_settings()['items'];
        if ( ! is_array( $items ) || ! $items ) {
            return '';
        }
        $items  = array_values( $items );
        $layout = ( 'vertical' === ( $settings['layout'] ?? 'horizontal' ) ) ? 'vertical' : 'horizontal';
        $uid    = sanitize_html_class( $el['id'] ?? wp_rand() );
        $nav    = '';
        $panels = '';
        foreach ( $items as $i => $item ) {
            $active  = ( 0 === $i );
            $tid     = 'lk-tab-' . $uid . '-' . $i;
            $pid     = 'lk-tabpanel-' . $uid . '-' . $i;
            $nav    .= '<button type="button" class="lk-tab-title' . ( $active ? ' lk-active' : '' ) . '" role="tab" id="' . esc_attr( $tid ) . '" aria-selected="' . ( $active ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $pid ) . '" tabindex="' . ( $active ? '0' : '-1' ) . '">' . self::rich( $item['title'] ?? '', false ) . '</button>';
            $panels .= '<div class="lk-tab-panel' . ( $active ? ' lk-active' : '' ) . '" role="tabpanel" id="' . esc_attr( $pid ) . '" aria-labelledby="' . esc_attr( $tid ) . '"' . ( $active ? '' : ' hidden' ) . '>' . self::rich( $item['content'] ?? '' ) . '</div>';
        }
        return '<div class="lk-tabs lk-tabs-' . $layout . '" data-lk-tabs><div class="lk-tabs-nav" role="tablist">' . $nav . '</div><div class="lk-tabs-panels">' . $panels . '</div></div>';
    }
}

/**
 * Counter: counts up to a number when scrolled into view.
 */
class Loopkit_Widget_Counter extends Loopkit_Interactive_Widget {

    public function get_name() { return 'counter'; }
    public function get_title() { return esc_html__( 'Counter', 'loopkit' ); }
    public function get_icon() { return 'dashicons-performance'; }
    public function get_keywords() { return array( 'counter', 'number', 'stats', 'count' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_counter',
                    'title'    => __( 'Counter', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'start', 'label' => __( 'Starting Number', 'loopkit' ), 'type' => 'number', 'default' => 0 ),
                        array( 'name' => 'end', 'label' => __( 'Ending Number', 'loopkit' ), 'type' => 'number', 'default' => 100 ),
                        array( 'name' => 'prefix', 'label' => __( 'Number Prefix', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                        array( 'name' => 'suffix', 'label' => __( 'Number Suffix', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                        array( 'name' => 'duration', 'label' => __( 'Animation Duration (ms)', 'loopkit' ), 'type' => 'number', 'default' => 2000, 'min' => 100, 'max' => 10000 ),
                        array( 'name' => 'separator', 'label' => __( 'Thousand Separator', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'title', 'label' => __( 'Title', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Cool Number', 'loopkit' ) ),
                    ),
                ),
            ),
            'style'   => array(
                self::style_section( 'section_style_counter', esc_html__( 'Counter', 'loopkit' ), array(
                    self::align(),
                    self::c( 'number_color', esc_html__( 'Number Color', 'loopkit' ) ),
                    self::t( 'number_typography', esc_html__( 'Number Typography', 'loopkit' ) ),
                    self::c( 'title_color', esc_html__( 'Title Color', 'loopkit' ) ),
                    self::t( 'title_typography', esc_html__( 'Title Typography', 'loopkit' ) ),
                ) ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $start = isset( $settings['start'] ) && is_numeric( $settings['start'] ) ? (float) $settings['start'] : 0;
        $end   = isset( $settings['end'] ) && is_numeric( $settings['end'] ) ? (float) $settings['end'] : 100;
        $dur   = isset( $settings['duration'] ) && is_numeric( $settings['duration'] ) ? max( 100, (int) $settings['duration'] ) : 2000;
        $sep   = ( $this->switcher_on( $settings, 'separator', true ) );
        $dec   = ( floor( $end ) == $end ) ? 0 : min( 3, strlen( substr( strrchr( (string) $end, '.' ), 1 ) ) );
        $shown = number_format( $end, $dec, '.', $sep ? ',' : '' );

        $html  = '<div class="lk-counter" data-lk-counter data-start="' . esc_attr( $start ) . '" data-end="' . esc_attr( $end ) . '" data-duration="' . esc_attr( $dur ) . '" data-decimals="' . esc_attr( $dec ) . '" data-separator="' . ( $sep ? 'yes' : 'no' ) . '">';
        $html .= '<div class="lk-counter-number-wrapper"><span class="lk-counter-prefix">' . esc_html( $settings['prefix'] ?? '' ) . '</span><span class="lk-counter-number">' . esc_html( $shown ) . '</span><span class="lk-counter-suffix">' . esc_html( $settings['suffix'] ?? '' ) . '</span></div>';
        $title = $settings['title'] ?? __( 'Cool Number', 'loopkit' );
        if ( '' !== trim( (string) $title ) ) {
            $html .= '<div class="lk-counter-title">' . self::rich( $title, false ) . '</div>';
        }
        return $html . '</div>';
    }
}

/**
 * Progress Bar.
 */
class Loopkit_Widget_Progress_Bar extends Loopkit_Interactive_Widget {

    public function get_name() { return 'progress-bar'; }
    public function get_title() { return esc_html__( 'Progress Bar', 'loopkit' ); }
    public function get_icon() { return 'dashicons-chart-bar'; }
    public function get_keywords() { return array( 'progress', 'bar', 'skill', 'percent' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_progress',
                    'title'    => __( 'Progress Bar', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'title', 'label' => __( 'Title', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'My Skill', 'loopkit' ) ),
                        array( 'name' => 'percent', 'label' => __( 'Percentage', 'loopkit' ), 'type' => 'number', 'default' => 70, 'min' => 0, 'max' => 100 ),
                        array( 'name' => 'show_percent', 'label' => __( 'Display Percentage', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'inner_text', 'label' => __( 'Inner Text', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                    ),
                ),
            ),
            'style'   => array(
                self::style_section( 'section_style_progress', esc_html__( 'Bar', 'loopkit' ), array(
                    self::c( 'bar_color', esc_html__( 'Bar Color', 'loopkit' ) ),
                    self::c( 'track_color', esc_html__( 'Track Color', 'loopkit' ) ),
                    self::slider( 'height', esc_html__( 'Height', 'loopkit' ), 2, 60 ),
                    self::slider( 'radius', esc_html__( 'Border Radius', 'loopkit' ), 0, 30, array( 'px' ), false ),
                    self::c( 'inner_color', esc_html__( 'Inner Text Color', 'loopkit' ) ),
                    self::c( 'title_color', esc_html__( 'Title Color', 'loopkit' ) ),
                    self::t( 'title_typography', esc_html__( 'Title Typography', 'loopkit' ) ),
                    self::c( 'percent_color', esc_html__( 'Percentage Color', 'loopkit' ) ),
                ) ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $pct   = isset( $settings['percent'] ) && is_numeric( $settings['percent'] ) ? max( 0, min( 100, (float) $settings['percent'] ) ) : 70;
        $pct_s = rtrim( rtrim( number_format( $pct, 2, '.', '' ), '0' ), '.' );
        $title = (string) ( $settings['title'] ?? __( 'My Skill', 'loopkit' ) );
        $show  = ( $this->switcher_on( $settings, 'show_percent', true ) );

        $html = '<div class="lk-progress" data-lk-progress data-percent="' . esc_attr( $pct_s ) . '">';
        if ( '' !== trim( $title ) || $show ) {
            $html .= '<div class="lk-progress-header"><span class="lk-progress-title">' . self::rich( $title, false ) . '</span>' . ( $show ? '<span class="lk-progress-percent">' . esc_html( $pct_s ) . '%</span>' : '' ) . '</div>';
        }
        $html .= '<div class="lk-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( $pct_s ) . '"><div class="lk-progress-fill" style="width:' . esc_attr( $pct_s ) . '%">';
        $inner = (string) ( $settings['inner_text'] ?? '' );
        if ( '' !== $inner ) {
            $html .= '<span class="lk-progress-inner">' . esc_html( $inner ) . '</span>';
        }
        return $html . '</div></div></div>';
    }
}

/**
 * Alert.
 */
class Loopkit_Widget_Alert extends Loopkit_Interactive_Widget {

    public function get_name() { return 'alert'; }
    public function get_title() { return esc_html__( 'Alert', 'loopkit' ); }
    public function get_icon() { return 'dashicons-warning'; }
    public function get_keywords() { return array( 'alert', 'notice', 'message', 'warning', 'info' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_alert',
                    'title'    => __( 'Alert', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'alert_type', 'label' => __( 'Type', 'loopkit' ), 'type' => 'select', 'default' => 'info', 'options' => array( 'info' => esc_html__( 'Info', 'loopkit' ), 'success' => esc_html__( 'Success', 'loopkit' ), 'warning' => esc_html__( 'Warning', 'loopkit' ), 'danger' => esc_html__( 'Danger', 'loopkit' ) ) ),
                        array( 'name' => 'title', 'label' => __( 'Title', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'This is an Alert', 'loopkit' ) ),
                        array( 'name' => 'content', 'label' => __( 'Description', 'loopkit' ), 'type' => 'textarea', 'default' => esc_html__( 'I am a description. Click the edit button to change this text.', 'loopkit' ) ),
                        array( 'name' => 'dismiss', 'label' => __( 'Dismiss Button', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                    ),
                ),
            ),
            'style'   => array(
                self::style_section( 'section_style_alert', esc_html__( 'Alert', 'loopkit' ), array(
                    self::c( 'bg_color', esc_html__( 'Background Color', 'loopkit' ) ),
                    self::c( 'border_color', esc_html__( 'Border Color', 'loopkit' ) ),
                    self::c( 'title_color', esc_html__( 'Title Color', 'loopkit' ) ),
                    self::t( 'title_typography', esc_html__( 'Title Typography', 'loopkit' ) ),
                    self::c( 'description_color', esc_html__( 'Description Color', 'loopkit' ) ),
                    self::t( 'description_typography', esc_html__( 'Description Typography', 'loopkit' ) ),
                ) ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $type = $settings['alert_type'] ?? 'info';
        if ( ! in_array( $type, array( 'info', 'success', 'warning', 'danger' ), true ) ) {
            $type = 'info';
        }
        $title = (string) ( $settings['title'] ?? __( 'This is an Alert', 'loopkit' ) );
        $desc  = (string) ( $settings['content'] ?? __( 'I am a description. Click the edit button to change this text.', 'loopkit' ) );
        $html  = '<div class="lk-alert lk-alert-' . $type . '" role="alert"><div class="lk-alert-body">';
        if ( '' !== trim( $title ) ) {
            $html .= '<div class="lk-alert-title">' . self::rich( $title, false ) . '</div>';
        }
        if ( '' !== trim( $desc ) ) {
            $html .= '<div class="lk-alert-description">' . self::rich( $desc ) . '</div>';
        }
        $html .= '</div>';
        if ( $this->switcher_on( $settings, 'dismiss', true ) ) {
            $html .= '<button type="button" class="lk-alert-dismiss" aria-label="' . esc_attr__( 'Dismiss this alert', 'loopkit' ) . '"><span aria-hidden="true">&times;</span></button>';
        }
        return $html . '</div>';
    }
}

/**
 * Star Rating.
 */
class Loopkit_Widget_Star_Rating extends Loopkit_Interactive_Widget {

    public function get_name() { return 'star-rating'; }
    public function get_title() { return esc_html__( 'Star Rating', 'loopkit' ); }
    public function get_icon() { return 'dashicons-star-half'; }
    public function get_keywords() { return array( 'star', 'rating', 'review', 'score' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_rating',
                    'title'    => __( 'Rating', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'scale', 'label' => __( 'Rating Scale', 'loopkit' ), 'type' => 'select', 'default' => '5', 'options' => array( '5' => '0-5', '10' => '0-10' ) ),
                        array( 'name' => 'rating', 'label' => __( 'Rating', 'loopkit' ), 'type' => 'number', 'default' => 5, 'min' => 0, 'max' => 10, 'step' => 0.1 ),
                        array( 'name' => 'title', 'label' => __( 'Title', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                    ),
                ),
            ),
            'style'   => array(
                self::style_section( 'section_style_rating', esc_html__( 'Stars', 'loopkit' ), array(
                    self::align(),
                    self::slider( 'star_size', esc_html__( 'Size', 'loopkit' ), 8, 80 ),
                    self::slider( 'star_gap', esc_html__( 'Spacing', 'loopkit' ), 0, 30 ),
                    self::c( 'star_color', esc_html__( 'Color', 'loopkit' ) ),
                    self::c( 'star_empty_color', esc_html__( 'Unmarked Color', 'loopkit' ) ),
                    self::c( 'title_color', esc_html__( 'Title Color', 'loopkit' ) ),
                    self::t( 'title_typography', esc_html__( 'Title Typography', 'loopkit' ) ),
                ) ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $scale  = ( '10' === (string) ( $settings['scale'] ?? '5' ) ) ? 10 : 5;
        $rating = isset( $settings['rating'] ) && is_numeric( $settings['rating'] ) ? max( 0, min( $scale, (float) $settings['rating'] ) ) : 5;
        $label  = sprintf( /* translators: 1: rating, 2: scale */ __( 'Rated %1$s out of %2$s', 'loopkit' ), rtrim( rtrim( number_format( $rating, 1, '.', '' ), '0' ), '.' ), $scale );

        $html  = '<div class="lk-star-rating-wrapper">';
        $title = (string) ( $settings['title'] ?? '' );
        if ( '' !== trim( $title ) ) {
            $html .= '<div class="lk-star-rating-title">' . self::rich( $title, false ) . '</div>';
        }
        $html .= '<div class="lk-star-rating" role="img" aria-label="' . esc_attr( $label ) . '">';
        for ( $i = 1; $i <= $scale; $i++ ) {
            $fill  = max( 0, min( 1, $rating - ( $i - 1 ) ) ) * 100;
            $html .= '<span class="lk-star"><span class="lk-star-fill" style="width:' . esc_attr( rtrim( rtrim( number_format( $fill, 2, '.', '' ), '0' ), '.' ) ) . '%"></span></span>';
        }
        return $html . '</div></div>';
    }
}

/**
 * Testimonial.
 */
class Loopkit_Widget_Testimonial extends Loopkit_Interactive_Widget {

    public function get_name() { return 'testimonial'; }
    public function get_title() { return esc_html__( 'Testimonial', 'loopkit' ); }
    public function get_icon() { return 'dashicons-format-quote'; }
    public function get_keywords() { return array( 'testimonial', 'quote', 'review', 'client', 'feedback' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_testimonial',
                    'title'    => __( 'Testimonial', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'content', 'label' => __( 'Content', 'loopkit' ), 'type' => 'textarea', 'default' => esc_html__( 'Working with this team was a great experience. They listened, delivered on time, and the result exceeded our expectations.', 'loopkit' ) ),
                        array( 'name' => 'image', 'label' => __( 'Image', 'loopkit' ), 'type' => 'media' ),
                        array( 'name' => 'name', 'label' => __( 'Name', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'John Doe', 'loopkit' ) ),
                        array( 'name' => 'job', 'label' => __( 'Title', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Designer', 'loopkit' ) ),
                        array( 'name' => 'image_position', 'label' => __( 'Image Position', 'loopkit' ), 'type' => 'select', 'default' => 'aside', 'options' => array( 'aside' => esc_html__( 'Aside', 'loopkit' ), 'top' => esc_html__( 'Top', 'loopkit' ) ) ),
                    ),
                ),
            ),
            'style'   => array(
                self::style_section( 'section_style_testimonial', esc_html__( 'Testimonial', 'loopkit' ), array(
                    self::align(),
                    self::c( 'content_color', esc_html__( 'Content Color', 'loopkit' ) ),
                    self::t( 'content_typography', esc_html__( 'Content Typography', 'loopkit' ) ),
                    self::slider( 'image_size', esc_html__( 'Image Size', 'loopkit' ), 20, 200 ),
                    self::c( 'name_color', esc_html__( 'Name Color', 'loopkit' ) ),
                    self::t( 'name_typography', esc_html__( 'Name Typography', 'loopkit' ) ),
                    self::c( 'job_color', esc_html__( 'Title Color', 'loopkit' ) ),
                    self::t( 'job_typography', esc_html__( 'Title Typography', 'loopkit' ) ),
                ) ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $pos     = ( 'top' === ( $settings['image_position'] ?? 'aside' ) ) ? 'top' : 'aside';
        $content = (string) ( $settings['content'] ?? __( 'Working with this team was a great experience. They listened, delivered on time, and the result exceeded our expectations.', 'loopkit' ) );
        $name    = (string) ( $settings['name'] ?? __( 'John Doe', 'loopkit' ) );
        $job     = (string) ( $settings['job'] ?? __( 'Designer', 'loopkit' ) );
        $img     = $settings['image'] ?? array();
        $img_url = is_array( $img ) ? ( $img['url'] ?? '' ) : (string) $img;
        if ( is_array( $img ) && ! empty( $img['id'] ) ) {
            $src     = wp_get_attachment_image_src( (int) $img['id'], 'thumbnail' );
            $img_url = $src ? $src[0] : $img_url;
        }

        $html = '<div class="lk-testimonial lk-testimonial-image-' . $pos . '">';
        if ( '' !== trim( $content ) ) {
            $html .= '<div class="lk-testimonial-content">' . self::rich( $content ) . '</div>';
        }
        $html .= '<div class="lk-testimonial-meta">';
        if ( $img_url ) {
            $html .= '<div class="lk-testimonial-image"><img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $name ) . '" loading="lazy"></div>';
        }
        $html .= '<div class="lk-testimonial-details">';
        if ( '' !== trim( $name ) ) {
            $html .= '<div class="lk-testimonial-name">' . esc_html( $name ) . '</div>';
        }
        if ( '' !== trim( $job ) ) {
            $html .= '<div class="lk-testimonial-job">' . esc_html( $job ) . '</div>';
        }
        return $html . '</div></div></div>';
    }
}
