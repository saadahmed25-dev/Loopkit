<?php
/**
 * Loopkit Loop Grid Widget.
 *
 * Repeats a "Loop Item" template (designed in the Loopkit editor with any widgets, usually
 * the Post widgets) for every post of a query. Query, columns and pagination work like the
 * Posts widget. Each item is wrapped in .loopkit.loopkit-{templateId} so the template's
 * saved CSS applies; that CSS is printed once per page.
 *
 * Markup: <div class="lk-loop-grid"><div class="lk-loop-items"><div class="lk-loop-item loopkit loopkit-{id}">…</div>…</div>[pagination]</div>
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Loop_Grid extends Loopkit_Widget_Posts {

    /** Nesting guard (a Loop Item may itself contain a Loop Grid). */
    private static $depth = 0;

    /** Template IDs whose CSS was already printed on this request. */
    private static $printed_css = array();

    public function get_name() { return 'loop-grid'; }
    public function get_title() { return esc_html__( 'Loop Grid', 'loopkit' ); }
    public function get_icon() { return 'dashicons-screenoptions'; }
    public function get_keywords() { return array( 'loop', 'grid', 'posts', 'query', 'template', 'cards', 'archive' ); }

    /**
     * Loop Item templates.
     *
     * @return array
     */
    public static function loop_item_options() {
        $options = array( '' => esc_html__( '— Choose a Loop Item template —', 'loopkit' ) );
        $posts   = get_posts( array(
            'post_type'      => 'loopkit_template',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 100,
            'meta_key'       => '_loopkit_template_type',
            'meta_value'     => 'loop-item',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        foreach ( $posts as $p ) {
            $options[ (string) $p->ID ] = ( '' !== $p->post_title ? $p->post_title : '#' . $p->ID ) . ( 'publish' !== $p->post_status ? ' (' . $p->post_status . ')' : '' );
        }
        return $options;
    }

    protected function register_controls() {
        $parent = parent::register_controls();

        // Replace the Posts card options with the Loop Item template choice.
        $layout = array(
            'id'       => 'section_loop_layout',
            'title'    => __( 'Layout', 'loopkit' ),
            'tab'      => 'content',
            'controls' => array(
                array(
                    'name'        => 'template_id',
                    'label'       => __( 'Loop Item Template', 'loopkit' ),
                    'type'        => 'select',
                    'default'     => '',
                    'options'     => self::loop_item_options(),
                    'description' => __( 'Design the card in Loopkit > Templates > Add New > Loop Item. Save the page and reload the editor to see new templates here.', 'loopkit' ),
                ),
                array( 'name' => 'columns', 'label' => __( 'Columns', 'loopkit' ), 'type' => 'number', 'responsive' => true, 'default' => 3, 'min' => 1, 'max' => 6 ),
            ),
        );

        $content = array( $layout );
        foreach ( $parent['content'] as $section ) {
            if ( 'section_posts_layout' !== $section['id'] ) {
                $content[] = $section;
            }
        }

        $style = array();
        foreach ( $parent['style'] as $section ) {
            if ( in_array( $section['id'], array( 'section_style_posts_layout', 'section_style_posts_pagination' ), true ) ) {
                $style[] = $section;
            }
        }

        return array( 'content' => $content, 'style' => $style );
    }

    /**
     * The template's saved CSS as a <style> tag, once per request.
     *
     * @param int $tid Template ID.
     * @return string
     */
    private static function template_css( $tid ) {
        if ( isset( self::$printed_css[ $tid ] ) ) {
            return '';
        }
        self::$printed_css[ $tid ] = true;
        $css = (string) get_post_meta( $tid, '_loopkit_css', true );
        if ( '' === $css ) {
            $file = WP_CONTENT_DIR . '/uploads/loopkit/css/post-' . $tid . '.css';
            if ( file_exists( $file ) ) {
                $css = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            }
        }
        return '' !== $css ? '<style id="loopkit-loop-css-' . (int) $tid . '">' . $css . '</style>' : '';
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $tid = absint( $settings['template_id'] ?? 0 );
        if ( ! $tid || 'loop-item' !== get_post_meta( $tid, '_loopkit_template_type', true ) ) {
            return $editor_mode ? '<div class="lk-loop-grid-empty">' . esc_html__( 'Choose a Loop Item template in the widget settings (create one in Loopkit > Templates > Add New > Loop Item).', 'loopkit' ) . '</div>' : '';
        }
        if ( self::$depth > 1 || ! class_exists( 'Loopkit_Frontend' ) ) {
            return '';
        }

        $elements = Loopkit_Frontend::ensure_sections( Loopkit_Frontend::decode_elements( get_post_meta( $tid, '_loopkit_data', true ) ) );
        $args     = $this->query_args( $settings, $editor_mode );

        if ( null === $args ) {
            global $wp_query;
            $query   = $wp_query;
            $current = self::current_page();
            $total   = (int) $wp_query->max_num_pages;
        } else {
            $base_offset = (int) ( $args['_lk_base_offset'] ?? 0 );
            unset( $args['_lk_base_offset'] );
            $query   = new WP_Query( $args );
            $ppp     = (int) $args['posts_per_page'];
            $current = $editor_mode ? 1 : self::current_page();
            $total   = $ppp > 0 ? (int) ceil( max( 0, $query->found_posts - $base_offset ) / $ppp ) : 1;
        }

        $html = self::template_css( $tid ) . '<div class="lk-loop-grid">';

        if ( ! $query->have_posts() ) {
            $msg   = ( isset( $settings['no_posts_message'] ) && '' !== $settings['no_posts_message'] ) ? $settings['no_posts_message'] : __( 'No posts found.', 'loopkit' );
            $html .= '<div class="lk-posts-empty">' . esc_html( $msg ) . '</div>';
        } else {
            self::$depth++;
            $html .= '<div class="lk-loop-items">';
            while ( $query->have_posts() ) {
                $query->the_post();
                $html .= '<div class="lk-loop-item loopkit loopkit-' . $tid . ' post-' . (int) get_the_ID() . '" data-loopkit-type="loop-item">' . Loopkit_Frontend::render_elements( $elements, false ) . '</div>';
            }
            $html .= '</div>';
            self::$depth--;
            if ( null === $args ) {
                $query->rewind_posts();
            }
            wp_reset_postdata();
        }

        $type = $settings['pagination'] ?? 'none';
        if ( $editor_mode && 'none' !== $type && $total < 2 ) {
            $total = 3; // editor: sample links so pagination can be styled
        }
        $html .= self::pagination_html( $current, $total, $type, 'lk-posts-pagination' );

        return $html . '</div>';
    }
}
