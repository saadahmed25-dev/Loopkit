<?php
/**
 * Loopkit admin screens for Submissions and Automations.
 *
 * Kept in its own class so the main admin file stays readable.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Admin_Automations {

    /**
     * Hook up.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menus' ), 20 );
        add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
    }

    /**
     * Menu entries.
     */
    public static function register_menus() {
        add_submenu_page(
            'loopkit',
            esc_html__( 'Submissions', 'loopkit' ),
            esc_html__( 'Submissions', 'loopkit' ),
            'edit_pages',
            'loopkit-submissions',
            array( __CLASS__, 'render_submissions' )
        );

        add_submenu_page(
            'loopkit',
            esc_html__( 'Automations', 'loopkit' ),
            esc_html__( 'Automations', 'loopkit' ),
            'manage_options',
            'loopkit-automations',
            array( __CLASS__, 'render_automations' )
        );
    }

    /**
     * Save rules, delete submissions, export CSV.
     */
    public static function handle_actions() {
        if ( empty( $_POST['loopkit_auto_action'] ) && empty( $_GET['loopkit_auto_action'] ) ) {
            return;
        }
        $action = sanitize_key( wp_unslash( $_POST['loopkit_auto_action'] ?? $_GET['loopkit_auto_action'] ?? '' ) );

        // Export
        if ( 'export_csv' === $action ) {
            if ( ! current_user_can( 'edit_pages' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'loopkit_export_csv' ) ) {
                wp_die( esc_html__( 'You are not allowed to export submissions.', 'loopkit' ) );
            }
            $source = sanitize_key( wp_unslash( $_GET['source'] ?? 'form' ) );
            $csv    = Loopkit_Submissions::to_csv( array(
                'search' => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
                'source' => $source,
            ) );
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=loopkit-' . ( 'shop' === $source ? 'shop-' : '' ) . 'submissions-' . gmdate( 'Y-m-d' ) . '.csv' );
            echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        // Delete one submission
        if ( 'delete_submission' === $action ) {
            if ( ! current_user_can( 'edit_pages' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'loopkit_delete_submission' ) ) {
                wp_die( esc_html__( 'You are not allowed to delete submissions.', 'loopkit' ) );
            }
            Loopkit_Submissions::delete( absint( $_GET['id'] ?? 0 ) );
            wp_safe_redirect( admin_url( 'admin.php?page=loopkit-submissions&deleted=1' ) );
            exit;
        }

        // Save / delete a rule
        if ( in_array( $action, array( 'save_rule', 'delete_rule' ), true ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You are not allowed to manage automations.', 'loopkit' ) );
            }
            check_admin_referer( 'loopkit_automations' );

            $rules = Loopkit_Automations::get_rules();

            if ( 'delete_rule' === $action ) {
                $id    = sanitize_key( wp_unslash( $_POST['rule_id'] ?? '' ) );
                $rules = array_values( array_filter( $rules, function( $r ) use ( $id ) {
                    return ( $r['id'] ?? '' ) !== $id;
                } ) );
                Loopkit_Automations::save_rules( $rules );
                wp_safe_redirect( admin_url( 'admin.php?page=loopkit-automations&saved=deleted' ) );
                exit;
            }

            $raw  = wp_unslash( $_POST['rule'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $rule = Loopkit_Automations::sanitize_rule( is_array( $raw ) ? $raw : array() );

            $found = false;
            foreach ( $rules as $i => $existing ) {
                if ( ( $existing['id'] ?? '' ) === $rule['id'] ) {
                    $rules[ $i ] = $rule;
                    $found       = true;
                    break;
                }
            }
            if ( ! $found ) {
                $rules[] = $rule;
            }

            Loopkit_Automations::save_rules( $rules );
            wp_safe_redirect( admin_url( 'admin.php?page=loopkit-automations&saved=1' ) );
            exit;
        }
    }

    /**
     * Submissions screen.
     */
    public static function render_submissions() {
        Loopkit_Submissions::install();

        $search   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
        $paged    = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
        $per_page = 20;
        $rows     = Loopkit_Submissions::get_submissions( array( 'search' => $search, 'page' => $paged, 'per_page' => $per_page ) );
        $total    = Loopkit_Submissions::count_submissions( array( 'search' => $search ) );
        $pages    = max( 1, (int) ceil( $total / $per_page ) );
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Form Submissions', 'loopkit' ); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=loopkit-submissions&loopkit_auto_action=export_csv&s=' . rawurlencode( $search ) ), 'loopkit_export_csv' ) ); ?>">
                <?php esc_html_e( 'Export CSV', 'loopkit' ); ?>
            </a>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Submission deleted.', 'loopkit' ); ?></p></div>
            <?php endif; ?>

            <form method="get" style="margin:16px 0">
                <input type="hidden" name="page" value="loopkit-submissions">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search submissions…', 'loopkit' ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'Search', 'loopkit' ); ?></button>
                <span style="margin-left:10px;color:#646970"><?php
                    /* translators: %d: number of submissions */
                    printf( esc_html__( '%d total', 'loopkit' ), (int) $total );
                ?></span>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:150px"><?php esc_html_e( 'Date', 'loopkit' ); ?></th>
                        <th><?php esc_html_e( 'Submission', 'loopkit' ); ?></th>
                        <th style="width:200px"><?php esc_html_e( 'Page', 'loopkit' ); ?></th>
                        <th style="width:90px"><?php esc_html_e( 'Email', 'loopkit' ); ?></th>
                        <th style="width:80px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'No submissions yet. They appear here as soon as a Contact Form is used.', 'loopkit' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $rows as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( mysql2date( 'M j, Y H:i', $row['created_at'] ) ); ?></td>
                            <td>
                                <?php foreach ( $row['fields'] as $label => $value ) : ?>
                                    <div><strong><?php echo esc_html( $label ); ?>:</strong> <?php echo esc_html( wp_trim_words( (string) $value, 30 ) ); ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?php if ( $row['page_url'] ) : ?>
                                    <a href="<?php echo esc_url( $row['page_url'] ); ?>" target="_blank" rel="noreferrer"><?php echo esc_html( $row['form_name'] ?: $row['page_url'] ); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html( $row['form_name'] ); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $row['mail_sent'] ? esc_html__( 'Sent', 'loopkit' ) : '<span style="color:#b32d2e">' . esc_html__( 'Failed', 'loopkit' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                            <td>
                                <a class="button-link delete" style="color:#b32d2e"
                                   href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=loopkit-submissions&loopkit_auto_action=delete_submission&id=' . (int) $row['id'] ), 'loopkit_delete_submission' ) ); ?>"
                                   onclick="return confirm('<?php esc_attr_e( 'Delete this submission?', 'loopkit' ); ?>')"><?php esc_html_e( 'Delete', 'loopkit' ); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $pages > 1 ) : ?>
                <div class="tablenav"><div class="tablenav-pages"><?php
                    echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        'base'    => admin_url( 'admin.php?page=loopkit-submissions&paged=%#%&s=' . rawurlencode( $search ) ),
                        'format'  => '',
                        'current' => $paged,
                        'total'   => $pages,
                    ) );
                ?></div></div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Automations screen.
     */
    public static function render_automations() {
        $rules   = Loopkit_Automations::get_rules();
        $edit_id = sanitize_key( wp_unslash( $_GET['rule'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
        $editing = null;
        foreach ( $rules as $r ) {
            if ( ( $r['id'] ?? '' ) === $edit_id ) {
                $editing = $r;
            }
        }
        $is_new = ( 'new' === $edit_id );
        ?>
        <div class="wrap lk-admin-wrap lk-admin-simple">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Automations', 'loopkit' ); ?></h1>
            <?php if ( ! $editing && ! $is_new ) : ?>
                <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-automations&rule=new' ) ); ?>"><?php esc_html_e( 'Add Rule', 'loopkit' ); ?></a>
            <?php endif; ?>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                <div class="notice notice-success is-dismissible"><p><?php
                    echo 'deleted' === $_GET['saved'] ? esc_html__( 'Rule deleted.', 'loopkit' ) : esc_html__( 'Rule saved.', 'loopkit' ); // phpcs:ignore WordPress.Security.NonceVerification
                ?></p></div>
            <?php endif; ?>

            <?php if ( $editing || $is_new ) : self::render_rule_form( $editing ); return; ?>
            <?php endif; ?>

            <p><?php esc_html_e( 'Rules run automatically: when something happens, if your conditions hold, Loopkit does the actions — like an email, an autoresponder or a webhook to your CRM.', 'loopkit' ); ?></p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Rule', 'loopkit' ); ?></th>
                        <th style="width:180px"><?php esc_html_e( 'When', 'loopkit' ); ?></th>
                        <th style="width:120px"><?php esc_html_e( 'Conditions', 'loopkit' ); ?></th>
                        <th style="width:220px"><?php esc_html_e( 'Then', 'loopkit' ); ?></th>
                        <th style="width:90px"><?php esc_html_e( 'Status', 'loopkit' ); ?></th>
                        <th style="width:80px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $rules ) ) : ?>
                    <tr><td colspan="6"><?php esc_html_e( 'No rules yet. Add one to send emails, autoresponders or webhooks when a form is submitted.', 'loopkit' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $rules as $rule ) : ?>
                        <tr>
                            <td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-automations&rule=' . rawurlencode( $rule['id'] ) ) ); ?>"><?php echo esc_html( $rule['name'] ); ?></a></strong></td>
                            <td><?php echo esc_html( 'post_published' === $rule['trigger'] ? __( 'Post published', 'loopkit' ) : __( 'Form submitted', 'loopkit' ) ); ?></td>
                            <td><?php echo (int) count( $rule['conditions'] ?? array() ); ?></td>
                            <td><?php
                                $labels = array(
                                    'send_email'    => __( 'Send email', 'loopkit' ),
                                    'autoresponder' => __( 'Autoresponder', 'loopkit' ),
                                    'webhook'       => __( 'Webhook', 'loopkit' ),
                                    'add_user_note' => __( 'Add note', 'loopkit' ),
                                    'create_post'   => __( 'Create draft post', 'loopkit' ),
                                );
                                $names = array();
                                foreach ( (array) ( $rule['actions'] ?? array() ) as $a ) {
                                    $names[] = $labels[ $a['type'] ] ?? $a['type'];
                                }
                                echo esc_html( implode( ', ', $names ) );
                            ?></td>
                            <td><?php echo empty( $rule['active'] ) ? esc_html__( 'Paused', 'loopkit' ) : '<span style="color:#008a20">' . esc_html__( 'Active', 'loopkit' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('<?php esc_attr_e( 'Delete this rule?', 'loopkit' ); ?>')">
                                    <?php wp_nonce_field( 'loopkit_automations' ); ?>
                                    <input type="hidden" name="loopkit_auto_action" value="delete_rule">
                                    <input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule['id'] ); ?>">
                                    <button type="submit" class="button-link delete" style="color:#b32d2e"><?php esc_html_e( 'Delete', 'loopkit' ); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Add / edit rule form.
     *
     * @param array|null $rule Rule being edited.
     */
    private static function render_rule_form( $rule ) {
        $rule = wp_parse_args( (array) $rule, array(
            'id'              => '',
            'name'            => '',
            'active'          => 1,
            'trigger'         => 'form_submitted',
            'trigger_post_id' => 0,
            'conditions'      => array(),
            'actions'         => array(),
        ) );
        $conditions = $rule['conditions'] ? $rule['conditions'] : array( array( 'field' => '', 'operator' => 'contains', 'value' => '' ) );
        $actions    = $rule['actions'] ? $rule['actions'] : array( array( 'type' => 'send_email', 'to' => get_option( 'admin_email' ), 'subject' => '', 'message' => '', 'url' => '' ) );

        $pages = get_posts( array(
            'post_type'      => array( 'page', 'post' ),
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $operators = array(
            'is'           => __( 'is exactly', 'loopkit' ),
            'is_not'       => __( 'is not', 'loopkit' ),
            'contains'     => __( 'contains', 'loopkit' ),
            'not_contains' => __( 'does not contain', 'loopkit' ),
            'starts_with'  => __( 'starts with', 'loopkit' ),
            'ends_with'    => __( 'ends with', 'loopkit' ),
            'is_empty'     => __( 'is empty', 'loopkit' ),
            'not_empty'    => __( 'is not empty', 'loopkit' ),
        );
        $action_types = array(
            'send_email'    => __( 'Send an email to me / my team', 'loopkit' ),
            'autoresponder' => __( 'Send an autoresponder to the person', 'loopkit' ),
            'webhook'       => __( 'Send a webhook (Zapier, Make, CRM)', 'loopkit' ),
            'add_user_note' => __( 'Add a note to the submission', 'loopkit' ),
            'create_post'   => __( 'Create a draft post', 'loopkit' ),
        );
        ?>
        <form method="post" class="lk-automation-form">
            <?php wp_nonce_field( 'loopkit_automations' ); ?>
            <input type="hidden" name="loopkit_auto_action" value="save_rule">
            <input type="hidden" name="rule[id]" value="<?php echo esc_attr( $rule['id'] ); ?>">

            <div class="lk-card lk-auto-card">
                <div class="lk-card-header">
                    <h3><?php esc_html_e( 'Rule', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc"><?php esc_html_e( 'Give the rule a name you will recognise later, such as "Email me new leads".', 'loopkit' ); ?></p>
                </div>
                <div class="lk-card-body lk-auto-grid">
                    <div class="lk-auto-field">
                        <label for="lk-rule-name"><?php esc_html_e( 'Rule name', 'loopkit' ); ?></label>
                        <input type="text" id="lk-rule-name" name="rule[name]" value="<?php echo esc_attr( $rule['name'] ); ?>" placeholder="<?php esc_attr_e( 'Email me new leads', 'loopkit' ); ?>" required>
                    </div>
                    <div class="lk-auto-field">
                        <label><?php esc_html_e( 'Status', 'loopkit' ); ?></label>
                        <label class="lk-auto-check"><input type="checkbox" name="rule[active]" value="1" <?php checked( ! empty( $rule['active'] ) ); ?>> <?php esc_html_e( 'Active — run this rule', 'loopkit' ); ?></label>
                    </div>
                </div>
            </div>

            <div class="lk-card lk-auto-card">
                <div class="lk-card-header">
                    <span class="lk-auto-step">1</span>
                    <h3><?php esc_html_e( 'When this happens', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc"><?php esc_html_e( 'What starts the rule.', 'loopkit' ); ?></p>
                </div>
                <div class="lk-card-body lk-auto-grid">
                    <div class="lk-auto-field">
                        <label for="lk-rule-trigger"><?php esc_html_e( 'Trigger', 'loopkit' ); ?></label>
                        <select id="lk-rule-trigger" name="rule[trigger]">
                            <option value="form_submitted" <?php selected( $rule['trigger'], 'form_submitted' ); ?>><?php esc_html_e( 'A form is submitted', 'loopkit' ); ?></option>
                            <option value="post_published" <?php selected( $rule['trigger'], 'post_published' ); ?>><?php esc_html_e( 'A post is published', 'loopkit' ); ?></option>
                            <?php if ( class_exists( 'Loopkit_Shop_Orders' ) ) : ?>
                                <option value="order_placed" <?php selected( $rule['trigger'], 'order_placed' ); ?>><?php esc_html_e( 'An order is placed', 'loopkit' ); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="lk-auto-field" id="lk-trigger-page">
                        <label for="lk-rule-page"><?php esc_html_e( 'Only on this page', 'loopkit' ); ?></label>
                        <select id="lk-rule-page" name="rule[trigger_post_id]">
                            <option value="0"><?php esc_html_e( 'Every form on the site', 'loopkit' ); ?></option>
                            <?php foreach ( $pages as $p ) : ?>
                                <option value="<?php echo (int) $p->ID; ?>" <?php selected( (int) $rule['trigger_post_id'], (int) $p->ID ); ?>>
                                    <?php echo esc_html( $p->post_title . ' (#' . $p->ID . ')' ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="lk-auto-help"><?php esc_html_e( 'Leave as "Every form" unless this rule is only for one page.', 'loopkit' ); ?></span>
                    </div>
                </div>
            </div>

            <div class="lk-card lk-auto-card">
                <div class="lk-card-header">
                    <span class="lk-auto-step">2</span>
                    <h3><?php esc_html_e( 'Only if', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc"><?php esc_html_e( 'Every condition must hold. Leave them empty to run every time.', 'loopkit' ); ?></p>
                </div>
                <div class="lk-card-body">
                    <div id="lk-conditions-rows">
                        <?php foreach ( $conditions as $i => $c ) : ?>
                            <div class="lk-auto-row">
                                <div class="lk-auto-field">
                                    <label><?php esc_html_e( 'Field', 'loopkit' ); ?></label>
                                    <input type="text" name="rule[conditions][<?php echo (int) $i; ?>][field]" value="<?php echo esc_attr( $c['field'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Email', 'loopkit' ); ?>" list="lk-field-suggestions">
                                </div>
                                <div class="lk-auto-field">
                                    <label><?php esc_html_e( 'Rule', 'loopkit' ); ?></label>
                                    <select name="rule[conditions][<?php echo (int) $i; ?>][operator]">
                                        <?php foreach ( $operators as $op => $label ) : ?>
                                            <option value="<?php echo esc_attr( $op ); ?>" <?php selected( $c['operator'] ?? '', $op ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="lk-auto-field">
                                    <label><?php esc_html_e( 'Value', 'loopkit' ); ?></label>
                                    <input type="text" name="rule[conditions][<?php echo (int) $i; ?>][value]" value="<?php echo esc_attr( $c['value'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'gmail.com', 'loopkit' ); ?>">
                                </div>
                                <button type="button" class="lk-auto-remove" aria-label="<?php esc_attr_e( 'Remove condition', 'loopkit' ); ?>">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <datalist id="lk-field-suggestions">
                        <option value="Name"></option><option value="Email"></option><option value="Phone"></option>
                        <option value="Message"></option><option value="page_url"></option><option value="form_name"></option>
                        <option value="user_email"></option><option value="user_role"></option>
                    </datalist>
                    <button type="button" class="lk-btn lk-btn-secondary" id="lk-add-condition"><?php esc_html_e( '+ Add condition', 'loopkit' ); ?></button>
                </div>
            </div>

            <div class="lk-card lk-auto-card">
                <div class="lk-card-header">
                    <span class="lk-auto-step">3</span>
                    <h3><?php esc_html_e( 'Then do this', 'loopkit' ); ?></h3>
                    <p class="lk-card-desc">
                        <?php esc_html_e( 'Placeholders you can use in a subject or message:', 'loopkit' ); ?>
                        <code>{{Name}}</code> <code>{{Email}}</code> <code>{{all_fields}}</code> <code>{{page_url}}</code> <code>{{form_name}}</code> <code>{{date}}</code>
                    </p>
                </div>
                <div class="lk-card-body">
                    <div id="lk-actions-rows">
                        <?php foreach ( $actions as $i => $a ) : $type = $a['type'] ?? 'send_email'; ?>
                            <div class="lk-auto-action" data-type="<?php echo esc_attr( $type ); ?>">
                                <div class="lk-auto-field lk-auto-field-full">
                                    <label><?php esc_html_e( 'Action', 'loopkit' ); ?></label>
                                    <select class="lk-action-type" name="rule[actions][<?php echo (int) $i; ?>][type]">
                                        <?php foreach ( $action_types as $key => $label ) : ?>
                                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="lk-auto-field" data-for="to">
                                    <label><?php esc_html_e( 'Send to', 'loopkit' ); ?></label>
                                    <input type="text" name="rule[actions][<?php echo (int) $i; ?>][to]" value="<?php echo esc_attr( $a['to'] ?? '' ); ?>" placeholder="you@yoursite.com, sales@yoursite.com">
                                    <span class="lk-auto-help"><?php esc_html_e( 'One or more addresses, separated by commas.', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-auto-field" data-for="subject">
                                    <label><?php esc_html_e( 'Subject', 'loopkit' ); ?></label>
                                    <input type="text" name="rule[actions][<?php echo (int) $i; ?>][subject]" value="<?php echo esc_attr( $a['subject'] ?? '' ); ?>" placeholder="New enquiry from {{Name}}">
                                </div>
                                <div class="lk-auto-field lk-auto-field-full" data-for="url">
                                    <label><?php esc_html_e( 'Webhook URL', 'loopkit' ); ?></label>
                                    <input type="url" name="rule[actions][<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $a['url'] ?? '' ); ?>" placeholder="https://hooks.zapier.com/hooks/catch/123/abc">
                                    <span class="lk-auto-help"><?php esc_html_e( 'Loopkit posts the submission there as JSON.', 'loopkit' ); ?></span>
                                </div>
                                <div class="lk-auto-field lk-auto-field-full" data-for="message">
                                    <label><?php esc_html_e( 'Message', 'loopkit' ); ?></label>
                                    <textarea name="rule[actions][<?php echo (int) $i; ?>][message]" rows="4" placeholder="{{all_fields}}&#10;&#10;Page: {{page_url}}"><?php echo esc_textarea( $a['message'] ?? '' ); ?></textarea>
                                </div>
                                <button type="button" class="lk-auto-remove" aria-label="<?php esc_attr_e( 'Remove action', 'loopkit' ); ?>">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="lk-btn lk-btn-secondary" id="lk-add-action"><?php esc_html_e( '+ Add action', 'loopkit' ); ?></button>
                </div>
            </div>

            <p class="lk-auto-submit">
                <button type="submit" class="lk-btn lk-btn-primary"><?php esc_html_e( 'Save Rule', 'loopkit' ); ?></button>
                <a class="lk-btn lk-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=loopkit-automations' ) ); ?>"><?php esc_html_e( 'Cancel', 'loopkit' ); ?></a>
            </p>
        </form>

        <script>
        ( function () {
            var cRows = document.getElementById( 'lk-conditions-rows' );
            var aRows = document.getElementById( 'lk-actions-rows' );

            // Only show the fields the chosen action actually uses
            var FIELDS = {
                send_email:    [ 'to', 'subject', 'message' ],
                autoresponder: [ 'subject', 'message' ],
                webhook:       [ 'url' ],
                add_user_note: [ 'message' ],
                create_post:   [ 'subject', 'message' ]
            };
            function syncAction( box ) {
                var type = box.querySelector( '.lk-action-type' ).value;
                box.setAttribute( 'data-type', type );
                var used = FIELDS[ type ] || [];
                box.querySelectorAll( '[data-for]' ).forEach( function ( f ) {
                    f.style.display = used.indexOf( f.getAttribute( 'data-for' ) ) === -1 ? 'none' : '';
                } );
            }
            function syncAll() { aRows.querySelectorAll( '.lk-auto-action' ).forEach( syncAction ); }

            function renumber( wrap, key, selector ) {
                Array.prototype.forEach.call( wrap.querySelectorAll( selector ), function ( row, i ) {
                    row.querySelectorAll( '[name]' ).forEach( function ( f ) {
                        f.name = f.name.replace( new RegExp( '\\[' + key + '\\]\\[\\d+\\]' ), '[' + key + '][' + i + ']' );
                    } );
                } );
            }

            document.getElementById( 'lk-add-condition' ).addEventListener( 'click', function () {
                var row = cRows.children[0].cloneNode( true );
                row.querySelectorAll( 'input' ).forEach( function ( f ) { f.value = ''; } );
                cRows.appendChild( row );
                renumber( cRows, 'conditions', '.lk-auto-row' );
            } );

            document.getElementById( 'lk-add-action' ).addEventListener( 'click', function () {
                var box = aRows.children[0].cloneNode( true );
                box.querySelectorAll( 'input, textarea' ).forEach( function ( f ) { f.value = ''; } );
                aRows.appendChild( box );
                renumber( aRows, 'actions', '.lk-auto-action' );
                syncAction( box );
            } );

            document.addEventListener( 'click', function ( e ) {
                var btn = e.target.closest ? e.target.closest( '.lk-auto-remove' ) : null;
                if ( ! btn ) return;
                var row = btn.closest( '.lk-auto-row, .lk-auto-action' );
                var wrap = row.parentNode;
                if ( wrap.children.length === 1 ) {
                    row.querySelectorAll( 'input, textarea' ).forEach( function ( f ) { f.value = ''; } );
                    return;
                }
                row.remove();
                if ( wrap === cRows ) renumber( cRows, 'conditions', '.lk-auto-row' );
                else renumber( aRows, 'actions', '.lk-auto-action' );
            } );

            aRows.addEventListener( 'change', function ( e ) {
                if ( e.target.classList.contains( 'lk-action-type' ) ) syncAction( e.target.closest( '.lk-auto-action' ) );
            } );

            // "Only on this page" makes no sense for the post trigger
            var trigger = document.getElementById( 'lk-rule-trigger' );
            function syncTrigger() {
                document.getElementById( 'lk-trigger-page' ).style.display = trigger.value === 'form_submitted' ? '' : 'none';
            }
            trigger.addEventListener( 'change', syncTrigger );

            syncAll();
            syncTrigger();
        } )();
        </script>
        <?php
    }

}
