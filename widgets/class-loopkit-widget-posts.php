<?php
/**
 * Loopkit Posts Widget (grid / list of posts with query controls and pagination).
 *
 * The editor canvas renders this widget on the server (POST /loopkit/v1/render-widget)
 * with this same class, so canvas and front end markup are identical.
 *
 * Markup:
 * <div class="lk-posts lk-posts-{grid|list}">
 *   <div class="lk-posts-items">
 *     <article class="lk-post-card">
 *       [<a class="lk-post-card-image" href><img></a>]
 *       <div class="lk-post-card-body">[title][meta][excerpt][read more]</div>
 *     </article> …
 *   </div>
 *   [<nav class="lk-posts-pagination">…</nav>]
 * </div>
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Posts extends Loopkit_Widget_Base {

    public function get_name() {
        return 'posts';
    }

    public function get_title() {
        return esc_html__( 'Posts', 'loopkit' );
    }

    public function get_icon() {
        return 'dashicons-grid-view';
    }

    public function get_category() {
        return 'post';
    }

    public function get_keywords() {
        return array( 'posts', 'grid', 'list', 'blog', 'loop', 'query', 'archive', 'recent' );
    }

    public function get_context() {
        return 'all';
    }

    /**
     * Post types the query can target.
     *
     * Every public post type is offered, not just posts and pages, so a post type built
     * with the Post Types module (or any other plugin's) can be looped over here.
     *
     * @return array
     */
    private function post_type_options() {
        $options = array();
        foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
            $options[ $type->name ] = $type->labels->singular_name;
        }
        return $options;
    }

    /**
     * Category options for the query.
     *
     * @return array
     */
    private function category_options() {
        $options = array( '' => esc_html__( 'All categories', 'loopkit' ) );
        $terms   = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'number' => 100 ) );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                $options[ (string) $t->term_id ] = html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' );
            }
        }
        return $options;
    }

    protected function register_controls() {
        $typo = function( $name, $label ) {
            return array( 'name' => $name, 'label' => $label, 'type' => 'typography' );
        };
        $color = function( $name, $label ) {
            return array( 'name' => $name, 'label' => $label, 'type' => 'color' );
        };

        return array(
            'content' => array(
                array(
                    'id'       => 'section_posts_layout',
                    'title'    => __( 'Layout', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'layout',
                            'label'   => __( 'Layout', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'grid',
                            'options' => array( 'grid' => esc_html__( 'Grid', 'loopkit' ), 'list' => esc_html__( 'List', 'loopkit' ) ),
                        ),
                        array( 'name' => 'columns', 'label' => __( 'Columns', 'loopkit' ), 'type' => 'number', 'responsive' => true, 'default' => 3, 'min' => 1, 'max' => 6, 'condition' => array( 'layout' => 'grid' ) ),
                        array( 'name' => 'show_image', 'label' => __( 'Image', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array(
                            'name'      => 'image_size',
                            'label'     => __( 'Image Size', 'loopkit' ),
                            'type'      => 'select',
                            'default'   => 'medium_large',
                            'options'   => array( 'thumbnail' => esc_html__( 'Thumbnail', 'loopkit' ), 'medium' => esc_html__( 'Medium', 'loopkit' ), 'medium_large' => esc_html__( 'Medium Large', 'loopkit' ), 'large' => esc_html__( 'Large', 'loopkit' ), 'full' => esc_html__( 'Full', 'loopkit' ) ),
                            'condition' => array( 'show_image' => 'yes' ),
                        ),
                        array( 'name' => 'show_title', 'label' => __( 'Title', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array(
                            'name'      => 'title_tag',
                            'label'     => __( 'Title HTML Tag', 'loopkit' ),
                            'type'      => 'select',
                            'default'   => 'h3',
                            'options'   => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'div', 'p' => 'p' ),
                            'condition' => array( 'show_title' => 'yes' ),
                        ),
                        array( 'name' => 'show_author', 'label' => __( 'Author', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'show_date', 'label' => __( 'Date', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'show_comments', 'label' => __( 'Comments', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                        array( 'name' => 'show_excerpt', 'label' => __( 'Excerpt', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'excerpt_length', 'label' => __( 'Excerpt Length (words)', 'loopkit' ), 'type' => 'number', 'default' => 20, 'min' => 1, 'max' => 200, 'condition' => array( 'show_excerpt' => 'yes' ) ),
                        array( 'name' => 'show_read_more', 'label' => __( 'Read More', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'read_more_text', 'label' => __( 'Read More Text', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Read More »', 'loopkit' ), 'condition' => array( 'show_read_more' => 'yes' ) ),
                    ),
                ),
                array(
                    'id'       => 'section_posts_query',
                    'title'    => __( 'Query', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'source',
                            'label'       => __( 'Source', 'loopkit' ),
                            'type'        => 'select',
                            'default'     => 'custom',
                            'options'     => array( 'custom' => esc_html__( 'Custom Query', 'loopkit' ), 'current' => esc_html__( 'Current Query', 'loopkit' ) ),
                            'description' => __( 'Current Query shows the posts of the archive, search or blog page being viewed (use it in Archive templates).', 'loopkit' ),
                        ),
                        array( 'name' => 'post_type', 'label' => __( 'Post Type', 'loopkit' ), 'type' => 'select', 'default' => 'post', 'options' => $this->post_type_options(), 'condition' => array( 'source' => 'custom' ) ),
                        array( 'name' => 'category', 'label' => __( 'Category', 'loopkit' ), 'type' => 'select', 'default' => '', 'options' => $this->category_options(), 'condition' => array( 'source' => 'custom', 'post_type' => 'post' ) ),
                        array( 'name' => 'posts_per_page', 'label' => __( 'Posts Per Page', 'loopkit' ), 'type' => 'number', 'default' => 6, 'min' => 1, 'max' => 100, 'condition' => array( 'source' => 'custom' ) ),
                        array(
                            'name'      => 'orderby',
                            'label'     => __( 'Order By', 'loopkit' ),
                            'type'      => 'select',
                            'default'   => 'date',
                            'options'   => array( 'date' => esc_html__( 'Date', 'loopkit' ), 'modified' => esc_html__( 'Last Modified', 'loopkit' ), 'title' => __( 'Title', 'loopkit' ), 'comment_count' => esc_html__( 'Comment Count', 'loopkit' ), 'menu_order' => esc_html__( 'Menu Order', 'loopkit' ), 'rand' => esc_html__( 'Random', 'loopkit' ) ),
                            'condition' => array( 'source' => 'custom' ),
                        ),
                        array( 'name' => 'query_order', 'label' => __( 'Order', 'loopkit' ), 'type' => 'select', 'default' => 'DESC', 'options' => array( 'DESC' => esc_html__( 'Descending', 'loopkit' ), 'ASC' => esc_html__( 'Ascending', 'loopkit' ) ), 'condition' => array( 'source' => 'custom' ) ),
                        array( 'name' => 'offset', 'label' => __( 'Offset', 'loopkit' ), 'type' => 'number', 'default' => 0, 'min' => 0, 'max' => 100, 'condition' => array( 'source' => 'custom' ) ),
                        array( 'name' => 'exclude_current', 'label' => __( 'Exclude Current Post', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes', 'condition' => array( 'source' => 'custom' ) ),
                        array( 'name' => 'no_posts_message', 'label' => __( 'Nothing Found Message', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'No posts found.', 'loopkit' ) ),
                    ),
                ),
                array(
                    'id'       => 'section_posts_pagination',
                    'title'    => __( 'Pagination', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'pagination',
                            'label'   => __( 'Pagination', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'none',
                            'options' => array(
                                'none'              => esc_html__( 'None', 'loopkit' ),
                                'numbers'           => esc_html__( 'Numbers', 'loopkit' ),
                                'prev_next'         => esc_html__( 'Previous / Next', 'loopkit' ),
                                'numbers_prev_next' => esc_html__( 'Numbers + Previous / Next', 'loopkit' ),
                            ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_posts_layout',
                    'title'    => __( 'Layout', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'column_gap', 'label' => __( 'Columns Gap', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'range' => array( 'min' => 0, 'max' => 100 ), 'units' => array( 'px' ) ),
                        array( 'name' => 'row_gap', 'label' => __( 'Rows Gap', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'range' => array( 'min' => 0, 'max' => 100 ), 'units' => array( 'px' ) ),
                    ),
                ),
                array(
                    'id'       => 'section_style_posts_card',
                    'title'    => __( 'Card', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        $color( 'card_bg', esc_html__( 'Background Color', 'loopkit' ) ),
                        array( 'name' => 'card_padding', 'label' => __( 'Content Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
                        array( 'name' => 'card_radius', 'label' => __( 'Border Radius', 'loopkit' ), 'type' => 'dimensions' ),
                        array( 'name' => 'image_height', 'label' => __( 'Image Height', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'range' => array( 'min' => 50, 'max' => 600 ), 'units' => array( 'px' ) ),
                    ),
                ),
                array(
                    'id'       => 'section_style_posts_text',
                    'title'    => __( 'Text', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        $color( 'title_color', esc_html__( 'Title Color', 'loopkit' ) ),
                        $color( 'title_hover_color', esc_html__( 'Title Hover Color', 'loopkit' ) ),
                        $typo( 'title_typography', esc_html__( 'Title Typography', 'loopkit' ) ),
                        $color( 'meta_color', esc_html__( 'Meta Color', 'loopkit' ) ),
                        $typo( 'meta_typography', esc_html__( 'Meta Typography', 'loopkit' ) ),
                        $color( 'excerpt_color', esc_html__( 'Excerpt Color', 'loopkit' ) ),
                        $typo( 'excerpt_typography', esc_html__( 'Excerpt Typography', 'loopkit' ) ),
                        $color( 'read_more_color', esc_html__( 'Read More Color', 'loopkit' ) ),
                        $typo( 'read_more_typography', esc_html__( 'Read More Typography', 'loopkit' ) ),
                    ),
                ),
                array(
                    'id'       => 'section_style_posts_pagination',
                    'title'    => __( 'Pagination', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array( 'name' => 'pagination_align', 'label' => __( 'Alignment', 'loopkit' ), 'type' => 'choose', 'options' => Loopkit_Widget_Post_Title::align_options( false ) ),
                        $color( 'pagination_color', esc_html__( 'Color', 'loopkit' ) ),
                        $color( 'pagination_active_color', esc_html__( 'Active Color', 'loopkit' ) ),
                        $typo( 'pagination_typography', esc_html__( 'Typography', 'loopkit' ) ),
                    ),
                ),
            ),
        );
    }

    /**
     * Current page number (archives use "paged", static pages use "page").
     *
     * @return int
     */
    public static function current_page() {
        return max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
    }

    /**
     * Pagination links. Same markup for the Posts widget and the Pagination widget.
     *
     * @param int    $current Current page.
     * @param int    $total   Total pages.
     * @param string $type    numbers | prev_next | numbers_prev_next.
     * @param string $class   Wrapper class.
     * @return string
     */
    public static function pagination_html( $current, $total, $type, $class ) {
        if ( $total < 2 || 'none' === $type ) {
            return '';
        }
        $prev_text = esc_html__( '« Previous', 'loopkit' );
        $next_text = esc_html__( 'Next »', 'loopkit' );
        $big       = 999999999;
        $base      = str_replace( (string) $big, '%#%', esc_url( get_pagenum_link( $big ) ) );

        if ( 'prev_next' === $type ) {
            $links = '';
            if ( $current > 1 ) {
                $links .= '<a class="prev page-numbers" href="' . esc_url( get_pagenum_link( $current - 1 ) ) . '">' . $prev_text . '</a>';
            }
            if ( $current < $total ) {
                $links .= '<a class="next page-numbers" href="' . esc_url( get_pagenum_link( $current + 1 ) ) . '">' . $next_text . '</a>';
            }
        } else {
            $links = paginate_links( array(
                'base'      => $base,
                'format'    => '',
                'current'   => $current,
                'total'     => $total,
                'prev_next' => ( 'numbers_prev_next' === $type ),
                'prev_text' => $prev_text,
                'next_text' => $next_text,
                'type'      => 'plain',
            ) );
        }
        return $links ? '<nav class="' . esc_attr( $class ) . '" aria-label="' . esc_attr__( 'Posts pagination', 'loopkit' ) . '">' . $links . '</nav>' : '';
    }

    /**
     * WP_Query args for the widget, or null to use the main query.
     *
     * @param array $settings    Settings.
     * @param bool  $editor_mode Editor canvas.
     * @return array|null
     */
    protected function query_args( $settings, $editor_mode ) {
        $paged = $editor_mode ? 1 : self::current_page();

        if ( 'current' === ( $settings['source'] ?? 'custom' ) ) {
            if ( ! $editor_mode ) {
                return null;
            }
            // Editor: rebuild the preview archive's query.
            $args = array(
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => (int) get_option( 'posts_per_page', 10 ),
            );
            $doc = isset( $_GET['loopkit_canvas'] ) ? absint( $_GET['loopkit_canvas'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
            if ( $doc && 'archive' === get_post_meta( $doc, '_loopkit_template_type', true ) && class_exists( 'Loopkit_Archive_Data' ) ) {
                $key = Loopkit_Archive_Data::get_preview_key( $doc );
                if ( preg_match( '/^category:(\d+)$/', $key, $m ) ) {
                    $args['cat'] = (int) $m[1];
                } elseif ( preg_match( '/^post_tag:(\d+)$/', $key, $m ) ) {
                    $args['tag_id'] = (int) $m[1];
                } elseif ( 'search' === $key ) {
                    $args['s'] = __( 'sample search', 'loopkit' );
                }
            }
            return $args;
        }

        $ppp    = max( 1, (int) ( $settings['posts_per_page'] ?? 6 ) );
        $offset = max( 0, (int) ( $settings['offset'] ?? 0 ) );
        $order  = ( 'ASC' === ( ( $settings['query_order'] ?? $settings['order'] ?? null ) ?? 'DESC' ) ) ? 'ASC' : 'DESC';
        $by     = $settings['orderby'] ?? 'date';
        if ( ! in_array( $by, array( 'date', 'modified', 'title', 'comment_count', 'menu_order', 'rand' ), true ) ) {
            $by = 'date';
        }

        $post_type = (string) ( $settings['post_type'] ?? 'post' );
        if ( ! post_type_exists( $post_type ) ) {
            $post_type = 'post';
        }

        $args = array(
            'post_type'           => $post_type,
            'post_status'         => 'publish',
            'posts_per_page'      => $ppp,
            'orderby'             => $by,
            'order'               => $order,
            'ignore_sticky_posts' => true,
            // WordPress ignores "paged" when "offset" is set, so page manually.
            'offset'              => $offset + ( $paged - 1 ) * $ppp,
            '_lk_base_offset'     => $offset,
        );
        if ( 'post' === $post_type && ! empty( $settings['category'] ) ) {
            $args['cat'] = (int) $settings['category'];
        }
        if ( $this->switcher_on( $settings, 'exclude_current', true ) ) {
            $current = $editor_mode && class_exists( 'Loopkit_Post_Data' ) ? Loopkit_Post_Data::get_render_post_id( true ) : ( is_singular() ? get_queried_object_id() : 0 );
            if ( $current ) {
                $args['post__not_in'] = array( (int) $current );
            }
        }
        return $args;
    }

    /**
     * One post card.
     *
     * @param WP_Post $p        Post.
     * @param array   $settings Settings.
     * @return string
     */
    private function card( $p, $settings ) {
        $url  = get_permalink( $p );
        $html = '<article class="lk-post-card">';

        if ( $this->switcher_on( $settings, 'show_image', true ) ) {
            $thumb = get_post_thumbnail_id( $p );
            $src   = $thumb ? wp_get_attachment_image_src( $thumb, $settings['image_size'] ?? 'medium_large' ) : false;
            if ( $src ) {
                $alt   = (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true );
                $html .= '<a class="lk-post-card-image" href="' . esc_url( $url ) . '"><img src="' . esc_url( $src[0] ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy"></a>';
            }
        }

        $html .= '<div class="lk-post-card-body">';
        if ( $this->switcher_on( $settings, 'show_title', true ) ) {
            $tag = $settings['title_tag'] ?? 'h3';
            if ( ! in_array( $tag, array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' ), true ) ) {
                $tag = 'h3';
            }
            $html .= '<' . $tag . ' class="lk-post-card-title"><a href="' . esc_url( $url ) . '">' . esc_html( Loopkit_Post_Data::plain_title( $p ) ) . '</a></' . $tag . '>';
        }

        $meta = '';
        if ( $this->switcher_on( $settings, 'show_author', true ) ) {
            $meta .= '<span class="lk-post-card-author">' . esc_html( Loopkit_Post_Data::plain( get_the_author_meta( 'display_name', $p->post_author ) ) ) . '</span>';
        }
        if ( $this->switcher_on( $settings, 'show_date', true ) ) {
            $meta .= '<span class="lk-post-card-date">' . esc_html( get_the_date( '', $p ) ) . '</span>';
        }
        if ( $this->switcher_on( $settings, 'show_comments', false ) ) {
            $meta .= '<span class="lk-post-card-comments">' . esc_html( Loopkit_Post_Data::comments_text( get_comments_number( $p ) ) ) . '</span>';
        }
        if ( '' !== $meta ) {
            $html .= '<div class="lk-post-card-meta">' . $meta . '</div>';
        }

        if ( $this->switcher_on( $settings, 'show_excerpt', true ) ) {
            $len  = max( 1, (int) ( $settings['excerpt_length'] ?? 20 ) );
            $text = has_excerpt( $p ) ? Loopkit_Post_Data::plain( $p->post_excerpt ) : Loopkit_Post_Data::plain( strip_shortcodes( function_exists( 'excerpt_remove_blocks' ) ? excerpt_remove_blocks( $p->post_content ) : $p->post_content ) );
            $text = Loopkit_Post_Data::trim_words( $text, $len );
            if ( '' !== $text ) {
                $html .= '<div class="lk-post-card-excerpt">' . esc_html( $text ) . '</div>';
            }
        }

        if ( $this->switcher_on( $settings, 'show_read_more', true ) ) {
            $label = ( isset( $settings['read_more_text'] ) && '' !== $settings['read_more_text'] ) ? $settings['read_more_text'] : __( 'Read More »', 'loopkit' );
            $html .= '<a class="lk-post-card-read-more" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        }

        return $html . '</div></article>';
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $args = $this->query_args( $settings, $editor_mode );

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

        $layout = ( 'list' === ( $settings['layout'] ?? 'grid' ) ) ? 'list' : 'grid';
        $html   = '<div class="lk-posts lk-posts-' . $layout . '">';

        if ( empty( $query->posts ) ) {
            $msg   = ( isset( $settings['no_posts_message'] ) && '' !== $settings['no_posts_message'] ) ? $settings['no_posts_message'] : __( 'No posts found.', 'loopkit' );
            $html .= '<div class="lk-posts-empty">' . esc_html( $msg ) . '</div>';
        } else {
            $html .= '<div class="lk-posts-items">';
            foreach ( $query->posts as $p ) {
                $html .= $this->card( $p, $settings );
            }
            $html .= '</div>';
        }

        $type = $settings['pagination'] ?? 'none';
        if ( $editor_mode && 'none' !== $type && $total < 2 ) {
            $total = 3; // editor: show sample links so pagination can be styled
        }
        $html .= self::pagination_html( $current, $total, $type, 'lk-posts-pagination' );

        return $html . '</div>';
    }
}
