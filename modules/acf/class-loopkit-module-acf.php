<?php
/**
 * Loopkit Custom Fields module (Loopkit's own, not the ACF plugin).
 *
 * Switched on in Loopkit > Tools > Modules; this file is only loaded when it is on.
 *
 *   • Field groups are stored as the `loopkit_field_group` post type.
 *   • Each group holds its fields (label, key, type, instructions, default, required, choices)
 *     and its location rules (post type, one post ID, or a page template).
 *   • Matching edit screens get a meta box with those fields; values are saved as post meta
 *     under the field key, so `{{lk:custom_field:key}}` and get_post_meta() both work.
 *   • The editor's Dynamic Tags gain a "Custom Fields" group listing every field.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Module_ACF {

    const POST_TYPE = 'loopkit_field_group';

    /**
     * Field types this module offers.
     *
     * @return array
     */
    public static function field_types() {
        return array(
            'text'       => __( 'Text', 'loopkit' ),
            'textarea'   => __( 'Text Area', 'loopkit' ),
            'wysiwyg'    => __( 'Rich Text', 'loopkit' ),
            'number'     => __( 'Number', 'loopkit' ),
            'email'      => __( 'Email', 'loopkit' ),
            'url'        => __( 'URL', 'loopkit' ),
            'select'     => __( 'Select', 'loopkit' ),
            'radio'      => __( 'Radio Buttons', 'loopkit' ),
            'checkbox'   => __( 'Checkboxes', 'loopkit' ),
            'true_false' => __( 'True / False', 'loopkit' ),
            'color'      => __( 'Colour', 'loopkit' ),
            'date'       => __( 'Date', 'loopkit' ),
            'image'      => __( 'Image', 'loopkit' ),
            'file'       => __( 'File', 'loopkit' ),
            'gallery'    => __( 'Gallery', 'loopkit' ),
            'post'       => __( 'Post Link', 'loopkit' ),
            'repeater'   => __( 'Repeater (rows)', 'loopkit' ),
        );
    }

    /**
     * Start the module.
     */
    public function init() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'admin_menu', array( $this, 'register_menu' ), 30 );
        add_action( 'admin_init', array( $this, 'handle_save' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

        // Taxonomy term screens
        add_action( 'admin_init', array( $this, 'hook_taxonomy_fields' ) );

        // User profiles
        add_action( 'show_user_profile', array( $this, 'render_user_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'render_user_fields' ) );
        add_action( 'personal_options_update', array( $this, 'save_user_fields' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_user_fields' ) );

        // Options page
        add_action( 'admin_menu', array( $this, 'register_options_page' ), 31 );
        add_action( 'admin_init', array( $this, 'save_options_page' ) );
        add_action( 'save_post', array( $this, 'save_values' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
        add_filter( 'loopkit_dynamic_tags', array( $this, 'add_dynamic_tags' ) );
    }

    /**
     * Storage for field groups.
     */
    public function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'labels'          => array(
                'name'          => __( 'Field Groups', 'loopkit' ),
                'singular_name' => __( 'Field Group', 'loopkit' ),
            ),
            'public'          => false,
            'show_ui'         => false,
            'show_in_menu'    => false,
            'supports'        => array( 'title' ),
            'capability_type' => 'post',
        ) );
    }

    /**
     * Admin screen.
     */
    public function register_menu() {
        add_menu_page(
            __( 'Custom Fields', 'loopkit' ),
            __( 'Custom Fields', 'loopkit' ),
            'manage_options',
            'loopkit-fields',
            array( $this, 'render_page' ),
            'dashicons-forms',
            27
        );

        add_submenu_page( 'loopkit-fields', __( 'Field Groups', 'loopkit' ), __( 'Field Groups', 'loopkit' ), 'manage_options', 'loopkit-fields', array( $this, 'render_page' ) );
        add_submenu_page( 'loopkit-fields', __( 'Add Field Group', 'loopkit' ), __( 'Add Field Group', 'loopkit' ), 'manage_options', 'loopkit-fields&group=new', array( $this, 'render_page' ) );
    }

    /**
     * Media picker for image / file / gallery fields.
     *
     * @param string $hook Current screen.
     */
    public function enqueue( $hook ) {
        if ( false !== strpos( (string) $hook, 'loopkit-fields' ) || in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            wp_enqueue_media();
        }
    }

    /**
     * All field groups.
     *
     * @return array
     */
    public static function get_field_groups() {
        $groups = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array( 'publish', 'draft' ),
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $out = array();
        foreach ( $groups as $group ) {
            $fields    = get_post_meta( $group->ID, '_loopkit_fields', true );
            $locations = get_post_meta( $group->ID, '_loopkit_locations', true );
            $out[]     = array(
                'id'        => $group->ID,
                'title'     => $group->post_title,
                'status'    => $group->post_status,
                'fields'    => is_array( $fields ) ? $fields : array(),
                'locations' => is_array( $locations ) ? $locations : array(),
            );
        }
        return $out;
    }

    /**
     * Groups that belong on this post.
     *
     * @param WP_Post $post Post being edited.
     * @return array
     */
    public static function groups_for_post( $post ) {
        $out = array();
        if ( is_numeric( $post ) ) {
            $post = get_post( (int) $post );
        }
        if ( ! $post instanceof WP_Post ) {
            return $out;
        }
        foreach ( self::get_field_groups() as $group ) {
            if ( 'publish' !== $group['status'] || empty( $group['fields'] ) ) {
                continue;
            }
            foreach ( $group['locations'] as $rule ) {
                $type  = $rule['type'] ?? 'post_type';
                $value = (string) ( $rule['value'] ?? '' );
                if ( 'post_type' === $type && $value === $post->post_type ) {
                    $out[] = $group;
                    break;
                }
                if ( 'post_id' === $type && (int) $value === (int) $post->ID ) {
                    $out[] = $group;
                    break;
                }
                if ( 'template' === $type && $value === get_page_template_slug( $post->ID ) ) {
                    $out[] = $group;
                    break;
                }
                // "Has term" — e.g. only products in the Gear category
                if ( 'term' === $type && $post->ID && has_term( $value, self::taxonomy_of( $value, $post ), $post ) ) {
                    $out[] = $group;
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * Attach the term-screen fields to every taxonomy a group asks for.
     */
    public function hook_taxonomy_fields() {
        foreach ( get_taxonomies( array(), 'names' ) as $taxonomy ) {
            if ( ! self::groups_for_place( 'taxonomy', $taxonomy ) ) {
                continue;
            }
            add_action( $taxonomy . '_add_form_fields', array( $this, 'render_term_add_fields' ) );
            add_action( $taxonomy . '_edit_form_fields', array( $this, 'render_term_edit_fields' ), 10, 2 );
            add_action( 'created_' . $taxonomy, array( $this, 'save_term_fields' ), 10, 1 );
            add_action( 'edited_' . $taxonomy, array( $this, 'save_term_fields' ), 10, 1 );
        }
    }

    /**
     * Fields on the "add term" form.
     *
     * @param string $taxonomy Taxonomy.
     */
    public function render_term_add_fields( $taxonomy ) {
        wp_nonce_field( 'loopkit_field_values', 'loopkit_field_values_nonce' );
        foreach ( self::groups_for_place( 'taxonomy', $taxonomy ) as $group ) {
            echo '<div class="form-field"><h3>' . esc_html( $group['title'] ) . '</h3>';
            self::render_fields( $group, 0, 'taxonomy' );
            echo '</div>';
        }
    }

    /**
     * Fields on the "edit term" form.
     *
     * @param WP_Term $term     Term.
     * @param string  $taxonomy Taxonomy.
     */
    public function render_term_edit_fields( $term, $taxonomy ) {
        wp_nonce_field( 'loopkit_field_values', 'loopkit_field_values_nonce' );
        foreach ( self::groups_for_place( 'taxonomy', $taxonomy ) as $group ) {
            echo '<tr class="form-field"><th colspan="2"><h3>' . esc_html( $group['title'] ) . '</h3></th></tr>';
            echo '<tr class="form-field"><td colspan="2">';
            self::render_fields( $group, $term->term_id, 'taxonomy' );
            echo '</td></tr>';
        }
    }

    /**
     * Save a term's fields.
     *
     * @param int $term_id Term.
     */
    public function save_term_fields( $term_id ) {
        if ( empty( $_POST['loopkit_field_values_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['loopkit_field_values_nonce'] ) ), 'loopkit_field_values' ) ) {
            return;
        }
        $term = get_term( $term_id );
        if ( ! $term instanceof WP_Term || ! current_user_can( 'manage_categories' ) ) {
            return;
        }
        self::save_values_for_place( 'taxonomy', $term->taxonomy, $term_id, wp_unslash( $_POST['loopkit_field'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    }

    /**
     * Fields on a user profile.
     *
     * @param WP_User $user User.
     */
    public function render_user_fields( $user ) {
        $groups = self::groups_for_place( 'user' );
        if ( ! $groups ) {
            return;
        }
        wp_nonce_field( 'loopkit_field_values', 'loopkit_field_values_nonce' );
        foreach ( $groups as $group ) {
            echo '<h2>' . esc_html( $group['title'] ) . '</h2>';
            self::render_fields( $group, $user->ID, 'user' );
        }
    }

    /**
     * Save a user's fields.
     *
     * @param int $user_id User.
     */
    public function save_user_fields( $user_id ) {
        if ( empty( $_POST['loopkit_field_values_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['loopkit_field_values_nonce'] ) ), 'loopkit_field_values' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }
        self::save_values_for_place( 'user', '', $user_id, wp_unslash( $_POST['loopkit_field'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    }

    /**
     * The site-wide options screen, shown only when a group asks for it.
     */
    public function register_options_page() {
        if ( ! self::groups_for_place( 'options' ) ) {
            return;
        }
        add_submenu_page(
            'loopkit-fields',
            __( 'Options', 'loopkit' ),
            __( 'Options', 'loopkit' ),
            'manage_options',
            'loopkit-field-options',
            array( $this, 'render_options_page' )
        );
    }

    /**
     * Save the options screen.
     */
    public function save_options_page() {
        if ( empty( $_POST['loopkit_options_save'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to change these options.', 'loopkit' ) );
        }
        check_admin_referer( 'loopkit_field_options' );

        self::save_values_for_place( 'options', '', 0, wp_unslash( $_POST['loopkit_field'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        wp_safe_redirect( admin_url( 'admin.php?page=loopkit-field-options&saved=1' ) );
        exit;
    }

    /**
     * The options screen itself.
     */
    public function render_options_page() {
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Options', 'loopkit' ); ?></h1>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Options saved.', 'loopkit' ); ?></p></div>
            <?php endif; ?>

            <form method="post" class="lk-automation-form">
                <?php wp_nonce_field( 'loopkit_field_options' ); ?>
                <input type="hidden" name="loopkit_options_save" value="1">

                <?php foreach ( self::groups_for_place( 'options' ) as $group ) : ?>
                    <div class="lk-card lk-auto-card">
                        <div class="lk-card-header">
                            <h3><?php echo esc_html( $group['title'] ); ?></h3>
                            <p class="lk-card-desc"><?php esc_html_e( 'These values belong to the whole site, not one page. Use them with {{lk:option:key}}.', 'loopkit' ); ?></p>
                        </div>
                        <div class="lk-card-body">
                            <?php self::render_fields( $group, 0, 'options' ); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <p class="lk-auto-submit">
                    <button type="submit" class="lk-btn lk-btn-primary"><?php esc_html_e( 'Save Options', 'loopkit' ); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * A repeater field: rows of the sub-fields defined on it.
     *
     * @param array $field Field definition.
     * @param mixed $value Stored rows.
     */
    private static function render_repeater( $field, $value ) {
        $subs = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $field['choices'] ?? '' ) ) ) );
        if ( empty( $subs ) ) {
            $subs = array( __( 'Value', 'loopkit' ) );
        }

        $rows = is_array( $value ) ? $value : array();
        if ( empty( $rows ) ) {
            $rows = array( array() );
        }

        echo '<div class="lk-rep" data-key="' . esc_attr( $field['key'] ) . '">';
        echo '<div class="lk-rep-rows">';

        foreach ( array_values( $rows ) as $index => $row ) {
            echo '<div class="lk-rep-row">';
            foreach ( $subs as $sub ) {
                $sub_key = sanitize_key( str_replace( '-', '_', sanitize_title( $sub ) ) );
                echo '<label class="lk-rep-field"><span>' . esc_html( $sub ) . '</span>';
                echo '<input type="text" name="loopkit_field[' . esc_attr( $field['key'] ) . '][' . (int) $index . '][' . esc_attr( $sub_key ) . ']" value="' . esc_attr( (string) ( $row[ $sub_key ] ?? '' ) ) . '">';
                echo '</label>';
            }
            echo '<button type="button" class="lk-rep-remove" aria-label="' . esc_attr__( 'Remove row', 'loopkit' ) . '">&times;</button>';
            echo '</div>';
        }

        echo '</div>';
        echo '<button type="button" class="lk-rep-add button">' . esc_html__( '+ Add row', 'loopkit' ) . '</button>';
        echo '</div>';
    }

    /**
     * Groups whose rules point at a given place: 'options', a taxonomy name, or 'user'.
     *
     * @param string $place Place type.
     * @param string $value Taxonomy name where it matters.
     * @return array
     */
    public static function groups_for_place( $place, $value = '' ) {
        $out = array();
        foreach ( self::get_field_groups() as $group ) {
            if ( 'publish' !== $group['status'] || empty( $group['fields'] ) ) {
                continue;
            }
            foreach ( $group['locations'] as $rule ) {
                if ( ( $rule['type'] ?? '' ) !== $place ) {
                    continue;
                }
                // "options" and "user" need no value; a taxonomy rule can name one or cover all.
                if ( 'taxonomy' === $place && '' !== ( $rule['value'] ?? '' ) && $rule['value'] !== $value ) {
                    continue;
                }
                $out[] = $group;
                break;
            }
        }
        return $out;
    }

    /**
     * Which taxonomy a term slug belongs to, among the ones this post type uses.
     *
     * @param string  $slug Term slug.
     * @param WP_Post $post Post.
     * @return string
     */
    private static function taxonomy_of( $slug, $post ) {
        foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
            if ( get_term_by( 'slug', $slug, $taxonomy ) ) {
                return $taxonomy;
            }
        }
        return '';
    }

    /* ---------------------------------------------------------------------
     * Builder screen
     * ------------------------------------------------------------------- */

    /**
     * Save or delete a field group.
     */
    public function handle_save() {
        if ( empty( $_POST['loopkit_fields_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage field groups.', 'loopkit' ) );
        }
        check_admin_referer( 'loopkit_fields' );

        $action = sanitize_key( wp_unslash( $_POST['loopkit_fields_action'] ) );
        $id     = absint( $_POST['group_id'] ?? 0 );

        if ( 'delete_group' === $action ) {
            if ( $id ) {
                wp_delete_post( $id, true );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=loopkit-fields&saved=deleted' ) );
            exit;
        }

        $title  = sanitize_text_field( wp_unslash( $_POST['group_title'] ?? '' ) );
        $title  = '' !== $title ? $title : __( 'Untitled group', 'loopkit' );
        $status = ! empty( $_POST['group_active'] ) ? 'publish' : 'draft';

        $fields = array();
        $used   = array();
        foreach ( (array) ( $_POST['fields'] ?? array() ) as $raw ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            $raw   = wp_unslash( $raw );
            $label = sanitize_text_field( $raw['label'] ?? '' );
            if ( '' === $label ) {
                continue;
            }
            $key = sanitize_key( $raw['key'] ?? '' );
            if ( '' === $key ) {
                $key = sanitize_key( str_replace( '-', '_', sanitize_title( $label ) ) );
            }
            while ( isset( $used[ $key ] ) ) {
                $key .= '_2';
            }
            $used[ $key ] = true;

            $type = sanitize_key( $raw['type'] ?? 'text' );
            if ( ! isset( self::field_types()[ $type ] ) ) {
                $type = 'text';
            }

            $fields[] = array(
                'label'        => $label,
                'key'          => $key,
                'type'         => $type,
                'instructions' => sanitize_text_field( $raw['instructions'] ?? '' ),
                'default'      => sanitize_textarea_field( $raw['default'] ?? '' ),
                'choices'      => sanitize_textarea_field( $raw['choices'] ?? '' ),
                'required'     => ! empty( $raw['required'] ) ? 1 : 0,
            );
        }

        $locations = array();
        foreach ( (array) ( $_POST['locations'] ?? array() ) as $raw ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            $raw   = wp_unslash( $raw );
            $value = sanitize_text_field( $raw['value'] ?? '' );
            if ( '' === $value ) {
                continue;
            }
            $locations[] = array(
                'type'  => in_array( $raw['type'] ?? '', array( 'post_type', 'post_id', 'template', 'term', 'options', 'taxonomy', 'user' ), true ) ? $raw['type'] : 'post_type',
                'value' => $value,
            );
        }

        $postarr = array(
            'ID'          => $id ?: 0,
            'post_type'   => self::POST_TYPE,
            'post_title'  => $title,
            'post_status' => $status,
        );
        $id = $id ? wp_update_post( $postarr ) : wp_insert_post( $postarr );

        update_post_meta( $id, '_loopkit_fields', $fields );
        update_post_meta( $id, '_loopkit_locations', $locations );

        wp_safe_redirect( admin_url( 'admin.php?page=loopkit-fields&group=' . (int) $id . '&saved=1' ) );
        exit;
    }

    /**
     * Screen: list or builder.
     */
    public function render_page() {
        $edit = isset( $_GET['group'] ) ? sanitize_text_field( wp_unslash( $_GET['group'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        if ( '' !== $edit ) {
            $group = null;
            if ( 'new' !== $edit ) {
                foreach ( self::get_field_groups() as $g ) {
                    if ( (int) $g['id'] === (int) $edit ) {
                        $group = $g;
                    }
                }
            }
            $this->render_builder( $group );
            return;
        }

        $groups = self::get_field_groups();
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Custom Fields', 'loopkit' ); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-fields&group=new' ) ); ?>"><?php esc_html_e( 'Add Field Group', 'loopkit' ); ?></a>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-success is-dismissible"><p><?php
                    echo 'deleted' === $_GET['saved'] ? esc_html__( 'Field group deleted.', 'loopkit' ) : esc_html__( 'Field group saved.', 'loopkit' ); // phpcs:ignore WordPress.Security.NonceVerification
                ?></p></div>
            <?php endif; ?>

            <div class="lk-card">
                <div class="lk-card-header">
                    <h3><?php esc_html_e( 'Field Groups', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc"><?php esc_html_e( 'Fields you add here appear on the chosen edit screens and in the editor\'s Dynamic Tags, so any widget can show them.', 'loopkit' ); ?></p>
                </div>
                <div class="lk-card-body">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Group', 'loopkit' ); ?></th>
                                <th style="width:90px"><?php esc_html_e( 'Fields', 'loopkit' ); ?></th>
                                <th style="width:260px"><?php esc_html_e( 'Shown on', 'loopkit' ); ?></th>
                                <th style="width:90px"><?php esc_html_e( 'Status', 'loopkit' ); ?></th>
                                <th style="width:80px"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ( empty( $groups ) ) : ?>
                            <tr><td colspan="5"><?php esc_html_e( 'No field groups yet. Add one to start putting your own fields on posts, pages or any post type.', 'loopkit' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $groups as $group ) : ?>
                                <tr>
                                    <td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-fields&group=' . (int) $group['id'] ) ); ?>"><?php echo esc_html( $group['title'] ); ?></a></strong></td>
                                    <td><?php echo (int) count( $group['fields'] ); ?></td>
                                    <td><?php
                                        $labels = array();
                                        foreach ( $group['locations'] as $rule ) {
                                            $labels[] = ( 'post_id' === ( $rule['type'] ?? '' ) )
                                                ? get_the_title( (int) $rule['value'] )
                                                : $rule['value'];
                                        }
                                        echo esc_html( $labels ? implode( ', ', $labels ) : '—' );
                                    ?></td>
                                    <td><?php echo 'publish' === $group['status'] ? '<span style="color:#008a20">' . esc_html__( 'Active', 'loopkit' ) . '</span>' : esc_html__( 'Draft', 'loopkit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('<?php esc_attr_e( 'Delete this field group?', 'loopkit' ); ?>')">
                                            <?php wp_nonce_field( 'loopkit_fields' ); ?>
                                            <input type="hidden" name="loopkit_fields_action" value="delete_group">
                                            <input type="hidden" name="group_id" value="<?php echo (int) $group['id']; ?>">
                                            <button type="submit" class="button-link delete" style="color:#b32d2e"><?php esc_html_e( 'Delete', 'loopkit' ); ?></button>
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

    /**
     * Add / edit a field group.
     *
     * @param array|null $group Group being edited.
     */
    private function render_builder( $group ) {
        $group = wp_parse_args( (array) $group, array(
            'id'        => 0,
            'title'     => '',
            'status'    => 'publish',
            'fields'    => array(),
            'locations' => array(),
        ) );
        $fields     = $group['fields'] ? $group['fields'] : array( array( 'label' => '', 'key' => '', 'type' => 'text', 'instructions' => '', 'default' => '', 'choices' => '', 'required' => 0 ) );
        $locations  = $group['locations'] ? $group['locations'] : array( array( 'type' => 'post_type', 'value' => 'post' ) );
        $types      = self::field_types();
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php echo $group['id'] ? esc_html__( 'Edit Field Group', 'loopkit' ) : esc_html__( 'Add Field Group', 'loopkit' ); ?></h1>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Field group saved.', 'loopkit' ); ?></p></div>
            <?php endif; ?>

            <form method="post" class="lk-automation-form lk-fields-form">
                <?php wp_nonce_field( 'loopkit_fields' ); ?>
                <input type="hidden" name="loopkit_fields_action" value="save_group">
                <input type="hidden" name="group_id" value="<?php echo (int) $group['id']; ?>">

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <h3><?php esc_html_e( 'Group', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'A name for yourself, such as "Product details" or "Page hero".', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body lk-auto-grid">
                        <div class="lk-auto-field">
                            <label for="lk-group-title"><?php esc_html_e( 'Group name', 'loopkit' ); ?></label>
                            <input type="text" id="lk-group-title" name="group_title" value="<?php echo esc_attr( $group['title'] ); ?>" placeholder="<?php esc_attr_e( 'Product details', 'loopkit' ); ?>" required>
                        </div>
                        <div class="lk-auto-field">
                            <label><?php esc_html_e( 'Status', 'loopkit' ); ?></label>
                            <label class="lk-auto-check"><input type="checkbox" name="group_active" value="1" <?php checked( 'publish', $group['status'] ); ?>> <?php esc_html_e( 'Active — show these fields', 'loopkit' ); ?></label>
                        </div>
                    </div>
                </div>

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <span class="lk-auto-step">1</span>
                        <h3><?php esc_html_e( 'Show these fields on', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'A whole post type (including Products), one specific post or page by ID, a page template, or anything in a category — for example only products in "gear".', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body">
                        <div id="lk-locations-rows">
                            <?php foreach ( $locations as $i => $rule ) : ?>
                                <div class="lk-auto-row lk-loc-row">
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Rule', 'loopkit' ); ?></label>
                                        <select name="locations[<?php echo (int) $i; ?>][type]" class="lk-loc-type">
                                            <option value="post_type" <?php selected( $rule['type'] ?? '', 'post_type' ); ?>><?php esc_html_e( 'Post type is', 'loopkit' ); ?></option>
                                            <option value="post_id" <?php selected( $rule['type'] ?? '', 'post_id' ); ?>><?php esc_html_e( 'Post / Page ID is', 'loopkit' ); ?></option>
                                            <option value="template" <?php selected( $rule['type'] ?? '', 'template' ); ?>><?php esc_html_e( 'Page template is', 'loopkit' ); ?></option>
                                            <option value="term" <?php selected( $rule['type'] ?? '', 'term' ); ?>><?php esc_html_e( 'Has category / term', 'loopkit' ); ?></option>
                                            <option value="taxonomy" <?php selected( $rule['type'] ?? '', 'taxonomy' ); ?>><?php esc_html_e( 'Taxonomy term screen', 'loopkit' ); ?></option>
                                            <option value="user" <?php selected( $rule['type'] ?? '', 'user' ); ?>><?php esc_html_e( 'User profile', 'loopkit' ); ?></option>
                                            <option value="options" <?php selected( $rule['type'] ?? '', 'options' ); ?>><?php esc_html_e( 'Options page (site-wide)', 'loopkit' ); ?></option>
                                        </select>
                                    </div>
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Post type', 'loopkit' ); ?></label>
                                        <select name="locations[<?php echo (int) $i; ?>][value]" class="lk-loc-post-type" <?php disabled( 'post_type' !== ( $rule['type'] ?? 'post_type' ) ); ?>>
                                            <?php foreach ( $post_types as $pt ) : ?>
                                                <option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $rule['value'] ?? '', $pt->name ); ?>><?php echo esc_html( $pt->labels->singular_name . ' (' . $pt->name . ')' ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'ID / template / term slug', 'loopkit' ); ?></label>
                                        <input type="text" class="lk-loc-custom" name="locations[<?php echo (int) $i; ?>][value_custom]" value="<?php echo esc_attr( 'post_type' !== ( $rule['type'] ?? 'post_type' ) ? ( $rule['value'] ?? '' ) : '' ); ?>" placeholder="42" <?php disabled( 'post_type' === ( $rule['type'] ?? 'post_type' ) ); ?>>
                                    </div>
                                    <button type="button" class="lk-auto-remove" aria-label="<?php esc_attr_e( 'Remove rule', 'loopkit' ); ?>">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="lk-btn lk-btn-secondary" id="lk-add-location"><?php esc_html_e( '+ Add rule', 'loopkit' ); ?></button>
                    </div>
                </div>

                <div class="lk-card lk-auto-card">
                    <div class="lk-card-header">
                        <span class="lk-auto-step">2</span>
                        <h3><?php esc_html_e( 'Fields', 'loopkit' ); ?></h3>
                        <p class="lk-card-desc"><?php esc_html_e( 'The name is filled in from the label. Use it in the editor as {{lk:custom_field:your_key}}.', 'loopkit' ); ?></p>
                    </div>
                    <div class="lk-card-body">
                        <div id="lk-fields-rows">
                            <?php foreach ( $fields as $i => $field ) : ?>
                                <div class="lk-auto-action lk-field-row" data-type="<?php echo esc_attr( $field['type'] ?? 'text' ); ?>">
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Label', 'loopkit' ); ?></label>
                                        <input type="text" class="lk-field-label" name="fields[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $field['label'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Price', 'loopkit' ); ?>">
                                    </div>
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Name (key)', 'loopkit' ); ?></label>
                                        <input type="text" class="lk-field-key" name="fields[<?php echo (int) $i; ?>][key]" value="<?php echo esc_attr( $field['key'] ?? '' ); ?>" placeholder="price">
                                    </div>
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Type', 'loopkit' ); ?></label>
                                        <select class="lk-field-type" name="fields[<?php echo (int) $i; ?>][type]">
                                            <?php foreach ( $types as $key => $label ) : ?>
                                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field['type'] ?? 'text', $key ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Required', 'loopkit' ); ?></label>
                                        <label class="lk-auto-check"><input type="checkbox" name="fields[<?php echo (int) $i; ?>][required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?>> <?php esc_html_e( 'Must be filled', 'loopkit' ); ?></label>
                                    </div>
                                    <div class="lk-auto-field lk-auto-field-full" data-for="choices">
                                        <label><?php esc_html_e( 'Choices (one per line)', 'loopkit' ); ?></label>
                                        <textarea name="fields[<?php echo (int) $i; ?>][choices]" rows="3" placeholder="Small&#10;Medium&#10;Large"><?php echo esc_textarea( $field['choices'] ?? '' ); ?></textarea>
                                    </div>
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Instructions', 'loopkit' ); ?></label>
                                        <input type="text" name="fields[<?php echo (int) $i; ?>][instructions]" value="<?php echo esc_attr( $field['instructions'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Shown under the field', 'loopkit' ); ?>">
                                    </div>
                                    <div class="lk-auto-field">
                                        <label><?php esc_html_e( 'Default value', 'loopkit' ); ?></label>
                                        <input type="text" name="fields[<?php echo (int) $i; ?>][default]" value="<?php echo esc_attr( $field['default'] ?? '' ); ?>">
                                    </div>
                                    <button type="button" class="lk-auto-remove" aria-label="<?php esc_attr_e( 'Remove field', 'loopkit' ); ?>">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="lk-btn lk-btn-secondary" id="lk-add-field"><?php esc_html_e( '+ Add field', 'loopkit' ); ?></button>
                    </div>
                </div>

                <p class="lk-auto-submit">
                    <button type="submit" class="lk-btn lk-btn-primary"><?php esc_html_e( 'Save Field Group', 'loopkit' ); ?></button>
                    <a class="lk-btn lk-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-fields' ) ); ?>"><?php esc_html_e( 'Back', 'loopkit' ); ?></a>
                </p>
            </form>
        </div>

        <script>
        ( function () {
            var fieldRows = document.getElementById( 'lk-fields-rows' );
            var locRows = document.getElementById( 'lk-locations-rows' );

            function renumber( wrap, key, selector ) {
                Array.prototype.forEach.call( wrap.querySelectorAll( selector ), function ( row, i ) {
                    row.querySelectorAll( '[name]' ).forEach( function ( f ) {
                        f.name = f.name.replace( new RegExp( '^' + key + '\\[\\d+\\]' ), key + '[' + i + ']' );
                    } );
                } );
            }

            // Choices only matter for select / radio / checkbox
            function syncField( row ) {
                var type = row.querySelector( '.lk-field-type' ).value;
                row.setAttribute( 'data-type', type );
                var choices = row.querySelector( '[data-for="choices"]' );
                if ( choices ) choices.style.display = [ 'select', 'radio', 'checkbox' ].indexOf( type ) === -1 ? 'none' : '';
            }

            // A location rule uses either the post-type dropdown or the free text box
            function syncLocation( row ) {
                var type = row.querySelector( '.lk-loc-type' ).value;
                var pt = row.querySelector( '.lk-loc-post-type' );
                var custom = row.querySelector( '.lk-loc-custom' );
                var isPostType = type === 'post_type';
                pt.disabled = ! isPostType;
                pt.closest( '.lk-auto-field' ).style.display = isPostType ? '' : 'none';
                custom.disabled = isPostType;
                custom.closest( '.lk-auto-field' ).style.display = isPostType ? 'none' : '';
                custom.placeholder = type === 'template' ? 'full-width.php' : ( type === 'term' ? 'gear' : '42' );
                // whichever is in use carries the value
                custom.name = custom.name.replace( '[value]', '[value_custom]' );
                if ( ! isPostType ) custom.name = custom.name.replace( '[value_custom]', '[value]' );
                else pt.name = pt.name.replace( '[value_custom]', '[value]' );
            }

            fieldRows.addEventListener( 'input', function ( e ) {
                if ( e.target.classList.contains( 'lk-field-key' ) ) { e.target.dataset.touched = '1'; return; }
                if ( ! e.target.classList.contains( 'lk-field-label' ) ) return;
                var key = e.target.closest( '.lk-field-row' ).querySelector( '.lk-field-key' );
                if ( ! key.dataset.touched ) {
                    key.value = e.target.value.toLowerCase().replace( /[^a-z0-9]+/g, '_' ).replace( /^_+|_+$/g, '' );
                }
            } );
            fieldRows.addEventListener( 'change', function ( e ) {
                if ( e.target.classList.contains( 'lk-field-type' ) ) syncField( e.target.closest( '.lk-field-row' ) );
            } );
            locRows.addEventListener( 'change', function ( e ) {
                if ( e.target.classList.contains( 'lk-loc-type' ) ) syncLocation( e.target.closest( '.lk-loc-row' ) );
            } );

            document.getElementById( 'lk-add-field' ).addEventListener( 'click', function () {
                var row = fieldRows.children[0].cloneNode( true );
                row.querySelectorAll( 'input, textarea' ).forEach( function ( f ) {
                    if ( f.type === 'checkbox' ) f.checked = false; else f.value = '';
                    delete f.dataset.touched;
                } );
                fieldRows.appendChild( row );
                renumber( fieldRows, 'fields', '.lk-field-row' );
                syncField( row );
            } );

            document.getElementById( 'lk-add-location' ).addEventListener( 'click', function () {
                var row = locRows.children[0].cloneNode( true );
                locRows.appendChild( row );
                renumber( locRows, 'locations', '.lk-loc-row' );
                syncLocation( row );
            } );

            document.addEventListener( 'click', function ( e ) {
                var btn = e.target.closest ? e.target.closest( '.lk-auto-remove' ) : null;
                if ( ! btn ) return;
                var row = btn.closest( '.lk-field-row, .lk-loc-row' );
                if ( ! row ) return;
                var wrap = row.parentNode;
                if ( wrap.children.length === 1 ) return;
                row.remove();
                renumber( wrap, wrap === fieldRows ? 'fields' : 'locations', wrap === fieldRows ? '.lk-field-row' : '.lk-loc-row' );
            } );

            fieldRows.querySelectorAll( '.lk-field-row' ).forEach( syncField );
            locRows.querySelectorAll( '.lk-loc-row' ).forEach( syncLocation );
        } )();
        </script>
        <?php
    }

    /* ---------------------------------------------------------------------
     * Edit screens
     * ------------------------------------------------------------------- */

    /**
     * A meta box for every matching group.
     */
    public function add_meta_boxes() {
        global $post;
        if ( ! $post instanceof WP_Post ) {
            return;
        }
        foreach ( self::groups_for_post( $post ) as $group ) {
            add_meta_box(
                'loopkit-fields-' . $group['id'],
                $group['title'],
                function() use ( $group, $post ) {
                    $this->render_meta_box( $group, $post );
                },
                null,
                'normal',
                'high'
            );
        }
    }

    /**
     * The fields on an edit screen.
     *
     * @param array   $group Field group.
     * @param WP_Post $post  Post.
     */
    private function render_meta_box( $group, $post ) {
        wp_nonce_field( 'loopkit_field_values', 'loopkit_field_values_nonce' );
        self::render_fields( $group, $post );
    }

    /**
     * The fields of one group. Used by the meta box and by Loopkit's own screens, such as the
     * Shop product editor, which does not use the WordPress post editor.
     *
     * @param array        $group Field group.
     * @param WP_Post|int  $post  Post or its ID.
     */
    public static function render_fields( $group, $post, $place = 'post' ) {
        if ( 'post' === $place ) {
            $post = is_numeric( $post ) ? get_post( (int) $post ) : $post;
            if ( ! $post instanceof WP_Post ) {
                // A product being created: render empty fields so they can be filled in now.
                $post = (object) array( 'ID' => 0 );
            }
        } else {
            // A term, a user or the options page: the id is all that is needed.
            $post = (object) array( 'ID' => (int) $post );
        }
        echo '<div class="lk-meta-fields">';

        foreach ( $group['fields'] as $field ) {
            $key   = $field['key'];
            $value = 'post' === $place
                ? get_post_meta( $post->ID, $key, true )
                : self::get_place_value( $place, $post->ID, $key );
            if ( '' === $value && '' !== ( $field['default'] ?? '' ) ) {
                $value = $field['default'];
            }
            $name    = 'loopkit_field[' . esc_attr( $key ) . ']';
            $id      = 'lk-field-' . esc_attr( $key );
            $choices = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $field['choices'] ?? '' ) ) ) ) );

            echo '<div class="lk-meta-field">';
            echo '<label class="lk-meta-label" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . ( ! empty( $field['required'] ) ? ' <span class="lk-meta-req">*</span>' : '' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

            switch ( $field['type'] ) {
                case 'textarea':
                    echo '<textarea id="' . esc_attr( $id ) . '" name="' . $name . '" rows="4">' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    break;
                case 'wysiwyg':
                    wp_editor( (string) $value, 'lkfield_' . $key, array(
                        'textarea_name' => 'loopkit_field[' . $key . ']',
                        'textarea_rows' => 8,
                        'media_buttons' => true,
                    ) );
                    break;
                case 'number':
                    echo '<input type="number" id="' . esc_attr( $id ) . '" name="' . $name . '" value="' . esc_attr( (string) $value ) . '" step="any">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    break;
                case 'email':
                case 'url':
                case 'date':
                case 'color':
                    echo '<input type="' . esc_attr( $field['type'] ) . '" id="' . esc_attr( $id ) . '" name="' . $name . '" value="' . esc_attr( (string) $value ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    break;
                case 'true_false':
                    echo '<label class="lk-meta-check"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . $name . '" value="1" ' . checked( '1', (string) $value, false ) . '> ' . esc_html__( 'Yes', 'loopkit' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    break;
                case 'select':
                    echo '<select id="' . esc_attr( $id ) . '" name="' . $name . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo '<option value="">' . esc_html__( '— Select —', 'loopkit' ) . '</option>';
                    foreach ( $choices as $choice ) {
                        echo '<option value="' . esc_attr( $choice ) . '" ' . selected( $choice, (string) $value, false ) . '>' . esc_html( $choice ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                    echo '</select>';
                    break;
                case 'radio':
                    foreach ( $choices as $choice ) {
                        echo '<label class="lk-meta-check"><input type="radio" name="' . $name . '" value="' . esc_attr( $choice ) . '" ' . checked( $choice, (string) $value, false ) . '> ' . esc_html( $choice ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                    break;
                case 'checkbox':
                    $selected = array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );
                    foreach ( $choices as $choice ) {
                        echo '<label class="lk-meta-check"><input type="checkbox" name="loopkit_field[' . esc_attr( $key ) . '][]" value="' . esc_attr( $choice ) . '" ' . checked( true, in_array( $choice, $selected, true ), false ) . '> ' . esc_html( $choice ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                    break;
                case 'image':
                case 'file':
                case 'gallery':
                    echo '<div class="lk-meta-media" data-multiple="' . ( 'gallery' === $field['type'] ? '1' : '0' ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . $name . '" value="' . esc_attr( (string) $value ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo '<div class="lk-meta-media-preview"></div>';
                    echo '<button type="button" class="button lk-meta-media-pick">' . esc_html__( 'Choose', 'loopkit' ) . '</button> ';
                    echo '<button type="button" class="button lk-meta-media-clear">' . esc_html__( 'Clear', 'loopkit' ) . '</button>';
                    echo '</div>';
                    break;
                case 'repeater':
                    self::render_repeater( $field, $value );
                    break;

                case 'post':
                    echo '<select id="' . esc_attr( $id ) . '" name="' . $name . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo '<option value="">' . esc_html__( '— Select —', 'loopkit' ) . '</option>';
                    foreach ( get_posts( array( 'post_type' => array( 'post', 'page' ), 'posts_per_page' => 200, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
                        echo '<option value="' . (int) $p->ID . '" ' . selected( (string) $p->ID, (string) $value, false ) . '>' . esc_html( $p->post_title ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                    echo '</select>';
                    break;
                default:
                    echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . $name . '" value="' . esc_attr( (string) $value ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }

            if ( ! empty( $field['instructions'] ) ) {
                echo '<p class="lk-meta-help">' . esc_html( $field['instructions'] ) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
        ?>
        <style>
        .lk-meta-fields { display: grid; gap: 18px; }
        .lk-meta-field { display: flex; flex-direction: column; gap: 6px; }
        .lk-meta-label { font-weight: 600; }
        .lk-meta-req { color: #b32d2e; }
        .lk-meta-field input[type="text"], .lk-meta-field input[type="number"], .lk-meta-field input[type="email"],
        .lk-meta-field input[type="url"], .lk-meta-field input[type="date"], .lk-meta-field select, .lk-meta-field textarea {
            width: 100%; max-width: 640px; padding: 8px 12px; border: 1px solid #d7dde5; border-radius: 6px;
        }
        .lk-meta-check { display: inline-flex; align-items: center; gap: 6px; margin-right: 14px; }
        .lk-meta-help { margin: 0; font-size: 12px; color: #646970; }
        .lk-meta-media-preview img { max-height: 90px; border-radius: 6px; margin: 6px 8px 6px 0; }
        </style>
        <script>
        ( function () {
            function paint( box ) {
                var input = box.querySelector( 'input[type=hidden]' );
                var prev = box.querySelector( '.lk-meta-media-preview' );
                prev.innerHTML = '';
                ( input.value || '' ).split( ',' ).filter( Boolean ).forEach( function ( id ) {
                    var url = window.lkMediaUrls && window.lkMediaUrls[ id ];
                    if ( url ) {
                        var img = document.createElement( 'img' );
                        img.src = url;
                        prev.appendChild( img );
                    } else {
                        prev.appendChild( document.createTextNode( '#' + id + ' ' ) );
                    }
                } );
            }
            document.querySelectorAll( '.lk-meta-media' ).forEach( function ( box ) {
                var input = box.querySelector( 'input[type=hidden]' );
                var multiple = box.getAttribute( 'data-multiple' ) === '1';
                paint( box );
                box.querySelector( '.lk-meta-media-pick' ).addEventListener( 'click', function () {
                    var frame = wp.media( { multiple: multiple, title: 'Select' } );
                    frame.on( 'select', function () {
                        var ids = frame.state().get( 'selection' ).map( function ( a ) {
                            var at = a.toJSON();
                            window.lkMediaUrls = window.lkMediaUrls || {};
                            window.lkMediaUrls[ at.id ] = ( at.sizes && at.sizes.thumbnail ) ? at.sizes.thumbnail.url : at.url;
                            return at.id;
                        } );
                        input.value = ids.join( ',' );
                        paint( box );
                    } );
                    frame.open();
                } );
                box.querySelector( '.lk-meta-media-clear' ).addEventListener( 'click', function () {
                    input.value = '';
                    paint( box );
                } );
            } );
        } )();
        </script>
        <?php
    }

    /**
     * Save the values from an edit screen.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post.
     */
    public function save_values( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( empty( $_POST['loopkit_field_values_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['loopkit_field_values_nonce'] ) ), 'loopkit_field_values' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        self::save_values_for_post( $post_id, wp_unslash( $_POST['loopkit_field'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    }

    /**
     * Store field values for a post. Used by the meta box and by Loopkit's own screens.
     *
     * @param int   $post_id Post.
     * @param array $values  key => value from the form.
     */
    public static function save_values_for_post( $post_id, $values ) {
        $post = get_post( (int) $post_id );
        if ( ! $post instanceof WP_Post || ! is_array( $values ) ) {
            return;
        }

        // Only keys belonging to a group shown on this post are saved.
        $allowed = array();
        foreach ( self::groups_for_post( $post ) as $group ) {
            foreach ( $group['fields'] as $field ) {
                $allowed[ $field['key'] ] = $field['type'];
            }
        }

        foreach ( $allowed as $key => $type ) {
            $value = self::clean_value( $values[ $key ] ?? '', $type );

            if ( '' === $value || array() === $value ) {
                delete_post_meta( $post_id, $key );
            } else {
                update_post_meta( $post_id, $key, $value );
            }
        }
    }

    /**
     * Clean one submitted value for its field type.
     *
     * @param mixed  $value Raw value.
     * @param string $type  Field type.
     * @return mixed
     */
    public static function clean_value( $value, $type ) {
        // Repeater: rows of plain text, empty rows dropped.
        if ( 'repeater' === $type ) {
            $rows = array();
            foreach ( (array) $value as $row ) {
                $clean = array();
                foreach ( (array) $row as $sub_key => $sub_value ) {
                    $clean[ sanitize_key( $sub_key ) ] = sanitize_text_field( $sub_value );
                }
                if ( array_filter( $clean, function( $v ) { return '' !== $v; } ) ) {
                    $rows[] = $clean;
                }
            }
            return $rows;
        }

        if ( is_array( $value ) ) {
            return implode( ', ', array_map( 'sanitize_text_field', $value ) );
        }
        if ( 'wysiwyg' === $type ) {
            return wp_kses_post( $value );
        }
        if ( 'textarea' === $type ) {
            return sanitize_textarea_field( $value );
        }
        if ( 'email' === $type ) {
            return sanitize_email( $value );
        }
        if ( 'url' === $type ) {
            return esc_url_raw( $value );
        }
        return sanitize_text_field( $value );
    }

    /**
     * Save the fields of the groups aimed at a place — a term, a user, or the options page.
     *
     * @param string $place    'taxonomy' | 'user' | 'options'.
     * @param string $value    Taxonomy name where it matters.
     * @param int    $owner_id Term or user id; 0 for the options page.
     * @param array  $values   Submitted values.
     */
    public static function save_values_for_place( $place, $value, $owner_id, $values ) {
        if ( ! is_array( $values ) ) {
            return;
        }

        $allowed = array();
        foreach ( self::groups_for_place( $place, $value ) as $group ) {
            foreach ( $group['fields'] as $field ) {
                $allowed[ $field['key'] ] = $field['type'];
            }
        }

        $options = 'options' === $place ? get_option( 'loopkit_field_options', array() ) : array();

        foreach ( $allowed as $key => $type ) {
            $clean = self::clean_value( $values[ $key ] ?? '', $type );

            if ( 'taxonomy' === $place ) {
                if ( '' === $clean || array() === $clean ) {
                    delete_term_meta( $owner_id, $key );
                } else {
                    update_term_meta( $owner_id, $key, $clean );
                }
            } elseif ( 'user' === $place ) {
                if ( '' === $clean || array() === $clean ) {
                    delete_user_meta( $owner_id, $key );
                } else {
                    update_user_meta( $owner_id, $key, $clean );
                }
            } else {
                $options[ $key ] = $clean;
            }
        }

        if ( 'options' === $place ) {
            update_option( 'loopkit_field_options', is_array( $options ) ? $options : array() );
        }
    }

    /**
     * A stored value for any owner, so rendering works the same everywhere.
     *
     * @param string $place    Place type.
     * @param int    $owner_id Owner.
     * @param string $key      Field key.
     * @return mixed
     */
    public static function get_place_value( $place, $owner_id, $key ) {
        if ( 'taxonomy' === $place ) {
            return get_term_meta( $owner_id, $key, true );
        }
        if ( 'user' === $place ) {
            return get_user_meta( $owner_id, $key, true );
        }
        $options = get_option( 'loopkit_field_options', array() );
        return is_array( $options ) ? ( $options[ $key ] ?? '' ) : '';
    }

    /**
     * Fields from this module appear in the editor's Dynamic Tags.
     *
     * @param array $groups Tag groups.
     * @return array
     */
    public function add_dynamic_tags( $groups ) {
        $tags        = array();
        $option_tags = array();
        foreach ( self::get_field_groups() as $group ) {
            if ( 'publish' !== $group['status'] ) {
                continue;
            }
            foreach ( $group['fields'] as $field ) {
                $key = $field['key'] ?? '';
                if ( '' === $key ) {
                    continue;
                }
                if ( $this->group_targets( $group, 'options' ) ) {
                    $option_tags[] = array(
                        'name'     => 'option',
                        'label'    => $field['label'] ?? $key,
                        'subgroup' => $group['title'],
                        'type'     => 'text',
                        'arg'      => $key,
                    );
                    continue;
                }

                $tags[] = array(
                    'name'     => 'custom_field',
                    'label'    => $field['label'] ?? $key,
                    'subgroup' => $group['title'],
                    'type'     => in_array( $field['type'] ?? '', array( 'image', 'gallery' ), true ) ? 'image' : 'text',
                    'arg'      => $key,
                );
            }
        }

        if ( $tags ) {
            $groups[] = array(
                'group' => __( 'Custom Fields', 'loopkit' ),
                'tags'  => $tags,
            );
        }
        if ( $option_tags ) {
            $groups[] = array(
                'group' => __( 'Site Options', 'loopkit' ),
                'tags'  => $option_tags,
            );
        }
        return $groups;
    }

    /**
     * Does a group aim at a given place?
     *
     * @param array  $group Group.
     * @param string $place Place type.
     * @return bool
     */
    private function group_targets( $group, $place ) {
        foreach ( $group['locations'] as $rule ) {
            if ( ( $rule['type'] ?? '' ) === $place ) {
                return true;
            }
        }
        return false;
    }
}
