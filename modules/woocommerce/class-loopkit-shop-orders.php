<?php
/**
 * Loopkit Shop — orders and checkout.
 *
 * Orders are the `loopkit_order` post type. Placing an order writes the line items and totals
 * into the order, reduces stock, empties the cart, sends the emails and hands the customer a
 * thank-you page. Payment methods are Cash on Delivery and Bank Transfer out of the box, and
 * `loopkit_shop_payment_methods` lets anything else be added.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Shop_Orders {

    const POST_TYPE = 'loopkit_order';

    /**
     * Order statuses.
     *
     * @return array
     */
    public static function statuses() {
        return array(
            'pending'    => __( 'Pending payment', 'loopkit' ),
            'processing' => __( 'Processing', 'loopkit' ),
            'completed'  => __( 'Completed', 'loopkit' ),
            'cancelled'  => __( 'Cancelled', 'loopkit' ),
            'refunded'   => __( 'Refunded', 'loopkit' ),
        );
    }

    /**
     * Payment methods.
     *
     * @return array
     */
    public static function payment_methods() {
        $s       = Loopkit_Module_WooCommerce::get_settings();
        $methods = array();

        if ( ! empty( $s['pay_cod'] ) ) {
            $methods['cod'] = array(
                'label'       => __( 'Cash on delivery', 'loopkit' ),
                'description' => $s['cod_note'] ?? __( 'Pay with cash when your order arrives.', 'loopkit' ),
            );
        }
        if ( ! empty( $s['pay_bank'] ) ) {
            $details = Loopkit_Module_WooCommerce::bank_details_text();
            $methods['bank'] = array(
                'label'       => __( 'Bank transfer', 'loopkit' ),
                'description' => '' !== $details ? $details : __( 'Send the amount to our account and we will confirm your order.', 'loopkit' ),
            );
        }

        // Card payments through Stripe Checkout
        if ( ! empty( $s['pay_stripe'] ) && ! empty( $s['stripe_secret'] ) ) {
            $methods['stripe'] = array(
                'label'       => __( 'Card payment', 'loopkit' ),
                'description' => $s['stripe_note'] ?? __( 'Pay securely by card.', 'loopkit' ),
                'online'      => true,
            );
        }

        // Methods added on the settings screen — bank wallets, card links, anything.
        foreach ( (array) ( $s['custom_methods'] ?? array() ) as $custom ) {
            if ( empty( $custom['id'] ) || empty( $custom['label'] ) ) {
                continue;
            }
            $methods[ $custom['id'] ] = array(
                'label'       => $custom['label'],
                'description' => $custom['description'] ?? '',
                'link'        => $custom['link'] ?? '',
                'online'      => ! empty( $custom['link'] ),
            );
        }

        /**
         * Payment methods available at checkout.
         *
         * @param array $methods id => array( label, description ).
         */
        return apply_filters( 'loopkit_shop_payment_methods', $methods );
    }

    /**
     * Hook up.
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_post_type' ), 5 );
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 31 );
        add_action( 'admin_init', array( __CLASS__, 'handle_admin_actions' ) );

        // Front-end actions
        add_action( 'wp_ajax_loopkit_cart', array( __CLASS__, 'ajax_cart' ) );
        add_action( 'wp_ajax_nopriv_loopkit_cart', array( __CLASS__, 'ajax_cart' ) );
        add_action( 'wp_ajax_loopkit_checkout', array( __CLASS__, 'ajax_checkout' ) );
        add_action( 'wp_ajax_nopriv_loopkit_checkout', array( __CLASS__, 'ajax_checkout' ) );
        add_action( 'wp_ajax_loopkit_payment_intent', array( __CLASS__, 'ajax_payment_intent' ) );
        add_action( 'wp_ajax_nopriv_loopkit_payment_intent', array( __CLASS__, 'ajax_payment_intent' ) );

        add_shortcode( 'loopkit_cart', array( __CLASS__, 'shortcode_cart' ) );
        add_shortcode( 'loopkit_checkout', array( __CLASS__, 'shortcode_checkout' ) );
        add_shortcode( 'loopkit_thank_you', array( __CLASS__, 'shortcode_thank_you' ) );

        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    /**
     * Orders.
     */
    public static function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'labels'       => array(
                'name'          => __( 'Orders', 'loopkit' ),
                'singular_name' => __( 'Order', 'loopkit' ),
            ),
            'public'       => false,
            'show_ui'      => false,
            'show_in_menu' => false,
            'supports'     => array( 'title' ),
        ) );
    }

    /**
     * Orders screen.
     */
    public static function register_menu() {
        // Orders and Shop Submissions are registered by Loopkit_Shop_Admin under the Shop menu.
    }

    /**
     * Cart and checkout behaviour on the front end.
     */
    public static function enqueue() {
        $settings = Loopkit_Module_WooCommerce::get_settings();

        if ( self::stripe_inline_ready() ) {
            wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
        }

        $shop_js  = class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'modules/woocommerce/loopkit-shop.js' ) : ( LOOPKIT_PLUGIN_URL . 'modules/woocommerce/loopkit-shop.js' );
        $shop_css = class_exists( 'Loopkit_Assets' ) ? Loopkit_Assets::get_asset_url( 'modules/woocommerce/loopkit-shop.css' ) : ( LOOPKIT_PLUGIN_URL . 'modules/woocommerce/loopkit-shop.css' );
        wp_register_script( 'loopkit-shop', $shop_js, array(), LOOPKIT_VERSION, true );
        wp_localize_script( 'loopkit-shop', 'loopkitShop', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'loopkit_shop_front' ),
            'qvNonce'      => wp_create_nonce( 'loopkit_quick_view' ),
            'stripeKey'    => self::stripe_inline_ready() ? $settings['stripe_public'] : '',
        ) );
        wp_enqueue_script( 'loopkit-shop' );
        wp_enqueue_style( 'loopkit-shop', $shop_css, array(), LOOPKIT_VERSION );
    }

    /* ---------------------------------------------------------------------
     * Front-end endpoints
     * ------------------------------------------------------------------- */

    /**
     * Cart actions: add, qty, remove, coupon, fragments.
     */
    public static function ajax_cart() {
        check_ajax_referer( 'loopkit_shop_front', 'nonce' );

        $do = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );

        switch ( $do ) {
            case 'add':
                $result = Loopkit_Shop_Cart::add(
                    absint( $_POST['product_id'] ?? 0 ),
                    absint( $_POST['qty'] ?? 1 ),
                    sanitize_text_field( wp_unslash( $_POST['variation'] ?? '' ) )
                );
                if ( is_wp_error( $result ) ) {
                    wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
                }
                break;

            case 'qty':
                Loopkit_Shop_Cart::set_qty( sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) ), absint( $_POST['qty'] ?? 1 ) );
                break;

            case 'remove':
                Loopkit_Shop_Cart::remove( sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) ) );
                break;

            case 'coupon':
                $applied = Loopkit_Shop_Cart::apply_coupon( wp_unslash( $_POST['code'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                if ( is_wp_error( $applied ) ) {
                    wp_send_json_error( array( 'message' => $applied->get_error_message() ), 400 );
                }
                break;

            case 'remove_coupon':
                Loopkit_Shop_Cart::remove_coupon();
                break;
        }

        $totals = Loopkit_Shop_Cart::get_totals( sanitize_text_field( wp_unslash( $_POST['shipping'] ?? '' ) ) );
        wp_send_json_success( array(
            'count'    => $totals['count'],
            'subtotal' => Loopkit_Module_WooCommerce::format_price( $totals['subtotal'] ),
            'discount' => Loopkit_Module_WooCommerce::format_price( $totals['discount'] ),
            'shipping' => Loopkit_Module_WooCommerce::format_price( $totals['shipping'] ),
            'tax'      => Loopkit_Module_WooCommerce::format_price( $totals['tax'] ?? 0 ),
            'total'    => Loopkit_Module_WooCommerce::format_price( $totals['total'] ),
            'html'     => self::cart_table_html(),
        ) );
    }

    /**
     * Place the order.
     */
    public static function ajax_checkout() {
        check_ajax_referer( 'loopkit_shop_front', 'nonce' );

        $fields = array();
        foreach ( self::checkout_fields() as $key => $field ) {
            $raw = wp_unslash( $_POST[ $key ] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            if ( 'email' === $field['type'] ) {
                $fields[ $key ] = sanitize_email( $raw );
            } elseif ( 'textarea' === $field['type'] ) {
                $fields[ $key ] = sanitize_textarea_field( $raw );
            } else {
                $fields[ $key ] = sanitize_text_field( $raw );
            }
        }
        $payment  = sanitize_key( wp_unslash( $_POST['payment'] ?? '' ) );
        $shipping = sanitize_key( wp_unslash( $_POST['shipping'] ?? '' ) );

        foreach ( self::checkout_fields() as $key => $field ) {
            if ( ! empty( $field['required'] ) && '' === $fields[ $key ] ) {
                wp_send_json_error( array( 'message' => __( 'Please fill in every required field.', 'loopkit' ) ), 422 );
            }
        }
        if ( ! is_email( $fields['email'] ) ) {
            wp_send_json_error( array( 'message' => __( 'That email address does not look right.', 'loopkit' ) ), 422 );
        }
        if ( ! array_key_exists( $payment, self::payment_methods() ) ) {
            wp_send_json_error( array( 'message' => __( 'Choose a payment method.', 'loopkit' ) ), 422 );
        }

        // A card paid on our own checkout arrives with the intent it was paid against.
        $intent = sanitize_text_field( wp_unslash( $_POST['payment_intent'] ?? '' ) );

        $order_id = self::create_order( $fields, $payment, $shipping );
        if ( is_wp_error( $order_id ) ) {
            wp_send_json_error( array( 'message' => $order_id->get_error_message() ), 400 );
        }

        if ( 'stripe' === $payment && '' !== $intent ) {
            $order = self::get_order( $order_id );
            update_post_meta( $order_id, '_lk_stripe_intent', $intent );
            if ( self::intent_is_paid( $intent, $order['total'] ) ) {
                update_post_meta( $order_id, '_lk_paid', 1 );
                self::set_status( $order_id, 'processing' );
            }

            wp_send_json_success( array(
                'order_id' => $order_id,
                'redirect' => add_query_arg(
                    array( 'lk_order' => $order_id, 'key' => get_post_meta( $order_id, '_lk_key', true ) ),
                    self::thank_you_url()
                ),
            ) );
        }

        $thank_you = add_query_arg(
            array( 'lk_order' => $order_id, 'key' => get_post_meta( $order_id, '_lk_key', true ) ),
            self::thank_you_url()
        );

        // Online methods send the customer off to pay first; they land back on the thank-you page.
        $pay_url = self::payment_redirect( $order_id, $payment, $thank_you );

        wp_send_json_success( array(
            'order_id' => $order_id,
            'redirect' => $pay_url ? $pay_url : $thank_you,
        ) );
    }

    /**
     * Are card fields on our own checkout switched on and set up?
     *
     * @return bool
     */
    public static function stripe_inline_ready() {
        $s = Loopkit_Module_WooCommerce::get_settings();
        return ! empty( $s['pay_stripe'] ) && ! empty( $s['stripe_secret'] ) && ! empty( $s['stripe_public'] ) && ! empty( $s['stripe_inline'] );
    }

    /**
     * Start a card payment: Stripe gives us a client secret, the browser confirms the card
     * against it, and the card number never touches this server.
     */
    public static function ajax_payment_intent() {
        check_ajax_referer( 'loopkit_shop_front', 'nonce' );

        if ( ! self::stripe_inline_ready() ) {
            wp_send_json_error( array( 'message' => __( 'Card payments are not set up.', 'loopkit' ) ), 400 );
        }
        if ( ! Loopkit_Shop_Cart::get_contents() ) {
            wp_send_json_error( array( 'message' => __( 'Your cart is empty.', 'loopkit' ) ), 400 );
        }

        $s        = Loopkit_Module_WooCommerce::get_settings();
        $totals   = Loopkit_Shop_Cart::get_totals( sanitize_key( wp_unslash( $_POST['shipping'] ?? '' ) ) );
        $currency = strtolower( (string) ( $s['currency'] ?? 'usd' ) );
        $zero     = array( 'jpy', 'krw', 'vnd', 'clp', 'isk', 'ugx' );
        $amount   = in_array( $currency, $zero, true ) ? (int) round( $totals['total'] ) : (int) round( $totals['total'] * 100 );

        $response = wp_remote_post( 'https://api.stripe.com/v1/payment_intents', array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $s['stripe_secret'],
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body'    => array(
                'amount'                 => $amount,
                'currency'               => $currency,
                'automatic_payment_methods[enabled]' => 'true',
                'automatic_payment_methods[allow_redirects]' => 'never',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ), 500 );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['client_secret'] ) ) {
            wp_send_json_error( array( 'message' => $data['error']['message'] ?? __( 'Stripe did not accept the payment.', 'loopkit' ) ), 500 );
        }

        wp_send_json_success( array(
            'clientSecret' => $data['client_secret'],
            'intentId'     => $data['id'] ?? '',
        ) );
    }

    /**
     * Has this payment intent actually been paid? Checked before an order is marked paid.
     *
     * @param string $intent_id Intent.
     * @param float  $expected  Expected total.
     * @return bool
     */
    private static function intent_is_paid( $intent_id, $expected ) {
        $s = Loopkit_Module_WooCommerce::get_settings();
        if ( '' === $intent_id || empty( $s['stripe_secret'] ) ) {
            return false;
        }

        $response = wp_remote_get( 'https://api.stripe.com/v1/payment_intents/' . rawurlencode( $intent_id ), array(
            'timeout' => 20,
            'headers' => array( 'Authorization' => 'Bearer ' . $s['stripe_secret'] ),
        ) );
        if ( is_wp_error( $response ) ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return 'succeeded' === ( $data['status'] ?? '' );
    }

    /**
     * Where an online payment method sends the customer, or '' for the ones paid offline.
     *
     * @param int    $order_id  Order.
     * @param string $payment   Method id.
     * @param string $thank_you Thank-you URL.
     * @return string
     */
    private static function payment_redirect( $order_id, $payment, $thank_you ) {
        $methods = self::payment_methods();
        $method  = $methods[ $payment ] ?? array();

        if ( 'stripe' === $payment ) {
            $session = self::stripe_session( $order_id, $thank_you );
            if ( is_wp_error( $session ) ) {
                // Payment could not be started: the order stays pending and says why.
                update_post_meta( $order_id, '_lk_payment_error', $session->get_error_message() );
                return '';
            }
            return $session;
        }

        // A method with a payment link: pay there, then come back.
        if ( ! empty( $method['link'] ) ) {
            update_post_meta( $order_id, '_lk_payment_link', $method['link'] );
            return (string) $method['link'];
        }

        return '';
    }

    /**
     * Ask Stripe for a Checkout session and hand back the URL to send the customer to.
     *
     * @param int    $order_id  Order.
     * @param string $thank_you Where Stripe returns them.
     * @return string|WP_Error
     */
    private static function stripe_session( $order_id, $thank_you ) {
        $s      = Loopkit_Module_WooCommerce::get_settings();
        $secret = (string) ( $s['stripe_secret'] ?? '' );
        if ( '' === $secret ) {
            return new WP_Error( 'lk_no_stripe', __( 'Card payments are not set up yet.', 'loopkit' ) );
        }

        $order    = self::get_order( $order_id );
        $currency = strtolower( (string) ( $s['currency'] ?? 'usd' ) );
        // Stripe counts in the smallest unit; zero-decimal currencies are sent as whole numbers.
        $zero_decimal = array( 'jpy', 'krw', 'vnd', 'clp', 'isk', 'ugx' );
        $amount       = in_array( $currency, $zero_decimal, true )
            ? (int) round( $order['total'] )
            : (int) round( $order['total'] * 100 );

        $body = array(
            'mode'                                 => 'payment',
            'success_url'                          => add_query_arg( 'lk_paid', '1', $thank_you ),
            'cancel_url'                           => add_query_arg( 'lk_paid', '0', $thank_you ),
            'client_reference_id'                  => (string) $order_id,
            'customer_email'                       => $order['customer']['email'],
            'line_items[0][quantity]'              => 1,
            'line_items[0][price_data][currency]'  => $currency,
            'line_items[0][price_data][unit_amount]' => $amount,
            /* translators: %d: order number */
            'line_items[0][price_data][product_data][name]' => sprintf( __( 'Order #%d', 'loopkit' ), $order_id ),
            'metadata[order_id]'                   => (string) $order_id,
        );

        $response = wp_remote_post( 'https://api.stripe.com/v1/checkout/sessions', array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body'    => $body,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['url'] ) ) {
            $message = $data['error']['message'] ?? __( 'Stripe did not accept the payment request.', 'loopkit' );
            return new WP_Error( 'lk_stripe_failed', $message );
        }

        update_post_meta( $order_id, '_lk_stripe_session', sanitize_text_field( $data['id'] ?? '' ) );
        return (string) $data['url'];
    }

    /**
     * When the customer returns from Stripe, ask Stripe whether the payment actually went
     * through before the order is marked as paid.
     *
     * @param int $order_id Order.
     * @return bool
     */
    public static function confirm_stripe_payment( $order_id ) {
        $s       = Loopkit_Module_WooCommerce::get_settings();
        $secret  = (string) ( $s['stripe_secret'] ?? '' );
        $session = (string) get_post_meta( $order_id, '_lk_stripe_session', true );
        if ( '' === $secret || '' === $session ) {
            return false;
        }

        $response = wp_remote_get( 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode( $session ), array(
            'timeout' => 20,
            'headers' => array( 'Authorization' => 'Bearer ' . $secret ),
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( 'paid' !== ( $data['payment_status'] ?? '' ) ) {
            return false;
        }

        update_post_meta( $order_id, '_lk_paid', 1 );
        self::set_status( $order_id, 'processing' );
        return true;
    }

    /**
     * Create the order from the cart.
     *
     * @param array  $fields   Customer fields.
     * @param string $payment  Payment method id.
     * @param string $shipping Shipping method id.
     * @return int|WP_Error
     */
    public static function create_order( $fields, $payment, $shipping = '' ) {
        $lines = Loopkit_Shop_Cart::get_contents();
        if ( empty( $lines ) ) {
            return new WP_Error( 'lk_empty_cart', __( 'Your cart is empty.', 'loopkit' ) );
        }

        // Last stock check before taking the order
        foreach ( $lines as $line ) {
            $available = Loopkit_Shop_Cart::available_stock( $line['product_id'], $line['variation'] );
            if ( null !== $available && $line['qty'] > $available ) {
                return new WP_Error( 'lk_stock', sprintf(
                    /* translators: 1: product name, 2: quantity left */
                    __( '%1$s: only %2$d left in stock.', 'loopkit' ),
                    $line['name'],
                    max( 0, $available )
                ) );
            }
        }

        $totals = Loopkit_Shop_Cart::get_totals( $shipping, trim( ( $fields['city'] ?? '' ) . ' ' . ( $fields['state'] ?? '' ) . ' ' . ( $fields['country'] ?? '' ) ) );

        $order_id = wp_insert_post( array(
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => sprintf( /* translators: %s: customer name */ __( 'Order from %s', 'loopkit' ), trim( ( $fields['first_name'] ?? '' ) . ' ' . ( $fields['last_name'] ?? '' ) ) ),
        ) );

        if ( is_wp_error( $order_id ) || ! $order_id ) {
            return new WP_Error( 'lk_order_failed', __( 'The order could not be created.', 'loopkit' ) );
        }

        $items = array();
        foreach ( $lines as $line ) {
            $items[] = array(
                'product_id' => $line['product_id'],
                'name'       => $line['name'],
                'variation'  => $line['variation'],
                'sku'        => $line['sku'],
                'qty'        => $line['qty'],
                'price'      => $line['price'],
                'total'      => $line['total'],
            );
        }

        update_post_meta( $order_id, '_lk_items', $items );
        update_post_meta( $order_id, '_lk_customer', wp_parse_args( $fields, array_fill_keys( array_keys( self::checkout_fields() ), '' ) ) );
        update_post_meta( $order_id, '_lk_payment', $payment );
        update_post_meta( $order_id, '_lk_shipping_method', $shipping ?: 'flat' );
        update_post_meta( $order_id, '_lk_subtotal', $totals['subtotal'] );
        update_post_meta( $order_id, '_lk_discount', $totals['discount'] );
        update_post_meta( $order_id, '_lk_shipping', $totals['shipping'] );
        if ( is_user_logged_in() ) {
            update_post_meta( $order_id, '_lk_user', get_current_user_id() );
        }
        update_post_meta( $order_id, '_lk_tax', $totals['tax'] ?? 0 );
        update_post_meta( $order_id, '_lk_total', $totals['total'] );
        update_post_meta( $order_id, '_lk_coupon', $totals['coupon']['code'] ?? '' );
        update_post_meta( $order_id, '_lk_status', 'cod' === $payment ? 'processing' : 'pending' );
        update_post_meta( $order_id, '_lk_key', wp_generate_password( 20, false, false ) );
        update_post_meta( $order_id, '_lk_user_id', get_current_user_id() );

        self::reduce_stock( $items );
        Loopkit_Shop_Cart::clear();
        self::send_emails( $order_id );
        self::record_submission( $order_id, $items, $fields, $payment, $totals );

        /**
         * An order was placed. Automations listen here.
         *
         * @param int   $order_id Order.
         * @param array $items    Line items.
         */
        do_action( 'loopkit_order_placed', $order_id, $items );

        return $order_id;
    }

    /**
     * Keep the customer's details as a Shop Submission — the lead — so checkouts are never
     * only inside the order. Works for every payment method, including ones added by a plugin
     * through `loopkit_shop_payment_methods`.
     *
     * @param int    $order_id Order.
     * @param array  $items    Line items.
     * @param array  $fields   Customer fields.
     * @param string $payment  Payment method id.
     * @param array  $totals   Totals.
     */
    private static function record_submission( $order_id, $items, $fields, $payment, $totals ) {
        if ( ! class_exists( 'Loopkit_Submissions' ) ) {
            return;
        }

        $names = array();
        foreach ( $items as $item ) {
            $names[] = $item['name'] . ( $item['variation'] ? ' (' . $item['variation'] . ')' : '' ) . ' × ' . $item['qty'];
        }

        $methods = self::payment_methods();
        $address = trim( implode( ', ', array_filter( array(
            $fields['address'] ?? '', $fields['address_2'] ?? '', $fields['city'] ?? '',
            $fields['state'] ?? '', $fields['postcode'] ?? '', $fields['country'] ?? '',
        ) ) ) );

        Loopkit_Submissions::record( 'shop', array(
            'Order'     => '#' . $order_id,
            'Name'      => trim( ( $fields['first_name'] ?? '' ) . ' ' . ( $fields['last_name'] ?? '' ) ),
            'Company'   => $fields['company'] ?? '',
            'Email'     => $fields['email'],
            'Phone'     => $fields['phone'],
            'Address'   => $address,
            'Items'     => implode( ', ', $names ),
            'Subtotal'  => Loopkit_Module_WooCommerce::format_price( $totals['subtotal'] ),
            'Discount'  => $totals['discount'] ? Loopkit_Module_WooCommerce::format_price( $totals['discount'] ) : '',
            'Shipping'  => Loopkit_Module_WooCommerce::format_price( $totals['shipping'] ),
            'Total'     => Loopkit_Module_WooCommerce::format_price( $totals['total'] ),
            'Payment'   => $methods[ $payment ]['label'] ?? $payment,
            'Coupon'    => $totals['coupon']['code'] ?? '',
            'Notes'     => $fields['notes'] ?? '',
        ), array(
            'form_name' => __( 'Checkout', 'loopkit' ),
            'page_url'  => self::page_url( 'checkout' ),
            'ref_id'    => (int) $order_id,
            'post_id'   => (int) $order_id,
        ) );
    }

    /**
     * Take the sold quantity off the stock.
     *
     * @param array $items Line items.
     */
    private static function reduce_stock( $items ) {
        foreach ( $items as $item ) {
            $product_id = (int) $item['product_id'];
            $data       = Loopkit_Module_WooCommerce::get_product_data( $product_id );

            // Variation stock
            if ( '' !== $item['variation'] && ! empty( $data['variations'] ) ) {
                $variations = $data['variations'];
                foreach ( $variations as $i => $var ) {
                    if ( ( $var['combo'] ?? '' ) === $item['variation'] && '' !== ( $var['stock_qty'] ?? '' ) ) {
                        $variations[ $i ]['stock_qty'] = max( 0, (int) $var['stock_qty'] - (int) $item['qty'] );
                    }
                }
                update_post_meta( $product_id, '_loopkit_variations', $variations );
            }

            if ( '1' === (string) $data['manage_stock'] && '' !== $data['stock_qty'] ) {
                $left = max( 0, (int) $data['stock_qty'] - (int) $item['qty'] );
                update_post_meta( $product_id, '_loopkit_stock_qty', $left );
                if ( 0 === $left ) {
                    update_post_meta( $product_id, '_loopkit_stock_status', 'outofstock' );
                }
            }
        }
    }

    /**
     * Order emails: one to the shop, one to the customer.
     *
     * @param int $order_id Order.
     */
    public static function send_emails( $order_id ) {
        $order    = self::get_order( $order_id );
        $settings = Loopkit_Module_WooCommerce::get_settings();
        $to_admin = ! empty( $settings['order_email'] ) ? $settings['order_email'] : get_option( 'admin_email' );
        $site     = get_bloginfo( 'name' );

        $lines = array();
        foreach ( $order['items'] as $item ) {
            $lines[] = sprintf(
                '%s%s × %d — %s',
                $item['name'],
                $item['variation'] ? ' (' . $item['variation'] . ')' : '',
                $item['qty'],
                Loopkit_Module_WooCommerce::format_price( $item['total'] )
            );
        }

        $summary = implode( "\n", $lines ) . "\n\n"
            . __( 'Subtotal', 'loopkit' ) . ': ' . Loopkit_Module_WooCommerce::format_price( $order['subtotal'] ) . "\n"
            . ( $order['discount'] ? __( 'Discount', 'loopkit' ) . ': -' . Loopkit_Module_WooCommerce::format_price( $order['discount'] ) . "\n" : '' )
            . __( 'Shipping', 'loopkit' ) . ': ' . Loopkit_Module_WooCommerce::format_price( $order['shipping'] ) . "\n"
            . ( ! empty( $order['tax'] ) ? Loopkit_Module_WooCommerce::get_settings()['tax_label'] . ': ' . Loopkit_Module_WooCommerce::format_price( $order['tax'] ) . "\n" : '' )
            . __( 'Total', 'loopkit' ) . ': ' . Loopkit_Module_WooCommerce::format_price( $order['total'] ) . "\n\n"
            . __( 'Payment', 'loopkit' ) . ': ' . ( self::payment_methods()[ $order['payment'] ]['label'] ?? $order['payment'] );

        $customer = $order['customer'];
        $address  = trim(
            $customer['address'] . ( ! empty( $customer['address_2'] ) ? "\n" . $customer['address_2'] : '' ) . "\n"
            . trim( $customer['city'] . ' ' . $customer['state'] . ' ' . $customer['postcode'] ) . "\n" . $customer['country']
        );

        // Shop
        wp_mail(
            $to_admin,
            /* translators: 1: order number, 2: site name */
            sprintf( __( 'New order #%1$d on %2$s', 'loopkit' ), $order_id, $site ),
            trim( $customer['first_name'] . ' ' . $customer['last_name'] ) . "\n" . $customer['email'] . "\n" . $customer['phone'] . "\n\n" . $address . "\n\n" . $summary
                . ( $customer['notes'] ? "\n\n" . __( 'Notes', 'loopkit' ) . ': ' . $customer['notes'] : '' )
                . "\n\n" . admin_url( 'admin.php?page=loopkit-orders&order=' . $order_id )
        );

        // Customer
        wp_mail(
            $customer['email'],
            /* translators: 1: site name, 2: order number */
            sprintf( __( '%1$s — order #%2$d received', 'loopkit' ), $site, $order_id ),
            /* translators: %s: customer first name */
            sprintf( __( 'Thanks %s, we have your order.', 'loopkit' ), $customer['first_name'] ) . "\n\n" . $summary
                . ( 'bank' === $order['payment'] ? "\n\n" . Loopkit_Module_WooCommerce::bank_details_text() : '' )
        );
    }

    /**
     * One order, ready to read.
     *
     * @param int $order_id Order.
     * @return array
     */
    public static function get_order( $order_id ) {
        $order_id = (int) $order_id;
        return array(
            'id'       => $order_id,
            'date'     => get_the_date( 'M j, Y H:i', $order_id ),
            // Only ever expose real item arrays: an empty or corrupted _lk_items meta used to
            // cast to array('') which made every consumer fatal on $item['name'] — taking the
            // whole single-order screen (including its Delete control) down with it.
            'items'    => array_values( array_filter( (array) get_post_meta( $order_id, '_lk_items', true ), 'is_array' ) ),
            'customer' => wp_parse_args( (array) get_post_meta( $order_id, '_lk_customer', true ), array_fill_keys( array_keys( self::checkout_fields() ), '' ) ),
            'payment'  => (string) get_post_meta( $order_id, '_lk_payment', true ),
            'shipping_method' => (string) get_post_meta( $order_id, '_lk_shipping_method', true ),
            'subtotal' => (float) get_post_meta( $order_id, '_lk_subtotal', true ),
            'discount' => (float) get_post_meta( $order_id, '_lk_discount', true ),
            'shipping' => (float) get_post_meta( $order_id, '_lk_shipping', true ),
            'tax'      => (float) get_post_meta( $order_id, '_lk_tax', true ),
            'total'    => (float) get_post_meta( $order_id, '_lk_total', true ),
            'coupon'   => (string) get_post_meta( $order_id, '_lk_coupon', true ),
            'status'   => (string) get_post_meta( $order_id, '_lk_status', true ) ?: 'pending',
            'key'      => (string) get_post_meta( $order_id, '_lk_key', true ),
        );
    }

    /**
     * Change an order's status and tell the customer.
     *
     * @param int    $order_id Order.
     * @param string $status   New status.
     */
    public static function set_status( $order_id, $status ) {
        if ( ! array_key_exists( $status, self::statuses() ) ) {
            return;
        }
        $old = get_post_meta( $order_id, '_lk_status', true );
        if ( $old === $status ) {
            return;
        }

        update_post_meta( $order_id, '_lk_status', $status );
        $order = self::get_order( $order_id );

        if ( ! empty( $order['customer']['email'] ) ) {
            wp_mail(
                $order['customer']['email'],
                /* translators: 1: order number, 2: status */
                sprintf( __( 'Order #%1$d is now %2$s', 'loopkit' ), $order_id, self::statuses()[ $status ] ),
                /* translators: 1: order number, 2: status */
                sprintf( __( 'Your order #%1$d is now marked as %2$s.', 'loopkit' ), $order_id, self::statuses()[ $status ] )
            );
        }

        /**
         * An order status changed.
         *
         * @param int    $order_id Order.
         * @param string $status   New status.
         * @param string $old      Previous status.
         */
        do_action( 'loopkit_order_status_changed', $order_id, $status, $old );
    }

    /* ---------------------------------------------------------------------
     * Pages
     * ------------------------------------------------------------------- */

    /**
     * Where the cart, checkout and thank-you pages live.
     *
     * @param string $which cart | checkout | thank_you.
     * @return string
     */
    public static function page_url( $which ) {
        $settings = Loopkit_Module_WooCommerce::get_settings();
        $page_id  = (int) ( $settings[ $which . '_page' ] ?? 0 );
        return $page_id ? (string) get_permalink( $page_id ) : home_url( '/' );
    }

    /**
     * Thank-you URL.
     *
     * @return string
     */
    public static function thank_you_url() {
        return self::page_url( 'thank_you' );
    }

    /**
     * The cart table, also used to refresh the page after a change.
     *
     * @return string
     */
    public static function cart_table_html( $preview = false ) {
        $lines  = Loopkit_Shop_Cart::get_contents();
        $totals = Loopkit_Shop_Cart::get_totals();

        // In the editor an empty cart would leave nothing to style, so the canvas shows real
        // products as a sample. Nothing is added to anyone's cart.
        if ( empty( $lines ) && $preview ) {
            $lines  = self::sample_lines();
            $totals = self::sample_totals( $lines );
        }

        ob_start();
        if ( empty( $lines ) ) {
            ?>
            <div class="lk-cart-empty-state">
                <p class="lk-cart-empty"><?php esc_html_e( 'Your cart is empty.', 'loopkit' ); ?></p>
                <a class="lk-btn-shop lk-btn-shop-primary" href="<?php echo esc_url( get_post_type_archive_link( Loopkit_Module_WooCommerce::POST_TYPE ) ?: home_url( '/' ) ); ?>">
                    <?php esc_html_e( 'Continue shopping', 'loopkit' ); ?>
                </a>
            </div>
            <?php
            return (string) ob_get_clean();
        }
        ?>
        <table class="lk-cart-table">
            <thead>
                <tr>
                    <th colspan="2"><?php esc_html_e( 'Product', 'loopkit' ); ?></th>
                    <th><?php esc_html_e( 'Price', 'loopkit' ); ?></th>
                    <th><?php esc_html_e( 'Quantity', 'loopkit' ); ?></th>
                    <th><?php esc_html_e( 'Total', 'loopkit' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $lines as $line ) : ?>
                <tr>
                    <td class="lk-cart-thumb"><?php if ( $line['image'] ) : ?><img src="<?php echo esc_url( $line['image'] ); ?>" alt=""><?php endif; ?></td>
                    <td>
                        <a href="<?php echo esc_url( $line['permalink'] ); ?>"><?php echo esc_html( $line['name'] ); ?></a>
                        <?php if ( $line['variation'] ) : ?><span class="lk-cart-variation"><?php echo esc_html( $line['variation'] ); ?></span><?php endif; ?>
                    </td>
                    <td><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $line['price'] ) ); ?></td>
                    <td><input type="number" class="lk-cart-qty" min="1" value="<?php echo (int) $line['qty']; ?>" data-key="<?php echo esc_attr( $line['key'] ); ?>"></td>
                    <td><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $line['total'] ) ); ?></td>
                    <td><button type="button" class="lk-cart-remove" data-key="<?php echo esc_attr( $line['key'] ); ?>" aria-label="<?php esc_attr_e( 'Remove', 'loopkit' ); ?>">&times;</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="lk-cart-footer">
            <form class="lk-coupon-form">
                <input type="text" class="lk-coupon-code" placeholder="<?php esc_attr_e( 'Coupon code', 'loopkit' ); ?>" value="<?php echo esc_attr( $totals['coupon']['code'] ?? '' ); ?>">
                <button type="submit" class="lk-btn-shop"><?php esc_html_e( 'Apply', 'loopkit' ); ?></button>
                <span class="lk-coupon-message"></span>
            </form>

            <div class="lk-cart-totals">
                <div><span><?php esc_html_e( 'Subtotal', 'loopkit' ); ?></span><strong data-lk-subtotal><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $totals['subtotal'] ) ); ?></strong></div>
                <?php if ( $totals['discount'] ) : ?>
                    <div><span><?php esc_html_e( 'Discount', 'loopkit' ); ?></span><strong data-lk-discount>-<?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $totals['discount'] ) ); ?></strong></div>
                <?php endif; ?>
                <div><span><?php esc_html_e( 'Shipping', 'loopkit' ); ?></span><strong data-lk-shipping><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $totals['shipping'] ) ); ?></strong></div>
                <?php if ( ! empty( $totals['tax'] ) ) : ?>
                    <div><span><?php echo esc_html( Loopkit_Module_WooCommerce::get_settings()['tax_label'] ?: __( 'Tax', 'loopkit' ) ); ?></span><strong data-lk-tax><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $totals['tax'] ) ); ?></strong></div>
                <?php endif; ?>
                <div class="lk-cart-total"><span><?php esc_html_e( 'Total', 'loopkit' ); ?></span><strong data-lk-total><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $totals['total'] ) ); ?></strong></div>
                <a class="lk-btn-shop lk-btn-shop-primary" href="<?php echo esc_url( self::page_url( 'checkout' ) ); ?>"><?php esc_html_e( 'Checkout', 'loopkit' ); ?></a>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * A couple of real products, priced as they are, purely so the editor has something to
     * lay out when the cart is empty.
     *
     * @return array
     */
    private static function sample_lines() {
        $products = get_posts( array(
            'post_type'      => Loopkit_Module_WooCommerce::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 2,
        ) );

        $lines = array();
        foreach ( $products as $index => $product ) {
            $price = (float) Loopkit_Module_WooCommerce::get_active_price( $product->ID );
            $qty   = $index + 1;
            $lines[ 'preview' . $index ] = array(
                'key'        => 'preview' . $index,
                'product_id' => $product->ID,
                'name'       => $product->post_title,
                'variation'  => '',
                'qty'        => $qty,
                'price'      => $price,
                'total'      => $price * $qty,
                'image'      => get_the_post_thumbnail_url( $product->ID, 'thumbnail' ),
                'permalink'  => get_permalink( $product->ID ),
                'sku'        => Loopkit_Module_WooCommerce::get_product_data( $product->ID )['sku'],
            );
        }

        if ( empty( $lines ) ) {
            $lines['preview0'] = array(
                'key' => 'preview0', 'product_id' => 0, 'name' => __( 'Sample product', 'loopkit' ), 'variation' => '',
                'qty' => 1, 'price' => 2499, 'total' => 2499, 'image' => '', 'permalink' => '#', 'sku' => 'SAMPLE',
            );
        }
        return $lines;
    }

    /**
     * Totals for the sample lines.
     *
     * @param array $lines Sample lines.
     * @return array
     */
    private static function sample_totals( $lines ) {
        $subtotal = 0;
        foreach ( $lines as $line ) {
            $subtotal += $line['total'];
        }
        $shipping = (float) ( Loopkit_Module_WooCommerce::get_settings()['flat_rate'] ?? 0 );

        return array(
            'subtotal' => $subtotal,
            'discount' => 0,
            'shipping' => $shipping,
            'total'    => $subtotal + $shipping,
            'coupon'   => null,
            'count'    => count( $lines ),
        );
    }

    /**
     * [loopkit_cart]
     *
     * @return string
     */
    public static function shortcode_cart() {
        return '<div class="lk-cart">' . self::cart_table_html() . '</div>';
    }

    /**
     * Every field the checkout can show. The set is fixed — a shop needs exactly these — and
     * each one says whether it is required and whether it can be switched off. Nothing here
     * can be added or deleted, so a checkout can never be saved in a broken state.
     *
     * @return array
     */
    public static function checkout_fields() {
        return array(
            'first_name' => array( 'label' => __( 'First name', 'loopkit' ), 'type' => 'text', 'required' => true, 'optional_toggle' => false, 'width' => 'half', 'group' => 'contact' ),
            'last_name'  => array( 'label' => __( 'Last name', 'loopkit' ), 'type' => 'text', 'required' => false, 'optional_toggle' => true, 'width' => 'half', 'group' => 'contact' ),
            'email'      => array( 'label' => __( 'Email', 'loopkit' ), 'type' => 'email', 'required' => true, 'optional_toggle' => false, 'width' => 'half', 'group' => 'contact' ),
            'phone'      => array( 'label' => __( 'Phone', 'loopkit' ), 'type' => 'tel', 'required' => true, 'optional_toggle' => false, 'width' => 'half', 'group' => 'contact' ),
            'company'    => array( 'label' => __( 'Company', 'loopkit' ), 'type' => 'text', 'required' => false, 'optional_toggle' => true, 'width' => 'full', 'group' => 'contact' ),
            'address'    => array( 'label' => __( 'Address', 'loopkit' ), 'type' => 'textarea', 'required' => true, 'optional_toggle' => false, 'width' => 'full', 'group' => 'address' ),
            'address_2'  => array( 'label' => __( 'Apartment, suite, etc.', 'loopkit' ), 'type' => 'text', 'required' => false, 'optional_toggle' => true, 'width' => 'full', 'group' => 'address' ),
            'city'       => array( 'label' => __( 'City', 'loopkit' ), 'type' => 'text', 'required' => true, 'optional_toggle' => false, 'width' => 'half', 'group' => 'address' ),
            'state'      => array( 'label' => __( 'State / Province', 'loopkit' ), 'type' => 'text', 'required' => false, 'optional_toggle' => true, 'width' => 'half', 'group' => 'address' ),
            'postcode'   => array( 'label' => __( 'Postcode', 'loopkit' ), 'type' => 'text', 'required' => false, 'optional_toggle' => true, 'width' => 'half', 'group' => 'address' ),
            'country'    => array( 'label' => __( 'Country', 'loopkit' ), 'type' => 'text', 'required' => false, 'optional_toggle' => true, 'width' => 'half', 'group' => 'address' ),
            'notes'      => array( 'label' => __( 'Order notes', 'loopkit' ), 'type' => 'textarea', 'required' => false, 'optional_toggle' => true, 'width' => 'full', 'group' => 'extra' ),
        );
    }

    /**
     * Is an optional field switched on? Optional fields are hidden with a widget setting
     * named hide_<field>; everything else is always shown.
     *
     * @param string $key      Field key.
     * @param array  $field    Field definition.
     * @param array  $settings Widget settings.
     * @return bool
     */
    private static function field_shown( $key, $field, $settings ) {
        if ( empty( $field['optional_toggle'] ) ) {
            return true;
        }
        $setting = $settings[ 'checkout_show_' . $key ] ?? 'yes';
        return ! in_array( (string) $setting, array( 'no', '', '0' ), true );
    }

    /**
     * [loopkit_checkout]
     *
     * @return string
     */
    public static function shortcode_checkout( $atts = array(), $content = '', $tag = '', $preview = false, $field_settings = array() ) {
        $field_settings = (array) $field_settings;
        $lines  = Loopkit_Shop_Cart::get_contents();
        $totals = Loopkit_Shop_Cart::get_totals();

        if ( empty( $lines ) && $preview ) {
            $lines  = self::sample_lines();
            $totals = self::sample_totals( $lines );
        }

        if ( empty( $lines ) ) {
            ob_start();
            ?>
            <div class="lk-cart-empty-state">
                <p class="lk-cart-empty"><?php esc_html_e( 'There is nothing to check out yet.', 'loopkit' ); ?></p>
                <a class="lk-btn-shop lk-btn-shop-primary" href="<?php echo esc_url( get_post_type_archive_link( Loopkit_Module_WooCommerce::POST_TYPE ) ?: home_url( '/' ) ); ?>">
                    <?php esc_html_e( 'Continue shopping', 'loopkit' ); ?>
                </a>
            </div>
            <?php
            return (string) ob_get_clean();
        }

        $settings = Loopkit_Module_WooCommerce::get_settings();
        $methods  = self::payment_methods();

        ob_start();
        ?>
        <form class="lk-checkout" novalidate>
            <div class="lk-checkout-fields">
                <h3 class="lk-checkout-heading"><?php esc_html_e( 'Your details', 'loopkit' ); ?></h3>
                <div class="lk-checkout-grid">
                    <?php foreach ( self::checkout_fields() as $key => $field ) : ?>
                        <?php
                        if ( 'extra' === $field['group'] || ! self::field_shown( $key, $field, $field_settings ) ) {
                            continue;
                        }
                        $required = ! empty( $field['required'] );
                        $id       = 'lk-checkout-' . $key;
                        ?>
                        <label class="<?php echo 'full' === $field['width'] ? 'lk-wide' : ''; ?>" for="<?php echo esc_attr( $id ); ?>">
                            <span><?php echo esc_html( $field['label'] ); ?><?php echo $required ? ' *' : ''; ?></span>
                            <?php if ( 'textarea' === $field['type'] ) : ?>
                                <textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="2" <?php echo $required ? 'required' : ''; ?>></textarea>
                            <?php else : ?>
                                <input type="<?php echo esc_attr( $field['type'] ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>"
                                       value="<?php echo 'country' === $key ? esc_attr( $settings['country'] ?? '' ) : ''; ?>" <?php echo $required ? 'required' : ''; ?>>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>

                    <?php foreach ( self::checkout_fields() as $key => $field ) : ?>
                        <?php
                        if ( 'extra' !== $field['group'] || ! self::field_shown( $key, $field, $field_settings ) ) {
                            continue;
                        }
                        ?>
                        <label class="lk-wide" for="lk-checkout-<?php echo esc_attr( $key ); ?>">
                            <span><?php echo esc_html( $field['label'] ); ?></span>
                            <textarea id="lk-checkout-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="2"></textarea>
                        </label>
                    <?php endforeach; ?>
                </div>

                <h3 class="lk-checkout-heading"><?php esc_html_e( 'Shipping', 'loopkit' ); ?></h3>
                <div class="lk-checkout-choices">
                    <label><input type="radio" name="shipping" value="flat" checked>
                        <?php
                        echo esc_html( sprintf(
                            /* translators: %s: shipping cost */
                            __( 'Delivery — %s', 'loopkit' ),
                            Loopkit_Module_WooCommerce::format_price( Loopkit_Shop_Cart::get_shipping( 'flat' ) )
                        ) );
                        ?>
                    </label>
                    <?php if ( ! empty( $settings['pickup'] ) ) : ?>
                        <label><input type="radio" name="shipping" value="pickup"> <?php esc_html_e( 'Local pickup — free', 'loopkit' ); ?></label>
                    <?php endif; ?>
                </div>

                <h3 class="lk-checkout-heading"><?php esc_html_e( 'Payment', 'loopkit' ); ?></h3>
                <div class="lk-checkout-choices">
                    <?php $first = true; foreach ( $methods as $id => $method ) : ?>
                        <label>
                            <input type="radio" name="payment" value="<?php echo esc_attr( $id ); ?>" <?php checked( $first ); ?>>
                            <strong><?php echo esc_html( $method['label'] ); ?></strong>
                            <span class="lk-pay-note"><?php echo esc_html( $method['description'] ); ?></span>
                            <?php if ( 'stripe' === $id && self::stripe_inline_ready() ) : ?>
                                <span class="lk-card-box" data-lk-card-box hidden>
                                    <span class="lk-card-field" id="lk-card-element"></span>
                                    <span class="lk-card-error" role="alert"></span>
                                    <span class="lk-card-hint"><?php esc_html_e( 'Your card details go straight to Stripe. They never reach this website.', 'loopkit' ); ?></span>
                                </span>
                            <?php endif; ?>
                        </label>
                    <?php $first = false; endforeach; ?>
                    <?php if ( empty( $methods ) ) : ?>
                        <p><?php esc_html_e( 'No payment method is switched on yet.', 'loopkit' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="lk-checkout-summary">
                <h3 class="lk-checkout-heading"><?php esc_html_e( 'Your order', 'loopkit' ); ?></h3>
                <ul class="lk-checkout-items">
                    <?php foreach ( $lines as $line ) : ?>
                        <li>
                            <span><?php echo esc_html( $line['name'] . ( $line['variation'] ? ' (' . $line['variation'] . ')' : '' ) ); ?> × <?php echo (int) $line['qty']; ?></span>
                            <strong><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $line['total'] ) ); ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="lk-cart-totals">
                    <div><span><?php esc_html_e( 'Subtotal', 'loopkit' ); ?></span><strong data-lk-subtotal><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $totals['subtotal'] ) ); ?></strong></div>
                    <?php if ( $totals['discount'] ) : ?>
                        <div><span><?php esc_html_e( 'Discount', 'loopkit' ); ?></span><strong data-lk-discount>-<?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $totals['discount'] ) ); ?></strong></div>
                    <?php endif; ?>
                    <div><span><?php esc_html_e( 'Shipping', 'loopkit' ); ?></span><strong data-lk-shipping><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $totals['shipping'] ) ); ?></strong></div>
                <?php if ( ! empty( $totals['tax'] ) ) : ?>
                    <div><span><?php echo esc_html( Loopkit_Module_WooCommerce::get_settings()['tax_label'] ?: __( 'Tax', 'loopkit' ) ); ?></span><strong data-lk-tax><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $totals['tax'] ) ); ?></strong></div>
                <?php endif; ?>
                    <div class="lk-cart-total"><span><?php esc_html_e( 'Total', 'loopkit' ); ?></span><strong data-lk-total><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $totals['total'] ) ); ?></strong></div>
                </div>
                <button type="submit" class="lk-btn-shop lk-btn-shop-primary lk-place-order"><?php echo esc_html( $field_settings['checkout_button_text'] ?? __( 'Place order', 'loopkit' ) ); ?></button>
                <p class="lk-checkout-message" role="alert"></p>
            </div>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * [loopkit_thank_you]
     *
     * @return string
     */
    public static function shortcode_thank_you() {
        $order_id = absint( $_GET['lk_order'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
        $key      = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

        if ( ! $order_id || get_post_type( $order_id ) !== self::POST_TYPE ) {
            return '<p>' . esc_html__( 'No order to show.', 'loopkit' ) . '</p>';
        }
        $order = self::get_order( $order_id );
        if ( ! hash_equals( $order['key'], $key ) && ! current_user_can( 'manage_options' ) ) {
            return '<p>' . esc_html__( 'No order to show.', 'loopkit' ) . '</p>';
        }

        // Back from an online payment: check it with the gateway before saying it is paid.
        $paid = (bool) get_post_meta( $order_id, '_lk_paid', true );
        if ( ! $paid && isset( $_GET['lk_paid'] ) && '1' === $_GET['lk_paid'] && 'stripe' === $order['payment'] ) { // phpcs:ignore WordPress.Security.NonceVerification
            $paid  = self::confirm_stripe_payment( $order_id );
            $order = self::get_order( $order_id );
        }

        $methods     = self::payment_methods();
        $method      = $methods[ $order['payment'] ] ?? array();
        $pay_link    = (string) get_post_meta( $order_id, '_lk_payment_link', true );
        $pay_error   = (string) get_post_meta( $order_id, '_lk_payment_error', true );

        ob_start();
        ?>
        <div class="lk-thankyou">
            <h2><?php esc_html_e( 'Thank you — your order is in.', 'loopkit' ); ?></h2>

            <?php if ( $paid ) : ?>
                <p class="lk-thankyou-paid"><?php esc_html_e( 'Payment received.', 'loopkit' ); ?></p>
            <?php elseif ( $pay_error ) : ?>
                <p class="lk-thankyou-note"><?php esc_html_e( 'We could not start the card payment, so the order is waiting. We will be in touch.', 'loopkit' ); ?></p>
            <?php elseif ( $pay_link ) : ?>
                <p class="lk-thankyou-note"><?php esc_html_e( 'Your order is waiting for payment.', 'loopkit' ); ?></p>
                <p><a class="lk-btn-shop lk-btn-shop-primary" href="<?php echo esc_url( $pay_link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Pay now', 'loopkit' ); ?></a></p>
            <?php elseif ( ! empty( $method['description'] ) ) : ?>
                <p class="lk-thankyou-note"><?php echo nl2br( esc_html( $method['description'] ) ); ?></p>
            <?php endif; ?>
            <p class="lk-thankyou-number">
                <?php
                /* translators: %d: order number */
                printf( esc_html__( 'Order number: #%d', 'loopkit' ), (int) $order_id );
                ?>
            </p>
            <ul class="lk-checkout-items">
                <?php foreach ( $order['items'] as $item ) : ?>
                    <li>
                        <span><?php echo esc_html( $item['name'] . ( $item['variation'] ? ' (' . $item['variation'] . ')' : '' ) ); ?> × <?php echo (int) $item['qty']; ?></span>
                        <strong><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $item['total'] ) ); ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="lk-cart-totals">
                <div class="lk-cart-total"><span><?php esc_html_e( 'Total', 'loopkit' ); ?></span><strong><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $order['total'] ) ); ?></strong></div>
                <div><span><?php esc_html_e( 'Payment', 'loopkit' ); ?></span><strong><?php echo esc_html( self::payment_methods()[ $order['payment'] ]['label'] ?? $order['payment'] ); ?></strong></div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /* ---------------------------------------------------------------------
     * Admin
     * ------------------------------------------------------------------- */

    /**
     * Status changes and deletes from the orders screen.
     */
    public static function handle_admin_actions() {
        if ( empty( $_POST['loopkit_order_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage orders.', 'loopkit' ) );
        }
        check_admin_referer( 'loopkit_orders' );

        $action   = sanitize_key( wp_unslash( $_POST['loopkit_order_action'] ) );
        $order_id = absint( $_POST['order_id'] ?? 0 );

        if ( 'set_status' === $action && $order_id ) {
            self::set_status( $order_id, sanitize_key( wp_unslash( $_POST['status'] ?? '' ) ) );
            wp_safe_redirect( admin_url( 'admin.php?page=loopkit-orders&order=' . $order_id . '&saved=1' ) );
            exit;
        }
        if ( 'delete_order' === $action && $order_id ) {
            wp_delete_post( $order_id, true );
            wp_safe_redirect( admin_url( 'admin.php?page=loopkit-orders&saved=deleted' ) );
            exit;
        }
    }

    /**
     * Shop Submissions: every checkout, with the payment method used and a link to the order.
     */
    public static function render_submissions_page() {
        if ( ! class_exists( 'Loopkit_Submissions' ) ) {
            return;
        }
        Loopkit_Submissions::install();

        $search   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
        $paged    = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
        $per_page = 20;
        $args     = array( 'source' => 'shop', 'search' => $search, 'page' => $paged, 'per_page' => $per_page );
        $rows     = Loopkit_Submissions::get_submissions( $args );
        $total    = Loopkit_Submissions::count_submissions( $args );
        $pages    = max( 1, (int) ceil( $total / $per_page ) );
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Shop Submissions', 'loopkit' ); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=loopkit-shop-submissions&loopkit_auto_action=export_csv&source=shop&s=' . rawurlencode( $search ) ), 'loopkit_export_csv' ) ); ?>">
                <?php esc_html_e( 'Export CSV', 'loopkit' ); ?>
            </a>
            <hr class="wp-header-end">

            <div class="lk-card">
                <div class="lk-card-header">
                    <h3><?php esc_html_e( 'Checkout leads', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc"><?php esc_html_e( 'Every checkout is kept here with the customer, the items, the total and the payment method they chose — including payment methods added by another plugin. Nothing is lost if an order email fails.', 'loopkit' ); ?></p>
                </div>
                <div class="lk-card-body">
                    <form method="get" style="margin-bottom:14px">
                        <input type="hidden" name="page" value="loopkit-shop-submissions">
                        <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email, phone…', 'loopkit' ); ?>">
                        <button type="submit" class="button"><?php esc_html_e( 'Search', 'loopkit' ); ?></button>
                        <span style="margin-left:10px;color:#646970"><?php
                            /* translators: %d: number of submissions */
                            printf( esc_html__( '%d total', 'loopkit' ), (int) $total );
                        ?></span>
                    </form>

                    <table class="widefat striped">
                        <thead><tr>
                            <th style="width:150px"><?php esc_html_e( 'Date', 'loopkit' ); ?></th>
                            <th style="width:200px"><?php esc_html_e( 'Customer', 'loopkit' ); ?></th>
                            <th><?php esc_html_e( 'Items', 'loopkit' ); ?></th>
                            <th style="width:120px"><?php esc_html_e( 'Total', 'loopkit' ); ?></th>
                            <th style="width:150px"><?php esc_html_e( 'Payment', 'loopkit' ); ?></th>
                            <th style="width:90px"><?php esc_html_e( 'Order', 'loopkit' ); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php if ( empty( $rows ) ) : ?>
                            <tr><td colspan="6"><?php esc_html_e( 'No checkouts yet. They appear here the moment someone places an order.', 'loopkit' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $rows as $row ) : $f = $row['fields']; ?>
                                <tr>
                                    <td><?php echo esc_html( mysql2date( 'M j, Y H:i', $row['created_at'] ) ); ?></td>
                                    <td>
                                        <strong><?php echo esc_html( $f['Name'] ?? '' ); ?></strong><br>
                                        <small><?php echo esc_html( $f['Email'] ?? '' ); ?><br><?php echo esc_html( $f['Phone'] ?? '' ); ?></small>
                                    </td>
                                    <td><?php echo esc_html( $f['Items'] ?? '' ); ?>
                                        <?php if ( ! empty( $f['Coupon'] ) ) : ?><br><small><?php echo esc_html( $f['Coupon'] ); ?></small><?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $f['Total'] ?? '' ); ?></td>
                                    <td><?php echo esc_html( $f['Payment'] ?? '' ); ?></td>
                                    <td>
                                        <?php if ( $row['ref_id'] && get_post_type( $row['ref_id'] ) === self::POST_TYPE ) : ?>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-orders&order=' . (int) $row['ref_id'] ) ); ?>">#<?php echo (int) $row['ref_id']; ?></a>
                                        <?php else : ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ( $pages > 1 ) : ?>
                        <div class="tablenav"><div class="tablenav-pages"><?php
                            echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                'base'    => admin_url( 'admin.php?page=loopkit-shop-submissions&paged=%#%&s=' . rawurlencode( $search ) ),
                                'format'  => '',
                                'current' => $paged,
                                'total'   => $pages,
                            ) );
                        ?></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Orders list and single order.
     */
    public static function render_orders_page() {
        $single = absint( $_GET['order'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
        if ( $single && get_post_type( $single ) === self::POST_TYPE ) {
            self::render_single_order( $single );
            return;
        }

        $orders = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        $statuses = self::statuses();
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Orders', 'loopkit' ); ?></h1>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Done.', 'loopkit' ); ?></p></div>
            <?php endif; ?>

            <div class="lk-card">
                <div class="lk-card-body">
                    <table class="widefat striped">
                        <thead><tr>
                            <th style="width:90px"><?php esc_html_e( 'Order', 'loopkit' ); ?></th>
                            <th><?php esc_html_e( 'Customer', 'loopkit' ); ?></th>
                            <th style="width:120px"><?php esc_html_e( 'Items', 'loopkit' ); ?></th>
                            <th style="width:130px"><?php esc_html_e( 'Total', 'loopkit' ); ?></th>
                            <th style="width:150px"><?php esc_html_e( 'Status', 'loopkit' ); ?></th>
                            <th style="width:170px"><?php esc_html_e( 'Date', 'loopkit' ); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php if ( empty( $orders ) ) : ?>
                            <tr><td colspan="6"><?php esc_html_e( 'No orders yet.', 'loopkit' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $orders as $post ) : $order = self::get_order( $post->ID ); ?>
                                <tr>
                                    <td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-orders&order=' . $post->ID ) ); ?>">#<?php echo (int) $post->ID; ?></a></strong></td>
                                    <td><?php echo esc_html( trim( $order['customer']['first_name'] . ' ' . $order['customer']['last_name'] ) ); ?><br><small><?php echo esc_html( $order['customer']['email'] ); ?></small></td>
                                    <td><?php echo (int) count( $order['items'] ); ?></td>
                                    <td><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $order['total'] ) ); ?></td>
                                    <td><?php echo esc_html( $statuses[ $order['status'] ] ?? $order['status'] ); ?></td>
                                    <td><?php echo esc_html( $order['date'] ); ?></td>
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
     * One order.
     *
     * @param int $order_id Order.
     */
    private static function render_single_order( $order_id ) {
        $order    = self::get_order( $order_id );
        $statuses = self::statuses();
        $c        = $order['customer'];
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline">#<?php echo (int) $order_id; ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( Loopkit_Shop_Account::invoice_link( $order_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Invoice', 'loopkit' ); ?></a>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-orders' ) ); ?>"><?php esc_html_e( 'All orders', 'loopkit' ); ?></a>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Order updated. The customer was emailed.', 'loopkit' ); ?></p></div>
            <?php endif; ?>

            <div class="lk-card">
                <div class="lk-card-header">
                    <h3><?php esc_html_e( 'Items', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc"><?php echo esc_html( $order['date'] ); ?></p>
                </div>
                <div class="lk-card-body">
                    <table class="widefat striped">
                        <thead><tr><th><?php esc_html_e( 'Product', 'loopkit' ); ?></th><th style="width:120px"><?php esc_html_e( 'SKU', 'loopkit' ); ?></th><th style="width:80px"><?php esc_html_e( 'Qty', 'loopkit' ); ?></th><th style="width:130px"><?php esc_html_e( 'Total', 'loopkit' ); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ( $order['items'] as $item ) : ?>
                            <tr>
                                <td><?php echo esc_html( $item['name'] ?? '' ); ?><?php echo ! empty( $item['variation'] ) ? ' <small>' . esc_html( $item['variation'] ) . '</small>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                <td><?php echo esc_html( ( $item['sku'] ?? '' ) ?: '—' ); ?></td>
                                <td><?php echo (int) ( $item['qty'] ?? 0 ); ?></td>
                                <td><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $item['total'] ?? 0 ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p style="margin-top:14px">
                        <?php esc_html_e( 'Subtotal', 'loopkit' ); ?>: <?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $order['subtotal'] ) ); ?><br>
                        <?php if ( $order['discount'] ) : ?>
                            <?php esc_html_e( 'Discount', 'loopkit' ); ?>: -<?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $order['discount'] ) ); ?>
                            <?php echo $order['coupon'] ? ' (' . esc_html( $order['coupon'] ) . ')' : ''; ?><br>
                        <?php endif; ?>
                        <?php esc_html_e( 'Shipping', 'loopkit' ); ?>: <?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $order['shipping'] ) ); ?><br>
                        <strong><?php esc_html_e( 'Total', 'loopkit' ); ?>: <?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $order['total'] ) ); ?></strong>
                    </p>
                </div>
            </div>

            <div class="lk-card">
                <div class="lk-card-header"><h3><?php esc_html_e( 'Customer', 'loopkit' ); ?></h3></div>
                <div class="lk-card-body">
                    <p>
                        <strong><?php echo esc_html( trim( $c['first_name'] . ' ' . $c['last_name'] ) ); ?></strong><br>
                        <?php echo esc_html( $c['email'] ); ?> &middot; <?php echo esc_html( $c['phone'] ); ?><br>
                        <?php echo nl2br( esc_html( $c['address'] ) ); ?><br>
                        <?php echo esc_html( trim( $c['city'] . ' ' . $c['postcode'] . ' ' . $c['country'] ) ); ?>
                    </p>
                    <?php if ( $c['notes'] ) : ?>
                        <p><em><?php echo esc_html( $c['notes'] ); ?></em></p>
                    <?php endif; ?>
                    <p><?php esc_html_e( 'Payment', 'loopkit' ); ?>: <strong><?php echo esc_html( self::payment_methods()[ $order['payment'] ]['label'] ?? $order['payment'] ); ?></strong></p>
                </div>
            </div>

            <div class="lk-card">
                <div class="lk-card-header"><h3><?php esc_html_e( 'Status', 'loopkit' ); ?></h3></div>
                <div class="lk-card-body">
                    <form method="post" class="lk-automation-form">
                        <?php wp_nonce_field( 'loopkit_orders' ); ?>
                        <input type="hidden" name="loopkit_order_action" value="set_status">
                        <input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>">
                        <div class="lk-auto-field" style="max-width:320px">
                            <label for="lk-order-status"><?php esc_html_e( 'Order status', 'loopkit' ); ?></label>
                            <select id="lk-order-status" name="status">
                                <?php foreach ( $statuses as $key => $label ) : ?>
                                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $order['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="lk-auto-help"><?php esc_html_e( 'The customer is emailed whenever this changes.', 'loopkit' ); ?></span>
                        </div>
                        <p class="lk-auto-submit"><button type="submit" class="lk-btn lk-btn-primary"><?php esc_html_e( 'Update Status', 'loopkit' ); ?></button></p>
                    </form>

                    <form method="post" onsubmit="return confirm('<?php esc_attr_e( 'Delete this order?', 'loopkit' ); ?>')">
                        <?php wp_nonce_field( 'loopkit_orders' ); ?>
                        <input type="hidden" name="loopkit_order_action" value="delete_order">
                        <input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>">
                        <button type="submit" class="button-link delete" style="color:#b32d2e"><?php esc_html_e( 'Delete order', 'loopkit' ); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
