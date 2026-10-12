<?php
/**
 * Loopkit Shop — its own admin area.
 *
 * The shop gets a top-level "Shop" menu of its own, separate from Loopkit, with every shop
 * screen under it: Products, Add Product, Categories, Tags, Orders, Shop Submissions and
 * Settings. Products are edited on a purpose-built screen — not the WordPress post editor —
 * so the product fields, gallery and categories are all in one place.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Shop_Admin {

    const MENU = 'loopkit-shop';

    /**
     * Hook up.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'redirect_post_editor' ), 1 );
        add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_filter( 'parent_file', array( __CLASS__, 'highlight_menu' ) );
    }

    /**
     * Old links into the WordPress post editor (a bookmark, or "Edit" somewhere) land on
     * Loopkit's own product screen instead of an error, since the post type has no UI.
     */
    public static function redirect_post_editor() {
        global $pagenow;

        $post_type = '';
        if ( 'post-new.php' === $pagenow ) {
            $post_type = sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
        } elseif ( 'post.php' === $pagenow ) {
            $post_id   = absint( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
            $post_type = $post_id ? (string) get_post_type( $post_id ) : '';
        } elseif ( 'edit.php' === $pagenow ) {
            $post_type = sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
        }

        if ( Loopkit_Module_WooCommerce::POST_TYPE !== $post_type ) {
            return;
        }

        if ( 'edit.php' === $pagenow ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU ) );
            exit;
        }

        $product = ( 'post.php' === $pagenow ) ? absint( $_GET['post'] ?? 0 ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        wp_safe_redirect( admin_url( 'admin.php?page=loopkit-product-edit' . ( $product ? '&product=' . $product : '' ) ) );
        exit;
    }

    /**
     * The Shop menu.
     */
    public static function register_menu() {
        $post_type = Loopkit_Module_WooCommerce::POST_TYPE;

        add_menu_page(
            __( 'Shop', 'loopkit' ),
            __( 'Shop', 'loopkit' ),
            'edit_pages',
            self::MENU,
            array( __CLASS__, 'render_products' ),
            'dashicons-cart',
            26
        );

        add_submenu_page( self::MENU, __( 'Products', 'loopkit' ), __( 'Products', 'loopkit' ), 'edit_pages', self::MENU, array( __CLASS__, 'render_products' ) );
        add_submenu_page( self::MENU, __( 'Add Product', 'loopkit' ), __( 'Add Product', 'loopkit' ), 'edit_pages', 'loopkit-product-edit', array( __CLASS__, 'render_product_editor' ) );
        add_submenu_page( self::MENU, __( 'Categories', 'loopkit' ), __( 'Categories', 'loopkit' ), 'manage_categories', 'edit-tags.php?taxonomy=' . Loopkit_Module_WooCommerce::TAX_CAT . '&post_type=' . $post_type );
        add_submenu_page( self::MENU, __( 'Tags', 'loopkit' ), __( 'Tags', 'loopkit' ), 'manage_categories', 'edit-tags.php?taxonomy=' . Loopkit_Module_WooCommerce::TAX_TAG . '&post_type=' . $post_type );
        add_submenu_page( self::MENU, __( 'Orders', 'loopkit' ), __( 'Orders', 'loopkit' ), 'manage_options', 'loopkit-orders', array( 'Loopkit_Shop_Orders', 'render_orders_page' ) );
        add_submenu_page( self::MENU, __( 'Payments', 'loopkit' ), __( 'Payments', 'loopkit' ), 'manage_options', 'loopkit-shop-payments', array( 'Loopkit_Module_WooCommerce', 'render_payments_page' ) );
        add_submenu_page( self::MENU, __( 'Shop Submissions', 'loopkit' ), __( 'Shop Submissions', 'loopkit' ), 'edit_pages', 'loopkit-shop-submissions', array( 'Loopkit_Shop_Orders', 'render_submissions_page' ) );
        add_submenu_page( self::MENU, __( 'Settings', 'loopkit' ), __( 'Settings', 'loopkit' ), 'manage_options', 'loopkit-shop-settings', array( 'Loopkit_Module_WooCommerce', 'render_settings_page' ) );
    }

    /**
     * Keep the Shop menu highlighted on the taxonomy screens.
     *
     * @param string $parent Parent file.
     * @return string
     */
    public static function highlight_menu( $parent ) {
        global $current_screen;
        if ( $current_screen && in_array( $current_screen->taxonomy, array( Loopkit_Module_WooCommerce::TAX_CAT, Loopkit_Module_WooCommerce::TAX_TAG ), true ) ) {
            return self::MENU;
        }
        return $parent;
    }

    /**
     * Media picker and the editor on the product screen.
     *
     * @param string $hook Screen.
     */
    public static function enqueue( $hook ) {
        if ( false !== strpos( (string) $hook, 'loopkit-product-edit' ) ) {
            wp_enqueue_media();
        }
    }

    /* ---------------------------------------------------------------------
     * Saving
     * ------------------------------------------------------------------- */

    /**
     * Save, trash or restore a product from the shop screens.
     */
    public static function handle_save() {
        if ( empty( $_POST['loopkit_shop_admin_action'] ) && empty( $_GET['loopkit_shop_admin_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage products.', 'loopkit' ) );
        }

        $action = sanitize_key( wp_unslash( $_POST['loopkit_shop_admin_action'] ?? $_GET['loopkit_shop_admin_action'] ?? '' ) );
        $list   = admin_url( 'admin.php?page=' . self::MENU );

        if ( 'delete_product' === $action ) {
            $id = absint( $_GET['product'] ?? 0 );
            check_admin_referer( 'loopkit_delete_product_' . $id );
            if ( $id && Loopkit_Module_WooCommerce::POST_TYPE === get_post_type( $id ) ) {
                wp_trash_post( $id );
            }
            wp_safe_redirect( add_query_arg( 'saved', 'deleted', $list ) );
            exit;
        }

        if ( 'save_product' !== $action ) {
            return;
        }
        check_admin_referer( 'loopkit_save_product' );

        $raw   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $id    = absint( $raw['product_id'] ?? 0 );
        $title = sanitize_text_field( $raw['product_title'] ?? '' );
        if ( '' === $title ) {
            $title = __( 'Untitled product', 'loopkit' );
        }

        $postarr = array(
            'ID'           => $id ?: 0,
            'post_type'    => Loopkit_Module_WooCommerce::POST_TYPE,
            'post_title'   => $title,
            'post_content' => wp_kses_post( $raw['product_description'] ?? '' ),
            'post_excerpt' => sanitize_textarea_field( $raw['product_excerpt'] ?? '' ),
            'post_status'  => in_array( $raw['product_status'] ?? 'publish', array( 'publish', 'draft', 'private' ), true ) ? $raw['product_status'] : 'publish',
        );
        $id = $id ? wp_update_post( $postarr ) : wp_insert_post( $postarr );

        if ( is_wp_error( $id ) || ! $id ) {
            wp_safe_redirect( add_query_arg( 'saved', 'error', $list ) );
            exit;
        }

        // Featured image
        $thumb = absint( $raw['product_thumbnail'] ?? 0 );
        if ( $thumb ) {
            set_post_thumbnail( $id, $thumb );
        } else {
            delete_post_thumbnail( $id );
        }

        // Terms
        wp_set_object_terms( $id, array_map( 'absint', (array) ( $raw['product_cats'] ?? array() ) ), Loopkit_Module_WooCommerce::TAX_CAT );
        $tags = array_filter( array_map( 'trim', explode( ',', (string) ( $raw['product_tags'] ?? '' ) ) ) );
        wp_set_object_terms( $id, $tags, Loopkit_Module_WooCommerce::TAX_TAG );

        // Product data (prices, stock, variations…) — the same routine the meta box uses
        Loopkit_Module_WooCommerce::save_product_data( $id, (array) ( $raw['lk_product'] ?? array() ) );

        // Custom Fields module: values from any field group aimed at products
        if ( class_exists( 'Loopkit_Module_ACF' ) && ! empty( $raw['loopkit_field'] ) ) {
            Loopkit_Module_ACF::save_values_for_post( $id, (array) $raw['loopkit_field'] );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=loopkit-product-edit&product=' . (int) $id . '&saved=1' ) );
        exit;
    }

    /**
     * Custom Fields groups that belong on a product.
     *
     * @param int $id Product (0 when it is being created).
     * @return array
     */
    public static function field_groups_for_product( $id ) {
        if ( ! class_exists( 'Loopkit_Module_ACF' ) ) {
            return array();
        }

        if ( $id ) {
            return Loopkit_Module_ACF::groups_for_post( get_post( (int) $id ) );
        }

        // A brand new product has no ID yet, so match on the post type alone.
        $out = array();
        foreach ( Loopkit_Module_ACF::get_field_groups() as $group ) {
            if ( 'publish' !== $group['status'] || empty( $group['fields'] ) ) {
                continue;
            }
            foreach ( $group['locations'] as $rule ) {
                if ( 'post_type' === ( $rule['type'] ?? '' ) && Loopkit_Module_WooCommerce::POST_TYPE === ( $rule['value'] ?? '' ) ) {
                    $out[] = $group;
                    break;
                }
            }
        }
        return $out;
    }

    /* ---------------------------------------------------------------------
     * Products list
     * ------------------------------------------------------------------- */

    /**
     * All products.
     */
    public static function render_products() {
        $search   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
        $paged    = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
        $per_page = 20;

        $query = new WP_Query( array(
            'post_type'      => Loopkit_Module_WooCommerce::POST_TYPE,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            's'              => $search,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Products', 'loopkit' ); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-product-edit' ) ); ?>"><?php esc_html_e( 'Add Product', 'loopkit' ); ?></a>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-success is-dismissible"><p><?php
                    echo 'deleted' === $_GET['saved'] ? esc_html__( 'Product moved to trash.', 'loopkit' ) : esc_html__( 'Saved.', 'loopkit' ); // phpcs:ignore WordPress.Security.NonceVerification
                ?></p></div>
            <?php endif; ?>

            <div class="lk-card">
                <div class="lk-card-body">
                    <form method="get" style="margin-bottom:14px">
                        <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU ); ?>">
                        <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search products…', 'loopkit' ); ?>">
                        <button type="submit" class="button"><?php esc_html_e( 'Search', 'loopkit' ); ?></button>
                        <span style="margin-left:10px;color:#646970"><?php
                            /* translators: %d: number of products */
                            printf( esc_html__( '%d products', 'loopkit' ), (int) $query->found_posts );
                        ?></span>
                    </form>

                    <table class="widefat striped">
                        <thead><tr>
                            <th style="width:70px"><?php esc_html_e( 'Image', 'loopkit' ); ?></th>
                            <th><?php esc_html_e( 'Product', 'loopkit' ); ?></th>
                            <th style="width:120px"><?php esc_html_e( 'SKU', 'loopkit' ); ?></th>
                            <th style="width:150px"><?php esc_html_e( 'Price', 'loopkit' ); ?></th>
                            <th style="width:140px"><?php esc_html_e( 'Stock', 'loopkit' ); ?></th>
                            <th style="width:110px"><?php esc_html_e( 'Status', 'loopkit' ); ?></th>
                            <th style="width:120px"></th>
                        </tr></thead>
                        <tbody>
                        <?php if ( ! $query->have_posts() ) : ?>
                            <tr><td colspan="7"><?php esc_html_e( 'No products yet. Add your first one.', 'loopkit' ); ?></td></tr>
                        <?php else : ?>
                            <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                                <?php
                                $id   = get_the_ID();
                                $d    = Loopkit_Module_WooCommerce::get_product_data( $id );
                                $edit = admin_url( 'admin.php?page=loopkit-product-edit&product=' . $id );
                                ?>
                                <tr>
                                    <td>
                                        <?php if ( has_post_thumbnail( $id ) ) : ?>
                                            <?php echo get_the_post_thumbnail( $id, array( 48, 48 ), array( 'style' => 'border-radius:6px;object-fit:cover' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-format-image" style="color:#c3c4c7"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><a href="<?php echo esc_url( $edit ); ?>"><?php the_title(); ?></a></strong>
                                        <?php if ( 'variable' === $d['type'] ) : ?>
                                            <span class="lk-pill"><?php esc_html_e( 'Variable', 'loopkit' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $d['sku'] ?: '—' ); ?></td>
                                    <td>
                                        <?php if ( Loopkit_Module_WooCommerce::is_on_sale( $id ) ) : ?>
                                            <del><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $d['regular_price'] ) ); ?></del>
                                            <strong><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $d['sale_price'] ) ); ?></strong>
                                        <?php else : ?>
                                            <?php echo esc_html( '' !== $d['regular_price'] ? Loopkit_Module_WooCommerce::format_price( $d['regular_price'] ) : '—' ); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $labels = array(
                                            'instock'     => array( __( 'In stock', 'loopkit' ), '#008a20' ),
                                            'outofstock'  => array( __( 'Out of stock', 'loopkit' ), '#b32d2e' ),
                                            'onbackorder' => array( __( 'On backorder', 'loopkit' ), '#996800' ),
                                        );
                                        $info = $labels[ $d['stock_status'] ] ?? $labels['instock'];
                                        $qty  = ( '1' === (string) $d['manage_stock'] && '' !== $d['stock_qty'] ) ? ' (' . (int) $d['stock_qty'] . ')' : '';
                                        ?>
                                        <span style="color:<?php echo esc_attr( $info[1] ); ?>"><?php echo esc_html( $info[0] . $qty ); ?></span>
                                    </td>
                                    <td><?php echo esc_html( get_post_status() === 'publish' ? __( 'Published', 'loopkit' ) : ucfirst( get_post_status() ) ); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'loopkit' ); ?></a> |
                                        <a href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'loopkit' ); ?></a> |
                                        <a style="color:#b32d2e" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::MENU . '&loopkit_shop_admin_action=delete_product&product=' . $id ), 'loopkit_delete_product_' . $id ) ); ?>"
                                           onclick="return confirm('<?php esc_attr_e( 'Move this product to trash?', 'loopkit' ); ?>')"><?php esc_html_e( 'Trash', 'loopkit' ); ?></a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php wp_reset_postdata(); ?>
                        <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ( $query->max_num_pages > 1 ) : ?>
                        <div class="tablenav"><div class="tablenav-pages"><?php
                            echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                'base'    => admin_url( 'admin.php?page=' . self::MENU . '&paged=%#%&s=' . rawurlencode( $search ) ),
                                'format'  => '',
                                'current' => $paged,
                                'total'   => $query->max_num_pages,
                            ) );
                        ?></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <style>.lk-pill { display:inline-block; margin-left:6px; padding:1px 8px; border-radius:999px; background:#eef6fd; color:#1d6fa5; font-size:11px; }</style>
        <?php
    }

    /* ---------------------------------------------------------------------
     * Product editor
     * ------------------------------------------------------------------- */

    /**
     * Add / edit a product on Loopkit's own screen.
     */
    public static function render_product_editor() {
        $id   = absint( $_GET['product'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
        $post = $id ? get_post( $id ) : null;
        if ( $post && Loopkit_Module_WooCommerce::POST_TYPE !== $post->post_type ) {
            $post = null;
            $id   = 0;
        }

        $title       = $post ? $post->post_title : '';
        $content     = $post ? $post->post_content : '';
        $excerpt     = $post ? $post->post_excerpt : '';
        $status      = $post ? $post->post_status : 'publish';
        $thumb       = $post ? (int) get_post_thumbnail_id( $id ) : 0;
        $cat_ids     = $post ? wp_get_object_terms( $id, Loopkit_Module_WooCommerce::TAX_CAT, array( 'fields' => 'ids' ) ) : array();
        $tag_names   = $post ? wp_get_object_terms( $id, Loopkit_Module_WooCommerce::TAX_TAG, array( 'fields' => 'names' ) ) : array();
        $categories  = get_terms( array( 'taxonomy' => Loopkit_Module_WooCommerce::TAX_CAT, 'hide_empty' => false ) );
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php echo $id ? esc_html__( 'Edit Product', 'loopkit' ) : esc_html__( 'Add Product', 'loopkit' ); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU ) ); ?>"><?php esc_html_e( 'All products', 'loopkit' ); ?></a>
            <?php if ( $id ) : ?>
                <a class="page-title-action" href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'loopkit' ); ?></a>
            <?php endif; ?>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Product saved.', 'loopkit' ); ?></p></div>
            <?php endif; ?>

            <form method="post" class="lk-automation-form lk-product-form">
                <?php wp_nonce_field( 'loopkit_save_product' ); ?>
                <input type="hidden" name="loopkit_shop_admin_action" value="save_product">
                <input type="hidden" name="product_id" value="<?php echo (int) $id; ?>">

                <div class="lk-product-columns">
                    <div class="lk-product-main">
                        <div class="lk-card lk-auto-card">
                            <div class="lk-card-header">
                                <span class="lk-auto-step">1</span>
                                <h3><?php esc_html_e( 'Product', 'loopkit' ); ?></h3>
                            </div>
                            <div class="lk-card-body">
                                <div class="lk-auto-field">
                                    <label for="lk-product-title"><?php esc_html_e( 'Name', 'loopkit' ); ?></label>
                                    <input type="text" id="lk-product-title" name="product_title" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'Boxing Gloves', 'loopkit' ); ?>" required>
                                </div>
                                <div class="lk-auto-field" style="margin-top:16px">
                                    <label for="lk-product-excerpt"><?php esc_html_e( 'Short description', 'loopkit' ); ?></label>
                                    <textarea id="lk-product-excerpt" name="product_excerpt" rows="2" placeholder="<?php esc_attr_e( 'One or two lines shown in listings.', 'loopkit' ); ?>"><?php echo esc_textarea( $excerpt ); ?></textarea>
                                </div>
                                <div class="lk-auto-field" style="margin-top:16px">
                                    <label><?php esc_html_e( 'Description', 'loopkit' ); ?></label>
                                    <?php
                                    wp_editor( $content, 'product_description', array(
                                        'textarea_name' => 'product_description',
                                        'textarea_rows' => 10,
                                        'media_buttons' => true,
                                    ) );
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="lk-card lk-auto-card">
                            <div class="lk-card-header">
                                <span class="lk-auto-step">2</span>
                                <h3><?php esc_html_e( 'Product Data', 'loopkit' ); ?></h3>
                                <p class="lk-card-desc"><?php esc_html_e( 'Prices, stock, shipping, options and downloads.', 'loopkit' ); ?></p>
                            </div>
                            <div class="lk-card-body">
                                <?php Loopkit_Module_WooCommerce::product_data_fields( $id ); ?>
                            </div>
                        </div>

                        <?php
                        // Custom Fields module: any field group aimed at products shows here too.
                        $field_groups = self::field_groups_for_product( $id );
                        $step         = 3;
                        foreach ( $field_groups as $group ) :
                            ?>
                            <div class="lk-card lk-auto-card">
                                <div class="lk-card-header">
                                    <span class="lk-auto-step"><?php echo (int) $step; ?></span>
                                    <h3><?php echo esc_html( $group['title'] ); ?></h3>
                                    <p class="lk-card-desc"><?php esc_html_e( 'From your Custom Fields groups.', 'loopkit' ); ?></p>
                                </div>
                                <div class="lk-card-body">
                                    <?php Loopkit_Module_ACF::render_fields( $group, $id ); ?>
                                </div>
                            </div>
                            <?php
                            $step++;
                        endforeach;
                        ?>
                    </div>

                    <div class="lk-product-side">
                        <div class="lk-card lk-auto-card">
                            <div class="lk-card-header"><h3><?php esc_html_e( 'Publish', 'loopkit' ); ?></h3></div>
                            <div class="lk-card-body">
                                <div class="lk-auto-field">
                                    <label for="lk-product-status"><?php esc_html_e( 'Status', 'loopkit' ); ?></label>
                                    <select id="lk-product-status" name="product_status">
                                        <option value="publish" <?php selected( $status, 'publish' ); ?>><?php esc_html_e( 'Published', 'loopkit' ); ?></option>
                                        <option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'loopkit' ); ?></option>
                                        <option value="private" <?php selected( $status, 'private' ); ?>><?php esc_html_e( 'Private', 'loopkit' ); ?></option>
                                    </select>
                                </div>
                                <p class="lk-auto-submit" style="margin-top:14px">
                                    <button type="submit" class="lk-btn lk-btn-primary"><?php echo $id ? esc_html__( 'Update Product', 'loopkit' ) : esc_html__( 'Add Product', 'loopkit' ); ?></button>
                                </p>
                            </div>
                        </div>

                        <div class="lk-card lk-auto-card">
                            <div class="lk-card-header"><h3><?php esc_html_e( 'Featured Image', 'loopkit' ); ?></h3></div>
                            <div class="lk-card-body">
                                <div class="lk-meta-media lk-thumb-box" data-multiple="0">
                                    <input type="hidden" name="product_thumbnail" id="lk-product-thumb" value="<?php echo (int) $thumb; ?>">
                                    <div class="lk-thumb-preview">
                                        <?php if ( $thumb ) : ?>
                                            <img src="<?php echo esc_url( (string) wp_get_attachment_image_url( $thumb, 'medium' ) ); ?>" alt="">
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button lk-thumb-pick"><?php esc_html_e( 'Choose image', 'loopkit' ); ?></button>
                                    <button type="button" class="button lk-thumb-clear"><?php esc_html_e( 'Remove', 'loopkit' ); ?></button>
                                </div>
                            </div>
                        </div>

                        <div class="lk-card lk-auto-card">
                            <div class="lk-card-header"><h3><?php esc_html_e( 'Product Gallery', 'loopkit' ); ?></h3></div>
                            <div class="lk-card-body">
                                <?php Loopkit_Module_WooCommerce::gallery_field( $id ); ?>
                            </div>
                        </div>

                        <div class="lk-card lk-auto-card">
                            <div class="lk-card-header"><h3><?php esc_html_e( 'Categories & Tags', 'loopkit' ); ?></h3></div>
                            <div class="lk-card-body">
                                <div class="lk-auto-field">
                                    <label><?php esc_html_e( 'Categories', 'loopkit' ); ?></label>
                                    <div class="lk-cat-list">
                                        <?php if ( empty( $categories ) || is_wp_error( $categories ) ) : ?>
                                            <p class="lk-auto-help"><?php esc_html_e( 'No categories yet — add some under Shop > Categories.', 'loopkit' ); ?></p>
                                        <?php else : ?>
                                            <?php foreach ( $categories as $term ) : ?>
                                                <label class="lk-auto-check"><input type="checkbox" name="product_cats[]" value="<?php echo (int) $term->term_id; ?>" <?php checked( in_array( (int) $term->term_id, (array) $cat_ids, true ) ); ?>> <?php echo esc_html( $term->name ); ?></label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="lk-auto-field" style="margin-top:14px">
                                    <label for="lk-product-tags"><?php esc_html_e( 'Tags', 'loopkit' ); ?></label>
                                    <input type="text" id="lk-product-tags" name="product_tags" value="<?php echo esc_attr( implode( ', ', (array) $tag_names ) ); ?>" placeholder="<?php esc_attr_e( 'new, sale, featured', 'loopkit' ); ?>">
                                    <span class="lk-auto-help"><?php esc_html_e( 'Separate with commas.', 'loopkit' ); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <style>
        .lk-product-columns { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 20px; align-items: start; }
        .lk-product-side .lk-card { margin-bottom: 18px; }
        .lk-thumb-preview img { max-width: 100%; border-radius: 8px; margin-bottom: 10px; display: block; }
        .lk-cat-list { display: flex; flex-direction: column; gap: 6px; max-height: 220px; overflow: auto; }
        @media (max-width: 1100px) { .lk-product-columns { grid-template-columns: 1fr; } }
        </style>
        <script>
        ( function () {
            var input = document.getElementById( 'lk-product-thumb' );
            var prev = document.querySelector( '.lk-thumb-preview' );
            if ( ! input ) return;
            document.querySelector( '.lk-thumb-pick' ).addEventListener( 'click', function () {
                var frame = wp.media( { multiple: false, title: 'Featured image' } );
                frame.on( 'select', function () {
                    var at = frame.state().get( 'selection' ).first().toJSON();
                    input.value = at.id;
                    prev.innerHTML = '<img src="' + ( ( at.sizes && at.sizes.medium ) ? at.sizes.medium.url : at.url ) + '" alt="">';
                } );
                frame.open();
            } );
            document.querySelector( '.lk-thumb-clear' ).addEventListener( 'click', function () {
                input.value = '';
                prev.innerHTML = '';
            } );
        } )();
        </script>
        <?php
    }
}
