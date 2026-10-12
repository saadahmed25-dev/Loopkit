<?php
/**
 * Loopkit Frontend Rendering class.
 * Handles front-end rendering of Loopkit pages and canvas preview for the editor.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Frontend {

    /**
     * Constructor.
     */
    public function __construct() {
        // Front-end rendering: replace the_content for Loopkit pages
        add_filter( 'the_content', array( $this, 'render_content' ), 9999 );

        // Body classes
        add_filter( 'body_class', array( $this, 'add_body_classes' ) );

        // Enqueue front-end CSS for Loopkit pages
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_page_css' ) );
        // Popups can appear on any page, so their script and styles are registered always
        // and enqueued by Loopkit_Popups only when a popup actually matches.
        add_action( 'wp_enqueue_scripts', array( $this, 'register_popup_assets' ), 5 );

        // Query setup for canvas preview
        add_action( 'pre_get_posts', array( $this, 'fix_canvas_query' ), 1 );

        // Bypass canonical redirects for canvas iframe (e.g. front page)
        add_filter( 'redirect_canonical', array( $this, 'prevent_canvas_canonical_redirect' ), 10, 2 );

        // Posts widget pagination on pages/posts uses /page/N/; WordPress would redirect it away.
        add_filter( 'redirect_canonical', array( $this, 'keep_paged_singular' ), 10, 2 );

        // Page JS / site JS (Custom JS boxes), printed just before </body>
        add_action( 'wp_footer', array( $this, 'print_custom_js' ), 99 );

        // Canvas preview for the editor iframe
        add_action( 'template_redirect', array( $this, 'handle_canvas_preview' ), 1 );
    }

    /**
     * Keep /page/N/ on pages and posts that show paginated Loopkit content (Posts widget):
     * WordPress would otherwise redirect a paged request on a single-page post to page 1.
     *
     * @param string $redirect_url  Redirect URL.
     * @param string $requested_url Requested URL.
     * @return string|false
     */
    /**
     * Print the Custom JS boxes: site-wide JS (Site Settings) and this document's Page JS.
     * Each runs inside its own function with jQuery passed in as $, so a mistake in one
     * cannot break the other or leak variables into the page.
     */
    public function print_custom_js() {
        if ( is_admin() || is_feed() || is_embed() ) {
            return;
        }

        $blocks = array();

        $site = class_exists( 'Loopkit_Site_Settings' ) ? Loopkit_Site_Settings::get_settings() : array();
        if ( ! empty( $site['site_custom_js'] ) ) {
            $blocks['site'] = (string) $site['site_custom_js'];
        }

        // The document whose layout is on screen: the post itself, or the Single/Archive template.
        $doc_ids = array();
        if ( is_singular() ) {
            $doc_ids[] = (int) get_queried_object_id();
        }
        if ( class_exists( 'Loopkit_Theme_Builder' ) ) {
            $tb = Loopkit_Theme_Builder::get_instance();
            foreach ( array( 'get_single_template_for_request', 'get_archive_template_for_request' ) as $method ) {
                if ( method_exists( $tb, $method ) ) {
                    $winner = $tb->$method();
                    if ( $winner && ! empty( $winner['id'] ) ) {
                        $doc_ids[] = (int) $winner['id'];
                    }
                }
            }
        }

        foreach ( array_unique( $doc_ids ) as $doc_id ) {
            $settings = get_post_meta( $doc_id, '_loopkit_page_settings', true );
            if ( is_array( $settings ) && ! empty( $settings['custom_js'] ) ) {
                $blocks[ 'doc-' . $doc_id ] = (string) $settings['custom_js'];
            }
        }

        foreach ( $blocks as $key => $code ) {
            $code = trim( $code );
            if ( '' === $code ) {
                continue;
            }
            // Never let the code close the script tag early.
            $code = str_replace( '</script', '<\/script', $code );
            echo "\n<script id=\"loopkit-custom-js-" . esc_attr( $key ) . "\">\n";
            echo "( function ( $ ) {\ntry {\n" . $code . "\n} catch ( e ) { if ( window.console ) console.error( 'Loopkit Custom JS:', e ); }\n} )( window.jQuery || null );\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo "</script>\n";
        }
    }

    public function keep_paged_singular( $redirect_url, $requested_url ) {
        if ( is_singular() && (int) get_query_var( 'page' ) > 1 ) {
            $post_id = get_queried_object_id();
            if ( 'builder' === get_post_meta( $post_id, '_loopkit_edit_mode', true ) || self::request_has_theme_template() ) {
                return false;
            }
        }
        return $redirect_url;
    }

    /**
     * Prevent canonical redirects on canvas iframe requests (e.g. front page or preview query).
     *
     * @param string $redirect_url The target URL.
     * @param string $requested_url The requested URL.
     * @return string|false
     */
    public function prevent_canvas_canonical_redirect( $redirect_url, $requested_url ) {
        if ( isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return false;
        }
        return $redirect_url;
    }

    /**
     * Safely decode elements from raw meta data (string JSON or array).
     *
     * @param mixed $raw Raw meta value.
     * @return array Decoded elements array.
     */
    public static function decode_elements( $raw ) {
        if ( is_array( $raw ) ) {
            return $raw;
        }
        if ( is_string( $raw ) && '' !== trim( $raw ) ) {
            $decoded = json_decode( $raw, true );
            if ( ! is_array( $decoded ) && function_exists( 'wp_unslash' ) ) {
                $decoded = json_decode( wp_unslash( $raw ), true );
            }
            if ( ! is_array( $decoded ) ) {
                $decoded = json_decode( stripslashes( $raw ), true );
            }
            return is_array( $decoded ) ? $decoded : array();
        }
        return array();
    }

    /**
     * Ensure root level elements are Sections.
     * Converts any root-level container to a Section preserving ID and settings.
     * Wraps any root-level widget into a Section.
     *
     * @param array $elements Elements list.
     * @return array
     */
    public static function ensure_sections( $elements ) {
        if ( ! is_array( $elements ) ) {
            return array();
        }
        $site_settings = class_exists( 'Loopkit_Site_Settings' ) ? Loopkit_Site_Settings::get_settings() : array();
        $default_width = ! empty( $site_settings['container_width'] ) ? intval( $site_settings['container_width'] ) : 1140;

        $wrapped = array();
        foreach ( $elements as $el ) {
            if ( ! is_array( $el ) ) {
                continue;
            }

            $el_type = $el['elType'] ?? '';

            // Global instances: global Section valid at root, global Container wrapped in Section
            if ( 'global' === $el_type ) {
                $g_type = $el['globalType'] ?? 'section';
                if ( 'container' === $g_type ) {
                    $wrapped[] = array(
                        'id'       => loopkit_generate_id(),
                        'elType'   => 'section',
                        'isInner'  => false,
                        'settings' => array(
                            'container_type' => 'flexbox',
                            'content_width'  => 'boxed',
                            'width'          => array( 'size' => $default_width, 'unit' => 'px' ),
                            'flex_direction' => 'column',
                            'flex_gap'       => array( 'column' => '20', 'row' => '20', 'unit' => 'px', 'isLinked' => true ),
                            'padding'        => array( 'top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10', 'unit' => 'px', 'isLinked' => true ),
                            'html_tag'       => 'section',
                        ),
                        'elements' => array( $el ),
                    );
                } else {
                    $wrapped[] = $el;
                }
                continue;
            }

            if ( 'widget' === $el_type ) {
                $wrapped[] = array(
                    'id'       => loopkit_generate_id(),
                    'elType'   => 'section',
                    'isInner'  => false,
                    'settings' => array(
                        'container_type' => 'flexbox',
                        'content_width'  => 'boxed',
                        'width'          => array( 'size' => $default_width, 'unit' => 'px' ),
                        'flex_direction' => 'column',
                        'flex_gap'       => array( 'column' => '20', 'row' => '20', 'unit' => 'px', 'isLinked' => true ),
                        'padding'        => array( 'top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10', 'unit' => 'px', 'isLinked' => true ),
                        'html_tag'       => 'section',
                    ),
                    'elements' => array( $el ),
                );
            } elseif ( 'container' === $el_type ) {
                $converted             = $el;
                $converted['elType']   = 'section';
                $converted['settings'] = is_array( $el['settings'] ?? null ) ? $el['settings'] : array();
                if ( empty( $converted['settings']['html_tag'] ) || 'div' === $converted['settings']['html_tag'] ) {
                    $converted['settings']['html_tag'] = 'section';
                }
                $wrapped[] = $converted;
            } else {
                $wrapped[] = $el;
            }
        }
        return $wrapped;
    }

    /**
     * Add Loopkit body classes (Section 20).
     *
     * @param array $classes Existing body classes.
     * @return array
     */
    public function add_body_classes( $classes ) {
        if ( ! is_singular() && ! is_front_page() && ! isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return $classes;
        }

        $post_id = get_queried_object_id() ?: get_the_ID();
        if ( ! $post_id && isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $post_id = absint( $_GET['loopkit_canvas'] );
        }

        if ( ! $post_id ) {
            return $classes;
        }

        $edit_mode = get_post_meta( $post_id, '_loopkit_edit_mode', true );
        $is_canvas = isset( $_GET['loopkit_canvas'] ); // phpcs:ignore WordPress.Security.NonceVerification

        if ( 'builder' === $edit_mode || $is_canvas ) {
            $classes[] = 'loopkit-kit';
            $classes[] = 'loopkit-page';
            $classes[] = 'loopkit-page-' . $post_id;

            $layout = class_exists( 'Loopkit_Templates' ) ? Loopkit_Templates::get_page_layout( $post_id ) : 'loopkit-full-width';
            $classes[] = 'loopkit-layout-' . $layout;

            if ( 'loopkit-full-width' === $layout ) {
                $classes[] = 'loopkit-template-full-width';
                $classes[] = 'page-template-loopkit-full-width';
            } elseif ( 'loopkit-canvas' === $layout ) {
                $classes[] = 'loopkit-template-canvas';
                $classes[] = 'page-template-loopkit-canvas';
            }

            if ( $is_canvas ) {
                $classes[] = 'lk-canvas-body';
            }
        }

        return array_unique( $classes );
    }

    /**
     * Replace the_content with Loopkit-rendered elements for builder pages (Section 20)
     * and editor canvas iframe.
     *
     * @param string $content Original content.
     * @return string Modified content.
     */
    public function render_content( $content ) {
        // A Post Content widget on a Loopkit-built page asks for the_content() again, which
        // would render this same layout inside itself: the page appeared twice and weighed
        // twice as much. Nested requests for the document already being rendered are skipped.
        static $rendering = 0;

        $post_id = get_the_ID() ?: get_queried_object_id();
        if ( ! $post_id && is_front_page() ) {
            $post_id = (int) get_option( 'page_on_front' );
        }
        if ( ! $post_id && isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $post_id = absint( $_GET['loopkit_canvas'] );
        }
        if ( ! $post_id ) {
            global $post;
            $post_id = ( $post && isset( $post->ID ) ) ? $post->ID : 0;
        }
        if ( ! $post_id && isset( $_GET['page_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $post_id = absint( $_GET['page_id'] );
        }
        if ( ! $post_id && isset( $_GET['p'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $post_id = absint( $_GET['p'] );
        }

        if ( ! $post_id ) {
            return $content;
        }

        $edit_mode = get_post_meta( $post_id, '_loopkit_edit_mode', true );
        $is_canvas = isset( $_GET['loopkit_canvas'] ); // phpcs:ignore WordPress.Security.NonceVerification

        if ( 'builder' !== $edit_mode && ! $is_canvas ) {
            return $content;
        }

        // Already rendering this document: hand back the raw content instead of recursing.
        if ( $rendering === (int) $post_id ) {
            return $content;
        }
        $rendering = (int) $post_id;

        // Inside canvas preview: render canvas elements with editing handles
        if ( $is_canvas && (int) absint( $_GET['loopkit_canvas'] ) === (int) $post_id ) {
            $html      = $this->render_canvas_elements( $content );
            $rendering = 0;
            return $html;
        }

        $raw      = get_post_meta( $post_id, '_loopkit_data', true );
        $elements = self::decode_elements( $raw );

        if ( empty( $elements ) || ! is_array( $elements ) ) {
            $rendering = 0;
            return $content;
        }

        $elements = self::ensure_sections( $elements );

        // Section 20 wrapper: <div class="loopkit loopkit-{ID}" data-loopkit-type="wp-page">
        $html  = '<div class="loopkit loopkit-' . esc_attr( $post_id ) . '" data-loopkit-type="wp-page">';
        $html .= self::render_elements( $elements, false );
        $html .= '</div>';

        $rendering = 0;

        return $html;
    }

    /**
     * Loopkit base CSS/JS: frontend + global CSS, nav menu JS, and hover / lightbox /
     * animation / motion assets when the given elements (or any Theme Builder template) use them.
     *
     * @param array $elements  Elements of the current document (may be empty).
     * @param bool  $is_canvas Whether this is the editor canvas.
     */
    public function enqueue_base_assets( $elements, $is_canvas = false ) {
        // Google Fonts: a typography choice only shows if the font is actually loaded.
        if ( class_exists( 'Loopkit_Fonts' ) ) {
            Loopkit_Fonts::collect( $elements );

            // Site Settings global fonts
            if ( class_exists( 'Loopkit_Site_Settings' ) ) {
                $site = Loopkit_Site_Settings::get_settings();
                foreach ( array( 'font_primary', 'font_secondary', 'font_text', 'font_accent' ) as $key ) {
                    if ( ! empty( $site[ $key ] ) ) {
                        Loopkit_Fonts::add( $site[ $key ] );
                    }
                }
                Loopkit_Fonts::collect( isset( $site['theme_typography'] ) ? $site['theme_typography'] : array() );
            }

            // Header / Footer / Single / Archive templates rendering on this request
            if ( class_exists( 'Loopkit_Theme_Builder' ) ) {
                $tb = Loopkit_Theme_Builder::get_instance();
                foreach ( array( 'header', 'footer' ) as $loc ) {
                    $winner = $tb->get_active_template_for_location( $loc );
                    if ( $winner && ! empty( $winner['id'] ) ) {
                        Loopkit_Fonts::collect_from_post( (int) $winner['id'] );
                    }
                }
                foreach ( array( 'get_single_template_for_request', 'get_archive_template_for_request' ) as $method ) {
                    if ( method_exists( $tb, $method ) ) {
                        $winner = $tb->$method();
                        if ( $winner && ! empty( $winner['id'] ) ) {
                            Loopkit_Fonts::collect_from_post( (int) $winner['id'] );
                        }
                    }
                }
            }

            Loopkit_Fonts::enqueue();
        }

        $use_optimized = class_exists( 'Loopkit_Assets' ) && Loopkit_Assets::is_optimization_enabled();
        $bundle_file   = LOOPKIT_PLUGIN_DIR . 'assets/js/loopkit-frontend.min.js';

        if ( $use_optimized && file_exists( $bundle_file ) ) {
            wp_enqueue_script(
                'loopkit-frontend',
                LOOPKIT_PLUGIN_URL . 'assets/js/loopkit-frontend.min.js',
                array(),
                LOOPKIT_VERSION,
                true
            );
            // Ensure compatibility with any custom code expecting the loopkit-widgets handle
            wp_register_script( 'loopkit-widgets', false, array( 'loopkit-frontend' ), LOOPKIT_VERSION, true );
            wp_localize_script( 'loopkit-frontend', 'loopkitWidgets', array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'formNonce' => wp_create_nonce( 'loopkit_form' ),
            ) );
        } else {
            // Same scripts, unbundled and unminified, for local debugging. The source
            // files are the single source of truth: loopkit-frontend.min.js is built
            // from loopkit-frontend.js and loopkit-pro.min.js from loopkit-pro.js.
            wp_enqueue_script(
                'loopkit-frontend',
                class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'assets/js/loopkit-frontend.js' ) : ( LOOPKIT_PLUGIN_URL . 'assets/js/loopkit-frontend.js' ),
                array(),
                LOOPKIT_VERSION,
                true
            );
            // Pro widgets behaviour (countdown ticking, animated headline, marquee).
            // Depends on loopkit-frontend: it delegates to the implementations there.
            wp_enqueue_script(
                'loopkit-pro',
                class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'assets/js/loopkit-pro.js' ) : ( LOOPKIT_PLUGIN_URL . 'assets/js/loopkit-pro.js' ),
                array( 'loopkit-frontend' ),
                LOOPKIT_VERSION,
                true
            );
            wp_localize_script( 'loopkit-frontend', 'loopkitWidgets', array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'formNonce' => wp_create_nonce( 'loopkit_form' ),
            ) );
            wp_enqueue_script(
                'loopkit-nav-menu',
                class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'assets/js/loopkit-nav-menu.js' ) : ( LOOPKIT_PLUGIN_URL . 'assets/js/loopkit-nav-menu.js' ),
                array(),
                LOOPKIT_VERSION,
                true
            );
        }

        // Always enqueue base frontend stylesheet
        wp_enqueue_style(
            'loopkit-frontend',
            class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'assets/css/loopkit-frontend.css' ) : ( LOOPKIT_PLUGIN_URL . 'assets/css/loopkit-frontend.css' ),
            array(),
            LOOPKIT_VERSION
        );

        // Dashicons: the Search Form and Mini Cart widgets print dashicon glyphs, and on the
        // front end that stylesheet is not loaded by default, so the icons came out blank.
        wp_enqueue_style( 'dashicons' );

        // Enqueue global compiled CSS from Site Settings
        $upload_dir = wp_upload_dir();
        $global_css_file = trailingslashit( $upload_dir['basedir'] ) . 'loopkit/css/global.css';
        if ( file_exists( $global_css_file ) ) {
            $global_time = get_option( 'loopkit_global_css_time', LOOPKIT_VERSION );
            wp_enqueue_style(
                'loopkit-global',
                trailingslashit( $upload_dir['baseurl'] ) . 'loopkit/css/global.css',
                array( 'loopkit-frontend' ),
                $global_time
            );
        } else {
            $global_css = get_option( 'loopkit_global_css', '' );
            if ( empty( $global_css ) && class_exists( 'Loopkit_Site_Settings' ) ) {
                $global_css = Loopkit_Site_Settings::build_global_css();
            }
            if ( ! empty( $global_css ) ) {
                wp_add_inline_style( 'loopkit-frontend', $global_css );
            }
        }

        // Widget front end CSS/JS (lightbox, hover animations) loads only on pages that use that widget / feature
        $needs_hover = $is_canvas || self::elements_have_feature( $elements, 'hover_animation' );
        if ( $needs_hover ) {
            wp_enqueue_style(
                'loopkit-hover-animations',
                class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'assets/css/loopkit-hover-animations.css' ) : ( LOOPKIT_PLUGIN_URL . 'assets/css/loopkit-hover-animations.css' ),
                array(),
                LOOPKIT_VERSION
            );
        }

        $needs_lightbox = $is_canvas || self::elements_have_feature( $elements, 'lightbox' );
        if ( $needs_lightbox ) {
            wp_enqueue_style(
                'loopkit-lightbox',
                class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'assets/css/loopkit-lightbox.css' ) : ( LOOPKIT_PLUGIN_URL . 'assets/css/loopkit-lightbox.css' ),
                array(),
                LOOPKIT_VERSION
            );
            wp_enqueue_script(
                'loopkit-lightbox',
                class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'assets/js/loopkit-lightbox.js' ) : ( LOOPKIT_PLUGIN_URL . 'assets/js/loopkit-lightbox.js' ),
                array(),
                LOOPKIT_VERSION,
                true
            );
        }

        // Entrance animations CSS/JS loads conditionally only if any element on the page has entrance_animation set
        $needs_animations = $is_canvas || self::elements_have_entrance_animation( $elements );
        if ( $needs_animations ) {
            wp_enqueue_style(
                'loopkit-animations',
                class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'assets/css/loopkit-animations.css' ) : ( LOOPKIT_PLUGIN_URL . 'assets/css/loopkit-animations.css' ),
                array(),
                LOOPKIT_VERSION
            );
            wp_enqueue_script(
                'loopkit-animations',
                class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'assets/js/loopkit-animations.js' ) : ( LOOPKIT_PLUGIN_URL . 'assets/js/loopkit-animations.js' ),
                array(),
                LOOPKIT_VERSION,
                true
            );
        }

        // Motion Effects (Sticky, Scroll, Mouse) CSS/JS loads conditionally
        $needs_motion_effects = $is_canvas ||
            self::elements_have_sticky( $elements ) ||
            self::elements_have_scroll_effects( $elements ) ||
            self::elements_have_mouse_effects( $elements );
        if ( $needs_motion_effects ) {
            wp_enqueue_style(
                'loopkit-motion-effects',
                class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'assets/css/loopkit-motion-effects.css' ) : ( LOOPKIT_PLUGIN_URL . 'assets/css/loopkit-motion-effects.css' ),
                array(),
                LOOPKIT_VERSION
            );
            wp_enqueue_script(
                'loopkit-motion-effects',
                class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'assets/js/loopkit-motion-effects.js' ) : ( LOOPKIT_PLUGIN_URL . 'assets/js/loopkit-motion-effects.js' ),
                array(),
                LOOPKIT_VERSION,
                true
            );
        }
    }

    /**
     * Whether a Loopkit Header, Footer, Single or Archive template renders on this request.
     *
     * @return bool
     */
    public static function request_has_theme_template() {
        if ( ! class_exists( 'Loopkit_Theme_Builder' ) ) {
            return false;
        }
        $tb = Loopkit_Theme_Builder::get_instance();
        foreach ( array( 'header', 'footer' ) as $loc ) {
            if ( $tb->get_active_template_for_location( $loc ) ) {
                return true;
            }
        }
        if ( method_exists( $tb, 'get_archive_template_for_request' ) && $tb->get_archive_template_for_request() ) {
            return true;
        }
        return method_exists( $tb, 'get_single_template_for_request' ) && (bool) $tb->get_single_template_for_request();
    }


    /**
     * Enqueue per-page CSS on the front end (Section 3 & 20).
     */
    /**
     * Register (not enqueue) the popup script and its styles.
     */
    public function register_popup_assets() {
        wp_register_script(
            'loopkit-popup',
            class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'assets/js/loopkit-popup.js' ) : ( LOOPKIT_PLUGIN_URL . 'assets/js/loopkit-popup.js' ),
            array(),
            LOOPKIT_VERSION,
            true
        );
        $popup_css = class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'assets/css/loopkit-popup.css' ) : ( LOOPKIT_PLUGIN_URL . 'assets/css/loopkit-popup.css' );
        wp_register_style( 'loopkit-popup', $popup_css, array(), LOOPKIT_VERSION );
        wp_style_add_data( 'loopkit-popup', 'path', class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_path( 'assets/css/loopkit-popup.css' ) : ( LOOPKIT_PLUGIN_DIR . 'assets/css/loopkit-popup.css' ) );
    }

    public function enqueue_page_css() {
        if ( ! is_singular() && ! isset( $_GET['loopkit_canvas'] ) && ! isset( $_GET['page_id'] ) && ! isset( $_GET['p'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            // Archives, search results and the blog index have no Loopkit document of their own,
            // but a Loopkit Header, Footer or Archive template may render there and needs the base assets.
            // (get_queried_object_id() is a term/user ID here, never a post ID, so no post CSS is loaded.)
            if ( self::request_has_theme_template() ) {
                $this->enqueue_base_assets( array(), false );
            }
            return;
        }

        $post_id = get_queried_object_id() ?: get_the_ID();
        if ( ! $post_id && is_front_page() ) {
            $post_id = (int) get_option( 'page_on_front' );
        }
        if ( ! $post_id && isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $post_id = absint( $_GET['loopkit_canvas'] );
        }
        if ( ! $post_id ) {
            global $post;
            $post_id = ( $post && isset( $post->ID ) ) ? $post->ID : 0;
        }
        if ( ! $post_id && isset( $_GET['page_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $post_id = absint( $_GET['page_id'] );
        }
        if ( ! $post_id && isset( $_GET['p'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $post_id = absint( $_GET['p'] );
        }

        if ( ! $post_id ) {
            return;
        }

        $edit_mode = get_post_meta( $post_id, '_loopkit_edit_mode', true );
        $is_canvas = isset( $_GET['loopkit_canvas'] ); // phpcs:ignore WordPress.Security.NonceVerification

        // A normal (non-Loopkit) post or page still needs Loopkit's base CSS/JS when a Loopkit
        // Header, Footer or Single template renders on it.
        if ( 'builder' !== $edit_mode && ! $is_canvas ) {
            if ( self::request_has_theme_template() ) {
                $this->enqueue_base_assets( array(), false );
            }
            return;
        }

        $raw_data = get_post_meta( $post_id, '_loopkit_data', true );
        $elements = self::decode_elements( $raw_data );

        $this->enqueue_base_assets( $elements, $is_canvas );

        $advanced = get_option( 'loopkit_advanced_settings', array() );
        $method   = $advanced['css_print_method'] ?? 'external';

        $file_loaded = false;
        if ( 'external' === $method ) {
            $upload_dir = wp_upload_dir();
            if ( empty( $upload_dir['error'] ) ) {
                $css_file = trailingslashit( $upload_dir['basedir'] ) . 'loopkit/css/post-' . $post_id . '.css';

                // Missing file: rebuild it from the stored layout instead of serving
                // nothing. This used to leave the page permanently unstyled, because
                // the CSS only ever got written on save -- so a wiped uploads folder,
                // a migration or a staging copy lost the entire design of every
                // Loopkit page with no way back short of re-saving each one by hand.
                if ( ! file_exists( $css_file ) && class_exists( 'Loopkit_Rest_Api' ) ) {
                    Loopkit_Rest_Api::rebuild_css_file( $post_id );
                }

                if ( file_exists( $css_file ) ) {
                    $css_time = get_post_meta( $post_id, '_loopkit_css_time', true ) ?: LOOPKIT_VERSION;
                    wp_enqueue_style(
                        'loopkit-post-' . $post_id,
                        trailingslashit( $upload_dir['baseurl'] ) . 'loopkit/css/post-' . $post_id . '.css',
                        array( 'loopkit-frontend' ),
                        $css_time
                    );
                    $file_loaded = true;
                }
            }
        }

        // Fallback or inline print method: add inline CSS to loopkit-frontend
        if ( ! $file_loaded ) {
            $css = get_post_meta( $post_id, '_loopkit_css', true );
            if ( ! empty( $css ) ) {
                wp_add_inline_style( 'loopkit-frontend', $css );
            }
        }

        // Enqueue CSS for any global templates used on this page
        $raw_data = get_post_meta( $post_id, '_loopkit_data', true );
        $elements = self::decode_elements( $raw_data );
        $global_ids = array();
        self::collect_global_ids( $elements, $global_ids );

        // Also collect globals used in active header/footer if Theme Builder is active
        if ( class_exists( 'Loopkit_Theme_Builder' ) ) {
            $tb_cache = get_option( 'loopkit_theme_builder_cache', array() );
            foreach ( array( 'header', 'footer', 'single', 'archive' ) as $loc ) {
                if ( ! empty( $tb_cache[ $loc ] ) && is_array( $tb_cache[ $loc ] ) ) {
                    foreach ( $tb_cache[ $loc ] as $tpl_info ) {
                        $tpl_id = $tpl_info['id'] ?? 0;
                        if ( $tpl_id ) {
                            $t_raw = get_post_meta( $tpl_id, '_loopkit_data', true );
                            $t_els = self::decode_elements( $t_raw );
                            self::collect_global_ids( $t_els, $global_ids );
                        }
                    }
                }
            }
        }

        $upload_dir = wp_upload_dir();
        foreach ( array_unique( $global_ids ) as $gid ) {
            $g_loaded = false;
            if ( 'external' === $method && empty( $upload_dir['error'] ) ) {
                $g_css_file = trailingslashit( $upload_dir['basedir'] ) . 'loopkit/css/post-' . $gid . '.css';
                if ( file_exists( $g_css_file ) ) {
                    $g_css_time = get_post_meta( $gid, '_loopkit_css_time', true ) ?: LOOPKIT_VERSION;
                    wp_enqueue_style(
                        'loopkit-global-' . $gid,
                        trailingslashit( $upload_dir['baseurl'] ) . 'loopkit/css/post-' . $gid . '.css',
                        array( 'loopkit-frontend' ),
                        $g_css_time
                    );
                    $g_loaded = true;
                }
            }
            if ( ! $g_loaded ) {
                $g_css = get_post_meta( $gid, '_loopkit_css', true );
                if ( ! empty( $g_css ) ) {
                    wp_add_inline_style( 'loopkit-frontend', $g_css );
                }
            }
        }
    }

    /**
     * Recursively collect global template IDs from elements.
     *
     * @param array $elements Elements to scan.
     * @param array $ids      Accumulator (by reference).
     */
    public static function collect_global_ids( $elements, &$ids ) {
        if ( ! is_array( $elements ) ) return;
        foreach ( $elements as $el ) {
            if ( ! is_array( $el ) ) continue;
            if ( ( $el['elType'] ?? '' ) === 'global' && ! empty( $el['templateId'] ) ) {
                $tid = absint( $el['templateId'] );
                if ( $tid && ! in_array( $tid, $ids, true ) ) {
                    $ids[] = $tid;
                }
            }
            if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
                self::collect_global_ids( $el['elements'], $ids );
            }
        }
    }

    /**
     * Recursively check if elements use a specific feature (e.g. hover_animation, lightbox).
     *
     * @param array  $elements Elements list.
     * @param string $feature  Feature name ('hover_animation' or 'lightbox').
     * @return bool
     */
    public static function elements_have_feature( $elements, $feature ) {
        if ( ! is_array( $elements ) ) {
            return false;
        }
        foreach ( $elements as $el ) {
            if ( ! is_array( $el ) ) {
                continue;
            }
            $settings    = $el['settings'] ?? array();
            $widget_type = $el['widgetType'] ?? $el['widget'] ?? '';

            if ( 'hover_animation' === $feature ) {
                if ( ! empty( $settings['hover_animation'] ) || ! empty( $settings['button_hover_animation'] ) ) {
                    return true;
                }
            } elseif ( 'lightbox' === $feature && 'image-gallery' === $widget_type && ( $settings['lightbox'] ?? 'yes' ) !== 'no' ) {
                return true;
            } elseif ( 'lightbox' === $feature ) {
                if ( 'image' === $widget_type && ( ( $settings['open_lightbox'] ?? '' ) === 'yes' || ( ( $settings['link_to'] ?? '' ) === 'file' && ( $settings['open_lightbox'] ?? 'default' ) !== 'no' ) ) ) {
                    return true;
                }
            } elseif ( 'entrance_animation' === $feature ) {
                if ( ( ! empty( $settings['entrance_animation'] ) && 'none' !== $settings['entrance_animation'] ) ||
                     ( ! empty( $settings['entrance_animation_tablet'] ) && 'none' !== $settings['entrance_animation_tablet'] ) ||
                     ( ! empty( $settings['entrance_animation_mobile'] ) && 'none' !== $settings['entrance_animation_mobile'] ) ) {
                    return true;
                }
            } elseif ( 'sticky' === $feature ) {
                if ( ( ! empty( $settings['sticky'] ) && 'none' !== $settings['sticky'] ) ||
                     ( ! empty( $settings['sticky_tablet'] ) && 'none' !== $settings['sticky_tablet'] ) ||
                     ( ! empty( $settings['sticky_mobile'] ) && 'none' !== $settings['sticky_mobile'] ) ) {
                    return true;
                }
            } elseif ( 'scroll_effects' === $feature ) {
                if ( ! empty( $settings['scrolling_effects'] ) && ( 'yes' === $settings['scrolling_effects'] || true === $settings['scrolling_effects'] ) ) {
                    if ( ( ! empty( $settings['scroll_vertical'] ) && ( 'yes' === $settings['scroll_vertical'] || true === $settings['scroll_vertical'] ) ) ||
                         ( ! empty( $settings['scroll_horizontal'] ) && ( 'yes' === $settings['scroll_horizontal'] || true === $settings['scroll_horizontal'] ) ) ||
                         ( ! empty( $settings['scroll_transparency'] ) && ( 'yes' === $settings['scroll_transparency'] || true === $settings['scroll_transparency'] ) ) ||
                         ( ! empty( $settings['scroll_blur'] ) && ( 'yes' === $settings['scroll_blur'] || true === $settings['scroll_blur'] ) ) ||
                         ( ! empty( $settings['scroll_rotate'] ) && ( 'yes' === $settings['scroll_rotate'] || true === $settings['scroll_rotate'] ) ) ||
                         ( ! empty( $settings['scroll_scale'] ) && ( 'yes' === $settings['scroll_scale'] || true === $settings['scroll_scale'] ) ) ) {
                        return true;
                    }
                }
            } elseif ( 'mouse_effects' === $feature ) {
                if ( ! empty( $settings['mouse_effects'] ) && ( 'yes' === $settings['mouse_effects'] || true === $settings['mouse_effects'] ) ) {
                    if ( ( ! empty( $settings['mouse_track'] ) && ( 'yes' === $settings['mouse_track'] || true === $settings['mouse_track'] ) ) ||
                         ( ! empty( $settings['mouse_tilt'] ) && ( 'yes' === $settings['mouse_tilt'] || true === $settings['mouse_tilt'] ) ) ) {
                        return true;
                    }
                }
            }

            if ( ! empty( $el['elements'] ) && self::elements_have_feature( $el['elements'], $feature ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if page elements or any embedded global template elements use entrance animations.
     *
     * @param array $elements Elements array.
     * @return bool
     */
    public static function elements_have_entrance_animation( $elements ) {
        if ( self::elements_have_feature( $elements, 'entrance_animation' ) ) {
            return true;
        }
        $global_ids = array();
        self::collect_global_ids( $elements, $global_ids );
        foreach ( array_unique( $global_ids ) as $gid ) {
            $t_raw = get_post_meta( $gid, '_loopkit_data', true );
            $t_els = self::decode_elements( $t_raw );
            if ( self::elements_have_feature( $t_els, 'entrance_animation' ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if page elements or any embedded global template elements use sticky positioning.
     *
     * @param array $elements Elements array.
     * @return bool
     */
    public static function elements_have_sticky( $elements ) {
        if ( self::elements_have_feature( $elements, 'sticky' ) ) {
            return true;
        }
        $global_ids = array();
        self::collect_global_ids( $elements, $global_ids );
        foreach ( array_unique( $global_ids ) as $gid ) {
            $t_raw = get_post_meta( $gid, '_loopkit_data', true );
            $t_els = self::decode_elements( $t_raw );
            if ( self::elements_have_feature( $t_els, 'sticky' ) ) {
                return true;
            }
        }
        // Also check Theme Builder active header and footer templates
        if ( class_exists( 'Loopkit_Theme_Builder' ) ) {
            $tb_cache = get_option( 'loopkit_theme_builder_cache', array() );
            foreach ( array( 'header', 'footer', 'single', 'archive' ) as $loc ) {
                if ( ! empty( $tb_cache[ $loc ] ) && is_array( $tb_cache[ $loc ] ) ) {
                    foreach ( $tb_cache[ $loc ] as $tpl_info ) {
                        $tpl_id = $tpl_info['id'] ?? 0;
                        if ( $tpl_id ) {
                            $t_raw = get_post_meta( $tpl_id, '_loopkit_data', true );
                            $t_els = self::decode_elements( $t_raw );
                            if ( self::elements_have_feature( $t_els, 'sticky' ) ) {
                                return true;
                            }
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Check if page elements or any embedded global template elements use scrolling effects.
     *
     * @param array $elements Elements array.
     * @return bool
     */
    public static function elements_have_scroll_effects( $elements ) {
        if ( self::elements_have_feature( $elements, 'scroll_effects' ) ) {
            return true;
        }
        $global_ids = array();
        self::collect_global_ids( $elements, $global_ids );
        foreach ( array_unique( $global_ids ) as $gid ) {
            $t_raw = get_post_meta( $gid, '_loopkit_data', true );
            $t_els = self::decode_elements( $t_raw );
            if ( self::elements_have_feature( $t_els, 'scroll_effects' ) ) {
                return true;
            }
        }
        if ( class_exists( 'Loopkit_Theme_Builder' ) ) {
            $tb_cache = get_option( 'loopkit_theme_builder_cache', array() );
            foreach ( array( 'header', 'footer', 'single', 'archive' ) as $loc ) {
                if ( ! empty( $tb_cache[ $loc ] ) && is_array( $tb_cache[ $loc ] ) ) {
                    foreach ( $tb_cache[ $loc ] as $tpl_info ) {
                        $tpl_id = $tpl_info['id'] ?? 0;
                        if ( $tpl_id ) {
                            $t_raw = get_post_meta( $tpl_id, '_loopkit_data', true );
                            $t_els = self::decode_elements( $t_raw );
                            if ( self::elements_have_feature( $t_els, 'scroll_effects' ) ) {
                                return true;
                            }
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Check if page elements or any embedded global template elements use mouse effects.
     *
     * @param array $elements Elements array.
     * @return bool
     */
    public static function elements_have_mouse_effects( $elements ) {
        if ( self::elements_have_feature( $elements, 'mouse_effects' ) ) {
            return true;
        }
        $global_ids = array();
        self::collect_global_ids( $elements, $global_ids );
        foreach ( array_unique( $global_ids ) as $gid ) {
            $t_raw = get_post_meta( $gid, '_loopkit_data', true );
            $t_els = self::decode_elements( $t_raw );
            if ( self::elements_have_feature( $t_els, 'mouse_effects' ) ) {
                return true;
            }
        }
        if ( class_exists( 'Loopkit_Theme_Builder' ) ) {
            $tb_cache = get_option( 'loopkit_theme_builder_cache', array() );
            foreach ( array( 'header', 'footer', 'single', 'archive' ) as $loc ) {
                if ( ! empty( $tb_cache[ $loc ] ) && is_array( $tb_cache[ $loc ] ) ) {
                    foreach ( $tb_cache[ $loc ] as $tpl_info ) {
                        $tpl_id = $tpl_info['id'] ?? 0;
                        if ( $tpl_id ) {
                            $t_raw = get_post_meta( $tpl_id, '_loopkit_data', true );
                            $t_els = self::decode_elements( $t_raw );
                            if ( self::elements_have_feature( $t_els, 'mouse_effects' ) ) {
                                return true;
                            }
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Extract classes, data attributes, and badges for Motion Effects (Scroll & Mouse).
     *
     * @param array $settings    Element settings.
     * @param bool  $editor_mode Whether rendering in editor mode.
     * @return array Array with 'classes', 'attrs', and 'badges'.
     */
    public static function get_motion_effects_data( $settings, $editor_mode = false ) {
        $classes = '';
        $attrs   = '';
        $badges  = '';

        $has_scroll = ( ! empty( $settings['scrolling_effects'] ) && ( 'yes' === $settings['scrolling_effects'] || true === $settings['scrolling_effects'] ) );
        $has_mouse  = ( ! empty( $settings['mouse_effects'] ) && ( 'yes' === $settings['mouse_effects'] || true === $settings['mouse_effects'] ) );

        // Scroll effects
        if ( $has_scroll ) {
            $effects_config = array();
            $scroll_active  = false;

            if ( ! empty( $settings['scroll_vertical'] ) && ( 'yes' === $settings['scroll_vertical'] || true === $settings['scroll_vertical'] ) ) {
                $scroll_active = true;
                $effects_config['vertical'] = array(
                    'direction' => $settings['scroll_vertical_direction'] ?? 'up',
                    'speed'     => isset( $settings['scroll_vertical_speed'] ) ? floatval( $settings['scroll_vertical_speed'] ) : 4.0,
                    'enter'     => isset( $settings['scroll_vertical_viewport_enter'] ) ? ( is_array( $settings['scroll_vertical_viewport_enter'] ) ? intval( $settings['scroll_vertical_viewport_enter']['size'] ?? 0 ) : intval( $settings['scroll_vertical_viewport_enter'] ) ) : 0,
                    'exit'      => isset( $settings['scroll_vertical_viewport_exit'] ) ? ( is_array( $settings['scroll_vertical_viewport_exit'] ) ? intval( $settings['scroll_vertical_viewport_exit']['size'] ?? 100 ) : intval( $settings['scroll_vertical_viewport_exit'] ) ) : 100,
                );
            }
            if ( ! empty( $settings['scroll_horizontal'] ) && ( 'yes' === $settings['scroll_horizontal'] || true === $settings['scroll_horizontal'] ) ) {
                $scroll_active = true;
                $effects_config['horizontal'] = array(
                    'direction' => $settings['scroll_horizontal_direction'] ?? 'left',
                    'speed'     => isset( $settings['scroll_horizontal_speed'] ) ? floatval( $settings['scroll_horizontal_speed'] ) : 4.0,
                    'enter'     => isset( $settings['scroll_horizontal_viewport_enter'] ) ? ( is_array( $settings['scroll_horizontal_viewport_enter'] ) ? intval( $settings['scroll_horizontal_viewport_enter']['size'] ?? 0 ) : intval( $settings['scroll_horizontal_viewport_enter'] ) ) : 0,
                    'exit'      => isset( $settings['scroll_horizontal_viewport_exit'] ) ? ( is_array( $settings['scroll_horizontal_viewport_exit'] ) ? intval( $settings['scroll_horizontal_viewport_exit']['size'] ?? 100 ) : intval( $settings['scroll_horizontal_viewport_exit'] ) ) : 100,
                );
            }
            if ( ! empty( $settings['scroll_transparency'] ) && ( 'yes' === $settings['scroll_transparency'] || true === $settings['scroll_transparency'] ) ) {
                $scroll_active = true;
                $effects_config['transparency'] = array(
                    'direction' => $settings['scroll_transparency_direction'] ?? 'fade_in',
                    'level'     => isset( $settings['scroll_transparency_level'] ) ? floatval( $settings['scroll_transparency_level'] ) : 5.0,
                    'enter'     => isset( $settings['scroll_transparency_viewport_enter'] ) ? ( is_array( $settings['scroll_transparency_viewport_enter'] ) ? intval( $settings['scroll_transparency_viewport_enter']['size'] ?? 0 ) : intval( $settings['scroll_transparency_viewport_enter'] ) ) : 0,
                    'exit'      => isset( $settings['scroll_transparency_viewport_exit'] ) ? ( is_array( $settings['scroll_transparency_viewport_exit'] ) ? intval( $settings['scroll_transparency_viewport_exit']['size'] ?? 100 ) : intval( $settings['scroll_transparency_viewport_exit'] ) ) : 100,
                );
            }
            if ( ! empty( $settings['scroll_blur'] ) && ( 'yes' === $settings['scroll_blur'] || true === $settings['scroll_blur'] ) ) {
                $scroll_active = true;
                $effects_config['blur'] = array(
                    'direction' => $settings['scroll_blur_direction'] ?? 'blur_in',
                    'level'     => isset( $settings['scroll_blur_level'] ) ? floatval( $settings['scroll_blur_level'] ) : 5.0,
                    'enter'     => isset( $settings['scroll_blur_viewport_enter'] ) ? ( is_array( $settings['scroll_blur_viewport_enter'] ) ? intval( $settings['scroll_blur_viewport_enter']['size'] ?? 0 ) : intval( $settings['scroll_blur_viewport_enter'] ) ) : 0,
                    'exit'      => isset( $settings['scroll_blur_viewport_exit'] ) ? ( is_array( $settings['scroll_blur_viewport_exit'] ) ? intval( $settings['scroll_blur_viewport_exit']['size'] ?? 100 ) : intval( $settings['scroll_blur_viewport_exit'] ) ) : 100,
                );
            }
            if ( ! empty( $settings['scroll_rotate'] ) && ( 'yes' === $settings['scroll_rotate'] || true === $settings['scroll_rotate'] ) ) {
                $scroll_active = true;
                $effects_config['rotate'] = array(
                    'direction' => $settings['scroll_rotate_direction'] ?? 'clockwise',
                    'speed'     => isset( $settings['scroll_rotate_speed'] ) ? floatval( $settings['scroll_rotate_speed'] ) : 4.0,
                    'enter'     => isset( $settings['scroll_rotate_viewport_enter'] ) ? ( is_array( $settings['scroll_rotate_viewport_enter'] ) ? intval( $settings['scroll_rotate_viewport_enter']['size'] ?? 0 ) : intval( $settings['scroll_rotate_viewport_enter'] ) ) : 0,
                    'exit'      => isset( $settings['scroll_rotate_viewport_exit'] ) ? ( is_array( $settings['scroll_rotate_viewport_exit'] ) ? intval( $settings['scroll_rotate_viewport_exit']['size'] ?? 100 ) : intval( $settings['scroll_rotate_viewport_exit'] ) ) : 100,
                );
            }
            if ( ! empty( $settings['scroll_scale'] ) && ( 'yes' === $settings['scroll_scale'] || true === $settings['scroll_scale'] ) ) {
                $scroll_active = true;
                $effects_config['scale'] = array(
                    'direction' => $settings['scroll_scale_direction'] ?? 'scale_up',
                    'speed'     => isset( $settings['scroll_scale_speed'] ) ? floatval( $settings['scroll_scale_speed'] ) : 4.0,
                    'enter'     => isset( $settings['scroll_scale_viewport_enter'] ) ? ( is_array( $settings['scroll_scale_viewport_enter'] ) ? intval( $settings['scroll_scale_viewport_enter']['size'] ?? 0 ) : intval( $settings['scroll_scale_viewport_enter'] ) ) : 0,
                    'exit'      => isset( $settings['scroll_scale_viewport_exit'] ) ? ( is_array( $settings['scroll_scale_viewport_exit'] ) ? intval( $settings['scroll_scale_viewport_exit']['size'] ?? 100 ) : intval( $settings['scroll_scale_viewport_exit'] ) ) : 100,
                );
            }

            if ( $scroll_active ) {
                $classes .= ' lk-has-scroll-effects';
                $devices = $settings['scroll_effects_on'] ?? array( 'desktop', 'tablet', 'mobile' );
                if ( empty( $devices ) || ! is_array( $devices ) ) {
                    $devices = array( 'desktop', 'tablet', 'mobile' );
                }
                $ax = isset( $settings['scroll_anchor_x'] ) ? ( is_array( $settings['scroll_anchor_x'] ) ? floatval( $settings['scroll_anchor_x']['size'] ?? 50 ) : floatval( $settings['scroll_anchor_x'] ) ) : 50.0;
                $ay = isset( $settings['scroll_anchor_y'] ) ? ( is_array( $settings['scroll_anchor_y'] ) ? floatval( $settings['scroll_anchor_y']['size'] ?? 50 ) : floatval( $settings['scroll_anchor_y'] ) ) : 50.0;

                $scroll_data = array(
                    'effects'     => $effects_config,
                    'relative_to' => $settings['scroll_relative_to'] ?? 'default',
                    'devices'     => array_values( array_map( 'sanitize_key', $devices ) ),
                    'anchor_x'    => $ax,
                    'anchor_y'    => $ay,
                );
                $attrs .= ' data-lk-scroll-settings="' . esc_attr( wp_json_encode( $scroll_data ) ) . '"';
                if ( $editor_mode ) {
                    $badges .= ' <span class="lk-scroll-badge">' . esc_html__( 'Scroll', 'loopkit' ) . '</span>';
                }
            }
        }

        // Mouse effects
        if ( $has_mouse ) {
            $mouse_config = array();
            $mouse_active = false;

            if ( ! empty( $settings['mouse_track'] ) && ( 'yes' === $settings['mouse_track'] || true === $settings['mouse_track'] ) ) {
                $mouse_active = true;
                $mouse_config['track'] = array(
                    'direction' => $settings['mouse_track_direction'] ?? 'opposite',
                    'speed'     => isset( $settings['mouse_track_speed'] ) ? floatval( $settings['mouse_track_speed'] ) : 2.0,
                );
            }
            if ( ! empty( $settings['mouse_tilt'] ) && ( 'yes' === $settings['mouse_tilt'] || true === $settings['mouse_tilt'] ) ) {
                $mouse_active = true;
                $classes .= ' lk-has-mouse-tilt';
                $mouse_config['tilt'] = array(
                    'direction' => $settings['mouse_tilt_direction'] ?? 'direct',
                    'speed'     => isset( $settings['mouse_tilt_speed'] ) ? floatval( $settings['mouse_tilt_speed'] ) : 2.0,
                );
            }

            if ( $mouse_active ) {
                $classes .= ' lk-has-mouse-effects';
                $m_devices = $settings['mouse_effects_on'] ?? array( 'desktop', 'tablet', 'mobile' );
                if ( empty( $m_devices ) || ! is_array( $m_devices ) ) {
                    $m_devices = array( 'desktop', 'tablet', 'mobile' );
                }
                $mouse_data = array(
                    'effects' => $mouse_config,
                    'devices' => array_values( array_map( 'sanitize_key', $m_devices ) ),
                );
                $attrs .= ' data-lk-mouse-settings="' . esc_attr( wp_json_encode( $mouse_data ) ) . '"';
                if ( $editor_mode ) {
                    $badges .= ' <span class="lk-mouse-badge">' . esc_html__( 'Mouse', 'loopkit' ) . '</span>';
                }
            }
        }

        // 3D Transform class
        $has_3d_transform = false;
        foreach ( array( 'transform_rotate_x', 'transform_rotate_y', 'transform_rotate_x_hover', 'transform_rotate_y_hover' ) as $t3k ) {
            $t3v = $settings[ $t3k ] ?? null;
            $t3_num = is_array( $t3v ) ? ( $t3v['size'] ?? 0 ) : $t3v;
            if ( ! empty( $t3_num ) && 0.0 !== floatval( $t3_num ) ) {
                $has_3d_transform = true;
                break;
            }
        }
        if ( $has_3d_transform ) {
            $classes .= ' lk-has-3d-transform';
        }

        return array(
            'classes' => $classes,
            'attrs'   => $attrs,
            'badges'  => $badges,
        );
    }

    /**
     * Fix main query on canvas preview so draft/template posts load properly.
     *
     * @param WP_Query $query The main query object.
     */
    public function fix_canvas_query( $query ) {
        if ( ! $query->is_main_query() || is_admin() ) {
            return;
        }

        if ( isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $post_id = absint( $_GET['loopkit_canvas'] ); // phpcs:ignore WordPress.Security.NonceVerification
            if ( $post_id ) {
                $target = get_post( $post_id );
                if ( $target ) {
                    $query->set( 'p', $post_id );
                    $query->set( 'post_type', $target->post_type );
                    $query->set( 'post_status', 'any' );
                    $query->is_singular = true;
                    $query->is_single   = ( 'page' !== $target->post_type );
                    $query->is_page     = ( 'page' === $target->post_type );
                    $query->is_home     = false;
                    $query->is_archive  = false;
                    $query->is_404      = false;
                }
            }
        }
    }

    /**
     * Handle canvas preview request (editor iframe).
     */
    public function handle_canvas_preview() {
        if ( ! isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $post_id = absint( $_GET['loopkit_canvas'] ); // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! $post_id ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! wp_verify_nonce( $nonce, 'loopkit_canvas_' . $post_id ) ) {
            wp_die( esc_html__( 'Security check failed.', 'loopkit' ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'You do not have permission to edit this page.', 'loopkit' ) );
        }

        // Set global post and query state
        global $post, $wp_query;
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }
        setup_postdata( $post );

        if ( $wp_query ) {
            $wp_query->posts             = array( $post );
            $wp_query->post_count        = 1;
            $wp_query->is_singular       = true;
            $wp_query->is_single         = ( 'page' !== $post->post_type );
            $wp_query->is_page           = ( 'page' === $post->post_type );
            $wp_query->is_home           = false;
            $wp_query->is_archive        = false;
            $wp_query->is_404            = false;
            $wp_query->queried_object    = $post;
            $wp_query->queried_object_id = $post_id;
        }

        // Enqueue canvas preview scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_canvas_assets' ), 9999 );

        // Canvas head styles
        add_action( 'wp_head', array( $this, 'canvas_head' ), 9999 );

        // Canvas footer scripts
        add_action( 'wp_footer', array( $this, 'canvas_footer' ), 9999 );
    }

    /**
     * Enqueue canvas preview assets.
     */
    public function enqueue_canvas_assets() {
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style(
            'loopkit-hover-animations',
            LOOPKIT_PLUGIN_URL . 'assets/css/loopkit-hover-animations.css',
            array(),
            LOOPKIT_VERSION
        );
        wp_enqueue_style(
            'loopkit-frontend',
            LOOPKIT_PLUGIN_URL . 'assets/css/loopkit-frontend.css',
            array( 'loopkit-hover-animations' ),
            LOOPKIT_VERSION
        );
        wp_enqueue_style(
            'loopkit-animations',
            LOOPKIT_PLUGIN_URL . 'assets/css/loopkit-animations.css',
            array(),
            LOOPKIT_VERSION
        );
        wp_enqueue_script(
            'loopkit-animations',
            LOOPKIT_PLUGIN_URL . 'assets/js/loopkit-animations.js',
            array(),
            LOOPKIT_VERSION,
            true
        );
        wp_enqueue_style(
            'loopkit-motion-effects',
            LOOPKIT_PLUGIN_URL . 'assets/css/loopkit-motion-effects.css',
            array(),
            LOOPKIT_VERSION
        );
        wp_enqueue_script(
            'loopkit-motion-effects',
            LOOPKIT_PLUGIN_URL . 'assets/js/loopkit-motion-effects.js',
            array(),
            LOOPKIT_VERSION,
            true
        );
        wp_enqueue_script(
            'loopkit-nav-menu',
            LOOPKIT_PLUGIN_URL . 'assets/js/loopkit-nav-menu.js',
            array(),
            LOOPKIT_VERSION,
            true
        );
        wp_enqueue_style(
            'loopkit-canvas-css',
            LOOPKIT_PLUGIN_URL . 'editor/css/loopkit-canvas.css',
            array( 'dashicons', 'loopkit-frontend', 'loopkit-hover-animations', 'loopkit-animations', 'loopkit-motion-effects' ),
            LOOPKIT_VERSION
        );
    }

    /**
     * Render elements for the canvas preview (editor iframe).
     *
     * @param string $content Original content.
     * @return string Canvas content.
     */
    public function render_canvas_elements( $content ) {
        $post_id = get_the_ID() ?: get_queried_object_id();
        if ( ! $post_id && isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $post_id = absint( $_GET['loopkit_canvas'] );
        }

        $raw      = get_post_meta( $post_id, '_loopkit_data', true );
        $elements = self::decode_elements( $raw );

        $html = '<div id="lk-canvas-root" class="lk-canvas-root loopkit loopkit-' . esc_attr( $post_id ) . '" data-post-id="' . esc_attr( $post_id ) . '">';

        if ( empty( $elements ) || ! is_array( $elements ) ) {
            $html .= '<div class="lk-canvas-empty" id="lk-canvas-empty">';
            $html .= '  <div class="lk-empty-inner">';
            $html .= '    <div class="lk-empty-icon"><span class="dashicons dashicons-plus-alt2"></span></div>';
            $html .= '    <div class="lk-empty-text">' . esc_html__( 'Drag widget here', 'loopkit' ) . '</div>';
            $html .= '  </div>';
            $html .= '</div>';
        } else {
            $elements = self::ensure_sections( $elements );
            $html .= self::render_elements( $elements, true );
        }

        // Add New Section area at the bottom of the page
        $html .= '<div class="lk-add-section-area" id="lk-add-section-area">';
        $html .= '  <div class="lk-add-section-box">';
        $html .= '    <div class="lk-add-section-buttons" id="lk-add-section-buttons">';
        $html .= '      <button type="button" class="lk-add-section-circle-btn lk-btn-add-section" id="lk-btn-open-structure" title="' . esc_attr__( 'Add New Section', 'loopkit' ) . '">';
        $html .= '        <span class="dashicons dashicons-plus-alt2"></span>';
        $html .= '      </button>';
        $html .= '    </div>';
        $html .= '    <div class="lk-structure-picker" id="lk-structure-picker">';
        $html .= '      <div class="lk-structure-title">' . esc_html__( 'Select your structure', 'loopkit' ) . '</div>';
        $html .= '      <div class="lk-structure-presets">';
        $html .= '        <div class="lk-structure-preset" data-preset="100" title="1 Column"><div class="lk-preset-col" style="flex:1;"></div></div>';
        $html .= '        <div class="lk-structure-preset" data-preset="50-50" title="2 Columns (50/50)"><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:1;"></div></div>';
        $html .= '        <div class="lk-structure-preset" data-preset="33-33-33" title="3 Columns (33/33/33)"><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:1;"></div></div>';
        $html .= '        <div class="lk-structure-preset" data-preset="25-25-25-25" title="4 Columns (25/25/25/25)"><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:1;"></div></div>';
        $html .= '        <div class="lk-structure-preset" data-preset="33-66" title="2 Columns (33/66)"><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:2;"></div></div>';
        $html .= '        <div class="lk-structure-preset" data-preset="66-33" title="2 Columns (66/33)"><div class="lk-preset-col" style="flex:2;"></div><div class="lk-preset-col" style="flex:1;"></div></div>';
        $html .= '      </div>';
        $html .= '      <button type="button" class="lk-structure-close" id="lk-structure-close" title="' . esc_attr__( 'Close', 'loopkit' ) . '"><span class="dashicons dashicons-no-alt"></span></button>';
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render elements to HTML.
     *
     * @param array $elements     Elements array.
     * @param bool  $editor_mode  Whether rendering for the editor canvas.
     * @return string HTML output.
     */
    public static function render_elements( $elements, $editor_mode = false ) {
        if ( ! is_array( $elements ) ) {
            return '';
        }

        $html = '';
        foreach ( $elements as $el ) {
            $html .= self::render_element( $el, $editor_mode );
        }
        return $html;
    }

    /**
     * Parse custom attributes from textarea (key|value per line).
     * Sanitizes attribute name, blocks on* attributes, blocks javascript: in values,
     * and never overwrites class, id, style, data-id, data-element_type.
     *
     * @param string $raw Raw attribute string.
     * @return string Sanitized HTML attributes string.
     */
    public static function parse_custom_attributes( $raw ) {
        if ( empty( $raw ) || ! is_string( $raw ) ) {
            return '';
        }
        $lines = explode( "\n", $raw );
        $attrs = array();
        $forbidden = array( 'class', 'id', 'style', 'data-id', 'data-element_type' );

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) {
                continue;
            }
            $parts = explode( '|', $line, 2 );
            $k = trim( $parts[0] );
            $v = isset( $parts[1] ) ? trim( $parts[1] ) : '';

            $k = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $k );
            if ( empty( $k ) ) {
                continue;
            }

            $k_lower = strtolower( $k );
            if ( in_array( $k_lower, $forbidden, true ) ) {
                continue;
            }
            if ( 0 === strpos( $k_lower, 'on' ) ) {
                continue;
            }
            if ( preg_match( '/^\s*javascript\s*:/i', $v ) ) {
                continue;
            }

            $attrs[] = esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
        }

        return ! empty( $attrs ) ? ' ' . implode( ' ', $attrs ) : '';
    }

    /**
     * Check if element has background overlay configured.
     *
     * @param array $settings Element settings.
     * @return bool
     */
    public static function has_background_overlay( $settings ) {
        if ( ! is_array( $settings ) ) {
            return false;
        }
        if ( ! empty( $settings['background_overlay_color'] ) ) return true;
        if ( ! empty( $settings['background_overlay_image'] ) ) {
            if ( is_array( $settings['background_overlay_image'] ) && ! empty( $settings['background_overlay_image']['url'] ) ) return true;
            if ( is_string( $settings['background_overlay_image'] ) && trim( $settings['background_overlay_image'] ) !== '' ) return true;
        }
        if ( ! empty( $settings['background_overlay_type'] ) && 'gradient' === $settings['background_overlay_type'] &&
             ( ! empty( $settings['background_overlay_gradient_color'] ) || ! empty( $settings['background_overlay_gradient_second_color'] ) ) ) return true;
        if ( ! empty( $settings['background_overlay_color_hover'] ) || ! empty( $settings['background_overlay_hover_color'] ) ) return true;
        $hov_img = $settings['background_overlay_image_hover'] ?? ( $settings['background_overlay_hover_image'] ?? '' );
        if ( ! empty( $hov_img ) ) {
            if ( is_array( $hov_img ) && ! empty( $hov_img['url'] ) ) return true;
            if ( is_string( $hov_img ) && trim( $hov_img ) !== '' ) return true;
        }
        $hov_type = $settings['background_overlay_type_hover'] ?? ( $settings['background_overlay_hover_type'] ?? ( $settings['background_overlay_hover_background'] ?? '' ) );
        if ( 'gradient' === $hov_type &&
             ( ! empty( $settings['background_overlay_gradient_color_hover'] ) || ! empty( $settings['background_overlay_hover_gradient_color'] ) || ! empty( $settings['background_overlay_gradient_second_color_hover'] ) || ! empty( $settings['background_overlay_hover_gradient_second_color'] ) ) ) return true;
        if ( ! empty( $settings['background_overlay_blend_mode'] ) && 'normal' !== $settings['background_overlay_blend_mode'] ) return true;
        if ( ! empty( $settings['background_overlay_css_filters'] ) && is_array( $settings['background_overlay_css_filters'] ) ) {
            foreach ( $settings['background_overlay_css_filters'] as $fk => $fv ) {
                if ( '' !== $fv && null !== $fv && ( ( 'brightness' !== $fk && 'contrast' !== $fk && 'saturation' !== $fk ) || 100 != $fv ) ) return true;
            }
        }
        $hov_filters = $settings['background_overlay_css_filters_hover'] ?? ( $settings['background_overlay_hover_css_filters'] ?? null );
        if ( ! empty( $hov_filters ) && is_array( $hov_filters ) ) {
            foreach ( $hov_filters as $fk => $fv ) {
                if ( '' !== $fv && null !== $fv && ( ( 'brightness' !== $fk && 'contrast' !== $fk && 'saturation' !== $fk ) || 100 != $fv ) ) return true;
            }
        }
        if ( isset( $settings['background_overlay_opacity_hover'] ) && '' !== $settings['background_overlay_opacity_hover'] ) return true;
        if ( isset( $settings['background_overlay_hover_opacity'] ) && '' !== $settings['background_overlay_hover_opacity'] ) return true;
        if ( isset( $settings['background_overlay_hover_transition_duration'] ) && '' !== $settings['background_overlay_hover_transition_duration'] ) return true;
        return false;
    }

    /**
     * Get SVG string for 18 shape divider types.
     *
     * @param string $shape Shape type name.
     * @return string SVG markup.
     */
    public static function get_shape_divider_svg( $shape ) {
        $shapes = array(
            'mountains' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 L200,80 L350,20 L500,90 L680,30 L850,75 L1000,0 L1000,100 L0,100 Z"></path></svg>',
            'drops' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,100 C150,100 200,20 250,20 C300,20 350,100 500,100 C650,100 700,20 750,20 C800,20 850,100 1000,100 L1000,0 L0,0 Z"></path></svg>',
            'clouds' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,100 C50,60 100,50 150,70 C200,40 280,30 330,60 C380,20 480,10 530,50 C580,20 680,30 730,65 C780,40 860,50 900,70 C940,55 980,70 1000,100 L1000,0 L0,0 Z"></path></svg>',
            'zigzag' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 L50,100 L100,0 L150,100 L200,0 L250,100 L300,0 L350,100 L400,0 L450,100 L500,0 L550,100 L600,0 L650,100 L700,0 L750,100 L800,0 L850,100 L900,0 L950,100 L1000,0 L1000,100 L0,100 Z"></path></svg>',
            'pyramids' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,100 L125,20 L250,100 L375,10 L500,100 L625,25 L750,100 L875,15 L1000,100 L1000,0 L0,0 Z"></path></svg>',
            'triangle' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 L500,100 L1000,0 L1000,100 L0,100 Z"></path></svg>',
            'triangle_asymmetrical' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 L750,100 L1000,0 L1000,100 L0,100 Z"></path></svg>',
            'tilt' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,100 L1000,0 L1000,100 Z"></path></svg>',
            'tilt_opacity' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,100 L1000,0 L1000,100 Z" opacity="0.4"></path><path d="M0,100 L1000,30 L1000,100 Z" opacity="0.7"></path><path d="M0,100 L1000,60 L1000,100 Z"></path></svg>',
            'fan_opacity' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 Q500,100 1000,0 L1000,100 L0,100 Z" opacity="0.3"></path><path d="M0,20 Q500,110 1000,20 L1000,100 L0,100 Z" opacity="0.6"></path><path d="M0,40 Q500,120 1000,40 L1000,100 L0,100 Z"></path></svg>',
            'curve' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 C300,120 700,120 1000,0 L1000,100 L0,100 Z"></path></svg>',
            'curve_asymmetrical' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 C400,130 800,40 1000,0 L1000,100 L0,100 Z"></path></svg>',
            'waves' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,50 C200,0 300,100 500,50 C700,0 800,100 1000,50 L1000,100 L0,100 Z"></path></svg>',
            'waves_brush' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,40 C120,60 220,20 340,45 C460,70 560,30 680,50 C800,70 900,35 1000,45 L1000,100 L0,100 Z"></path></svg>',
            'waves_pattern' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,60 Q125,20 250,60 T500,60 T750,60 T1000,60 L1000,100 L0,100 Z"></path></svg>',
            'arrow' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 L500,80 L1000,0 L1000,100 L0,100 Z"></path></svg>',
            'split' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,50 L500,0 L1000,50 L1000,100 L0,100 Z"></path></svg>',
            'book' => '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,100 C250,0 450,80 500,100 C550,80 750,0 1000,100 L1000,0 L0,0 Z"></path></svg>',
        );
        return $shapes[ $shape ] ?? '';
    }

    /**
     * Render a single element (Sections 9 & 10).
     *
     * @param array $el           Element data.
     * @param bool  $editor_mode  Whether rendering for the editor canvas.
     * @return string HTML output.
     */
    public static function render_element( $el, $editor_mode = false ) {
        $id       = esc_attr( $el['id'] ?? '' );
        $elType   = $el['elType'] ?? '';
        $settings = $el['settings'] ?? array();
        $children = $el['elements'] ?? array();

        // Dynamic tags: {{lk:post_title}} etc. in any field become the current post's data.
        if ( class_exists( 'Loopkit_Dynamic' ) && Loopkit_Dynamic::has_tokens( $settings ) ) {
            $settings        = Loopkit_Dynamic::resolve_settings( $settings, $editor_mode );
            $el['settings']  = $settings;
        }

        $data_attrs = '';
        if ( $editor_mode ) {
            $data_attrs  = ' data-id="' . $id . '" data-lk-id="' . $id . '" data-element_type="' . esc_attr( $elType ) . '" data-lk-type="' . esc_attr( $elType ) . '"';
            $wtype = $el['widgetType'] ?? $el['widget'] ?? '';
            if ( ! empty( $wtype ) ) {
                $data_attrs .= ' data-widget_type="' . esc_attr( $wtype ) . '.default" data-lk-widget="' . esc_attr( $wtype ) . '"';
            }
        }

        $html = '';

        if ( 'global' === $elType ) {
            return self::render_global( $el, $editor_mode );
        }

        if ( 'section' === $elType ) {
            // SECTION (outer layer of page)
            $tag = ! empty( $settings['html_tag'] ) ? esc_attr( $settings['html_tag'] ) : 'section';
            $allowed_tags = array( 'section', 'div', 'header', 'footer', 'article', 'main', 'aside', 'nav' );
            if ( ! in_array( $tag, $allowed_tags, true ) ) {
                $tag = 'section';
            }

            $is_boxed = ( 'full' !== ( $settings['content_width'] ?? 'boxed' ) );
            $con_type = ( $settings['container_type'] ?? 'flexbox' ) === 'grid' ? 'grid' : 'flex';

            $classes = 'lk-element lk-element-' . $id . ' lk-section ' .
                       ( $is_boxed ? 'lk-section-boxed' : 'lk-section-full' ) .
                       ' lk-con--' . $con_type;

            if ( self::elements_have_feature( array( $el ), 'sticky' ) ) {
                $classes .= ' lk-has-sticky';
            }

            if ( $editor_mode ) {
                $classes .= ' lk-editable';
            }
            if ( ! empty( $settings['css_classes'] ) ) {
                $classes .= ' ' . esc_attr( $settings['css_classes'] );
            }

            $id_attr = '';
            if ( ! empty( $settings['css_id'] ) ) {
                $id_attr = ' id="' . esc_attr( $settings['css_id'] ) . '"';
            }

            $custom_attrs = self::parse_custom_attributes( $settings['custom_attributes'] ?? '' );

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

            $motion_data   = self::get_motion_effects_data( $settings, $editor_mode );
            $classes      .= $motion_data['classes'];
            $custom_attrs .= $motion_data['attrs'];

            $style_attr = ! empty( trim( $anim_style ) ) ? ' style="' . esc_attr( trim( $anim_style ) ) . '"' : '';

            $html .= '<' . $tag . ' class="' . esc_attr( $classes ) . '"' . $data_attrs . $id_attr . $custom_attrs . $sticky_attrs . $style_attr . '>';

            // Background Overlay (rendered as first child of outer element)
            if ( self::has_background_overlay( $settings ) ) {
                $html .= '<div class="lk-background-overlay"></div>';
            }

            // Shape Dividers
            if ( ! empty( $settings['shape_divider_top_type'] ) && 'none' !== $settings['shape_divider_top_type'] ) {
                $html .= '<div class="lk-shape-divider lk-shape-divider--top">' . self::get_shape_divider_svg( $settings['shape_divider_top_type'] ) . '</div>';
            }
            if ( ! empty( $settings['shape_divider_bottom_type'] ) && 'none' !== $settings['shape_divider_bottom_type'] ) {
                $html .= '<div class="lk-shape-divider lk-shape-divider--bottom">' . self::get_shape_divider_svg( $settings['shape_divider_bottom_type'] ) . '</div>';
            }

            // Section handle: top center, #1E90E0
            if ( $editor_mode ) {
                $sticky_badge = ( ! empty( $sticky ) && 'none' !== $sticky ) ? ' <span class="lk-sticky-badge">' . esc_html__( 'Sticky', 'loopkit' ) . '</span>' : '';
                $sticky_badge .= $motion_data['badges'];
                $html .= '<div class="lk-element-handle lk-handle-section">';
                $html .= '  <button type="button" class="lk-handle-btn lk-handle-add" data-action="add-section-before" data-id="' . $id . '" title="' . esc_attr__( 'Add Section', 'loopkit' ) . '"><span class="dashicons dashicons-plus-alt2"></span></button>';
                $html .= '  <span class="lk-handle-label">' . esc_html__( 'Section', 'loopkit' ) . '</span>' . $sticky_badge;
                $html .= '  <button type="button" class="lk-handle-btn lk-handle-edit" data-action="edit" data-id="' . $id . '" draggable="true" title="' . esc_attr__( 'Edit Section', 'loopkit' ) . '"><span class="dashicons dashicons-edit"></span></button>';
                $html .= '  <button type="button" class="lk-handle-btn lk-handle-duplicate" data-action="duplicate" data-id="' . $id . '" title="' . esc_attr__( 'Duplicate', 'loopkit' ) . '"><span class="dashicons dashicons-admin-page"></span></button>';
                $html .= '  <button type="button" class="lk-handle-btn lk-handle-delete" data-action="delete" data-id="' . $id . '" title="' . esc_attr__( 'Delete', 'loopkit' ) . '"><span class="dashicons dashicons-trash"></span></button>';
                $html .= '</div>';
            }

            // Boxed: wrap children in .lk-section-inner
            if ( $is_boxed ) {
                $inner_classes = 'lk-section-inner lk-con--' . $con_type;
                $html .= '<div class="' . esc_attr( $inner_classes ) . '">';
            }

            if ( ! empty( $children ) ) {
                $html .= self::render_elements( $children, $editor_mode );
            } elseif ( $editor_mode ) {
                $html .= '<div class="lk-container-empty" data-parent-id="' . $id . '">';
                $html .= '  <span class="dashicons dashicons-plus-alt2"></span>';
                $html .= '  <span>' . esc_html__( 'Drop or add widget here', 'loopkit' ) . '</span>';
                $html .= '</div>';
            }

            if ( $is_boxed ) {
                $html .= '</div>'; // .lk-section-inner
            }

            $html .= '</' . $tag . '>';

        } elseif ( 'container' === $elType ) {
            $tag = ! empty( $settings['html_tag'] ) ? esc_attr( $settings['html_tag'] ) : 'div';
            $allowed_tags = array( 'div', 'section', 'header', 'footer', 'article', 'main', 'aside', 'nav', 'a' );
            if ( ! in_array( $tag, $allowed_tags, true ) ) {
                $tag = 'div';
            }

            $is_boxed = ( 'boxed' === ( $settings['content_width'] ?? 'full' ) );
            $con_type = ( $settings['container_type'] ?? 'flexbox' ) === 'grid' ? 'grid' : 'flex';

            $classes = 'lk-element lk-element-' . $id . ' lk-con lk-container ' .
                       ( $is_boxed ? 'lk-con-boxed lk-container-boxed' : 'lk-con-full lk-container-full' ) .
                       ' lk-con--' . $con_type;

            if ( self::elements_have_feature( array( $el ), 'sticky' ) ) {
                $classes .= ' lk-has-sticky';
            }

            if ( $editor_mode ) {
                $classes .= ' lk-editable';
            }
            if ( ! empty( $settings['css_classes'] ) ) {
                $classes .= ' ' . esc_attr( $settings['css_classes'] );
            }

            $id_attr = '';
            if ( ! empty( $settings['css_id'] ) ) {
                $id_attr = ' id="' . esc_attr( $settings['css_id'] ) . '"';
            }

            $extra_attrs = '';
            if ( 'a' === $tag && ! empty( $settings['link']['url'] ) ) {
                $extra_attrs .= ' href="' . esc_url( $settings['link']['url'] ) . '"';
                if ( ! empty( $settings['link']['is_external'] ) ) {
                    $extra_attrs .= ' target="_blank"';
                }
                if ( ! empty( $settings['link']['nofollow'] ) ) {
                    $extra_attrs .= ' rel="nofollow"';
                }
            }

            $custom_attrs = self::parse_custom_attributes( $settings['custom_attributes'] ?? '' );

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

            $motion_data   = self::get_motion_effects_data( $settings, $editor_mode );
            $classes      .= $motion_data['classes'];
            $custom_attrs .= $motion_data['attrs'];

            $style_attr = ! empty( trim( $anim_style ) ) ? ' style="' . esc_attr( trim( $anim_style ) ) . '"' : '';

            $html .= '<' . $tag . ' class="' . esc_attr( $classes ) . '"' . $data_attrs . $id_attr . $extra_attrs . $custom_attrs . $sticky_attrs . $style_attr . '>';

            // Background Overlay (rendered as first child of outer element)
            if ( self::has_background_overlay( $settings ) ) {
                $html .= '<div class="lk-background-overlay"></div>';
            }

            // Shape Dividers
            if ( ! empty( $settings['shape_divider_top_type'] ) && 'none' !== $settings['shape_divider_top_type'] ) {
                $html .= '<div class="lk-shape-divider lk-shape-divider--top">' . self::get_shape_divider_svg( $settings['shape_divider_top_type'] ) . '</div>';
            }
            if ( ! empty( $settings['shape_divider_bottom_type'] ) && 'none' !== $settings['shape_divider_bottom_type'] ) {
                $html .= '<div class="lk-shape-divider lk-shape-divider--bottom">' . self::get_shape_divider_svg( $settings['shape_divider_bottom_type'] ) . '</div>';
            }

            // Container handle: top left, #38A8F5
            if ( $editor_mode ) {
                $sticky_badge = ( ! empty( $sticky ) && 'none' !== $sticky ) ? ' <span class="lk-sticky-badge">' . esc_html__( 'Sticky', 'loopkit' ) . '</span>' : '';
                $sticky_badge .= $motion_data['badges'];
                $html .= '<div class="lk-element-handle lk-handle-container">';
                $html .= '  <span class="lk-handle-label">' . esc_html__( 'Container', 'loopkit' ) . '</span>' . $sticky_badge;
                $html .= '  <button type="button" class="lk-handle-btn lk-handle-add" data-action="add-container-inside" data-id="' . $id . '" title="' . esc_attr__( 'Add', 'loopkit' ) . '"><span class="dashicons dashicons-plus-alt2"></span></button>';
                $html .= '  <button type="button" class="lk-handle-btn lk-handle-edit" data-action="edit" data-id="' . $id . '" draggable="true" title="' . esc_attr__( 'Edit Container', 'loopkit' ) . '"><span class="dashicons dashicons-edit"></span></button>';
                $html .= '  <button type="button" class="lk-handle-btn lk-handle-duplicate" data-action="duplicate" data-id="' . $id . '" title="' . esc_attr__( 'Duplicate', 'loopkit' ) . '"><span class="dashicons dashicons-admin-page"></span></button>';
                $html .= '  <button type="button" class="lk-handle-btn lk-handle-delete" data-action="delete" data-id="' . $id . '" title="' . esc_attr__( 'Delete', 'loopkit' ) . '"><span class="dashicons dashicons-trash"></span></button>';
                $html .= '</div>';
            }

            // Boxed: wrap children in .lk-con-inner
            if ( $is_boxed ) {
                $inner_classes = 'lk-con-inner lk-container-inner lk-con--' . $con_type;
                $html .= '<div class="' . esc_attr( $inner_classes ) . '">';
            }

            if ( ! empty( $children ) ) {
                $html .= self::render_elements( $children, $editor_mode );
            } elseif ( $editor_mode ) {
                $html .= '<div class="lk-container-empty" data-parent-id="' . $id . '">';
                $html .= '  <span class="dashicons dashicons-plus-alt2"></span>';
                $html .= '  <span>' . esc_html__( 'Drop or add widget here', 'loopkit' ) . '</span>';
                $html .= '</div>';
            }

            if ( $is_boxed ) {
                $html .= '</div>'; // .lk-con-inner
            }

            $html .= '</' . $tag . '>';

        } elseif ( 'widget' === $elType ) {
            $widgetType = $el['widgetType'] ?? $el['widget'] ?? '';
            if ( empty( $widgetType ) && isset( $el['settings']['title'] ) ) {
                $widgetType = 'heading';
            }

            if ( class_exists( 'Loopkit_Widgets_Registry' ) ) {
                $widget = Loopkit_Widgets_Registry::instance()->get_widget( $widgetType );
                if ( $widget ) {
                    return $widget->render_element( $el, $editor_mode );
                }
            }

            if ( 'heading' === $widgetType ) {
                $html .= self::render_heading( $el, $editor_mode );
            }
        }

        return $html;
    }

    /**
     * Render a heading widget (Section 10).
     * Markup: <div class="lk-element lk-element-{id} lk-widget lk-widget-heading" data-id="{id}" data-element_type="widget" data-widget_type="heading.default"><h2 class="lk-heading-title">Text</h2></div>
     *
     * @param array $el           Element data.
     * @param bool  $editor_mode  Whether rendering for the editor canvas.
     * @return string HTML output.
     */
    private static function render_heading( $el, $editor_mode = false ) {
        $id       = esc_attr( $el['id'] ?? '' );
        $settings = $el['settings'] ?? array();
        $title    = $settings['title'] ?? esc_html__( 'Add Your Heading Text Here', 'loopkit' );
        $tag      = $settings['tag'] ?? 'h2';

        $allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );
        if ( ! in_array( $tag, $allowed_tags, true ) ) {
            $tag = 'h2';
        }

        $classes = 'lk-element lk-element-' . $id . ' lk-widget lk-widget-heading';
        if ( $editor_mode ) {
            $classes .= ' lk-editable';
        }
        if ( ! empty( $settings['css_classes'] ) ) {
            $classes .= ' ' . esc_attr( $settings['css_classes'] );
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

        $data_attrs = '';
        if ( $editor_mode ) {
            $data_attrs  = ' data-id="' . $id . '" data-lk-id="' . $id . '" data-element_type="widget" data-lk-type="widget" data-widget_type="heading.default" data-lk-widget="heading"';
        }

        $id_attr = '';
        if ( ! empty( $settings['css_id'] ) ) {
            $id_attr = ' id="' . esc_attr( $settings['css_id'] ) . '"';
        }

        // Link wrapper
        $has_link = ! empty( $settings['link'] ) && ! empty( $settings['link']['url'] );
        $link_html = '';
        $link_close = '';

        if ( $has_link ) {
            $url      = esc_url( $settings['link']['url'] );
            $target   = ! empty( $settings['link']['is_external'] ) ? ' target="_blank"' : '';
            $nofollow = ! empty( $settings['link']['nofollow'] ) ? ' rel="nofollow"' : '';
            $link_html = '<a href="' . $url . '"' . $target . $nofollow . '>';
            $link_close = '</a>';
        }

        $custom_attrs = self::parse_custom_attributes( $settings['custom_attributes'] ?? '' );
        $style_attr = ! empty( trim( $anim_style ) ) ? ' style="' . esc_attr( trim( $anim_style ) ) . '"' : '';
        $motion_data   = self::get_motion_effects_data( $settings, $editor_mode );
        $classes      .= $motion_data['classes'];
        $custom_attrs .= $motion_data['attrs'];

        $html = '<div class="' . esc_attr( $classes ) . '"' . $data_attrs . $id_attr . $custom_attrs . $sticky_attrs . $style_attr . '>';

        // Widget handle: top right, #38A8F5
        if ( $editor_mode ) {
            $sticky_badge = ( ! empty( $sticky ) && 'none' !== $sticky ) ? ' <span class="lk-sticky-badge">' . esc_html__( 'Sticky', 'loopkit' ) . '</span>' : '';
            $sticky_badge .= $motion_data['badges'];
            $html .= '<div class="lk-element-handle lk-handle-widget">';
            $html .= '  <span class="lk-handle-label">' . esc_html__( 'Heading', 'loopkit' ) . '</span>' . $sticky_badge;
            $html .= '  <button type="button" class="lk-handle-btn lk-handle-edit" data-action="edit" data-id="' . $id . '" draggable="true" title="' . esc_attr__( 'Edit Heading', 'loopkit' ) . '"><span class="dashicons dashicons-edit"></span></button>';
            $html .= '  <button type="button" class="lk-handle-btn lk-handle-duplicate" data-action="duplicate" data-id="' . $id . '" title="' . esc_attr__( 'Duplicate', 'loopkit' ) . '"><span class="dashicons dashicons-admin-page"></span></button>';
            $html .= '  <button type="button" class="lk-handle-btn lk-handle-delete" data-action="delete" data-id="' . $id . '" title="' . esc_attr__( 'Delete', 'loopkit' ) . '"><span class="dashicons dashicons-trash"></span></button>';
            $html .= '</div>';
        }

        $inline_attr = $editor_mode ? ' data-lk-inline-edit="title"' : '';
        $html .= '<' . $tag . ' class="lk-heading-title"' . $inline_attr . '>';
        $html .= $link_html;
        $html .= wp_kses_post( $title );
        $html .= $link_close;
        $html .= '</' . $tag . '>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a Global Section or Container instance.
     *
     * @param array $el          Global instance data { id, elType: 'global', globalType, templateId, overrides }.
     * @param bool  $editor_mode Whether rendering for the editor canvas.
     * @return string HTML output.
     */
    private static function render_global( $el, $editor_mode = false ) {
        $instance_id = esc_attr( $el['id'] ?? '' );
        $template_id = absint( $el['templateId'] ?? 0 );

        $post = $template_id ? get_post( $template_id ) : null;
        $is_missing = ( ! $post || 'trash' === $post->post_status );

        if ( $is_missing ) {
            if ( $editor_mode ) {
                $html  = '<div class="lk-element lk-element-' . $instance_id . ' lk-global lk-global-missing lk-editable" data-id="' . $instance_id . '" data-lk-id="' . $instance_id . '" data-element_type="global" data-lk-type="global" data-template-id="' . $template_id . '">';
                $html .= '  <div class="lk-global-missing-box">';
                $html .= '    <span class="dashicons dashicons-warning"></span>';
                $html .= '    <span class="lk-missing-text">' . esc_html__( 'Global section not found', 'loopkit' ) . '</span>';
                $html .= '    <button type="button" class="lk-btn lk-btn-sm lk-btn-danger lk-global-remove-btn" data-action="delete" data-id="' . $instance_id . '">' . esc_html__( 'Remove', 'loopkit' ) . '</button>';
                $html .= '  </div>';
                $html .= '</div>';
                return $html;
            } else {
                if ( current_user_can( 'edit_posts' ) ) {
                    return '<!-- Loopkit: Global template ' . $template_id . ' not found -->';
                }
                return '';
            }
        }

        $raw_data = get_post_meta( $template_id, '_loopkit_data', true );
        $master_elements = self::decode_elements( $raw_data );
        if ( empty( $master_elements ) || ! is_array( $master_elements ) ) {
            if ( $editor_mode ) {
                $html  = '<div class="lk-element lk-element-' . $instance_id . ' lk-global lk-global-missing lk-editable" data-id="' . $instance_id . '" data-lk-id="' . $instance_id . '" data-element_type="global" data-lk-type="global">';
                $html .= '  <div class="lk-global-missing-box">';
                $html .= '    <span class="dashicons dashicons-warning"></span>';
                $html .= '    <span class="lk-missing-text">' . esc_html__( 'Global section not found', 'loopkit' ) . '</span>';
                $html .= '    <button type="button" class="lk-btn lk-btn-sm lk-btn-danger lk-global-remove-btn" data-action="delete" data-id="' . $instance_id . '">' . esc_html__( 'Remove', 'loopkit' ) . '</button>';
                $html .= '  </div>';
                $html .= '</div>';
                return $html;
            }
            return '';
        }

        $master          = $master_elements[0];
        $master_id       = esc_attr( $master['id'] ?? '' );
        $master_type     = $master['elType'] ?? ( $el['globalType'] ?? 'section' );
        $master_settings = $master['settings'] ?? array();
        $master_children = $master['elements'] ?? array();
        $global_title    = esc_html( $post->post_title );

        $is_section = ( 'section' === $master_type );
        $is_boxed   = $is_section
            ? ( 'full' !== ( $master_settings['content_width'] ?? 'boxed' ) )
            : ( 'boxed' === ( $master_settings['content_width'] ?? 'full' ) );
        $con_type   = ( $master_settings['container_type'] ?? 'flexbox' ) === 'grid' ? 'grid' : 'flex';

        if ( $is_section ) {
            $tag = ! empty( $master_settings['html_tag'] ) ? esc_attr( $master_settings['html_tag'] ) : 'section';
            $allowed_tags = array( 'section', 'div', 'header', 'footer', 'article', 'main', 'aside', 'nav' );
            if ( ! in_array( $tag, $allowed_tags, true ) ) {
                $tag = 'section';
            }
            $base_classes = 'lk-section ' . ( $is_boxed ? 'lk-section-boxed' : 'lk-section-full' );
        } else {
            $tag = ! empty( $master_settings['html_tag'] ) ? esc_attr( $master_settings['html_tag'] ) : 'div';
            $allowed_tags = array( 'div', 'section', 'header', 'footer', 'article', 'main', 'aside', 'nav', 'a' );
            if ( ! in_array( $tag, $allowed_tags, true ) ) {
                $tag = 'div';
            }
            $base_classes = 'lk-con lk-container ' . ( $is_boxed ? 'lk-con-boxed lk-container-boxed' : 'lk-con-full lk-container-full' );
        }

        $classes  = 'lk-element lk-element-' . $instance_id . ' lk-element-' . $master_id . ' ' . $base_classes . ' lk-con--' . $con_type;
        $classes .= ' lk-global lk-global-' . $template_id . ' lk-global-instance-' . $instance_id;

        if ( $editor_mode ) {
            $classes .= ' lk-editable';
        }
        if ( ! empty( $master_settings['css_classes'] ) ) {
            $classes .= ' ' . esc_attr( $master_settings['css_classes'] );
        }

        $data_attrs = '';
        if ( $editor_mode ) {
            $data_attrs  = ' data-id="' . $instance_id . '" data-lk-id="' . $instance_id . '" data-element_type="global" data-lk-type="global" data-global-type="' . esc_attr( $master_type ) . '" data-loopkit-global="' . $template_id . '"';
        } else {
            $data_attrs  = ' data-loopkit-global="' . $template_id . '"';
        }

        $id_attr = '';
        if ( ! empty( $master_settings['css_id'] ) ) {
            $id_attr = ' id="' . esc_attr( $master_settings['css_id'] ) . '"';
        }

        $extra_attrs = '';
        if ( ! $is_section && 'a' === $tag && ! empty( $master_settings['link']['url'] ) ) {
            $extra_attrs .= ' href="' . esc_url( $master_settings['link']['url'] ) . '"';
            if ( ! empty( $master_settings['link']['is_external'] ) ) {
                $extra_attrs .= ' target="_blank"';
            }
            if ( ! empty( $master_settings['link']['nofollow'] ) ) {
                $extra_attrs .= ' rel="nofollow"';
            }
        }

        $html = '<' . $tag . ' class="' . esc_attr( $classes ) . '"' . $data_attrs . $id_attr . $extra_attrs . '>';

        // Handle in editor mode: purple background #9B51E0 with globe icon and global title
        if ( $editor_mode ) {
            $handle_type_cls = $is_section ? 'lk-handle-section' : 'lk-handle-container';
            $html .= '<div class="lk-element-handle ' . $handle_type_cls . ' lk-handle-global">';
            if ( $is_section ) {
                $html .= '  <button type="button" class="lk-handle-btn lk-handle-add" data-action="add-section-before" data-id="' . $instance_id . '" title="' . esc_attr__( 'Add Section', 'loopkit' ) . '"><span class="dashicons dashicons-plus-alt2"></span></button>';
            } else {
                $html .= '  <button type="button" class="lk-handle-btn lk-handle-add" data-action="add-container-inside" data-id="' . $instance_id . '" title="' . esc_attr__( 'Add', 'loopkit' ) . '"><span class="dashicons dashicons-plus-alt2"></span></button>';
            }
            $html .= '  <span class="dashicons dashicons-admin-site-alt3 lk-handle-globe-icon"></span>';
            $html .= '  <span class="lk-handle-label">' . $global_title . '</span>';
            $html .= '  <button type="button" class="lk-handle-btn lk-handle-edit" data-action="edit" data-id="' . $instance_id . '" draggable="true" title="' . esc_attr__( 'Edit Global', 'loopkit' ) . '"><span class="dashicons dashicons-edit"></span></button>';
            $html .= '  <button type="button" class="lk-handle-btn lk-handle-duplicate" data-action="duplicate" data-id="' . $instance_id . '" title="' . esc_attr__( 'Duplicate', 'loopkit' ) . '"><span class="dashicons dashicons-admin-page"></span></button>';
            $html .= '  <button type="button" class="lk-handle-btn lk-handle-delete" data-action="delete" data-id="' . $instance_id . '" title="' . esc_attr__( 'Delete', 'loopkit' ) . '"><span class="dashicons dashicons-trash"></span></button>';
            $html .= '</div>';
        }

        // Boxed inner wrapper
        if ( $is_boxed ) {
            $inner_cls = $is_section
                ? 'lk-section-inner lk-con--' . $con_type
                : 'lk-con-inner lk-container-inner lk-con--' . $con_type;
            $html .= '<div class="' . esc_attr( $inner_cls ) . '">';
        }

        if ( ! empty( $master_children ) ) {
            $html .= self::render_elements( $master_children, $editor_mode );
        }

        if ( $is_boxed ) {
            $html .= '</div>';
        }

        $html .= '</' . $tag . '>';

        return $html;
    }

    /**
     * Output canvas-specific styles in wp_head (Section 3).
     */
    public function canvas_head() {
        $post_id = get_the_ID() ?: get_queried_object_id();
        if ( ! $post_id && isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $post_id = absint( $_GET['loopkit_canvas'] );
        }

        $css = get_post_meta( $post_id, '_loopkit_css', true );

        // Live CSS style tag inside canvas iframe (Section 3: <style id="lk-live-css">)
        echo '<style id="lk-live-css">' . "\n" . ( $css ? $css : '' ) . "\n" . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput

        // Output CSS for any global templates in the canvas elements
        $raw_canvas_data = get_post_meta( $post_id, '_loopkit_data', true );
        $canvas_elements = self::decode_elements( $raw_canvas_data );
        $canvas_global_ids = array();
        self::collect_global_ids( $canvas_elements, $canvas_global_ids );
        foreach ( array_unique( $canvas_global_ids ) as $cgid ) {
            $g_css = get_post_meta( $cgid, '_loopkit_css', true );
            if ( ! empty( $g_css ) ) {
                echo '<style id="lk-global-css-' . esc_attr( $cgid ) . '">' . "\n" . $g_css . "\n" . '</style>' . "\n";
            }
        }

        // Canvas override styles
        ?>
        <style id="loopkit-canvas-overrides">
            html { overflow: auto !important; }
            body { min-height: 100vh; }
            #wpadminbar { display: none !important; }
            html.wp-toolbar { padding-top: 0 !important; }
        </style>
        <?php
    }

    /**
     * Output canvas-specific scripts in wp_footer.
     */
    public function canvas_footer() {
        ?>
        <script>
            window.loopkitCanvas = {
                pluginUrl: <?php echo wp_json_encode( LOOPKIT_PLUGIN_URL ); ?>,
                version: <?php echo wp_json_encode( LOOPKIT_VERSION ); ?>,
                restUrl: <?php echo wp_json_encode( rest_url( 'loopkit/v1/' ) ); ?>,
                nonce: <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>,
                postId: <?php echo wp_json_encode( get_the_ID() ); ?>
            };
        </script>
        <script id="loopkit-canvas-js" src="<?php echo esc_url( LOOPKIT_PLUGIN_URL . 'editor/js/loopkit-canvas.js?ver=' . LOOPKIT_VERSION ); ?>"></script>
        <?php
    }
}

new Loopkit_Frontend();
