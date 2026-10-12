<?php
/**
 * Loopkit Admin class.
 * Registers admin menus and renders all dashboard pages.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Admin {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        // Tools > Update Loopkit: install an uploaded plugin/theme zip over the current one
        add_action( 'admin_init', array( $this, 'handle_upload_update' ) );
        add_action( 'admin_init', array( $this, 'handle_module_toggle' ) );
        add_action( 'admin_init', array( $this, 'handle_template_transfer' ) );
        add_action( 'admin_init', array( $this, 'handle_remove_all_data' ) );
        add_action( 'admin_notices', array( $this, 'upload_update_notice' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register admin menus.
     */
    public function register_menus() {
        // Top-level menu. The slug stays 'loopkit' because the builder's own
        // "back to dashboard" link points at admin.php?page=loopkit, and that URL
        // now lands on the Dashboard. The old Home screen has been folded into it
        // (its quick actions and its richer Recently Edited table), so there is one
        // landing screen rather than two that overlap.
        add_menu_page(
            esc_html__( 'Loopkit', 'loopkit' ),
            esc_html__( 'Loopkit', 'loopkit' ),
            'edit_posts',
            'loopkit',
            array( 'Loopkit_Dashboard', 'render' ),
            'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#38A8F5"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>' ),
            58
        );

        add_submenu_page(
            'loopkit',
            esc_html__( 'Settings', 'loopkit' ),
            esc_html__( 'Settings', 'loopkit' ),
            'manage_options',
            'loopkit-settings',
            array( $this, 'render_settings' )
        );

        add_submenu_page(
            'loopkit',
            esc_html__( 'Site Settings', 'loopkit' ),
            esc_html__( 'Site Settings', 'loopkit' ),
            'manage_options',
            'loopkit-site-settings',
            array( $this, 'render_site_settings' )
        );

        add_submenu_page(
            'loopkit',
            esc_html__( 'Templates', 'loopkit' ),
            esc_html__( 'Templates', 'loopkit' ),
            'edit_posts',
            'loopkit-templates',
            array( $this, 'render_templates' )
        );

        add_submenu_page(
            'loopkit',
            esc_html__( 'Tools', 'loopkit' ),
            esc_html__( 'Tools', 'loopkit' ),
            'manage_options',
            'loopkit-tools',
            array( $this, 'render_tools' )
        );

        add_submenu_page(
            'loopkit',
            esc_html__( 'System Info', 'loopkit' ),
            esc_html__( 'System Info', 'loopkit' ),
            'manage_options',
            'loopkit-system-info',
            array( $this, 'render_system_info' )
        );
    }

    /**
     * Check if current page is a Loopkit admin page.
     *
     * @return bool
     */
    private function is_loopkit_page() {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return false;
        }
        return strpos( $screen->id, 'loopkit' ) !== false;
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( ! $this->is_loopkit_page() ) {
            return;
        }

        wp_enqueue_style(
            'loopkit-admin',
            LOOPKIT_PLUGIN_URL . 'admin/css/loopkit-admin.css',
            array(),
            LOOPKIT_VERSION
        );

        wp_enqueue_script(
            'loopkit-admin',
            LOOPKIT_PLUGIN_URL . 'admin/js/loopkit-admin.js',
            array( 'jquery' ),
            LOOPKIT_VERSION,
            true
        );

        wp_localize_script( 'loopkit-admin', 'loopkitAdmin', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'toolsNonce'     => wp_create_nonce( 'loopkit_tools_nonce' ),
            'templatesNonce' => wp_create_nonce( 'loopkit_templates_nonce' ),
            'strings'        => array(
                'confirmClear'     => esc_html__( 'Are you sure you want to clear all Loopkit CSS cache files?', 'loopkit' ),
                'confirmDelete'    => esc_html__( 'Are you sure you want to delete this template?', 'loopkit' ),
                'copied'           => esc_html__( 'Copied to clipboard!', 'loopkit' ),
                'enterTitle'       => esc_html__( 'Enter template title:', 'loopkit' ),
                'cacheCleared'     => esc_html__( 'Cache cleared successfully.', 'loopkit' ),
                'urlReplaced'      => esc_html__( 'URL replacement complete.', 'loopkit' ),
                'error'            => esc_html__( 'An error occurred. Please try again.', 'loopkit' ),
            ),
        ) );

        // WordPress color picker for site settings
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        // Dashboard screen only: its own stylesheet and counter script. The dashboard is
        // the top-level Loopkit screen, so its hook suffix is toplevel_page_loopkit.
        // Form controls (checkbox, radio, select, file, colour picker) across EVERY
        // Loopkit admin screen, not one. Presentation only: no labels, behaviour
        // or values are changed.
        wp_enqueue_style(
            'loopkit-forms',
            LOOPKIT_PLUGIN_URL . 'admin/css/loopkit-forms.css',
            array( 'loopkit-admin' ),
            LOOPKIT_VERSION
        );
        wp_enqueue_script(
            'loopkit-forms',
            LOOPKIT_PLUGIN_URL . 'admin/js/loopkit-forms.js',
            array(),
            LOOPKIT_VERSION,
            true
        );

        if ( 'toplevel_page_loopkit' === $hook_suffix ) {
            wp_enqueue_style(
                'loopkit-dashboard',
                LOOPKIT_PLUGIN_URL . 'admin/css/loopkit-dashboard.css',
                array( 'loopkit-admin' ),
                LOOPKIT_VERSION
            );
            wp_enqueue_script(
                'loopkit-dashboard',
                LOOPKIT_PLUGIN_URL . 'admin/js/loopkit-dashboard.js',
                array(),
                LOOPKIT_VERSION,
                true
            );
        }
    }

    /**
     * Render page header with navigation.
     *
     * @param string $title     Page title.
     * @param string $active    Active page slug.
     */
    private function render_header( $title, $active = '' ) {
        ?>
        <div class="lk-admin-wrap">
            <div class="lk-admin-header">
                <div class="lk-admin-header-inner">
                    <div class="lk-admin-logo">
                        <span class="dashicons dashicons-admin-generic lk-logo-icon"></span>
                        <span class="lk-logo-text"><?php esc_html_e( 'Loopkit', 'loopkit' ); ?></span>
                    </div>
                    <nav class="lk-admin-nav">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit' ) ); ?>"
                           class="lk-nav-item <?php echo $active === 'home' ? 'lk-nav-active' : ''; ?>">
                            <?php esc_html_e( 'Home', 'loopkit' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-settings' ) ); ?>"
                           class="lk-nav-item <?php echo $active === 'settings' ? 'lk-nav-active' : ''; ?>">
                            <?php esc_html_e( 'Settings', 'loopkit' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-site-settings' ) ); ?>"
                           class="lk-nav-item <?php echo $active === 'site-settings' ? 'lk-nav-active' : ''; ?>">
                            <?php esc_html_e( 'Site Settings', 'loopkit' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-templates' ) ); ?>"
                           class="lk-nav-item <?php echo $active === 'templates' ? 'lk-nav-active' : ''; ?>">
                            <?php esc_html_e( 'Templates', 'loopkit' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-tools' ) ); ?>"
                           class="lk-nav-item <?php echo $active === 'tools' ? 'lk-nav-active' : ''; ?>">
                            <?php esc_html_e( 'Tools', 'loopkit' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-system-info' ) ); ?>"
                           class="lk-nav-item <?php echo $active === 'system-info' ? 'lk-nav-active' : ''; ?>">
                            <?php esc_html_e( 'System Info', 'loopkit' ); ?>
                        </a>
                    </nav>
                </div>
            </div>
            <div class="lk-admin-content">
        <?php
    }

    /**
     * Render page footer.
     */
    private function render_footer() {
        ?>
            </div><!-- .lk-admin-content -->
        </div><!-- .lk-admin-wrap -->
        <?php
    }

    /**
     * Render the Home page.
     */
        /**
     * Render the Settings page (tabbed: General, Advanced).
     */
    public function render_settings() {
        // Handle save
        if ( isset( $_POST['loopkit_settings_submit'] ) ) {
            check_admin_referer( 'loopkit_settings_save', 'loopkit_settings_nonce' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'loopkit' ) );
            }

            $active_tab = sanitize_key( $_POST['active_tab'] ?? 'general' );

            if ( 'general' === $active_tab ) {
                $general = array(
                    'post_types'             => array(),
                    'disable_default_colors' => '',
                    'disable_default_fonts'  => '',
                );

                if ( ! empty( $_POST['loopkit_post_types'] ) && is_array( $_POST['loopkit_post_types'] ) ) {
                    foreach ( $_POST['loopkit_post_types'] as $pt ) {
                        $general['post_types'][] = sanitize_key( $pt );
                    }
                }

                $general['disable_default_colors'] = ! empty( $_POST['loopkit_disable_default_colors'] ) ? '1' : '';
                $general['disable_default_fonts']  = ! empty( $_POST['loopkit_disable_default_fonts'] ) ? '1' : '';

                update_option( 'loopkit_general_settings', $general );

                // The mascot is its own option, not part of the general blob,
                // because the front-end code reads it on every admin page and
                // should not have to unpack an array to do it.
                // The mascot saves itself on admin_init — see Loopkit_Mascot.
            }

            if ( 'advanced' === $active_tab ) {
                $advanced = array();

                $allowed_methods = array( 'external', 'internal' );
                $advanced['css_print_method'] = in_array( sanitize_key( $_POST['loopkit_css_print_method'] ?? '' ), $allowed_methods, true )
                    ? sanitize_key( $_POST['loopkit_css_print_method'] )
                    : 'external';

                $advanced['load_font_awesome'] = in_array( sanitize_key( $_POST['loopkit_load_font_awesome'] ?? '' ), array( 'yes', 'no' ), true )
                    ? sanitize_key( $_POST['loopkit_load_font_awesome'] )
                    : 'yes';

                $allowed_loaders = array( 'default', 'minimal', 'none' );
                $advanced['editor_loader_style'] = in_array( sanitize_key( $_POST['loopkit_editor_loader_style'] ?? '' ), $allowed_loaders, true )
                    ? sanitize_key( $_POST['loopkit_editor_loader_style'] )
                    : 'default';

                $advanced['optimize_assets'] = in_array( sanitize_key( $_POST['loopkit_optimize_assets'] ?? '' ), array( 'yes', 'no' ), true )
                    ? sanitize_key( $_POST['loopkit_optimize_assets'] )
                    : 'yes';

                $advanced['remove_data_on_uninstall'] = ! empty( $_POST['loopkit_remove_data_on_uninstall'] ) ? '1' : '';

                update_option( 'loopkit_advanced_settings', $advanced );
            }

            echo '<div class="lk-notice lk-notice-success"><p>' . esc_html__( 'Settings saved successfully.', 'loopkit' ) . '</p></div>';
        }

        $this->render_header( esc_html__( 'Settings', 'loopkit' ), 'settings' );

        $tab = sanitize_key( $_GET['tab'] ?? 'general' );
        $general  = Loopkit_Settings::get_general();
        $advanced = Loopkit_Settings::get_advanced();

        // Get available post types
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        unset( $post_types['attachment'] );
        ?>

        <div class="lk-tabs-wrap">
            <div class="lk-tabs-nav">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-settings&tab=general' ) ); ?>"
                   class="lk-tab <?php echo $tab === 'general' ? 'lk-tab-active' : ''; ?>">
                    <?php esc_html_e( 'General', 'loopkit' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-settings&tab=advanced' ) ); ?>"
                   class="lk-tab <?php echo $tab === 'advanced' ? 'lk-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Advanced', 'loopkit' ); ?>
                </a>
            </div>

            <?php if ( 'general' === $tab ) : ?>
            <div class="lk-tab-content">
                <form method="post" action="">
                    <?php wp_nonce_field( 'loopkit_settings_save', 'loopkit_settings_nonce' ); ?>
                    <input type="hidden" name="active_tab" value="general" />
                    <input type="hidden" name="loopkit_settings_submit" value="1" />

                    <div class="lk-card">
                        <div class="lk-card-header">
                            <h3><?php esc_html_e( 'Post Types', 'loopkit' ); ?></h3>
                            <p class="lk-card-desc"><?php esc_html_e( 'Select the post types where Loopkit editor is enabled.', 'loopkit' ); ?></p>
                        </div>
                        <div class="lk-card-body">
                            <?php foreach ( $post_types as $pt_slug => $pt_obj ) : ?>
                                <label class="lk-checkbox-row">
                                    <input type="checkbox" name="loopkit_post_types[]"
                                           value="<?php echo esc_attr( $pt_slug ); ?>"
                                           <?php checked( in_array( $pt_slug, $general['post_types'] ?? array(), true ) ); ?> />
                                    <span class="lk-checkbox-label"><?php echo esc_html( $pt_obj->labels->name ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="lk-card">
                        <div class="lk-card-header">
                            <h3><?php esc_html_e( 'Defaults', 'loopkit' ); ?></h3>
                        </div>
                        <div class="lk-card-body">
                            <label class="lk-toggle-row">
                                <span class="lk-toggle-wrap">
                                    <input type="checkbox" name="loopkit_disable_default_colors" value="1"
                                           <?php checked( $general['disable_default_colors'], '1' ); ?> />
                                    <span class="lk-toggle-slider"></span>
                                </span>
                                <span class="lk-toggle-label"><?php esc_html_e( 'Disable Default Colours', 'loopkit' ); ?></span>
                            </label>
                            <label class="lk-toggle-row">
                                <span class="lk-toggle-wrap">
                                    <input type="checkbox" name="loopkit_disable_default_fonts" value="1"
                                           <?php checked( $general['disable_default_fonts'], '1' ); ?> />
                                    <span class="lk-toggle-slider"></span>
                                </span>
                                <span class="lk-toggle-label"><?php esc_html_e( 'Disable Default Fonts', 'loopkit' ); ?></span>
                            </label>
                        </div>
                    </div>

                    <div class="lk-card">
                        <div class="lk-card-header">
                            <h3><?php esc_html_e( 'Mascot', 'loopkit' ); ?></h3>
                            <p class="lk-card-desc"><?php esc_html_e( 'A small character in the corner of the builder and the Loopkit screens. It reacts to what you do and explains a widget the first time you hover it.', 'loopkit' ); ?></p>
                        </div>
                        <div class="lk-card-body">
                            <label class="lk-toggle-row">
                                <span class="lk-toggle-wrap">
                                    <input type="checkbox" name="loopkit_mascot" value="1"
                                           <?php checked( get_option( 'loopkit_mascot', '1' ), '1' ); ?> />
                                    <span class="lk-toggle-slider"></span>
                                </span>
                                <span class="lk-toggle-label"><?php esc_html_e( 'Show the mascot', 'loopkit' ); ?></span>
                            </label>
                            <p class="lk-field-help" style="margin-top:10px">
                                <?php esc_html_e( 'Turning this on also clears a dismissal made from the mascot itself, which is remembered per browser rather than per site.', 'loopkit' ); ?>
                            </p>
                        </div>
                    </div>

                    <button type="submit" class="lk-btn lk-btn-primary lk-btn-lg">
                        <?php esc_html_e( 'Save Changes', 'loopkit' ); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <?php if ( 'advanced' === $tab ) : ?>
            <div class="lk-tab-content">
                <form method="post" action="">
                    <?php wp_nonce_field( 'loopkit_settings_save', 'loopkit_settings_nonce' ); ?>
                    <input type="hidden" name="active_tab" value="advanced" />
                    <input type="hidden" name="loopkit_settings_submit" value="1" />

                    <div class="lk-card">
                        <div class="lk-card-header">
                            <h3><?php esc_html_e( 'CSS Print Method', 'loopkit' ); ?></h3>
                            <p class="lk-card-desc"><?php esc_html_e( 'How CSS files generated by Loopkit are loaded on the front end.', 'loopkit' ); ?></p>
                        </div>
                        <div class="lk-card-body">
                            <label class="lk-radio-row">
                                <input type="radio" name="loopkit_css_print_method" value="external"
                                       <?php checked( $advanced['css_print_method'], 'external' ); ?> />
                                <span><?php esc_html_e( 'External File', 'loopkit' ); ?></span>
                                <span class="lk-radio-desc"><?php esc_html_e( 'Generates CSS as external .css files. Better for caching.', 'loopkit' ); ?></span>
                            </label>
                            <label class="lk-radio-row">
                                <input type="radio" name="loopkit_css_print_method" value="internal"
                                       <?php checked( $advanced['css_print_method'], 'internal' ); ?> />
                                <span><?php esc_html_e( 'Internal Embedding', 'loopkit' ); ?></span>
                                <span class="lk-radio-desc"><?php esc_html_e( 'Embeds CSS inline in the page HTML. Fewer HTTP requests.', 'loopkit' ); ?></span>
                            </label>
                        </div>
                    </div>

                    <div class="lk-card">
                        <div class="lk-card-header">
                            <h3><?php esc_html_e( 'Load Font Awesome', 'loopkit' ); ?></h3>
                            <p class="lk-card-desc"><?php esc_html_e( 'Load the Font Awesome icon library on the front end.', 'loopkit' ); ?></p>
                        </div>
                        <div class="lk-card-body">
                            <select name="loopkit_load_font_awesome" class="lk-select">
                                <option value="yes" <?php selected( $advanced['load_font_awesome'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'loopkit' ); ?></option>
                                <option value="no" <?php selected( $advanced['load_font_awesome'], 'no' ); ?>><?php esc_html_e( 'No', 'loopkit' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="lk-card">
                        <div class="lk-card-header">
                            <h3><?php esc_html_e( 'Editor Loader Style', 'loopkit' ); ?></h3>
                            <p class="lk-card-desc"><?php esc_html_e( 'Choose the loading animation style for the editor.', 'loopkit' ); ?></p>
                        </div>
                        <div class="lk-card-body">
                            <select name="loopkit_editor_loader_style" class="lk-select">
                                <option value="default" <?php selected( $advanced['editor_loader_style'], 'default' ); ?>><?php esc_html_e( 'Default', 'loopkit' ); ?></option>
                                <option value="minimal" <?php selected( $advanced['editor_loader_style'], 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'loopkit' ); ?></option>
                                <option value="none" <?php selected( $advanced['editor_loader_style'], 'none' ); ?>><?php esc_html_e( 'None', 'loopkit' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="lk-card">
                        <div class="lk-card-header">
                            <h3><?php esc_html_e( 'Optimize Assets', 'loopkit' ); ?></h3>
                            <p class="lk-card-desc"><?php esc_html_e( 'Minify generated CSS and combine frontend scripts to reduce HTTP requests and improve load performance.', 'loopkit' ); ?></p>
                        </div>
                        <div class="lk-card-body">
                            <select name="loopkit_optimize_assets" class="lk-select">
                                <option value="yes" <?php selected( $advanced['optimize_assets'] ?? 'yes', 'yes' ); ?>><?php esc_html_e( 'Enabled (Recommended)', 'loopkit' ); ?></option>
                                <option value="no" <?php selected( $advanced['optimize_assets'] ?? 'yes', 'no' ); ?>><?php esc_html_e( 'Disabled', 'loopkit' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="lk-card">
                        <div class="lk-card-header">
                            <h3><?php esc_html_e( 'Uninstalling', 'loopkit' ); ?></h3>
                            <p class="lk-card-desc"><?php esc_html_e( 'What happens to your data if Loopkit is deleted from the Plugins screen. Deactivating never removes anything.', 'loopkit' ); ?></p>
                        </div>
                        <div class="lk-card-body">
                            <label class="lk-toggle-row">
                                <span class="lk-toggle-wrap">
                                    <input type="checkbox" name="loopkit_remove_data_on_uninstall" value="1"
                                           <?php checked( $advanced['remove_data_on_uninstall'] ?? '', '1' ); ?> />
                                    <span class="lk-toggle-slider"></span>
                                </span>
                                <span class="lk-toggle-label"><?php esc_html_e( 'Remove all data on uninstall', 'loopkit' ); ?></span>
                            </label>
                            <p class="lk-field-help" style="margin-top:10px">
                                <?php esc_html_e( 'Off by default, and deliberately so. With it on, deleting the plugin drops your templates, products, orders, collections and form submissions, strips Loopkit data from every page, and removes the generated CSS. There is no undo. Your pages and posts themselves are kept.', 'loopkit' ); ?>
                            </p>
                        </div>
                    </div>

                    <button type="submit" class="lk-btn lk-btn-primary lk-btn-lg">
                        <?php esc_html_e( 'Save Changes', 'loopkit' ); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <?php
        $this->render_footer();
    }

    /**
     * Render the Site Settings page.
     */
    public function render_site_settings() {
        // Handle save
        if ( isset( $_POST['loopkit_site_settings_submit'] ) ) {
            check_admin_referer( 'loopkit_site_settings_save', 'loopkit_site_settings_nonce' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'loopkit' ) );
            }

            $site = array();

            // Colours
            $color_fields = array( 'color_primary', 'color_secondary', 'color_text', 'color_accent', 'page_background_color' );
            $defaults = Loopkit_Site_Settings::get_defaults();
            foreach ( $color_fields as $f ) {
                $site[ $f ] = sanitize_hex_color( wp_unslash( $_POST[ 'loopkit_' . $f ] ?? '' ) );
                if ( empty( $site[ $f ] ) ) {
                    $site[ $f ] = $defaults[ $f ];
                }
            }

            // Typography
            $tags = array( 'body', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
            $props = array( 'font_size', 'font_weight', 'line_height', 'letter_spacing' );
            foreach ( $tags as $tag ) {
                foreach ( $props as $prop ) {
                    $key = $tag . '_' . $prop;
                    $site[ $key ] = sanitize_text_field( wp_unslash( $_POST[ 'loopkit_' . $key ] ?? '' ) );
                }
            }

            // Layout
            $site['content_width']     = absint( $_POST['loopkit_content_width'] ?? 1140 );
            $site['container_padding'] = absint( $_POST['loopkit_container_padding'] ?? 10 );

            $merged = wp_parse_args( $site, Loopkit_Site_Settings::get_settings() );
            update_option( Loopkit_Site_Settings::OPTION_NAME, $merged );
            update_option( 'loopkit_site_settings', $merged );
            if ( class_exists( 'Loopkit_Site_Settings' ) ) {
                Loopkit_Site_Settings::save_global_css_file();
            }
            echo '<div class="lk-notice lk-notice-success"><p>' . esc_html__( 'Site settings saved successfully.', 'loopkit' ) . '</p></div>';
        }

        $this->render_header( esc_html__( 'Site Settings', 'loopkit' ), 'site-settings' );

        $settings = Loopkit_Site_Settings::get_settings();
        ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'loopkit_site_settings_save', 'loopkit_site_settings_nonce' ); ?>
            <input type="hidden" name="loopkit_site_settings_submit" value="1" />

            <!-- Global Colours -->
            <div class="lk-card">
                <div class="lk-card-header">
                    <h3><span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Global Colours', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc"><?php esc_html_e( 'These colours are available throughout the editor and output as CSS variables on the front end.', 'loopkit' ); ?></p>
                </div>
                <div class="lk-card-body">
                    <div class="lk-form-grid">
                        <div class="lk-form-field">
                            <label><?php esc_html_e( 'Primary', 'loopkit' ); ?></label>
                            <input type="text" name="loopkit_color_primary" value="<?php echo esc_attr( $settings['color_primary'] ); ?>" class="lk-color-picker" data-default-color="#38A8F5" />
                        </div>
                        <div class="lk-form-field">
                            <label><?php esc_html_e( 'Secondary', 'loopkit' ); ?></label>
                            <input type="text" name="loopkit_color_secondary" value="<?php echo esc_attr( $settings['color_secondary'] ); ?>" class="lk-color-picker" data-default-color="#54595F" />
                        </div>
                        <div class="lk-form-field">
                            <label><?php esc_html_e( 'Text', 'loopkit' ); ?></label>
                            <input type="text" name="loopkit_color_text" value="<?php echo esc_attr( $settings['color_text'] ); ?>" class="lk-color-picker" data-default-color="#333333" />
                        </div>
                        <div class="lk-form-field">
                            <label><?php esc_html_e( 'Accent', 'loopkit' ); ?></label>
                            <input type="text" name="loopkit_color_accent" value="<?php echo esc_attr( $settings['color_accent'] ); ?>" class="lk-color-picker" data-default-color="#38A8F5" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Typography -->
            <div class="lk-card">
                <div class="lk-card-header">
                    <h3><span class="dashicons dashicons-editor-textcolor"></span> <?php esc_html_e( 'Global Typography', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc"><?php esc_html_e( 'Set the default typography for body text and headings.', 'loopkit' ); ?></p>
                </div>
                <div class="lk-card-body">
                    <?php
                    $tags_labels = array(
                        'body' => esc_html__( 'Body', 'loopkit' ),
                        'h1'   => 'H1',
                        'h2'   => 'H2',
                        'h3'   => 'H3',
                        'h4'   => 'H4',
                        'h5'   => 'H5',
                        'h6'   => 'H6',
                    );
                    foreach ( $tags_labels as $tag => $label ) :
                    ?>
                    <div class="lk-typo-section">
                        <h4 class="lk-typo-heading"><?php echo esc_html( $label ); ?></h4>
                        <div class="lk-form-grid lk-form-grid-4">
                            <div class="lk-form-field">
                                <label><?php esc_html_e( 'Size (px)', 'loopkit' ); ?></label>
                                <input type="number" name="loopkit_<?php echo esc_attr( $tag ); ?>_font_size"
                                       value="<?php echo esc_attr( $settings[ $tag . '_font_size' ] ); ?>"
                                       min="1" max="200" class="lk-input" />
                            </div>
                            <div class="lk-form-field">
                                <label><?php esc_html_e( 'Weight', 'loopkit' ); ?></label>
                                <select name="loopkit_<?php echo esc_attr( $tag ); ?>_font_weight" class="lk-select">
                                    <?php
                                    $weights = array( '100', '200', '300', '400', '500', '600', '700', '800', '900' );
                                    foreach ( $weights as $w ) :
                                    ?>
                                        <option value="<?php echo esc_attr( $w ); ?>" <?php selected( $settings[ $tag . '_font_weight' ], $w ); ?>><?php echo esc_html( $w ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="lk-form-field">
                                <label><?php esc_html_e( 'Line Height', 'loopkit' ); ?></label>
                                <input type="text" name="loopkit_<?php echo esc_attr( $tag ); ?>_line_height"
                                       value="<?php echo esc_attr( $settings[ $tag . '_line_height' ] ); ?>"
                                       class="lk-input" />
                            </div>
                            <div class="lk-form-field">
                                <label><?php esc_html_e( 'Letter Spacing (px)', 'loopkit' ); ?></label>
                                <input type="text" name="loopkit_<?php echo esc_attr( $tag ); ?>_letter_spacing"
                                       value="<?php echo esc_attr( $settings[ $tag . '_letter_spacing' ] ); ?>"
                                       class="lk-input" />
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Layout -->
            <div class="lk-card">
                <div class="lk-card-header">
                    <h3><span class="dashicons dashicons-grid-view"></span> <?php esc_html_e( 'Layout', 'loopkit' ); ?></h3>
                </div>
                <div class="lk-card-body">
                    <div class="lk-form-grid lk-form-grid-3">
                        <div class="lk-form-field">
                            <label><?php esc_html_e( 'Content Width (px)', 'loopkit' ); ?></label>
                            <input type="number" name="loopkit_content_width"
                                   value="<?php echo esc_attr( $settings['content_width'] ); ?>"
                                   min="300" max="2000" class="lk-input" />
                        </div>
                        <div class="lk-form-field">
                            <label><?php esc_html_e( 'Container Padding (px)', 'loopkit' ); ?></label>
                            <input type="number" name="loopkit_container_padding"
                                   value="<?php echo esc_attr( $settings['container_padding'] ); ?>"
                                   min="0" max="100" class="lk-input" />
                        </div>
                        <div class="lk-form-field">
                            <label><?php esc_html_e( 'Page Background Colour', 'loopkit' ); ?></label>
                            <input type="text" name="loopkit_page_background_color"
                                   value="<?php echo esc_attr( $settings['page_background_color'] ); ?>"
                                   class="lk-color-picker" data-default-color="#FFFFFF" />
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="lk-btn lk-btn-primary lk-btn-lg">
                <?php esc_html_e( 'Save Changes', 'loopkit' ); ?>
            </button>
        </form>

        <?php
        $this->render_footer();
    }

    /**
     * Render the Templates page.
     */
    public function render_templates() {
        $this->render_header( esc_html__( 'Templates', 'loopkit' ), 'templates' );

        $current_type = sanitize_key( $_GET['type'] ?? 'all' );
        $valid_types  = array( 'all', 'header', 'footer', 'single', 'archive', 'loop-item', 'popup', 'page', 'section', 'container', 'global', 'cms-item' );
        if ( ! in_array( $current_type, $valid_types, true ) ) {
            $current_type = 'all';
        }

        $templates = Loopkit_Templates::get_all( $current_type );
        $counts    = Loopkit_Templates::get_counts();
        ?>

        <div class="lk-section-header-row">
            <h2 class="lk-section-title"><?php esc_html_e( 'Templates', 'loopkit' ); ?></h2>
            <button type="button" class="lk-btn lk-btn-primary" id="lk-add-template">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Add New', 'loopkit' ); ?>
            </button>
        </div>

        <!-- Filter tabs with counts -->
        <div class="lk-tabs-nav lk-templates-nav" style="margin-bottom: 20px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-templates&type=all' ) ); ?>"
               class="lk-tab <?php echo 'all' === $current_type ? 'lk-tab-active' : ''; ?>">
                <?php esc_html_e( 'All', 'loopkit' ); ?> <span class="lk-tab-count">(<?php echo intval( $counts['all'] ); ?>)</span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-templates&type=header' ) ); ?>"
               class="lk-tab <?php echo 'header' === $current_type ? 'lk-tab-active' : ''; ?>">
                <?php esc_html_e( 'Header', 'loopkit' ); ?> <span class="lk-tab-count">(<?php echo intval( $counts['header'] ); ?>)</span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-templates&type=footer' ) ); ?>"
               class="lk-tab <?php echo 'footer' === $current_type ? 'lk-tab-active' : ''; ?>">
                <?php esc_html_e( 'Footer', 'loopkit' ); ?> <span class="lk-tab-count">(<?php echo intval( $counts['footer'] ); ?>)</span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-templates&type=single' ) ); ?>"
               class="lk-tab <?php echo 'single' === $current_type ? 'lk-tab-active' : ''; ?>">
                <?php esc_html_e( 'Single', 'loopkit' ); ?> <span class="lk-tab-count">(<?php echo intval( $counts['single'] ); ?>)</span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-templates&type=archive' ) ); ?>"
               class="lk-tab <?php echo 'archive' === $current_type ? 'lk-tab-active' : ''; ?>">
                <?php esc_html_e( 'Archive', 'loopkit' ); ?> <span class="lk-tab-count">(<?php echo intval( $counts['archive'] ); ?>)</span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-templates&type=loop-item' ) ); ?>"
               class="lk-tab <?php echo 'loop-item' === $current_type ? 'lk-tab-active' : ''; ?>">
                <?php esc_html_e( 'Loop Item', 'loopkit' ); ?> <span class="lk-tab-count">(<?php echo intval( $counts['loop-item'] ); ?>)</span>
            </a>
            <?php if ( ! empty( $_GET['lk_import'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-<?php echo 'done' === $_GET['lk_import'] ? 'success' : 'error'; // phpcs:ignore WordPress.Security.NonceVerification ?> is-dismissible" style="margin:0 0 14px"><p><?php
                    $lk_state = sanitize_key( wp_unslash( $_GET['lk_import'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
                    if ( 'done' === $lk_state ) {
                        /* translators: %d: number of templates */
                        printf( esc_html__( '%d templates imported.', 'loopkit' ), (int) ( $_GET['count'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
                    } elseif ( 'nofile' === $lk_state ) {
                        esc_html_e( 'Choose a Loopkit templates file first.', 'loopkit' );
                    } else {
                        esc_html_e( 'That file is not a Loopkit templates export.', 'loopkit' );
                    }
                ?></p></div>
            <?php endif; ?>

            <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-templates&type=popup' ) ); ?>"
               class="lk-tab <?php echo 'popup' === $current_type ? 'lk-tab-active' : ''; ?>">
                <?php esc_html_e( 'Popup', 'loopkit' ); ?> <span class="lk-tab-count">(<?php echo intval( $counts['popup'] ); ?>)</span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-templates&type=page' ) ); ?>"
               class="lk-tab <?php echo 'page' === $current_type ? 'lk-tab-active' : ''; ?>">
                <?php esc_html_e( 'Page', 'loopkit' ); ?> <span class="lk-tab-count">(<?php echo intval( $counts['page'] ); ?>)</span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-templates&type=section' ) ); ?>"
               class="lk-tab <?php echo 'section' === $current_type ? 'lk-tab-active' : ''; ?>">
                <?php esc_html_e( 'Section', 'loopkit' ); ?> <span class="lk-tab-count">(<?php echo intval( $counts['section'] ); ?>)</span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-templates&type=container' ) ); ?>"
               class="lk-tab <?php echo 'container' === $current_type ? 'lk-tab-active' : ''; ?>">
                <?php esc_html_e( 'Container', 'loopkit' ); ?> <span class="lk-tab-count">(<?php echo intval( $counts['container'] ); ?>)</span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-templates&type=global' ) ); ?>"
               class="lk-tab <?php echo 'global' === $current_type ? 'lk-tab-active' : ''; ?>">
                <?php esc_html_e( 'Global', 'loopkit' ); ?> <span class="lk-tab-count">(<?php echo intval( $counts['global'] ?? 0 ); ?>)</span>
            </a>
        </div>

            <div class="lk-card" style="margin:16px 0">
                <div class="lk-card-header">
                    <h3><?php esc_html_e( 'Move templates between sites', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc"><?php esc_html_e( 'Export every template as one file — headers, footers, singles, archives, loop items, popups and saved sections, with their display conditions — then import it on another site.', 'loopkit' ); ?></p>
                </div>
                <div class="lk-card-body lk-templates-transfer">
                    <a class="lk-btn lk-btn-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=loopkit-templates&loopkit_templates_action=export' ), 'loopkit_templates_transfer' ) ); ?>">
                        <?php esc_html_e( 'Export all templates', 'loopkit' ); ?>
                    </a>

                    <form method="post" enctype="multipart/form-data" class="lk-templates-import">
                        <?php wp_nonce_field( 'loopkit_templates_transfer' ); ?>
                        <input type="hidden" name="loopkit_templates_action" value="import">
                        <input type="file" name="loopkit_templates_file" accept=".json,application/json" required>
                        <button type="submit" class="lk-btn lk-btn-primary"><?php esc_html_e( 'Import', 'loopkit' ); ?></button>
                    </form>
                </div>
            </div>

        <div class="lk-card" id="lk-templates-card">
            <?php if ( empty( $templates ) ) : ?>
                <div class="lk-empty-state" id="lk-templates-empty">
                    <span class="dashicons dashicons-media-document"></span>
                    <p><?php esc_html_e( 'No templates found. Click "Add New" to create your first template.', 'loopkit' ); ?></p>
                </div>
            <?php endif; ?>

            <table class="lk-table <?php echo empty( $templates ) ? 'lk-hidden' : ''; ?>" id="lk-templates-table">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php esc_html_e( 'Title', 'loopkit' ); ?></th>
                        <th style="width: 12%;"><?php esc_html_e( 'Type', 'loopkit' ); ?></th>
                        <th style="width: 13%;"><?php esc_html_e( 'Author', 'loopkit' ); ?></th>
                        <th style="width: 18%;"><?php esc_html_e( 'Display Conditions', 'loopkit' ); ?></th>
                        <th style="width: 15%;"><?php esc_html_e( 'Used on', 'loopkit' ); ?></th>
                        <th style="width: 12%;"><?php esc_html_e( 'Date', 'loopkit' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $templates as $tpl ) :
                        $tpl_type  = get_post_meta( $tpl->ID, '_loopkit_template_type', true ) ?: 'page';
                        $is_global = (bool) get_post_meta( $tpl->ID, '_loopkit_is_global', true );
                        $usage_ids = get_post_meta( $tpl->ID, '_loopkit_global_usage', true );
                        $usage_ids = is_array( $usage_ids ) ? $usage_ids : array();
                        $author    = get_userdata( $tpl->post_author );
                        $edit_url  = admin_url( 'post.php?post=' . $tpl->ID . '&action=loopkit' );
                        $is_hf     = in_array( $tpl_type, array( 'header', 'footer', 'single', 'archive', 'popup' ), true );
                        $cond_str  = $is_hf ? Loopkit_Templates::get_conditions_summary( $tpl->ID ) : '—';
                        $export_url = wp_nonce_url(
                            admin_url( 'admin-post.php?action=loopkit_export_template&id=' . $tpl->ID ),
                            'loopkit_export_' . $tpl->ID
                        );
                    ?>
                        <tr data-template-id="<?php echo esc_attr( $tpl->ID ); ?>" data-template-title="<?php echo esc_attr( $tpl->post_title ); ?>">
                            <td>
                                <div class="lk-row-title-wrap">
                                    <a href="<?php echo esc_url( $edit_url ); ?>" class="lk-template-title-link">
                                        <strong><?php echo esc_html( $tpl->post_title ); ?></strong>
                                    </a>
                                    <div class="lk-row-actions">
                                        <a href="<?php echo esc_url( $edit_url ); ?>" class="lk-action-link lk-action-edit">
                                            <?php esc_html_e( 'Edit with Loopkit', 'loopkit' ); ?>
                                        </a>
                                        <?php if ( $is_hf ) : ?>
                                            <span class="lk-action-sep">|</span>
                                            <button type="button" class="lk-action-btn lk-edit-conditions" data-id="<?php echo esc_attr( $tpl->ID ); ?>" data-type="<?php echo esc_attr( $tpl_type ); ?>" data-title="<?php echo esc_attr( $tpl->post_title ); ?>">
                                                <?php esc_html_e( 'Edit Conditions', 'loopkit' ); ?>
                                            </button>
                                        <?php endif; ?>
                                        <span class="lk-action-sep">|</span>
                                        <button type="button" class="lk-action-btn lk-rename-template" data-id="<?php echo esc_attr( $tpl->ID ); ?>" data-title="<?php echo esc_attr( $tpl->post_title ); ?>">
                                            <?php esc_html_e( 'Rename', 'loopkit' ); ?>
                                        </button>
                                        <?php if ( in_array( $tpl_type, array( 'section', 'container' ), true ) && ! $is_global ) : ?>
                                            <span class="lk-action-sep">|</span>
                                            <button type="button" class="lk-action-btn lk-make-global-template" data-id="<?php echo esc_attr( $tpl->ID ); ?>" data-title="<?php echo esc_attr( $tpl->post_title ); ?>">
                                                <?php esc_html_e( 'Make Global', 'loopkit' ); ?>
                                            </button>
                                        <?php endif; ?>
                                        <span class="lk-action-sep">|</span>
                                        <button type="button" class="lk-action-btn lk-duplicate-template" data-id="<?php echo esc_attr( $tpl->ID ); ?>">
                                            <?php esc_html_e( 'Duplicate', 'loopkit' ); ?>
                                        </button>
                                        <span class="lk-action-sep">|</span>
                                        <a href="<?php echo esc_url( $export_url ); ?>" class="lk-action-link lk-export-template">
                                            <?php esc_html_e( 'Export', 'loopkit' ); ?>
                                        </a>
                                        <span class="lk-action-sep">|</span>
                                        <button type="button" class="lk-action-btn lk-action-danger lk-delete-template" data-id="<?php echo esc_attr( $tpl->ID ); ?>" data-title="<?php echo esc_attr( $tpl->post_title ); ?>">
                                            <?php esc_html_e( 'Delete', 'loopkit' ); ?>
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ( $is_global ) : ?>
                                    <span class="lk-badge lk-type-badge lk-type-global" style="background:#F3EAFC; color:#9B51E0; border:1px solid #E0C7F7;">
                                        <span class="dashicons dashicons-admin-site-alt3" style="font-size:12px; width:12px; height:12px; vertical-align:middle; margin-right:2px;"></span>
                                        <?php echo esc_html( ucfirst( $tpl_type ) ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="lk-badge lk-type-badge lk-type-<?php echo esc_attr( $tpl_type ); ?>">
                                        <?php echo esc_html( ucfirst( $tpl_type ) ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $author ? $author->display_name : '—' ); ?></td>
                            <td>
                                <?php if ( $is_hf ) : ?>
                                    <span class="lk-conditions-text <?php echo 'Draft' === $cond_str ? 'lk-cond-draft' : ''; ?>">
                                        <?php echo esc_html( $cond_str ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="lk-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $is_global && ! empty( $usage_ids ) ) :
                                    $displayed_links = array();
                                    $first_three = array_slice( $usage_ids, 0, 3 );
                                    foreach ( $first_three as $uid ) {
                                        $u_post = get_post( $uid );
                                        if ( $u_post && 'trash' !== $u_post->post_status ) {
                                            $u_url = get_edit_post_link( $uid ) ?: get_permalink( $uid );
                                            $displayed_links[] = '<a href="' . esc_url( $u_url ) . '" class="lk-usage-link" target="_blank">' . esc_html( get_the_title( $uid ) ) . '</a>';
                                        }
                                    }
                                    if ( ! empty( $displayed_links ) ) {
                                        echo implode( ', ', $displayed_links );
                                        if ( count( $usage_ids ) > 3 ) {
                                            echo ' <span class="lk-usage-more" title="' . esc_attr__( 'Used on more pages', 'loopkit' ) . '">+ ' . ( count( $usage_ids ) - 3 ) . ' ' . esc_html__( 'more', 'loopkit' ) . '</span>';
                                        }
                                    } else {
                                        echo '<span class="lk-muted">—</span>';
                                    }
                                else : ?>
                                    <span class="lk-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="lk-date-text"><?php echo esc_html( get_the_date( '', $tpl ) ); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Styled Modal: Create a Template -->
        <div class="lk-admin-modal-backdrop" id="lk-create-modal" style="display:none;">
            <div class="lk-admin-modal" role="dialog" aria-modal="true" aria-labelledby="lk-create-modal-title">
                <div class="lk-admin-modal-header">
                    <h3 id="lk-create-modal-title"><?php esc_html_e( 'Create a Template', 'loopkit' ); ?></h3>
                    <button type="button" class="lk-admin-modal-close" aria-label="<?php esc_attr_e( 'Close', 'loopkit' ); ?>">&times;</button>
                </div>
                <div class="lk-admin-modal-body">
                    <p class="lk-modal-desc">
                        <?php esc_html_e( 'Templates are reusable parts such as headers, footers, single post and archive layouts, pages, sections and containers.', 'loopkit' ); ?>
                    </p>
                    <div class="lk-modal-form-group">
                        <label for="lk-template-type-select"><?php esc_html_e( 'Select the type of template you want to work on', 'loopkit' ); ?></label>
                        <select id="lk-template-type-select" class="lk-modal-select">
                            <option value="header" <?php selected( $current_type, 'header' ); ?>><?php esc_html_e( 'Header', 'loopkit' ); ?></option>
                            <option value="footer" <?php selected( $current_type, 'footer' ); ?>><?php esc_html_e( 'Footer', 'loopkit' ); ?></option>
                            <option value="single" <?php selected( $current_type, 'single' ); ?>><?php esc_html_e( 'Single', 'loopkit' ); ?></option>
                            <option value="cms-item" <?php selected( $current_type, 'cms-item' ); ?>><?php esc_html_e( 'Collection Item', 'loopkit' ); ?></option>
                            <option value="archive" <?php selected( $current_type, 'archive' ); ?>><?php esc_html_e( 'Archive', 'loopkit' ); ?></option>
                            <option value="loop-item" <?php selected( $current_type, 'loop-item' ); ?>><?php esc_html_e( 'Loop Item', 'loopkit' ); ?></option>
                            <option value="popup" <?php selected( $current_type, 'popup' ); ?>><?php esc_html_e( 'Popup', 'loopkit' ); ?></option>
                            <option value="page" <?php selected( in_array( $current_type, array( 'all', 'page' ), true ) ); ?>><?php esc_html_e( 'Page', 'loopkit' ); ?></option>
                            <option value="section" <?php selected( $current_type, 'section' ); ?>><?php esc_html_e( 'Section', 'loopkit' ); ?></option>
                            <option value="container" <?php selected( $current_type, 'container' ); ?>><?php esc_html_e( 'Container', 'loopkit' ); ?></option>
                        </select>
                    </div>
                    <div class="lk-modal-form-group" id="lk-make-global-group" style="<?php echo in_array( $current_type, array( 'section', 'container' ), true ) ? '' : 'display:none;'; ?>">
                        <label class="lk-switch-label" style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                            <span style="font-size:13px; font-weight:500; color:#374151;"><?php esc_html_e( 'Make it global', 'loopkit' ); ?></span>
                            <input type="checkbox" id="lk-template-is-global" value="1" style="width:18px; height:18px; cursor:pointer; accent-color:#9B51E0;">
                        </label>
                        <p class="lk-modal-desc" style="margin:4px 0 0; font-size:12px; color:#6B7280;">
                            <?php esc_html_e( 'Global elements can be inserted across your site and stay in sync.', 'loopkit' ); ?>
                        </p>
                    </div>
                    <div class="lk-modal-form-group">
                        <label for="lk-template-name-input"><?php esc_html_e( 'Name your template', 'loopkit' ); ?></label>
                        <input type="text" id="lk-template-name-input" class="lk-modal-input" placeholder="<?php esc_attr_e( 'e.g. Main Header', 'loopkit' ); ?>" required autocomplete="off">
                        <div class="lk-modal-error" id="lk-create-modal-error" style="display:none;"></div>
                    </div>
                </div>
                <div class="lk-admin-modal-footer">
                    <button type="button" class="lk-btn lk-btn-secondary lk-modal-cancel"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></button>
                    <button type="button" class="lk-btn lk-btn-primary" id="lk-confirm-create-template">
                        <span class="lk-btn-text"><?php esc_html_e( 'Create Template', 'loopkit' ); ?></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Styled Modal: Rename Template -->
        <div class="lk-admin-modal-backdrop" id="lk-rename-modal" style="display:none;">
            <div class="lk-admin-modal" role="dialog" aria-modal="true" aria-labelledby="lk-rename-modal-title">
                <div class="lk-admin-modal-header">
                    <h3 id="lk-rename-modal-title"><?php esc_html_e( 'Rename Template', 'loopkit' ); ?></h3>
                    <button type="button" class="lk-admin-modal-close" aria-label="<?php esc_attr_e( 'Close', 'loopkit' ); ?>">&times;</button>
                </div>
                <div class="lk-admin-modal-body">
                    <div class="lk-modal-form-group">
                        <label for="lk-rename-name-input"><?php esc_html_e( 'Template Name', 'loopkit' ); ?></label>
                        <input type="text" id="lk-rename-name-input" class="lk-modal-input" required autocomplete="off">
                        <input type="hidden" id="lk-rename-id-input">
                        <div class="lk-modal-error" id="lk-rename-modal-error" style="display:none;"></div>
                    </div>
                </div>
                <div class="lk-admin-modal-footer">
                    <button type="button" class="lk-btn lk-btn-secondary lk-modal-cancel"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></button>
                    <button type="button" class="lk-btn lk-btn-primary" id="lk-confirm-rename-template">
                        <span class="lk-btn-text"><?php esc_html_e( 'Save', 'loopkit' ); ?></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Styled Modal: Delete Confirmation -->
        <div class="lk-admin-modal-backdrop" id="lk-delete-modal" style="display:none;">
            <div class="lk-admin-modal lk-modal-sm" role="dialog" aria-modal="true" aria-labelledby="lk-delete-modal-title">
                <div class="lk-admin-modal-header">
                    <h3 id="lk-delete-modal-title"><?php esc_html_e( 'Delete Template', 'loopkit' ); ?></h3>
                    <button type="button" class="lk-admin-modal-close" aria-label="<?php esc_attr_e( 'Close', 'loopkit' ); ?>">&times;</button>
                </div>
                <div class="lk-admin-modal-body">
                    <div class="lk-modal-error" id="lk-delete-modal-error" style="display:none;"></div>
                    <p class="lk-modal-desc">
                        <?php esc_html_e( 'Are you sure you want to delete this template? This action cannot be undone.', 'loopkit' ); ?>
                    </p>
                    <p class="lk-delete-target-title"><strong id="lk-delete-item-title"></strong></p>
                    <input type="hidden" id="lk-delete-id-input">
                </div>
                <div class="lk-admin-modal-footer">
                    <button type="button" class="lk-btn lk-btn-secondary lk-modal-cancel"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></button>
                    <button type="button" class="lk-btn lk-btn-danger" id="lk-confirm-delete-template">
                        <span class="lk-btn-text"><?php esc_html_e( 'Delete', 'loopkit' ); ?></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Styled Modal: Display Conditions -->
        <div class="lk-admin-modal-backdrop" id="lk-admin-conditions-modal" style="display:none;">
            <div class="lk-admin-modal lk-conditions-modal" role="dialog" aria-modal="true" aria-labelledby="lk-admin-conditions-title">
                <div class="lk-admin-modal-header">
                    <div class="lk-modal-header-titles">
                        <h3 id="lk-admin-conditions-title"><?php esc_html_e( 'Display Conditions', 'loopkit' ); ?></h3>
                        <p class="lk-modal-desc"><?php esc_html_e( 'Set the conditions that determine where your template is used throughout your site.', 'loopkit' ); ?></p>
                    </div>
                    <button type="button" class="lk-admin-modal-close" aria-label="<?php esc_attr_e( 'Close', 'loopkit' ); ?>">&times;</button>
                </div>
                <div class="lk-admin-modal-body">
                    <input type="hidden" id="lk-admin-cond-template-id">
                    <input type="hidden" id="lk-admin-cond-template-type">
                    <div class="lk-conditions-conflict-notice" id="lk-admin-conditions-conflict-notice" style="display:none;"></div>
                    <div class="lk-conditions-list" id="lk-admin-conditions-list"></div>
                    <div class="lk-conditions-add-wrap" style="margin-top: 15px;">
                        <button type="button" class="lk-btn lk-btn-outline" id="lk-admin-add-condition-btn">
                            <span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add Condition', 'loopkit' ); ?>
                        </button>
                    </div>
                </div>
                <div class="lk-admin-modal-footer">
                    <button type="button" class="lk-btn lk-btn-secondary lk-modal-cancel"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></button>
                    <button type="button" class="lk-btn lk-btn-primary" id="lk-admin-save-conditions-btn">
                        <span class="lk-btn-text"><?php esc_html_e( 'Save & Close', 'loopkit' ); ?></span>
                    </button>
                </div>
            </div>
        </div>

        <?php
        $this->render_footer();
    }

    /**
     * Install an uploaded Loopkit plugin or theme zip over the current one.
     * Runs on admin_init so redirects work before any output.
     */
    /**
     * Turn a module on or off (Tools > Modules).
     */
    public function handle_module_toggle() {
        if ( empty( $_POST['loopkit_action'] ) || 'toggle_module' !== $_POST['loopkit_action'] ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to change modules.', 'loopkit' ) );
        }
        check_admin_referer( 'loopkit_modules' );

        $id      = sanitize_key( wp_unslash( $_POST['module'] ?? '' ) );
        $enabled = ! empty( $_POST['enabled'] );
        $ok      = class_exists( 'Loopkit_Modules' ) ? Loopkit_Modules::set_enabled( $id, $enabled ) : false;

        wp_safe_redirect( add_query_arg(
            array( 'page' => 'loopkit-tools', 'tab' => 'modules', 'lk_module' => $ok ? ( $enabled ? 'on' : 'off' ) : 'error' ),
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    /**
     * Export templates to a JSON file, or import one back.
     */
    public function handle_template_transfer() {
        $action = sanitize_key( wp_unslash( $_REQUEST['loopkit_templates_action'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
        if ( '' === $action ) {
            return;
        }
        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_die( esc_html__( 'You are not allowed to move templates.', 'loopkit' ) );
        }

        if ( 'export' === $action ) {
            check_admin_referer( 'loopkit_templates_transfer' );

            $templates = get_posts( array(
                'post_type'      => 'loopkit_template',
                'post_status'    => array( 'publish', 'draft' ),
                'posts_per_page' => -1,
            ) );

            $payload = array(
                'format'    => 'loopkit-templates',
                'version'   => LOOPKIT_VERSION,
                'exported'  => current_time( 'mysql' ),
                'site'      => home_url( '/' ),
                'templates' => array(),
            );

            foreach ( $templates as $template ) {
                $payload['templates'][] = array(
                    'title'      => $template->post_title,
                    'status'     => $template->post_status,
                    'type'       => (string) get_post_meta( $template->ID, '_loopkit_template_type', true ),
                    'elements'   => json_decode( (string) get_post_meta( $template->ID, '_loopkit_data', true ), true ),
                    'conditions' => get_post_meta( $template->ID, '_loopkit_conditions', true ),
                    'settings'   => get_post_meta( $template->ID, '_loopkit_page_settings', true ),
                    'popup'      => get_post_meta( $template->ID, '_loopkit_popup_settings', true ),
                );
            }

            nocache_headers();
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=loopkit-templates-' . gmdate( 'Y-m-d' ) . '.json' );
            echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            exit;
        }

        if ( 'import' !== $action ) {
            return;
        }
        check_admin_referer( 'loopkit_templates_transfer' );

        $redirect = admin_url( 'admin.php?page=loopkit-templates' );
        if ( empty( $_FILES['loopkit_templates_file']['tmp_name'] ) ) {
            wp_safe_redirect( add_query_arg( 'lk_import', 'nofile', $redirect ) );
            exit;
        }

        $raw  = file_get_contents( $_FILES['loopkit_templates_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.Security.ValidatedSanitizedInput
        $data = json_decode( (string) $raw, true );

        if ( ! is_array( $data ) || 'loopkit-templates' !== ( $data['format'] ?? '' ) || empty( $data['templates'] ) ) {
            wp_safe_redirect( add_query_arg( 'lk_import', 'invalid', $redirect ) );
            exit;
        }

        $made = 0;
        foreach ( (array) $data['templates'] as $template ) {
            $title = sanitize_text_field( $template['title'] ?? __( 'Imported template', 'loopkit' ) );
            $id    = wp_insert_post( array(
                'post_type'   => 'loopkit_template',
                'post_status' => in_array( $template['status'] ?? 'draft', array( 'publish', 'draft' ), true ) ? $template['status'] : 'draft',
                'post_title'  => $title,
            ) );
            if ( ! $id || is_wp_error( $id ) ) {
                continue;
            }

            update_post_meta( $id, '_loopkit_template_type', sanitize_key( $template['type'] ?? 'section' ) );
            update_post_meta( $id, '_loopkit_edit_mode', 'builder' );
            if ( ! empty( $template['elements'] ) ) {
                update_post_meta( $id, '_loopkit_data', wp_slash( wp_json_encode( $template['elements'] ) ) );
            }
            foreach ( array( 'conditions' => '_loopkit_conditions', 'settings' => '_loopkit_page_settings', 'popup' => '_loopkit_popup_settings' ) as $key => $meta ) {
                if ( ! empty( $template[ $key ] ) && is_array( $template[ $key ] ) ) {
                    update_post_meta( $id, $meta, $template[ $key ] );
                }
            }
            $made++;
        }

        if ( class_exists( 'Loopkit_Theme_Builder' ) ) {
            Loopkit_Theme_Builder::rebuild_cache();
        }

        wp_safe_redirect( add_query_arg( array( 'lk_import' => 'done', 'count' => $made ), $redirect ) );
        exit;
    }

    /**
     * Remove everything Loopkit has stored. Typed confirmation, administrators only.
     */
    public function handle_remove_all_data() {
        if ( empty( $_POST['loopkit_action'] ) || 'remove_all_data' !== $_POST['loopkit_action'] ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to do that.', 'loopkit' ) );
        }
        check_admin_referer( 'loopkit_remove_all' );

        $typed = strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['confirm'] ?? '' ) ) ) );
        if ( 'DELETE' !== $typed ) {
            wp_safe_redirect( admin_url( 'admin.php?page=loopkit-tools&tab=danger&lk_removed=notyped' ) );
            exit;
        }

        global $wpdb;

        // Loopkit's own posts
        $types = array( 'loopkit_template' );
        if ( ! empty( $_POST['include_module_data'] ) ) {
            $types = array_merge( $types, array( 'loopkit_field_group', 'loopkit_product', 'loopkit_order' ) );
        }
        foreach ( $types as $type ) {
            foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
                wp_delete_post( $id, true );
            }
        }

        // Builder data on ordinary posts and pages
        foreach ( array( '_loopkit_data', '_loopkit_css', '_loopkit_edit_mode', '_loopkit_page_settings', '_loopkit_conditions', '_loopkit_popup_settings' ) as $meta ) {
            delete_post_meta_by_key( $meta );
        }

        // Options and tables
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'loopkit\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        if ( ! empty( $_POST['include_submissions'] ) && class_exists( 'Loopkit_Submissions' ) ) {
            $table = Loopkit_Submissions::table();
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        }

        // Generated CSS files
        $dir = WP_CONTENT_DIR . '/uploads/loopkit/css';
        if ( is_dir( $dir ) ) {
            foreach ( (array) glob( $dir . '/*.css' ) as $file ) {
                wp_delete_file( $file );
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=loopkit-tools&tab=danger&lk_removed=1' ) );
        exit;
    }

    public function handle_upload_update() {
        // A POST larger than post_max_size arrives with empty $_POST and $_FILES.
        if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && empty( $_POST ) && empty( $_FILES )
            && ! empty( $_SERVER['CONTENT_LENGTH'] ) && ! empty( $_GET['page'] ) && 'loopkit-tools' === $_GET['page'] ) {
            set_transient( 'loopkit_update_result_' . get_current_user_id(), array( 'state' => 'toobig', 'kind' => '' ), MINUTE_IN_SECONDS );
            wp_safe_redirect( admin_url( 'admin.php?page=loopkit-tools' ) );
            exit;
        }

        if ( empty( $_POST['loopkit_action'] ) || 'upload_update' !== $_POST['loopkit_action'] ) {
            return;
        }
        if ( ! current_user_can( 'update_plugins' ) || ! current_user_can( 'update_themes' ) ) {
            wp_die( esc_html__( 'You are not allowed to update Loopkit.', 'loopkit' ) );
        }
        check_admin_referer( 'loopkit_upload_update', 'loopkit_update_nonce' );

        $redirect = admin_url( 'admin.php?page=loopkit-tools' );

        // The outcome is also stored for this user: when the plugin replaces its own files the
        // redirect can be lost, and the message must still be correct on the next page load.
        $finish = function( $state, $kind = '' ) use ( $redirect ) {
            set_transient( 'loopkit_update_result_' . get_current_user_id(), array( 'state' => $state, 'kind' => $kind ), MINUTE_IN_SECONDS );
            wp_safe_redirect( add_query_arg( array( 'lk_update' => $state, 'lk_kind' => $kind ), $redirect ) );
            exit;
        };

        if ( empty( $_FILES['loopkit_package']['name'] ) ) {
            $finish( 'nofile' );
        }

        $err = (int) $_FILES['loopkit_package']['error']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        if ( UPLOAD_ERR_INI_SIZE === $err || UPLOAD_ERR_FORM_SIZE === $err ) {
            $finish( 'toobig' );
        }
        if ( UPLOAD_ERR_OK !== $err ) {
            $finish( 'upload' );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $file = wp_handle_upload(
            $_FILES['loopkit_package'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
            array(
                'test_form' => false,
                'mimes'     => array( 'zip' => 'application/zip' ),
            )
        );

        if ( ! empty( $file['error'] ) || empty( $file['file'] ) ) {
            $finish( 'upload' );
        }

        // Look inside the zip: only Loopkit's own plugin or theme package is accepted.
        $kind = self::detect_package_kind( $file['file'] );
        if ( ! $kind ) {
            wp_delete_file( $file['file'] );
            $finish( 'notloopkit' );
        }

        $overwrite = function( $options ) {
            $options['clear_destination']           = true;
            $options['abort_if_destination_exists'] = false;
            return $options;
        };
        add_filter( 'upgrader_package_options', $overwrite );

        $skin     = new Automatic_Upgrader_Skin();
        $upgrader = ( 'theme' === $kind ) ? new Theme_Upgrader( $skin ) : new Plugin_Upgrader( $skin );
        $result   = $upgrader->install( $file['file'], array( 'overwrite_package' => true ) );

        remove_filter( 'upgrader_package_options', $overwrite );
        wp_delete_file( $file['file'] );

        if ( is_wp_error( $result ) || false === $result ) {
            $finish( 'failed' );
        }

        // Installing over an active plugin deactivates it; switch it back on.
        if ( 'plugin' === $kind ) {
            $basename = plugin_basename( LOOPKIT_PLUGIN_DIR . 'loopkit.php' );
            if ( ! is_plugin_active( $basename ) ) {
                activate_plugin( $basename );
            }
        }

        $finish( 'done', $kind );
    }

    /**
     * Is this zip the Loopkit plugin, the Loopkit theme, or neither?
     *
     * @param string $zip_path Uploaded file path.
     * @return string|false 'plugin', 'theme' or false.
     */
    private static function detect_package_kind( $zip_path ) {
        $files = array(); // relative name => first 8 KB of the file

        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $zip_path ) ) {
                return false;
            }
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $name = (string) $zip->getNameIndex( $i );
                if ( preg_match( '#^[^/]+/(loopkit\.php|style\.css)$#', $name ) ) {
                    $files[ $name ] = (string) $zip->getFromIndex( $i, 8192 );
                }
            }
            $zip->close();
        } else {
            // Many servers (and XAMPP) have no zip extension: use the PclZip library that
            // ships with WordPress instead.
            if ( ! class_exists( 'PclZip' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
            }
            $archive = new PclZip( $zip_path );
            $list    = $archive->listContent();
            if ( ! is_array( $list ) ) {
                return false;
            }
            foreach ( $list as $item ) {
                $name = isset( $item['filename'] ) ? (string) $item['filename'] : '';
                if ( ! preg_match( '#^[^/]+/(loopkit\.php|style\.css)$#', $name ) ) {
                    continue;
                }
                $extracted = $archive->extract( PCLZIP_OPT_BY_NAME, $name, PCLZIP_OPT_EXTRACT_AS_STRING );
                if ( is_array( $extracted ) && ! empty( $extracted[0]['content'] ) ) {
                    $files[ $name ] = substr( (string) $extracted[0]['content'], 0, 8192 );
                }
            }
        }

        foreach ( $files as $name => $head ) {
            if ( '' === $head ) {
                continue;
            }
            if ( '/loopkit.php' === substr( $name, -12 ) && false !== stripos( $head, 'Plugin Name:' ) && false !== stripos( $head, 'Loopkit' ) ) {
                return 'plugin';
            }
            if ( '/style.css' === substr( $name, -10 ) && false !== stripos( $head, 'Theme Name:' ) && false !== stripos( $head, 'Loopkit' ) ) {
                return 'theme';
            }
        }

        return false;
    }

    /**
     * Notice shown after an upload-update attempt.
     */
    public function upload_update_notice() {
        if ( empty( $_GET['page'] ) || 'loopkit-tools' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $stored = get_transient( 'loopkit_update_result_' . get_current_user_id() );
        if ( is_array( $stored ) ) {
            delete_transient( 'loopkit_update_result_' . get_current_user_id() );
            $state = (string) ( $stored['state'] ?? '' );
            $kind  = (string) ( $stored['kind'] ?? '' );
        } elseif ( ! empty( $_GET['lk_update'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $state = sanitize_key( wp_unslash( $_GET['lk_update'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
            $kind  = isset( $_GET['lk_kind'] ) ? sanitize_key( wp_unslash( $_GET['lk_kind'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        } else {
            return;
        }

        $messages = array(
            'done'       => 'theme' === $kind
                ? __( 'Loopkit theme updated. Current version: ', 'loopkit' ) . ( wp_get_theme( 'loopkit' )->exists() ? wp_get_theme( 'loopkit' )->get( 'Version' ) : '' )
                : __( 'Loopkit plugin updated. Current version: ', 'loopkit' ) . LOOPKIT_VERSION,
            'nofile'     => __( 'Choose a zip file first.', 'loopkit' ),
            'upload'     => __( 'That file could not be uploaded. Please try again.', 'loopkit' ),
            'toobig'     => sprintf(
                /* translators: %s: maximum upload size, e.g. 2 MB */
                __( 'The zip is larger than this server allows (%s). Raise upload_max_filesize and post_max_size in php.ini, or update through Plugins > Add New > Upload Plugin.', 'loopkit' ),
                size_format( wp_max_upload_size() )
            ),
            'notloopkit' => __( 'That zip is not the Loopkit plugin or the Loopkit theme, so nothing was installed.', 'loopkit' ),
            'failed'     => __( 'The update could not be installed. Check that WordPress can write to wp-content.', 'loopkit' ),
        );
        if ( empty( $messages[ $state ] ) ) {
            return;
        }
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            'done' === $state ? 'success' : 'error',
            esc_html( $messages[ $state ] )
        );
    }

    /**
     * Render the Tools page (tabbed: General, Replace URL, Maintenance Mode).
     */
    public function render_tools() {
        // Handle maintenance mode save
        if ( isset( $_POST['loopkit_maintenance_submit'] ) ) {
            check_admin_referer( 'loopkit_maintenance_save', 'loopkit_maintenance_nonce' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'loopkit' ) );
            }

            $maintenance = array();
            $allowed_modes = array( 'disabled', 'coming_soon', 'maintenance' );
            $maintenance['mode'] = in_array( sanitize_key( $_POST['loopkit_maintenance_mode'] ?? '' ), $allowed_modes, true )
                ? sanitize_key( $_POST['loopkit_maintenance_mode'] )
                : 'disabled';

            $maintenance['template_id'] = absint( $_POST['loopkit_maintenance_template'] ?? 0 );

            $allowed_access = array( 'logged_in', 'admins_only' );
            $maintenance['who_access'] = in_array( sanitize_key( $_POST['loopkit_maintenance_access'] ?? '' ), $allowed_access, true )
                ? sanitize_key( $_POST['loopkit_maintenance_access'] )
                : 'logged_in';

            update_option( 'loopkit_maintenance_settings', $maintenance );
            echo '<div class="lk-notice lk-notice-success"><p>' . esc_html__( 'Maintenance settings saved.', 'loopkit' ) . '</p></div>';
        }

        $this->render_header( esc_html__( 'Tools', 'loopkit' ), 'tools' );

        $tab = sanitize_key( $_GET['tab'] ?? 'general' );
        $maintenance = Loopkit_Tools::get_maintenance();
        $templates   = Loopkit_Templates::get_all();
        ?>

        <div class="lk-tabs-wrap">
            <div class="lk-tabs-nav">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-tools&tab=general' ) ); ?>"
                   class="lk-tab <?php echo $tab === 'general' ? 'lk-tab-active' : ''; ?>">
                    <?php esc_html_e( 'General', 'loopkit' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-tools&tab=replace-url' ) ); ?>"
                   class="lk-tab <?php echo $tab === 'replace-url' ? 'lk-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Replace URL', 'loopkit' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-tools&tab=maintenance' ) ); ?>"
                   class="lk-tab <?php echo $tab === 'maintenance' ? 'lk-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Maintenance Mode', 'loopkit' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-tools&tab=modules' ) ); ?>"
                   class="lk-tab <?php echo $tab === 'modules' ? 'lk-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Modules', 'loopkit' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-tools&tab=danger' ) ); ?>"
                   class="lk-tab <?php echo $tab === 'danger' ? 'lk-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Remove Data', 'loopkit' ); ?>
                </a>
            </div>

            <?php if ( 'general' === $tab ) : ?>
            <div class="lk-tab-content">
                <div class="lk-card">
                    <div class="lk-card-header">
                        <h3><?php esc_html_e( 'Clear Files & Data', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'Delete all generated Loopkit CSS cache files. They will regenerate on the next page load.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body">
                        <button type="button" class="lk-btn lk-btn-danger" id="lk-clear-cache">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e( 'Clear Files & Data', 'loopkit' ); ?>
                        </button>
                        <div id="lk-cache-result" class="lk-tool-result"></div>
                    </div>
                </div>

                <div class="lk-card">
                    <div class="lk-card-header">
                        <h3><?php esc_html_e( 'Update Loopkit', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc">
                            <?php esc_html_e( 'Upload a newer Loopkit plugin or theme zip. The existing copy is replaced in place — no need to deactivate, delete or reinstall, and your pages, templates and settings stay exactly as they are.', 'loopkit' ); ?>
                        </p>
                    </div>
                    <div class="lk-card-body">
                        <?php
                        $lk_theme         = wp_get_theme( 'loopkit' );
                        $lk_theme_version = $lk_theme->exists() ? $lk_theme->get( 'Version' ) : '';
                        ?>
                        <p class="lk-update-current">
                            <strong><?php esc_html_e( 'Installed now:', 'loopkit' ); ?></strong>
                            <?php
                            /* translators: %s: plugin version */
                            printf( esc_html__( 'Plugin %s', 'loopkit' ), esc_html( LOOPKIT_VERSION ) );
                            if ( $lk_theme_version ) {
                                /* translators: %s: theme version */
                                echo ' &middot; ' . esc_html( sprintf( __( 'Theme %s', 'loopkit' ), $lk_theme_version ) );
                            }
                            ?>
                        </p>

                        <form method="post" enctype="multipart/form-data" class="lk-update-form">
                            <?php wp_nonce_field( 'loopkit_upload_update', 'loopkit_update_nonce' ); ?>
                            <input type="hidden" name="loopkit_action" value="upload_update">
                            <label class="lk-update-label" for="lk-update-file"><?php esc_html_e( 'Loopkit plugin or theme zip', 'loopkit' ); ?></label>
                            <input type="file" id="lk-update-file" name="loopkit_package" accept=".zip,application/zip" required>
                            <button type="submit" class="lk-btn lk-btn-primary">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e( 'Upload &amp; Replace', 'loopkit' ); ?>
                            </button>
                        </form>
                        <p class="lk-card-desc" style="margin-top:10px">
                            <?php
                            printf(
                                /* translators: %s: maximum upload size */
                                esc_html__( 'Loopkit only accepts its own plugin and theme packages here; any other zip is rejected. Maximum upload size on this server: %s.', 'loopkit' ),
                                esc_html( size_format( wp_max_upload_size() ) )
                            );
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( 'danger' === $tab ) : ?>
            <div class="lk-tab-content">
                <?php if ( ! empty( $_GET['lk_removed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                    <div class="notice notice-<?php echo '1' === $_GET['lk_removed'] ? 'success' : 'error'; // phpcs:ignore WordPress.Security.NonceVerification ?> is-dismissible"><p><?php
                        echo '1' === $_GET['lk_removed'] // phpcs:ignore WordPress.Security.NonceVerification
                            ? esc_html__( 'Loopkit data removed.', 'loopkit' )
                            : esc_html__( 'Type DELETE to confirm.', 'loopkit' );
                    ?></p></div>
                <?php endif; ?>

                <div class="lk-card lk-danger-card">
                    <div class="lk-card-header">
                        <h3><?php esc_html_e( 'Remove all Loopkit data', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'Deletes every template, the builder content on your pages, all Loopkit settings and the generated CSS files. Your pages and posts themselves stay — they simply go back to the theme\'s own layout. This cannot be undone, so take a backup first.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body">
                        <form method="post" onsubmit="return confirm('<?php esc_attr_e( 'Really remove all Loopkit data? This cannot be undone.', 'loopkit' ); ?>')">
                            <?php wp_nonce_field( 'loopkit_remove_all' ); ?>
                            <input type="hidden" name="loopkit_action" value="remove_all_data">

                            <p><label class="lk-auto-check"><input type="checkbox" name="include_module_data" value="1"> <?php esc_html_e( 'Also delete module content: field groups, products and orders', 'loopkit' ); ?></label></p>
                            <p><label class="lk-auto-check"><input type="checkbox" name="include_submissions" value="1"> <?php esc_html_e( 'Also drop the submissions table (form entries and shop leads)', 'loopkit' ); ?></label></p>

                            <p class="lk-auto-field" style="max-width:320px">
                                <label for="lk-remove-confirm"><?php esc_html_e( 'Type DELETE to confirm', 'loopkit' ); ?></label>
                                <input type="text" id="lk-remove-confirm" name="confirm" placeholder="DELETE" autocomplete="off" required>
                            </p>

                            <button type="submit" class="lk-btn lk-btn-danger"><?php esc_html_e( 'Remove all data', 'loopkit' ); ?></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( 'modules' === $tab ) : ?>
            <div class="lk-tab-content">
                <div class="lk-card">
                    <div class="lk-card-header">
                        <h3><?php esc_html_e( 'Modules', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( "Built-in features that stay switched off until you need them. A module that is off loads no code at all — turn one on and it appears in the menu straight away, with its Dynamic Tags available in the editor.", 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body">
                        <?php
                        $lk_modules = Loopkit_Modules::get_modules();
                        $lk_states  = Loopkit_Modules::get_states();
                        ?>
                        <div class="lk-modules-grid">
                            <?php foreach ( $lk_modules as $lk_id => $lk_module ) : $lk_on = ! empty( $lk_states[ $lk_id ] ); ?>
                                <div class="lk-module-card <?php echo $lk_on ? 'lk-module-on' : ''; ?>">
                                    <div class="lk-module-top">
                                        <span class="lk-module-icon dashicons <?php echo esc_attr( $lk_module['icon'] ); ?>"></span>
                                        <div class="lk-module-name">
                                            <strong><?php echo esc_html( $lk_module['name'] ); ?></strong>
                                            <span class="lk-module-short"><?php echo esc_html( $lk_module['short'] ); ?></span>
                                        </div>
                                        <form method="post" class="lk-module-form">
                                            <?php wp_nonce_field( 'loopkit_modules' ); ?>
                                            <input type="hidden" name="loopkit_action" value="toggle_module">
                                            <input type="hidden" name="module" value="<?php echo esc_attr( $lk_id ); ?>">
                                            <input type="hidden" name="enabled" value="<?php echo $lk_on ? '0' : '1'; ?>">
                                            <button type="submit" class="lk-module-toggle" role="switch" aria-checked="<?php echo $lk_on ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $lk_module['name'] ); ?>">
                                                <span class="lk-module-knob"></span>
                                            </button>
                                        </form>
                                    </div>
                                    <p class="lk-module-desc"><?php echo esc_html( $lk_module['description'] ); ?></p>
                                    <p class="lk-module-status"><?php echo $lk_on ? '<span class="lk-module-badge">' . esc_html__( 'Active', 'loopkit' ) . '</span>' : esc_html__( 'Not active', 'loopkit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( 'replace-url' === $tab ) : ?>
            <div class="lk-tab-content">
                <div class="lk-card">
                    <div class="lk-card-header">
                        <h3><?php esc_html_e( 'Replace URL', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'Replace URLs in all Loopkit page data. Useful after migrating your site to a new domain.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body">
                        <div class="lk-form-grid">
                            <div class="lk-form-field">
                                <label><?php esc_html_e( 'Old URL', 'loopkit' ); ?></label>
                                <input type="url" id="lk-old-url" class="lk-input lk-input-full" placeholder="https://old-site.com" />
                            </div>
                            <div class="lk-form-field">
                                <label><?php esc_html_e( 'New URL', 'loopkit' ); ?></label>
                                <input type="url" id="lk-new-url" class="lk-input lk-input-full" placeholder="https://new-site.com" />
                            </div>
                        </div>
                        <button type="button" class="lk-btn lk-btn-primary" id="lk-replace-url" style="margin-top:16px;">
                            <?php esc_html_e( 'Replace URL', 'loopkit' ); ?>
                        </button>
                        <div id="lk-replace-result" class="lk-tool-result"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( 'maintenance' === $tab ) : ?>
            <div class="lk-tab-content">
                <form method="post" action="">
                    <?php wp_nonce_field( 'loopkit_maintenance_save', 'loopkit_maintenance_nonce' ); ?>
                    <input type="hidden" name="loopkit_maintenance_submit" value="1" />

                    <div class="lk-card">
                        <div class="lk-card-header">
                            <h3><?php esc_html_e( 'Maintenance Mode', 'loopkit' ); ?></h3>
                            <p class="lk-card-desc"><?php esc_html_e( 'Restrict site access and display a maintenance or coming soon page.', 'loopkit' ); ?></p>
                        </div>
                        <div class="lk-card-body">
                            <div class="lk-form-field" style="margin-bottom:20px;">
                                <label><?php esc_html_e( 'Mode', 'loopkit' ); ?></label>
                                <select name="loopkit_maintenance_mode" class="lk-select">
                                    <option value="disabled" <?php selected( $maintenance['mode'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'loopkit' ); ?></option>
                                    <option value="coming_soon" <?php selected( $maintenance['mode'], 'coming_soon' ); ?>><?php esc_html_e( 'Coming Soon', 'loopkit' ); ?></option>
                                    <option value="maintenance" <?php selected( $maintenance['mode'], 'maintenance' ); ?>><?php esc_html_e( 'Maintenance', 'loopkit' ); ?></option>
                                </select>
                            </div>

                            <div class="lk-form-field" style="margin-bottom:20px;">
                                <label><?php esc_html_e( 'Template', 'loopkit' ); ?></label>
                                <select name="loopkit_maintenance_template" class="lk-select">
                                    <option value=""><?php esc_html_e( '— Select Template —', 'loopkit' ); ?></option>
                                    <?php foreach ( $templates as $tpl ) : ?>
                                        <option value="<?php echo esc_attr( $tpl->ID ); ?>" <?php selected( $maintenance['template_id'], $tpl->ID ); ?>>
                                            <?php echo esc_html( $tpl->post_title ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="lk-form-field">
                                <label><?php esc_html_e( 'Who Can Access', 'loopkit' ); ?></label>
                                <select name="loopkit_maintenance_access" class="lk-select">
                                    <option value="logged_in" <?php selected( $maintenance['who_access'], 'logged_in' ); ?>><?php esc_html_e( 'Logged-in Users', 'loopkit' ); ?></option>
                                    <option value="admins_only" <?php selected( $maintenance['who_access'], 'admins_only' ); ?>><?php esc_html_e( 'Administrators Only', 'loopkit' ); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="lk-btn lk-btn-primary lk-btn-lg">
                        <?php esc_html_e( 'Save Changes', 'loopkit' ); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <?php
        $this->render_footer();
    }

    /**
     * Render the System Info page.
     */
    public function render_system_info() {
        $this->render_header( esc_html__( 'System Info', 'loopkit' ), 'system-info' );

        global $wpdb;
        $theme = wp_get_theme();
        $plugins = get_plugins();
        $active_plugins = get_option( 'active_plugins', array() );

        $active_list = array();
        foreach ( $active_plugins as $plugin_path ) {
            if ( isset( $plugins[ $plugin_path ] ) ) {
                $active_list[] = $plugins[ $plugin_path ]['Name'] . ' (' . $plugins[ $plugin_path ]['Version'] . ')';
            }
        }

        $info = array(
            esc_html__( 'WordPress Version', 'loopkit' )   => get_bloginfo( 'version' ),
            esc_html__( 'PHP Version', 'loopkit' )         => phpversion(),
            esc_html__( 'MySQL Version', 'loopkit' )       => $wpdb->db_version(),
            esc_html__( 'Web Server', 'loopkit' )          => sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ?? esc_html__( 'Unknown', 'loopkit' ) ) ),
            esc_html__( 'Active Theme', 'loopkit' )        => $theme->get( 'Name' ) . ' (' . $theme->get( 'Version' ) . ')',
            esc_html__( 'Active Plugins', 'loopkit' )      => implode( "\n", $active_list ),
            esc_html__( 'Memory Limit', 'loopkit' )        => WP_MEMORY_LIMIT,
            esc_html__( 'Max Upload Size', 'loopkit' )     => size_format( wp_max_upload_size() ),
            esc_html__( 'Multisite', 'loopkit' )           => is_multisite() ? esc_html__( 'Yes', 'loopkit' ) : esc_html__( 'No', 'loopkit' ),
            esc_html__( 'Loopkit Version', 'loopkit' )     => LOOPKIT_VERSION,
        );

        // Build plain text version for copy
        $plain_text = "=== Loopkit System Info ===\n\n";
        foreach ( $info as $label => $value ) {
            $plain_text .= $label . ': ' . $value . "\n";
        }
        ?>

        <div class="lk-section-header-row">
            <h2 class="lk-section-title"><?php esc_html_e( 'System Info', 'loopkit' ); ?></h2>
            <button type="button" class="lk-btn lk-btn-outline" id="lk-copy-sysinfo">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e( 'Copy to Clipboard', 'loopkit' ); ?>
            </button>
        </div>

        <div class="lk-card">
            <div class="lk-card-body">
                <table class="lk-table lk-sysinfo-table">
                    <tbody>
                        <?php foreach ( $info as $label => $value ) : ?>
                            <tr>
                                <td class="lk-sysinfo-label"><?php echo esc_html( $label ); ?></td>
                                <td class="lk-sysinfo-value">
                                    <?php if ( strpos( $value, "\n" ) !== false ) : ?>
                                        <?php echo nl2br( esc_html( $value ) ); ?>
                                    <?php else : ?>
                                        <?php echo esc_html( $value ); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <textarea id="lk-sysinfo-text" class="lk-hidden" readonly><?php echo esc_textarea( $plain_text ); ?></textarea>

        <?php
        $this->render_footer();
    }
}

new Loopkit_Admin();
