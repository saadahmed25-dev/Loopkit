<?php
/**
 * Loopkit Pro-style widgets.
 *
 * Flip Box, Price Table, Countdown, Animated Headline, Call to Action and Share Buttons.
 * All are rendered by PHP on the front end AND in the editor canvas (they are in the
 * server-rendered list), so the preview can never drift from the live page.
 *
 * Control names are prefixed per widget (flip_*, price_*, cd_*, ah_*, cta_*, share_*) so they
 * can never collide with the Advanced tab's own settings (padding, margin, size, position…).
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared helpers.
 */
trait Loopkit_Pro_Widget_Helpers {

    /**
     * Icon markup from an icon control value.
     *
     * @param array $icon Icon value.
     * @param string $class Extra class.
     * @return string
     */
    protected function icon_html( $icon, $class = '' ) {
        if ( empty( $icon['value'] ) ) {
            return '';
        }
        $library = $icon['library'] ?? 'fa-solid';
        $value   = $icon['value'];
        $classes = trim( $class . ' ' . ( false !== strpos( $value, 'fa-' ) ? $library . ' ' . $value : 'fa-solid fa-' . $value ) );
        return '<i class="' . esc_attr( $classes ) . '" aria-hidden="true"></i>';
    }

    /**
     * Link attributes from a link control value.
     *
     * @param array $link Link value.
     * @return string
     */
    protected function link_attrs( $link ) {
        $url = esc_url( $link['url'] ?? '' );
        if ( '' === $url ) {
            return '';
        }
        $attrs = ' href="' . $url . '"';
        if ( ! empty( $link['is_external'] ) ) {
            $attrs .= ' target="_blank"';
        }
        if ( ! empty( $link['nofollow'] ) ) {
            $attrs .= ' rel="nofollow"';
        }
        return $attrs;
    }
}

/**
 * Flip Box — a card that flips on hover (or tap) to show the back side.
 */
class Loopkit_Widget_Flip_Box extends Loopkit_Widget_Base {
    use Loopkit_Pro_Widget_Helpers;

    public function get_name() { return 'flip-box'; }
    public function get_title() { return esc_html__( 'Flip Box', 'loopkit' ); }
    public function get_icon() { return 'dashicons-image-rotate'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'flip', 'box', 'card', 'hover', ' 3d' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_flip_front',
                    'title'    => __( 'Front', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'flip_front_icon', 'label' => __( 'Icon', 'loopkit' ), 'type' => 'icon' ),
                        array( 'name' => 'flip_front_title', 'label' => __( 'Title', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Front Side', 'loopkit' ) ),
                        array( 'name' => 'flip_front_text', 'label' => __( 'Description', 'loopkit' ), 'type' => 'textarea', 'default' => esc_html__( 'Add the short version here. The rest goes on the back.', 'loopkit' ) ),
                        array( 'name' => 'flip_front_bg', 'label' => __( 'Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'flip_front_color', 'label' => __( 'Text Colour', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                    ),
                ),
                array(
                    'id'       => 'section_flip_back',
                    'title'    => __( 'Back', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'flip_back_title', 'label' => __( 'Title', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Back Side', 'loopkit' ) ),
                        array( 'name' => 'flip_back_text', 'label' => __( 'Description', 'loopkit' ), 'type' => 'textarea', 'default' => esc_html__( 'The detail people see after the flip.', 'loopkit' ) ),
                        array( 'name' => 'flip_back_bg', 'label' => __( 'Background', 'loopkit' ), 'type' => 'color', 'default' => '#1f2937' ),
                        array( 'name' => 'flip_back_color', 'label' => __( 'Text Colour', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'flip_button_text', 'label' => __( 'Button Text', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Learn more', 'loopkit' ) ),
                        array( 'name' => 'flip_button_link', 'label' => __( 'Button Link', 'loopkit' ), 'type' => 'url' ),
                    ),
                ),
                array(
                    'id'       => 'section_flip_settings',
                    'title'    => __( 'Settings', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'flip_effect',
                            'label'   => __( 'Flip Direction', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'left',
                            'options' => array(
                                'left'  => esc_html__( 'Flip Left', 'loopkit' ),
                                'right' => esc_html__( 'Flip Right', 'loopkit' ),
                                'up'    => esc_html__( 'Flip Up', 'loopkit' ),
                                'down'  => esc_html__( 'Flip Down', 'loopkit' ),
                                'fade'  => esc_html__( 'Fade', 'loopkit' ),
                            ),
                        ),
                        array( 'name' => 'flip_height', 'label' => __( 'Height', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 280, 'unit' => 'px' ), 'responsive' => true, 'min' => 120, 'max' => 800 ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_flip_style',
                    'title'    => __( 'Box', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'flip_align', 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'default' => 'center', 'responsive' => true, 'options' => array(
                            'left'   => array( 'title' => __( 'Left', 'loopkit' ), 'icon' => 'dashicons-editor-alignleft' ),
                            'center' => array( 'title' => __( 'Center', 'loopkit' ), 'icon' => 'dashicons-editor-aligncenter' ),
                            'right'  => array( 'title' => __( 'Right', 'loopkit' ), 'icon' => 'dashicons-editor-alignright' ),
                        ) ),
                        array( 'name' => 'flip_padding', 'label' => __( 'Inner Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'flip_radius', 'label' => __( 'Border Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'flip_icon_size', 'label' => __( 'Icon Size', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 42, 'unit' => 'px' ), 'responsive' => true, 'min' => 10, 'max' => 200 ),
                        array( 'name' => 'flip_title_typography', 'label' => __( 'Title Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'flip_text_typography', 'label' => __( 'Description Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'flip_shadow', 'label' => __( 'Box Shadow', 'loopkit' ), 'type' => 'popover', 'popoverType' => 'box_shadow' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $effect = sanitize_html_class( $settings['flip_effect'] ?? 'left' );
        $front_icon = $this->icon_html( $settings['flip_front_icon'] ?? array(), 'lk-flip-icon' );

        $button = '';
        $btn_text = (string) ( $settings['flip_button_text'] ?? '' );
        if ( '' !== $btn_text ) {
            $link  = $settings['flip_button_link'] ?? array();
            $attrs = $this->link_attrs( $link );
            $button = $attrs
                ? '<a class="lk-flip-btn"' . $attrs . '>' . esc_html( $btn_text ) . '</a>'
                : '<span class="lk-flip-btn">' . esc_html( $btn_text ) . '</span>';
        }

        ob_start();
        ?>
        <div class="lk-flip-box lk-flip-<?php echo esc_attr( $effect ); ?>" tabindex="0">
            <div class="lk-flip-inner">
                <div class="lk-flip-face lk-flip-front">
                    <?php echo $front_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php if ( ! empty( $settings['flip_front_title'] ) ) : ?>
                        <h3 class="lk-flip-title"><?php echo esc_html( $settings['flip_front_title'] ); ?></h3>
                    <?php endif; ?>
                    <?php if ( ! empty( $settings['flip_front_text'] ) ) : ?>
                        <p class="lk-flip-text"><?php echo esc_html( $settings['flip_front_text'] ); ?></p>
                    <?php endif; ?>
                </div>
                <div class="lk-flip-face lk-flip-back">
                    <?php if ( ! empty( $settings['flip_back_title'] ) ) : ?>
                        <h3 class="lk-flip-title"><?php echo esc_html( $settings['flip_back_title'] ); ?></h3>
                    <?php endif; ?>
                    <?php if ( ! empty( $settings['flip_back_text'] ) ) : ?>
                        <p class="lk-flip-text"><?php echo esc_html( $settings['flip_back_text'] ); ?></p>
                    <?php endif; ?>
                    <?php echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Price Table.
 */
class Loopkit_Widget_Price_Table extends Loopkit_Widget_Base {
    use Loopkit_Pro_Widget_Helpers;

    public function get_name() { return 'price-table'; }
    public function get_title() { return esc_html__( 'Price Table', 'loopkit' ); }
    public function get_icon() { return 'dashicons-money-alt'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'price', 'pricing', 'table', 'plan', 'package' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_price_header',
                    'title'    => __( 'Header', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'price_heading', 'label' => __( 'Plan Name', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Growth', 'loopkit' ) ),
                        array( 'name' => 'price_subheading', 'label' => __( 'Sub Title', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'For growing teams', 'loopkit' ) ),
                        array( 'name' => 'price_ribbon', 'label' => __( 'Ribbon Text', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                    ),
                ),
                array(
                    'id'       => 'section_price_price',
                    'title'    => __( 'Price', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'price_currency', 'label' => __( 'Currency Symbol', 'loopkit' ), 'type' => 'text', 'default' => '$' ),
                        array( 'name' => 'price_value', 'label' => __( 'Price', 'loopkit' ), 'type' => 'text', 'default' => '49' ),
                        array( 'name' => 'price_original', 'label' => __( 'Original Price (crossed out)', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                        array( 'name' => 'price_period', 'label' => __( 'Period', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'per month', 'loopkit' ) ),
                    ),
                ),
                array(
                    'id'       => 'section_price_features',
                    'title'    => __( 'Features', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'price_features',
                            'label'       => __( 'Features', 'loopkit' ),
                            'type'        => 'repeater',
                            'title_field' => 'text',
                            'default'     => array(
                                array( 'text' => esc_html__( 'Everything in Starter', 'loopkit' ), 'included' => 'yes' ),
                                array( 'text' => esc_html__( '10 projects', 'loopkit' ), 'included' => 'yes' ),
                                array( 'text' => esc_html__( 'Priority support', 'loopkit' ), 'included' => 'no' ),
                            ),
                            'fields'      => array(
                                array( 'name' => 'text', 'label' => __( 'Text', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Feature', 'loopkit' ) ),
                                array( 'name' => 'included', 'label' => __( 'Included', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                            ),
                        ),
                    ),
                ),
                array(
                    'id'       => 'section_price_footer',
                    'title'    => __( 'Button', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'price_button_text', 'label' => __( 'Button Text', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Choose plan', 'loopkit' ) ),
                        array( 'name' => 'price_button_link', 'label' => __( 'Button Link', 'loopkit' ), 'type' => 'url' ),
                        array( 'name' => 'price_footer_note', 'label' => __( 'Note Under Button', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_price_style',
                    'title'    => __( 'Table', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'price_bg', 'label' => __( 'Background', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'price_border_color', 'label' => __( 'Border Colour', 'loopkit' ), 'type' => 'color', 'default' => '#e5e7eb' ),
                        array( 'name' => 'price_radius', 'label' => __( 'Border Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'price_inner_padding', 'label' => __( 'Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'price_heading_color', 'label' => __( 'Plan Name Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'price_heading_typography', 'label' => __( 'Plan Name Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'price_value_color', 'label' => __( 'Price Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'price_value_typography', 'label' => __( 'Price Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'price_features_color', 'label' => __( 'Features Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'price_features_typography', 'label' => __( 'Features Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'price_button_bg', 'label' => __( 'Button Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'price_button_color', 'label' => __( 'Button Text Colour', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'price_button_hover_bg', 'label' => __( 'Button Hover Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'price_button_hover_color', 'label' => __( 'Button Hover Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'price_ribbon_bg', 'label' => __( 'Ribbon Background', 'loopkit' ), 'type' => 'color', 'default' => '#f59e0b' ),
                        array( 'name' => 'price_ribbon_color', 'label' => __( 'Ribbon Text Colour', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'price_shadow', 'label' => __( 'Box Shadow', 'loopkit' ), 'type' => 'popover', 'popoverType' => 'box_shadow' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $features = $settings['price_features'] ?? array();
        if ( ! is_array( $features ) ) {
            $features = array();
        }

        $btn_text = (string) ( $settings['price_button_text'] ?? '' );
        $attrs    = $this->link_attrs( $settings['price_button_link'] ?? array() );

        ob_start();
        ?>
        <div class="lk-price-table">
            <?php if ( ! empty( $settings['price_ribbon'] ) ) : ?>
                <span class="lk-price-ribbon"><?php echo esc_html( $settings['price_ribbon'] ); ?></span>
            <?php endif; ?>

            <div class="lk-price-header">
                <?php if ( ! empty( $settings['price_heading'] ) ) : ?>
                    <h3 class="lk-price-heading"><?php echo esc_html( $settings['price_heading'] ); ?></h3>
                <?php endif; ?>
                <?php if ( ! empty( $settings['price_subheading'] ) ) : ?>
                    <p class="lk-price-subheading"><?php echo esc_html( $settings['price_subheading'] ); ?></p>
                <?php endif; ?>
            </div>

            <div class="lk-price-price">
                <?php if ( ! empty( $settings['price_original'] ) ) : ?>
                    <span class="lk-price-original"><?php echo esc_html( ( $settings['price_currency'] ?? '' ) . $settings['price_original'] ); ?></span>
                <?php endif; ?>
                <span class="lk-price-amount">
                    <span class="lk-price-currency"><?php echo esc_html( $settings['price_currency'] ?? '' ); ?></span><span class="lk-price-value"><?php echo esc_html( $settings['price_value'] ?? '' ); ?></span>
                </span>
                <?php if ( ! empty( $settings['price_period'] ) ) : ?>
                    <span class="lk-price-period"><?php echo esc_html( $settings['price_period'] ); ?></span>
                <?php endif; ?>
            </div>

            <?php if ( $features ) : ?>
                <ul class="lk-price-features">
                    <?php foreach ( $features as $f ) : $in = ( 'no' !== ( $f['included'] ?? 'yes' ) ); ?>
                        <li class="lk-price-feature <?php echo $in ? 'lk-included' : 'lk-excluded'; ?>">
                            <span class="lk-price-tick" aria-hidden="true"><?php echo $in ? '&#10003;' : '&times;'; ?></span>
                            <span class="lk-price-feature-text"><?php echo esc_html( $f['text'] ?? '' ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ( '' !== $btn_text ) : ?>
                <div class="lk-price-footer">
                    <?php if ( $attrs ) : ?>
                        <a class="lk-price-btn"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $btn_text ); ?></a>
                    <?php else : ?>
                        <span class="lk-price-btn"><?php echo esc_html( $btn_text ); ?></span>
                    <?php endif; ?>
                    <?php if ( ! empty( $settings['price_footer_note'] ) ) : ?>
                        <p class="lk-price-note"><?php echo esc_html( $settings['price_footer_note'] ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Countdown.
 */
class Loopkit_Widget_Countdown extends Loopkit_Widget_Base {
    use Loopkit_Pro_Widget_Helpers;

    public function get_name() { return 'countdown'; }
    public function get_title() { return esc_html__( 'Countdown', 'loopkit' ); }
    public function get_icon() { return 'dashicons-clock'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'countdown', 'timer', 'clock', 'evergreen', 'offer' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_cd_content',
                    'title'    => __( 'Countdown', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'cd_due_date',
                            'label'       => __( 'Due Date & Time', 'loopkit' ),
                            'type'        => 'text',
                            'default'     => gmdate( 'Y-m-d H:i', time() + WEEK_IN_SECONDS ),
                            'description' => __( 'Format: YYYY-MM-DD HH:MM (site time).', 'loopkit' ),
                        ),
                        array( 'name' => 'cd_show_days', 'label' => __( 'Show Days', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'cd_show_hours', 'label' => __( 'Show Hours', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'cd_show_minutes', 'label' => __( 'Show Minutes', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'cd_show_seconds', 'label' => __( 'Show Seconds', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'cd_label_days', 'label' => __( 'Days Label', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Days', 'loopkit' ) ),
                        array( 'name' => 'cd_label_hours', 'label' => __( 'Hours Label', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Hours', 'loopkit' ) ),
                        array( 'name' => 'cd_label_minutes', 'label' => __( 'Minutes Label', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Minutes', 'loopkit' ) ),
                        array( 'name' => 'cd_label_seconds', 'label' => __( 'Seconds Label', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Seconds', 'loopkit' ) ),
                        array( 'name' => 'cd_expire_message', 'label' => __( 'Message When Finished', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'This offer has ended.', 'loopkit' ) ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_cd_style',
                    'title'    => __( 'Boxes', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'cd_align', 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'default' => 'center', 'responsive' => true, 'options' => array(
                            'flex-start' => array( 'title' => __( 'Left', 'loopkit' ), 'icon' => 'dashicons-editor-alignleft' ),
                            'center'     => array( 'title' => __( 'Center', 'loopkit' ), 'icon' => 'dashicons-editor-aligncenter' ),
                            'flex-end'   => array( 'title' => __( 'Right', 'loopkit' ), 'icon' => 'dashicons-editor-alignright' ),
                        ) ),
                        array( 'name' => 'cd_gap', 'label' => __( 'Space Between', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 14, 'unit' => 'px' ), 'responsive' => true, 'min' => 0, 'max' => 80 ),
                        array( 'name' => 'cd_box_bg', 'label' => __( 'Box Background', 'loopkit' ), 'type' => 'color', 'default' => '#111827' ),
                        array( 'name' => 'cd_box_padding', 'label' => __( 'Box Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'cd_box_radius', 'label' => __( 'Box Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'cd_digit_color', 'label' => __( 'Digit Colour', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'cd_digit_typography', 'label' => __( 'Digit Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'cd_label_color', 'label' => __( 'Label Colour', 'loopkit' ), 'type' => 'color', 'default' => '#9ca3af' ),
                        array( 'name' => 'cd_label_typography', 'label' => __( 'Label Typography', 'loopkit' ), 'type' => 'typography' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $due = trim( (string) ( $settings['cd_due_date'] ?? '' ) );
        $ts  = $due ? strtotime( $due ) : 0;
        if ( ! $ts ) {
            $ts = time() + WEEK_IN_SECONDS;
        }
        // The stored value is site time; JS works in UTC.
        $utc = $ts - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

        $parts = array(
            'days'    => array( 'show' => $this->switcher_on( $settings, 'cd_show_days', true ), 'label' => $settings['cd_label_days'] ?? __( 'Days', 'loopkit' ) ),
            'hours'   => array( 'show' => $this->switcher_on( $settings, 'cd_show_hours', true ), 'label' => $settings['cd_label_hours'] ?? __( 'Hours', 'loopkit' ) ),
            'minutes' => array( 'show' => $this->switcher_on( $settings, 'cd_show_minutes', true ), 'label' => $settings['cd_label_minutes'] ?? __( 'Minutes', 'loopkit' ) ),
            'seconds' => array( 'show' => $this->switcher_on( $settings, 'cd_show_seconds', true ), 'label' => $settings['cd_label_seconds'] ?? __( 'Seconds', 'loopkit' ) ),
        );

        // Server-side first paint so the numbers are right before JS runs.
        $left  = max( 0, $utc - time() + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
        $now   = array(
            'days'    => (int) floor( $left / DAY_IN_SECONDS ),
            'hours'   => (int) floor( ( $left % DAY_IN_SECONDS ) / HOUR_IN_SECONDS ),
            'minutes' => (int) floor( ( $left % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS ),
            'seconds' => (int) ( $left % MINUTE_IN_SECONDS ),
        );

        ob_start();
        ?>
        <div class="lk-countdown" data-lk-countdown="<?php echo esc_attr( $utc * 1000 ); ?>"
             data-lk-expire-message="<?php echo esc_attr( $settings['cd_expire_message'] ?? '' ); ?>">
            <div class="lk-countdown-items">
                <?php foreach ( $parts as $key => $part ) : ?>
                    <?php if ( ! $part['show'] ) { continue; } ?>
                    <div class="lk-countdown-item lk-countdown-<?php echo esc_attr( $key ); ?>">
                        <span class="lk-countdown-digit" data-lk-unit="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( str_pad( (string) $now[ $key ], 2, '0', STR_PAD_LEFT ) ); ?></span>
                        <span class="lk-countdown-label"><?php echo esc_html( $part['label'] ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="lk-countdown-expired" hidden><?php echo esc_html( $settings['cd_expire_message'] ?? '' ); ?></div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Animated Headline.
 */
class Loopkit_Widget_Animated_Headline extends Loopkit_Widget_Base {
    use Loopkit_Pro_Widget_Helpers;

    public function get_name() { return 'animated-headline'; }
    public function get_title() { return esc_html__( 'Animated Headline', 'loopkit' ); }
    public function get_icon() { return 'dashicons-editor-textcolor'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'headline', 'animated', 'typing', 'rotate', 'highlight' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_ah_content',
                    'title'    => __( 'Headline', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'ah_effect',
                            'label'   => __( 'Effect', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'rotate',
                            'options' => array(
                                'rotate'    => esc_html__( 'Rotating words', 'loopkit' ),
                                'typing'    => esc_html__( 'Typing', 'loopkit' ),
                                'highlight' => esc_html__( 'Highlighted text', 'loopkit' ),
                            ),
                        ),
                        array( 'name' => 'ah_before_text', 'label' => __( 'Text Before', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'We build', 'loopkit' ) ),
                        array( 'name' => 'ah_words', 'label' => __( 'Animated Words (one per line)', 'loopkit' ), 'type' => 'textarea', 'default' => "websites\nfunnels\nbrands" ),
                        array( 'name' => 'ah_after_text', 'label' => __( 'Text After', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'that convert.', 'loopkit' ) ),
                        array( 'name' => 'ah_speed', 'label' => __( 'Speed (ms per word)', 'loopkit' ), 'type' => 'number', 'default' => 2200 ),
                        array(
                            'name'    => 'ah_tag',
                            'label'   => __( 'HTML Tag', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'h2',
                            'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div', 'p' => 'p' ),
                        ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_ah_style',
                    'title'    => __( 'Headline', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'ah_align', 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'default' => 'left', 'responsive' => true, 'options' => array(
                            'left'   => array( 'title' => __( 'Left', 'loopkit' ), 'icon' => 'dashicons-editor-alignleft' ),
                            'center' => array( 'title' => __( 'Center', 'loopkit' ), 'icon' => 'dashicons-editor-aligncenter' ),
                            'right'  => array( 'title' => __( 'Right', 'loopkit' ), 'icon' => 'dashicons-editor-alignright' ),
                        ) ),
                        array( 'name' => 'ah_color', 'label' => __( 'Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'ah_typography', 'label' => __( 'Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'ah_word_color', 'label' => __( 'Animated Word Colour', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'ah_highlight_color', 'label' => __( 'Highlight Colour', 'loopkit' ), 'type' => 'color', 'default' => '#fde68a' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $tag    = (string) ( $settings['ah_tag'] ?? 'h2' );
        $tag    = in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'div', 'p' ), true ) ? $tag : 'h2';
        $effect = (string) ( $settings['ah_effect'] ?? 'rotate' );
        $effect = in_array( $effect, array( 'rotate', 'typing', 'highlight' ), true ) ? $effect : 'rotate';
        $words  = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $settings['ah_words'] ?? '' ) ) ) ) );
        if ( ! $words ) {
            $words = array( '' );
        }
        $speed = max( 400, (int) ( $settings['ah_speed'] ?? 2200 ) );

        ob_start();
        ?>
        <<?php echo esc_attr( $tag ); ?> class="lk-animated-headline lk-ah-<?php echo esc_attr( $effect ); ?>"
            data-lk-headline="<?php echo esc_attr( wp_json_encode( array( 'effect' => $effect, 'words' => $words, 'speed' => $speed ) ) ); ?>">
            <?php if ( ! empty( $settings['ah_before_text'] ) ) : ?><span class="lk-ah-before"><?php echo esc_html( $settings['ah_before_text'] ); ?></span> <?php endif; ?>
            <span class="lk-ah-words"><span class="lk-ah-word"><?php echo esc_html( $words[0] ); ?></span></span>
            <?php if ( ! empty( $settings['ah_after_text'] ) ) : ?> <span class="lk-ah-after"><?php echo esc_html( $settings['ah_after_text'] ); ?></span><?php endif; ?>
        </<?php echo esc_attr( $tag ); ?>>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Call to Action.
 */
class Loopkit_Widget_Call_To_Action extends Loopkit_Widget_Base {
    use Loopkit_Pro_Widget_Helpers;

    public function get_name() { return 'call-to-action'; }
    public function get_title() { return esc_html__( 'Call to Action', 'loopkit' ); }
    public function get_icon() { return 'dashicons-megaphone'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'cta', 'call to action', 'banner', 'promo' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_cta_content',
                    'title'    => __( 'Content', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'cta_image', 'label' => __( 'Background Image', 'loopkit' ), 'type' => 'media' ),
                        array( 'name' => 'cta_title', 'label' => __( 'Title', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Ready to start?', 'loopkit' ) ),
                        array( 'name' => 'cta_description', 'label' => __( 'Description', 'loopkit' ), 'type' => 'textarea', 'default' => esc_html__( 'Tell people what happens next and why it is worth their time.', 'loopkit' ) ),
                        array( 'name' => 'cta_button_text', 'label' => __( 'Button Text', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Get started', 'loopkit' ) ),
                        array( 'name' => 'cta_button_link', 'label' => __( 'Button Link', 'loopkit' ), 'type' => 'url' ),
                        array( 'name' => 'cta_min_height', 'label' => __( 'Minimum Height', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 320, 'unit' => 'px' ), 'responsive' => true, 'min' => 100, 'max' => 900 ),
                        array( 'name' => 'cta_content_align', 'label' => __( 'Content Alignment', 'loopkit' ), 'type' => 'choose', 'default' => 'center', 'responsive' => true, 'options' => array(
                            'flex-start' => array( 'title' => __( 'Left', 'loopkit' ), 'icon' => 'dashicons-editor-alignleft' ),
                            'center'     => array( 'title' => __( 'Center', 'loopkit' ), 'icon' => 'dashicons-editor-aligncenter' ),
                            'flex-end'   => array( 'title' => __( 'Right', 'loopkit' ), 'icon' => 'dashicons-editor-alignright' ),
                        ) ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_cta_style',
                    'title'    => __( 'Box', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'cta_overlay_color', 'label' => __( 'Overlay Colour', 'loopkit' ), 'type' => 'color', 'default' => 'rgba(0,0,0,0.55)' ),
                        array( 'name' => 'cta_padding', 'label' => __( 'Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'cta_radius', 'label' => __( 'Border Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'cta_title_color', 'label' => __( 'Title Colour', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'cta_title_typography', 'label' => __( 'Title Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'cta_desc_color', 'label' => __( 'Description Colour', 'loopkit' ), 'type' => 'color', 'default' => '#e5e7eb' ),
                        array( 'name' => 'cta_desc_typography', 'label' => __( 'Description Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'cta_button_bg', 'label' => __( 'Button Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'cta_button_color', 'label' => __( 'Button Text Colour', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'cta_button_hover_bg', 'label' => __( 'Button Hover Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'cta_button_hover_color', 'label' => __( 'Button Hover Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'cta_button_radius', 'label' => __( 'Button Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $image = $settings['cta_image']['url'] ?? '';
        $style = $image ? ' style="background-image:url(' . esc_url( $image ) . ')"' : '';
        $attrs = $this->link_attrs( $settings['cta_button_link'] ?? array() );
        $btn   = (string) ( $settings['cta_button_text'] ?? '' );

        ob_start();
        ?>
        <div class="lk-cta"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="lk-cta-overlay"></div>
            <div class="lk-cta-content">
                <?php if ( ! empty( $settings['cta_title'] ) ) : ?>
                    <h2 class="lk-cta-title"><?php echo esc_html( $settings['cta_title'] ); ?></h2>
                <?php endif; ?>
                <?php if ( ! empty( $settings['cta_description'] ) ) : ?>
                    <p class="lk-cta-description"><?php echo esc_html( $settings['cta_description'] ); ?></p>
                <?php endif; ?>
                <?php if ( '' !== $btn ) : ?>
                    <?php if ( $attrs ) : ?>
                        <a class="lk-cta-btn"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $btn ); ?></a>
                    <?php else : ?>
                        <span class="lk-cta-btn"><?php echo esc_html( $btn ); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Share Buttons.
 */
class Loopkit_Widget_Share_Buttons extends Loopkit_Widget_Base {
    use Loopkit_Pro_Widget_Helpers;

    public function get_name() { return 'share-buttons'; }
    public function get_title() { return esc_html__( 'Share Buttons', 'loopkit' ); }
    public function get_icon() { return 'dashicons-share'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'share', 'social', 'facebook', 'whatsapp', 'linkedin' ); }

    /**
     * Supported networks: share URL pattern, label, brand colour, icon class.
     *
     * @return array
     */
    public static function networks() {
        return array(
            'facebook'  => array( 'label' => 'Facebook', 'color' => '#1877f2', 'icon' => 'fa-brands fa-facebook-f', 'url' => 'https://www.facebook.com/sharer/sharer.php?u={url}' ),
            'x'         => array( 'label' => 'X', 'color' => '#111111', 'icon' => 'fa-brands fa-x-twitter', 'url' => 'https://twitter.com/intent/tweet?url={url}&text={title}' ),
            'linkedin'  => array( 'label' => 'LinkedIn', 'color' => '#0a66c2', 'icon' => 'fa-brands fa-linkedin-in', 'url' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}' ),
            'whatsapp'  => array( 'label' => 'WhatsApp', 'color' => '#25d366', 'icon' => 'fa-brands fa-whatsapp', 'url' => 'https://api.whatsapp.com/send?text={title}%20{url}' ),
            'telegram'  => array( 'label' => 'Telegram', 'color' => '#2aabee', 'icon' => 'fa-brands fa-telegram', 'url' => 'https://t.me/share/url?url={url}&text={title}' ),
            'pinterest' => array( 'label' => 'Pinterest', 'color' => '#e60023', 'icon' => 'fa-brands fa-pinterest-p', 'url' => 'https://pinterest.com/pin/create/button/?url={url}&description={title}' ),
            'reddit'    => array( 'label' => 'Reddit', 'color' => '#ff4500', 'icon' => 'fa-brands fa-reddit-alien', 'url' => 'https://www.reddit.com/submit?url={url}&title={title}' ),
            'email'     => array( 'label' => 'Email', 'color' => '#6b7280', 'icon' => 'fa-solid fa-envelope', 'url' => 'mailto:?subject={title}&body={url}' ),
        );
    }

    protected function register_controls() {
        $options = array();
        foreach ( self::networks() as $key => $net ) {
            $options[ $key ] = $net['label'];
        }

        return array(
            'content' => array(
                array(
                    'id'       => 'section_share_content',
                    'title'    => __( 'Share Buttons', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'share_networks',
                            'label'       => __( 'Networks', 'loopkit' ),
                            'type'        => 'repeater',
                            'title_field' => 'network',
                            'default'     => array(
                                array( 'network' => 'facebook' ),
                                array( 'network' => 'x' ),
                                array( 'network' => 'linkedin' ),
                                array( 'network' => 'whatsapp' ),
                            ),
                            'fields'      => array(
                                array( 'name' => 'network', 'label' => __( 'Network', 'loopkit' ), 'type' => 'select', 'default' => 'facebook', 'options' => $options ),
                                array( 'name' => 'custom_label', 'label' => __( 'Custom Label', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                            ),
                        ),
                        array(
                            'name'    => 'share_view',
                            'label'   => __( 'View', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'icon-text',
                            'options' => array(
                                'icon'      => esc_html__( 'Icon only', 'loopkit' ),
                                'text'      => esc_html__( 'Text only', 'loopkit' ),
                                'icon-text' => esc_html__( 'Icon and text', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'    => 'share_shape',
                            'label'   => __( 'Shape', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'rounded',
                            'options' => array(
                                'square'  => esc_html__( 'Square', 'loopkit' ),
                                'rounded' => esc_html__( 'Rounded', 'loopkit' ),
                                'circle'  => esc_html__( 'Circle', 'loopkit' ),
                            ),
                        ),
                        array( 'name' => 'share_brand_colors', 'label' => __( 'Use Brand Colours', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_share_style',
                    'title'    => __( 'Buttons', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'share_align', 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'default' => 'flex-start', 'responsive' => true, 'options' => array(
                            'flex-start' => array( 'title' => __( 'Left', 'loopkit' ), 'icon' => 'dashicons-editor-alignleft' ),
                            'center'     => array( 'title' => __( 'Center', 'loopkit' ), 'icon' => 'dashicons-editor-aligncenter' ),
                            'flex-end'   => array( 'title' => __( 'Right', 'loopkit' ), 'icon' => 'dashicons-editor-alignright' ),
                        ) ),
                        array( 'name' => 'share_gap', 'label' => __( 'Space Between', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 10, 'unit' => 'px' ), 'responsive' => true, 'min' => 0, 'max' => 60 ),
                        array( 'name' => 'share_bg', 'label' => __( 'Background (custom colours)', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'share_color', 'label' => __( 'Icon / Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'share_hover_bg', 'label' => __( 'Hover Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'share_hover_color', 'label' => __( 'Hover Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'share_padding', 'label' => __( 'Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'share_icon_size', 'label' => __( 'Icon Size', 'loopkit' ), 'type' => 'slider', 'default' => array( 'size' => 16, 'unit' => 'px' ), 'responsive' => true, 'min' => 8, 'max' => 60 ),
                        array( 'name' => 'share_typography', 'label' => __( 'Typography', 'loopkit' ), 'type' => 'typography' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $items = $settings['share_networks'] ?? array();
        if ( ! is_array( $items ) || ! $items ) {
            $items = array( array( 'network' => 'facebook' ), array( 'network' => 'x' ) );
        }

        $networks = self::networks();
        $view     = (string) ( $settings['share_view'] ?? 'icon-text' );
        $view     = in_array( $view, array( 'icon', 'text', 'icon-text' ), true ) ? $view : 'icon-text';
        $shape    = (string) ( $settings['share_shape'] ?? 'rounded' );
        $shape    = in_array( $shape, array( 'square', 'rounded', 'circle' ), true ) ? $shape : 'rounded';
        $brand    = $this->switcher_on( $settings, 'share_brand_colors', true );

        $post_id = class_exists( 'Loopkit_Post_Data' ) ? Loopkit_Post_Data::get_render_post_id( $editor_mode ) : get_the_ID();
        $url     = $post_id ? get_permalink( $post_id ) : home_url( '/' );
        $title   = $post_id ? get_the_title( $post_id ) : get_bloginfo( 'name' );

        $html = '<div class="lk-share-buttons lk-share-' . esc_attr( $shape ) . ' lk-share-view-' . esc_attr( $view ) . ( $brand ? ' lk-share-brand' : '' ) . '">';
        foreach ( $items as $item ) {
            $key = $item['network'] ?? 'facebook';
            if ( ! isset( $networks[ $key ] ) ) {
                continue;
            }
            $net   = $networks[ $key ];
            $label = ( '' !== ( $item['custom_label'] ?? '' ) ) ? $item['custom_label'] : $net['label'];
            $share = str_replace(
                array( '{url}', '{title}' ),
                array( rawurlencode( $url ), rawurlencode( $title ) ),
                $net['url']
            );

            $html .= '<a class="lk-share-btn lk-share-' . esc_attr( $key ) . '" href="' . esc_url( $share ) . '" target="_blank" rel="noopener nofollow"'
                . ' style="--lk-share-brand: ' . esc_attr( $net['color'] ) . '"'
                . ' aria-label="' . esc_attr( sprintf( /* translators: %s: network name */ __( 'Share on %s', 'loopkit' ), $net['label'] ) ) . '">';
            if ( 'text' !== $view ) {
                $html .= '<i class="' . esc_attr( $net['icon'] ) . ' lk-share-icon" aria-hidden="true"></i>';
            }
            if ( 'icon' !== $view ) {
                $html .= '<span class="lk-share-label">' . esc_html( $label ) . '</span>';
            }
            $html .= '</a>';
        }
        $html .= '</div>';

        return $html;
    }
}
