<?php
/**
 * Loopkit Shop — product reviews and star ratings.
 *
 * A rating rides along with the ordinary WordPress comment, so moderation, spam protection
 * and everything else keep working. The average is kept on the product so grids and sorting
 * never have to count comments.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Shop_Reviews {

    /**
     * Hook up.
     */
    public static function init() {
        add_filter( 'comment_form_fields', array( __CLASS__, 'add_rating_field' ) );
        add_action( 'comment_post', array( __CLASS__, 'save_rating' ), 10, 1 );
        add_action( 'wp_set_comment_status', array( __CLASS__, 'refresh_on_status' ), 10, 1 );
        add_filter( 'comment_text', array( __CLASS__, 'show_rating' ), 10, 2 );
        add_filter( 'loopkit_dynamic_tags', array( __CLASS__, 'add_dynamic_tags' ) );
    }

    /**
     * Stars on the review form of a product.
     *
     * @param array $fields Comment form fields.
     * @return array
     */
    public static function add_rating_field( $fields ) {
        if ( Loopkit_Module_WooCommerce::POST_TYPE !== get_post_type() ) {
            return $fields;
        }

        $stars = '<p class="lk-review-rating"><label>' . esc_html__( 'Your rating', 'loopkit' ) . '</label><span class="lk-review-stars">';
        for ( $i = 5; $i >= 1; $i-- ) {
            $stars .= '<input type="radio" id="lk-star-' . $i . '" name="lk_rating" value="' . $i . '"><label for="lk-star-' . $i . '" title="' . esc_attr( sprintf( /* translators: %d: number of stars */ _n( '%d star', '%d stars', $i, 'loopkit' ), $i ) ) . '">&#9733;</label>';
        }
        $stars .= '</span></p>';

        // The stars go above the comment box.
        $ordered = array( 'lk_rating' => $stars );
        foreach ( $fields as $key => $field ) {
            $ordered[ $key ] = $field;
        }
        return $ordered;
    }

    /**
     * Keep the rating with the comment and refresh the product's average.
     *
     * @param int $comment_id Comment.
     */
    public static function save_rating( $comment_id ) {
        $rating = isset( $_POST['lk_rating'] ) ? (int) $_POST['lk_rating'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
        if ( $rating < 1 || $rating > 5 ) {
            return;
        }

        $comment = get_comment( $comment_id );
        if ( ! $comment || Loopkit_Module_WooCommerce::POST_TYPE !== get_post_type( $comment->comment_post_ID ) ) {
            return;
        }

        update_comment_meta( $comment_id, 'lk_rating', $rating );
        self::refresh_average( (int) $comment->comment_post_ID );
    }

    /**
     * A review being approved or unapproved changes the average.
     *
     * @param int $comment_id Comment.
     */
    public static function refresh_on_status( $comment_id ) {
        $comment = get_comment( $comment_id );
        if ( $comment ) {
            self::refresh_average( (int) $comment->comment_post_ID );
        }
    }

    /**
     * Work out and store the average rating and how many reviews it came from.
     *
     * @param int $product_id Product.
     */
    public static function refresh_average( $product_id ) {
        $comments = get_comments( array(
            'post_id' => $product_id,
            'status'  => 'approve',
            'meta_key' => 'lk_rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        ) );

        $sum   = 0;
        $count = 0;
        foreach ( $comments as $comment ) {
            $rating = (int) get_comment_meta( $comment->comment_ID, 'lk_rating', true );
            if ( $rating > 0 ) {
                $sum += $rating;
                $count++;
            }
        }

        if ( $count ) {
            update_post_meta( $product_id, '_loopkit_rating', round( $sum / $count, 2 ) );
            update_post_meta( $product_id, '_loopkit_rating_count', $count );
        } else {
            delete_post_meta( $product_id, '_loopkit_rating' );
            delete_post_meta( $product_id, '_loopkit_rating_count' );
        }
    }

    /**
     * Show the stars above a review.
     *
     * @param string     $text    Comment text.
     * @param WP_Comment $comment Comment.
     * @return string
     */
    public static function show_rating( $text, $comment = null ) {
        if ( ! $comment instanceof WP_Comment ) {
            return $text;
        }
        $rating = (int) get_comment_meta( $comment->comment_ID, 'lk_rating', true );
        if ( $rating < 1 ) {
            return $text;
        }
        return '<span class="lk-review-stars-shown" aria-label="' . esc_attr( sprintf( /* translators: %d: rating out of five */ __( 'Rated %d out of 5', 'loopkit' ), $rating ) ) . '">'
            . str_repeat( '&#9733;', $rating ) . '<span class="lk-review-star-off">' . str_repeat( '&#9733;', 5 - $rating ) . '</span></span>' . $text;
    }

    /**
     * The average and count as Dynamic Tags.
     *
     * @param array $groups Tag groups.
     * @return array
     */
    public static function add_dynamic_tags( $groups ) {
        foreach ( $groups as $i => $group ) {
            if ( __( 'Shop', 'loopkit' ) !== $group['group'] ) {
                continue;
            }
            $groups[ $i ]['tags'][] = array( 'name' => 'product_rating', 'label' => __( 'Rating (average)', 'loopkit' ), 'type' => 'text' );
            $groups[ $i ]['tags'][] = array( 'name' => 'product_rating_count', 'label' => __( 'Number of reviews', 'loopkit' ), 'type' => 'text' );
        }
        return $groups;
    }
}
