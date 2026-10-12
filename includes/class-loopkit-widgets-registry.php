<?php
/**
 * Loopkit Widgets Registry.
 * Manages registration, discovery, and retrieval of builder widgets.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widgets_Registry {

    /**
     * Singleton instance.
     *
     * @var Loopkit_Widgets_Registry|null
     */
    private static $instance = null;

    /**
     * Registered widget instances.
     *
     * @var array<string, Loopkit_Widget_Base>
     */
    private $widgets = array();

    /**
     * Get singleton instance.
     *
     * @return Loopkit_Widgets_Registry
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get singleton instance (alias).
     *
     * @return Loopkit_Widgets_Registry
     */
    public static function get_instance() {
        return self::instance();
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->load_builtin_widgets();
    }

    /**
     * Load builtin widget files and register them.
     */
    private function load_builtin_widgets() {
        require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-widget-base.php';

        $widget_files = array(
            'heading'      => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-heading.php',
            'text-editor'  => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-text-editor.php',
            'image'        => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-image.php',
            'video'        => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-video.php',
            'button'       => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-button.php',
            'divider'      => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-divider.php',
            'spacer'       => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-spacer.php',
            'icon'         => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-icon.php',
            'image-box'    => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-image-box.php',
            'icon-box'     => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-icon-box.php',
            'icon-list'    => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-icon-list.php',
            'social-icons' => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-social-icons.php',
            'html'         => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-html.php',
            'shortcode'    => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-shortcode.php',
            'site-logo'    => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-site-logo.php',
            'site-title'   => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-site-title.php',
            'nav-menu'     => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-nav-menu.php',
            'search-form'  => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-search-form.php',
            'post-title'          => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-post-title.php',
            'post-featured-image' => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-post-featured-image.php',
            'post-excerpt'        => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-post-excerpt.php',
            'post-info'           => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-post-info.php',
            'post-content' => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-post-content.php',
            'author-box' => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-author-box.php',
            'post-navigation' => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-post-navigation.php',
            'post-comments' => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-post-comments.php',
            'archive-title'       => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-archive-title.php',
            'archive-description' => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-archive-description.php',
            'posts'               => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-posts.php',
            'loop-grid'           => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-loop-grid.php',
            'marquee'             => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-marquee.php',
            'product-grid' => LOOPKIT_PLUGIN_DIR . 'modules/woocommerce/class-loopkit-shop-widgets.php',
            'add-to-cart' => LOOPKIT_PLUGIN_DIR . 'modules/woocommerce/class-loopkit-shop-widgets.php',
            'product-price' => LOOPKIT_PLUGIN_DIR . 'modules/woocommerce/class-loopkit-shop-widgets.php',
            'product-gallery' => LOOPKIT_PLUGIN_DIR . 'modules/woocommerce/class-loopkit-shop-widgets.php',
            'cart-icon' => LOOPKIT_PLUGIN_DIR . 'modules/woocommerce/class-loopkit-shop-widgets.php',
            'cart-table' => LOOPKIT_PLUGIN_DIR . 'modules/woocommerce/class-loopkit-shop-widgets.php',
            'checkout-form' => LOOPKIT_PLUGIN_DIR . 'modules/woocommerce/class-loopkit-shop-widgets.php',
            'product-categories' => LOOPKIT_PLUGIN_DIR . 'modules/woocommerce/class-loopkit-shop-widgets.php',
            'product-filters' => LOOPKIT_PLUGIN_DIR . 'modules/woocommerce/class-loopkit-shop-widgets2.php',
            'mini-cart' => LOOPKIT_PLUGIN_DIR . 'modules/woocommerce/class-loopkit-shop-widgets2.php',
            'product-rating' => LOOPKIT_PLUGIN_DIR . 'modules/woocommerce/class-loopkit-shop-widgets2.php',
            'my-account' => LOOPKIT_PLUGIN_DIR . 'modules/woocommerce/class-loopkit-shop-widgets2.php',
            'flip-box'            => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro.php',
            'price-table'         => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro.php',
            'countdown'           => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro.php',
            'animated-headline'   => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro.php',
            'call-to-action'      => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro.php',
            'share-buttons'       => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro.php',
            'team-member'         => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro2.php',
            'testimonial-carousel' => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro2.php',
            'blockquote'          => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro2.php',
            'business-hours'      => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro2.php',
            'hotspot'             => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro2.php',
            'portfolio'           => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro2.php',
            'login-form'          => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro2.php',
            'pagination'          => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-pagination.php',
            'interactive'         => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-interactive.php',
            'media'               => LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-media.php',
            'collection-list'     => LOOPKIT_PLUGIN_DIR . 'modules/cms/class-loopkit-cms-widgets.php',
        );

        foreach ( $widget_files as $slug => $file ) {
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }

        if ( class_exists( 'Loopkit_Widget_Heading' ) ) {
            $this->register( new Loopkit_Widget_Heading() );
        }
        if ( class_exists( 'Loopkit_Widget_Text_Editor' ) ) {
            $this->register( new Loopkit_Widget_Text_Editor() );
        }
        if ( class_exists( 'Loopkit_Widget_Image' ) ) {
            $this->register( new Loopkit_Widget_Image() );
        }
        if ( class_exists( 'Loopkit_Widget_Video' ) ) {
            $this->register( new Loopkit_Widget_Video() );
        }
        if ( class_exists( 'Loopkit_Widget_Button' ) ) {
            $this->register( new Loopkit_Widget_Button() );
        }
        if ( class_exists( 'Loopkit_Widget_Divider' ) ) {
            $this->register( new Loopkit_Widget_Divider() );
        }
        if ( class_exists( 'Loopkit_Widget_Spacer' ) ) {
            $this->register( new Loopkit_Widget_Spacer() );
        }
        if ( class_exists( 'Loopkit_Widget_Icon' ) ) {
            $this->register( new Loopkit_Widget_Icon() );
        }
        if ( class_exists( 'Loopkit_Widget_Image_Box' ) ) {
            $this->register( new Loopkit_Widget_Image_Box() );
        }
        if ( class_exists( 'Loopkit_Widget_Icon_Box' ) ) {
            $this->register( new Loopkit_Widget_Icon_Box() );
        }
        if ( class_exists( 'Loopkit_Widget_Icon_List' ) ) {
            $this->register( new Loopkit_Widget_Icon_List() );
        }
        if ( class_exists( 'Loopkit_Widget_Social_Icons' ) ) {
            $this->register( new Loopkit_Widget_Social_Icons() );
        }
        if ( class_exists( 'Loopkit_Widget_HTML' ) ) {
            $this->register( new Loopkit_Widget_HTML() );
        }
        if ( class_exists( 'Loopkit_Widget_Shortcode' ) ) {
            $this->register( new Loopkit_Widget_Shortcode() );
        }
        if ( class_exists( 'Loopkit_Widget_Site_Logo' ) ) {
            $this->register( new Loopkit_Widget_Site_Logo() );
        }
        if ( class_exists( 'Loopkit_Widget_Site_Title' ) ) {
            $this->register( new Loopkit_Widget_Site_Title() );
        }
        if ( class_exists( 'Loopkit_Widget_Nav_Menu' ) ) {
            $this->register( new Loopkit_Widget_Nav_Menu() );
        }
        if ( class_exists( 'Loopkit_Widget_Search_Form' ) ) {
            $this->register( new Loopkit_Widget_Search_Form() );
        }
        if ( class_exists( 'Loopkit_Widget_Post_Title' ) ) {
            $this->register( new Loopkit_Widget_Post_Title() );
        }
        if ( class_exists( 'Loopkit_Widget_Post_Featured_Image' ) ) {
            $this->register( new Loopkit_Widget_Post_Featured_Image() );
        }
        if ( class_exists( 'Loopkit_Widget_Post_Excerpt' ) ) {
            $this->register( new Loopkit_Widget_Post_Excerpt() );
        }
        if ( class_exists( 'Loopkit_Widget_Post_Info' ) ) {
            $this->register( new Loopkit_Widget_Post_Info() );
        }
        if ( class_exists( 'Loopkit_Widget_Post_Content' ) ) {
            $this->register( new Loopkit_Widget_Post_Content() );
        }
        if ( class_exists( 'Loopkit_Widget_Author_Box' ) ) {
            $this->register( new Loopkit_Widget_Author_Box() );
        }
        if ( class_exists( 'Loopkit_Widget_Post_Navigation' ) ) {
            $this->register( new Loopkit_Widget_Post_Navigation() );
        }
        if ( class_exists( 'Loopkit_Widget_Post_Comments' ) ) {
            $this->register( new Loopkit_Widget_Post_Comments() );
        }
        if ( class_exists( 'Loopkit_Widget_Archive_Title' ) ) {
            $this->register( new Loopkit_Widget_Archive_Title() );
        }
        if ( class_exists( 'Loopkit_Widget_Archive_Description' ) ) {
            $this->register( new Loopkit_Widget_Archive_Description() );
        }
        if ( class_exists( 'Loopkit_Widget_Posts' ) ) {
            $this->register( new Loopkit_Widget_Posts() );
        }
        if ( class_exists( 'Loopkit_Widget_Loop_Grid' ) ) {
            $this->register( new Loopkit_Widget_Loop_Grid() );
        }

        // Shop widgets, only when the Shop module is on
        if ( class_exists( 'Loopkit_Modules' ) && Loopkit_Modules::is_enabled( 'woocommerce' ) && class_exists( 'Loopkit_Module_WooCommerce' ) ) {
            require_once LOOPKIT_PLUGIN_DIR . 'modules/woocommerce/class-loopkit-shop-widgets.php';
            foreach ( array( 'Product_Grid', 'Add_To_Cart', 'Product_Price', 'Product_Gallery', 'Cart_Icon', 'Cart_Table', 'Checkout_Form', 'Product_Categories', 'Product_Filters', 'Mini_Cart', 'Product_Rating', 'My_Account' ) as $shop_widget ) {
                $class = 'Loopkit_Widget_' . $shop_widget;
                if ( class_exists( $class ) ) {
                    $this->register( new $class() );
                }
            }
        }

        require_once LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widget-marquee.php';
        if ( class_exists( 'Loopkit_Widget_Marquee' ) ) {
            $this->register( new Loopkit_Widget_Marquee() );
        }

        foreach ( array(
            'Loopkit_Widget_Team_Member',
            'Loopkit_Widget_Testimonial_Carousel',
            'Loopkit_Widget_Blockquote',
            'Loopkit_Widget_Business_Hours',
            'Loopkit_Widget_Hotspot',
            'Loopkit_Widget_Portfolio',
            'Loopkit_Widget_Login',
        ) as $lk_class ) {
            if ( class_exists( $lk_class ) ) {
                $this->register( new $lk_class() );
            }
        }

        // Pro-style widgets
        require_once LOOPKIT_PLUGIN_DIR . 'widgets/class-loopkit-widgets-pro.php';
        foreach ( array( 'Flip_Box', 'Price_Table', 'Countdown', 'Animated_Headline', 'Call_To_Action', 'Share_Buttons' ) as $pro ) {
            $class = 'Loopkit_Widget_' . $pro;
            if ( class_exists( $class ) ) {
                $this->register( new $class() );
            }
        }
        if ( class_exists( 'Loopkit_Widget_Pagination' ) ) {
            $this->register( new Loopkit_Widget_Pagination() );
        }
        // Part 9 interactive widgets
        if ( class_exists( 'Loopkit_Widget_Accordion' ) ) {
            $this->register( new Loopkit_Widget_Accordion() );
        }
        if ( class_exists( 'Loopkit_Widget_Toggle' ) ) {
            $this->register( new Loopkit_Widget_Toggle() );
        }
        if ( class_exists( 'Loopkit_Widget_Tabs' ) ) {
            $this->register( new Loopkit_Widget_Tabs() );
        }
        if ( class_exists( 'Loopkit_Widget_Counter' ) ) {
            $this->register( new Loopkit_Widget_Counter() );
        }
        if ( class_exists( 'Loopkit_Widget_Progress_Bar' ) ) {
            $this->register( new Loopkit_Widget_Progress_Bar() );
        }
        if ( class_exists( 'Loopkit_Widget_Alert' ) ) {
            $this->register( new Loopkit_Widget_Alert() );
        }
        if ( class_exists( 'Loopkit_Widget_Star_Rating' ) ) {
            $this->register( new Loopkit_Widget_Star_Rating() );
        }
        if ( class_exists( 'Loopkit_Widget_Testimonial' ) ) {
            $this->register( new Loopkit_Widget_Testimonial() );
        }
        // Part 10 widgets
        if ( class_exists( 'Loopkit_Widget_Image_Carousel' ) ) {
            $this->register( new Loopkit_Widget_Image_Carousel() );
        }
        if ( class_exists( 'Loopkit_Widget_Image_Gallery' ) ) {
            $this->register( new Loopkit_Widget_Image_Gallery() );
        }
        if ( class_exists( 'Loopkit_Widget_Google_Maps' ) ) {
            $this->register( new Loopkit_Widget_Google_Maps() );
        }
        if ( class_exists( 'Loopkit_Widget_Contact_Form' ) ) {
            $this->register( new Loopkit_Widget_Contact_Form() );
        }
        if ( class_exists( 'Loopkit_Widget_Tooltip' ) ) {
            $this->register( new Loopkit_Widget_Tooltip() );
        }
        if ( class_exists( 'Loopkit_Widget_Table_Of_Contents' ) ) {
            $this->register( new Loopkit_Widget_Table_Of_Contents() );
        }

        // CMS: only when the module is on, so the widget never appears
        // in the panel for a site that has no collections to show.
        if ( class_exists( 'Loopkit_Widget_Collection_List' )
            && class_exists( 'Loopkit_Modules' ) && Loopkit_Modules::is_enabled( 'cms' ) ) {
            $this->register( new Loopkit_Widget_Collection_List() );
        }

        /**
         * Action for add-ons or future widgets to register.
         */
        do_action( 'loopkit_widgets_registered', $this );
    }

    /**
     * Register a widget.
     *
     * @param Loopkit_Widget_Base $widget Widget instance.
     */
    public function register( Loopkit_Widget_Base $widget ) {
        $this->widgets[ $widget->get_name() ] = $widget;
    }

    /**
     * Get a registered widget by slug.
     *
     * @param string $name Widget slug.
     * @return Loopkit_Widget_Base|null
     */
    public function get_widget( $name ) {
        return $this->widgets[ $name ] ?? null;
    }

    /**
     * Get all registered widgets.
     *
     * @return array<string, Loopkit_Widget_Base>
     */
    public function get_registered_widgets() {
        return $this->widgets;
    }

    /**
     * Alias for get_registered_widgets.
     *
     * @return array<string, Loopkit_Widget_Base>
     */
    public function get_widgets() {
        return $this->widgets;
    }

    /**
     * Get widget metadata and control configurations for editor JSON.
     *
     * @param string $context Current editor context (e.g. 'all', 'post', 'header', 'footer').
     * @return array
     */
    public function get_widgets_for_editor( $context = 'all' ) {
        $output = array();

        foreach ( $this->widgets as $name => $widget ) {
            $widget_context = $widget->get_context();
            if ( 'all' !== $widget_context && 'all' !== $context && $widget_context !== $context ) {
                continue;
            }

            $output[ $name ] = array(
                'name'             => $widget->get_name(),
                'title'            => $widget->get_title(),
                'icon'             => $widget->get_icon(),
                'category'         => $widget->get_category(),
                'keywords'         => $widget->get_keywords(),
                'context'          => $widget->get_context(),
                'default_settings' => $widget->get_default_settings(),
                'controls'         => $widget->get_controls_config(),
            );
        }

        return $output;
    }
}
