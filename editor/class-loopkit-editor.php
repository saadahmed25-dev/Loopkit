<?php
/**
 * Loopkit Editor class.
 * Renders the full-screen visual editor page.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Editor {

    /**
     * Post ID.
     *
     * @var int
     */
    private $post_id;

    /**
     * Post object.
     *
     * @var WP_Post
     */
    private $post;

    /**
     * @var bool
     */
    public $is_template = false;

    /**
     * @var string
     */
    public $template_type = '';

    /**
     * @var string
     */
    public $template_type_label = '';

    /**
     * @var string
     */
    public $exit_url = '';

    /**
     * Constructor.
     *
     * @param int $post_id Post ID.
     */
    public function __construct( $post_id ) {
        $this->post_id             = absint( $post_id );
        $this->post                = get_post( $this->post_id );
        $this->is_template         = ( $this->post && 'loopkit_template' === $this->post->post_type );
        $this->template_type       = $this->is_template ? ( get_post_meta( $this->post_id, '_loopkit_template_type', true ) ?: 'page' ) : '';
        $this->template_type_label = $this->is_template ? ucfirst( $this->template_type ) : '';
        $this->exit_url            = $this->is_template
            ? admin_url( 'admin.php?page=loopkit-templates' )
            : admin_url( 'post.php?post=' . $this->post_id . '&action=edit' );
    }

    /**
     * Render the full editor page.
     */
    public function render() {
        // Prepare data before outputting anything
        $raw      = get_post_meta( $this->post_id, '_loopkit_data', true );
        $elements = $raw ? json_decode( $raw, true ) : array();
        if ( ! is_array( $elements ) ) {
            $elements = array();
        }
        $elements = $this->ensure_sections( $elements );

        $is_template         = ( 'loopkit_template' === $this->post->post_type );
        $template_type       = $is_template ? get_post_meta( $this->post_id, '_loopkit_template_type', true ) : '';
        $template_type_label = $is_template ? ucfirst( $template_type ?: 'template' ) : '';

        $exit_url = $is_template
            ? admin_url( 'admin.php?page=loopkit-templates' )
            : admin_url( 'post.php?post=' . $this->post_id . '&action=edit' );

        $preview_url = get_preview_post_link( $this->post );
        if ( ! $preview_url ) {
            $preview_url = get_permalink( $this->post_id );
        }
        $canvas_url = add_query_arg( array(
            'loopkit_canvas' => $this->post_id,
            '_wpnonce'       => wp_create_nonce( 'loopkit_canvas_' . $this->post_id ),
        ), $preview_url );

        $site_settings = Loopkit_Site_Settings::get_settings();

        $active_breakpoints = array( 'desktop', 'tablet', 'mobile' );
        if ( ! empty( $site_settings['active_breakpoints'] ) && is_array( $site_settings['active_breakpoints'] ) ) {
            $active_breakpoints = array_unique( array_merge( array( 'desktop', 'tablet', 'mobile' ), $site_settings['active_breakpoints'] ) );
        }

        $page_settings = get_post_meta( $this->post_id, '_loopkit_page_settings', true );
        if ( ! is_array( $page_settings ) ) {
            $page_settings = array();
        }

        // Enqueue code editor for Custom CSS and HTML widget
        $cm_settings = function_exists( 'wp_enqueue_code_editor' )
            ? wp_enqueue_code_editor( array( 'type' => 'text/css' ) )
            : false;
        $cm_html_settings = function_exists( 'wp_enqueue_code_editor' )
            ? wp_enqueue_code_editor( array( 'type' => 'text/html' ) )
            : false;

        $conditions = get_post_meta( $this->post_id, '_loopkit_conditions', true );
        if ( ! is_array( $conditions ) ) {
            $conditions = array();
        }

        $globals_data = array();
        $globals_posts = get_posts( array(
            'post_type'      => 'loopkit_template',
            'posts_per_page' => -1,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'meta_key'       => '_loopkit_is_global',
            'meta_value'     => '1',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        foreach ( $globals_posts as $gp ) {
            $g_raw   = get_post_meta( $gp->ID, '_loopkit_data', true );
            $g_els   = $g_raw ? json_decode( $g_raw, true ) : array();
            $g_usage = get_post_meta( $gp->ID, '_loopkit_global_usage', true );
            $g_usage = is_array( $g_usage ) ? $g_usage : array();
            $globals_data[ $gp->ID ] = array(
                'id'         => $gp->ID,
                'title'      => $gp->post_title,
                'type'       => get_post_meta( $gp->ID, '_loopkit_template_type', true ) ?: 'section',
                'usageCount' => count( $g_usage ),
                'master'     => ! empty( $g_els ) ? $g_els[0] : null,
                'elements'   => $g_els,
                'css'        => get_post_meta( $gp->ID, '_loopkit_css', true ) ?: '',
            );
        }

        $editor_data = array(
            'postId'             => $this->post_id,
            'postTitle'          => $this->post->post_title,
            'postStatus'         => $this->post->post_status,
            'isTemplate'         => $is_template,
            'templateType'       => $template_type,
            'templateTypeLabel'  => $template_type_label,
            'conditions'         => $conditions,
            'pageSettings'       => $page_settings,
            'elements'           => $elements,
            'globals'            => $globals_data,
            'siteSettings'       => $site_settings,
            'siteIdentity'       => array(
                'title'         => get_option( 'blogname' ),
                'tagline'       => get_option( 'blogdescription' ),
                'site_icon'     => (int) get_option( 'site_icon' ),
                'site_icon_url' => get_option( 'site_icon' ) ? wp_get_attachment_image_url( get_option( 'site_icon' ), 'full' ) : '',
            ),
            'userPreferences'    => ( $uid = get_current_user_id() ) ? ( get_user_meta( $uid, '_loopkit_user_preferences', true ) ?: array() ) : array(),
            'activeBreakpoints'  => array_values( $active_breakpoints ),
            'canvasUrl'          => $canvas_url,
            'previewUrl'         => get_permalink( $this->post_id ),
            'exitUrl'            => $exit_url,
            'dashboardUrl'       => admin_url( 'admin.php?page=loopkit' ),
            'siteSettingsUrl'    => admin_url( 'admin.php?page=loopkit-site-settings' ),
            'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
            'templatesNonce'     => wp_create_nonce( 'loopkit_templates_nonce' ),
            'restNonce'          => wp_create_nonce( 'wp_rest' ),
            'restBase'           => rest_url( 'loopkit/v1/' ),
            'restUrl'            => get_rest_url(),
            'codeEditorSettings' => $cm_settings ?: array( 'codemirror' => array( 'mode' => 'css', 'lineNumbers' => true ) ),
            'breakpoints'        => array(
                'desktop' => 99999,
                'tablet'  => 1024,
                'mobile'  => 767,
            ),
            'strings' => array(
                'unsavedChanges'   => esc_html__( 'You have unsaved changes. Are you sure you want to leave?', 'loopkit' ),
                'saved'            => esc_html__( 'Saved', 'loopkit' ),
                'saving'           => esc_html__( 'Saving...', 'loopkit' ),
                'publish'          => esc_html__( 'Publish', 'loopkit' ),
                'update'           => esc_html__( 'Update', 'loopkit' ),
                'saveDraft'        => esc_html__( 'Save Draft', 'loopkit' ),
                'saveTemplate'     => esc_html__( 'Save as Template', 'loopkit' ),
                'exitToDashboard'  => esc_html__( 'Exit to WordPress', 'loopkit' ),
                'siteSettings'     => esc_html__( 'Site Settings', 'loopkit' ),
                'userPreferences'  => esc_html__( 'User Preferences', 'loopkit' ),
                'keyboardShortcuts' => esc_html__( 'Keyboard Shortcuts', 'loopkit' ),
                'finder'           => esc_html__( 'Finder', 'loopkit' ),
                'pageSettings'     => esc_html__( 'Page Settings', 'loopkit' ),
                'viewPage'         => esc_html__( 'View Page', 'loopkit' ),
                'navigator'        => esc_html__( 'Structure', 'loopkit' ),
                'history'          => esc_html__( 'History', 'loopkit' ),
                'addElement'       => esc_html__( 'Add Element', 'loopkit' ),
                'preview'          => esc_html__( 'Preview Changes', 'loopkit' ),
                'desktop'          => esc_html__( 'Desktop', 'loopkit' ),
                'tablet'           => esc_html__( 'Tablet', 'loopkit' ),
                'mobile'           => esc_html__( 'Mobile', 'loopkit' ),
                'searchWidget'     => esc_html__( 'Search Widget...', 'loopkit' ),
                'editSection'      => esc_html__( 'Edit Section', 'loopkit' ),
                'editContainer'    => esc_html__( 'Edit Container', 'loopkit' ),
                'editHeading'      => esc_html__( 'Edit Heading', 'loopkit' ),
                'elementsTitle'    => esc_html__( 'Elements', 'loopkit' ),
            ),
            'imageSizes'         => Loopkit_Images::get_image_sizes(),
            'iconsUrl'           => Loopkit_Icons::get_font_awesome_url(),
            'pluginUrl'          => LOOPKIT_PLUGIN_URL,
            // Used by the Site Title / Site Logo canvas previews
            'canEditJs'          => current_user_can( 'manage_options' ),
            'dynamicTags'        => class_exists( 'Loopkit_Dynamic' ) ? Loopkit_Dynamic::get_tags() : array(),
            'fontFamilies'       => class_exists( 'Loopkit_Fonts' ) ? Loopkit_Fonts::get_picker_list() : array(),
            'conditionPostTypes' => array_map( function( $pt ) { return array( 'slug' => $pt->name, 'label' => $pt->labels->singular_name ); }, array_values( get_post_types( array( 'public' => true ), 'objects' ) ) ),
            'conditionTaxonomies' => array_map( function( $tx ) { return array( 'slug' => $tx->name, 'label' => $tx->labels->singular_name ); }, array_values( get_taxonomies( array( 'public' => true ), 'objects' ) ) ),
            'popupSettings'      => ( class_exists( 'Loopkit_Popups' ) && 'popup' === $template_type ) ? Loopkit_Popups::get_settings( $this->post_id ) : array(),
            'siteTitle'          => get_bloginfo( 'name' ),
            'siteTagline'        => get_bloginfo( 'description' ),
            'userName'           => wp_get_current_user()->display_name,
            'homeUrl'            => home_url( '/' ),
            'customLogoUrl'      => ( get_theme_mod( 'custom_logo' ) ? (string) wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' ) : '' ),
            'widgets'            => $this->panel_widgets( $template_type, $elements ),
            // Post widgets: data of the post shown in the canvas (same array the PHP renderers use)
            'previewPostId'      => class_exists( 'Loopkit_Post_Data' ) ? Loopkit_Post_Data::get_preview_post_id( $this->post_id ) : 0,
            'previewPost'        => class_exists( 'Loopkit_Post_Data' ) ? ( Loopkit_Post_Data::get_data( Loopkit_Post_Data::get_preview_post_id( $this->post_id ), true ) ?: Loopkit_Post_Data::get_sample_data() ) : null,
            'previewPostOptions' => ( class_exists( 'Loopkit_Post_Data' ) && in_array( $template_type, array( 'single', 'loop-item' ), true ) ) ? Loopkit_Post_Data::get_preview_options() : array(),
            // Archive widgets: data of the archive shown in the canvas (Archive templates only)
            'previewArchiveKey'     => ( class_exists( 'Loopkit_Archive_Data' ) && 'archive' === $template_type ) ? Loopkit_Archive_Data::get_preview_key( $this->post_id ) : '',
            'previewArchive'        => ( class_exists( 'Loopkit_Archive_Data' ) && 'archive' === $template_type ) ? Loopkit_Archive_Data::preview( Loopkit_Archive_Data::get_preview_key( $this->post_id ) ) : null,
            'previewArchiveOptions' => ( class_exists( 'Loopkit_Archive_Data' ) && 'archive' === $template_type ) ? Loopkit_Archive_Data::get_preview_options() : array(),
            'isAdmin'                => current_user_can( 'manage_options' ),
            'canUnfilteredHtml'      => current_user_can( 'unfiltered_html' ),
            'editorNonce'            => wp_create_nonce( 'loopkit_editor_nonce' ),
            'codeEditorSettings'     => $cm_settings,
            'codeEditorHtmlSettings' => $cm_html_settings,
        );

        // Enqueue media library & WP editor. The builder runs on its own screen rather than
        // an ordinary admin page, so the admin styles the media modal is built on top of are
        // not there by default and the picker comes out unstyled.
        wp_enqueue_media();
        wp_enqueue_style( 'common' );
        wp_enqueue_style( 'forms' );
        wp_enqueue_style( 'buttons' );
        wp_enqueue_style( 'media' );
        if ( function_exists( 'wp_enqueue_editor' ) ) {
            wp_enqueue_editor();
        }

        // Enqueue scripts and styles
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style(
            'loopkit-hover-animations',
            LOOPKIT_PLUGIN_URL . 'assets/css/loopkit-hover-animations.css',
            array(),
            LOOPKIT_VERSION
        );
        wp_enqueue_style(
            'loopkit-editor-css',
            LOOPKIT_PLUGIN_URL . 'editor/css/loopkit-editor.css',
            array( 'dashicons', 'loopkit-hover-animations' ),
            LOOPKIT_VERSION
        );
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script(
            'loopkit-css-js',
            LOOPKIT_PLUGIN_URL . 'editor/js/loopkit-css.js',
            array( 'jquery' ),
            LOOPKIT_VERSION,
            true
        );
        wp_enqueue_script(
            'loopkit-controls-js',
            LOOPKIT_PLUGIN_URL . 'editor/js/loopkit-controls.js',
            array( 'jquery', 'loopkit-css-js' ),
            LOOPKIT_VERSION,
            true
        );
        wp_enqueue_script(
            'loopkit-editor-js',
            LOOPKIT_PLUGIN_URL . 'editor/js/loopkit-editor.js',
            array( 'jquery', 'loopkit-controls-js', 'loopkit-css-js' ),
            LOOPKIT_VERSION,
            true
        );
        $editor_data['ajaxUrl']      = admin_url( 'admin-ajax.php' );
        $editor_data['libraryNonce'] = wp_create_nonce( 'loopkit_library' );

        wp_localize_script( 'loopkit-css-js', 'loopkitEditor', $editor_data );
        wp_localize_script( 'loopkit-editor-js', 'loopkitEditor', $editor_data );

        /** Anything that wants to add itself to the builder shell. */
        do_action( 'loopkit_editor_enqueue' );

        $this->output_html();
    }

    /**
     * Widget definitions for the panel.
     *
     * Widgets carry a context — a Post Content or Archive Title element is only offered
     * inside Single or Archive templates — but a page or post can already contain one
     * (a page built with every widget does), and it still has to be editable. Anything
     * already used in the document is therefore added to the list the panel gets.
     *
     * @param string $template_type Current template type, empty for a page or post.
     * @param array  $elements      Elements of the document.
     * @return array
     */
    private function panel_widgets( $template_type, $elements ) {
        if ( ! class_exists( 'Loopkit_Widgets_Registry' ) ) {
            return array();
        }

        $registry = Loopkit_Widgets_Registry::instance();
        $widgets  = $registry->get_widgets_for_editor( $template_type ?: 'post' );

        $used    = array();
        $collect = function( $els ) use ( &$collect, &$used ) {
            foreach ( (array) $els as $el ) {
                if ( 'widget' === ( $el['elType'] ?? '' ) && ! empty( $el['widgetType'] ) ) {
                    $used[ $el['widgetType'] ] = true;
                }
                if ( ! empty( $el['elements'] ) ) {
                    $collect( $el['elements'] );
                }
            }
        };
        $collect( $elements );

        foreach ( array_keys( $used ) as $type ) {
            if ( isset( $widgets[ $type ] ) ) {
                continue;
            }
            $widget = $registry->get_widget( $type );
            if ( ! $widget ) {
                continue;
            }
            $widgets[ $type ] = array(
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

        return $widgets;
    }

    /**
     * Ensure root level elements are Sections.
     * Converts any root-level container to a Section preserving ID and settings.
     * Wraps any root-level widget into a Section.
     *
     * @param array $elements Elements list.
     * @return array
     */
    private function ensure_sections( $elements ) {
        if ( ! is_array( $elements ) ) {
            return array();
        }
        $site_settings = Loopkit_Site_Settings::get_settings();
        $default_width = ! empty( $site_settings['container_width'] ) ? intval( $site_settings['container_width'] ) : 1140;

        $wrapped = array();
        foreach ( $elements as $el ) {
            if ( ! is_array( $el ) ) {
                continue;
            }

            $el_type = $el['elType'] ?? '';

            if ( 'widget' === $el_type ) {
                // Wrap widget in a new Section
                $wrapped[] = array(
                    'id'       => loopkit_generate_id(),
                    'elType'   => 'section',
                    'isInner'  => false,
                    'settings' => array(
                        'container_type' => 'flexbox',
                        'content_width'  => 'boxed',
                        'width'          => array( 'size' => $default_width, 'unit' => 'px' ),
                        'flex_direction' => 'column',
                        'flex_gap'       => array( 'column' => '20', 'row' => '20', 'unit' => 'px', 'isLinked' => true ),
                        'padding'        => array( 'top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10', 'unit' => 'px', 'isLinked' => true ),
                        'html_tag'       => 'section',
                    ),
                    'elements' => array( $el ),
                );
            } elseif ( 'container' === $el_type ) {
                // Convert root container to Section preserving id and all settings
                $converted             = $el;
                $converted['elType']   = 'section';
                $converted['settings'] = is_array( $el['settings'] ?? null ) ? $el['settings'] : array();
                if ( empty( $converted['settings']['html_tag'] ) || 'div' === $converted['settings']['html_tag'] ) {
                    $converted['settings']['html_tag'] = 'section';
                }
                $wrapped[] = $converted;
            } else {
                $wrapped[] = $el;
            }
        }
        return $wrapped;
    }

    /**
     * Output the editor HTML.
     */
    private function output_html() {
        $is_template         = $this->is_template;
        $template_type       = $this->template_type;
        $template_type_label = $this->template_type_label;
        $exit_url            = $this->exit_url;
        $is_published        = ( $this->post && 'publish' === $this->post->post_status );
        $publish_text        = $is_published
            ? esc_html__( 'Update', 'loopkit' )
            : esc_html__( 'Publish', 'loopkit' );
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php echo esc_html( sprintf( __( 'Loopkit — %s', 'loopkit' ), $this->post->post_title ) ); ?></title>
<?php
wp_print_styles();
wp_print_head_scripts();
wp_head();
?>
</head>
<body class="lk-editor-body">

<!-- ============ TOP BAR ============ -->
<div id="lk-topbar" class="lk-topbar" role="banner">
    <?php // Named so a screen reader user can tell what is being edited. ?>
    <h1 class="screen-reader-text"><?php
        printf(
            /* translators: %s: what is open in the builder */
            esc_html__( 'Editing: %s', 'loopkit' ),
            esc_html( get_the_title( $this->post_id ) ? get_the_title( $this->post_id ) : __( 'Untitled', 'loopkit' ) )
        );
    ?></h1>

    <!-- Top bar LEFT -->
    <div class="lk-topbar-section lk-topbar-left">

        <!-- Logo / Main menu -->
        <div class="lk-topbar-menu-wrap">
            <button type="button" class="lk-topbar-btn lk-topbar-logo-btn" id="lk-menu-toggle" title="<?php esc_attr_e( 'Loopkit Menu', 'loopkit' ); ?>">
                <span class="dashicons dashicons-admin-generic"></span>
            </button>
            <div class="lk-dropdown lk-menu-dropdown" id="lk-main-menu">
                <div class="lk-dropdown-item" id="lk-menu-site-settings">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e( 'Site Settings', 'loopkit' ); ?>
                </div>
                <div class="lk-dropdown-item" id="lk-menu-user-prefs">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e( 'User Preferences', 'loopkit' ); ?>
                </div>
                <div class="lk-dropdown-item" id="lk-menu-shortcuts">
                    <span class="dashicons dashicons-editor-help"></span>
                    <?php esc_html_e( 'Keyboard Shortcuts', 'loopkit' ); ?>
                </div>
                <div class="lk-dropdown-item" id="lk-menu-finder">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e( 'Finder', 'loopkit' ); ?>
                    <span class="lk-shortcut-hint">Ctrl+E</span>
                </div>
                <div class="lk-dropdown-sep"></div>
                <a href="<?php echo esc_url( $exit_url ); ?>" class="lk-dropdown-item" id="lk-exit-wordpress-btn">
                    <span class="dashicons dashicons-wordpress"></span>
                    <?php esc_html_e( 'Exit to WordPress', 'loopkit' ); ?>
                </a>
            </div>
        </div>

        <!-- Add Element -->
        <button type="button" class="lk-topbar-btn" id="lk-add-element-toggle" title="<?php esc_attr_e( 'Add Element', 'loopkit' ); ?>">
            <span class="dashicons dashicons-plus-alt2"></span>
        </button>

        <!-- Starter library: ready-made sections and pages -->
        <button type="button" class="lk-topbar-btn" id="lk-starter-library-toggle" title="<?php esc_attr_e( 'Starter Library', 'loopkit' ); ?>">
            <span class="dashicons dashicons-layout"></span>
        </button>

        <!-- Template Library (Ctrl+Shift+L) -->
        <button type="button" class="lk-topbar-btn" id="lk-templates-library-toggle" title="<?php esc_attr_e( 'Template Library (Ctrl+Shift+L)', 'loopkit' ); ?>">
            <span class="dashicons dashicons-category"></span>
        </button>

        <!-- Page / Template Settings -->
        <button type="button" class="lk-topbar-btn" id="lk-page-settings-btn" title="<?php echo $is_template ? esc_attr__( 'Template Settings', 'loopkit' ) : esc_attr__( 'Page Settings', 'loopkit' ); ?>">
            <span class="dashicons dashicons-admin-generic"></span>
        </button>

        <!-- Navigator / Structure -->
        <button type="button" class="lk-topbar-btn" id="lk-navigator-toggle" title="<?php esc_attr_e( 'Structure', 'loopkit' ); ?>">
            <span class="dashicons dashicons-networking"></span>
        </button>

        <!-- History -->
        <button type="button" class="lk-topbar-btn" id="lk-history-toggle" title="<?php esc_attr_e( 'History', 'loopkit' ); ?>">
            <span class="dashicons dashicons-backup"></span>
        </button>

    </div>

    <!-- Top bar CENTER -->
    <div class="lk-topbar-section lk-topbar-center">

        <!-- Page title dropdown -->
        <div class="lk-topbar-title-wrap">
            <button type="button" class="lk-topbar-title-btn" id="lk-title-toggle">
                <span class="lk-topbar-title-text" id="lk-topbar-title"><?php echo esc_html( $this->post->post_title ); ?></span>
                <?php if ( $is_template && ! empty( $template_type_label ) ) : ?>
                    <span class="lk-topbar-type-badge"><?php echo esc_html( $template_type_label ); ?></span>
                <?php endif; ?>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
            <div class="lk-dropdown lk-title-dropdown" id="lk-title-menu">
                <div class="lk-dropdown-item" id="lk-title-page-settings">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php echo $is_template ? esc_html__( 'Template Settings', 'loopkit' ) : esc_html__( 'Page Settings', 'loopkit' ); ?>
                </div>
                <a href="<?php echo esc_url( get_permalink( $this->post_id ) ); ?>" class="lk-dropdown-item" target="_blank">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php esc_html_e( 'View Page', 'loopkit' ); ?>
                </a>
                <div class="lk-dropdown-sep"></div>
                <a href="<?php echo esc_url( $exit_url ); ?>" class="lk-dropdown-item">
                    <span class="dashicons dashicons-wordpress"></span>
                    <?php esc_html_e( 'Exit to WordPress', 'loopkit' ); ?>
                </a>
            </div>
        </div>

        <!-- Responsive device switcher -->
        <div class="lk-responsive-switcher" id="lk-responsive-switcher">
            <button type="button" class="lk-device-btn lk-device-active" data-device="desktop" title="<?php esc_attr_e( 'Desktop', 'loopkit' ); ?>">
                <span class="dashicons dashicons-desktop"></span>
            </button>
            <button type="button" class="lk-device-btn" data-device="tablet" title="<?php esc_attr_e( 'Tablet', 'loopkit' ); ?>">
                <span class="dashicons dashicons-tablet"></span>
            </button>
            <button type="button" class="lk-device-btn" data-device="mobile" title="<?php esc_attr_e( 'Mobile', 'loopkit' ); ?>">
                <span class="dashicons dashicons-smartphone"></span>
            </button>
        </div>

    </div>

    <!-- Top bar RIGHT -->
    <div class="lk-topbar-section lk-topbar-right">

        <!-- Preview -->
        <button type="button" class="lk-topbar-btn" id="lk-preview-btn" title="<?php esc_attr_e( 'Preview Changes', 'loopkit' ); ?>">
            <span class="dashicons dashicons-visibility"></span>
        </button>

        <!-- Publish / Update -->
        <div class="lk-publish-wrap" id="lk-publish-wrap">
            <button type="button" class="lk-publish-btn" id="lk-publish-btn" disabled>
                <?php echo esc_html( $publish_text ); ?>
            </button>
            <button type="button" class="lk-publish-arrow" id="lk-publish-arrow"
                    aria-label="<?php esc_attr_e( 'More publish options', 'loopkit' ); ?>"
                    aria-haspopup="true" aria-expanded="false">
                <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
            </button>
            <div class="lk-dropdown lk-publish-dropdown" id="lk-publish-menu">
                <div class="lk-dropdown-item" id="lk-save-draft">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e( 'Save Draft', 'loopkit' ); ?>
                </div>
                <div class="lk-dropdown-item" id="lk-save-template">
                    <span class="dashicons dashicons-portfolio"></span>
                    <?php esc_html_e( 'Save as Template', 'loopkit' ); ?>
                </div>
                <?php if ( $is_template && in_array( $template_type, array( 'header', 'footer', 'single', 'archive', 'popup' ), true ) ) : ?>
                <div class="lk-dropdown-item" id="lk-display-conditions-btn">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php esc_html_e( 'Display Conditions', 'loopkit' ); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div><!-- .lk-topbar -->

<!-- ============ MAIN EDITOR ============ -->
<div id="lk-editor-main" class="lk-editor-main">

    <!-- LEFT PANEL -->
    <div id="lk-panel" class="lk-panel" role="complementary" aria-label="<?php esc_attr_e( 'Widgets and settings', 'loopkit' ); ?>">
        <div class="lk-panel-inner">

            <!-- Panel header -->
            <div class="lk-panel-header" id="lk-panel-header">
                <button type="button" class="lk-panel-header-btn" id="lk-panel-back-to-elements" title="<?php esc_attr_e( 'Elements', 'loopkit' ); ?>" style="display:none;">
                    <span class="dashicons dashicons-grid-view"></span>
                </button>
                <div class="lk-panel-header-title" id="lk-panel-header-title">
                    <span class="dashicons dashicons-screenoptions" id="lk-panel-header-icon"></span>
                    <span id="lk-panel-header-text"><?php esc_html_e( 'Elements', 'loopkit' ); ?></span>
                </div>
            </div>

            <!-- Panel Tabs (Section/Container = Layout | Style | Advanced; Heading = Content | Style | Advanced) -->
            <div class="lk-panel-tabs" id="lk-panel-tabs" style="display:none;">
                <button type="button" class="lk-tab-btn lk-tab-active" data-tab="layout" id="lk-tab-first"><?php esc_html_e( 'Layout', 'loopkit' ); ?></button>
                <button type="button" class="lk-tab-btn" data-tab="style"><?php esc_html_e( 'Style', 'loopkit' ); ?></button>
                <button type="button" class="lk-tab-btn" data-tab="advanced"><?php esc_html_e( 'Advanced', 'loopkit' ); ?></button>
            </div>

            <!-- Elements view (widget list: Section, Container, Heading + Globals) -->
            <div class="lk-panel-content" id="lk-panel-elements">

                <!-- Elements / Globals / Templates tabs -->
                <div class="lk-elements-tab-bar" id="lk-elements-tab-bar">
                    <button type="button" class="lk-elements-tab-btn lk-tab-active" data-elements-tab="elements">
                        <?php esc_html_e( 'Elements', 'loopkit' ); ?>
                    </button>
                    <button type="button" class="lk-elements-tab-btn" data-elements-tab="globals">
                        <?php esc_html_e( 'Globals', 'loopkit' ); ?>
                    </button>
                    <button type="button" class="lk-elements-tab-btn" data-elements-tab="templates">
                        <?php esc_html_e( 'Templates', 'loopkit' ); ?>
                    </button>
                </div>

                <!-- Tab 1: Elements -->
                <div class="lk-elements-tab-pane" id="lk-pane-elements">
                    <!-- Search -->
                    <div class="lk-panel-search">
                        <span class="dashicons dashicons-search lk-search-icon"></span>
                        <label for="lk-widget-search" class="screen-reader-text"><?php esc_html_e( 'Search widgets', 'loopkit' ); ?></label>
                        <input type="text" id="lk-widget-search" class="lk-widget-search"
                               placeholder="<?php esc_attr_e( 'Search Widget...', 'loopkit' ); ?>" />
                    </div>

                    <!-- Elements panel categories: Layout, Basic, General -->
                    <div class="lk-widget-categories" id="lk-widget-categories">

                        <!-- Layout category -->
                        <div class="lk-widget-category" data-category="layout">
                            <button type="button" class="lk-category-header" aria-expanded="true">
                                <span><?php esc_html_e( 'Layout', 'loopkit' ); ?></span>
                                <span class="dashicons dashicons-arrow-down-alt2 lk-category-arrow" aria-hidden="true"></span>
                            </button>
                            <div class="lk-category-widgets">
                                <div class="lk-widget-item" data-widget="section" data-keywords="section layout flexbox grid columns wrapper row" draggable="true">
                                    <span class="dashicons dashicons-format-aside"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Section', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="container" data-keywords="container layout flexbox grid inner block flex" draggable="true">
                                    <span class="dashicons dashicons-layout"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Container', 'loopkit' ); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Basic category -->
                        <div class="lk-widget-category" data-category="basic">
                            <button type="button" class="lk-category-header" aria-expanded="true">
                                <span><?php esc_html_e( 'Basic', 'loopkit' ); ?></span>
                                <span class="dashicons dashicons-arrow-down-alt2 lk-category-arrow" aria-hidden="true"></span>
                            </button>
                            <div class="lk-category-widgets">
                                <div class="lk-widget-item" data-widget="heading" data-keywords="heading title text h1 h2 h3 h4 h5 h6 typography" draggable="true">
                                    <span class="dashicons dashicons-heading"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Heading', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="image" data-keywords="image photo picture media figure graphic visual" draggable="true">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Image', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="text-editor" data-keywords="text editor paragraph content wysiwyg typography article copy" draggable="true">
                                    <span class="dashicons dashicons-editor-paragraph"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Text Editor', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="video" data-keywords="video youtube vimeo embed player media movie clip" draggable="true">
                                    <span class="dashicons dashicons-video-alt3"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Video', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="button" data-keywords="button link cta call to action action click url" draggable="true">
                                    <span class="dashicons dashicons-button"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Button', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="divider" data-keywords="divider line separator rule hr border break" draggable="true">
                                    <span class="dashicons dashicons-minus"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Divider', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="spacer" data-keywords="spacer space gap blank empty height margin padding" draggable="true">
                                    <span class="dashicons dashicons-sort"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Spacer', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="icon" data-keywords="icon star symbol fontawesome glyph mark badge" draggable="true">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Icon', 'loopkit' ); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- General category -->
                        <div class="lk-widget-category" data-category="general">
                            <button type="button" class="lk-category-header" aria-expanded="true">
                                <span><?php esc_html_e( 'General', 'loopkit' ); ?></span>
                                <span class="dashicons dashicons-arrow-down-alt2 lk-category-arrow" aria-hidden="true"></span>
                            </button>
                            <div class="lk-category-widgets">
                                <div class="lk-widget-item" data-widget="image-box" data-keywords="image box card media feature promo photo banner" draggable="true">
                                    <span class="dashicons dashicons-format-gallery"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Image Box', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="icon-box" data-keywords="icon box feature service card blurb perk benefit" draggable="true">
                                    <span class="dashicons dashicons-art"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Icon Box', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="icon-list" data-keywords="icon list bullet items check checklist ordered unordered" draggable="true">
                                    <span class="dashicons dashicons-list-view"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Icon List', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="social-icons" data-keywords="social icons share facebook twitter x youtube instagram linkedin follow" draggable="true">
                                    <span class="dashicons dashicons-share"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Social Icons', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="html" data-keywords="html code custom raw embed iframe script tag" draggable="true">
                                    <span class="dashicons dashicons-html"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'HTML', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="shortcode" data-keywords="shortcode plugin wordpress form contact dynamic snippet" draggable="true">
                                    <span class="dashicons dashicons-shortcode"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Shortcode', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="accordion" data-keywords="accordion interactive" draggable="true">
                                    <span class="dashicons dashicons-list-view"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Accordion', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="toggle" data-keywords="toggle interactive" draggable="true">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Toggle', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="tabs" data-keywords="tabs interactive" draggable="true">
                                    <span class="dashicons dashicons-index-card"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Tabs', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="counter" data-keywords="counter interactive" draggable="true">
                                    <span class="dashicons dashicons-performance"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Counter', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="progress-bar" data-keywords="progress bar interactive" draggable="true">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Progress Bar', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="alert" data-keywords="alert interactive" draggable="true">
                                    <span class="dashicons dashicons-warning"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Alert', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="star-rating" data-keywords="star rating interactive" draggable="true">
                                    <span class="dashicons dashicons-star-half"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Star Rating', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="testimonial" data-keywords="testimonial interactive" draggable="true">
                                    <span class="dashicons dashicons-format-quote"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Testimonial', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="image-carousel" data-keywords="image carousel" draggable="true">
                                    <span class="dashicons dashicons-slides"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Image Carousel', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="image-gallery" data-keywords="image gallery" draggable="true">
                                    <span class="dashicons dashicons-format-gallery"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Image Gallery', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="google-maps" data-keywords="google maps" draggable="true">
                                    <span class="dashicons dashicons-location-alt"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Google Maps', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="contact-form" data-keywords="contact form" draggable="true">
                                    <span class="dashicons dashicons-feedback"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Contact Form', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="tooltip" data-keywords="tooltip" draggable="true">
                                    <span class="dashicons dashicons-testimonial"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Tooltip', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="table-of-contents" data-keywords="table of contents" draggable="true">
                                    <span class="dashicons dashicons-editor-ol"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Table of Contents', 'loopkit' ); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Site category -->
                        <div class="lk-widget-category" data-category="site">
                            <button type="button" class="lk-category-header" aria-expanded="true">
                                <span><?php esc_html_e( 'Site', 'loopkit' ); ?></span>
                                <span class="dashicons dashicons-arrow-down-alt2 lk-category-arrow" aria-hidden="true"></span>
                            </button>
                            <div class="lk-category-widgets">
                                <div class="lk-widget-item" data-widget="site-logo" data-keywords="site logo brand identity custom header image" draggable="true">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Site Logo', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="site-title" data-keywords="site title name heading bloginfo brand identity" draggable="true">
                                    <span class="dashicons dashicons-admin-site"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Site Title', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="nav-menu" data-keywords="nav menu navigation navbar header links hamburger" draggable="true">
                                    <span class="dashicons dashicons-menu-alt3"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Nav Menu', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="search-form" data-keywords="search form find query input header site" draggable="true">
                                    <span class="dashicons dashicons-search"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Search Form', 'loopkit' ); ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if ( 'archive' === $template_type ) : // Archive widgets only inside Archive templates ?>
                        <!-- Archive category -->
                        <div class="lk-widget-category" data-category="archive">
                            <button type="button" class="lk-category-header" aria-expanded="true">
                                <span><?php esc_html_e( 'Archive', 'loopkit' ); ?></span>
                                <span class="dashicons dashicons-arrow-down-alt2 lk-category-arrow" aria-hidden="true"></span>
                            </button>
                            <div class="lk-category-widgets">
                                <div class="lk-widget-item" data-widget="archive-title" data-keywords="archive title category tag heading search" draggable="true">
                                    <span class="dashicons dashicons-editor-textcolor"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Archive Title', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="archive-description" data-keywords="archive description category tag text" draggable="true">
                                    <span class="dashicons dashicons-text"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Archive Description', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="pagination" data-keywords="pagination pages next previous archive" draggable="true">
                                    <span class="dashicons dashicons-ellipsis"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Pagination', 'loopkit' ); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Post category -->
                        <div class="lk-widget-category" data-category="post">
                            <button type="button" class="lk-category-header" aria-expanded="true">
                                <span><?php esc_html_e( 'Post', 'loopkit' ); ?></span>
                                <span class="dashicons dashicons-arrow-down-alt2 lk-category-arrow" aria-hidden="true"></span>
                            </button>
                            <div class="lk-category-widgets">
                                <?php if ( 'loop-item' !== $template_type ) : // a Loop Item cannot contain the grid that repeats it ?>
                                <div class="lk-widget-item" data-widget="loop-grid" data-keywords="loop grid posts query template cards archive" draggable="true">
                                    <span class="dashicons dashicons-screenoptions"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Loop Grid', 'loopkit' ); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ( class_exists( 'Loopkit_Modules' ) && Loopkit_Modules::is_enabled( 'woocommerce' ) ) : ?>
                                <div class="lk-widget-item" data-widget="product-grid" data-keywords="shop product cart store product grid" draggable="true">
                                    <span class="dashicons dashicons-grid-view"></span>
                                    <span class="lk-widget-name">Product Grid</span>
                                </div>
                                <div class="lk-widget-item" data-widget="add-to-cart" data-keywords="shop product cart store add to cart" draggable="true">
                                    <span class="dashicons dashicons-cart"></span>
                                    <span class="lk-widget-name">Add to Cart</span>
                                </div>
                                <div class="lk-widget-item" data-widget="product-price" data-keywords="shop product cart store product price" draggable="true">
                                    <span class="dashicons dashicons-tag"></span>
                                    <span class="lk-widget-name">Product Price</span>
                                </div>
                                <div class="lk-widget-item" data-widget="product-gallery" data-keywords="shop product cart store product gallery" draggable="true">
                                    <span class="dashicons dashicons-format-gallery"></span>
                                    <span class="lk-widget-name">Product Gallery</span>
                                </div>
                                <div class="lk-widget-item" data-widget="cart-icon" data-keywords="shop product cart store cart icon" draggable="true">
                                    <span class="dashicons dashicons-cart"></span>
                                    <span class="lk-widget-name">Cart Icon</span>
                                </div>
                                <div class="lk-widget-item" data-widget="cart-table" data-keywords="shop product cart store cart table" draggable="true">
                                    <span class="dashicons dashicons-list-view"></span>
                                    <span class="lk-widget-name">Cart</span>
                                </div>
                                <div class="lk-widget-item" data-widget="checkout-form" data-keywords="shop product cart store checkout form" draggable="true">
                                    <span class="dashicons dashicons-money-alt"></span>
                                    <span class="lk-widget-name">Checkout</span>
                                </div>
                                <div class="lk-widget-item" data-widget="product-categories" data-keywords="shop product cart store product categories" draggable="true">
                                    <span class="dashicons dashicons-category"></span>
                                    <span class="lk-widget-name">Product Categories</span>
                                </div>
                                <?php endif; ?>
                                <div class="lk-widget-item" data-widget="marquee" data-keywords="marquee ticker scroll slider logos infinite news" draggable="true">
                                    <span class="dashicons dashicons-controls-repeat"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Marquee', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="flip-box" data-keywords="flip box card hover 3d" draggable="true">
                                    <span class="dashicons dashicons-image-rotate"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Flip Box', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="price-table" data-keywords="price pricing table plan package" draggable="true">
                                    <span class="dashicons dashicons-money-alt"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Price Table', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="countdown" data-keywords="countdown timer clock offer deadline" draggable="true">
                                    <span class="dashicons dashicons-clock"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Countdown', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="animated-headline" data-keywords="animated headline typing rotate highlight" draggable="true">
                                    <span class="dashicons dashicons-editor-textcolor"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Animated Headline', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="call-to-action" data-keywords="cta call to action banner promo" draggable="true">
                                    <span class="dashicons dashicons-megaphone"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Call to Action', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="share-buttons" data-keywords="share social facebook whatsapp linkedin" draggable="true">
                                    <span class="dashicons dashicons-share"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Share Buttons', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="posts" data-keywords="posts grid list blog loop query archive recent" draggable="true">
                                    <span class="dashicons dashicons-grid-view"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Posts', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="post-title" data-keywords="post title heading single name" draggable="true">
                                    <span class="dashicons dashicons-editor-textcolor"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Post Title', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="post-featured-image" data-keywords="post featured image thumbnail single photo" draggable="true">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Featured Image', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="post-excerpt" data-keywords="post excerpt summary description single" draggable="true">
                                    <span class="dashicons dashicons-text"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Post Excerpt', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="post-info" data-keywords="post info meta date author time comments categories tags" draggable="true">
                                    <span class="dashicons dashicons-info-outline"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Post Info', 'loopkit' ); ?></span>
                                </div>
                                <?php if ( 'single' === $template_type ) : // Post Content only inside Single templates (a post cannot contain itself) ?>
                                <div class="lk-widget-item" data-widget="post-content" data-keywords="post content body text single" draggable="true">
                                    <span class="dashicons dashicons-media-document"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Post Content', 'loopkit' ); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="lk-widget-item" data-widget="author-box" data-keywords="author box bio avatar about post single" draggable="true">
                                    <span class="dashicons dashicons-id"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Author Box', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="post-navigation" data-keywords="post navigation previous next pagination single" draggable="true">
                                    <span class="dashicons dashicons-leftright"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Post Navigation', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-widget-item" data-widget="post-comments" data-keywords="comments discussion reply form post single" draggable="true">
                                    <span class="dashicons dashicons-admin-comments"></span>
                                    <span class="lk-widget-name"><?php esc_html_e( 'Comments', 'loopkit' ); ?></span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div><!-- #lk-pane-elements -->

                <!-- Tab 2: Globals -->
                <div class="lk-elements-tab-pane" id="lk-pane-globals" style="display:none;">
                    <!-- Globals Search -->
                    <div class="lk-panel-search">
                        <span class="dashicons dashicons-search lk-search-icon"></span>
                        <label for="lk-global-search" class="screen-reader-text"><?php esc_html_e( 'Search templates', 'loopkit' ); ?></label>
                        <input type="text" id="lk-global-search" class="lk-widget-search"
                               placeholder="<?php esc_attr_e( 'Search Globals...', 'loopkit' ); ?>" />
                    </div>

                    <!-- Globals list container -->
                    <div class="lk-globals-list" id="lk-globals-list">
                        <!-- Rendered by JS -->
                    </div>

                    <!-- Globals empty state -->
                    <div class="lk-globals-empty" id="lk-globals-empty" style="display:none;">
                        <span class="dashicons dashicons-admin-site-alt3 lk-empty-globe-icon"></span>
                        <p class="lk-empty-title"><?php esc_html_e( 'No globals yet', 'loopkit' ); ?></p>
                        <p class="lk-empty-desc"><?php esc_html_e( 'Right-click any Section or Container and choose Save as Global.', 'loopkit' ); ?></p>
                    </div>
                </div><!-- #lk-pane-globals -->

                <!-- Tab 3: Templates -->
                <div class="lk-elements-tab-pane" id="lk-pane-templates" style="display:none;">
                    <!-- Templates Search -->
                    <div class="lk-panel-search">
                        <span class="dashicons dashicons-search lk-search-icon"></span>
                        <input type="text" id="lk-template-search" class="lk-widget-search"
                               placeholder="<?php esc_attr_e( 'Search Templates...', 'loopkit' ); ?>" />
                    </div>

                    <!-- Filter Chips: All | Sections | Containers -->
                    <div class="lk-template-filter-chips" id="lk-panel-template-filters">
                        <button type="button" class="lk-filter-chip lk-chip-active" data-filter="all"><?php esc_html_e( 'All', 'loopkit' ); ?></button>
                        <button type="button" class="lk-filter-chip" data-filter="section"><?php esc_html_e( 'Sections', 'loopkit' ); ?></button>
                        <button type="button" class="lk-filter-chip" data-filter="container"><?php esc_html_e( 'Containers', 'loopkit' ); ?></button>
                    </div>

                    <!-- Templates list container -->
                    <div class="lk-templates-list" id="lk-templates-list">
                        <!-- Rendered by JS -->
                    </div>

                    <!-- Templates empty state -->
                    <div class="lk-templates-empty" id="lk-templates-empty" style="display:none;">
                        <span class="dashicons dashicons-category lk-empty-globe-icon"></span>
                        <p class="lk-empty-title"><?php esc_html_e( 'No templates yet', 'loopkit' ); ?></p>
                        <p class="lk-empty-desc"><?php printf( esc_html__( 'Create Section or Container templates from the %1$sTemplates page%2$s.', 'loopkit' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=loopkit_template' ) ) . '" target="_blank">', '</a>' ); ?></p>
                    </div>
                </div><!-- #lk-pane-templates -->

            </div><!-- #lk-panel-elements -->

            <!-- Controls view (settings for selected element) -->
            <div class="lk-panel-content lk-panel-controls" id="lk-panel-controls" style="display:none;">
            </div>

            <!-- History view (Actions | Revisions) -->
            <div class="lk-panel-content lk-panel-history" id="lk-panel-history" style="display:none;">

                <!-- Actions / Revisions tabs -->
                <div class="lk-elements-tab-bar lk-history-tab-bar" id="lk-history-tab-bar">
                    <button type="button" class="lk-elements-tab-btn lk-tab-active" data-history-tab="actions">
                        <?php esc_html_e( 'Actions', 'loopkit' ); ?>
                    </button>
                    <button type="button" class="lk-elements-tab-btn" data-history-tab="revisions">
                        <?php esc_html_e( 'Revisions', 'loopkit' ); ?>
                    </button>
                </div>

                <!-- Tab 1: Actions -->
                <div class="lk-history-tab-pane" id="lk-pane-history-actions">
                    <div class="lk-history-actions-list" id="lk-history-actions-list">
                        <!-- Populated by JS -->
                    </div>
                    <div class="lk-history-empty" id="lk-history-actions-empty" style="display:none;">
                        <span class="dashicons dashicons-backup lk-empty-history-icon"></span>
                        <p class="lk-empty-title"><?php esc_html_e( 'No actions yet', 'loopkit' ); ?></p>
                        <p class="lk-empty-desc"><?php esc_html_e( 'As you make changes to your page, your actions will appear here.', 'loopkit' ); ?></p>
                    </div>
                </div>

                <!-- Tab 2: Revisions -->
                <div class="lk-history-tab-pane" id="lk-pane-history-revisions" style="display:none;">
                    <div class="lk-history-revisions-list" id="lk-history-revisions-list">
                        <!-- Populated by JS from REST -->
                    </div>
                    <div class="lk-history-empty" id="lk-history-revisions-empty" style="display:none;">
                        <span class="dashicons dashicons-backup lk-empty-history-icon"></span>
                        <p class="lk-empty-title"><?php esc_html_e( 'No revisions yet', 'loopkit' ); ?></p>
                        <p class="lk-empty-desc"><?php esc_html_e( 'Revisions are saved each time you update or publish the page.', 'loopkit' ); ?></p>
                    </div>
                    <!-- Preview bar fixed at bottom when previewing a revision -->
                    <div class="lk-revision-preview-bar" id="lk-revision-preview-bar" style="display:none;">
                        <div class="lk-revision-preview-info">
                            <span class="dashicons dashicons-visibility"></span>
                            <span id="lk-revision-preview-label"><?php esc_html_e( 'Previewing revision', 'loopkit' ); ?></span>
                        </div>
                        <div class="lk-revision-preview-actions">
                            <button type="button" class="lk-btn lk-btn-default lk-btn-sm" id="lk-revision-discard-btn">
                                <?php esc_html_e( 'Discard', 'loopkit' ); ?>
                            </button>
                            <button type="button" class="lk-btn lk-btn-primary lk-btn-sm" id="lk-revision-apply-btn">
                                <?php esc_html_e( 'Apply', 'loopkit' ); ?>
                            </button>
                        </div>
                    </div>
                </div>

            </div><!-- #lk-panel-history -->

            <!-- Site Settings view (D1) -->
            <div class="lk-panel-content lk-panel-site-settings" id="lk-panel-site-settings" style="display:none;">
                <div class="lk-site-settings-nav" id="lk-site-settings-nav">
                    <!-- Populated by JS -->
                </div>
                <div class="lk-site-settings-subscreen" id="lk-site-settings-subscreen" style="display:none;">
                    <!-- Populated by JS -->
                </div>
                <div class="lk-site-settings-footer" id="lk-site-settings-footer">
                    <button type="button" class="lk-btn lk-btn-primary lk-site-settings-save-btn" id="lk-site-settings-save">
                        <?php esc_html_e( 'Save', 'loopkit' ); ?>
                    </button>
                </div>
            </div>

            <!-- User Preferences view (D2) -->
            <div class="lk-panel-content lk-panel-user-prefs" id="lk-panel-user-prefs" style="display:none;">
                <!-- Populated by JS -->
            </div>

        </div><!-- .lk-panel-inner -->

        <!-- Resize handle -->
        <div class="lk-panel-resize" id="lk-panel-resize"></div>

        <!-- Collapse toggle -->
        <button type="button" class="lk-panel-collapse-btn" id="lk-panel-collapse" title="<?php esc_attr_e( 'Hide Panel', 'loopkit' ); ?>">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
        </button>
    </div><!-- .lk-panel -->

    <!-- CANVAS WRAP -->
    <div id="lk-canvas-wrap" class="lk-canvas-wrap">

        <!-- Responsive bar (visible on tablet/mobile modes) -->
        <div class="lk-responsive-bar" id="lk-responsive-bar" style="display:none;">
            <div class="lk-responsive-bar-inner">
                <!-- Width -->
                <div class="lk-rbar-field-wrap">
                    <span class="lk-rbar-label"><?php esc_html_e( 'W', 'loopkit' ); ?></span>
                    <input type="number" class="lk-rbar-input" id="lk-rbar-width" min="200" max="3840" step="1" title="<?php esc_attr_e( 'Canvas Width (px)', 'loopkit' ); ?>" />
                    <span class="lk-rbar-unit"><?php esc_html_e( 'PX', 'loopkit' ); ?></span>
                </div>

                <!-- Height -->
                <div class="lk-rbar-field-wrap">
                    <span class="lk-rbar-label"><?php esc_html_e( 'H', 'loopkit' ); ?></span>
                    <input type="number" class="lk-rbar-input" id="lk-rbar-height" min="100" max="3840" step="1" placeholder="<?php esc_attr_e( 'Auto', 'loopkit' ); ?>" title="<?php esc_attr_e( 'Canvas Height (px, blank for Auto)', 'loopkit' ); ?>" />
                    <span class="lk-rbar-unit"><?php esc_html_e( 'PX', 'loopkit' ); ?></span>
                </div>

                <!-- Scale -->
                <div class="lk-rbar-field-wrap lk-rbar-scale-wrap">
                    <select class="lk-rbar-select" id="lk-rbar-scale" title="<?php esc_attr_e( 'Preview Scale', 'loopkit' ); ?>">
                        <option value="100">100%</option>
                        <option value="85">85%</option>
                        <option value="75">75%</option>
                        <option value="50">50%</option>
                    </select>
                </div>

                <!-- Separator -->
                <div class="lk-rbar-sep"></div>

                <!-- Settings (Gear) -->
                <button type="button" class="lk-rbar-btn" id="lk-rbar-settings" title="<?php esc_attr_e( 'Breakpoint Settings', 'loopkit' ); ?>">
                    <span class="dashicons dashicons-admin-generic"></span>
                </button>

                <!-- Close (X) -->
                <button type="button" class="lk-rbar-btn lk-rbar-close" id="lk-rbar-close" title="<?php esc_attr_e( 'Exit Responsive Mode (Return to Desktop)', 'loopkit' ); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
        </div>

        <!-- Canvas iframe container -->
        <div class="lk-canvas-frame-wrap" id="lk-canvas-frame-wrap" role="main" aria-label="<?php esc_attr_e( 'Page canvas', 'loopkit' ); ?>">
            <iframe id="lk-canvas-iframe" class="lk-canvas-iframe" frameborder="0" title="<?php esc_attr_e( 'Page canvas', 'loopkit' ); ?>"></iframe>
        </div>

    </div><!-- .lk-canvas-wrap -->

    <!-- STRUCTURE (NAVIGATOR) PANEL -->
    <div id="lk-structure-panel" class="lk-structure-panel" style="display:none;">
        <div class="lk-structure-header" id="lk-structure-header">
            <div class="lk-structure-title">
                <span class="dashicons dashicons-networking"></span>
                <span><?php esc_html_e( 'Structure', 'loopkit' ); ?></span>
            </div>
            <div class="lk-structure-header-actions">
                <button type="button" class="lk-structure-btn" id="lk-structure-expand-all" title="<?php esc_attr_e( 'Expand / Collapse All', 'loopkit' ); ?>">
                    <span class="dashicons dashicons-sort"></span>
                </button>
                <button type="button" class="lk-structure-btn lk-structure-close" id="lk-structure-close" title="<?php esc_attr_e( 'Close', 'loopkit' ); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
        </div>
        <div class="lk-structure-body" id="lk-structure-body">
            <div class="lk-structure-tree" id="lk-structure-tree"></div>
            <div class="lk-structure-empty" id="lk-structure-empty" style="display:none;">
                <p><?php esc_html_e( 'No elements on page yet.', 'loopkit' ); ?></p>
            </div>
        </div>
        <!-- Resize handles for floating mode -->
        <div class="lk-structure-resizer lk-sr-e"></div>
        <div class="lk-structure-resizer lk-sr-w"></div>
        <div class="lk-structure-resizer lk-sr-s"></div>
        <div class="lk-structure-resizer lk-sr-se"></div>
    </div><!-- #lk-structure-panel -->

</div><!-- .lk-editor-main -->

<!-- Keyboard Shortcuts Modal -->
<div class="lk-modal-overlay" id="lk-shortcuts-modal" style="display:none;">
    <div class="lk-modal lk-shortcuts-modal">
        <div class="lk-modal-header">
            <h3><?php esc_html_e( 'Keyboard Shortcuts', 'loopkit' ); ?></h3>
            <button type="button" class="lk-modal-close" data-close="lk-shortcuts-modal">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="lk-modal-body">
            <div class="lk-shortcuts-grid">
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>S</kbd> <span><?php esc_html_e( 'Save', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>Z</kbd> <span><?php esc_html_e( 'Undo', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>Y</kbd> <span><?php esc_html_e( 'Redo', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>C</kbd> <span><?php esc_html_e( 'Copy', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>V</kbd> <span><?php esc_html_e( 'Paste', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>V</kbd> <span><?php esc_html_e( 'Paste Style', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>D</kbd> <span><?php esc_html_e( 'Duplicate', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Delete</kbd> <span><?php esc_html_e( 'Delete', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>E</kbd> <span><?php esc_html_e( 'Finder', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>K</kbd> <span><?php esc_html_e( 'Navigator', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>H</kbd> <span><?php esc_html_e( 'History', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>M</kbd> <span><?php esc_html_e( 'Responsive Mode', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>P</kbd> <span><?php esc_html_e( 'Toggle Panel', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Esc</kbd> <span><?php esc_html_e( 'Deselect / Close', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>/</kbd> <span><?php esc_html_e( 'Shortcuts', 'loopkit' ); ?></span></div>
                <div class="lk-shortcut-item"><kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>L</kbd> <span><?php esc_html_e( 'Templates Library', 'loopkit' ); ?></span></div>
            </div>
        </div>
    </div>
</div>

<!-- Display Conditions Modal (Header / Footer Theme Builder) -->
<div class="lk-modal-overlay" id="lk-conditions-modal" style="display:none;">
    <div class="lk-modal lk-conditions-modal">
        <div class="lk-modal-header">
            <div class="lk-modal-header-titles">
                <h3 id="lk-conditions-modal-title"><?php echo esc_html( sprintf( __( 'Where do you want to display your %s?', 'loopkit' ), $template_type_label ?: 'Template' ) ); ?></h3>
                <p class="lk-modal-sub"><?php esc_html_e( 'Set the conditions that determine where your template is used throughout your site.', 'loopkit' ); ?></p>
            </div>
            <button type="button" class="lk-modal-close" data-close="lk-conditions-modal" aria-label="<?php esc_attr_e( 'Close', 'loopkit' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="lk-modal-body">
            <div class="lk-conditions-conflict-notice" id="lk-conditions-conflict-notice" style="display:none;"></div>
            <div class="lk-conditions-list" id="lk-conditions-list">
                <!-- Condition rows rendered by JS -->
            </div>
            <div class="lk-conditions-add-wrap">
                <button type="button" class="lk-btn lk-btn-outline" id="lk-add-condition-btn">
                    <span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add Condition', 'loopkit' ); ?>
                </button>
            </div>
        </div>
        <div class="lk-modal-footer">
            <button type="button" class="lk-btn lk-btn-secondary lk-modal-cancel" id="lk-conditions-cancel-btn"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></button>
            <button type="button" class="lk-btn lk-btn-primary" id="lk-conditions-save-btn">
                <span class="lk-btn-text"><?php esc_html_e( 'Save & Close', 'loopkit' ); ?></span>
            </button>
        </div>
    </div>
</div>

<!-- Save as Global Modal -->
<div class="lk-modal-overlay" id="lk-save-global-modal" style="display:none;">
    <div class="lk-modal lk-save-global-modal">
        <div class="lk-modal-header">
            <div class="lk-modal-header-titles">
                <h3 id="lk-save-global-title"><?php esc_html_e( 'Save as Global Section', 'loopkit' ); ?></h3>
                <p class="lk-modal-sub">
                    <?php esc_html_e( 'Changes to a global update it everywhere it is used.', 'loopkit' ); ?>
                </p>
            </div>
            <button type="button" class="lk-modal-close" data-close="lk-save-global-modal" aria-label="<?php esc_attr_e( 'Close', 'loopkit' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="lk-modal-body">
            <div class="lk-form-field">
                <label for="lk-global-name-input" class="lk-modal-label">
                    <?php esc_html_e( 'Name', 'loopkit' ); ?> <span class="lk-required-star">*</span>
                </label>
                <label for="lk-global-name-input" class="screen-reader-text"><?php esc_html_e( 'Template name', 'loopkit' ); ?></label>
                <input type="text" id="lk-global-name-input" class="lk-input" placeholder="<?php esc_attr_e( 'e.g. Hero Section', 'loopkit' ); ?>" />
            </div>
            <div class="lk-error-msg" id="lk-save-global-error" style="display:none;"></div>
        </div>
        <div class="lk-modal-footer">
            <button type="button" class="lk-btn lk-btn-secondary lk-modal-cancel" id="lk-save-global-cancel"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></button>
            <button type="button" class="lk-btn lk-btn-primary lk-btn-global-save" id="lk-save-global-save-btn">
                <span class="lk-btn-text"><?php esc_html_e( 'Save', 'loopkit' ); ?></span>
            </button>
        </div>
    </div>
</div>

<!-- Delete Global Modal -->
<div class="lk-modal-overlay" id="lk-delete-global-modal" style="display:none;">
    <div class="lk-modal lk-delete-global-modal" style="max-width:440px;">
        <div class="lk-modal-header">
            <div class="lk-modal-header-titles">
                <h3><?php esc_html_e( 'Delete Global Template', 'loopkit' ); ?></h3>
            </div>
            <button type="button" class="lk-modal-close" data-close="lk-delete-global-modal" aria-label="<?php esc_attr_e( 'Close', 'loopkit' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="lk-modal-body">
            <p class="lk-modal-desc">
                <?php esc_html_e( 'Are you sure you want to delete this global template? This action cannot be undone.', 'loopkit' ); ?>
            </p>
            <div class="lk-error-msg" id="lk-delete-global-error" style="display:none;"></div>
        </div>
        <div class="lk-modal-footer">
            <button type="button" class="lk-btn lk-btn-secondary lk-modal-cancel" id="lk-delete-global-cancel"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></button>
            <button type="button" class="lk-btn lk-btn-danger" id="lk-delete-global-confirm-btn">
                <span class="lk-btn-text"><?php esc_html_e( 'Delete', 'loopkit' ); ?></span>
            </button>
        </div>
    </div>
</div>

<!-- Breakpoint Settings Modal -->
<div class="lk-modal-overlay" id="lk-breakpoints-modal" style="display:none;">
    <div class="lk-modal lk-breakpoints-modal" style="max-width:480px;">
        <div class="lk-modal-header">
            <h3><?php esc_html_e( 'Breakpoint Settings', 'loopkit' ); ?></h3>
            <button type="button" class="lk-modal-close" data-close="lk-breakpoints-modal" aria-label="<?php esc_attr_e( 'Close', 'loopkit' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="lk-modal-body">
            <p class="lk-modal-desc">
                <?php esc_html_e( 'Active responsive breakpoints for styling elements across different screen widths.', 'loopkit' ); ?>
            </p>
            <div class="lk-breakpoints-preview-list">
                <div class="lk-breakpoint-preview-row">
                    <span class="lk-breakpoint-preview-device">
                        <span class="dashicons dashicons-desktop"></span> <?php esc_html_e( 'Desktop', 'loopkit' ); ?>
                    </span>
                    <span class="lk-breakpoint-preview-note"><?php esc_html_e( 'Default (Full Width)', 'loopkit' ); ?></span>
                </div>
                <div class="lk-breakpoint-preview-row">
                    <span class="lk-breakpoint-preview-device">
                        <span class="dashicons dashicons-tablet"></span> <?php esc_html_e( 'Tablet', 'loopkit' ); ?>
                    </span>
                    <span class="lk-breakpoint-preview-val">&le; 1024px</span>
                </div>
                <div class="lk-breakpoint-preview-row">
                    <span class="lk-breakpoint-preview-device">
                        <span class="dashicons dashicons-smartphone"></span> <?php esc_html_e( 'Mobile', 'loopkit' ); ?>
                    </span>
                    <span class="lk-breakpoint-preview-val">&le; 767px</span>
                </div>
            </div>
            <div class="lk-breakpoints-modal-footer-row">
                <span class="lk-breakpoints-modal-hint"><?php esc_html_e( 'Configure additional breakpoints in Site Settings.', 'loopkit' ); ?></span>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-site-settings' ) ); ?>" target="_blank" class="lk-btn lk-btn-outline lk-breakpoints-open-btn">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e( 'Open Site Settings', 'loopkit' ); ?>
                </a>
            </div>
        </div>
        <div class="lk-modal-footer">
            <button type="button" class="lk-btn lk-btn-secondary lk-modal-close" data-close="lk-breakpoints-modal"><?php esc_html_e( 'Close', 'loopkit' ); ?></button>
        </div>
    </div>
</div>

<!-- Finder Command Palette Modal (D3) -->
<div class="lk-finder-modal" id="lk-finder-modal" style="display:none;">
    <div class="lk-finder-overlay" id="lk-finder-overlay"></div>
    <div class="lk-finder-box">
        <div class="lk-finder-header">
            <span class="dashicons dashicons-search lk-finder-search-icon"></span>
            <input type="text" id="lk-finder-input" class="lk-finder-input" placeholder="<?php esc_attr_e( 'Type a command or search...', 'loopkit' ); ?>" autocomplete="off" />
            <span class="lk-finder-esc-badge">ESC</span>
        </div>
        <div class="lk-finder-body" id="lk-finder-results">
            <!-- Rendered by JS -->
        </div>
    </div>
</div>

<!-- Site Settings Unsaved Changes Confirmation Modal (D1) -->
<div class="lk-modal-overlay" id="lk-unsaved-modal" style="display:none;">
    <div class="lk-modal lk-unsaved-modal" style="max-width:420px;">
        <div class="lk-modal-header">
            <h3><?php esc_html_e( 'Save Changes?', 'loopkit' ); ?></h3>
        </div>
        <div class="lk-modal-body">
            <p class="lk-modal-desc">
                <?php esc_html_e( 'You have unsaved changes in Site Settings. Do you want to save them before leaving?', 'loopkit' ); ?>
            </p>
        </div>
        <div class="lk-modal-footer lk-unsaved-modal-footer">
            <button type="button" class="lk-btn lk-btn-default" id="lk-unsaved-discard"><?php esc_html_e( 'Discard', 'loopkit' ); ?></button>
            <button type="button" class="lk-btn lk-btn-secondary" id="lk-unsaved-cancel"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></button>
            <button type="button" class="lk-btn lk-btn-primary" id="lk-unsaved-save"><?php esc_html_e( 'Save', 'loopkit' ); ?></button>
        </div>
    </div>
</div>

<!-- Templates Library Modal (Part E3) -->
<div class="lk-modal-overlay" id="lk-templates-library-modal" style="display:none;">
    <div class="lk-modal lk-templates-library-dialog" style="max-width:880px; width:92%; max-height:88vh; display:flex; flex-direction:column;">
        <div class="lk-modal-header">
            <div class="lk-modal-header-titles">
                <h3><?php esc_html_e( 'Template Library', 'loopkit' ); ?></h3>
                <p class="lk-modal-sub"><?php esc_html_e( 'Insert, export, or import Section and Container templates.', 'loopkit' ); ?></p>
            </div>
            <button type="button" class="lk-modal-close" data-close="lk-templates-library-modal" aria-label="<?php esc_attr_e( 'Close', 'loopkit' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="lk-templates-modal-toolbar">
            <div class="lk-panel-search lk-modal-search-wrap" style="margin:0; flex:1;">
                <span class="dashicons dashicons-search lk-search-icon"></span>
                <input type="text" id="lk-modal-template-search" class="lk-widget-search"
                       placeholder="<?php esc_attr_e( 'Search templates...', 'loopkit' ); ?>" />
            </div>
            <div class="lk-template-filter-chips" id="lk-modal-template-filters" style="margin:0;">
                <button type="button" class="lk-filter-chip lk-chip-active" data-filter="all"><?php esc_html_e( 'All', 'loopkit' ); ?></button>
                <button type="button" class="lk-filter-chip" data-filter="section"><?php esc_html_e( 'Sections', 'loopkit' ); ?></button>
                <button type="button" class="lk-filter-chip" data-filter="container"><?php esc_html_e( 'Containers', 'loopkit' ); ?></button>
            </div>
        </div>
        <div class="lk-modal-body lk-templates-modal-body" style="flex:1; overflow-y:auto; padding:16px 20px;">
            <div class="lk-templates-grid" id="lk-modal-templates-grid">
                <!-- Rendered by JS -->
            </div>
            <div class="lk-templates-empty" id="lk-modal-templates-empty" style="display:none; padding:40px 20px; text-align:center;">
                <span class="dashicons dashicons-category lk-empty-globe-icon" style="font-size:40px; width:40px; height:40px; color:var(--lk-panel-tab-fg);"></span>
                <p class="lk-empty-title" style="margin-top:10px; font-weight:600; font-size:14px; color:var(--lk-panel-fg);"><?php esc_html_e( 'No templates found', 'loopkit' ); ?></p>
                <p class="lk-empty-desc" style="font-size:12px; color:var(--lk-panel-tab-fg);"><?php esc_html_e( 'Try a different filter or import a template below.', 'loopkit' ); ?></p>
            </div>
        </div>
        <div class="lk-modal-footer lk-templates-modal-footer" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
            <div class="lk-templates-footer-left" style="display:flex; gap:10px; align-items:center;">
                <input type="file" id="lk-template-file-input" accept=".json" style="display:none;" />
                <button type="button" class="lk-btn lk-btn-secondary" id="lk-template-import-trigger">
                    <span class="dashicons dashicons-upload" style="font-size:16px; line-height:1; vertical-align:middle; margin-right:4px;"></span>
                    <?php esc_html_e( 'Import JSON', 'loopkit' ); ?>
                </button>
                <button type="button" class="lk-btn lk-btn-secondary" id="lk-template-export-selected-btn" disabled>
                    <span class="dashicons dashicons-download" style="font-size:16px; line-height:1; vertical-align:middle; margin-right:4px;"></span>
                    <span class="lk-export-btn-text"><?php esc_html_e( 'Export Selected (0)', 'loopkit' ); ?></span>
                </button>
            </div>
            <button type="button" class="lk-btn lk-btn-default lk-modal-close" data-close="lk-templates-library-modal">
                <?php esc_html_e( 'Close', 'loopkit' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- Rename Template Modal -->
<div class="lk-modal-overlay" id="lk-rename-template-modal" style="display:none;">
    <div class="lk-modal" style="max-width:440px;">
        <div class="lk-modal-header">
            <h3><?php esc_html_e( 'Rename Template', 'loopkit' ); ?></h3>
            <button type="button" class="lk-modal-close" data-close="lk-rename-template-modal">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="lk-modal-body">
            <label for="lk-rename-template-title-input" class="lk-modal-label" style="display:block; margin-bottom:8px; font-size:13px; font-weight:500; color:var(--lk-panel-fg);">
                <?php esc_html_e( 'Template Name', 'loopkit' ); ?>
            </label>
            <input type="text" id="lk-rename-template-title-input" class="lk-modal-input" style="width:100%; box-sizing:border-box;" />
            <div id="lk-rename-template-error" class="lk-notice-error" style="display:none; margin-top:8px; font-size:12px; color:#EF4444;"></div>
        </div>
        <div class="lk-modal-footer">
            <button type="button" class="lk-btn lk-btn-secondary lk-modal-close" data-close="lk-rename-template-modal"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></button>
            <button type="button" class="lk-btn lk-btn-primary" id="lk-rename-template-confirm-btn"><?php esc_html_e( 'Save', 'loopkit' ); ?></button>
        </div>
    </div>
</div>

<!-- Delete Template Confirmation Modal -->
<div class="lk-modal-overlay" id="lk-delete-template-modal" style="display:none;">
    <div class="lk-modal" style="max-width:440px;">
        <div class="lk-modal-header">
            <h3><?php esc_html_e( 'Delete Template', 'loopkit' ); ?></h3>
            <button type="button" class="lk-modal-close" data-close="lk-delete-template-modal">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="lk-modal-body">
            <p class="lk-modal-desc" id="lk-delete-template-confirm-text">
                <?php esc_html_e( 'Are you sure you want to delete this template? This action cannot be undone.', 'loopkit' ); ?>
            </p>
            <div id="lk-delete-template-error" class="lk-notice-error" style="display:none; margin-top:8px; font-size:12px; color:#EF4444;"></div>
        </div>
        <div class="lk-modal-footer">
            <button type="button" class="lk-btn lk-btn-secondary lk-modal-close" data-close="lk-delete-template-modal"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></button>
            <button type="button" class="lk-btn lk-btn-danger" id="lk-delete-template-confirm-btn"><?php esc_html_e( 'Delete', 'loopkit' ); ?></button>
        </div>
    </div>
</div>

<!-- Styled Template Error / Notice Modal (Zero Alert) -->
<div class="lk-modal-overlay" id="lk-template-error-modal" style="display:none;">
    <div class="lk-modal" style="max-width:440px;">
        <div class="lk-modal-header">
            <h3 id="lk-template-error-title"><?php esc_html_e( 'Template Error', 'loopkit' ); ?></h3>
            <button type="button" class="lk-modal-close" data-close="lk-template-error-modal">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="lk-modal-body">
            <div style="display:flex; gap:12px; align-items:flex-start;">
                <span class="dashicons dashicons-warning" style="color:#EF4444; font-size:24px; width:24px; height:24px; flex-shrink:0;"></span>
                <p class="lk-modal-desc" id="lk-template-error-message" style="margin:0; font-size:13px; line-height:1.5; color:var(--lk-panel-fg);">
                    <!-- Dynamic message -->
                </p>
            </div>
        </div>
        <div class="lk-modal-footer">
            <button type="button" class="lk-btn lk-btn-primary lk-modal-close" data-close="lk-template-error-modal"><?php esc_html_e( 'OK', 'loopkit' ); ?></button>
        </div>
    </div>
</div>

<?php
wp_print_footer_scripts();
wp_footer();
?>
</body>
</html>
        <?php
    }
}
