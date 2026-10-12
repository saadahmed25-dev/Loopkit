<?php
/**
 * Loopkit Theme Builder.
 * Handles Display Conditions, Header & Footer replacement on block and classic themes,
 * caching, and condition evaluation.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Theme_Builder {

    /**
     * Cache option key.
     */
    const CACHE_OPTION = 'loopkit_theme_builder_cache';

    /**
     * Template types that use Display Conditions and are resolved per request.
     */
    const LOCATIONS = array( 'header', 'footer', 'single', 'archive' );

    /**
     * Singleton instance.
     *
     * @var Loopkit_Theme_Builder|null
     */
    private static $instance = null;

    /**
     * Resolved templates for current request.
     *
     * @var array
     */
    private $resolved_locations = array();

    /**
     * Rendered locations for current request.
     *
     * @var array
     */
    private $rendered_locations = array();

    /**
     * Get singleton instance.
     *
     * @return Loopkit_Theme_Builder
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Singleton instance alias.
     *
     * @return Loopkit_Theme_Builder
     */
    public static function instance() {
        return self::get_instance();
    }

    /**
     * Check if a location has already been rendered for this request.
     *
     * @param string $location 'header' or 'footer'.
     * @return bool
     */
    public function is_location_rendered( $location ) {
        return ! empty( $this->rendered_locations[ $location ] );
    }

    /**
     * Mark a location as rendered for this request.
     *
     * @param string $location 'header' or 'footer'.
     */
    public function mark_location_rendered( $location ) {
        $this->rendered_locations[ $location ] = true;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        // Block themes (Twenty Twenty-Five, etc.): replace template-part blocks
        add_filter( 'render_block', array( $this, 'filter_template_part_render' ), 10, 2 );

        // Classic themes: replace header.php and footer.php
        add_action( 'get_header', array( $this, 'handle_classic_header' ) );
        add_action( 'get_footer', array( $this, 'handle_classic_footer' ) );

        // Debug HTML comment for logged-in administrators
        add_action( 'wp_head', array( $this, 'output_debug_comment' ), 1 );

        // Enqueue template CSS and fonts early on front end
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_active_template_assets' ), 20 );

        // Cache invalidation and rebuilding on template changes
        add_action( 'save_post_loopkit_template', array( $this, 'on_template_save' ), 20, 2 );
        // Use the "after" hooks so the post status has already changed when the cache is rebuilt.
        add_action( 'trashed_post', array( $this, 'on_template_delete' ) );
        add_action( 'untrashed_post', array( $this, 'on_template_delete' ) );
        add_action( 'after_delete_post', array( $this, 'on_template_deleted' ), 10, 2 );

        // Enforce that Header/Footer templates cannot be published without conditions
        add_filter( 'wp_insert_post_data', array( $this, 'enforce_template_conditions' ), 10, 2 );

        // Single templates: replace the theme's single post/page template on the front end
        add_filter( 'template_include', array( $this, 'apply_single_template' ), 100 );
        add_filter( 'body_class', array( $this, 'add_single_body_class' ) );
        add_action( 'init', array( $this, 'register_template_block' ) );

        // AJAX handlers
        add_action( 'wp_ajax_loopkit_get_conditions', array( $this, 'ajax_get_conditions' ) );
        add_action( 'wp_ajax_loopkit_save_conditions', array( $this, 'ajax_save_conditions' ) );
        add_action( 'wp_ajax_loopkit_search_condition_items', array( $this, 'ajax_search_items' ) );
    }

    /**
     * Rebuild the conditions cache.
     *
     * @return array The rebuilt cache data.
     */
    public static function rebuild_cache() {
        $cache = array();

        foreach ( self::LOCATIONS as $location ) {
            $cache[ $location ] = array();

            $posts = get_posts( array(
                'post_type'      => 'loopkit_template',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_key'       => '_loopkit_template_type',
                'meta_value'     => $location,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            ) );

            foreach ( $posts as $post ) {
                $conditions = get_post_meta( $post->ID, '_loopkit_conditions', true );
                if ( ! empty( $conditions ) && is_array( $conditions ) ) {
                    $cache[ $location ][] = array(
                        'id'         => $post->ID,
                        'title'      => $post->post_title,
                        'conditions' => $conditions,
                        'modified'   => strtotime( $post->post_modified ),
                    );
                }
            }
        }

        update_option( self::CACHE_OPTION, $cache );
        return $cache;
    }

    /**
     * Get cached templates.
     *
     * @return array
     */
    public static function get_cached_templates() {
        $cache = get_option( self::CACHE_OPTION );
        // Rebuild if missing, or if it predates a template type (e.g. caches built before Single existed).
        if ( false === $cache || ! is_array( $cache ) || array_diff( self::LOCATIONS, array_keys( $cache ) ) ) {
            $cache = self::rebuild_cache();
        }
        return $cache;
    }

    /**
     * Hook: On template save, rebuild cache.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function on_template_save( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        $tpl_type = get_post_meta( $post_id, '_loopkit_template_type', true );
        if ( in_array( $tpl_type, self::LOCATIONS, true ) ) {
            self::rebuild_cache();
        }
    }

    /**
     * Hook: On template delete/trash, rebuild cache.
     *
     * @param int $post_id Post ID.
     */
    public function on_template_delete( $post_id ) {
        if ( 'loopkit_template' === get_post_type( $post_id ) ) {
            self::rebuild_cache();
        }
    }

    /**
     * Hook: After a template is permanently deleted, rebuild cache.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Deleted post object.
     */
    public function on_template_deleted( $post_id, $post = null ) {
        if ( $post && 'loopkit_template' === $post->post_type ) {
            self::rebuild_cache();
        }
    }

    /**
     * Enforce that Header/Footer templates cannot be published without conditions.
     *
     * @param array $data    An array of slashed, sanitized, and processed post data.
     * @param array $postarr An array of sanitized (and slashed) but otherwise unmodified post data.
     * @return array
     */
    public function enforce_template_conditions( $data, $postarr ) {
        if ( ( $data['post_type'] ?? '' ) !== 'loopkit_template' ) {
            return $data;
        }

        $post_id = ! empty( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
        if ( ! $post_id ) {
            return $data;
        }

        $tpl_type = get_post_meta( $post_id, '_loopkit_template_type', true );
        if ( ! in_array( $tpl_type, self::LOCATIONS, true ) ) {
            return $data;
        }

        // If target status is publish
        if ( 'publish' === ( $data['post_status'] ?? '' ) ) {
            $conditions = get_post_meta( $post_id, '_loopkit_conditions', true );

            // Check if conditions are being submitted in $_POST
            if ( isset( $_POST['loopkit_conditions'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
                $raw = wp_unslash( $_POST['loopkit_conditions'] ); // phpcs:ignore WordPress.Security.NonceVerification
                $conditions = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
            }

            if ( empty( $conditions ) || ! is_array( $conditions ) ) {
                // Force status to draft!
                $data['post_status'] = 'draft';
            }
        }

        return $data;
    }

    /**
     * Get the active template for a location (header or footer).
     * Evaluates conditions and returns the winning template ID and match info.
     *
     * @param string $location Location: 'header' or 'footer'.
     * @return array|null Array with 'id', 'title', 'reason', 'score' or null if none.
     */
    public function get_active_template_for_location( $location ) {
        if ( array_key_exists( $location, $this->resolved_locations ) ) {
            return $this->resolved_locations[ $location ];
        }

        // Conditional tags (is_front_page, is_singular, ...) are only reliable once the
        // main query has run. If a theme asks earlier, resolve without memoizing so the
        // real result can still be computed later in the request.
        if ( ! did_action( 'wp' ) && ! is_admin() ) {
            return $this->resolve_location_uncached( $location );
        }

        $result = $this->resolve_location_uncached( $location );
        $this->resolved_locations[ $location ] = $result;
        return $result;
    }

    /**
     * Resolve the winning template for a location without touching the per-request memo.
     *
     * @param string $location Location: 'header' or 'footer'.
     * @return array|null
     */
    private function resolve_location_uncached( $location ) {

        // 1. If inside Loopkit editor canvas iframe
        if ( isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $canvas_id = absint( $_GET['loopkit_canvas'] ); // phpcs:ignore WordPress.Security.NonceVerification
            $canvas_post_type = get_post_type( $canvas_id );

            if ( 'loopkit_template' === $canvas_post_type ) {
                $canvas_tpl_type = get_post_meta( $canvas_id, '_loopkit_template_type', true );
                // A template is NEVER rendered inside itself!
                if ( $canvas_tpl_type === $location ) {
                    return null;
                }
                // Also never render header/footer inside section/container template canvases
                if ( in_array( $canvas_tpl_type, array( 'section', 'container' ), true ) ) {
                    return null;
                }
            }
        }

        // 2. Check if current page uses Loopkit Canvas layout (shows no header and no footer).
        // Only singular requests have a page layout: on archives the queried object ID is a term/user ID.
        $current_id = ( is_singular() || isset( $_GET['loopkit_canvas'] ) ) ? ( get_queried_object_id() ?: get_the_ID() ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! $current_id && isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $current_id = absint( $_GET['loopkit_canvas'] ); // phpcs:ignore WordPress.Security.NonceVerification
        }
        if ( $current_id ) {
            $layout = Loopkit_Templates::get_page_layout( $current_id );
            if ( 'loopkit-canvas' === $layout ) {
                return null;
            }
        }

        // 3. Get candidates from cache
        $cache = self::get_cached_templates();
        $candidates = $cache[ $location ] ?? array();

        if ( empty( $candidates ) ) {
            return null;
        }

        $matching_candidates = array();

        foreach ( $candidates as $cand ) {
            $eval = $this->evaluate_conditions( $cand['conditions'] );
            if ( $eval['matched'] ) {
                $matching_candidates[] = array(
                    'id'       => $cand['id'],
                    'title'    => $cand['title'],
                    'reason'   => $eval['reason'],
                    'score'    => $eval['score'],
                    'modified' => $cand['modified'],
                );
            }
        }

        if ( empty( $matching_candidates ) ) {
            return null;
        }

        // Sort by specificity score DESC, then by modified timestamp DESC
        usort( $matching_candidates, function( $a, $b ) {
            if ( $a['score'] !== $b['score'] ) {
                return $b['score'] - $a['score'];
            }
            return $b['modified'] - $a['modified'];
        } );

        return $matching_candidates[0];
    }

    /**
     * Evaluate a list of condition rows against current request.
     *
     * @param array $conditions Condition rows array.
     * @return array Array with 'matched' (bool), 'score' (int), 'reason' (string).
     */
    public function evaluate_conditions( $conditions ) {
        if ( empty( $conditions ) || ! is_array( $conditions ) ) {
            return array( 'matched' => false, 'score' => 0, 'reason' => 'No conditions' );
        }

        $has_include_match = false;
        $highest_score     = 0;
        $match_reason      = '';

        // Check each condition row
        foreach ( $conditions as $row ) {
            $type = $row['type'] ?? 'include';
            $is_match = $this->match_single_condition( $row );

            if ( 'exclude' === $type && $is_match ) {
                // An exclude match immediately disqualifies this template!
                return array(
                    'matched' => false,
                    'score'   => 0,
                    'reason'  => 'Excluded by: ' . ( $row['name'] ?? '' ) . ' / ' . ( $row['sub'] ?? '' ),
                );
            }

            if ( 'include' === $type && $is_match ) {
                $has_include_match = true;
                $score = $this->calculate_specificity( $row );
                if ( $score >= $highest_score ) {
                    $highest_score = $score;
                    $match_reason  = $this->format_row_label( $row );
                }
            }
        }

        return array(
            'matched' => $has_include_match,
            'score'   => $highest_score,
            'reason'  => $match_reason ?: 'Include matched',
        );
    }

    /**
     * Check if a single condition row matches current request.
     *
     * @param array $row Condition row.
     * @return bool
     */
    private function match_single_condition( $row ) {
        $name = $row['name'] ?? 'general';
        $sub  = $row['sub'] ?? 'all';
        $id   = ! empty( $row['id'] ) ? absint( $row['id'] ) : ( ! empty( $row['item'] ) ? absint( $row['item'] ) : 0 );

        // Entire site matches every request
        if ( 'general' === $name ) {
            return true;
        }

        // Singular
        if ( 'singular' === $name ) {
            if ( 'all' === $sub ) {
                return is_singular();
            }

            if ( 'front_page' === $sub ) {
                return is_front_page();
            }

            if ( '404' === $sub ) {
                return is_404();
            }

            if ( 'page' === $sub ) {
                if ( ! is_page() && ! ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) ) {
                    return false;
                }
                if ( ! $id ) {
                    return true;
                }
                $current_id = get_queried_object_id() ?: (int) get_option( 'page_on_front' );
                return ( (int) $current_id === (int) $id );
            }

            if ( 'post' === $sub ) {
                if ( ! is_single() || 'post' !== get_post_type() ) {
                    return false;
                }
                if ( ! $id ) {
                    return true;
                }
                return ( (int) get_queried_object_id() === (int) $id );
            }

            // Any other registered post type: products, and anything the Post Types module makes.
            if ( post_type_exists( $sub ) ) {
                if ( ! is_singular( $sub ) ) {
                    return false;
                }
                if ( ! $id ) {
                    return true;
                }
                return ( (int) get_queried_object_id() === (int) $id );
            }

            return false;
        }

        // Archives
        if ( 'archive' === $name ) {
            if ( 'all' === $sub ) {
                return ( is_archive() || is_home() || is_search() );
            }

            if ( 'posts_archive' === $sub ) {
                return is_home();
            }

            if ( 'category' === $sub ) {
                if ( ! is_category() ) {
                    return false;
                }
                if ( ! $id ) {
                    return true;
                }
                return ( (int) get_queried_object_id() === (int) $id );
            }

            if ( 'post_tag' === $sub ) {
                if ( ! is_tag() ) {
                    return false;
                }
                if ( ! $id ) {
                    return true;
                }
                return ( (int) get_queried_object_id() === (int) $id );
            }

            if ( 'author' === $sub ) {
                if ( ! is_author() ) {
                    return false;
                }
                if ( ! $id ) {
                    return true;
                }
                return ( (int) get_queried_object_id() === (int) $id );
            }

            if ( 'date' === $sub ) {
                return is_date();
            }

            if ( 'search' === $sub ) {
                return is_search();
            }


            // A custom post type archive (shop, projects…) or a custom taxonomy
            if ( post_type_exists( $sub ) ) {
                return is_post_type_archive( $sub );
            }
            if ( taxonomy_exists( $sub ) ) {
                if ( ! is_tax( $sub ) ) {
                    return false;
                }
                if ( ! $id ) {
                    return true;
                }
                return ( (int) get_queried_object_id() === (int) $id );
            }

            return false;
        }

        return false;
    }

    /**
     * Calculate specificity score for a condition row.
     * Specific Item (40) > Subgroup (30) > Section (20) > Entire Site (10).
     *
     * @param array $row Condition row.
     * @return int Specificity score.
     */
    private function calculate_specificity( $row ) {
        $name = $row['name'] ?? 'general';
        $sub  = $row['sub'] ?? 'all';
        $id   = ! empty( $row['id'] ) ? absint( $row['id'] ) : ( ! empty( $row['item'] ) ? absint( $row['item'] ) : 0 );

        if ( 'general' === $name ) {
            return 10;
        }

        if ( $id > 0 ) {
            return 40; // Specific item (e.g. Page ID 7)
        }

        if ( in_array( $sub, array( 'page', 'post', 'category', 'post_tag', 'author' ), true ) ) {
            return 30; // Subgroup (e.g. all Pages, all Posts)
        }

        return 20; // Section level (e.g. All Singular, All Archives, Front Page, 404)
    }

    /**
     * Format a readable label for a condition row.
     *
     * @param array $row Condition row.
     * @return string
     */
    private function format_row_label( $row ) {
        $name = $row['name'] ?? 'general';
        $sub  = $row['sub'] ?? 'all';
        $id   = ! empty( $row['id'] ) ? absint( $row['id'] ) : ( ! empty( $row['item'] ) ? absint( $row['item'] ) : 0 );

        if ( 'general' === $name ) {
            return 'Entire Site';
        }

        if ( 'singular' === $name ) {
            if ( 'front_page' === $sub ) return 'Front Page';
            if ( '404' === $sub ) return '404 Page';
            if ( 'all' === $sub ) return 'All Singular';
            if ( 'page' === $sub ) {
                if ( $id ) {
                    $p = get_post( $id );
                    return 'Pages: ' . ( $p ? $p->post_title : "#$id" );
                }
                return 'Pages';
            }
            if ( 'post' === $sub ) {
                if ( $id ) {
                    $p = get_post( $id );
                    return 'Posts: ' . ( $p ? $p->post_title : "#$id" );
                }
                return 'Posts';
            }
        }

        if ( 'archive' === $name ) {
            if ( 'all' === $sub ) return 'All Archives';
            if ( 'posts_archive' === $sub ) return 'Posts Archive';
            if ( 'category' === $sub ) {
                if ( $id ) {
                    $t = get_term( $id );
                    return 'Categories: ' . ( $t && ! is_wp_error( $t ) ? $t->name : "#$id" );
                }
                return 'Categories';
            }
            if ( 'post_tag' === $sub ) return 'Tags';
            if ( 'author' === $sub ) return 'Author Archive';
            if ( 'date' === $sub ) return 'Date Archive';
            if ( 'search' === $sub ) return 'Search Results';
        }

        return ucfirst( $name );
    }

    /**
     * Block themes (Twenty Twenty-Five): Filter template-part render.
     * Replaces header and footer core/template-part blocks with the matching Loopkit template.
     *
     * @param string $block_content The block content.
     * @param array  $block         The full block, including name and attributes.
     * @return string Modified content.
     */
    public function filter_template_part_render( $block_content, $block ) {
        if ( ( $block['blockName'] ?? '' ) !== 'core/template-part' ) {
            return $block_content;
        }

        $area = $this->detect_template_part_area( $block );
        if ( ! in_array( $area, array( 'header', 'footer' ), true ) ) {
            return $block_content;
        }

        // Pages using the Loopkit Canvas layout still show no header or footer
        $current_id = get_queried_object_id() ?: get_the_ID();
        if ( ! $current_id && isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $current_id = absint( $_GET['loopkit_canvas'] ); // phpcs:ignore WordPress.Security.NonceVerification
        }
        if ( $current_id && 'loopkit-canvas' === Loopkit_Templates::get_page_layout( $current_id ) ) {
            return '';
        }

        // Inside template canvas: never render a template inside its own canvas or section/container canvases
        if ( isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $canvas_id = absint( $_GET['loopkit_canvas'] ); // phpcs:ignore WordPress.Security.NonceVerification
            if ( 'loopkit_template' === get_post_type( $canvas_id ) ) {
                $canvas_tpl_type = get_post_meta( $canvas_id, '_loopkit_template_type', true );
                if ( $canvas_tpl_type === $area || in_array( $canvas_tpl_type, array( 'section', 'container' ), true ) ) {
                    return '';
                }
            }
        }

        if ( 'header' === $area ) {
            $winner = $this->get_active_template_for_location( 'header' );
            if ( $winner && ! empty( $winner['id'] ) ) {
                if ( ! empty( $this->rendered_locations['header'] ) ) {
                    return ''; // Already rendered, prevent duplicate
                }
                $this->rendered_locations['header'] = true;
                return $this->render_location( 'header', $winner['id'] );
            }
        } elseif ( 'footer' === $area ) {
            $winner = $this->get_active_template_for_location( 'footer' );
            if ( $winner && ! empty( $winner['id'] ) ) {
                if ( ! empty( $this->rendered_locations['footer'] ) ) {
                    return ''; // Already rendered, prevent duplicate
                }
                $this->rendered_locations['footer'] = true;
                return $this->render_location( 'footer', $winner['id'] );
            }
        }

        return $block_content;
    }

    /**
     * Detect the area ('header' or 'footer') of a core/template-part block.
     * Resolves via get_block_template with heuristics for slugs and tagNames.
     *
     * @param array $block Block array.
     * @return string 'header', 'footer', or empty string.
     */
    private function detect_template_part_area( $block ) {
        $attrs = $block['attrs'] ?? array();
        $area  = $attrs['area'] ?? '';
        $slug  = $attrs['slug'] ?? '';
        $theme = $attrs['theme'] ?? get_stylesheet();

        // 1. Direct attribute
        if ( 'header' === $area || 'footer' === $area ) {
            return $area;
        }

        // 2. Resolve template part object from WP block template registry
        if ( ! empty( $slug ) && function_exists( 'get_block_template' ) ) {
            $tpl = get_block_template( $theme . '//' . $slug, 'wp_template_part' );
            if ( $tpl && ! empty( $tpl->area ) ) {
                if ( 'header' === $tpl->area || 'footer' === $tpl->area ) {
                    return $tpl->area;
                }
            }
        }

        // 3. Fallback: Slug contains header or footer
        if ( ! empty( $slug ) ) {
            if ( false !== strpos( $slug, 'header' ) ) {
                return 'header';
            }
            if ( false !== strpos( $slug, 'footer' ) ) {
                return 'footer';
            }
        }

        // 4. Fallback: tagName attribute
        $tag_name = strtolower( $attrs['tagName'] ?? '' );
        if ( 'header' === $tag_name ) {
            return 'header';
        }
        if ( 'footer' === $tag_name ) {
            return 'footer';
        }

        return '';
    }

    /**
     * Classic themes: Replace header.php output.
     *
     * @param string|null $name The name of the specialized header.
     */
    public function handle_classic_header( $name ) {
        // If block theme, do nothing
        if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
            return;
        }

        $winner = $this->get_active_template_for_location( 'header' );
        if ( ! $winner || empty( $winner['id'] ) || $this->is_location_rendered( 'header' ) ) {
            return;
        }

        // The Loopkit companion theme renders the header itself in header.php (after wp_body_open()).
        if ( current_theme_supports( 'loopkit-theme' ) ) {
            return;
        }

        // Any other classic theme: its header.php prints its own header, so printing ours as well
        // showed two headers. Print the document head, <body> and the Loopkit header ourselves,
        // then load the theme's header.php once into a discarded buffer (require_once, so
        // get_header() will not load it again). Same approach as handle_classic_footer().
        $this->mark_location_rendered( 'header' );
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
        wp_body_open();
        echo $this->render_location( 'header', $winner['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        remove_all_actions( 'wp_head' );
        remove_all_actions( 'wp_body_open' );

        $templates = array();
        $name      = (string) $name;
        if ( '' !== $name ) {
            $templates[] = 'header-' . $name . '.php';
        }
        $templates[] = 'header.php';

        ob_start();
        locate_template( $templates, true, true );
        ob_end_clean();
    }

    /**
     * Classic themes: Replace footer.php output.
     *
     * @param string|null $name The name of the specialized footer.
     */
    public function handle_classic_footer( $name ) {
        if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
            return;
        }

        $winner = $this->get_active_template_for_location( 'footer' );
        if ( ! $winner || empty( $winner['id'] ) ) {
            return;
        }

        if ( $this->is_location_rendered( 'footer' ) ) {
            return;
        }

        // The Loopkit companion theme renders the footer itself in footer.php, after it
        // closes </main>. Printing here (get_footer fires BEFORE footer.php loads) put the
        // footer inside <main>, which was the placement bug.
        if ( current_theme_supports( 'loopkit-theme' ) ) {
            return;
        }

        // Any other classic theme: print the Loopkit footer, wp_footer() and the closing
        // tags ourselves, then load the theme's footer.php into a discarded buffer so its
        // own footer never shows. It is loaded with require_once, so when get_footer()
        // tries to load it again right after this hook, nothing is printed twice.
        $this->mark_location_rendered( 'footer' );
        echo $this->render_location( 'footer', $winner['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        wp_footer();
        echo "\n</body>\n</html>\n";

        remove_all_actions( 'wp_footer' );

        $templates = array();
        $name      = (string) $name;
        if ( '' !== $name ) {
            $templates[] = 'footer-' . $name . '.php';
        }
        $templates[] = 'footer.php';

        ob_start();
        locate_template( $templates, true, true );
        ob_end_clean();
    }

    /**
     * Render the Header or Footer template markup and styles.
     *
     * @param string $location    'header' or 'footer'.
     * @param int    $template_id Template post ID.
     * @return string HTML output.
     */
    public function render_location( $location, $template_id ) {
        $post = get_post( $template_id );
        if ( ! $post || 'loopkit_template' !== $post->post_type ) {
            return '';
        }

        $raw_elements = get_post_meta( $template_id, '_loopkit_data', true );
        $elements     = $raw_elements ? json_decode( $raw_elements, true ) : array();
        if ( ! is_array( $elements ) ) {
            $elements = array();
        }

        // Root-level widgets inside template MUST be wrapped in a Section
        $elements = Loopkit_Frontend::ensure_sections( $elements );

        // Wrapper tag from Template Settings
        $page_settings = get_post_meta( $template_id, '_loopkit_page_settings', true );
        $default_tag  = in_array( $location, array( 'header', 'footer' ), true ) ? $location : 'div';
        $tag          = ! empty( $page_settings['wrapper_tag'] ) ? sanitize_key( $page_settings['wrapper_tag'] ) : $default_tag;
        $allowed_tags = array( 'header', 'footer', 'div', 'section', 'aside', 'nav', 'main', 'article' );
        if ( ! in_array( $tag, $allowed_tags, true ) ) {
            $tag = $default_tag;
        }

        // Get CSS: print inline with the block to guarantee zero FOUC on block themes
        $css = get_post_meta( $template_id, '_loopkit_css', true );
        if ( empty( $css ) && class_exists( 'Loopkit_REST_API' ) ) {
            // Generate CSS if not yet cached
            $css_file = WP_CONTENT_DIR . '/uploads/loopkit/css/post-' . $template_id . '.css';
            if ( ! file_exists( $css_file ) ) {
                /*
                 * The cached file is gone and there is no meta to fall back on, so
                 * without this the template renders with no CSS at all. Rebuild it
                 * from the stored layout rather than printing nothing: a wiped
                 * uploads folder, a migration or a staging copy without uploads
                 * used to strip the design off every template on the site, with no
                 * way back short of re-saving each one by hand.
                 */
                Loopkit_REST_API::rebuild_css_file( $template_id );
            }
            if ( file_exists( $css_file ) ) {
                $css = file_get_contents( $css_file );
            }
            if ( empty( $css ) ) {
                $css = get_post_meta( $template_id, '_loopkit_css', true );
            }
        }

        $style_tag = '';
        if ( ! empty( $css ) ) {
            $style_tag = '<style id="loopkit-template-css-' . esc_attr( $template_id ) . '">' . $css . '</style>' . "\n";
        }

        $rendered_elements = Loopkit_Frontend::render_elements( $elements, false );

        // Output matching specification:
        // <header class="loopkit loopkit-{templateID} loopkit-location-header" data-loopkit-type="header">...</header>
        $classes = 'loopkit loopkit-' . esc_attr( $template_id ) . ' loopkit-location-' . esc_attr( $location );
        $html  = $style_tag;

        // If inside page editor canvas, wrap with Edit Header/Footer indicator
        $is_page_canvas = isset( $_GET['loopkit_canvas'] ) && (int) $_GET['loopkit_canvas'] !== (int) $template_id; // phpcs:ignore WordPress.Security.NonceVerification
        if ( $is_page_canvas ) {
            $edit_url = admin_url( 'post.php?post=' . $template_id . '&action=loopkit' );
            $label = ( 'header' === $location ? esc_html__( 'Edit Header', 'loopkit' ) : esc_html__( 'Edit Footer', 'loopkit' ) );
            $html .= '<div class="lk-global-part-wrap lk-global-' . esc_attr( $location ) . '-wrap" data-template-id="' . esc_attr( $template_id ) . '" data-edit-url="' . esc_url( $edit_url ) . '">';
            $html .= '  <div class="lk-global-part-tab"><span class="dashicons dashicons-edit"></span> ' . $label . '</div>';
        }

        $html .= '<' . $tag . ' class="' . esc_attr( $classes ) . '" data-loopkit-type="' . esc_attr( $location ) . '">';
        $html .= $rendered_elements;
        $html .= '</' . $tag . '>';

        if ( $is_page_canvas ) {
            $html .= '</div>'; // .lk-global-part-wrap
        }

        return $html;
    }

    /**
     * Enqueue CSS file and Google Fonts for the active header & footer templates.
     */
    public function enqueue_active_template_assets() {
        if ( is_admin() || isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $active_header = $this->get_active_template_for_location( 'header' );
        if ( $active_header && ! empty( $active_header['id'] ) ) {
            $this->enqueue_template_file( $active_header['id'] );
        }

        $active_footer = $this->get_active_template_for_location( 'footer' );
        if ( $active_footer && ! empty( $active_footer['id'] ) ) {
            $this->enqueue_template_file( $active_footer['id'] );
        }

        $active_single = $this->get_single_template_for_request();
        if ( $active_single && ! empty( $active_single['id'] ) ) {
            $this->enqueue_template_file( $active_single['id'] );
        }

        $active_archive = $this->get_archive_template_for_request();
        if ( $active_archive && ! empty( $active_archive['id'] ) ) {
            $this->enqueue_template_file( $active_archive['id'] );
        }
    }

    /**
     * The Archive template that should replace the current request, or null.
     * Archives, the blog posts index and search results; never in wp-admin or the editor canvas.
     *
     * @return array|null
     */
    public function get_archive_template_for_request() {
        if ( is_admin() || isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return null;
        }
        if ( ! did_action( 'wp' ) || is_singular() || is_404() ) {
            return null;
        }
        if ( ! ( is_archive() || is_home() || is_search() ) ) {
            return null;
        }
        $winner = $this->get_active_template_for_location( 'archive' );
        return ( $winner && ! empty( $winner['id'] ) ) ? $winner : null;
    }

    /**
     * The Single template that should replace the current request, or null.
     * Only for real front-end singular requests: never in the editor canvas, never in
     * wp-admin, and never for Loopkit's own template posts.
     *
     * @return array|null Winner array ('id', 'title', 'reason', 'score') or null.
     */
    public function get_single_template_for_request() {
        if ( is_admin() || isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return null;
        }
        if ( ! did_action( 'wp' ) || ! is_singular() ) {
            return null;
        }
        if ( 'loopkit_template' === get_post_type( get_queried_object_id() ) ) {
            return null;
        }

        $winner = $this->get_active_template_for_location( 'single' );
        return ( $winner && ! empty( $winner['id'] ) ) ? $winner : null;
    }

    /**
     * template_include: swap in the Loopkit Single template when one matches.
     * Runs after Loopkit_Templates (priority 99).
     *
     * @param string $template Resolved template path.
     * @return string
     */
    public function apply_single_template( $template ) {
        $location = 'single';
        $winner   = $this->get_single_template_for_request();
        if ( ! $winner ) {
            $location = 'archive';
            $winner   = $this->get_archive_template_for_request();
        }
        if ( ! $winner ) {
            return $template;
        }

        // Block themes: theme header part + Single template + theme footer part.
        // The header/footer parts are still replaced by Loopkit Header/Footer templates via render_block.
        if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
            global $_wp_current_template_id, $_wp_current_template_content;

            $canvas_file = ABSPATH . WPINC . '/template-canvas.php';
            if ( ! file_exists( $canvas_file ) ) {
                return $template;
            }

            $_wp_current_template_id      = get_stylesheet() . '//loopkit-' . $location;
            $_wp_current_template_content = '<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->' . "\n"
                . '<!-- wp:group {"tagName":"main","layout":{"type":"default"}} -->' . "\n"
                . '<main class="wp-block-group"><!-- wp:loopkit/template {"location":"' . $location . '"} /--></main>' . "\n"
                . '<!-- /wp:group -->' . "\n"
                . '<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->';

            return $canvas_file;
        }

        // Classic themes: theme header + Single/Archive template + theme footer.
        $file = LOOPKIT_PLUGIN_DIR . 'templates/' . $location . '.php';
        return file_exists( $file ) ? $file : $template;
    }

    /**
     * Add a body class when a Single template is applied.
     *
     * @param array $classes Body classes.
     * @return array
     */
    public function add_single_body_class( $classes ) {
        $winner = $this->get_single_template_for_request();
        if ( $winner ) {
            $classes[] = 'loopkit-single-template';
            $classes[] = 'loopkit-single-template-' . (int) $winner['id'];
        }
        $archive = $this->get_archive_template_for_request();
        if ( $archive ) {
            $classes[] = 'loopkit-archive-template';
            $classes[] = 'loopkit-archive-template-' . (int) $archive['id'];
        }
        return $classes;
    }

    /**
     * Register the server-rendered block that prints a Loopkit template inside block themes.
     * Not shown in the block inserter; only used by apply_single_template().
     */
    public function register_template_block() {
        if ( ! function_exists( 'register_block_type' ) || ( class_exists( 'WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( 'loopkit/template' ) ) ) {
            return;
        }

        register_block_type( 'loopkit/template', array(
            'attributes'      => array(
                'location' => array(
                    'type'    => 'string',
                    'default' => 'single',
                ),
            ),
            'supports'        => array(
                'inserter' => false,
                'html'     => false,
            ),
            'render_callback' => function( $attributes ) {
                $location = sanitize_key( $attributes['location'] ?? 'single' );
                if ( ! in_array( $location, array( 'single', 'archive' ), true ) || ! class_exists( 'Loopkit_Templates' ) ) {
                    return '';
                }
                return Loopkit_Templates::render_template( $location );
            },
        ) );
    }

    /**
     * Enqueue single template CSS file.
     *
     * @param int $template_id Template post ID.
     */
    private function enqueue_template_file( $template_id ) {
        $css_file = WP_CONTENT_DIR . '/uploads/loopkit/css/post-' . $template_id . '.css';
        if ( file_exists( $css_file ) ) {
            $css_url = content_url( '/uploads/loopkit/css/post-' . $template_id . '.css' );
            wp_enqueue_style(
                'loopkit-template-' . $template_id,
                $css_url,
                array( 'loopkit-frontend' ),
                filemtime( $css_file )
            );
        }
    }

    /**
     * Output debug HTML comment for logged-in administrators only.
     */
    public function output_debug_comment() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['loopkit_canvas'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $header_match = $this->get_active_template_for_location( 'header' );
        $footer_match = $this->get_active_template_for_location( 'footer' );

        $header_str = $header_match
            ? "template {$header_match['id']} ({$header_match['reason']})"
            : $this->get_no_match_reason( 'header' );

        $footer_str = $footer_match
            ? "template {$footer_match['id']} ({$footer_match['reason']})"
            : $this->get_no_match_reason( 'footer' );

        $single_str = 'not a single post/page';
        if ( is_singular() ) {
            $single_match = $this->get_single_template_for_request();
            $single_str   = $single_match
                ? "template {$single_match['id']} ({$single_match['reason']})"
                : $this->get_no_match_reason( 'single' );
        }

        $archive_str = 'not an archive';
        if ( is_archive() || is_home() || is_search() ) {
            $archive_match = $this->get_archive_template_for_request();
            $archive_str   = $archive_match
                ? "template {$archive_match['id']} ({$archive_match['reason']})"
                : $this->get_no_match_reason( 'archive' );
        }

        echo "\n<!-- Loopkit: header = {$header_str} | footer = {$footer_str} | single = {$single_str} | archive = {$archive_str} -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Helper to describe why nothing matched for debug comment.
     *
     * @param string $location 'header' or 'footer'.
     * @return string
     */
    private function get_no_match_reason( $location ) {
        $cache = self::get_cached_templates();
        $candidates = $cache[ $location ] ?? array();
        if ( empty( $candidates ) ) {
            return 'none matched (no published templates with conditions)';
        }
        return 'none matched';
    }

    /**
     * AJAX: Get conditions and conflict warnings for a template.
     */
    public function ajax_get_conditions() {
        check_ajax_referer( 'loopkit_templates_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'loopkit' ) ) );
        }

        $post_id = absint( $_GET['template_id'] ?? 0 );
        if ( ! $post_id || 'loopkit_template' !== get_post_type( $post_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid template ID.', 'loopkit' ) ) );
        }

        $type = get_post_meta( $post_id, '_loopkit_template_type', true ) ?: 'header';
        $conditions = get_post_meta( $post_id, '_loopkit_conditions', true );
        if ( ! is_array( $conditions ) ) {
            $conditions = array();
        }

        // Find conflicts with other published templates of the same type
        $conflicts = $this->find_conflicts( $post_id, $type, $conditions );

        wp_send_json_success( array(
            'id'         => $post_id,
            'type'       => $type,
            'conditions' => $conditions,
            'conflicts'  => $conflicts,
        ) );
    }

    /**
     * AJAX: Save conditions for a template.
     */
    public function ajax_save_conditions() {
        check_ajax_referer( 'loopkit_templates_nonce', 'nonce' );

        $post_id = absint( $_POST['template_id'] ?? 0 );
        if ( ! $post_id || 'loopkit_template' !== get_post_type( $post_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid template ID.', 'loopkit' ) ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'loopkit' ) ) );
        }

        $raw = wp_unslash( $_POST['conditions'] ?? array() );
        $conditions = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
        $sanitized = self::sanitize_conditions( is_array( $conditions ) ? $conditions : array() );

        update_post_meta( $post_id, '_loopkit_conditions', $sanitized );

        // If publish requested or currently draft being published
        $publish = ! empty( $_POST['publish'] );
        if ( $publish && ! empty( $sanitized ) ) {
            wp_update_post( array(
                'ID'          => $post_id,
                'post_status' => 'publish',
            ) );
        }

        self::rebuild_cache();

        $summary = Loopkit_Templates::get_conditions_summary( $post_id );

        wp_send_json_success( array(
            'id'         => $post_id,
            'conditions' => $sanitized,
            'summary'    => $summary,
            'status'     => get_post_status( $post_id ),
        ) );
    }

    /**
     * AJAX: Search condition items (pages, posts, categories, tags).
     */
    public function ajax_search_items() {
        check_ajax_referer( 'loopkit_templates_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'loopkit' ) ) );
        }

        $query = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
        $sub   = sanitize_key( wp_unslash( $_GET['sub'] ?? 'page' ) );

        $results = array();

        if ( 'page' === $sub || 'post' === $sub ) {
            $posts = get_posts( array(
                'post_type'      => $sub,
                'post_status'    => 'publish',
                's'              => $query,
                'posts_per_page' => 20,
            ) );
            foreach ( $posts as $p ) {
                $results[] = array(
                    'id'   => $p->ID,
                    'text' => $p->post_title ?: "(no title #{$p->ID})",
                );
            }
        } elseif ( 'category' === $sub || 'post_tag' === $sub ) {
            $taxonomy = ( 'category' === $sub ) ? 'category' : 'post_tag';
            $terms = get_terms( array(
                'taxonomy'   => $taxonomy,
                'name__like' => $query,
                'number'     => 20,
                'hide_empty' => false,
            ) );
            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $t ) {
                    $results[] = array(
                        'id'   => $t->term_id,
                        'text' => $t->name,
                    );
                }
            }
        }

        wp_send_json_success( array( 'results' => $results ) );
    }

    /**
     * Check for condition conflicts with other published templates of the same type.
     *
     * @param int    $template_id Current template ID.
     * @param string $type        'header' or 'footer'.
     * @param array  $conditions  Conditions to check.
     * @return array Array of conflict messages.
     */
    public function find_conflicts( $template_id, $type, $conditions ) {
        $conflicts = array();
        $cache = self::get_cached_templates();
        $candidates = $cache[ $type ] ?? array();

        foreach ( $candidates as $cand ) {
            if ( (int) $cand['id'] === (int) $template_id ) {
                continue;
            }

            foreach ( $conditions as $my_row ) {
                $my_key = ( $my_row['type'] ?? 'include' ) . ':' . ( $my_row['name'] ?? 'general' ) . ':' . ( $my_row['sub'] ?? 'all' ) . ':' . ( $my_row['id'] ?? 0 );

                foreach ( $cand['conditions'] as $other_row ) {
                    $other_key = ( $other_row['type'] ?? 'include' ) . ':' . ( $other_row['name'] ?? 'general' ) . ':' . ( $other_row['sub'] ?? 'all' ) . ':' . ( $other_row['id'] ?? 0 );

                    if ( $my_key === $other_key ) {
                        $label = $this->format_row_label( $my_row );
                        $conflicts[] = sprintf(
                            /* translators: 1: condition name, 2: other template title */
                            __( 'Condition "%1$s" is already used by published %2$s template "%3$s".', 'loopkit' ),
                            $label,
                            ucfirst( $type ),
                            $cand['title']
                        );
                    }
                }
            }
        }

        return array_unique( $conflicts );
    }

    /**
     * Sanitize conditions array.
     *
     * @param array $conditions Raw conditions.
     * @return array Sanitized conditions.
     */
    public static function sanitize_conditions( $conditions ) {
        if ( ! is_array( $conditions ) ) {
            return array();
        }

        $sanitized = array();
        $allowed_types = array( 'include', 'exclude' );
        $allowed_names = array( 'general', 'archive', 'singular' );

        foreach ( $conditions as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $type = in_array( $row['type'] ?? '', $allowed_types, true ) ? $row['type'] : 'include';
            $name = in_array( $row['name'] ?? '', $allowed_names, true ) ? $row['name'] : 'general';
            $sub  = sanitize_key( $row['sub'] ?? 'all' );
            $id   = ! empty( $row['id'] ) ? absint( $row['id'] ) : ( ! empty( $row['item'] ) ? absint( $row['item'] ) : 0 );

            $item = array(
                'type' => $type,
                'name' => $name,
            );

            if ( 'general' !== $name ) {
                $item['sub'] = $sub;
                if ( $id > 0 ) {
                    $item['id']   = $id;
                    $item['item'] = $id;
                }
            }

            $sanitized[] = $item;
        }

        return $sanitized;
    }
}
