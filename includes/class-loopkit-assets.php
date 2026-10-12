<?php
/**
 * Loopkit Assets Optimizer.
 * Handles asset minification, combining, and optimized enqueueing.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Assets {

    /**
     * Check if asset optimization (minification & combining) is enabled.
     *
     * @return bool True if enabled.
     */
    public static function is_optimization_enabled() {
        if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
            return false;
        }

        $advanced = get_option( 'loopkit_advanced_settings', array() );
        return ( $advanced['optimize_assets'] ?? 'yes' ) === 'yes';
    }

    /**
     * Minify a CSS string in pure PHP.
     * Strips comments, collapses redundant whitespace, removes semicolons before closing braces,
     * and preserves calc() expressions and quoted strings.
     *
     * @param string $css Raw CSS code.
     * @return string Minified CSS code.
     */
    public static function minify_css( $css ) {
        if ( empty( $css ) || ! is_string( $css ) ) {
            return '';
        }

        // 1. Remove comments (except if important /*! ... */)
        $css = preg_replace( '#/\*(?!!)[^*]*\*+([^/][^*]*\*+)*/#', '', $css );

        // 2. Normalize whitespace characters to spaces
        $css = str_replace( array( "\r\n", "\r", "\n", "\t" ), ' ', $css );

        // 3. Temporarily protect strings in single/double quotes to avoid breaking content/urls
        $strings = array();
        $css = preg_replace_callback( '/(".*?"|\'.*?\')/s', function( $m ) use ( &$strings ) {
            $key = '___STR_' . count( $strings ) . '___';
            $strings[ $key ] = $m[1];
            return $key;
        }, $css );

        // 4. Collapse multiple spaces into a single space
        $css = preg_replace( '/\s{2,}/', ' ', $css );

        // 5. Remove space around delimiters: { } ; : , > ~
        $css = preg_replace( '/\s*([{};:,>~])\s*/', '$1', $css );

        // 6. Ensure spaces around + and - inside calc() expressions are preserved
        $css = preg_replace_callback( '/calc\([^\)]+\)/i', function( $m ) {
            return preg_replace( '/([^\s\+\-\*\/])([\+\-])([^\s\+\-\*\/])/', '$1 $2 $3', $m[0] );
        }, $css );

        // 7. Remove trailing semicolons before closing brace
        $css = str_replace( ';}', '}', $css );

        // 8. Remove empty rules (e.g. .empty {} or @media (...) {})
        $css = preg_replace( '/[^{}]+\{\s*\}/', '', $css );

        // 9. Restore strings
        if ( ! empty( $strings ) ) {
            $css = strtr( $css, $strings );
        }

        return trim( $css );
    }

    /**
     * Get URL for an asset, automatically appending .min if optimization is enabled and file exists.
     *
     * @param string $relative_path Relative path from plugin root, e.g. 'assets/css/loopkit-frontend.css'.
     * @return string Full URL to the asset.
     */
    /**
     * The minified twin of a file, if there is one worth using.
     *
     * A .min file older than its source is serving yesterday's code — worse
     * than serving a larger file, because nothing about it looks wrong. It
     * has happened: CSS added to loopkit-frontend.css shipped to nobody,
     * because the minified copy beside it was never rebuilt, and that is the
     * copy a site with optimisation on actually loads.
     *
     * @param string $relative_path Path from the plugin root.
     * @return string The .min path to use, or '' to use the source.
     */
    protected static function usable_min( $relative_path ) {
        if ( ! self::is_optimization_enabled() ) {
            return '';
        }
        $ext      = pathinfo( $relative_path, PATHINFO_EXTENSION );
        $min_path = preg_replace( '/\.' . preg_quote( $ext, '/' ) . '$/', '.min.' . $ext, $relative_path );
        $min_file = LOOPKIT_PLUGIN_DIR . $min_path;
        $src_file = LOOPKIT_PLUGIN_DIR . $relative_path;

        if ( ! file_exists( $min_file ) ) {
            return '';
        }
        if ( file_exists( $src_file ) && filemtime( $src_file ) > filemtime( $min_file ) ) {
            return ''; // stale: serve the source until it is rebuilt
        }
        return $min_path;
    }

    public static function get_asset_url( $relative_path ) {
        $min_path = self::usable_min( $relative_path );
        return LOOPKIT_PLUGIN_URL . ( '' !== $min_path ? $min_path : $relative_path );
    }

    /**
     * Get filesystem path for an asset, automatically appending .min if optimization is enabled and file exists.
     *
     * @param string $relative_path Relative path from plugin root.
     * @return string Full filesystem path.
     */
    public static function get_asset_path( $relative_path ) {
        $min_path = self::usable_min( $relative_path );
        return LOOPKIT_PLUGIN_DIR . ( '' !== $min_path ? $min_path : $relative_path );
    }
}
