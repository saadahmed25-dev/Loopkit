<?php
/**
 * Loopkit Shop — the last set of storefront widgets: product filters, a mini cart, quick
 * view, the rating badge and the customer's account panel.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Product Filters — categories, price and stock, applied to any Product Grid on the page.
 */
class Loopkit_Widget_Product_Filters extends Loopkit_Widget_Base {

    public function get_name() { return 'product-filters'; }
    public function get_title() { return __( 'Product Filters', 'loopkit' ); }
    public function get_icon() { return 'dashicons-filter'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'filter', 'shop', 'products', 'category', 'price' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_pfilters',
                    'title'    => __( 'Filters', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'  => 'pfilters_note',
                            'label' => __( 'How this works', 'loopkit' ),
                            'type'  => 'html',
                            'html'  => '<p style="margin:0;font-size:12px;line-height:1.5;opacity:.75">'
                                . esc_html__( 'These filters work on every Product Grid on the same page, without reloading it.', 'loopkit' ) . '</p>',
                        ),
                        array( 'name' => 'pfilters_categories', 'label' => __( 'Category buttons', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'pfilters_price', 'label' => __( 'Price slider', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'pfilters_stock', 'label' => __( 'In stock only', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'pfilters_sort', 'label' => __( 'Sort dropdown', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'pfilters_all_label', 'label' => __( '"All" button text', 'loopkit' ), 'type' => 'text', 'default' => __( 'All', 'loopkit' ) ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_pfilters_style',
                    'title'    => __( 'Style', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'pfilters_gap', 'label' => __( 'Gap', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 0, 'max' => 40, 'default' => array( 'size' => 12, 'unit' => 'px' ) ),
                        array( 'name' => 'pfilters_chip_bg', 'label' => __( 'Button Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pfilters_chip_color', 'label' => __( 'Button Text', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pfilters_chip_radius', 'label' => __( 'Button Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'pfilters_active_bg', 'label' => __( 'Active Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'pfilters_active_color', 'label' => __( 'Active Text', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'pfilters_label_color', 'label' => __( 'Label Colour', 'loopkit' ), 'type' => 'color' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        if ( ! class_exists( 'Loopkit_Module_WooCommerce' ) ) {
            return '';
        }

        $terms = get_terms( array( 'taxonomy' => 'loopkit_product_cat', 'hide_empty' => false ) );
        $terms = is_wp_error( $terms ) ? array() : $terms;

        // The price range comes from the products themselves.
        $prices = array();
        foreach ( get_posts( array( 'post_type' => Loopkit_Module_WooCommerce::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $product_id ) {
            $prices[] = (float) Loopkit_Module_WooCommerce::get_active_price( $product_id );
        }
        $min = $prices ? (int) floor( min( $prices ) ) : 0;
        $max = $prices ? (int) ceil( max( $prices ) ) : 1000;
        if ( $max <= $min ) {
            $max = $min + 1;
        }

        ob_start();
        ?>
        <div class="lk-pfilters" data-lk-product-filters data-min="<?php echo esc_attr( $min ); ?>" data-max="<?php echo esc_attr( $max ); ?>">
            <?php if ( $this->switcher_on( $settings, 'pfilters_categories', true ) && $terms ) : ?>
                <div class="lk-pfilters-row lk-pfilters-cats">
                    <button type="button" class="lk-pfilter-chip lk-active" data-cat="*"><?php echo esc_html( $settings['pfilters_all_label'] ?? __( 'All', 'loopkit' ) ); ?></button>
                    <?php foreach ( $terms as $term ) : ?>
                        <button type="button" class="lk-pfilter-chip" data-cat="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="lk-pfilters-row lk-pfilters-controls">
                <?php if ( $this->switcher_on( $settings, 'pfilters_price', true ) ) : ?>
                    <label class="lk-pfilter-price">
                        <span class="lk-pfilter-label">
                            <?php esc_html_e( 'Up to', 'loopkit' ); ?>
                            <strong data-lk-price-out><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $max ) ); ?></strong>
                        </span>
                        <input type="range" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" value="<?php echo esc_attr( $max ); ?>" data-lk-price>
                    </label>
                <?php endif; ?>

                <?php if ( $this->switcher_on( $settings, 'pfilters_stock', true ) ) : ?>
                    <label class="lk-pfilter-check">
                        <input type="checkbox" data-lk-instock>
                        <span class="lk-pfilter-label"><?php esc_html_e( 'In stock only', 'loopkit' ); ?></span>
                    </label>
                <?php endif; ?>

                <?php if ( $this->switcher_on( $settings, 'pfilters_sort', true ) ) : ?>
                    <label class="lk-pfilter-sort">
                        <span class="lk-pfilter-label"><?php esc_html_e( 'Sort', 'loopkit' ); ?></span>
                        <select data-lk-sort>
                            <option value=""><?php esc_html_e( 'Default', 'loopkit' ); ?></option>
                            <option value="price-asc"><?php esc_html_e( 'Price: low to high', 'loopkit' ); ?></option>
                            <option value="price-desc"><?php esc_html_e( 'Price: high to low', 'loopkit' ); ?></option>
                            <option value="title"><?php esc_html_e( 'Name A–Z', 'loopkit' ); ?></option>
                        </select>
                    </label>
                <?php endif; ?>
            </div>

            <p class="lk-pfilters-count" data-lk-filter-count></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Mini Cart — the cart icon with a panel that opens under it.
 */
class Loopkit_Widget_Mini_Cart extends Loopkit_Widget_Base {

    public function get_name() { return 'mini-cart'; }
    public function get_title() { return __( 'Mini Cart', 'loopkit' ); }
    public function get_icon() { return 'dashicons-cart'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'cart', 'mini', 'basket', 'dropdown', 'shop' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_mc',
                    'title'    => __( 'Mini Cart', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'mc_icon', 'label' => __( 'Icon', 'loopkit' ), 'type' => 'icon' ),
                        array( 'name' => 'mc_show_count', 'label' => __( 'Show the number of items', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'mc_show_total', 'label' => __( 'Show the total', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                        array( 'name' => 'mc_empty_text', 'label' => __( 'Text when empty', 'loopkit' ), 'type' => 'text', 'default' => __( 'Your cart is empty.', 'loopkit' ) ),
                        array( 'name' => 'mc_cart_text', 'label' => __( 'Cart button', 'loopkit' ), 'type' => 'text', 'default' => __( 'View cart', 'loopkit' ) ),
                        array( 'name' => 'mc_checkout_text', 'label' => __( 'Checkout button', 'loopkit' ), 'type' => 'text', 'default' => __( 'Checkout', 'loopkit' ) ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_mc_style',
                    'title'    => __( 'Style', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'mc_icon_color', 'label' => __( 'Icon Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'mc_icon_size', 'label' => __( 'Icon Size', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 12, 'max' => 60, 'default' => array( 'size' => 22, 'unit' => 'px' ) ),
                        array( 'name' => 'mc_badge_bg', 'label' => __( 'Badge Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'mc_badge_color', 'label' => __( 'Badge Text', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'mc_panel_bg', 'label' => __( 'Panel Background', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'mc_panel_color', 'label' => __( 'Panel Text', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'mc_panel_width', 'label' => __( 'Panel Width', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 200, 'max' => 520, 'default' => array( 'size' => 320, 'unit' => 'px' ) ),
                        array( 'name' => 'mc_panel_radius', 'label' => __( 'Panel Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'mc_btn_bg', 'label' => __( 'Button Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'mc_btn_color', 'label' => __( 'Button Text', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        if ( ! class_exists( 'Loopkit_Shop_Cart' ) ) {
            return '';
        }

        $lines  = Loopkit_Shop_Cart::get_contents();
        $totals = Loopkit_Shop_Cart::get_totals();
        $icon   = ! empty( $settings['mc_icon']['value'] ) ? '<i class="' . esc_attr( $settings['mc_icon']['value'] ) . '"></i>' : '<span class="dashicons dashicons-cart"></span>';

        $settings_shop = Loopkit_Module_WooCommerce::get_settings();
        $cart_url      = $settings_shop['cart_page'] ? get_permalink( $settings_shop['cart_page'] ) : home_url( '/' );
        $checkout_url  = $settings_shop['checkout_page'] ? get_permalink( $settings_shop['checkout_page'] ) : home_url( '/' );

        ob_start();
        ?>
        <div class="lk-mc" data-lk-mini-cart>
            <button type="button" class="lk-mc-toggle" aria-expanded="false" aria-label="<?php esc_attr_e( 'Cart', 'loopkit' ); ?>">
                <?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php if ( $this->switcher_on( $settings, 'mc_show_count', true ) ) : ?>
                    <span class="lk-mc-count" data-lk-cart-count><?php echo (int) $totals['count']; ?></span>
                <?php endif; ?>
                <?php if ( $this->switcher_on( $settings, 'mc_show_total', false ) ) : ?>
                    <span class="lk-mc-total" data-lk-cart-total><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $totals['total'] ) ); ?></span>
                <?php endif; ?>
            </button>

            <div class="lk-mc-panel" hidden>
                <?php if ( empty( $lines ) ) : ?>
                    <p class="lk-mc-empty"><?php echo esc_html( $settings['mc_empty_text'] ?? __( 'Your cart is empty.', 'loopkit' ) ); ?></p>
                <?php else : ?>
                    <ul class="lk-mc-items">
                        <?php foreach ( $lines as $line ) : ?>
                            <li class="lk-mc-item">
                                <?php if ( ! empty( $line['image'] ) ) : ?>
                                    <img src="<?php echo esc_url( $line['image'] ); ?>" alt="" loading="lazy" decoding="async">
                                <?php endif; ?>
                                <span class="lk-mc-item-body">
                                    <a class="lk-mc-item-name" href="<?php echo esc_url( $line['permalink'] ); ?>"><?php echo esc_html( $line['name'] ); ?></a>
                                    <span class="lk-mc-item-meta"><?php echo (int) $line['qty']; ?> × <?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $line['price'] ) ); ?></span>
                                </span>
                                <button type="button" class="lk-mc-remove" data-lk-cart-remove="<?php echo esc_attr( $line['key'] ); ?>" aria-label="<?php esc_attr_e( 'Remove', 'loopkit' ); ?>">&times;</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="lk-mc-total-row">
                        <span><?php esc_html_e( 'Total', 'loopkit' ); ?></span>
                        <strong data-lk-total><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $totals['total'] ) ); ?></strong>
                    </div>

                    <div class="lk-mc-actions">
                        <a class="lk-mc-btn" href="<?php echo esc_url( $cart_url ); ?>"><?php echo esc_html( $settings['mc_cart_text'] ?? __( 'View cart', 'loopkit' ) ); ?></a>
                        <a class="lk-mc-btn lk-mc-btn-primary" href="<?php echo esc_url( $checkout_url ); ?>"><?php echo esc_html( $settings['mc_checkout_text'] ?? __( 'Checkout', 'loopkit' ) ); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Product Rating — the average stars and review count for a product.
 */
class Loopkit_Widget_Product_Rating extends Loopkit_Widget_Base {

    public function get_name() { return 'product-rating'; }
    public function get_title() { return __( 'Product Rating', 'loopkit' ); }
    public function get_icon() { return 'dashicons-star-filled'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'rating', 'stars', 'reviews', 'product' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_prating',
                    'title'    => __( 'Rating', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'prating_show_count', 'label' => __( 'Show the number of reviews', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'prating_show_number', 'label' => __( 'Show the average as a number', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'prating_empty', 'label' => __( 'Text when there are no reviews', 'loopkit' ), 'type' => 'text', 'default' => __( 'No reviews yet', 'loopkit' ) ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_prating_style',
                    'title'    => __( 'Style', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'prating_star_color', 'label' => __( 'Star Colour', 'loopkit' ), 'type' => 'color', 'default' => '#f59e0b' ),
                        array( 'name' => 'prating_empty_color', 'label' => __( 'Empty Star Colour', 'loopkit' ), 'type' => 'color', 'default' => '#d1d5db' ),
                        array( 'name' => 'prating_size', 'label' => __( 'Star Size', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 10, 'max' => 40, 'default' => array( 'size' => 18, 'unit' => 'px' ) ),
                        array( 'name' => 'prating_text_color', 'label' => __( 'Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'prating_align', 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'default' => 'left', 'options' => array(
                            'left' => __( 'Left', 'loopkit' ), 'center' => __( 'Center', 'loopkit' ), 'right' => __( 'Right', 'loopkit' ),
                        ) ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $product_id = get_the_ID();
        $average    = (float) get_post_meta( $product_id, '_loopkit_rating', true );
        $count      = (int) get_post_meta( $product_id, '_loopkit_rating_count', true );

        // Something to look at while designing.
        if ( $editor_mode && ! $count ) {
            $average = 4.5;
            $count   = 12;
        }

        if ( ! $count ) {
            return '<div class="lk-prating lk-prating-none">' . esc_html( $settings['prating_empty'] ?? __( 'No reviews yet', 'loopkit' ) ) . '</div>';
        }

        $full = (int) floor( $average );
        $half = ( $average - $full ) >= 0.5;

        ob_start();
        ?>
        <div class="lk-prating" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: rating */ __( 'Rated %s out of 5', 'loopkit' ), $average ) ); ?>">
            <span class="lk-prating-stars">
                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                    <span class="lk-prating-star<?php echo ( $i <= $full || ( $i === $full + 1 && $half ) ) ? ' lk-on' : ''; ?>">&#9733;</span>
                <?php endfor; ?>
            </span>
            <?php if ( $this->switcher_on( $settings, 'prating_show_number', true ) ) : ?>
                <span class="lk-prating-number"><?php echo esc_html( number_format_i18n( $average, 1 ) ); ?></span>
            <?php endif; ?>
            <?php if ( $this->switcher_on( $settings, 'prating_show_count', true ) ) : ?>
                <span class="lk-prating-count">
                    <?php
                    /* translators: %d: number of reviews */
                    printf( esc_html( _n( '(%d review)', '(%d reviews)', $count, 'loopkit' ) ), (int) $count );
                    ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * My Account — the customer's orders and downloads, as a widget.
 */
class Loopkit_Widget_My_Account extends Loopkit_Widget_Base {

    public function get_name() { return 'my-account'; }
    public function get_title() { return __( 'My Account', 'loopkit' ); }
    public function get_icon() { return 'dashicons-id'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'account', 'orders', 'downloads', 'customer' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_ma',
                    'title'    => __( 'Account', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'  => 'ma_note',
                            'label' => __( 'About', 'loopkit' ),
                            'type'  => 'html',
                            'html'  => '<p style="margin:0;font-size:12px;line-height:1.5;opacity:.75">'
                                . esc_html__( 'Shows the logged-in customer their own orders and the files they have paid for. Visitors who are not logged in get a login form.', 'loopkit' ) . '</p>',
                        ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_ma_style',
                    'title'    => __( 'Style', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'ma_heading_color', 'label' => __( 'Heading Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'ma_heading_typography', 'label' => __( 'Heading Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'ma_text_color', 'label' => __( 'Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'ma_row_border', 'label' => __( 'Row Border Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'ma_btn_bg', 'label' => __( 'Button Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'ma_btn_color', 'label' => __( 'Button Text', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        if ( ! class_exists( 'Loopkit_Shop_Account' ) ) {
            return '';
        }
        return Loopkit_Shop_Account::shortcode_account();
    }
}
