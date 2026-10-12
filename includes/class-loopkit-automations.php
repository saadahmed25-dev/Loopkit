<?php
/**
 * Loopkit Automations.
 *
 * Rules in the shape CRMs use: WHEN something happens, IF these conditions hold, THEN do
 * these actions. Rules are stored in one option and run in order.
 *
 * Triggers
 *   form_submitted   — a Loopkit Contact Form was submitted (any form, or one page's form)
 *   post_published   — a post/page was published
 *
 * Conditions (all must hold; each is field / operator / value)
 *   A form field by label, or one of: page_url, form_name, user_email, user_role
 *   Operators: is, is_not, contains, not_contains, starts_with, ends_with, is_empty, not_empty
 *
 * Actions
 *   send_email       — to a fixed address, with {{Field}} placeholders
 *   autoresponder    — email the person who submitted (first email field)
 *   webhook          — POST the submission as JSON to a URL (Zapier, Make, n8n, a CRM)
 *   add_user_note    — store a note on the submission
 *   create_post      — create a draft post from the submission
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Automations {

    const OPTION = 'loopkit_automations';

    /**
     * Hook up.
     */
    public static function init() {
        add_action( 'loopkit_submission_stored', array( __CLASS__, 'on_submission' ), 10, 1 );
        add_action( 'transition_post_status', array( __CLASS__, 'on_post_published' ), 10, 3 );
        add_action( 'loopkit_order_placed', array( __CLASS__, 'on_order_placed' ), 10, 2 );
    }

    /**
     * All rules.
     *
     * @return array
     */
    public static function get_rules() {
        $rules = get_option( self::OPTION, array() );
        return is_array( $rules ) ? $rules : array();
    }

    /**
     * Save all rules.
     *
     * @param array $rules Rules.
     */
    public static function save_rules( $rules ) {
        update_option( self::OPTION, array_values( (array) $rules ) );
    }

    /**
     * Clean one rule coming from the admin form.
     *
     * @param array $raw Raw rule.
     * @return array
     */
    public static function sanitize_rule( $raw ) {
        $rule = array(
            'id'      => ! empty( $raw['id'] ) ? sanitize_key( $raw['id'] ) : uniqid( 'rule_' ),
            'name'    => sanitize_text_field( $raw['name'] ?? __( 'Untitled rule', 'loopkit' ) ),
            'active'  => ! empty( $raw['active'] ) ? 1 : 0,
            'trigger' => in_array( $raw['trigger'] ?? '', array( 'form_submitted', 'post_published', 'order_placed' ), true ) ? $raw['trigger'] : 'form_submitted',
            'trigger_post_id' => absint( $raw['trigger_post_id'] ?? 0 ),
            'conditions' => array(),
            'actions'    => array(),
        );

        foreach ( (array) ( $raw['conditions'] ?? array() ) as $c ) {
            $field = sanitize_text_field( $c['field'] ?? '' );
            if ( '' === $field ) {
                continue;
            }
            $rule['conditions'][] = array(
                'field'    => $field,
                'operator' => in_array( $c['operator'] ?? '', array( 'is', 'is_not', 'contains', 'not_contains', 'starts_with', 'ends_with', 'is_empty', 'not_empty' ), true ) ? $c['operator'] : 'contains',
                'value'    => sanitize_text_field( $c['value'] ?? '' ),
            );
        }

        foreach ( (array) ( $raw['actions'] ?? array() ) as $a ) {
            $type = sanitize_key( $a['type'] ?? '' );
            if ( ! in_array( $type, array( 'send_email', 'autoresponder', 'webhook', 'add_user_note', 'create_post' ), true ) ) {
                continue;
            }
            $rule['actions'][] = array(
                'type'    => $type,
                'to'      => sanitize_text_field( $a['to'] ?? '' ),
                'subject' => sanitize_text_field( $a['subject'] ?? '' ),
                'message' => wp_kses_post( $a['message'] ?? '' ),
                'url'     => esc_url_raw( $a['url'] ?? '' ),
            );
        }

        return $rule;
    }

    /**
     * A form was submitted.
     *
     * @param array $submission Stored submission.
     */
    public static function on_submission( $submission ) {
        foreach ( self::get_rules() as $rule ) {
            if ( empty( $rule['active'] ) || 'form_submitted' !== ( $rule['trigger'] ?? '' ) ) {
                continue;
            }
            if ( ! empty( $rule['trigger_post_id'] ) && (int) $rule['trigger_post_id'] !== (int) $submission['post_id'] ) {
                continue;
            }
            if ( ! self::conditions_pass( $rule['conditions'] ?? array(), $submission ) ) {
                continue;
            }
            self::run_actions( $rule, $submission );
        }
    }

    /**
     * A post was published.
     *
     * @param string  $new New status.
     * @param string  $old Old status.
     * @param WP_Post $post Post.
     */
    public static function on_post_published( $new, $old, $post ) {
        if ( 'publish' !== $new || 'publish' === $old || ! $post instanceof WP_Post ) {
            return;
        }
        if ( in_array( $post->post_type, array( 'revision', 'loopkit_template' ), true ) ) {
            return;
        }

        $context = array(
            'post_id'   => $post->ID,
            'form_name' => $post->post_title,
            'page_url'  => get_permalink( $post ),
            'fields'    => array(
                'Title'  => $post->post_title,
                'Type'   => $post->post_type,
                'Author' => get_the_author_meta( 'display_name', $post->post_author ),
            ),
            'created_at' => current_time( 'mysql' ),
        );

        foreach ( self::get_rules() as $rule ) {
            if ( empty( $rule['active'] ) || 'post_published' !== ( $rule['trigger'] ?? '' ) ) {
                continue;
            }
            if ( ! self::conditions_pass( $rule['conditions'] ?? array(), $context ) ) {
                continue;
            }
            self::run_actions( $rule, $context );
        }
    }

    /**
     * An order was placed (Shop module).
     *
     * @param int   $order_id Order.
     * @param array $items    Line items.
     */
    public static function on_order_placed( $order_id, $items ) {
        if ( ! class_exists( 'Loopkit_Shop_Orders' ) ) {
            return;
        }
        $order = Loopkit_Shop_Orders::get_order( $order_id );
        $names = array();
        foreach ( (array) $items as $item ) {
            $names[] = $item['name'] . ' × ' . $item['qty'];
        }

        $context = array(
            'id'        => $order_id,
            'post_id'   => $order_id,
            'form_name' => sprintf( /* translators: %d: order number */ __( 'Order #%d', 'loopkit' ), $order_id ),
            'page_url'  => admin_url( 'admin.php?page=loopkit-orders&order=' . $order_id ),
            'created_at' => current_time( 'mysql' ),
            'fields'    => array(
                'Order'    => '#' . $order_id,
                'Name'     => trim( $order['customer']['first_name'] . ' ' . $order['customer']['last_name'] ),
                'Email'    => $order['customer']['email'],
                'Phone'    => $order['customer']['phone'],
                'Address'  => trim( $order['customer']['address'] . ', ' . $order['customer']['city'] ),
                'Items'    => implode( ', ', $names ),
                'Total'    => Loopkit_Module_WooCommerce::format_price( $order['total'] ),
                'Payment'  => $order['payment'],
                'Status'   => $order['status'],
            ),
        );

        foreach ( self::get_rules() as $rule ) {
            if ( empty( $rule['active'] ) || 'order_placed' !== ( $rule['trigger'] ?? '' ) ) {
                continue;
            }
            if ( ! self::conditions_pass( $rule['conditions'] ?? array(), $context ) ) {
                continue;
            }
            self::run_actions( $rule, $context );
        }
    }

    /**
     * Do all conditions hold?
     *
     * @param array $conditions Conditions.
     * @param array $data       Submission or context.
     * @return bool
     */
    public static function conditions_pass( $conditions, $data ) {
        if ( empty( $conditions ) ) {
            return true;
        }

        foreach ( $conditions as $c ) {
            $actual = self::field_value( $c['field'], $data );
            $value  = (string) ( $c['value'] ?? '' );
            $ok     = false;

            switch ( $c['operator'] ) {
                case 'is':           $ok = 0 === strcasecmp( trim( $actual ), trim( $value ) ); break;
                case 'is_not':       $ok = 0 !== strcasecmp( trim( $actual ), trim( $value ) ); break;
                case 'contains':     $ok = '' !== $value && false !== stripos( $actual, $value ); break;
                case 'not_contains': $ok = '' === $value || false === stripos( $actual, $value ); break;
                case 'starts_with':  $ok = 0 === stripos( $actual, $value ); break;
                case 'ends_with':    $ok = '' !== $value && substr( strtolower( $actual ), -strlen( $value ) ) === strtolower( $value ); break;
                case 'is_empty':     $ok = '' === trim( $actual ); break;
                case 'not_empty':    $ok = '' !== trim( $actual ); break;
            }

            if ( ! $ok ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Value of one condition field.
     *
     * @param string $field Field label or special key.
     * @param array  $data  Submission.
     * @return string
     */
    private static function field_value( $field, $data ) {
        switch ( $field ) {
            case 'page_url':   return (string) ( $data['page_url'] ?? '' );
            case 'form_name':  return (string) ( $data['form_name'] ?? '' );
            case 'user_email':
                foreach ( (array) ( $data['fields'] ?? array() ) as $value ) {
                    if ( is_string( $value ) && is_email( trim( $value ) ) ) {
                        return trim( $value );
                    }
                }
                return '';
            case 'user_role':
                $user = wp_get_current_user();
                return ( $user && $user->ID ) ? implode( ',', (array) $user->roles ) : '';
        }

        // A form field, matched on its label (case-insensitive)
        foreach ( (array) ( $data['fields'] ?? array() ) as $label => $value ) {
            if ( 0 === strcasecmp( (string) $label, (string) $field ) ) {
                return is_scalar( $value ) ? (string) $value : '';
            }
        }
        return '';
    }

    /**
     * Replace {{Field}} placeholders.
     *
     * @param string $text Text with placeholders.
     * @param array  $data Submission.
     * @return string
     */
    public static function replace_tags( $text, $data ) {
        $text = (string) $text;

        $text = str_replace(
            array( '{{page_url}}', '{{form_name}}', '{{date}}', '{{all_fields}}' ),
            array(
                (string) ( $data['page_url'] ?? '' ),
                (string) ( $data['form_name'] ?? '' ),
                (string) ( $data['created_at'] ?? current_time( 'mysql' ) ),
                self::fields_as_text( $data ),
            ),
            $text
        );

        foreach ( (array) ( $data['fields'] ?? array() ) as $label => $value ) {
            $text = str_ireplace( '{{' . $label . '}}', is_scalar( $value ) ? (string) $value : '', $text );
        }
        return $text;
    }

    /**
     * All fields as readable text.
     *
     * @param array $data Submission.
     * @return string
     */
    private static function fields_as_text( $data ) {
        $out = array();
        foreach ( (array) ( $data['fields'] ?? array() ) as $label => $value ) {
            $out[] = $label . ': ' . ( is_scalar( $value ) ? $value : '' );
        }
        return implode( "\n", $out );
    }

    /**
     * Run a rule's actions.
     *
     * @param array $rule Rule.
     * @param array $data Submission.
     */
    public static function run_actions( $rule, $data ) {
        foreach ( (array) ( $rule['actions'] ?? array() ) as $action ) {
            switch ( $action['type'] ) {
                case 'send_email':
                    $to = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', self::replace_tags( $action['to'], $data ) ) ) ), 'is_email' );
                    if ( ! $to ) {
                        $to = array( get_option( 'admin_email' ) );
                    }
                    wp_mail(
                        $to,
                        self::replace_tags( $action['subject'] ?: __( 'Loopkit automation', 'loopkit' ), $data ),
                        self::replace_tags( $action['message'] ?: '{{all_fields}}', $data )
                    );
                    break;

                case 'autoresponder':
                    $email = self::field_value( 'user_email', $data );
                    if ( $email ) {
                        wp_mail(
                            $email,
                            self::replace_tags( $action['subject'] ?: __( 'Thanks for getting in touch', 'loopkit' ), $data ),
                            self::replace_tags( $action['message'] ?: __( 'Thanks, we have received your message and will reply soon.', 'loopkit' ), $data )
                        );
                    }
                    break;

                case 'webhook':
                    if ( ! empty( $action['url'] ) ) {
                        wp_remote_post( $action['url'], array(
                            'timeout'  => 10,
                            'blocking' => false,
                            'headers'  => array( 'Content-Type' => 'application/json' ),
                            'body'     => wp_json_encode( array(
                                'rule'       => $rule['name'] ?? '',
                                'form'       => $data['form_name'] ?? '',
                                'page_url'   => $data['page_url'] ?? '',
                                'created_at' => $data['created_at'] ?? '',
                                'fields'     => $data['fields'] ?? array(),
                            ) ),
                        ) );
                    }
                    break;

                case 'add_user_note':
                    if ( ! empty( $data['id'] ) ) {
                        global $wpdb;
                        $table = Loopkit_Submissions::table();
                        $note  = self::replace_tags( $action['message'], $data );
                        $fields = (array) ( $data['fields'] ?? array() );
                        $fields['Note'] = trim( ( $fields['Note'] ?? '' ) . "\n" . $note );
                        $wpdb->update( $table, array( 'fields' => wp_json_encode( $fields ) ), array( 'id' => (int) $data['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    }
                    break;

                case 'create_post':
                    wp_insert_post( array(
                        'post_type'    => 'post',
                        'post_status'  => 'draft',
                        'post_title'   => self::replace_tags( $action['subject'] ?: '{{form_name}} — {{date}}', $data ),
                        'post_content' => self::replace_tags( $action['message'] ?: '{{all_fields}}', $data ),
                    ) );
                    break;
            }
        }

        /**
         * A rule has run.
         *
         * @param array $rule Rule.
         * @param array $data Submission.
         */
        do_action( 'loopkit_automation_ran', $rule, $data );
    }
}
