<?php
/**
 * Loopkit Fonts.
 *
 * Typography controls write font-family into the generated CSS, but the font file still has
 * to be loaded or the browser silently falls back — which is why choosing Poppins, Montserrat
 * and friends appeared to do nothing. This collects every family actually used (page elements,
 * Header/Footer/Single/Archive templates, Site Settings global fonts) and loads the
 * non-system ones from Google Fonts.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Fonts {

    /**
     * Families that are already on the device: never requested from Google.
     */
    const SYSTEM_FONTS = array(
        'default', 'inherit', 'initial', 'arial', 'helvetica', 'helvetica neue', 'times new roman',
        'times', 'georgia', 'verdana', 'tahoma', 'trebuchet ms', 'courier new', 'courier',
        'garamond', 'palatino', 'bookman', 'impact', 'system-ui', 'sans-serif', 'serif', 'monospace',
    );

    /**
     * Families collected for this request.
     *
     * @var array
     */
    private static $families = array();

    /**
     * Font families installed in WordPress: Appearance > Fonts (Font Library) and the
     * theme's own theme.json families. These already have their @font-face printed by
     * WordPress, so they must never be requested from Google as well.
     *
     * @return array List of array( 'name' => 'Inter', 'value' => 'Inter', 'source' => 'installed' )
     */
    public static function get_installed() {
        static $cache = null;
        if ( null !== $cache ) {
            return $cache;
        }

        $cache = array();
        if ( ! function_exists( 'wp_get_global_settings' ) ) {
            return $cache;
        }

        $settings = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
        if ( ! is_array( $settings ) ) {
            return $cache;
        }

        foreach ( array( 'custom', 'theme', 'default' ) as $origin ) {
            if ( empty( $settings[ $origin ] ) || ! is_array( $settings[ $origin ] ) ) {
                continue;
            }
            foreach ( $settings[ $origin ] as $font ) {
                $name  = isset( $font['name'] ) ? (string) $font['name'] : '';
                $stack = isset( $font['fontFamily'] ) ? (string) $font['fontFamily'] : '';
                if ( '' === $name && '' === $stack ) {
                    continue;
                }
                if ( '' === $name ) {
                    $name = trim( explode( ',', $stack )[0], " \"'" );
                }
                $cache[ strtolower( $name ) ] = array(
                    'name'   => $name,
                    'value'  => $name,
                    'source' => 'installed',
                );
            }
        }

        $cache = array_values( $cache );
        return $cache;
    }

    /**
     * Whether a family is already installed in WordPress (so Google is not needed).
     *
     * @param string $family Family name.
     * @return bool
     */
    public static function is_installed( $family ) {
        $family = strtolower( trim( (string) $family ) );
        foreach ( self::get_installed() as $font ) {
            if ( strtolower( $font['name'] ) === $family ) {
                return true;
            }
        }
        return false;
    }

    /**
     * The full list for the editor's font picker, grouped by source.
     *
     * @return array
     */
    public static function get_picker_list() {
        $system = array( 'Arial', 'Helvetica', 'Times New Roman', 'Georgia', 'Verdana', 'Tahoma', 'Trebuchet MS', 'Courier New' );
        $google = array(
            'Roboto', 'Roboto Slab', 'Open Sans', 'Lato', 'Montserrat', 'Poppins', 'Inter', 'Manrope',
            'Playfair Display', 'Oswald', 'Raleway', 'Merriweather', 'Nunito', 'Rubik', 'Work Sans',
            'DM Sans', 'Source Sans 3', 'Bebas Neue', 'Quicksand', 'Barlow', 'Fira Code',
        );

        $list = array();
        foreach ( self::get_installed() as $font ) {
            $list[] = array( 'name' => $font['name'], 'value' => $font['value'], 'group' => __( 'Installed on this site', 'loopkit' ) );
        }
        foreach ( $system as $name ) {
            $list[] = array( 'name' => $name, 'value' => $name, 'group' => __( 'System', 'loopkit' ) );
        }
        foreach ( $google as $name ) {
            if ( self::is_installed( $name ) ) {
                continue; // already installed: listed above, loaded by WordPress
            }
            $list[] = array( 'name' => $name, 'value' => $name, 'group' => __( 'Google Fonts', 'loopkit' ) );
        }

        /**
         * Filter the families offered in the Loopkit font picker.
         *
         * @param array $list Font list.
         */
        return apply_filters( 'loopkit_font_picker_list', $list );
    }

    /**
     * Add one family.
     *
     * @param string $family Font family.
     */
    public static function add( $family ) {
        $family = trim( (string) $family, " \t\n\r\0\x0B\"'" );
        if ( '' === $family ) {
            return;
        }
        // "Poppins, sans-serif" -> "Poppins"
        $family = trim( explode( ',', $family )[0], " \"'" );
        if ( '' === $family || in_array( strtolower( $family ), self::SYSTEM_FONTS, true ) ) {
            return;
        }
        // Installed via Appearance > Fonts or the theme: WordPress prints its @font-face already.
        if ( self::is_installed( $family ) ) {
            return;
        }
        self::$families[ $family ] = $family;
    }

    /**
     * Walk any settings structure and collect typography families.
     *
     * @param mixed $value Settings value.
     */
    public static function collect( $value ) {
        if ( ! is_array( $value ) ) {
            return;
        }
        foreach ( $value as $key => $item ) {
            if ( is_array( $item ) ) {
                if ( isset( $item['family'] ) && is_string( $item['family'] ) ) {
                    self::add( $item['family'] );
                }
                self::collect( $item );
            } elseif ( is_string( $item ) && ( 'family' === $key || 'font_family' === $key || 0 === strpos( (string) $key, 'font_' ) ) ) {
                self::add( $item );
            }
        }
    }

    /**
     * Collect from a document's saved layout.
     *
     * @param int $post_id Post ID.
     */
    public static function collect_from_post( $post_id ) {
        if ( ! $post_id || ! class_exists( 'Loopkit_Frontend' ) ) {
            return;
        }
        $elements = Loopkit_Frontend::decode_elements( get_post_meta( $post_id, '_loopkit_data', true ) );
        self::collect( $elements );
    }

    /**
     * Enqueue the Google Fonts stylesheet for everything collected so far.
     * Call late (after the page and its templates have been collected).
     */
    public static function enqueue() {
        /**
         * Filter the families Loopkit loads from Google Fonts. Return an empty array to disable.
         *
         * @param array $families Family names.
         */
        $families = apply_filters( 'loopkit_google_fonts', array_values( self::$families ) );
        if ( empty( $families ) ) {
            return;
        }

        $parts = array();
        foreach ( array_slice( $families, 0, 12 ) as $family ) {
            $parts[] = 'family=' . rawurlencode( $family ) . ':wght@300;400;500;600;700;800';
        }

        wp_enqueue_style(
            'loopkit-google-fonts',
            'https://fonts.googleapis.com/css2?' . implode( '&', $parts ) . '&display=swap',
            array(),
            null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
        );
    }

    /**
     * The weights a family actually ships. Google Fonts silently returns only the weights
     * that exist, so asking for 800 on a font that stops at 700 renders as 700 — which is why
     * 600/700/800 could look identical. Installed fonts report their own faces.
     *
     * @param string $family Family name.
     * @return array Weights as integers, empty when unknown.
     */
    public static function get_available_weights( $family ) {
        $family = trim( (string) $family );
        if ( '' === $family ) {
            return array();
        }

        // System fonts: the browser synthesises everything.
        if ( in_array( strtolower( $family ), self::SYSTEM_FONTS, true ) ) {
            return array( 400, 700 );
        }

        // Installed in WordPress: read the faces registered in theme.json / Font Library.
        if ( self::is_installed( $family ) && function_exists( 'wp_get_global_settings' ) ) {
            $settings = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
            foreach ( array( 'custom', 'theme', 'default' ) as $origin ) {
                foreach ( (array) ( $settings[ $origin ] ?? array() ) as $font ) {
                    $name = $font['name'] ?? '';
                    if ( strtolower( $name ) !== strtolower( $family ) ) {
                        continue;
                    }
                    $weights = array();
                    foreach ( (array) ( $font['fontFace'] ?? array() ) as $face ) {
                        $w = (string) ( $face['fontWeight'] ?? '400' );
                        foreach ( preg_split( '/[\s;,]+/', $w ) as $part ) {
                            if ( is_numeric( $part ) ) {
                                $weights[] = (int) $part;
                            } elseif ( false !== strpos( $part, ' ' ) ) {
                                continue;
                            }
                        }
                        // "100 900" variable range
                        if ( preg_match( '/^(\d+)\s+(\d+)$/', trim( $w ), $m ) ) {
                            for ( $i = (int) $m[1]; $i <= (int) $m[2]; $i += 100 ) {
                                $weights[] = $i;
                            }
                        }
                    }
                    $weights = array_values( array_unique( array_filter( $weights ) ) );
                    sort( $weights );
                    return $weights ? $weights : array( 400 );
                }
            }
            return array( 400 );
        }

        // Google font: ask once, cache for a week.
        $key    = 'loopkit_font_weights_' . md5( strtolower( $family ) );
        $cached = get_transient( $key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $url  = 'https://fonts.googleapis.com/css2?family=' . rawurlencode( $family ) . ':wght@100;200;300;400;500;600;700;800;900&display=swap';
        $args = array(
            'timeout'    => 8,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
        );

        $response = wp_remote_get( $url, $args );
        if ( is_wp_error( $response ) ) {
            // Local servers (XAMPP, MAMP) often ship without a CA bundle, so the HTTPS request
            // fails. Retry once without verification: this only reads a public stylesheet.
            $response = wp_remote_get( $url, array_merge( $args, array( 'sslverify' => false ) ) );
        }

        $weights = array();
        if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
            preg_match_all( '/font-weight:\s*([0-9]{3})/', wp_remote_retrieve_body( $response ), $m );
            $weights = array_values( array_unique( array_map( 'intval', $m[1] ) ) );
            sort( $weights );
        }

        if ( empty( $weights ) ) {
            $weights = self::known_weights( $family );
        }

        set_transient( $key, $weights, $weights ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );
        return $weights;
    }

    /**
     * Weights of the families offered in the picker, used when Google cannot be reached.
     *
     * @param string $family Family name.
     * @return array
     */
    private static function known_weights( $family ) {
        $all  = array( 100, 200, 300, 400, 500, 600, 700, 800, 900 );
        $map  = array(
            'roboto'            => $all,
            'roboto slab'       => $all,
            'open sans'         => array( 300, 400, 500, 600, 700, 800 ),
            'lato'              => array( 100, 300, 400, 700, 900 ),
            'montserrat'        => $all,
            'poppins'           => $all,
            'inter'             => $all,
            'manrope'           => array( 200, 300, 400, 500, 600, 700, 800 ),
            'playfair display'  => array( 400, 500, 600, 700, 800, 900 ),
            'oswald'            => array( 200, 300, 400, 500, 600, 700 ),
            'raleway'           => $all,
            'merriweather'      => array( 300, 400, 700, 900 ),
            'nunito'            => array( 200, 300, 400, 500, 600, 700, 800, 900 ),
            'rubik'             => array( 300, 400, 500, 600, 700, 800, 900 ),
            'work sans'         => $all,
            'dm sans'           => array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ),
            'source sans 3'     => array( 200, 300, 400, 500, 600, 700, 800, 900 ),
            'bebas neue'        => array( 400 ),
            'quicksand'         => array( 300, 400, 500, 600, 700 ),
            'barlow'            => $all,
            'fira code'         => array( 300, 400, 500, 600, 700 ),
        );
        return $map[ strtolower( $family ) ] ?? array();
    }
}
