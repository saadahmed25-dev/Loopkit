<?php
/**
 * Loopkit Widget Base Class.
 * Abstract base class for all Loopkit widgets.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Loopkit_Widget_Base {

    /**
     * Cached controls configuration.
     *
     * @var array|null
     */
    protected $controls_config = null;

    /**
     * Cached control defaults.
     *
     * @var array|null
     */
    protected $defaults_cache = null;

    /**
     * Is a switcher on?
     *
     * A switcher that is turned off sends an empty string, so "no" is not the only off value.
     * Missing key = the control was never touched, so the widget's own default applies.
     *
     * @param array  $settings Widget settings.
     * @param string $key      Control name.
     * @param bool   $default  What an untouched control means.
     * @return bool
     */
    protected static function switcher_on( $settings, $key, $default = true ) {
        if ( ! array_key_exists( $key, (array) $settings ) ) {
            return (bool) $default;
        }
        $value = $settings[ $key ];
        if ( is_bool( $value ) ) {
            return $value;
        }
        return in_array( (string) $value, array( 'yes', '1', 'true', 'on' ), true );
    }


    /**
     * Get widget name (unique slug).
     *
     * @return string
     */
    abstract public function get_name();

    /**
     * Get widget title.
     *
     * @return string
     */
    abstract public function get_title();

    /**
     * Get widget icon (dashicon class or FA icon).
     *
     * @return string
     */
    abstract public function get_icon();

    /**
     * Get widget category ('layout', 'basic', 'general').
     *
     * @return string
     */
    public function get_category() {
        return 'basic';
    }

    /**
     * Get search keywords.
     *
     * @return array
     */
    public function get_keywords() {
        return array();
    }

    /**
     * Get widget context ('all', 'post', or template types).
     *
     * @return string
     */
    public function get_context() {
        return 'all';
    }

    /**
     * Check if default colours are disabled in Loopkit Settings.
     *
     * @return bool
     */
    protected function is_default_colors_disabled() {
        $gen = get_option( 'loopkit_general_settings', array() );
        return ! empty( $gen['disable_default_colors'] );
    }

    /**
     * Check if default fonts are disabled in Loopkit Settings.
     *
     * @return bool
     */
    protected function is_default_fonts_disabled() {
        $gen = get_option( 'loopkit_general_settings', array() );
        return ! empty( $gen['disable_default_fonts'] );
    }

    /**
     * Register controls for the widget.
     * Must return an array with 'content' and 'style' keys containing sections array.
     *
     * @return array
     */
    abstract protected function register_controls();

    /**
     * Get full controls configuration.
     *
     * @return array
     */
    /**
     * The widget's own control defaults, keyed by control name.
     *
     * A widget's settings only carry what has actually been set, so anything untouched has
     * to fall back to the control's default — otherwise a widget inserted from the library
     * or an older document renders empty even though the panel shows the defaults.
     *
     * @return array
     */
    public function get_defaults() {
        if ( null !== $this->defaults_cache ) {
            return $this->defaults_cache;
        }

        $defaults = array();
        foreach ( (array) $this->get_controls_config() as $tab ) {
            foreach ( (array) $tab as $section ) {
                foreach ( (array) ( $section['controls'] ?? array() ) as $control ) {
                    if ( isset( $control['name'], $control['default'] ) ) {
                        $defaults[ $control['name'] ] = $control['default'];
                    }
                }
            }
        }

        $this->defaults_cache = $defaults;
        return $defaults;
    }

    /**
     * Settings with anything untouched filled in from the control defaults.
     *
     * @param array $settings Stored settings.
     * @return array
     */
    public function with_defaults( $settings ) {
        $settings = is_array( $settings ) ? $settings : array();
        return array_merge( $this->get_defaults(), $settings );
    }

    public function get_controls_config() {
        if ( null === $this->controls_config ) {
            $this->controls_config = $this->register_controls();
        }
        return $this->controls_config;
    }

    /**
     * Get default settings for a newly created instance.
     *
     * @return array
     */
    public function get_default_settings() {
        $defaults = array();
        $config = $this->get_controls_config();

        $extract_defaults = function( $controls, &$defaults, $extract_fn ) {
            foreach ( $controls as $control ) {
                if ( isset( $control['name'] ) && array_key_exists( 'default', $control ) ) {
                    $defaults[ $control['name'] ] = $control['default'];
                }
                if ( ! empty( $control['subtabs'] ) && is_array( $control['subtabs'] ) ) {
                    foreach ( $control['subtabs'] as $subtab ) {
                        if ( ! empty( $subtab['controls'] ) && is_array( $subtab['controls'] ) ) {
                            $extract_fn( $subtab['controls'], $defaults, $extract_fn );
                        }
                    }
                }
            }
        };

        foreach ( array( 'content', 'style' ) as $tab ) {
            if ( ! empty( $config[ $tab ] ) && is_array( $config[ $tab ] ) ) {
                foreach ( $config[ $tab ] as $section ) {
                    if ( ! empty( $section['controls'] ) && is_array( $section['controls'] ) ) {
                        $extract_defaults( $section['controls'], $defaults, $extract_defaults );
                    }
                }
            }
        }

        return $defaults;
    }

    /**
     * Render the widget's inner content.
     *
     * @param array $settings    Element settings.
     * @param array $el          Full element array.
     * @param bool  $editor_mode Whether rendering in editor/canvas mode.
     * @return string HTML output.
     */
    abstract public function render( $settings, $el, $editor_mode = false );

    /**
     * Render the complete widget element with outer wrapper and editor handles.
     * Follows the Heading wrapper pattern:
     * <div class="lk-element lk-element-{id} lk-widget lk-widget-{name}" data-id="{id}" data-element_type="widget" data-widget_type="{name}.default">
     *
     * @param array $el          Full element array.
     * @param bool  $editor_mode Whether rendering in editor/canvas mode.
     * @return string HTML output.
     */
    public function render_element( $el, $editor_mode = false ) {
        $id       = esc_attr( $el['id'] ?? '' );
        $settings = $this->with_defaults( $el['settings'] ?? array() );
        $name     = $this->get_name();

        $classes = 'lk-element lk-element-' . $id . ' lk-widget lk-widget-' . $name;
        if ( $editor_mode ) {
            $classes .= ' lk-editable';
        }
        if ( ! empty( $settings['css_classes'] ) ) {
            $classes .= ' ' . esc_attr( $settings['css_classes'] );
        }

        $data_attrs = '';
        if ( $editor_mode ) {
            $data_attrs = ' data-id="' . $id . '" data-lk-id="' . $id . '" data-element_type="widget" data-lk-type="widget" data-widget_type="' . esc_attr( $name ) . '.default" data-lk-widget="' . esc_attr( $name ) . '"';
        } else {
            $data_attrs = ' data-id="' . $id . '" data-element_type="widget" data-widget_type="' . esc_attr( $name ) . '.default"';
        }

        $id_attr = '';
        if ( ! empty( $settings['css_id'] ) ) {
            $id_attr = ' id="' . esc_attr( $settings['css_id'] ) . '"';
        }

        $custom_attrs = '';
        if ( ! empty( $settings['custom_attributes'] ) && class_exists( 'Loopkit_Frontend' ) ) {
            $custom_attrs = Loopkit_Frontend::parse_custom_attributes( $settings['custom_attributes'] );
        }

        // Entrance Animation
        $anim_style = '';
        $anim_name = $settings['entrance_animation'] ?? '';
        if ( ! empty( $anim_name ) && 'none' !== $anim_name ) {
            $classes .= ' lk-anim lk-anim-' . sanitize_html_class( $anim_name );
            $duration_map = array(
                'slow'   => '2',
                'normal' => '1.25',
                'fast'   => '0.75',
            );
            $dur_setting = $settings['animation_duration'] ?? 'normal';
            $duration = isset( $duration_map[ $dur_setting ] ) ? $duration_map[ $dur_setting ] : ( is_numeric( $dur_setting ) ? floatval( $dur_setting ) : '1.25' );
            $delay = isset( $settings['animation_delay'] ) && is_numeric( $settings['animation_delay'] ) ? intval( $settings['animation_delay'] ) : 0;
            $anim_style .= '--lk-anim-duration: ' . $duration . 's; --lk-anim-delay: ' . $delay . 'ms; ';
        }

        // Sticky (Motion Effects)
        $sticky = $settings['sticky'] ?? 'none';
        $sticky_attrs = '';
        if ( ! empty( $sticky ) && 'none' !== $sticky ) {
            $classes .= ' lk-sticky lk-sticky--' . sanitize_html_class( $sticky );
            $sticky_offset = 0;
            if ( isset( $settings['sticky_offset'] ) ) {
                if ( is_numeric( $settings['sticky_offset'] ) ) {
                    $sticky_offset = intval( $settings['sticky_offset'] );
                } elseif ( is_array( $settings['sticky_offset'] ) && isset( $settings['sticky_offset']['size'] ) && is_numeric( $settings['sticky_offset']['size'] ) ) {
                    $sticky_offset = intval( $settings['sticky_offset']['size'] );
                }
            }
            $sticky_effects_offset = 0;
            if ( isset( $settings['sticky_effects_offset'] ) ) {
                if ( is_numeric( $settings['sticky_effects_offset'] ) ) {
                    $sticky_effects_offset = intval( $settings['sticky_effects_offset'] );
                } elseif ( is_array( $settings['sticky_effects_offset'] ) && isset( $settings['sticky_effects_offset']['size'] ) && is_numeric( $settings['sticky_effects_offset']['size'] ) ) {
                    $sticky_effects_offset = intval( $settings['sticky_effects_offset']['size'] );
                }
            }
            $anim_style .= '--lk-sticky-offset: ' . $sticky_offset . 'px; --lk-sticky-effects-offset: ' . $sticky_effects_offset . 'px; ';

            $sticky_on = $settings['sticky_on'] ?? array( 'desktop', 'tablet', 'mobile' );
            if ( empty( $sticky_on ) || ( is_array( $sticky_on ) && empty( array_filter( $sticky_on ) ) ) ) {
                $sticky_on = array( 'desktop', 'tablet', 'mobile' );
            }
            if ( is_array( $sticky_on ) ) {
                $sticky_on_str = implode( ',', array_map( 'sanitize_key', $sticky_on ) );
            } else {
                $sticky_on_str = sanitize_text_field( (string) $sticky_on );
            }
            if ( empty( $sticky_on_str ) ) {
                $sticky_on_str = 'desktop,tablet,mobile';
            }
            $sticky_attrs .= ' data-lk-sticky-on="' . esc_attr( $sticky_on_str ) . '"';

            if ( ! empty( $settings['stay_in_container'] ) && ( 'yes' === $settings['stay_in_container'] || true === $settings['stay_in_container'] ) ) {
                $classes .= ' lk-sticky--in-container';
                $sticky_attrs .= ' data-lk-sticky-in-container="1"';
            }
        }

        $motion_data   = class_exists( 'Loopkit_Frontend' ) ? Loopkit_Frontend::get_motion_effects_data( $settings, $editor_mode ) : array( 'classes' => '', 'attrs' => '', 'badges' => '' );
        $classes      .= $motion_data['classes'];
        $custom_attrs .= $motion_data['attrs'];

        $style_attr = ! empty( trim( $anim_style ) ) ? ' style="' . esc_attr( trim( $anim_style ) ) . '"' : '';

        $html = '<div class="' . esc_attr( $classes ) . '"' . $data_attrs . $id_attr . $custom_attrs . $sticky_attrs . $style_attr . '>';

        // Widget handle in editor mode: top right, background #38A8F5, label, Edit, Duplicate, Delete
        if ( $editor_mode ) {
            $title = ! empty( $settings['_title'] ) ? $settings['_title'] : $this->get_title();
            $sticky_badge = ( ! empty( $sticky ) && 'none' !== $sticky ) ? ' <span class="lk-sticky-badge">' . esc_html__( 'Sticky', 'loopkit' ) . '</span>' : '';
            $sticky_badge .= $motion_data['badges'];
            $html .= '<div class="lk-element-handle lk-handle-widget">';
            $html .= '  <span class="lk-handle-label">' . esc_html( $title ) . '</span>' . $sticky_badge;
            $html .= '  <button type="button" class="lk-handle-btn lk-handle-edit" data-action="edit" data-id="' . $id . '" draggable="true" title="' . esc_attr__( 'Edit', 'loopkit' ) . ' ' . esc_attr( $this->get_title() ) . '"><span class="dashicons dashicons-edit"></span></button>';
            $html .= '  <button type="button" class="lk-handle-btn lk-handle-duplicate" data-action="duplicate" data-id="' . $id . '" title="' . esc_attr__( 'Duplicate', 'loopkit' ) . '"><span class="dashicons dashicons-admin-page"></span></button>';
            $html .= '  <button type="button" class="lk-handle-btn lk-handle-delete" data-action="delete" data-id="' . $id . '" title="' . esc_attr__( 'Delete', 'loopkit' ) . '"><span class="dashicons dashicons-trash"></span></button>';
            $html .= '</div>';
        }

        $html .= '<div class="lk-widget-container">';
        $html .= $this->render( $settings, $el, $editor_mode );
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }
}
