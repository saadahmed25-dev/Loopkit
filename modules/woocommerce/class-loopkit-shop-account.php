<?php
/**
 * Loopkit Shop — the customer's side: their orders, their downloads, and the shop's own
 * stock alerts and printable invoices.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Shop_Account {

    /**
     * Hook up.
     */
    public static function init() {
        add_shortcode( 'loopkit_account', array( __CLASS__, 'shortcode_account' ) );
        add_action( 'init', array( __CLASS__, 'handle_download' ) );
        add_action( 'loopkit_order_placed', array( __CLASS__, 'check_stock_levels' ), 20, 1 );
        add_action( 'admin_post_loopkit_invoice', array( __CLASS__, 'handle_invoice' ) );
    }

    /* ---------------------------------------------------------------------
     * My orders
     * ------------------------------------------------------------------ */

    /**
     * [loopkit_account] — the orders belonging to whoever is looking, with their downloads.
     *
     * @return string
     */
    public static function shortcode_account() {
        if ( ! is_user_logged_in() ) {
            ob_start();
            ?>
            <div class="lk-account lk-account-guest">
                <p><?php esc_html_e( 'Log in to see your orders and downloads.', 'loopkit' ); ?></p>
                <?php wp_login_form( array( 'redirect' => get_permalink() ) ); ?>
            </div>
            <?php
            return (string) ob_get_clean();
        }

        $user   = wp_get_current_user();
        $orders = get_posts( array(
            'post_type'      => 'loopkit_order',
            'post_status'    => 'any',
            'posts_per_page' => 30,
            'meta_key'       => '_lk_user', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value'     => $user->ID,  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ) );

        ob_start();
        ?>
        <div class="lk-account">
            <h3 class="lk-account-heading"><?php esc_html_e( 'Your orders', 'loopkit' ); ?></h3>

            <?php if ( empty( $orders ) ) : ?>
                <p class="lk-account-empty"><?php esc_html_e( 'No orders yet.', 'loopkit' ); ?></p>
                <a class="lk-btn-shop lk-btn-shop-primary" href="<?php echo esc_url( get_post_type_archive_link( Loopkit_Module_WooCommerce::POST_TYPE ) ?: home_url( '/' ) ); ?>"><?php esc_html_e( 'Start shopping', 'loopkit' ); ?></a>
            <?php else : ?>
                <table class="lk-account-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Order', 'loopkit' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'loopkit' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'loopkit' ); ?></th>
                            <th><?php esc_html_e( 'Total', 'loopkit' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $orders as $order_post ) : ?>
                        <?php
                        $order    = Loopkit_Shop_Orders::get_order( $order_post->ID );
                        $statuses = Loopkit_Shop_Orders::statuses();
                        $view     = add_query_arg(
                            array( 'lk_order' => $order_post->ID, 'key' => get_post_meta( $order_post->ID, '_lk_key', true ) ),
                            Loopkit_Shop_Orders::thank_you_url()
                        );
                        ?>
                        <tr>
                            <td>#<?php echo (int) $order_post->ID; ?></td>
                            <td><?php echo esc_html( get_the_date( '', $order_post ) ); ?></td>
                            <td><?php echo esc_html( $statuses[ $order['status'] ] ?? $order['status'] ); ?></td>
                            <td><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $order['total'] ) ); ?></td>
                            <td><a href="<?php echo esc_url( $view ); ?>"><?php esc_html_e( 'View', 'loopkit' ); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                $downloads = self::downloads_for_user( $user->ID );
                if ( $downloads ) :
                    ?>
                    <h3 class="lk-account-heading"><?php esc_html_e( 'Your downloads', 'loopkit' ); ?></h3>
                    <ul class="lk-account-downloads">
                        <?php foreach ( $downloads as $download ) : ?>
                            <li>
                                <span><?php echo esc_html( $download['name'] ); ?></span>
                                <a class="lk-btn-shop lk-btn-shop-primary" href="<?php echo esc_url( $download['url'] ); ?>"><?php esc_html_e( 'Download', 'loopkit' ); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /* ---------------------------------------------------------------------
     * Downloads
     * ------------------------------------------------------------------ */

    /**
     * Every downloadable file this customer has paid for, each with its own signed link.
     *
     * @param int $user_id User.
     * @return array
     */
    public static function downloads_for_user( $user_id ) {
        $orders = get_posts( array(
            'post_type'      => 'loopkit_order',
            'post_status'    => 'any',
            'posts_per_page' => 50,
            'meta_key'       => '_lk_user', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value'     => $user_id,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ) );

        $out = array();
        foreach ( $orders as $order_post ) {
            $order = Loopkit_Shop_Orders::get_order( $order_post->ID );

            // Only orders that are actually paid for hand over files.
            if ( ! in_array( $order['status'], array( 'processing', 'completed' ), true ) ) {
                continue;
            }

            foreach ( (array) $order['items'] as $item ) {
                $product_id = (int) ( $item['product_id'] ?? 0 );
                $data       = $product_id ? Loopkit_Module_WooCommerce::get_product_data( $product_id ) : array();
                $file       = trim( (string) ( $data['download_url'] ?? '' ) );
                if ( '' === $file ) {
                    continue;
                }

                $out[] = array(
                    'name' => $item['name'] ?? get_the_title( $product_id ),
                    'url'  => self::download_link( $order_post->ID, $product_id ),
                );
            }
        }
        return $out;
    }

    /**
     * A download link that only works for this order and this product.
     *
     * @param int $order_id   Order.
     * @param int $product_id Product.
     * @return string
     */
    public static function download_link( $order_id, $product_id ) {
        return add_query_arg( array(
            'lk_download' => $product_id,
            'order'       => $order_id,
            'token'       => self::download_token( $order_id, $product_id ),
        ), home_url( '/' ) );
    }

    /**
     * The token tying a link to one order, product and order key.
     *
     * @param int $order_id   Order.
     * @param int $product_id Product.
     * @return string
     */
    private static function download_token( $order_id, $product_id ) {
        $key = (string) get_post_meta( $order_id, '_lk_key', true );
        return substr( wp_hash( 'lk-download|' . $order_id . '|' . $product_id . '|' . $key ), 0, 20 );
    }

    /**
     * Serve a file to someone who has paid for it, without ever exposing its real path.
     */
    public static function handle_download() {
        if ( empty( $_GET['lk_download'] ) || empty( $_GET['order'] ) || empty( $_GET['token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $product_id = (int) $_GET['lk_download']; // phpcs:ignore WordPress.Security.NonceVerification
        $order_id   = (int) $_GET['order'];       // phpcs:ignore WordPress.Security.NonceVerification
        $token      = sanitize_text_field( wp_unslash( $_GET['token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

        if ( ! hash_equals( self::download_token( $order_id, $product_id ), $token ) ) {
            wp_die( esc_html__( 'This download link is not valid.', 'loopkit' ), '', array( 'response' => 403 ) );
        }

        $order = Loopkit_Shop_Orders::get_order( $order_id );
        if ( ! $order || ! in_array( $order['status'], array( 'processing', 'completed' ), true ) ) {
            wp_die( esc_html__( 'This order is not ready for downloads yet.', 'loopkit' ), '', array( 'response' => 403 ) );
        }

        $bought = false;
        foreach ( (array) $order['items'] as $item ) {
            if ( (int) ( $item['product_id'] ?? 0 ) === $product_id ) {
                $bought = true;
                break;
            }
        }
        if ( ! $bought ) {
            wp_die( esc_html__( 'That file is not part of this order.', 'loopkit' ), '', array( 'response' => 403 ) );
        }

        $data = Loopkit_Module_WooCommerce::get_product_data( $product_id );
        $file = trim( (string) ( $data['download_url'] ?? '' ) );
        if ( '' === $file ) {
            wp_die( esc_html__( 'There is no file on this product.', 'loopkit' ), '', array( 'response' => 404 ) );
        }

        // Count the download, then hand the file over.
        $count = (int) get_post_meta( $order_id, '_lk_downloads_' . $product_id, true );
        update_post_meta( $order_id, '_lk_downloads_' . $product_id, $count + 1 );

        $uploads = wp_upload_dir();
        $path    = '';
        if ( 0 === strpos( $file, $uploads['baseurl'] ) ) {
            $path = $uploads['basedir'] . substr( $file, strlen( $uploads['baseurl'] ) );
        }

        if ( $path && file_exists( $path ) ) {
            nocache_headers();
            header( 'Content-Type: application/octet-stream' );
            header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
            header( 'Content-Length: ' . filesize( $path ) );
            readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
            exit;
        }

        // A file kept somewhere else: send them straight to it.
        wp_redirect( $file ); // phpcs:ignore WordPress.Security.SafeRedirect
        exit;
    }

    /* ---------------------------------------------------------------------
     * Stock alerts
     * ------------------------------------------------------------------ */

    /**
     * Tell the shop when an order leaves a product low or sold out.
     *
     * @param int $order_id Order.
     */
    public static function check_stock_levels( $order_id ) {
        $settings  = Loopkit_Module_WooCommerce::get_settings();
        $threshold = (int) ( $settings['low_stock'] ?? 3 );
        $to        = $settings['order_email'] ?: get_option( 'admin_email' );
        $order     = Loopkit_Shop_Orders::get_order( $order_id );

        foreach ( (array) $order['items'] as $item ) {
            $product_id = (int) ( $item['product_id'] ?? 0 );
            if ( ! $product_id ) {
                continue;
            }

            $data = Loopkit_Module_WooCommerce::get_product_data( $product_id );
            if ( empty( $data['manage_stock'] ) ) {
                continue;
            }

            $left = (int) $data['stock_qty'];
            $name = get_the_title( $product_id );

            if ( $left <= 0 ) {
                wp_mail(
                    $to,
                    /* translators: %s: product name */
                    sprintf( __( 'Sold out: %s', 'loopkit' ), $name ),
                    sprintf(
                        /* translators: 1: product name, 2: edit link */
                        __( "%1\$s has just sold out.\n\nRestock it here: %2\$s", 'loopkit' ),
                        $name,
                        admin_url( 'admin.php?page=loopkit-product-edit&product=' . $product_id )
                    )
                );
            } elseif ( $left <= $threshold ) {
                wp_mail(
                    $to,
                    /* translators: %s: product name */
                    sprintf( __( 'Running low: %s', 'loopkit' ), $name ),
                    sprintf(
                        /* translators: 1: product name, 2: units left, 3: edit link */
                        __( "%1\$s is down to %2\$d left.\n\nRestock it here: %3\$s", 'loopkit' ),
                        $name,
                        $left,
                        admin_url( 'admin.php?page=loopkit-product-edit&product=' . $product_id )
                    )
                );
            }
        }
    }

    /* ---------------------------------------------------------------------
     * Invoice
     * ------------------------------------------------------------------ */

    /**
     * A printable invoice for one order, opened from the Orders screen.
     */
    public static function handle_invoice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to see invoices.', 'loopkit' ) );
        }
        check_admin_referer( 'loopkit_invoice' );

        $order_id = (int) ( $_GET['order'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
        $order    = Loopkit_Shop_Orders::get_order( $order_id );
        if ( ! $order ) {
            wp_die( esc_html__( 'That order does not exist.', 'loopkit' ) );
        }

        $settings = Loopkit_Module_WooCommerce::get_settings();
        $customer = $order['customer'];

        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php printf( esc_html__( 'Invoice #%d', 'loopkit' ), (int) $order_id ); ?></title>
    <style>
        body { font-family: -apple-system, "Segoe UI", Roboto, sans-serif; color: #101828; margin: 0; padding: 40px; background: #f4f6f9; }
        .sheet { max-width: 760px; margin: 0 auto; padding: 40px; background: #fff; border-radius: 10px; }
        .head { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 32px; }
        h1 { margin: 0 0 4px; font-size: 24px; }
        .muted { color: #667085; font-size: 13px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin: 24px 0; font-size: 14px; }
        th, td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #eaecf0; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #667085; }
        td.num, th.num { text-align: right; }
        .totals { margin-left: auto; width: 280px; font-size: 14px; }
        .totals div { display: flex; justify-content: space-between; padding: 6px 0; }
        .totals .grand { border-top: 2px solid #101828; margin-top: 6px; padding-top: 10px; font-size: 17px; font-weight: 700; }
        .print { margin-bottom: 20px; text-align: center; }
        .print button { padding: 10px 22px; border: 0; border-radius: 6px; background: #38A8F5; color: #fff; font-size: 14px; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .sheet { border-radius: 0; padding: 0; } .print { display: none; } }
    </style>
</head>
<body>
    <div class="print"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print or save as PDF', 'loopkit' ); ?></button></div>

    <div class="sheet">
        <div class="head">
            <div>
                <h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
                <p class="muted"><?php echo esc_html( home_url( '/' ) ); ?><br><?php echo esc_html( $settings['order_email'] ?: get_option( 'admin_email' ) ); ?></p>
            </div>
            <div style="text-align:right">
                <h1><?php printf( esc_html__( 'Invoice #%d', 'loopkit' ), (int) $order_id ); ?></h1>
                <p class="muted">
                    <?php echo esc_html( get_the_date( '', $order_id ) ); ?><br>
                    <?php echo esc_html( Loopkit_Shop_Orders::statuses()[ $order['status'] ] ?? $order['status'] ); ?>
                </p>
            </div>
        </div>

        <p class="muted">
            <strong><?php esc_html_e( 'Billed to', 'loopkit' ); ?></strong><br>
            <?php echo esc_html( trim( $customer['first_name'] . ' ' . $customer['last_name'] ) ); ?><br>
            <?php echo nl2br( esc_html( trim(
                $customer['address'] . "\n"
                . trim( $customer['city'] . ' ' . $customer['state'] . ' ' . $customer['postcode'] ) . "\n"
                . $customer['country']
            ) ) ); ?><br>
            <?php echo esc_html( $customer['email'] ); ?> &middot; <?php echo esc_html( $customer['phone'] ); ?>
        </p>

        <table>
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Item', 'loopkit' ); ?></th>
                    <th class="num"><?php esc_html_e( 'Qty', 'loopkit' ); ?></th>
                    <th class="num"><?php esc_html_e( 'Price', 'loopkit' ); ?></th>
                    <th class="num"><?php esc_html_e( 'Total', 'loopkit' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( (array) $order['items'] as $item ) : ?>
                <tr>
                    <td><?php echo esc_html( $item['name'] ); ?><?php echo ! empty( $item['variation'] ) ? ' — ' . esc_html( $item['variation'] ) : ''; ?></td>
                    <td class="num"><?php echo (int) $item['qty']; ?></td>
                    <td class="num"><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $item['price'] ) ); ?></td>
                    <td class="num"><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $item['total'] ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div><span><?php esc_html_e( 'Subtotal', 'loopkit' ); ?></span><span><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $order['subtotal'] ) ); ?></span></div>
            <?php if ( ! empty( $order['discount'] ) ) : ?>
                <div><span><?php esc_html_e( 'Discount', 'loopkit' ); ?></span><span>-<?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $order['discount'] ) ); ?></span></div>
            <?php endif; ?>
            <div><span><?php esc_html_e( 'Shipping', 'loopkit' ); ?></span><span><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $order['shipping'] ) ); ?></span></div>
            <?php if ( ! empty( $order['tax'] ) ) : ?>
                <div><span><?php echo esc_html( $settings['tax_label'] ?: __( 'Tax', 'loopkit' ) ); ?></span><span><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $order['tax'] ) ); ?></span></div>
            <?php endif; ?>
            <div class="grand"><span><?php esc_html_e( 'Total', 'loopkit' ); ?></span><span><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $order['total'] ) ); ?></span></div>
        </div>

        <p class="muted"><?php esc_html_e( 'Payment method', 'loopkit' ); ?>: <?php
            $methods = Loopkit_Shop_Orders::payment_methods();
            echo esc_html( $methods[ $order['payment'] ]['label'] ?? $order['payment'] );
        ?></p>
    </div>
</body>
</html>
        <?php
        exit;
    }

    /**
     * The link to an order's invoice.
     *
     * @param int $order_id Order.
     * @return string
     */
    public static function invoice_link( $order_id ) {
        return add_query_arg(
            array(
                'action'   => 'loopkit_invoice',
                'order'    => (int) $order_id,
                '_wpnonce' => wp_create_nonce( 'loopkit_invoice' ),
            ),
            admin_url( 'admin-post.php' )
        );
    }
}
