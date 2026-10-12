<?php
/**
 * Plugin Name:       Loopkit
 * Plugin URI:        https://loopkit.com
 * Description:       A lightweight, modern, performant website builder for WordPress.
 * Version:           2.1.4
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Loopkit Team
 * Author URI:        https://loopkit.com
 * Text Domain:       loopkit
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LOOPKIT_VERSION', '2.1.4' );
define( 'LOOPKIT_PLUGIN_FILE', __FILE__ );
define( 'LOOPKIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LOOPKIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LOOPKIT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Activation hook.
 */
function loopkit_activate() {
    // Submissions table (also created lazily on the first submission).
    if ( class_exists( 'Loopkit_Submissions' ) ) {
        Loopkit_Submissions::install();
    }
    // Set default options on first activation
    if ( false === get_option( 'loopkit_general_settings' ) ) {
        update_option( 'loopkit_general_settings', array(
            'post_types'             => array( 'page', 'post' ),
            'disable_default_colors' => '',
            'disable_default_fonts'  => '',
        ) );
    }

    if ( false === get_option( 'loopkit_advanced_settings' ) ) {
        update_option( 'loopkit_advanced_settings', array(
            'css_print_method'    => 'external',
            'load_font_awesome'   => 'yes',
            'editor_loader_style' => 'default',
            'optimize_assets'     => 'yes',
        ) );
    }

    if ( false === get_option( 'loopkit_site_settings' ) ) {
        update_option( 'loopkit_site_settings', array(
            'color_primary'           => '#38A8F5',
            'color_secondary'         => '#54595F',
            'color_text'              => '#333333',
            'color_accent'            => '#38A8F5',
            'body_font_size'          => '16',
            'body_font_weight'        => '400',
            'body_line_height'        => '1.6',
            'body_letter_spacing'     => '0',
            'h1_font_size'            => '40',
            'h1_font_weight'          => '600',
            'h1_line_height'          => '1.2',
            'h1_letter_spacing'       => '0',
            'h2_font_size'            => '32',
            'h2_font_weight'          => '600',
            'h2_line_height'          => '1.3',
            'h2_letter_spacing'       => '0',
            'h3_font_size'            => '28',
            'h3_font_weight'          => '600',
            'h3_line_height'          => '1.3',
            'h3_letter_spacing'       => '0',
            'h4_font_size'            => '24',
            'h4_font_weight'          => '600',
            'h4_line_height'          => '1.4',
            'h4_letter_spacing'       => '0',
            'h5_font_size'            => '20',
            'h5_font_weight'          => '600',
            'h5_line_height'          => '1.4',
            'h5_letter_spacing'       => '0',
            'h6_font_size'            => '16',
            'h6_font_weight'          => '600',
            'h6_line_height'          => '1.5',
            'h6_letter_spacing'       => '0',
            'content_width'           => '1140',
            'container_padding'       => '10',
            'page_background_color'   => '#FFFFFF',
        ) );
    }

    if ( false === get_option( 'loopkit_maintenance_settings' ) ) {
        update_option( 'loopkit_maintenance_settings', array(
            'mode'        => 'disabled',
            'template_id' => '',
            'who_access'  => 'logged_in',
        ) );
    }

    // Register CPT so rewrite rules can be flushed
    loopkit_register_template_cpt();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'loopkit_activate' );

/**
 * Deactivation hook.
 */
function loopkit_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'loopkit_deactivate' );

// Include files
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-assets.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-settings.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-site-settings.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-templates.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-library.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-tools.php';
require_once LOOPKIT_PLUGIN_DIR . 'editor/mascot/class-loopkit-mascot.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-rest-api.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-frontend.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-theme-builder.php';
require_once LOOPKIT_PLUGIN_DIR . 'editor/class-loopkit-editor.php';
require_once LOOPKIT_PLUGIN_DIR . 'admin/class-loopkit-dashboard.php';
require_once LOOPKIT_PLUGIN_DIR . 'admin/class-loopkit-admin.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-icons.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-images.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-widget-base.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-post-data.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-archive-data.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-dynamic.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-fonts.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-popups.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-submissions.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-automations.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-modules.php';
if ( is_admin() ) {
    require_once LOOPKIT_PLUGIN_DIR . 'admin/class-loopkit-admin-automations.php';
}
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-widgets-registry.php';
require_once LOOPKIT_PLUGIN_DIR . 'includes/class-loopkit-editor-integration.php';

/**
 * Initialize Loopkit Theme Builder, Icons, and Widgets Registry.
 */
function loopkit_init_theme_builder() {
    Loopkit_Theme_Builder::get_instance();
    Loopkit_Popups::init();
    Loopkit_Submissions::init();
    Loopkit_Automations::init();
    if ( is_admin() && class_exists( 'Loopkit_Admin_Automations' ) ) {
        Loopkit_Admin_Automations::init();
    }
    Loopkit_Icons::init();
    Loopkit_Library::init();
    Loopkit_Mascot::init();
    Loopkit_Widgets_Registry::instance();
}
add_action( 'init', 'loopkit_init_theme_builder', 5 );

/**
 * Load the translations.
 *
 * The plugin header has named a text domain and a /languages path since the
 * beginning, and 4,139 strings are wrapped for translation — but nothing ever
 * loaded a translation file, so none of that did anything. A site in Urdu or
 * Turkish got English.
 *
 * On `init`, not earlier: since WordPress 6.7 loading a text domain before
 * `init` raises a notice, because the locale is not settled until then.
 */
function loopkit_load_textdomain() {
	load_plugin_textdomain( 'loopkit', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'loopkit_load_textdomain', 1 );

/**
 * Optional modules (Custom Fields, Post Types, Shop) start before `init` so the hooks they
 * register — post types, taxonomies, admin menus — run at the usual time.
 */
function loopkit_init_modules() {
    if ( class_exists( 'Loopkit_Modules' ) ) {
        Loopkit_Modules::init();
    }
}
add_action( 'plugins_loaded', 'loopkit_init_modules', 5 );

/**
 * Register the loopkit_template custom post type.
 */
function loopkit_register_template_cpt() {
    $labels = array(
        'name'               => esc_html__( 'Templates', 'loopkit' ),
        'singular_name'      => esc_html__( 'Template', 'loopkit' ),
        'add_new'            => esc_html__( 'Add New', 'loopkit' ),
        'add_new_item'       => esc_html__( 'Add New Template', 'loopkit' ),
        'edit_item'          => esc_html__( 'Edit Template', 'loopkit' ),
        'new_item'           => esc_html__( 'New Template', 'loopkit' ),
        'all_items'          => esc_html__( 'All Templates', 'loopkit' ),
        'view_item'          => esc_html__( 'View Template', 'loopkit' ),
        'search_items'       => esc_html__( 'Search Templates', 'loopkit' ),
        'not_found'          => esc_html__( 'No templates found.', 'loopkit' ),
        'not_found_in_trash' => esc_html__( 'No templates found in Trash.', 'loopkit' ),
        'menu_name'          => esc_html__( 'Templates', 'loopkit' ),
    );

    $args = array(
        'labels'              => $labels,
        'public'              => false,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_menu'        => false,
        'show_in_admin_bar'   => false,
        'show_in_rest'        => true,
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'supports'            => array( 'title', 'editor', 'author', 'revisions' ),
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => true,
    );

    register_post_type( 'loopkit_template', $args );
}
add_action( 'init', 'loopkit_register_template_cpt' );

/**
 * One-time migration: templates titled "Header" with no type become header templates.
 * Runs once per site (tracked by the loopkit_templates_migrated option) instead of on every request.
 */
function loopkit_migrate_templates() {
    if ( get_option( 'loopkit_templates_migrated' ) ) {
        return;
    }

    global $wpdb;
    $headers = $wpdb->get_results( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s",
        'loopkit_template',
        'Header'
    ) );
    if ( ! empty( $headers ) ) {
        foreach ( $headers as $h ) {
            $t = get_post_meta( $h->ID, '_loopkit_template_type', true );
            if ( empty( $t ) || 'page' === $t ) {
                update_post_meta( $h->ID, '_loopkit_template_type', 'header' );
            }
        }
        if ( class_exists( 'Loopkit_Theme_Builder' ) ) {
            Loopkit_Theme_Builder::rebuild_cache();
        }
    }

    update_option( 'loopkit_templates_migrated', 1, false );
}
add_action( 'admin_init', 'loopkit_migrate_templates' );

/**
 * Output site settings as CSS variables on front end.
 */
function loopkit_output_site_settings_css() {
    $settings = get_option( 'loopkit_site_settings', array() );
    if ( empty( $settings ) ) {
        return;
    }

    $css = ':root {' . "\n";
    $css .= '  --lk-color-primary: '     . esc_attr( $settings['color_primary'] ?? '#38A8F5' )   . ';' . "\n";
    $css .= '  --lk-color-secondary: '   . esc_attr( $settings['color_secondary'] ?? '#54595F' ) . ';' . "\n";
    $css .= '  --lk-color-text: '        . esc_attr( $settings['color_text'] ?? '#333333' )      . ';' . "\n";
    $css .= '  --lk-color-accent: '      . esc_attr( $settings['color_accent'] ?? '#38A8F5' )    . ';' . "\n";
    $css .= '  --lk-body-font-size: '    . intval( $settings['body_font_size'] ?? 16 )           . 'px;' . "\n";
    $css .= '  --lk-body-font-weight: '  . esc_attr( $settings['body_font_weight'] ?? '400' )    . ';' . "\n";
    $css .= '  --lk-body-line-height: '  . esc_attr( $settings['body_line_height'] ?? '1.6' )    . ';' . "\n";
    $css .= '  --lk-body-letter-spacing: ' . esc_attr( $settings['body_letter_spacing'] ?? '0' ) . 'px;' . "\n";

    for ( $i = 1; $i <= 6; $i++ ) {
        $css .= '  --lk-h' . $i . '-font-size: '      . intval( $settings[ 'h' . $i . '_font_size' ] ?? 16 )          . 'px;' . "\n";
        $css .= '  --lk-h' . $i . '-font-weight: '     . esc_attr( $settings[ 'h' . $i . '_font_weight' ] ?? '600' )   . ';' . "\n";
        $css .= '  --lk-h' . $i . '-line-height: '     . esc_attr( $settings[ 'h' . $i . '_line_height' ] ?? '1.3' )   . ';' . "\n";
        $css .= '  --lk-h' . $i . '-letter-spacing: '  . esc_attr( $settings[ 'h' . $i . '_letter_spacing' ] ?? '0' )  . 'px;' . "\n";
    }

    $css .= '  --lk-content-width: '        . intval( $settings['content_width'] ?? 1140 )                     . 'px;' . "\n";
    $css .= '  --lk-container-padding: '     . intval( $settings['container_padding'] ?? 10 )                   . 'px;' . "\n";
    $css .= '  --lk-page-background-color: ' . esc_attr( $settings['page_background_color'] ?? '#FFFFFF' )      . ';' . "\n";
    $css .= '}' . "\n";

    echo '<style id="loopkit-site-settings-css">' . "\n" . $css . '</style>' . "\n";
}
add_action( 'wp_head', 'loopkit_output_site_settings_css' );
