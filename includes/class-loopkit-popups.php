<?php
/**
 * Loopkit Popups.
 *
 * A popup is a template (type "popup") with its own settings: size, position, overlay,
 * entrance animation, how often it may appear, and one or more triggers (page load,
 * scroll, exit intent, inactivity, click). Display Conditions decide which pages it
 * belongs to, exactly like Header/Footer/Single/Archive templates.
 *
 * Every matching popup is printed in the footer as hidden markup plus a small JSON
 * settings block; assets/js/loopkit-popup.js does the rest on the front end.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Popups {

    /**
     * Popups already printed on this request (never print one twice).
     *
     * @var array
     */
    private static $printed = array();

    /**
     * Hook up.
     */
    public static function init() {
        add_action( 'wp_footer', array( __CLASS__, 'render_popups' ), 20 );
    }

    /**
     * Default settings for a popup template.
     *
     * @return array
     */
    public static function defaults() {
        return array(
            'width'             => 600,
            'width_unit'        => 'px',
            'height'            => '',
            'height_unit'       => 'px',
            'position'          => 'center',
            'overlay'           => 'yes',
            'overlay_color'     => 'rgba(0,0,0,0.75)',
            'close_on_overlay'  => 'yes',
            'close_on_esc'      => 'yes',
            'close_button'      => 'yes',
            'close_button_position' => 'inside',
            'animation'         => 'fade',
            'prevent_scroll'    => 'yes',
            'background'        => '#ffffff',
            'border_radius'     => 8,
            'padding'           => 30,

            // Triggers
            'trigger_load'       => 'yes',
            'trigger_load_delay' => 2,
            'trigger_scroll'     => 'no',
            'trigger_scroll_at'  => 50,
            'trigger_exit'       => 'no',
            'trigger_idle'       => 'no',
            'trigger_idle_after' => 30,
            'trigger_click'      => 'no',
            'trigger_click_selector' => '',

            // How often
            'frequency'          => 'session',   // always | session | days
            'frequency_days'     => 7,
            'close_stops_it'     => 'yes',
        );
    }

    /**
     * Settings of one popup, merged with the defaults.
     *
     * @param int $popup_id Template ID.
     * @return array
     */
    public static function get_settings( $popup_id ) {
        $saved = get_post_meta( $popup_id, '_loopkit_popup_settings', true );
        $saved = is_array( $saved ) ? $saved : array();
        return array_merge( self::defaults(), $saved );
    }

    /**
     * Sanitize settings coming from the editor.
     *
     * @param array $input Raw settings.
     * @return array
     */
    public static function sanitize_settings( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }
        $out      = array();
        $defaults = self::defaults();
        foreach ( $defaults as $key => $default ) {
            if ( ! array_key_exists( $key, $input ) ) {
                continue;
            }
            $value = $input[ $key ];
            if ( is_int( $default ) ) {
                $out[ $key ] = '' === $value ? '' : (float) $value;
            } else {
                $out[ $key ] = sanitize_text_field( (string) $value );
            }
        }
        return $out;
    }

    /**
     * Published popup templates whose Display Conditions match this request.
     *
     * @return array List of post IDs.
     */
    public static function get_matching_popups() {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return array();
        }

        $popups = get_posts( array(
            'post_type'      => 'loopkit_template',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'meta_key'       => '_loopkit_template_type',
            'meta_value'     => 'popup',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ) );

        if ( empty( $popups ) ) {
            return array();
        }

        $builder = class_exists( 'Loopkit_Theme_Builder' ) ? Loopkit_Theme_Builder::get_instance() : null;
        $matched = array();

        foreach ( $popups as $popup_id ) {
            $conditions = get_post_meta( $popup_id, '_loopkit_conditions', true );
            if ( empty( $conditions ) || ! is_array( $conditions ) ) {
                continue; // a popup without conditions is never shown by itself
            }
            // evaluate_conditions() returns array( matched, score, reason ) — not a boolean.
            if ( $builder && method_exists( $builder, 'evaluate_conditions' ) ) {
                $result = $builder->evaluate_conditions( $conditions );
                if ( ! is_array( $result ) || empty( $result['matched'] ) ) {
                    continue;
                }
            }
            $matched[] = (int) $popup_id;
        }

        return $matched;
    }

    /**
     * Print the matching popups in the footer.
     */
    public static function render_popups() {
        if ( is_admin() || is_feed() || is_embed() || isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $popups = self::get_matching_popups();
        if ( empty( $popups ) ) {
            return;
        }

        // Popups are printed in the footer, by which point scripts and styles may already
        // have gone out, so print them here if they have not.
        wp_enqueue_style( 'loopkit-popup' );
        if ( ! wp_style_is( 'loopkit-popup', 'done' ) ) {
            wp_print_styles( array( 'loopkit-popup' ) );
        }
        wp_enqueue_script( 'loopkit-popup' );
        if ( ! wp_script_is( 'loopkit-popup', 'done' ) ) {
            wp_print_scripts( array( 'loopkit-popup' ) );
        }

        foreach ( $popups as $popup_id ) {
            echo self::render_popup( $popup_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    /**
     * Markup of one popup.
     *
     * @param int $popup_id Template ID.
     * @return string
     */
    public static function render_popup( $popup_id ) {
        $popup_id = (int) $popup_id;
        if ( isset( self::$printed[ $popup_id ] ) || ! class_exists( 'Loopkit_Frontend' ) ) {
            return '';
        }
        self::$printed[ $popup_id ] = true;

        $elements = Loopkit_Frontend::ensure_sections( Loopkit_Frontend::decode_elements( get_post_meta( $popup_id, '_loopkit_data', true ) ) );
        if ( empty( $elements ) ) {
            return '';
        }

        $s   = self::get_settings( $popup_id );
        $css = (string) get_post_meta( $popup_id, '_loopkit_css', true );
        if ( '' === $css ) {
            $file = WP_CONTENT_DIR . '/uploads/loopkit/css/post-' . $popup_id . '.css';
            if ( file_exists( $file ) ) {
                $css = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            }
        }

        $width  = '' === $s['width'] ? '' : 'width:' . (float) $s['width'] . esc_attr( $s['width_unit'] ) . ';max-width:calc(100vw - 32px);';
        $height = '' === $s['height'] ? '' : 'height:' . (float) $s['height'] . esc_attr( $s['height_unit'] ) . ';';
        $box    = 'background:' . esc_attr( $s['background'] ) . ';border-radius:' . (float) $s['border_radius'] . 'px;padding:' . (float) $s['padding'] . 'px;';

        $config = array(
            'id'             => $popup_id,
            'triggers'       => array(
                'load'     => 'yes' === $s['trigger_load'] ? (float) $s['trigger_load_delay'] : false,
                'scroll'   => 'yes' === $s['trigger_scroll'] ? (float) $s['trigger_scroll_at'] : false,
                'exit'     => 'yes' === $s['trigger_exit'],
                'idle'     => 'yes' === $s['trigger_idle'] ? (float) $s['trigger_idle_after'] : false,
                'click'    => 'yes' === $s['trigger_click'] ? (string) $s['trigger_click_selector'] : false,
            ),
            'frequency'      => $s['frequency'],
            'frequencyDays'  => (float) $s['frequency_days'],
            'closeStopsIt'   => 'yes' === $s['close_stops_it'],
            'closeOnOverlay' => 'yes' === $s['close_on_overlay'],
            'closeOnEsc'     => 'yes' === $s['close_on_esc'],
            'preventScroll'  => 'yes' === $s['prevent_scroll'],
        );

        $classes = 'lk-popup lk-popup-pos-' . sanitize_html_class( $s['position'] ) . ' lk-popup-anim-' . sanitize_html_class( $s['animation'] );
        if ( 'yes' !== $s['overlay'] ) {
            $classes .= ' lk-popup-no-overlay';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr( $classes ); ?>" id="lk-popup-<?php echo $popup_id; ?>"
             data-lk-popup="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
             role="dialog" aria-modal="true" aria-hidden="true" hidden
             style="--lk-popup-overlay: <?php echo esc_attr( $s['overlay_color'] ); ?>">
            <div class="lk-popup-overlay" data-lk-popup-close-overlay></div>
            <div class="lk-popup-box loopkit-<?php echo $popup_id; ?>" style="<?php echo esc_attr( $width . $height . $box ); ?>" tabindex="-1">
                <?php if ( 'yes' === $s['close_button'] ) : ?>
                    <button type="button" class="lk-popup-close lk-popup-close-<?php echo esc_attr( $s['close_button_position'] ); ?>" aria-label="<?php esc_attr_e( 'Close', 'loopkit' ); ?>" data-lk-popup-close>&times;</button>
                <?php endif; ?>
                <div class="lk-popup-content">
                    <?php echo Loopkit_Frontend::render_elements( $elements, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        </div>
        <?php if ( '' !== $css ) : ?>
            <style id="loopkit-popup-css-<?php echo $popup_id; ?>"><?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
        <?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }
}
