<?php
/**
 * Loopkit Modules.
 *
 * Optional built-in features that stay dormant until someone turns them on in
 * Loopkit > Tools > Modules. A module that is off loads no code at all — its files simply sit
 * in the plugin — so sites that do not need it pay nothing for it.
 *
 * Current modules:
 *   acf          Custom Fields — Loopkit's own field groups (not the ACF plugin)
 *   cpt          Post Types — Loopkit's own custom post types and taxonomies
 *   woocommerce  Shop — Loopkit's own store features
 *   cms          CMS — Loopkit's own content system (collections, fields, items)
 *
 * Every enabled module can add Dynamic Tags through the `loopkit_dynamic_tags` filter, so the
 * tag menu in the editor grows as modules are switched on.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Modules {

    const OPTION = 'loopkit_modules';

    /**
     * Loaded module objects, keyed by id.
     *
     * @var array
     */
    private static $loaded = array();

    /**
     * Everything Loopkit ships as a module.
     *
     * @return array
     */
    public static function get_modules() {
        return array(
            'acf' => array(
                'name'        => __( 'Custom Fields', 'loopkit' ),
                'short'       => __( 'ACF', 'loopkit' ),
                'description' => __( "Loopkit's own custom fields: field groups, 20+ field types, repeaters and conditional logic, attached to any post type, taxonomy, user or options page. Independent of the ACF plugin — if ACF is installed, both can run side by side.", 'loopkit' ),
                'icon'        => 'dashicons-forms',
                'file'        => 'modules/acf/class-loopkit-module-acf.php',
                'class'       => 'Loopkit_Module_ACF',
            ),
            'cpt' => array(
                'name'        => __( 'Post Types', 'loopkit' ),
                'short'       => __( 'CPT', 'loopkit' ),
                'description' => __( "Loopkit's own custom post types and taxonomies: labels, icons, archives, rewrite rules, REST support and admin columns, all from one screen — no code and no separate plugin.", 'loopkit' ),
                'icon'        => 'dashicons-screenoptions',
                'file'        => 'modules/cpt/class-loopkit-module-cpt.php',
                'class'       => 'Loopkit_Module_CPT',
            ),
            'woocommerce' => array(
                'name'        => __( 'Shop', 'loopkit' ),
                'short'       => __( 'WooCommerce', 'loopkit' ),
                'description' => __( "Loopkit's own store: products, cart, checkout and order screens, plus product widgets and templates for the builder. Runs on its own — it does not require the WooCommerce plugin.", 'loopkit' ),
                'icon'        => 'dashicons-cart',
                'file'        => 'modules/woocommerce/class-loopkit-module-woocommerce.php',
                'class'       => 'Loopkit_Module_WooCommerce',
            ),
            'cms' => array(
                'name'        => __( 'CMS', 'loopkit' ),
                'short'       => __( 'CMS', 'loopkit' ),
                'description' => __( "Loopkit's own content system: collections, fields and items in a spreadsheet view, with dynamic tags and a Collection List widget for the builder. No post types, no taxonomies, no meta — its own tables.", 'loopkit' ),
                'icon'        => 'dashicons-database',
                'file'        => 'modules/cms/class-loopkit-module-cms.php',
                'class'       => 'Loopkit_Module_CMS',
            ),
        );
    }

    /**
     * Saved on/off state for every module.
     *
     * @return array id => bool
     */
    public static function get_states() {
        $saved = get_option( self::OPTION, array() );
        $saved = is_array( $saved ) ? $saved : array();
        $out   = array();
        foreach ( array_keys( self::get_modules() ) as $id ) {
            $out[ $id ] = ! empty( $saved[ $id ] );
        }
        return $out;
    }

    /**
     * Is this module on?
     *
     * @param string $id Module id.
     * @return bool
     */
    public static function is_enabled( $id ) {
        $states = self::get_states();
        return ! empty( $states[ $id ] );
    }

    /**
     * Turn a module on or off.
     *
     * @param string $id      Module id.
     * @param bool   $enabled State.
     * @return bool Whether the module exists.
     */
    public static function set_enabled( $id, $enabled ) {
        $modules = self::get_modules();
        if ( ! isset( $modules[ $id ] ) ) {
            return false;
        }

        $states        = self::get_states();
        $states[ $id ] = (bool) $enabled;
        update_option( self::OPTION, $states );

        /**
         * A module was switched on or off.
         *
         * @param string $id      Module id.
         * @param bool   $enabled New state.
         */
        do_action( 'loopkit_module_toggled', $id, (bool) $enabled );

        // Post types and fields change rewrite rules, so flush them next load.
        update_option( 'loopkit_flush_rewrite', 1 );

        return true;
    }

    /**
     * Load and start every enabled module.
     */
    public static function init() {
        foreach ( self::get_modules() as $id => $module ) {
            if ( ! self::is_enabled( $id ) ) {
                continue;
            }
            $path = LOOPKIT_PLUGIN_DIR . $module['file'];
            if ( ! file_exists( $path ) ) {
                continue;
            }
            require_once $path;
            if ( ! class_exists( $module['class'] ) ) {
                continue;
            }
            $instance = new $module['class']();
            if ( method_exists( $instance, 'init' ) ) {
                $instance->init();
            }
            self::$loaded[ $id ] = $instance;
        }

        add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite' ), 99 );
    }

    /**
     * One flush after a module is toggled.
     */
    public static function maybe_flush_rewrite() {
        if ( get_option( 'loopkit_flush_rewrite' ) ) {
            delete_option( 'loopkit_flush_rewrite' );
            flush_rewrite_rules( false );
        }
    }

    /**
     * A loaded module instance, or null.
     *
     * @param string $id Module id.
     * @return object|null
     */
    public static function get( $id ) {
        return self::$loaded[ $id ] ?? null;
    }
}
