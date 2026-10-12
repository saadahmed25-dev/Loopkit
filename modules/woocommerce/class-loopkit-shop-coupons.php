<?php
/**
 * Loopkit Shop — coupons.
 *
 * Coupons are stored in one option and managed on Shop > Coupons: a code, a percentage or
 * fixed discount, an optional minimum spend, an expiry date and a usage limit. Every use is
 * counted when an order is placed, so a limit actually holds.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Shop_Coupons {

    const OPTION = 'loopkit_shop_coupons';

    /**
     * Hook up.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 32 );
        add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
        add_action( 'loopkit_order_placed', array( __CLASS__, 'count_use' ), 10, 1 );
    }

    /**
     * All coupons.
     *
     * @return array
     */
    public static function all() {
        $coupons = get_option( self::OPTION, array() );
        return is_array( $coupons ) ? $coupons : array();
    }

    /**
     * One coupon by its code.
     *
     * @param string $code Code.
     * @return array|null
     */
    public static function get( $code ) {
        foreach ( self::all() as $coupon ) {
            if ( strtoupper( $coupon['code'] ?? '' ) === strtoupper( trim( (string) $code ) ) ) {
                return $coupon;
            }
        }
        return null;
    }

    /**
     * Count a use once an order carrying the coupon is placed.
     *
     * @param int $order_id Order.
     */
    public static function count_use( $order_id ) {
        $code = (string) get_post_meta( $order_id, '_lk_coupon', true );
        if ( '' === $code ) {
            return;
        }

        $coupons = self::all();
        foreach ( $coupons as $i => $coupon ) {
            if ( strtoupper( $coupon['code'] ?? '' ) === strtoupper( $code ) ) {
                $coupons[ $i ]['used'] = (int) ( $coupon['used'] ?? 0 ) + 1;
                update_option( self::OPTION, $coupons );
                return;
            }
        }
    }

    /**
     * Menu entry.
     */
    public static function register_menu() {
        add_submenu_page(
            'loopkit-shop',
            __( 'Coupons', 'loopkit' ),
            __( 'Coupons', 'loopkit' ),
            'manage_options',
            'loopkit-shop-coupons',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Save or delete a coupon.
     */
    public static function handle_save() {
        if ( empty( $_POST['loopkit_coupon_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage coupons.', 'loopkit' ) );
        }
        check_admin_referer( 'loopkit_coupons' );

        $action  = sanitize_key( wp_unslash( $_POST['loopkit_coupon_action'] ) );
        $coupons = self::all();
        $list    = admin_url( 'admin.php?page=loopkit-shop-coupons' );

        if ( 'delete' === $action ) {
            $code    = strtoupper( sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ) );
            $coupons = array_values( array_filter( $coupons, function( $c ) use ( $code ) {
                return strtoupper( $c['code'] ?? '' ) !== $code;
            } ) );
            update_option( self::OPTION, $coupons );
            wp_safe_redirect( add_query_arg( 'saved', 'deleted', $list ) );
            exit;
        }

        $raw  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $code = strtoupper( sanitize_text_field( $raw['code'] ?? '' ) );
        if ( '' === $code ) {
            wp_safe_redirect( add_query_arg( 'saved', 'error', $list ) );
            exit;
        }

        $entry = array(
            'code'        => $code,
            'type'        => in_array( $raw['type'] ?? 'percent', array( 'percent', 'fixed' ), true ) ? $raw['type'] : 'percent',
            'amount'      => max( 0, (float) ( $raw['amount'] ?? 0 ) ),
            'min_spend'   => '' === ( $raw['min_spend'] ?? '' ) ? '' : max( 0, (float) $raw['min_spend'] ),
            'expires'     => sanitize_text_field( $raw['expires'] ?? '' ),
            'usage_limit' => '' === ( $raw['usage_limit'] ?? '' ) ? '' : max( 0, (int) $raw['usage_limit'] ),
            'active'      => ! empty( $raw['active'] ) ? 1 : 0,
            'used'        => 0,
        );

        $found = false;
        foreach ( $coupons as $i => $coupon ) {
            if ( strtoupper( $coupon['code'] ?? '' ) === $code ) {
                $entry['used'] = (int) ( $coupon['used'] ?? 0 );
                $coupons[ $i ] = $entry;
                $found         = true;
                break;
            }
        }
        if ( ! $found ) {
            $coupons[] = $entry;
        }

        update_option( self::OPTION, $coupons );
        wp_safe_redirect( add_query_arg( 'saved', '1', $list ) );
        exit;
    }

    /**
     * Coupons screen: the list and the form together.
     */
    public static function render_page() {
        $coupons = self::all();
        $edit    = strtoupper( sanitize_text_field( wp_unslash( $_GET['coupon'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
        $current = $edit ? self::get( $edit ) : null;
        $current = wp_parse_args( (array) $current, array(
            'code' => '', 'type' => 'percent', 'amount' => '', 'min_spend' => '',
            'expires' => '', 'usage_limit' => '', 'active' => 1, 'used' => 0,
        ) );
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Coupons', 'loopkit' ); ?></h1>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-<?php echo 'error' === $_GET['saved'] ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification ?> is-dismissible"><p><?php
                    $state = sanitize_key( wp_unslash( $_GET['saved'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
                    if ( 'deleted' === $state ) {
                        esc_html_e( 'Coupon deleted.', 'loopkit' );
                    } elseif ( 'error' === $state ) {
                        esc_html_e( 'A code is required.', 'loopkit' );
                    } else {
                        esc_html_e( 'Coupon saved.', 'loopkit' );
                    }
                ?></p></div>
            <?php endif; ?>

            <form method="post" class="lk-automation-form">
                <?php wp_nonce_field( 'loopkit_coupons' ); ?>
                <input type="hidden" name="loopkit_coupon_action" value="save">

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <h3><?php echo $edit ? esc_html__( 'Edit coupon', 'loopkit' ) : esc_html__( 'Add a coupon', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'Customers type the code in the cart. A percentage comes off the items total; a fixed amount comes off the same total.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <div class="lk-auto-field">
                            <label for="lk-coupon-code"><?php esc_html_e( 'Code', 'loopkit' ); ?></label>
                            <input type="text" id="lk-coupon-code" name="code" value="<?php echo esc_attr( $current['code'] ); ?>" placeholder="SAVE10" required>
                            <span class="lk-auto-help"><?php esc_html_e( 'Not case sensitive for the customer.', 'loopkit' ); ?></span>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-coupon-type"><?php esc_html_e( 'Discount', 'loopkit' ); ?></label>
                            <select id="lk-coupon-type" name="type">
                                <option value="percent" <?php selected( $current['type'], 'percent' ); ?>><?php esc_html_e( 'Percentage off', 'loopkit' ); ?></option>
                                <option value="fixed" <?php selected( $current['type'], 'fixed' ); ?>><?php esc_html_e( 'Fixed amount off', 'loopkit' ); ?></option>
                            </select>
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-coupon-amount"><?php esc_html_e( 'Amount', 'loopkit' ); ?></label>
                            <input type="number" step="any" id="lk-coupon-amount" name="amount" value="<?php echo esc_attr( (string) $current['amount'] ); ?>" placeholder="10">
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-coupon-min"><?php esc_html_e( 'Minimum spend', 'loopkit' ); ?></label>
                            <input type="number" step="any" id="lk-coupon-min" name="min_spend" value="<?php echo esc_attr( (string) $current['min_spend'] ); ?>" placeholder="<?php esc_attr_e( 'no minimum', 'loopkit' ); ?>">
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-coupon-expires"><?php esc_html_e( 'Expires', 'loopkit' ); ?></label>
                            <input type="date" id="lk-coupon-expires" name="expires" value="<?php echo esc_attr( $current['expires'] ); ?>">
                        </div>
                        <div class="lk-auto-field">
                            <label for="lk-coupon-limit"><?php esc_html_e( 'Usage limit', 'loopkit' ); ?></label>
                            <input type="number" id="lk-coupon-limit" name="usage_limit" value="<?php echo esc_attr( (string) $current['usage_limit'] ); ?>" placeholder="<?php esc_attr_e( 'unlimited', 'loopkit' ); ?>">
                            <span class="lk-auto-help"><?php
                                /* translators: %d: times used */
                                printf( esc_html__( 'Used %d times so far.', 'loopkit' ), (int) $current['used'] );
                            ?></span>
                        </div>
                        <div class="lk-auto-field">
                            <label><?php esc_html_e( 'Status', 'loopkit' ); ?></label>
                            <label class="lk-auto-check"><input type="checkbox" name="active" value="1" <?php checked( ! empty( $current['active'] ) ); ?>> <?php esc_html_e( 'Active', 'loopkit' ); ?></label>
                        </div>
                    </div>
                </div>

                <p class="lk-auto-submit">
                    <button type="submit" class="lk-btn lk-btn-primary"><?php echo $edit ? esc_html__( 'Update coupon', 'loopkit' ) : esc_html__( 'Add coupon', 'loopkit' ); ?></button>
                    <?php if ( $edit ) : ?>
                        <a class="lk-btn lk-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-shop-coupons' ) ); ?>"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></a>
                    <?php endif; ?>
                </p>
            </form>

            <div class="lk-card">
                <div class="lk-card-body">
                    <table class="widefat striped">
                        <thead><tr>
                            <th style="width:150px"><?php esc_html_e( 'Code', 'loopkit' ); ?></th>
                            <th style="width:150px"><?php esc_html_e( 'Discount', 'loopkit' ); ?></th>
                            <th style="width:150px"><?php esc_html_e( 'Minimum', 'loopkit' ); ?></th>
                            <th style="width:130px"><?php esc_html_e( 'Expires', 'loopkit' ); ?></th>
                            <th style="width:120px"><?php esc_html_e( 'Used', 'loopkit' ); ?></th>
                            <th style="width:90px"><?php esc_html_e( 'Status', 'loopkit' ); ?></th>
                            <th style="width:120px"></th>
                        </tr></thead>
                        <tbody>
                        <?php if ( empty( $coupons ) ) : ?>
                            <tr><td colspan="7"><?php esc_html_e( 'No coupons yet.', 'loopkit' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $coupons as $coupon ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $coupon['code'] ); ?></strong></td>
                                    <td><?php
                                        echo 'percent' === $coupon['type']
                                            ? esc_html( $coupon['amount'] . '%' )
                                            : esc_html( Loopkit_Module_WooCommerce::format_price( $coupon['amount'] ) );
                                    ?></td>
                                    <td><?php echo '' === ( $coupon['min_spend'] ?? '' ) ? '&mdash;' : esc_html( Loopkit_Module_WooCommerce::format_price( $coupon['min_spend'] ) ); ?></td>
                                    <td><?php echo empty( $coupon['expires'] ) ? '&mdash;' : esc_html( $coupon['expires'] ); ?></td>
                                    <td><?php
                                        echo (int) ( $coupon['used'] ?? 0 );
                                        if ( '' !== ( $coupon['usage_limit'] ?? '' ) ) {
                                            echo ' / ' . (int) $coupon['usage_limit'];
                                        }
                                    ?></td>
                                    <td><?php echo empty( $coupon['active'] ) ? esc_html__( 'Paused', 'loopkit' ) : '<span style="color:#008a20">' . esc_html__( 'Active', 'loopkit' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                    <td>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-shop-coupons&coupon=' . rawurlencode( $coupon['code'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'loopkit' ); ?></a>
                                        <form method="post" style="display:inline" onsubmit="return confirm('<?php esc_attr_e( 'Delete this coupon?', 'loopkit' ); ?>')">
                                            <?php wp_nonce_field( 'loopkit_coupons' ); ?>
                                            <input type="hidden" name="loopkit_coupon_action" value="delete">
                                            <input type="hidden" name="code" value="<?php echo esc_attr( $coupon['code'] ); ?>">
                                            | <button type="submit" class="button-link delete" style="color:#b32d2e"><?php esc_html_e( 'Delete', 'loopkit' ); ?></button>
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
}
