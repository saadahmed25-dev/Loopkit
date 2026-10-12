<?php
/**
 * Loopkit Shop — builder widgets (Part 3).
 *
 * Every widget is rendered by this PHP on the front end and in the editor canvas, so the
 * preview always matches the live page. Control names are prefixed per widget so they can
 * never collide with the Advanced tab.
 *
 * Widgets: Product Grid, Add to Cart, Product Price, Product Gallery, Product Badge,
 * Cart Icon, Cart Table, Checkout Form, Product Categories.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helpers shared by the shop widgets.
 */
trait Loopkit_Shop_Widget_Helpers {

    /**
     * The product this widget is showing: the one being previewed, or the current post.
     *
     * @param bool $editor_mode In the canvas.
     * @return int
     */
    protected function product_id( $editor_mode = false ) {
        if ( class_exists( 'Loopkit_Post_Data' ) ) {
            $id = Loopkit_Post_Data::get_render_post_id( $editor_mode );
            if ( $id && Loopkit_Module_WooCommerce::POST_TYPE === get_post_type( $id ) ) {
                return (int) $id;
            }
        }
        $id = get_the_ID();
        if ( $id && Loopkit_Module_WooCommerce::POST_TYPE === get_post_type( $id ) ) {
            return (int) $id;
        }

        // In the editor, fall back to the newest product so the widget has something to show.
        $latest = get_posts( array( 'post_type' => Loopkit_Module_WooCommerce::POST_TYPE, 'posts_per_page' => 1, 'fields' => 'ids' ) );
        return $latest ? (int) $latest[0] : 0;
    }
}

/**
 * Product Grid.
 */
class Loopkit_Widget_Product_Grid extends Loopkit_Widget_Base {
    use Loopkit_Shop_Widget_Helpers;

    /** Template ids whose CSS has already been printed this request. */
    private static $printed_css = array();

    /**
     * Loop Item templates, for the card-design control.
     *
     * The same templates the Loop Grid uses. A product card and a post card
     * are the same shape of thing, so one kind of template serves both
     * rather than inventing a Product Loop Item that does the same job.
     *
     * @return array
     */
    protected static function loop_item_options() {
        $out = array( '' => __( '— the built-in card —', 'loopkit' ) );
        $posts = get_posts( array(
            'post_type'      => 'loopkit_template',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 100,
            'meta_key'       => '_loopkit_template_type',
            'meta_value'     => 'loop-item',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        foreach ( $posts as $p ) {
            $out[ (string) $p->ID ] = ( '' !== $p->post_title ? $p->post_title : '#' . $p->ID )
                . ( 'publish' !== $p->post_status ? ' (' . $p->post_status . ')' : '' );
        }
        return $out;
    }

    /**
     * A Loop Item template's own CSS, once per request.
     *
     * @param int $tid Template id.
     * @return string
     */
    protected static function template_css( $tid ) {
        if ( isset( self::$printed_css[ $tid ] ) ) {
            return '';
        }
        self::$printed_css[ $tid ] = true;
        $css = (string) get_post_meta( $tid, '_loopkit_css', true );
        if ( '' === $css ) {
            $file = WP_CONTENT_DIR . '/uploads/loopkit/css/post-' . (int) $tid . '.css';
            if ( file_exists( $file ) ) {
                $css = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            }
        }
        return '' !== $css ? '<style id="loopkit-loop-css-' . (int) $tid . '">' . $css . '</style>' : '';
    }

    public function get_name() { return 'product-grid'; }
    public function get_title() { return esc_html__( 'Product Grid', 'loopkit' ); }
    public function get_icon() { return 'dashicons-grid-view'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'products', 'shop', 'grid', 'store', 'woocommerce' ); }

    protected function register_controls() {
        $cats = array( '' => esc_html__( 'All categories', 'loopkit' ) );
        foreach ( get_terms( array( 'taxonomy' => Loopkit_Module_WooCommerce::TAX_CAT, 'hide_empty' => false ) ) as $term ) {
            if ( ! is_wp_error( $term ) && isset( $term->slug ) ) {
                $cats[ $term->slug ] = $term->name;
            }
        }

        return array(
            'content' => array(
                array(
                    'id'       => 'section_pgrid_query',
                    'title'    => __( 'Products', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'pgrid_source', 'label' => __( 'Source', 'loopkit' ), 'type' => 'select', 'default' => 'custom', 'options' => array(
                            'custom'  => esc_html__( 'Choose products', 'loopkit' ),
                            'current' => esc_html__( 'Current query (shop / category page)', 'loopkit' ),
                        ) ),
                        array( 'name' => 'pgrid_category', 'label' => __( 'Category', 'loopkit' ), 'type' => 'select', 'default' => '', 'options' => $cats, 'condition' => array( 'pgrid_source' => 'custom' ) ),
                        array( 'name' => 'pgrid_count', 'label' => __( 'How many', 'loopkit' ), 'type' => 'number', 'default' => 8, 'condition' => array( 'pgrid_source' => 'custom' ) ),
                        array( 'name' => 'pgrid_orderby', 'label' => __( 'Order by', 'loopkit' ), 'type' => 'select', 'default' => 'date', 'options' => array(
                            'date'  => esc_html__( 'Newest', 'loopkit' ),
                            'title' => __( 'Name', 'loopkit' ),
                            'price' => esc_html__( 'Price', 'loopkit' ),
                            'rand'  => esc_html__( 'Random', 'loopkit' ),
                        ), 'condition' => array( 'pgrid_source' => 'custom' ) ),
                        array( 'name' => 'pgrid_order', 'label' => __( 'Direction', 'loopkit' ), 'type' => 'select', 'default' => 'DESC', 'options' => array( 'DESC' => esc_html__( 'Descending', 'loopkit' ), 'ASC' => esc_html__( 'Ascending', 'loopkit' ) ), 'condition' => array( 'pgrid_source' => 'custom' ) ),
                        array( 'name' => 'pgrid_columns', 'label' => __( 'Columns', 'loopkit' ), 'type' => 'number', 'default' => 4, 'responsive' => true, 'min' => 1, 'max' => 6 ),
                        array( 'name' => 'pgrid_show_price', 'label' => __( 'Show Price', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'pgrid_show_cart', 'label' => __( 'Show Add to Cart', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'pgrid_show_badge', 'label' => __( 'Show Sale Badge', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'pgrid_quick_view', 'label' => __( 'Quick View', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                        array(
                            'name'    => 'pgrid_template',
                            'label'   => __( 'Card design', 'loopkit' ),
                            'type'    => 'select',
                            'default' => '',
                            'options' => self::loop_item_options(),
                        ),
                        array( 'name' => 'pgrid_show_cats', 'label' => __( 'Show Category', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                        array( 'name' => 'pgrid_cart_text', 'label' => __( 'Button Text', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Add to cart', 'loopkit' ) ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_pgrid_style',
                    'title'    => __( 'Cards', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'pgrid_gap', 'label' => __( 'Gap', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 24, 'unit' => 'px' ), 'responsive' => true, 'min' => 0, 'max' => 80 ),
                        array( 'name' => 'pgrid_card_bg', 'label' => __( 'Card Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pgrid_card_padding', 'label' => __( 'Card Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'pgrid_card_radius', 'label' => __( 'Card Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'pgrid_card_border', 'label' => __( 'Card Border Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pgrid_image_height', 'label' => __( 'Image Height', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 260, 'unit' => 'px' ), 'responsive' => true, 'min' => 80, 'max' => 700 ),
                        array( 'name' => 'pgrid_title_color', 'label' => __( 'Title Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pgrid_title_typography', 'label' => __( 'Title Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'pgrid_price_color', 'label' => __( 'Price Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pgrid_price_typography', 'label' => __( 'Price Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'pgrid_btn_bg', 'label' => __( 'Button Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'pgrid_btn_color', 'label' => __( 'Button Text Colour', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'pgrid_btn_hover_bg', 'label' => __( 'Button Hover Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pgrid_btn_hover_color', 'label' => __( 'Button Hover Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pgrid_badge_bg', 'label' => __( 'Badge Background', 'loopkit' ), 'type' => 'color', 'default' => '#ef4444' ),
                        array( 'name' => 'pgrid_align', 'label' => __( 'Text Alignment', 'loopkit' ), 'type' => 'choose', 'default' => 'left', 'responsive' => true, 'options' => array(
                            'left'   => array( 'title' => __( 'Left', 'loopkit' ), 'icon' => 'dashicons-editor-alignleft' ),
                            'center' => array( 'title' => __( 'Center', 'loopkit' ), 'icon' => 'dashicons-editor-aligncenter' ),
                            'right'  => array( 'title' => __( 'Right', 'loopkit' ), 'icon' => 'dashicons-editor-alignright' ),
                        ) ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $source = $settings['pgrid_source'] ?? 'custom';

        if ( 'current' === $source && ! $editor_mode && ( is_post_type_archive( Loopkit_Module_WooCommerce::POST_TYPE ) || is_tax( Loopkit_Module_WooCommerce::TAX_CAT ) || is_tax( Loopkit_Module_WooCommerce::TAX_TAG ) ) ) {
            global $wp_query;
            $products = $wp_query->posts;
        } else {
            $args = array(
                'post_type'      => Loopkit_Module_WooCommerce::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => max( 1, (int) ( $settings['pgrid_count'] ?? 8 ) ),
                'order'          => 'ASC' === ( $settings['pgrid_order'] ?? 'DESC' ) ? 'ASC' : 'DESC',
            );

            $orderby = $settings['pgrid_orderby'] ?? 'date';
            if ( 'price' === $orderby ) {
                $args['meta_key'] = '_loopkit_price';
                $args['orderby']  = 'meta_value_num';
            } else {
                $args['orderby'] = $orderby;
            }

            if ( ! empty( $settings['pgrid_category'] ) ) {
                $args['tax_query'] = array( array(
                    'taxonomy' => Loopkit_Module_WooCommerce::TAX_CAT,
                    'field'    => 'slug',
                    'terms'    => sanitize_title( $settings['pgrid_category'] ),
                ) );
            }

            $settings_shop = Loopkit_Module_WooCommerce::get_settings();
            if ( ! empty( $settings_shop['hide_out_of_stock'] ) ) {
                $args['meta_query'] = array( 'relation' => 'OR',
                    array( 'key' => '_loopkit_stock_status', 'value' => 'outofstock', 'compare' => '!=' ),
                    array( 'key' => '_loopkit_stock_status', 'compare' => 'NOT EXISTS' ),
                );
            }

            $products = get_posts( $args );
        }

        if ( empty( $products ) ) {
            return $editor_mode
                ? '<div class="lk-shop-empty">' . esc_html__( 'No products yet. Add one under Loopkit > Products.', 'loopkit' ) . '</div>'
                : '';
        }

        $show_price = $this->switcher_on( $settings, 'pgrid_show_price', true );
        $show_cart  = $this->switcher_on( $settings, 'pgrid_show_cart', true );
        $show_badge = $this->switcher_on( $settings, 'pgrid_show_badge', true );
        $quick_view = $this->switcher_on( $settings, 'pgrid_quick_view', false );
        $show_cats  = $this->switcher_on( $settings, 'pgrid_show_cats', false );
        $cart_text  = $settings['pgrid_cart_text'] ?? __( 'Add to cart', 'loopkit' );

        ob_start();
        // A Loop Item template, if one was chosen. Products are posts, so the
        // template renders against each one exactly as it does in a Loop Grid
        // — the dynamic tags resolve, and the card is whatever was designed.
        $tid = absint( $settings['pgrid_template'] ?? 0 );
        if ( $tid && 'loop-item' === get_post_meta( $tid, '_loopkit_template_type', true )
            && class_exists( 'Loopkit_Frontend' ) ) {
            $elements = Loopkit_Frontend::ensure_sections(
                Loopkit_Frontend::decode_elements( get_post_meta( $tid, '_loopkit_data', true ) )
            );
            if ( $elements ) {
                echo self::template_css( $tid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '<div class="lk-product-grid lk-product-grid--templated">';
                global $post;
                $saved = $post;
                foreach ( $products as $product ) {
                    $pid  = is_object( $product ) ? (int) $product->ID : (int) $product;
                    $post = get_post( $pid ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
                    setup_postdata( $post );
                    echo '<div class="lk-loop-item loopkit loopkit-' . (int) $tid . ' post-' . $pid . '" data-loopkit-type="loop-item">'
                        . Loopkit_Frontend::render_elements( $elements, false ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        . '</div>';
                }
                $post = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
                wp_reset_postdata();
                echo '</div>';
                // This method buffers and returns; a bare return would hand
                // back nothing and leave the buffer open, which then swallows
                // whatever the page prints next.
                return ob_get_clean();
            }
        }

        echo '<div class="lk-product-grid">';
        foreach ( $products as $product ) {
            $id   = is_object( $product ) ? (int) $product->ID : (int) $product;
            $data = Loopkit_Module_WooCommerce::get_product_data( $id );
            $sale = Loopkit_Module_WooCommerce::is_on_sale( $id );
            ?>
            <?php
            $lk_cats   = wp_get_object_terms( $id, 'loopkit_product_cat', array( 'fields' => 'slugs' ) );
            $lk_cats   = is_wp_error( $lk_cats ) ? array() : $lk_cats;
            $lk_price  = (float) Loopkit_Module_WooCommerce::get_active_price( $id );
            $lk_data   = Loopkit_Module_WooCommerce::get_product_data( $id );
            $lk_instock = 'outofstock' !== ( $lk_data['stock_status'] ?? 'instock' );
            ?>
            <div class="lk-product-card" data-lk-product
                 data-cats="<?php echo esc_attr( implode( ' ', $lk_cats ) ); ?>"
                 data-price="<?php echo esc_attr( $lk_price ); ?>"
                 data-instock="<?php echo $lk_instock ? '1' : '0'; ?>"
                 data-title="<?php echo esc_attr( get_the_title( $id ) ); ?>">
                <a class="lk-product-image" href="<?php echo esc_url( get_permalink( $id ) ); ?>">
                    <?php if ( has_post_thumbnail( $id ) ) : ?>
                        <?php echo get_the_post_thumbnail( $id, 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php else : ?>
                        <span class="lk-product-noimage"></span>
                    <?php endif; ?>
                    <?php if ( $show_badge && $sale ) : ?>
                        <span class="lk-product-badge"><?php esc_html_e( 'Sale', 'loopkit' ); ?></span>
                    <?php endif; ?>
                    <?php if ( 'outofstock' === $data['stock_status'] ) : ?>
                        <span class="lk-product-badge lk-product-badge-out"><?php esc_html_e( 'Sold out', 'loopkit' ); ?></span>
                    <?php endif; ?>
                </a>

                <?php if ( $quick_view ) : ?>
                    <?php // Outside the <a>, not inside it: a button nested in a link
                          // is invalid markup and the link swallows the click. A real
                          // button, not an overlay div, so the keyboard can reach it. ?>
                    <button type="button" class="lk-quick-view-btn"
                            data-lk-quick-view="<?php echo (int) $id; ?>"
                            aria-haspopup="dialog">
                        <?php esc_html_e( 'Quick view', 'loopkit' ); ?>
                    </button>
                <?php endif; ?>

                <div class="lk-product-body">
                    <?php if ( $show_cats ) : ?>
                        <?php $terms = get_the_terms( $id, Loopkit_Module_WooCommerce::TAX_CAT ); ?>
                        <?php if ( is_array( $terms ) ) : ?>
                            <span class="lk-product-cat"><?php echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) ); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <h3 class="lk-product-title"><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></h3>

                    <?php if ( $show_price ) : ?>
                        <div class="lk-product-price">
                            <?php if ( $sale ) : ?>
                                <del><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $data['regular_price'] ) ); ?></del>
                            <?php endif; ?>
                            <span><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( Loopkit_Module_WooCommerce::get_active_price( $id ) ) ); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ( $show_cart ) : ?>
                        <?php if ( 'variable' === $data['type'] && ! empty( $data['variations'] ) ) : ?>
                            <a class="lk-product-btn" href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php esc_html_e( 'Choose options', 'loopkit' ); ?></a>
                        <?php elseif ( 'outofstock' === $data['stock_status'] ) : ?>
                            <span class="lk-product-btn lk-product-btn-disabled"><?php esc_html_e( 'Sold out', 'loopkit' ); ?></span>
                        <?php else : ?>
                            <button type="button" class="lk-product-btn" data-lk-add-to-cart="<?php echo (int) $id; ?>" data-lk-added-text="<?php esc_attr_e( 'Added', 'loopkit' ); ?>"><?php echo esc_html( $cart_text ); ?></button>
                            <span class="lk-product-message" data-lk-message></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
        echo '</div>';
        return (string) ob_get_clean();
    }
}

/**
 * Add to Cart (single product).
 */
class Loopkit_Widget_Add_To_Cart extends Loopkit_Widget_Base {
    use Loopkit_Shop_Widget_Helpers;

    public function get_name() { return 'add-to-cart'; }
    public function get_title() { return esc_html__( 'Add to Cart', 'loopkit' ); }
    public function get_icon() { return 'dashicons-cart'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'cart', 'buy', 'shop', 'product' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_atc_content',
                    'title'    => __( 'Add to Cart', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'atc_text', 'label' => __( 'Button Text', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Add to cart', 'loopkit' ) ),
                        array( 'name' => 'atc_show_qty', 'label' => __( 'Quantity Box', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'atc_show_stock', 'label' => __( 'Stock Line', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'atc_full_width', 'label' => __( 'Full Width Button', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_atc_style',
                    'title'    => __( 'Button', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'atc_bg', 'label' => __( 'Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'atc_color', 'label' => __( 'Text Colour', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'atc_hover_bg', 'label' => __( 'Hover Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'atc_hover_color', 'label' => __( 'Hover Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'atc_padding', 'label' => __( 'Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'atc_radius', 'label' => __( 'Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'atc_typography', 'label' => __( 'Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'atc_gap', 'label' => __( 'Space Between', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 12, 'unit' => 'px' ), 'responsive' => true, 'min' => 0, 'max' => 60 ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $id = $this->product_id( $editor_mode );
        if ( ! $id ) {
            return $editor_mode ? '<div class="lk-shop-empty">' . esc_html__( 'Add a product to preview this.', 'loopkit' ) . '</div>' : '';
        }

        $data      = Loopkit_Module_WooCommerce::get_product_data( $id );
        $out_stock = 'outofstock' === $data['stock_status'];
        $classes   = 'lk-atc' . ( $this->switcher_on( $settings, 'atc_full_width', false ) ? ' lk-atc-full' : '' );

        ob_start();
        ?>
        <div class="<?php echo esc_attr( $classes ); ?>" data-lk-product>
            <?php if ( 'variable' === $data['type'] && ! empty( $data['variations'] ) ) : ?>
                <select class="lk-atc-variation" data-lk-variation aria-label="<?php esc_attr_e( 'Options', 'loopkit' ); ?>">
                    <?php foreach ( $data['variations'] as $var ) : ?>
                        <?php
                        $price = ( '' !== ( $var['sale_price'] ?? '' ) ) ? $var['sale_price'] : ( $var['price'] ?? '' );
                        $label = $var['combo'] . ( '' !== $price ? ' — ' . Loopkit_Module_WooCommerce::format_price( $price ) : '' );
                        $sold  = ( '' !== ( $var['stock_qty'] ?? '' ) && (int) $var['stock_qty'] < 1 );
                        ?>
                        <option value="<?php echo esc_attr( $var['combo'] ); ?>" <?php disabled( $sold ); ?>>
                            <?php echo esc_html( $label . ( $sold ? ' (' . __( 'sold out', 'loopkit' ) . ')' : '' ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <?php if ( $this->switcher_on( $settings, 'atc_show_qty', true ) && ! $out_stock ) : ?>
                <input type="number" class="lk-atc-qty" data-lk-qty value="1" min="1" aria-label="<?php esc_attr_e( 'Quantity', 'loopkit' ); ?>">
            <?php endif; ?>

            <?php if ( $out_stock ) : ?>
                <span class="lk-atc-btn lk-atc-disabled"><?php esc_html_e( 'Sold out', 'loopkit' ); ?></span>
            <?php else : ?>
                <button type="button" class="lk-atc-btn" data-lk-add-to-cart="<?php echo (int) $id; ?>" data-lk-added-text="<?php esc_attr_e( 'Added to cart', 'loopkit' ); ?>">
                    <?php echo esc_html( $settings['atc_text'] ?? __( 'Add to cart', 'loopkit' ) ); ?>
                </button>
            <?php endif; ?>

            <span class="lk-atc-message" data-lk-message></span>

            <?php if ( $this->switcher_on( $settings, 'atc_show_stock', true ) ) : ?>
                <span class="lk-atc-stock">
                    <?php
                    if ( $out_stock ) {
                        esc_html_e( 'Out of stock', 'loopkit' );
                    } elseif ( '1' === (string) $data['manage_stock'] && '' !== $data['stock_qty'] ) {
                        /* translators: %d: quantity in stock */
                        printf( esc_html__( '%d in stock', 'loopkit' ), (int) $data['stock_qty'] );
                    } else {
                        esc_html_e( 'In stock', 'loopkit' );
                    }
                    ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Product Price.
 */
class Loopkit_Widget_Product_Price extends Loopkit_Widget_Base {
    use Loopkit_Shop_Widget_Helpers;

    public function get_name() { return 'product-price'; }
    public function get_title() { return esc_html__( 'Product Price', 'loopkit' ); }
    public function get_icon() { return 'dashicons-tag'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'price', 'product', 'shop' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_pprice_content',
                    'title'    => __( 'Price', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'pprice_show_old', 'label' => __( 'Show Old Price', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'pprice_show_save', 'label' => __( 'Show "You save"', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_pprice_style',
                    'title'    => __( 'Price', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'pprice_color', 'label' => __( 'Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pprice_typography', 'label' => __( 'Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'pprice_old_color', 'label' => __( 'Old Price Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pprice_save_color', 'label' => __( '"You save" Colour', 'loopkit' ), 'type' => 'color', 'default' => '#16a34a' ),
                        array( 'name' => 'pprice_align', 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'default' => 'left', 'responsive' => true, 'options' => array(
                            'left'   => array( 'title' => __( 'Left', 'loopkit' ), 'icon' => 'dashicons-editor-alignleft' ),
                            'center' => array( 'title' => __( 'Center', 'loopkit' ), 'icon' => 'dashicons-editor-aligncenter' ),
                            'right'  => array( 'title' => __( 'Right', 'loopkit' ), 'icon' => 'dashicons-editor-alignright' ),
                        ) ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $id = $this->product_id( $editor_mode );
        if ( ! $id ) {
            return '';
        }
        $data    = Loopkit_Module_WooCommerce::get_product_data( $id );
        $active  = Loopkit_Module_WooCommerce::get_active_price( $id );
        $on_sale = Loopkit_Module_WooCommerce::is_on_sale( $id );

        ob_start();
        ?>
        <div class="lk-pprice">
            <?php if ( $on_sale && $this->switcher_on( $settings, 'pprice_show_old', true ) ) : ?>
                <del class="lk-pprice-old"><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $data['regular_price'] ) ); ?></del>
            <?php endif; ?>
            <span class="lk-pprice-now"><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $active ) ); ?></span>
            <?php if ( $on_sale && $this->switcher_on( $settings, 'pprice_show_save', false ) ) : ?>
                <span class="lk-pprice-save">
                    <?php
                    $saved = (float) $data['regular_price'] - (float) $active;
                    /* translators: %s: amount saved */
                    printf( esc_html__( 'You save %s', 'loopkit' ), esc_html( Loopkit_Module_WooCommerce::format_price( $saved ) ) );
                    ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Product Gallery.
 */
class Loopkit_Widget_Product_Gallery extends Loopkit_Widget_Base {
    use Loopkit_Shop_Widget_Helpers;

    public function get_name() { return 'product-gallery'; }
    public function get_title() { return esc_html__( 'Product Gallery', 'loopkit' ); }
    public function get_icon() { return 'dashicons-format-gallery'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'gallery', 'product', 'images', 'shop' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_pgal_content',
                    'title'    => __( 'Gallery', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'pgal_thumbs', 'label' => __( 'Thumbnails', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'pgal_thumb_count', 'label' => __( 'Thumbnails Per Row', 'loopkit' ), 'type' => 'number', 'default' => 5, 'responsive' => true, 'min' => 2, 'max' => 8 ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_pgal_style',
                    'title'    => __( 'Gallery', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'pgal_radius', 'label' => __( 'Image Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'pgal_gap', 'label' => __( 'Gap', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 10, 'unit' => 'px' ), 'responsive' => true, 'min' => 0, 'max' => 40 ),
                        array( 'name' => 'pgal_main_height', 'label' => __( 'Main Image Height', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 460, 'unit' => 'px' ), 'responsive' => true, 'min' => 100, 'max' => 900 ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $id = $this->product_id( $editor_mode );
        if ( ! $id ) {
            return '';
        }

        $images = array();
        if ( has_post_thumbnail( $id ) ) {
            $images[] = (int) get_post_thumbnail_id( $id );
        }
        foreach ( array_filter( explode( ',', (string) get_post_meta( $id, '_loopkit_gallery', true ) ) ) as $gid ) {
            $images[] = (int) $gid;
        }
        $images = array_values( array_unique( array_filter( $images ) ) );

        if ( empty( $images ) ) {
            return $editor_mode ? '<div class="lk-shop-empty">' . esc_html__( 'This product has no images yet.', 'loopkit' ) . '</div>' : '';
        }

        ob_start();
        ?>
        <div class="lk-pgallery">
            <div class="lk-pgallery-main">
                <img src="<?php echo esc_url( (string) wp_get_attachment_image_url( $images[0], 'large' ) ); ?>" alt="<?php echo esc_attr( get_the_title( $id ) ); ?>">
            </div>
            <?php if ( count( $images ) > 1 && $this->switcher_on( $settings, 'pgal_thumbs', true ) ) : ?>
                <div class="lk-pgallery-thumbs">
                    <?php foreach ( $images as $index => $image_id ) : ?>
                        <button type="button" class="lk-pgallery-thumb<?php echo 0 === $index ? ' is-active' : ''; ?>" data-full="<?php echo esc_url( (string) wp_get_attachment_image_url( $image_id, 'large' ) ); ?>">
                            <img src="<?php echo esc_url( (string) wp_get_attachment_image_url( $image_id, 'thumbnail' ) ); ?>" alt="">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Cart Icon.
 */
class Loopkit_Widget_Cart_Icon extends Loopkit_Widget_Base {
    use Loopkit_Shop_Widget_Helpers;

    public function get_name() { return 'cart-icon'; }
    public function get_title() { return esc_html__( 'Cart Icon', 'loopkit' ); }
    public function get_icon() { return 'dashicons-cart'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'cart', 'basket', 'icon', 'shop', 'header' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_cicon_content',
                    'title'    => __( 'Cart Icon', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'cicon_show_count', 'label' => __( 'Show Count', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'cicon_show_total', 'label' => __( 'Show Total', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                        array( 'name' => 'cicon_label', 'label' => __( 'Label', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_cicon_style',
                    'title'    => __( 'Icon', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'cicon_size', 'label' => __( 'Icon Size', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 22, 'unit' => 'px' ), 'responsive' => true, 'min' => 10, 'max' => 80 ),
                        array( 'name' => 'cicon_color', 'label' => __( 'Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'cicon_badge_bg', 'label' => __( 'Count Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'cicon_badge_color', 'label' => __( 'Count Colour', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'cicon_typography', 'label' => __( 'Typography', 'loopkit' ), 'type' => 'typography' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $count = class_exists( 'Loopkit_Shop_Cart' ) ? Loopkit_Shop_Cart::count() : 0;
        $total = class_exists( 'Loopkit_Shop_Cart' ) ? Loopkit_Shop_Cart::get_totals()['total'] : 0;
        $url   = class_exists( 'Loopkit_Shop_Orders' ) ? Loopkit_Shop_Orders::page_url( 'cart' ) : home_url( '/' );

        ob_start();
        ?>
        <a class="lk-cart-icon" href="<?php echo esc_url( $url ); ?>">
            <span class="lk-cart-icon-glyph" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="20" r="1.4"></circle><circle cx="18" cy="20" r="1.4"></circle>
                    <path d="M2 3h3l2.4 12.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 7H6"></path>
                </svg>
            </span>
            <?php if ( ! empty( $settings['cicon_label'] ) ) : ?>
                <span class="lk-cart-icon-label"><?php echo esc_html( $settings['cicon_label'] ); ?></span>
            <?php endif; ?>
            <?php if ( $this->switcher_on( $settings, 'cicon_show_count', true ) ) : ?>
                <span class="lk-cart-icon-count<?php echo 0 === $count ? ' lk-cart-empty-count' : ''; ?>" data-lk-cart-count><?php echo (int) $count; ?></span>
            <?php endif; ?>
            <?php if ( $this->switcher_on( $settings, 'cicon_show_total', false ) ) : ?>
                <span class="lk-cart-icon-total" data-lk-total><?php echo esc_html( Loopkit_Module_WooCommerce::format_price( $total ) ); ?></span>
            <?php endif; ?>
        </a>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Cart Table.
 */
class Loopkit_Widget_Cart_Table extends Loopkit_Widget_Base {

    public function get_name() { return 'cart-table'; }
    public function get_title() { return esc_html__( 'Cart', 'loopkit' ); }
    public function get_icon() { return 'dashicons-list-view'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'cart', 'basket', 'shop', 'table' ); }

    protected function register_controls() {
        return array(
            'style' => array(
                array(
                    'id'       => 'section_carttable_style',
                    'title'    => __( 'Cart', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'carttable_color', 'label' => __( 'Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'carttable_border', 'label' => __( 'Line Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'carttable_typography', 'label' => __( 'Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'carttable_btn_bg', 'label' => __( 'Button Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'carttable_btn_color', 'label' => __( 'Button Text Colour', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'carttable_btn_hover_bg', 'label' => __( 'Checkout Button Hover Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'carttable_btn_hover_color', 'label' => __( 'Checkout Button Hover Text', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'carttable_btn_radius', 'label' => __( 'Button Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'carttable_btn_typography', 'label' => __( 'Button Typography', 'loopkit' ), 'type' => 'typography' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        if ( ! class_exists( 'Loopkit_Shop_Orders' ) ) {
            return '';
        }
        return '<div class="lk-cart">' . Loopkit_Shop_Orders::cart_table_html( (bool) $editor_mode ) . '</div>';
    }
}

/**
 * Checkout Form.
 */
class Loopkit_Widget_Checkout_Form extends Loopkit_Widget_Base {

    public function get_name() { return 'checkout-form'; }
    public function get_title() { return esc_html__( 'Checkout', 'loopkit' ); }
    public function get_icon() { return 'dashicons-money-alt'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'checkout', 'order', 'pay', 'shop', 'form' ); }

    protected function register_controls() {
        // The field set is fixed — a shop needs exactly these — so there is nothing to add or
        // delete here. The optional ones can be switched off, and everything can be styled.
        $optional = array();
        foreach ( Loopkit_Shop_Orders::checkout_fields() as $key => $field ) {
            if ( empty( $field['optional_toggle'] ) ) {
                continue;
            }
            $optional[] = array(
                'name'    => 'checkout_show_' . $key,
                /* translators: %s: field label */
                'label'   => sprintf( __( 'Show %s', 'loopkit' ), $field['label'] ),
                'type'    => 'switcher',
                'default' => 'yes',
            );
        }

        return array(
            'content' => array(
                array(
                    'id'       => 'section_checkout_fields',
                    'title'    => __( 'Fields', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array_merge(
                        array(
                            array(
                                'name'  => 'checkout_fields_note',
                                'label' => __( 'Fields', 'loopkit' ),
                                'type'  => 'html',
                                'html'  => '<p style="margin:0;font-size:12px;line-height:1.5;opacity:.75">'
                                    . esc_html__( 'The checkout carries the fields a shop needs: name, email, phone, company, address, city, state, postcode, country and order notes. Required ones stay on; the rest can be switched off here. Fields cannot be added or removed, so checkout can never break.', 'loopkit' )
                                    . '</p>',
                            ),
                        ),
                        $optional,
                        array(
                            array( 'name' => 'checkout_button_text', 'label' => __( 'Button Text', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Place order', 'loopkit' ) ),
                        )
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_checkout_style',
                    'title'    => __( 'Form', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'checkout_heading_color', 'label' => __( 'Section Heading Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'checkout_heading_typography', 'label' => __( 'Section Heading Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'checkout_label_color', 'label' => __( 'Label Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'checkout_label_typography', 'label' => __( 'Label Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'checkout_field_bg', 'label' => __( 'Field Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'checkout_field_color', 'label' => __( 'Field Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'checkout_field_border', 'label' => __( 'Field Border Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'checkout_field_focus', 'label' => __( 'Field Border on Focus', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'checkout_field_radius', 'label' => __( 'Field Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'checkout_field_padding', 'label' => __( 'Field Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'checkout_field_typography', 'label' => __( 'Field Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'checkout_gap', 'label' => __( 'Space Between Fields', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 16, 'unit' => 'px' ), 'responsive' => true, 'min' => 0, 'max' => 60 ),
                    ),
                ),
                array(
                    'id'       => 'section_checkout_summary',
                    'title'    => __( 'Summary & Button', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'checkout_summary_bg', 'label' => __( 'Summary Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'checkout_summary_color', 'label' => __( 'Summary Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'checkout_summary_radius', 'label' => __( 'Summary Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'checkout_choice_border', 'label' => __( 'Payment Box Border', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'checkout_btn_bg', 'label' => __( 'Button Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'checkout_btn_color', 'label' => __( 'Button Text Colour', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'checkout_btn_hover_bg', 'label' => __( 'Button Hover Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'checkout_btn_hover_color', 'label' => __( 'Button Hover Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'checkout_btn_radius', 'label' => __( 'Button Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'checkout_btn_typography', 'label' => __( 'Button Typography', 'loopkit' ), 'type' => 'typography' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        if ( ! class_exists( 'Loopkit_Shop_Orders' ) ) {
            return '';
        }
        return Loopkit_Shop_Orders::shortcode_checkout( array(), '', '', (bool) $editor_mode, $settings );
    }
}

/**
 * Product Categories.
 */
class Loopkit_Widget_Product_Categories extends Loopkit_Widget_Base {

    public function get_name() { return 'product-categories'; }
    public function get_title() { return esc_html__( 'Product Categories', 'loopkit' ); }
    public function get_icon() { return 'dashicons-category'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'categories', 'product', 'shop', 'browse' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_pcats_content',
                    'title'    => __( 'Categories', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'pcats_columns', 'label' => __( 'Columns', 'loopkit' ), 'type' => 'number', 'default' => 4, 'responsive' => true, 'min' => 1, 'max' => 6 ),
                        array( 'name' => 'pcats_count', 'label' => __( 'How many', 'loopkit' ), 'type' => 'number', 'default' => 8 ),
                        array( 'name' => 'pcats_show_count', 'label' => __( 'Show Product Count', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'pcats_hide_empty', 'label' => __( 'Hide Empty', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_pcats_style',
                    'title'    => __( 'Cards', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'pcats_gap', 'label' => __( 'Gap', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 18, 'unit' => 'px' ), 'responsive' => true, 'min' => 0, 'max' => 60 ),
                        array( 'name' => 'pcats_bg', 'label' => __( 'Background', 'loopkit' ), 'type' => 'color', 'default' => '#f3f4f6' ),
                        array( 'name' => 'pcats_color', 'label' => __( 'Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pcats_padding', 'label' => __( 'Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'pcats_radius', 'label' => __( 'Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'pcats_typography', 'label' => __( 'Typography', 'loopkit' ), 'type' => 'typography' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $terms = get_terms( array(
            'taxonomy'   => Loopkit_Module_WooCommerce::TAX_CAT,
            'hide_empty' => $this->switcher_on( $settings, 'pcats_hide_empty', true ),
            'number'     => max( 1, (int) ( $settings['pcats_count'] ?? 8 ) ),
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return $editor_mode ? '<div class="lk-shop-empty">' . esc_html__( 'No product categories yet.', 'loopkit' ) . '</div>' : '';
        }

        $show_count = $this->switcher_on( $settings, 'pcats_show_count', true );

        ob_start();
        echo '<div class="lk-pcats">';
        foreach ( $terms as $term ) {
            ?>
            <a class="lk-pcat" href="<?php echo esc_url( (string) get_term_link( $term ) ); ?>">
                <span class="lk-pcat-name"><?php echo esc_html( $term->name ); ?></span>
                <?php if ( $show_count ) : ?>
                    <span class="lk-pcat-count"><?php echo (int) $term->count; ?></span>
                <?php endif; ?>
            </a>
            <?php
        }
        echo '</div>';
        return (string) ob_get_clean();
    }
}
