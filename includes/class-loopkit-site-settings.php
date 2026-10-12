<?php
/**
 * Loopkit Site Settings class.
 * Handles global theme-style settings (colours, typography, layout, breakpoints).
 * Stores settings in 'loopkit_kit_settings' (Section 1 & 17).
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Site_Settings {

    /**
     * Primary option name as specified in Section 1:
     * "Site Settings are stored in ONE option: loopkit_kit_settings.
     *  The editor Site Settings and the admin page Loopkit > Site Settings
     *  both read and write this same option."
     */
    const OPTION_NAME = 'loopkit_kit_settings';
    const LEGACY_OPTION = 'loopkit_site_settings';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Register the site settings option.
     */
    public function register_settings() {
        register_setting( 'loopkit_site', self::OPTION_NAME, array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize' ),
        ) );
        register_setting( 'loopkit_site', self::LEGACY_OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize' ),
        ) );
    }

    /**
     * Validate breakpoints in descending order and as positive integers.
     *
     * @param array $input Settings array.
     * @return true|WP_Error
     */
    public static function validate_breakpoints( $input ) {
        $keys = array(
            'breakpoint_widescreen'   => 'Widescreen',
            'breakpoint_laptop'       => 'Laptop',
            'breakpoint_tablet_extra' => 'Tablet Landscape',
            'breakpoint_tablet'       => 'Tablet Portrait',
            'breakpoint_mobile_extra' => 'Mobile Landscape',
            'breakpoint_mobile'       => 'Mobile Portrait',
        );

        $values = array();
        foreach ( $keys as $k => $label ) {
            if ( isset( $input[ $k ] ) && '' !== $input[ $k ] ) {
                $val = intval( $input[ $k ] );
                if ( $val <= 0 ) {
                    return new WP_Error( 'invalid_breakpoint', sprintf( '%s breakpoint must be a positive integer.', $label ) );
                }
                $values[ $k ] = $val;
            }
        }

        // Check strictly descending order
        $prev_val = null;
        $prev_label = null;
        foreach ( $values as $k => $val ) {
            if ( null !== $prev_val ) {
                if ( $val >= $prev_val ) {
                    return new WP_Error(
                        'breakpoint_order',
                        sprintf( '%s (%dpx) must be smaller than %s (%dpx). Breakpoints must be in strictly descending order.', $keys[ $k ], $val, $prev_label, $prev_val )
                    );
                }
            }
            $prev_val = $val;
            $prev_label = $keys[ $k ];
        }

        return true;
    }

    /**
     * Sanitize site settings.
     *
     * @param array $input Raw input.
     * @return array Sanitized input.
     */
    public function sanitize( $input ) {
        if ( ! is_array( $input ) ) {
            return self::get_defaults();
        }

        $sanitized = self::get_defaults();

        // 1. Global Colors
        $color_fields = array( 'color_primary', 'color_secondary', 'color_text', 'color_accent', 'page_background_color' );
        foreach ( $color_fields as $field ) {
            if ( isset( $input[ $field ] ) && '' !== $input[ $field ] ) {
                $hex = sanitize_hex_color( $input[ $field ] );
                if ( $hex ) {
                    $sanitized[ $field ] = $hex;
                }
            }
        }

        // Custom colors list
        if ( isset( $input['custom_colors'] ) && is_array( $input['custom_colors'] ) ) {
            $custom_colors = array();
            foreach ( $input['custom_colors'] as $c ) {
                if ( ! is_array( $c ) ) continue;
                $c_id = sanitize_key( $c['id'] ?? '' );
                $c_val = sanitize_hex_color( $c['value'] ?? '' ) ?: sanitize_text_field( $c['value'] ?? '' );
                $c_label = sanitize_text_field( $c['label'] ?? '' );
                if ( $c_id && $c_val ) {
                    $custom_colors[] = array(
                        'id'    => $c_id,
                        'label' => $c_label ?: ucfirst( str_replace( '-', ' ', $c_id ) ),
                        'value' => $c_val,
                    );
                }
            }
            $sanitized['custom_colors'] = $custom_colors;
        }

        // Custom fonts list
        if ( isset( $input['custom_fonts'] ) && is_array( $input['custom_fonts'] ) ) {
            $custom_fonts = array();
            foreach ( $input['custom_fonts'] as $f ) {
                if ( ! is_array( $f ) ) continue;
                $f_id = sanitize_key( $f['id'] ?? '' );
                $f_label = sanitize_text_field( $f['label'] ?? '' );
                $f_val = is_array( $f['value'] ?? null ) ? $f['value'] : array();
                if ( $f_id ) {
                    $custom_fonts[] = array(
                        'id'    => $f_id,
                        'label' => $f_label ?: ucfirst( str_replace( '-', ' ', $f_id ) ),
                        'value' => array(
                            'family'         => sanitize_text_field( $f_val['family'] ?? '' ),
                            'size'           => is_array( $f_val['size'] ?? null ) ? array( 'size' => sanitize_text_field( $f_val['size']['size'] ?? '' ), 'unit' => sanitize_key( $f_val['size']['unit'] ?? 'px' ) ) : sanitize_text_field( $f_val['size'] ?? '' ),
                            'weight'         => sanitize_text_field( $f_val['weight'] ?? '' ),
                            'transform'      => sanitize_key( $f_val['transform'] ?? '' ),
                            'style'          => sanitize_key( $f_val['style'] ?? '' ),
                            'line_height'    => is_array( $f_val['line_height'] ?? null ) ? array( 'size' => sanitize_text_field( $f_val['line_height']['size'] ?? '' ), 'unit' => sanitize_key( $f_val['line_height']['unit'] ?? 'em' ) ) : sanitize_text_field( $f_val['line_height'] ?? '' ),
                            'letter_spacing' => is_array( $f_val['letter_spacing'] ?? null ) ? array( 'size' => sanitize_text_field( $f_val['letter_spacing']['size'] ?? '' ), 'unit' => sanitize_key( $f_val['letter_spacing']['unit'] ?? 'px' ) ) : sanitize_text_field( $f_val['letter_spacing'] ?? '' ),
                        ),
                    );
                }
            }
            $sanitized['custom_fonts'] = $custom_fonts;
        }

        // Typography fields
        $typo_tags  = array( 'body', 'link', 'link_hover', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
        $typo_props = array( 'font_family', 'font_size', 'font_weight', 'line_height', 'letter_spacing', 'text_transform', 'font_style', 'color' );

        foreach ( $typo_tags as $tag ) {
            foreach ( $typo_props as $prop ) {
                $key = $tag . '_' . $prop;
                if ( isset( $input[ $key ] ) ) {
                    $sanitized[ $key ] = sanitize_text_field( $input[ $key ] );
                }
            }
        }

        // Theme Buttons
        $btn_keys = array(
            'btn_font_family', 'btn_font_size', 'btn_font_weight', 'btn_line_height', 'btn_letter_spacing', 'btn_text_transform',
            'btn_color', 'btn_color_hover', 'btn_bg_color', 'btn_bg_color_hover',
            'btn_border_style', 'btn_border_color', 'btn_border_width', 'btn_border_radius', 'btn_padding'
        );
        foreach ( $btn_keys as $bk ) {
            if ( isset( $input[ $bk ] ) ) {
                $sanitized[ $bk ] = is_array( $input[ $bk ] ) ? $input[ $bk ] : sanitize_text_field( $input[ $bk ] );
            }
        }

        // Theme Images
        $img_keys = array( 'img_border_style', 'img_border_color', 'img_border_width', 'img_border_radius', 'img_box_shadow', 'img_opacity', 'img_filter_blur', 'img_filter_brightness', 'img_filter_contrast', 'img_filter_saturate' );
        foreach ( $img_keys as $ik ) {
            if ( isset( $input[ $ik ] ) ) {
                $sanitized[ $ik ] = is_array( $input[ $ik ] ) ? $input[ $ik ] : sanitize_text_field( $input[ $ik ] );
            }
        }

        // Theme Form Fields
        $form_keys = array( 'form_color', 'form_bg_color', 'form_border_style', 'form_border_color', 'form_border_width', 'form_border_radius', 'form_padding', 'form_focus_color', 'form_focus_border_color' );
        foreach ( $form_keys as $fk ) {
            if ( isset( $input[ $fk ] ) ) {
                $sanitized[ $fk ] = is_array( $input[ $fk ] ) ? $input[ $fk ] : sanitize_text_field( $input[ $fk ] );
            }
        }

        // Page Background (classic / gradient / image)
        $bg_fields = array( 'page_bg_type', 'page_bg_color', 'page_bg_image', 'page_bg_position', 'page_bg_attachment', 'page_bg_repeat', 'page_bg_size', 'page_bg_color_b', 'page_bg_gradient_type', 'page_bg_gradient_angle' );
        foreach ( $bg_fields as $bg_f ) {
            if ( isset( $input[ $bg_f ] ) ) {
                $sanitized[ $bg_f ] = is_array( $input[ $bg_f ] ) ? $input[ $bg_f ] : sanitize_text_field( $input[ $bg_f ] );
            }
        }

        // Layout (Section 17)
        $sanitized['content_width']       = absint( $input['content_width'] ?? 1140 );
        $sanitized['container_padding']   = absint( $input['container_padding'] ?? 10 );
        $sanitized['widgets_space']       = absint( $input['widgets_space'] ?? 20 );
        $sanitized['page_title_selector'] = sanitize_text_field( $input['page_title_selector'] ?? 'h1.entry-title' );

        // Default Page Layout
        $layout = sanitize_key( $input['default_page_layout'] ?? 'loopkit-full-width' );
        if ( in_array( $layout, array( 'loopkit-full-width', 'loopkit-canvas', 'default', 'theme' ), true ) ) {
            $sanitized['default_page_layout'] = $layout;
        } else {
            $sanitized['default_page_layout'] = 'loopkit-full-width';
        }

        // Breakpoints (Section 2 & 17)
        $bp_keys = array(
            'breakpoint_widescreen'   => 2400,
            'breakpoint_laptop'       => 1366,
            'breakpoint_tablet_extra' => 1200,
            'breakpoint_tablet'       => 1024,
            'breakpoint_mobile_extra' => 880,
            'breakpoint_mobile'       => 767,
        );
        foreach ( $bp_keys as $bp_k => $def_bp ) {
            if ( isset( $input[ $bp_k ] ) && '' !== $input[ $bp_k ] ) {
                $sanitized[ $bp_k ] = absint( $input[ $bp_k ] );
            }
            $act_k = $bp_k . '_active';
            if ( isset( $input[ $act_k ] ) ) {
                $sanitized[ $act_k ] = ( 'yes' === $input[ $act_k ] || true === $input[ $act_k ] || 1 === $input[ $act_k ] || '1' === $input[ $act_k ] ) ? 'yes' : 'no';
            }
        }

        // Lightbox
        $sanitized['lightbox_enable']          = ( ! isset( $input['lightbox_enable'] ) || 'yes' === $input['lightbox_enable'] || true === $input['lightbox_enable'] ) ? 'yes' : 'no';
        $sanitized['lightbox_widgets']         = is_array( $input['lightbox_widgets'] ?? null ) ? array_map( 'sanitize_key', $input['lightbox_widgets'] ) : array( 'image', 'gallery', 'video' );
        $sanitized['lightbox_ui_color']        = sanitize_text_field( $input['lightbox_ui_color'] ?? '#ffffff' );
        $sanitized['lightbox_ui_hover_color']  = sanitize_text_field( $input['lightbox_ui_hover_color'] ?? '#38A8F5' );
        $sanitized['lightbox_overlay_color']   = sanitize_text_field( $input['lightbox_overlay_color'] ?? 'rgba(0,0,0,0.85)' );

        // Custom CSS
        // Site-wide JS: executable code, administrators only.
        if ( isset( $input['site_custom_js'] ) ) {
            $sanitized['site_custom_js'] = current_user_can( 'manage_options' ) ? (string) $input['site_custom_js'] : '';
        }

        if ( isset( $input['site_custom_css'] ) ) {
            $sanitized['site_custom_css'] = wp_strip_all_tags( $input['site_custom_css'] );
        }

        // Keep both options in sync
        update_option( self::LEGACY_OPTION, $sanitized );

        return $sanitized;
    }

    /**
     * Get defaults.
     *
     * @return array
     */
    public static function get_defaults() {
        return array(
            'color_primary'           => '#38A8F5',
            'color_secondary'         => '#54595F',
            'color_text'              => '#333333',
            'color_accent'            => '#38A8F5',
            'custom_colors'           => array(),
            'custom_fonts'            => array(),
            'body_font_size'          => '16',
            'body_font_weight'        => '400',
            'body_line_height'        => '1.6',
            'body_letter_spacing'     => '0',
            'body_color'              => '#333333',
            'link_color'              => '#38A8F5',
            'link_hover_color'        => '#1E90E0',
            'h1_font_size'            => '40',
            'h1_font_weight'          => '600',
            'h1_line_height'          => '1.2',
            'h1_letter_spacing'       => '0',
            'h2_font_size'            => '32',
            'h2_font_weight'          => '600',
            'h2_line_height'          => '1.3',
            'h2_letter_spacing'       => '0',
            'h3_font_size'            => '28',
            'h3_font_weight'          => '600',
            'h3_line_height'          => '1.3',
            'h3_letter_spacing'       => '0',
            'h4_font_size'            => '24',
            'h4_font_weight'          => '600',
            'h4_line_height'          => '1.4',
            'h4_letter_spacing'       => '0',
            'h5_font_size'            => '20',
            'h5_font_weight'          => '600',
            'h5_line_height'          => '1.4',
            'h5_letter_spacing'       => '0',
            'h6_font_size'            => '16',
            'h6_font_weight'          => '600',
            'h6_line_height'          => '1.5',
            'h6_letter_spacing'       => '0',
            'content_width'           => 1140,
            'container_padding'       => 10,
            'widgets_space'           => 20,
            'page_title_selector'     => 'h1.entry-title',
            'default_page_layout'     => 'loopkit-full-width',
            'page_background_color'   => '#FFFFFF',
            'breakpoint_mobile'       => 767,
            'breakpoint_mobile_active' => 'yes',
            'breakpoint_mobile_extra' => 880,
            'breakpoint_mobile_extra_active' => 'no',
            'breakpoint_tablet'       => 1024,
            'breakpoint_tablet_active' => 'yes',
            'breakpoint_tablet_extra' => 1200,
            'breakpoint_tablet_extra_active' => 'no',
            'breakpoint_laptop'       => 1366,
            'breakpoint_laptop_active' => 'no',
            'breakpoint_widescreen'   => 2400,
            'breakpoint_widescreen_active' => 'no',
            'lightbox_enable'         => 'yes',
            'lightbox_widgets'        => array( 'image', 'gallery', 'video' ),
            'lightbox_ui_color'       => '#ffffff',
            'lightbox_ui_hover_color' => '#38A8F5',
            'lightbox_overlay_color'  => 'rgba(0,0,0,0.85)',
            'site_custom_css'         => '',
            'site_custom_js'          => '',
        );
    }

    /**
     * Get site settings (reads loopkit_kit_settings with fallback to legacy loopkit_site_settings).
     *
     * @return array
     */
    public static function get_settings() {
        $saved = get_option( self::OPTION_NAME, null );
        if ( null === $saved || false === $saved ) {
            $saved = get_option( self::LEGACY_OPTION, array() );
        }
        return wp_parse_args(
            is_array( $saved ) ? $saved : array(),
            self::get_defaults()
        );
    }

    /**
     * Compile CSS variables and global theme styles into a CSS string.
     *
     * @param array $settings Settings array.
     * @return string Compiled CSS.
     */
    public static function build_global_css( $settings = null ) {
        if ( null === $settings ) {
            $settings = self::get_settings();
        }

        $css = array();

        // 1. :root CSS Variables
        $vars = array();
        $vars[] = '--lk-global-color-primary:' . ( $settings['color_primary'] ?? '#38A8F5' );
        $vars[] = '--lk-global-color-secondary:' . ( $settings['color_secondary'] ?? '#54595F' );
        $vars[] = '--lk-global-color-text:' . ( $settings['color_text'] ?? '#333333' );
        $vars[] = '--lk-global-color-accent:' . ( $settings['color_accent'] ?? '#38A8F5' );

        if ( ! empty( $settings['custom_colors'] ) && is_array( $settings['custom_colors'] ) ) {
            foreach ( $settings['custom_colors'] as $cc ) {
                if ( ! empty( $cc['id'] ) && ! empty( $cc['value'] ) ) {
                    $vars[] = '--lk-global-color-' . sanitize_key( $cc['id'] ) . ':' . esc_attr( $cc['value'] );
                }
            }
        }

        if ( ! empty( $settings['custom_fonts'] ) && is_array( $settings['custom_fonts'] ) ) {
            foreach ( $settings['custom_fonts'] as $cf ) {
                if ( ! empty( $cf['id'] ) && ! empty( $cf['value'] ) && is_array( $cf['value'] ) ) {
                    $fid = sanitize_key( $cf['id'] );
                    $fv = $cf['value'];
                    if ( ! empty( $fv['family'] ) ) $vars[] = '--lk-global-font-' . $fid . '-family:' . esc_attr( $fv['family'] );
                    if ( ! empty( $fv['size'] ) ) {
                        $s_sz = is_array( $fv['size'] ) ? ( $fv['size']['size'] . ( $fv['size']['unit'] ?? 'px' ) ) : ( $fv['size'] . 'px' );
                        $vars[] = '--lk-global-font-' . $fid . '-size:' . esc_attr( $s_sz );
                    }
                    if ( ! empty( $fv['weight'] ) ) $vars[] = '--lk-global-font-' . $fid . '-weight:' . esc_attr( $fv['weight'] );
                }
            }
        }

        // Layout variables
        $vars[] = '--lk-content-width:' . intval( $settings['content_width'] ?? 1140 ) . 'px';
        $vars[] = '--lk-container-padding:' . intval( $settings['container_padding'] ?? 10 ) . 'px';
        $vars[] = '--lk-widgets-space:' . intval( $settings['widgets_space'] ?? 20 ) . 'px';

        // Lightbox variables
        $vars[] = '--lk-lightbox-ui-color:' . ( $settings['lightbox_ui_color'] ?? '#ffffff' );
        $vars[] = '--lk-lightbox-ui-hover-color:' . ( $settings['lightbox_ui_hover_color'] ?? '#38A8F5' );
        $vars[] = '--lk-lightbox-overlay-color:' . ( $settings['lightbox_overlay_color'] ?? 'rgba(0,0,0,0.85)' );

        $css[] = ":root {\n  " . implode( ";\n  ", $vars ) . ";\n}";

        // 2. Page Background
        $bg_rules = array();
        if ( ! empty( $settings['page_bg_type'] ) && 'gradient' === $settings['page_bg_type'] ) {
            $c1 = $settings['page_bg_color'] ?? '#ffffff';
            $c2 = $settings['page_bg_color_b'] ?? '#f1f2f3';
            $angle = intval( $settings['page_bg_gradient_angle'] ?? 180 );
            $bg_rules[] = 'background:linear-gradient(' . $angle . 'deg, ' . esc_attr( $c1 ) . ', ' . esc_attr( $c2 ) . ')';
        } elseif ( ! empty( $settings['page_bg_image']['url'] ) || ( is_string( $settings['page_bg_image'] ?? null ) && '' !== $settings['page_bg_image'] ) ) {
            $img_url = is_array( $settings['page_bg_image'] ) ? ( $settings['page_bg_image']['url'] ?? '' ) : $settings['page_bg_image'];
            if ( $img_url ) {
                $bg_rules[] = 'background-image:url("' . esc_url( $img_url ) . '")';
                if ( ! empty( $settings['page_bg_position'] ) ) $bg_rules[] = 'background-position:' . esc_attr( $settings['page_bg_position'] );
                if ( ! empty( $settings['page_bg_attachment'] ) ) $bg_rules[] = 'background-attachment:' . esc_attr( $settings['page_bg_attachment'] );
                if ( ! empty( $settings['page_bg_repeat'] ) ) $bg_rules[] = 'background-repeat:' . esc_attr( $settings['page_bg_repeat'] );
                if ( ! empty( $settings['page_bg_size'] ) ) $bg_rules[] = 'background-size:' . esc_attr( $settings['page_bg_size'] );
            }
            if ( ! empty( $settings['page_bg_color'] ) ) $bg_rules[] = 'background-color:' . esc_attr( $settings['page_bg_color'] );
        } elseif ( ! empty( $settings['page_background_color'] ) || ! empty( $settings['page_bg_color'] ) ) {
            $bg_rules[] = 'background-color:' . esc_attr( $settings['page_bg_color'] ?? $settings['page_background_color'] );
        }

        if ( ! empty( $bg_rules ) ) {
            $css[] = "body.loopkit-page, body.lk-canvas-body {\n  " . implode( ";\n  ", $bg_rules ) . ";\n}";
        }

        // 3. Typography Styles
        $body_typo = array();
        if ( ! empty( $settings['body_font_family'] ) ) $body_typo[] = 'font-family:' . esc_attr( $settings['body_font_family'] );
        if ( ! empty( $settings['body_font_size'] ) ) $body_typo[] = 'font-size:' . floatval( $settings['body_font_size'] ) . 'px';
        if ( ! empty( $settings['body_font_weight'] ) ) $body_typo[] = 'font-weight:' . esc_attr( $settings['body_font_weight'] );
        if ( ! empty( $settings['body_line_height'] ) ) $body_typo[] = 'line-height:' . esc_attr( $settings['body_line_height'] );
        if ( ! empty( $settings['body_letter_spacing'] ) ) $body_typo[] = 'letter-spacing:' . floatval( $settings['body_letter_spacing'] ) . 'px';
        if ( ! empty( $settings['body_color'] ) ) $body_typo[] = 'color:' . esc_attr( $settings['body_color'] );

        if ( ! empty( $body_typo ) ) {
            $css[] = "body.loopkit-page, body.lk-canvas-body, .lk-element {\n  " . implode( ";\n  ", $body_typo ) . ";\n}";
        }

        // Links
        if ( ! empty( $settings['link_color'] ) ) {
            $css[] = ".loopkit-page a, .lk-canvas-body a { color: " . esc_attr( $settings['link_color'] ) . "; }";
        }
        if ( ! empty( $settings['link_hover_color'] ) ) {
            $css[] = ".loopkit-page a:hover, .lk-canvas-body a:hover { color: " . esc_attr( $settings['link_hover_color'] ) . "; }";
        }

        // Headings h1-h6
        for ( $i = 1; $i <= 6; $i++ ) {
            $h_rules = array();
            if ( ! empty( $settings[ "h{$i}_font_size" ] ) ) $h_rules[] = 'font-size:' . floatval( $settings[ "h{$i}_font_size" ] ) . 'px';
            if ( ! empty( $settings[ "h{$i}_font_weight" ] ) ) $h_rules[] = 'font-weight:' . esc_attr( $settings[ "h{$i}_font_weight" ] );
            if ( ! empty( $settings[ "h{$i}_line_height" ] ) ) $h_rules[] = 'line-height:' . esc_attr( $settings[ "h{$i}_line_height" ] );
            if ( ! empty( $settings[ "h{$i}_letter_spacing" ] ) ) $h_rules[] = 'letter-spacing:' . floatval( $settings[ "h{$i}_letter_spacing" ] ) . 'px';
            if ( ! empty( $h_rules ) ) {
                $css[] = ".loopkit-page h{$i}, .lk-canvas-body h{$i} {\n  " . implode( ";\n  ", $h_rules ) . ";\n}";
            }
        }

        // 4. Buttons Theme Style
        $btn_rules = array();
        if ( ! empty( $settings['btn_color'] ) ) $btn_rules[] = 'color:' . esc_attr( $settings['btn_color'] );
        if ( ! empty( $settings['btn_bg_color'] ) ) $btn_rules[] = 'background-color:' . esc_attr( $settings['btn_bg_color'] );
        if ( ! empty( $settings['btn_border_radius'] ) ) $btn_rules[] = 'border-radius:' . intval( $settings['btn_border_radius'] ) . 'px';
        if ( ! empty( $settings['btn_border_style'] ) && 'none' !== $settings['btn_border_style'] ) {
            $btn_rules[] = 'border-style:' . esc_attr( $settings['btn_border_style'] );
            if ( ! empty( $settings['btn_border_color'] ) ) $btn_rules[] = 'border-color:' . esc_attr( $settings['btn_border_color'] );
            if ( ! empty( $settings['btn_border_width'] ) ) $btn_rules[] = 'border-width:' . intval( $settings['btn_border_width'] ) . 'px';
        }
        if ( ! empty( $btn_rules ) ) {
            $css[] = ".loopkit-page .lk-btn, .lk-canvas-body .lk-btn {\n  " . implode( ";\n  ", $btn_rules ) . ";\n}";
        }

        $btn_hover = array();
        if ( ! empty( $settings['btn_color_hover'] ) ) $btn_hover[] = 'color:' . esc_attr( $settings['btn_color_hover'] );
        if ( ! empty( $settings['btn_bg_color_hover'] ) ) $btn_hover[] = 'background-color:' . esc_attr( $settings['btn_bg_color_hover'] );
        if ( ! empty( $btn_hover ) ) {
            $css[] = ".loopkit-page .lk-btn:hover, .lk-canvas-body .lk-btn:hover {\n  " . implode( ";\n  ", $btn_hover ) . ";\n}";
        }

        // 5. Custom CSS
        if ( ! empty( $settings['site_custom_css'] ) ) {
            $css[] = "/* Site Custom CSS */\n" . $settings['site_custom_css'];
        }

        return implode( "\n\n", $css );
    }

    /**
     * Save compiled global CSS to file.
     *
     * @param string $css CSS string.
     * @return bool True on success.
     */
    public static function save_global_css_file( $css = null ) {
        if ( null === $css ) {
            $css = self::build_global_css();
        }

        if ( class_exists( 'Loopkit_Assets' ) && Loopkit_Assets::is_optimization_enabled() ) {
            $css = Loopkit_Assets::minify_css( $css );
        }

        update_option( 'loopkit_global_css', $css );
        update_option( 'loopkit_global_css_time', time() );

        $upload_dir = wp_upload_dir();
        if ( empty( $upload_dir['error'] ) ) {
            $css_dir = trailingslashit( $upload_dir['basedir'] ) . 'loopkit/css/';
            if ( ! is_dir( $css_dir ) ) {
                wp_mkdir_p( $css_dir );
            }
            if ( is_dir( $css_dir ) && wp_is_writable( $css_dir ) ) {
                $file = $css_dir . 'global.css';
                return (bool) @file_put_contents( $file, $css );
            }
        }
        return false;
    }
}

new Loopkit_Site_Settings();
