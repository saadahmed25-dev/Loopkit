<?php
/**
 * Loopkit Icons Manager.
 * Handles Font Awesome Free icons, SVG sanitization, and inline SVG rendering.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Icons {

    /**
     * Cache of loaded icon paths.
     *
     * @var array
     */
    private static $icon_cache = array();

    /**
     * Cache of full icon index.
     *
     * @var array|null
     */
    private static $icons_index = null;

    /**
     * Get the absolute path to Font Awesome assets.
     *
     * @return string
     */
    public static function get_font_awesome_dir() {
        return LOOPKIT_PLUGIN_DIR . 'assets/lib/font-awesome/';
    }

    /**
     * Get the URL to Font Awesome assets.
     *
     * @return string
     */
    public static function get_font_awesome_url() {
        return LOOPKIT_PLUGIN_URL . 'assets/lib/font-awesome/';
    }

    /**
     * Get all bundled Font Awesome icons metadata.
     *
     * @return array
     */
    public static function get_icons_index() {
        if ( null !== self::$icons_index ) {
            return self::$icons_index;
        }

        $json_file = self::get_font_awesome_dir() . 'icons.json';
        if ( file_exists( $json_file ) ) {
            $data = json_decode( file_get_contents( $json_file ), true );
            if ( is_array( $data ) ) {
                self::$icons_index = $data;
                return self::$icons_index;
            }
        }

        self::$icons_index = array();
        return self::$icons_index;
    }

    /**
     * Get SVG content for a specific Font Awesome icon.
     *
     * @param string $style 'solid', 'regular', or 'brands'.
     * @param string $name  Icon name (e.g. 'star', 'user', 'facebook').
     * @return string Inline SVG or empty string.
     */
    public static function get_fa_svg( $style, $name ) {
        if ( is_string( $name ) ) {
            if ( preg_match( '/\bfa-([a-z0-9-]+)/', $name, $matches ) ) {
                $name = $matches[1];
            } elseif ( preg_match( '/\b([a-z0-9-]+)$/', $name, $matches ) ) {
                $name = $matches[1];
            }
        }
        $name  = sanitize_key( $name );
        $style = sanitize_key( $style );

        // Normalize style names
        if ( 'fa-solid' === $style || 'fas' === $style ) {
            $style = 'solid';
        } elseif ( 'fa-regular' === $style || 'far' === $style ) {
            $style = 'regular';
        } elseif ( 'fa-brands' === $style || 'fab' === $style ) {
            $style = 'brands';
        }

        if ( ! in_array( $style, array( 'solid', 'regular', 'brands' ), true ) ) {
            $style = 'solid';
        }

        $cache_key = $style . '/' . $name;
        if ( isset( self::$icon_cache[ $cache_key ] ) ) {
            return self::$icon_cache[ $cache_key ];
        }

        // Try direct SVG file first
        $svg_file = self::get_font_awesome_dir() . 'svgs/' . $style . '/' . $name . '.svg';
        if ( file_exists( $svg_file ) ) {
            $svg_content = file_get_contents( $svg_file );
            if ( ! empty( $svg_content ) ) {
                // Add aria-hidden and standard classes
                $svg_content = preg_replace( '/<svg /i', '<svg aria-hidden="true" class="lk-svg-icon" ', $svg_content, 1 );
                self::$icon_cache[ $cache_key ] = $svg_content;
                return $svg_content;
            }
        }

        // Try index fallback
        $index = self::get_icons_index();
        if ( isset( $index[ $cache_key ] ) ) {
            $item = $index[ $cache_key ];
            $vb   = esc_attr( $item['viewBox'] ?? '0 0 512 512' );
            $path = esc_attr( $item['path'] ?? '' );
            $svg  = '<svg aria-hidden="true" class="lk-svg-icon" viewBox="' . $vb . '" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="' . $path . '"></path></svg>';
            self::$icon_cache[ $cache_key ] = $svg;
            return $svg;
        }

        return '';
    }

    /**
     * Sanitize custom SVG content.
     * Strictly removes <script>, <foreignObject>, on* attributes, and external URLs.
     *
     * @param string $svg Raw SVG string.
     * @return string Sanitized SVG string or empty string.
     */
    public static function sanitize_svg( $svg ) {
        if ( empty( $svg ) || ! is_string( $svg ) ) {
            return '';
        }

        // Must contain <svg
        if ( false === stripos( $svg, '<svg' ) ) {
            return '';
        }

        // Remove XML declaration and comments
        $svg = preg_replace( '/<\?xml.*?\?>/i', '', $svg );
        $svg = preg_replace( '/<!--.*?-->/s', '', $svg );

        // Strip dangerous tags completely
        $dangerous_tags = array( 'script', 'style', 'iframe', 'foreignObject', 'object', 'embed', 'applet', 'meta', 'link' );
        foreach ( $dangerous_tags as $tag ) {
            $svg = preg_replace( '#<' . $tag . '.*?>.*?</' . $tag . '>#is', '', $svg );
            $svg = preg_replace( '#<' . $tag . '.*?>#is', '', $svg );
        }

        // Strip inline event handlers (onload, onclick, onerror, on*)
        $svg = preg_replace( '/\s*on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg );

        // Strip javascript: and data: pseudo-protocols in href and xlink:href
        $svg = preg_replace( '/(href|xlink:href)\s*=\s*["\']\s*(javascript:|data:).*?["\']/i', '', $svg );

        // Strip external HTTP/HTTPS references inside href/xlink:href
        $svg = preg_replace( '/(href|xlink:href)\s*=\s*["\']\s*https?:.*?["\']/i', '', $svg );

        // Ensure aria-hidden
        if ( false === stripos( $svg, 'aria-hidden' ) ) {
            $svg = preg_replace( '/<svg /i', '<svg aria-hidden="true" ', $svg, 1 );
        }

        return trim( $svg );
    }

    /**
     * Render an icon value.
     *
     * @param array|string $icon Icon data { library, value }.
     * @param array        $extra_attrs Additional HTML attributes for wrapper.
     * @return string Rendered HTML.
     */
    public static function render( $icon, $extra_attrs = array() ) {
        if ( empty( $icon ) ) {
            return '';
        }

        if ( is_string( $icon ) ) {
            $icon = array(
                'library' => 'fa-solid',
                'value'   => $icon,
            );
        }

        $library = $icon['library'] ?? 'fa-solid';
        $value   = $icon['value'] ?? '';

        if ( empty( $value ) ) {
            return '';
        }

        $classes = array( 'lk-icon', 'loopkit-icon' );
        $svg     = '';

        if ( 'svg' === $library ) {
            $classes[] = 'lk-icon--svg';
            if ( is_array( $value ) ) {
                if ( ! empty( $value['svg'] ) ) {
                    $svg = self::sanitize_svg( $value['svg'] );
                } elseif ( ! empty( $value['id'] ) ) {
                    $file = get_attached_file( $value['id'] );
                    if ( $file && file_exists( $file ) ) {
                        $svg = self::sanitize_svg( file_get_contents( $file ) );
                    }
                } elseif ( ! empty( $value['url'] ) ) {
                    // Path from URL if local
                    $svg = '<img src="' . esc_url( $value['url'] ) . '" alt="" class="lk-svg-img">';
                }
            } elseif ( is_string( $value ) ) {
                if ( 0 === strpos( trim( $value ), '<svg' ) ) {
                    $svg = self::sanitize_svg( $value );
                } else {
                    $svg = '<img src="' . esc_url( $value ) . '" alt="" class="lk-svg-img">';
                }
            }
        } else {
            // Font Awesome (solid, regular, brands)
            $style = $library;
            if ( 0 === strpos( $style, 'fa-' ) ) {
                $style = substr( $style, 3 );
            }
            $classes[] = 'lk-icon--fa';
            $classes[] = 'lk-icon--' . sanitize_html_class( $style );
            $svg       = self::get_fa_svg( $style, is_string( $value ) ? $value : ( $value['name'] ?? '' ) );
        }

        if ( empty( $svg ) ) {
            return '';
        }

        $attrs = '';
        foreach ( $extra_attrs as $k => $v ) {
            $attrs .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
        }

        return '<span class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $attrs . '>' . $svg . '</span>';
    }

    /**
     * Initialize icon hooks.
     */
    public static function init() {
        add_action( 'wp_ajax_loopkit_upload_svg', array( __CLASS__, 'handle_ajax_upload_svg' ) );
        add_filter( 'upload_mimes', array( __CLASS__, 'allow_svg_upload' ) );
        add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'check_svg_filetype' ), 10, 4 );
    }

    /**
     * Allow SVG uploads for administrators only.
     *
     * @param array $mimes Allowed mimes.
     * @return array
     */
    public static function allow_svg_upload( $mimes ) {
        if ( current_user_can( 'manage_options' ) ) {
            $mimes['svg'] = 'image/svg+xml';
        }
        return $mimes;
    }

    /**
     * Correct filetype and extension check for SVGs.
     */
    public static function check_svg_filetype( $data, $file, $filename, $mimes ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $data;
        }
        $ext = pathinfo( $filename, PATHINFO_EXTENSION );
        if ( 'svg' === strtolower( $ext ) ) {
            $data['type'] = 'image/svg+xml';
            $data['ext']  = 'svg';
        }
        return $data;
    }

    /**
     * AJAX handler for sanitized SVG upload.
     */
    public static function handle_ajax_upload_svg() {
        check_ajax_referer( 'loopkit_editor_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied. Only administrators can upload SVGs.', 'loopkit' ) ) );
        }

        if ( empty( $_FILES['svg_file'] ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'No file uploaded.', 'loopkit' ) ) );
        }

        $file = $_FILES['svg_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        if ( ! empty( $file['error'] ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'File upload error.', 'loopkit' ) ) );
        }

        $content = file_get_contents( $file['tmp_name'] );
        $sanitized = self::sanitize_svg( $content );

        if ( empty( $sanitized ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid or empty SVG content.', 'loopkit' ) ) );
        }

        file_put_contents( $file['tmp_name'], $sanitized );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $overrides = array( 'test_form' => false );
        $upload    = wp_handle_upload( $file, $overrides );

        if ( isset( $upload['error'] ) ) {
            wp_send_json_error( array( 'message' => $upload['error'] ) );
        }

        $filename = sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) );
        $att_id   = wp_insert_attachment( array(
            'post_mime_type' => 'image/svg+xml',
            'post_title'     => $filename,
            'post_status'    => 'inherit',
            'guid'           => $upload['url'],
        ), $upload['file'] );

        wp_send_json_success( array(
            'library' => 'svg',
            'value'   => array(
                'id'    => $att_id,
                'url'   => $upload['url'],
                'title' => $filename,
                'svg'   => $sanitized,
            ),
        ) );
    }
}

