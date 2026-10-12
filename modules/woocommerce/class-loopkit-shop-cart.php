<?php
/**
 * Loopkit Shop — cart.
 *
 * The cart lives in a transient keyed by a cookie, so it works without PHP sessions and
 * survives page loads for guests. Logged-in customers keep their cart in user meta as well,
 * so it follows them between devices.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Shop_Cart {

    const COOKIE = 'loopkit_cart';
    const TTL    = WEEK_IN_SECONDS;

    /**
     * Cart token for this visitor, created if needed.
     *
     * @return string
     */
    public static function token() {
        static $token = null;
        if ( null !== $token ) {
            return $token;
        }

        if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
            $token = sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
        } else {
            $token = wp_generate_password( 24, false, false );
            if ( ! headers_sent() ) {
                setcookie( self::COOKIE, $token, time() + self::TTL, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
            }
            $_COOKIE[ self::COOKIE ] = $token;
        }
        return $token;
    }

    /**
     * Raw cart contents.
     *
     * @return array
     */
    public static function get_items() {
        $items = get_transient( 'loopkit_cart_' . self::token() );
        if ( ! is_array( $items ) && is_user_logged_in() ) {
            $items = get_user_meta( get_current_user_id(), '_loopkit_cart', true );
        }
        return is_array( $items ) ? $items : array();
    }

    /**
     * Save the cart.
     *
     * @param array $items Items.
     */
    private static function save( $items ) {
        set_transient( 'loopkit_cart_' . self::token(), $items, self::TTL );
        if ( is_user_logged_in() ) {
            update_user_meta( get_current_user_id(), '_loopkit_cart', $items );
        }
        /**
         * The cart changed.
         *
         * @param array $items Items.
         */
        do_action( 'loopkit_cart_updated', $items );
    }

    /**
     * A key that separates the same product with different options.
     *
     * @param int    $product_id Product.
     * @param string $variation  Variation combination.
     * @return string
     */
    private static function key( $product_id, $variation ) {
        return md5( (int) $product_id . '|' . (string) $variation );
    }

    /**
     * Add a product.
     *
     * @param int    $product_id Product.
     * @param int    $qty        Quantity.
     * @param string $variation  Variation combination, e.g. "M / Black".
     * @return array|WP_Error Cart items, or an error.
     */
    public static function add( $product_id, $qty = 1, $variation = '' ) {
        $product_id = (int) $product_id;
        $qty        = max( 1, (int) $qty );

        if ( get_post_type( $product_id ) !== Loopkit_Module_WooCommerce::POST_TYPE || 'publish' !== get_post_status( $product_id ) ) {
            return new WP_Error( 'lk_no_product', __( 'That product is not available.', 'loopkit' ) );
        }

        $data = Loopkit_Module_WooCommerce::get_product_data( $product_id );
        if ( 'outofstock' === $data['stock_status'] ) {
            return new WP_Error( 'lk_out_of_stock', __( 'This product is out of stock.', 'loopkit' ) );
        }

        // Variable products must say which variation
        if ( 'variable' === $data['type'] && '' === $variation && ! empty( $data['variations'] ) ) {
            return new WP_Error( 'lk_pick_variation', __( 'Please choose an option first.', 'loopkit' ) );
        }

        $items = self::get_items();
        $key   = self::key( $product_id, $variation );
        $have  = isset( $items[ $key ] ) ? (int) $items[ $key ]['qty'] : 0;
        $want  = $have + $qty;

        $available = self::available_stock( $product_id, $variation );
        if ( null !== $available && $want > $available ) {
            if ( $available < 1 ) {
                return new WP_Error( 'lk_out_of_stock', __( 'This product is out of stock.', 'loopkit' ) );
            }
            /* translators: %d: quantity left */
            return new WP_Error( 'lk_stock_limit', sprintf( __( 'Only %d left in stock.', 'loopkit' ), $available ) );
        }

        $items[ $key ] = array(
            'product_id' => $product_id,
            'variation'  => (string) $variation,
            'qty'        => $want,
        );

        self::save( $items );
        return $items;
    }

    /**
     * Set the quantity of one line (0 removes it).
     *
     * @param string $key Line key.
     * @param int    $qty Quantity.
     * @return array
     */
    public static function set_qty( $key, $qty ) {
        $items = self::get_items();
        $key   = sanitize_text_field( $key );
        if ( ! isset( $items[ $key ] ) ) {
            return $items;
        }

        $qty = (int) $qty;
        if ( $qty < 1 ) {
            unset( $items[ $key ] );
        } else {
            $available = self::available_stock( $items[ $key ]['product_id'], $items[ $key ]['variation'] );
            $items[ $key ]['qty'] = ( null !== $available ) ? min( $qty, max( 1, $available ) ) : $qty;
        }

        self::save( $items );
        return $items;
    }

    /**
     * Remove one line.
     *
     * @param string $key Line key.
     * @return array
     */
    public static function remove( $key ) {
        return self::set_qty( $key, 0 );
    }

    /**
     * Empty the cart and any coupon.
     */
    public static function clear() {
        delete_transient( 'loopkit_cart_' . self::token() );
        delete_transient( 'loopkit_coupon_' . self::token() );
        if ( is_user_logged_in() ) {
            delete_user_meta( get_current_user_id(), '_loopkit_cart' );
        }
        do_action( 'loopkit_cart_updated', array() );
    }

    /**
     * Stock left for a product or one of its variations; null when stock is not tracked.
     *
     * @param int    $product_id Product.
     * @param string $variation  Variation.
     * @return int|null
     */
    public static function available_stock( $product_id, $variation = '' ) {
        $data = Loopkit_Module_WooCommerce::get_product_data( $product_id );

        if ( '' !== $variation && ! empty( $data['variations'] ) ) {
            foreach ( $data['variations'] as $var ) {
                if ( ( $var['combo'] ?? '' ) === $variation ) {
                    return ( '' === ( $var['stock_qty'] ?? '' ) ) ? null : (int) $var['stock_qty'];
                }
            }
        }

        if ( '1' !== (string) $data['manage_stock'] || '' === $data['stock_qty'] ) {
            return null;
        }
        return (int) $data['stock_qty'];
    }

    /**
     * The price of one line item, honouring variations and sales.
     *
     * @param int    $product_id Product.
     * @param string $variation  Variation.
     * @return float
     */
    public static function line_price( $product_id, $variation = '' ) {
        $data = Loopkit_Module_WooCommerce::get_product_data( $product_id );

        if ( '' !== $variation && ! empty( $data['variations'] ) ) {
            foreach ( $data['variations'] as $var ) {
                if ( ( $var['combo'] ?? '' ) !== $variation ) {
                    continue;
                }
                if ( '' !== ( $var['sale_price'] ?? '' ) ) {
                    return (float) $var['sale_price'];
                }
                if ( '' !== ( $var['price'] ?? '' ) ) {
                    return (float) $var['price'];
                }
            }
        }

        return (float) Loopkit_Module_WooCommerce::get_active_price( $product_id );
    }

    /**
     * The cart with names, prices and line totals worked out.
     *
     * @return array
     */
    public static function get_contents() {
        $out = array();
        foreach ( self::get_items() as $key => $item ) {
            $product_id = (int) $item['product_id'];
            if ( 'publish' !== get_post_status( $product_id ) ) {
                continue;
            }
            $price = self::line_price( $product_id, $item['variation'] );
            $qty   = max( 1, (int) $item['qty'] );

            $out[ $key ] = array(
                'key'        => $key,
                'product_id' => $product_id,
                'name'       => get_the_title( $product_id ),
                'variation'  => $item['variation'],
                'qty'        => $qty,
                'price'      => $price,
                'total'      => $price * $qty,
                'image'      => get_the_post_thumbnail_url( $product_id, 'thumbnail' ),
                'permalink'  => get_permalink( $product_id ),
                'sku'        => Loopkit_Module_WooCommerce::get_product_data( $product_id )['sku'],
            );
        }
        return $out;
    }

    /**
     * How many items are in the cart.
     *
     * @return int
     */
    public static function count() {
        $count = 0;
        foreach ( self::get_items() as $item ) {
            $count += max( 1, (int) $item['qty'] );
        }
        return $count;
    }

    /* ---------------------------------------------------------------------
     * Coupons
     * ------------------------------------------------------------------- */

    /**
     * Coupons defined in the shop settings.
     *
     * @return array
     */
    public static function get_coupons() {
        $coupons = get_option( 'loopkit_shop_coupons', array() );
        return is_array( $coupons ) ? $coupons : array();
    }

    /**
     * Whether a coupon can still be used at all.
     *
     * @param array $coupon Coupon.
     * @return true|WP_Error
     */
    private static function coupon_usable( $coupon ) {
        if ( isset( $coupon['active'] ) && empty( $coupon['active'] ) ) {
            return new WP_Error( 'lk_coupon_paused', __( 'That coupon is not available.', 'loopkit' ) );
        }
        if ( '' !== ( $coupon['usage_limit'] ?? '' ) && (int) ( $coupon['used'] ?? 0 ) >= (int) $coupon['usage_limit'] ) {
            return new WP_Error( 'lk_coupon_used_up', __( 'That coupon has been used up.', 'loopkit' ) );
        }
        return true;
    }

    /**
     * Try a coupon code against the current cart.
     *
     * @param string $code Code.
     * @return true|WP_Error
     */
    public static function apply_coupon( $code ) {
        $code = strtoupper( trim( sanitize_text_field( $code ) ) );
        if ( '' === $code ) {
            return new WP_Error( 'lk_coupon_empty', __( 'Enter a coupon code.', 'loopkit' ) );
        }

        foreach ( self::get_coupons() as $coupon ) {
            if ( strtoupper( $coupon['code'] ?? '' ) !== $code ) {
                continue;
            }
            $usable = self::coupon_usable( $coupon );
            if ( is_wp_error( $usable ) ) {
                return $usable;
            }
            if ( ! empty( $coupon['expires'] ) && strtotime( $coupon['expires'] . ' 23:59:59' ) < current_time( 'timestamp' ) ) {
                return new WP_Error( 'lk_coupon_expired', __( 'That coupon has expired.', 'loopkit' ) );
            }
            $subtotal = self::get_subtotal();
            if ( ! empty( $coupon['min_spend'] ) && $subtotal < (float) $coupon['min_spend'] ) {
                return new WP_Error( 'lk_coupon_min', sprintf(
                    /* translators: %s: minimum spend */
                    __( 'This coupon needs a subtotal of at least %s.', 'loopkit' ),
                    Loopkit_Module_WooCommerce::format_price( $coupon['min_spend'] )
                ) );
            }

            set_transient( 'loopkit_coupon_' . self::token(), $code, self::TTL );
            return true;
        }

        return new WP_Error( 'lk_coupon_invalid', __( 'That coupon code was not recognised.', 'loopkit' ) );
    }

    /**
     * Forget the coupon.
     */
    public static function remove_coupon() {
        delete_transient( 'loopkit_coupon_' . self::token() );
    }

    /**
     * The coupon on this cart, if any.
     *
     * @return array|null
     */
    public static function get_coupon() {
        $code = get_transient( 'loopkit_coupon_' . self::token() );
        if ( ! $code ) {
            return null;
        }
        foreach ( self::get_coupons() as $coupon ) {
            if ( strtoupper( $coupon['code'] ?? '' ) === strtoupper( $code ) ) {
                return $coupon;
            }
        }
        return null;
    }

    /* ---------------------------------------------------------------------
     * Totals
     * ------------------------------------------------------------------- */

    /**
     * Items total before discount and shipping.
     *
     * @return float
     */
    public static function get_subtotal() {
        $total = 0;
        foreach ( self::get_contents() as $line ) {
            $total += $line['total'];
        }
        return (float) $total;
    }

    /**
     * Discount from the applied coupon.
     *
     * @return float
     */
    public static function get_discount() {
        $coupon = self::get_coupon();
        if ( ! $coupon ) {
            return 0.0;
        }
        $subtotal = self::get_subtotal();
        $amount   = (float) ( $coupon['amount'] ?? 0 );

        $discount = ( 'percent' === ( $coupon['type'] ?? 'fixed' ) )
            ? $subtotal * ( $amount / 100 )
            : $amount;

        return (float) min( $discount, $subtotal );
    }

    /**
     * Shipping for the chosen method.
     *
     * @param string $method Method id.
     * @return float
     */
    public static function get_shipping( $method = '', $destination = '' ) {
        $s      = Loopkit_Module_WooCommerce::get_settings();
        $method = $method ?: ( $s['default_shipping'] ?? 'flat' );
        $after  = self::get_subtotal() - self::get_discount();

        if ( 'pickup' === $method ) {
            return 0.0;
        }

        // A zone that matches where the order is going wins over the flat rate.
        $zone = self::match_zone( $destination );
        if ( $zone ) {
            if ( '' !== ( $zone['free_over'] ?? '' ) && $after >= (float) $zone['free_over'] ) {
                return 0.0;
            }
            return (float) ( $zone['rate'] ?? 0 );
        }

        if ( ! empty( $s['free_shipping_over'] ) && $after >= (float) $s['free_shipping_over'] ) {
            return 0.0;
        }
        return (float) ( $s['flat_rate'] ?? 0 );
    }

    /**
     * The first shipping zone whose places include this destination.
     *
     * @param string $destination City, state or country the customer typed.
     * @return array|null
     */
    public static function match_zone( $destination ) {
        $destination = strtolower( trim( (string) $destination ) );
        if ( '' === $destination ) {
            return null;
        }

        foreach ( (array) ( Loopkit_Module_WooCommerce::get_settings()['zones'] ?? array() ) as $zone ) {
            foreach ( array_filter( array_map( 'trim', explode( ',', strtolower( (string) ( $zone['places'] ?? '' ) ) ) ) ) as $place ) {
                if ( '' !== $place && false !== strpos( $destination, $place ) ) {
                    return $zone;
                }
            }
        }
        return null;
    }

    /**
     * Tax on the order, as set in the shop settings.
     *
     * @param float $taxable Amount tax applies to.
     * @return float
     */
    public static function get_tax( $taxable ) {
        $s = Loopkit_Module_WooCommerce::get_settings();
        if ( empty( $s['tax_enabled'] ) || empty( $s['tax_rate'] ) ) {
            return 0.0;
        }

        $rate = (float) $s['tax_rate'] / 100;
        // Prices that already include tax: the tax is the part inside the total.
        if ( ! empty( $s['tax_included'] ) ) {
            return (float) ( $taxable - ( $taxable / ( 1 + $rate ) ) );
        }
        return (float) ( $taxable * $rate );
    }

    /**
     * Everything a checkout or cart page needs.
     *
     * @param string $shipping_method Chosen shipping.
     * @return array
     */
    public static function get_totals( $shipping_method = '', $destination = '' ) {
        $settings = Loopkit_Module_WooCommerce::get_settings();
        $subtotal = self::get_subtotal();
        $discount = self::get_discount();
        $shipping = self::get_shipping( $shipping_method, $destination );
        $taxable  = max( 0, $subtotal - $discount ) + ( empty( $settings['tax_shipping'] ) ? 0 : $shipping );
        $tax      = self::get_tax( $taxable );

        // With tax-inclusive prices the tax is already inside the total, so it is shown but
        // not added again.
        $total = empty( $settings['tax_included'] )
            ? max( 0, $subtotal - $discount + $shipping + $tax )
            : max( 0, $subtotal - $discount + $shipping );

        return array(
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'tax'      => $tax,
            'total'    => $total,
            'coupon'   => self::get_coupon(),
            'count'    => self::count(),
        );
    }
}
