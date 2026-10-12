<?php
/**
 * Loopkit Templates class.
 * Manages the loopkit_template custom post type and registers page templates
 * (Loopkit Canvas and Loopkit Full Width) for block themes and classic themes.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Templates {

    /**
     * Constructor.
     */
    public function __construct() {
        // AJAX handlers for templates dashboard
        add_action( 'wp_ajax_loopkit_create_template', array( $this, 'ajax_create_template' ) );
        add_action( 'wp_ajax_loopkit_rename_template', array( $this, 'ajax_rename_template' ) );
        add_action( 'wp_ajax_loopkit_duplicate_template', array( $this, 'ajax_duplicate_template' ) );
        add_action( 'wp_ajax_loopkit_delete_template', array( $this, 'ajax_delete_template' ) );
        add_action( 'wp_ajax_loopkit_make_global_template', array( $this, 'ajax_make_global_template' ) );

        // Export template
        add_action( 'admin_post_loopkit_export_template', array( $this, 'export_template' ) );

        // Register block templates for block themes (WP 6.7+)
        add_action( 'init', array( $this, 'register_block_templates' ) );

        // Register templates in WordPress template selector (block and classic editors)
        add_filter( 'theme_page_templates', array( $this, 'add_theme_templates' ), 10, 4 );
        add_filter( 'theme_post_templates', array( $this, 'add_theme_templates' ), 10, 4 );

        // Template hierarchy resolution for block themes
        add_filter( 'page_template_hierarchy', array( $this, 'filter_template_hierarchy' ), 20 );
        add_filter( 'single_template_hierarchy', array( $this, 'filter_template_hierarchy' ), 20 );
        add_filter( 'singular_template_hierarchy', array( $this, 'filter_template_hierarchy' ), 20 );
        add_filter( 'frontpage_template_hierarchy', array( $this, 'filter_template_hierarchy' ), 20 );

        // Template loading & safety fallbacks
        add_filter( 'template_include', array( $this, 'filter_template_include' ), 99 );
    }

    /**
     * Register block templates for block themes (FSE).
     * Full Width = header template part + post content block (full align, default layout, NOT constrained) + footer template part.
     * Canvas = post content block only.
     */
    public function register_block_templates() {
        if ( ! function_exists( 'register_block_template' ) ) {
            return;
        }

        $prefix = get_stylesheet() . '//';
        register_block_template( $prefix . 'loopkit-full-width', array(
            'title'       => __( 'Loopkit Full Width', 'loopkit' ),
            'description' => __( 'Full width template with theme header and footer, no page title, and edge-to-edge content.', 'loopkit' ),
            'content'     => '<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->' . "\n" .
                             '<!-- wp:post-content {"align":"full","layout":{"type":"default"}} /-->' . "\n" .
                             '<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->',
            'post_types'  => array( 'page', 'post' ),
        ) );

        register_block_template( $prefix . 'loopkit-canvas', array(
            'title'       => __( 'Loopkit Canvas', 'loopkit' ),
            'description' => __( 'Blank canvas template with no header, footer or title; only Loopkit content.', 'loopkit' ),
            'content'     => '<!-- wp:post-content {"align":"full","layout":{"type":"default"}} /-->',
            'post_types'  => array( 'page', 'post' ),
        ) );
    }

    /**
     * Add Loopkit page templates to WordPress template selector.
     *
     * @param array $templates Array of template names keyed by file/slug.
     * @return array
     */
    public function add_theme_templates( $templates ) {
        $templates['loopkit-full-width'] = __( 'Loopkit Full Width', 'loopkit' );
        $templates['loopkit-canvas']     = __( 'Loopkit Canvas', 'loopkit' );
        return $templates;
    }

    /**
     * Get effective page layout for a post.
     * Respects page setting, then _wp_page_template, then Site Settings default_page_layout.
     * Header, Footer, Section, and Container templates use loopkit-canvas so they display
     * only the template content on a clean full-width canvas.
     *
     * @param int $post_id Post ID.
     * @return string 'loopkit-full-width', 'loopkit-canvas', 'default', or 'theme'.
     */
    public static function get_page_layout( $post_id ) {
        if ( ! $post_id ) {
            return 'loopkit-full-width';
        }

        // Templates (Header, Footer, Section, Container) use loopkit-canvas
        if ( 'loopkit_template' === get_post_type( $post_id ) ) {
            $tpl_type = get_post_meta( $post_id, '_loopkit_template_type', true );
            if ( in_array( $tpl_type, array( 'header', 'footer', 'section', 'container', 'loop-item', 'popup' ), true ) ) {
                return 'loopkit-canvas';
            }
        }

        // Check if page has an explicit Loopkit page setting
        $page_settings = get_post_meta( $post_id, '_loopkit_page_settings', true );
        if ( is_array( $page_settings ) && ! empty( $page_settings['page_layout'] ) ) {
            $layout = $page_settings['page_layout'];
            if ( 'loopkit_full_width' === $layout || 'loopkit-full-width' === $layout ) {
                return 'loopkit-full-width';
            }
            if ( 'loopkit_canvas' === $layout || 'loopkit-canvas' === $layout ) {
                return 'loopkit-canvas';
            }
            if ( 'default' === $layout || 'theme' === $layout ) {
                return 'default';
            }
        }

        // Check standard _wp_page_template
        $wp_template = get_post_meta( $post_id, '_wp_page_template', true );
        if ( ! empty( $wp_template ) && 'default' !== $wp_template ) {
            if ( 'loopkit-full-width' === $wp_template || 'loopkit_full_width' === $wp_template ) {
                return 'loopkit-full-width';
            }
            if ( 'loopkit-canvas' === $wp_template || 'loopkit_canvas' === $wp_template ) {
                return 'loopkit-canvas';
            }
            return 'default';
        }

        // Fallback to Site Settings Default Page Layout (default: Loopkit Full Width)
        if ( class_exists( 'Loopkit_Site_Settings' ) ) {
            $settings = Loopkit_Site_Settings::get_settings();
            $default  = $settings['default_page_layout'] ?? 'loopkit-full-width';
            if ( 'loopkit_canvas' === $default || 'loopkit-canvas' === $default ) {
                return 'loopkit-canvas';
            }
            if ( 'default' === $default || 'theme' === $default ) {
                return 'default';
            }
        }

        return 'loopkit-full-width';
    }

    /**
     * Filter template hierarchy for block themes to inject the effective Loopkit block template.
     *
     * @param array $templates Template hierarchy list.
     * @return array
     */
    public function filter_template_hierarchy( $templates ) {
        // Only singular documents (and the editor canvas) can be Loopkit pages. On archives the
        // queried object ID is a term/user ID and must never be read as a post ID.
        if ( ! isset( $_GET['loopkit_canvas'] ) && ! is_singular() ) { // phpcs:ignore WordPress.Security.NonceVerification
            return $templates;
        }
        $post_id = 0;
        if ( isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $post_id = absint( $_GET['loopkit_canvas'] );
        }
        if ( ! $post_id ) {
            $post_id = get_queried_object_id();
        }
        if ( ! $post_id && is_front_page() ) {
            $post_id = (int) get_option( 'page_on_front' );
        }
        if ( ! $post_id ) {
            global $post;
            $post_id = $post ? $post->ID : 0;
        }

        if ( ! $post_id ) {
            return $templates;
        }

        $edit_mode = get_post_meta( $post_id, '_loopkit_edit_mode', true );
        $is_canvas = isset( $_GET['loopkit_canvas'] ); // phpcs:ignore WordPress.Security.NonceVerification

        if ( 'builder' !== $edit_mode && ! $is_canvas ) {
            return $templates;
        }

        $layout = self::get_page_layout( $post_id );

        if ( 'loopkit-full-width' === $layout ) {
            array_unshift( $templates, 'loopkit-full-width' );
        } elseif ( 'loopkit-canvas' === $layout ) {
            array_unshift( $templates, 'loopkit-canvas' );
        }

        return array_unique( $templates );
    }

    /**
     * Filter template_include for classic themes and safety fallback for block themes.
     *
     * @param string $template Path to resolved template file.
     * @return string
     */
    public function filter_template_include( $template ) {
        // Only singular documents (and the editor canvas) can be Loopkit pages. On archives the
        // queried object ID is a term/user ID and must never be read as a post ID.
        if ( ! isset( $_GET['loopkit_canvas'] ) && ! is_singular() ) { // phpcs:ignore WordPress.Security.NonceVerification
            return $template;
        }
        $post_id = 0;
        if ( isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $post_id = absint( $_GET['loopkit_canvas'] );
        }
        if ( ! $post_id ) {
            $post_id = get_queried_object_id();
        }
        if ( ! $post_id && is_front_page() ) {
            $post_id = (int) get_option( 'page_on_front' );
        }
        if ( ! $post_id ) {
            global $post;
            $post_id = $post ? $post->ID : 0;
        }

        if ( ! $post_id ) {
            return $template;
        }

        $edit_mode = get_post_meta( $post_id, '_loopkit_edit_mode', true );
        $is_canvas = isset( $_GET['loopkit_canvas'] ); // phpcs:ignore WordPress.Security.NonceVerification

        if ( 'builder' !== $edit_mode && ! $is_canvas ) {
            return $template;
        }

        $layout = self::get_page_layout( $post_id );

        // If block theme
        if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
            global $_wp_current_template_id, $_wp_current_template_content;

            $invalid_templates = array( 'home', 'index', 'archive', 'search', '404' );
            $is_invalid = false;
            if ( ! empty( $_wp_current_template_id ) ) {
                foreach ( $invalid_templates as $inv ) {
                    if ( false !== strpos( $_wp_current_template_id, '//' . $inv ) ) {
                        $is_invalid = true;
                        break;
                    }
                }
            } else {
                $is_invalid = true;
            }

            // Fallback: If template is invalid or empty, force resolution to Loopkit block template
            if ( $is_invalid || empty( $_wp_current_template_content ) ) {
                $prefix = get_stylesheet() . '//';
                if ( 'loopkit-canvas' === $layout ) {
                    $_wp_current_template_id = $prefix . 'loopkit-canvas';
                    $_wp_current_template_content = '<!-- wp:post-content {"align":"full","layout":{"type":"default"}} /-->';
                } else {
                    $_wp_current_template_id = $prefix . 'loopkit-full-width';
                    $_wp_current_template_content = "<!-- wp:template-part {\"slug\":\"header\",\"area\":\"header\",\"tagName\":\"header\"} /-->\n<!-- wp:post-content {\"align\":\"full\",\"layout\":{\"type\":\"default\"}} /-->\n<!-- wp:template-part {\"slug\":\"footer\",\"area\":\"footer\",\"tagName\":\"footer\"} /-->";
                }
            }

            $canvas_file = ABSPATH . WPINC . '/template-canvas.php';
            if ( file_exists( $canvas_file ) ) {
                return $canvas_file;
            }
        }

        // Classic theme or fallback
        if ( 'loopkit-canvas' === $layout ) {
            $file = LOOPKIT_PLUGIN_DIR . 'templates/canvas.php';
            if ( file_exists( $file ) ) {
                return $file;
            }
        } elseif ( 'loopkit-full-width' === $layout ) {
            $file = LOOPKIT_PLUGIN_DIR . 'templates/full-width.php';
            if ( file_exists( $file ) ) {
                return $file;
            }
        }

        // Safety fallback: Never fall back to home/index/archive
        $page_template = get_page_template();
        if ( $page_template && file_exists( $page_template ) ) {
            return $page_template;
        }

        return $template;
    }

    /**
     * Get all templates, optionally filtered by type.
     *
     * @param string $type Optional template type to filter by.
     * @return WP_Post[]
     */
    public static function get_all( $type = 'all' ) {
        $args = array(
            'post_type'      => 'loopkit_template',
            'posts_per_page' => -1,
            'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( ! empty( $type ) && 'all' !== $type ) {
            if ( 'global' === $type ) {
                $args['meta_key']   = '_loopkit_is_global';
                $args['meta_value'] = '1';
            } else {
                $args['meta_key']   = '_loopkit_template_type';
                $args['meta_value'] = sanitize_key( $type );
            }
        }

        return get_posts( $args );
    }

    /**
     * Get template counts grouped by type.
     *
     * @return array
     */
    public static function get_counts() {
        $all = self::get_all( 'all' );
        $counts = array(
            'all'       => count( $all ),
            'header'    => 0,
            'footer'    => 0,
            'single'    => 0,
            'archive'   => 0,
            'loop-item' => 0,
            'popup'     => 0,
            'page'      => 0,
            'section'   => 0,
            'container' => 0,
            'global'    => 0,
        );

        foreach ( $all as $tpl ) {
            $is_global = get_post_meta( $tpl->ID, '_loopkit_is_global', true );
            if ( $is_global ) {
                $counts['global']++;
            }

            $type = get_post_meta( $tpl->ID, '_loopkit_template_type', true );
            $clean_type = strtolower( trim( (string) $type ) );
            if ( isset( $counts[ $clean_type ] ) ) {
                $counts[ $clean_type ]++;
            } elseif ( empty( $clean_type ) ) {
                $counts['page']++;
            }
        }

        return $counts;
    }

    /**
     * Get a human-readable display conditions summary for Header/Footer templates.
     *
     * @param int $post_id Template post ID.
     * @return string
     */
    public static function get_conditions_summary( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return '—';
        }

        if ( 'publish' !== $post->post_status ) {
            return __( 'Draft', 'loopkit' );
        }

        $conditions = get_post_meta( $post_id, '_loopkit_conditions', true );
        if ( empty( $conditions ) || ! is_array( $conditions ) ) {
            return __( 'No conditions', 'loopkit' );
        }

        $summaries = array();
        foreach ( $conditions as $cond ) {
            $type = $cond['type'] ?? 'include';
            $name = $cond['name'] ?? '';
            $sub  = $cond['sub'] ?? '';
            $id   = $cond['id'] ?? '';

            $prefix = 'exclude' === $type ? 'Exclude: ' : '';

            if ( 'general' === $name || 'entire_site' === $name || empty( $name ) ) {
                $summaries[] = $prefix . __( 'Entire Site', 'loopkit' );
            } elseif ( 'singular' === $name ) {
                if ( 'front_page' === $sub ) {
                    $summaries[] = $prefix . __( 'Front Page', 'loopkit' );
                } elseif ( 'page' === $sub ) {
                    if ( ! empty( $id ) && 'all' !== $id ) {
                        $target_title = get_the_title( absint( $id ) );
                        $summaries[] = $prefix . sprintf( __( 'Pages: %s', 'loopkit' ), $target_title ?: $id );
                    } else {
                        $summaries[] = $prefix . __( 'Pages', 'loopkit' );
                    }
                } elseif ( 'post' === $sub ) {
                    if ( ! empty( $id ) && 'all' !== $id ) {
                        $target_title = get_the_title( absint( $id ) );
                        $summaries[] = $prefix . sprintf( __( 'Posts: %s', 'loopkit' ), $target_title ?: $id );
                    } else {
                        $summaries[] = $prefix . __( 'Posts', 'loopkit' );
                    }
                } elseif ( '404' === $sub ) {
                    $summaries[] = $prefix . __( '404 Page', 'loopkit' );
                } else {
                    $summaries[] = $prefix . __( 'All Singular', 'loopkit' );
                }
            } elseif ( 'archive' === $name || 'archives' === $name ) {
                if ( 'post' === $sub || 'blog' === $sub ) {
                    $summaries[] = $prefix . __( 'Posts Archive', 'loopkit' );
                } elseif ( 'search' === $sub ) {
                    $summaries[] = $prefix . __( 'Search Results', 'loopkit' );
                } else {
                    $summaries[] = $prefix . __( 'Archives', 'loopkit' );
                }
            } else {
                $summaries[] = $prefix . ucfirst( $name );
            }
        }

        if ( empty( $summaries ) ) {
            return __( 'No conditions', 'loopkit' );
        }

        return implode( ', ', array_slice( $summaries, 0, 2 ) ) . ( count( $summaries ) > 2 ? '...' : '' );
    }

    /**
     * AJAX handler: create a template.
     */
    public function ajax_create_template() {
        check_ajax_referer( 'loopkit_templates_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'loopkit' ) ) );
        }

        $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        if ( empty( $title ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Please enter a template name.', 'loopkit' ) ) );
        }

        $raw_type = sanitize_key( wp_unslash( $_POST['template_type'] ?? 'page' ) );
        $valid_types = array( 'header', 'footer', 'single', 'archive', 'loop-item', 'popup', 'page', 'section', 'container' );
        $template_type = in_array( $raw_type, $valid_types, true ) ? $raw_type : 'page';

        $post_id = wp_insert_post( array(
            'post_type'   => 'loopkit_template',
            'post_title'  => $title,
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
        ) );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
        }

        $is_global = ! empty( $_POST['is_global'] ) && in_array( $template_type, array( 'section', 'container' ), true );

        update_post_meta( $post_id, '_loopkit_template_type', $template_type );
        update_post_meta( $post_id, '_loopkit_edit_mode', 'builder' );

        if ( $is_global ) {
            update_post_meta( $post_id, '_loopkit_is_global', 1 );
            update_post_meta( $post_id, '_loopkit_global_usage', array() );
            $master_id = function_exists( 'loopkit_generate_id' ) ? loopkit_generate_id() : substr( md5( uniqid( '', true ) ), 0, 7 );
            $master_el = array(
                'id'       => $master_id,
                'elType'   => $template_type,
                'settings' => array(
                    'content_width' => 'boxed',
                    'html_tag'      => ( 'section' === $template_type ) ? 'section' : 'div',
                ),
                'elements' => array(),
            );
            update_post_meta( $post_id, '_loopkit_data', wp_slash( wp_json_encode( array( $master_el ) ) ) );
        } else {
            update_post_meta( $post_id, '_loopkit_data', wp_json_encode( array() ) );
        }

        wp_send_json_success( array(
            'id'       => $post_id,
            'title'    => $title,
            'type'     => $template_type,
            'is_global'=> $is_global,
            'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=loopkit' ),
        ) );
    }

    /**
     * AJAX handler: convert an existing section/container template to global.
     */
    public function ajax_make_global_template() {
        check_ajax_referer( 'loopkit_templates_nonce', 'nonce' );

        $post_id = absint( $_POST['template_id'] ?? 0 );
        if ( ! $post_id || get_post_type( $post_id ) !== 'loopkit_template' ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid template.', 'loopkit' ) ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'loopkit' ) ) );
        }

        $tpl_type = get_post_meta( $post_id, '_loopkit_template_type', true );
        if ( ! in_array( $tpl_type, array( 'section', 'container' ), true ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Only Section and Container templates can be made global.', 'loopkit' ) ) );
        }

        update_post_meta( $post_id, '_loopkit_is_global', 1 );
        if ( ! metadata_exists( 'post', $post_id, '_loopkit_global_usage' ) ) {
            update_post_meta( $post_id, '_loopkit_global_usage', array() );
        }

        // Generate scoped global CSS if data exists
        $raw = get_post_meta( $post_id, '_loopkit_data', true );
        $elements = $raw ? json_decode( $raw, true ) : array();
        if ( is_array( $elements ) && ! empty( $elements ) && class_exists( 'Loopkit_Rest_Api' ) ) {
            try {
                $rest = new Loopkit_Rest_Api();
                $method = new ReflectionMethod( $rest, 'generate_global_css' );
                $method->setAccessible( true );
                $method->invoke( $rest, $post_id, $elements );
            } catch ( Throwable $e ) {
                // Non-fatal
            }
        }

        wp_send_json_success( array(
            'id'      => $post_id,
            'message' => esc_html__( 'Template converted to Global successfully.', 'loopkit' ),
        ) );
    }

    /**
     * AJAX handler: rename a template.
     */
    public function ajax_rename_template() {
        check_ajax_referer( 'loopkit_templates_nonce', 'nonce' );

        $post_id = absint( $_POST['template_id'] ?? 0 );
        if ( ! $post_id || get_post_type( $post_id ) !== 'loopkit_template' ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid template.', 'loopkit' ) ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'loopkit' ) ) );
        }

        $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        if ( empty( $title ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Template title cannot be empty.', 'loopkit' ) ) );
        }

        $res = wp_update_post( array(
            'ID'         => $post_id,
            'post_title' => $title,
        ) );

        if ( is_wp_error( $res ) ) {
            wp_send_json_error( array( 'message' => $res->get_error_message() ) );
        }

        wp_send_json_success( array(
            'id'    => $post_id,
            'title' => $title,
        ) );
    }

    /**
     * AJAX handler: duplicate a template.
     */
    public function ajax_duplicate_template() {
        check_ajax_referer( 'loopkit_templates_nonce', 'nonce' );

        $source_id = absint( $_POST['template_id'] ?? 0 );
        if ( ! $source_id || get_post_type( $source_id ) !== 'loopkit_template' ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid template.', 'loopkit' ) ) );
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'loopkit' ) ) );
        }

        $source = get_post( $source_id );
        if ( ! $source ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Template not found.', 'loopkit' ) ) );
        }

        $new_title = sprintf( __( 'Copy of %s', 'loopkit' ), $source->post_title );

        $new_id = wp_insert_post( array(
            'post_type'   => 'loopkit_template',
            'post_title'  => $new_title,
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
        ) );

        if ( is_wp_error( $new_id ) ) {
            wp_send_json_error( array( 'message' => $new_id->get_error_message() ) );
        }

        // Clone meta
        $template_type = get_post_meta( $source_id, '_loopkit_template_type', true );
        $loopkit_data  = get_post_meta( $source_id, '_loopkit_data', true );
        $loopkit_css   = get_post_meta( $source_id, '_loopkit_css', true );
        $page_settings = get_post_meta( $source_id, '_loopkit_page_settings', true );

        update_post_meta( $new_id, '_loopkit_template_type', $template_type ?: 'page' );
        update_post_meta( $new_id, '_loopkit_edit_mode', 'builder' );
        update_post_meta( $new_id, '_loopkit_data', wp_slash( $loopkit_data ?: wp_json_encode( array() ) ) );
        if ( ! empty( $loopkit_css ) ) {
            update_post_meta( $new_id, '_loopkit_css', $loopkit_css );
        }
        if ( ! empty( $page_settings ) ) {
            update_post_meta( $new_id, '_loopkit_page_settings', $page_settings );
        }

        wp_send_json_success( array(
            'id'       => $new_id,
            'title'    => $new_title,
            'edit_url' => admin_url( 'post.php?post=' . $new_id . '&action=loopkit' ),
        ) );
    }

    /**
     * AJAX handler: delete a template.
     */
    public function ajax_delete_template() {
        check_ajax_referer( 'loopkit_templates_nonce', 'nonce' );

        $post_id = absint( $_POST['template_id'] ?? 0 );
        if ( ! $post_id || get_post_type( $post_id ) !== 'loopkit_template' ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid template.', 'loopkit' ) ) );
        }

        if ( ! current_user_can( 'delete_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'loopkit' ) ) );
        }

        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        wp_delete_post( $post_id, true );
        wp_send_json_success( array(
            'id'     => $post_id,
            'counts' => self::get_counts(),
        ) );
    }

    /**
     * Download template JSON export.
     */
    public function export_template() {
        $post_id = absint( $_GET['id'] ?? 0 );
        $nonce   = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, 'loopkit_export_' . $post_id ) ) {
            wp_die( esc_html__( 'Security check failed.', 'loopkit' ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'Permission denied.', 'loopkit' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || 'loopkit_template' !== $post->post_type ) {
            wp_die( esc_html__( 'Template not found.', 'loopkit' ) );
        }

        $raw_data      = get_post_meta( $post_id, '_loopkit_data', true );
        $elements      = $raw_data ? json_decode( $raw_data, true ) : array();
        $template_type = get_post_meta( $post_id, '_loopkit_template_type', true );
        $page_settings = get_post_meta( $post_id, '_loopkit_page_settings', true );

        $export_payload = array(
            'version'       => LOOPKIT_VERSION,
            'title'         => $post->post_title,
            'type'          => $template_type ?: 'page',
            'elements'      => $elements,
            'page_settings' => $page_settings ?: array(),
        );

        $filename = sanitize_title( $post->post_title ) . '-template-' . $post_id . '.json';

        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        echo wp_json_encode( $export_payload, JSON_PRETTY_PRINT );
        exit;
    }

    /**
     * Get matching template post ID for a location ('header' or 'footer') for current request.
     * Public resolver exposed for themes and external integrations.
     *
     * @param string $type Location type ('header' or 'footer').
     * @return int|null Template post ID if matching template applies, null otherwise.
     */
    public static function get_matching_template( $type ) {
        if ( class_exists( 'Loopkit_Theme_Builder' ) ) {
            $builder = Loopkit_Theme_Builder::get_instance();
            if ( method_exists( $builder, 'get_active_template_for_location' ) ) {
                $winner = $builder->get_active_template_for_location( $type );
                if ( ! empty( $winner['id'] ) ) {
                    return (int) $winner['id'];
                }
            }
        }
        return null;
    }

    /**
     * Render a Loopkit template (header or footer) by type or post ID.
     *
     * @param string   $type        Location type ('header' or 'footer').
     * @param int|null $template_id Optional template post ID. If null, resolves matching template.
     * @return string Rendered template HTML.
     */
    public static function render_template( $type, $template_id = null ) {
        if ( null === $template_id ) {
            $template_id = self::get_matching_template( $type );
        }
        if ( ! $template_id || ! class_exists( 'Loopkit_Theme_Builder' ) ) {
            return '';
        }
        $builder = Loopkit_Theme_Builder::get_instance();
        if ( method_exists( $builder, 'is_location_rendered' ) && $builder->is_location_rendered( $type ) ) {
            return '';
        }
        if ( method_exists( $builder, 'mark_location_rendered' ) ) {
            $builder->mark_location_rendered( $type );
        }
        if ( method_exists( $builder, 'render_location' ) ) {
            return $builder->render_location( $type, $template_id );
        }
        return '';
    }
}

new Loopkit_Templates();
