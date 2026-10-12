<?php
/**
 * Loopkit REST API Endpoints.
 * Handles loading, saving, autosaving, revisioning and CSS generation.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Rest_Api {

    /**
     * REST namespace.
     *
     * @var string
     */
    const REST_NAMESPACE = 'loopkit/v1';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( '_wp_put_post_revision', array( $this, 'on_put_post_revision' ) );
    }

    /**
     * Register REST routes.
     */
    public function register_routes() {
        // Document: get and save at /document/(?P<id>\d+)
        register_rest_route(
            self::REST_NAMESPACE,
            '/document/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_document' ),
                    'permission_callback' => array( $this, 'check_permission' ),
                    'args'                => array(
                        'id' => array(
                            'validate_callback' => function( $param ) {
                                return is_numeric( $param );
                            },
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'save_document' ),
                    'permission_callback' => array( $this, 'check_permission' ),
                    'args'                => array(
                        'id' => array(
                            'validate_callback' => function( $param ) {
                                return is_numeric( $param );
                            },
                        ),
                    ),
                ),
            )
        );

        // Document: save alias at /document/(?P<id>\d+)/save for compatibility
        register_rest_route(
            self::REST_NAMESPACE,
            '/document/(?P<id>\d+)/save',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'save_document' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'id' => array(
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            )
        );

        // Document: get revisions at /document/(?P<id>\d+)/revisions
        register_rest_route(
            self::REST_NAMESPACE,
            '/document/(?P<id>\d+)/revisions',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_document_revisions' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'id' => array(
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            )
        );

        // Global: save as global
        register_rest_route(
            self::REST_NAMESPACE,
            '/global',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'save_as_global' ),
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        // Global: list all globals
        register_rest_route(
            self::REST_NAMESPACE,
            '/globals',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'list_globals' ),
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        // Global: delete global
        register_rest_route(
            self::REST_NAMESPACE,
            '/global/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_global' ),
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
                'args'                => array(
                    'id' => array(
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            )
        );

        // Templates (Non-Global Section & Container)
        register_rest_route(
            self::REST_NAMESPACE,
            '/templates',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'list_templates' ),
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/templates/import',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'import_template' ),
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        // Render shortcode endpoint (server-side preview)
        register_rest_route(
            self::REST_NAMESPACE,
            '/render-shortcode',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'render_shortcode' ),
                'permission_callback' => function( $request ) {
                    $post_id = $request->get_param( 'post_id' );
                    if ( $post_id ) {
                        return current_user_can( 'edit_post', $post_id );
                    }
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        // Post widgets: data of the preview post (Template Settings > Preview With)
        register_rest_route(
            self::REST_NAMESPACE,
            '/post-preview-data',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_post_preview_data' ),
                'permission_callback' => function( $request ) {
                    $id = absint( $request->get_param( 'id' ) );
                    return current_user_can( 'edit_posts' ) && $id && current_user_can( 'read_post', $id );
                },
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            )
        );

        // Archive widgets: data of the preview archive (Template Settings > Preview With)
        register_rest_route(
            self::REST_NAMESPACE,
            '/archive-preview-data',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_archive_preview_data' ),
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
                'args'                => array(
                    'key' => array(
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_string( $param ) && preg_match( '/^(blog|search|(category|post_tag):\d+)$/', $param );
                        },
                    ),
                ),
            )
        );

        // Server-rendered widgets for the editor canvas (Posts, Pagination): same PHP as the front end
        register_rest_route(
            self::REST_NAMESPACE,
            '/render-widget',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'render_widget_for_canvas' ),
                'permission_callback' => function( $request ) {
                    $doc = absint( $request->get_param( 'document_id' ) );
                    return $doc && current_user_can( 'edit_post', $doc );
                },
            )
        );

        // Which weights a font family actually ships (editor greys out the rest)
        register_rest_route(
            self::REST_NAMESPACE,
            '/font-weights',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => function( $request ) {
                    $family = sanitize_text_field( (string) $request->get_param( 'family' ) );
                    return new WP_REST_Response( array(
                        'family'  => $family,
                        'weights' => class_exists( 'Loopkit_Fonts' ) ? Loopkit_Fonts::get_available_weights( $family ) : array(),
                    ), 200 );
                },
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );

        // Site Settings endpoint (D1)
        register_rest_route(
            self::REST_NAMESPACE,
            '/site-settings',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_site_settings' ),
                    'permission_callback' => function() {
                        return current_user_can( 'edit_posts' );
                    },
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'save_site_settings' ),
                    'permission_callback' => function() {
                        // Changes site title, site icon and global CSS: admins only.
                        return current_user_can( 'manage_options' );
                    },
                ),
            )
        );

        // User Preferences endpoint (D2)
        register_rest_route(
            self::REST_NAMESPACE,
            '/user-preferences',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_user_preferences' ),
                    'permission_callback' => function() {
                        return is_user_logged_in();
                    },
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'save_user_preferences' ),
                    'permission_callback' => function() {
                        return is_user_logged_in();
                    },
                ),
            )
        );

        // Finder dynamic items endpoint (D3)
        register_rest_route(
            self::REST_NAMESPACE,
            '/finder-items',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_finder_items' ),
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }

    /**
     * Permission callback for every document route.
     *
     * Read and edit used to be two identical methods both returning edit_post. They are
     * merged here so there is one rule to keep honest: this endpoint exposes a post's
     * layout document, and anyone who may edit that post may read and write it.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool
     */
    public function check_permission( $request ) {
        $post_id = (int) $request['id'];
        return (bool) current_user_can( 'edit_post', $post_id );
    }

    /**
     * Get document endpoint.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_document( $request ) {
        $post_id = (int) $request['id'];
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return new WP_REST_Response( array( 'message' => esc_html__( 'Post not found.', 'loopkit' ) ), 404 );
        }

        $raw_elements  = get_post_meta( $post_id, '_loopkit_data', true );
        $elements      = $raw_elements ? json_decode( $raw_elements, true ) : array();
        $page_settings = get_post_meta( $post_id, '_loopkit_page_settings', true );

        // Ensure root level elements are Sections
        $elements = $this->ensure_sections( is_array( $elements ) ? $elements : array() );

        return new WP_REST_Response(
            array(
                'id'           => $post_id,
                'title'        => $post->post_title,
                'status'       => $post->post_status,
                'elements'     => $elements,
                'pageSettings' => is_array( $page_settings ) ? $page_settings : array(),
            ),
            200
        );
    }

    /**
     * Save document endpoint (Section 19).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function save_document( $request ) {
        $post_id = (int) $request['id'];
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return new WP_REST_Response( array( 'message' => esc_html__( 'Post not found.', 'loopkit' ) ), 404 );
        }

        $params = $request->get_json_params();
        if ( ! $params ) {
            $params = $request->get_body_params();
        }
        if ( ! $params ) {
            $raw_body = $request->get_body();
            if ( $raw_body ) {
                $params = json_decode( $raw_body, true );
            }
        }
        if ( ! is_array( $params ) ) {
            $params = array();
        }

        $raw_elements = $params['elements'] ?? array();
        if ( is_string( $raw_elements ) ) {
            $raw_elements = json_decode( $raw_elements, true );
        }

        // Ensure root level elements are Sections
        $raw_elements = $this->ensure_sections( is_array( $raw_elements ) ? $raw_elements : array() );

        // Sanitize elements recursively
        $elements = $this->sanitize_elements( $raw_elements );

        // Save element data as JSON array
        $json_data = wp_json_encode( $elements );
        // wp_slash(): update_post_meta() strips backslashes, which would corrupt JSON escapes
        // (line breaks became "n" and a straight double quote broke the whole layout).
        update_post_meta( $post_id, '_loopkit_data', wp_slash( $json_data ) );

        // Set edit mode to builder (Section 1 & 20)
        update_post_meta( $post_id, '_loopkit_edit_mode', 'builder' );
        update_post_meta( $post_id, '_loopkit_version', LOOPKIT_VERSION );

        // Save page settings if provided
        if ( isset( $params['pageSettings'] ) && is_array( $params['pageSettings'] ) ) {
            // Popup settings live in their own meta key so they survive page-settings saves.
            if ( class_exists( 'Loopkit_Popups' ) && isset( $params['pageSettings']['popup'] ) && is_array( $params['pageSettings']['popup'] ) ) {
                update_post_meta( $post_id, '_loopkit_popup_settings', Loopkit_Popups::sanitize_settings( $params['pageSettings']['popup'] ) );
                unset( $params['pageSettings']['popup'] );
            }

            $sanitized_settings = $this->sanitize_settings( $params['pageSettings'] );

            // Custom JS is admin-only and was dropped for everyone else: keep what an
            // administrator saved earlier instead of wiping it when a non-admin saves the page.
            if ( ! isset( $sanitized_settings['custom_js'] ) ) {
                $existing = get_post_meta( $post_id, '_loopkit_page_settings', true );
                if ( is_array( $existing ) && isset( $existing['custom_js'] ) && ! current_user_can( 'manage_options' ) ) {
                    $sanitized_settings['custom_js'] = $existing['custom_js'];
                }
            }

            update_post_meta( $post_id, '_loopkit_page_settings', $sanitized_settings );

            // Sync with _wp_page_template if template was chosen
            if ( ! empty( $sanitized_settings['page_layout'] ) ) {
                $layout = $sanitized_settings['page_layout'];
                if ( in_array( $layout, array( 'loopkit-full-width', 'loopkit-canvas', 'default', 'theme' ), true ) ) {
                    update_post_meta( $post_id, '_wp_page_template', $layout );
                }
            }
        }

        // Save display conditions if provided
        if ( isset( $params['conditions'] ) && is_array( $params['conditions'] ) ) {
            $sanitized_conditions = array();
            foreach ( $params['conditions'] as $cond ) {
                if ( ! is_array( $cond ) ) {
                    continue;
                }
                $sanitized_conditions[] = array(
                    'type' => ( isset( $cond['type'] ) && 'exclude' === $cond['type'] ) ? 'exclude' : 'include',
                    'name' => sanitize_text_field( $cond['name'] ?? 'general' ),
                    'sub'  => sanitize_text_field( $cond['sub'] ?? '' ),
                    'item' => sanitize_text_field( $cond['item'] ?? '' ),
                );
            }
            update_post_meta( $post_id, '_loopkit_conditions', $sanitized_conditions );
        }

        // Update post status / title if requested
        $post_updates = array( 'ID' => $post_id );
        $needs_update = false;

        $post_type = get_post_type( $post_id );
        $tpl_type  = get_post_meta( $post_id, '_loopkit_template_type', true );
        $is_hf     = ( 'loopkit_template' === $post_type && in_array( $tpl_type, array( 'header', 'footer', 'single', 'archive', 'popup' ), true ) );

        if ( ! empty( $params['status'] ) && in_array( $params['status'], array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
            $target_status = sanitize_key( $params['status'] );
            if ( $is_hf && 'publish' === $target_status ) {
                $conds = get_post_meta( $post_id, '_loopkit_conditions', true );
                if ( empty( $conds ) || ! is_array( $conds ) ) {
                    // Cannot publish without conditions!
                    $target_status = 'draft';
                }
            }
            $post_updates['post_status'] = $target_status;
            $needs_update = true;
        } elseif ( $is_hf && 'publish' === get_post_status( $post_id ) ) {
            $conds = get_post_meta( $post_id, '_loopkit_conditions', true );
            if ( empty( $conds ) || ! is_array( $conds ) ) {
                $post_updates['post_status'] = 'draft';
                $needs_update = true;
            }
        }

        if ( ! empty( $params['title'] ) ) {
            $post_updates['post_title'] = sanitize_text_field( $params['title'] );
            $needs_update = true;
        }

        if ( $needs_update ) {
            wp_update_post( $post_updates );
        }

        // Force creation of a WordPress revision that also stores _loopkit_data and page settings
        add_filter( 'wp_save_post_revision_post_has_changed', '__return_true' );
        $rev_id = wp_save_post_revision( $post_id );
        remove_filter( 'wp_save_post_revision_post_has_changed', '__return_true' );
        if ( $rev_id && ! is_wp_error( $rev_id ) ) {
            // wp_slash(): update_metadata() unslashes what it is given, and the element JSON
            // contains escapes (\/ from wp_json_encode, \n inside text). Without slashing
            // again here the revision is stored damaged even though the live post at the
            // top of this method was written correctly â€” so every revision was corrupted
            // while the page itself looked fine.
            update_metadata( 'post', $rev_id, '_loopkit_data', wp_slash( $json_data ) );
            if ( isset( $sanitized_settings ) ) {
                update_metadata( 'post', $rev_id, '_loopkit_page_settings', $sanitized_settings );
            }
        }

        // Generate and save CSS (Section 3). If CSS file cannot be written, fall back to inline CSS meta.
        try {
            $is_global_tpl = (bool) get_post_meta( $post_id, '_loopkit_is_global', true );
            if ( $is_global_tpl ) {
                $this->generate_global_css( $post_id, $elements );
            } else {
                $this->generate_css( $post_id, $elements );
            }
        } catch ( Throwable $e ) {
            // Keep page save intact even if CSS engine catches an unexpected error
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Loopkit CSS generation error: ' . $e->getMessage() );
            }
        }

        // Rebuild global usage index
        $this->rebuild_global_usage( $post_id, $elements );

        // Rebuild Theme Builder conditions cache if template was saved
        if ( class_exists( 'Loopkit_Theme_Builder' ) ) {
            Loopkit_Theme_Builder::rebuild_cache();
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'status'  => get_post_status( $post_id ),
                'message' => esc_html__( 'Page saved successfully.', 'loopkit' ),
            ),
            200
        );
    }

    /**
     * Render a server-side widget (Posts, Pagination) for the editor canvas.
     * The preview_* values are the editor's current (possibly unsaved) Preview With choice.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function render_widget_for_canvas( WP_REST_Request $request ) {
        $type = sanitize_key( (string) $request->get_param( 'widget' ) );
        // Widgets whose content comes from the WordPress dashboard (menus, search form,
        // shortcodes) or from a query: the canvas renders them with the same PHP as the front
        // end, so the preview can never differ from the live site.
        $server_rendered = array( 'product-grid', 'add-to-cart', 'product-price', 'product-gallery', 'cart-icon', 'cart-table', 'checkout-form', 'product-categories', 'marquee', 'flip-box', 'price-table', 'countdown', 'animated-headline', 'call-to-action', 'share-buttons',
            'nav-menu', 'search-form', 'posts', 'loop-grid', 'pagination', 'accordion', 'toggle', 'tabs', 'counter', 'progress-bar', 'alert', 'star-rating', 'testimonial', 'image-carousel', 'image-gallery', 'google-maps', 'contact-form', 'tooltip', 'table-of-contents',
            'team-member', 'testimonial-carousel', 'blockquote', 'business-hours', 'hotspot', 'portfolio', 'login-form',
            'product-filters', 'mini-cart', 'product-rating', 'my-account',
            'collection-list' );
        if ( ! in_array( $type, $server_rendered, true ) || ! class_exists( 'Loopkit_Widgets_Registry' ) ) {
            return new WP_Error( 'invalid_widget', __( 'Unknown widget', 'loopkit' ), array( 'status' => 400 ) );
        }
        $widget = Loopkit_Widgets_Registry::instance()->get_widget( $type );
        if ( ! $widget ) {
            return new WP_Error( 'invalid_widget', __( 'Unknown widget', 'loopkit' ), array( 'status' => 400 ) );
        }
        $settings = $request->get_param( 'settings' );
        $settings = is_array( $settings ) ? $this->sanitize_settings( $settings ) : array();
        $doc      = absint( $request->get_param( 'document_id' ) );

        // Render exactly as the canvas would (editor mode, previewing this document),
        // honouring the editor's current Preview With choice.
        $_GET['loopkit_canvas'] = $doc; // phpcs:ignore WordPress.Security.NonceVerification
        $preview_filter = function( $value, $object_id, $meta_key ) use ( $doc, $request, &$preview_filter ) {
            if ( (int) $object_id !== $doc || '_loopkit_page_settings' !== $meta_key ) {
                return $value;
            }
            // Read the stored value with this filter detached: get_post_meta() runs this same
            // filter again, which would otherwise recurse until PHP runs out of memory.
            remove_filter( 'get_post_metadata', $preview_filter, 10 );
            $stored = get_post_meta( $doc, '_loopkit_page_settings', true );
            add_filter( 'get_post_metadata', $preview_filter, 10, 3 );
            $stored = is_array( $stored ) ? $stored : array();
            if ( $request->get_param( 'preview_archive' ) ) {
                $stored['preview_archive'] = sanitize_text_field( (string) $request->get_param( 'preview_archive' ) );
            }
            if ( $request->get_param( 'preview_id' ) ) {
                $stored['preview_id'] = absint( $request->get_param( 'preview_id' ) );
            }
            return array( $stored );
        };
        add_filter( 'get_post_metadata', $preview_filter, 10, 3 );
        if ( class_exists( 'Loopkit_Dynamic' ) && Loopkit_Dynamic::has_tokens( $settings ) ) {
            $settings = Loopkit_Dynamic::resolve_settings( $settings, true );
        }
        $settings = $widget->with_defaults( $settings );
        $html     = $widget->render( $settings, array( 'id' => sanitize_html_class( (string) $request->get_param( 'element_id' ) ) ?: 'preview', 'settings' => $settings ), true );
        remove_filter( 'get_post_metadata', $preview_filter, 10 );
        unset( $_GET['loopkit_canvas'] );

        return new WP_REST_Response( array( 'html' => $html ), 200 );
    }

    /**
     * Data of one archive for the editor's Archive widgets (Preview With).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_archive_preview_data( WP_REST_Request $request ) {
        return new WP_REST_Response( Loopkit_Archive_Data::preview( (string) $request->get_param( 'key' ) ), 200 );
    }

    /**
     * Data of one post for the editor's Post widgets (Preview With).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_post_preview_data( WP_REST_Request $request ) {
        $id = absint( $request->get_param( 'id' ) );
        if ( ! class_exists( 'Loopkit_Post_Data' ) || ! Loopkit_Post_Data::is_previewable( $id ) ) {
            return new WP_Error( 'not_found', __( 'Post not found', 'loopkit' ), array( 'status' => 404 ) );
        }
        return new WP_REST_Response( Loopkit_Post_Data::get_data( $id, true ), 200 );
    }

    /**
     * Render shortcode for editor live preview.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function render_shortcode( WP_REST_Request $request ) {
        $shortcode = trim( (string) $request->get_param( 'shortcode' ) );
        if ( empty( $shortcode ) ) {
            return new WP_REST_Response( array(
                'html'        => '<div class="lk-shortcode-placeholder"><span class="dashicons dashicons-shortcode"></span><span>' . esc_html__( 'Enter your shortcode in the settings panel', 'loopkit' ) . '</span></div>',
                'is_fallback' => true,
            ), 200 );
        }

        $post_id = $request->get_param( 'post_id' );
        if ( $post_id ) {
            global $post;
            $post = get_post( $post_id );
            if ( $post ) {
                setup_postdata( $post );
            }
        }

        // Check if shortcode tag exists
        preg_match( '/\[([a-zA-Z0-9_\-]+)/', $shortcode, $matches );
        $tag_exists = ! empty( $matches[1] ) && shortcode_exists( $matches[1] );

        $rendered = do_shortcode( $shortcode );

        if ( ! $tag_exists || empty( trim( $rendered ) ) || trim( $rendered ) === trim( $shortcode ) ) {
            return new WP_REST_Response( array(
                'html'        => '<div class="lk-shortcode-placeholder"><span class="dashicons dashicons-shortcode"></span><span>' . esc_html( $shortcode ) . '</span></div>',
                'is_fallback' => true,
            ), 200 );
        }

        return new WP_REST_Response( array(
            'html'        => '<div class="lk-widget-shortcode">' . $rendered . '</div>',
            'is_fallback' => false,
        ), 200 );
    }

    /**
     * Ensure root level elements are Sections.
     * Converts any root-level container to a Section preserving ID and settings.
     * Wraps any root-level widget into a Section.
     *
     * @param array $elements Elements list.
     * @return array
     */
    private function ensure_sections( $elements ) {
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
     * Recursively sanitize elements.
     *
     * @param array $elements Elements array.
     * @return array Sanitized elements.
     */
    private function sanitize_elements( $elements ) {
        if ( ! is_array( $elements ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $elements as $el ) {
            if ( ! is_array( $el ) ) {
                continue;
            }

            $el_type = isset( $el['elType'] ) ? sanitize_key( $el['elType'] ) : 'container';

            // Global instance: preserve special fields, don't recurse into children
            if ( 'global' === $el_type ) {
                $item = array(
                    'id'         => isset( $el['id'] ) ? sanitize_text_field( $el['id'] ) : loopkit_generate_id(),
                    'elType'     => 'global',
                    'globalType' => isset( $el['globalType'] ) ? sanitize_key( $el['globalType'] ) : 'section',
                    'templateId' => isset( $el['templateId'] ) ? absint( $el['templateId'] ) : 0,
                    'overrides'  => isset( $el['overrides'] ) && is_array( $el['overrides'] )
                        ? $this->sanitize_settings( $el['overrides'] )
                        : array(),
                    'elements'   => array(),
                );
                $sanitized[] = $item;
                continue;
            }

            $item            = array();
            $item['id']      = isset( $el['id'] ) ? sanitize_text_field( $el['id'] ) : loopkit_generate_id();
            $item['elType']  = $el_type;
            $item['isInner'] = ! empty( $el['isInner'] );

            if ( isset( $el['widgetType'] ) ) {
                $item['widgetType'] = sanitize_key( $el['widgetType'] );
            } elseif ( isset( $el['widget'] ) ) {
                $item['widgetType'] = sanitize_key( $el['widget'] );
            }

            // Settings: sanitize recursively
            $item['settings'] = isset( $el['settings'] ) && is_array( $el['settings'] )
                ? $this->sanitize_settings( $el['settings'] )
                : array();

            // Children
            $item['elements'] = isset( $el['elements'] ) && is_array( $el['elements'] )
                ? $this->sanitize_elements( $el['elements'] )
                : array();

            $sanitized[] = $item;
        }

        return $sanitized;
    }
    /**
     * Run wp_kses_post() without touching any {{lk:â€¦}} Dynamic Tags in the markup.
     *
     * kses rewrites the brace syntax, which silently emptied every Dynamic Tag inside an
     * HTML widget for users without the unfiltered_html capability. The tokens are swapped
     * for plain placeholders (which kses leaves alone) and restored afterwards.
     *
     * @param string $html Raw markup.
     * @return string Sanitized markup with its tokens intact.
     */
    private static function kses_keeping_dynamic_tags( $html ) {
        $tokens = array();
        $masked = preg_replace_callback(
            '/\{\{\s*lk:[a-z0-9_]+(?::[^|}]*)?(?:\|[^}]*)?\}\}/i',
            static function ( $m ) use ( &$tokens ) {
                $placeholder              = 'lkDynamicTag' . count( $tokens ) . 'End';
                $tokens[ $placeholder ] = $m[0];
                return $placeholder;
            },
            (string) $html
        );

        return strtr( wp_kses_post( $masked ), $tokens );
    }


    /**
     * Setting names whose value is multi-line and must keep its line breaks.
     *
     * This used to be a hand-written list of names, which is how seven textarea controls
     * came to be flattened by sanitize_text_field() while the panel showed them correctly.
     * The list is now derived from the widgets themselves: every control declared with
     * type 'textarea' is multi-line by definition, so a new textarea is covered the moment
     * it is written rather than whenever somebody remembers to add it here.
     *
     * A few names are still added by hand because they are multi-line without declaring
     * a textarea control of that name.
     *
     * @return array<string,bool> Lower-cased setting names.
     */
    private static function multiline_setting_names() {
        // Every setting whose value must keep its line breaks. Derived by walking every
        // widget's declared controls, so it is complete for the current widget set;
        // add a name here only when a field is multi-line without declaring a textarea.
        static $names = array(
            // hand-kept: multi-line values that are not textarea controls
            'custom_attributes' => true,
            'items'             => true,
            'custom_js'         => true,
            // textarea controls
            'ah_words'          => true, // animated headline, one word per line
            'flip_front_text'   => true,
            'flip_back_text'    => true,
            'cta_description'   => true,
            'description_text'  => true, // icon box, image box
            'shortcode'         => true,
            'team_bio'          => true,
            'quote'             => true,
            'bq_text'           => true,
            'text'              => true, // hotspot tooltip
            'title'             => true,
            'content'           => true,
        );

        return $names;
    }

    /**
     * Sanitize settings recursively.
     *
     * @param array $settings Settings array.
     * @return array Sanitized settings.
     */
    private function sanitize_settings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $settings as $key => $value ) {
            $clean_key = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key );
            if ( '' === $clean_key ) {
                continue;
            }

            if ( is_null( $value ) ) {
                $sanitized[ $clean_key ] = null;
            } elseif ( preg_match( '/^background_overlay_opacity(_hover)?(_(tablet|mobile|widescreen|laptop|tablet_extra|mobile_extra))?$/', $clean_key ) ) {
                if ( is_array( $value ) ) {
                    $val_num = $value['size'] ?? '';
                    $sanitized[ $clean_key ] = ( '' !== $val_num && is_numeric( $val_num ) ) ? floatval( $val_num ) : ( is_numeric( $val_num ) ? floatval( $val_num ) : $val_num );
                } elseif ( is_string( $value ) ) {
                    $clean_num = preg_replace( '/[^0-9.]/', '', $value );
                    $sanitized[ $clean_key ] = ( '' !== $clean_num && is_numeric( $clean_num ) ) ? floatval( $clean_num ) : $value;
                } else {
                    $sanitized[ $clean_key ] = is_numeric( $value ) ? floatval( $value ) : $value;
                }
            } elseif ( strpos( $clean_key, 'background_image' ) === 0 && is_array( $value ) ) {
                $att_id  = absint( $value['id'] ?? 0 );
                $raw_url = esc_url_raw( (string) ( $value['url'] ?? '' ) );
                if ( $att_id > 0 ) {
                    $att_post = get_post( $att_id );
                    if ( ! $att_post || 'trash' === $att_post->post_status ) {
                        $att_id  = 0;
                        $raw_url = '';
                    } else {
                        $fresh_url = wp_get_attachment_url( $att_id );
                        if ( $fresh_url ) {
                            $raw_url = $fresh_url;
                        }
                    }
                }
                $sanitized[ $clean_key ] = array(
                    'id'  => $att_id,
                    'url' => $raw_url,
                );
            } elseif ( is_array( $value ) ) {
                $sanitized[ $clean_key ] = $this->sanitize_settings( $value );
            } elseif ( is_bool( $value ) ) {
                $sanitized[ $clean_key ] = $value;
            } elseif ( is_numeric( $value ) ) {
                $sanitized[ $clean_key ] = $value;
            } else {
                // Custom JS is executable code: administrators only, never from other roles.
                if ( 'custom_js' === $clean_key ) {
                    // Administrators only. Editors have unfiltered_html on single sites, so that
                    // capability is not strict enough for executable JavaScript.
                    if ( ! current_user_can( 'manage_options' ) ) {
                        continue;
                    }
                    $sanitized[ $clean_key ] = (string) $value;
                    continue;
                }

                if ( 'custom_css' === $clean_key ) {
                    // Strip style and script tags but preserve CSS code with newlines
                    $clean_css = preg_replace( '/<\/?(style|script)[^>]*>/i', '', (string) $value );
                    $sanitized[ $clean_key ] = $clean_css;
                } elseif ( isset( self::multiline_setting_names()[ $clean_key ] ) ) {
                    // Multi-line fields: sanitize_text_field() would flatten the line
                    // breaks. The set of names comes from the widgets' own declared
                    // control types, so this cannot fall out of step with them.
                    $sanitized[ $clean_key ] = sanitize_textarea_field( (string) $value );
                } elseif ( 'html' === $clean_key ) {
                    if ( current_user_can( 'unfiltered_html' ) ) {
                        $sanitized[ $clean_key ] = (string) $value;
                    } else {
                        // wp_kses() mangles the {{lk:name:arg}} token syntax, so Dynamic Tags
                        // were destroyed on save for anyone without unfiltered_html. Hide the
                        // tokens while kses runs, then put them back.
                        $sanitized[ $clean_key ] = self::kses_keeping_dynamic_tags( (string) $value );
                    }
                } elseif ( 'editor' === $clean_key ) {
                    // The Text Editor widget's wysiwyg control. sanitize_text_field() strips
                    // every tag and glues the paragraphs together, so saved layouts lost all
                    // their formatting.
                    $sanitized[ $clean_key ] = wp_kses_post( (string) $value );
                } elseif ( in_array( $clean_key, array( 'title', 'content' ), true ) ) {
                    $sanitized[ $clean_key ] = wp_kses_post( (string) $value );
                } elseif ( 'items' === $clean_key || false !== strpos( $clean_key, '_lines' ) ) {
                    // Fields that hold one entry per line, inside repeater rows as well.
                    $sanitized[ $clean_key ] = sanitize_textarea_field( (string) $value );
                } else {
                    $sanitized[ $clean_key ] = sanitize_text_field( (string) $value );
                }
            }
        }
        return $sanitized;
    }

    /**
     * Generate responsive CSS from elements and save to file or meta (Section 3).
     *
     * @param int   $post_id  Post ID.
     * @param array $elements Elements array.
     */
    /**
     * Rebuild a post's stylesheet file on demand.
     *
     * The per-post CSS is cached as uploads/loopkit/css/post-NNN.css and is only
     * written when a document is saved. If that file goes missing -- a migration,
     * an uploads cleanup, a host wiping the folder, a staging copy taken without
     * uploads -- the front end had nothing to enqueue and the page rendered
     * completely unstyled, with no way back short of re-saving every page by
     * hand. This regenerates it from the stored layout, so the page recovers
     * itself the first time it is visited.
     *
     * @param int $post_id Post ID.
     * @return bool Whether the CSS file exists afterwards.
     */
    public static function rebuild_css_file( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id ) {
            return false;
        }

        $raw      = get_post_meta( $post_id, '_loopkit_data', true );
        $elements = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();
        if ( ! is_array( $elements ) ) {
            $elements = array();
        }

        $self = new self();
        $self->generate_css( $post_id, $elements );

        /*
         * Templates are read from the _loopkit_css META first and only fall back to
         * the file (class-loopkit-theme-builder). A post is the other way round. So a
         * template whose file went missing also needs its meta restored, or the
         * theme builder finds neither and prints no CSS at all. Write whichever is
         * missing, from whichever survives.
         */
        $upload_dir = wp_upload_dir();
        $css_file   = trailingslashit( $upload_dir['basedir'] ) . 'loopkit/css/post-' . $post_id . '.css';

        if ( ! file_exists( $css_file ) && empty( get_post_meta( $post_id, '_loopkit_css', true ) ) ) {
            return false;
        }

        return file_exists( $css_file );
    }

    private function generate_css( $post_id, $elements ) {
        $flat_elements = array();
        $this->flatten_elements( $elements, $flat_elements );

        $wrapper_id = '.loopkit-' . $post_id;

        // 1. Desktop rules (base)
        $desktop_css = '';
        foreach ( $flat_elements as $el ) {
            $id = $el['id'] ?? '';
            if ( empty( $id ) ) continue;

            $el_cls         = '.lk-element-' . esc_attr( $id );
            $selector       = $wrapper_id . ' ' . $el_cls . ', ' . $el_cls;
            $inner_selector = $wrapper_id . ' ' . $el_cls . ' > .lk-section-inner, ' . $el_cls . ' > .lk-section-inner, ' .
                              $wrapper_id . ' ' . $el_cls . ' > .lk-con-inner, ' . $el_cls . ' > .lk-con-inner, ' .
                              $wrapper_id . ' ' . $el_cls . ' > .lk-container-inner, ' . $el_cls . ' > .lk-container-inner';

            $d_res = $this->build_device_rules( $el, 'desktop' );
            if ( ! empty( $d_res['base'] ) ) {
                $desktop_css .= $selector . ' {' . implode( ';', $d_res['base'] ) . ";}\n";
            }
            if ( ! empty( $d_res['inner'] ) ) {
                $desktop_css .= $inner_selector . ' {' . implode( ';', $d_res['inner'] ) . ";}\n";
            }
            if ( ! empty( $d_res['hover'] ) ) {
                $hover_selector = ( $wrapper_id ? $wrapper_id . ' ' . $el_cls . ':hover, ' : '' ) . $el_cls . ':hover';
                $desktop_css .= $hover_selector . ' {' . implode( ';', $d_res['hover'] ) . ";}\n";
            }
            if ( ! empty( $d_res['parent'] ) ) {
                $desktop_css .= ':has(>' . $el_cls . ') {' . implode( ';', $d_res['parent'] ) . ";}\n";
            }
            if ( ! empty( $d_res['hover_parent'] ) ) {
                $desktop_css .= ':has(>' . $el_cls . ':hover) {' . implode( ';', $d_res['hover_parent'] ) . ";}\n";
            }
            if ( ! empty( $d_res['overlay'] ) ) {
                $desktop_css .= $wrapper_id . ' ' . $el_cls . ' > .lk-background-overlay, ' . $el_cls . ' > .lk-background-overlay {' . implode( ';', $d_res['overlay'] ) . ";}\n";
            }
            if ( ! empty( $d_res['overlay_hover'] ) ) {
                $desktop_css .= $wrapper_id . ' ' . $el_cls . ':hover > .lk-background-overlay, ' . $el_cls . ':hover > .lk-background-overlay {' . implode( ';', $d_res['overlay_hover'] ) . ";}\n";
            }
            if ( ! empty( $d_res['divider_top'] ) ) {
                $desktop_css .= $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--top, ' . $el_cls . ' > .lk-shape-divider--top {' . implode( ';', $d_res['divider_top'] ) . ";}\n";
            }
            if ( ! empty( $d_res['divider_top_svg'] ) ) {
                $desktop_css .= $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--top svg, ' . $el_cls . ' > .lk-shape-divider--top svg {' . implode( ';', $d_res['divider_top_svg'] ) . ";}\n";
            }
            if ( ! empty( $d_res['divider_bottom'] ) ) {
                $desktop_css .= $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--bottom, ' . $el_cls . ' > .lk-shape-divider--bottom {' . implode( ';', $d_res['divider_bottom'] ) . ";}\n";
            }
            if ( ! empty( $d_res['divider_bottom_svg'] ) ) {
                $desktop_css .= $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--bottom svg, ' . $el_cls . ' > .lk-shape-divider--bottom svg {' . implode( ';', $d_res['divider_bottom_svg'] ) . ";}\n";
            }
            if ( ! empty( $d_res['sub_rules'] ) ) {
                foreach ( $d_res['sub_rules'] as $sub_sel => $s_rules ) {
                    if ( ! empty( $s_rules ) ) {
                        $full_sub = ( $wrapper_id ? $wrapper_id . ' ' . $el_cls . $sub_sel . ', ' : '' ) . $el_cls . $sub_sel;
                        $desktop_css .= $full_sub . ' {' . implode( ';', $s_rules ) . ";}\n";
                    }
                }
            }
        }

        $full_css = $desktop_css;

        // 2. Responsive Breakpoints: Output order per Section 3:
        // Widescreen (min-width), then max-width queries from largest to smallest:
        // Laptop (1366px), Tablet Landscape (1200px), Tablet Portrait (1024px), Mobile Landscape (880px), Mobile Portrait (767px)
        $breakpoints = array(
            array( 'id' => 'widescreen',   'query' => '(min-width: 2400px)' ),
            array( 'id' => 'laptop',       'query' => '(max-width: 1366px)' ),
            array( 'id' => 'tablet_extra', 'query' => '(max-width: 1200px)' ),
            array( 'id' => 'tablet',       'query' => '(max-width: 1024px)' ),
            array( 'id' => 'mobile_extra', 'query' => '(max-width: 880px)' ),
            array( 'id' => 'mobile',       'query' => '(max-width: 767px)' ),
        );

        foreach ( $breakpoints as $bp ) {
            $dev_id  = $bp['id'];
            $dev_css = '';

            foreach ( $flat_elements as $el ) {
                $id = $el['id'] ?? '';
                if ( empty( $id ) ) continue;

                $el_cls         = '.lk-element-' . esc_attr( $id );
                $selector       = $wrapper_id . ' ' . $el_cls . ', ' . $el_cls;
                $inner_selector = $wrapper_id . ' ' . $el_cls . ' > .lk-section-inner, ' . $el_cls . ' > .lk-section-inner, ' .
                                  $wrapper_id . ' ' . $el_cls . ' > .lk-con-inner, ' . $el_cls . ' > .lk-con-inner, ' .
                                  $wrapper_id . ' ' . $el_cls . ' > .lk-container-inner, ' . $el_cls . ' > .lk-container-inner';

                $res = $this->build_device_rules( $el, $dev_id );
                if ( ! empty( $res['base'] ) ) {
                    $dev_css .= '  ' . $selector . ' {' . implode( ';', $res['base'] ) . ";}\n";
                }
                if ( ! empty( $res['inner'] ) ) {
                    $dev_css .= '  ' . $inner_selector . ' {' . implode( ';', $res['inner'] ) . ";}\n";
                }
                if ( ! empty( $res['hover'] ) ) {
                    $b_hover_selector = ( $wrapper_id ? $wrapper_id . ' ' . $el_cls . ':hover, ' : '' ) . $el_cls . ':hover';
                    $dev_css .= '  ' . $b_hover_selector . ' {' . implode( ';', $res['hover'] ) . ";}\n";
                }
                if ( ! empty( $res['parent'] ) ) {
                    $dev_css .= '  :has(>' . $el_cls . ') {' . implode( ';', $res['parent'] ) . ";}\n";
                }
                if ( ! empty( $res['hover_parent'] ) ) {
                    $dev_css .= '  :has(>' . $el_cls . ':hover) {' . implode( ';', $res['hover_parent'] ) . ";}\n";
                }
                if ( ! empty( $res['overlay'] ) ) {
                    $dev_css .= '  ' . $wrapper_id . ' ' . $el_cls . ' > .lk-background-overlay, ' . $el_cls . ' > .lk-background-overlay {' . implode( ';', $res['overlay'] ) . ";}\n";
                }
                if ( ! empty( $res['overlay_hover'] ) ) {
                    $dev_css .= '  ' . $wrapper_id . ' ' . $el_cls . ':hover > .lk-background-overlay, ' . $el_cls . ':hover > .lk-background-overlay {' . implode( ';', $res['overlay_hover'] ) . ";}\n";
                }
                if ( ! empty( $res['divider_top'] ) ) {
                    $dev_css .= '  ' . $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--top, ' . $el_cls . ' > .lk-shape-divider--top {' . implode( ';', $res['divider_top'] ) . ";}\n";
                }
                if ( ! empty( $res['divider_top_svg'] ) ) {
                    $dev_css .= '  ' . $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--top svg, ' . $el_cls . ' > .lk-shape-divider--top svg {' . implode( ';', $res['divider_top_svg'] ) . ";}\n";
                }
                if ( ! empty( $res['divider_bottom'] ) ) {
                    $dev_css .= '  ' . $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--bottom, ' . $el_cls . ' > .lk-shape-divider--bottom {' . implode( ';', $res['divider_bottom'] ) . ";}\n";
                }
                if ( ! empty( $res['divider_bottom_svg'] ) ) {
                    $dev_css .= '  ' . $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--bottom svg, ' . $el_cls . ' > .lk-shape-divider--bottom svg {' . implode( ';', $res['divider_bottom_svg'] ) . ";}\n";
                }
                if ( ! empty( $res['sub_rules'] ) ) {
                    foreach ( $res['sub_rules'] as $sub_sel => $s_rules ) {
                        if ( ! empty( $s_rules ) ) {
                            $full_sub = ( $wrapper_id ? $wrapper_id . ' ' . $el_cls . $sub_sel . ', ' : '' ) . $el_cls . $sub_sel;
                            $dev_css .= '  ' . $full_sub . ' {' . implode( ';', $s_rules ) . ";}\n";
                        }
                    }
                }
            }

            if ( ! empty( $dev_css ) ) {
                $full_css .= "\n@media " . $bp['query'] . " {\n" . $dev_css . "}\n";
            }
        }

        // 3. Element Custom CSS (Problem 4 & Section 11)
        // Matches {{WRAPPER}} and "selector" with .loopkit-{post_id} .lk-element-{id}
        // Also supports {{CURRENT_ITEM}} for repeater items
        $custom_css_output = '';
        foreach ( $flat_elements as $el ) {
            $wrapper_selector = '.loopkit-' . $post_id . ' .lk-element-' . $el['id'];
            if ( ! empty( $el['settings']['custom_css'] ) ) {
                $raw_custom   = $el['settings']['custom_css'];
                $clean_custom = preg_replace( '/<\/?(style|script)[^>]*>/i', '', (string) $raw_custom );
                $compiled     = preg_replace( '/\bselector\b|\{\{WRAPPER\}\}/i', $wrapper_selector, $clean_custom );
                if ( ! empty( $el['_id'] ) ) {
                    $compiled = str_replace( '{{CURRENT_ITEM}}', '.lk-repeater-item-' . sanitize_html_class( $el['_id'] ), $compiled );
                }
                if ( trim( $compiled ) ) {
                    $custom_css_output .= "/* Custom CSS: " . $el['id'] . " */\n" . $compiled . "\n";
                }
            }
            if ( ! empty( $el['settings'] ) && is_array( $el['settings'] ) ) {
                foreach ( $el['settings'] as $field_val ) {
                    if ( is_array( $field_val ) ) {
                        foreach ( $field_val as $sub_item ) {
                            if ( is_array( $sub_item ) && ! empty( $sub_item['_id'] ) && ! empty( $sub_item['custom_css'] ) ) {
                                $item_raw  = preg_replace( '/<\/?(style|script)[^>]*>/i', '', (string) $sub_item['custom_css'] );
                                $item_comp = preg_replace( '/\bselector\b|\{\{WRAPPER\}\}/i', $wrapper_selector, $item_raw );
                                $item_comp = str_replace( '{{CURRENT_ITEM}}', '.lk-repeater-item-' . sanitize_html_class( $sub_item['_id'] ), $item_comp );
                                if ( trim( $item_comp ) ) {
                                    $custom_css_output .= "/* Repeater Custom CSS: " . sanitize_text_field( $sub_item['_id'] ) . " */\n" . $item_comp . "\n";
                                }
                            }
                        }
                    }
                }
            }
        }

        if ( ! empty( $custom_css_output ) ) {
            $full_css .= "\n/* Element Custom CSS */\n" . $custom_css_output;
        }

        if ( class_exists( 'Loopkit_Assets' ) && Loopkit_Assets::is_optimization_enabled() ) {
            $full_css = Loopkit_Assets::minify_css( $full_css );
        }

        $advanced = get_option( 'loopkit_advanced_settings', array() );
        $method   = $advanced['css_print_method'] ?? 'external';

        if ( 'external' === $method && ! empty( $full_css ) ) {
            $upload_dir = wp_upload_dir();
            if ( empty( $upload_dir['error'] ) ) {
                $css_dir = trailingslashit( $upload_dir['basedir'] ) . 'loopkit/css/';

                if ( ! is_dir( $css_dir ) ) {
                    wp_mkdir_p( $css_dir );
                }

                if ( is_dir( $css_dir ) && wp_is_writable( $css_dir ) ) {
                    $css_file = $css_dir . 'post-' . $post_id . '.css';
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                    @file_put_contents( $css_file, $full_css );
                }
            }
        }

        // Always store in post meta as fallback and for inline CSS print method
        update_post_meta( $post_id, '_loopkit_css', $full_css );

        // Update timestamp for cache busting query string
        update_post_meta( $post_id, '_loopkit_css_time', time() );

        return $full_css;
    }

    /**
     * Flatten elements array into a flat list.
     *
     * @param array $elements Input elements.
     * @param array $list     Accumulator (by reference).
     */
    private function flatten_elements( $elements, &$list ) {
        if ( ! is_array( $elements ) ) return;
        foreach ( $elements as $el ) {
            // Skip global instances â€” their CSS comes from the global template
            if ( ( $el['elType'] ?? '' ) === 'global' ) {
                continue;
            }
            $list[] = $el;
            if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
                $this->flatten_elements( $el['elements'], $list );
            }
        }
    }

    /**
     * Helper to get explicit device value.
     */
    private function get_device_val( $settings, $key, $device ) {
        if ( ! is_array( $settings ) ) return null;
        $store_key = ( 'desktop' === $device ) ? $key : $key . '_' . $device;
        if ( ! isset( $settings[ $store_key ] ) || '' === $settings[ $store_key ] ) {
            return null;
        }
        return $settings[ $store_key ];
    }

    /**
     * Helper to resolve global references into CSS variables.
     */
    private function resolve_global( $val ) {
        if ( ! is_string( $val ) ) return $val;
        if ( false !== strpos( $val, 'globals/colors?id=' ) ) {
            $parts = explode( 'id=', $val );
            return 'var(--lk-global-color-' . sanitize_key( $parts[1] ) . ')';
        }
        if ( false !== strpos( $val, 'globals/typography?id=' ) ) {
            $parts = explode( 'id=', $val );
            return 'var(--lk-global-typography-' . sanitize_key( $parts[1] ) . ')';
        }
        return $val;
    }

    /**
     * Build background CSS rules (Classic & Gradient, Normal & Hover).
     *
     * @param array  $settings Settings.
     * @param string $prefix   Key prefix (e.g. '' or 'background_overlay_').
     * @param bool   $is_hover Whether hover state.
     * @param string $device   Device ID.
     * @return array CSS rules.
     */
    private function build_background_rules( $settings, $prefix, $is_hover, $device ) {
        $rules = array();

        $get_key = function( $base ) use ( $prefix, $is_hover, $settings, $device ) {
            if ( 'background_overlay_' === $prefix ) {
                $suffix = preg_replace( '/^background_/', '', $base );
                $key1   = $is_hover ? ( 'background_overlay_' . $suffix . '_hover' ) : ( 'background_overlay_' . $suffix );
                $key1_alt = $is_hover ? ( 'background_overlay_hover_' . $suffix ) : $key1;
                $key2   = $is_hover ? ( 'background_overlay_background_' . $suffix . '_hover' ) : ( 'background_overlay_background_' . $suffix );
                $key2_alt = $is_hover ? ( 'background_overlay_hover_background_' . $suffix ) : $key2;
                if ( isset( $settings[ $key1 ] ) || isset( $settings[ $key1 . '_' . $device ] ) ) {
                    return $key1;
                }
                if ( isset( $settings[ $key1_alt ] ) || isset( $settings[ $key1_alt . '_' . $device ] ) ) {
                    return $key1_alt;
                }
                if ( isset( $settings[ $key2 ] ) || isset( $settings[ $key2 . '_' . $device ] ) ) {
                    return $key2;
                }
                if ( isset( $settings[ $key2_alt ] ) || isset( $settings[ $key2_alt . '_' . $device ] ) ) {
                    return $key2_alt;
                }
                return $key1;
            }
            if ( ! $is_hover ) {
                return $prefix . $base;
            }
            $key_suff = $prefix . $base . '_hover';
            $infix_base = preg_replace( '/^([a-z0-9]+)_/', '$1_hover_', $base );
            $key_infix = $prefix . $infix_base;
            if ( isset( $settings[ $key_suff ] ) || isset( $settings[ $key_suff . '_' . $device ] ) ) {
                return $key_suff;
            }
            if ( isset( $settings[ $key_infix ] ) || isset( $settings[ $key_infix . '_' . $device ] ) ) {
                return $key_infix;
            }
            return $key_suff;
        };

        $type_key = $get_key( 'background_type' );
        $bg_type  = $this->get_device_val( $settings, $type_key, $device );
        if ( ! $bg_type ) {
            $alt_key = $get_key( 'background_background' );
            $bg_type = $this->get_device_val( $settings, $alt_key, $device );
        }
        if ( ! $bg_type ) {
            $g_col1_check = $this->get_device_val( $settings, $get_key( 'background_gradient_color' ), $device );
            $g_col2_check = $this->get_device_val( $settings, $get_key( 'background_gradient_second_color' ), $device );
            if ( $g_col1_check || $g_col2_check ) {
                $bg_type = 'gradient';
            } else {
                $bg_type = 'classic';
            }
        }

        if ( 'gradient' === $bg_type ) {
            $col1_key   = $get_key( 'background_gradient_color' );
            $loc1_key   = $get_key( 'background_gradient_location' );
            $col2_key   = $get_key( 'background_gradient_second_color' );
            $loc2_key   = $get_key( 'background_gradient_second_location' );
            $g_type_key = $get_key( 'background_gradient_type' );

            $g_col1 = $this->get_device_val( $settings, $col1_key, $device ) ?: '#38A8F5';
            $g_loc1 = $this->get_device_val( $settings, $loc1_key, $device );
            if ( null === $g_loc1 || '' === $g_loc1 ) $g_loc1 = 0;

            $g_col2 = $this->get_device_val( $settings, $col2_key, $device ) ?: '#9B51E0';
            $g_loc2 = $this->get_device_val( $settings, $loc2_key, $device );
            if ( null === $g_loc2 || '' === $g_loc2 ) $g_loc2 = 100;

            $g_type = $this->get_device_val( $settings, $g_type_key, $device ) ?: 'linear';

            if ( 'radial' === $g_type ) {
                $pos_key = $get_key( 'background_gradient_position' );
                $g_pos = $this->get_device_val( $settings, $pos_key, $device ) ?: 'center center';
                $rules[] = 'background-image:radial-gradient(at ' . esc_attr( $g_pos ) . ',' . esc_attr( $this->resolve_global( $g_col1 ) ) . ' ' . floatval( $g_loc1 ) . '%,' . esc_attr( $this->resolve_global( $g_col2 ) ) . ' ' . floatval( $g_loc2 ) . '%)';
            } else {
                $ang_key = $get_key( 'background_gradient_angle' );
                $g_angle = $this->get_device_val( $settings, $ang_key, $device );
                if ( null === $g_angle || '' === $g_angle ) $g_angle = 180;
                $rules[] = 'background-image:linear-gradient(' . floatval( $g_angle ) . 'deg,' . esc_attr( $this->resolve_global( $g_col1 ) ) . ' ' . floatval( $g_loc1 ) . '%,' . esc_attr( $this->resolve_global( $g_col2 ) ) . ' ' . floatval( $g_loc2 ) . '%)';
            }
        } elseif ( 'classic' === $bg_type || empty( $bg_type ) ) {
            $col_key = $get_key( 'background_color' );
            $col     = $this->get_device_val( $settings, $col_key, $device );
            if ( $col ) {
                $rules[] = 'background-color:' . esc_attr( $this->resolve_global( $col ) );
            }

            $img_key = $get_key( 'background_image' );
            $b_img   = $this->get_device_val( $settings, $img_key, $device );
            $b_url   = '';
            if ( is_array( $b_img ) ) {
                $att_id = absint( $b_img['id'] ?? 0 );
                if ( $att_id > 0 && get_post( $att_id ) ) {
                    $b_url = wp_get_attachment_url( $att_id ) ?: ( $b_img['url'] ?? '' );
                } else {
                    $b_url = $b_img['url'] ?? '';
                }
            } elseif ( is_string( $b_img ) ) {
                $b_url = $b_img;
            }

            if ( ! empty( $b_url ) ) {
                $rules[] = 'background-image:url("' . esc_url_raw( $b_url ) . '")';

                $pos_key = $get_key( 'background_position' );
                $b_pos   = $this->get_device_val( $settings, $pos_key, $device );
                if ( 'custom' === $b_pos ) {
                    $x_key = $get_key( 'background_x_position' );
                    $y_key = $get_key( 'background_y_position' );
                    $bx    = $this->get_device_val( $settings, $x_key, $device );
                    $by    = $this->get_device_val( $settings, $y_key, $device );
                    $xs    = '0%';
                    $ys    = '0%';
                    if ( is_array( $bx ) && isset( $bx['size'] ) && '' !== $bx['size'] ) $xs = floatval( $bx['size'] ) . ( $bx['unit'] ?? 'px' );
                    elseif ( is_numeric( $bx ) ) $xs = floatval( $bx ) . 'px';
                    if ( is_array( $by ) && isset( $by['size'] ) && '' !== $by['size'] ) $ys = floatval( $by['size'] ) . ( $by['unit'] ?? 'px' );
                    elseif ( is_numeric( $by ) ) $ys = floatval( $by ) . 'px';
                    $rules[] = 'background-position:' . esc_attr( $xs . ' ' . $ys );
                } elseif ( ! empty( $b_pos ) && 'default' !== $b_pos ) {
                    $rules[] = 'background-position:' . esc_attr( $b_pos );
                }

                $attach_key = $get_key( 'background_attachment' );
                $b_attach   = $this->get_device_val( $settings, $attach_key, $device );
                if ( 'desktop' === $device ) {
                    if ( ! empty( $b_attach ) && 'default' !== $b_attach ) $rules[] = 'background-attachment:' . esc_attr( $b_attach );
                } else {
                    $desk_att = $settings[ $attach_key ] ?? '';
                    if ( 'fixed' === $desk_att || 'fixed' === $b_attach ) $rules[] = 'background-attachment:scroll';
                    elseif ( ! empty( $b_attach ) && 'default' !== $b_attach ) $rules[] = 'background-attachment:' . esc_attr( $b_attach );
                }

                $rep_key = $get_key( 'background_repeat' );
                $b_rep   = $this->get_device_val( $settings, $rep_key, $device );
                if ( ! empty( $b_rep ) && 'default' !== $b_rep ) $rules[] = 'background-repeat:' . esc_attr( $b_rep );

                $size_key = $get_key( 'background_size' );
                $b_size   = $this->get_device_val( $settings, $size_key, $device );
                if ( 'custom' === $b_size ) {
                    $cs_key  = $get_key( 'background_custom_size' );
                    $b_csize = $this->get_device_val( $settings, $cs_key, $device );
                    if ( is_array( $b_csize ) && isset( $b_csize['size'] ) && '' !== $b_csize['size'] ) {
                        $rules[] = 'background-size:' . floatval( $b_csize['size'] ) . ( $b_csize['unit'] ?? 'px' ) . ' auto';
                    } elseif ( is_numeric( $b_csize ) ) {
                        $rules[] = 'background-size:' . floatval( $b_csize ) . 'px auto';
                    }
                } elseif ( ! empty( $b_size ) && 'default' !== $b_size ) {
                    $rules[] = 'background-size:' . esc_attr( $b_size );
                }
            } elseif ( $is_hover && ! empty( $col ) ) {
                // Clear any background-image from normal (gradient or image) so solid hover color displays cleanly
                $rules[] = 'background-image:none';
            }
        }

        return $rules;
    }

    /**
     * Build border, radius and box-shadow CSS rules (Normal & Hover).
     *
     * @param array  $settings Settings.
     * @param bool   $is_hover Whether hover state.
     * @param string $device   Device ID.
     * @return array CSS rules.
     */
    private function build_border_rules( $settings, $is_hover, $device ) {
        $rules      = array();
        $type_key   = $is_hover ? 'border_type_hover' : 'border_type';
        $width_key  = $is_hover ? 'border_width_hover' : 'border_width';
        $color_key  = $is_hover ? 'border_color_hover' : 'border_color';
        $radius_key = $is_hover ? 'border_radius_hover' : 'border_radius';
        $shadow_key = $is_hover ? 'box_shadow_hover' : 'box_shadow';

        $b_type = $this->get_device_val( $settings, $type_key, $device );
        if ( ! $b_type && $is_hover ) {
            $b_type = $this->get_device_val( $settings, 'border_hover_type', $device );
        }
        if ( ! $b_type ) {
            $alt_key = $is_hover ? 'border_border_hover' : 'border_border';
            $b_type  = $this->get_device_val( $settings, $alt_key, $device );
        }
        if ( ! $b_type && $is_hover ) {
            $b_type = $this->get_device_val( $settings, 'border_hover_border', $device );
        }
        if ( ! $is_hover && ! $b_type ) {
            $legacy_border = $this->get_device_val( $settings, 'border', $device );
            if ( is_array( $legacy_border ) && ! empty( $legacy_border['type'] ) ) $b_type = $legacy_border['type'];
            elseif ( is_string( $legacy_border ) ) $b_type = $legacy_border;
        }

        if ( $b_type && 'none' !== $b_type && 'default' !== $b_type ) {
            $rules[] = 'border-style:' . esc_attr( $b_type );

            $b_col = $this->get_device_val( $settings, $color_key, $device );
            if ( ! $b_col && $is_hover ) {
                $b_col = $this->get_device_val( $settings, 'border_hover_color', $device );
            }
            if ( ! $is_hover && ! $b_col && ! empty( $settings['border']['color'] ) ) $b_col = $settings['border']['color'];
            if ( $b_col ) $rules[] = 'border-color:' . esc_attr( $this->resolve_global( $b_col ) );

            $b_width = $this->get_device_val( $settings, $width_key, $device );
            if ( ! $b_width && $is_hover ) {
                $b_width = $this->get_device_val( $settings, 'border_hover_width', $device );
            }
            if ( ! $is_hover && ! $b_width && ! empty( $settings['border']['width'] ) ) $b_width = $settings['border']['width'];
            if ( is_array( $b_width ) ) {
                $u    = $b_width['unit'] ?? 'px';
                $top  = isset( $b_width['top'] ) && '' !== $b_width['top'] ? floatval( $b_width['top'] ) . $u : '0';
                $rt   = isset( $b_width['right'] ) && '' !== $b_width['right'] ? floatval( $b_width['right'] ) . $u : '0';
                $btm  = isset( $b_width['bottom'] ) && '' !== $b_width['bottom'] ? floatval( $b_width['bottom'] ) . $u : '0';
                $lt   = isset( $b_width['left'] ) && '' !== $b_width['left'] ? floatval( $b_width['left'] ) . $u : '0';
                $rules[] = 'border-width:' . $top . ' ' . $rt . ' ' . $btm . ' ' . $lt;
            }
        }

        /*
         * Per-side border colour.
         *
         * border_width has always been a top/right/bottom/left box, but there was
         * only one colour for all four sides, so "a hairline under the header" meant
         * drawing the same hairline around the whole element. These per-side values
         * override border_color for that side only, and are emitted after it so they
         * win. Existing sites are untouched: with none of these set, nothing is
         * emitted and the single colour behaves exactly as before.
         */
        foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
            $side_key = $is_hover ? 'border_color_' . $side . '_hover' : 'border_color_' . $side;
            $side_val = $this->get_device_val( $settings, $side_key, $device );
            if ( ! $side_val && $is_hover ) {
                $side_val = $this->get_device_val( $settings, 'border_' . $side . '_color_hover', $device );
            }
            if ( $side_val && 'none' !== $side_val ) {
                $rules[] = 'border-' . $side . '-color:' . esc_attr( $this->resolve_global( $side_val ) );
            }
        }

        $b_radius = $this->get_device_val( $settings, $radius_key, $device );
        if ( ! $is_hover && ! $b_radius && ! empty( $settings['border']['radius'] ) ) $b_radius = $settings['border']['radius'];
        if ( is_array( $b_radius ) ) {
            $ru   = $b_radius['unit'] ?? 'px';
            $rtop = isset( $b_radius['top'] ) && '' !== $b_radius['top'] ? floatval( $b_radius['top'] ) . $ru : '0';
            $rrt  = isset( $b_radius['right'] ) && '' !== $b_radius['right'] ? floatval( $b_radius['right'] ) . $ru : '0';
            $rbtm = isset( $b_radius['bottom'] ) && '' !== $b_radius['bottom'] ? floatval( $b_radius['bottom'] ) . $ru : '0';
            $rlt  = isset( $b_radius['left'] ) && '' !== $b_radius['left'] ? floatval( $b_radius['left'] ) . $ru : '0';
            $rules[] = 'border-radius:' . $rtop . ' ' . $rrt . ' ' . $rbtm . ' ' . $rlt;
        }

        $b_shadow = $this->get_device_val( $settings, $shadow_key, $device );
        if ( is_array( $b_shadow ) && ! empty( $b_shadow['color'] ) ) {
            $bsh    = intval( $b_shadow['horizontal'] ?? 0 );
            $bsv    = intval( $b_shadow['vertical'] ?? 0 );
            $bsb    = intval( $b_shadow['blur'] ?? 0 );
            $bss    = intval( $b_shadow['spread'] ?? 0 );
            $binset = ( ( $b_shadow['position'] ?? '' ) === 'inset' ) ? 'inset ' : '';
            $rules[] = 'box-shadow:' . $binset . $bsh . 'px ' . $bsv . 'px ' . $bsb . 'px ' . $bss . 'px ' . esc_attr( $this->resolve_global( $b_shadow['color'] ) );
        }

        return $rules;
    }

    /**
     * POST WIDGETS (Part 5): declarative CSS rules.
     * KEEP IN SYNC: the identical table lives in editor/js/loopkit-css.js
     * (POST_WIDGET_CSS_RULES) and is applied by the identical applyDeclarativeRules().
     * Row: [ settingKey, kind, selectorSuffix, cssProperty, option ]
     *
     * @return array
     */
    private static function post_widget_css_rules() {
        $align_flex = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
        return array(
            'product-grid' => array(
                array( 'pgrid_columns', 'unitless', ' .lk-product-grid', '--lk-pgrid-columns' ),
                array( 'pgrid_gap', 'slider', ' .lk-product-grid', '--lk-pgrid-gap', 'px' ),
                array( 'pgrid_image_height', 'slider', ' .lk-product-grid', '--lk-pgrid-image-height', 'px' ),
                array( 'pgrid_card_bg', 'color', ' .lk-product-card', 'background-color' ),
                array( 'pgrid_card_border', 'color', ' .lk-product-card', 'border-color' ),
                array( 'pgrid_card_radius', 'dims', ' .lk-product-card', 'border-radius', 'px' ),
                array( 'pgrid_card_padding', 'dims', ' .lk-product-body', 'padding', 'px' ),
                array( 'pgrid_align', 'raw', ' .lk-product-body', 'text-align' ),
                array( 'pgrid_title_color', 'color', ' .lk-product-title', 'color' ),
                array( 'pgrid_title_typography', 'typography', ' .lk-product-title' ),
                array( 'pgrid_price_color', 'color', ' .lk-product-price', 'color' ),
                array( 'pgrid_price_typography', 'typography', ' .lk-product-price' ),
                array( 'pgrid_btn_bg', 'color', ' .lk-product-btn', 'background-color' ),
                array( 'pgrid_btn_color', 'color', ' .lk-product-btn', 'color' ),
                array( 'pgrid_btn_hover_bg', 'color', ' .lk-product-btn:hover', 'background-color' ),
                array( 'pgrid_badge_bg', 'color', ' .lk-product-badge', 'background-color' ),
                array( 'pgrid_btn_hover_color', 'color', ' .lk-product-btn:hover', 'color' ),
            ),
            'add-to-cart' => array(
                array( 'atc_gap', 'slider', ' .lk-atc', '--lk-atc-gap', 'px' ),
                array( 'atc_bg', 'color', ' .lk-atc-btn', 'background-color' ),
                array( 'atc_color', 'color', ' .lk-atc-btn', 'color' ),
                array( 'atc_hover_bg', 'color', ' .lk-atc-btn:hover', 'background-color' ),
                array( 'atc_padding', 'dims', ' .lk-atc-btn', 'padding', 'px' ),
                array( 'atc_radius', 'dims', ' .lk-atc-btn', 'border-radius', 'px' ),
                array( 'atc_typography', 'typography', ' .lk-atc-btn' ),
                array( 'atc_hover_color', 'color', ' .lk-atc-btn:hover', 'color' ),
            ),
            'product-price' => array(
                array( 'pprice_align', 'raw', ' .lk-pprice', 'justify-content' ),
                array( 'pprice_color', 'color', ' .lk-pprice-now', 'color' ),
                array( 'pprice_typography', 'typography', ' .lk-pprice-now' ),
                array( 'pprice_old_color', 'color', ' .lk-pprice-old', 'color' ),
                array( 'pprice_save_color', 'color', ' .lk-pprice-save', 'color' ),
            ),
            'product-gallery' => array(
                array( 'pgal_gap', 'slider', ' .lk-pgallery', '--lk-pgal-gap', 'px' ),
                array( 'pgal_main_height', 'slider', ' .lk-pgallery', '--lk-pgal-main-height', 'px' ),
                array( 'pgal_thumb_count', 'unitless', ' .lk-pgallery', '--lk-pgal-thumbs' ),
                array( 'pgal_radius', 'dims', ' .lk-pgallery-main img', 'border-radius', 'px' ),
            ),
            'cart-icon' => array(
                array( 'cicon_size', 'slider', ' .lk-cart-icon', 'font-size', 'px' ),
                array( 'cicon_color', 'color', ' .lk-cart-icon', 'color' ),
                array( 'cicon_badge_bg', 'color', ' .lk-cart-icon-count', 'background-color' ),
                array( 'cicon_badge_color', 'color', ' .lk-cart-icon-count', 'color' ),
                array( 'cicon_typography', 'typography', ' .lk-cart-icon' ),
            ),
            'cart-table' => array(
                array( 'carttable_color', 'color', ' .lk-cart-table', 'color' ),
                array( 'carttable_border', 'color', ' .lk-cart-table td', 'border-bottom-color' ),
                array( 'carttable_typography', 'typography', ' .lk-cart-table' ),
                array( 'carttable_btn_bg', 'color', ' .lk-btn-shop-primary', 'background-color' ),
                array( 'carttable_btn_color', 'color', ' .lk-btn-shop-primary', 'color' ),
                array( 'carttable_btn_hover_bg', 'color', ' .lk-btn-shop-primary:hover', 'background-color' ),
                array( 'carttable_btn_hover_color', 'color', ' .lk-btn-shop-primary:hover', 'color' ),
                array( 'carttable_btn_radius', 'dims', ' .lk-btn-shop-primary', 'border-radius', 'px' ),
                array( 'carttable_btn_typography', 'typography', ' .lk-btn-shop-primary' ),
            ),
            'team-member' => array(
                array( 'team_align', 'choose', ' .lk-team-body', 'text-align' ),
                array( 'team_card_bg', 'color', '', 'background-color' ),
                array( 'team_card_radius', 'dims', '', 'border-radius', 'px' ),
                array( 'team_card_padding', 'dims', '', 'padding', 'px' ),
                array( 'team_img_radius', 'dims', ' .lk-team-photo', 'border-radius', 'px' ),
                array( 'team_img_height', 'slider', ' .lk-team-photo img', 'height', 'px' ),
                array( 'team_name_color', 'color', ' .lk-team-name', 'color' ),
                array( 'team_name_typography', 'typography', ' .lk-team-name' ),
                array( 'team_role_color', 'color', ' .lk-team-role', 'color' ),
                array( 'team_role_typography', 'typography', ' .lk-team-role' ),
                array( 'team_bio_color', 'color', ' .lk-team-bio', 'color' ),
                array( 'team_social_color', 'color', ' .lk-team-social', 'color' ),
                array( 'team_social_hover', 'color', ' .lk-team-social:hover', 'color' ),
                array( 'team_social_size', 'slider', ' .lk-team-social', 'font-size', 'px' ),
            ),
            'testimonial-carousel' => array(
                array( 'tc_align', 'choose', ' .lk-tc-slide', 'text-align' ),
                array( 'tc_bg', 'color', ' .lk-tc-slide', 'background-color' ),
                array( 'tc_radius', 'dims', ' .lk-tc-slide', 'border-radius', 'px' ),
                array( 'tc_padding', 'dims', ' .lk-tc-slide', 'padding', 'px' ),
                array( 'tc_quote_color', 'color', ' .lk-tc-quote', 'color' ),
                array( 'tc_quote_typography', 'typography', ' .lk-tc-quote' ),
                array( 'tc_name_color', 'color', ' .lk-tc-name', 'color' ),
                array( 'tc_role_color', 'color', ' .lk-tc-role', 'color' ),
                array( 'tc_star_color', 'color', ' .lk-tc-stars', 'color' ),
                array( 'tc_dot_color', 'color', ' .lk-tc-dot', 'background-color' ),
                array( 'tc_dot_active', 'color', ' .lk-tc-dot.lk-active', 'background-color' ),
                array( 'tc_arrow_color', 'color', ' .lk-tc-arrow', 'color' ),
            ),
            'blockquote' => array(
                array( 'bq_align', 'choose', '', 'text-align' ),
                array( 'bq_color', 'color', ' .lk-bq-text', 'color' ),
                array( 'bq_typography', 'typography', ' .lk-bq-text' ),
                array( 'bq_author_color', 'color', ' .lk-bq-author', 'color' ),
                array( 'bq_author_typography', 'typography', ' .lk-bq-author' ),
                array( 'bq_accent', 'color', ' .lk-bq-border', 'border-left-color' ),
                array( 'bq_accent', 'color', ' .lk-bq-quote::before', 'color' ),
                array( 'bq_bg', 'color', ' .lk-bq', 'background-color' ),
                array( 'bq_padding', 'dims', ' .lk-bq', 'padding', 'px' ),
                array( 'bq_radius', 'dims', ' .lk-bq', 'border-radius', 'px' ),
            ),
            'business-hours' => array(
                array( 'bh_day_color', 'color', ' .lk-bh-day', 'color' ),
                array( 'bh_hours_color', 'color', ' .lk-bh-hours', 'color' ),
                array( 'bh_typography', 'typography', ' .lk-bh-row' ),
                array( 'bh_row_padding', 'dims', ' .lk-bh-row', 'padding', 'px' ),
                array( 'bh_divider', 'color', ' .lk-bh-row', 'border-bottom-color' ),
                array( 'bh_today_bg', 'color', ' .lk-bh-today', 'background-color' ),
                array( 'bh_today_color', 'color', ' .lk-bh-today .lk-bh-day', 'color' ),
                array( 'bh_closed_color', 'color', ' .lk-bh-shut .lk-bh-hours', 'color' ),
            ),
            'hotspot' => array(
                array( 'hs_marker_bg', 'color', ' .lk-hs-marker', 'background-color' ),
                array( 'hs_marker_color', 'color', ' .lk-hs-marker', 'color' ),
                array( 'hs_marker_size', 'slider', ' .lk-hs-marker', 'width', 'px' ),
                array( 'hs_marker_size', 'slider', ' .lk-hs-marker', 'height', 'px' ),
                array( 'hs_tip_bg', 'color', ' .lk-hs-tip', 'background-color' ),
                array( 'hs_tip_color', 'color', ' .lk-hs-tip', 'color' ),
                array( 'hs_tip_radius', 'dims', ' .lk-hs-tip', 'border-radius', 'px' ),
                array( 'hs_tip_width', 'slider', ' .lk-hs-tip', 'width', 'px' ),
                array( 'hs_img_radius', 'dims', ' .lk-hs-image', 'border-radius', 'px' ),
            ),
            'collection-list' => array(
                array( 'cl_columns', 'slider', ' .lk-cl--grid', 'grid-template-columns', 'repeat' ),
                array( 'cl_gap', 'slider', ' .lk-cl', 'gap', 'px' ),
                array( 'cl_card_bg', 'color', ' .lk-cl-card', 'background-color' ),
                array( 'cl_card_padding', 'dims', ' .lk-cl-card', 'padding', 'px' ),
                array( 'cl_card_radius', 'dims', ' .lk-cl-card', 'border-radius', 'px' ),
                array( 'cl_card_border_type', 'choose', ' .lk-cl-card', 'border-style' ),
                array( 'cl_card_border_width', 'dims', ' .lk-cl-card', 'border-width', 'px' ),
                array( 'cl_card_border_color', 'color', ' .lk-cl-card', 'border-color' ),
                array( 'cl_img_height', 'slider', ' .lk-cl-media img', 'height', 'px' ),
                array( 'cl_img_radius', 'dims', ' .lk-cl-media img', 'border-radius', 'px' ),
                array( 'cl_img_height', 'slider', ' .lk-cl-video video', 'height', 'px' ),
                array( 'cl_img_radius', 'dims', ' .lk-cl-video video', 'border-radius', 'px' ),
                array( 'cl_meta_color', 'color', ' .lk-cl-meta', 'color' ),
                array( 'cl_meta_typography', 'typography', ' .lk-cl-meta' ),
                array( 'cl_title_color', 'color', ' .lk-cl-title', 'color' ),
                array( 'cl_title_typography', 'typography', ' .lk-cl-title' ),
                array( 'cl_text_color', 'color', ' .lk-cl-text', 'color' ),
                array( 'cl_text_typography', 'typography', ' .lk-cl-text' ),
                array( 'cl_link_color', 'color', ' .lk-cl-link', 'color' ),
                array( 'cl_link_typography', 'typography', ' .lk-cl-link' ),
            ),
            'portfolio' => array(
                array( 'pf_columns', 'slider', ' .lk-pf-grid', 'grid-template-columns', 'repeat' ),
                array( 'pf_gap', 'slider', ' .lk-pf-grid', 'gap', 'px' ),
                array( 'pf_img_height', 'slider', ' .lk-pf-media img', 'height', 'px' ),
                array( 'pf_img_height', 'slider', ' .lk-pf-media-empty', 'height', 'px' ),
                array( 'pf_radius', 'dims', ' .lk-pf-item', 'border-radius', 'px' ),
                array( 'pf_title_color', 'color', ' .lk-pf-title', 'color' ),
                array( 'pf_title_typography', 'typography', ' .lk-pf-title' ),
                array( 'pf_term_color', 'color', ' .lk-pf-terms', 'color' ),
                array( 'pf_filter_color', 'color', ' .lk-pf-filter', 'color' ),
                array( 'pf_filter_active_bg', 'color', ' .lk-pf-filter.lk-active', 'background-color' ),
                array( 'pf_filter_active_color', 'color', ' .lk-pf-filter.lk-active', 'color' ),
                array( 'pf_overlay', 'color', ' .lk-pf-media::after', 'background-color' ),
            ),
            'login-form' => array(
                array( 'login_label_color', 'color', ' .lk-login-field span', 'color' ),
                array( 'login_field_bg', 'color', ' .lk-login-field input', 'background-color' ),
                array( 'login_field_color', 'color', ' .lk-login-field input', 'color' ),
                array( 'login_field_border', 'color', ' .lk-login-field input', 'border-color' ),
                array( 'login_field_radius', 'dims', ' .lk-login-field input', 'border-radius', 'px' ),
                array( 'login_btn_bg', 'color', ' .lk-login-btn', 'background-color' ),
                array( 'login_btn_color', 'color', ' .lk-login-btn', 'color' ),
                array( 'login_btn_hover_bg', 'color', ' .lk-login-btn:hover', 'background-color' ),
                array( 'login_btn_hover_color', 'color', ' .lk-login-btn:hover', 'color' ),
                array( 'login_btn_radius', 'dims', ' .lk-login-btn', 'border-radius', 'px' ),
                array( 'login_link_color', 'color', ' .lk-login-links a', 'color' ),
            ),
            'product-filters' => array(
                array( 'pfilters_gap', 'slider', ' .lk-pfilters-row', 'gap', 'px' ),
                array( 'pfilters_chip_bg', 'color', ' .lk-pfilter-chip', 'background-color' ),
                array( 'pfilters_chip_color', 'color', ' .lk-pfilter-chip', 'color' ),
                array( 'pfilters_chip_radius', 'dims', ' .lk-pfilter-chip', 'border-radius', 'px' ),
                array( 'pfilters_active_bg', 'color', ' .lk-pfilter-chip.lk-active', 'background-color' ),
                array( 'pfilters_active_color', 'color', ' .lk-pfilter-chip.lk-active', 'color' ),
                array( 'pfilters_label_color', 'color', ' .lk-pfilter-label', 'color' ),
            ),
            'mini-cart' => array(
                array( 'mc_icon_color', 'color', ' .lk-mc-toggle', 'color' ),
                array( 'mc_icon_size', 'slider', ' .lk-mc-toggle', 'font-size', 'px' ),
                array( 'mc_badge_bg', 'color', ' .lk-mc-count', 'background-color' ),
                array( 'mc_badge_color', 'color', ' .lk-mc-count', 'color' ),
                array( 'mc_panel_bg', 'color', ' .lk-mc-panel', 'background-color' ),
                array( 'mc_panel_color', 'color', ' .lk-mc-panel', 'color' ),
                array( 'mc_panel_width', 'slider', ' .lk-mc-panel', 'width', 'px' ),
                array( 'mc_panel_radius', 'dims', ' .lk-mc-panel', 'border-radius', 'px' ),
                array( 'mc_btn_bg', 'color', ' .lk-mc-btn-primary', 'background-color' ),
                array( 'mc_btn_color', 'color', ' .lk-mc-btn-primary', 'color' ),
            ),
            'product-rating' => array(
                array( 'prating_star_color', 'color', ' .lk-prating-star.lk-on', 'color' ),
                array( 'prating_empty_color', 'color', ' .lk-prating-star', 'color' ),
                array( 'prating_size', 'slider', ' .lk-prating-stars', 'font-size', 'px' ),
                array( 'prating_text_color', 'color', ' .lk-prating', 'color' ),
                array( 'prating_align', 'choose', '', 'text-align' ),
            ),
            'my-account' => array(
                array( 'ma_heading_color', 'color', ' .lk-account-heading', 'color' ),
                array( 'ma_heading_typography', 'typography', ' .lk-account-heading' ),
                array( 'ma_text_color', 'color', ' .lk-account', 'color' ),
                array( 'ma_row_border', 'color', ' .lk-account-table td', 'border-bottom-color' ),
                array( 'ma_btn_bg', 'color', ' .lk-btn-shop-primary', 'background-color' ),
                array( 'ma_btn_color', 'color', ' .lk-btn-shop-primary', 'color' ),
            ),
            'checkout-form' => array(
                array( 'checkout_heading_color', 'color', ' .lk-checkout-heading', 'color' ),
                array( 'checkout_heading_typography', 'typography', ' .lk-checkout-heading' ),
                array( 'checkout_label_color', 'color', ' .lk-checkout-grid label', 'color' ),
                array( 'checkout_label_typography', 'typography', ' .lk-checkout-grid label span' ),
                array( 'checkout_field_bg', 'color', ' .lk-checkout-grid input', 'background-color' ),
                array( 'checkout_field_bg', 'color', ' .lk-checkout-grid textarea', 'background-color' ),
                array( 'checkout_field_color', 'color', ' .lk-checkout-grid input', 'color' ),
                array( 'checkout_field_color', 'color', ' .lk-checkout-grid textarea', 'color' ),
                array( 'checkout_field_border', 'color', ' .lk-checkout-grid input', 'border-color' ),
                array( 'checkout_field_border', 'color', ' .lk-checkout-grid textarea', 'border-color' ),
                array( 'checkout_field_focus', 'color', ' .lk-checkout-grid input:focus', 'border-color' ),
                array( 'checkout_field_focus', 'color', ' .lk-checkout-grid textarea:focus', 'border-color' ),
                array( 'checkout_field_radius', 'dims', ' .lk-checkout-grid input', 'border-radius', 'px' ),
                array( 'checkout_field_radius', 'dims', ' .lk-checkout-grid textarea', 'border-radius', 'px' ),
                array( 'checkout_field_padding', 'dims', ' .lk-checkout-grid input', 'padding', 'px' ),
                array( 'checkout_field_padding', 'dims', ' .lk-checkout-grid textarea', 'padding', 'px' ),
                array( 'checkout_field_typography', 'typography', ' .lk-checkout-grid input' ),
                array( 'checkout_field_typography', 'typography', ' .lk-checkout-grid textarea' ),
                array( 'checkout_gap', 'slider', ' .lk-checkout-grid', 'gap', 'px' ),
                array( 'checkout_summary_bg', 'color', ' .lk-checkout-summary', 'background-color' ),
                array( 'checkout_summary_color', 'color', ' .lk-checkout-summary', 'color' ),
                array( 'checkout_summary_radius', 'dims', ' .lk-checkout-summary', 'border-radius', 'px' ),
                array( 'checkout_choice_border', 'color', ' .lk-checkout-choices label', 'border-color' ),
                array( 'checkout_btn_bg', 'color', ' .lk-place-order', 'background-color' ),
                array( 'checkout_btn_color', 'color', ' .lk-place-order', 'color' ),
                array( 'checkout_btn_hover_bg', 'color', ' .lk-place-order:hover', 'background-color' ),
                array( 'checkout_btn_radius', 'dims', ' .lk-place-order', 'border-radius', 'px' ),
                array( 'checkout_btn_typography', 'typography', ' .lk-place-order' ),
                array( 'checkout_btn_hover_color', 'color', ' .lk-place-order:hover', 'color' ),
                array( 'checkout_btn_bg', 'color', ' .lk-cart-empty-state .lk-btn-shop-primary', 'background-color' ),
                array( 'checkout_btn_color', 'color', ' .lk-cart-empty-state .lk-btn-shop-primary', 'color' ),
                array( 'checkout_btn_hover_bg', 'color', ' .lk-cart-empty-state .lk-btn-shop-primary:hover', 'background-color' ),
                array( 'checkout_btn_hover_color', 'color', ' .lk-cart-empty-state .lk-btn-shop-primary:hover', 'color' ),
                array( 'checkout_btn_radius', 'dims', ' .lk-cart-empty-state .lk-btn-shop-primary', 'border-radius', 'px' ),
                array( 'checkout_btn_typography', 'typography', ' .lk-cart-empty-state .lk-btn-shop-primary' ),
                array( 'checkout_btn_bg', 'color', ' .lk-btn-shop-primary', 'background-color' ),
                array( 'checkout_btn_color', 'color', ' .lk-btn-shop-primary', 'color' ),
                array( 'checkout_btn_hover_bg', 'color', ' .lk-btn-shop-primary:hover', 'background-color' ),
                array( 'checkout_btn_hover_color', 'color', ' .lk-btn-shop-primary:hover', 'color' ),
                array( 'checkout_btn_radius', 'dims', ' .lk-btn-shop-primary', 'border-radius', 'px' ),
                array( 'checkout_btn_typography', 'typography', ' .lk-btn-shop-primary' ),
            ),
            'product-categories' => array(
                array( 'pcats_columns', 'unitless', ' .lk-pcats', '--lk-pcats-columns' ),
                array( 'pcats_gap', 'slider', ' .lk-pcats', '--lk-pcats-gap', 'px' ),
                array( 'pcats_bg', 'color', ' .lk-pcat', 'background-color' ),
                array( 'pcats_color', 'color', ' .lk-pcat', 'color' ),
                array( 'pcats_padding', 'dims', ' .lk-pcat', 'padding', 'px' ),
                array( 'pcats_radius', 'dims', ' .lk-pcat', 'border-radius', 'px' ),
                array( 'pcats_typography', 'typography', ' .lk-pcat' ),
            ),
            'marquee' => array(
                array( 'mq_gap', 'slider', ' .lk-marquee', '--lk-marquee-gap', 'px' ),
                array( 'mq_row_gap', 'slider', ' .lk-marquee', '--lk-marquee-row-gap', 'px' ),
                array( 'mq_height', 'slider', ' .lk-marquee', '--lk-marquee-height', 'px' ),
                array( 'mq_fade_width', 'slider', ' .lk-marquee', '--lk-marquee-fade', 'px' ),
                array( 'mq_fade_color', 'color', ' .lk-marquee', '--lk-marquee-fade-color' ),
                array( 'mq_bg', 'color', ' .lk-marquee', 'background-color' ),
                array( 'mq_padding', 'dims', ' .lk-marquee', 'padding', 'px' ),
                array( 'mq_radius', 'dims', ' .lk-marquee', 'border-radius', 'px' ),
                array( 'mq_text_color', 'color', ' .lk-marquee-item', 'color' ),
                array( 'mq_hover_color', 'color', ' .lk-marquee-item:hover', 'color' ),
                array( 'mq_typography', 'typography', ' .lk-marquee-item' ),
                array( 'mq_stroke', 'text_stroke', ' .lk-marquee-text' ),
                array( 'mq_sep_color', 'color', ' .lk-marquee-sep', 'color' ),
                array( 'mq_icon_size', 'slider', ' .lk-marquee-icon', 'font-size', 'px' ),
                array( 'mq_image_height', 'slider', ' .lk-marquee-img', 'height', 'px' ),
                array( 'mq_image_radius', 'dims', ' .lk-marquee-img', 'border-radius', 'px' ),
                array( 'mq_image_opacity', 'unitless', ' .lk-marquee-img', 'opacity' ),
            ),
            'flip-box' => array(
                array( 'flip_align', 'raw', ' .lk-flip-face', 'text-align' ),
                array( 'flip_height', 'slider', ' .lk-flip-box', 'height', 'px' ),
                array( 'flip_padding', 'dims', ' .lk-flip-face', 'padding', 'px' ),
                array( 'flip_radius', 'dims', ' .lk-flip-face', 'border-radius', 'px' ),
                array( 'flip_front_bg', 'color', ' .lk-flip-front', 'background-color' ),
                array( 'flip_front_color', 'color', ' .lk-flip-front', 'color' ),
                array( 'flip_back_bg', 'color', ' .lk-flip-back', 'background-color' ),
                array( 'flip_back_color', 'color', ' .lk-flip-back', 'color' ),
                array( 'flip_icon_size', 'slider', ' .lk-flip-icon', 'font-size', 'px' ),
                array( 'flip_title_typography', 'typography', ' .lk-flip-title' ),
                array( 'flip_text_typography', 'typography', ' .lk-flip-text' ),
                array( 'flip_shadow', 'box_shadow', ' .lk-flip-face' ),
            ),
            'price-table' => array(
                array( 'price_bg', 'color', ' .lk-price-table', 'background-color' ),
                array( 'price_border_color', 'color', ' .lk-price-table', 'border-color' ),
                array( 'price_radius', 'dims', ' .lk-price-table', 'border-radius', 'px' ),
                array( 'price_inner_padding', 'dims', ' .lk-price-table', 'padding', 'px' ),
                array( 'price_heading_color', 'color', ' .lk-price-heading', 'color' ),
                array( 'price_heading_typography', 'typography', ' .lk-price-heading' ),
                array( 'price_value_color', 'color', ' .lk-price-amount', 'color' ),
                array( 'price_value_typography', 'typography', ' .lk-price-amount' ),
                array( 'price_features_color', 'color', ' .lk-price-features', 'color' ),
                array( 'price_features_typography', 'typography', ' .lk-price-features' ),
                array( 'price_button_bg', 'color', ' .lk-price-btn', 'background-color' ),
                array( 'price_button_color', 'color', ' .lk-price-btn', 'color' ),
                array( 'price_button_hover_bg', 'color', ' .lk-price-btn:hover', 'background-color' ),
                array( 'price_ribbon_bg', 'color', ' .lk-price-ribbon', 'background-color' ),
                array( 'price_ribbon_color', 'color', ' .lk-price-ribbon', 'color' ),
                array( 'price_shadow', 'box_shadow', ' .lk-price-table' ),
                array( 'price_button_hover_color', 'color', ' .lk-price-btn:hover', 'color' ),
            ),
            'countdown' => array(
                array( 'cd_align', 'raw', ' .lk-countdown-items', 'justify-content' ),
                array( 'cd_gap', 'slider', ' .lk-countdown-items', 'gap', 'px' ),
                array( 'cd_box_bg', 'color', ' .lk-countdown-item', 'background-color' ),
                array( 'cd_box_padding', 'dims', ' .lk-countdown-item', 'padding', 'px' ),
                array( 'cd_box_radius', 'dims', ' .lk-countdown-item', 'border-radius', 'px' ),
                array( 'cd_digit_color', 'color', ' .lk-countdown-digit', 'color' ),
                array( 'cd_digit_typography', 'typography', ' .lk-countdown-digit' ),
                array( 'cd_label_color', 'color', ' .lk-countdown-label', 'color' ),
                array( 'cd_label_typography', 'typography', ' .lk-countdown-label' ),
            ),
            'animated-headline' => array(
                array( 'ah_align', 'raw', ' .lk-animated-headline', 'text-align' ),
                array( 'ah_color', 'color', ' .lk-animated-headline', 'color' ),
                array( 'ah_typography', 'typography', ' .lk-animated-headline' ),
                array( 'ah_word_color', 'color', ' .lk-ah-word', 'color' ),
                array( 'ah_highlight_color', 'raw', ' .lk-ah-highlight .lk-ah-word', 'background-color' ),
            ),
            'call-to-action' => array(
                array( 'cta_min_height', 'slider', ' .lk-cta', 'min-height', 'px' ),
                array( 'cta_content_align', 'raw', ' .lk-cta-content', 'align-items' ),
                array( 'cta_content_align', 'raw', ' .lk-cta-content', 'text-align' ),
                array( 'cta_overlay_color', 'color', ' .lk-cta-overlay', 'background-color' ),
                array( 'cta_padding', 'dims', ' .lk-cta', 'padding', 'px' ),
                array( 'cta_radius', 'dims', ' .lk-cta', 'border-radius', 'px' ),
                array( 'cta_title_color', 'color', ' .lk-cta-title', 'color' ),
                array( 'cta_title_typography', 'typography', ' .lk-cta-title' ),
                array( 'cta_desc_color', 'color', ' .lk-cta-description', 'color' ),
                array( 'cta_desc_typography', 'typography', ' .lk-cta-description' ),
                array( 'cta_button_bg', 'color', ' .lk-cta-btn', 'background-color' ),
                array( 'cta_button_color', 'color', ' .lk-cta-btn', 'color' ),
                array( 'cta_button_hover_bg', 'color', ' .lk-cta-btn:hover', 'background-color' ),
                array( 'cta_button_radius', 'dims', ' .lk-cta-btn', 'border-radius', 'px' ),
                array( 'cta_button_hover_color', 'color', ' .lk-cta-btn:hover', 'color' ),
            ),
            'share-buttons' => array(
                array( 'share_align', 'raw', ' .lk-share-buttons', 'justify-content' ),
                array( 'share_gap', 'slider', ' .lk-share-buttons', 'gap', 'px' ),
                array( 'share_bg', 'color', ' .lk-share-btn', 'background-color' ),
                array( 'share_color', 'color', ' .lk-share-btn', 'color' ),
                array( 'share_hover_bg', 'color', ' .lk-share-btn:hover', 'background-color' ),
                array( 'share_hover_color', 'color', ' .lk-share-btn:hover', 'color' ),
                array( 'share_padding', 'dims', ' .lk-share-btn', 'padding', 'px' ),
                array( 'share_icon_size', 'slider', ' .lk-share-icon', 'font-size', 'px' ),
                array( 'share_typography', 'typography', ' .lk-share-btn' ),
            ),
            'icon' => array(
                array( 'primary_color', 'color', ' .lk-icon', 'color' ),
                array( 'primary_color', 'color', ' .lk-icon svg', 'fill' ),
                array( 'hover_primary_color', 'color', ':hover .lk-icon', 'color' ),
                array( 'hover_primary_color', 'color', ':hover .lk-icon svg', 'fill' ),
                array( 'secondary_color', 'color', ' .lk-icon-wrap', 'background-color' ),
                array( 'hover_secondary_color', 'color', ':hover .lk-icon-wrap', 'background-color' ),
                array( 'icon_size', 'slider', ' .lk-icon', 'font-size', 'px' ),
                array( 'icon_size', 'slider', ' .lk-icon svg', 'width', 'px' ),
                array( 'icon_size', 'slider', ' .lk-icon svg', 'height', 'px' ),
                array( 'icon_padding', 'slider', ' .lk-icon-wrap', 'padding', 'px' ),
                array( 'border_width', 'dims', ' .lk-icon-wrap', 'border-width', 'px' ),
                array( 'border_radius', 'dims', ' .lk-icon-wrap', 'border-radius', 'px' ),
                array( 'rotate', 'slider', ' .lk-icon', 'rotate', 'deg' ),
            ),
            'divider' => array(
                array( 'text_color', 'color', ' .lk-divider__text', 'color' ),
                array( 'text_typography', 'typography', ' .lk-divider__text' ),
                array( 'text_spacing', 'slider', ' .lk-divider__text', 'padding-inline', 'px' ),
                array( 'icon_primary_color', 'color', ' .lk-divider .lk-icon', 'color' ),
                array( 'icon_primary_color', 'color', ' .lk-divider .lk-icon svg', 'fill' ),
                array( 'icon_secondary_color', 'color', ' .lk-divider__icon', 'background-color' ),
                array( 'icon_size', 'slider', ' .lk-divider .lk-icon', 'font-size', 'px' ),
                array( 'icon_size', 'slider', ' .lk-divider .lk-icon svg', 'width', 'px' ),
                array( 'icon_size', 'slider', ' .lk-divider .lk-icon svg', 'height', 'px' ),
                array( 'icon_spacing', 'slider', ' .lk-divider__icon', 'padding-inline', 'px' ),
            ),
            'icon-list' => array(
                array( 'text_typography', 'typography', ' .lk-icon-list-text' ),
                array( 'text_hover_color', 'color', ' .lk-icon-list-item:hover .lk-icon-list-text', 'color' ),
                array( 'icon_hover_color', 'color', ' .lk-icon-list-item:hover .lk-icon-list-icon', 'color' ),
                array( 'icon_hover_color', 'color', ' .lk-icon-list-item:hover .lk-icon-list-icon svg', 'fill' ),
                array( 'divider_color', 'color', ' .lk-has-divider .lk-icon-list-item:not(:last-child)', 'border-color' ),
            ),
            'icon-box' => array(
                array( 'title_typography', 'typography', ' .lk-icon-box-title' ),
                array( 'title_space', 'slider', ' .lk-icon-box-title', 'margin-bottom', 'px' ),
                array( 'secondary_color', 'color', ' .lk-icon-box-icon .lk-icon-wrap', 'background-color' ),
                array( 'hover_primary_color', 'color', ':hover .lk-icon-box-icon .lk-icon', 'color' ),
                array( 'hover_primary_color', 'color', ':hover .lk-icon-box-icon .lk-icon svg', 'fill' ),
                array( 'hover_secondary_color', 'color', ':hover .lk-icon-box-icon .lk-icon-wrap', 'background-color' ),
                array( 'icon_border_width', 'dims', ' .lk-icon-box-icon .lk-icon-wrap', 'border-width', 'px' ),
                array( 'icon_border_radius', 'dims', ' .lk-icon-box-icon .lk-icon-wrap', 'border-radius', 'px' ),
                array( 'icon_space', 'slider', ' .lk-icon-box-icon', 'margin-bottom', 'px' ),
                array( 'description_typography', 'typography', ' .lk-icon-box-description' ),
                array( 'title_size', 'slider', ' .lk-icon-box-title', 'font-size', 'px' ),
            ),
            'image' => array(
                array( 'box_shadow', 'box_shadow', ' img' ),
                array( 'caption_text_shadow', 'text_shadow', ' .lk-image-caption' ),
                array( 'border_radius', 'dims', ' img', 'border-radius', 'px' ),
                array( 'caption_text_color', 'color', ' .lk-image-caption', 'color' ),
                array( 'caption_background_color', 'color', ' .lk-image-caption', 'background-color' ),
                array( 'caption_typography', 'typography', ' .lk-image-caption' ),
            ),
            'image-box' => array(
                array( 'title_typography', 'typography', ' .lk-image-box-title' ),
                array( 'title_size', 'slider', ' .lk-image-box-title', 'font-size', 'px' ),
                array( 'title_space', 'slider', ' .lk-image-box-title', 'margin-bottom', 'px' ),
                array( 'description_typography', 'typography', ' .lk-image-box-description' ),
                array( 'image_border_radius', 'dims', ' .lk-image-box-img img', 'border-radius', 'px' ),
            ),
            'site-logo' => array(
                array( 'box_shadow', 'box_shadow', ' .lk-site-logo-img' ),
                array( 'border_radius', 'dims', ' .lk-site-logo-img', 'border-radius', 'px' ),
            ),
            'social-icons' => array(
                array( 'primary_color', 'color', ' .lk-social-icon', 'background-color' ),
                array( 'secondary_color', 'color', ' .lk-social-icon', 'color' ),
                array( 'secondary_color', 'color', ' .lk-social-icon svg', 'fill' ),
                array( 'item_color', 'color', ' .lk-social-icon', 'background-color' ),
                array( 'item_primary_color', 'color', ' .lk-social-icon', 'background-color' ),
                array( 'item_secondary_color', 'color', ' .lk-social-icon', 'color' ),
                array( 'icon_size', 'slider', ' .lk-social-icon', 'font-size', 'px' ),
                array( 'icon_size', 'slider', ' .lk-social-icon svg', 'width', 'px' ),
                array( 'icon_size', 'slider', ' .lk-social-icon svg', 'height', 'px' ),
                array( 'icon_padding', 'slider', ' .lk-social-icon', 'padding', 'px' ),
                array( 'icon_spacing', 'slider', ' .lk-social-icons-wrapper', 'column-gap', 'px' ),
                array( 'rows_gap', 'slider', ' .lk-social-icons-wrapper', 'row-gap', 'px' ),
            ),
            'video' => array(
                array( 'play_icon_color', 'color', ' .lk-video-placeholder-play', 'color' ),
                array( 'play_icon_color', 'color', ' .lk-video-placeholder-play svg', 'fill' ),
                array( 'play_icon_size', 'slider', ' .lk-video-placeholder-play', 'font-size', 'px' ),
                array( 'play_icon_size', 'slider', ' .lk-video-placeholder-play svg', 'width', 'px' ),
                array( 'play_icon_size', 'slider', ' .lk-video-placeholder-play svg', 'height', 'px' ),
                array( 'play_icon_text_shadow', 'text_shadow', ' .lk-video-placeholder-play' ),
            ),
            'search-form' => array(
                array( 'button_box_shadow', 'box_shadow', ' .lk-search-form__submit' ),
                array( 'input_box_shadow', 'box_shadow', ' .lk-search-form__input' ),
                array( 'input_placeholder_color', 'color', ' .lk-search-form__input::placeholder', 'color' ),
                array( 'input_text_color', 'color', ' .lk-search-form__input', 'color' ),
                array( 'input_bg_color', 'color', ' .lk-search-form__input', 'background-color' ),
                array( 'input_typography', 'typography', ' .lk-search-form__input' ),
                array( 'input_padding', 'dims', ' .lk-search-form__input', 'padding', 'px' ),
                array( 'input_border_radius', 'dims', ' .lk-search-form__input', 'border-radius', 'px' ),
                array( 'button_text_color', 'color', ' .lk-search-form__submit', 'color' ),
                array( 'button_text_color_hover', 'color', ' .lk-search-form__submit:hover', 'color' ),
                array( 'button_bg_color', 'color', ' .lk-search-form__submit', 'background-color' ),
                array( 'button_bg_color_hover', 'color', ' .lk-search-form__submit:hover', 'background-color' ),
                array( 'button_typography', 'typography', ' .lk-search-form__submit' ),
                array( 'button_padding', 'dims', ' .lk-search-form__submit', 'padding', 'px' ),
                array( 'button_border_radius', 'dims', ' .lk-search-form__submit', 'border-radius', 'px' ),
                array( 'button_icon_size', 'slider', ' .lk-search-form__icon', 'font-size', 'px' ),
            ),
            'text-editor' => array(
                array( 'drop_cap_typography', 'typography', ' .lk-has-drop-cap::first-letter' ),
                array( 'drop_cap_primary_color', 'color', ' .lk-has-drop-cap::first-letter', 'color' ),
                array( 'drop_cap_secondary_color', 'color', ' .lk-has-drop-cap::first-letter', 'background-color' ),
                array( 'drop_cap_size', 'slider', ' .lk-has-drop-cap::first-letter', 'font-size', 'px' ),
                array( 'drop_cap_space', 'slider', ' .lk-has-drop-cap::first-letter', 'margin-right', 'px' ),
                array( 'drop_cap_border_radius', 'dims', ' .lk-has-drop-cap::first-letter', 'border-radius', 'px' ),
                array( 'drop_cap_border_width', 'dims', ' .lk-has-drop-cap::first-letter', 'border-width', 'px' ),
            ),
            'nav-menu' => array(
                array( 'dropdown_box_shadow', 'box_shadow', ' .lk-sub-menu' ),
                array( 'text_color', 'color', ' .lk-menu-link', 'color' ),
                array( 'text_color_hover', 'color', ' .lk-menu-link:hover', 'color' ),
                array( 'menu_bg', 'color', ' .lk-menu-link', 'background-color' ),
                array( 'menu_bg_hover', 'color', ' .lk-menu-link:hover', 'background-color' ),
                array( 'typography', 'typography', ' .lk-menu-link' ),
                array( 'item_padding', 'dims', ' .lk-menu-link', 'padding', 'px' ),
                array( 'border_radius', 'dims', ' .lk-menu-link', 'border-radius', 'px' ),
                array( 'item_gap', 'slider', ' .lk-nav-menu', 'gap', 'px' ),
                array( 'dropdown_bg', 'color', ' .lk-sub-menu', 'background-color' ),
                array( 'dropdown_text_color', 'color', ' .lk-sub-menu .lk-menu-link', 'color' ),
                array( 'dropdown_text_color_hover', 'color', ' .lk-sub-menu .lk-menu-link:hover', 'color' ),
                array( 'dropdown_item_bg_hover', 'color', ' .lk-sub-menu .lk-menu-link:hover', 'background-color' ),
                array( 'dropdown_typography', 'typography', ' .lk-sub-menu .lk-menu-link' ),
                array( 'dropdown_padding', 'dims', ' .lk-sub-menu .lk-menu-link', 'padding', 'px' ),
                array( 'dropdown_border_radius', 'dims', ' .lk-sub-menu', 'border-radius', 'px' ),
                array( 'dropdown_width', 'slider', ' .lk-sub-menu', 'width', 'px' ),
                array( 'hamburger_color', 'color', ' .lk-nav-menu-toggle', 'color' ),
                array( 'hamburger_bg', 'color', ' .lk-nav-menu-toggle', 'background-color' ),
                array( 'hamburger_size', 'slider', ' .lk-nav-menu-toggle', 'font-size', 'px' ),
                array( 'hamburger_border_radius', 'dims', ' .lk-nav-menu-toggle', 'border-radius', 'px' ),
            ),
            'heading'             => array(
                array( 'title_color', 'color', ' .lk-heading-title', 'color' ),
                array( 'title_color', 'color', ' .lk-heading-title a', 'color' ),
                array( 'typography', 'typography', ' .lk-heading-title' ),
                array( 'text_shadow', 'text_shadow', ' .lk-heading-title' ),
                array( 'text_stroke', 'text_stroke', ' .lk-heading-title' ),
                array( 'blend_mode', 'raw', ' .lk-heading-title', 'mix-blend-mode', 'normal' ),
            ),
            'loop-grid' => array(
                array( 'columns', 'grid_cols', ' .lk-loop-items', 'grid-template-columns' ),
                array( 'column_gap', 'slider', ' .lk-loop-items', 'column-gap', 'px' ),
                array( 'row_gap', 'slider', ' .lk-loop-items', 'row-gap', 'px' ),
                array( 'pagination_align', 'map', ' .lk-posts-pagination', 'justify-content', $align_flex ),
                array( 'pagination_color', 'color', ' .lk-posts-pagination .page-numbers', 'color' ),
                array( 'pagination_active_color', 'color', ' .lk-posts-pagination .page-numbers.current', 'color' ),
                array( 'pagination_typography', 'typography', ' .lk-posts-pagination' ),
            ),
            'site-title'          => array(
                array( 'align', 'raw', ' .lk-site-title-wrapper', 'text-align' ),
                array( 'title_color', 'color', ' .lk-site-title-heading', 'color' ),
                array( 'title_color', 'color', ' .lk-site-title-heading a', 'color' ),
                array( 'title_hover_color', 'color', ' .lk-site-title-heading a:hover', 'color' ),
                array( 'typography', 'typography', ' .lk-site-title-heading' ),
                array( 'text_shadow', 'text_shadow', ' .lk-site-title-heading' ),
                array( 'text_stroke', 'text_stroke', ' .lk-site-title-heading' ),
                array( 'blend_mode', 'raw', ' .lk-site-title-heading', 'mix-blend-mode', 'normal' ),
            ),
            'post-title'          => array(
                array( 'align', 'raw', ' .lk-post-title-wrapper', 'text-align' ),
                array( 'title_color', 'color', ' .lk-post-title', 'color' ),
                array( 'title_color', 'color', ' .lk-post-title a', 'color' ),
                array( 'title_hover_color', 'color', ' .lk-post-title a:hover', 'color' ),
                array( 'typography', 'typography', ' .lk-post-title' ),
                array( 'text_shadow', 'text_shadow', ' .lk-post-title' ),
                array( 'text_stroke', 'text_stroke', ' .lk-post-title' ),
                array( 'blend_mode', 'raw', ' .lk-post-title', 'mix-blend-mode', 'normal' ),
            ),
            'post-featured-image' => array(
                array( 'align', 'raw', ' .lk-post-featured-image', 'text-align' ),
                array( 'width', 'slider', ' .lk-post-featured-image-img', 'width', '%' ),
                array( 'max_width', 'slider', ' .lk-post-featured-image-img', 'max-width', '%' ),
                array( 'height', 'slider', ' .lk-post-featured-image-img', 'height', 'px' ),
                array( 'object_fit', 'raw', ' .lk-post-featured-image-img', 'object-fit' ),
                array( 'image_border_radius', 'dims', ' .lk-post-featured-image-img', 'border-radius', 'px' ),
                array( 'opacity', 'unitless', ' .lk-post-featured-image-img', 'opacity' ),
                array( 'caption_align', 'raw', ' .lk-post-featured-image-caption', 'text-align' ),
                array( 'caption_color', 'color', ' .lk-post-featured-image-caption', 'color' ),
                array( 'caption_typography', 'typography', ' .lk-post-featured-image-caption' ),
            ),
            'post-excerpt'        => array(
                array( 'align', 'raw', ' .lk-post-excerpt', 'text-align' ),
                array( 'text_color', 'color', ' .lk-post-excerpt', 'color' ),
                array( 'typography', 'typography', ' .lk-post-excerpt' ),
            ),
            'post-info'           => array(
                array( 'align', 'map', ' .lk-post-info-inline', 'justify-content', $align_flex ),
                array( 'align', 'map', ' .lk-post-info-list', 'align-items', $align_flex ),
                array( 'space_between', 'slider', ' .lk-post-info-items', 'gap', 'px' ),
                array( 'divider_color', 'color', ' .lk-post-info-has-divider .lk-post-info-item:not(:first-child)::before', 'color' ),
                array( 'icon_color', 'color', ' .lk-post-info-icon', 'color' ),
                array( 'icon_color', 'color', ' .lk-post-info-icon svg', 'fill' ),
                array( 'icon_size', 'slider', ' .lk-post-info-icon', 'font-size', 'px' ),
                array( 'icon_size', 'slider', ' .lk-post-info-icon svg', 'width', 'px' ),
                array( 'icon_size', 'slider', ' .lk-post-info-icon svg', 'height', 'px' ),
                array( 'text_color', 'color', ' .lk-post-info-item', 'color' ),
                array( 'text_color', 'color', ' .lk-post-info-item a', 'color' ),
                array( 'link_hover_color', 'color', ' .lk-post-info-item a:hover', 'color' ),
                array( 'typography', 'typography', ' .lk-post-info-item' ),
            ),
            'post-content' => array(
                array( 'align', 'raw', ' .lk-post-content', 'text-align' ),
                array( 'text_color', 'color', ' .lk-post-content', 'color' ),
                array( 'link_color', 'color', ' .lk-post-content a', 'color' ),
                array( 'link_hover_color', 'color', ' .lk-post-content a:hover', 'color' ),
                array( 'typography', 'typography', ' .lk-post-content' ),
            ),
            'author-box' => array(
                array( 'align', 'raw', ' .lk-author-box-text', 'text-align' ),
                array( 'align', 'map', ' .lk-author-box-above', 'align-items', $align_flex ),
                array( 'avatar_size', 'slider', ' .lk-author-box-avatar img', 'width', 'px' ),
                array( 'avatar_size', 'slider', ' .lk-author-box-avatar img', 'height', 'px' ),
                array( 'avatar_radius', 'slider', ' .lk-author-box-avatar img', 'border-radius', '%' ),
                array( 'gap', 'slider', ' .lk-author-box', 'gap', 'px' ),
                array( 'name_color', 'color', ' .lk-author-box-name', 'color' ),
                array( 'name_color', 'color', ' .lk-author-box-name a', 'color' ),
                array( 'name_typography', 'typography', ' .lk-author-box-name' ),
                array( 'bio_color', 'color', ' .lk-author-box-bio', 'color' ),
                array( 'bio_typography', 'typography', ' .lk-author-box-bio' ),
                array( 'button_color', 'color', ' .lk-author-box-link', 'color' ),
                array( 'button_bg', 'color', ' .lk-author-box-link', 'background-color' ),
                array( 'button_typography', 'typography', ' .lk-author-box-link' ),
            ),
            'post-navigation' => array(
                array( 'label_color', 'color', ' .lk-post-nav-label', 'color' ),
                array( 'label_typography', 'typography', ' .lk-post-nav-label' ),
                array( 'title_color', 'color', ' .lk-post-nav-title', 'color' ),
                array( 'title_hover_color', 'color', ' .lk-post-nav-link:hover .lk-post-nav-title', 'color' ),
                array( 'title_typography', 'typography', ' .lk-post-nav-title' ),
                array( 'arrow_color', 'color', ' .lk-post-nav-arrow', 'color' ),
                array( 'arrow_size', 'slider', ' .lk-post-nav-arrow svg', 'width', 'px' ),
                array( 'arrow_size', 'slider', ' .lk-post-nav-arrow svg', 'height', 'px' ),
            ),
            'post-comments' => array(
                array( 'title_color', 'color', ' .lk-comments-title', 'color' ),
                array( 'title_typography', 'typography', ' .lk-comments-title' ),
                array( 'text_color', 'color', ' .lk-post-comments', 'color' ),
                array( 'link_color', 'color', ' .lk-post-comments a', 'color' ),
                array( 'typography', 'typography', ' .lk-post-comments' ),
            ),
            'archive-title' => array(
                array( 'align', 'raw', ' .lk-archive-title-wrapper', 'text-align' ),
                array( 'title_color', 'color', ' .lk-archive-title', 'color' ),
                array( 'prefix_color', 'color', ' .lk-archive-title-prefix', 'color' ),
                array( 'typography', 'typography', ' .lk-archive-title' ),
                array( 'text_shadow', 'text_shadow', ' .lk-archive-title' ),
            ),
            'archive-description' => array(
                array( 'align', 'raw', ' .lk-archive-description', 'text-align' ),
                array( 'text_color', 'color', ' .lk-archive-description', 'color' ),
                array( 'link_color', 'color', ' .lk-archive-description a', 'color' ),
                array( 'typography', 'typography', ' .lk-archive-description' ),
            ),
            'posts' => array(
                array( 'columns', 'grid_cols', ' .lk-posts-grid .lk-posts-items', 'grid-template-columns' ),
                array( 'column_gap', 'slider', ' .lk-posts-items', 'column-gap', 'px' ),
                array( 'row_gap', 'slider', ' .lk-posts-items', 'row-gap', 'px' ),
                array( 'card_bg', 'color', ' .lk-post-card', 'background-color' ),
                array( 'card_padding', 'dims', ' .lk-post-card-body', 'padding', 'px' ),
                array( 'card_radius', 'dims', ' .lk-post-card', 'border-radius', 'px' ),
                array( 'image_height', 'slider', ' .lk-post-card-image img', 'height', 'px' ),
                array( 'title_color', 'color', ' .lk-post-card-title a', 'color' ),
                array( 'title_hover_color', 'color', ' .lk-post-card-title a:hover', 'color' ),
                array( 'title_typography', 'typography', ' .lk-post-card-title' ),
                array( 'meta_color', 'color', ' .lk-post-card-meta', 'color' ),
                array( 'meta_typography', 'typography', ' .lk-post-card-meta' ),
                array( 'excerpt_color', 'color', ' .lk-post-card-excerpt', 'color' ),
                array( 'excerpt_typography', 'typography', ' .lk-post-card-excerpt' ),
                array( 'read_more_color', 'color', ' .lk-post-card-read-more', 'color' ),
                array( 'read_more_typography', 'typography', ' .lk-post-card-read-more' ),
                array( 'pagination_align', 'map', ' .lk-posts-pagination', 'justify-content', $align_flex ),
                array( 'pagination_color', 'color', ' .lk-posts-pagination .page-numbers', 'color' ),
                array( 'pagination_active_color', 'color', ' .lk-posts-pagination .page-numbers.current', 'color' ),
                array( 'pagination_typography', 'typography', ' .lk-posts-pagination' ),
            ),
            'pagination' => array(
                array( 'align', 'map', ' .lk-pagination', 'justify-content', $align_flex ),
                array( 'space', 'slider', ' .lk-pagination', 'gap', 'px' ),
                array( 'color', 'color', ' .lk-pagination .page-numbers', 'color' ),
                array( 'active_color', 'color', ' .lk-pagination .page-numbers.current', 'color' ),
                array( 'typography', 'typography', ' .lk-pagination' ),
            ),
            'accordion' => array(
                array( 'space_between', 'slider', ' .lk-accordion-item + .lk-accordion-item', 'margin-top', 'px' ),
                array( 'border_color', 'color', ' .lk-accordion-item', 'border-color' ),
                array( 'radius', 'slider', ' .lk-accordion-item', 'border-radius', 'px' ),
                array( 'title_color', 'color', ' .lk-accordion-title', 'color' ),
                array( 'title_bg', 'color', ' .lk-accordion-title', 'background-color' ),
                array( 'title_active_color', 'color', ' .lk-accordion-item.lk-open .lk-accordion-title', 'color' ),
                array( 'title_active_bg', 'color', ' .lk-accordion-item.lk-open .lk-accordion-title', 'background-color' ),
                array( 'title_typography', 'typography', ' .lk-accordion-title' ),
                array( 'title_padding', 'dims', ' .lk-accordion-title', 'padding', 'px' ),
                array( 'content_color', 'color', ' .lk-accordion-content', 'color' ),
                array( 'content_bg', 'color', ' .lk-accordion-content', 'background-color' ),
                array( 'content_typography', 'typography', ' .lk-accordion-content' ),
                array( 'content_padding', 'dims', ' .lk-accordion-content', 'padding', 'px' ),
            ),
            'tabs' => array(
                array( 'border_color', 'color', ' .lk-tabs-nav', 'border-color' ),
                array( 'border_color', 'color', ' .lk-tab-title.lk-active', 'border-color' ),
                array( 'border_color', 'color', ' .lk-tabs-panels', 'border-color' ),
                array( 'title_color', 'color', ' .lk-tab-title', 'color' ),
                array( 'title_active_color', 'color', ' .lk-tab-title.lk-active', 'color' ),
                array( 'title_active_bg', 'color', ' .lk-tab-title.lk-active', 'background-color' ),
                array( 'title_typography', 'typography', ' .lk-tab-title' ),
                array( 'content_color', 'color', ' .lk-tab-panel', 'color' ),
                array( 'content_bg', 'color', ' .lk-tabs-panels', 'background-color' ),
                array( 'content_typography', 'typography', ' .lk-tab-panel' ),
                array( 'content_padding', 'dims', ' .lk-tab-panel', 'padding', 'px' ),
            ),
            'counter' => array(
                array( 'align', 'raw', ' .lk-counter', 'text-align' ),
                array( 'number_color', 'color', ' .lk-counter-number-wrapper', 'color' ),
                array( 'number_typography', 'typography', ' .lk-counter-number-wrapper' ),
                array( 'title_color', 'color', ' .lk-counter-title', 'color' ),
                array( 'title_typography', 'typography', ' .lk-counter-title' ),
            ),
            'progress-bar' => array(
                array( 'bar_color', 'color', ' .lk-progress-fill', 'background-color' ),
                array( 'track_color', 'color', ' .lk-progress-track', 'background-color' ),
                array( 'height', 'slider', ' .lk-progress-track', 'height', 'px' ),
                array( 'radius', 'slider', ' .lk-progress-track', 'border-radius', 'px' ),
                array( 'radius', 'slider', ' .lk-progress-fill', 'border-radius', 'px' ),
                array( 'inner_color', 'color', ' .lk-progress-inner', 'color' ),
                array( 'title_color', 'color', ' .lk-progress-title', 'color' ),
                array( 'title_typography', 'typography', ' .lk-progress-header' ),
                array( 'percent_color', 'color', ' .lk-progress-percent', 'color' ),
            ),
            'alert' => array(
                array( 'bg_color', 'color', ' .lk-alert', 'background-color' ),
                array( 'border_color', 'color', ' .lk-alert', 'border-color' ),
                array( 'title_color', 'color', ' .lk-alert-title', 'color' ),
                array( 'title_typography', 'typography', ' .lk-alert-title' ),
                array( 'description_color', 'color', ' .lk-alert-description', 'color' ),
                array( 'description_typography', 'typography', ' .lk-alert-description' ),
            ),
            'star-rating' => array(
                array( 'align', 'raw', ' .lk-star-rating-wrapper', 'text-align' ),
                array( 'star_size', 'slider', ' .lk-star', 'font-size', 'px' ),
                array( 'star_gap', 'slider', ' .lk-star-rating', 'gap', 'px' ),
                array( 'star_color', 'color', ' .lk-star-fill', 'color' ),
                array( 'star_empty_color', 'color', ' .lk-star', 'color' ),
                array( 'title_color', 'color', ' .lk-star-rating-title', 'color' ),
                array( 'title_typography', 'typography', ' .lk-star-rating-title' ),
            ),
            'testimonial' => array(
                array( 'align', 'raw', ' .lk-testimonial', 'text-align' ),
                array( 'align', 'map', ' .lk-testimonial-meta', 'justify-content', $align_flex ),
                array( 'content_color', 'color', ' .lk-testimonial-content', 'color' ),
                array( 'content_typography', 'typography', ' .lk-testimonial-content' ),
                array( 'image_size', 'slider', ' .lk-testimonial-image img', 'width', 'px' ),
                array( 'image_size', 'slider', ' .lk-testimonial-image img', 'height', 'px' ),
                array( 'name_color', 'color', ' .lk-testimonial-name', 'color' ),
                array( 'name_typography', 'typography', ' .lk-testimonial-name' ),
                array( 'job_color', 'color', ' .lk-testimonial-job', 'color' ),
                array( 'job_typography', 'typography', ' .lk-testimonial-job' ),
            ),
            'image-carousel' => array(
                array( 'slides_to_show', 'raw', ' .lk-carousel', '--lk-slides' ),
                array( 'gap', 'slider', ' .lk-carousel', '--lk-gap', 'px' ),
                array( 'height', 'slider', ' .lk-carousel-image', 'height', 'px' ),
                array( 'radius', 'slider', ' .lk-carousel-image', 'border-radius', 'px' ),
                array( 'arrow_color', 'color', ' .lk-carousel-arrow', 'color' ),
                array( 'arrow_bg', 'color', ' .lk-carousel-arrow', 'background-color' ),
                array( 'dot_color', 'color', ' .lk-carousel-dot', 'background-color' ),
                array( 'dot_active_color', 'color', ' .lk-carousel-dot.lk-active', 'background-color' ),
                array( 'caption_color', 'color', ' .lk-carousel-caption', 'color' ),
                array( 'caption_bg', 'color', ' .lk-carousel-caption', 'background-color' ),
                array( 'caption_typography', 'typography', ' .lk-carousel-caption' ),
            ),
            'image-gallery' => array(
                array( 'columns', 'grid_cols', ' .lk-gallery', 'grid-template-columns' ),
                array( 'gap', 'slider', ' .lk-gallery', 'gap', 'px' ),
                array( 'height', 'slider', ' .lk-gallery img', 'height', 'px' ),
                array( 'radius', 'slider', ' .lk-gallery img', 'border-radius', 'px' ),
                array( 'caption_color', 'color', ' .lk-gallery-caption', 'color' ),
                array( 'caption_typography', 'typography', ' .lk-gallery-caption' ),
            ),
            'google-maps' => array(
                array( 'height', 'slider', ' .lk-map iframe', 'height', 'px' ),
                array( 'radius', 'slider', ' .lk-map', 'border-radius', 'px' ),
            ),
            'contact-form' => array(
                array( 'row_gap', 'slider', ' .lk-form-fields', 'row-gap', 'px' ),
                array( 'label_color', 'color', ' .lk-form-label', 'color' ),
                array( 'label_typography', 'typography', ' .lk-form-label' ),
                array( 'field_text_color', 'color', ' .lk-form-input', 'color' ),
                array( 'field_bg', 'color', ' .lk-form-input', 'background-color' ),
                array( 'field_border_color', 'color', ' .lk-form-input', 'border-color' ),
                array( 'field_radius', 'slider', ' .lk-form-input', 'border-radius', 'px' ),
                array( 'button_color', 'color', ' .lk-form-submit', 'color' ),
                array( 'button_bg', 'color', ' .lk-form-submit', 'background-color' ),
                array( 'button_hover_bg', 'color', ' .lk-form-submit:hover', 'background-color' ),
                array( 'button_typography', 'typography', ' .lk-form-submit' ),
            ),
            'tooltip' => array(
                array( 'align', 'raw', ' .lk-tooltip-wrapper', 'text-align' ),
                array( 'trigger_color', 'color', ' .lk-tooltip-trigger', 'color' ),
                array( 'trigger_typography', 'typography', ' .lk-tooltip-trigger' ),
                array( 'tooltip_color', 'color', ' .lk-tooltip', 'color' ),
                array( 'tooltip_bg', 'color', ' .lk-tooltip', 'background-color' ),
                array( 'tooltip_bg', 'color', ' .lk-tooltip-wrap', '--lk-tooltip-bg' ),
                array( 'tooltip_typography', 'typography', ' .lk-tooltip' ),
                array( 'tooltip_width', 'slider', ' .lk-tooltip', 'width', 'px' ),
            ),
            'table-of-contents' => array(
                array( 'bg_color', 'color', ' .lk-toc', 'background-color' ),
                array( 'border_color', 'color', ' .lk-toc', 'border-color' ),
                array( 'title_color', 'color', ' .lk-toc-title', 'color' ),
                array( 'title_typography', 'typography', ' .lk-toc-title' ),
                array( 'link_color', 'color', ' .lk-toc-item a', 'color' ),
                array( 'link_hover_color', 'color', ' .lk-toc-item a:hover', 'color' ),
                array( 'link_typography', 'typography', ' .lk-toc-item' ),
            ),
            'toggle' => array(
                array( 'space_between', 'slider', ' .lk-accordion-item + .lk-accordion-item', 'margin-top', 'px' ),
                array( 'border_color', 'color', ' .lk-accordion-item', 'border-color' ),
                array( 'radius', 'slider', ' .lk-accordion-item', 'border-radius', 'px' ),
                array( 'title_color', 'color', ' .lk-accordion-title', 'color' ),
                array( 'title_bg', 'color', ' .lk-accordion-title', 'background-color' ),
                array( 'title_active_color', 'color', ' .lk-accordion-item.lk-open .lk-accordion-title', 'color' ),
                array( 'title_active_bg', 'color', ' .lk-accordion-item.lk-open .lk-accordion-title', 'background-color' ),
                array( 'title_typography', 'typography', ' .lk-accordion-title' ),
                array( 'title_padding', 'dims', ' .lk-accordion-title', 'padding', 'px' ),
                array( 'content_color', 'color', ' .lk-accordion-content', 'color' ),
                array( 'content_bg', 'color', ' .lk-accordion-content', 'background-color' ),
                array( 'content_typography', 'typography', ' .lk-accordion-content' ),
                array( 'content_padding', 'dims', ' .lk-accordion-content', 'padding', 'px' ),
            ),
        );
    }

    /**
     * Whether a size-like value is set (mirrors hasSize() in loopkit-css.js).
     *
     * @param mixed $v Value.
     * @return bool
     */
    private static function has_size( $v ) {
        return null !== $v && '' !== $v;
    }

    /**
     * Number formatted like JavaScript's parseFloat() output (1.0 -> 1, 0.5 -> 0.5).
     *
     * @param mixed $v Value.
     * @return string
     */
    private static function css_num( $v ) {
        $f = (float) $v;
        return ( floor( $f ) == $f ) ? (string) (int) $f : rtrim( rtrim( sprintf( '%.4F', $f ), '0' ), '.' );
    }

    /**
     * CSS declarations for one declarative row (mirrors declarativeDeclarations() in loopkit-css.js).
     *
     * @param array $row Rule row.
     * @param mixed $val Setting value for the device.
     * @return array
     */
    private function declarative_declarations( $row, $val ) {
        $kind = $row[1];
        $prop = $row[3] ?? '';
        $opt  = $row[4] ?? null;
        $out  = array();

        if ( 'raw' === $kind ) {
            if ( is_array( $val ) ) return $out;
            if ( null !== $opt && (string) $val === (string) $opt ) return $out;
            $out[] = $prop . ': ' . esc_attr( $val );
        } elseif ( 'color' === $kind ) {
            if ( ! is_string( $val ) ) return $out;
            $out[] = $prop . ': ' . esc_attr( $this->resolve_global( $val ) );
        } elseif ( 'map' === $kind ) {
            if ( is_scalar( $val ) && is_array( $opt ) && isset( $opt[ $val ] ) ) $out[] = $prop . ': ' . $opt[ $val ];
        } elseif ( 'slider' === $kind ) {
            if ( is_array( $val ) ) {
                if ( self::has_size( $val['size'] ?? null ) && is_numeric( $val['size'] ) ) {
                    $out[] = $prop . ': ' . self::css_num( $val['size'] ) . esc_attr( ! empty( $val['unit'] ) ? $val['unit'] : ( $opt ?: 'px' ) );
                }
            } elseif ( self::has_size( $val ) && is_numeric( $val ) ) {
                $out[] = $prop . ': ' . self::css_num( $val ) . esc_attr( $opt ?: 'px' );
            }
        } elseif ( 'box_shadow' === $kind ) {
            if ( ! is_array( $val ) || empty( $val['color'] ) ) return $out;
            $out[] = 'box-shadow:' . ( ( ( $val['position'] ?? '' ) === 'inset' ) ? 'inset ' : '' ) .
                self::css_num( $val['horizontal'] ?? 0 ) . 'px ' . self::css_num( $val['vertical'] ?? 0 ) . 'px ' .
                self::css_num( $val['blur'] ?? 0 ) . 'px ' . self::css_num( $val['spread'] ?? 0 ) . 'px ' . esc_attr( $this->resolve_global( $val['color'] ) );
        } elseif ( 'grid_cols' === $kind ) {
            $cols = (int) ( is_array( $val ) ? ( $val['size'] ?? 0 ) : $val );
            if ( $cols > 0 ) $out[] = $prop . ': repeat(' . $cols . ', minmax(0, 1fr))';
        } elseif ( 'unitless' === $kind ) {
            $n = is_array( $val ) ? ( $val['size'] ?? null ) : $val;
            if ( self::has_size( $n ) && is_numeric( $n ) ) $out[] = $prop . ': ' . self::css_num( $n );
        } elseif ( 'dims' === $kind ) {
            if ( ! is_array( $val ) ) return $out;
            $sides = array( $val['top'] ?? null, $val['right'] ?? null, $val['bottom'] ?? null, $val['left'] ?? null );
            if ( ! array_filter( $sides, array( __CLASS__, 'has_size' ) ) ) return $out;
            $u     = esc_attr( ! empty( $val['unit'] ) ? $val['unit'] : ( $opt ?: 'px' ) );
            $parts = array();
            foreach ( $sides as $s ) {
                $parts[] = ( self::has_size( $s ) && is_numeric( $s ) ? self::css_num( $s ) : '0' ) . $u;
            }
            $out[] = $prop . ': ' . implode( ' ', $parts );
        } elseif ( 'typography' === $kind ) {
            if ( ! is_array( $val ) ) return $out;
            if ( ! empty( $val['family'] ) ) $out[] = 'font-family: ' . esc_attr( $val['family'] );
            if ( isset( $val['size']['size'] ) && self::has_size( $val['size']['size'] ) && is_numeric( $val['size']['size'] ) ) $out[] = 'font-size: ' . self::css_num( $val['size']['size'] ) . esc_attr( ! empty( $val['size']['unit'] ) ? $val['size']['unit'] : 'px' );
            if ( ! empty( $val['weight'] ) ) $out[] = 'font-weight: ' . esc_attr( $val['weight'] );
            if ( ! empty( $val['transform'] ) ) $out[] = 'text-transform: ' . esc_attr( $val['transform'] );
            if ( ! empty( $val['style'] ) ) $out[] = 'font-style: ' . esc_attr( $val['style'] );
            if ( ! empty( $val['decoration'] ) ) $out[] = 'text-decoration: ' . esc_attr( $val['decoration'] );
            if ( isset( $val['line_height']['size'] ) && self::has_size( $val['line_height']['size'] ) && is_numeric( $val['line_height']['size'] ) ) $out[] = 'line-height: ' . self::css_num( $val['line_height']['size'] ) . esc_attr( ! empty( $val['line_height']['unit'] ) ? $val['line_height']['unit'] : 'em' );
            if ( isset( $val['letter_spacing']['size'] ) && self::has_size( $val['letter_spacing']['size'] ) && is_numeric( $val['letter_spacing']['size'] ) ) $out[] = 'letter-spacing: ' . self::css_num( $val['letter_spacing']['size'] ) . esc_attr( ! empty( $val['letter_spacing']['unit'] ) ? $val['letter_spacing']['unit'] : 'px' );
            if ( isset( $val['word_spacing']['size'] ) && self::has_size( $val['word_spacing']['size'] ) && is_numeric( $val['word_spacing']['size'] ) ) $out[] = 'word-spacing: ' . self::css_num( $val['word_spacing']['size'] ) . esc_attr( ! empty( $val['word_spacing']['unit'] ) ? $val['word_spacing']['unit'] : 'px' );
        } elseif ( 'text_shadow' === $kind ) {
            if ( ! is_array( $val ) || empty( $val['color'] ) ) return $out;
            $out[] = 'text-shadow: ' . self::css_num( $val['horizontal'] ?? 0 ) . 'px ' . self::css_num( $val['vertical'] ?? 0 ) . 'px ' . self::css_num( $val['blur'] ?? 0 ) . 'px ' . esc_attr( $this->resolve_global( $val['color'] ) );
        } elseif ( 'text_stroke' === $kind ) {
            if ( ! is_array( $val ) || ! self::has_size( $val['width'] ?? null ) || ! (float) $val['width'] ) return $out;
            $out[] = '-webkit-text-stroke-width: ' . self::css_num( $val['width'] ) . esc_attr( ! empty( $val['unit'] ) ? $val['unit'] : 'px' );
            if ( ! empty( $val['color'] ) ) $out[] = '-webkit-text-stroke-color: ' . esc_attr( $this->resolve_global( $val['color'] ) );
        }

        return $out;
    }

    /**
     * Apply declarative rows to $sub_rules (mirrors applyDeclarativeRules() in loopkit-css.js).
     *
     * @param array  $rows      Rule rows.
     * @param array  $settings  Element settings.
     * @param string $device    Device key.
     * @param array  $sub_rules Sub rules, by reference.
     */
    private function apply_declarative_rules( $rows, $settings, $device, &$sub_rules ) {
        foreach ( $rows as $row ) {
            $val = $this->get_device_val( $settings, $row[0], $device );
            if ( null === $val ) continue;
            $decls = $this->declarative_declarations( $row, $val );
            if ( empty( $decls ) ) continue;
            $sub_rules[ $row[2] ] = array_merge( $sub_rules[ $row[2] ] ?? array(), $decls );
        }
    }

    /**
     * Build CSS rules for a single element on a specific device.
     *
     * @param array  $el     Element data.
     * @param string $device Device name.
     * @return array Array with 'base' and 'inner' rules.
     */
    private function build_device_rules( $el, $device ) {
        $base         = array();
        $inner        = array();
        $parent       = array();
        $hover_parent = array();
        $sub_rules    = array();
        $settings  = $el['settings'] ?? array();
        $el_type   = $el['elType'] ?? '';

        // Section & Container Layout (Problem 2 & Section 9 & Section Element)
        if ( 'container' === $el_type || 'section' === $el_type ) {
            $explicit_type          = $this->get_device_val( $settings, 'container_type', $device );
            $type                   = $explicit_type ?: ( $settings['container_type'] ?? 'flexbox' );
            $explicit_content_width = $this->get_device_val( $settings, 'content_width', $device );
            $content_width          = $explicit_content_width ?: ( $settings['content_width'] ?? ( 'section' === $el_type ? 'boxed' : 'full' ) );
            $width_val              = $this->get_device_val( $settings, 'width', $device );
            $min_height             = $this->get_device_val( $settings, 'min_height', $device );
            $overflow               = $this->get_device_val( $settings, 'overflow', $device );

            $is_boxed = ( 'boxed' === $content_width );

            if ( 'grid' === $type ) {
                if ( 'desktop' === $device || 'grid' === $explicit_type ) {
                    if ( $is_boxed ) {
                        $inner[] = 'display:grid';
                    } else {
                        $base[] = 'display:grid';
                    }
                }
                $cols = $this->get_device_val( $settings, 'grid_columns', $device ) ?: ( 'desktop' === $device ? ( $settings['grid_columns'] ?? 3 ) : null );
                $rows = $this->get_device_val( $settings, 'grid_rows', $device ) ?: ( 'desktop' === $device ? ( $settings['grid_rows'] ?? 2 ) : null );
                if ( $cols ) {
                    $rule = 'grid-template-columns:repeat(' . intval( $cols ) . ',minmax(0,1fr))';
                    if ( $is_boxed ) $inner[] = $rule; else $base[] = $rule;
                }
                if ( $rows ) {
                    $rule = 'grid-template-rows:repeat(' . intval( $rows ) . ',minmax(0,1fr))';
                    if ( $is_boxed ) $inner[] = $rule; else $base[] = $rule;
                }

                $flow = $this->get_device_val( $settings, 'grid_auto_flow', $device );
                if ( $flow ) {
                    $rule = 'grid-auto-flow:' . esc_attr( $flow );
                    if ( $is_boxed ) $inner[] = $rule; else $base[] = $rule;
                }

                $j_items = $this->get_device_val( $settings, 'justify_items', $device );
                if ( $j_items ) {
                    $rule = 'justify-items:' . esc_attr( $j_items );
                    if ( $is_boxed ) $inner[] = $rule; else $base[] = $rule;
                }

                $a_items = $this->get_device_val( $settings, 'align_items_grid', $device );
                if ( $a_items ) {
                    $rule = 'align-items:' . esc_attr( $a_items );
                    if ( $is_boxed ) $inner[] = $rule; else $base[] = $rule;
                }

                $g_gaps = $this->get_device_val( $settings, 'grid_gaps', $device );
                if ( is_array( $g_gaps ) ) {
                    $u   = $g_gaps['unit'] ?? 'px';
                    $col = isset( $g_gaps['column'] ) && '' !== $g_gaps['column'] ? floatval( $g_gaps['column'] ) . $u : '';
                    $row = isset( $g_gaps['row'] ) && '' !== $g_gaps['row'] ? floatval( $g_gaps['row'] ) . $u : '';
                    $rule = '';
                    if ( $row && $col ) $rule = 'gap:' . $row . ' ' . $col;
                    elseif ( $row ) $rule = 'row-gap:' . $row;
                    elseif ( $col ) $rule = 'column-gap:' . $col;
                    if ( $rule ) {
                        if ( $is_boxed ) $inner[] = $rule; else $base[] = $rule;
                    }
                }
            } else {
                if ( 'desktop' === $device || 'flexbox' === $explicit_type ) {
                    if ( $is_boxed ) {
                        $inner[] = 'display:flex';
                    } else {
                        $base[] = 'display:flex';
                    }
                }
                $dir = $this->get_device_val( $settings, 'flex_direction', $device ) ?: ( 'desktop' === $device ? ( $settings['flex_direction'] ?? 'column' ) : null );
                if ( $dir ) {
                    $rule = 'flex-direction:' . esc_attr( $dir );
                    if ( $is_boxed ) $inner[] = $rule; else $base[] = $rule;
                }

                $jc = $this->get_device_val( $settings, 'justify_content', $device );
                if ( $jc ) {
                    $rule = 'justify-content:' . esc_attr( $jc );
                    if ( $is_boxed ) $inner[] = $rule; else $base[] = $rule;
                }

                $ai = $this->get_device_val( $settings, 'align_items', $device );
                if ( $ai ) {
                    $rule = 'align-items:' . esc_attr( $ai );
                    if ( $is_boxed ) $inner[] = $rule; else $base[] = $rule;
                }

                $wrap = $this->get_device_val( $settings, 'flex_wrap', $device );
                if ( $wrap ) {
                    $rule = 'flex-wrap:' . esc_attr( $wrap );
                    if ( $is_boxed ) $inner[] = $rule; else $base[] = $rule;
                }

                if ( 'wrap' === $wrap || ( ! $wrap && ( $settings['flex_wrap'] ?? '' ) === 'wrap' ) ) {
                    $ac = $this->get_device_val( $settings, 'align_content', $device );
                    if ( $ac ) {
                        $rule = 'align-content:' . esc_attr( $ac );
                        if ( $is_boxed ) $inner[] = $rule; else $base[] = $rule;
                    }
                }

                $gaps = $this->get_device_val( $settings, 'flex_gap', $device );
                if ( is_array( $gaps ) ) {
                    $u   = $gaps['unit'] ?? 'px';
                    $col = isset( $gaps['column'] ) && '' !== $gaps['column'] ? floatval( $gaps['column'] ) . $u : '';
                    $row = isset( $gaps['row'] ) && '' !== $gaps['row'] ? floatval( $gaps['row'] ) . $u : '';
                    $rule = '';
                    if ( $row && $col ) $rule = 'gap:' . $row . ' ' . $col;
                    elseif ( $row ) $rule = 'row-gap:' . $row;
                    elseif ( $col ) $rule = 'column-gap:' . $col;
                    if ( $rule ) {
                        if ( $is_boxed ) $inner[] = $rule; else $base[] = $rule;
                    }
                } elseif ( is_numeric( $gaps ) ) {
                    $rule = 'gap:' . floatval( $gaps ) . 'px';
                    if ( $is_boxed ) $inner[] = $rule; else $base[] = $rule;
                }
            }

            // Boxed vs Full Width
            if ( 'desktop' === $device || $explicit_content_width || $width_val ) {
                if ( $is_boxed ) {
                    $box_w = '1140px';
                    if ( is_array( $width_val ) && ! empty( $width_val['size'] ) ) {
                        $box_w = floatval( $width_val['size'] ) . ( $width_val['unit'] ?? 'px' );
                    } elseif ( is_numeric( $width_val ) ) {
                        $box_w = floatval( $width_val ) . 'px';
                    }
                    $base[]  = 'width:100%';
                    $base[]  = 'display:flex';
                    $inner[] = 'max-width:' . $box_w;
                    $inner[] = 'margin-left:auto';
                    $inner[] = 'margin-right:auto';
                    $inner[] = 'width:100%';
                    $inner[] = 'min-height:inherit';
                    $inner[] = 'align-self:stretch';
                } else {
                    $full_w = '100%';
                    if ( is_array( $width_val ) && ! empty( $width_val['size'] ) ) {
                        $full_w = floatval( $width_val['size'] ) . ( $width_val['unit'] ?? '%' );
                    }
                    $base[]  = 'width:' . $full_w;
                    $base[]  = 'max-width:100%';
                }
            }

            if ( is_array( $min_height ) && ! empty( $min_height['size'] ) ) {
                $base[] = 'min-height:' . floatval( $min_height['size'] ) . ( $min_height['unit'] ?? 'px' );
            } elseif ( is_numeric( $min_height ) ) {
                $base[] = 'min-height:' . floatval( $min_height ) . 'px';
            }

            if ( $overflow && 'default' !== $overflow ) {
                $base[] = 'overflow:' . esc_attr( $overflow );
            }
        }

        // Heading widget
        $widget_type = $el['widgetType'] ?? $el['widget'] ?? '';
        if ( empty( $widget_type ) && isset( $settings['title'] ) ) {
            $widget_type = 'heading';
        }
        // Heading: only alignment belongs on the wrapper; text styles target .lk-heading-title
        // via post_widget_css_rules() (on the wrapper the theme's h1..h6 rules win).
        if ( 'widget' === $el_type && 'heading' === $widget_type ) {
            $align = $this->get_device_val( $settings, 'align', $device );
            if ( $align ) $base[] = 'text-align:' . esc_attr( $align );
        }

        // Text Editor widget
        if ( 'widget' === $el_type && 'text-editor' === $widget_type ) {
            $align = $this->get_device_val( $settings, 'align', $device );
            if ( $align ) $base[] = 'text-align:' . esc_attr( $align );

            $color = $this->get_device_val( $settings, 'text_color', $device );
            if ( $color ) $base[] = 'color:' . esc_attr( $this->resolve_global( $color ) );

            $typo = $this->get_device_val( $settings, 'typography', $device );
            if ( is_array( $typo ) ) {
                if ( ! empty( $typo['family'] ) ) $base[] = 'font-family:' . esc_attr( $typo['family'] );
                if ( ! empty( $typo['size']['size'] ) ) $base[] = 'font-size:' . floatval( $typo['size']['size'] ) . ( $typo['size']['unit'] ?? 'px' );
                if ( ! empty( $typo['weight'] ) ) $base[] = 'font-weight:' . esc_attr( $typo['weight'] );
                if ( ! empty( $typo['transform'] ) ) $base[] = 'text-transform:' . esc_attr( $typo['transform'] );
                if ( ! empty( $typo['style'] ) ) $base[] = 'font-style:' . esc_attr( $typo['style'] );
                if ( ! empty( $typo['line_height']['size'] ) ) $base[] = 'line-height:' . floatval( $typo['line_height']['size'] ) . ( $typo['line_height']['unit'] ?? 'em' );
                if ( ! empty( $typo['letter_spacing']['size'] ) ) $base[] = 'letter-spacing:' . floatval( $typo['letter_spacing']['size'] ) . 'px';
                if ( ! empty( $typo['word_spacing']['size'] ) ) $base[] = 'word-spacing:' . floatval( $typo['word_spacing']['size'] ) . 'px';
            }

            $ts = $this->get_device_val( $settings, 'text_shadow', $device );
            if ( is_array( $ts ) && ! empty( $ts['color'] ) ) {
                $base[] = 'text-shadow:' . intval( $ts['horizontal'] ?? 0 ) . 'px ' . intval( $ts['vertical'] ?? 0 ) . 'px ' . intval( $ts['blur'] ?? 0 ) . 'px ' . esc_attr( $this->resolve_global( $ts['color'] ) );
            }

            $cols = $this->get_device_val( $settings, 'columns', $device );
            if ( $cols && '1' !== (string) $cols ) {
                $base[] = 'columns:' . intval( $cols );
                $base[] = '-webkit-columns:' . intval( $cols );

                $gap = $this->get_device_val( $settings, 'columns_gap', $device );
                if ( is_array( $gap ) && isset( $gap['size'] ) && '' !== $gap['size'] ) {
                    $g_val = floatval( $gap['size'] ) . ( $gap['unit'] ?? 'px' );
                    $base[] = 'column-gap:' . $g_val;
                    $base[] = '-webkit-column-gap:' . $g_val;
                }
            }

            $space = $this->get_device_val( $settings, 'space', $device );
            if ( is_array( $space ) && isset( $space['size'] ) && '' !== $space['size'] ) {
                $s_val = floatval( $space['size'] ) . ( $space['unit'] ?? 'px' );
                $sub_rules[' p:not(:last-child)'] = array( 'margin-bottom:' . $s_val );
            }

            $link_col = $this->get_device_val( $settings, 'link_color', $device );
            if ( $link_col ) {
                $sub_rules[' a'] = array( 'color:' . esc_attr( $this->resolve_global( $link_col ) ) );
            }
            $link_hov_col = $this->get_device_val( $settings, 'link_hover_color', $device );
            if ( $link_hov_col ) {
                $sub_rules[' a:hover'] = array( 'color:' . esc_attr( $this->resolve_global( $link_hov_col ) ) );
            }

            // Drop cap rules
            $dc_rules = array();
            $dc_view = $settings['drop_cap_view'] ?? 'default';
            $dc_pri = $this->get_device_val( $settings, 'drop_cap_primary_color', $device );
            $dc_sec = $this->get_device_val( $settings, 'drop_cap_secondary_color', $device );

            if ( $dc_pri ) {
                $c_pri = esc_attr( $this->resolve_global( $dc_pri ) );
                if ( 'stacked' === $dc_view ) {
                    $dc_rules[] = 'background-color:' . $c_pri;
                } elseif ( 'framed' === $dc_view ) {
                    $dc_rules[] = 'color:' . $c_pri;
                    $dc_rules[] = 'border-color:' . $c_pri;
                } else {
                    $dc_rules[] = 'color:' . $c_pri;
                }
            }
            if ( $dc_sec ) {
                $c_sec = esc_attr( $this->resolve_global( $dc_sec ) );
                if ( 'stacked' === $dc_view ) {
                    $dc_rules[] = 'color:' . $c_sec;
                } elseif ( 'framed' === $dc_view ) {
                    $dc_rules[] = 'background-color:' . $c_sec;
                }
            }

            $dc_size = $this->get_device_val( $settings, 'drop_cap_size', $device );
            if ( is_array( $dc_size ) && isset( $dc_size['size'] ) && '' !== $dc_size['size'] ) {
                $dc_rules[] = 'font-size:' . floatval( $dc_size['size'] ) . ( $dc_size['unit'] ?? 'em' );
            }

            $dc_space = $this->get_device_val( $settings, 'drop_cap_space', $device );
            if ( is_array( $dc_space ) && isset( $dc_space['size'] ) && '' !== $dc_space['size'] ) {
                $dc_rules[] = 'margin-right:' . floatval( $dc_space['size'] ) . ( $dc_space['unit'] ?? 'px' );
            }

            $dc_radius = $this->get_device_val( $settings, 'drop_cap_border_radius', $device );
            if ( is_array( $dc_radius ) && isset( $dc_radius['size'] ) && '' !== $dc_radius['size'] ) {
                $dc_rules[] = 'border-radius:' . floatval( $dc_radius['size'] ) . ( $dc_radius['unit'] ?? 'px' );
            }

            $dc_border_w = $this->get_device_val( $settings, 'drop_cap_border_width', $device );
            if ( is_array( $dc_border_w ) && isset( $dc_border_w['size'] ) && '' !== $dc_border_w['size'] ) {
                $dc_rules[] = 'border-width:' . floatval( $dc_border_w['size'] ) . ( $dc_border_w['unit'] ?? 'px' );
                $dc_rules[] = 'border-style:solid';
            }

            if ( ! empty( $dc_rules ) ) {
                $sub_rules[' .lk-drop-cap'] = $dc_rules;
            }
        }

        // Image widget
        if ( 'widget' === $el_type && 'image' === $widget_type ) {
            $align = $this->get_device_val( $settings, 'align', $device );
            if ( $align ) $base[] = 'text-align:' . esc_attr( $align );

            $img_rules = array();
            $img_hover_rules = array();

            $w = $this->get_device_val( $settings, 'width', $device );
            if ( is_array( $w ) && isset( $w['size'] ) && '' !== $w['size'] ) {
                $img_rules[] = 'width:' . floatval( $w['size'] ) . ( $w['unit'] ?? '%' );
            }
            $max_w = $this->get_device_val( $settings, 'max_width', $device );
            if ( is_array( $max_w ) && isset( $max_w['size'] ) && '' !== $max_w['size'] ) {
                $img_rules[] = 'max-width:' . floatval( $max_w['size'] ) . ( $max_w['unit'] ?? '%' );
            }
            $h = $this->get_device_val( $settings, 'height', $device );
            if ( is_array( $h ) && isset( $h['size'] ) && '' !== $h['size'] ) {
                $img_rules[] = 'height:' . floatval( $h['size'] ) . ( $h['unit'] ?? 'px' );
            }

            $fit = $this->get_device_val( $settings, 'object_fit', $device );
            if ( $fit ) $img_rules[] = 'object-fit:' . esc_attr( $fit );

            $pos = $this->get_device_val( $settings, 'object_position', $device );
            if ( $pos && 'cover' === $fit ) $img_rules[] = 'object-position:' . esc_attr( $pos );

            $op = $this->get_device_val( $settings, 'opacity', $device );
            if ( is_array( $op ) && isset( $op['size'] ) && '' !== $op['size'] ) {
                $img_rules[] = 'opacity:' . floatval( $op['size'] );
            } elseif ( null !== $op && '' !== $op && ! is_array( $op ) ) {
                $img_rules[] = 'opacity:' . floatval( $op );
            }

            $op_hov = $this->get_device_val( $settings, 'opacity_hover', $device );
            if ( is_array( $op_hov ) && isset( $op_hov['size'] ) && '' !== $op_hov['size'] ) {
                $img_hover_rules[] = 'opacity:' . floatval( $op_hov['size'] );
            } elseif ( null !== $op_hov && '' !== $op_hov && ! is_array( $op_hov ) ) {
                $img_hover_rules[] = 'opacity:' . floatval( $op_hov );
            }

            $trans = $this->get_device_val( $settings, 'hover_transition_duration', $device );
            if ( is_array( $trans ) && isset( $trans['size'] ) && '' !== $trans['size'] ) {
                $img_rules[] = 'transition:all ' . intval( $trans['size'] ) . 'ms';
            } elseif ( ! empty( $trans ) && ! is_array( $trans ) ) {
                $img_rules[] = 'transition:all ' . intval( $trans ) . 'ms';
            }

            if ( ! empty( $img_rules ) ) {
                $sub_rules[' img'] = $img_rules;
            }
            if ( ! empty( $img_hover_rules ) ) {
                $sub_rules[' img:hover'] = $img_hover_rules;
            }

            // Caption styles
            $caption_rules = array();
            $cap_align = $this->get_device_val( $settings, 'caption_align', $device );
            if ( $cap_align ) $caption_rules[] = 'text-align:' . esc_attr( $cap_align );

            $cap_color = $this->get_device_val( $settings, 'caption_color', $device );
            if ( $cap_color ) $caption_rules[] = 'color:' . esc_attr( $this->resolve_global( $cap_color ) );

            $cap_space = $this->get_device_val( $settings, 'caption_space', $device );
            if ( is_array( $cap_space ) && isset( $cap_space['size'] ) && '' !== $cap_space['size'] ) {
                $caption_rules[] = 'margin-top:' . floatval( $cap_space['size'] ) . ( $cap_space['unit'] ?? 'px' );
            }

            if ( ! empty( $caption_rules ) ) {
                $sub_rules[' .lk-image-caption'] = $caption_rules;
            }
        }

        // Button widget
        if ( 'widget' === $el_type && 'button' === $widget_type ) {
            $align = $this->get_device_val( $settings, 'align', $device );
            if ( $align ) {
                if ( 'justify' === $align ) {
                    $base[] = 'text-align:justify';
                    $sub_rules[' .lk-button'] = array_merge( $sub_rules[' .lk-button'] ?? array(), array( 'width:100%', 'display:flex' ) );
                } else {
                    $base[] = 'text-align:' . esc_attr( $align );
                }
            }

            $btn_rules = array();
            $btn_hover_rules = array();

            $btn_color = $this->get_device_val( $settings, 'button_text_color', $device );
            if ( $btn_color ) {
                $btn_rules[] = 'color:' . esc_attr( $this->resolve_global( $btn_color ) );
                $btn_rules[] = 'fill:' . esc_attr( $this->resolve_global( $btn_color ) );
            }

            // Mirrors loopkit-css.js: background_color (+ gradient) is what the widget saves.
            $btn_bg_type  = $this->get_device_val( $settings, 'button_background_type', $device ) ?: ( $this->get_device_val( $settings, 'background_type', $device ) ?: 'classic' );
            $btn_bg_color = $this->get_device_val( $settings, 'button_background_color', $device );
            if ( null === $btn_bg_color || '' === $btn_bg_color ) {
                $btn_bg_color = $this->get_device_val( $settings, 'background_color', $device );
            }
            if ( 'gradient' === $btn_bg_type ) {
                $g1        = ( $this->get_device_val( $settings, 'button_background_gradient_color', $device ) ?: $this->get_device_val( $settings, 'background_gradient_color', $device ) ) ?: ( $btn_bg_color ?: '#38A8F5' );
                $g2        = ( $this->get_device_val( $settings, 'button_background_gradient_second_color', $device ) ?: $this->get_device_val( $settings, 'background_gradient_second_color', $device ) ) ?: '#ffffff';
                $g_type    = ( $this->get_device_val( $settings, 'button_background_gradient_type', $device ) ?: $this->get_device_val( $settings, 'background_gradient_type', $device ) ) ?: 'linear';
                $angle_raw = ( $this->get_device_val( $settings, 'button_background_gradient_angle', $device ) ?: $this->get_device_val( $settings, 'background_gradient_angle', $device ) );
                $angle     = is_array( $angle_raw ) ? ( $angle_raw['size'] ?? 180 ) : $angle_raw;
                $angle     = ( '' === $angle || null === $angle ) ? 180 : $angle;
                $btn_rules[] = 'background-image:' . ( 'radial' === $g_type
                    ? 'radial-gradient(circle, ' . esc_attr( $this->resolve_global( $g1 ) ) . ' 0%, ' . esc_attr( $this->resolve_global( $g2 ) ) . ' 100%)'
                    : 'linear-gradient(' . floatval( $angle ) . 'deg, ' . esc_attr( $this->resolve_global( $g1 ) ) . ' 0%, ' . esc_attr( $this->resolve_global( $g2 ) ) . ' 100%)' );
            } elseif ( $btn_bg_color ) {
                $btn_rules[] = 'background-color:' . esc_attr( $this->resolve_global( $btn_bg_color ) );
            }

            $hov_color = $this->get_device_val( $settings, 'button_hover_text_color', $device ) ?: $this->get_device_val( $settings, 'hover_color', $device );
            if ( $hov_color ) {
                $btn_hover_rules[] = 'color:' . esc_attr( $this->resolve_global( $hov_color ) );
                $btn_hover_rules[] = 'fill:' . esc_attr( $this->resolve_global( $hov_color ) );
            }

            $btn_hov_bg = $this->get_device_val( $settings, 'button_hover_background_color', $device ) ?: $this->get_device_val( $settings, 'button_background_hover_color', $device );
            if ( $btn_hov_bg ) {
                $btn_hover_rules[] = 'background-color:' . esc_attr( $this->resolve_global( $btn_hov_bg ) );
            }

            $btn_bdr_style = $this->get_device_val( $settings, 'button_border_border', $device ) ?: $this->get_device_val( $settings, 'border_type', $device );
            if ( $btn_bdr_style && 'none' !== $btn_bdr_style && 'default' !== $btn_bdr_style ) {
                $btn_rules[] = 'border-style:' . esc_attr( $btn_bdr_style );
                $b_width = $this->get_device_val( $settings, 'button_border_width', $device ) ?: $this->get_device_val( $settings, 'border_width', $device );
                if ( is_array( $b_width ) ) {
                    $u = $b_width['unit'] ?? 'px';
                    $btn_rules[] = 'border-width:' . floatval( $b_width['top'] ?? 0 ) . $u . ' ' . floatval( $b_width['right'] ?? 0 ) . $u . ' ' . floatval( $b_width['bottom'] ?? 0 ) . $u . ' ' . floatval( $b_width['left'] ?? 0 ) . $u;
                }
                $b_col = $this->get_device_val( $settings, 'button_border_color', $device ) ?: $this->get_device_val( $settings, 'border_color', $device );
                if ( $b_col ) {
                    $btn_rules[] = 'border-color:' . esc_attr( $this->resolve_global( $b_col ) );
                }
            }

            $bdr_hov_color = $this->get_device_val( $settings, 'button_hover_border_color', $device ) ?: $this->get_device_val( $settings, 'border_hover_color', $device );
            if ( $bdr_hov_color ) {
                $btn_hover_rules[] = 'border-color:' . esc_attr( $this->resolve_global( $bdr_hov_color ) );
            }

            $b_radius = $this->get_device_val( $settings, 'button_border_radius', $device ) ?: $this->get_device_val( $settings, 'border_radius', $device );
            if ( is_array( $b_radius ) ) {
                $u = $b_radius['unit'] ?? 'px';
                $btn_rules[] = 'border-radius:' . floatval( $b_radius['top'] ?? 0 ) . $u . ' ' . floatval( $b_radius['right'] ?? 0 ) . $u . ' ' . floatval( $b_radius['bottom'] ?? 0 ) . $u . ' ' . floatval( $b_radius['left'] ?? 0 ) . $u;
            }

            $btn_pad = $this->get_device_val( $settings, 'button_padding', $device ) ?: $this->get_device_val( $settings, 'padding', $device );
            if ( is_array( $btn_pad ) ) {
                $u = $btn_pad['unit'] ?? 'px';
                $btn_rules[] = 'padding:' . floatval( $btn_pad['top'] ?? 0 ) . $u . ' ' . floatval( $btn_pad['right'] ?? 0 ) . $u . ' ' . floatval( $btn_pad['bottom'] ?? 0 ) . $u . ' ' . floatval( $btn_pad['left'] ?? 0 ) . $u;
            }

            $typo = $this->get_device_val( $settings, 'typography', $device );
            if ( is_array( $typo ) ) {
                if ( ! empty( $typo['family'] ) ) $btn_rules[] = 'font-family:' . esc_attr( $typo['family'] );
                if ( ! empty( $typo['size']['size'] ) ) $btn_rules[] = 'font-size:' . floatval( $typo['size']['size'] ) . ( $typo['size']['unit'] ?? 'px' );
                if ( ! empty( $typo['weight'] ) ) $btn_rules[] = 'font-weight:' . esc_attr( $typo['weight'] );
                if ( ! empty( $typo['transform'] ) ) $btn_rules[] = 'text-transform:' . esc_attr( $typo['transform'] );
                if ( ! empty( $typo['style'] ) ) $btn_rules[] = 'font-style:' . esc_attr( $typo['style'] );
                if ( ! empty( $typo['line_height']['size'] ) ) $btn_rules[] = 'line-height:' . floatval( $typo['line_height']['size'] ) . ( $typo['line_height']['unit'] ?? 'em' );
                if ( ! empty( $typo['letter_spacing']['size'] ) ) $btn_rules[] = 'letter-spacing:' . floatval( $typo['letter_spacing']['size'] ) . 'px';
                if ( ! empty( $typo['word_spacing']['size'] ) ) $btn_rules[] = 'word-spacing:' . floatval( $typo['word_spacing']['size'] ) . 'px';
            }

            $ts = $this->get_device_val( $settings, 'text_shadow', $device );
            if ( is_array( $ts ) && ! empty( $ts['color'] ) ) {
                $btn_rules[] = 'text-shadow:' . intval( $ts['horizontal'] ?? 0 ) . 'px ' . intval( $ts['vertical'] ?? 0 ) . 'px ' . intval( $ts['blur'] ?? 0 ) . 'px ' . esc_attr( $this->resolve_global( $ts['color'] ) );
            }

            $b_shadow = $this->get_device_val( $settings, 'button_box_shadow', $device ) ?: $this->get_device_val( $settings, 'box_shadow', $device );
            if ( is_array( $b_shadow ) && ! empty( $b_shadow['color'] ) ) {
                $inset = ! empty( $b_shadow['position'] ) && 'inset' === $b_shadow['position'] ? 'inset ' : '';
                $btn_rules[] = 'box-shadow:' . $inset . intval( $b_shadow['horizontal'] ?? 0 ) . 'px ' . intval( $b_shadow['vertical'] ?? 0 ) . 'px ' . intval( $b_shadow['blur'] ?? 0 ) . 'px ' . intval( $b_shadow['spread'] ?? 0 ) . 'px ' . esc_attr( $this->resolve_global( $b_shadow['color'] ) );
            }

            if ( ! empty( $btn_rules ) ) {
                $sub_rules[' .lk-button'] = $btn_rules;
            }
            if ( ! empty( $btn_hover_rules ) ) {
                $sub_rules[' .lk-button:hover'] = $btn_hover_rules;
            }

            $indent = $this->get_device_val( $settings, 'icon_indent', $device );
            if ( is_array( $indent ) && isset( $indent['size'] ) && '' !== $indent['size'] ) {
                $i_val = floatval( $indent['size'] ) . ( $indent['unit'] ?? 'px' );
                $sub_rules[' .lk-button-icon.lk-align-icon-right'] = array( 'margin-left:' . $i_val );
                $sub_rules[' .lk-button-icon.lk-align-icon-left'] = array( 'margin-right:' . $i_val );
            }
        }

        // Spacer widget
        if ( 'widget' === $el_type && 'spacer' === $widget_type ) {
            $space = $this->get_device_val( $settings, 'space', $device );
            if ( is_array( $space ) && isset( $space['size'] ) && '' !== $space['size'] ) {
                $sub_rules[' .lk-spacer'] = array( 'height:' . floatval( $space['size'] ) . ( $space['unit'] ?? 'px' ) );
            } elseif ( is_numeric( $space ) ) {
                $sub_rules[' .lk-spacer'] = array( 'height:' . floatval( $space ) . 'px' );
            }
        }

        // Divider widget
        if ( 'widget' === $el_type && 'divider' === $widget_type ) {
            $d_align = $this->get_device_val( $settings, 'align', $device );
            if ( 'left' === $d_align ) {
                $sub_rules[' .lk-divider'] = array_merge( $sub_rules[' .lk-divider'] ?? array(), array( 'margin-left:0', 'margin-right:auto' ) );
            } elseif ( 'right' === $d_align ) {
                $sub_rules[' .lk-divider'] = array_merge( $sub_rules[' .lk-divider'] ?? array(), array( 'margin-left:auto', 'margin-right:0' ) );
            } elseif ( 'center' === $d_align ) {
                $sub_rules[' .lk-divider'] = array_merge( $sub_rules[' .lk-divider'] ?? array(), array( 'margin-left:auto', 'margin-right:auto' ) );
            }

            $d_width = $this->get_device_val( $settings, 'width', $device );
            if ( is_array( $d_width ) && isset( $d_width['size'] ) && '' !== $d_width['size'] ) {
                $sub_rules[' .lk-divider'] = array_merge( $sub_rules[' .lk-divider'] ?? array(), array( 'width:' . floatval( $d_width['size'] ) . ( $d_width['unit'] ?? '%' ) ) );
            }
            $d_color  = $this->get_device_val( $settings, 'color', $device );
            $d_weight = $this->get_device_val( $settings, 'weight', $device );
            $w_val    = is_array( $d_weight ) ? ( $d_weight['size'] ?? 1 ) : ( is_numeric( $d_weight ) ? $d_weight : 1 );
            $sep_rules = array();
            if ( $d_color ) {
                $sep_rules[] = 'border-top-color:' . esc_attr( $this->resolve_global( $d_color ) );
            }
            if ( $d_weight ) {
                $sep_rules[] = 'border-top-width:' . floatval( $w_val ) . 'px';
            }
            if ( ! empty( $sep_rules ) ) {
                $sub_rules[' .lk-divider-separator'] = $sep_rules;
            }
            $d_gap = $this->get_device_val( $settings, 'gap', $device );
            if ( is_array( $d_gap ) && isset( $d_gap['size'] ) && '' !== $d_gap['size'] ) {
                $g_val = floatval( $d_gap['size'] ) . ( $d_gap['unit'] ?? 'px' );
                $sub_rules[' .lk-divider'][] = 'padding-top:' . $g_val;
                $sub_rules[' .lk-divider'][] = 'padding-bottom:' . $g_val;
            }
        }

        // Icon widget
        if ( 'widget' === $el_type && 'icon' === $widget_type ) {
            $icon_align = $this->get_device_val( $settings, 'align', $device );
            if ( $icon_align ) {
                $base[] = 'text-align:' . esc_attr( $icon_align );
                $sub_rules[' .lk-icon-wrapper'] = array( 'text-align:' . esc_attr( $icon_align ) );
            }

            $icon_rules = array();
            $pri_color = $this->get_device_val( $settings, 'primary_color', $device );
            if ( $pri_color ) {
                $icon_rules[] = 'color:' . esc_attr( $this->resolve_global( $pri_color ) );
                $icon_rules[] = 'fill:' . esc_attr( $this->resolve_global( $pri_color ) );
            }
            $i_size = $this->get_device_val( $settings, 'size', $device );
            if ( is_array( $i_size ) && isset( $i_size['size'] ) && '' !== $i_size['size'] ) {
                $icon_rules[] = 'font-size:' . floatval( $i_size['size'] ) . ( $i_size['unit'] ?? 'px' );
            }
            $rotate = $this->get_device_val( $settings, 'rotate', $device );
            if ( is_numeric( $rotate ) && 0 !== intval( $rotate ) ) {
                $icon_rules[] = 'transform:rotate(' . intval( $rotate ) . 'deg)';
            }
            if ( ! empty( $icon_rules ) ) {
                $sub_rules[' .lk-icon'] = $icon_rules;
            }
            $hov_pri = $this->get_device_val( $settings, 'hover_primary_color', $device );
            if ( $hov_pri ) {
                $sub_rules[' .lk-icon:hover'] = array(
                    'color:' . esc_attr( $this->resolve_global( $hov_pri ) ),
                    'fill:' . esc_attr( $this->resolve_global( $hov_pri ) ),
                );
            }
        }

        // Image Box widget
        if ( 'widget' === $el_type && 'image-box' === $widget_type ) {
            $ib_align = $this->get_device_val( $settings, 'alignment', $device );
            $ib_pos   = $this->get_device_val( $settings, 'position', $device ) ?: ( $settings['position'] ?? 'top' );
            if ( $ib_align ) {
                $ib_wrapper_rules = array( 'text-align:' . esc_attr( $ib_align ) );
                if ( 'left' === $ib_pos || 'right' === $ib_pos ) {
                    $ib_valign = $this->get_device_val( $settings, 'vertical_alignment', $device ) ?: ( $settings['vertical_alignment'] ?? 'top' );
                    $va_map    = array( 'top' => 'flex-start', 'middle' => 'center', 'bottom' => 'flex-end' );
                    if ( isset( $va_map[ $ib_valign ] ) ) {
                        $ib_wrapper_rules[] = 'align-items:' . $va_map[ $ib_valign ];
                    }
                } else {
                    $ai_map = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end', 'justify' => 'stretch' );
                    if ( isset( $ai_map[ $ib_align ] ) ) {
                        $ib_wrapper_rules[] = 'align-items:' . $ai_map[ $ib_align ];
                    }
                    if ( 'left' === $ib_align ) {
                        $sub_rules[' .lk-image-box-img'] = array_merge( $sub_rules[' .lk-image-box-img'] ?? array(), array( 'margin-left:0', 'margin-right:auto' ) );
                    } elseif ( 'right' === $ib_align ) {
                        $sub_rules[' .lk-image-box-img'] = array_merge( $sub_rules[' .lk-image-box-img'] ?? array(), array( 'margin-left:auto', 'margin-right:0' ) );
                    } elseif ( 'center' === $ib_align ) {
                        $sub_rules[' .lk-image-box-img'] = array_merge( $sub_rules[' .lk-image-box-img'] ?? array(), array( 'margin-left:auto', 'margin-right:auto' ) );
                    }
                }
                $sub_rules[' .lk-image-box-wrapper'] = $ib_wrapper_rules;
                $sub_rules[' .lk-image-box-content'] = array( 'text-align:' . esc_attr( $ib_align ) );
            }

            $ib_width = $this->get_device_val( $settings, 'image_width', $device );
            if ( is_array( $ib_width ) && isset( $ib_width['size'] ) && '' !== $ib_width['size'] ) {
                $sub_rules[' .lk-image-box-img'] = array_merge( $sub_rules[' .lk-image-box-img'] ?? array(), array( 'width:' . floatval( $ib_width['size'] ) . ( $ib_width['unit'] ?? '%' ) ) );
            }
            $ib_space = $this->get_device_val( $settings, 'image_space', $device );
            if ( is_array( $ib_space ) && isset( $ib_space['size'] ) && '' !== $ib_space['size'] ) {
                $sub_rules[' .lk-image-box-img'] = array_merge( $sub_rules[' .lk-image-box-img'] ?? array(), array( 'margin-bottom:' . floatval( $ib_space['size'] ) . ( $ib_space['unit'] ?? 'px' ) ) );
            }
            $t_color = $this->get_device_val( $settings, 'title_color', $device );
            if ( $t_color ) {
                $sub_rules[' .lk-image-box-title'] = array( 'color:' . esc_attr( $this->resolve_global( $t_color ) ) );
            }
            $d_color = $this->get_device_val( $settings, 'description_color', $device );
            if ( $d_color ) {
                $sub_rules[' .lk-image-box-description'] = array( 'color:' . esc_attr( $this->resolve_global( $d_color ) ) );
            }
        }

        // Icon Box widget
        if ( 'widget' === $el_type && 'icon-box' === $widget_type ) {
            $ibox_align = $this->get_device_val( $settings, 'alignment', $device );
            $ibox_pos   = $this->get_device_val( $settings, 'position', $device ) ?: ( $settings['position'] ?? 'top' );
            if ( $ibox_align ) {
                $ibox_wrapper_rules = array( 'text-align:' . esc_attr( $ibox_align ) );
                if ( 'left' === $ibox_pos || 'right' === $ibox_pos ) {
                    $ibox_valign = $this->get_device_val( $settings, 'vertical_alignment', $device ) ?: ( $settings['vertical_alignment'] ?? 'top' );
                    $va_map      = array( 'top' => 'flex-start', 'middle' => 'center', 'bottom' => 'flex-end' );
                    if ( isset( $va_map[ $ibox_valign ] ) ) {
                        $ibox_wrapper_rules[] = 'align-items:' . $va_map[ $ibox_valign ];
                    }
                } else {
                    $ai_map = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end', 'justify' => 'stretch' );
                    if ( isset( $ai_map[ $ibox_align ] ) ) {
                        $ibox_wrapper_rules[] = 'align-items:' . $ai_map[ $ibox_align ];
                    }
                    if ( 'left' === $ibox_align ) {
                        $sub_rules[' .lk-icon-box-icon'] = array_merge( $sub_rules[' .lk-icon-box-icon'] ?? array(), array( 'margin-left:0', 'margin-right:auto', 'align-self:flex-start' ) );
                    } elseif ( 'right' === $ibox_align ) {
                        $sub_rules[' .lk-icon-box-icon'] = array_merge( $sub_rules[' .lk-icon-box-icon'] ?? array(), array( 'margin-left:auto', 'margin-right:0', 'align-self:flex-end' ) );
                    } elseif ( 'center' === $ibox_align ) {
                        $sub_rules[' .lk-icon-box-icon'] = array_merge( $sub_rules[' .lk-icon-box-icon'] ?? array(), array( 'margin-left:auto', 'margin-right:auto', 'align-self:center' ) );
                    }
                }
                $sub_rules[' .lk-icon-box-wrapper'] = $ibox_wrapper_rules;
                $sub_rules[' .lk-icon-box-content'] = array( 'text-align:' . esc_attr( $ibox_align ) );
            }

            $icon_rules = array();
            $pri_color = $this->get_device_val( $settings, 'primary_color', $device );
            if ( $pri_color ) {
                $icon_rules[] = 'color:' . esc_attr( $this->resolve_global( $pri_color ) );
                $icon_rules[] = 'fill:' . esc_attr( $this->resolve_global( $pri_color ) );
            }
            $i_size = $this->get_device_val( $settings, 'icon_size', $device );
            if ( is_array( $i_size ) && isset( $i_size['size'] ) && '' !== $i_size['size'] ) {
                $icon_rules[] = 'font-size:' . floatval( $i_size['size'] ) . ( $i_size['unit'] ?? 'px' );
            }
            if ( ! empty( $icon_rules ) ) {
                $sub_rules[' .lk-icon-box-icon'] = array_merge( $sub_rules[' .lk-icon-box-icon'] ?? array(), $icon_rules );
            }
            $t_color = $this->get_device_val( $settings, 'title_color', $device );
            if ( $t_color ) {
                $sub_rules[' .lk-icon-box-title'] = array( 'color:' . esc_attr( $this->resolve_global( $t_color ) ) );
            }
            $d_color = $this->get_device_val( $settings, 'description_color', $device );
            if ( $d_color ) {
                $sub_rules[' .lk-icon-box-description'] = array( 'color:' . esc_attr( $this->resolve_global( $d_color ) ) );
            }
        }

        // Icon List widget
        if ( 'widget' === $el_type && 'icon-list' === $widget_type ) {
            $il_align = $this->get_device_val( $settings, 'alignment', $device );
            if ( $il_align ) {
                $jc_map = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
                $jc     = $jc_map[ $il_align ] ?? $il_align;
                $sub_rules[' .lk-icon-list-items'] = array( 'justify-content:' . $jc, 'text-align:' . esc_attr( $il_align ) );
                $sub_rules[' .lk-icon-list-item']  = array( 'justify-content:' . $jc );
            }

            $sp = $this->get_device_val( $settings, 'space_between', $device );
            if ( is_array( $sp ) && isset( $sp['size'] ) && '' !== $sp['size'] ) {
                $sub_rules[' .lk-icon-list-item:not(:last-child)'] = array( 'margin-bottom:' . floatval( $sp['size'] ) . ( $sp['unit'] ?? 'px' ) );
            }
            $i_col = $this->get_device_val( $settings, 'icon_color', $device );
            if ( $i_col ) {
                $sub_rules[' .lk-icon-list-icon'] = array( 'color:' . esc_attr( $this->resolve_global( $i_col ) ), 'fill:' . esc_attr( $this->resolve_global( $i_col ) ) );
            }
            $t_col = $this->get_device_val( $settings, 'text_color', $device );
            if ( $t_col ) {
                $sub_rules[' .lk-icon-list-text'] = array( 'color:' . esc_attr( $this->resolve_global( $t_col ) ) );
            }
        }

        // Social Icons widget
        if ( 'widget' === $el_type && 'social-icons' === $widget_type ) {
            $si_align = $this->get_device_val( $settings, 'align', $device );
            if ( $si_align ) {
                $jc_map = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
                $jc     = $jc_map[ $si_align ] ?? $si_align;
                $sub_rules[' .lk-social-icons-wrapper'] = array( 'justify-content:' . $jc, 'text-align:' . esc_attr( $si_align ) );
            }

            $soc_rules = array();
            $i_size = $this->get_device_val( $settings, 'icon_size', $device );
            if ( is_array( $i_size ) && isset( $i_size['size'] ) && '' !== $i_size['size'] ) {
                $soc_rules[] = 'font-size:' . floatval( $i_size['size'] ) . ( $i_size['unit'] ?? 'px' );
            }
            $i_pad = $this->get_device_val( $settings, 'icon_padding', $device );
            if ( is_array( $i_pad ) && isset( $i_pad['size'] ) && '' !== $i_pad['size'] ) {
                $soc_rules[] = 'padding:' . floatval( $i_pad['size'] ) . ( $i_pad['unit'] ?? 'px' );
            }
            if ( ! empty( $soc_rules ) ) {
                $sub_rules[' .lk-social-icon'] = $soc_rules;
            }
            $i_space = $this->get_device_val( $settings, 'icon_spacing', $device );
            if ( is_array( $i_space ) && isset( $i_space['size'] ) && '' !== $i_space['size'] ) {
                $sub_rules[' .lk-grid-item:not(:last-child)'] = array( 'margin-right:' . floatval( $i_space['size'] ) . ( $i_space['unit'] ?? 'px' ) );
            }
        }

        // Site Logo widget
        if ( 'widget' === $el_type && 'site-logo' === $widget_type ) {
            $logo_align = $this->get_device_val( $settings, 'align', $device );
            if ( $logo_align ) {
                $base[] = 'text-align:' . esc_attr( $logo_align );
            }
            $logo_width = $this->get_device_val( $settings, 'width', $device );
            if ( is_array( $logo_width ) && isset( $logo_width['size'] ) && '' !== $logo_width['size'] ) {
                $sub_rules[' .lk-site-logo-img'] = array_merge( $sub_rules[' .lk-site-logo-img'] ?? array(), array( 'width:' . floatval( $logo_width['size'] ) . ( $logo_width['unit'] ?? 'px' ) ) );
            }
            $logo_max_width = $this->get_device_val( $settings, 'max_width', $device );
            if ( is_array( $logo_max_width ) && isset( $logo_max_width['size'] ) && '' !== $logo_max_width['size'] ) {
                $sub_rules[' .lk-site-logo-img'] = array_merge( $sub_rules[' .lk-site-logo-img'] ?? array(), array( 'max-width:' . floatval( $logo_max_width['size'] ) . ( $logo_max_width['unit'] ?? 'px' ) ) );
            }
            $logo_height = $this->get_device_val( $settings, 'height', $device );
            if ( is_array( $logo_height ) && isset( $logo_height['size'] ) && '' !== $logo_height['size'] ) {
                $sub_rules[' .lk-site-logo-img'] = array_merge( $sub_rules[' .lk-site-logo-img'] ?? array(), array( 'height:' . floatval( $logo_height['size'] ) . ( $logo_height['unit'] ?? 'px' ) ) );
            }
            $logo_fit = $this->get_device_val( $settings, 'object_fit', $device );
            if ( $logo_fit ) {
                $sub_rules[' .lk-site-logo-img'] = array_merge( $sub_rules[' .lk-site-logo-img'] ?? array(), array( 'object-fit:' . esc_attr( $logo_fit ) ) );
            }
            $logo_opacity = $this->get_device_val( $settings, 'opacity', $device );
            if ( null !== $logo_opacity && '' !== $logo_opacity ) {
                $sub_rules[' .lk-site-logo-img'] = array_merge( $sub_rules[' .lk-site-logo-img'] ?? array(), array( 'opacity:' . floatval( $logo_opacity ) ) );
            }
            $logo_hov_opacity = $this->get_device_val( $settings, 'opacity_hover', $device );
            if ( null !== $logo_hov_opacity && '' !== $logo_hov_opacity ) {
                $sub_rules[' .lk-site-logo-img:hover'] = array( 'opacity:' . floatval( $logo_hov_opacity ) );
            }
        }

        // Site Title widget
        // Nav Menu widget
        if ( 'widget' === $el_type && 'nav-menu' === $widget_type ) {
            $nm_align = $this->get_device_val( $settings, 'align', $device );
            if ( $nm_align ) {
                $nm_jc_map = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end', 'stretch' => 'space-between' );
                $nm_jc     = $nm_jc_map[ $nm_align ] ?? $nm_align;
                $sub_rules[' .lk-nav-menu'] = array_merge( $sub_rules[' .lk-nav-menu'] ?? array(), array( 'justify-content:' . $nm_jc ) );
            }
            $nm_gap = $this->get_device_val( $settings, 'item_spacing', $device );
            if ( is_array( $nm_gap ) && isset( $nm_gap['size'] ) && '' !== $nm_gap['size'] ) {
                $sub_rules[' .lk-nav-menu'] = array_merge( $sub_rules[' .lk-nav-menu'] ?? array(), array( 'gap:' . floatval( $nm_gap['size'] ) . ( $nm_gap['unit'] ?? 'px' ) ) );
            }
            $nm_color = $this->get_device_val( $settings, 'text_color', $device );
            if ( $nm_color ) {
                $sub_rules[' .lk-menu-link'] = array_merge( $sub_rules[' .lk-menu-link'] ?? array(), array( 'color:' . esc_attr( $this->resolve_global( $nm_color ) ) ) );
            }
            $nm_hov_color = $this->get_device_val( $settings, 'text_color_hover', $device );
            if ( $nm_hov_color ) {
                $sub_rules[' .lk-menu-link:hover'] = array( 'color:' . esc_attr( $this->resolve_global( $nm_hov_color ) ) );
            }
            $nm_ptr_col = $this->get_device_val( $settings, 'pointer_color', $device );
            if ( $nm_ptr_col ) {
                $base[] = '--lk-pointer-color:' . esc_attr( $this->resolve_global( $nm_ptr_col ) );
            }
            $nm_ptr_w = $this->get_device_val( $settings, 'pointer_width', $device );
            if ( is_array( $nm_ptr_w ) && isset( $nm_ptr_w['size'] ) && '' !== $nm_ptr_w['size'] ) {
                $base[] = '--lk-pointer-width:' . floatval( $nm_ptr_w['size'] ) . ( $nm_ptr_w['unit'] ?? 'px' );
            }
            $nm_sub_bg = $this->get_device_val( $settings, 'dropdown_bg_color', $device );
            if ( $nm_sub_bg ) {
                $sub_rules[' .lk-sub-menu'] = array_merge( $sub_rules[' .lk-sub-menu'] ?? array(), array( 'background-color:' . esc_attr( $this->resolve_global( $nm_sub_bg ) ) ) );
            }
            $nm_sub_width = $this->get_device_val( $settings, 'dropdown_width', $device );
            if ( is_array( $nm_sub_width ) && isset( $nm_sub_width['size'] ) && '' !== $nm_sub_width['size'] ) {
                $sub_rules[' .lk-sub-menu'] = array_merge( $sub_rules[' .lk-sub-menu'] ?? array(), array( 'min-width:' . floatval( $nm_sub_width['size'] ) . ( $nm_sub_width['unit'] ?? 'px' ) ) );
            }
            $nm_hamb_col = $this->get_device_val( $settings, 'hamburger_color', $device );
            if ( $nm_hamb_col ) {
                $sub_rules[' .lk-nav-menu-toggle'] = array( 'color:' . esc_attr( $this->resolve_global( $nm_hamb_col ) ) );
            }
        }

        // Search Form widget
        // Post widgets (Part 5) â€” declarative table above, mirrored in loopkit-css.js
        $post_widget_rules = self::post_widget_css_rules();
        if ( 'widget' === $el_type && isset( $post_widget_rules[ $widget_type ] ) ) {
            $this->apply_declarative_rules( $post_widget_rules[ $widget_type ], $settings, $device, $sub_rules );
        }

        if ( 'widget' === $el_type && 'search-form' === $widget_type ) {
            $sf_align = $this->get_device_val( $settings, 'align', $device );
            if ( $sf_align ) {
                $sf_jc_map = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
                $sf_jc     = $sf_jc_map[ $sf_align ] ?? $sf_align;
                $sub_rules[' .lk-widget-container'] = array( 'display:flex', 'justify-content:' . $sf_jc );
            }
            $sf_inp_color = $this->get_device_val( $settings, 'input_text_color', $device );
            if ( $sf_inp_color ) {
                $sub_rules[' .lk-search-form__input'] = array_merge( $sub_rules[' .lk-search-form__input'] ?? array(), array( 'color:' . esc_attr( $this->resolve_global( $sf_inp_color ) ) ) );
            }
            $sf_inp_bg = $this->get_device_val( $settings, 'input_bg_color', $device );
            if ( $sf_inp_bg ) {
                $sub_rules[' .lk-search-form__input'] = array_merge( $sub_rules[' .lk-search-form__input'] ?? array(), array( 'background-color:' . esc_attr( $this->resolve_global( $sf_inp_bg ) ) ) );
            }
            $sf_btn_color = $this->get_device_val( $settings, 'button_text_color', $device );
            if ( $sf_btn_color ) {
                $sub_rules[' .lk-search-form__submit'] = array_merge( $sub_rules[' .lk-search-form__submit'] ?? array(), array( 'color:' . esc_attr( $this->resolve_global( $sf_btn_color ) ) ) );
            }
            $sf_btn_bg = $this->get_device_val( $settings, 'button_bg_color', $device );
            if ( $sf_btn_bg ) {
                $sub_rules[' .lk-search-form__submit'] = array_merge( $sub_rules[' .lk-search-form__submit'] ?? array(), array( 'background-color:' . esc_attr( $this->resolve_global( $sf_btn_bg ) ) ) );
            }
            $sf_btn_bg_hov = $this->get_device_val( $settings, 'button_bg_color_hover', $device );
            if ( $sf_btn_bg_hov ) {
                $sub_rules[' .lk-search-form__submit:hover'] = array( 'background-color:' . esc_attr( $this->resolve_global( $sf_btn_bg_hov ) ) );
            }
            $sf_icon_size = $this->get_device_val( $settings, 'button_icon_size', $device );
            if ( is_array( $sf_icon_size ) && isset( $sf_icon_size['size'] ) && '' !== $sf_icon_size['size'] ) {
                $is_val = floatval( $sf_icon_size['size'] ) . ( $sf_icon_size['unit'] ?? 'px' );
                $sub_rules[' .lk-search-form__icon'] = array( 'font-size:' . $is_val, 'width:' . $is_val, 'height:' . $is_val );
            }
        }

        // Shared Advanced: Widget Width
        if ( 'widget' === $el_type ) {
            $w_width = $this->get_device_val( $settings, 'widget_width', $device );
            if ( 'full' === $w_width ) {
                $base[] = 'width:100%!important';
                $base[] = 'max-width:100%';
            } elseif ( 'inline' === $w_width ) {
                $base[] = 'width:auto!important';
                $base[] = 'max-width:100%';
                $base[] = 'display:inline-block';
            } elseif ( 'custom' === $w_width ) {
                $c_width = $this->get_device_val( $settings, 'custom_width', $device );
                if ( is_array( $c_width ) && ! empty( $c_width['size'] ) ) {
                    $base[] = 'width:' . floatval( $c_width['size'] ) . ( $c_width['unit'] ?? 'px' ) . '!important';
                    $base[] = 'max-width:100%';
                }
            }
        }

        // Shared Advanced: Align Self
        $align_self = $this->get_device_val( $settings, 'align_self', $device );
        if ( $align_self ) {
            $as_val = $align_self;
            if ( 'start' === $as_val ) $as_val = 'flex-start';
            elseif ( 'end' === $as_val ) $as_val = 'flex-end';
            $base[] = 'align-self:' . esc_attr( $as_val );
        }

        // Shared Advanced: Order
        $order = $this->get_device_val( $settings, 'order', $device );
        if ( 'start' === $order ) {
            $base[] = 'order:-1';
        } elseif ( 'end' === $order ) {
            $base[] = 'order:9999';
        } elseif ( 'custom' === $order ) {
            $order_custom = $this->get_device_val( $settings, 'order_custom', $device );
            if ( null !== $order_custom && '' !== $order_custom ) {
                $base[] = 'order:' . intval( $order_custom );
            }
        }

        // Shared Advanced: Size (Flex Grow / Shrink)
        $flex_size = $this->get_device_val( $settings, 'size', $device );
        if ( 'none' === $flex_size ) {
            $base[] = 'flex-grow:0!important';
            $base[] = 'flex-shrink:0!important';
        } elseif ( 'grow' === $flex_size ) {
            $base[] = 'flex-grow:1!important';
        } elseif ( 'shrink' === $flex_size ) {
            $base[] = 'flex-shrink:1!important';
        } elseif ( 'custom' === $flex_size ) {
            $f_grow   = $this->get_device_val( $settings, 'flex_grow', $device );
            $f_shrink = $this->get_device_val( $settings, 'flex_shrink', $device );
            if ( null !== $f_grow && '' !== $f_grow ) $base[] = 'flex-grow:' . floatval( $f_grow ) . '!important';
            if ( null !== $f_shrink && '' !== $f_shrink ) $base[] = 'flex-shrink:' . floatval( $f_shrink ) . '!important';
        }

        // Shared Advanced: Position & Offsets
        $position = $this->get_device_val( $settings, 'position', $device );
        if ( $position && 'default' !== $position ) {
            $base[] = 'position:' . esc_attr( $position );

            $h_orient = $this->get_device_val( $settings, 'offset_orientation_h', $device ) ?: 'left';
            $h_offset = $this->get_device_val( $settings, 'offset_x', $device );
            if ( is_array( $h_offset ) && isset( $h_offset['size'] ) && '' !== $h_offset['size'] ) {
                $h_val = floatval( $h_offset['size'] ) . ( $h_offset['unit'] ?? 'px' );
                if ( 'right' === $h_orient ) {
                    $base[] = 'right:' . $h_val;
                    $base[] = 'left:auto';
                } else {
                    $base[] = 'left:' . $h_val;
                    $base[] = 'right:auto';
                }
            }

            $v_orient = $this->get_device_val( $settings, 'offset_orientation_v', $device ) ?: 'top';
            $v_offset = $this->get_device_val( $settings, 'offset_y', $device );
            if ( is_array( $v_offset ) && isset( $v_offset['size'] ) && '' !== $v_offset['size'] ) {
                $v_val = floatval( $v_offset['size'] ) . ( $v_offset['unit'] ?? 'px' );
                if ( 'bottom' === $v_orient ) {
                    $base[] = 'bottom:' . $v_val;
                    $base[] = 'top:auto';
                } else {
                    $base[] = 'top:' . $v_val;
                    $base[] = 'bottom:auto';
                }
            }
        }

        // Common: padding, margin, background, border
        $padding = $this->get_device_val( $settings, 'padding', $device );
        if ( is_array( $padding ) ) {
            $u = $padding['unit'] ?? 'px';
            foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
                if ( isset( $padding[ $side ] ) && '' !== $padding[ $side ] ) {
                    $base[] = 'padding-' . $side . ':' . floatval( $padding[ $side ] ) . $u;
                }
            }
        }

        $margin = $this->get_device_val( $settings, 'margin', $device );
        if ( is_array( $margin ) ) {
            $u = $margin['unit'] ?? 'px';
            foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
                if ( isset( $margin[ $side ] ) && '' !== $margin[ $side ] ) {
                    $base[] = 'margin-' . $side . ':' . floatval( $margin[ $side ] ) . $u;
                }
            }
        }

        // Part B: Background (Normal & Hover, Classic & Gradient)
        $hover              = array();
        $overlay            = array();
        $overlay_hover      = array();
        $divider_top        = array();
        $divider_top_svg    = array();
        $divider_bottom     = array();
        $divider_bottom_svg = array();

        $normal_bg = $this->build_background_rules( $settings, '', false, $device );
        foreach ( $normal_bg as $nbr ) $base[] = $nbr;

        $hover_bg = $this->build_background_rules( $settings, '', true, $device );
        foreach ( $hover_bg as $hbr ) $hover[] = $hbr;

        // Hover Transition Duration (outputs transition on normal state)
        $bg_trans  = $this->get_device_val( $settings, 'background_hover_transition_duration', $device );
        $bdr_trans = $this->get_device_val( $settings, 'border_hover_transition_duration', $device );
        $gen_trans = $this->get_device_val( $settings, 'hover_transition_duration', $device );
        $eff_trans = ( null !== $bg_trans && '' !== $bg_trans ) ? $bg_trans : ( ( null !== $bdr_trans && '' !== $bdr_trans ) ? $bdr_trans : $gen_trans );
        if ( null !== $eff_trans && '' !== $eff_trans ) {
            $base[] = 'transition:all ' . floatval( $eff_trans ) . 's ease';
        }

        // Part B: Border, Radius & Box Shadow (Normal & Hover)
        $normal_bdr = $this->build_border_rules( $settings, false, $device );
        foreach ( $normal_bdr as $nbdr ) $base[] = $nbdr;

        $hover_bdr = $this->build_border_rules( $settings, true, $device );
        foreach ( $hover_bdr as $hbdr ) $hover[] = $hbdr;

        // Part B: Background Overlay (Normal & Hover)
        if ( 'section' === $el_type || 'container' === $el_type ) {
            $ov_img_hov = $settings['background_overlay_image_hover'] ?? ( $settings['background_overlay_hover_image'] ?? '' );
            $ov_type_hov = $settings['background_overlay_type_hover'] ?? ( $settings['background_overlay_hover_type'] ?? ( $settings['background_overlay_hover_background'] ?? '' ) );
            $has_norm_overlay_bg = ! empty( $settings['background_overlay_color'] ) ||
                                   ( ! empty( $settings['background_overlay_image'] ) && ( ( is_array( $settings['background_overlay_image'] ) && ! empty( $settings['background_overlay_image']['url'] ) ) || ( is_string( $settings['background_overlay_image'] ) && '' !== trim( $settings['background_overlay_image'] ) ) ) ) ||
                                   ( ! empty( $settings['background_overlay_type'] ) && 'gradient' === $settings['background_overlay_type'] && ( ! empty( $settings['background_overlay_gradient_color'] ) || ! empty( $settings['background_overlay_gradient_second_color'] ) ) );

            $has_overlay = $has_norm_overlay_bg ||
                           ! empty( $settings['background_overlay_color_hover'] ) ||
                           ! empty( $settings['background_overlay_hover_color'] ) ||
                           ( ! empty( $ov_img_hov ) && ( ( is_array( $ov_img_hov ) && ! empty( $ov_img_hov['url'] ) ) || ( is_string( $ov_img_hov ) && '' !== trim( $ov_img_hov ) ) ) ) ||
                           ( 'gradient' === $ov_type_hov && ( ! empty( $settings['background_overlay_gradient_color_hover'] ) || ! empty( $settings['background_overlay_hover_gradient_color'] ) || ! empty( $settings['background_overlay_gradient_second_color_hover'] ) || ! empty( $settings['background_overlay_hover_gradient_second_color'] ) ) ) ||
                           ( ! empty( $settings['background_overlay_blend_mode'] ) && 'normal' !== $settings['background_overlay_blend_mode'] ) ||
                           ( ! empty( $settings['background_overlay_css_filters'] ) && is_array( $settings['background_overlay_css_filters'] ) ) ||
                           ( ! empty( $settings['background_overlay_css_filters_hover'] ) && is_array( $settings['background_overlay_css_filters_hover'] ) ) ||
                           ( ! empty( $settings['background_overlay_hover_css_filters'] ) && is_array( $settings['background_overlay_hover_css_filters'] ) ) ||
                           ( isset( $settings['background_overlay_opacity_hover'] ) && '' !== $settings['background_overlay_opacity_hover'] ) ||
                           ( isset( $settings['background_overlay_hover_opacity'] ) && '' !== $settings['background_overlay_hover_opacity'] ) ||
                           ( isset( $settings['background_overlay_hover_transition_duration'] ) && '' !== $settings['background_overlay_hover_transition_duration'] );

            if ( $has_overlay ) {
                if ( 'desktop' === $device ) {
                    $overlay[] = 'position:absolute';
                    $overlay[] = 'inset:0';
                    $overlay[] = 'width:100%';
                    $overlay[] = 'height:100%';
                    $overlay[] = 'pointer-events:none';
                    $overlay[] = 'z-index:0';
                    $overlay[] = 'border-radius:inherit';
                }

                $ov_norm_bg = $this->build_background_rules( $settings, 'background_overlay_', false, $device );
                foreach ( $ov_norm_bg as $onb ) $overlay[] = $onb;

                $ov_op = $this->get_device_val( $settings, 'background_overlay_opacity', $device );
                if ( null === $ov_op && 'desktop' === $device && isset( $settings['background_overlay_opacity'] ) ) {
                    $ov_op = $settings['background_overlay_opacity'];
                }
                if ( is_array( $ov_op ) ) {
                    $ov_op = $ov_op['size'] ?? '';
                }
                if ( is_string( $ov_op ) && preg_match( '/^([0-9]+(?:\.[0-9]+)?)/', trim( $ov_op ), $m_op ) ) {
                    $ov_op = floatval( $m_op[1] );
                }
                if ( null !== $ov_op && '' !== $ov_op && is_numeric( $ov_op ) ) {
                    $overlay[] = 'opacity:' . floatval( $ov_op );
                } elseif ( ! $has_norm_overlay_bg ) {
                    $overlay[] = 'opacity:0';
                } elseif ( 'desktop' === $device ) {
                    $overlay[] = 'opacity:0.5';
                }

                $ov_blend = $this->get_device_val( $settings, 'background_overlay_blend_mode', $device );
                if ( $ov_blend && 'normal' !== $ov_blend ) {
                    $overlay[] = 'mix-blend-mode:' . esc_attr( $ov_blend );
                }

                $ov_filters = $this->get_device_val( $settings, 'background_overlay_css_filters', $device );
                if ( is_array( $ov_filters ) ) {
                    $f_parts = array();
                    if ( ! empty( $ov_filters['blur'] ) ) $f_parts[] = 'blur(' . floatval( $ov_filters['blur'] ) . 'px)';
                    if ( isset( $ov_filters['brightness'] ) && '' !== $ov_filters['brightness'] && 100 != $ov_filters['brightness'] ) $f_parts[] = 'brightness(' . floatval( $ov_filters['brightness'] ) . '%)';
                    if ( isset( $ov_filters['contrast'] ) && '' !== $ov_filters['contrast'] && 100 != $ov_filters['contrast'] ) $f_parts[] = 'contrast(' . floatval( $ov_filters['contrast'] ) . '%)';
                    if ( isset( $ov_filters['saturation'] ) && '' !== $ov_filters['saturation'] && 100 != $ov_filters['saturation'] ) $f_parts[] = 'saturate(' . floatval( $ov_filters['saturation'] ) . '%)';
                    if ( ! empty( $ov_filters['hue'] ) ) $f_parts[] = 'hue-rotate(' . floatval( $ov_filters['hue'] ) . 'deg)';
                    if ( ! empty( $f_parts ) ) $overlay[] = 'filter:' . implode( ' ', $f_parts );
                }

                $ov_trans = $this->get_device_val( $settings, 'background_overlay_hover_transition_duration', $device );
                if ( null === $ov_trans ) {
                    $ov_trans = $this->get_device_val( $settings, 'background_overlay_transition_duration', $device );
                }
                if ( is_array( $ov_trans ) ) {
                    $ov_trans = $ov_trans['size'] ?? '';
                }
                if ( null !== $ov_trans && '' !== $ov_trans && is_numeric( $ov_trans ) ) {
                    $overlay[] = 'transition:all ' . floatval( $ov_trans ) . 's ease';
                }

                $ov_hov_bg = $this->build_background_rules( $settings, 'background_overlay_', true, $device );
                foreach ( $ov_hov_bg as $ohb ) $overlay_hover[] = $ohb;

                $ov_hov_op = $this->get_device_val( $settings, 'background_overlay_opacity_hover', $device );
                if ( null === $ov_hov_op ) {
                    $ov_hov_op = $this->get_device_val( $settings, 'background_overlay_hover_opacity', $device );
                }
                if ( is_array( $ov_hov_op ) ) {
                    $ov_hov_op = $ov_hov_op['size'] ?? '';
                }
                if ( is_string( $ov_hov_op ) && preg_match( '/^([0-9]+(?:\.[0-9]+)?)/', trim( $ov_hov_op ), $m_hop ) ) {
                    $ov_hov_op = floatval( $m_hop[1] );
                }
                if ( null !== $ov_hov_op && '' !== $ov_hov_op && is_numeric( $ov_hov_op ) ) {
                    $overlay_hover[] = 'opacity:' . floatval( $ov_hov_op );
                } elseif ( ! empty( $overlay_hover ) ) {
                    $overlay_hover[] = 'opacity:0.5';
                }

                if ( ! empty( $overlay_hover ) && ( null === $ov_trans || '' === $ov_trans ) ) {
                    $overlay[] = 'transition:all 0.3s ease';
                }

                $ov_hov_filters = $this->get_device_val( $settings, 'background_overlay_css_filters_hover', $device );
                if ( is_array( $ov_hov_filters ) ) {
                    $hf_parts = array();
                    if ( ! empty( $ov_hov_filters['blur'] ) ) $hf_parts[] = 'blur(' . floatval( $ov_hov_filters['blur'] ) . 'px)';
                    if ( isset( $ov_hov_filters['brightness'] ) && '' !== $ov_hov_filters['brightness'] && 100 != $ov_hov_filters['brightness'] ) $hf_parts[] = 'brightness(' . floatval( $ov_hov_filters['brightness'] ) . '%)';
                    if ( isset( $ov_hov_filters['contrast'] ) && '' !== $ov_hov_filters['contrast'] && 100 != $ov_hov_filters['contrast'] ) $hf_parts[] = 'contrast(' . floatval( $ov_hov_filters['contrast'] ) . '%)';
                    if ( isset( $ov_hov_filters['saturation'] ) && '' !== $ov_hov_filters['saturation'] && 100 != $ov_hov_filters['saturation'] ) $hf_parts[] = 'saturate(' . floatval( $ov_hov_filters['saturation'] ) . '%)';
                    if ( ! empty( $ov_hov_filters['hue'] ) ) $hf_parts[] = 'hue-rotate(' . floatval( $ov_hov_filters['hue'] ) . 'deg)';
                    if ( ! empty( $hf_parts ) ) $overlay_hover[] = 'filter:' . implode( ' ', $hf_parts );
                }
            }

            // Part B: Shape Dividers (Top & Bottom)
            $s_top_type = $this->get_device_val( $settings, 'shape_divider_top_type', $device ) ?: ( 'desktop' === $device ? ( $settings['shape_divider_top_type'] ?? '' ) : '' );
            if ( $s_top_type && 'none' !== $s_top_type ) {
                $st_height = $this->get_device_val( $settings, 'shape_divider_top_height', $device ) ?: ( 'desktop' === $device ? ( $settings['shape_divider_top_height'] ?? 50 ) : '' );
                $st_color  = $this->get_device_val( $settings, 'shape_divider_top_color', $device ) ?: ( 'desktop' === $device ? ( $settings['shape_divider_top_color'] ?? '#FFFFFF' ) : '' );
                $st_width  = $this->get_device_val( $settings, 'shape_divider_top_width', $device ) ?: ( 'desktop' === $device ? ( $settings['shape_divider_top_width'] ?? 100 ) : '' );
                $st_flip   = ( ( $settings['shape_divider_top_flip'] ?? '' ) === 'yes' || true === ( $settings['shape_divider_top_flip'] ?? false ) );
                $st_invert = ( ( $settings['shape_divider_top_invert'] ?? '' ) === 'yes' || true === ( $settings['shape_divider_top_invert'] ?? false ) );
                $st_front  = ( ( $settings['shape_divider_top_front'] ?? '' ) === 'yes' || true === ( $settings['shape_divider_top_front'] ?? false ) );

                if ( $st_height ) $divider_top[] = 'height:' . floatval( $st_height ) . 'px';
                if ( $st_front )  $divider_top[] = 'z-index:10';

                if ( $st_color )  $divider_top_svg[] = 'fill:' . esc_attr( $this->resolve_global( $st_color ) );
                if ( $st_height ) $divider_top_svg[] = 'height:' . floatval( $st_height ) . 'px';

                $tw_num = floatval( $st_width ) ?: 100;
                $t_transforms = array();
                if ( 100 != $tw_num ) {
                    $divider_top_svg[] = 'width:' . $tw_num . '%';
                    $divider_top_svg[] = 'left:50%';
                    $t_transforms[] = 'translateX(-50%)';
                }
                if ( $st_flip && $st_invert ) $t_transforms[] = 'scale(-1,-1)';
                elseif ( $st_flip ) $t_transforms[] = 'scaleX(-1)';
                elseif ( $st_invert ) $t_transforms[] = 'scaleY(-1)';

                if ( ! empty( $t_transforms ) ) {
                    $divider_top_svg[] = 'transform:' . implode( ' ', $t_transforms );
                    $divider_top_svg[] = 'transform-origin:center';
                }
            }

            $s_btm_type = $this->get_device_val( $settings, 'shape_divider_bottom_type', $device ) ?: ( 'desktop' === $device ? ( $settings['shape_divider_bottom_type'] ?? '' ) : '' );
            if ( $s_btm_type && 'none' !== $s_btm_type ) {
                $sb_height = $this->get_device_val( $settings, 'shape_divider_bottom_height', $device ) ?: ( 'desktop' === $device ? ( $settings['shape_divider_bottom_height'] ?? 50 ) : '' );
                $sb_color  = $this->get_device_val( $settings, 'shape_divider_bottom_color', $device ) ?: ( 'desktop' === $device ? ( $settings['shape_divider_bottom_color'] ?? '#FFFFFF' ) : '' );
                $sb_width  = $this->get_device_val( $settings, 'shape_divider_bottom_width', $device ) ?: ( 'desktop' === $device ? ( $settings['shape_divider_bottom_width'] ?? 100 ) : '' );
                $sb_flip   = ( ( $settings['shape_divider_bottom_flip'] ?? '' ) === 'yes' || true === ( $settings['shape_divider_bottom_flip'] ?? false ) );
                $sb_invert = ( ( $settings['shape_divider_bottom_invert'] ?? '' ) === 'yes' || true === ( $settings['shape_divider_bottom_invert'] ?? false ) );
                $sb_front  = ( ( $settings['shape_divider_bottom_front'] ?? '' ) === 'yes' || true === ( $settings['shape_divider_bottom_front'] ?? false ) );

                if ( $sb_height ) $divider_bottom[] = 'height:' . floatval( $sb_height ) . 'px';
                if ( $sb_front )  $divider_bottom[] = 'z-index:10';

                if ( $sb_color )  $divider_bottom_svg[] = 'fill:' . esc_attr( $this->resolve_global( $sb_color ) );
                if ( $sb_height ) $divider_bottom_svg[] = 'height:' . floatval( $sb_height ) . 'px';

                $bw_num = floatval( $sb_width ) ?: 100;
                $b_transforms = array();
                if ( 100 != $bw_num ) {
                    $divider_bottom_svg[] = 'width:' . $bw_num . '%';
                    $divider_bottom_svg[] = 'left:50%';
                    $b_transforms[] = 'translateX(-50%)';
                }
                if ( $sb_flip && $sb_invert ) $b_transforms[] = 'scale(-1,-1)';
                elseif ( $sb_flip ) $b_transforms[] = 'scaleX(-1)';
                elseif ( $sb_invert ) $b_transforms[] = 'scaleY(-1)';

                if ( ! empty( $b_transforms ) ) {
                    $divider_bottom_svg[] = 'transform:' . implode( ' ', $b_transforms );
                    $divider_bottom_svg[] = 'transform-origin:center';
                }
            }
        }

        $z_index = $this->get_device_val( $settings, 'z_index', $device );
        if ( null !== $z_index && '' !== $z_index ) $base[] = 'z-index:' . intval( $z_index );

        // Entrance Animation (responsive duration, delay, and per-device animation-name)
        $anim_dur = $this->get_device_val( $settings, 'animation_duration', $device );
        if ( null !== $anim_dur && '' !== $anim_dur ) {
            $dur_map = array( 'slow' => '2s', 'normal' => '1.25s', 'fast' => '0.75s' );
            $dur_val = $dur_map[ $anim_dur ] ?? ( is_numeric( $anim_dur ) ? $anim_dur . 's' : $anim_dur );
            $base[] = '--lk-anim-duration:' . $dur_val;
        }
        $anim_delay = $this->get_device_val( $settings, 'animation_delay', $device );
        if ( null !== $anim_delay && '' !== $anim_delay ) {
            $base[] = '--lk-anim-delay:' . intval( $anim_delay ) . 'ms';
        }
        $anim_name = $this->get_device_val( $settings, 'entrance_animation', $device );
        if ( null !== $anim_name && '' !== $anim_name ) {
            if ( 'none' === $anim_name ) {
                $base[] = 'animation-name:none!important';
                $base[] = 'opacity:1!important';
            } else {
                $base[] = 'animation-name:lk-anim-' . sanitize_html_class( $anim_name );
            }
        }

        // Sticky (Motion Effects)
        $sticky_val = $this->get_device_val( $settings, 'sticky', $device );
        if ( null !== $sticky_val && '' !== $sticky_val ) {
            if ( 'none' === $sticky_val ) {
                $base[] = 'position:static';
            } elseif ( 'top' === $sticky_val ) {
                $base[] = 'position:-webkit-sticky';
                $base[] = 'position:sticky';
                $base[] = 'top:var(--lk-sticky-offset, 0px)';
                $base[] = 'bottom:auto';
                $base[] = 'z-index:100';
            } elseif ( 'bottom' === $sticky_val ) {
                $base[] = 'position:-webkit-sticky';
                $base[] = 'position:sticky';
                $base[] = 'bottom:var(--lk-sticky-offset, 0px)';
                $base[] = 'top:auto';
                $base[] = 'z-index:100';
            }
        }
        $sticky_offset = $this->get_device_val( $settings, 'sticky_offset', $device );
        if ( null !== $sticky_offset && '' !== $sticky_offset ) {
            $base[] = '--lk-sticky-offset:' . intval( $sticky_offset ) . 'px';
        }
        $sticky_effects_offset = $this->get_device_val( $settings, 'sticky_effects_offset', $device );
        if ( null !== $sticky_effects_offset && '' !== $sticky_effects_offset ) {
            $base[] = '--lk-sticky-effects-offset:' . intval( $sticky_effects_offset ) . 'px';
        }

        // -------------------------------------------------------------
        // TRANSFORM (NORMAL & HOVER, 2D & 3D, FLIP, ANCHOR POINTS)
        // -------------------------------------------------------------
        $transform_parts = array();
        $t_rotate = $this->get_device_val( $settings, 'transform_rotate', $device );
        if ( is_array( $t_rotate ) && isset( $t_rotate['size'] ) && '' !== $t_rotate['size'] ) {
            $transform_parts[] = 'rotate(' . floatval( $t_rotate['size'] ) . ( $t_rotate['unit'] ?? 'deg' ) . ')';
        }
        $t_off_x = $this->get_device_val( $settings, 'transform_offset_x', $device );
        if ( is_array( $t_off_x ) && isset( $t_off_x['size'] ) && '' !== $t_off_x['size'] ) {
            $transform_parts[] = 'translateX(' . floatval( $t_off_x['size'] ) . ( $t_off_x['unit'] ?? 'px' ) . ')';
        }
        $t_off_y = $this->get_device_val( $settings, 'transform_offset_y', $device );
        if ( is_array( $t_off_y ) && isset( $t_off_y['size'] ) && '' !== $t_off_y['size'] ) {
            $transform_parts[] = 'translateY(' . floatval( $t_off_y['size'] ) . ( $t_off_y['unit'] ?? 'px' ) . ')';
        }
        $t_scale = $this->get_device_val( $settings, 'transform_scale', $device );
        if ( null !== $t_scale && '' !== $t_scale ) {
            $scale_val = is_array( $t_scale ) ? ( $t_scale['size'] ?? '' ) : $t_scale;
            if ( '' !== $scale_val ) {
                $transform_parts[] = 'scale(' . floatval( $scale_val ) . ')';
            }
        }
        $t_skew_x = $this->get_device_val( $settings, 'transform_skew_x', $device );
        if ( is_array( $t_skew_x ) && isset( $t_skew_x['size'] ) && '' !== $t_skew_x['size'] ) {
            $transform_parts[] = 'skewX(' . floatval( $t_skew_x['size'] ) . ( $t_skew_x['unit'] ?? 'deg' ) . ')';
        }
        $t_skew_y = $this->get_device_val( $settings, 'transform_skew_y', $device );
        if ( is_array( $t_skew_y ) && isset( $t_skew_y['size'] ) && '' !== $t_skew_y['size'] ) {
            $transform_parts[] = 'skewY(' . floatval( $t_skew_y['size'] ) . ( $t_skew_y['unit'] ?? 'deg' ) . ')';
        }

        // C4: 3D Rotate, Perspective, Flip (Normal)
        $t_rotate_x = $this->get_device_val( $settings, 'transform_rotate_x', $device );
        $rx_val     = ( is_array( $t_rotate_x ) && isset( $t_rotate_x['size'] ) ) ? floatval( $t_rotate_x['size'] ) : ( ( null !== $t_rotate_x && '' !== $t_rotate_x ) ? floatval( $t_rotate_x ) : 0.0 );
        if ( 0.0 !== $rx_val ) {
            $base[] = '--lk-transform-rotate-x:' . $rx_val . 'deg';
        }

        $t_rotate_y = $this->get_device_val( $settings, 'transform_rotate_y', $device );
        $ry_val     = ( is_array( $t_rotate_y ) && isset( $t_rotate_y['size'] ) ) ? floatval( $t_rotate_y['size'] ) : ( ( null !== $t_rotate_y && '' !== $t_rotate_y ) ? floatval( $t_rotate_y ) : 0.0 );
        if ( 0.0 !== $ry_val ) {
            $base[] = '--lk-transform-rotate-y:' . $ry_val . 'deg';
        }

        $t_flip_x = $settings['transform_flip_x'] ?? '';
        if ( 'yes' === $t_flip_x || true === $t_flip_x ) {
            $base[] = '--lk-transform-flip-x:-1';
        }

        $t_flip_y = $settings['transform_flip_y'] ?? '';
        if ( 'yes' === $t_flip_y || true === $t_flip_y ) {
            $base[] = '--lk-transform-flip-y:-1';
        }

        $has_3d   = ( 0.0 !== $rx_val || 0.0 !== $ry_val );
        $has_flip = ( 'yes' === $t_flip_x || true === $t_flip_x || 'yes' === $t_flip_y || true === $t_flip_y );

        $t_perspective = $this->get_device_val( $settings, 'transform_perspective', $device );
        $p_val         = ( is_array( $t_perspective ) && isset( $t_perspective['size'] ) ) ? floatval( $t_perspective['size'] ) : ( ( null !== $t_perspective && '' !== $t_perspective ) ? floatval( $t_perspective ) : 1000.0 );
        if ( $has_3d ) {
            $base[] = 'transform-style:preserve-3d';
            if ( $p_val > 0 ) {
                $parent[] = 'perspective:' . $p_val . 'px';
            }
        }

        $has_scroll = ( ! empty( $settings['scrolling_effects'] ) && ( 'yes' === $settings['scrolling_effects'] || true === $settings['scrolling_effects'] ) );
        $has_mouse  = ( ! empty( $settings['mouse_effects'] ) && ( 'yes' === $settings['mouse_effects'] || true === $settings['mouse_effects'] ) );

        if ( $has_scroll || $has_mouse || ! empty( $transform_parts ) || $has_3d || $has_flip ) {
            $motion_transform = 'translateX(var(--lk-scroll-translate-x, 0)) translateY(var(--lk-scroll-translate-y, 0)) rotate(var(--lk-scroll-rotate, 0deg)) scale(var(--lk-scroll-scale, 1)) rotateX(var(--lk-mouse-rotate-x, 0deg)) rotateY(var(--lk-mouse-rotate-y, 0deg)) translateX(var(--lk-mouse-translate-x, 0)) translateY(var(--lk-mouse-translate-y, 0)) rotateX(var(--lk-transform-rotate-x, 0deg)) rotateY(var(--lk-transform-rotate-y, 0deg)) scaleX(var(--lk-transform-flip-x, 1)) scaleY(var(--lk-transform-flip-y, 1))';
            if ( ! empty( $transform_parts ) ) {
                $motion_transform .= ' ' . implode( ' ', $transform_parts );
            }
            $base[] = 'transform:' . $motion_transform;
        }

        if ( $has_scroll ) {
            $base[] = 'filter:blur(var(--lk-scroll-blur, 0px))';
            $base[] = 'opacity:calc(1 + var(--lk-scroll-opacity, 0))';
        }

        // C4 Anchor Point X/Y (merged with C3 scroll anchor point)
        $anchor_x = $this->get_device_val( $settings, 'transform_anchor_x', $device );
        if ( null === $anchor_x || '' === $anchor_x ) {
            $anchor_x = $this->get_device_val( $settings, 'scroll_anchor_x', $device );
        }
        $ax_val = ( is_array( $anchor_x ) && isset( $anchor_x['size'] ) && '' !== $anchor_x['size'] ) ? $anchor_x['size'] : ( ( null !== $anchor_x && '' !== $anchor_x ) ? $anchor_x : 50 );

        $anchor_y = $this->get_device_val( $settings, 'transform_anchor_y', $device );
        if ( null === $anchor_y || '' === $anchor_y ) {
            $anchor_y = $this->get_device_val( $settings, 'scroll_anchor_y', $device );
        }
        $ay_val = ( is_array( $anchor_y ) && isset( $anchor_y['size'] ) && '' !== $anchor_y['size'] ) ? $anchor_y['size'] : ( ( null !== $anchor_y && '' !== $anchor_y ) ? $anchor_y : 50 );

        $ax_num = floatval( $ax_val );
        $ay_num = floatval( $ay_val );

        if ( $has_scroll || 50.0 !== $ax_num || 50.0 !== $ay_num ) {
            $base[] = 'transform-origin:' . $ax_num . '% ' . $ay_num . '%';
        }

        if ( $has_mouse && ! empty( $settings['mouse_tilt'] ) && ( 'yes' === $settings['mouse_tilt'] || true === $settings['mouse_tilt'] ) ) {
            $base[] = 'transform-style:preserve-3d';
            $base[] = 'perspective:1000px';
        }

        // C4 Transform Hover
        $h_rot   = $this->get_device_val( $settings, 'transform_rotate_hover', $device );
        $h_off_x = $this->get_device_val( $settings, 'transform_offset_x_hover', $device );
        $h_off_y = $this->get_device_val( $settings, 'transform_offset_y_hover', $device );
        $h_scale = $this->get_device_val( $settings, 'transform_scale_hover', $device );
        $h_skew_x = $this->get_device_val( $settings, 'transform_skew_x_hover', $device );
        $h_skew_y = $this->get_device_val( $settings, 'transform_skew_y_hover', $device );
        $h_rot_x  = $this->get_device_val( $settings, 'transform_rotate_x_hover', $device );
        $h_rot_y  = $this->get_device_val( $settings, 'transform_rotate_y_hover', $device );
        $h_persp  = $this->get_device_val( $settings, 'transform_perspective_hover', $device );
        $h_flip_x = $settings['transform_flip_x_hover'] ?? '';
        $h_flip_y = $settings['transform_flip_y_hover'] ?? '';
        $h_anc_x  = $this->get_device_val( $settings, 'transform_anchor_x_hover', $device );
        $h_anc_y  = $this->get_device_val( $settings, 'transform_anchor_y_hover', $device );
        $t_dur    = $this->get_device_val( $settings, 'transform_transition_duration', $device );

        $has_prop_val = function( $v ) {
            if ( null === $v || '' === $v ) return false;
            if ( is_array( $v ) ) return isset( $v['size'] ) && '' !== $v['size'];
            return true;
        };

        $has_hover_transform = $has_prop_val( $h_rot ) || $has_prop_val( $h_off_x ) || $has_prop_val( $h_off_y ) ||
                               $has_prop_val( $h_scale ) || $has_prop_val( $h_skew_x ) || $has_prop_val( $h_skew_y ) ||
                               $has_prop_val( $h_rot_x ) || $has_prop_val( $h_rot_y ) || $has_prop_val( $h_persp ) ||
                               $has_prop_val( $h_flip_x ) || $has_prop_val( $h_flip_y ) || $has_prop_val( $h_anc_x ) ||
                               $has_prop_val( $h_anc_y ) || $has_prop_val( $t_dur );

        if ( $has_hover_transform ) {
            $explicit_dur = $this->get_device_val( $settings, 'transform_transition_duration', $device );
            if ( 'desktop' === $device || null !== $explicit_dur ) {
                $eff_dur = ( null !== $explicit_dur ) ? $explicit_dur : ( $settings['transform_transition_duration'] ?? null );
                $dur_num = ( is_array( $eff_dur ) && isset( $eff_dur['size'] ) ) ? floatval( $eff_dur['size'] ) : ( ( null !== $eff_dur && '' !== $eff_dur ) ? floatval( $eff_dur ) : 0.3 );
                $base[]  = 'transition:transform ' . $dur_num . 's ease, filter ' . $dur_num . 's ease';
            }

            $hover_transform_parts = array();

            // Rotate
            $eff_h_rot = $has_prop_val( $h_rot ) ? $h_rot : $t_rotate;
            if ( is_array( $eff_h_rot ) && isset( $eff_h_rot['size'] ) && '' !== $eff_h_rot['size'] ) {
                $hover_transform_parts[] = 'rotate(' . floatval( $eff_h_rot['size'] ) . ( $eff_h_rot['unit'] ?? 'deg' ) . ')';
            }

            // Offset X
            $eff_h_off_x = $has_prop_val( $h_off_x ) ? $h_off_x : $t_off_x;
            if ( is_array( $eff_h_off_x ) && isset( $eff_h_off_x['size'] ) && '' !== $eff_h_off_x['size'] ) {
                $hover_transform_parts[] = 'translateX(' . floatval( $eff_h_off_x['size'] ) . ( $eff_h_off_x['unit'] ?? 'px' ) . ')';
            }

            // Offset Y
            $eff_h_off_y = $has_prop_val( $h_off_y ) ? $h_off_y : $t_off_y;
            if ( is_array( $eff_h_off_y ) && isset( $eff_h_off_y['size'] ) && '' !== $eff_h_off_y['size'] ) {
                $hover_transform_parts[] = 'translateY(' . floatval( $eff_h_off_y['size'] ) . ( $eff_h_off_y['unit'] ?? 'px' ) . ')';
            }

            // Scale
            $eff_h_scale = $has_prop_val( $h_scale ) ? $h_scale : $t_scale;
            if ( null !== $eff_h_scale && '' !== $eff_h_scale ) {
                $hs_val = is_array( $eff_h_scale ) ? ( $eff_h_scale['size'] ?? '' ) : $eff_h_scale;
                if ( '' !== $hs_val ) {
                    $hover_transform_parts[] = 'scale(' . floatval( $hs_val ) . ')';
                }
            }

            // Skew X
            $eff_h_skew_x = $has_prop_val( $h_skew_x ) ? $h_skew_x : $t_skew_x;
            if ( is_array( $eff_h_skew_x ) && isset( $eff_h_skew_x['size'] ) && '' !== $eff_h_skew_x['size'] ) {
                $hover_transform_parts[] = 'skewX(' . floatval( $eff_h_skew_x['size'] ) . ( $eff_h_skew_x['unit'] ?? 'deg' ) . ')';
            }

            // Skew Y
            $eff_h_skew_y = $has_prop_val( $h_skew_y ) ? $h_skew_y : $t_skew_y;
            if ( is_array( $eff_h_skew_y ) && isset( $eff_h_skew_y['size'] ) && '' !== $eff_h_skew_y['size'] ) {
                $hover_transform_parts[] = 'skewY(' . floatval( $eff_h_skew_y['size'] ) . ( $eff_h_skew_y['unit'] ?? 'deg' ) . ')';
            }

            // Rotate X
            $eff_h_rot_x = $has_prop_val( $h_rot_x ) ? $h_rot_x : $t_rotate_x;
            $h_rx_val    = ( is_array( $eff_h_rot_x ) && isset( $eff_h_rot_x['size'] ) ) ? floatval( $eff_h_rot_x['size'] ) : ( ( null !== $eff_h_rot_x && '' !== $eff_h_rot_x ) ? floatval( $eff_h_rot_x ) : null );
            if ( null !== $h_rx_val ) {
                $hover[] = '--lk-transform-rotate-x:' . $h_rx_val . 'deg';
            }

            // Rotate Y
            $eff_h_rot_y = $has_prop_val( $h_rot_y ) ? $h_rot_y : $t_rotate_y;
            $h_ry_val    = ( is_array( $eff_h_rot_y ) && isset( $eff_h_rot_y['size'] ) ) ? floatval( $eff_h_rot_y['size'] ) : ( ( null !== $eff_h_rot_y && '' !== $eff_h_rot_y ) ? floatval( $eff_h_rot_y ) : null );
            if ( null !== $h_ry_val ) {
                $hover[] = '--lk-transform-rotate-y:' . $h_ry_val . 'deg';
            }

            // Flip X
            $eff_h_flip_x = $has_prop_val( $h_flip_x ) ? $h_flip_x : $t_flip_x;
            if ( 'yes' === $eff_h_flip_x || true === $eff_h_flip_x ) {
                $hover[] = '--lk-transform-flip-x:-1';
            } elseif ( 'yes' === $t_flip_x || true === $t_flip_x ) {
                $hover[] = '--lk-transform-flip-x:1';
            }

            // Flip Y
            $eff_h_flip_y = $has_prop_val( $h_flip_y ) ? $h_flip_y : $t_flip_y;
            if ( 'yes' === $eff_h_flip_y || true === $eff_h_flip_y ) {
                $hover[] = '--lk-transform-flip-y:-1';
            } elseif ( 'yes' === $t_flip_y || true === $t_flip_y ) {
                $hover[] = '--lk-transform-flip-y:1';
            }

            // Anchor Point on Hover
            if ( $has_prop_val( $h_anc_x ) || $has_prop_val( $h_anc_y ) ) {
                $h_ax_num = ( is_array( $h_anc_x ) && isset( $h_anc_x['size'] ) ) ? floatval( $h_anc_x['size'] ) : ( ( null !== $h_anc_x && '' !== $h_anc_x ) ? floatval( $h_anc_x ) : $ax_num );
                $h_ay_num = ( is_array( $h_anc_y ) && isset( $h_anc_y['size'] ) ) ? floatval( $h_anc_y['size'] ) : ( ( null !== $h_anc_y && '' !== $h_anc_y ) ? floatval( $h_anc_y ) : $ay_num );
                $hover[]  = 'transform-origin:' . $h_ax_num . '% ' . $h_ay_num . '%';
            }

            // Perspective on Hover
            $eff_h_persp = $has_prop_val( $h_persp ) ? $h_persp : $t_perspective;
            $h_p_val     = ( is_array( $eff_h_persp ) && isset( $eff_h_persp['size'] ) ) ? floatval( $eff_h_persp['size'] ) : ( ( null !== $eff_h_persp && '' !== $eff_h_persp ) ? floatval( $eff_h_persp ) : null );
            if ( null !== $h_p_val && $h_p_val > 0 ) {
                $hover_parent[] = 'perspective:' . $h_p_val . 'px';
            }

            $hover_motion_transform = 'translateX(var(--lk-scroll-translate-x, 0)) translateY(var(--lk-scroll-translate-y, 0)) rotate(var(--lk-scroll-rotate, 0deg)) scale(var(--lk-scroll-scale, 1)) rotateX(var(--lk-mouse-rotate-x, 0deg)) rotateY(var(--lk-mouse-rotate-y, 0deg)) translateX(var(--lk-mouse-translate-x, 0)) translateY(var(--lk-mouse-translate-y, 0)) rotateX(var(--lk-transform-rotate-x, 0deg)) rotateY(var(--lk-transform-rotate-y, 0deg)) scaleX(var(--lk-transform-flip-x, 1)) scaleY(var(--lk-transform-flip-y, 1))';
            if ( ! empty( $hover_transform_parts ) ) {
                $hover_motion_transform .= ' ' . implode( ' ', $hover_transform_parts );
            }
            $hover[] = 'transform:' . $hover_motion_transform;
        }

        // Responsive Visibility (Section 11: hide on specific device on live page)
        $hide_on_device = $settings[ 'hide_' . $device ] ?? '';
        if ( 'yes' === $hide_on_device || true === $hide_on_device ) {
            $base[] = 'display:none!important';
        }

        return array(
            'base'               => $base,
            'inner'              => $inner,
            'hover'              => $hover,
            'parent'             => $parent,
            'hover_parent'       => $hover_parent,
            'sub_rules'          => $sub_rules,
            'overlay'            => $overlay,
            'overlay_hover'      => $overlay_hover,
            'divider_top'        => $divider_top,
            'divider_top_svg'    => $divider_top_svg,
            'divider_bottom'     => $divider_bottom,
            'divider_bottom_svg' => $divider_bottom_svg,
        );
    }
    /**
     * Save as Global endpoint.
     * Creates a global template from an element.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function save_as_global( $request ) {
        $params = $request->get_json_params();
        if ( ! $params ) {
            $params = $request->get_body_params();
        }
        if ( ! is_array( $params ) ) {
            $params = array();
        }

        $name = sanitize_text_field( $params['name'] ?? '' );
        if ( empty( $name ) ) {
            return new WP_REST_Response( array( 'message' => esc_html__( 'Name is required.', 'loopkit' ) ), 400 );
        }

        $element_data = $params['elementData'] ?? null;
        if ( ! is_array( $element_data ) || empty( $element_data['elType'] ) ) {
            return new WP_REST_Response( array( 'message' => esc_html__( 'Invalid element data.', 'loopkit' ) ), 400 );
        }

        $el_type = $element_data['elType'];
        if ( ! in_array( $el_type, array( 'section', 'container' ), true ) ) {
            return new WP_REST_Response( array( 'message' => esc_html__( 'Only Sections and Containers can be saved.', 'loopkit' ) ), 400 );
        }

        // isGlobal false => a normal reusable template (Templates tab / Library), not a linked Global.
        $is_global = ! isset( $params['isGlobal'] ) || (bool) $params['isGlobal'];

        // Check for circular references: element must not already contain a global referencing itself (not possible on create, but guard anyway)
        $source_post_id = absint( $params['sourcePostId'] ?? 0 );

        // Create the global template
        $post_id = wp_insert_post( array(
            'post_type'   => 'loopkit_template',
            'post_title'  => $name,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ) );

        if ( is_wp_error( $post_id ) ) {
            return new WP_REST_Response( array( 'message' => $post_id->get_error_message() ), 500 );
        }

        $template_type = ( 'section' === $el_type ) ? 'section' : 'container';

        // Sanitize the element data
        $sanitized = $this->sanitize_elements( array( $element_data ) );
        $master_element = ! empty( $sanitized ) ? $sanitized[0] : $element_data;

        update_post_meta( $post_id, '_loopkit_template_type', $template_type );
        update_post_meta( $post_id, '_loopkit_edit_mode', 'builder' );
        update_post_meta( $post_id, '_loopkit_data', wp_slash( wp_json_encode( array( $master_element ) ) ) );

        if ( $is_global ) {
            update_post_meta( $post_id, '_loopkit_is_global', 1 );

            // Generate CSS for the global template scoped to .lk-global-{templateId}
            try {
                $this->generate_global_css( $post_id, array( $master_element ) );
            } catch ( Throwable $e ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Loopkit global CSS generation error: ' . $e->getMessage() );
                }
            }

            // Set initial usage
            $usage = array();
            if ( $source_post_id ) {
                $usage[] = $source_post_id;
            }
            update_post_meta( $post_id, '_loopkit_global_usage', $usage );
        } else {
            // Plain template: its CSS is generated from the element when it is inserted/edited.
            try {
                $this->generate_css( $post_id, array( $master_element ) );
            } catch ( Throwable $e ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Loopkit template CSS generation error: ' . $e->getMessage() );
                }
            }
        }

        return new WP_REST_Response(
            array(
                'success'    => true,
                'templateId' => $post_id,
                'name'       => $name,
                'type'       => $template_type,
                'isGlobal'   => $is_global,
            ),
            200
        );
    }

    /**
     * List all global templates.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function list_globals( $request ) {
        $globals = get_posts( array(
            'post_type'      => 'loopkit_template',
            'posts_per_page' => -1,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'meta_key'       => '_loopkit_is_global',
            'meta_value'     => '1',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $result = array();
        foreach ( $globals as $g ) {
            $tpl_type = get_post_meta( $g->ID, '_loopkit_template_type', true ) ?: 'section';
            $usage    = get_post_meta( $g->ID, '_loopkit_global_usage', true );
            $usage    = is_array( $usage ) ? $usage : array();
            $g_raw    = get_post_meta( $g->ID, '_loopkit_data', true );
            $g_els    = $g_raw ? json_decode( $g_raw, true ) : array();

            $result[ $g->ID ] = array(
                'id'         => $g->ID,
                'title'      => $g->post_title,
                'type'       => $tpl_type,
                'usageCount' => count( $usage ),
                'master'     => ! empty( $g_els ) ? $g_els[0] : null,
                'elements'   => $g_els,
                'css'        => get_post_meta( $g->ID, '_loopkit_css', true ) ?: '',
                'status'     => $g->post_status,
            );
        }

        return new WP_REST_Response( array( 'globals' => $result ), 200 );
    }

    /**
     * Delete a global template.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function delete_global( $request ) {
        $id = (int) $request['id'];
        if ( ! $id || get_post_type( $id ) !== 'loopkit_template' ) {
            return new WP_Error( 'not_found', __( 'Global template not found', 'loopkit' ), array( 'status' => 404 ) );
        }
        if ( ! current_user_can( 'delete_post', $id ) ) {
            return new WP_Error( 'forbidden', __( 'You are not allowed to delete this template', 'loopkit' ), array( 'status' => 403 ) );
        }
        $res = wp_delete_post( $id, true );
        if ( ! $res ) {
            return new WP_Error( 'delete_failed', __( 'Could not delete template', 'loopkit' ), array( 'status' => 500 ) );
        }
        return new WP_REST_Response( array( 'success' => true, 'id' => $id ), 200 );
    }

    /**
     * List non-global Section and Container templates.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function list_templates( $request ) {
        $templates = get_posts( array(
            'post_type'      => 'loopkit_template',
            'posts_per_page' => -1,
            'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ) );

        $result = array();
        foreach ( $templates as $t ) {
            $is_global = get_post_meta( $t->ID, '_loopkit_is_global', true );
            if ( ! empty( $is_global ) && '1' === (string) $is_global ) {
                continue; // Exclude globals - those belong in Globals tab
            }

            $tpl_type = strtolower( trim( (string) get_post_meta( $t->ID, '_loopkit_template_type', true ) ) );
            if ( 'section' !== $tpl_type && 'container' !== $tpl_type ) {
                continue; // Do not list Page, Header, or Footer templates
            }

            $t_raw = get_post_meta( $t->ID, '_loopkit_data', true );
            $t_els = $t_raw ? json_decode( $t_raw, true ) : array();
            $p_set = get_post_meta( $t->ID, '_loopkit_page_settings', true );

            $result[] = array(
                'id'           => $t->ID,
                'title'        => $t->post_title ?: ( ucfirst( $tpl_type ) . ' #' . $t->ID ),
                'type'         => $tpl_type,
                'date'         => get_the_modified_date( 'M j, Y', $t ),
                'modified_gmt' => $t->post_modified_gmt,
                'status'       => $t->post_status,
                'elements'     => is_array( $t_els ) ? $t_els : array(),
                'pageSettings' => is_array( $p_set ) ? $p_set : array(),
            );
        }

        return new WP_REST_Response( array( 'templates' => $result ), 200 );
    }

    /**
     * Import a template JSON and create a new non-global Section/Container template.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function import_template( $request ) {
        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            return new WP_Error( 'invalid_json', __( 'Invalid JSON payload.', 'loopkit' ), array( 'status' => 400 ) );
        }

        $type = isset( $body['type'] ) ? strtolower( sanitize_key( $body['type'] ) ) : '';
        if ( 'section' !== $type && 'container' !== $type ) {
            return new WP_Error( 'invalid_type', __( 'Template type must be Section or Container.', 'loopkit' ), array( 'status' => 400 ) );
        }

        $elements = isset( $body['elements'] ) ? $body['elements'] : null;
        if ( ! is_array( $elements ) ) {
            return new WP_Error( 'invalid_elements', __( 'Template elements must be an array.', 'loopkit' ), array( 'status' => 400 ) );
        }

        // Validate top elements have valid elType and id
        foreach ( $elements as $el ) {
            if ( ! is_array( $el ) || empty( $el['elType'] ) || empty( $el['id'] ) ) {
                return new WP_Error( 'invalid_element_schema', __( 'Each element in template must contain a valid elType and id.', 'loopkit' ), array( 'status' => 400 ) );
            }
        }

        // Deep regenerate IDs for elements & repeater items
        $fresh_elements = $this->deep_reassign_ids( $elements );

        $title = ! empty( $body['title'] ) ? sanitize_text_field( $body['title'] ) : sprintf( __( 'Imported %s', 'loopkit' ), ucfirst( $type ) );

        $post_id = wp_insert_post( array(
            'post_type'   => 'loopkit_template',
            'post_title'  => $title,
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
        ) );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        update_post_meta( $post_id, '_loopkit_template_type', $type );
        update_post_meta( $post_id, '_loopkit_edit_mode', 'builder' );
        update_post_meta( $post_id, '_loopkit_data', wp_slash( wp_json_encode( $fresh_elements ) ) );
        if ( ! empty( $body['page_settings'] ) && is_array( $body['page_settings'] ) ) {
            update_post_meta( $post_id, '_loopkit_page_settings', $this->sanitize_settings( $body['page_settings'] ) );
        }

        return new WP_REST_Response( array(
            'success'  => true,
            'id'       => $post_id,
            'title'    => $title,
            'type'     => $type,
            'date'     => get_the_modified_date( 'M j, Y', $post_id ),
            'elements' => $fresh_elements,
        ), 200 );
    }

    /**
     * Recursively regenerate IDs for elements and repeater items.
     *
     * @param array $elements List of elements.
     * @return array
     */
    private function deep_reassign_ids( $elements ) {
        if ( ! is_array( $elements ) ) {
            return array();
        }

        $result = array();
        foreach ( $elements as $el ) {
            if ( ! is_array( $el ) ) {
                continue;
            }
            $clone = $el;
            $clone['id'] = function_exists( 'loopkit_generate_id' ) ? loopkit_generate_id() : substr( md5( uniqid( '', true ) ), 0, 7 );

            // Check settings for repeater items with _id
            if ( ! empty( $clone['settings'] ) && is_array( $clone['settings'] ) ) {
                foreach ( $clone['settings'] as $k => $val ) {
                    if ( is_array( $val ) ) {
                        foreach ( $val as $rIdx => $rItem ) {
                            if ( is_array( $rItem ) && isset( $rItem['_id'] ) ) {
                                $clone['settings'][ $k ][ $rIdx ]['_id'] = 'lk_item_' . substr( md5( uniqid( '', true ) ), 0, 8 );
                            }
                        }
                    }
                }
            }

            // Recurse children
            if ( ! empty( $clone['elements'] ) && is_array( $clone['elements'] ) ) {
                $clone['elements'] = $this->deep_reassign_ids( $clone['elements'] );
            }

            $result[] = $clone;
        }

        return $result;
    }

    /**
     * Rebuild global usage index for all globals referenced by a page's elements.
     *
     * @param int   $post_id  The page/template post ID being saved.
     * @param array $elements The saved elements.
     */
    private function rebuild_global_usage( $post_id, $elements ) {
        // Collect all global template IDs referenced in this page
        $referenced_ids = array();
        $this->collect_global_ids( $elements, $referenced_ids );

        // Get all global templates
        $all_globals = get_posts( array(
            'post_type'      => 'loopkit_template',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_key'       => '_loopkit_is_global',
            'meta_value'     => '1',
            'fields'         => 'ids',
        ) );

        foreach ( $all_globals as $global_id ) {
            $usage = get_post_meta( $global_id, '_loopkit_global_usage', true );
            $usage = is_array( $usage ) ? $usage : array();

            if ( in_array( $global_id, $referenced_ids, true ) ) {
                // Add this page to usage if not already there
                if ( ! in_array( $post_id, $usage, true ) ) {
                    $usage[] = $post_id;
                }
            } else {
                // Remove this page from usage
                $usage = array_values( array_diff( $usage, array( $post_id ) ) );
            }

            update_post_meta( $global_id, '_loopkit_global_usage', $usage );
        }
    }

    /**
     * Recursively collect global template IDs from elements.
     *
     * @param array $elements     Elements to scan.
     * @param array $ids          Accumulator (by reference).
     */
    private function collect_global_ids( $elements, &$ids ) {
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
                $this->collect_global_ids( $el['elements'], $ids );
            }
        }
    }

    /**
     * Check for circular global references.
     *
     * @param array $elements        Elements to scan.
     * @param int   $target_template The template ID to check for circularity.
     * @return bool True if circular reference detected.
     */
    private function has_circular_global( $elements, $target_template ) {
        if ( ! is_array( $elements ) ) return false;
        foreach ( $elements as $el ) {
            if ( ! is_array( $el ) ) continue;
            if ( ( $el['elType'] ?? '' ) === 'global' ) {
                $tid = absint( $el['templateId'] ?? 0 );
                if ( $tid === $target_template ) {
                    return true;
                }
                // Recursively check the referenced global's content
                $ref_data = get_post_meta( $tid, '_loopkit_data', true );
                $ref_els  = $ref_data ? json_decode( $ref_data, true ) : array();
                if ( is_array( $ref_els ) && $this->has_circular_global( $ref_els, $target_template ) ) {
                    return true;
                }
            }
            if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
                if ( $this->has_circular_global( $el['elements'], $target_template ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Generate CSS for a global template, scoped to .lk-global-{templateId}.
     *
     * @param int   $template_id Global template post ID.
     * @param array $elements    Elements array (master content).
     */
    private function generate_global_css( $template_id, $elements ) {
        $flat_elements = array();
        $this->flatten_elements( $elements, $flat_elements );

        $wrapper_id = '.lk-global-' . $template_id;

        // 1. Desktop rules (base)
        $desktop_css = '';
        foreach ( $flat_elements as $el ) {
            $id = $el['id'] ?? '';
            if ( empty( $id ) ) continue;

            $el_cls         = '.lk-element-' . esc_attr( $id );
            $selector       = $wrapper_id . ' ' . $el_cls . ', ' . $el_cls;
            $inner_selector = $wrapper_id . ' ' . $el_cls . ' > .lk-section-inner, ' . $el_cls . ' > .lk-section-inner, ' .
                              $wrapper_id . ' ' . $el_cls . ' > .lk-con-inner, ' . $el_cls . ' > .lk-con-inner, ' .
                              $wrapper_id . ' ' . $el_cls . ' > .lk-container-inner, ' . $el_cls . ' > .lk-container-inner';

            $d_res = $this->build_device_rules( $el, 'desktop' );
            if ( ! empty( $d_res['base'] ) ) {
                $desktop_css .= $selector . ' {' . implode( ';', $d_res['base'] ) . ";}\n";
            }
            if ( ! empty( $d_res['inner'] ) ) {
                $desktop_css .= $inner_selector . ' {' . implode( ';', $d_res['inner'] ) . ";}\n";
            }
            if ( ! empty( $d_res['hover'] ) ) {
                $hover_selector = ( $wrapper_id ? $wrapper_id . ' ' . $el_cls . ':hover, ' : '' ) . $el_cls . ':hover';
                $desktop_css .= $hover_selector . ' {' . implode( ';', $d_res['hover'] ) . ";}\n";
            }
            if ( ! empty( $d_res['parent'] ) ) {
                $desktop_css .= ':has(>' . $el_cls . ') {' . implode( ';', $d_res['parent'] ) . ";}\n";
            }
            if ( ! empty( $d_res['hover_parent'] ) ) {
                $desktop_css .= ':has(>' . $el_cls . ':hover) {' . implode( ';', $d_res['hover_parent'] ) . ";}\n";
            }
            if ( ! empty( $d_res['overlay'] ) ) {
                $desktop_css .= $wrapper_id . ' ' . $el_cls . ' > .lk-background-overlay, ' . $el_cls . ' > .lk-background-overlay {' . implode( ';', $d_res['overlay'] ) . ";}\n";
            }
            if ( ! empty( $d_res['overlay_hover'] ) ) {
                $desktop_css .= $wrapper_id . ' ' . $el_cls . ':hover > .lk-background-overlay, ' . $el_cls . ':hover > .lk-background-overlay {' . implode( ';', $d_res['overlay_hover'] ) . ";}\n";
            }
            if ( ! empty( $d_res['divider_top'] ) ) {
                $desktop_css .= $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--top, ' . $el_cls . ' > .lk-shape-divider--top {' . implode( ';', $d_res['divider_top'] ) . ";}\n";
            }
            if ( ! empty( $d_res['divider_top_svg'] ) ) {
                $desktop_css .= $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--top svg, ' . $el_cls . ' > .lk-shape-divider--top svg {' . implode( ';', $d_res['divider_top_svg'] ) . ";}\n";
            }
            if ( ! empty( $d_res['divider_bottom'] ) ) {
                $desktop_css .= $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--bottom, ' . $el_cls . ' > .lk-shape-divider--bottom {' . implode( ';', $d_res['divider_bottom'] ) . ";}\n";
            }
            if ( ! empty( $d_res['divider_bottom_svg'] ) ) {
                $desktop_css .= $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--bottom svg, ' . $el_cls . ' > .lk-shape-divider--bottom svg {' . implode( ';', $d_res['divider_bottom_svg'] ) . ";}\n";
            }
            if ( ! empty( $d_res['sub_rules'] ) ) {
                foreach ( $d_res['sub_rules'] as $sub_sel => $s_rules ) {
                    if ( ! empty( $s_rules ) ) {
                        $full_sub = ( $wrapper_id ? $wrapper_id . ' ' . $el_cls . $sub_sel . ', ' : '' ) . $el_cls . $sub_sel;
                        $desktop_css .= $full_sub . ' {' . implode( ';', $s_rules ) . ";}\n";
                    }
                }
            }
        }

        $full_css = $desktop_css;

        // 2. Responsive Breakpoints
        $breakpoints = array(
            array( 'id' => 'widescreen',   'query' => '(min-width: 2400px)' ),
            array( 'id' => 'laptop',       'query' => '(max-width: 1366px)' ),
            array( 'id' => 'tablet_extra', 'query' => '(max-width: 1200px)' ),
            array( 'id' => 'tablet',       'query' => '(max-width: 1024px)' ),
            array( 'id' => 'mobile_extra', 'query' => '(max-width: 880px)' ),
            array( 'id' => 'mobile',       'query' => '(max-width: 767px)' ),
        );

        foreach ( $breakpoints as $bp ) {
            $dev_id  = $bp['id'];
            $dev_css = '';

            foreach ( $flat_elements as $el ) {
                $id = $el['id'] ?? '';
                if ( empty( $id ) ) continue;

                $el_cls         = '.lk-element-' . esc_attr( $id );
                $selector       = $wrapper_id . ' ' . $el_cls . ', ' . $el_cls;
                $inner_selector = $wrapper_id . ' ' . $el_cls . ' > .lk-section-inner, ' . $el_cls . ' > .lk-section-inner, ' .
                                  $wrapper_id . ' ' . $el_cls . ' > .lk-con-inner, ' . $el_cls . ' > .lk-con-inner, ' .
                                  $wrapper_id . ' ' . $el_cls . ' > .lk-container-inner, ' . $el_cls . ' > .lk-container-inner';

                $res = $this->build_device_rules( $el, $dev_id );
                if ( ! empty( $res['base'] ) ) {
                    $dev_css .= '  ' . $selector . ' {' . implode( ';', $res['base'] ) . ";}\n";
                }
                if ( ! empty( $res['inner'] ) ) {
                    $dev_css .= '  ' . $inner_selector . ' {' . implode( ';', $res['inner'] ) . ";}\n";
                }
                if ( ! empty( $res['hover'] ) ) {
                    $b_hover_selector = ( $wrapper_id ? $wrapper_id . ' ' . $el_cls . ':hover, ' : '' ) . $el_cls . ':hover';
                    $dev_css .= '  ' . $b_hover_selector . ' {' . implode( ';', $res['hover'] ) . ";}\n";
                }
                if ( ! empty( $res['parent'] ) ) {
                    $dev_css .= '  :has(>' . $el_cls . ') {' . implode( ';', $res['parent'] ) . ";}\n";
                }
                if ( ! empty( $res['hover_parent'] ) ) {
                    $dev_css .= '  :has(>' . $el_cls . ':hover) {' . implode( ';', $res['hover_parent'] ) . ";}\n";
                }
                if ( ! empty( $res['overlay'] ) ) {
                    $dev_css .= '  ' . $wrapper_id . ' ' . $el_cls . ' > .lk-background-overlay, ' . $el_cls . ' > .lk-background-overlay {' . implode( ';', $res['overlay'] ) . ";}\n";
                }
                if ( ! empty( $res['overlay_hover'] ) ) {
                    $dev_css .= '  ' . $wrapper_id . ' ' . $el_cls . ':hover > .lk-background-overlay, ' . $el_cls . ':hover > .lk-background-overlay {' . implode( ';', $res['overlay_hover'] ) . ";}\n";
                }
                if ( ! empty( $res['divider_top'] ) ) {
                    $dev_css .= '  ' . $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--top, ' . $el_cls . ' > .lk-shape-divider--top {' . implode( ';', $res['divider_top'] ) . ";}\n";
                }
                if ( ! empty( $res['divider_top_svg'] ) ) {
                    $dev_css .= '  ' . $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--top svg, ' . $el_cls . ' > .lk-shape-divider--top svg {' . implode( ';', $res['divider_top_svg'] ) . ";}\n";
                }
                if ( ! empty( $res['divider_bottom'] ) ) {
                    $dev_css .= '  ' . $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--bottom, ' . $el_cls . ' > .lk-shape-divider--bottom {' . implode( ';', $res['divider_bottom'] ) . ";}\n";
                }
                if ( ! empty( $res['divider_bottom_svg'] ) ) {
                    $dev_css .= '  ' . $wrapper_id . ' ' . $el_cls . ' > .lk-shape-divider--bottom svg, ' . $el_cls . ' > .lk-shape-divider--bottom svg {' . implode( ';', $res['divider_bottom_svg'] ) . ";}\n";
                }
                if ( ! empty( $res['sub_rules'] ) ) {
                    foreach ( $res['sub_rules'] as $sub_sel => $s_rules ) {
                        if ( ! empty( $s_rules ) ) {
                            $full_sub = ( $wrapper_id ? $wrapper_id . ' ' . $el_cls . $sub_sel . ', ' : '' ) . $el_cls . $sub_sel;
                            $dev_css .= '  ' . $full_sub . ' {' . implode( ';', $s_rules ) . ";}\n";
                        }
                    }
                }
            }

            if ( ! empty( $dev_css ) ) {
                $full_css .= "\n@media " . $bp['query'] . " {\n" . $dev_css . "}\n";
            }
        }

        // 3. Custom CSS
        $custom_css_output = '';
        foreach ( $flat_elements as $el ) {
            $wrapper_selector = '.lk-global-' . $template_id . ' .lk-element-' . $el['id'];
            if ( ! empty( $el['settings']['custom_css'] ) ) {
                $raw_custom   = $el['settings']['custom_css'];
                $clean_custom = preg_replace( '/<\/?(style|script)[^>]*>/i', '', (string) $raw_custom );
                $compiled     = preg_replace( '/\bselector\b|\{\{WRAPPER\}\}/i', $wrapper_selector, $clean_custom );
                if ( ! empty( $el['_id'] ) ) {
                    $compiled = str_replace( '{{CURRENT_ITEM}}', '.lk-repeater-item-' . sanitize_html_class( $el['_id'] ), $compiled );
                }
                if ( trim( $compiled ) ) {
                    $custom_css_output .= "/* Custom CSS: " . $el['id'] . " */\n" . $compiled . "\n";
                }
            }
            if ( ! empty( $el['settings'] ) && is_array( $el['settings'] ) ) {
                foreach ( $el['settings'] as $field_val ) {
                    if ( is_array( $field_val ) ) {
                        foreach ( $field_val as $sub_item ) {
                            if ( is_array( $sub_item ) && ! empty( $sub_item['_id'] ) && ! empty( $sub_item['custom_css'] ) ) {
                                $item_raw  = preg_replace( '/<\/?(style|script)[^>]*>/i', '', (string) $sub_item['custom_css'] );
                                $item_comp = preg_replace( '/\bselector\b|\{\{WRAPPER\}\}/i', $wrapper_selector, $item_raw );
                                $item_comp = str_replace( '{{CURRENT_ITEM}}', '.lk-repeater-item-' . sanitize_html_class( $sub_item['_id'] ), $item_comp );
                                if ( trim( $item_comp ) ) {
                                    $custom_css_output .= "/* Repeater Custom CSS: " . sanitize_text_field( $sub_item['_id'] ) . " */\n" . $item_comp . "\n";
                                }
                            }
                        }
                    }
                }
            }
        }

        if ( ! empty( $custom_css_output ) ) {
            $full_css .= "\n/* Element Custom CSS */\n" . $custom_css_output;
        }

        if ( class_exists( 'Loopkit_Assets' ) && Loopkit_Assets::is_optimization_enabled() ) {
            $full_css = Loopkit_Assets::minify_css( $full_css );
        }

        $advanced = get_option( 'loopkit_advanced_settings', array() );
        $method   = $advanced['css_print_method'] ?? 'external';

        if ( 'external' === $method && ! empty( $full_css ) ) {
            $upload_dir = wp_upload_dir();
            if ( empty( $upload_dir['error'] ) ) {
                $css_dir = trailingslashit( $upload_dir['basedir'] ) . 'loopkit/css/';
                if ( ! is_dir( $css_dir ) ) {
                    wp_mkdir_p( $css_dir );
                }
                if ( is_dir( $css_dir ) && wp_is_writable( $css_dir ) ) {
                    $css_file = $css_dir . 'post-' . $template_id . '.css';
                    @file_put_contents( $css_file, $full_css );
                }
            }
        }

        update_post_meta( $template_id, '_loopkit_css', $full_css );
        update_post_meta( $template_id, '_loopkit_css_time', time() );

        return $full_css;
    }

    /**
     * Get revisions for a document.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_document_revisions( $request ) {
        $post_id   = (int) $request['id'];
        $revisions = wp_get_post_revisions( $post_id, array( 'posts_per_page' => 50 ) );

        $items = array();
        if ( ! empty( $revisions ) ) {
            foreach ( $revisions as $rev ) {
                $author_id    = $rev->post_author;
                $rev_data     = get_metadata( 'post', $rev->ID, '_loopkit_data', true );
                $rev_settings = get_metadata( 'post', $rev->ID, '_loopkit_page_settings', true );
                $is_autosave  = wp_is_post_autosave( $rev->ID );

                $elements = array();
                // A revision that never had Loopkit data saved against it must say so.
                // Substituting the live document here would show today's page under an
                // older timestamp, which misrepresents what that revision actually holds.
                $has_data = ! empty( $rev_data );
                if ( $has_data ) {
                    $elements = is_string( $rev_data ) ? json_decode( $rev_data, true ) : $rev_data;
                }

                $time_diff = human_time_diff( strtotime( $rev->post_date_gmt ), current_time( 'timestamp', 1 ) );

                $items[] = array(
                    'id'          => $rev->ID,
                    'author_name' => get_the_author_meta( 'display_name', $author_id ) ?: __( 'User', 'loopkit' ),
                    'avatar_url'  => get_avatar_url( $author_id, array( 'size' => 48 ) ),
                    'time_ago'    => sprintf( __( '%s ago', 'loopkit' ), $time_diff ),
                    'date_str'    => get_the_date( 'M j, Y @ H:i', $rev->ID ),
                    'type'        => $is_autosave ? 'Autosave' : 'Revision',
                    'has_data'    => (bool) $has_data,
                    'elements'    => is_array( $elements ) ? $elements : array(),
                    'settings'    => is_array( $rev_settings ) ? $rev_settings : array(),
                );
            }
        }

        return new WP_REST_Response(
            array(
                'success'   => true,
                'revisions' => $items,
            ),
            200
        );
    }

    /**
     * Store _loopkit_data on post revision creation.
     *
     * @param int $revision_id Revision ID.
     */
    public function on_put_post_revision( $revision_id ) {
        $parent_id = wp_is_post_revision( $revision_id );
        if ( $parent_id ) {
            $loopkit_data     = get_post_meta( $parent_id, '_loopkit_data', true );
            $loopkit_settings = get_post_meta( $parent_id, '_loopkit_page_settings', true );
            if ( ! empty( $loopkit_data ) ) {
                // get_post_meta() hands the value back unslashed, and update_metadata()
                // unslashes again â€” so it has to be re-slashed here or the escapes in the
                // JSON are stripped and the stored revision cannot be decoded.
                update_metadata( 'post', $revision_id, '_loopkit_data', wp_slash( $loopkit_data ) );
            }
            if ( ! empty( $loopkit_settings ) ) {
                update_metadata( 'post', $revision_id, '_loopkit_page_settings', $loopkit_settings );
            }
        }
    }

    /**
     * Get site settings endpoint.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_site_settings( $request ) {
        $settings = class_exists( 'Loopkit_Site_Settings' ) ? Loopkit_Site_Settings::get_settings() : array();
        $site_icon_id = (int) get_option( 'site_icon' );
        $site_icon_url = $site_icon_id ? wp_get_attachment_image_url( $site_icon_id, 'full' ) : '';

        return new WP_REST_Response(
            array(
                'success'      => true,
                'settings'     => $settings,
                'siteIdentity' => array(
                    'title'         => get_option( 'blogname' ),
                    'tagline'       => get_option( 'blogdescription' ),
                    'site_icon'     => $site_icon_id,
                    'site_icon_url' => $site_icon_url,
                ),
            ),
            200
        );
    }

    /**
     * Save site settings endpoint.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function save_site_settings( $request ) {
        $params = $request->get_json_params();
        if ( ! $params ) {
            $params = $request->get_body_params();
        }
        if ( ! is_array( $params ) ) {
            $params = array();
        }

        // 1. Site Identity (Native WordPress options)
        if ( isset( $params['siteIdentity'] ) && is_array( $params['siteIdentity'] ) ) {
            if ( isset( $params['siteIdentity']['title'] ) ) {
                update_option( 'blogname', sanitize_text_field( $params['siteIdentity']['title'] ) );
            }
            if ( isset( $params['siteIdentity']['tagline'] ) ) {
                update_option( 'blogdescription', sanitize_text_field( $params['siteIdentity']['tagline'] ) );
            }
            if ( isset( $params['siteIdentity']['site_icon'] ) ) {
                update_option( 'site_icon', absint( $params['siteIdentity']['site_icon'] ) );
            }
        }

        // 2. Kit Settings
        $new_settings = $params['settings'] ?? array();
        if ( is_array( $new_settings ) && ! empty( $new_settings ) ) {
            // Validate Breakpoints
            if ( class_exists( 'Loopkit_Site_Settings' ) ) {
                $val_res = Loopkit_Site_Settings::validate_breakpoints( $new_settings );
                if ( is_wp_error( $val_res ) ) {
                    return new WP_REST_Response(
                        array(
                            'success' => false,
                            'message' => $val_res->get_error_message(),
                        ),
                        400
                    );
                }
            }

            // Check if breakpoints changed to regenerate page CSS
            $current_settings = class_exists( 'Loopkit_Site_Settings' ) ? Loopkit_Site_Settings::get_settings() : array();
            $bp_changed = false;
            $bp_keys = array( 'breakpoint_widescreen', 'breakpoint_laptop', 'breakpoint_tablet_extra', 'breakpoint_tablet', 'breakpoint_mobile_extra', 'breakpoint_mobile' );
            foreach ( $bp_keys as $bk ) {
                if ( isset( $new_settings[ $bk ] ) && isset( $current_settings[ $bk ] ) && (int) $new_settings[ $bk ] !== (int) $current_settings[ $bk ] ) {
                    $bp_changed = true;
                    break;
                }
            }

            $sanitizer = new Loopkit_Site_Settings();
            $sanitized = $sanitizer->sanitize( $new_settings );
            update_option( Loopkit_Site_Settings::OPTION_NAME, $sanitized );
            update_option( Loopkit_Site_Settings::LEGACY_OPTION, $sanitized );

            // Rebuild global.css
            Loopkit_Site_Settings::save_global_css_file();

            // If breakpoints changed, regenerate compiled CSS for all Loopkit builder posts
            if ( $bp_changed ) {
                $posts = get_posts( array(
                    'post_type'      => array( 'page', 'post', 'loopkit_template' ),
                    'post_status'    => 'any',
                    'meta_key'       => '_loopkit_edit_mode',
                    'meta_value'     => 'builder',
                    'posts_per_page' => 100,
                    'fields'         => 'ids',
                ) );
                foreach ( $posts as $pid ) {
                    $this->generate_css( $pid );
                }
            }
        }

        return $this->get_site_settings( $request );
    }

    /**
     * Get user preferences endpoint.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_user_preferences( $request ) {
        $user_id = get_current_user_id();
        $defaults = array(
            'ui_theme'             => 'auto',
            'panel_width'          => 300,
            'show_editing_handles' => 'yes',
            'lightbox_in_editor'   => 'yes',
            'exit_to'              => 'dashboard',
        );

        $saved = $user_id ? get_user_meta( $user_id, '_loopkit_user_preferences', true ) : array();
        $prefs = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );

        return new WP_REST_Response(
            array(
                'success'     => true,
                'preferences' => $prefs,
            ),
            200
        );
    }

    /**
     * Save user preferences endpoint.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function save_user_preferences( $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_REST_Response( array( 'message' => 'User not logged in' ), 401 );
        }

        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            $raw_body = $request->get_body();
            if ( ! empty( $raw_body ) ) {
                $decoded = json_decode( $raw_body, true );
                if ( is_array( $decoded ) ) {
                    $params = $decoded;
                }
            }
        }
        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }
        if ( ! is_array( $params ) ) {
            $params = array();
        }
        if ( isset( $params['preferences'] ) && is_array( $params['preferences'] ) ) {
            $params = $params['preferences'];
        }

        $cur_prefs = get_user_meta( $user_id, '_loopkit_user_preferences', true );
        $cur_prefs = is_array( $cur_prefs ) ? $cur_prefs : array();

        if ( isset( $params['ui_theme'] ) ) {
            $theme = sanitize_key( $params['ui_theme'] );
            if ( in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ) {
                $cur_prefs['ui_theme'] = $theme;
            }
        }

        if ( isset( $params['panel_width'] ) ) {
            $w = intval( $params['panel_width'] );
            if ( $w >= 240 && $w <= 600 ) {
                $cur_prefs['panel_width'] = $w;
            }
        }

        if ( isset( $params['show_editing_handles'] ) || isset( $params['show_edit_handles'] ) ) {
            $val = $params['show_editing_handles'] ?? $params['show_edit_handles'];
            $cur_prefs['show_editing_handles'] = ( 'yes' === $val || true === $val || 1 === $val || '1' === $val ) ? 'yes' : 'no';
            $cur_prefs['show_edit_handles'] = $cur_prefs['show_editing_handles'] === 'yes';
        }

        if ( isset( $params['lightbox_in_editor'] ) ) {
            $val = $params['lightbox_in_editor'];
            $cur_prefs['lightbox_in_editor'] = ( 'yes' === $val || true === $val || 1 === $val || '1' === $val ) ? 'yes' : 'no';
        }

        if ( isset( $params['exit_to'] ) ) {
            $exit = sanitize_key( $params['exit_to'] );
            if ( in_array( $exit, array( 'dashboard', 'pages', 'frontend' ), true ) ) {
                $cur_prefs['exit_to'] = $exit;
            }
        }

        update_user_meta( $user_id, '_loopkit_user_preferences', $cur_prefs );

        return new WP_REST_Response(
            array(
                'success'     => true,
                'preferences' => $cur_prefs,
            ),
            200
        );
    }

    /**
     * Get dynamic Finder items endpoint.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_finder_items( $request ) {
        // Recently edited (5)
        $recent_posts = get_posts( array(
            'post_type'      => array( 'page', 'post', 'loopkit_template' ),
            'post_status'    => 'any',
            'meta_key'       => '_loopkit_edit_mode',
            'meta_value'     => 'builder',
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'posts_per_page' => 5,
        ) );

        if ( empty( $recent_posts ) ) {
            $recent_posts = get_posts( array(
                'post_type'      => array( 'page', 'post' ),
                'post_status'    => 'any',
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'posts_per_page' => 5,
            ) );
        }

        $recent_items = array();
        foreach ( $recent_posts as $rp ) {
            $recent_items[] = array(
                'id'       => $rp->ID,
                'title'    => $rp->post_title ?: sprintf( '(#%d) Untitled', $rp->ID ),
                'type'     => $rp->post_type,
                'edit_url' => admin_url( 'post.php?post=' . $rp->ID . '&action=loopkit' ),
                'date'     => get_the_modified_date( 'M j, Y', $rp ),
            );
        }

        // All loopkit pages & templates (up to 50)
        $all_posts = get_posts( array(
            'post_type'      => array( 'page', 'post', 'loopkit_template' ),
            'post_status'    => 'any',
            'meta_key'       => '_loopkit_edit_mode',
            'meta_value'     => 'builder',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'posts_per_page' => 50,
        ) );

        if ( empty( $all_posts ) ) {
            $all_posts = get_posts( array(
                'post_type'      => array( 'page', 'post' ),
                'post_status'    => 'any',
                'orderby'        => 'title',
                'order'          => 'ASC',
                'posts_per_page' => 50,
            ) );
        }

        $all_items = array();
        foreach ( $all_posts as $ap ) {
            $all_items[] = array(
                'id'       => $ap->ID,
                'title'    => $ap->post_title ?: sprintf( '(#%d) Untitled', $ap->ID ),
                'type'     => $ap->post_type,
                'edit_url' => admin_url( 'post.php?post=' . $ap->ID . '&action=loopkit' ),
            );
        }

        // Combine into unified items array with 'group' for Finder modal
        $items = array();
        foreach ( $recent_posts as $rp ) {
            $items[] = array(
                'id'     => 'recent_' . $rp->ID,
                'group'  => 'Recently Edited',
                'title'  => $rp->post_title ?: sprintf( '(#%d) Untitled', $rp->ID ),
                'icon'   => 'dashicons-edit',
                'action' => 'url',
                'url'    => admin_url( 'post.php?post=' . $rp->ID . '&action=loopkit' ),
                'hint'   => get_the_modified_date( 'M j', $rp ),
            );
        }
        foreach ( $all_posts as $ap ) {
            $items[] = array(
                'id'     => 'post_' . $ap->ID,
                'group'  => 'Edit',
                'title'  => $ap->post_title ?: sprintf( '(#%d) Untitled', $ap->ID ),
                'icon'   => 'dashicons-admin-page',
                'action' => 'url',
                'url'    => admin_url( 'post.php?post=' . $ap->ID . '&action=loopkit' ),
            );
        }

        return new WP_REST_Response(
            array(
                'success'  => true,
                'items'    => $items,
                'recently' => $recent_items,
                'all'      => $all_items,
            ),
            200
        );
    }
}

/**
 * Generate a random 7-character element ID.
 *
 * @return string
 */
function loopkit_generate_id() {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $id    = '';
    for ( $i = 0; $i < 7; $i++ ) {
        $id .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
    }
    return $id;
}

new Loopkit_Rest_Api();
