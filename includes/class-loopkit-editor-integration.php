<?php
/**
 * Loopkit Editor Integration class.
 * Adds "Edit with Loopkit" links to post list row actions and
 * injects an "Edit with Loopkit" button into the Block Editor header toolbar.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Editor_Integration {

    /**
     * Constructor.
     */
    public function __construct() {
        // Row actions for hierarchical (Pages) and non-hierarchical (Posts, CPTs)
        add_filter( 'page_row_actions', array( $this, 'add_row_action' ), 10, 2 );
        add_filter( 'post_row_actions', array( $this, 'add_row_action' ), 10, 2 );

        // Block Editor button
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_button' ) );

        // Builder action handler (placeholder until Step 3)
        add_action( 'admin_action_loopkit', array( $this, 'handle_builder_action' ) );

        // "Back to WordPress Editor" handler
        add_action( 'admin_post_loopkit_back_to_wp', array( $this, 'handle_back_to_wp' ) );
    }

    /**
     * Check whether a post type is enabled for Loopkit editing.
     * If the option has never been saved (missing or empty), Pages and Posts are enabled by default.
     * Templates (loopkit_template) are always supported.
     *
     * @param string $post_type Post type slug.
     * @return bool
     */
    private function is_post_type_supported( $post_type ) {
        if ( 'loopkit_template' === $post_type ) {
            return true;
        }

        $settings = get_option( 'loopkit_general_settings' );

        // Option has never been saved — fall back to pages + posts
        if ( false === $settings || ! is_array( $settings ) || ! isset( $settings['post_types'] ) ) {
            return in_array( $post_type, array( 'page', 'post' ), true );
        }

        $post_types = $settings['post_types'];

        // Saved but the array is empty (user unchecked everything)
        if ( empty( $post_types ) || ! is_array( $post_types ) ) {
            return false;
        }

        return in_array( $post_type, $post_types, true );
    }

    /**
     * Add "Edit with Loopkit" row action after "Edit" on list screens.
     *
     * @param array   $actions Existing row actions.
     * @param WP_Post $post    Current post object.
     * @return array  Modified row actions.
     */
    public function add_row_action( $actions, $post ) {
        if ( ! $this->is_post_type_supported( $post->post_type ) ) {
            return $actions;
        }

        if ( ! current_user_can( 'edit_post', $post->ID ) ) {
            return $actions;
        }

        $url  = admin_url( 'post.php?post=' . $post->ID . '&action=loopkit' );
        $link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( $url ),
            esc_html__( 'Edit with Loopkit', 'loopkit' )
        );

        // Insert right after the "edit" key so it reads:
        // Edit | Edit with Loopkit | Quick Edit | Trash | View
        $new_actions = array();
        $inserted    = false;

        foreach ( $actions as $key => $value ) {
            $new_actions[ $key ] = $value;
            if ( 'edit' === $key && ! $inserted ) {
                $new_actions['edit_with_loopkit'] = $link;
                $inserted = true;
            }
        }

        // Fallback: if 'edit' key was not present, append at the end
        if ( ! $inserted ) {
            $new_actions['edit_with_loopkit'] = $link;
        }

        return $new_actions;
    }

    /**
     * Enqueue the Block Editor button script and inline CSS.
     */
    public function enqueue_editor_button() {
        global $post;

        if ( ! $post ) {
            return;
        }

        if ( ! $this->is_post_type_supported( $post->post_type ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post->ID ) ) {
            return;
        }

        // Script
        wp_enqueue_script(
            'loopkit-editor-button',
            LOOPKIT_PLUGIN_URL . 'admin/js/loopkit-editor-button.js',
            array( 'wp-data', 'wp-dom-ready', 'wp-edit-post' ),
            LOOPKIT_VERSION,
            true
        );

        $is_loopkit_mode = ( get_post_meta( $post->ID, '_loopkit_edit_mode', true ) === 'builder' );

        $back_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=loopkit_back_to_wp&post=' . $post->ID ),
            'loopkit_back_to_wp_' . $post->ID
        );

        wp_localize_script( 'loopkit-editor-button', 'loopkitEditorButton', array(
            'builderUrl'    => admin_url( 'post.php?post=' . $post->ID . '&action=loopkit' ),
            'backUrl'       => $back_url,
            'isLoopkitMode' => $is_loopkit_mode,
            'buttonText'    => esc_html__( 'Edit with Loopkit', 'loopkit' ),
            'backText'      => esc_html__( 'Back to WordPress Editor', 'loopkit' ),
        ) );

        // Inline CSS (registered with empty src so wp_add_inline_style works)
        wp_register_style( 'loopkit-editor-button', false, array( 'dashicons' ), LOOPKIT_VERSION );
        wp_enqueue_style( 'loopkit-editor-button' );
        wp_add_inline_style( 'loopkit-editor-button', $this->get_button_css() );
    }

    /**
     * Return CSS for the Block Editor button.
     *
     * @return string
     */
    private function get_button_css() {
        return '
#lk-editor-buttons-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: 8px;
}
.lk-editor-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 12px;
    background: #38A8F5;
    color: #fff !important;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none !important;
    line-height: 1;
    height: 32px;
    white-space: nowrap;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.lk-editor-btn:hover {
    background: #1E90E0;
    color: #fff !important;
}
.lk-editor-btn:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(56, 168, 245, 0.4);
}
.lk-editor-btn .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    line-height: 16px;
}
.lk-editor-btn-back {
    background: transparent;
    color: #38A8F5 !important;
    border: 1px solid #38A8F5;
    font-weight: 500;
    font-size: 12px;
}
.lk-editor-btn-back:hover {
    background: #E9F6FE;
    color: #1E90E0 !important;
    border-color: #1E90E0;
}';
    }

    /**
     * Handle the admin_action_loopkit request.
     * Sets post meta and renders the visual editor.
     */
    public function handle_builder_action() {
        $post_id = absint( $_GET['post'] ?? 0 );

        if ( ! $post_id ) {
            wp_die( esc_html__( 'Invalid post ID.', 'loopkit' ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'You do not have permission to edit this post.', 'loopkit' ) );
        }

        // Mark the post as edited with Loopkit
        update_post_meta( $post_id, '_loopkit_edit_mode', 'builder' );

        if ( ! metadata_exists( 'post', $post_id, '_loopkit_data' ) ) {
            update_post_meta( $post_id, '_loopkit_data', wp_json_encode( array() ) );
        }

        // Render full-screen visual editor
        $editor = new Loopkit_Editor( $post_id );
        $editor->render();
        exit;
    }

    /**
     * Handle the "Back to WordPress Editor" action.
     * Clears the Loopkit edit mode meta and redirects back to the standard editor.
     */
    public function handle_back_to_wp() {
        $post_id = absint( $_GET['post'] ?? 0 );

        check_admin_referer( 'loopkit_back_to_wp_' . $post_id );

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'Permission denied.', 'loopkit' ) );
        }

        delete_post_meta( $post_id, '_loopkit_edit_mode' );

        wp_safe_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
        exit;
    }
}

new Loopkit_Editor_Integration();
