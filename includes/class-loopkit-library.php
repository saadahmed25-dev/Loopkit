<?php
/**
 * Loopkit — the starter library.
 *
 * Ready-made sections and whole pages that drop straight into the canvas. Each one is
 * plain element data, built from Loopkit's own widgets, so anything inserted can be edited
 * like something you built by hand.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Library {

    /**
     * Hook up.
     */
    public static function init() {
        add_action( 'wp_ajax_loopkit_library', array( __CLASS__, 'ajax_library' ) );
    }

    /**
     * A fresh element id.
     *
     * @return string
     */
    private static function id() {
        return 'lk' . substr( md5( uniqid( '', true ) ), 0, 7 );
    }

    /**
     * Shorthand for a widget element.
     *
     * @param string $type     Widget name.
     * @param array  $settings Settings.
     * @return array
     */
    private static function widget( $type, $settings = array() ) {
        return array(
            'id'         => self::id(),
            'elType'     => 'widget',
            'widgetType' => $type,
            'settings'   => array_merge( self::defaults_for( $type ), $settings ),
            'elements'   => array(),
        );
    }

    /**
     * A widget's own control defaults, so a library block carries real content instead of
     * an empty widget.
     *
     * @param string $type Widget name.
     * @return array
     */
    private static function defaults_for( $type ) {
        static $cache = array();
        if ( isset( $cache[ $type ] ) ) {
            return $cache[ $type ];
        }

        $defaults = array();
        $widget   = Loopkit_Widgets_Registry::instance()->get_widget( $type );

        if ( $widget ) {
            $method = new ReflectionMethod( $widget, 'register_controls' );
            $method->setAccessible( true );
            $config = $method->invoke( $widget );

            foreach ( (array) $config as $sections ) {
                foreach ( (array) $sections as $section ) {
                    foreach ( (array) ( $section['controls'] ?? array() ) as $control ) {
                        if ( isset( $control['name'], $control['default'] ) ) {
                            $defaults[ $control['name'] ] = $control['default'];
                        }
                    }
                }
            }
        }

        $cache[ $type ] = $defaults;
        return $defaults;
    }

    /**
     * Shorthand for a section holding elements.
     *
     * @param array $children Child elements.
     * @param array $settings Section settings.
     * @return array
     */
    private static function section( $children, $settings = array() ) {
        return array(
            'id'       => self::id(),
            'elType'   => 'section',
            'settings' => $settings,
            'elements' => $children,
        );
    }

    /**
     * Every block in the library, grouped by what it is for.
     *
     * @return array
     */
    public static function blocks() {
        $accent = '#38A8F5';

        $blocks = array();

        /* ---------------- Hero ---------------- */
        $blocks[] = array(
            'slug'     => 'hero-centred',
            'title'    => __( 'Hero — centred', 'loopkit' ),
            'group'    => __( 'Hero', 'loopkit' ),
            'keywords' => 'hero header banner intro',
            'elements' => array(
                self::section(
                    array(
                        self::widget( 'heading', array(
                            'title'       => __( 'Websites that earn their keep', 'loopkit' ),
                            'header_size' => 'h1',
                            'align'       => 'center',
                        ) ),
                        self::widget( 'text-editor', array(
                            'editor' => '<p>' . __( 'Design, build and launch — without the six-week wait or the agency retainer.', 'loopkit' ) . '</p>',
                            'align'  => 'center',
                        ) ),
                        self::widget( 'button', array(
                            'text'    => __( 'Start a project', 'loopkit' ),
                            'align'   => 'center',
                            'button_background_color' => $accent,
                        ) ),
                    ),
                    array( 'padding' => array( 'top' => '90', 'right' => '20', 'bottom' => '90', 'left' => '20', 'unit' => 'px', 'isLinked' => false ) )
                ),
            ),
        );

        $blocks[] = array(
            'slug'     => 'hero-split',
            'title'    => __( 'Hero — text and image', 'loopkit' ),
            'group'    => __( 'Hero', 'loopkit' ),
            'keywords' => 'hero split image banner',
            'elements' => array(
                self::section(
                    array(
                        self::widget( 'heading', array( 'title' => __( 'Built for the way you actually sell', 'loopkit' ), 'header_size' => 'h1' ) ),
                        self::widget( 'text-editor', array( 'editor' => '<p>' . __( 'One page, one job: turn a visitor into an enquiry. Everything else is decoration.', 'loopkit' ) . '</p>' ) ),
                        self::widget( 'button', array( 'text' => __( 'See the work', 'loopkit' ), 'button_background_color' => $accent ) ),
                        self::widget( 'image', array() ),
                    ),
                    array(
                        'flex_direction' => 'row',
                        'align_items'    => 'center',
                        'padding'        => array( 'top' => '70', 'right' => '20', 'bottom' => '70', 'left' => '20', 'unit' => 'px', 'isLinked' => false ),
                    )
                ),
            ),
        );

        /* ---------------- Features ---------------- */
        $blocks[] = array(
            'slug'     => 'features-three',
            'title'    => __( 'Three feature boxes', 'loopkit' ),
            'group'    => __( 'Features', 'loopkit' ),
            'keywords' => 'features services icons three columns',
            'elements' => array(
                self::section(
                    array(
                        self::widget( 'icon-box', array( 'title' => __( 'Fast to launch', 'loopkit' ), 'description' => __( 'Most builds go live inside two weeks.', 'loopkit' ) ) ),
                        self::widget( 'icon-box', array( 'title' => __( 'Built to convert', 'loopkit' ), 'description' => __( 'Every section earns its place on the page.', 'loopkit' ) ) ),
                        self::widget( 'icon-box', array( 'title' => __( 'Yours to edit', 'loopkit' ), 'description' => __( 'No lock-in, no monthly page-builder fee.', 'loopkit' ) ) ),
                    ),
                    array(
                        'flex_direction' => 'row',
                        'flex_gap'       => array( 'column' => '24', 'row' => '24', 'unit' => 'px' ),
                        'padding'        => array( 'top' => '60', 'right' => '20', 'bottom' => '60', 'left' => '20', 'unit' => 'px', 'isLinked' => false ),
                    )
                ),
            ),
        );

        $blocks[] = array(
            'slug'     => 'stats-row',
            'title'    => __( 'Numbers row', 'loopkit' ),
            'group'    => __( 'Features', 'loopkit' ),
            'keywords' => 'stats counters numbers results',
            'elements' => array(
                self::section(
                    array(
                        self::widget( 'counter', array( 'counter_end' => '120', 'counter_title' => __( 'Projects shipped', 'loopkit' ) ) ),
                        self::widget( 'counter', array( 'counter_end' => '14', 'counter_title' => __( 'Days average build', 'loopkit' ) ) ),
                        self::widget( 'counter', array( 'counter_end' => '98', 'counter_suffix' => '%', 'counter_title' => __( 'Would work with us again', 'loopkit' ) ) ),
                    ),
                    array(
                        'flex_direction' => 'row',
                        'padding'        => array( 'top' => '50', 'right' => '20', 'bottom' => '50', 'left' => '20', 'unit' => 'px', 'isLinked' => false ),
                    )
                ),
            ),
        );

        /* ---------------- Social proof ---------------- */
        $blocks[] = array(
            'slug'     => 'testimonials',
            'title'    => __( 'Testimonial carousel', 'loopkit' ),
            'group'    => __( 'Social proof', 'loopkit' ),
            'keywords' => 'testimonial reviews quotes clients',
            'elements' => array(
                self::section(
                    array(
                        self::widget( 'heading', array( 'title' => __( 'What clients say', 'loopkit' ), 'header_size' => 'h2', 'align' => 'center' ) ),
                        self::widget( 'testimonial-carousel', array() ),
                    ),
                    array( 'padding' => array( 'top' => '60', 'right' => '20', 'bottom' => '60', 'left' => '20', 'unit' => 'px', 'isLinked' => false ) )
                ),
            ),
        );

        $blocks[] = array(
            'slug'     => 'logos-marquee',
            'title'    => __( 'Logo marquee', 'loopkit' ),
            'group'    => __( 'Social proof', 'loopkit' ),
            'keywords' => 'logos clients brands marquee scroll',
            'elements' => array(
                self::section(
                    array(
                        self::widget( 'heading', array( 'title' => __( 'Trusted by', 'loopkit' ), 'header_size' => 'h3', 'align' => 'center' ) ),
                        self::widget( 'marquee', array( 'mq_rows' => 1, 'mq_separator' => '•' ) ),
                    ),
                    array( 'padding' => array( 'top' => '40', 'right' => '20', 'bottom' => '40', 'left' => '20', 'unit' => 'px', 'isLinked' => false ) )
                ),
            ),
        );

        $blocks[] = array(
            'slug'     => 'team-row',
            'title'    => __( 'Team row', 'loopkit' ),
            'group'    => __( 'Social proof', 'loopkit' ),
            'keywords' => 'team people staff about',
            'elements' => array(
                self::section(
                    array(
                        self::widget( 'team-member', array( 'team_name' => __( 'Zara Ahmed', 'loopkit' ), 'team_role' => __( 'Lead Designer', 'loopkit' ) ) ),
                        self::widget( 'team-member', array( 'team_name' => __( 'Omar Farooq', 'loopkit' ), 'team_role' => __( 'Developer', 'loopkit' ) ) ),
                        self::widget( 'team-member', array( 'team_name' => __( 'Hina Malik', 'loopkit' ), 'team_role' => __( 'Project Manager', 'loopkit' ) ) ),
                    ),
                    array(
                        'flex_direction' => 'row',
                        'flex_gap'       => array( 'column' => '24', 'row' => '24', 'unit' => 'px' ),
                        'padding'        => array( 'top' => '60', 'right' => '20', 'bottom' => '60', 'left' => '20', 'unit' => 'px', 'isLinked' => false ),
                    )
                ),
            ),
        );

        /* ---------------- Pricing and offers ---------------- */
        $blocks[] = array(
            'slug'     => 'pricing-three',
            'title'    => __( 'Three pricing plans', 'loopkit' ),
            'group'    => __( 'Pricing', 'loopkit' ),
            'keywords' => 'pricing plans packages table',
            'elements' => array(
                self::section(
                    array(
                        self::widget( 'price-table', array( 'pt_title' => __( 'Starter', 'loopkit' ), 'pt_price' => '499' ) ),
                        self::widget( 'price-table', array( 'pt_title' => __( 'Growth', 'loopkit' ), 'pt_price' => '999', 'pt_ribbon' => __( 'Popular', 'loopkit' ) ) ),
                        self::widget( 'price-table', array( 'pt_title' => __( 'Scale', 'loopkit' ), 'pt_price' => '1,899' ) ),
                    ),
                    array(
                        'flex_direction' => 'row',
                        'flex_gap'       => array( 'column' => '24', 'row' => '24', 'unit' => 'px' ),
                        'padding'        => array( 'top' => '60', 'right' => '20', 'bottom' => '60', 'left' => '20', 'unit' => 'px', 'isLinked' => false ),
                    )
                ),
            ),
        );

        $blocks[] = array(
            'slug'     => 'cta-band',
            'title'    => __( 'Call to action band', 'loopkit' ),
            'group'    => __( 'Pricing', 'loopkit' ),
            'keywords' => 'cta call to action banner offer',
            'elements' => array(
                self::section(
                    array( self::widget( 'call-to-action', array(
                        'cta_title'       => __( 'Ready when you are', 'loopkit' ),
                        'cta_description' => __( 'Tell us what you need and we will come back with a plan and a price.', 'loopkit' ),
                        'cta_button_text' => __( 'Get a quote', 'loopkit' ),
                    ) ) ),
                    array( 'padding' => array( 'top' => '40', 'right' => '20', 'bottom' => '40', 'left' => '20', 'unit' => 'px', 'isLinked' => false ) )
                ),
            ),
        );

        /* ---------------- Content ---------------- */
        $blocks[] = array(
            'slug'     => 'faq-accordion',
            'title'    => __( 'FAQ accordion', 'loopkit' ),
            'group'    => __( 'Content', 'loopkit' ),
            'keywords' => 'faq questions accordion answers',
            'elements' => array(
                self::section(
                    array(
                        self::widget( 'heading', array( 'title' => __( 'Questions people ask', 'loopkit' ), 'header_size' => 'h2', 'align' => 'center' ) ),
                        self::widget( 'accordion', array() ),
                    ),
                    array( 'padding' => array( 'top' => '60', 'right' => '20', 'bottom' => '60', 'left' => '20', 'unit' => 'px', 'isLinked' => false ) )
                ),
            ),
        );

        $blocks[] = array(
            'slug'     => 'contact-form',
            'title'    => __( 'Contact section', 'loopkit' ),
            'group'    => __( 'Content', 'loopkit' ),
            'keywords' => 'contact form enquiry email',
            'elements' => array(
                self::section(
                    array(
                        self::widget( 'heading', array( 'title' => __( 'Tell us about the project', 'loopkit' ), 'header_size' => 'h2' ) ),
                        self::widget( 'contact-form', array() ),
                    ),
                    array( 'padding' => array( 'top' => '60', 'right' => '20', 'bottom' => '60', 'left' => '20', 'unit' => 'px', 'isLinked' => false ) )
                ),
            ),
        );

        $blocks[] = array(
            'slug'     => 'blog-grid',
            'title'    => __( 'Latest posts', 'loopkit' ),
            'group'    => __( 'Content', 'loopkit' ),
            'keywords' => 'blog posts articles grid news',
            'elements' => array(
                self::section(
                    array(
                        self::widget( 'heading', array( 'title' => __( 'From the blog', 'loopkit' ), 'header_size' => 'h2' ) ),
                        self::widget( 'posts', array() ),
                    ),
                    array( 'padding' => array( 'top' => '60', 'right' => '20', 'bottom' => '60', 'left' => '20', 'unit' => 'px', 'isLinked' => false ) )
                ),
            ),
        );

        /* ---------------- Shop ---------------- */
        $blocks[] = array(
            'slug'     => 'shop-grid',
            'title'    => __( 'Shop — filters and grid', 'loopkit' ),
            'group'    => __( 'Shop', 'loopkit' ),
            'keywords' => 'shop products grid filters store',
            'module'   => 'woocommerce',
            'elements' => array(
                self::section(
                    array(
                        self::widget( 'heading', array( 'title' => __( 'Shop', 'loopkit' ), 'header_size' => 'h1' ) ),
                        self::widget( 'product-filters', array() ),
                        self::widget( 'product-grid', array() ),
                    ),
                    array( 'padding' => array( 'top' => '50', 'right' => '20', 'bottom' => '70', 'left' => '20', 'unit' => 'px', 'isLinked' => false ) )
                ),
            ),
        );

        $blocks[] = array(
            'slug'     => 'product-single',
            'title'    => __( 'Single product layout', 'loopkit' ),
            'group'    => __( 'Shop', 'loopkit' ),
            'keywords' => 'product single gallery add to cart',
            'module'   => 'woocommerce',
            'elements' => array(
                self::section(
                    array(
                        self::widget( 'product-gallery', array() ),
                        self::widget( 'post-title', array() ),
                        self::widget( 'product-rating', array() ),
                        self::widget( 'product-price', array() ),
                        self::widget( 'add-to-cart', array() ),
                    ),
                    array(
                        'flex_direction' => 'row',
                        'flex_gap'       => array( 'column' => '40', 'row' => '24', 'unit' => 'px' ),
                        'padding'        => array( 'top' => '50', 'right' => '20', 'bottom' => '50', 'left' => '20', 'unit' => 'px', 'isLinked' => false ),
                    )
                ),
            ),
        );

        /**
         * Blocks available in the starter library.
         *
         * @param array $blocks Blocks.
         */
        return apply_filters( 'loopkit_library_blocks', $blocks );
    }

    /**
     * Whole pages, each a list of block slugs.
     *
     * @return array
     */
    public static function pages() {
        return apply_filters( 'loopkit_library_pages', array(
            array(
                'slug'   => 'page-agency',
                'title'  => __( 'Agency home', 'loopkit' ),
                'blocks' => array( 'hero-centred', 'features-three', 'logos-marquee', 'testimonials', 'pricing-three', 'faq-accordion', 'cta-band' ),
            ),
            array(
                'slug'   => 'page-service',
                'title'  => __( 'Service page', 'loopkit' ),
                'blocks' => array( 'hero-split', 'features-three', 'stats-row', 'testimonials', 'contact-form' ),
            ),
            array(
                'slug'   => 'page-about',
                'title'  => __( 'About page', 'loopkit' ),
                'blocks' => array( 'hero-split', 'stats-row', 'team-row', 'testimonials', 'cta-band' ),
            ),
            array(
                'slug'   => 'page-shop',
                'title'  => __( 'Shop page', 'loopkit' ),
                'module' => 'woocommerce',
                'blocks' => array( 'shop-grid', 'logos-marquee', 'cta-band' ),
            ),
        ) );
    }

    /**
     * What the editor asks for: the catalogue, or one item's elements.
     */
    public static function ajax_library() {
        check_ajax_referer( 'loopkit_library', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Not allowed.', 'loopkit' ) ), 403 );
        }

        $what = sanitize_key( wp_unslash( $_REQUEST['what'] ?? 'list' ) );
        $slug = sanitize_key( wp_unslash( $_REQUEST['slug'] ?? '' ) );

        // A block belonging to a module that is switched off is not offered.
        $enabled = function( $item ) {
            if ( empty( $item['module'] ) ) {
                return true;
            }
            return class_exists( 'Loopkit_Modules' ) && Loopkit_Modules::is_enabled( $item['module'] );
        };

        if ( 'list' === $what ) {
            $blocks = array();
            foreach ( self::blocks() as $block ) {
                if ( ! $enabled( $block ) ) {
                    continue;
                }
                $blocks[] = array(
                    'slug'     => $block['slug'],
                    'title'    => $block['title'],
                    'group'    => $block['group'],
                    'keywords' => $block['keywords'] ?? '',
                );
            }

            $pages = array();
            foreach ( self::pages() as $page ) {
                if ( ! $enabled( $page ) ) {
                    continue;
                }
                $pages[] = array(
                    'slug'  => $page['slug'],
                    'title' => $page['title'],
                    'count' => count( $page['blocks'] ),
                );
            }

            wp_send_json_success( array( 'blocks' => $blocks, 'pages' => $pages ) );
        }

        if ( 'block' === $what ) {
            foreach ( self::blocks() as $block ) {
                if ( $block['slug'] === $slug ) {
                    wp_send_json_success( array( 'elements' => $block['elements'] ) );
                }
            }
            wp_send_json_error( array( 'message' => __( 'That block is not in the library.', 'loopkit' ) ), 404 );
        }

        if ( 'page' === $what ) {
            $blocks = array();
            foreach ( self::blocks() as $block ) {
                $blocks[ $block['slug'] ] = $block;
            }

            foreach ( self::pages() as $page ) {
                if ( $page['slug'] !== $slug ) {
                    continue;
                }
                $elements = array();
                foreach ( $page['blocks'] as $block_slug ) {
                    if ( isset( $blocks[ $block_slug ] ) ) {
                        // Fresh ids every time, so inserting twice never clashes.
                        $elements = array_merge( $elements, json_decode( wp_json_encode( $blocks[ $block_slug ]['elements'] ), true ) );
                    }
                }
                wp_send_json_success( array( 'elements' => $elements ) );
            }
            wp_send_json_error( array( 'message' => __( 'That page is not in the library.', 'loopkit' ) ), 404 );
        }

        wp_send_json_error( array( 'message' => __( 'Unknown request.', 'loopkit' ) ), 400 );
    }
}
