<?php
/**
 * Loopkit Dynamic Tags.
 *
 * Any text, link, image or URL field can hold a token instead of a fixed value:
 *
 *   {{lk:post_title}}
 *   {{lk:custom_field:price}}
 *   {{lk:post_title|Untitled}}          <-- text after "|" is the fallback
 *
 * Tokens are replaced when the element is rendered, so every widget becomes dynamic —
 * not only the Post widgets. The same list and format are used by the editor canvas
 * (loopkit-canvas.js resolveDynamicSettings), so the preview matches the front end.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Dynamic {

    /**
     * Token pattern: {{lk:name}} / {{lk:name:arg}} / {{lk:name|fallback}} / {{lk:name:arg|fallback}}
     */
    const PATTERN = '/\{\{\s*lk:([a-z0-9_]+)(?::([^|}]*))?(?:\|([^}]*))?\}\}/i';

    /**
     * All available tags, grouped for the editor menu.
     *
     * @return array
     */
    public static function get_tags() {
        /**
         * Tag groups shown in the editor. Enabled modules (Custom Fields, Post Types, Shop)
         * add their own groups here.
         *
         * @param array $groups Tag groups.
         */
        return apply_filters( 'loopkit_dynamic_tags', self::core_tags() );
    }

    /**
     * The tags Loopkit ships with.
     *
     * @return array
     */
    private static function core_tags() {
        return array(
            array(
                'group' => __( 'Post', 'loopkit' ),
                'tags'  => array(
                    array( 'name' => 'post_title', 'label' => __( 'Post Title', 'loopkit' ), 'type' => 'text' ),
                    array( 'name' => 'post_excerpt', 'label' => __( 'Post Excerpt', 'loopkit' ), 'type' => 'text' ),
                    array( 'name' => 'post_url', 'label' => __( 'Post URL', 'loopkit' ), 'type' => 'url' ),
                    array( 'name' => 'post_id', 'label' => __( 'Post ID', 'loopkit' ), 'type' => 'text' ),
                    array( 'name' => 'post_date', 'label' => __( 'Post Date', 'loopkit' ), 'type' => 'text' ),
                    array( 'name' => 'post_time', 'label' => __( 'Post Time', 'loopkit' ), 'type' => 'text' ),
                    array( 'name' => 'post_modified', 'label' => __( 'Last Modified', 'loopkit' ), 'type' => 'text' ),
                    array( 'name' => 'post_author', 'label' => __( 'Author Name', 'loopkit' ), 'type' => 'text' ),
                    array( 'name' => 'post_author_url', 'label' => __( 'Author URL', 'loopkit' ), 'type' => 'url' ),
                    array( 'name' => 'post_featured_image', 'label' => __( 'Featured Image URL', 'loopkit' ), 'type' => 'image' ),
                    array( 'name' => 'post_terms', 'label' => __( 'Categories / Tags', 'loopkit' ), 'type' => 'text', 'arg' => __( 'taxonomy (category, post_tag)', 'loopkit' ) ),
                    array( 'name' => 'post_comments_count', 'label' => __( 'Comments Count', 'loopkit' ), 'type' => 'text' ),
                ),
            ),
            array(
                'group' => __( 'Archive', 'loopkit' ),
                'tags'  => array(
                    array( 'name' => 'archive_title', 'label' => __( 'Archive Title', 'loopkit' ), 'type' => 'text' ),
                    array( 'name' => 'archive_description', 'label' => __( 'Archive Description', 'loopkit' ), 'type' => 'text' ),
                ),
            ),
            array(
                'group' => __( 'Site', 'loopkit' ),
                'tags'  => array(
                    array( 'name' => 'site_title', 'label' => __( 'Site Title', 'loopkit' ), 'type' => 'text' ),
                    array( 'name' => 'site_tagline', 'label' => __( 'Site Tagline', 'loopkit' ), 'type' => 'text' ),
                    array( 'name' => 'site_url', 'label' => __( 'Site URL', 'loopkit' ), 'type' => 'url' ),
                    array( 'name' => 'site_logo', 'label' => __( 'Site Logo URL', 'loopkit' ), 'type' => 'image' ),
                    array( 'name' => 'current_year', 'label' => __( 'Current Year', 'loopkit' ), 'type' => 'text' ),
                    array( 'name' => 'current_date', 'label' => __( 'Current Date', 'loopkit' ), 'type' => 'text' ),
                ),
            ),
            array(
                'group' => __( 'Other', 'loopkit' ),
                'tags'  => array(
                    array( 'name' => 'custom_field', 'label' => __( 'Custom Field', 'loopkit' ), 'type' => 'text', 'arg' => __( 'field key', 'loopkit' ) ),
                    array( 'name' => 'user_name', 'label' => __( 'Logged-in User Name', 'loopkit' ), 'type' => 'text' ),
                ),
            ),
        );
    }

    /**
     * Value of one tag for the current post / request.
     *
     * @param string $name        Tag name.
     * @param string $arg         Optional argument (field key, taxonomy).
     * @param bool   $editor_mode Whether rendering in the editor canvas.
     * @return string
     */
    public static function get_value( $name, $arg = '', $editor_mode = false ) {
        $post_id = class_exists( 'Loopkit_Post_Data' ) ? Loopkit_Post_Data::get_render_post_id( $editor_mode ) : (int) get_the_ID();
        $post    = $post_id ? get_post( $post_id ) : null;

        switch ( $name ) {
            case 'post_title':
                return $post ? Loopkit_Post_Data::plain_title( $post ) : '';
            case 'post_excerpt':
                if ( ! $post ) {
                    return '';
                }
                $data = Loopkit_Post_Data::get_data( $post->ID );
                return $data ? Loopkit_Widget_Post_Excerpt::get_excerpt_text( $data, array( 'excerpt_length' => 25 ) ) : '';
            case 'post_url':
                return $post ? (string) get_permalink( $post ) : '';
            case 'post_id':
                return $post ? (string) $post->ID : '';
            case 'post_date':
                return $post ? (string) get_the_date( '', $post ) : '';
            case 'post_time':
                return $post ? (string) get_the_time( '', $post ) : '';
            case 'post_modified':
                return $post ? (string) get_the_modified_date( '', $post ) : '';
            case 'post_author':
                return $post ? Loopkit_Post_Data::plain( get_the_author_meta( 'display_name', $post->post_author ) ) : '';
            case 'post_author_url':
                return $post ? (string) get_author_posts_url( (int) $post->post_author ) : '';
            case 'post_featured_image':
                if ( ! $post ) {
                    return '';
                }
                $thumb = get_post_thumbnail_id( $post );
                if ( ! $thumb ) {
                    return '';
                }
                $src = wp_get_attachment_image_src( $thumb, 'large' );
                return $src ? $src[0] : '';
            case 'post_terms':
                if ( ! $post ) {
                    return '';
                }
                $tax   = ( 'post_tag' === $arg ) ? 'post_tag' : ( $arg ? sanitize_key( $arg ) : 'category' );
                $terms = get_the_terms( $post, $tax );
                if ( ! is_array( $terms ) ) {
                    return '';
                }
                return implode( ', ', array_map( function( $t ) {
                    return Loopkit_Post_Data::plain( $t->name );
                }, $terms ) );
            case 'post_comments_count':
                return $post ? (string) (int) get_comments_number( $post ) : '';

            case 'archive_title':
                $a = class_exists( 'Loopkit_Archive_Data' ) ? Loopkit_Archive_Data::get_render_data( $editor_mode ) : null;
                return $a ? trim( $a['prefix'] . ' ' . $a['title'] ) : '';
            case 'archive_description':
                $a = class_exists( 'Loopkit_Archive_Data' ) ? Loopkit_Archive_Data::get_render_data( $editor_mode ) : null;
                return $a ? wp_strip_all_tags( $a['description'] ) : '';

            case 'site_title':
                return (string) get_bloginfo( 'name' );
            case 'site_tagline':
                return (string) get_bloginfo( 'description' );
            case 'site_url':
                return (string) home_url( '/' );
            case 'site_logo':
                $logo = (int) get_theme_mod( 'custom_logo' );
                return $logo ? (string) wp_get_attachment_image_url( $logo, 'full' ) : '';
            case 'current_year':
                return (string) date_i18n( 'Y' );
            case 'current_date':
                return (string) date_i18n( get_option( 'date_format' ) );

            case 'custom_field':
                if ( ! $post || '' === $arg ) {
                    return '';
                }
                $key = sanitize_text_field( $arg );
                if ( '_' === substr( $key, 0, 1 ) ) {
                    return ''; // protected/private meta is not exposed
                }
                $value = get_post_meta( $post->ID, $key, true );
                if ( is_array( $value ) ) {
                    $value = implode( ', ', array_filter( $value, 'is_scalar' ) );
                }
                return is_scalar( $value ) ? (string) $value : '';
            case 'user_name':
                $user = wp_get_current_user();
                return ( $user && $user->ID ) ? Loopkit_Post_Data::plain( $user->display_name ) : '';

            case 'post_type_label':
                if ( ! $post ) {
                    return '';
                }
                $obj = get_post_type_object( $post->post_type );
                return $obj ? (string) $obj->labels->singular_name : '';
        }

        // Site-wide values from the Custom Fields module's Options page.
        if ( 'option' === $name ) {
            $options = get_option( 'loopkit_field_options', array() );
            $stored  = is_array( $options ) ? ( $options[ $arg ] ?? '' ) : '';
            return is_array( $stored ) ? implode( ', ', array_map( function( $row ) {
                return is_array( $row ) ? implode( ' ', $row ) : (string) $row;
            }, $stored ) ) : (string) $stored;
        }

        // Tags added by enabled modules (Shop prices, and anything a module registers).
        if ( 0 === strpos( $name, 'product_' ) && class_exists( 'Loopkit_Module_WooCommerce' ) && $post ) {
            return Loopkit_Module_WooCommerce::tag_value( $name, $post->ID );
        }

        /**
         * Value of a tag a module added.
         *
         * @param string   $value   Empty by default.
         * @param string   $name    Tag name.
         * @param string   $arg     Optional argument.
         * @param int|null $post_id Current post.
         */
        return (string) apply_filters( 'loopkit_dynamic_tag_value', '', $name, $arg, $post ? $post->ID : 0 );
    }

    /**
     * Replace every token in a string.
     *
     * @param string $text        Text possibly containing tokens.
     * @param bool   $editor_mode Editor canvas.
     * @return string
     */
    public static function resolve_string( $text, $editor_mode = false ) {
        if ( ! is_string( $text ) || false === strpos( $text, '{{' ) ) {
            return $text;
        }

        return preg_replace_callback( self::PATTERN, function( $m ) use ( $editor_mode ) {
            $name     = strtolower( $m[1] );
            $arg      = isset( $m[2] ) ? trim( $m[2] ) : '';
            $fallback = isset( $m[3] ) ? trim( $m[3] ) : '';
            $value    = self::get_value( $name, $arg, $editor_mode );
            return ( '' !== trim( (string) $value ) ) ? $value : $fallback;
        }, $text );
    }

    /**
     * Replace tokens anywhere inside a settings array (including repeater rows).
     *
     * @param mixed $settings    Settings value.
     * @param bool  $editor_mode Editor canvas.
     * @return mixed
     */
    public static function resolve_settings( $settings, $editor_mode = false ) {
        if ( is_string( $settings ) ) {
            return self::resolve_string( $settings, $editor_mode );
        }
        if ( is_array( $settings ) ) {
            foreach ( $settings as $key => $value ) {
                $settings[ $key ] = self::resolve_settings( $value, $editor_mode );
            }
        }
        return $settings;
    }

    /**
     * Whether a settings array contains any token (cheap check before resolving).
     *
     * @param mixed $settings Settings.
     * @return bool
     */
    public static function has_tokens( $settings ) {
        if ( is_string( $settings ) ) {
            return false !== strpos( $settings, '{{' );
        }
        if ( is_array( $settings ) ) {
            foreach ( $settings as $value ) {
                if ( self::has_tokens( $value ) ) {
                    return true;
                }
            }
        }
        return false;
    }
}
