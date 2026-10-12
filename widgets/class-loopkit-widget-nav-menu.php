<?php
/**
 * Loopkit Nav Menu Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Custom walker for Loopkit nav menu output.
 */
class Loopkit_Nav_Menu_Walker extends Walker_Nav_Menu {

    /**
     * Submenu indicator flag.
     *
     * @var bool
     */
    private $show_indicator = true;

    /**
     * Constructor.
     *
     * @param bool $show_indicator Whether to display submenu indicators.
     */
    public function __construct( $show_indicator = true ) {
        $this->show_indicator = $show_indicator;
    }

    /**
     * Starts the list before the elements are added.
     */
    public function start_lvl( &$output, $depth = 0, $args = null ) {
        $indent = str_repeat( "\t", $depth );
        $output .= "\n$indent<ul class=\"lk-sub-menu lk-sub-depth-{$depth}\">\n";
    }

    /**
     * Ends the list of after the elements are added.
     */
    public function end_lvl( &$output, $depth = 0, $args = null ) {
        $indent = str_repeat( "\t", $depth );
        $output .= "$indent</ul>\n";
    }

    /**
     * Starts the element output.
     */
    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';

        $has_children = in_array( 'menu-item-has-children', $item->classes, true );

        $classes   = empty( $item->classes ) ? array() : (array) $item->classes;
        $classes[] = 'lk-menu-item';
        $classes[] = 'lk-item-depth-' . $depth;

        if ( $has_children ) {
            $classes[] = 'lk-has-children';
        }
        if ( in_array( 'current-menu-item', $classes, true ) || in_array( 'current-menu-ancestor', $classes, true ) ) {
            $classes[] = 'lk-item-active';
        }

        $class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth ) );
        $class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

        $output .= $indent . '<li' . $class_names . '>';

        $atts           = array();
        $atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
        $atts['target'] = ! empty( $item->target ) ? $item->target : '';
        if ( '_blank' === $item->target && empty( $item->xfn ) ) {
            $atts['rel'] = 'noopener noreferrer';
        } else {
            $atts['rel'] = $item->xfn;
        }
        $atts['href']         = ! empty( $item->url ) ? $item->url : '';
        $atts['aria-current'] = $item->current ? 'page' : '';
        $atts['class']        = 'lk-menu-link';

        if ( $has_children ) {
            $atts['aria-haspopup'] = 'true';
            $atts['aria-expanded'] = 'false';
        }

        $atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

        $attributes = '';
        foreach ( $atts as $attr => $value ) {
            if ( is_scalar( $value ) && '' !== $value && false !== $value ) {
                $value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
                $attributes .= ' ' . $attr . '="' . $value . '"';
            }
        }

        $title = apply_filters( 'the_title', $item->title, $item->ID );
        $title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );

        $item_output = $args->before ?? '';
        $item_output .= '<a' . $attributes . '>';
        $item_output .= '<span class="lk-link-title">' . ( $args->link_before ?? '' ) . $title . ( $args->link_after ?? '' ) . '</span>';

        if ( $has_children && $this->show_indicator ) {
            $item_output .= '<span class="lk-submenu-indicator" aria-hidden="true"><svg viewBox="0 0 10 6" class="lk-indicator-icon"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
        }

        $item_output .= '</a>';
        $item_output .= $args->after ?? '';

        $output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
    }

    /**
     * Ends the element output.
     */
    public function end_el( &$output, $item, $depth = 0, $args = null ) {
        $output .= "</li>\n";
    }
}

class Loopkit_Widget_Nav_Menu extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'nav-menu';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Nav Menu', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-menu';
    }

    /**
     * Get widget category.
     *
     * @return string
     */
    public function get_category() {
        return 'site';
    }

    /**
     * Get search keywords.
     *
     * @return array
     */
    public function get_keywords() {
        return array( 'nav', 'menu', 'navigation', 'navbar', 'header', 'links', 'dropdown', 'hamburger' );
    }

    /**
     * Get widget context.
     *
     * @return string
     */
    public function get_context() {
        return 'all';
    }

    /**
     * Get menu options for select control.
     *
     * @return array
     */
    private function get_menu_options() {
        $options = array();
        $menus = wp_get_nav_menus();
        if ( ! empty( $menus ) ) {
            foreach ( $menus as $m ) {
                $options[ (string) $m->term_id ] = $m->name;
            }
        }
        return $options;
    }

    /**
     * Register controls.
     *
     * @return array
     */
    protected function register_controls() {
        $menu_options = $this->get_menu_options();
        $default_menu = ! empty( $menu_options ) ? array_key_first( $menu_options ) : '';

        return array(
            'content' => array(
                array(
                    'id'       => 'section_nav_menu_content',
                    'title'    => __( 'Menu', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'menu',
                            'label'   => __( 'Select Menu', 'loopkit' ),
                            'type'    => 'select',
                            'default' => $default_menu,
                            'options' => $menu_options,
                        ),
                        array(
                            'name'       => 'layout',
                            'label'      => __( 'Layout', 'loopkit' ),
                            'type'       => 'select',
                            'responsive' => true,
                            'default'    => 'horizontal',
                            'options'    => array(
                                'horizontal' => esc_html__( 'Horizontal', 'loopkit' ),
                                'vertical'   => esc_html__( 'Vertical', 'loopkit' ),
                                'dropdown'   => esc_html__( 'Dropdown', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'    => 'pointer',
                            'label'   => __( 'Pointer', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'underline',
                            'options' => array(
                                'none'        => esc_html__( 'None', 'loopkit' ),
                                'underline'   => esc_html__( 'Underline', 'loopkit' ),
                                'overline'    => esc_html__( 'Overline', 'loopkit' ),
                                'double-line' => esc_html__( 'Double Line', 'loopkit' ),
                                'framed'      => esc_html__( 'Framed', 'loopkit' ),
                                'background'  => esc_html__( 'Background', 'loopkit' ),
                                'text'        => esc_html__( 'Text', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'    => 'animation',
                            'label'   => __( 'Animation', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'fade',
                            'options' => array(
                                'none'    => esc_html__( 'None', 'loopkit' ),
                                'fade'    => esc_html__( 'Fade', 'loopkit' ),
                                'slide'   => esc_html__( 'Slide', 'loopkit' ),
                                'grow'    => esc_html__( 'Grow', 'loopkit' ),
                                'drop-in' => esc_html__( 'Drop In', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'    => 'submenu_indicator',
                            'label'   => __( 'Submenu Indicator', 'loopkit' ),
                            'type'    => 'switcher',
                            'default' => 'yes',
                        ),
                        array(
                            'name'       => 'mobile_breakpoint',
                            'label'      => __( 'Mobile Breakpoint', 'loopkit' ),
                            'type'       => 'select',
                            'default'    => 'tablet',
                            'options'    => array(
                                'laptop' => esc_html__( 'Laptop (1366px)', 'loopkit' ),
                                'tablet' => esc_html__( 'Tablet (1024px)', 'loopkit' ),
                                'mobile' => esc_html__( 'Mobile (767px)', 'loopkit' ),
                                'none'   => esc_html__( 'None', 'loopkit' ),
                            ),
                        ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_style_main_menu',
                    'title'    => __( 'Main Menu', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'  => 'typography',
                            'label' => __( 'Typography', 'loopkit' ),
                            'type'  => 'typography',
                        ),
                        array(
                            'name'  => 'text_color',
                            'label' => __( 'Text Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'text_color_hover',
                            'label' => __( 'Hover Text Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'menu_bg',
                            'label' => __( 'Background', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'menu_bg_hover',
                            'label' => __( 'Hover Background', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'       => 'item_gap',
                            'label'      => __( 'Space Between Items', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'px' => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
                            ),
                            'default'    => array( 'size' => '20', 'unit' => 'px' ),
                        ),
                        array(
                            'name'       => 'item_padding',
                            'label'      => __( 'Item Padding', 'loopkit' ),
                            'type'       => 'dimension',
                            'responsive' => true,
                            'default'    => array( 'top' => '8', 'right' => '12', 'bottom' => '8', 'left' => '12', 'unit' => 'px', 'isLinked' => false ),
                        ),
                        array(
                            'name'       => 'border_radius',
                            'label'      => __( 'Border Radius', 'loopkit' ),
                            'type'       => 'dimension',
                            'responsive' => true,
                            'default'    => array( 'top' => '', 'right' => '', 'bottom' => '', 'left' => '', 'unit' => 'px', 'isLinked' => true ),
                        ),
                    ),
                ),
                array(
                    'id'       => 'section_style_pointer',
                    'title'    => __( 'Pointer', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'  => 'pointer_color',
                            'label' => __( 'Pointer Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'pointer_color_hover',
                            'label' => __( 'Hover Pointer Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'    => 'pointer_width',
                            'label'   => __( 'Pointer Width', 'loopkit' ),
                            'type'    => 'slider',
                            'range'   => array(
                                'px' => array( 'min' => 1, 'max' => 20, 'step' => 1 ),
                            ),
                            'default' => array( 'size' => '2', 'unit' => 'px' ),
                        ),
                    ),
                ),
                array(
                    'id'       => 'section_style_dropdown',
                    'title'    => __( 'Dropdown Submenu', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'  => 'dropdown_typography',
                            'label' => __( 'Typography', 'loopkit' ),
                            'type'  => 'typography',
                        ),
                        array(
                            'name'  => 'dropdown_bg',
                            'label' => __( 'Background Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'dropdown_text_color',
                            'label' => __( 'Text Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'dropdown_text_color_hover',
                            'label' => __( 'Hover Text Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'dropdown_item_bg_hover',
                            'label' => __( 'Hover Item Background', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'       => 'dropdown_width',
                            'label'      => __( 'Dropdown Width', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'px' => array( 'min' => 120, 'max' => 500, 'step' => 1 ),
                            ),
                            'default'    => array( 'size' => '220', 'unit' => 'px' ),
                        ),
                        array(
                            'name'       => 'dropdown_padding',
                            'label'      => __( 'Padding', 'loopkit' ),
                            'type'       => 'dimension',
                            'responsive' => true,
                            'default'    => array( 'top' => '8', 'right' => '0', 'bottom' => '8', 'left' => '0', 'unit' => 'px', 'isLinked' => false ),
                        ),
                        array(
                            'name'       => 'dropdown_border_radius',
                            'label'      => __( 'Border Radius', 'loopkit' ),
                            'type'       => 'dimension',
                            'responsive' => true,
                            'default'    => array( 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px', 'isLinked' => true ),
                        ),
                        array(
                            'name'  => 'dropdown_box_shadow',
                            'label' => __( 'Box Shadow', 'loopkit' ),
                            'type'  => 'box_shadow',
                        ),
                    ),
                ),
                array(
                    'id'       => 'section_style_hamburger',
                    'title'    => __( 'Hamburger Button', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'       => 'hamburger_size',
                            'label'      => __( 'Icon Size', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'px' => array( 'min' => 12, 'max' => 60, 'step' => 1 ),
                            ),
                            'default'    => array( 'size' => '24', 'unit' => 'px' ),
                        ),
                        array(
                            'name'  => 'hamburger_color',
                            'label' => __( 'Icon Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'  => 'hamburger_bg',
                            'label' => __( 'Background Color', 'loopkit' ),
                            'type'  => 'color',
                        ),
                        array(
                            'name'       => 'hamburger_border_radius',
                            'label'      => __( 'Border Radius', 'loopkit' ),
                            'type'       => 'dimension',
                            'responsive' => true,
                            'default'    => array( 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px', 'isLinked' => true ),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Render widget output.
     *
     * @param array $settings    Widget settings.
     * @param array $el          Element data.
     * @param bool  $editor_mode Whether rendering in editor mode.
     * @return string HTML output.
     */
    public function render( $settings, $el, $editor_mode = false ) {
        $menu_id = ! empty( $settings['menu'] ) ? $settings['menu'] : '';
        $layout  = ! empty( $settings['layout'] ) ? $settings['layout'] : 'horizontal';
        $pointer = ! empty( $settings['pointer'] ) ? $settings['pointer'] : 'underline';
        $anim    = ! empty( $settings['animation'] ) ? $settings['animation'] : 'fade';
        $bp      = ! empty( $settings['mobile_breakpoint'] ) ? $settings['mobile_breakpoint'] : 'tablet';
        $show_ind = ! empty( $settings['submenu_indicator'] ) && ( 'yes' === $settings['submenu_indicator'] || true === $settings['submenu_indicator'] );

        $menus = wp_get_nav_menus();
        if ( empty( $menus ) ) {
            if ( $editor_mode ) {
                return '<div class="lk-nav-menu-empty">' .
                       '  <span class="dashicons dashicons-menu"></span>' .
                       '  <p>' . esc_html__( 'No menus found.', 'loopkit' ) . ' <a href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '" target="_blank">' . esc_html__( 'Create a menu in Appearance → Menus', 'loopkit' ) . '</a></p>' .
                       '</div>';
            }
            return '';
        }

        // If no menu chosen, pick first
        if ( empty( $menu_id ) ) {
            $menu_id = $menus[0]->term_id;
        }

        $classes = array(
            'lk-nav-menu-wrapper',
            'lk-nav-layout-' . sanitize_html_class( $layout ),
            'lk-pointer-' . sanitize_html_class( $pointer ),
            'lk-anim-' . sanitize_html_class( $anim ),
            'lk-bp-' . sanitize_html_class( $bp ),
        );

        if ( $editor_mode ) {
            $classes[] = 'lk-in-editor';
        }

        $walker = new Loopkit_Nav_Menu_Walker( $show_ind );

        $menu_html = wp_nav_menu( array(
            'menu'            => $menu_id,
            'container'       => 'nav',
            'container_class' => 'lk-nav-menu-container',
            'menu_class'      => 'lk-nav-menu',
            'walker'          => $walker,
            'fallback_cb'     => false,
            'echo'            => false,
            'depth'           => 4,
        ) );

        if ( empty( $menu_html ) && $editor_mode ) {
            return '<div class="lk-nav-menu-empty"><p>' . esc_html__( 'Selected menu has no items.', 'loopkit' ) . '</p></div>';
        }

        // Hamburger toggle button
        $toggle_html = '<button type="button" class="lk-nav-menu-toggle" aria-label="' . esc_attr__( 'Toggle menu', 'loopkit' ) . '" aria-expanded="false">' .
                       '  <span class="lk-nav-menu-toggle-icon">' .
                       '    <span></span><span></span><span></span>' .
                       '  </span>' .
                       '</button>';

        return '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">' .
               $toggle_html .
               $menu_html .
               '</div>';
    }
}
