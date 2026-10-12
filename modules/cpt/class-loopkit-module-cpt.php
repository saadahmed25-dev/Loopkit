<?php
/**
 * Loopkit Post Types module (Loopkit's own, not a separate plugin).
 *
 * Switched on in Loopkit > Tools > Modules; this file is only loaded when it is on.
 *
 *   • Post types and taxonomies are built on one screen and stored in options.
 *   • They are registered on every load, so they behave like any other post type: menus,
 *     archives, permalinks, REST, the block editor, and Loopkit's own Display Conditions,
 *     Single/Archive templates and the Loop Grid query.
 *   • Their labels and terms are available to Dynamic Tags.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Module_CPT {

    const OPTION_TYPES      = 'loopkit_post_types';
    const OPTION_TAXONOMIES = 'loopkit_taxonomies';

    /**
     * What a post type can support.
     *
     * @return array
     */
    public static function supports_list() {
        return array(
            'title'           => __( 'Title', 'loopkit' ),
            'editor'          => __( 'Editor', 'loopkit' ),
            'thumbnail'       => __( 'Featured image', 'loopkit' ),
            'excerpt'         => __( 'Excerpt', 'loopkit' ),
            'author'          => __( 'Author', 'loopkit' ),
            'comments'        => __( 'Comments', 'loopkit' ),
            'custom-fields'   => __( 'Custom fields', 'loopkit' ),
            'revisions'       => __( 'Revisions', 'loopkit' ),
            'page-attributes' => __( 'Page attributes', 'loopkit' ),
        );
    }

    /**
     * A few common dashicons for the picker.
     *
     * @return array
     */
    public static function icon_list() {
        return array(
            'dashicons-admin-post', 'dashicons-admin-page', 'dashicons-portfolio', 'dashicons-cart',
            'dashicons-store', 'dashicons-building', 'dashicons-groups', 'dashicons-businessman',
            'dashicons-calendar-alt', 'dashicons-location', 'dashicons-format-gallery', 'dashicons-format-video',
            'dashicons-book', 'dashicons-megaphone', 'dashicons-star-filled', 'dashicons-heart',
            'dashicons-hammer', 'dashicons-food', 'dashicons-car', 'dashicons-tickets-alt',
        );
    }

    /**
     * Start the module.
     */
    public function init() {
        add_action( 'init', array( $this, 'register_all' ), 5 );
        add_action( 'admin_init', array( $this, 'hook_admin_columns' ) );
        add_action( 'admin_menu', array( $this, 'register_menu' ), 30 );
        add_action( 'admin_init', array( $this, 'handle_save' ) );
        add_filter( 'loopkit_dynamic_tags', array( $this, 'add_dynamic_tags' ) );
    }

    /**
     * Post types defined in this module.
     *
     * @return array
     */
    public static function get_post_types() {
        $types = get_option( self::OPTION_TYPES, array() );
        return is_array( $types ) ? $types : array();
    }

    /**
     * Taxonomies defined in this module.
     *
     * @return array
     */
    public static function get_taxonomies() {
        $taxes = get_option( self::OPTION_TAXONOMIES, array() );
        return is_array( $taxes ) ? $taxes : array();
    }

    /**
     * Register everything with WordPress.
     */
    public function register_all() {
        foreach ( self::get_post_types() as $type ) {
            $slug = sanitize_key( $type['slug'] ?? '' );
            if ( '' === $slug || post_type_exists( $slug ) ) {
                continue;
            }
            $single = $type['singular'] ?? ucfirst( $slug );
            $plural = $type['plural'] ?? $single . 's';

            register_post_type( $slug, array(
                'labels'       => array(
                    'name'               => $plural,
                    'singular_name'      => $single,
                    'menu_name'          => $plural,
                    'add_new'            => __( 'Add New', 'loopkit' ),
                    /* translators: %s: singular name */
                    'add_new_item'       => sprintf( __( 'Add %s', 'loopkit' ), $single ),
                    /* translators: %s: singular name */
                    'edit_item'          => sprintf( __( 'Edit %s', 'loopkit' ), $single ),
                    /* translators: %s: singular name */
                    'new_item'           => sprintf( __( 'New %s', 'loopkit' ), $single ),
                    /* translators: %s: singular name */
                    'view_item'          => sprintf( __( 'View %s', 'loopkit' ), $single ),
                    /* translators: %s: plural name */
                    'search_items'       => sprintf( __( 'Search %s', 'loopkit' ), $plural ),
                    /* translators: %s: plural name */
                    'not_found'          => sprintf( __( 'No %s found', 'loopkit' ), strtolower( $plural ) ),
                    'all_items'          => $plural,
                ),
                'public'       => empty( $type['private'] ),
                'has_archive'  => ! empty( $type['has_archive'] ),
                'hierarchical' => ! empty( $type['hierarchical'] ),
                'menu_icon'    => $type['icon'] ?? 'dashicons-admin-post',
                'menu_position' => isset( $type['menu_position'] ) && '' !== $type['menu_position'] ? (int) $type['menu_position'] : null,
                'supports'     => ! empty( $type['supports'] ) ? (array) $type['supports'] : array( 'title', 'editor', 'thumbnail' ),
                'show_in_rest' => ! empty( $type['show_in_rest'] ),
                'taxonomies'   => (array) ( $type['taxonomies'] ?? array() ),
                'rewrite'      => array( 'slug' => $type['rewrite'] ?: $slug, 'with_front' => false ),
            ) );
        }

        foreach ( self::get_taxonomies() as $tax ) {
            $slug = sanitize_key( $tax['slug'] ?? '' );
            if ( '' === $slug || taxonomy_exists( $slug ) ) {
                continue;
            }
            $single = $tax['singular'] ?? ucfirst( $slug );
            $plural = $tax['plural'] ?? $single . 's';

            register_taxonomy( $slug, (array) ( $tax['post_types'] ?? array( 'post' ) ), array(
                'labels'       => array(
                    'name'          => $plural,
                    'singular_name' => $single,
                    'menu_name'     => $plural,
                    /* translators: %s: plural name */
                    'search_items'  => sprintf( __( 'Search %s', 'loopkit' ), $plural ),
                    /* translators: %s: singular name */
                    'edit_item'     => sprintf( __( 'Edit %s', 'loopkit' ), $single ),
                    /* translators: %s: singular name */
                    'add_new_item'  => sprintf( __( 'Add %s', 'loopkit' ), $single ),
                ),
                'public'       => true,
                'hierarchical' => ! empty( $tax['hierarchical'] ),
                'show_in_rest' => ! empty( $tax['show_in_rest'] ),
                'show_admin_column' => ! empty( $tax['admin_column'] ),
                'rewrite'      => array( 'slug' => $tax['rewrite'] ?: $slug, 'with_front' => false ),
            ) );
        }
    }

    /**
     * Admin screen.
     */
    public function register_menu() {
        add_menu_page(
            __( 'Post Types', 'loopkit' ),
            __( 'Post Types', 'loopkit' ),
            'manage_options',
            'loopkit-post-types',
            array( $this, 'render_page' ),
            'dashicons-screenoptions',
            28
        );

        add_submenu_page( 'loopkit-post-types', __( 'All Types', 'loopkit' ), __( 'All Types', 'loopkit' ), 'manage_options', 'loopkit-post-types', array( $this, 'render_page' ) );
        add_submenu_page( 'loopkit-post-types', __( 'Add Post Type', 'loopkit' ), __( 'Add Post Type', 'loopkit' ), 'manage_options', 'loopkit-post-types&type=new', array( $this, 'render_page' ) );
        add_submenu_page( 'loopkit-post-types', __( 'Add Taxonomy', 'loopkit' ), __( 'Add Taxonomy', 'loopkit' ), 'manage_options', 'loopkit-post-types&tax=new', array( $this, 'render_page' ) );
    }

    /**
     * Save / delete a post type or taxonomy.
     */
    public function handle_save() {
        if ( empty( $_POST['loopkit_cpt_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage post types.', 'loopkit' ) );
        }
        check_admin_referer( 'loopkit_cpt' );

        $action = sanitize_key( wp_unslash( $_POST['loopkit_cpt_action'] ) );
        $redirect = admin_url( 'admin.php?page=loopkit-post-types' );

        if ( 'delete_type' === $action || 'delete_tax' === $action ) {
            $slug   = sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) );
            $option = ( 'delete_type' === $action ) ? self::OPTION_TYPES : self::OPTION_TAXONOMIES;
            $items  = get_option( $option, array() );
            $items  = array_values( array_filter( (array) $items, function( $item ) use ( $slug ) {
                return ( $item['slug'] ?? '' ) !== $slug;
            } ) );
            update_option( $option, $items );
            update_option( 'loopkit_flush_rewrite', 1 );
            wp_safe_redirect( add_query_arg( 'saved', 'deleted', $redirect ) );
            exit;
        }

        if ( 'save_type' === $action ) {
            $raw  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            $slug = sanitize_key( $raw['slug'] ?? '' );
            if ( '' === $slug ) {
                wp_safe_redirect( add_query_arg( 'saved', 'error', $redirect ) );
                exit;
            }

            $entry = array(
                'slug'          => $slug,
                'singular'      => sanitize_text_field( $raw['singular'] ?? ucfirst( $slug ) ),
                'plural'        => sanitize_text_field( $raw['plural'] ?? ucfirst( $slug ) . 's' ),
                'icon'          => sanitize_text_field( $raw['icon'] ?? 'dashicons-admin-post' ),
                'has_archive'   => ! empty( $raw['has_archive'] ) ? 1 : 0,
                'hierarchical'  => ! empty( $raw['hierarchical'] ) ? 1 : 0,
                'private'       => ! empty( $raw['private'] ) ? 1 : 0,
                'show_in_rest'  => ! empty( $raw['show_in_rest'] ) ? 1 : 0,
                'menu_position' => '' === ( $raw['menu_position'] ?? '' ) ? '' : (int) $raw['menu_position'],
                'rewrite'       => sanitize_title( $raw['rewrite'] ?? '' ),
                'supports'      => array_values( array_intersect( array_keys( self::supports_list() ), (array) ( $raw['supports'] ?? array() ) ) ),
                'columns'       => array_values( array_intersect( array_keys( self::columns_list() ), (array) ( $raw['columns'] ?? array() ) ) ),
                'column_fields' => sanitize_text_field( $raw['column_fields'] ?? '' ),
                'taxonomies'    => array_map( 'sanitize_key', (array) ( $raw['taxonomies'] ?? array() ) ),
            );

            $types   = self::get_post_types();
            $updated = false;
            foreach ( $types as $i => $existing ) {
                if ( ( $existing['slug'] ?? '' ) === $slug ) {
                    $types[ $i ] = $entry;
                    $updated     = true;
                    break;
                }
            }
            if ( ! $updated ) {
                $types[] = $entry;
            }

            update_option( self::OPTION_TYPES, $types );
            update_option( 'loopkit_flush_rewrite', 1 );
            wp_safe_redirect( add_query_arg( array( 'saved' => '1', 'type' => $slug ), $redirect ) );
            exit;
        }

        if ( 'save_tax' === $action ) {
            $raw  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            $slug = sanitize_key( $raw['slug'] ?? '' );
            if ( '' === $slug ) {
                wp_safe_redirect( add_query_arg( 'saved', 'error', $redirect ) );
                exit;
            }

            $entry = array(
                'slug'         => $slug,
                'singular'     => sanitize_text_field( $raw['singular'] ?? ucfirst( $slug ) ),
                'plural'       => sanitize_text_field( $raw['plural'] ?? ucfirst( $slug ) . 's' ),
                'post_types'   => array_map( 'sanitize_key', (array) ( $raw['post_types'] ?? array( 'post' ) ) ),
                'hierarchical' => ! empty( $raw['hierarchical'] ) ? 1 : 0,
                'show_in_rest' => ! empty( $raw['show_in_rest'] ) ? 1 : 0,
                'admin_column' => ! empty( $raw['admin_column'] ) ? 1 : 0,
                'rewrite'      => sanitize_title( $raw['rewrite'] ?? '' ),
            );

            $taxes   = self::get_taxonomies();
            $updated = false;
            foreach ( $taxes as $i => $existing ) {
                if ( ( $existing['slug'] ?? '' ) === $slug ) {
                    $taxes[ $i ] = $entry;
                    $updated     = true;
                    break;
                }
            }
            if ( ! $updated ) {
                $taxes[] = $entry;
            }

            update_option( self::OPTION_TAXONOMIES, $taxes );
            update_option( 'loopkit_flush_rewrite', 1 );
            wp_safe_redirect( add_query_arg( array( 'saved' => '1', 'tax' => $slug ), $redirect ) );
            exit;
        }
    }

    /**
     * Screen: list, post-type form, or taxonomy form.
     */
    /**
     * Columns a post type's admin list can show.
     *
     * @return array
     */
    public static function columns_list() {
        return array(
            'thumbnail' => __( 'Featured image', 'loopkit' ),
            'excerpt'   => __( 'Excerpt', 'loopkit' ),
            'author'    => __( 'Author', 'loopkit' ),
            'date'      => __( 'Date', 'loopkit' ),
            'id'        => __( 'ID', 'loopkit' ),
            'slug'      => __( 'Slug', 'loopkit' ),
        );
    }

    /**
     * Add the chosen columns to each post type's list screen.
     */
    public function hook_admin_columns() {
        foreach ( self::get_post_types() as $type ) {
            $slug = $type['slug'] ?? '';
            if ( '' === $slug ) {
                continue;
            }
            add_filter( 'manage_' . $slug . '_posts_columns', function( $columns ) use ( $type ) {
                return self::build_columns( $columns, $type );
            } );
            add_action( 'manage_' . $slug . '_posts_custom_column', function( $column, $post_id ) use ( $type ) {
                self::render_column( $column, $post_id, $type );
            }, 10, 2 );
        }
    }

    /**
     * The column header row for a post type.
     *
     * @param array $columns Existing columns.
     * @param array $type    Post type settings.
     * @return array
     */
    public static function build_columns( $columns, $type ) {
        $chosen = (array) ( $type['columns'] ?? array() );
        $fields = array_filter( array_map( 'trim', explode( ',', (string) ( $type['column_fields'] ?? '' ) ) ) );

        // Start from the title and whatever taxonomies WordPress added, then our own.
        $out = array();
        foreach ( $columns as $key => $label ) {
            if ( in_array( $key, array( 'date', 'author' ), true ) ) {
                continue;
            }
            $out[ $key ] = $label;
            if ( 'cb' === $key && in_array( 'thumbnail', $chosen, true ) ) {
                $out['lk_thumbnail'] = __( 'Image', 'loopkit' );
            }
        }

        foreach ( $fields as $field ) {
            $out[ 'lk_field_' . sanitize_key( $field ) ] = ucwords( str_replace( array( '_', '-' ), ' ', $field ) );
        }
        if ( in_array( 'excerpt', $chosen, true ) ) {
            $out['lk_excerpt'] = __( 'Excerpt', 'loopkit' );
        }
        if ( in_array( 'slug', $chosen, true ) ) {
            $out['lk_slug'] = __( 'Slug', 'loopkit' );
        }
        if ( in_array( 'id', $chosen, true ) ) {
            $out['lk_id'] = __( 'ID', 'loopkit' );
        }
        if ( in_array( 'author', $chosen, true ) ) {
            $out['author'] = __( 'Author', 'loopkit' );
        }
        if ( in_array( 'date', $chosen, true ) ) {
            $out['date'] = __( 'Date', 'loopkit' );
        }
        return $out;
    }

    /**
     * What each of our columns shows.
     *
     * @param string $column  Column key.
     * @param int    $post_id Post.
     * @param array  $type    Post type settings.
     */
    public static function render_column( $column, $post_id, $type ) {
        if ( 'lk_thumbnail' === $column ) {
            $thumb = get_the_post_thumbnail( $post_id, array( 48, 48 ) );
            echo $thumb ? $thumb : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }
        if ( 'lk_excerpt' === $column ) {
            echo esc_html( wp_trim_words( get_the_excerpt( $post_id ), 14 ) );
            return;
        }
        if ( 'lk_slug' === $column ) {
            echo esc_html( get_post_field( 'post_name', $post_id ) );
            return;
        }
        if ( 'lk_id' === $column ) {
            echo (int) $post_id;
            return;
        }
        if ( 0 === strpos( $column, 'lk_field_' ) ) {
            $value = get_post_meta( $post_id, substr( $column, 9 ), true );
            if ( is_array( $value ) ) {
                $value = implode( ', ', array_map( function( $row ) {
                    return is_array( $row ) ? implode( ' ', $row ) : (string) $row;
                }, $value ) );
            }
            echo '' === $value ? '&mdash;' : esc_html( wp_trim_words( (string) $value, 10 ) );
        }
    }

    public function render_page() {
        $edit_type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $edit_tax  = isset( $_GET['tax'] ) ? sanitize_text_field( wp_unslash( $_GET['tax'] ) ) : '';  // phpcs:ignore WordPress.Security.NonceVerification

        if ( '' !== $edit_type ) {
            $current = null;
            foreach ( self::get_post_types() as $type ) {
                if ( ( $type['slug'] ?? '' ) === $edit_type ) {
                    $current = $type;
                }
            }
            $this->render_type_form( $current );
            return;
        }
        if ( '' !== $edit_tax ) {
            $current = null;
            foreach ( self::get_taxonomies() as $tax ) {
                if ( ( $tax['slug'] ?? '' ) === $edit_tax ) {
                    $current = $tax;
                }
            }
            $this->render_tax_form( $current );
            return;
        }

        $types = self::get_post_types();
        $taxes = self::get_taxonomies();
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Post Types', 'loopkit' ); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-post-types&type=new' ) ); ?>"><?php esc_html_e( 'Add Post Type', 'loopkit' ); ?></a>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-post-types&tax=new' ) ); ?>"><?php esc_html_e( 'Add Taxonomy', 'loopkit' ); ?></a>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-<?php echo 'error' === $_GET['saved'] ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification ?> is-dismissible"><p><?php
                    $state = sanitize_key( wp_unslash( $_GET['saved'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
                    if ( 'deleted' === $state ) {
                        esc_html_e( 'Deleted.', 'loopkit' );
                    } elseif ( 'error' === $state ) {
                        esc_html_e( 'A slug is required.', 'loopkit' );
                    } else {
                        esc_html_e( 'Saved. Permalinks were refreshed.', 'loopkit' );
                    }
                ?></p></div>
            <?php endif; ?>

            <div class="lk-card">
                <div class="lk-card-header">
                    <h3><?php esc_html_e( 'Post Types', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc"><?php esc_html_e( 'These work everywhere: the admin menu, archives, permalinks, the block editor, Display Conditions, Single and Archive templates, and the Loop Grid query.', 'loopkit' ); ?></p>
                </div>
                <div class="lk-card-body">
                    <table class="widefat striped">
                        <thead><tr>
                            <th><?php esc_html_e( 'Name', 'loopkit' ); ?></th>
                            <th style="width:150px"><?php esc_html_e( 'Slug', 'loopkit' ); ?></th>
                            <th style="width:110px"><?php esc_html_e( 'Archive', 'loopkit' ); ?></th>
                            <th style="width:110px"><?php esc_html_e( 'Items', 'loopkit' ); ?></th>
                            <th style="width:90px"></th>
                        </tr></thead>
                        <tbody>
                        <?php if ( empty( $types ) ) : ?>
                            <tr><td colspan="5"><?php esc_html_e( 'No post types yet. Add one — for example Portfolio, Services, Team or Events.', 'loopkit' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $types as $type ) : $count = post_type_exists( $type['slug'] ) ? (int) wp_count_posts( $type['slug'] )->publish : 0; ?>
                                <tr>
                                    <td><span class="dashicons <?php echo esc_attr( $type['icon'] ?? 'dashicons-admin-post' ); ?>"></span>
                                        <strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-post-types&type=' . rawurlencode( $type['slug'] ) ) ); ?>"><?php echo esc_html( $type['plural'] ?? $type['slug'] ); ?></a></strong></td>
                                    <td><code><?php echo esc_html( $type['slug'] ); ?></code></td>
                                    <td><?php echo empty( $type['has_archive'] ) ? '&mdash;' : esc_html__( 'Yes', 'loopkit' ); ?></td>
                                    <td><?php echo (int) $count; ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('<?php esc_attr_e( 'Delete this post type? Its content stays in the database.', 'loopkit' ); ?>')">
                                            <?php wp_nonce_field( 'loopkit_cpt' ); ?>
                                            <input type="hidden" name="loopkit_cpt_action" value="delete_type">
                                            <input type="hidden" name="slug" value="<?php echo esc_attr( $type['slug'] ); ?>">
                                            <button type="submit" class="button-link delete" style="color:#b32d2e"><?php esc_html_e( 'Delete', 'loopkit' ); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="lk-card">
                <div class="lk-card-header">
                    <h3><?php esc_html_e( 'Taxonomies', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc"><?php esc_html_e( 'Categories or tags of your own, attached to any post type.', 'loopkit' ); ?></p>
                </div>
                <div class="lk-card-body">
                    <table class="widefat striped">
                        <thead><tr>
                            <th><?php esc_html_e( 'Name', 'loopkit' ); ?></th>
                            <th style="width:150px"><?php esc_html_e( 'Slug', 'loopkit' ); ?></th>
                            <th style="width:200px"><?php esc_html_e( 'Attached to', 'loopkit' ); ?></th>
                            <th style="width:110px"><?php esc_html_e( 'Type', 'loopkit' ); ?></th>
                            <th style="width:90px"></th>
                        </tr></thead>
                        <tbody>
                        <?php if ( empty( $taxes ) ) : ?>
                            <tr><td colspan="5"><?php esc_html_e( 'No taxonomies yet.', 'loopkit' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $taxes as $tax ) : ?>
                                <tr>
                                    <td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-post-types&tax=' . rawurlencode( $tax['slug'] ) ) ); ?>"><?php echo esc_html( $tax['plural'] ?? $tax['slug'] ); ?></a></strong></td>
                                    <td><code><?php echo esc_html( $tax['slug'] ); ?></code></td>
                                    <td><?php echo esc_html( implode( ', ', (array) ( $tax['post_types'] ?? array() ) ) ); ?></td>
                                    <td><?php echo empty( $tax['hierarchical'] ) ? esc_html__( 'Tags', 'loopkit' ) : esc_html__( 'Categories', 'loopkit' ); ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('<?php esc_attr_e( 'Delete this taxonomy?', 'loopkit' ); ?>')">
                                            <?php wp_nonce_field( 'loopkit_cpt' ); ?>
                                            <input type="hidden" name="loopkit_cpt_action" value="delete_tax">
                                            <input type="hidden" name="slug" value="<?php echo esc_attr( $tax['slug'] ); ?>">
                                            <button type="submit" class="button-link delete" style="color:#b32d2e"><?php esc_html_e( 'Delete', 'loopkit' ); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Add / edit a post type.
     *
     * @param array|null $type Current values.
     */
    private function render_type_form( $type ) {
        $is_new = empty( $type );
        $type   = wp_parse_args( (array) $type, array(
            'slug'          => '',
            'singular'      => '',
            'plural'        => '',
            'icon'          => 'dashicons-admin-post',
            'has_archive'   => 1,
            'hierarchical'  => 0,
            'private'       => 0,
            'show_in_rest'  => 1,
            'menu_position' => '',
            'rewrite'       => '',
            'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
            'columns'       => array( 'thumbnail', 'date' ),
            'column_fields' => '',
            'taxonomies'    => array(),
        ) );
        $all_taxes = get_taxonomies( array( 'public' => true ), 'objects' );
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php echo $is_new ? esc_html__( 'Add Post Type', 'loopkit' ) : esc_html__( 'Edit Post Type', 'loopkit' ); ?></h1>
            <hr class="wp-header-end">

            <form method="post" class="lk-automation-form">
                <?php wp_nonce_field( 'loopkit_cpt' ); ?>
                <input type="hidden" name="loopkit_cpt_action" value="save_type">

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <span class="lk-auto-step">1</span>
                        <h3><?php esc_html_e( 'Names', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'The slug is used in URLs and in code; it cannot be changed later without losing the link between the two.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <div class="lk-auto-field">
                            <label for="lk-cpt-singular"><?php esc_html_e( 'Singular name', 'loopkit' ); ?></label>
                            <input type="text" id="lk-cpt-singular" name="singular" value="<?php echo esc_attr( $type['singular'] ); ?>" placeholder="<?php esc_attr_e( 'Project', 'loopkit' ); ?>" required>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-cpt-plural"><?php esc_html_e( 'Plural name', 'loopkit' ); ?></label>
                            <input type="text" id="lk-cpt-plural" name="plural" value="<?php echo esc_attr( $type['plural'] ); ?>" placeholder="<?php esc_attr_e( 'Projects', 'loopkit' ); ?>" required>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-cpt-slug"><?php esc_html_e( 'Slug', 'loopkit' ); ?></label>
                            <input type="text" id="lk-cpt-slug" name="slug" value="<?php echo esc_attr( $type['slug'] ); ?>" placeholder="project" <?php echo $is_new ? '' : 'readonly'; ?> required>
                            <span class="lk-auto-help"><?php esc_html_e( 'Lowercase letters, numbers and underscores. Max 20 characters.', 'loopkit' ); ?></span>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-cpt-rewrite"><?php esc_html_e( 'URL base', 'loopkit' ); ?></label>
                            <input type="text" id="lk-cpt-rewrite" name="rewrite" value="<?php echo esc_attr( $type['rewrite'] ); ?>" placeholder="<?php esc_attr_e( 'same as slug', 'loopkit' ); ?>">
                            <span class="lk-auto-help"><?php echo esc_html( home_url( '/' ) ); ?><strong id="lk-cpt-url-preview"><?php echo esc_html( $type['rewrite'] ?: ( $type['slug'] ?: 'project' ) ); ?></strong>/item-name/</span>
                        </div>
                    </div>
                </div>

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <span class="lk-auto-step">2</span>
                        <h3><?php esc_html_e( 'Behaviour', 'loopkit' ); ?></h3>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <div class="lk-auto-field">
                            <label for="lk-cpt-icon"><?php esc_html_e( 'Menu icon', 'loopkit' ); ?></label>
                            <select id="lk-cpt-icon" name="icon">
                                <?php foreach ( self::icon_list() as $icon ) : ?>
                                    <option value="<?php echo esc_attr( $icon ); ?>" <?php selected( $type['icon'], $icon ); ?>><?php echo esc_html( str_replace( 'dashicons-', '', $icon ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-cpt-menu-position"><?php esc_html_e( 'Menu position', 'loopkit' ); ?></label>
                            <input type="number" id="lk-cpt-menu-position" name="menu_position" value="<?php echo esc_attr( (string) $type['menu_position'] ); ?>" placeholder="<?php esc_attr_e( 'auto', 'loopkit' ); ?>">
                            <span class="lk-auto-help"><?php esc_html_e( '5 = below Posts, 20 = below Pages.', 'loopkit' ); ?></span>
                        </div>
                        <div class="lk-auto-field">
                            <label><?php esc_html_e( 'Options', 'loopkit' ); ?></label>
                            <label class="lk-auto-check"><input type="checkbox" name="has_archive" value="1" <?php checked( ! empty( $type['has_archive'] ) ); ?>> <?php esc_html_e( 'Has an archive page', 'loopkit' ); ?></label>
                            <label class="lk-auto-check"><input type="checkbox" name="hierarchical" value="1" <?php checked( ! empty( $type['hierarchical'] ) ); ?>> <?php esc_html_e( 'Hierarchical (like Pages)', 'loopkit' ); ?></label>
                        </div>
                        <div class="lk-auto-field">
                            <label>&nbsp;</label>
                            <label class="lk-auto-check"><input type="checkbox" name="show_in_rest" value="1" <?php checked( ! empty( $type['show_in_rest'] ) ); ?>> <?php esc_html_e( 'Block editor / REST API', 'loopkit' ); ?></label>
                            <label class="lk-auto-check"><input type="checkbox" name="private" value="1" <?php checked( ! empty( $type['private'] ) ); ?>> <?php esc_html_e( 'Private (not shown on the site)', 'loopkit' ); ?></label>
                        </div>
                    </div>
                </div>

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <span class="lk-auto-step">3</span>
                        <h3><?php esc_html_e( 'Fields and taxonomies', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'What the edit screen offers, and which categories or tags this type uses.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <div class="lk-auto-field">
                            <label><?php esc_html_e( 'Supports', 'loopkit' ); ?></label>
                            <?php foreach ( self::supports_list() as $key => $label ) : ?>
                                <label class="lk-auto-check"><input type="checkbox" name="supports[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $type['supports'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="lk-auto-field">
                            <label><?php esc_html_e( 'Taxonomies', 'loopkit' ); ?></label>
                            <?php foreach ( $all_taxes as $tax ) : ?>
                                <label class="lk-auto-check"><input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr( $tax->name ); ?>" <?php checked( in_array( $tax->name, (array) $type['taxonomies'], true ) ); ?>> <?php echo esc_html( $tax->labels->name ); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <span class="lk-auto-step">4</span>
                        <h3><?php esc_html_e( 'List columns', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'What the admin list shows for this post type, in this order. The title and any taxonomy columns are always there.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body">
                        <div class="lk-auto-checks">
                            <?php foreach ( self::columns_list() as $lk_key => $lk_label ) : ?>
                                <label class="lk-auto-check"><input type="checkbox" name="columns[]" value="<?php echo esc_attr( $lk_key ); ?>" <?php checked( in_array( $lk_key, (array) ( $type['columns'] ?? array() ), true ) ); ?>> <?php echo esc_html( $lk_label ); ?></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="lk-auto-field" style="margin-top:14px">
                            <label for="lk-column-fields"><?php esc_html_e( 'Custom field columns', 'loopkit' ); ?></label>
                            <input type="text" id="lk-column-fields" name="column_fields" value="<?php echo esc_attr( $type['column_fields'] ?? '' ); ?>" placeholder="price, city, rating">
                            <span class="lk-auto-help"><?php esc_html_e( 'Field keys separated by commas — each becomes its own column showing that value.', 'loopkit' ); ?></span>
                        </div>
                    </div>
                </div>

                <p class="lk-auto-submit">
                    <button type="submit" class="lk-btn lk-btn-primary"><?php esc_html_e( 'Save Post Type', 'loopkit' ); ?></button>
                    <a class="lk-btn lk-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-post-types' ) ); ?>"><?php esc_html_e( 'Back', 'loopkit' ); ?></a>
                </p>
            </form>
        </div>

        <script>
        ( function () {
            var singular = document.getElementById( 'lk-cpt-singular' );
            var plural = document.getElementById( 'lk-cpt-plural' );
            var slug = document.getElementById( 'lk-cpt-slug' );
            var rewrite = document.getElementById( 'lk-cpt-rewrite' );
            var preview = document.getElementById( 'lk-cpt-url-preview' );

            function toSlug( text ) {
                return text.toLowerCase().replace( /[^a-z0-9]+/g, '_' ).replace( /^_+|_+$/g, '' ).slice( 0, 20 );
            }
            singular.addEventListener( 'input', function () {
                if ( ! plural.value || plural.dataset.auto !== '0' ) { plural.value = singular.value ? singular.value + 's' : ''; plural.dataset.auto = '1'; }
                if ( ! slug.readOnly && slug.dataset.touched !== '1' ) { slug.value = toSlug( singular.value ); }
                paint();
            } );
            plural.addEventListener( 'input', function () { plural.dataset.auto = '0'; } );
            slug.addEventListener( 'input', function () { slug.dataset.touched = '1'; slug.value = toSlug( slug.value ); paint(); } );
            rewrite.addEventListener( 'input', paint );
            function paint() { preview.textContent = ( rewrite.value || slug.value || 'project' ); }
        } )();
        </script>
        <?php
    }

    /**
     * Add / edit a taxonomy.
     *
     * @param array|null $tax Current values.
     */
    private function render_tax_form( $tax ) {
        $is_new = empty( $tax );
        $tax    = wp_parse_args( (array) $tax, array(
            'slug'         => '',
            'singular'     => '',
            'plural'       => '',
            'post_types'   => array( 'post' ),
            'hierarchical' => 1,
            'show_in_rest' => 1,
            'admin_column' => 1,
            'rewrite'      => '',
        ) );
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php echo $is_new ? esc_html__( 'Add Taxonomy', 'loopkit' ) : esc_html__( 'Edit Taxonomy', 'loopkit' ); ?></h1>
            <hr class="wp-header-end">

            <form method="post" class="lk-automation-form">
                <?php wp_nonce_field( 'loopkit_cpt' ); ?>
                <input type="hidden" name="loopkit_cpt_action" value="save_tax">

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <span class="lk-auto-step">1</span>
                        <h3><?php esc_html_e( 'Names', 'loopkit' ); ?></h3>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <div class="lk-auto-field">
                            <label for="lk-tax-singular"><?php esc_html_e( 'Singular name', 'loopkit' ); ?></label>
                            <input type="text" id="lk-tax-singular" name="singular" value="<?php echo esc_attr( $tax['singular'] ); ?>" placeholder="<?php esc_attr_e( 'Genre', 'loopkit' ); ?>" required>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-tax-plural"><?php esc_html_e( 'Plural name', 'loopkit' ); ?></label>
                            <input type="text" id="lk-tax-plural" name="plural" value="<?php echo esc_attr( $tax['plural'] ); ?>" placeholder="<?php esc_attr_e( 'Genres', 'loopkit' ); ?>" required>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-tax-slug"><?php esc_html_e( 'Slug', 'loopkit' ); ?></label>
                            <input type="text" id="lk-tax-slug" name="slug" value="<?php echo esc_attr( $tax['slug'] ); ?>" placeholder="genre" <?php echo $is_new ? '' : 'readonly'; ?> required>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-tax-rewrite"><?php esc_html_e( 'URL base', 'loopkit' ); ?></label>
                            <input type="text" id="lk-tax-rewrite" name="rewrite" value="<?php echo esc_attr( $tax['rewrite'] ); ?>" placeholder="<?php esc_attr_e( 'same as slug', 'loopkit' ); ?>">
                        </div>
                    </div>
                </div>

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <span class="lk-auto-step">2</span>
                        <h3><?php esc_html_e( 'Attach to', 'loopkit' ); ?></h3>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <div class="lk-auto-field">
                            <label><?php esc_html_e( 'Post types', 'loopkit' ); ?></label>
                            <?php foreach ( $post_types as $pt ) : ?>
                                <label class="lk-auto-check"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, (array) $tax['post_types'], true ) ); ?>> <?php echo esc_html( $pt->labels->name ); ?></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="lk-auto-field">
                            <label><?php esc_html_e( 'Options', 'loopkit' ); ?></label>
                            <label class="lk-auto-check"><input type="checkbox" name="hierarchical" value="1" <?php checked( ! empty( $tax['hierarchical'] ) ); ?>> <?php esc_html_e( 'Hierarchical (like Categories)', 'loopkit' ); ?></label>
                            <label class="lk-auto-check"><input type="checkbox" name="admin_column" value="1" <?php checked( ! empty( $tax['admin_column'] ) ); ?>> <?php esc_html_e( 'Show a column in the posts list', 'loopkit' ); ?></label>
                            <label class="lk-auto-check"><input type="checkbox" name="show_in_rest" value="1" <?php checked( ! empty( $tax['show_in_rest'] ) ); ?>> <?php esc_html_e( 'Block editor / REST API', 'loopkit' ); ?></label>
                        </div>
                    </div>
                </div>

                <p class="lk-auto-submit">
                    <button type="submit" class="lk-btn lk-btn-primary"><?php esc_html_e( 'Save Taxonomy', 'loopkit' ); ?></button>
                    <a class="lk-btn lk-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-post-types' ) ); ?>"><?php esc_html_e( 'Back', 'loopkit' ); ?></a>
                </p>
            </form>
        </div>

        <script>
        ( function () {
            var singular = document.getElementById( 'lk-tax-singular' );
            var plural = document.getElementById( 'lk-tax-plural' );
            var slug = document.getElementById( 'lk-tax-slug' );
            singular.addEventListener( 'input', function () {
                if ( plural.dataset.auto !== '0' ) plural.value = singular.value ? singular.value + 's' : '';
                if ( ! slug.readOnly && slug.dataset.touched !== '1' ) {
                    slug.value = singular.value.toLowerCase().replace( /[^a-z0-9]+/g, '_' ).replace( /^_+|_+$/g, '' ).slice( 0, 20 );
                }
            } );
            plural.addEventListener( 'input', function () { plural.dataset.auto = '0'; } );
            slug.addEventListener( 'input', function () { slug.dataset.touched = '1'; } );
        } )();
        </script>
        <?php
    }

    /**
     * Post types created here get their own Dynamic Tags entry.
     *
     * @param array $groups Tag groups.
     * @return array
     */
    public function add_dynamic_tags( $groups ) {
        if ( empty( self::get_post_types() ) && empty( self::get_taxonomies() ) ) {
            return $groups;
        }

        $tags = array(
            array( 'name' => 'post_type_label', 'label' => __( 'Post Type Name', 'loopkit' ), 'type' => 'text' ),
        );
        foreach ( self::get_taxonomies() as $tax ) {
            $tags[] = array(
                'name'  => 'post_terms',
                'label' => sprintf( /* translators: %s: taxonomy name */ __( 'Terms: %s', 'loopkit' ), $tax['plural'] ?? $tax['slug'] ),
                'type'  => 'text',
                'arg'   => $tax['slug'],
            );
        }

        $groups[] = array(
            'group' => __( 'Post Types', 'loopkit' ),
            'tags'  => $tags,
        );
        return $groups;
    }
}
