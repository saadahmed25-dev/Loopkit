<?php
/**
 * Loopkit Post Data.
 *
 * One source of post data for the Post widgets (Post Title, Featured Image,
 * Post Excerpt, Post Info). The PHP renderers read it for the current post and the
 * editor receives the same array as LK.previewPost, so the canvas and the front end
 * render from identical values.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Post_Data {

    /**
     * Date formats offered by Post Info (key = PHP date format, 'default' = site setting).
     */
    const DATE_FORMATS = array( 'default', 'F j, Y', 'Y-m-d', 'm/d/Y', 'd/m/Y' );

    /**
     * Time formats offered by Post Info.
     */
    const TIME_FORMATS = array( 'default', 'g:i a', 'g:i A', 'H:i' );

    /**
     * Maximum number of words of content kept for excerpt generation.
     */
    const CONTENT_WORDS = 300;

    /**
     * The post whose data a Post widget should render.
     *
     * Editor canvas: the preview post of the document being edited.
     * Front end: the post in the loop, else the queried singular post, else the global post.
     *
     * @param bool $editor_mode Whether rendering inside the editor canvas.
     * @return int Post ID or 0.
     */
    public static function get_render_post_id( $editor_mode = false ) {
        // Inside a loop (Loop Grid, Single template loop) the loop's post wins, unless the
        // "post" is the Loopkit template being edited in the canvas.
        if ( in_the_loop() && get_the_ID() && 'loopkit_template' !== get_post_type( get_the_ID() ) ) {
            return (int) get_the_ID();
        }

        if ( $editor_mode && isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return self::get_preview_post_id( absint( $_GET['loopkit_canvas'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
        }

        if ( is_singular() ) {
            return (int) get_queried_object_id();
        }

        return (int) get_the_ID();
    }

    /**
     * The post shown in the editor while editing a document.
     *
     * Loopkit templates (Single, Header, ...) have no post of their own, so they preview
     * the post chosen in Template Settings > Preview With (Single only), else the latest
     * published post, else the latest published page. Normal pages/posts preview themselves.
     *
     * @param int $document_id Post ID of the document open in the editor.
     * @return int Post ID or 0.
     */
    public static function get_preview_post_id( $document_id ) {
        $document_id = absint( $document_id );
        if ( ! $document_id ) {
            return 0;
        }

        if ( 'loopkit_template' !== get_post_type( $document_id ) ) {
            return $document_id;
        }

        $page_settings = get_post_meta( $document_id, '_loopkit_page_settings', true );
        $chosen        = is_array( $page_settings ) ? absint( $page_settings['preview_id'] ?? 0 ) : 0;
        if ( $chosen && self::is_previewable( $chosen ) ) {
            return $chosen;
        }

        foreach ( array( 'post', 'page' ) as $type ) {
            $latest = get_posts( array(
                'post_type'      => $type,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ) );
            if ( ! empty( $latest ) ) {
                return (int) $latest[0];
            }
        }

        return 0;
    }

    /**
     * Whether a post can be used as a preview post.
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    public static function is_previewable( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || 'loopkit_template' === $post->post_type ) {
            return false;
        }
        return in_array( $post->post_status, array( 'publish', 'private', 'draft', 'future', 'pending' ), true );
    }

    /**
     * Options for Template Settings > Preview With: recent posts and pages.
     *
     * @return array id => label
     */
    public static function get_preview_options() {
        $options = array();
        $posts   = get_posts( array(
            'post_type'      => array( 'post', 'page' ),
            'post_status'    => 'publish',
            'posts_per_page' => 40,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        foreach ( $posts as $p ) {
            $type_label           = ( 'page' === $p->post_type ) ? __( 'Page', 'loopkit' ) : __( 'Post', 'loopkit' );
            $title                = self::plain_title( $p );
            $options[ (string) $p->ID ] = $type_label . ': ' . ( '' !== $title ? $title : '#' . $p->ID );
        }
        return $options;
    }

    /**
     * Post title as plain text (entities decoded, tags stripped).
     *
     * @param WP_Post|int $post Post.
     * @return string
     */
    public static function plain_title( $post ) {
        return wp_strip_all_tags( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) );
    }

    /**
     * Stored text as plain text: WordPress keeps some fields entity-encoded (e.g. "&amp;"),
     * so decode once here; both renderers then escape it exactly once.
     *
     * @param string $text Text.
     * @return string
     */
    public static function plain( $text ) {
        return wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) );
    }

    /**
     * Plain text, trimmed to a number of words. Same algorithm as the canvas.
     *
     * @param string $text   Text.
     * @param int    $length Word count.
     * @return string
     */
    public static function trim_words( $text, $length ) {
        $text   = trim( (string) $text );
        $length = max( 1, (int) $length );
        if ( '' === $text ) {
            return '';
        }
        $words = preg_split( '/\s+/u', $text );
        if ( count( $words ) > $length ) {
            return implode( ' ', array_slice( $words, 0, $length ) ) . "\u{2026}";
        }
        return implode( ' ', $words );
    }

    /**
     * Collect everything the Post widgets need for one post.
     *
     * @param int $post_id Post ID.
     * @return array|null Null when the post does not exist.
     */
    public static function get_data( $post_id, $for_editor = false ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return null;
        }

        // Excerpt sources
        $custom_excerpt = has_excerpt( $post ) ? wp_strip_all_tags( html_entity_decode( $post->post_excerpt, ENT_QUOTES, 'UTF-8' ) ) : '';
        $content        = (string) $post->post_content;
        if ( function_exists( 'excerpt_remove_blocks' ) ) {
            $content = excerpt_remove_blocks( $content );
        }
        $content      = wp_strip_all_tags( html_entity_decode( strip_shortcodes( $content ), ENT_QUOTES, 'UTF-8' ) );
        $content_text = self::trim_words( $content, self::CONTENT_WORDS );
        if ( "\u{2026}" === mb_substr( $content_text, -1 ) ) {
            $content_text = mb_substr( $content_text, 0, -1 );
        }

        // Featured image
        $image    = null;
        $thumb_id = (int) get_post_thumbnail_id( $post );
        if ( $thumb_id ) {
            $sizes = array();
            foreach ( array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ) as $size ) {
                $src = wp_get_attachment_image_src( $thumb_id, $size );
                if ( $src ) {
                    $sizes[ $size ] = $src[0];
                }
            }
            $attachment = get_post( $thumb_id );
            $image      = array(
                'id'      => $thumb_id,
                'alt'     => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
                'caption' => $attachment ? self::plain( $attachment->post_excerpt ) : '',
                'sizes'   => $sizes,
            );
        }

        // Dates and times
        $dates    = array();
        $modified = array();
        foreach ( self::DATE_FORMATS as $fmt ) {
            $php_fmt          = ( 'default' === $fmt ) ? get_option( 'date_format' ) : $fmt;
            $dates[ $fmt ]    = get_the_date( $php_fmt, $post );
            $modified[ $fmt ] = get_the_modified_date( $php_fmt, $post );
        }
        $times = array();
        foreach ( self::TIME_FORMATS as $fmt ) {
            $php_fmt       = ( 'default' === $fmt ) ? get_option( 'time_format' ) : $fmt;
            $times[ $fmt ] = get_the_time( $php_fmt, $post );
        }

        // Terms
        $terms = array();
        foreach ( array( 'category', 'post_tag' ) as $tax ) {
            $terms[ $tax ] = array();
            if ( ! is_object_in_taxonomy( $post->post_type, $tax ) ) {
                continue;
            }
            $post_terms = get_the_terms( $post, $tax );
            if ( is_array( $post_terms ) ) {
                foreach ( $post_terms as $term ) {
                    $link            = get_term_link( $term );
                    $terms[ $tax ][] = array(
                        'name' => self::plain( $term->name ),
                        'url'  => is_wp_error( $link ) ? '' : $link,
                    );
                }
            }
        }

        $comment_count = (int) get_comments_number( $post );

        return array(
            'id'               => (int) $post->ID,
            'type'             => $post->post_type,
            'title'            => self::plain_title( $post ),
            'permalink'        => (string) get_permalink( $post ),
            'custom_excerpt'   => $custom_excerpt,
            'content_text'     => $content_text,
            'image'            => $image,
            'dates'            => $dates,
            'modified'         => $modified,
            'times'            => $times,
            'day_archive_url'  => get_day_link( get_the_date( 'Y', $post ), get_the_date( 'm', $post ), get_the_date( 'd', $post ) ),
            'author'           => self::author_data( $post->post_author ),
            'terms'            => $terms,
            'comments'         => array(
                'count' => $comment_count,
                'url'   => get_comments_link( $post ),
                'text'  => self::comments_text( $comment_count ),
            ),
            'nav'              => self::adjacent_data( $post ),
        ) + ( $for_editor ? array(
            // Server-rendered snapshots for the editor canvas (the front end renders these live).
            'content_html'  => self::content_html( $post->ID ),
            'comments_html' => self::comments_parts( $post->ID ),
        ) : array() );
    }

    /**
     * Author data for Post Info and Author Box.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public static function author_data( $user_id ) {
        $user_id = (int) $user_id;
        return array(
            'name'    => self::plain( get_the_author_meta( 'display_name', $user_id ) ),
            'url'     => $user_id ? (string) get_author_posts_url( $user_id ) : '',
            'bio'     => self::plain( get_the_author_meta( 'description', $user_id ) ),
            'website' => (string) get_the_author_meta( 'user_url', $user_id ),
            'avatar'  => (string) get_avatar_url( $user_id, array( 'size' => 300 ) ),
        );
    }

    /**
     * Previous / next posts, both across all posts and within the same category.
     *
     * @param WP_Post $post Post.
     * @return array
     */
    public static function adjacent_data( $the_post ) {
        $out = array( 'prev_all' => null, 'next_all' => null, 'prev_same' => null, 'next_same' => null );
        if ( ! $the_post instanceof WP_Post || is_post_type_hierarchical( $the_post->post_type ) ) {
            return $out; // pages and other hierarchical types have no previous/next post
        }

        // get_adjacent_post() reads the global $post.
        global $post;
        $saved_post = $post;
        $post       = $the_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

        foreach ( array( 'all' => false, 'same' => true ) as $key => $same_term ) {
            foreach ( array( 'prev' => true, 'next' => false ) as $dir => $previous ) {
                $adj = get_adjacent_post( $same_term, '', $previous, 'category' );
                if ( $adj instanceof WP_Post ) {
                    $out[ $dir . '_' . $key ] = array(
                        'title' => self::plain_title( $adj ),
                        'url'   => (string) get_permalink( $adj ),
                    );
                }
            }
        }

        $post = $saved_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        return $out;
    }

    /**
     * Rendering depth guard for Post Content (a post can never render itself inside itself).
     *
     * @var array
     */
    private static $rendering_content = array();

    /**
     * The post's content, run through the_content (Loopkit-built posts render their layout).
     * Password-protected posts return the password form.
     *
     * @param int $post_id Post ID.
     * @return string HTML.
     */
    public static function content_html( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || 'loopkit_template' === $post->post_type || ! empty( self::$rendering_content[ $post->ID ] ) || count( self::$rendering_content ) > 2 ) {
            return '';
        }
        if ( post_password_required( $post ) ) {
            return get_the_password_form( $post );
        }

        self::$rendering_content[ $post->ID ] = true;

        global $post;
        $saved_post = $post;
        $post       = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        setup_postdata( $post );

        $html  = apply_filters( 'the_content', get_the_content( null, false, $post ) );
        $html  = str_replace( ']]>', ']]&gt;', $html );
        $html .= wp_link_pages( array( 'echo' => 0 ) );

        $post = $saved_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        if ( $post ) {
            setup_postdata( $post );
        } else {
            wp_reset_postdata();
        }

        unset( self::$rendering_content[ $post_id ] );
        return $html;
    }

    /**
     * Comment list + comment form for a post, rendered without the theme's comments.php
     * so it works the same on classic and block themes.
     *
     * @param int $post_id Post ID.
     * @return array { list: string, form: string, open: bool, count: int }
     */
    public static function comments_parts( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return array( 'list' => '', 'form' => '', 'open' => false, 'count' => 0 );
        }

        $comments = get_comments( array(
            'post_id' => $post->ID,
            'status'  => 'approve',
            'order'   => 'ASC',
        ) );

        $list = '';
        if ( ! empty( $comments ) ) {
            $list = wp_list_comments( array(
                'style'       => 'ol',
                'short_ping'  => true,
                'avatar_size' => 48,
                'echo'        => false,
            ), $comments );
            $list = '<ol class="lk-comment-list">' . $list . '</ol>';
        }

        $form = '';
        $open = comments_open( $post );
        if ( $open ) {
            ob_start();
            comment_form( array( 'class_container' => 'lk-comment-respond comment-respond' ), $post );
            $form = ob_get_clean();
        }

        return array(
            'list'  => $list,
            'form'  => $form,
            'open'  => (bool) $open,
            'count' => count( $comments ),
        );
    }

    /**
     * "No Comments" / "One Comment" / "N Comments".
     *
     * @param int $count Comment count.
     * @return string
     */
    public static function comments_text( $count ) {
        if ( 0 === (int) $count ) {
            return __( 'No Comments', 'loopkit' );
        }
        if ( 1 === (int) $count ) {
            return __( 'One Comment', 'loopkit' );
        }
        /* translators: %s: number of comments */
        return sprintf( __( '%s Comments', 'loopkit' ), number_format_i18n( $count ) );
    }

    /**
     * Sample data used in the editor when the site has no posts at all.
     *
     * @return array
     */
    public static function get_sample_data() {
        $dates = array();
        foreach ( self::DATE_FORMATS as $fmt ) {
            $dates[ $fmt ] = date_i18n( 'default' === $fmt ? get_option( 'date_format' ) : $fmt );
        }
        $times = array();
        foreach ( self::TIME_FORMATS as $fmt ) {
            $times[ $fmt ] = date_i18n( 'default' === $fmt ? get_option( 'time_format' ) : $fmt );
        }
        return array(
            'id'              => 0,
            'type'            => 'post',
            'title'           => __( 'This Is a Sample Post Title', 'loopkit' ),
            'permalink'       => home_url( '/' ),
            'custom_excerpt'  => '',
            'content_text'    => __( 'This is sample post content. Publish a post to preview real content here. The excerpt widget shows the first words of the post, or its manual excerpt when one is set.', 'loopkit' ),
            'image'           => null,
            'dates'           => $dates,
            'modified'        => $dates,
            'times'           => $times,
            'day_archive_url' => home_url( '/' ),
            'author'          => array(
                'name'    => wp_get_current_user()->display_name,
                'url'     => home_url( '/' ),
                'bio'     => __( 'This is where the author biography appears. Add it under Users > Profile > Biographical Info.', 'loopkit' ),
                'website' => '',
                'avatar'  => (string) get_avatar_url( get_current_user_id(), array( 'size' => 300 ) ),
            ),
            'terms'           => array(
                'category' => array( array( 'name' => __( 'Uncategorized', 'loopkit' ), 'url' => home_url( '/' ) ) ),
                'post_tag' => array(),
            ),
            'comments'        => array(
                'count' => 0,
                'url'   => home_url( '/' ),
                'text'  => self::comments_text( 0 ),
            ),
            'nav'             => array(
                'prev_all'  => array( 'title' => __( 'Previous Post Title', 'loopkit' ), 'url' => home_url( '/' ) ),
                'next_all'  => array( 'title' => __( 'Next Post Title', 'loopkit' ), 'url' => home_url( '/' ) ),
                'prev_same' => array( 'title' => __( 'Previous Post Title', 'loopkit' ), 'url' => home_url( '/' ) ),
                'next_same' => array( 'title' => __( 'Next Post Title', 'loopkit' ), 'url' => home_url( '/' ) ),
            ),
            'content_html'    => '<p>' . esc_html__( 'This is sample post content. Publish a post, or choose one under Template Settings > Preview With, to see real content here.', 'loopkit' ) . '</p>',
            'comments_html'   => array( 'list' => '', 'form' => '', 'open' => false, 'count' => 0 ),
        );
    }

    /**
     * Data for the post a widget renders; sample data in the editor if there is none.
     *
     * @param bool $editor_mode Whether rendering inside the editor canvas.
     * @return array|null
     */
    public static function get_render_data( $editor_mode = false ) {
        $post_id = self::get_render_post_id( $editor_mode );
        $data    = $post_id ? self::get_data( $post_id ) : null;
        if ( ! $data && $editor_mode ) {
            $data = self::get_sample_data();
        }
        return $data;
    }
}
