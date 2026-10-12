<?php
/**
 * Loopkit — the second set of pro widgets.
 *
 * Team Member, Testimonial Carousel, Blockquote, Business Hours, Image Hotspots,
 * Filterable Portfolio and Login Form.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Team Member — photo, name, role, short bio and social links.
 */
class Loopkit_Widget_Team_Member extends Loopkit_Widget_Base {
    use Loopkit_Pro_Widget_Helpers;

    public function get_name() { return 'team-member'; }
    public function get_title() { return __( 'Team Member', 'loopkit' ); }
    public function get_icon() { return 'dashicons-businessperson'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'team', 'member', 'person', 'staff', 'profile' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_team',
                    'title'    => __( 'Member', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'team_image', 'label' => __( 'Photo', 'loopkit' ), 'type' => 'media' ),
                        array( 'name' => 'team_name', 'label' => __( 'Name', 'loopkit' ), 'type' => 'text', 'default' => __( 'Zara Ahmed', 'loopkit' ) ),
                        array( 'name' => 'team_role', 'label' => __( 'Role', 'loopkit' ), 'type' => 'text', 'default' => __( 'Lead Designer', 'loopkit' ) ),
                        array( 'name' => 'team_bio', 'label' => __( 'Short bio', 'loopkit' ), 'type' => 'textarea', 'default' => '' ),
                        array( 'name' => 'team_link', 'label' => __( 'Link the photo and name to', 'loopkit' ), 'type' => 'url' ),
                        array(
                            'name'        => 'team_socials',
                            'label'       => __( 'Social links', 'loopkit' ),
                            'type'        => 'repeater',
                            'title_field' => 'label',
                            'default'     => array(),
                            'fields'      => array(
                                array( 'name' => 'label', 'label' => __( 'Label', 'loopkit' ), 'type' => 'text', 'default' => 'LinkedIn' ),
                                array( 'name' => 'icon', 'label' => __( 'Icon', 'loopkit' ), 'type' => 'icon' ),
                                array( 'name' => 'url', 'label' => __( 'Link', 'loopkit' ), 'type' => 'url' ),
                            ),
                        ),
                        array(
                            'name'    => 'team_layout',
                            'label'   => __( 'Layout', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'stacked',
                            'options' => array(
                                'stacked' => __( 'Photo above', 'loopkit' ),
                                'side'    => __( 'Photo beside', 'loopkit' ),
                                'overlay' => __( 'Details over the photo on hover', 'loopkit' ),
                            ),
                        ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_team_style',
                    'title'    => __( 'Style', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'team_align', 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'default' => 'center', 'options' => array(
                            'left' => __( 'Left', 'loopkit' ), 'center' => __( 'Center', 'loopkit' ), 'right' => __( 'Right', 'loopkit' ),
                        ) ),
                        array( 'name' => 'team_card_bg', 'label' => __( 'Card Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'team_card_radius', 'label' => __( 'Card Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'team_card_padding', 'label' => __( 'Card Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'team_img_radius', 'label' => __( 'Photo Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'team_img_height', 'label' => __( 'Photo Height', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 80, 'max' => 700, 'default' => array( 'size' => 280, 'unit' => 'px' ) ),
                        array( 'name' => 'team_name_color', 'label' => __( 'Name Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'team_name_typography', 'label' => __( 'Name Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'team_role_color', 'label' => __( 'Role Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'team_role_typography', 'label' => __( 'Role Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'team_bio_color', 'label' => __( 'Bio Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'team_social_color', 'label' => __( 'Social Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'team_social_hover', 'label' => __( 'Social Hover Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'team_social_size', 'label' => __( 'Social Size', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 10, 'max' => 48, 'default' => array( 'size' => 18, 'unit' => 'px' ) ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $image  = $settings['team_image']['url'] ?? '';
        $name   = (string) ( $settings['team_name'] ?? '' );
        $role   = (string) ( $settings['team_role'] ?? '' );
        $bio    = (string) ( $settings['team_bio'] ?? '' );
        $layout = (string) ( $settings['team_layout'] ?? 'stacked' );
        $layout = in_array( $layout, array( 'stacked', 'side', 'overlay' ), true ) ? $layout : 'stacked';
        $link   = $settings['team_link']['url'] ?? '';

        if ( '' === $name && '' === $image && ! $editor_mode ) {
            return '';
        }

        ob_start();
        ?>
        <div class="lk-team lk-team-<?php echo esc_attr( $layout ); ?>">
            <?php if ( $image ) : ?>
                <div class="lk-team-photo">
                    <?php if ( $link ) : ?><a href="<?php echo esc_url( $link ); ?>"><?php endif; ?>
                    <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" decoding="async">
                    <?php if ( $link ) : ?></a><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="lk-team-body">
                <?php if ( '' !== $name ) : ?>
                    <h3 class="lk-team-name"><?php if ( $link ) : ?><a href="<?php echo esc_url( $link ); ?>"><?php endif; ?><?php echo esc_html( $name ); ?><?php if ( $link ) : ?></a><?php endif; ?></h3>
                <?php endif; ?>
                <?php if ( '' !== $role ) : ?>
                    <p class="lk-team-role"><?php echo esc_html( $role ); ?></p>
                <?php endif; ?>
                <?php if ( '' !== $bio ) : ?>
                    <p class="lk-team-bio"><?php echo esc_html( $bio ); ?></p>
                <?php endif; ?>

                <?php $socials = (array) ( $settings['team_socials'] ?? array() ); ?>
                <?php if ( $socials ) : ?>
                    <div class="lk-team-socials">
                        <?php foreach ( $socials as $social ) : ?>
                            <?php $url = $social['url']['url'] ?? ''; ?>
                            <a class="lk-team-social" href="<?php echo esc_url( $url ?: '#' ); ?>" aria-label="<?php echo esc_attr( $social['label'] ?? '' ); ?>"<?php echo $url ? ' rel="noopener"' : ''; ?>>
                                <?php
                                $icon = $this->icon_html( $social['icon'] ?? array() );
                                echo $icon ? $icon : esc_html( $social['label'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Testimonial Carousel — quotes that slide, with dots and arrows.
 */
class Loopkit_Widget_Testimonial_Carousel extends Loopkit_Widget_Base {
    use Loopkit_Pro_Widget_Helpers;

    public function get_name() { return 'testimonial-carousel'; }
    public function get_title() { return __( 'Testimonial Carousel', 'loopkit' ); }
    public function get_icon() { return 'dashicons-format-quote'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'testimonial', 'review', 'quote', 'carousel', 'slider' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_tc',
                    'title'    => __( 'Testimonials', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'tc_items',
                            'label'       => __( 'Testimonials', 'loopkit' ),
                            'type'        => 'repeater',
                            'title_field' => 'name',
                            'default'     => array(
                                array( 'quote' => __( 'They rebuilt our site in a fortnight and sales went up the same month.', 'loopkit' ), 'name' => __( 'Ayesha K.', 'loopkit' ), 'role' => __( 'Founder, Studio North', 'loopkit' ), 'rating' => '5' ),
                                array( 'quote' => __( 'Clear communication the whole way through. No chasing, no surprises.', 'loopkit' ), 'name' => __( 'Daniel R.', 'loopkit' ), 'role' => __( 'Marketing Lead', 'loopkit' ), 'rating' => '5' ),
                            ),
                            'fields'      => array(
                                array( 'name' => 'quote', 'label' => __( 'Quote', 'loopkit' ), 'type' => 'textarea', 'default' => '' ),
                                array( 'name' => 'name', 'label' => __( 'Name', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                                array( 'name' => 'role', 'label' => __( 'Role or company', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                                array( 'name' => 'image', 'label' => __( 'Photo', 'loopkit' ), 'type' => 'media' ),
                                array( 'name' => 'rating', 'label' => __( 'Stars (0-5)', 'loopkit' ), 'type' => 'number', 'default' => '5' ),
                            ),
                        ),
                        array( 'name' => 'tc_autoplay', 'label' => __( 'Autoplay', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'tc_speed', 'label' => __( 'Seconds per slide', 'loopkit' ), 'type' => 'number', 'default' => 5 ),
                        array( 'name' => 'tc_arrows', 'label' => __( 'Arrows', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'tc_dots', 'label' => __( 'Dots', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_tc_style',
                    'title'    => __( 'Style', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'tc_align', 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'default' => 'center', 'options' => array(
                            'left' => __( 'Left', 'loopkit' ), 'center' => __( 'Center', 'loopkit' ), 'right' => __( 'Right', 'loopkit' ),
                        ) ),
                        array( 'name' => 'tc_bg', 'label' => __( 'Card Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'tc_radius', 'label' => __( 'Card Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'tc_padding', 'label' => __( 'Card Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'tc_quote_color', 'label' => __( 'Quote Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'tc_quote_typography', 'label' => __( 'Quote Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'tc_name_color', 'label' => __( 'Name Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'tc_role_color', 'label' => __( 'Role Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'tc_star_color', 'label' => __( 'Star Colour', 'loopkit' ), 'type' => 'color', 'default' => '#f59e0b' ),
                        array( 'name' => 'tc_dot_color', 'label' => __( 'Dot Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'tc_dot_active', 'label' => __( 'Active Dot Colour', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'tc_arrow_color', 'label' => __( 'Arrow Colour', 'loopkit' ), 'type' => 'color' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $items = (array) ( $settings['tc_items'] ?? array() );
        if ( ! $items ) {
            return $editor_mode ? '<div class="lk-marquee-empty">' . esc_html__( 'Add a testimonial.', 'loopkit' ) . '</div>' : '';
        }

        $autoplay = $this->switcher_on( $settings, 'tc_autoplay', true );
        $seconds  = max( 1, (int) ( $settings['tc_speed'] ?? 5 ) );

        ob_start();
        ?>
        <div class="lk-tc" data-lk-testimonials data-autoplay="<?php echo $autoplay ? 'yes' : 'no'; ?>" data-speed="<?php echo esc_attr( $seconds * 1000 ); ?>">
            <div class="lk-tc-track">
                <?php foreach ( $items as $index => $item ) : ?>
                    <div class="lk-tc-slide<?php echo 0 === $index ? ' lk-active' : ''; ?>" role="group" aria-roledescription="slide">
                        <?php $rating = max( 0, min( 5, (int) ( $item['rating'] ?? 0 ) ) ); ?>
                        <?php if ( $rating ) : ?>
                            <div class="lk-tc-stars" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: rating */ __( 'Rated %d out of 5', 'loopkit' ), $rating ) ); ?>"><?php echo str_repeat( '&#9733;', $rating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <?php endif; ?>

                        <blockquote class="lk-tc-quote"><?php echo esc_html( $item['quote'] ?? '' ); ?></blockquote>

                        <div class="lk-tc-person">
                            <?php if ( ! empty( $item['image']['url'] ) ) : ?>
                                <img class="lk-tc-photo" src="<?php echo esc_url( $item['image']['url'] ); ?>" alt="<?php echo esc_attr( $item['name'] ?? '' ); ?>" loading="lazy" decoding="async">
                            <?php endif; ?>
                            <div>
                                <div class="lk-tc-name"><?php echo esc_html( $item['name'] ?? '' ); ?></div>
                                <div class="lk-tc-role"><?php echo esc_html( $item['role'] ?? '' ); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ( $this->switcher_on( $settings, 'tc_arrows', true ) && count( $items ) > 1 ) : ?>
                <button type="button" class="lk-tc-arrow lk-tc-prev" aria-label="<?php esc_attr_e( 'Previous', 'loopkit' ); ?>">&#8249;</button>
                <button type="button" class="lk-tc-arrow lk-tc-next" aria-label="<?php esc_attr_e( 'Next', 'loopkit' ); ?>">&#8250;</button>
            <?php endif; ?>

            <?php if ( $this->switcher_on( $settings, 'tc_dots', true ) && count( $items ) > 1 ) : ?>
                <div class="lk-tc-dots">
                    <?php foreach ( $items as $index => $item ) : ?>
                        <button type="button" class="lk-tc-dot<?php echo 0 === $index ? ' lk-active' : ''; ?>" data-index="<?php echo (int) $index; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: slide number */ __( 'Go to slide %d', 'loopkit' ), $index + 1 ) ); ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Blockquote — a pull quote with a citation and a few looks.
 */
class Loopkit_Widget_Blockquote extends Loopkit_Widget_Base {
    use Loopkit_Pro_Widget_Helpers;

    public function get_name() { return 'blockquote'; }
    public function get_title() { return __( 'Blockquote', 'loopkit' ); }
    public function get_icon() { return 'dashicons-editor-quote'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'quote', 'blockquote', 'pull quote', 'citation' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_bq',
                    'title'    => __( 'Quote', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'bq_text', 'label' => __( 'Quote', 'loopkit' ), 'type' => 'textarea', 'default' => __( 'Design is not just what it looks like. Design is how it works.', 'loopkit' ) ),
                        array( 'name' => 'bq_author', 'label' => __( 'Author', 'loopkit' ), 'type' => 'text', 'default' => __( 'Steve Jobs', 'loopkit' ) ),
                        array( 'name' => 'bq_source', 'label' => __( 'Source', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                        array(
                            'name'    => 'bq_style',
                            'label'   => __( 'Look', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'border',
                            'options' => array(
                                'border' => __( 'Line down the side', 'loopkit' ),
                                'quote'  => __( 'Big quote mark', 'loopkit' ),
                                'boxed'  => __( 'Boxed', 'loopkit' ),
                                'plain'  => __( 'Plain', 'loopkit' ),
                            ),
                        ),
                        array( 'name' => 'bq_tweet', 'label' => __( 'Tweet this quote', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_bq_style',
                    'title'    => __( 'Style', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'bq_align', 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'default' => 'left', 'options' => array(
                            'left' => __( 'Left', 'loopkit' ), 'center' => __( 'Center', 'loopkit' ), 'right' => __( 'Right', 'loopkit' ),
                        ) ),
                        array( 'name' => 'bq_color', 'label' => __( 'Quote Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'bq_typography', 'label' => __( 'Quote Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'bq_author_color', 'label' => __( 'Author Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'bq_author_typography', 'label' => __( 'Author Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'bq_accent', 'label' => __( 'Line / Mark Colour', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'bq_bg', 'label' => __( 'Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'bq_padding', 'label' => __( 'Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'bq_radius', 'label' => __( 'Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $text = (string) ( $settings['bq_text'] ?? '' );
        if ( '' === $text ) {
            return $editor_mode ? '<div class="lk-marquee-empty">' . esc_html__( 'Write the quote.', 'loopkit' ) . '</div>' : '';
        }

        $style  = (string) ( $settings['bq_style'] ?? 'border' );
        $style  = in_array( $style, array( 'border', 'quote', 'boxed', 'plain' ), true ) ? $style : 'border';
        $author = (string) ( $settings['bq_author'] ?? '' );
        $source = (string) ( $settings['bq_source'] ?? '' );

        ob_start();
        ?>
        <blockquote class="lk-bq lk-bq-<?php echo esc_attr( $style ); ?>">
            <p class="lk-bq-text"><?php echo esc_html( $text ); ?></p>

            <?php if ( '' !== $author || '' !== $source ) : ?>
                <cite class="lk-bq-cite">
                    <?php if ( '' !== $author ) : ?><span class="lk-bq-author"><?php echo esc_html( $author ); ?></span><?php endif; ?>
                    <?php if ( '' !== $source ) : ?><span class="lk-bq-source"><?php echo esc_html( $source ); ?></span><?php endif; ?>
                </cite>
            <?php endif; ?>

            <?php if ( $this->switcher_on( $settings, 'bq_tweet', false ) ) : ?>
                <a class="lk-bq-tweet" href="https://twitter.com/intent/tweet?text=<?php echo rawurlencode( $text . ( $author ? ' — ' . $author : '' ) ); ?>" target="_blank" rel="noopener">
                    <?php esc_html_e( 'Tweet this', 'loopkit' ); ?>
                </a>
            <?php endif; ?>
        </blockquote>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Business Hours — a day-by-day opening table that knows what today is.
 */
class Loopkit_Widget_Business_Hours extends Loopkit_Widget_Base {
    use Loopkit_Pro_Widget_Helpers;

    public function get_name() { return 'business-hours'; }
    public function get_title() { return __( 'Business Hours', 'loopkit' ); }
    public function get_icon() { return 'dashicons-clock'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'hours', 'opening', 'times', 'schedule', 'business' ); }

    protected function register_controls() {
        $rows = array();
        foreach ( array(
            __( 'Monday', 'loopkit' ), __( 'Tuesday', 'loopkit' ), __( 'Wednesday', 'loopkit' ),
            __( 'Thursday', 'loopkit' ), __( 'Friday', 'loopkit' ),
        ) as $day ) {
            $rows[] = array( 'day' => $day, 'hours' => '9:00 — 18:00', 'closed' => 'no' );
        }
        $rows[] = array( 'day' => __( 'Saturday', 'loopkit' ), 'hours' => '10:00 — 14:00', 'closed' => 'no' );
        $rows[] = array( 'day' => __( 'Sunday', 'loopkit' ), 'hours' => '', 'closed' => 'yes' );

        return array(
            'content' => array(
                array(
                    'id'       => 'section_bh',
                    'title'    => __( 'Hours', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'bh_rows',
                            'label'       => __( 'Days', 'loopkit' ),
                            'type'        => 'repeater',
                            'title_field' => 'day',
                            'default'     => $rows,
                            'fields'      => array(
                                array( 'name' => 'day', 'label' => __( 'Day', 'loopkit' ), 'type' => 'text', 'default' => __( 'Monday', 'loopkit' ) ),
                                array( 'name' => 'hours', 'label' => __( 'Hours', 'loopkit' ), 'type' => 'text', 'default' => '9:00 — 18:00' ),
                                array( 'name' => 'closed', 'label' => __( 'Closed', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                            ),
                        ),
                        array( 'name' => 'bh_closed_text', 'label' => __( 'What to write when closed', 'loopkit' ), 'type' => 'text', 'default' => __( 'Closed', 'loopkit' ) ),
                        array( 'name' => 'bh_highlight', 'label' => __( 'Highlight today', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_bh_style',
                    'title'    => __( 'Style', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'bh_day_color', 'label' => __( 'Day Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'bh_hours_color', 'label' => __( 'Hours Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'bh_typography', 'label' => __( 'Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'bh_row_padding', 'label' => __( 'Row Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'bh_divider', 'label' => __( 'Divider Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'bh_today_bg', 'label' => __( 'Today Background', 'loopkit' ), 'type' => 'color', 'default' => '#f2f8ff' ),
                        array( 'name' => 'bh_today_color', 'label' => __( 'Today Colour', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'bh_closed_color', 'label' => __( 'Closed Colour', 'loopkit' ), 'type' => 'color' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $rows = (array) ( $settings['bh_rows'] ?? array() );
        if ( ! $rows ) {
            return $editor_mode ? '<div class="lk-marquee-empty">' . esc_html__( 'Add your opening hours.', 'loopkit' ) . '</div>' : '';
        }

        $today     = $this->switcher_on( $settings, 'bh_highlight', true ) ? wp_date( 'l' ) : '';
        $closed    = (string) ( $settings['bh_closed_text'] ?? __( 'Closed', 'loopkit' ) );

        ob_start();
        ?>
        <div class="lk-bh">
            <?php foreach ( $rows as $row ) : ?>
                <?php
                $day       = (string) ( $row['day'] ?? '' );
                $is_today  = '' !== $today && 0 === strcasecmp( $day, $today );
                $is_closed = in_array( (string) ( $row['closed'] ?? 'no' ), array( 'yes', '1', 'true' ), true );
                ?>
                <div class="lk-bh-row<?php echo $is_today ? ' lk-bh-today' : ''; ?><?php echo $is_closed ? ' lk-bh-shut' : ''; ?>">
                    <span class="lk-bh-day"><?php echo esc_html( $day ); ?></span>
                    <span class="lk-bh-hours"><?php echo esc_html( $is_closed ? $closed : ( $row['hours'] ?? '' ) ); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Image Hotspots — dots on an image, each with its own tooltip.
 */
class Loopkit_Widget_Hotspot extends Loopkit_Widget_Base {
    use Loopkit_Pro_Widget_Helpers;

    public function get_name() { return 'hotspot'; }
    public function get_title() { return __( 'Image Hotspots', 'loopkit' ); }
    public function get_icon() { return 'dashicons-location'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'hotspot', 'pin', 'marker', 'image', 'tooltip' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_hs',
                    'title'    => __( 'Hotspots', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'hs_image', 'label' => __( 'Image', 'loopkit' ), 'type' => 'media' ),
                        array(
                            'name'        => 'hs_points',
                            'label'       => __( 'Points', 'loopkit' ),
                            'type'        => 'repeater',
                            'title_field' => 'title',
                            'default'     => array(
                                array( 'title' => __( 'Point 1', 'loopkit' ), 'text' => __( 'Say something about this part.', 'loopkit' ), 'x' => '30', 'y' => '40', 'label' => '1' ),
                            ),
                            'fields'      => array(
                                array( 'name' => 'title', 'label' => __( 'Title', 'loopkit' ), 'type' => 'text', 'default' => __( 'Point', 'loopkit' ) ),
                                array( 'name' => 'text', 'label' => __( 'Tooltip text', 'loopkit' ), 'type' => 'textarea', 'default' => '' ),
                                array( 'name' => 'label', 'label' => __( 'Marker label', 'loopkit' ), 'type' => 'text', 'default' => '+' ),
                                array( 'name' => 'x', 'label' => __( 'Left (%)', 'loopkit' ), 'type' => 'number', 'default' => 50 ),
                                array( 'name' => 'y', 'label' => __( 'Top (%)', 'loopkit' ), 'type' => 'number', 'default' => 50 ),
                                array( 'name' => 'url', 'label' => __( 'Link', 'loopkit' ), 'type' => 'url' ),
                            ),
                        ),
                        array(
                            'name'    => 'hs_trigger',
                            'label'   => __( 'Show the tooltip on', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'hover',
                            'options' => array(
                                'hover' => __( 'Hover', 'loopkit' ),
                                'click' => __( 'Click', 'loopkit' ),
                                'always' => __( 'Always', 'loopkit' ),
                            ),
                        ),
                        array( 'name' => 'hs_pulse', 'label' => __( 'Pulse the markers', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_hs_style',
                    'title'    => __( 'Style', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'hs_marker_bg', 'label' => __( 'Marker Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'hs_marker_color', 'label' => __( 'Marker Text', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'hs_marker_size', 'label' => __( 'Marker Size', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 16, 'max' => 72, 'default' => array( 'size' => 30, 'unit' => 'px' ) ),
                        array( 'name' => 'hs_tip_bg', 'label' => __( 'Tooltip Background', 'loopkit' ), 'type' => 'color', 'default' => '#101828' ),
                        array( 'name' => 'hs_tip_color', 'label' => __( 'Tooltip Text', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'hs_tip_radius', 'label' => __( 'Tooltip Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'hs_tip_width', 'label' => __( 'Tooltip Width', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 120, 'max' => 420, 'default' => array( 'size' => 220, 'unit' => 'px' ) ),
                        array( 'name' => 'hs_img_radius', 'label' => __( 'Image Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $image  = $settings['hs_image']['url'] ?? '';
        $points = (array) ( $settings['hs_points'] ?? array() );

        if ( '' === $image ) {
            return $editor_mode ? '<div class="lk-marquee-empty">' . esc_html__( 'Choose an image for the hotspots.', 'loopkit' ) . '</div>' : '';
        }

        $trigger = (string) ( $settings['hs_trigger'] ?? 'hover' );
        $trigger = in_array( $trigger, array( 'hover', 'click', 'always' ), true ) ? $trigger : 'hover';

        ob_start();
        ?>
        <div class="lk-hs lk-hs-<?php echo esc_attr( $trigger ); ?><?php echo $this->switcher_on( $settings, 'hs_pulse', true ) ? ' lk-hs-pulse' : ''; ?>" data-lk-hotspot>
            <img class="lk-hs-image" src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async">

            <?php foreach ( $points as $index => $point ) : ?>
                <?php
                $x   = max( 0, min( 100, (float) ( $point['x'] ?? 50 ) ) );
                $y   = max( 0, min( 100, (float) ( $point['y'] ?? 50 ) ) );
                $url = $point['url']['url'] ?? '';
                ?>
                <div class="lk-hs-point" style="left:<?php echo esc_attr( $x ); ?>%;top:<?php echo esc_attr( $y ); ?>%">
                    <button type="button" class="lk-hs-marker" aria-expanded="false" aria-label="<?php echo esc_attr( $point['title'] ?? '' ); ?>">
                        <?php echo esc_html( $point['label'] ?? '+' ); ?>
                    </button>
                    <div class="lk-hs-tip" role="tooltip">
                        <?php if ( ! empty( $point['title'] ) ) : ?>
                            <strong class="lk-hs-tip-title"><?php echo esc_html( $point['title'] ); ?></strong>
                        <?php endif; ?>
                        <?php if ( ! empty( $point['text'] ) ) : ?>
                            <span class="lk-hs-tip-text"><?php echo esc_html( $point['text'] ); ?></span>
                        <?php endif; ?>
                        <?php if ( $url ) : ?>
                            <a class="lk-hs-tip-link" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Read more', 'loopkit' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Filterable Portfolio — posts of any type, filtered by their terms without a page reload.
 */
class Loopkit_Widget_Portfolio extends Loopkit_Widget_Base {
    use Loopkit_Pro_Widget_Helpers;

    public function get_name() { return 'portfolio'; }
    public function get_title() { return __( 'Filterable Portfolio', 'loopkit' ); }
    public function get_icon() { return 'dashicons-portfolio'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'portfolio', 'filter', 'gallery', 'projects', 'work' ); }

    protected function register_controls() {
        $types = array();
        foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
            $types[ $type->name ] = $type->labels->singular_name;
        }

        $taxes = array( '' => __( 'Pick automatically', 'loopkit' ) );
        foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
            $taxes[ $tax->name ] = $tax->labels->singular_name;
        }

        return array(
            'content' => array(
                array(
                    'id'       => 'section_pf',
                    'title'    => __( 'Portfolio', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'pf_post_type', 'label' => __( 'Show', 'loopkit' ), 'type' => 'select', 'default' => 'post', 'options' => $types ),
                        array( 'name' => 'pf_taxonomy', 'label' => __( 'Filter by', 'loopkit' ), 'type' => 'select', 'default' => '', 'options' => $taxes ),
                        array( 'name' => 'pf_count', 'label' => __( 'How many', 'loopkit' ), 'type' => 'number', 'default' => 9 ),
                        array( 'name' => 'pf_columns', 'label' => __( 'Columns', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 1, 'max' => 6, 'default' => array( 'size' => 3, 'unit' => 'px' ) ),
                        array( 'name' => 'pf_all_label', 'label' => __( '"All" button text', 'loopkit' ), 'type' => 'text', 'default' => __( 'All', 'loopkit' ) ),
                        array( 'name' => 'pf_show_filters', 'label' => __( 'Show the filter buttons', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'pf_show_title', 'label' => __( 'Show the title', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'pf_show_terms', 'label' => __( 'Show the categories', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_pf_style',
                    'title'    => __( 'Style', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'pf_gap', 'label' => __( 'Gap', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 0, 'max' => 60, 'default' => array( 'size' => 20, 'unit' => 'px' ) ),
                        array( 'name' => 'pf_img_height', 'label' => __( 'Image Height', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 100, 'max' => 600, 'default' => array( 'size' => 260, 'unit' => 'px' ) ),
                        array( 'name' => 'pf_radius', 'label' => __( 'Card Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'pf_title_color', 'label' => __( 'Title Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pf_title_typography', 'label' => __( 'Title Typography', 'loopkit' ), 'type' => 'typography' ),
                        array( 'name' => 'pf_term_color', 'label' => __( 'Category Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pf_filter_color', 'label' => __( 'Filter Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'pf_filter_active_bg', 'label' => __( 'Active Filter Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'pf_filter_active_color', 'label' => __( 'Active Filter Text', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'pf_overlay', 'label' => __( 'Hover Overlay', 'loopkit' ), 'type' => 'color' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $post_type = (string) ( $settings['pf_post_type'] ?? 'post' );
        if ( ! post_type_exists( $post_type ) ) {
            $post_type = 'post';
        }

        $taxonomy = (string) ( $settings['pf_taxonomy'] ?? '' );
        if ( '' === $taxonomy ) {
            $available = get_object_taxonomies( $post_type, 'names' );
            $taxonomy  = $available ? reset( $available ) : '';
        }

        $posts = get_posts( array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => max( 1, (int) ( $settings['pf_count'] ?? 9 ) ),
        ) );

        if ( ! $posts ) {
            return $editor_mode ? '<div class="lk-marquee-empty">' . esc_html__( 'Nothing to show here yet.', 'loopkit' ) . '</div>' : '';
        }

        // Only the terms actually used by these posts become buttons.
        $terms = array();
        $map   = array();
        foreach ( $posts as $item ) {
            $slugs = array();
            if ( $taxonomy ) {
                foreach ( (array) get_the_terms( $item->ID, $taxonomy ) as $term ) {
                    if ( $term instanceof WP_Term ) {
                        $terms[ $term->slug ] = $term->name;
                        $slugs[]              = $term->slug;
                    }
                }
            }
            $map[ $item->ID ] = $slugs;
        }

        ob_start();
        ?>
        <div class="lk-pf" data-lk-portfolio>
            <?php if ( $this->switcher_on( $settings, 'pf_show_filters', true ) && $terms ) : ?>
                <div class="lk-pf-filters">
                    <button type="button" class="lk-pf-filter lk-active" data-filter="*"><?php echo esc_html( $settings['pf_all_label'] ?? __( 'All', 'loopkit' ) ); ?></button>
                    <?php foreach ( $terms as $slug => $name ) : ?>
                        <button type="button" class="lk-pf-filter" data-filter="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="lk-pf-grid">
                <?php foreach ( $posts as $item ) : ?>
                    <article class="lk-pf-item" data-terms="<?php echo esc_attr( implode( ' ', $map[ $item->ID ] ) ); ?>">
                        <a class="lk-pf-link" href="<?php echo esc_url( get_permalink( $item ) ); ?>">
                            <?php if ( has_post_thumbnail( $item ) ) : ?>
                                <span class="lk-pf-media"><?php echo get_the_post_thumbnail( $item, 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            <?php else : ?>
                                <span class="lk-pf-media lk-pf-media-empty"></span>
                            <?php endif; ?>

                            <span class="lk-pf-body">
                                <?php if ( $this->switcher_on( $settings, 'pf_show_title', true ) ) : ?>
                                    <span class="lk-pf-title"><?php echo esc_html( get_the_title( $item ) ); ?></span>
                                <?php endif; ?>
                                <?php if ( $this->switcher_on( $settings, 'pf_show_terms', true ) && $map[ $item->ID ] ) : ?>
                                    <span class="lk-pf-terms">
                                        <?php
                                        $names = array();
                                        foreach ( $map[ $item->ID ] as $slug ) {
                                            $names[] = $terms[ $slug ] ?? $slug;
                                        }
                                        echo esc_html( implode( ', ', $names ) );
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

/**
 * Login Form — a styled log-in box that greets whoever is already signed in.
 */
class Loopkit_Widget_Login extends Loopkit_Widget_Base {
    use Loopkit_Pro_Widget_Helpers;

    public function get_name() { return 'login-form'; }
    public function get_title() { return __( 'Login Form', 'loopkit' ); }
    public function get_icon() { return 'dashicons-admin-users'; }
    public function get_categories() { return array( 'general' ); }
    public function get_keywords() { return array( 'login', 'sign in', 'account', 'user', 'form' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_login',
                    'title'    => __( 'Login', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'login_title', 'label' => __( 'Heading', 'loopkit' ), 'type' => 'text', 'default' => __( 'Log in', 'loopkit' ) ),
                        array( 'name' => 'login_user_label', 'label' => __( 'Username label', 'loopkit' ), 'type' => 'text', 'default' => __( 'Username or email', 'loopkit' ) ),
                        array( 'name' => 'login_pass_label', 'label' => __( 'Password label', 'loopkit' ), 'type' => 'text', 'default' => __( 'Password', 'loopkit' ) ),
                        array( 'name' => 'login_button', 'label' => __( 'Button text', 'loopkit' ), 'type' => 'text', 'default' => __( 'Log in', 'loopkit' ) ),
                        array( 'name' => 'login_remember', 'label' => __( 'Remember me', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'login_lost', 'label' => __( 'Lost password link', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'login_register', 'label' => __( 'Register link', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                        array( 'name' => 'login_redirect', 'label' => __( 'Send them to', 'loopkit' ), 'type' => 'url' ),
                        array( 'name' => 'login_logged_in', 'label' => __( 'Message when already logged in', 'loopkit' ), 'type' => 'text', 'default' => __( 'You are logged in as %s.', 'loopkit' ) ),
                    ),
                ),
            ),
            'style' => array(
                array(
                    'id'       => 'section_login_style',
                    'title'    => __( 'Style', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'login_label_color', 'label' => __( 'Label Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'login_field_bg', 'label' => __( 'Field Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'login_field_color', 'label' => __( 'Field Text Colour', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'login_field_border', 'label' => __( 'Field Border', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'login_field_radius', 'label' => __( 'Field Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'login_btn_bg', 'label' => __( 'Button Background', 'loopkit' ), 'type' => 'color', 'default' => '#38A8F5' ),
                        array( 'name' => 'login_btn_color', 'label' => __( 'Button Text', 'loopkit' ), 'type' => 'color', 'default' => '#ffffff' ),
                        array( 'name' => 'login_btn_hover_bg', 'label' => __( 'Button Hover Background', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'login_btn_hover_color', 'label' => __( 'Button Hover Text', 'loopkit' ), 'type' => 'color' ),
                        array( 'name' => 'login_btn_radius', 'label' => __( 'Button Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'login_link_color', 'label' => __( 'Link Colour', 'loopkit' ), 'type' => 'color' ),
                    ),
                ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        // In the editor the form is always drawn, so it can be styled while logged in.
        if ( is_user_logged_in() && ! $editor_mode ) {
            $user    = wp_get_current_user();
            $message = (string) ( $settings['login_logged_in'] ?? __( 'You are logged in as %s.', 'loopkit' ) );
            return '<div class="lk-login lk-login-in"><p>'
                . esc_html( sprintf( $message, $user->display_name ) )
                . ' <a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Log out', 'loopkit' ) . '</a></p></div>';
        }

        $redirect = $settings['login_redirect']['url'] ?? '';

        ob_start();
        ?>
        <div class="lk-login">
            <?php if ( ! empty( $settings['login_title'] ) ) : ?>
                <h3 class="lk-login-title"><?php echo esc_html( $settings['login_title'] ); ?></h3>
            <?php endif; ?>

            <form class="lk-login-form" method="post" action="<?php echo esc_url( wp_login_url() ); ?>">
                <label class="lk-login-field">
                    <span><?php echo esc_html( $settings['login_user_label'] ?? __( 'Username or email', 'loopkit' ) ); ?></span>
                    <input type="text" name="log" autocomplete="username" <?php echo $editor_mode ? 'disabled' : ''; ?>>
                </label>

                <label class="lk-login-field">
                    <span><?php echo esc_html( $settings['login_pass_label'] ?? __( 'Password', 'loopkit' ) ); ?></span>
                    <input type="password" name="pwd" autocomplete="current-password" <?php echo $editor_mode ? 'disabled' : ''; ?>>
                </label>

                <?php if ( $this->switcher_on( $settings, 'login_remember', true ) ) : ?>
                    <label class="lk-login-remember"><input type="checkbox" name="rememberme" value="forever"> <?php esc_html_e( 'Remember me', 'loopkit' ); ?></label>
                <?php endif; ?>

                <input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ?: home_url( '/' ) ); ?>">
                <button type="submit" class="lk-login-btn"<?php echo $editor_mode ? ' disabled' : ''; ?>><?php echo esc_html( $settings['login_button'] ?? __( 'Log in', 'loopkit' ) ); ?></button>

                <?php if ( $this->switcher_on( $settings, 'login_lost', true ) || $this->switcher_on( $settings, 'login_register', false ) ) : ?>
                    <p class="lk-login-links">
                        <?php if ( $this->switcher_on( $settings, 'login_lost', true ) ) : ?>
                            <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'loopkit' ); ?></a>
                        <?php endif; ?>
                        <?php if ( $this->switcher_on( $settings, 'login_register', false ) && get_option( 'users_can_register' ) ) : ?>
                            <a href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'Create an account', 'loopkit' ); ?></a>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
