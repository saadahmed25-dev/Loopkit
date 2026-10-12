<?php
/**
 * Loopkit Archive Data.
 *
 * One source of archive data (title prefix, title, description) for the Archive Title and
 * Archive Description widgets. The PHP renderers read it for the current request and the
 * editor receives the same array as loopkitEditor.previewArchive.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Archive_Data {

    /**
     * Build the data array.
     *
     * @param string $prefix      Prefix such as "Category:" (may be empty).
     * @param string $title       Archive name.
     * @param string $description Description HTML.
     * @return array
     */
    private static function make( $prefix, $title, $description = '' ) {
        return array(
            'prefix'      => Loopkit_Post_Data::plain( $prefix ),
            'title'       => Loopkit_Post_Data::plain( $title ),
            'description' => trim( wp_kses_post( (string) $description ) ),
        );
    }

    /**
     * Blog posts index title.
     *
     * @return string
     */
    private static function blog_title() {
        $page_for_posts = (int) get_option( 'page_for_posts' );
        return $page_for_posts ? get_the_title( $page_for_posts ) : __( 'Blog', 'loopkit' );
    }

    /**
     * Data for a term.
     *
     * @param WP_Term $term Term.
     * @return array
     */
    private static function term_data( $term ) {
        if ( 'category' === $term->taxonomy ) {
            $prefix = __( 'Category:', 'loopkit' );
        } elseif ( 'post_tag' === $term->taxonomy ) {
            $prefix = __( 'Tag:', 'loopkit' );
        } else {
            $tax    = get_taxonomy( $term->taxonomy );
            $prefix = $tax ? $tax->labels->singular_name . ':' : '';
        }
        return self::make( $prefix, $term->name, term_description( $term ) );
    }

    /**
     * Data for the archive being viewed on the front end, or null when not an archive.
     *
     * @return array|null
     */
    public static function current() {
        if ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            return ( $term instanceof WP_Term ) ? self::term_data( $term ) : null;
        }
        if ( is_author() ) {
            $user_id = (int) get_queried_object_id();
            return self::make( __( 'Author:', 'loopkit' ), get_the_author_meta( 'display_name', $user_id ), wpautop( esc_html( Loopkit_Post_Data::plain( get_the_author_meta( 'description', $user_id ) ) ) ) );
        }
        if ( is_search() ) {
            return self::make( __( 'Search Results for:', 'loopkit' ), '"' . get_search_query( false ) . '"' );
        }
        if ( is_post_type_archive() ) {
            return self::make( __( 'Archives:', 'loopkit' ), post_type_archive_title( '', false ), get_the_post_type_description() );
        }
        if ( is_year() ) {
            return self::make( __( 'Year:', 'loopkit' ), get_the_date( 'Y' ) );
        }
        if ( is_month() ) {
            return self::make( __( 'Month:', 'loopkit' ), get_the_date( 'F Y' ) );
        }
        if ( is_day() ) {
            return self::make( __( 'Day:', 'loopkit' ), get_the_date() );
        }
        if ( is_home() ) {
            return self::make( '', self::blog_title() );
        }
        if ( is_archive() ) {
            return self::make( '', wp_strip_all_tags( get_the_archive_title() ), get_the_archive_description() );
        }
        return null;
    }

    /**
     * Data for a preview key: "blog", "search", "category:{id}" or "post_tag:{id}".
     *
     * @param string $key Preview key.
     * @return array
     */
    public static function preview( $key ) {
        $key = (string) $key;
        if ( 'search' === $key ) {
            return self::make( __( 'Search Results for:', 'loopkit' ), '"' . __( 'sample search', 'loopkit' ) . '"' );
        }
        if ( preg_match( '/^(category|post_tag):(\d+)$/', $key, $m ) ) {
            $term = get_term( (int) $m[2], $m[1] );
            if ( $term instanceof WP_Term ) {
                return self::term_data( $term );
            }
        }
        return self::make( '', self::blog_title() );
    }

    /**
     * Preview key of the Archive template open in the editor.
     *
     * @param int $document_id Template post ID.
     * @return string
     */
    public static function get_preview_key( $document_id ) {
        $page_settings = get_post_meta( (int) $document_id, '_loopkit_page_settings', true );
        $key           = is_array( $page_settings ) ? (string) ( $page_settings['preview_archive'] ?? '' ) : '';
        if ( '' !== $key && isset( self::get_preview_options()[ $key ] ) ) {
            return $key;
        }
        $cats = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true, 'number' => 1, 'orderby' => 'count', 'order' => 'DESC' ) );
        if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
            return 'category:' . $cats[0]->term_id;
        }
        return 'blog';
    }

    /**
     * Options for Template Settings > Preview With (Archive templates).
     *
     * @return array key => label
     */
    public static function get_preview_options() {
        $options = array(
            'blog'   => __( 'Blog (posts index)', 'loopkit' ),
            'search' => __( 'Search results', 'loopkit' ),
        );
        foreach ( array( 'category' => __( 'Category', 'loopkit' ), 'post_tag' => __( 'Tag', 'loopkit' ) ) as $tax => $label ) {
            $terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'number' => 30, 'orderby' => 'name' ) );
            if ( is_wp_error( $terms ) ) {
                continue;
            }
            foreach ( $terms as $term ) {
                $options[ $tax . ':' . $term->term_id ] = $label . ': ' . Loopkit_Post_Data::plain( $term->name );
            }
        }
        return $options;
    }

    /**
     * Data a widget should render: the canvas template's preview in the editor,
     * the current archive on the front end.
     *
     * @param bool $editor_mode Whether rendering in the editor canvas.
     * @return array|null
     */
    public static function get_render_data( $editor_mode = false ) {
        if ( $editor_mode && isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return self::preview( self::get_preview_key( absint( $_GET['loopkit_canvas'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
        }
        return self::current();
    }
}
