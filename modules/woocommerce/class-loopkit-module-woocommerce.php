<?php
/**
 * Loopkit Shop module — Part 1: products and their data.
 *
 * Loopkit's own store. It does not need the WooCommerce plugin and never touches WooCommerce
 * data if that plugin is installed.
 *
 * This part covers:
 *   • The product post type, product categories and product tags
 *   • The product data box: type, prices (with sale dates), SKU, stock, shipping, downloads
 *   • Variable products: attributes and per-variation price / SKU / stock
 *   • A product gallery beside the featured image
 *   • Shop settings: currency, symbol position, decimals, separators, stock behaviour
 *   • Admin columns: image, price, SKU, stock
 *   • Dynamic Tags for everything above, so any widget can already show product data
 *
 * Cart, checkout, orders and the shop widgets are Parts 2 and 3.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Module_WooCommerce {

    const POST_TYPE       = 'loopkit_product';
    const TAX_CAT         = 'loopkit_product_cat';
    const TAX_TAG         = 'loopkit_product_tag';
    const OPTION_SETTINGS = 'loopkit_shop_settings';

    /**
     * Start the module.
     */
    public function init() {
        add_action( 'loopkit_module_toggled', array( __CLASS__, 'on_module_toggled' ), 10, 2 );

        require_once __DIR__ . '/class-loopkit-shop-cart.php';
        require_once __DIR__ . '/class-loopkit-shop-orders.php';
        Loopkit_Shop_Orders::init();

        require_once __DIR__ . '/class-loopkit-shop-coupons.php';
        Loopkit_Shop_Coupons::init();

        require_once __DIR__ . '/class-loopkit-shop-reviews.php';
        Loopkit_Shop_Reviews::init();

        require_once __DIR__ . '/class-loopkit-shop-account.php';
        Loopkit_Shop_Account::init();

        if ( is_admin() ) {
            require_once __DIR__ . '/class-loopkit-shop-admin.php';
            Loopkit_Shop_Admin::init();
        }

        add_action( 'init', array( $this, 'register_post_type' ), 5 );
        add_action( 'admin_menu', array( $this, 'register_menu' ), 30 );
        add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
        add_action( 'wp_ajax_loopkit_shop_rate', array( __CLASS__, 'ajax_rate' ) );
        add_action( 'wp_ajax_loopkit_quick_view', array( __CLASS__, 'ajax_quick_view' ) );
        add_action( 'wp_ajax_nopriv_loopkit_quick_view', array( __CLASS__, 'ajax_quick_view' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_product' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );

        add_filter( 'loopkit_dynamic_tags', array( $this, 'add_dynamic_tags' ) );
    }

    /* ---------------------------------------------------------------------
     * Settings
     * ------------------------------------------------------------------- */

    /**
     * Switching the Shop module on creates the Cart, Checkout and Thank You pages, so the
     * store works straight away instead of needing three pages made by hand.
     *
     * @param string $id      Module id.
     * @param bool   $enabled New state.
     */
    public static function on_module_toggled( $id, $enabled ) {
        if ( 'woocommerce' !== $id || ! $enabled ) {
            return;
        }
        self::ensure_pages();
    }

    /**
     * Create any missing shop page and remember it in the settings.
     *
     * @return array The page ids, keyed by setting.
     */
    public static function ensure_pages() {
        $settings = self::get_settings();
        $pages    = array(
            'cart_page'      => array( __( 'Cart', 'loopkit' ), '[loopkit_cart]' ),
            'checkout_page'  => array( __( 'Checkout', 'loopkit' ), '[loopkit_checkout]' ),
            'thank_you_page' => array( __( 'Thank You', 'loopkit' ), '[loopkit_thank_you]' ),
            'account_page'   => array( __( 'My Account', 'loopkit' ), '[loopkit_account]' ),
        );

        foreach ( $pages as $key => $page ) {
            $existing = ! empty( $settings[ $key ] ) ? get_post( (int) $settings[ $key ] ) : null;
            if ( $existing && 'page' === $existing->post_type && 'trash' !== $existing->post_status ) {
                continue;
            }

            // Reuse a page that already carries the shortcode, before making a new one.
            $found = get_posts( array(
                'post_type'      => 'page',
                'post_status'    => array( 'publish', 'draft' ),
                'posts_per_page' => 1,
                's'              => $page[1],
            ) );

            $settings[ $key ] = $found ? (int) $found[0]->ID : (int) wp_insert_post( array(
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => $page[0],
                'post_content' => $page[1],
            ) );
        }

        update_option( self::OPTION_SETTINGS, $settings );
        return $settings;
    }

    /**
     * Shop settings with their defaults.
     *
     * @return array
     */
    public static function get_settings() {
        $saved = get_option( self::OPTION_SETTINGS, array() );
        return wp_parse_args( is_array( $saved ) ? $saved : array(), array(
            'currency'         => 'PKR',
            'currency_symbol'  => 'Rs',
            'symbol_position'  => 'left_space',
            'decimals'         => 0,
            'decimal_sep'      => '.',
            'thousand_sep'     => ',',
            'manage_stock'     => 1,
            'hide_out_of_stock' => 0,
            'low_stock_at'     => 2,
            'shop_slug'        => 'shop',
            // Part 2: payments, shipping, pages
            'pay_cod'           => 1,
            'cod_note'          => __( 'Pay with cash when your order arrives.', 'loopkit' ),
            'pay_bank'          => 0,
            'bank_note'         => '',
            'flat_rate'         => 0,
            'free_shipping_over' => '',
            'pickup'            => 0,
            'default_shipping'  => 'flat',
            'country'           => '',
            'order_email'       => '',
            'cart_page'         => 0,
            'checkout_page'     => 0,
            'thank_you_page'    => 0,
            'account_page'      => 0,
            'low_stock'         => 3,
            'custom_methods'    => array(),
            'zones'             => array(),
            'tax_enabled'       => 0,
            'tax_rate'          => '',
            'tax_label'         => __( 'Tax', 'loopkit' ),
            'tax_included'      => 0,
            'tax_shipping'      => 0,
            // Bank transfer, field by field
            'bank_name'         => '',
            'bank_account_title' => '',
            'bank_account_number' => '',
            'bank_iban'         => '',
            'bank_branch'       => '',
            'bank_swift'        => '',
            // Online payment
            'pay_stripe'        => 0,
            'stripe_secret'     => '',
            'stripe_public'     => '',
            'stripe_inline'     => 1,
            'stripe_note'       => __( 'Pay securely by card.', 'loopkit' ),
        ) );
    }

    /**
     * Clean the shipping zones from the settings screen.
     *
     * @param array $raw Rows.
     * @return array
     */
    public static function sanitize_zones( $raw ) {
        $out = array();
        foreach ( (array) $raw as $row ) {
            $name   = sanitize_text_field( $row['name'] ?? '' );
            $places = sanitize_text_field( $row['places'] ?? '' );
            if ( '' === $name && '' === $places ) {
                continue;
            }
            $out[] = array(
                'name'      => $name ?: $places,
                'places'    => $places,
                'rate'      => '' === ( $row['rate'] ?? '' ) ? 0 : max( 0, (float) $row['rate'] ),
                'free_over' => '' === ( $row['free_over'] ?? '' ) ? '' : max( 0, (float) $row['free_over'] ),
            );
        }
        return $out;
    }

    /**
     * Today's rate, for the converter on the settings screen.
     */
    /**
     * One product, rendered for the quick-view panel.
     *
     * Built on the server rather than from a JSON blob and a JS template,
     * so the markup and the escaping are the same ones the product page
     * uses. A second template drifts from the first.
     */
    public static function ajax_quick_view() {
        check_ajax_referer( 'loopkit_quick_view', 'nonce' );

        $id = isset( $_POST['product'] ) ? absint( $_POST['product'] ) : 0;
        $post = $id ? get_post( $id ) : null;
        if ( ! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
            wp_send_json_error( array( 'message' => __( 'That product is not available.', 'loopkit' ) ), 404 );
        }

        $data  = self::get_product_data( $id );
        $price = (float) self::get_active_price( $id );
        $sale  = self::is_on_sale( $id );
        $stock = 'outofstock' !== ( $data['stock_status'] ?? 'instock' );
        $gallery = get_post_meta( $id, '_loopkit_gallery', true );
        $gallery = is_array( $gallery ) ? $gallery : array();

        ob_start();
        ?>
        <div class="lk-qv">
            <div class="lk-qv-media">
                <?php if ( has_post_thumbnail( $id ) ) : ?>
                    <?php echo get_the_post_thumbnail( $id, 'large', array( 'class' => 'lk-qv-main' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php else : ?>
                    <span class="lk-qv-noimage"></span>
                <?php endif; ?>
                <?php if ( $gallery ) : ?>
                    <div class="lk-qv-thumbs">
                        <?php foreach ( array_slice( $gallery, 0, 4 ) as $gid ) : ?>
                            <?php echo wp_get_attachment_image( (int) $gid, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="lk-qv-body">
                <h2 class="lk-qv-title"><?php echo esc_html( get_the_title( $id ) ); ?></h2>

                <div class="lk-qv-price">
                    <?php if ( $sale && '' !== $data['regular_price'] ) : ?>
                        <del><?php echo esc_html( self::format_price( (float) $data['regular_price'] ) ); ?></del>
                    <?php endif; ?>
                    <span><?php echo esc_html( self::format_price( $price ) ); ?></span>
                </div>

                <?php if ( ! $stock ) : ?>
                    <p class="lk-qv-stock lk-qv-out"><?php esc_html_e( 'Sold out', 'loopkit' ); ?></p>
                <?php elseif ( ! empty( $data['manage_stock'] ) && '' !== $data['stock_qty'] && (int) $data['stock_qty'] <= 5 ) : ?>
                    <p class="lk-qv-stock lk-qv-low">
                        <?php
                        printf(
                            /* translators: %d: how many are left */
                            esc_html__( 'Only %d left', 'loopkit' ),
                            (int) $data['stock_qty']
                        );
                        ?>
                    </p>
                <?php endif; ?>

                <?php $excerpt = get_the_excerpt( $id ); ?>
                <?php if ( $excerpt ) : ?>
                    <div class="lk-qv-text"><?php echo wp_kses_post( wpautop( $excerpt ) ); ?></div>
                <?php endif; ?>

                <div class="lk-qv-actions">
                    <?php if ( $stock ) : ?>
                        <label class="screen-reader-text" for="lk-qv-qty"><?php esc_html_e( 'Quantity', 'loopkit' ); ?></label>
                        <input type="number" id="lk-qv-qty" class="lk-qv-qty" value="1" min="1" step="1">
                        <button type="button" class="lk-btn lk-btn-primary lk-add-to-cart"
                                data-product="<?php echo (int) $id; ?>" data-qty-from="#lk-qv-qty">
                            <?php esc_html_e( 'Add to cart', 'loopkit' ); ?>
                        </button>
                    <?php endif; ?>
                    <a class="lk-qv-full" href="<?php echo esc_url( get_permalink( $id ) ); ?>">
                        <?php esc_html_e( 'See full details', 'loopkit' ); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        wp_send_json_success( array(
            'html'  => ob_get_clean(),
            'title' => get_the_title( $id ),
        ) );
    }

    public static function ajax_rate() {
        check_ajax_referer( 'loopkit_shop_rate', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Not allowed.', 'loopkit' ) ), 403 );
        }

        $from = sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) );
        $to   = self::get_settings()['currency'];
        $rate = self::fetch_rate( $from, $to );

        if ( $rate <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Could not fetch a rate — type it in yourself.', 'loopkit' ) ) );
        }

        wp_send_json_success( array(
            'rate'    => round( $rate, 4 ),
            /* translators: 1: from currency, 2: rate, 3: to currency */
            'message' => sprintf( __( '1 %1$s = %2$s %3$s (today)', 'loopkit' ), $from, round( $rate, 4 ), $to ),
        ) );
    }

    /**
     * Multiply every stored price by a rate — used when the shop changes currency.
     * Touches product prices, sale prices and each variation's prices, plus the free
     * shipping threshold and the flat rate, and leaves past orders alone.
     *
     * @param float $rate Conversion rate.
     * @return int How many products were changed.
     */
    public static function convert_prices( $rate ) {
        $rate = (float) $rate;
        if ( $rate <= 0 || 1.0 === $rate ) {
            return 0;
        }

        $decimals = (int) ( self::get_settings()['decimals'] ?? 0 );
        $convert  = function( $value ) use ( $rate, $decimals ) {
            if ( '' === $value || null === $value ) {
                return '';
            }
            return (string) round( (float) $value * $rate, $decimals );
        };

        $products = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        foreach ( $products as $product_id ) {
            foreach ( array( '_loopkit_regular_price', '_loopkit_sale_price' ) as $key ) {
                $value = get_post_meta( $product_id, $key, true );
                if ( '' !== $value && null !== $value ) {
                    update_post_meta( $product_id, $key, $convert( $value ) );
                }
            }

            $variations = get_post_meta( $product_id, '_loopkit_variations', true );
            if ( is_array( $variations ) ) {
                foreach ( $variations as $i => $variation ) {
                    foreach ( array( 'price', 'sale_price' ) as $key ) {
                        if ( '' !== ( $variation[ $key ] ?? '' ) ) {
                            $variations[ $i ][ $key ] = $convert( $variation[ $key ] );
                        }
                    }
                }
                update_post_meta( $product_id, '_loopkit_variations', $variations );
            }

            update_post_meta( $product_id, '_loopkit_price', self::get_active_price( $product_id ) );
        }

        $settings = self::get_settings();
        foreach ( array( 'flat_rate', 'free_shipping_over' ) as $key ) {
            if ( '' !== ( $settings[ $key ] ?? '' ) ) {
                $settings[ $key ] = $convert( $settings[ $key ] );
            }
        }
        update_option( self::OPTION_SETTINGS, $settings );

        return count( $products );
    }

    /**
     * Today's rate between two currencies, from a free public rates service.
     * Returns 0 when it cannot be fetched, so the shop asks for the rate instead of guessing.
     *
     * @param string $from Currency code.
     * @param string $to   Currency code.
     * @return float
     */
    public static function fetch_rate( $from, $to ) {
        $from = strtoupper( sanitize_text_field( $from ) );
        $to   = strtoupper( sanitize_text_field( $to ) );
        if ( $from === $to ) {
            return 1.0;
        }

        $cached = get_transient( 'loopkit_rate_' . $from . '_' . $to );
        if ( $cached ) {
            return (float) $cached;
        }

        $response = wp_remote_get( 'https://open.er-api.com/v6/latest/' . rawurlencode( $from ), array( 'timeout' => 8 ) );
        if ( is_wp_error( $response ) ) {
            return 0.0;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $rate = (float) ( $data['rates'][ $to ] ?? 0 );
        if ( $rate > 0 ) {
            set_transient( 'loopkit_rate_' . $from . '_' . $to, $rate, DAY_IN_SECONDS );
        }
        return $rate;
    }

    /**
     * Currencies the shop can use: code => array( name, symbol ).
     *
     * @return array
     */
    public static function currencies() {
        return array(
            'PKR' => array( 'Pakistani rupee', 'Rs' ),
            'USD' => array( 'US dollar', '$' ),
            'EUR' => array( 'Euro', '€' ),
            'GBP' => array( 'Pound sterling', '£' ),
            'AED' => array( 'UAE dirham', 'د.إ' ),
            'SAR' => array( 'Saudi riyal', 'ر.س' ),
            'INR' => array( 'Indian rupee', '₹' ),
            'BDT' => array( 'Bangladeshi taka', '৳' ),
            'LKR' => array( 'Sri Lankan rupee', 'Rs' ),
            'NPR' => array( 'Nepalese rupee', 'Rs' ),
            'AFN' => array( 'Afghan afghani', '؋' ),
            'IRR' => array( 'Iranian rial', '﷼' ),
            'TRY' => array( 'Turkish lira', '₺' ),
            'QAR' => array( 'Qatari riyal', 'ر.ق' ),
            'KWD' => array( 'Kuwaiti dinar', 'د.ك' ),
            'BHD' => array( 'Bahraini dinar', '.د.ب' ),
            'OMR' => array( 'Omani rial', 'ر.ع.' ),
            'JOD' => array( 'Jordanian dinar', 'د.ا' ),
            'EGP' => array( 'Egyptian pound', 'E£' ),
            'MAD' => array( 'Moroccan dirham', 'د.م.' ),
            'NGN' => array( 'Nigerian naira', '₦' ),
            'KES' => array( 'Kenyan shilling', 'KSh' ),
            'GHS' => array( 'Ghanaian cedi', '₵' ),
            'ZAR' => array( 'South African rand', 'R' ),
            'TZS' => array( 'Tanzanian shilling', 'TSh' ),
            'UGX' => array( 'Ugandan shilling', 'USh' ),
            'CAD' => array( 'Canadian dollar', 'C$' ),
            'AUD' => array( 'Australian dollar', 'A$' ),
            'NZD' => array( 'New Zealand dollar', 'NZ$' ),
            'CHF' => array( 'Swiss franc', 'CHF' ),
            'SEK' => array( 'Swedish krona', 'kr' ),
            'NOK' => array( 'Norwegian krone', 'kr' ),
            'DKK' => array( 'Danish krone', 'kr' ),
            'PLN' => array( 'Polish złoty', 'zł' ),
            'CZK' => array( 'Czech koruna', 'Kč' ),
            'HUF' => array( 'Hungarian forint', 'Ft' ),
            'RON' => array( 'Romanian leu', 'lei' ),
            'BGN' => array( 'Bulgarian lev', 'лв' ),
            'RUB' => array( 'Russian rouble', '₽' ),
            'UAH' => array( 'Ukrainian hryvnia', '₴' ),
            'CNY' => array( 'Chinese yuan', '¥' ),
            'JPY' => array( 'Japanese yen', '¥' ),
            'KRW' => array( 'South Korean won', '₩' ),
            'HKD' => array( 'Hong Kong dollar', 'HK$' ),
            'SGD' => array( 'Singapore dollar', 'S$' ),
            'MYR' => array( 'Malaysian ringgit', 'RM' ),
            'IDR' => array( 'Indonesian rupiah', 'Rp' ),
            'THB' => array( 'Thai baht', '฿' ),
            'VND' => array( 'Vietnamese dong', '₫' ),
            'PHP' => array( 'Philippine peso', '₱' ),
            'TWD' => array( 'New Taiwan dollar', 'NT$' ),
            'BRL' => array( 'Brazilian real', 'R$' ),
            'MXN' => array( 'Mexican peso', 'MX$' ),
            'ARS' => array( 'Argentine peso', '$' ),
            'CLP' => array( 'Chilean peso', '$' ),
            'COP' => array( 'Colombian peso', '$' ),
            'PEN' => array( 'Peruvian sol', 'S/' ),
            'ILS' => array( 'Israeli shekel', '₪' ),
            'MMK' => array( 'Myanmar kyat', 'K' ),
            'KZT' => array( 'Kazakhstani tenge', '₸' ),
            'UZS' => array( 'Uzbekistani som', "so'm" ),
            'AZN' => array( 'Azerbaijani manat', '₼' ),
            'MVR' => array( 'Maldivian rufiyaa', 'Rf' ),
            'ISK' => array( 'Icelandic króna', 'kr' ),
        );
    }

    /**
     * Clean the payment methods someone added on the settings screen.
     *
     * @param array $raw Rows of label / description.
     * @return array
     */
    public static function sanitize_custom_methods( $raw ) {
        $out = array();
        foreach ( (array) $raw as $row ) {
            $label = sanitize_text_field( $row['label'] ?? '' );
            if ( '' === $label ) {
                continue;
            }
            $id = sanitize_key( $row['id'] ?? '' );
            if ( '' === $id ) {
                $id = 'pay_' . sanitize_key( str_replace( '-', '_', sanitize_title( $label ) ) );
            }
            $out[] = array(
                'id'          => $id,
                'label'       => $label,
                'description' => sanitize_textarea_field( $row['description'] ?? '' ),
                'link'        => esc_url_raw( $row['link'] ?? '' ),
            );
        }
        return $out;
    }

    /**
     * The bank details as one readable block, used at checkout and in emails.
     *
     * @return string
     */
    public static function bank_details_text() {
        $s     = self::get_settings();
        $lines = array();

        foreach ( array(
            'bank_name'           => __( 'Bank', 'loopkit' ),
            'bank_account_title'  => __( 'Account title', 'loopkit' ),
            'bank_account_number' => __( 'Account number', 'loopkit' ),
            'bank_iban'           => __( 'IBAN', 'loopkit' ),
            'bank_branch'         => __( 'Branch code', 'loopkit' ),
            'bank_swift'          => __( 'SWIFT / BIC', 'loopkit' ),
        ) as $key => $label ) {
            if ( ! empty( $s[ $key ] ) ) {
                $lines[] = $label . ': ' . $s[ $key ];
            }
        }

        if ( ! empty( $s['bank_note'] ) ) {
            $lines[] = $s['bank_note'];
        }

        return implode( "\n", $lines );
    }

    /**
     * A price as the shop shows it.
     *
     * @param mixed $amount Number.
     * @return string
     */
    public static function format_price( $amount ) {
        if ( '' === $amount || null === $amount ) {
            return '';
        }
        $s      = self::get_settings();
        $number = number_format( (float) $amount, (int) $s['decimals'], $s['decimal_sep'], $s['thousand_sep'] );
        $symbol = $s['currency_symbol'];

        switch ( $s['symbol_position'] ) {
            case 'right':       return $number . $symbol;
            case 'right_space': return $number . ' ' . $symbol;
            case 'left':        return $symbol . $number;
            default:            return $symbol . ' ' . $number;
        }
    }

    /**
     * Settings screen save.
     */
    public function handle_settings_save() {
        if ( empty( $_POST['loopkit_shop_action'] ) ) {
            return;
        }

        // One click creates the Cart, Checkout and Thank You pages with their shortcodes.
        if ( 'create_pages' === $_POST['loopkit_shop_action'] ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You are not allowed to do that.', 'loopkit' ) );
            }
            check_admin_referer( 'loopkit_shop' );

            $settings = self::get_settings();
            $pages    = array(
                'cart_page'      => array( __( 'Cart', 'loopkit' ), '[loopkit_cart]' ),
                'checkout_page'  => array( __( 'Checkout', 'loopkit' ), '[loopkit_checkout]' ),
                'thank_you_page' => array( __( 'Thank You', 'loopkit' ), '[loopkit_thank_you]' ),
            );
            foreach ( $pages as $key => $page ) {
                if ( ! empty( $settings[ $key ] ) && 'publish' === get_post_status( $settings[ $key ] ) ) {
                    continue;
                }
                $settings[ $key ] = wp_insert_post( array(
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_title'   => $page[0],
                    'post_content' => $page[1],
                ) );
            }
            update_option( self::OPTION_SETTINGS, $settings );
            wp_safe_redirect( admin_url( 'admin.php?page=loopkit-shop-settings&saved=pages' ) );
            exit;
        }

        if ( 'convert_prices' === $_POST['loopkit_shop_action'] ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You are not allowed to change prices.', 'loopkit' ) );
            }
            check_admin_referer( 'loopkit_shop' );

            $rate    = (float) ( $_POST['convert_rate'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            $changed = self::convert_prices( $rate );
            wp_safe_redirect( admin_url( 'admin.php?page=loopkit-shop-settings&saved=converted&count=' . (int) $changed ) );
            exit;
        }

        if ( 'save_payments' === $_POST['loopkit_shop_action'] ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You are not allowed to change payment settings.', 'loopkit' ) );
            }
            check_admin_referer( 'loopkit_shop' );

            $raw      = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            $settings = self::get_settings();
            $settings['pay_cod']        = ! empty( $raw['pay_cod'] ) ? 1 : 0;
            $settings['cod_note']       = sanitize_text_field( $raw['cod_note'] ?? '' );
            $settings['pay_bank']       = ! empty( $raw['pay_bank'] ) ? 1 : 0;
            $settings['bank_note']      = sanitize_textarea_field( $raw['bank_note'] ?? '' );
            $settings['order_email']    = sanitize_email( $raw['order_email'] ?? '' );
            $settings['custom_methods'] = self::sanitize_custom_methods( $raw['custom_methods'] ?? array() );
            foreach ( array( 'bank_name', 'bank_account_title', 'bank_account_number', 'bank_iban', 'bank_branch', 'bank_swift' ) as $bank_field ) {
                $settings[ $bank_field ] = sanitize_text_field( $raw[ $bank_field ] ?? '' );
            }
            $settings['pay_stripe']    = ! empty( $raw['pay_stripe'] ) ? 1 : 0;
            $settings['stripe_secret'] = sanitize_text_field( $raw['stripe_secret'] ?? '' );
            $settings['stripe_public'] = sanitize_text_field( $raw['stripe_public'] ?? '' );
            $settings['stripe_inline'] = ! empty( $raw['stripe_inline'] ) ? 1 : 0;
            $settings['stripe_note']   = sanitize_text_field( $raw['stripe_note'] ?? '' );
            update_option( self::OPTION_SETTINGS, $settings );

            wp_safe_redirect( admin_url( 'admin.php?page=loopkit-shop-payments&saved=1' ) );
            exit;
        }

        if ( 'save_settings' !== $_POST['loopkit_shop_action'] ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to change shop settings.', 'loopkit' ) );
        }
        check_admin_referer( 'loopkit_shop' );

        $raw     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $current = self::get_settings();
        update_option( self::OPTION_SETTINGS, array_merge( $current, array(
            'currency'          => sanitize_text_field( $raw['currency'] ?? 'PKR' ),
            'currency_symbol'   => sanitize_text_field( $raw['currency_symbol'] ?? 'Rs' ),
            'symbol_position'   => in_array( $raw['symbol_position'] ?? '', array( 'left', 'right', 'left_space', 'right_space' ), true ) ? $raw['symbol_position'] : 'left_space',
            'decimals'          => max( 0, min( 4, (int) ( $raw['decimals'] ?? 0 ) ) ),
            'decimal_sep'       => substr( sanitize_text_field( $raw['decimal_sep'] ?? '.' ), 0, 1 ),
            'thousand_sep'      => substr( sanitize_text_field( $raw['thousand_sep'] ?? ',' ), 0, 1 ),
            'manage_stock'      => ! empty( $raw['manage_stock'] ) ? 1 : 0,
            'hide_out_of_stock' => ! empty( $raw['hide_out_of_stock'] ) ? 1 : 0,
            'low_stock_at'      => max( 0, (int) ( $raw['low_stock_at'] ?? 2 ) ),
            'shop_slug'         => sanitize_title( $raw['shop_slug'] ?? 'shop' ),
            
            
            
            
            'flat_rate'         => '' === ( $raw['flat_rate'] ?? '' ) ? 0 : (float) $raw['flat_rate'],
            'free_shipping_over' => '' === ( $raw['free_shipping_over'] ?? '' ) ? '' : (float) $raw['free_shipping_over'],
            'pickup'            => ! empty( $raw['pickup'] ) ? 1 : 0,
            'default_shipping'  => in_array( $raw['default_shipping'] ?? 'flat', array( 'flat', 'pickup' ), true ) ? $raw['default_shipping'] : 'flat',
            'zones'             => self::sanitize_zones( $raw['zones'] ?? array() ),
            'tax_enabled'       => ! empty( $raw['tax_enabled'] ) ? 1 : 0,
            'tax_rate'          => '' === ( $raw['tax_rate'] ?? '' ) ? '' : max( 0, (float) $raw['tax_rate'] ),
            'tax_label'         => sanitize_text_field( $raw['tax_label'] ?? __( 'Tax', 'loopkit' ) ),
            'tax_included'      => ! empty( $raw['tax_included'] ) ? 1 : 0,
            'tax_shipping'      => ! empty( $raw['tax_shipping'] ) ? 1 : 0,
            'country'           => sanitize_text_field( $raw['country'] ?? '' ),
            
            'cart_page'         => absint( $raw['cart_page'] ?? 0 ),
            'checkout_page'     => absint( $raw['checkout_page'] ?? 0 ),
            'thank_you_page'    => absint( $raw['thank_you_page'] ?? 0 ),
            'account_page'      => absint( $raw['account_page'] ?? 0 ),
            'low_stock'         => max( 0, (int) ( $raw['low_stock'] ?? 3 ) ),
            
        ) ) );
        update_option( 'loopkit_flush_rewrite', 1 );

        wp_safe_redirect( admin_url( 'admin.php?page=loopkit-shop-settings&saved=1' ) );
        exit;
    }

    /* ---------------------------------------------------------------------
     * Post type
     * ------------------------------------------------------------------- */

    /**
     * Products, categories and tags.
     */
    public function register_post_type() {
        $s = self::get_settings();

        register_post_type( self::POST_TYPE, array(
            'labels'       => array(
                'name'          => __( 'Products', 'loopkit' ),
                'singular_name' => __( 'Product', 'loopkit' ),
                'add_new_item'  => __( 'Add Product', 'loopkit' ),
                'edit_item'     => __( 'Edit Product', 'loopkit' ),
                'search_items'  => __( 'Search Products', 'loopkit' ),
                'not_found'     => __( 'No products yet', 'loopkit' ),
            ),
            'public'       => true,
            'has_archive'  => $s['shop_slug'] ?: 'shop',
            'menu_icon'    => 'dashicons-cart',
            // Products are added and edited on Loopkit's own Shop screens, so the WordPress
            // post editor is switched off for them.
            'show_ui'      => false,
            'show_in_menu' => false,
            'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'comments' ),
            'show_in_rest' => true,
            'rewrite'      => array( 'slug' => 'product', 'with_front' => false ),
        ) );

        register_taxonomy( self::TAX_CAT, self::POST_TYPE, array(
            'labels'            => array( 'name' => __( 'Product Categories', 'loopkit' ), 'singular_name' => __( 'Product Category', 'loopkit' ) ),
            'public'            => true,
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => array( 'slug' => 'product-category', 'with_front' => false ),
        ) );

        register_taxonomy( self::TAX_TAG, self::POST_TYPE, array(
            'labels'            => array( 'name' => __( 'Product Tags', 'loopkit' ), 'singular_name' => __( 'Product Tag', 'loopkit' ) ),
            'public'            => true,
            'hierarchical'      => false,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => array( 'slug' => 'product-tag', 'with_front' => false ),
        ) );
    }

    /**
     * Admin screens.
     */
    public function register_menu() {
        // The shop lives in its own top-level "Shop" menu — see Loopkit_Shop_Admin.
    }

    /**
     * Media picker for the gallery.
     *
     * @param string $hook Screen.
     */
    public function enqueue( $hook ) {
        if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && self::POST_TYPE === get_post_type() ) {
            wp_enqueue_media();
        }
    }

    /* ---------------------------------------------------------------------
     * Product data
     * ------------------------------------------------------------------- */

    /**
     * All the product fields and their meta keys.
     *
     * @param int $post_id Product.
     * @return array
     */
    public static function get_product_data( $post_id ) {
        $keys = array(
            'type'           => 'simple',
            'regular_price'  => '',
            'sale_price'     => '',
            'sale_from'      => '',
            'sale_to'        => '',
            'sku'            => '',
            'stock_status'   => 'instock',
            'manage_stock'   => '',
            'stock_qty'      => '',
            'weight'         => '',
            'length'         => '',
            'width'          => '',
            'height'         => '',
            'downloadable'   => '',
            'download_url'   => '',
            'gallery'        => '',
            'attributes'     => array(),
            'variations'     => array(),
        );

        $out = array();
        foreach ( $keys as $key => $default ) {
            $value = get_post_meta( $post_id, '_loopkit_' . $key, true );
            $out[ $key ] = ( '' === $value || null === $value ) ? $default : $value;
        }
        return $out;
    }

    /**
     * The price a customer pays right now (sale price if the sale is running).
     *
     * @param int $post_id Product.
     * @return string
     */
    public static function get_active_price( $post_id ) {
        $data = self::get_product_data( $post_id );
        $sale = $data['sale_price'];

        if ( '' !== $sale ) {
            $now  = current_time( 'timestamp' );
            $from = $data['sale_from'] ? strtotime( $data['sale_from'] . ' 00:00:00' ) : 0;
            $to   = $data['sale_to'] ? strtotime( $data['sale_to'] . ' 23:59:59' ) : 0;
            if ( ( ! $from || $now >= $from ) && ( ! $to || $now <= $to ) ) {
                return (string) $sale;
            }
        }
        return (string) $data['regular_price'];
    }

    /**
     * Is this product on sale right now?
     *
     * @param int $post_id Product.
     * @return bool
     */
    public static function is_on_sale( $post_id ) {
        $data = self::get_product_data( $post_id );
        return '' !== $data['sale_price'] && self::get_active_price( $post_id ) === (string) $data['sale_price'];
    }

    /**
     * Product data boxes.
     */
    public function add_meta_boxes() {
        add_meta_box( 'loopkit-product-data', __( 'Product Data', 'loopkit' ), array( $this, 'render_product_box' ), self::POST_TYPE, 'normal', 'high' );
        add_meta_box( 'loopkit-product-gallery', __( 'Product Gallery', 'loopkit' ), array( $this, 'render_gallery_box' ), self::POST_TYPE, 'side', 'low' );
    }

    /**
     * The product data box.
     *
     * @param WP_Post $post Product.
     */
    public function render_product_box( $post ) {
        wp_nonce_field( 'loopkit_product', 'loopkit_product_nonce' );
        self::product_data_fields( $post->ID );
    }

    /**
     * The product data tabs — prices, stock, shipping, variations, downloads.
     * Used by the Shop screens and by the meta box.
     *
     * @param int $post_id Product (0 for a new one).
     */
    public static function product_data_fields( $post_id ) {
        $d = self::get_product_data( (int) $post_id );
        $attributes = is_array( $d['attributes'] ) ? $d['attributes'] : array();
        $variations = is_array( $d['variations'] ) ? $d['variations'] : array();
        if ( ! $attributes ) {
            $attributes = array( array( 'name' => '', 'values' => '' ) );
        }
        if ( ! $variations ) {
            $variations = array( array( 'combo' => '', 'price' => '', 'sale_price' => '', 'sku' => '', 'stock_qty' => '' ) );
        }
        ?>
        <div class="lk-product-box">
            <div class="lk-product-tabs">
                <button type="button" class="lk-ptab lk-ptab-active" data-tab="general"><?php esc_html_e( 'General', 'loopkit' ); ?></button>
                <button type="button" class="lk-ptab" data-tab="inventory"><?php esc_html_e( 'Inventory', 'loopkit' ); ?></button>
                <button type="button" class="lk-ptab" data-tab="shipping"><?php esc_html_e( 'Shipping', 'loopkit' ); ?></button>
                <button type="button" class="lk-ptab" data-tab="variations"><?php esc_html_e( 'Attributes & Variations', 'loopkit' ); ?></button>
                <button type="button" class="lk-ptab" data-tab="downloads"><?php esc_html_e( 'Downloads', 'loopkit' ); ?></button>
            </div>

            <div class="lk-product-panel" data-panel="general">
                <div class="lk-pgrid">
                    <label class="lk-pfield">
                        <span><?php esc_html_e( 'Product type', 'loopkit' ); ?></span>
                        <select name="lk_product[type]" id="lk-product-type">
                            <option value="simple" <?php selected( $d['type'], 'simple' ); ?>><?php esc_html_e( 'Simple', 'loopkit' ); ?></option>
                            <option value="variable" <?php selected( $d['type'], 'variable' ); ?>><?php esc_html_e( 'Variable (sizes, colours…)', 'loopkit' ); ?></option>
                        </select>
                    </label>
                    <label class="lk-pfield">
                        <span><?php esc_html_e( 'SKU', 'loopkit' ); ?></span>
                        <input type="text" name="lk_product[sku]" value="<?php echo esc_attr( $d['sku'] ); ?>" placeholder="HD-001">
                    </label>
                    <label class="lk-pfield">
                        <span><?php esc_html_e( 'Regular price', 'loopkit' ); ?></span>
                        <input type="number" step="any" name="lk_product[regular_price]" value="<?php echo esc_attr( $d['regular_price'] ); ?>" placeholder="2499">
                    </label>
                    <label class="lk-pfield">
                        <span><?php esc_html_e( 'Sale price', 'loopkit' ); ?></span>
                        <input type="number" step="any" name="lk_product[sale_price]" value="<?php echo esc_attr( $d['sale_price'] ); ?>" placeholder="1999">
                    </label>
                    <label class="lk-pfield">
                        <span><?php esc_html_e( 'Sale starts', 'loopkit' ); ?></span>
                        <input type="date" name="lk_product[sale_from]" value="<?php echo esc_attr( $d['sale_from'] ); ?>">
                    </label>
                    <label class="lk-pfield">
                        <span><?php esc_html_e( 'Sale ends', 'loopkit' ); ?></span>
                        <input type="date" name="lk_product[sale_to]" value="<?php echo esc_attr( $d['sale_to'] ); ?>">
                    </label>
                </div>
                <p class="lk-phelp">
                    <?php
                    $price = $post_id ? self::get_active_price( (int) $post_id ) : '';
                    if ( '' !== $price ) {
                        /* translators: %s: formatted price */
                        printf( esc_html__( 'Customers currently pay: %s', 'loopkit' ), '<strong>' . esc_html( self::format_price( $price ) ) . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                    ?>
                </p>
            </div>

            <div class="lk-product-panel" data-panel="inventory" hidden>
                <div class="lk-pgrid">
                    <label class="lk-pfield">
                        <span><?php esc_html_e( 'Stock status', 'loopkit' ); ?></span>
                        <select name="lk_product[stock_status]">
                            <option value="instock" <?php selected( $d['stock_status'], 'instock' ); ?>><?php esc_html_e( 'In stock', 'loopkit' ); ?></option>
                            <option value="outofstock" <?php selected( $d['stock_status'], 'outofstock' ); ?>><?php esc_html_e( 'Out of stock', 'loopkit' ); ?></option>
                            <option value="onbackorder" <?php selected( $d['stock_status'], 'onbackorder' ); ?>><?php esc_html_e( 'On backorder', 'loopkit' ); ?></option>
                        </select>
                    </label>
                    <label class="lk-pfield">
                        <span><?php esc_html_e( 'Track quantity', 'loopkit' ); ?></span>
                        <label class="lk-pcheck"><input type="checkbox" name="lk_product[manage_stock]" value="1" <?php checked( '1', (string) $d['manage_stock'] ); ?>> <?php esc_html_e( 'Count stock for this product', 'loopkit' ); ?></label>
                    </label>
                    <label class="lk-pfield">
                        <span><?php esc_html_e( 'Quantity', 'loopkit' ); ?></span>
                        <input type="number" name="lk_product[stock_qty]" value="<?php echo esc_attr( $d['stock_qty'] ); ?>" placeholder="10">
                    </label>
                </div>
            </div>

            <div class="lk-product-panel" data-panel="shipping" hidden>
                <div class="lk-pgrid">
                    <label class="lk-pfield"><span><?php esc_html_e( 'Weight (kg)', 'loopkit' ); ?></span><input type="number" step="any" name="lk_product[weight]" value="<?php echo esc_attr( $d['weight'] ); ?>"></label>
                    <label class="lk-pfield"><span><?php esc_html_e( 'Length (cm)', 'loopkit' ); ?></span><input type="number" step="any" name="lk_product[length]" value="<?php echo esc_attr( $d['length'] ); ?>"></label>
                    <label class="lk-pfield"><span><?php esc_html_e( 'Width (cm)', 'loopkit' ); ?></span><input type="number" step="any" name="lk_product[width]" value="<?php echo esc_attr( $d['width'] ); ?>"></label>
                    <label class="lk-pfield"><span><?php esc_html_e( 'Height (cm)', 'loopkit' ); ?></span><input type="number" step="any" name="lk_product[height]" value="<?php echo esc_attr( $d['height'] ); ?>"></label>
                </div>
            </div>

            <div class="lk-product-panel" data-panel="variations" hidden>
                <p class="lk-phelp"><?php esc_html_e( 'Attributes describe the choices (Size: Small, Medium, Large). Variations give each combination its own price, SKU and stock.', 'loopkit' ); ?></p>

                <h4><?php esc_html_e( 'Attributes', 'loopkit' ); ?></h4>
                <div id="lk-attr-rows">
                    <?php foreach ( $attributes as $i => $attr ) : ?>
                        <div class="lk-prow lk-attr-row">
                            <label class="lk-pfield"><span><?php esc_html_e( 'Name', 'loopkit' ); ?></span>
                                <input type="text" name="lk_product[attributes][<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $attr['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Size', 'loopkit' ); ?>"></label>
                            <label class="lk-pfield lk-pfield-wide"><span><?php esc_html_e( 'Values (comma separated)', 'loopkit' ); ?></span>
                                <input type="text" name="lk_product[attributes][<?php echo (int) $i; ?>][values]" value="<?php echo esc_attr( $attr['values'] ?? '' ); ?>" placeholder="Small, Medium, Large"></label>
                            <button type="button" class="button lk-prow-remove">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p><button type="button" class="button" id="lk-add-attr"><?php esc_html_e( '+ Add attribute', 'loopkit' ); ?></button></p>

                <h4><?php esc_html_e( 'Variations', 'loopkit' ); ?></h4>
                <div id="lk-var-rows">
                    <?php foreach ( $variations as $i => $var ) : ?>
                        <div class="lk-prow lk-var-row">
                            <label class="lk-pfield"><span><?php esc_html_e( 'Combination', 'loopkit' ); ?></span>
                                <input type="text" name="lk_product[variations][<?php echo (int) $i; ?>][combo]" value="<?php echo esc_attr( $var['combo'] ?? '' ); ?>" placeholder="Small / Blue"></label>
                            <label class="lk-pfield"><span><?php esc_html_e( 'Price', 'loopkit' ); ?></span>
                                <input type="number" step="any" name="lk_product[variations][<?php echo (int) $i; ?>][price]" value="<?php echo esc_attr( $var['price'] ?? '' ); ?>"></label>
                            <label class="lk-pfield"><span><?php esc_html_e( 'Sale price', 'loopkit' ); ?></span>
                                <input type="number" step="any" name="lk_product[variations][<?php echo (int) $i; ?>][sale_price]" value="<?php echo esc_attr( $var['sale_price'] ?? '' ); ?>"></label>
                            <label class="lk-pfield"><span><?php esc_html_e( 'SKU', 'loopkit' ); ?></span>
                                <input type="text" name="lk_product[variations][<?php echo (int) $i; ?>][sku]" value="<?php echo esc_attr( $var['sku'] ?? '' ); ?>"></label>
                            <label class="lk-pfield"><span><?php esc_html_e( 'Stock', 'loopkit' ); ?></span>
                                <input type="number" name="lk_product[variations][<?php echo (int) $i; ?>][stock_qty]" value="<?php echo esc_attr( $var['stock_qty'] ?? '' ); ?>"></label>
                            <button type="button" class="button lk-prow-remove">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p>
                    <button type="button" class="button" id="lk-add-var"><?php esc_html_e( '+ Add variation', 'loopkit' ); ?></button>
                    <button type="button" class="button" id="lk-build-vars"><?php esc_html_e( 'Build from attributes', 'loopkit' ); ?></button>
                </p>
            </div>

            <div class="lk-product-panel" data-panel="downloads" hidden>
                <div class="lk-pgrid">
                    <label class="lk-pfield">
                        <span><?php esc_html_e( 'Downloadable', 'loopkit' ); ?></span>
                        <label class="lk-pcheck"><input type="checkbox" name="lk_product[downloadable]" value="1" <?php checked( '1', (string) $d['downloadable'] ); ?>> <?php esc_html_e( 'This product is a file', 'loopkit' ); ?></label>
                    </label>
                    <label class="lk-pfield lk-pfield-wide">
                        <span><?php esc_html_e( 'File URL', 'loopkit' ); ?></span>
                        <input type="url" name="lk_product[download_url]" value="<?php echo esc_attr( $d['download_url'] ); ?>" placeholder="https://example.com/file.zip">
                    </label>
                </div>
            </div>
        </div>

        <style>
        .lk-product-tabs { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 16px; border-bottom: 1px solid #e3e8ef; }
        .lk-ptab { padding: 10px 16px; border: 0; background: transparent; cursor: pointer; font-size: 13px; font-weight: 600; color: #50575e; border-bottom: 2px solid transparent; }
        .lk-ptab-active { color: #38A8F5; border-bottom-color: #38A8F5; }
        .lk-pgrid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .lk-pfield { display: flex; flex-direction: column; gap: 6px; font-size: 13px; }
        .lk-pfield > span { font-weight: 600; }
        .lk-pfield input, .lk-pfield select { padding: 8px 12px; border: 1px solid #d7dde5; border-radius: 6px; }
        .lk-pfield-wide { grid-column: span 2; }
        .lk-pcheck { display: inline-flex; align-items: center; gap: 8px; font-weight: 400; }
        .lk-phelp { color: #646970; font-size: 13px; }
        .lk-prow { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)) 40px; gap: 12px; align-items: end; padding: 12px; margin-bottom: 10px; border: 1px solid #e3e8ef; border-radius: 8px; background: #fbfcfe; }
        .lk-prow-remove { color: #b32d2e; }
        .lk-product-box h4 { margin: 18px 0 10px; }
        </style>
        <script>
        ( function () {
            var box = document.querySelector( '.lk-product-box' );
            if ( ! box ) return;

            box.querySelectorAll( '.lk-ptab' ).forEach( function ( tab ) {
                tab.addEventListener( 'click', function () {
                    box.querySelectorAll( '.lk-ptab' ).forEach( function ( t ) { t.classList.remove( 'lk-ptab-active' ); } );
                    tab.classList.add( 'lk-ptab-active' );
                    box.querySelectorAll( '.lk-product-panel' ).forEach( function ( panel ) {
                        panel.hidden = panel.getAttribute( 'data-panel' ) !== tab.getAttribute( 'data-tab' );
                    } );
                } );
            } );

            function renumber( wrap, key, selector ) {
                Array.prototype.forEach.call( wrap.querySelectorAll( selector ), function ( row, i ) {
                    row.querySelectorAll( '[name]' ).forEach( function ( f ) {
                        f.name = f.name.replace( new RegExp( '\\\\[' + key + '\\\\]\\\\[\\\\d+\\\\]' ), '[' + key + '][' + i + ']' );
                    } );
                } );
            }

            function addRow( wrapId, key, selector ) {
                var wrap = document.getElementById( wrapId );
                var row = wrap.children[0].cloneNode( true );
                row.querySelectorAll( 'input' ).forEach( function ( f ) { f.value = ''; } );
                wrap.appendChild( row );
                renumber( wrap, key, selector );
                return row;
            }

            document.getElementById( 'lk-add-attr' ).addEventListener( 'click', function () { addRow( 'lk-attr-rows', 'attributes', '.lk-attr-row' ); } );
            document.getElementById( 'lk-add-var' ).addEventListener( 'click', function () { addRow( 'lk-var-rows', 'variations', '.lk-var-row' ); } );

            // Every combination of the attribute values, as variation rows
            document.getElementById( 'lk-build-vars' ).addEventListener( 'click', function () {
                var lists = [];
                document.querySelectorAll( '.lk-attr-row' ).forEach( function ( row ) {
                    var values = row.querySelectorAll( 'input' )[1].value.split( ',' ).map( function ( v ) { return v.trim(); } ).filter( Boolean );
                    if ( values.length ) lists.push( values );
                } );
                if ( ! lists.length ) { window.alert( 'Add an attribute with values first.' ); return; }

                var combos = lists.reduce( function ( acc, list ) {
                    var out = [];
                    acc.forEach( function ( prefix ) { list.forEach( function ( v ) { out.push( prefix ? prefix + ' / ' + v : v ); } ); } );
                    return out;
                }, [ '' ] );

                var wrap = document.getElementById( 'lk-var-rows' );
                var existing = {};
                wrap.querySelectorAll( '.lk-var-row' ).forEach( function ( row ) {
                    var combo = row.querySelector( 'input' ).value.trim();
                    if ( combo ) existing[ combo ] = true;
                } );
                // reuse the first row if it is empty
                var first = wrap.querySelector( '.lk-var-row input' );
                combos.forEach( function ( combo, i ) {
                    if ( existing[ combo ] ) return;
                    if ( i === 0 && first && ! first.value ) { first.value = combo; return; }
                    var row = addRow( 'lk-var-rows', 'variations', '.lk-var-row' );
                    row.querySelector( 'input' ).value = combo;
                } );
                renumber( wrap, 'variations', '.lk-var-row' );
            } );

            box.addEventListener( 'click', function ( e ) {
                var btn = e.target.closest( '.lk-prow-remove' );
                if ( ! btn ) return;
                var row = btn.closest( '.lk-prow' );
                var wrap = row.parentNode;
                if ( wrap.children.length === 1 ) {
                    row.querySelectorAll( 'input' ).forEach( function ( f ) { f.value = ''; } );
                    return;
                }
                row.remove();
                renumber( wrap, wrap.id === 'lk-attr-rows' ? 'attributes' : 'variations', wrap.id === 'lk-attr-rows' ? '.lk-attr-row' : '.lk-var-row' );
            } );
        } )();
        </script>
        <?php
    }

    /**
     * Gallery box.
     *
     * @param WP_Post $post Product.
     */
    public function render_gallery_box( $post ) {
        self::gallery_field( $post->ID );
    }

    /**
     * The gallery picker, used by the Shop screens and by the meta box.
     *
     * @param int $post_id Product (0 for a new one).
     */
    public static function gallery_field( $post_id ) {
        $ids = $post_id ? (string) get_post_meta( (int) $post_id, '_loopkit_gallery', true ) : '';
        ?>
        <div class="lk-gallery-box">
            <input type="hidden" name="lk_product[gallery]" id="lk-gallery-ids" value="<?php echo esc_attr( $ids ); ?>">
            <div class="lk-gallery-preview"></div>
            <p>
                <button type="button" class="button" id="lk-gallery-pick"><?php esc_html_e( 'Add images', 'loopkit' ); ?></button>
                <button type="button" class="button" id="lk-gallery-clear"><?php esc_html_e( 'Clear', 'loopkit' ); ?></button>
            </p>
        </div>
        <style>
        .lk-gallery-preview { display: flex; flex-wrap: wrap; gap: 6px; }
        .lk-gallery-preview img { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; }
        </style>
        <script>
        ( function () {
            var input = document.getElementById( 'lk-gallery-ids' );
            var prev = document.querySelector( '.lk-gallery-preview' );
            if ( ! input ) return;
            window.lkGalleryUrls = window.lkGalleryUrls || {};

            function paint() {
                prev.innerHTML = '';
                ( input.value || '' ).split( ',' ).filter( Boolean ).forEach( function ( id ) {
                    var url = window.lkGalleryUrls[ id ];
                    if ( url ) {
                        var img = document.createElement( 'img' );
                        img.src = url;
                        prev.appendChild( img );
                    } else {
                        prev.appendChild( document.createTextNode( '#' + id + ' ' ) );
                    }
                } );
            }
            document.getElementById( 'lk-gallery-pick' ).addEventListener( 'click', function () {
                var frame = wp.media( { multiple: true, title: 'Product gallery' } );
                frame.on( 'select', function () {
                    var ids = ( input.value || '' ).split( ',' ).filter( Boolean );
                    frame.state().get( 'selection' ).map( function ( a ) {
                        var at = a.toJSON();
                        window.lkGalleryUrls[ at.id ] = ( at.sizes && at.sizes.thumbnail ) ? at.sizes.thumbnail.url : at.url;
                        if ( ids.indexOf( String( at.id ) ) === -1 ) ids.push( String( at.id ) );
                    } );
                    input.value = ids.join( ',' );
                    paint();
                } );
                frame.open();
            } );
            document.getElementById( 'lk-gallery-clear' ).addEventListener( 'click', function () { input.value = ''; paint(); } );
            paint();
        } )();
        </script>
        <?php
    }

    /**
     * Save the product data.
     *
     * @param int     $post_id Product.
     * @param WP_Post $post    Post.
     */
    public function save_product( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( empty( $_POST['loopkit_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['loopkit_product_nonce'] ) ), 'loopkit_product' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $raw = wp_unslash( $_POST['lk_product'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        self::save_product_data( $post_id, is_array( $raw ) ? $raw : array() );
    }

    /**
     * Store the product data. Shared by the meta box and the Shop product screen.
     *
     * @param int   $post_id Product.
     * @param array $raw     Raw `lk_product` values.
     */
    public static function save_product_data( $post_id, $raw ) {
        if ( ! is_array( $raw ) || ! $post_id ) {
            return;
        }

        $simple = array(
            'type'         => in_array( $raw['type'] ?? 'simple', array( 'simple', 'variable' ), true ) ? $raw['type'] : 'simple',
            'regular_price' => self::clean_number( $raw['regular_price'] ?? '' ),
            'sale_price'   => self::clean_number( $raw['sale_price'] ?? '' ),
            'sale_from'    => sanitize_text_field( $raw['sale_from'] ?? '' ),
            'sale_to'      => sanitize_text_field( $raw['sale_to'] ?? '' ),
            'sku'          => sanitize_text_field( $raw['sku'] ?? '' ),
            'stock_status' => in_array( $raw['stock_status'] ?? 'instock', array( 'instock', 'outofstock', 'onbackorder' ), true ) ? $raw['stock_status'] : 'instock',
            'manage_stock' => ! empty( $raw['manage_stock'] ) ? '1' : '',
            'stock_qty'    => '' === ( $raw['stock_qty'] ?? '' ) ? '' : (int) $raw['stock_qty'],
            'weight'       => self::clean_number( $raw['weight'] ?? '' ),
            'length'       => self::clean_number( $raw['length'] ?? '' ),
            'width'        => self::clean_number( $raw['width'] ?? '' ),
            'height'       => self::clean_number( $raw['height'] ?? '' ),
            'downloadable' => ! empty( $raw['downloadable'] ) ? '1' : '',
            'download_url' => esc_url_raw( $raw['download_url'] ?? '' ),
            'gallery'      => implode( ',', array_filter( array_map( 'absint', explode( ',', (string) ( $raw['gallery'] ?? '' ) ) ) ) ),
        );

        foreach ( $simple as $key => $value ) {
            if ( '' === $value ) {
                delete_post_meta( $post_id, '_loopkit_' . $key );
            } else {
                update_post_meta( $post_id, '_loopkit_' . $key, $value );
            }
        }

        $attributes = array();
        foreach ( (array) ( $raw['attributes'] ?? array() ) as $attr ) {
            $name = sanitize_text_field( $attr['name'] ?? '' );
            if ( '' === $name ) {
                continue;
            }
            $attributes[] = array(
                'name'   => $name,
                'values' => sanitize_text_field( $attr['values'] ?? '' ),
            );
        }
        update_post_meta( $post_id, '_loopkit_attributes', $attributes );

        $variations = array();
        foreach ( (array) ( $raw['variations'] ?? array() ) as $var ) {
            $combo = sanitize_text_field( $var['combo'] ?? '' );
            if ( '' === $combo ) {
                continue;
            }
            $variations[] = array(
                'combo'      => $combo,
                'price'      => self::clean_number( $var['price'] ?? '' ),
                'sale_price' => self::clean_number( $var['sale_price'] ?? '' ),
                'sku'        => sanitize_text_field( $var['sku'] ?? '' ),
                'stock_qty'  => '' === ( $var['stock_qty'] ?? '' ) ? '' : (int) $var['stock_qty'],
            );
        }
        update_post_meta( $post_id, '_loopkit_variations', $variations );

        // A sortable, searchable copy of the live price
        update_post_meta( $post_id, '_loopkit_price', self::get_active_price( $post_id ) );
    }

    /**
     * Numbers only.
     *
     * @param mixed $value Raw.
     * @return string
     */
    private static function clean_number( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return '';
        }
        $value = preg_replace( '/[^0-9.\-]/', '', $value );
        return '' === $value ? '' : (string) (float) $value;
    }

    /* ---------------------------------------------------------------------
     * Admin list
     * ------------------------------------------------------------------- */

    /**
     * Columns on the products list.
     *
     * @param array $columns Columns.
     * @return array
     */
    public function columns( $columns ) {
        $out = array();
        foreach ( $columns as $key => $label ) {
            if ( 'title' === $key ) {
                $out['lk_image'] = __( 'Image', 'loopkit' );
            }
            $out[ $key ] = $label;
            if ( 'title' === $key ) {
                $out['lk_sku']   = __( 'SKU', 'loopkit' );
                $out['lk_price'] = __( 'Price', 'loopkit' );
                $out['lk_stock'] = __( 'Stock', 'loopkit' );
            }
        }
        return $out;
    }

    /**
     * Column content.
     *
     * @param string $column  Column key.
     * @param int    $post_id Product.
     */
    public function column_content( $column, $post_id ) {
        $d = self::get_product_data( $post_id );

        switch ( $column ) {
            case 'lk_image':
                echo has_post_thumbnail( $post_id )
                    ? get_the_post_thumbnail( $post_id, array( 48, 48 ), array( 'style' => 'border-radius:6px;object-fit:cover' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    : '<span class="dashicons dashicons-format-image" style="color:#c3c4c7"></span>';
                break;
            case 'lk_sku':
                echo esc_html( $d['sku'] ?: '—' );
                break;
            case 'lk_price':
                if ( self::is_on_sale( $post_id ) ) {
                    echo '<del>' . esc_html( self::format_price( $d['regular_price'] ) ) . '</del> <strong>' . esc_html( self::format_price( $d['sale_price'] ) ) . '</strong>';
                } else {
                    echo esc_html( '' !== $d['regular_price'] ? self::format_price( $d['regular_price'] ) : '—' );
                }
                break;
            case 'lk_stock':
                $labels = array(
                    'instock'     => array( __( 'In stock', 'loopkit' ), '#008a20' ),
                    'outofstock'  => array( __( 'Out of stock', 'loopkit' ), '#b32d2e' ),
                    'onbackorder' => array( __( 'On backorder', 'loopkit' ), '#996800' ),
                );
                $info = $labels[ $d['stock_status'] ] ?? $labels['instock'];
                $qty  = ( '1' === (string) $d['manage_stock'] && '' !== $d['stock_qty'] ) ? ' (' . (int) $d['stock_qty'] . ')' : '';
                echo '<span style="color:' . esc_attr( $info[1] ) . '">' . esc_html( $info[0] . $qty ) . '</span>';
                break;
        }
    }

    /* ---------------------------------------------------------------------
     * Settings screen
     * ------------------------------------------------------------------- */


    /**
     * Shop > Payments — every way a customer can pay.
     */
    public static function render_payments_page() {
        $s = self::get_settings();
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Payments', 'loopkit' ); ?></h1>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Payment settings saved.', 'loopkit' ); ?></p></div>
            <?php endif; ?>

            <?php
            $lk_live = class_exists( 'Loopkit_Shop_Orders' ) ? Loopkit_Shop_Orders::payment_methods() : array();
            ?>
            <div class="lk-card">
                <div class="lk-card-header">
                    <h3><?php esc_html_e( 'Live at checkout right now', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc">
                        <?php if ( empty( $lk_live ) ) : ?>
                            <?php esc_html_e( 'Nothing yet — switch a method on below and save, and it appears at checkout straight away.', 'loopkit' ); ?>
                        <?php else : ?>
                            <?php echo esc_html( implode( ' · ', wp_list_pluck( $lk_live, 'label' ) ) ); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="lk-card-body">
                    <p class="lk-auto-help" style="margin:0">
                        <?php esc_html_e( 'Card payment shows up at checkout as soon as "Accept card payments" is ticked and the Stripe secret key is saved. Add the publishable key too and the card fields appear on your own checkout page instead of sending customers to Stripe.', 'loopkit' ); ?>
                    </p>
                </div>
            </div>

            <form method="post" class="lk-automation-form">
                <?php wp_nonce_field( 'loopkit_shop' ); ?>
                <input type="hidden" name="loopkit_shop_action" value="save_payments">

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <span class="lk-auto-step">1</span>
                        <h3><?php esc_html_e( 'Payments', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'What customers can choose at checkout.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <div class="lk-auto-field">
                            <label><?php esc_html_e( 'Cash on delivery', 'loopkit' ); ?></label>
                            <label class="lk-auto-check"><input type="checkbox" name="pay_cod" value="1" <?php checked( ! empty( $s['pay_cod'] ) ); ?>> <?php esc_html_e( 'Accept cash on delivery', 'loopkit' ); ?></label>
                            <input type="text" name="cod_note" value="<?php echo esc_attr( $s['cod_note'] ); ?>" placeholder="<?php esc_attr_e( 'Pay with cash when your order arrives.', 'loopkit' ); ?>">
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-order-email"><?php esc_html_e( 'Send new orders to', 'loopkit' ); ?></label>
                            <input type="email" id="lk-order-email" name="order_email" value="<?php echo esc_attr( $s['order_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                        </div>
                    </div>

                    <div class="lk-card-body" style="border-top:1px solid #eef1f5">
                        <h4 style="margin:0 0 6px"><?php esc_html_e( 'Bank transfer', 'loopkit' ); ?></h4>
                        <p class="lk-card-desc" style="margin:0 0 14px"><?php esc_html_e( 'These details are shown at checkout and repeated in the order email, each on its own line.', 'loopkit' ); ?></p>
                        <label class="lk-auto-check" style="margin-bottom:14px"><input type="checkbox" name="pay_bank" value="1" <?php checked( ! empty( $s['pay_bank'] ) ); ?>> <?php esc_html_e( 'Accept bank transfer', 'loopkit' ); ?></label>
                        <div class="lk-auto-grid">
                            <div class="lk-auto-field">
                                <label for="lk-bank-name"><?php esc_html_e( 'Bank name', 'loopkit' ); ?></label>
                                <input type="text" id="lk-bank-name" name="bank_name" value="<?php echo esc_attr( $s['bank_name'] ); ?>" placeholder="<?php esc_attr_e( 'Meezan Bank', 'loopkit' ); ?>">
                            </div>
                            <div class="lk-auto-field">
                                <label for="lk-bank-title"><?php esc_html_e( 'Account title', 'loopkit' ); ?></label>
                                <input type="text" id="lk-bank-title" name="bank_account_title" value="<?php echo esc_attr( $s['bank_account_title'] ); ?>" placeholder="<?php esc_attr_e( 'Your business name', 'loopkit' ); ?>">
                            </div>
                            <div class="lk-auto-field">
                                <label for="lk-bank-number"><?php esc_html_e( 'Account number', 'loopkit' ); ?></label>
                                <input type="text" id="lk-bank-number" name="bank_account_number" value="<?php echo esc_attr( $s['bank_account_number'] ); ?>" placeholder="0123456789">
                            </div>
                            <div class="lk-auto-field">
                                <label for="lk-bank-iban"><?php esc_html_e( 'IBAN', 'loopkit' ); ?></label>
                                <input type="text" id="lk-bank-iban" name="bank_iban" value="<?php echo esc_attr( $s['bank_iban'] ); ?>" placeholder="PK00MEZN0000000000000000">
                            </div>
                            <div class="lk-auto-field">
                                <label for="lk-bank-branch"><?php esc_html_e( 'Branch code', 'loopkit' ); ?></label>
                                <input type="text" id="lk-bank-branch" name="bank_branch" value="<?php echo esc_attr( $s['bank_branch'] ); ?>">
                            </div>
                            <div class="lk-auto-field">
                                <label for="lk-bank-swift"><?php esc_html_e( 'SWIFT / BIC', 'loopkit' ); ?></label>
                                <input type="text" id="lk-bank-swift" name="bank_swift" value="<?php echo esc_attr( $s['bank_swift'] ); ?>">
                            </div>
                            <div class="lk-auto-field lk-auto-field-full">
                                <label for="lk-bank-note"><?php esc_html_e( 'Anything else the customer should know', 'loopkit' ); ?></label>
                                <textarea id="lk-bank-note" name="bank_note" rows="2" placeholder="<?php esc_attr_e( 'Send the receipt to our WhatsApp after paying.', 'loopkit' ); ?>"><?php echo esc_textarea( $s['bank_note'] ); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="lk-card-body" style="border-top:1px solid #eef1f5">
                        <h4 style="margin:0 0 6px"><?php esc_html_e( 'Card payments (Stripe)', 'loopkit' ); ?></h4>
                        <p class="lk-card-desc" style="margin:0 0 14px">
                            <?php esc_html_e( 'Customers pay by card on Stripe\'s own secure page and come back to your thank-you page. Loopkit checks with Stripe that the payment went through before marking the order as paid. Paste the secret key from your Stripe dashboard (Developers > API keys).', 'loopkit' ); ?>
                        </p>
                        <div class="lk-auto-grid">
                            <div class="lk-auto-field">
                                <label><?php esc_html_e( 'Card payments', 'loopkit' ); ?></label>
                                <label class="lk-auto-check"><input type="checkbox" name="pay_stripe" value="1" <?php checked( ! empty( $s['pay_stripe'] ) ); ?>> <?php esc_html_e( 'Accept card payments', 'loopkit' ); ?></label>
                            </div>
                            <div class="lk-auto-field">
                                <label for="lk-stripe-secret"><?php esc_html_e( 'Stripe secret key', 'loopkit' ); ?></label>
                                <input type="password" id="lk-stripe-secret" name="stripe_secret" value="<?php echo esc_attr( $s['stripe_secret'] ); ?>" placeholder="sk_live_…" autocomplete="off">
                            </div>
                            <div class="lk-auto-field">
                                <label for="lk-stripe-public"><?php esc_html_e( 'Stripe publishable key', 'loopkit' ); ?></label>
                                <input type="text" id="lk-stripe-public" name="stripe_public" value="<?php echo esc_attr( $s['stripe_public'] ); ?>" placeholder="pk_live_…" autocomplete="off">
                                <span class="lk-auto-help"><?php esc_html_e( 'Needed for card fields on your own checkout.', 'loopkit' ); ?></span>
                            </div>
                            <div class="lk-auto-field lk-auto-field-full">
                                <label><?php esc_html_e( 'Where customers type their card', 'loopkit' ); ?></label>
                                <label class="lk-auto-check"><input type="checkbox" name="stripe_inline" value="1" <?php checked( ! empty( $s['stripe_inline'] ) ); ?>> <?php esc_html_e( 'On my checkout page (card fields appear under the payment choice)', 'loopkit' ); ?></label>
                                <span class="lk-auto-help"><?php esc_html_e( 'Card details go straight to Stripe and never touch your server. Switch this off to send customers to Stripe\'s own page instead.', 'loopkit' ); ?></span>
                            </div>
                            <div class="lk-auto-field lk-auto-field-full">
                                <label for="lk-stripe-note"><?php esc_html_e( 'Text shown at checkout', 'loopkit' ); ?></label>
                                <input type="text" id="lk-stripe-note" name="stripe_note" value="<?php echo esc_attr( $s['stripe_note'] ); ?>" placeholder="<?php esc_attr_e( 'Pay securely by card.', 'loopkit' ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="lk-card-body" style="border-top:1px solid #eef1f5">
                        <h4 style="margin:0 0 6px"><?php esc_html_e( 'Your own payment methods', 'loopkit' ); ?></h4>
                        <p class="lk-card-desc" style="margin:0 0 14px">
                            <?php esc_html_e( 'Add any method your customers use — Easypaisa, JazzCash, a card payment link, a wallet. The name and instructions you write here are what they see at checkout, and the method is stored with the order and the Shop Submission.', 'loopkit' ); ?>
                        </p>

                        <?php
                        $lk_custom = (array) ( $s['custom_methods'] ?? array() );
                        if ( empty( $lk_custom ) ) {
                            $lk_custom = array( array( 'id' => '', 'label' => '', 'description' => '' ) );
                        }
                        ?>
                        <div id="lk-pay-rows">
                            <?php foreach ( $lk_custom as $lk_i => $lk_method ) : ?>
                                <div class="lk-auto-action lk-pay-row">
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Name', 'loopkit' ); ?></label>
                                        <input type="text" name="custom_methods[<?php echo (int) $lk_i; ?>][label]" value="<?php echo esc_attr( $lk_method['label'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Easypaisa', 'loopkit' ); ?>">
                                    </div>
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Key (optional)', 'loopkit' ); ?></label>
                                        <input type="text" name="custom_methods[<?php echo (int) $lk_i; ?>][id]" value="<?php echo esc_attr( $lk_method['id'] ?? '' ); ?>" placeholder="easypaisa">
                                    </div>
                                    <div class="lk-auto-field lk-auto-field-full">
                                        <label><?php esc_html_e( 'Instructions shown at checkout', 'loopkit' ); ?></label>
                                        <textarea name="custom_methods[<?php echo (int) $lk_i; ?>][description]" rows="2" placeholder="<?php esc_attr_e( 'Send the amount to 0300-0000000 and enter the transaction ID in the order notes.', 'loopkit' ); ?>"><?php echo esc_textarea( $lk_method['description'] ?? '' ); ?></textarea>
                                    </div>
                                    <div class="lk-auto-field lk-auto-field-full">
                                        <label><?php esc_html_e( 'Payment link (optional)', 'loopkit' ); ?></label>
                                        <input type="url" name="custom_methods[<?php echo (int) $lk_i; ?>][link]" value="<?php echo esc_attr( $lk_method['link'] ?? '' ); ?>" placeholder="https://paypal.me/yourname">
                                        <span class="lk-auto-help"><?php esc_html_e( 'Given a link, the customer is sent there to pay as soon as the order is placed — a PayPal.me link, an Easypaisa link, a Stripe payment link, anything.', 'loopkit' ); ?></span>
                                    </div>
                                    <button type="button" class="lk-auto-remove" aria-label="<?php esc_attr_e( 'Remove', 'loopkit' ); ?>">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="lk-btn lk-btn-secondary" id="lk-add-pay"><?php esc_html_e( '+ Add payment method', 'loopkit' ); ?></button>
                    </div>
                </div>


                <p class="lk-auto-submit">
                    <button type="submit" class="lk-btn lk-btn-primary"><?php esc_html_e( 'Save Payments', 'loopkit' ); ?></button>
                </p>
            </form>

            <script>
            ( function () {
                var rows = document.getElementById( 'lk-pay-rows' );
                if ( ! rows ) return;

                function renumber() {
                    Array.prototype.forEach.call( rows.querySelectorAll( '.lk-pay-row' ), function ( row, i ) {
                        row.querySelectorAll( '[name]' ).forEach( function ( f ) {
                            f.name = f.name.replace( /custom_methods\[\d+\]/, 'custom_methods[' + i + ']' );
                        } );
                    } );
                }

                document.getElementById( 'lk-add-pay' ).addEventListener( 'click', function () {
                    var row = rows.children[0].cloneNode( true );
                    row.querySelectorAll( 'input, textarea' ).forEach( function ( f ) { f.value = ''; } );
                    rows.appendChild( row );
                    renumber();
                } );

                rows.addEventListener( 'click', function ( e ) {
                    var btn = e.target.closest( '.lk-auto-remove' );
                    if ( ! btn ) return;
                    var row = btn.closest( '.lk-pay-row' );
                    if ( rows.children.length === 1 ) {
                        row.querySelectorAll( 'input, textarea' ).forEach( function ( f ) { f.value = ''; } );
                        return;
                    }
                    row.remove();
                    renumber();
                } );
            } )();
            </script>
        </div>
        <?php
    }

    /**
     * Shop settings.
     */
    public static function render_settings_page() {
        $s     = self::get_settings();
        $count = (int) wp_count_posts( self::POST_TYPE )->publish;
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Shop Settings', 'loopkit' ); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-product-edit' ) ); ?>"><?php esc_html_e( 'Add Product', 'loopkit' ); ?></a>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-success is-dismissible"><p><?php
                    if ( 'converted' === $_GET['saved'] ) { // phpcs:ignore WordPress.Security.NonceVerification
                        /* translators: %d: number of products */
                        printf( esc_html__( 'Prices converted on %d products.', 'loopkit' ), (int) ( $_GET['count'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
                    } elseif ( 'pages' === $_GET['saved'] ) { // phpcs:ignore WordPress.Security.NonceVerification
                        esc_html_e( 'Shop pages are ready.', 'loopkit' );
                    } else {
                        esc_html_e( 'Shop settings saved.', 'loopkit' );
                    }
                ?></p></div>
            <?php endif; ?>

            <form method="post" class="lk-automation-form">
                <?php wp_nonce_field( 'loopkit_shop' ); ?>
                <input type="hidden" name="loopkit_shop_action" value="save_settings">

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <h3><?php esc_html_e( 'Overview', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc">
                            <?php
                            /* translators: 1: number of products, 2: shop URL */
                            printf(
                                esc_html__( '%1$d products published. Shop page: %2$s', 'loopkit' ),
                                (int) $count,
                                '<a href="' . esc_url( get_post_type_archive_link( self::POST_TYPE ) ?: home_url( '/' ) ) . '" target="_blank">' . esc_html( get_post_type_archive_link( self::POST_TYPE ) ?: '—' ) . '</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            );
                            ?>
                        </p>
                    </div>
                </div>

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <span class="lk-auto-step">1</span>
                        <h3><?php esc_html_e( 'Currency', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'How prices are written everywhere in the shop.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <div class="lk-auto-field">
                            <label for="lk-shop-currency"><?php esc_html_e( 'Currency', 'loopkit' ); ?></label>
                            <select id="lk-shop-currency" name="currency">
                                <?php foreach ( self::currencies() as $code => $info ) : ?>
                                    <option value="<?php echo esc_attr( $code ); ?>" data-symbol="<?php echo esc_attr( $info[1] ); ?>" <?php selected( $s['currency'], $code ); ?>>
                                        <?php echo esc_html( $info[0] . ' (' . $code . ' ' . $info[1] . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="lk-auto-help"><?php esc_html_e( 'Choosing a currency fills in its symbol; you can still change the symbol yourself.', 'loopkit' ); ?></span>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-shop-symbol"><?php esc_html_e( 'Symbol', 'loopkit' ); ?></label>
                            <input type="text" id="lk-shop-symbol" name="currency_symbol" value="<?php echo esc_attr( $s['currency_symbol'] ); ?>" placeholder="Rs">
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-shop-position"><?php esc_html_e( 'Symbol position', 'loopkit' ); ?></label>
                            <?php
                            $lk_symbol = $s['currency_symbol'];
                            $lk_sample = number_format( 2499, (int) $s['decimals'], $s['decimal_sep'], $s['thousand_sep'] );
                            ?>
                            <select id="lk-shop-position" name="symbol_position" data-sample="<?php echo esc_attr( $lk_sample ); ?>">
                                <option value="left" data-format="{s}{n}" <?php selected( $s['symbol_position'], 'left' ); ?>><?php echo esc_html( sprintf( '%1$s (%2$s%3$s)', __( 'Left', 'loopkit' ), $lk_symbol, $lk_sample ) ); ?></option>
                                <option value="left_space" data-format="{s} {n}" <?php selected( $s['symbol_position'], 'left_space' ); ?>><?php echo esc_html( sprintf( '%1$s (%2$s %3$s)', __( 'Left with space', 'loopkit' ), $lk_symbol, $lk_sample ) ); ?></option>
                                <option value="right" data-format="{n}{s}" <?php selected( $s['symbol_position'], 'right' ); ?>><?php echo esc_html( sprintf( '%1$s (%2$s%3$s)', __( 'Right', 'loopkit' ), $lk_sample, $lk_symbol ) ); ?></option>
                                <option value="right_space" data-format="{n} {s}" <?php selected( $s['symbol_position'], 'right_space' ); ?>><?php echo esc_html( sprintf( '%1$s (%2$s %3$s)', __( 'Right with space', 'loopkit' ), $lk_sample, $lk_symbol ) ); ?></option>
                            </select>
                            <span class="lk-auto-help" id="lk-price-preview"><?php
                                /* translators: %s: example price */
                                printf( esc_html__( 'Prices will look like %s', 'loopkit' ), '<strong>' . esc_html( self::format_price( 2499 ) ) . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?></span>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-shop-decimals"><?php esc_html_e( 'Decimals', 'loopkit' ); ?></label>
                            <input type="number" id="lk-shop-decimals" name="decimals" value="<?php echo esc_attr( (string) $s['decimals'] ); ?>" min="0" max="4">
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-shop-thousand"><?php esc_html_e( 'Thousand separator', 'loopkit' ); ?></label>
                            <input type="text" id="lk-shop-thousand" name="thousand_sep" value="<?php echo esc_attr( $s['thousand_sep'] ); ?>" maxlength="1">
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-shop-decimal"><?php esc_html_e( 'Decimal separator', 'loopkit' ); ?></label>
                            <input type="text" id="lk-shop-decimal" name="decimal_sep" value="<?php echo esc_attr( $s['decimal_sep'] ); ?>" maxlength="1">
                        </div>
                    </div>
                </div>

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <span class="lk-auto-step">2</span>
                        <h3><?php esc_html_e( 'Stock and shop page', 'loopkit' ); ?></h3>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <div class="lk-auto-field">
                            <label><?php esc_html_e( 'Stock', 'loopkit' ); ?></label>
                            <label class="lk-auto-check"><input type="checkbox" name="manage_stock" value="1" <?php checked( ! empty( $s['manage_stock'] ) ); ?>> <?php esc_html_e( 'Track stock quantities', 'loopkit' ); ?></label>
                            <label class="lk-auto-check"><input type="checkbox" name="hide_out_of_stock" value="1" <?php checked( ! empty( $s['hide_out_of_stock'] ) ); ?>> <?php esc_html_e( 'Hide products that are out of stock', 'loopkit' ); ?></label>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-shop-low"><?php esc_html_e( 'Low stock warning at', 'loopkit' ); ?></label>
                            <input type="number" id="lk-shop-low" name="low_stock_at" value="<?php echo esc_attr( (string) $s['low_stock_at'] ); ?>" min="0">
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-shop-slug"><?php esc_html_e( 'Shop page URL', 'loopkit' ); ?></label>
                            <input type="text" id="lk-shop-slug" name="shop_slug" value="<?php echo esc_attr( $s['shop_slug'] ); ?>" placeholder="shop">
                            <span class="lk-auto-help"><?php echo esc_html( home_url( '/' ) ); ?><?php echo esc_html( $s['shop_slug'] ); ?>/</span>
                        </div>
                    </div>
                </div>

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <span class="lk-auto-step">3</span>
                        <h3><?php esc_html_e( 'Shipping', 'loopkit' ); ?></h3>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <div class="lk-auto-field">
                            <label for="lk-flat-rate"><?php esc_html_e( 'Flat rate', 'loopkit' ); ?></label>
                            <input type="number" step="any" id="lk-flat-rate" name="flat_rate" value="<?php echo esc_attr( (string) $s['flat_rate'] ); ?>" placeholder="250">
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-free-over"><?php esc_html_e( 'Free shipping over', 'loopkit' ); ?></label>
                            <input type="number" step="any" id="lk-free-over" name="free_shipping_over" value="<?php echo esc_attr( (string) $s['free_shipping_over'] ); ?>" placeholder="<?php esc_attr_e( 'leave empty for never', 'loopkit' ); ?>">
                        </div>
                        <div class="lk-auto-field">
                            <label><?php esc_html_e( 'Local pickup', 'loopkit' ); ?></label>
                            <label class="lk-auto-check"><input type="checkbox" name="pickup" value="1" <?php checked( ! empty( $s['pickup'] ) ); ?>> <?php esc_html_e( 'Offer free local pickup', 'loopkit' ); ?></label>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-country"><?php esc_html_e( 'Default country', 'loopkit' ); ?></label>
                            <input type="text" id="lk-country" name="country" value="<?php echo esc_attr( $s['country'] ); ?>" placeholder="Pakistan">
                        </div>
                    </div>

                    <div class="lk-card-body" style="border-top:1px solid #eef1f5">
                        <h4 style="margin:0 0 6px"><?php esc_html_e( 'Shipping zones', 'loopkit' ); ?></h4>
                        <p class="lk-card-desc" style="margin:0 0 14px"><?php esc_html_e( 'A zone charges its own rate when the address matches one of its places. List cities, states or countries separated by commas — the first zone that matches wins, and anything else pays the flat rate above.', 'loopkit' ); ?></p>

                        <?php
                        $lk_zones = (array) ( $s['zones'] ?? array() );
                        if ( empty( $lk_zones ) ) {
                            $lk_zones = array( array( 'name' => '', 'places' => '', 'rate' => '', 'free_over' => '' ) );
                        }
                        ?>
                        <div id="lk-zone-rows">
                            <?php foreach ( $lk_zones as $lk_i => $lk_zone ) : ?>
                                <div class="lk-auto-action lk-zone-row">
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Zone name', 'loopkit' ); ?></label>
                                        <input type="text" name="zones[<?php echo (int) $lk_i; ?>][name]" value="<?php echo esc_attr( $lk_zone['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Karachi', 'loopkit' ); ?>">
                                    </div>
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Places (comma separated)', 'loopkit' ); ?></label>
                                        <input type="text" name="zones[<?php echo (int) $lk_i; ?>][places]" value="<?php echo esc_attr( $lk_zone['places'] ?? '' ); ?>" placeholder="Karachi, Hyderabad">
                                    </div>
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Rate', 'loopkit' ); ?></label>
                                        <input type="number" step="any" name="zones[<?php echo (int) $lk_i; ?>][rate]" value="<?php echo esc_attr( (string) ( $lk_zone['rate'] ?? '' ) ); ?>" placeholder="150">
                                    </div>
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Free over', 'loopkit' ); ?></label>
                                        <input type="number" step="any" name="zones[<?php echo (int) $lk_i; ?>][free_over]" value="<?php echo esc_attr( (string) ( $lk_zone['free_over'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'never', 'loopkit' ); ?>">
                                    </div>
                                    <button type="button" class="lk-auto-remove" aria-label="<?php esc_attr_e( 'Remove zone', 'loopkit' ); ?>">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="lk-btn lk-btn-secondary" id="lk-add-zone"><?php esc_html_e( '+ Add zone', 'loopkit' ); ?></button>
                    </div>

                    <div class="lk-card-body" style="border-top:1px solid #eef1f5">
                        <h4 style="margin:0 0 14px"><?php esc_html_e( 'Tax', 'loopkit' ); ?></h4>
                        <div class="lk-auto-grid">
                            <div class="lk-auto-field">
                                <label><?php esc_html_e( 'Charge tax', 'loopkit' ); ?></label>
                                <label class="lk-auto-check"><input type="checkbox" name="tax_enabled" value="1" <?php checked( ! empty( $s['tax_enabled'] ) ); ?>> <?php esc_html_e( 'Add tax to orders', 'loopkit' ); ?></label>
                                <label class="lk-auto-check"><input type="checkbox" name="tax_included" value="1" <?php checked( ! empty( $s['tax_included'] ) ); ?>> <?php esc_html_e( 'My prices already include tax', 'loopkit' ); ?></label>
                                <label class="lk-auto-check"><input type="checkbox" name="tax_shipping" value="1" <?php checked( ! empty( $s['tax_shipping'] ) ); ?>> <?php esc_html_e( 'Tax the shipping too', 'loopkit' ); ?></label>
                            </div>
                            <div class="lk-auto-field">
                                <label for="lk-tax-rate"><?php esc_html_e( 'Rate (%)', 'loopkit' ); ?></label>
                                <input type="number" step="any" id="lk-tax-rate" name="tax_rate" value="<?php echo esc_attr( (string) $s['tax_rate'] ); ?>" placeholder="17">
                            </div>
                            <div class="lk-auto-field">
                                <label for="lk-tax-label"><?php esc_html_e( 'What to call it', 'loopkit' ); ?></label>
                                <input type="text" id="lk-tax-label" name="tax_label" value="<?php echo esc_attr( $s['tax_label'] ); ?>" placeholder="GST">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <span class="lk-auto-step">4</span>
                        <h3><?php esc_html_e( 'Shop pages', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'The cart, checkout and thank-you pages. Create them in one click, or pick pages you already have.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <?php
                        $lk_pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
                        foreach ( array(
                            'cart_page'      => __( 'Cart page', 'loopkit' ),
                            'checkout_page'  => __( 'Checkout page', 'loopkit' ),
                            'thank_you_page' => __( 'Thank you page', 'loopkit' ),
                        ) as $lk_key => $lk_label ) :
                            ?>
                            <div class="lk-auto-field">
                                <label for="lk-<?php echo esc_attr( $lk_key ); ?>"><?php echo esc_html( $lk_label ); ?></label>
                                <select id="lk-<?php echo esc_attr( $lk_key ); ?>" name="<?php echo esc_attr( $lk_key ); ?>">
                                    <option value="0"><?php esc_html_e( '— None —', 'loopkit' ); ?></option>
                                    <?php foreach ( $lk_pages as $lk_page ) : ?>
                                        <option value="<?php echo (int) $lk_page->ID; ?>" <?php selected( (int) $s[ $lk_key ], $lk_page->ID ); ?>><?php echo esc_html( $lk_page->post_title ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <p class="lk-auto-submit">
                    <button type="submit" class="lk-btn lk-btn-primary"><?php esc_html_e( 'Save Settings', 'loopkit' ); ?></button>
                </p>
            </form>

            <script>
            ( function () {
                // Picking a currency fills in its symbol, and every choice updates the example
                var currency = document.getElementById( 'lk-shop-currency' );
                var symbol = document.getElementById( 'lk-shop-symbol' );
                var position = document.getElementById( 'lk-shop-position' );
                var decimals = document.getElementById( 'lk-shop-decimals' );
                var thousand = document.getElementById( 'lk-shop-thousand' );
                var decimal = document.getElementById( 'lk-shop-decimal' );
                var preview = document.getElementById( 'lk-price-preview' );

                function sample() {
                    var d = Math.max( 0, Math.min( 4, parseInt( decimals.value, 10 ) || 0 ) );
                    var parts = ( 2499 ).toFixed( d ).split( '.' );
                    parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, thousand.value || '' );
                    return parts.join( parts[1] ? ( decimal.value || '.' ) : '' );
                }

                function paint() {
                    if ( ! preview ) return;
                    var s = symbol.value;
                    var n = sample();

                    // the choices themselves show this shop's symbol
                    Array.prototype.forEach.call( position.options, function ( opt ) {
                        var label = opt.textContent.replace( /\s*\(.*\)$/, '' );
                        opt.textContent = label + ' (' + ( opt.dataset.format || '{s} {n}' ).replace( '{s}', s ).replace( '{n}', n ) + ')';
                    } );

                    var format = position.options[ position.selectedIndex ].dataset.format || '{s} {n}';
                    preview.innerHTML = 'Prices will look like <strong>' + format.replace( '{s}', s ).replace( '{n}', n ) + '</strong>';
                }

                if ( currency && symbol && position ) {
                    currency.addEventListener( 'change', function () {
                        var opt = currency.options[ currency.selectedIndex ];
                        if ( opt && opt.dataset.symbol ) symbol.value = opt.dataset.symbol;
                        paint();
                    } );
                    [ symbol, position, decimals, thousand, decimal ].forEach( function ( field ) {
                        field.addEventListener( 'input', paint );
                        field.addEventListener( 'change', paint );
                    } );
                    paint();
                }

                // Shipping zones
                var zoneRows = document.getElementById( 'lk-zone-rows' );
                if ( zoneRows ) {
                    var renumberZones = function () {
                        Array.prototype.forEach.call( zoneRows.querySelectorAll( '.lk-zone-row' ), function ( row, i ) {
                            row.querySelectorAll( '[name]' ).forEach( function ( f ) {
                                f.name = f.name.replace( /zones\[\d+\]/, 'zones[' + i + ']' );
                            } );
                        } );
                    };
                    document.getElementById( 'lk-add-zone' ).addEventListener( 'click', function () {
                        var row = zoneRows.children[0].cloneNode( true );
                        row.querySelectorAll( 'input' ).forEach( function ( f ) { f.value = ''; } );
                        zoneRows.appendChild( row );
                        renumberZones();
                    } );
                    zoneRows.addEventListener( 'click', function ( e ) {
                        var btn = e.target.closest( '.lk-auto-remove' );
                        if ( ! btn ) return;
                        var row = btn.closest( '.lk-zone-row' );
                        if ( zoneRows.children.length === 1 ) {
                            row.querySelectorAll( 'input' ).forEach( function ( f ) { f.value = ''; } );
                            return;
                        }
                        row.remove();
                        renumberZones();
                    } );
                }

                var rows = document.getElementById( 'lk-pay-rows' );
                if ( ! rows ) return;

                function renumber() {
                    Array.prototype.forEach.call( rows.querySelectorAll( '.lk-pay-row' ), function ( row, i ) {
                        row.querySelectorAll( '[name]' ).forEach( function ( f ) {
                            f.name = f.name.replace( /custom_methods\[\d+\]/, 'custom_methods[' + i + ']' );
                        } );
                    } );
                }

                document.getElementById( 'lk-add-pay' ).addEventListener( 'click', function () {
                    var row = rows.children[0].cloneNode( true );
                    row.querySelectorAll( 'input, textarea' ).forEach( function ( f ) { f.value = ''; } );
                    rows.appendChild( row );
                    renumber();
                } );

                rows.addEventListener( 'click', function ( e ) {
                    var btn = e.target.closest( '.lk-auto-remove' );
                    if ( ! btn ) return;
                    var row = btn.closest( '.lk-pay-row' );
                    if ( rows.children.length === 1 ) {
                        row.querySelectorAll( 'input, textarea' ).forEach( function ( f ) { f.value = ''; } );
                        return;
                    }
                    row.remove();
                    renumber();
                } );
            } )();
            </script>

            <form method="post">
                <?php wp_nonce_field( 'loopkit_shop' ); ?>
                <input type="hidden" name="loopkit_shop_action" value="convert_prices">
                <div class="lk-card">
                    <div class="lk-card-header">
                        <h3><?php esc_html_e( 'Convert prices to another currency', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc">
                            <?php
                            /* translators: %s: currency code */
                            printf( esc_html__( 'Changing the currency above only changes how prices are written — the numbers stay as they are. This converts every product price, sale price and variation price, plus your shipping rates, into %s. Past orders are left untouched.', 'loopkit' ), '<strong>' . esc_html( $s['currency'] ) . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?>
                        </p>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <div class="lk-auto-field">
                            <label for="lk-convert-from"><?php esc_html_e( 'Prices are currently in', 'loopkit' ); ?></label>
                            <select id="lk-convert-from" name="convert_from">
                                <?php foreach ( self::currencies() as $code => $info ) : ?>
                                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $s['currency'], $code ); ?>><?php echo esc_html( $info[0] . ' (' . $code . ')' ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-convert-rate"><?php esc_html_e( 'Rate', 'loopkit' ); ?></label>
                            <input type="number" step="any" id="lk-convert-rate" name="convert_rate" placeholder="1.00">
                            <span class="lk-auto-help" id="lk-rate-hint">
                                <?php
                                /* translators: %s: currency code */
                                printf( esc_html__( 'How many %s one unit of the old currency is worth. Press "Get today\'s rate" to fill it in.', 'loopkit' ), esc_html( $s['currency'] ) );
                                ?>
                            </span>
                        </div>
                        <div class="lk-auto-field">
                            <label>&nbsp;</label>
                            <button type="button" class="lk-btn lk-btn-secondary" id="lk-get-rate"><?php esc_html_e( "Get today's rate", 'loopkit' ); ?></button>
                        </div>
                        <div class="lk-auto-field">
                            <label>&nbsp;</label>
                            <button type="submit" class="lk-btn lk-btn-primary" onclick="return confirm('<?php esc_attr_e( 'Convert every price with this rate? This changes your product prices.', 'loopkit' ); ?>')"><?php esc_html_e( 'Convert prices', 'loopkit' ); ?></button>
                        </div>
                    </div>
                </div>
            </form>

            <script>
            ( function () {
                var button = document.getElementById( 'lk-get-rate' );
                if ( ! button ) return;
                button.addEventListener( 'click', function () {
                    var from = document.getElementById( 'lk-convert-from' ).value;
                    var hint = document.getElementById( 'lk-rate-hint' );
                    button.disabled = true;
                    fetch( ajaxurl + '?action=loopkit_shop_rate&from=' + encodeURIComponent( from ) + '&nonce=' + '<?php echo esc_js( wp_create_nonce( 'loopkit_shop_rate' ) ); ?>' )
                        .then( function ( r ) { return r.json(); } )
                        .then( function ( res ) {
                            button.disabled = false;
                            if ( res.success && res.data.rate > 0 ) {
                                document.getElementById( 'lk-convert-rate' ).value = res.data.rate;
                                hint.textContent = res.data.message;
                            } else {
                                hint.textContent = res.data && res.data.message ? res.data.message : 'Could not fetch a rate — type it in yourself.';
                            }
                        } )
                        .catch( function () {
                            button.disabled = false;
                            hint.textContent = 'Could not fetch a rate — type it in yourself.';
                        } );
                } );
            } )();
            </script>

            <form method="post">
                <?php wp_nonce_field( 'loopkit_shop' ); ?>
                <input type="hidden" name="loopkit_shop_action" value="create_pages">
                <div class="lk-card">
                    <div class="lk-card-header">
                        <h3><?php esc_html_e( 'Create the shop pages', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'Creates Cart, Checkout and Thank You pages with the right shortcodes and selects them above. Existing pages are left alone.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body">
                        <button type="submit" class="lk-btn lk-btn-secondary"><?php esc_html_e( 'Create pages', 'loopkit' ); ?></button>
                    </div>
                </div>
            </form>

            <div class="lk-card">
                <div class="lk-card-header">
                    <h3><?php esc_html_e( 'What is next', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc"><?php esc_html_e( 'Part 3 brings the shop widgets and templates for the builder: product grids, add-to-cart, cart icon, filters and the Single Product / Shop / Cart / Checkout template types.', 'loopkit' ); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------------
     * Dynamic tags
     * ------------------------------------------------------------------- */

    /**
     * Product tags for the editor.
     *
     * @param array $groups Tag groups.
     * @return array
     */
    public function add_dynamic_tags( $groups ) {
        $groups[] = array(
            'group' => __( 'Shop', 'loopkit' ),
            'tags'  => array(
                array( 'name' => 'product_price', 'label' => __( 'Price (what they pay)', 'loopkit' ), 'type' => 'text' ),
                array( 'name' => 'product_regular_price', 'label' => __( 'Regular Price', 'loopkit' ), 'type' => 'text' ),
                array( 'name' => 'product_sale_price', 'label' => __( 'Sale Price', 'loopkit' ), 'type' => 'text' ),
                array( 'name' => 'product_price_raw', 'label' => __( 'Price (number only)', 'loopkit' ), 'type' => 'text' ),
                array( 'name' => 'product_sku', 'label' => __( 'SKU', 'loopkit' ), 'type' => 'text' ),
                array( 'name' => 'product_stock', 'label' => __( 'Stock Status', 'loopkit' ), 'type' => 'text' ),
                array( 'name' => 'product_stock_qty', 'label' => __( 'Stock Quantity', 'loopkit' ), 'type' => 'text' ),
                array( 'name' => 'product_categories', 'label' => __( 'Product Categories', 'loopkit' ), 'type' => 'text' ),
                array( 'name' => 'product_tags', 'label' => __( 'Product Tags', 'loopkit' ), 'type' => 'text' ),
                array( 'name' => 'product_weight', 'label' => __( 'Weight', 'loopkit' ), 'type' => 'text' ),
                array( 'name' => 'product_dimensions', 'label' => __( 'Dimensions', 'loopkit' ), 'type' => 'text' ),
                array( 'name' => 'product_gallery_first', 'label' => __( 'First Gallery Image', 'loopkit' ), 'type' => 'image' ),
            ),
        );
        return $groups;
    }

    /**
     * Value of one shop tag, used by Loopkit_Dynamic.
     *
     * @param string $name    Tag name.
     * @param int    $post_id Product ID.
     * @return string
     */
    public static function tag_value( $name, $post_id ) {
        $d = self::get_product_data( $post_id );

        switch ( $name ) {
            case 'product_price':
                return self::format_price( self::get_active_price( $post_id ) );
            case 'product_price_raw':
                return (string) self::get_active_price( $post_id );
            case 'product_regular_price':
                return '' === $d['regular_price'] ? '' : self::format_price( $d['regular_price'] );
            case 'product_sale_price':
                return '' === $d['sale_price'] ? '' : self::format_price( $d['sale_price'] );
            case 'product_sku':
                return (string) $d['sku'];
            case 'product_stock':
                $labels = array(
                    'instock'     => __( 'In stock', 'loopkit' ),
                    'outofstock'  => __( 'Out of stock', 'loopkit' ),
                    'onbackorder' => __( 'On backorder', 'loopkit' ),
                );
                return (string) ( $labels[ $d['stock_status'] ] ?? $labels['instock'] );
            case 'product_stock_qty':
                return '' === $d['stock_qty'] ? '' : (string) (int) $d['stock_qty'];
            case 'product_categories':
            case 'product_tags':
                $tax   = ( 'product_tags' === $name ) ? self::TAX_TAG : self::TAX_CAT;
                $terms = get_the_terms( $post_id, $tax );
                if ( ! is_array( $terms ) ) {
                    return '';
                }
                return implode( ', ', wp_list_pluck( $terms, 'name' ) );
            case 'product_weight':
                return '' === $d['weight'] ? '' : $d['weight'] . ' kg';
            case 'product_dimensions':
                $parts = array_filter( array( $d['length'], $d['width'], $d['height'] ), function( $v ) { return '' !== $v; } );
                return count( $parts ) === 3 ? implode( ' × ', $parts ) . ' cm' : '';
            case 'product_rating':
                $rating = get_post_meta( $post_id, '_loopkit_rating', true );
                return '' === $rating ? '' : (string) $rating;
            case 'product_rating_count':
                $count = (int) get_post_meta( $post_id, '_loopkit_rating_count', true );
                return $count ? (string) $count : '';
            case 'product_gallery_first':
                $ids = array_filter( explode( ',', (string) $d['gallery'] ) );
                return $ids ? (string) wp_get_attachment_image_url( (int) $ids[0], 'large' ) : '';
        }
        return '';
    }
}
