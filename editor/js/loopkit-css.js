/**
 * Loopkit CSS Engine
 * Generates responsive CSS rules for elements live in the editor and on the front end.
 *
 * @package Loopkit
 */

(function( window ) {
    'use strict';

    var LoopkitCSS = {};

    // Standard breakpoints and media queries (Section 2 & 3)
    // Order: Desktop (base), Widescreen (min-width), then largest to smallest max-width
    var BREAKPOINT_DEFS = [
        { id: 'widescreen',   type: 'min-width', query: '(min-width: 2400px)', defaultVal: 2400 },
        { id: 'laptop',       type: 'max-width', query: '(max-width: 1366px)', defaultVal: 1366 },
        { id: 'tablet_extra', type: 'max-width', query: '(max-width: 1200px)', defaultVal: 1200 },
        { id: 'tablet',       type: 'max-width', query: '(max-width: 1024px)', defaultVal: 1024 },
        { id: 'mobile_extra', type: 'max-width', query: '(max-width: 880px)',  defaultVal: 880 },
        { id: 'mobile',       type: 'max-width', query: '(max-width: 767px)',   defaultVal: 767 },
    ];

    /**
     * Get active devices list from editor config or default.
     */
    function getActiveDevices() {
        if ( window.loopkitEditor && window.loopkitEditor.activeBreakpoints && Array.isArray( window.loopkitEditor.activeBreakpoints ) ) {
            return window.loopkitEditor.activeBreakpoints;
        }
        return [ 'desktop', 'tablet', 'mobile' ];
    }

    /**
     * Get setting value for a specific device.
     * Returns undefined if this specific device has not set an override.
     */
    function getDeviceExplicitValue( settings, key, device ) {
        if ( ! settings ) return undefined;
        var storeKey = ( device === 'desktop' ) ? key : key + '_' + device;
        var val = settings[ storeKey ];
        if ( val === undefined || val === null || val === '' ) return undefined;
        return val;
    }

    /**
     * Helper to resolve global references to CSS variables (Section 1 & 3)
     * e.g. "globals/colors?id=primary" -> "var(--lk-global-color-primary)"
     */
    function resolveGlobalValue( val ) {
        if ( typeof val !== 'string' ) return val;
        if ( val.indexOf( 'globals/colors?id=' ) !== -1 ) {
            var id = val.split( 'id=' )[1];
            return 'var(--lk-global-color-' + id + ')';
        }
        if ( val.indexOf( 'globals/typography?id=' ) !== -1 ) {
            var tId = val.split( 'id=' )[1];
            return 'var(--lk-global-typography-' + tId + ')';
        }
        return val;
    }


    /**
     * POST WIDGETS (Part 5): declarative CSS rules.
     * KEEP IN SYNC: the identical table lives in includes/class-loopkit-rest-api.php
     * (post_widget_css_rules) and is applied by the identical apply_declarative_rules().
     * Row: [ settingKey, kind, selectorSuffix, cssProperty, option ]
     *   kind: raw | color | map | slider | unitless | dims | typography | text_shadow | text_stroke
     *   option: raw -> value to skip; map -> value map; slider/dims -> default unit
     */
    var ALIGN_FLEX_MAP = { left: 'flex-start', center: 'center', right: 'flex-end' };
    var POST_WIDGET_CSS_RULES = {
        'product-grid': [
            [ 'pgrid_columns', 'unitless', ' .lk-product-grid', '--lk-pgrid-columns' ],
            [ 'pgrid_gap', 'slider', ' .lk-product-grid', '--lk-pgrid-gap', 'px' ],
            [ 'pgrid_image_height', 'slider', ' .lk-product-grid', '--lk-pgrid-image-height', 'px' ],
            [ 'pgrid_card_bg', 'color', ' .lk-product-card', 'background-color' ],
            [ 'pgrid_card_border', 'color', ' .lk-product-card', 'border-color' ],
            [ 'pgrid_card_radius', 'dims', ' .lk-product-card', 'border-radius', 'px' ],
            [ 'pgrid_card_padding', 'dims', ' .lk-product-body', 'padding', 'px' ],
            [ 'pgrid_align', 'raw', ' .lk-product-body', 'text-align' ],
            [ 'pgrid_title_color', 'color', ' .lk-product-title', 'color' ],
            [ 'pgrid_title_typography', 'typography', ' .lk-product-title' ],
            [ 'pgrid_price_color', 'color', ' .lk-product-price', 'color' ],
            [ 'pgrid_price_typography', 'typography', ' .lk-product-price' ],
            [ 'pgrid_btn_bg', 'color', ' .lk-product-btn', 'background-color' ],
            [ 'pgrid_btn_color', 'color', ' .lk-product-btn', 'color' ],
            [ 'pgrid_btn_hover_bg', 'color', ' .lk-product-btn:hover', 'background-color' ],
            [ 'pgrid_badge_bg', 'color', ' .lk-product-badge', 'background-color' ],
            [ 'pgrid_btn_hover_color', 'color', ' .lk-product-btn:hover', 'color' ]
        ],
        'add-to-cart': [
            [ 'atc_gap', 'slider', ' .lk-atc', '--lk-atc-gap', 'px' ],
            [ 'atc_bg', 'color', ' .lk-atc-btn', 'background-color' ],
            [ 'atc_color', 'color', ' .lk-atc-btn', 'color' ],
            [ 'atc_hover_bg', 'color', ' .lk-atc-btn:hover', 'background-color' ],
            [ 'atc_padding', 'dims', ' .lk-atc-btn', 'padding', 'px' ],
            [ 'atc_radius', 'dims', ' .lk-atc-btn', 'border-radius', 'px' ],
            [ 'atc_typography', 'typography', ' .lk-atc-btn' ],
            [ 'atc_hover_color', 'color', ' .lk-atc-btn:hover', 'color' ]
        ],
        'product-price': [
            [ 'pprice_align', 'raw', ' .lk-pprice', 'justify-content' ],
            [ 'pprice_color', 'color', ' .lk-pprice-now', 'color' ],
            [ 'pprice_typography', 'typography', ' .lk-pprice-now' ],
            [ 'pprice_old_color', 'color', ' .lk-pprice-old', 'color' ],
            [ 'pprice_save_color', 'color', ' .lk-pprice-save', 'color' ]
        ],
        'product-gallery': [
            [ 'pgal_gap', 'slider', ' .lk-pgallery', '--lk-pgal-gap', 'px' ],
            [ 'pgal_main_height', 'slider', ' .lk-pgallery', '--lk-pgal-main-height', 'px' ],
            [ 'pgal_thumb_count', 'unitless', ' .lk-pgallery', '--lk-pgal-thumbs' ],
            [ 'pgal_radius', 'dims', ' .lk-pgallery-main img', 'border-radius', 'px' ]
        ],
        'cart-icon': [
            [ 'cicon_size', 'slider', ' .lk-cart-icon', 'font-size', 'px' ],
            [ 'cicon_color', 'color', ' .lk-cart-icon', 'color' ],
            [ 'cicon_badge_bg', 'color', ' .lk-cart-icon-count', 'background-color' ],
            [ 'cicon_badge_color', 'color', ' .lk-cart-icon-count', 'color' ],
            [ 'cicon_typography', 'typography', ' .lk-cart-icon' ]
        ],
        'cart-table': [
            [ 'carttable_color', 'color', ' .lk-cart-table', 'color' ],
            [ 'carttable_border', 'color', ' .lk-cart-table td', 'border-bottom-color' ],
            [ 'carttable_typography', 'typography', ' .lk-cart-table' ],
            [ 'carttable_btn_bg', 'color', ' .lk-btn-shop-primary', 'background-color' ],
            [ 'carttable_btn_color', 'color', ' .lk-btn-shop-primary', 'color' ],
            [ 'carttable_btn_hover_bg', 'color', ' .lk-btn-shop-primary:hover', 'background-color' ],
            [ 'carttable_btn_hover_color', 'color', ' .lk-btn-shop-primary:hover', 'color' ],
            [ 'carttable_btn_radius', 'dims', ' .lk-btn-shop-primary', 'border-radius', 'px' ],
            [ 'carttable_btn_typography', 'typography', ' .lk-btn-shop-primary' ]
        ],
        'team-member': [
            [ 'team_align', 'choose', ' .lk-team-body', 'text-align' ],
            [ 'team_card_bg', 'color', '', 'background-color' ],
            [ 'team_card_radius', 'dims', '', 'border-radius', 'px' ],
            [ 'team_card_padding', 'dims', '', 'padding', 'px' ],
            [ 'team_img_radius', 'dims', ' .lk-team-photo', 'border-radius', 'px' ],
            [ 'team_img_height', 'slider', ' .lk-team-photo img', 'height', 'px' ],
            [ 'team_name_color', 'color', ' .lk-team-name', 'color' ],
            [ 'team_name_typography', 'typography', ' .lk-team-name' ],
            [ 'team_role_color', 'color', ' .lk-team-role', 'color' ],
            [ 'team_role_typography', 'typography', ' .lk-team-role' ],
            [ 'team_bio_color', 'color', ' .lk-team-bio', 'color' ],
            [ 'team_social_color', 'color', ' .lk-team-social', 'color' ],
            [ 'team_social_hover', 'color', ' .lk-team-social:hover', 'color' ],
            [ 'team_social_size', 'slider', ' .lk-team-social', 'font-size', 'px' ]
        ],
        'testimonial-carousel': [
            [ 'tc_align', 'choose', ' .lk-tc-slide', 'text-align' ],
            [ 'tc_bg', 'color', ' .lk-tc-slide', 'background-color' ],
            [ 'tc_radius', 'dims', ' .lk-tc-slide', 'border-radius', 'px' ],
            [ 'tc_padding', 'dims', ' .lk-tc-slide', 'padding', 'px' ],
            [ 'tc_quote_color', 'color', ' .lk-tc-quote', 'color' ],
            [ 'tc_quote_typography', 'typography', ' .lk-tc-quote' ],
            [ 'tc_name_color', 'color', ' .lk-tc-name', 'color' ],
            [ 'tc_role_color', 'color', ' .lk-tc-role', 'color' ],
            [ 'tc_star_color', 'color', ' .lk-tc-stars', 'color' ],
            [ 'tc_dot_color', 'color', ' .lk-tc-dot', 'background-color' ],
            [ 'tc_dot_active', 'color', ' .lk-tc-dot.lk-active', 'background-color' ],
            [ 'tc_arrow_color', 'color', ' .lk-tc-arrow', 'color' ]
        ],
        'blockquote': [
            [ 'bq_align', 'choose', '', 'text-align' ],
            [ 'bq_color', 'color', ' .lk-bq-text', 'color' ],
            [ 'bq_typography', 'typography', ' .lk-bq-text' ],
            [ 'bq_author_color', 'color', ' .lk-bq-author', 'color' ],
            [ 'bq_author_typography', 'typography', ' .lk-bq-author' ],
            [ 'bq_accent', 'color', ' .lk-bq-border', 'border-left-color' ],
            [ 'bq_accent', 'color', ' .lk-bq-quote::before', 'color' ],
            [ 'bq_bg', 'color', ' .lk-bq', 'background-color' ],
            [ 'bq_padding', 'dims', ' .lk-bq', 'padding', 'px' ],
            [ 'bq_radius', 'dims', ' .lk-bq', 'border-radius', 'px' ]
        ],
        'business-hours': [
            [ 'bh_day_color', 'color', ' .lk-bh-day', 'color' ],
            [ 'bh_hours_color', 'color', ' .lk-bh-hours', 'color' ],
            [ 'bh_typography', 'typography', ' .lk-bh-row' ],
            [ 'bh_row_padding', 'dims', ' .lk-bh-row', 'padding', 'px' ],
            [ 'bh_divider', 'color', ' .lk-bh-row', 'border-bottom-color' ],
            [ 'bh_today_bg', 'color', ' .lk-bh-today', 'background-color' ],
            [ 'bh_today_color', 'color', ' .lk-bh-today .lk-bh-day', 'color' ],
            [ 'bh_closed_color', 'color', ' .lk-bh-shut .lk-bh-hours', 'color' ]
        ],
        'hotspot': [
            [ 'hs_marker_bg', 'color', ' .lk-hs-marker', 'background-color' ],
            [ 'hs_marker_color', 'color', ' .lk-hs-marker', 'color' ],
            [ 'hs_marker_size', 'slider', ' .lk-hs-marker', 'width', 'px' ],
            [ 'hs_marker_size', 'slider', ' .lk-hs-marker', 'height', 'px' ],
            [ 'hs_tip_bg', 'color', ' .lk-hs-tip', 'background-color' ],
            [ 'hs_tip_color', 'color', ' .lk-hs-tip', 'color' ],
            [ 'hs_tip_radius', 'dims', ' .lk-hs-tip', 'border-radius', 'px' ],
            [ 'hs_tip_width', 'slider', ' .lk-hs-tip', 'width', 'px' ],
            [ 'hs_img_radius', 'dims', ' .lk-hs-image', 'border-radius', 'px' ]
        ],
        'collection-list': [
            [ 'cl_columns', 'slider', ' .lk-cl--grid', 'grid-template-columns', 'repeat' ],
            [ 'cl_gap', 'slider', ' .lk-cl', 'gap', 'px' ],
            [ 'cl_card_bg', 'color', ' .lk-cl-card', 'background-color' ],
            [ 'cl_card_padding', 'dims', ' .lk-cl-card', 'padding', 'px' ],
            [ 'cl_card_radius', 'dims', ' .lk-cl-card', 'border-radius', 'px' ],
            [ 'cl_card_border_type', 'choose', ' .lk-cl-card', 'border-style' ],
            [ 'cl_card_border_width', 'dims', ' .lk-cl-card', 'border-width', 'px' ],
            [ 'cl_card_border_color', 'color', ' .lk-cl-card', 'border-color' ],
            [ 'cl_img_height', 'slider', ' .lk-cl-media img', 'height', 'px' ],
            [ 'cl_img_radius', 'dims', ' .lk-cl-media img', 'border-radius', 'px' ],
            [ 'cl_img_height', 'slider', ' .lk-cl-video video', 'height', 'px' ],
            [ 'cl_img_radius', 'dims', ' .lk-cl-video video', 'border-radius', 'px' ],
            [ 'cl_meta_color', 'color', ' .lk-cl-meta', 'color' ],
            [ 'cl_meta_typography', 'typography', ' .lk-cl-meta' ],
            [ 'cl_title_color', 'color', ' .lk-cl-title', 'color' ],
            [ 'cl_title_typography', 'typography', ' .lk-cl-title' ],
            [ 'cl_text_color', 'color', ' .lk-cl-text', 'color' ],
            [ 'cl_text_typography', 'typography', ' .lk-cl-text' ],
            [ 'cl_link_color', 'color', ' .lk-cl-link', 'color' ],
            [ 'cl_link_typography', 'typography', ' .lk-cl-link' ]
        ],
        'portfolio': [
            [ 'pf_columns', 'slider', ' .lk-pf-grid', 'grid-template-columns', 'repeat' ],
            [ 'pf_gap', 'slider', ' .lk-pf-grid', 'gap', 'px' ],
            [ 'pf_img_height', 'slider', ' .lk-pf-media img', 'height', 'px' ],
            [ 'pf_img_height', 'slider', ' .lk-pf-media-empty', 'height', 'px' ],
            [ 'pf_radius', 'dims', ' .lk-pf-item', 'border-radius', 'px' ],
            [ 'pf_title_color', 'color', ' .lk-pf-title', 'color' ],
            [ 'pf_title_typography', 'typography', ' .lk-pf-title' ],
            [ 'pf_term_color', 'color', ' .lk-pf-terms', 'color' ],
            [ 'pf_filter_color', 'color', ' .lk-pf-filter', 'color' ],
            [ 'pf_filter_active_bg', 'color', ' .lk-pf-filter.lk-active', 'background-color' ],
            [ 'pf_filter_active_color', 'color', ' .lk-pf-filter.lk-active', 'color' ],
            [ 'pf_overlay', 'color', ' .lk-pf-media::after', 'background-color' ]
        ],
        'login-form': [
            [ 'login_label_color', 'color', ' .lk-login-field span', 'color' ],
            [ 'login_field_bg', 'color', ' .lk-login-field input', 'background-color' ],
            [ 'login_field_color', 'color', ' .lk-login-field input', 'color' ],
            [ 'login_field_border', 'color', ' .lk-login-field input', 'border-color' ],
            [ 'login_field_radius', 'dims', ' .lk-login-field input', 'border-radius', 'px' ],
            [ 'login_btn_bg', 'color', ' .lk-login-btn', 'background-color' ],
            [ 'login_btn_color', 'color', ' .lk-login-btn', 'color' ],
            [ 'login_btn_hover_bg', 'color', ' .lk-login-btn:hover', 'background-color' ],
            [ 'login_btn_hover_color', 'color', ' .lk-login-btn:hover', 'color' ],
            [ 'login_btn_radius', 'dims', ' .lk-login-btn', 'border-radius', 'px' ],
            [ 'login_link_color', 'color', ' .lk-login-links a', 'color' ]
        ],
        'product-filters': [
            [ 'pfilters_gap', 'slider', ' .lk-pfilters-row', 'gap', 'px' ],
            [ 'pfilters_chip_bg', 'color', ' .lk-pfilter-chip', 'background-color' ],
            [ 'pfilters_chip_color', 'color', ' .lk-pfilter-chip', 'color' ],
            [ 'pfilters_chip_radius', 'dims', ' .lk-pfilter-chip', 'border-radius', 'px' ],
            [ 'pfilters_active_bg', 'color', ' .lk-pfilter-chip.lk-active', 'background-color' ],
            [ 'pfilters_active_color', 'color', ' .lk-pfilter-chip.lk-active', 'color' ],
            [ 'pfilters_label_color', 'color', ' .lk-pfilter-label', 'color' ]
        ],
        'mini-cart': [
            [ 'mc_icon_color', 'color', ' .lk-mc-toggle', 'color' ],
            [ 'mc_icon_size', 'slider', ' .lk-mc-toggle', 'font-size', 'px' ],
            [ 'mc_badge_bg', 'color', ' .lk-mc-count', 'background-color' ],
            [ 'mc_badge_color', 'color', ' .lk-mc-count', 'color' ],
            [ 'mc_panel_bg', 'color', ' .lk-mc-panel', 'background-color' ],
            [ 'mc_panel_color', 'color', ' .lk-mc-panel', 'color' ],
            [ 'mc_panel_width', 'slider', ' .lk-mc-panel', 'width', 'px' ],
            [ 'mc_panel_radius', 'dims', ' .lk-mc-panel', 'border-radius', 'px' ],
            [ 'mc_btn_bg', 'color', ' .lk-mc-btn-primary', 'background-color' ],
            [ 'mc_btn_color', 'color', ' .lk-mc-btn-primary', 'color' ]
        ],
        'product-rating': [
            [ 'prating_star_color', 'color', ' .lk-prating-star.lk-on', 'color' ],
            [ 'prating_empty_color', 'color', ' .lk-prating-star', 'color' ],
            [ 'prating_size', 'slider', ' .lk-prating-stars', 'font-size', 'px' ],
            [ 'prating_text_color', 'color', ' .lk-prating', 'color' ],
            [ 'prating_align', 'choose', '', 'text-align' ]
        ],
        'my-account': [
            [ 'ma_heading_color', 'color', ' .lk-account-heading', 'color' ],
            [ 'ma_heading_typography', 'typography', ' .lk-account-heading' ],
            [ 'ma_text_color', 'color', ' .lk-account', 'color' ],
            [ 'ma_row_border', 'color', ' .lk-account-table td', 'border-bottom-color' ],
            [ 'ma_btn_bg', 'color', ' .lk-btn-shop-primary', 'background-color' ],
            [ 'ma_btn_color', 'color', ' .lk-btn-shop-primary', 'color' ]
        ],
        'checkout-form': [
            [ 'checkout_heading_color', 'color', ' .lk-checkout-heading', 'color' ],
            [ 'checkout_heading_typography', 'typography', ' .lk-checkout-heading' ],
            [ 'checkout_label_color', 'color', ' .lk-checkout-grid label', 'color' ],
            [ 'checkout_label_typography', 'typography', ' .lk-checkout-grid label span' ],
            [ 'checkout_field_bg', 'color', ' .lk-checkout-grid input', 'background-color' ],
            [ 'checkout_field_bg', 'color', ' .lk-checkout-grid textarea', 'background-color' ],
            [ 'checkout_field_color', 'color', ' .lk-checkout-grid input', 'color' ],
            [ 'checkout_field_color', 'color', ' .lk-checkout-grid textarea', 'color' ],
            [ 'checkout_field_border', 'color', ' .lk-checkout-grid input', 'border-color' ],
            [ 'checkout_field_border', 'color', ' .lk-checkout-grid textarea', 'border-color' ],
            [ 'checkout_field_focus', 'color', ' .lk-checkout-grid input:focus', 'border-color' ],
            [ 'checkout_field_focus', 'color', ' .lk-checkout-grid textarea:focus', 'border-color' ],
            [ 'checkout_field_radius', 'dims', ' .lk-checkout-grid input', 'border-radius', 'px' ],
            [ 'checkout_field_radius', 'dims', ' .lk-checkout-grid textarea', 'border-radius', 'px' ],
            [ 'checkout_field_padding', 'dims', ' .lk-checkout-grid input', 'padding', 'px' ],
            [ 'checkout_field_padding', 'dims', ' .lk-checkout-grid textarea', 'padding', 'px' ],
            [ 'checkout_field_typography', 'typography', ' .lk-checkout-grid input' ],
            [ 'checkout_field_typography', 'typography', ' .lk-checkout-grid textarea' ],
            [ 'checkout_gap', 'slider', ' .lk-checkout-grid', 'gap', 'px' ],
            [ 'checkout_summary_bg', 'color', ' .lk-checkout-summary', 'background-color' ],
            [ 'checkout_summary_color', 'color', ' .lk-checkout-summary', 'color' ],
            [ 'checkout_summary_radius', 'dims', ' .lk-checkout-summary', 'border-radius', 'px' ],
            [ 'checkout_choice_border', 'color', ' .lk-checkout-choices label', 'border-color' ],
            [ 'checkout_btn_bg', 'color', ' .lk-place-order', 'background-color' ],
            [ 'checkout_btn_color', 'color', ' .lk-place-order', 'color' ],
            [ 'checkout_btn_hover_bg', 'color', ' .lk-place-order:hover', 'background-color' ],
            [ 'checkout_btn_radius', 'dims', ' .lk-place-order', 'border-radius', 'px' ],
            [ 'checkout_btn_typography', 'typography', ' .lk-place-order' ],
            [ 'checkout_btn_hover_color', 'color', ' .lk-place-order:hover', 'color' ],
            [ 'checkout_btn_bg', 'color', ' .lk-cart-empty-state .lk-btn-shop-primary', 'background-color' ],
            [ 'checkout_btn_color', 'color', ' .lk-cart-empty-state .lk-btn-shop-primary', 'color' ],
            [ 'checkout_btn_hover_bg', 'color', ' .lk-cart-empty-state .lk-btn-shop-primary:hover', 'background-color' ],
            [ 'checkout_btn_hover_color', 'color', ' .lk-cart-empty-state .lk-btn-shop-primary:hover', 'color' ],
            [ 'checkout_btn_radius', 'dims', ' .lk-cart-empty-state .lk-btn-shop-primary', 'border-radius', 'px' ],
            [ 'checkout_btn_typography', 'typography', ' .lk-cart-empty-state .lk-btn-shop-primary' ],
            [ 'checkout_btn_bg', 'color', ' .lk-btn-shop-primary', 'background-color' ],
            [ 'checkout_btn_color', 'color', ' .lk-btn-shop-primary', 'color' ],
            [ 'checkout_btn_hover_bg', 'color', ' .lk-btn-shop-primary:hover', 'background-color' ],
            [ 'checkout_btn_hover_color', 'color', ' .lk-btn-shop-primary:hover', 'color' ],
            [ 'checkout_btn_radius', 'dims', ' .lk-btn-shop-primary', 'border-radius', 'px' ],
            [ 'checkout_btn_typography', 'typography', ' .lk-btn-shop-primary' ]
        ],
        'product-categories': [
            [ 'pcats_columns', 'unitless', ' .lk-pcats', '--lk-pcats-columns' ],
            [ 'pcats_gap', 'slider', ' .lk-pcats', '--lk-pcats-gap', 'px' ],
            [ 'pcats_bg', 'color', ' .lk-pcat', 'background-color' ],
            [ 'pcats_color', 'color', ' .lk-pcat', 'color' ],
            [ 'pcats_padding', 'dims', ' .lk-pcat', 'padding', 'px' ],
            [ 'pcats_radius', 'dims', ' .lk-pcat', 'border-radius', 'px' ],
            [ 'pcats_typography', 'typography', ' .lk-pcat' ]
        ],
        'marquee': [
            [ 'mq_gap', 'slider', ' .lk-marquee', '--lk-marquee-gap', 'px' ],
            [ 'mq_row_gap', 'slider', ' .lk-marquee', '--lk-marquee-row-gap', 'px' ],
            [ 'mq_height', 'slider', ' .lk-marquee', '--lk-marquee-height', 'px' ],
            [ 'mq_fade_width', 'slider', ' .lk-marquee', '--lk-marquee-fade', 'px' ],
            [ 'mq_fade_color', 'color', ' .lk-marquee', '--lk-marquee-fade-color' ],
            [ 'mq_bg', 'color', ' .lk-marquee', 'background-color' ],
            [ 'mq_padding', 'dims', ' .lk-marquee', 'padding', 'px' ],
            [ 'mq_radius', 'dims', ' .lk-marquee', 'border-radius', 'px' ],
            [ 'mq_text_color', 'color', ' .lk-marquee-item', 'color' ],
            [ 'mq_hover_color', 'color', ' .lk-marquee-item:hover', 'color' ],
            [ 'mq_typography', 'typography', ' .lk-marquee-item' ],
            [ 'mq_stroke', 'text_stroke', ' .lk-marquee-text' ],
            [ 'mq_sep_color', 'color', ' .lk-marquee-sep', 'color' ],
            [ 'mq_icon_size', 'slider', ' .lk-marquee-icon', 'font-size', 'px' ],
            [ 'mq_image_height', 'slider', ' .lk-marquee-img', 'height', 'px' ],
            [ 'mq_image_radius', 'dims', ' .lk-marquee-img', 'border-radius', 'px' ],
            [ 'mq_image_opacity', 'unitless', ' .lk-marquee-img', 'opacity' ]
        ],
        'flip-box': [
            [ 'flip_align', 'raw', ' .lk-flip-face', 'text-align' ],
            [ 'flip_height', 'slider', ' .lk-flip-box', 'height', 'px' ],
            [ 'flip_padding', 'dims', ' .lk-flip-face', 'padding', 'px' ],
            [ 'flip_radius', 'dims', ' .lk-flip-face', 'border-radius', 'px' ],
            [ 'flip_front_bg', 'color', ' .lk-flip-front', 'background-color' ],
            [ 'flip_front_color', 'color', ' .lk-flip-front', 'color' ],
            [ 'flip_back_bg', 'color', ' .lk-flip-back', 'background-color' ],
            [ 'flip_back_color', 'color', ' .lk-flip-back', 'color' ],
            [ 'flip_icon_size', 'slider', ' .lk-flip-icon', 'font-size', 'px' ],
            [ 'flip_title_typography', 'typography', ' .lk-flip-title' ],
            [ 'flip_text_typography', 'typography', ' .lk-flip-text' ],
            [ 'flip_shadow', 'box_shadow', ' .lk-flip-face' ]
        ],
        'price-table': [
            [ 'price_bg', 'color', ' .lk-price-table', 'background-color' ],
            [ 'price_border_color', 'color', ' .lk-price-table', 'border-color' ],
            [ 'price_radius', 'dims', ' .lk-price-table', 'border-radius', 'px' ],
            [ 'price_inner_padding', 'dims', ' .lk-price-table', 'padding', 'px' ],
            [ 'price_heading_color', 'color', ' .lk-price-heading', 'color' ],
            [ 'price_heading_typography', 'typography', ' .lk-price-heading' ],
            [ 'price_value_color', 'color', ' .lk-price-amount', 'color' ],
            [ 'price_value_typography', 'typography', ' .lk-price-amount' ],
            [ 'price_features_color', 'color', ' .lk-price-features', 'color' ],
            [ 'price_features_typography', 'typography', ' .lk-price-features' ],
            [ 'price_button_bg', 'color', ' .lk-price-btn', 'background-color' ],
            [ 'price_button_color', 'color', ' .lk-price-btn', 'color' ],
            [ 'price_button_hover_bg', 'color', ' .lk-price-btn:hover', 'background-color' ],
            [ 'price_ribbon_bg', 'color', ' .lk-price-ribbon', 'background-color' ],
            [ 'price_ribbon_color', 'color', ' .lk-price-ribbon', 'color' ],
            [ 'price_shadow', 'box_shadow', ' .lk-price-table' ],
            [ 'price_button_hover_color', 'color', ' .lk-price-btn:hover', 'color' ]
        ],
        'countdown': [
            [ 'cd_align', 'raw', ' .lk-countdown-items', 'justify-content' ],
            [ 'cd_gap', 'slider', ' .lk-countdown-items', 'gap', 'px' ],
            [ 'cd_box_bg', 'color', ' .lk-countdown-item', 'background-color' ],
            [ 'cd_box_padding', 'dims', ' .lk-countdown-item', 'padding', 'px' ],
            [ 'cd_box_radius', 'dims', ' .lk-countdown-item', 'border-radius', 'px' ],
            [ 'cd_digit_color', 'color', ' .lk-countdown-digit', 'color' ],
            [ 'cd_digit_typography', 'typography', ' .lk-countdown-digit' ],
            [ 'cd_label_color', 'color', ' .lk-countdown-label', 'color' ],
            [ 'cd_label_typography', 'typography', ' .lk-countdown-label' ]
        ],
        'animated-headline': [
            [ 'ah_align', 'raw', ' .lk-animated-headline', 'text-align' ],
            [ 'ah_color', 'color', ' .lk-animated-headline', 'color' ],
            [ 'ah_typography', 'typography', ' .lk-animated-headline' ],
            [ 'ah_word_color', 'color', ' .lk-ah-word', 'color' ],
            [ 'ah_highlight_color', 'raw', ' .lk-ah-highlight .lk-ah-word', 'background-color' ]
        ],
        'call-to-action': [
            [ 'cta_min_height', 'slider', ' .lk-cta', 'min-height', 'px' ],
            [ 'cta_content_align', 'raw', ' .lk-cta-content', 'align-items' ],
            [ 'cta_content_align', 'raw', ' .lk-cta-content', 'text-align' ],
            [ 'cta_overlay_color', 'color', ' .lk-cta-overlay', 'background-color' ],
            [ 'cta_padding', 'dims', ' .lk-cta', 'padding', 'px' ],
            [ 'cta_radius', 'dims', ' .lk-cta', 'border-radius', 'px' ],
            [ 'cta_title_color', 'color', ' .lk-cta-title', 'color' ],
            [ 'cta_title_typography', 'typography', ' .lk-cta-title' ],
            [ 'cta_desc_color', 'color', ' .lk-cta-description', 'color' ],
            [ 'cta_desc_typography', 'typography', ' .lk-cta-description' ],
            [ 'cta_button_bg', 'color', ' .lk-cta-btn', 'background-color' ],
            [ 'cta_button_color', 'color', ' .lk-cta-btn', 'color' ],
            [ 'cta_button_hover_bg', 'color', ' .lk-cta-btn:hover', 'background-color' ],
            [ 'cta_button_radius', 'dims', ' .lk-cta-btn', 'border-radius', 'px' ],
            [ 'cta_button_hover_color', 'color', ' .lk-cta-btn:hover', 'color' ]
        ],
        'share-buttons': [
            [ 'share_align', 'raw', ' .lk-share-buttons', 'justify-content' ],
            [ 'share_gap', 'slider', ' .lk-share-buttons', 'gap', 'px' ],
            [ 'share_bg', 'color', ' .lk-share-btn', 'background-color' ],
            [ 'share_color', 'color', ' .lk-share-btn', 'color' ],
            [ 'share_hover_bg', 'color', ' .lk-share-btn:hover', 'background-color' ],
            [ 'share_hover_color', 'color', ' .lk-share-btn:hover', 'color' ],
            [ 'share_padding', 'dims', ' .lk-share-btn', 'padding', 'px' ],
            [ 'share_icon_size', 'slider', ' .lk-share-icon', 'font-size', 'px' ],
            [ 'share_typography', 'typography', ' .lk-share-btn' ]
        ],
        'icon': [
            [ 'primary_color', 'color', ' .lk-icon', 'color' ],
            [ 'primary_color', 'color', ' .lk-icon svg', 'fill' ],
            [ 'hover_primary_color', 'color', ':hover .lk-icon', 'color' ],
            [ 'hover_primary_color', 'color', ':hover .lk-icon svg', 'fill' ],
            [ 'secondary_color', 'color', ' .lk-icon-wrap', 'background-color' ],
            [ 'hover_secondary_color', 'color', ':hover .lk-icon-wrap', 'background-color' ],
            [ 'icon_size', 'slider', ' .lk-icon', 'font-size', 'px' ],
            [ 'icon_size', 'slider', ' .lk-icon svg', 'width', 'px' ],
            [ 'icon_size', 'slider', ' .lk-icon svg', 'height', 'px' ],
            [ 'icon_padding', 'slider', ' .lk-icon-wrap', 'padding', 'px' ],
            [ 'border_width', 'dims', ' .lk-icon-wrap', 'border-width', 'px' ],
            [ 'border_radius', 'dims', ' .lk-icon-wrap', 'border-radius', 'px' ],
            [ 'rotate', 'slider', ' .lk-icon', 'rotate', 'deg' ]
        ],
        'divider': [
            [ 'text_color', 'color', ' .lk-divider__text', 'color' ],
            [ 'text_typography', 'typography', ' .lk-divider__text' ],
            [ 'text_spacing', 'slider', ' .lk-divider__text', 'padding-inline', 'px' ],
            [ 'icon_primary_color', 'color', ' .lk-divider .lk-icon', 'color' ],
            [ 'icon_primary_color', 'color', ' .lk-divider .lk-icon svg', 'fill' ],
            [ 'icon_secondary_color', 'color', ' .lk-divider__icon', 'background-color' ],
            [ 'icon_size', 'slider', ' .lk-divider .lk-icon', 'font-size', 'px' ],
            [ 'icon_size', 'slider', ' .lk-divider .lk-icon svg', 'width', 'px' ],
            [ 'icon_size', 'slider', ' .lk-divider .lk-icon svg', 'height', 'px' ],
            [ 'icon_spacing', 'slider', ' .lk-divider__icon', 'padding-inline', 'px' ]
        ],
        'icon-list': [
            [ 'text_typography', 'typography', ' .lk-icon-list-text' ],
            [ 'text_hover_color', 'color', ' .lk-icon-list-item:hover .lk-icon-list-text', 'color' ],
            [ 'icon_hover_color', 'color', ' .lk-icon-list-item:hover .lk-icon-list-icon', 'color' ],
            [ 'icon_hover_color', 'color', ' .lk-icon-list-item:hover .lk-icon-list-icon svg', 'fill' ],
            [ 'divider_color', 'color', ' .lk-has-divider .lk-icon-list-item:not(:last-child)', 'border-color' ]
        ],
        'icon-box': [
            [ 'title_typography', 'typography', ' .lk-icon-box-title' ],
            [ 'title_space', 'slider', ' .lk-icon-box-title', 'margin-bottom', 'px' ],
            [ 'secondary_color', 'color', ' .lk-icon-box-icon .lk-icon-wrap', 'background-color' ],
            [ 'hover_primary_color', 'color', ':hover .lk-icon-box-icon .lk-icon', 'color' ],
            [ 'hover_primary_color', 'color', ':hover .lk-icon-box-icon .lk-icon svg', 'fill' ],
            [ 'hover_secondary_color', 'color', ':hover .lk-icon-box-icon .lk-icon-wrap', 'background-color' ],
            [ 'icon_border_width', 'dims', ' .lk-icon-box-icon .lk-icon-wrap', 'border-width', 'px' ],
            [ 'icon_border_radius', 'dims', ' .lk-icon-box-icon .lk-icon-wrap', 'border-radius', 'px' ],
            [ 'icon_space', 'slider', ' .lk-icon-box-icon', 'margin-bottom', 'px' ],
            [ 'description_typography', 'typography', ' .lk-icon-box-description' ],
            [ 'title_size', 'slider', ' .lk-icon-box-title', 'font-size', 'px' ]
        ],
        'image': [
            [ 'box_shadow', 'box_shadow', ' img' ],
            [ 'caption_text_shadow', 'text_shadow', ' .lk-image-caption' ],
            [ 'border_radius', 'dims', ' img', 'border-radius', 'px' ],
            [ 'caption_text_color', 'color', ' .lk-image-caption', 'color' ],
            [ 'caption_background_color', 'color', ' .lk-image-caption', 'background-color' ],
            [ 'caption_typography', 'typography', ' .lk-image-caption' ]
        ],
        'image-box': [
            [ 'title_typography', 'typography', ' .lk-image-box-title' ],
            [ 'title_size', 'slider', ' .lk-image-box-title', 'font-size', 'px' ],
            [ 'title_space', 'slider', ' .lk-image-box-title', 'margin-bottom', 'px' ],
            [ 'description_typography', 'typography', ' .lk-image-box-description' ],
            [ 'image_border_radius', 'dims', ' .lk-image-box-img img', 'border-radius', 'px' ]
        ],
        'site-logo': [
            [ 'box_shadow', 'box_shadow', ' .lk-site-logo-img' ],
            [ 'border_radius', 'dims', ' .lk-site-logo-img', 'border-radius', 'px' ]
        ],
        'social-icons': [
            [ 'primary_color', 'color', ' .lk-social-icon', 'background-color' ],
            [ 'secondary_color', 'color', ' .lk-social-icon', 'color' ],
            [ 'secondary_color', 'color', ' .lk-social-icon svg', 'fill' ],
            [ 'item_color', 'color', ' .lk-social-icon', 'background-color' ],
            [ 'item_primary_color', 'color', ' .lk-social-icon', 'background-color' ],
            [ 'item_secondary_color', 'color', ' .lk-social-icon', 'color' ],
            [ 'icon_size', 'slider', ' .lk-social-icon', 'font-size', 'px' ],
            [ 'icon_size', 'slider', ' .lk-social-icon svg', 'width', 'px' ],
            [ 'icon_size', 'slider', ' .lk-social-icon svg', 'height', 'px' ],
            [ 'icon_padding', 'slider', ' .lk-social-icon', 'padding', 'px' ],
            [ 'icon_spacing', 'slider', ' .lk-social-icons-wrapper', 'column-gap', 'px' ],
            [ 'rows_gap', 'slider', ' .lk-social-icons-wrapper', 'row-gap', 'px' ]
        ],
        'video': [
            [ 'play_icon_color', 'color', ' .lk-video-placeholder-play', 'color' ],
            [ 'play_icon_color', 'color', ' .lk-video-placeholder-play svg', 'fill' ],
            [ 'play_icon_size', 'slider', ' .lk-video-placeholder-play', 'font-size', 'px' ],
            [ 'play_icon_size', 'slider', ' .lk-video-placeholder-play svg', 'width', 'px' ],
            [ 'play_icon_size', 'slider', ' .lk-video-placeholder-play svg', 'height', 'px' ],
            [ 'play_icon_text_shadow', 'text_shadow', ' .lk-video-placeholder-play' ]
        ],
        'search-form': [
            [ 'button_box_shadow', 'box_shadow', ' .lk-search-form__submit' ],
            [ 'input_box_shadow', 'box_shadow', ' .lk-search-form__input' ],
            [ 'input_placeholder_color', 'color', ' .lk-search-form__input::placeholder', 'color' ],
            [ 'input_text_color', 'color', ' .lk-search-form__input', 'color' ],
            [ 'input_bg_color', 'color', ' .lk-search-form__input', 'background-color' ],
            [ 'input_typography', 'typography', ' .lk-search-form__input' ],
            [ 'input_padding', 'dims', ' .lk-search-form__input', 'padding', 'px' ],
            [ 'input_border_radius', 'dims', ' .lk-search-form__input', 'border-radius', 'px' ],
            [ 'button_text_color', 'color', ' .lk-search-form__submit', 'color' ],
            [ 'button_text_color_hover', 'color', ' .lk-search-form__submit:hover', 'color' ],
            [ 'button_bg_color', 'color', ' .lk-search-form__submit', 'background-color' ],
            [ 'button_bg_color_hover', 'color', ' .lk-search-form__submit:hover', 'background-color' ],
            [ 'button_typography', 'typography', ' .lk-search-form__submit' ],
            [ 'button_padding', 'dims', ' .lk-search-form__submit', 'padding', 'px' ],
            [ 'button_border_radius', 'dims', ' .lk-search-form__submit', 'border-radius', 'px' ],
            [ 'button_icon_size', 'slider', ' .lk-search-form__icon', 'font-size', 'px' ]
        ],
        'text-editor': [
            [ 'drop_cap_typography', 'typography', ' .lk-has-drop-cap::first-letter' ],
            [ 'drop_cap_primary_color', 'color', ' .lk-has-drop-cap::first-letter', 'color' ],
            [ 'drop_cap_secondary_color', 'color', ' .lk-has-drop-cap::first-letter', 'background-color' ],
            [ 'drop_cap_size', 'slider', ' .lk-has-drop-cap::first-letter', 'font-size', 'px' ],
            [ 'drop_cap_space', 'slider', ' .lk-has-drop-cap::first-letter', 'margin-right', 'px' ],
            [ 'drop_cap_border_radius', 'dims', ' .lk-has-drop-cap::first-letter', 'border-radius', 'px' ],
            [ 'drop_cap_border_width', 'dims', ' .lk-has-drop-cap::first-letter', 'border-width', 'px' ]
        ],
        'nav-menu': [
            [ 'dropdown_box_shadow', 'box_shadow', ' .lk-sub-menu' ],
            [ 'text_color', 'color', ' .lk-menu-link', 'color' ],
            [ 'text_color_hover', 'color', ' .lk-menu-link:hover', 'color' ],
            [ 'menu_bg', 'color', ' .lk-menu-link', 'background-color' ],
            [ 'menu_bg_hover', 'color', ' .lk-menu-link:hover', 'background-color' ],
            [ 'typography', 'typography', ' .lk-menu-link' ],
            [ 'item_padding', 'dims', ' .lk-menu-link', 'padding', 'px' ],
            [ 'border_radius', 'dims', ' .lk-menu-link', 'border-radius', 'px' ],
            [ 'item_gap', 'slider', ' .lk-nav-menu', 'gap', 'px' ],
            [ 'dropdown_bg', 'color', ' .lk-sub-menu', 'background-color' ],
            [ 'dropdown_text_color', 'color', ' .lk-sub-menu .lk-menu-link', 'color' ],
            [ 'dropdown_text_color_hover', 'color', ' .lk-sub-menu .lk-menu-link:hover', 'color' ],
            [ 'dropdown_item_bg_hover', 'color', ' .lk-sub-menu .lk-menu-link:hover', 'background-color' ],
            [ 'dropdown_typography', 'typography', ' .lk-sub-menu .lk-menu-link' ],
            [ 'dropdown_padding', 'dims', ' .lk-sub-menu .lk-menu-link', 'padding', 'px' ],
            [ 'dropdown_border_radius', 'dims', ' .lk-sub-menu', 'border-radius', 'px' ],
            [ 'dropdown_width', 'slider', ' .lk-sub-menu', 'width', 'px' ],
            [ 'hamburger_color', 'color', ' .lk-nav-menu-toggle', 'color' ],
            [ 'hamburger_bg', 'color', ' .lk-nav-menu-toggle', 'background-color' ],
            [ 'hamburger_size', 'slider', ' .lk-nav-menu-toggle', 'font-size', 'px' ],
            [ 'hamburger_border_radius', 'dims', ' .lk-nav-menu-toggle', 'border-radius', 'px' ]
        ],
        'heading': [
            [ 'title_color', 'color', ' .lk-heading-title', 'color' ],
            [ 'title_color', 'color', ' .lk-heading-title a', 'color' ],
            [ 'typography', 'typography', ' .lk-heading-title' ],
            [ 'text_shadow', 'text_shadow', ' .lk-heading-title' ],
            [ 'text_stroke', 'text_stroke', ' .lk-heading-title' ],
            [ 'blend_mode', 'raw', ' .lk-heading-title', 'mix-blend-mode', 'normal' ]
        ],
        'loop-grid': [
            [ 'columns', 'grid_cols', ' .lk-loop-items', 'grid-template-columns' ],
            [ 'column_gap', 'slider', ' .lk-loop-items', 'column-gap', 'px' ],
            [ 'row_gap', 'slider', ' .lk-loop-items', 'row-gap', 'px' ],
            [ 'pagination_align', 'map', ' .lk-posts-pagination', 'justify-content', ALIGN_FLEX_MAP ],
            [ 'pagination_color', 'color', ' .lk-posts-pagination .page-numbers', 'color' ],
            [ 'pagination_active_color', 'color', ' .lk-posts-pagination .page-numbers.current', 'color' ],
            [ 'pagination_typography', 'typography', ' .lk-posts-pagination' ]
        ],
        'site-title': [
            [ 'align', 'raw', ' .lk-site-title-wrapper', 'text-align' ],
            [ 'title_color', 'color', ' .lk-site-title-heading', 'color' ],
            [ 'title_color', 'color', ' .lk-site-title-heading a', 'color' ],
            [ 'title_hover_color', 'color', ' .lk-site-title-heading a:hover', 'color' ],
            [ 'typography', 'typography', ' .lk-site-title-heading' ],
            [ 'text_shadow', 'text_shadow', ' .lk-site-title-heading' ],
            [ 'text_stroke', 'text_stroke', ' .lk-site-title-heading' ],
            [ 'blend_mode', 'raw', ' .lk-site-title-heading', 'mix-blend-mode', 'normal' ]
        ],
        'post-title': [
            [ 'align', 'raw', ' .lk-post-title-wrapper', 'text-align' ],
            [ 'title_color', 'color', ' .lk-post-title', 'color' ],
            [ 'title_color', 'color', ' .lk-post-title a', 'color' ],
            [ 'title_hover_color', 'color', ' .lk-post-title a:hover', 'color' ],
            [ 'typography', 'typography', ' .lk-post-title' ],
            [ 'text_shadow', 'text_shadow', ' .lk-post-title' ],
            [ 'text_stroke', 'text_stroke', ' .lk-post-title' ],
            [ 'blend_mode', 'raw', ' .lk-post-title', 'mix-blend-mode', 'normal' ]
        ],
        'post-featured-image': [
            [ 'align', 'raw', ' .lk-post-featured-image', 'text-align' ],
            [ 'width', 'slider', ' .lk-post-featured-image-img', 'width', '%' ],
            [ 'max_width', 'slider', ' .lk-post-featured-image-img', 'max-width', '%' ],
            [ 'height', 'slider', ' .lk-post-featured-image-img', 'height', 'px' ],
            [ 'object_fit', 'raw', ' .lk-post-featured-image-img', 'object-fit' ],
            [ 'image_border_radius', 'dims', ' .lk-post-featured-image-img', 'border-radius', 'px' ],
            [ 'opacity', 'unitless', ' .lk-post-featured-image-img', 'opacity' ],
            [ 'caption_align', 'raw', ' .lk-post-featured-image-caption', 'text-align' ],
            [ 'caption_color', 'color', ' .lk-post-featured-image-caption', 'color' ],
            [ 'caption_typography', 'typography', ' .lk-post-featured-image-caption' ]
        ],
        'post-excerpt': [
            [ 'align', 'raw', ' .lk-post-excerpt', 'text-align' ],
            [ 'text_color', 'color', ' .lk-post-excerpt', 'color' ],
            [ 'typography', 'typography', ' .lk-post-excerpt' ]
        ],
        'post-info': [
            [ 'align', 'map', ' .lk-post-info-inline', 'justify-content', ALIGN_FLEX_MAP ],
            [ 'align', 'map', ' .lk-post-info-list', 'align-items', ALIGN_FLEX_MAP ],
            [ 'space_between', 'slider', ' .lk-post-info-items', 'gap', 'px' ],
            [ 'divider_color', 'color', ' .lk-post-info-has-divider .lk-post-info-item:not(:first-child)::before', 'color' ],
            [ 'icon_color', 'color', ' .lk-post-info-icon', 'color' ],
            [ 'icon_color', 'color', ' .lk-post-info-icon svg', 'fill' ],
            [ 'icon_size', 'slider', ' .lk-post-info-icon', 'font-size', 'px' ],
            [ 'icon_size', 'slider', ' .lk-post-info-icon svg', 'width', 'px' ],
            [ 'icon_size', 'slider', ' .lk-post-info-icon svg', 'height', 'px' ],
            [ 'text_color', 'color', ' .lk-post-info-item', 'color' ],
            [ 'text_color', 'color', ' .lk-post-info-item a', 'color' ],
            [ 'link_hover_color', 'color', ' .lk-post-info-item a:hover', 'color' ],
            [ 'typography', 'typography', ' .lk-post-info-item' ]
        ],
        'post-content': [
            [ 'align', 'raw', ' .lk-post-content', 'text-align' ],
            [ 'text_color', 'color', ' .lk-post-content', 'color' ],
            [ 'link_color', 'color', ' .lk-post-content a', 'color' ],
            [ 'link_hover_color', 'color', ' .lk-post-content a:hover', 'color' ],
            [ 'typography', 'typography', ' .lk-post-content' ]
        ],
        'author-box': [
            [ 'align', 'raw', ' .lk-author-box-text', 'text-align' ],
            [ 'align', 'map', ' .lk-author-box-above', 'align-items', ALIGN_FLEX_MAP ],
            [ 'avatar_size', 'slider', ' .lk-author-box-avatar img', 'width', 'px' ],
            [ 'avatar_size', 'slider', ' .lk-author-box-avatar img', 'height', 'px' ],
            [ 'avatar_radius', 'slider', ' .lk-author-box-avatar img', 'border-radius', '%' ],
            [ 'gap', 'slider', ' .lk-author-box', 'gap', 'px' ],
            [ 'name_color', 'color', ' .lk-author-box-name', 'color' ],
            [ 'name_color', 'color', ' .lk-author-box-name a', 'color' ],
            [ 'name_typography', 'typography', ' .lk-author-box-name' ],
            [ 'bio_color', 'color', ' .lk-author-box-bio', 'color' ],
            [ 'bio_typography', 'typography', ' .lk-author-box-bio' ],
            [ 'button_color', 'color', ' .lk-author-box-link', 'color' ],
            [ 'button_bg', 'color', ' .lk-author-box-link', 'background-color' ],
            [ 'button_typography', 'typography', ' .lk-author-box-link' ]
        ],
        'post-navigation': [
            [ 'label_color', 'color', ' .lk-post-nav-label', 'color' ],
            [ 'label_typography', 'typography', ' .lk-post-nav-label' ],
            [ 'title_color', 'color', ' .lk-post-nav-title', 'color' ],
            [ 'title_hover_color', 'color', ' .lk-post-nav-link:hover .lk-post-nav-title', 'color' ],
            [ 'title_typography', 'typography', ' .lk-post-nav-title' ],
            [ 'arrow_color', 'color', ' .lk-post-nav-arrow', 'color' ],
            [ 'arrow_size', 'slider', ' .lk-post-nav-arrow svg', 'width', 'px' ],
            [ 'arrow_size', 'slider', ' .lk-post-nav-arrow svg', 'height', 'px' ]
        ],
        'post-comments': [
            [ 'title_color', 'color', ' .lk-comments-title', 'color' ],
            [ 'title_typography', 'typography', ' .lk-comments-title' ],
            [ 'text_color', 'color', ' .lk-post-comments', 'color' ],
            [ 'link_color', 'color', ' .lk-post-comments a', 'color' ],
            [ 'typography', 'typography', ' .lk-post-comments' ]
        ],
        'archive-title': [
            [ 'align', 'raw', ' .lk-archive-title-wrapper', 'text-align' ],
            [ 'title_color', 'color', ' .lk-archive-title', 'color' ],
            [ 'prefix_color', 'color', ' .lk-archive-title-prefix', 'color' ],
            [ 'typography', 'typography', ' .lk-archive-title' ],
            [ 'text_shadow', 'text_shadow', ' .lk-archive-title' ]
        ],
        'archive-description': [
            [ 'align', 'raw', ' .lk-archive-description', 'text-align' ],
            [ 'text_color', 'color', ' .lk-archive-description', 'color' ],
            [ 'link_color', 'color', ' .lk-archive-description a', 'color' ],
            [ 'typography', 'typography', ' .lk-archive-description' ]
        ],
        'posts': [
            [ 'columns', 'grid_cols', ' .lk-posts-grid .lk-posts-items', 'grid-template-columns' ],
            [ 'column_gap', 'slider', ' .lk-posts-items', 'column-gap', 'px' ],
            [ 'row_gap', 'slider', ' .lk-posts-items', 'row-gap', 'px' ],
            [ 'card_bg', 'color', ' .lk-post-card', 'background-color' ],
            [ 'card_padding', 'dims', ' .lk-post-card-body', 'padding', 'px' ],
            [ 'card_radius', 'dims', ' .lk-post-card', 'border-radius', 'px' ],
            [ 'image_height', 'slider', ' .lk-post-card-image img', 'height', 'px' ],
            [ 'title_color', 'color', ' .lk-post-card-title a', 'color' ],
            [ 'title_hover_color', 'color', ' .lk-post-card-title a:hover', 'color' ],
            [ 'title_typography', 'typography', ' .lk-post-card-title' ],
            [ 'meta_color', 'color', ' .lk-post-card-meta', 'color' ],
            [ 'meta_typography', 'typography', ' .lk-post-card-meta' ],
            [ 'excerpt_color', 'color', ' .lk-post-card-excerpt', 'color' ],
            [ 'excerpt_typography', 'typography', ' .lk-post-card-excerpt' ],
            [ 'read_more_color', 'color', ' .lk-post-card-read-more', 'color' ],
            [ 'read_more_typography', 'typography', ' .lk-post-card-read-more' ],
            [ 'pagination_align', 'map', ' .lk-posts-pagination', 'justify-content', ALIGN_FLEX_MAP ],
            [ 'pagination_color', 'color', ' .lk-posts-pagination .page-numbers', 'color' ],
            [ 'pagination_active_color', 'color', ' .lk-posts-pagination .page-numbers.current', 'color' ],
            [ 'pagination_typography', 'typography', ' .lk-posts-pagination' ]
        ],
        'pagination': [
            [ 'align', 'map', ' .lk-pagination', 'justify-content', ALIGN_FLEX_MAP ],
            [ 'space', 'slider', ' .lk-pagination', 'gap', 'px' ],
            [ 'color', 'color', ' .lk-pagination .page-numbers', 'color' ],
            [ 'active_color', 'color', ' .lk-pagination .page-numbers.current', 'color' ],
            [ 'typography', 'typography', ' .lk-pagination' ]
        ],
        'accordion': [
            [ 'space_between', 'slider', ' .lk-accordion-item + .lk-accordion-item', 'margin-top', 'px' ],
            [ 'border_color', 'color', ' .lk-accordion-item', 'border-color' ],
            [ 'radius', 'slider', ' .lk-accordion-item', 'border-radius', 'px' ],
            [ 'title_color', 'color', ' .lk-accordion-title', 'color' ],
            [ 'title_bg', 'color', ' .lk-accordion-title', 'background-color' ],
            [ 'title_active_color', 'color', ' .lk-accordion-item.lk-open .lk-accordion-title', 'color' ],
            [ 'title_active_bg', 'color', ' .lk-accordion-item.lk-open .lk-accordion-title', 'background-color' ],
            [ 'title_typography', 'typography', ' .lk-accordion-title' ],
            [ 'title_padding', 'dims', ' .lk-accordion-title', 'padding', 'px' ],
            [ 'content_color', 'color', ' .lk-accordion-content', 'color' ],
            [ 'content_bg', 'color', ' .lk-accordion-content', 'background-color' ],
            [ 'content_typography', 'typography', ' .lk-accordion-content' ],
            [ 'content_padding', 'dims', ' .lk-accordion-content', 'padding', 'px' ]
        ],
        'tabs': [
            [ 'border_color', 'color', ' .lk-tabs-nav', 'border-color' ],
            [ 'border_color', 'color', ' .lk-tab-title.lk-active', 'border-color' ],
            [ 'border_color', 'color', ' .lk-tabs-panels', 'border-color' ],
            [ 'title_color', 'color', ' .lk-tab-title', 'color' ],
            [ 'title_active_color', 'color', ' .lk-tab-title.lk-active', 'color' ],
            [ 'title_active_bg', 'color', ' .lk-tab-title.lk-active', 'background-color' ],
            [ 'title_typography', 'typography', ' .lk-tab-title' ],
            [ 'content_color', 'color', ' .lk-tab-panel', 'color' ],
            [ 'content_bg', 'color', ' .lk-tabs-panels', 'background-color' ],
            [ 'content_typography', 'typography', ' .lk-tab-panel' ],
            [ 'content_padding', 'dims', ' .lk-tab-panel', 'padding', 'px' ]
        ],
        'counter': [
            [ 'align', 'raw', ' .lk-counter', 'text-align' ],
            [ 'number_color', 'color', ' .lk-counter-number-wrapper', 'color' ],
            [ 'number_typography', 'typography', ' .lk-counter-number-wrapper' ],
            [ 'title_color', 'color', ' .lk-counter-title', 'color' ],
            [ 'title_typography', 'typography', ' .lk-counter-title' ]
        ],
        'progress-bar': [
            [ 'bar_color', 'color', ' .lk-progress-fill', 'background-color' ],
            [ 'track_color', 'color', ' .lk-progress-track', 'background-color' ],
            [ 'height', 'slider', ' .lk-progress-track', 'height', 'px' ],
            [ 'radius', 'slider', ' .lk-progress-track', 'border-radius', 'px' ],
            [ 'radius', 'slider', ' .lk-progress-fill', 'border-radius', 'px' ],
            [ 'inner_color', 'color', ' .lk-progress-inner', 'color' ],
            [ 'title_color', 'color', ' .lk-progress-title', 'color' ],
            [ 'title_typography', 'typography', ' .lk-progress-header' ],
            [ 'percent_color', 'color', ' .lk-progress-percent', 'color' ]
        ],
        'alert': [
            [ 'bg_color', 'color', ' .lk-alert', 'background-color' ],
            [ 'border_color', 'color', ' .lk-alert', 'border-color' ],
            [ 'title_color', 'color', ' .lk-alert-title', 'color' ],
            [ 'title_typography', 'typography', ' .lk-alert-title' ],
            [ 'description_color', 'color', ' .lk-alert-description', 'color' ],
            [ 'description_typography', 'typography', ' .lk-alert-description' ]
        ],
        'star-rating': [
            [ 'align', 'raw', ' .lk-star-rating-wrapper', 'text-align' ],
            [ 'star_size', 'slider', ' .lk-star', 'font-size', 'px' ],
            [ 'star_gap', 'slider', ' .lk-star-rating', 'gap', 'px' ],
            [ 'star_color', 'color', ' .lk-star-fill', 'color' ],
            [ 'star_empty_color', 'color', ' .lk-star', 'color' ],
            [ 'title_color', 'color', ' .lk-star-rating-title', 'color' ],
            [ 'title_typography', 'typography', ' .lk-star-rating-title' ]
        ],
        'testimonial': [
            [ 'align', 'raw', ' .lk-testimonial', 'text-align' ],
            [ 'align', 'map', ' .lk-testimonial-meta', 'justify-content', ALIGN_FLEX_MAP ],
            [ 'content_color', 'color', ' .lk-testimonial-content', 'color' ],
            [ 'content_typography', 'typography', ' .lk-testimonial-content' ],
            [ 'image_size', 'slider', ' .lk-testimonial-image img', 'width', 'px' ],
            [ 'image_size', 'slider', ' .lk-testimonial-image img', 'height', 'px' ],
            [ 'name_color', 'color', ' .lk-testimonial-name', 'color' ],
            [ 'name_typography', 'typography', ' .lk-testimonial-name' ],
            [ 'job_color', 'color', ' .lk-testimonial-job', 'color' ],
            [ 'job_typography', 'typography', ' .lk-testimonial-job' ]
        ],
        'image-carousel': [
            [ 'slides_to_show', 'raw', ' .lk-carousel', '--lk-slides' ],
            [ 'gap', 'slider', ' .lk-carousel', '--lk-gap', 'px' ],
            [ 'height', 'slider', ' .lk-carousel-image', 'height', 'px' ],
            [ 'radius', 'slider', ' .lk-carousel-image', 'border-radius', 'px' ],
            [ 'arrow_color', 'color', ' .lk-carousel-arrow', 'color' ],
            [ 'arrow_bg', 'color', ' .lk-carousel-arrow', 'background-color' ],
            [ 'dot_color', 'color', ' .lk-carousel-dot', 'background-color' ],
            [ 'dot_active_color', 'color', ' .lk-carousel-dot.lk-active', 'background-color' ],
            [ 'caption_color', 'color', ' .lk-carousel-caption', 'color' ],
            [ 'caption_bg', 'color', ' .lk-carousel-caption', 'background-color' ],
            [ 'caption_typography', 'typography', ' .lk-carousel-caption' ]
        ],
        'image-gallery': [
            [ 'columns', 'grid_cols', ' .lk-gallery', 'grid-template-columns' ],
            [ 'gap', 'slider', ' .lk-gallery', 'gap', 'px' ],
            [ 'height', 'slider', ' .lk-gallery img', 'height', 'px' ],
            [ 'radius', 'slider', ' .lk-gallery img', 'border-radius', 'px' ],
            [ 'caption_color', 'color', ' .lk-gallery-caption', 'color' ],
            [ 'caption_typography', 'typography', ' .lk-gallery-caption' ]
        ],
        'google-maps': [
            [ 'height', 'slider', ' .lk-map iframe', 'height', 'px' ],
            [ 'radius', 'slider', ' .lk-map', 'border-radius', 'px' ]
        ],
        'contact-form': [
            [ 'row_gap', 'slider', ' .lk-form-fields', 'row-gap', 'px' ],
            [ 'label_color', 'color', ' .lk-form-label', 'color' ],
            [ 'label_typography', 'typography', ' .lk-form-label' ],
            [ 'field_text_color', 'color', ' .lk-form-input', 'color' ],
            [ 'field_bg', 'color', ' .lk-form-input', 'background-color' ],
            [ 'field_border_color', 'color', ' .lk-form-input', 'border-color' ],
            [ 'field_radius', 'slider', ' .lk-form-input', 'border-radius', 'px' ],
            [ 'button_color', 'color', ' .lk-form-submit', 'color' ],
            [ 'button_bg', 'color', ' .lk-form-submit', 'background-color' ],
            [ 'button_hover_bg', 'color', ' .lk-form-submit:hover', 'background-color' ],
            [ 'button_typography', 'typography', ' .lk-form-submit' ]
        ],
        'tooltip': [
            [ 'align', 'raw', ' .lk-tooltip-wrapper', 'text-align' ],
            [ 'trigger_color', 'color', ' .lk-tooltip-trigger', 'color' ],
            [ 'trigger_typography', 'typography', ' .lk-tooltip-trigger' ],
            [ 'tooltip_color', 'color', ' .lk-tooltip', 'color' ],
            [ 'tooltip_bg', 'color', ' .lk-tooltip', 'background-color' ],
            [ 'tooltip_bg', 'color', ' .lk-tooltip-wrap', '--lk-tooltip-bg' ],
            [ 'tooltip_typography', 'typography', ' .lk-tooltip' ],
            [ 'tooltip_width', 'slider', ' .lk-tooltip', 'width', 'px' ]
        ],
        'table-of-contents': [
            [ 'bg_color', 'color', ' .lk-toc', 'background-color' ],
            [ 'border_color', 'color', ' .lk-toc', 'border-color' ],
            [ 'title_color', 'color', ' .lk-toc-title', 'color' ],
            [ 'title_typography', 'typography', ' .lk-toc-title' ],
            [ 'link_color', 'color', ' .lk-toc-item a', 'color' ],
            [ 'link_hover_color', 'color', ' .lk-toc-item a:hover', 'color' ],
            [ 'link_typography', 'typography', ' .lk-toc-item' ]
        ],
        'toggle': [
            [ 'space_between', 'slider', ' .lk-accordion-item + .lk-accordion-item', 'margin-top', 'px' ],
            [ 'border_color', 'color', ' .lk-accordion-item', 'border-color' ],
            [ 'radius', 'slider', ' .lk-accordion-item', 'border-radius', 'px' ],
            [ 'title_color', 'color', ' .lk-accordion-title', 'color' ],
            [ 'title_bg', 'color', ' .lk-accordion-title', 'background-color' ],
            [ 'title_active_color', 'color', ' .lk-accordion-item.lk-open .lk-accordion-title', 'color' ],
            [ 'title_active_bg', 'color', ' .lk-accordion-item.lk-open .lk-accordion-title', 'background-color' ],
            [ 'title_typography', 'typography', ' .lk-accordion-title' ],
            [ 'title_padding', 'dims', ' .lk-accordion-title', 'padding', 'px' ],
            [ 'content_color', 'color', ' .lk-accordion-content', 'color' ],
            [ 'content_bg', 'color', ' .lk-accordion-content', 'background-color' ],
            [ 'content_typography', 'typography', ' .lk-accordion-content' ],
            [ 'content_padding', 'dims', ' .lk-accordion-content', 'padding', 'px' ]
        ]
    };

    function hasSize( v ) {
        return v !== undefined && v !== null && v !== '';
    }

    function declarativeDeclarations( row, val ) {
        var kind = row[1], prop = row[3], opt = row[4];
        var out = [];
        if ( kind === 'raw' ) {
            if ( typeof val === 'object' ) return out;
            if ( opt !== undefined && String( val ) === String( opt ) ) return out;
            out.push( prop + ': ' + val );
        } else if ( kind === 'color' ) {
            if ( typeof val !== 'string' ) return out;
            out.push( prop + ': ' + resolveGlobalValue( val ) );
        } else if ( kind === 'map' ) {
            if ( opt && opt[ val ] ) out.push( prop + ': ' + opt[ val ] );
        } else if ( kind === 'slider' ) {
            if ( typeof val === 'object' ) {
                if ( hasSize( val.size ) ) out.push( prop + ': ' + parseFloat( val.size ) + ( val.unit || opt || 'px' ) );
            } else if ( hasSize( val ) && ! isNaN( parseFloat( val ) ) ) {
                out.push( prop + ': ' + parseFloat( val ) + ( opt || 'px' ) );
            }
        } else if ( kind === 'box_shadow' ) {
            if ( typeof val !== 'object' || ! val || ! val.color ) return out;
            out.push( 'box-shadow: ' + ( val.position === 'inset' ? 'inset ' : '' ) +
                ( parseFloat( val.horizontal ) || 0 ) + 'px ' + ( parseFloat( val.vertical ) || 0 ) + 'px ' +
                ( parseFloat( val.blur ) || 0 ) + 'px ' + ( parseFloat( val.spread ) || 0 ) + 'px ' + resolveGlobalValue( val.color ) );
        } else if ( kind === 'grid_cols' ) {
            var cols = parseInt( ( typeof val === 'object' ) ? val.size : val, 10 );
            if ( cols > 0 ) out.push( prop + ': repeat(' + cols + ', minmax(0, 1fr))' );
        } else if ( kind === 'unitless' ) {
            var n = ( typeof val === 'object' ) ? val.size : val;
            if ( hasSize( n ) && ! isNaN( parseFloat( n ) ) ) out.push( prop + ': ' + parseFloat( n ) );
        } else if ( kind === 'dims' ) {
            if ( typeof val !== 'object' ) return out;
            if ( ! hasSize( val.top ) && ! hasSize( val.right ) && ! hasSize( val.bottom ) && ! hasSize( val.left ) ) return out;
            var u = val.unit || opt || 'px';
            var side = function( s ) { return ( hasSize( s ) ? parseFloat( s ) : 0 ) + u; };
            out.push( prop + ': ' + side( val.top ) + ' ' + side( val.right ) + ' ' + side( val.bottom ) + ' ' + side( val.left ) );
        } else if ( kind === 'typography' ) {
            if ( typeof val !== 'object' ) return out;
            if ( val.family ) out.push( 'font-family: ' + val.family );
            if ( val.size && hasSize( val.size.size ) ) out.push( 'font-size: ' + parseFloat( val.size.size ) + ( val.size.unit || 'px' ) );
            if ( val.weight ) out.push( 'font-weight: ' + val.weight );
            if ( val.transform ) out.push( 'text-transform: ' + val.transform );
            if ( val.style ) out.push( 'font-style: ' + val.style );
            if ( val.decoration ) out.push( 'text-decoration: ' + val.decoration );
            if ( val.line_height && hasSize( val.line_height.size ) ) out.push( 'line-height: ' + parseFloat( val.line_height.size ) + ( val.line_height.unit || 'em' ) );
            if ( val.letter_spacing && hasSize( val.letter_spacing.size ) ) out.push( 'letter-spacing: ' + parseFloat( val.letter_spacing.size ) + ( val.letter_spacing.unit || 'px' ) );
            if ( val.word_spacing && hasSize( val.word_spacing.size ) ) out.push( 'word-spacing: ' + parseFloat( val.word_spacing.size ) + ( val.word_spacing.unit || 'px' ) );
        } else if ( kind === 'text_shadow' ) {
            if ( typeof val !== 'object' || ! val.color ) return out;
            out.push( 'text-shadow: ' + ( parseFloat( val.horizontal ) || 0 ) + 'px ' + ( parseFloat( val.vertical ) || 0 ) + 'px ' + ( parseFloat( val.blur ) || 0 ) + 'px ' + resolveGlobalValue( val.color ) );
        } else if ( kind === 'text_stroke' ) {
            if ( typeof val !== 'object' || ! hasSize( val.width ) || ! parseFloat( val.width ) ) return out;
            out.push( '-webkit-text-stroke-width: ' + parseFloat( val.width ) + ( val.unit || 'px' ) );
            if ( val.color ) out.push( '-webkit-text-stroke-color: ' + resolveGlobalValue( val.color ) );
        }
        return out;
    }

    function applyDeclarativeRules( rows, settings, device, subRules ) {
        for ( var r = 0; r < rows.length; r++ ) {
            var row = rows[r];
            var val = getDeviceExplicitValue( settings, row[0], device );
            if ( val === undefined ) continue;
            var decls = declarativeDeclarations( row, val );
            if ( ! decls.length ) continue;
            subRules[ row[2] ] = ( subRules[ row[2] ] || [] ).concat( decls );
        }
    }

    /**
     * Generate CSS rules for a single element on a specific device.
     * Only outputs properties defined or overridden for that device.
     */
    function generateDeviceRules( el, device ) {
        var baseRules        = [];
        var innerRules       = [];
        var hoverRules       = [];
        var subRules         = {};
        var parentRules      = [];
        var hoverParentRules = [];
        var settings         = el.settings || {};
        var elType           = el.elType || '';
        var widgetType       = el.widgetType || '';

        // -------------------------------------------------------------
        // CONTAINER RULES (Section 9 & Problem 2)
        // Boxed container: outer element is 100% wide for full-width background,
        //   padding and min-height apply to outer. Direction, justify, align,
        //   gaps and wrap apply to .lk-con-inner, which has max-width and margin 0 auto.
        // Full Width container: no inner wrapper; flex/grid settings apply to
        //   the container itself and width controls outer element width.
        // -------------------------------------------------------------
        if ( elType === 'container' || elType === 'section' ) {
            var explicitType = getDeviceExplicitValue( settings, 'container_type', device );
            var containerType = explicitType || settings.container_type || 'flexbox';
            var explicitContentWidth = getDeviceExplicitValue( settings, 'content_width', device );
            var defaultContentWidth = ( elType === 'section' ) ? 'boxed' : 'full';
            var contentWidth  = explicitContentWidth || settings.content_width || defaultContentWidth;
            var widthVal      = getDeviceExplicitValue( settings, 'width', device );
            var minHeightVal  = getDeviceExplicitValue( settings, 'min_height', device );
            var overflowVal   = getDeviceExplicitValue( settings, 'overflow', device );

            var isBoxed = ( contentWidth === 'boxed' );
            var targetRules = isBoxed ? innerRules : baseRules;

            if ( containerType === 'grid' ) {
                if ( device === 'desktop' || explicitType === 'grid' ) {
                    targetRules.push( 'display: grid' );
                }
                var cols = getDeviceExplicitValue( settings, 'grid_columns', device ) || ( device === 'desktop' ? ( settings.grid_columns || 3 ) : '' );
                var rows = getDeviceExplicitValue( settings, 'grid_rows', device ) || ( device === 'desktop' ? ( settings.grid_rows || 2 ) : '' );
                if ( cols ) targetRules.push( 'grid-template-columns: repeat(' + cols + ', minmax(0, 1fr))' );
                if ( rows ) targetRules.push( 'grid-template-rows: repeat(' + rows + ', minmax(0, 1fr))' );

                var autoFlow = getDeviceExplicitValue( settings, 'grid_auto_flow', device );
                if ( autoFlow ) targetRules.push( 'grid-auto-flow: ' + autoFlow );

                var jItems = getDeviceExplicitValue( settings, 'justify_items', device );
                if ( jItems ) targetRules.push( 'justify-items: ' + jItems );

                var aItemsGrid = getDeviceExplicitValue( settings, 'align_items_grid', device );
                if ( aItemsGrid ) targetRules.push( 'align-items: ' + aItemsGrid );

                var gGaps = getDeviceExplicitValue( settings, 'grid_gaps', device );
                if ( gGaps && typeof gGaps === 'object' ) {
                    var gUnit = gGaps.unit || 'px';
                    var gRow = ( gGaps.row !== undefined && gGaps.row !== '' ) ? gGaps.row + gUnit : '';
                    var gCol = ( gGaps.column !== undefined && gGaps.column !== '' ) ? gGaps.column + gUnit : '';
                    if ( gRow && gCol ) targetRules.push( 'gap: ' + gRow + ' ' + gCol );
                    else if ( gRow ) targetRules.push( 'row-gap: ' + gRow );
                    else if ( gCol ) targetRules.push( 'column-gap: ' + gCol );
                }
            } else {
                // Flexbox
                if ( device === 'desktop' || explicitType === 'flexbox' ) {
                    targetRules.push( 'display: flex' );
                }

                var dir = getDeviceExplicitValue( settings, 'flex_direction', device ) || ( device === 'desktop' ? ( settings.flex_direction || 'column' ) : '' );
                if ( dir ) targetRules.push( 'flex-direction: ' + dir );

                var jContent = getDeviceExplicitValue( settings, 'justify_content', device );
                if ( jContent ) targetRules.push( 'justify-content: ' + jContent );

                var aItemsFlex = getDeviceExplicitValue( settings, 'align_items', device );
                if ( aItemsFlex ) targetRules.push( 'align-items: ' + aItemsFlex );

                var fWrap = getDeviceExplicitValue( settings, 'flex_wrap', device );
                if ( fWrap ) targetRules.push( 'flex-wrap: ' + fWrap );

                // Align Content only when flex_wrap is wrap (Section 3: false condition outputs no CSS)
                if ( fWrap === 'wrap' || ( ! fWrap && settings.flex_wrap === 'wrap' ) ) {
                    var aContent = getDeviceExplicitValue( settings, 'align_content', device );
                    if ( aContent ) targetRules.push( 'align-content: ' + aContent );
                }

                var fGaps = getDeviceExplicitValue( settings, 'flex_gap', device );
                if ( fGaps && typeof fGaps === 'object' ) {
                    var fUnit = fGaps.unit || 'px';
                    var fRow = ( fGaps.row !== undefined && fGaps.row !== '' ) ? fGaps.row + fUnit : '';
                    var fCol = ( fGaps.column !== undefined && fGaps.column !== '' ) ? fGaps.column + fUnit : '';
                    if ( fRow && fCol ) targetRules.push( 'gap: ' + fRow + ' ' + fCol );
                    else if ( fRow ) targetRules.push( 'row-gap: ' + fRow );
                    else if ( fCol ) targetRules.push( 'column-gap: ' + fCol );
                } else if ( typeof fGaps === 'number' || ( typeof fGaps === 'string' && fGaps !== '' ) ) {
                    targetRules.push( 'gap: ' + fGaps + 'px' );
                }
            }

            // Boxed vs Full Width sizing
            if ( device === 'desktop' || explicitContentWidth || widthVal ) {
                if ( isBoxed ) {
                    var boxWidth = '1140px';
                    if ( widthVal && typeof widthVal === 'object' && widthVal.size ) {
                        boxWidth = widthVal.size + ( widthVal.unit || 'px' );
                    } else if ( widthVal && ( typeof widthVal === 'string' || typeof widthVal === 'number' ) ) {
                        boxWidth = widthVal + 'px';
                    }
                    baseRules.push( 'width: 100%' );
                    baseRules.push( 'display: flex' );
                    innerRules.push( 'max-width: ' + boxWidth );
                    innerRules.push( 'margin-left: auto' );
                    innerRules.push( 'margin-right: auto' );
                    innerRules.push( 'width: 100%' );
                    innerRules.push( 'min-height: inherit' );
                    innerRules.push( 'align-self: stretch' );
                } else {
                    var fullW = '100%';
                    if ( widthVal && typeof widthVal === 'object' && widthVal.size ) {
                        fullW = widthVal.size + ( widthVal.unit || '%' );
                    } else if ( widthVal && ( typeof widthVal === 'string' || typeof widthVal === 'number' ) ) {
                        fullW = widthVal + '%';
                    }
                    baseRules.push( 'width: ' + fullW );
                    baseRules.push( 'max-width: 100%' );
                }
            }

            // Min Height (applies to outer container)
            if ( minHeightVal ) {
                if ( typeof minHeightVal === 'object' && minHeightVal.size !== undefined && minHeightVal.size !== '' ) {
                    baseRules.push( 'min-height: ' + minHeightVal.size + ( minHeightVal.unit || 'px' ) );
                } else if ( typeof minHeightVal === 'string' || typeof minHeightVal === 'number' ) {
                    baseRules.push( 'min-height: ' + minHeightVal + 'px' );
                }
            }

            // Overflow
            if ( overflowVal && overflowVal !== 'default' ) {
                baseRules.push( 'overflow: ' + overflowVal );
            }
        }

        // -------------------------------------------------------------
        // HEADING RULES (Section 10)
        // -------------------------------------------------------------
        // Heading: only alignment belongs on the wrapper. The text styles must target
        // .lk-heading-title itself (see POST_WIDGET_CSS_RULES['heading']) — on the wrapper the
        // theme's own h1..h6 rules win and font size/weight appeared to do nothing.
        if ( elType === 'widget' && widgetType === 'heading' ) {
            var align = getDeviceExplicitValue( settings, 'align', device );
            if ( align ) baseRules.push( 'text-align: ' + align );
        }

        // -------------------------------------------------------------
        // TEXT EDITOR RULES (Part 2)
        // -------------------------------------------------------------
        if ( elType === 'widget' && widgetType === 'text-editor' ) {
            var teAlign = getDeviceExplicitValue( settings, 'align', device );
            if ( teAlign ) baseRules.push( 'text-align: ' + teAlign );

            var teColor = getDeviceExplicitValue( settings, 'text_color', device );
            if ( teColor ) baseRules.push( 'color: ' + resolveGlobalValue( teColor ) );

            var teTypo = getDeviceExplicitValue( settings, 'typography', device );
            if ( teTypo && typeof teTypo === 'object' ) {
                if ( teTypo.family ) baseRules.push( 'font-family: ' + teTypo.family );
                if ( teTypo.size && teTypo.size.size ) baseRules.push( 'font-size: ' + teTypo.size.size + ( teTypo.size.unit || 'px' ) );
                if ( teTypo.weight ) baseRules.push( 'font-weight: ' + teTypo.weight );
                if ( teTypo.transform ) baseRules.push( 'text-transform: ' + teTypo.transform );
                if ( teTypo.style ) baseRules.push( 'font-style: ' + teTypo.style );
                if ( teTypo.line_height && teTypo.line_height.size ) baseRules.push( 'line-height: ' + teTypo.line_height.size + ( teTypo.line_height.unit || 'em' ) );
                if ( teTypo.letter_spacing && teTypo.letter_spacing.size ) baseRules.push( 'letter-spacing: ' + teTypo.letter_spacing.size + ( teTypo.letter_spacing.unit || 'px' ) );
                if ( teTypo.word_spacing && teTypo.word_spacing.size ) baseRules.push( 'word-spacing: ' + teTypo.word_spacing.size + ( teTypo.word_spacing.unit || 'px' ) );
            }

            var teShadow = getDeviceExplicitValue( settings, 'text_shadow', device );
            if ( teShadow && typeof teShadow === 'object' && teShadow.color ) {
                var teSH = teShadow.horizontal || 0;
                var teSV = teShadow.vertical || 0;
                var teSB = teShadow.blur || 0;
                baseRules.push( 'text-shadow: ' + teSH + 'px ' + teSV + 'px ' + teSB + 'px ' + resolveGlobalValue( teShadow.color ) );
            }

            var teCols = getDeviceExplicitValue( settings, 'columns', device );
            if ( teCols && teCols !== '1' ) {
                baseRules.push( 'columns: ' + parseInt( teCols, 10 ) );
                baseRules.push( '-webkit-columns: ' + parseInt( teCols, 10 ) );
                var teGap = getDeviceExplicitValue( settings, 'columns_gap', device );
                if ( teGap && typeof teGap === 'object' && teGap.size !== undefined && teGap.size !== '' ) {
                    var teGVal = teGap.size + ( teGap.unit || 'px' );
                    baseRules.push( 'column-gap: ' + teGVal );
                    baseRules.push( '-webkit-column-gap: ' + teGVal );
                }
            }

            var teSpace = getDeviceExplicitValue( settings, 'space', device );
            if ( teSpace && typeof teSpace === 'object' && teSpace.size !== undefined && teSpace.size !== '' ) {
                subRules[' p:not(:last-child)'] = [ 'margin-bottom: ' + teSpace.size + ( teSpace.unit || 'px' ) ];
            }

            var teLinkCol = getDeviceExplicitValue( settings, 'link_color', device );
            if ( teLinkCol ) {
                subRules[' a'] = [ 'color: ' + resolveGlobalValue( teLinkCol ) ];
            }
            var teLinkHovCol = getDeviceExplicitValue( settings, 'link_hover_color', device );
            if ( teLinkHovCol ) {
                subRules[' a:hover'] = [ 'color: ' + resolveGlobalValue( teLinkHovCol ) ];
            }

            var dcRules = [];
            var dcView = settings.drop_cap_view || 'default';
            var dcPri = getDeviceExplicitValue( settings, 'drop_cap_primary_color', device );
            var dcSec = getDeviceExplicitValue( settings, 'drop_cap_secondary_color', device );

            if ( dcPri ) {
                var cPri = resolveGlobalValue( dcPri );
                if ( dcView === 'stacked' ) {
                    dcRules.push( 'background-color: ' + cPri );
                } else if ( dcView === 'framed' ) {
                    dcRules.push( 'color: ' + cPri );
                    dcRules.push( 'border-color: ' + cPri );
                } else {
                    dcRules.push( 'color: ' + cPri );
                }
            }
            if ( dcSec ) {
                var cSec = resolveGlobalValue( dcSec );
                if ( dcView === 'stacked' ) {
                    dcRules.push( 'color: ' + cSec );
                } else if ( dcView === 'framed' ) {
                    dcRules.push( 'background-color: ' + cSec );
                }
            }

            var dcSize = getDeviceExplicitValue( settings, 'drop_cap_size', device );
            if ( dcSize && typeof dcSize === 'object' && dcSize.size !== undefined && dcSize.size !== '' ) {
                dcRules.push( 'font-size: ' + dcSize.size + ( dcSize.unit || 'em' ) );
            }

            var dcSpace = getDeviceExplicitValue( settings, 'drop_cap_space', device );
            if ( dcSpace && typeof dcSpace === 'object' && dcSpace.size !== undefined && dcSpace.size !== '' ) {
                dcRules.push( 'margin-right: ' + dcSpace.size + ( dcSpace.unit || 'px' ) );
            }

            var dcRadius = getDeviceExplicitValue( settings, 'drop_cap_border_radius', device );
            if ( dcRadius && typeof dcRadius === 'object' && dcRadius.size !== undefined && dcRadius.size !== '' ) {
                dcRules.push( 'border-radius: ' + dcRadius.size + ( dcRadius.unit || 'px' ) );
            }

            var dcBorderW = getDeviceExplicitValue( settings, 'drop_cap_border_width', device );
            if ( dcBorderW && typeof dcBorderW === 'object' && dcBorderW.size !== undefined && dcBorderW.size !== '' ) {
                dcRules.push( 'border-width: ' + dcBorderW.size + ( dcBorderW.unit || 'px' ) );
                dcRules.push( 'border-style: solid' );
            }

            if ( dcRules.length ) {
                subRules[' .lk-drop-cap'] = dcRules;
            }
        }

        // -------------------------------------------------------------
        // IMAGE RULES (Part 2)
        // -------------------------------------------------------------
        if ( elType === 'widget' && widgetType === 'image' ) {
            var imgAlign = getDeviceExplicitValue( settings, 'align', device );
            if ( imgAlign ) baseRules.push( 'text-align: ' + imgAlign );

            var imgRules = [];
            var imgHoverRules = [];

            var imgW = getDeviceExplicitValue( settings, 'width', device );
            if ( imgW && typeof imgW === 'object' && imgW.size !== undefined && imgW.size !== '' ) {
                imgRules.push( 'width: ' + imgW.size + ( imgW.unit || '%' ) );
            }
            var imgMaxW = getDeviceExplicitValue( settings, 'max_width', device );
            if ( imgMaxW && typeof imgMaxW === 'object' && imgMaxW.size !== undefined && imgMaxW.size !== '' ) {
                imgRules.push( 'max-width: ' + imgMaxW.size + ( imgMaxW.unit || '%' ) );
            }
            var imgH = getDeviceExplicitValue( settings, 'height', device );
            if ( imgH && typeof imgH === 'object' && imgH.size !== undefined && imgH.size !== '' ) {
                imgRules.push( 'height: ' + imgH.size + ( imgH.unit || 'px' ) );
            }

            var imgFit = getDeviceExplicitValue( settings, 'object_fit', device );
            if ( imgFit ) imgRules.push( 'object-fit: ' + imgFit );

            var imgPos = getDeviceExplicitValue( settings, 'object_position', device );
            if ( imgPos && imgFit === 'cover' ) imgRules.push( 'object-position: ' + imgPos );

            var imgOp = getDeviceExplicitValue( settings, 'opacity', device );
            if ( imgOp && typeof imgOp === 'object' && imgOp.size !== undefined && imgOp.size !== '' ) {
                imgRules.push( 'opacity: ' + parseFloat( imgOp.size ) );
            } else if ( imgOp !== undefined && imgOp !== null && imgOp !== '' ) {
                imgRules.push( 'opacity: ' + parseFloat( imgOp ) );
            }

            var imgOpHov = getDeviceExplicitValue( settings, 'opacity_hover', device );
            if ( imgOpHov && typeof imgOpHov === 'object' && imgOpHov.size !== undefined && imgOpHov.size !== '' ) {
                imgHoverRules.push( 'opacity: ' + parseFloat( imgOpHov.size ) );
            } else if ( imgOpHov !== undefined && imgOpHov !== null && imgOpHov !== '' ) {
                imgHoverRules.push( 'opacity: ' + parseFloat( imgOpHov ) );
            }

            var imgTrans = getDeviceExplicitValue( settings, 'hover_transition_duration', device );
            if ( imgTrans && typeof imgTrans === 'object' && imgTrans.size !== undefined && imgTrans.size !== '' ) {
                imgRules.push( 'transition: all ' + parseInt( imgTrans.size, 10 ) + 'ms' );
            } else if ( imgTrans ) {
                imgRules.push( 'transition: all ' + parseInt( imgTrans, 10 ) + 'ms' );
            }

            if ( imgRules.length ) {
                subRules[' img'] = imgRules;
            }
            if ( imgHoverRules.length ) {
                subRules[' img:hover'] = imgHoverRules;
            }

            // Caption styles
            var captionRules = [];
            var capAlign = getDeviceExplicitValue( settings, 'caption_align', device );
            if ( capAlign ) captionRules.push( 'text-align: ' + capAlign );

            var capCol = getDeviceExplicitValue( settings, 'caption_color', device );
            if ( capCol ) captionRules.push( 'color: ' + resolveGlobalValue( capCol ) );

            var capSpace = getDeviceExplicitValue( settings, 'caption_space', device );
            if ( capSpace && typeof capSpace === 'object' && capSpace.size !== undefined && capSpace.size !== '' ) {
                captionRules.push( 'margin-top: ' + capSpace.size + ( capSpace.unit || 'px' ) );
            }

            if ( captionRules.length ) {
                subRules[' .lk-image-caption'] = captionRules;
            }
        }

        // -------------------------------------------------------------
        // BUTTON RULES (Part 2)
        // -------------------------------------------------------------
        if ( elType === 'widget' && widgetType === 'button' ) {
            var btnAlign = getDeviceExplicitValue( settings, 'align', device );
            if ( btnAlign ) {
                if ( btnAlign === 'justify' ) {
                    baseRules.push( 'text-align: justify' );
                    subRules[' .lk-button'] = ( subRules[' .lk-button'] || [] ).concat( [ 'width: 100%', 'display: flex' ] );
                } else {
                    baseRules.push( 'text-align: ' + btnAlign );
                }
            }

            var btnRules = [];
            var btnHoverRules = [];

            var btnColor = getDeviceExplicitValue( settings, 'button_text_color', device );
            if ( btnColor ) {
                btnRules.push( 'color: ' + resolveGlobalValue( btnColor ) );
                btnRules.push( 'fill: ' + resolveGlobalValue( btnColor ) );
            }

            // The widget saves background_color / background_type / background_gradient_*;
            // older data used button_background_color, so both are accepted.
            var btnBgType = getDeviceExplicitValue( settings, 'button_background_type', device ) || getDeviceExplicitValue( settings, 'background_type', device ) || 'classic';
            var btnBg = getDeviceExplicitValue( settings, 'button_background_color', device );
            if ( btnBg === undefined || btnBg === '' ) btnBg = getDeviceExplicitValue( settings, 'background_color', device );
            if ( btnBgType === 'gradient' ) {
                var g1 = getDeviceExplicitValue( settings, 'button_background_gradient_color', device ) || getDeviceExplicitValue( settings, 'background_gradient_color', device ) || btnBg || '#38A8F5';
                var g2 = getDeviceExplicitValue( settings, 'button_background_gradient_second_color', device ) || getDeviceExplicitValue( settings, 'background_gradient_second_color', device ) || '#ffffff';
                var gType = getDeviceExplicitValue( settings, 'button_background_gradient_type', device ) || getDeviceExplicitValue( settings, 'background_gradient_type', device ) || 'linear';
                var gAngleRaw = getDeviceExplicitValue( settings, 'button_background_gradient_angle', device ) || getDeviceExplicitValue( settings, 'background_gradient_angle', device );
                var gAngle = ( gAngleRaw && typeof gAngleRaw === 'object' ) ? gAngleRaw.size : gAngleRaw;
                if ( gAngle === undefined || gAngle === '' ) gAngle = 180;
                btnRules.push( 'background-image: ' + ( gType === 'radial'
                    ? 'radial-gradient(circle, ' + resolveGlobalValue( g1 ) + ' 0%, ' + resolveGlobalValue( g2 ) + ' 100%)'
                    : 'linear-gradient(' + parseFloat( gAngle ) + 'deg, ' + resolveGlobalValue( g1 ) + ' 0%, ' + resolveGlobalValue( g2 ) + ' 100%)' ) );
            } else if ( btnBg ) {
                btnRules.push( 'background-color: ' + resolveGlobalValue( btnBg ) );
            }

            var btnHovColor = getDeviceExplicitValue( settings, 'button_hover_text_color', device ) || getDeviceExplicitValue( settings, 'hover_color', device );
            if ( btnHovColor ) {
                btnHoverRules.push( 'color: ' + resolveGlobalValue( btnHovColor ) );
                btnHoverRules.push( 'fill: ' + resolveGlobalValue( btnHovColor ) );
            }

            var btnHovBg = getDeviceExplicitValue( settings, 'button_hover_background_color', device ) || getDeviceExplicitValue( settings, 'button_background_hover_color', device );
            if ( btnHovBg ) {
                btnHoverRules.push( 'background-color: ' + resolveGlobalValue( btnHovBg ) );
            }

            var btnBdrStyle = getDeviceExplicitValue( settings, 'button_border_border', device );
            var btnBdrStyle = getDeviceExplicitValue( settings, 'button_border_border', device ) || getDeviceExplicitValue( settings, 'border_type', device );
            if ( btnBdrStyle && btnBdrStyle !== 'none' && btnBdrStyle !== 'default' ) {
                btnRules.push( 'border-style: ' + btnBdrStyle );
                var bWidth = getDeviceExplicitValue( settings, 'button_border_width', device ) || getDeviceExplicitValue( settings, 'border_width', device );
                if ( bWidth && typeof bWidth === 'object' ) {
                    var bu = bWidth.unit || 'px';
                    btnRules.push( 'border-width: ' + ( parseFloat( bWidth.top ) || 0 ) + bu + ' ' + ( parseFloat( bWidth.right ) || 0 ) + bu + ' ' + ( parseFloat( bWidth.bottom ) || 0 ) + bu + ' ' + ( parseFloat( bWidth.left ) || 0 ) + bu );
                }
                var bCol = getDeviceExplicitValue( settings, 'button_border_color', device ) || getDeviceExplicitValue( settings, 'border_color', device );
                if ( bCol ) {
                    btnRules.push( 'border-color: ' + resolveGlobalValue( bCol ) );
                }
            }

            var btnBdrHov = getDeviceExplicitValue( settings, 'button_hover_border_color', device ) || getDeviceExplicitValue( settings, 'border_hover_color', device );
            if ( btnBdrHov ) {
                btnHoverRules.push( 'border-color: ' + resolveGlobalValue( btnBdrHov ) );
            }

            var bRadius = getDeviceExplicitValue( settings, 'button_border_radius', device ) || getDeviceExplicitValue( settings, 'border_radius', device );
            if ( bRadius && typeof bRadius === 'object' ) {
                var ru = bRadius.unit || 'px';
                btnRules.push( 'border-radius: ' + ( parseFloat( bRadius.top ) || 0 ) + ru + ' ' + ( parseFloat( bRadius.right ) || 0 ) + ru + ' ' + ( parseFloat( bRadius.bottom ) || 0 ) + ru + ' ' + ( parseFloat( bRadius.left ) || 0 ) + ru );
            }

            var btnPad = getDeviceExplicitValue( settings, 'button_padding', device ) || getDeviceExplicitValue( settings, 'padding', device );
            if ( btnPad && typeof btnPad === 'object' ) {
                var pu = btnPad.unit || 'px';
                btnRules.push( 'padding: ' + ( parseFloat( btnPad.top ) || 0 ) + pu + ' ' + ( parseFloat( btnPad.right ) || 0 ) + pu + ' ' + ( parseFloat( btnPad.bottom ) || 0 ) + pu + ' ' + ( parseFloat( btnPad.left ) || 0 ) + pu );
            }

            var btnTypo = getDeviceExplicitValue( settings, 'typography', device );
            if ( btnTypo && typeof btnTypo === 'object' ) {
                if ( btnTypo.family ) btnRules.push( 'font-family: ' + btnTypo.family );
                if ( btnTypo.size && btnTypo.size.size ) btnRules.push( 'font-size: ' + btnTypo.size.size + ( btnTypo.size.unit || 'px' ) );
                if ( btnTypo.weight ) btnRules.push( 'font-weight: ' + btnTypo.weight );
                if ( btnTypo.transform ) btnRules.push( 'text-transform: ' + btnTypo.transform );
                if ( btnTypo.style ) btnRules.push( 'font-style: ' + btnTypo.style );
                if ( btnTypo.line_height && btnTypo.line_height.size ) btnRules.push( 'line-height: ' + btnTypo.line_height.size + ( btnTypo.line_height.unit || 'em' ) );
                if ( btnTypo.letter_spacing && btnTypo.letter_spacing.size ) btnRules.push( 'letter-spacing: ' + btnTypo.letter_spacing.size + 'px' );
                if ( btnTypo.word_spacing && btnTypo.word_spacing.size ) btnRules.push( 'word-spacing: ' + btnTypo.word_spacing.size + 'px' );
            }

            var btnShadow = getDeviceExplicitValue( settings, 'text_shadow', device );
            if ( btnShadow && typeof btnShadow === 'object' && btnShadow.color ) {
                var bsH = btnShadow.horizontal || 0;
                var bsV = btnShadow.vertical || 0;
                var bsB = btnShadow.blur || 0;
                btnRules.push( 'text-shadow: ' + bsH + 'px ' + bsV + 'px ' + bsB + 'px ' + resolveGlobalValue( btnShadow.color ) );
            }

            var btnBoxShadow = getDeviceExplicitValue( settings, 'button_box_shadow', device ) || getDeviceExplicitValue( settings, 'box_shadow', device );
            if ( btnBoxShadow && typeof btnBoxShadow === 'object' && btnBoxShadow.color ) {
                var bshH = btnBoxShadow.horizontal || 0;
                var bshV = btnBoxShadow.vertical || 0;
                var bshB = btnBoxShadow.blur || 0;
                var bshS = btnBoxShadow.spread || 0;
                var bshInset = ( btnBoxShadow.position === 'inset' ) ? 'inset ' : '';
                btnRules.push( 'box-shadow: ' + bshInset + bshH + 'px ' + bshV + 'px ' + bshB + 'px ' + bshS + 'px ' + resolveGlobalValue( btnBoxShadow.color ) );
            }

            if ( btnRules.length ) {
                subRules[' .lk-button'] = btnRules;
            }
            if ( btnHoverRules.length ) {
                subRules[' .lk-button:hover'] = btnHoverRules;
            }

            var indent = getDeviceExplicitValue( settings, 'icon_indent', device );
            if ( indent && typeof indent === 'object' && indent.size !== undefined && indent.size !== '' ) {
                var iVal = indent.size + ( indent.unit || 'px' );
                subRules[' .lk-button-icon.lk-align-icon-right'] = [ 'margin-left: ' + iVal ];
                subRules[' .lk-button-icon.lk-align-icon-left'] = [ 'margin-right: ' + iVal ];
            }
        }

        // Spacer widget
        if ( elType === 'widget' && widgetType === 'spacer' ) {
            var space = getDeviceExplicitValue( settings, 'space', device );
            if ( space && typeof space === 'object' && space.size !== undefined && space.size !== '' ) {
                subRules[' .lk-spacer'] = [ 'height: ' + space.size + ( space.unit || 'px' ) ];
            } else if ( typeof space === 'number' || ( typeof space === 'string' && space !== '' ) ) {
                subRules[' .lk-spacer'] = [ 'height: ' + space + 'px' ];
            }
        }

        // Divider widget
        if ( elType === 'widget' && widgetType === 'divider' ) {
            var dAlign = getDeviceExplicitValue( settings, 'align', device );
            if ( dAlign === 'left' ) {
                subRules[' .lk-divider'] = subRules[' .lk-divider'] || [];
                subRules[' .lk-divider'].push( 'margin-left: 0', 'margin-right: auto' );
            } else if ( dAlign === 'right' ) {
                subRules[' .lk-divider'] = subRules[' .lk-divider'] || [];
                subRules[' .lk-divider'].push( 'margin-left: auto', 'margin-right: 0' );
            } else if ( dAlign === 'center' ) {
                subRules[' .lk-divider'] = subRules[' .lk-divider'] || [];
                subRules[' .lk-divider'].push( 'margin-left: auto', 'margin-right: auto' );
            }

            var dWidth = getDeviceExplicitValue( settings, 'width', device );
            if ( dWidth && typeof dWidth === 'object' && dWidth.size !== undefined && dWidth.size !== '' ) {
                subRules[' .lk-divider'] = subRules[' .lk-divider'] || [];
                subRules[' .lk-divider'].push( 'width: ' + dWidth.size + ( dWidth.unit || '%' ) );
            }
            var dColor  = getDeviceExplicitValue( settings, 'color', device );
            var dWeight = getDeviceExplicitValue( settings, 'weight', device );
            var dwRaw   = ( dWeight && typeof dWeight === 'object' ) ? dWeight.size : dWeight;
            var dwUnit  = ( dWeight && typeof dWeight === 'object' && dWeight.unit ) ? dWeight.unit : 'px';
            var sepRules = [];
            if ( dColor ) sepRules.push( 'border-top-color: ' + resolveGlobalValue( dColor ) );
            // Only when the user set it: writing a default on every device made the editor CSS
            // differ from the saved CSS on tablet/mobile.
            if ( dwRaw !== undefined && dwRaw !== null && dwRaw !== '' ) sepRules.push( 'border-top-width: ' + parseFloat( dwRaw ) + dwUnit );
            if ( sepRules.length ) subRules[' .lk-divider-separator'] = sepRules;

            var dGap = getDeviceExplicitValue( settings, 'gap', device );
            if ( dGap && typeof dGap === 'object' && dGap.size !== undefined && dGap.size !== '' ) {
                var dgVal = dGap.size + ( dGap.unit || 'px' );
                subRules[' .lk-divider'] = subRules[' .lk-divider'] || [];
                subRules[' .lk-divider'].push( 'padding-top: ' + dgVal );
                subRules[' .lk-divider'].push( 'padding-bottom: ' + dgVal );
            }
        }

        // Icon widget
        if ( elType === 'widget' && widgetType === 'icon' ) {
            var iconAlign = getDeviceExplicitValue( settings, 'align', device );
            if ( iconAlign ) {
                baseRules.push( 'text-align: ' + iconAlign );
                subRules[' .lk-icon-wrapper'] = [ 'text-align: ' + iconAlign ];
            }

            var iconRules = [];
            var priColor = getDeviceExplicitValue( settings, 'primary_color', device );
            if ( priColor ) {
                iconRules.push( 'color: ' + resolveGlobalValue( priColor ) );
                iconRules.push( 'fill: ' + resolveGlobalValue( priColor ) );
            }
            var iSize = getDeviceExplicitValue( settings, 'size', device );
            if ( iSize && typeof iSize === 'object' && iSize.size !== undefined && iSize.size !== '' ) {
                iconRules.push( 'font-size: ' + iSize.size + ( iSize.unit || 'px' ) );
            }
            var rotate = getDeviceExplicitValue( settings, 'rotate', device );
            if ( rotate !== undefined && rotate !== '' && parseInt( rotate, 10 ) !== 0 ) {
                iconRules.push( 'transform: rotate(' + parseInt( rotate, 10 ) + 'deg)' );
            }
            if ( iconRules.length ) subRules[' .lk-icon'] = iconRules;

            var hovPri = getDeviceExplicitValue( settings, 'hover_primary_color', device );
            if ( hovPri ) {
                subRules[' .lk-icon:hover'] = [
                    'color: ' + resolveGlobalValue( hovPri ),
                    'fill: ' + resolveGlobalValue( hovPri )
                ];
            }
        }

        // Image Box widget
        if ( elType === 'widget' && widgetType === 'image-box' ) {
            var ibAlign = getDeviceExplicitValue( settings, 'alignment', device );
            var ibPos = getDeviceExplicitValue( settings, 'position', device ) || settings.position || 'top';
            if ( ibAlign ) {
                var ibWrapperRules = [ 'text-align: ' + ibAlign ];
                if ( ibPos === 'left' || ibPos === 'right' ) {
                    var ibVAlign = getDeviceExplicitValue( settings, 'vertical_alignment', device ) || settings.vertical_alignment || 'top';
                    var vaMap = { top: 'flex-start', middle: 'center', bottom: 'flex-end' };
                    if ( vaMap[ ibVAlign ] ) {
                        ibWrapperRules.push( 'align-items: ' + vaMap[ ibVAlign ] );
                    }
                } else {
                    var aiMap = { left: 'flex-start', center: 'center', right: 'flex-end', justify: 'stretch' };
                    if ( aiMap[ ibAlign ] ) {
                        ibWrapperRules.push( 'align-items: ' + aiMap[ ibAlign ] );
                    }
                    if ( ibAlign === 'left' ) {
                        subRules[' .lk-image-box-img'] = ( subRules[' .lk-image-box-img'] || [] ).concat( [ 'margin-left: 0', 'margin-right: auto' ] );
                    } else if ( ibAlign === 'right' ) {
                        subRules[' .lk-image-box-img'] = ( subRules[' .lk-image-box-img'] || [] ).concat( [ 'margin-left: auto', 'margin-right: 0' ] );
                    } else if ( ibAlign === 'center' ) {
                        subRules[' .lk-image-box-img'] = ( subRules[' .lk-image-box-img'] || [] ).concat( [ 'margin-left: auto', 'margin-right: auto' ] );
                    }
                }
                subRules[' .lk-image-box-wrapper'] = ibWrapperRules;
                subRules[' .lk-image-box-content'] = [ 'text-align: ' + ibAlign ];
            }

            var ibWidth = getDeviceExplicitValue( settings, 'image_width', device );
            if ( ibWidth && typeof ibWidth === 'object' && ibWidth.size !== undefined && ibWidth.size !== '' ) {
                subRules[' .lk-image-box-img'] = ( subRules[' .lk-image-box-img'] || [] ).concat( [ 'width: ' + ibWidth.size + ( ibWidth.unit || '%' ) ] );
            }
            var ibSpace = getDeviceExplicitValue( settings, 'image_space', device );
            if ( ibSpace && typeof ibSpace === 'object' && ibSpace.size !== undefined && ibSpace.size !== '' ) {
                subRules[' .lk-image-box-img'] = subRules[' .lk-image-box-img'] || [];
                subRules[' .lk-image-box-img'].push( 'margin-bottom: ' + ibSpace.size + ( ibSpace.unit || 'px' ) );
            }
            var tCol = getDeviceExplicitValue( settings, 'title_color', device );
            if ( tCol ) subRules[' .lk-image-box-title'] = [ 'color: ' + resolveGlobalValue( tCol ) ];
            var dCol = getDeviceExplicitValue( settings, 'description_color', device );
            if ( dCol ) subRules[' .lk-image-box-description'] = [ 'color: ' + resolveGlobalValue( dCol ) ];
        }

        // Icon Box widget
        if ( elType === 'widget' && widgetType === 'icon-box' ) {
            var iboxAlign = getDeviceExplicitValue( settings, 'alignment', device );
            var iboxPos = getDeviceExplicitValue( settings, 'position', device ) || settings.position || 'top';
            if ( iboxAlign ) {
                var iboxWrapperRules = [ 'text-align: ' + iboxAlign ];
                if ( iboxPos === 'left' || iboxPos === 'right' ) {
                    var iboxVAlign = getDeviceExplicitValue( settings, 'vertical_alignment', device ) || settings.vertical_alignment || 'top';
                    var vaMap = { top: 'flex-start', middle: 'center', bottom: 'flex-end' };
                    if ( vaMap[ iboxVAlign ] ) {
                        iboxWrapperRules.push( 'align-items: ' + vaMap[ iboxVAlign ] );
                    }
                } else {
                    var aiMap = { left: 'flex-start', center: 'center', right: 'flex-end', justify: 'stretch' };
                    if ( aiMap[ iboxAlign ] ) {
                        iboxWrapperRules.push( 'align-items: ' + aiMap[ iboxAlign ] );
                    }
                    if ( iboxAlign === 'left' ) {
                        subRules[' .lk-icon-box-icon'] = ( subRules[' .lk-icon-box-icon'] || [] ).concat( [ 'margin-left: 0', 'margin-right: auto', 'align-self: flex-start' ] );
                    } else if ( iboxAlign === 'right' ) {
                        subRules[' .lk-icon-box-icon'] = ( subRules[' .lk-icon-box-icon'] || [] ).concat( [ 'margin-left: auto', 'margin-right: 0', 'align-self: flex-end' ] );
                    } else if ( iboxAlign === 'center' ) {
                        subRules[' .lk-icon-box-icon'] = ( subRules[' .lk-icon-box-icon'] || [] ).concat( [ 'margin-left: auto', 'margin-right: auto', 'align-self: center' ] );
                    }
                }
                subRules[' .lk-icon-box-wrapper'] = iboxWrapperRules;
                subRules[' .lk-icon-box-content'] = [ 'text-align: ' + iboxAlign ];
            }

            var iboxRules = [];
            var ibPri = getDeviceExplicitValue( settings, 'primary_color', device );
            if ( ibPri ) {
                iboxRules.push( 'color: ' + resolveGlobalValue( ibPri ) );
                iboxRules.push( 'fill: ' + resolveGlobalValue( ibPri ) );
            }
            var ibSize = getDeviceExplicitValue( settings, 'icon_size', device );
            if ( ibSize && typeof ibSize === 'object' && ibSize.size !== undefined && ibSize.size !== '' ) {
                iboxRules.push( 'font-size: ' + ibSize.size + ( ibSize.unit || 'px' ) );
            }
            if ( iboxRules.length ) subRules[' .lk-icon-box-icon'] = ( subRules[' .lk-icon-box-icon'] || [] ).concat( iboxRules );

            var ibTCol = getDeviceExplicitValue( settings, 'title_color', device );
            if ( ibTCol ) subRules[' .lk-icon-box-title'] = [ 'color: ' + resolveGlobalValue( ibTCol ) ];
            var ibDCol = getDeviceExplicitValue( settings, 'description_color', device );
            if ( ibDCol ) subRules[' .lk-icon-box-description'] = [ 'color: ' + resolveGlobalValue( ibDCol ) ];
        }

        // Icon List widget
        if ( elType === 'widget' && widgetType === 'icon-list' ) {
            var ilAlign = getDeviceExplicitValue( settings, 'alignment', device );
            if ( ilAlign ) {
                var jcMap = { left: 'flex-start', center: 'center', right: 'flex-end' };
                var jc = jcMap[ ilAlign ] || ilAlign;
                subRules[' .lk-icon-list-items'] = [ 'justify-content: ' + jc, 'text-align: ' + ilAlign ];
                subRules[' .lk-icon-list-item'] = [ 'justify-content: ' + jc ];
            }

            var ilSp = getDeviceExplicitValue( settings, 'space_between', device );
            if ( ilSp && typeof ilSp === 'object' && ilSp.size !== undefined && ilSp.size !== '' ) {
                subRules[' .lk-icon-list-item:not(:last-child)'] = [ 'margin-bottom: ' + ilSp.size + ( ilSp.unit || 'px' ) ];
            }
            var ilICol = getDeviceExplicitValue( settings, 'icon_color', device );
            if ( ilICol ) subRules[' .lk-icon-list-icon'] = [ 'color: ' + resolveGlobalValue( ilICol ), 'fill: ' + resolveGlobalValue( ilICol ) ];
            var ilTCol = getDeviceExplicitValue( settings, 'text_color', device );
            if ( ilTCol ) subRules[' .lk-icon-list-text'] = [ 'color: ' + resolveGlobalValue( ilTCol ) ];
        }

        // Social Icons widget
        if ( elType === 'widget' && widgetType === 'social-icons' ) {
            var siAlign = getDeviceExplicitValue( settings, 'align', device );
            if ( siAlign ) {
                var jcMap = { left: 'flex-start', center: 'center', right: 'flex-end' };
                var jc = jcMap[ siAlign ] || siAlign;
                subRules[' .lk-social-icons-wrapper'] = subRules[' .lk-social-icons-wrapper'] || [];
                subRules[' .lk-social-icons-wrapper'].push( 'justify-content: ' + jc, 'text-align: ' + siAlign );
            }

            var socRules = [];
            var siSize = getDeviceExplicitValue( settings, 'icon_size', device );
            if ( siSize && typeof siSize === 'object' && siSize.size !== undefined && siSize.size !== '' ) {
                socRules.push( 'font-size: ' + siSize.size + ( siSize.unit || 'px' ) );
            }
            var siPad = getDeviceExplicitValue( settings, 'icon_padding', device );
            if ( siPad && typeof siPad === 'object' && siPad.size !== undefined && siPad.size !== '' ) {
                socRules.push( 'padding: ' + siPad.size + ( siPad.unit || 'px' ) );
            }
            if ( socRules.length ) subRules[' .lk-social-icon'] = socRules;

            var siSpace = getDeviceExplicitValue( settings, 'icon_spacing', device );
            if ( siSpace && typeof siSpace === 'object' && siSpace.size !== undefined && siSpace.size !== '' ) {
                subRules[' .lk-grid-item:not(:last-child)'] = [ 'margin-right: ' + siSpace.size + ( siSpace.unit || 'px' ) ];
            }
        }

        // Site Logo widget
        if ( elType === 'widget' && widgetType === 'site-logo' ) {
            var logoAlign = getDeviceExplicitValue( settings, 'align', device );
            if ( logoAlign ) {
                baseRules.push( 'text-align: ' + logoAlign );
            }
            var logoWidth = getDeviceExplicitValue( settings, 'width', device );
            if ( logoWidth && typeof logoWidth === 'object' && logoWidth.size !== undefined && logoWidth.size !== '' ) {
                subRules[' .lk-site-logo-img'] = subRules[' .lk-site-logo-img'] || [];
                subRules[' .lk-site-logo-img'].push( 'width: ' + logoWidth.size + ( logoWidth.unit || 'px' ) );
            }
            var logoMaxWidth = getDeviceExplicitValue( settings, 'max_width', device );
            if ( logoMaxWidth && typeof logoMaxWidth === 'object' && logoMaxWidth.size !== undefined && logoMaxWidth.size !== '' ) {
                subRules[' .lk-site-logo-img'] = subRules[' .lk-site-logo-img'] || [];
                subRules[' .lk-site-logo-img'].push( 'max-width: ' + logoMaxWidth.size + ( logoMaxWidth.unit || 'px' ) );
            }
            var logoHeight = getDeviceExplicitValue( settings, 'height', device );
            if ( logoHeight && typeof logoHeight === 'object' && logoHeight.size !== undefined && logoHeight.size !== '' ) {
                subRules[' .lk-site-logo-img'] = subRules[' .lk-site-logo-img'] || [];
                subRules[' .lk-site-logo-img'].push( 'height: ' + logoHeight.size + ( logoHeight.unit || 'px' ) );
            }
            var logoFit = getDeviceExplicitValue( settings, 'object_fit', device );
            if ( logoFit ) {
                subRules[' .lk-site-logo-img'] = subRules[' .lk-site-logo-img'] || [];
                subRules[' .lk-site-logo-img'].push( 'object-fit: ' + logoFit );
            }
            var logoOpacity = getDeviceExplicitValue( settings, 'opacity', device );
            if ( logoOpacity !== undefined && logoOpacity !== '' ) {
                subRules[' .lk-site-logo-img'] = subRules[' .lk-site-logo-img'] || [];
                subRules[' .lk-site-logo-img'].push( 'opacity: ' + logoOpacity );
            }
            var logoHovOpacity = getDeviceExplicitValue( settings, 'opacity_hover', device );
            if ( logoHovOpacity !== undefined && logoHovOpacity !== '' ) {
                subRules[' .lk-site-logo-img:hover'] = [ 'opacity: ' + logoHovOpacity ];
            }
        }

        // Site Title widget
        // Nav Menu widget
        if ( elType === 'widget' && widgetType === 'nav-menu' ) {
            var nmAlign = getDeviceExplicitValue( settings, 'align', device );
            if ( nmAlign ) {
                var nmJcMap = { left: 'flex-start', center: 'center', right: 'flex-end', stretch: 'space-between' };
                var nmJc = nmJcMap[ nmAlign ] || nmAlign;
                subRules[' .lk-nav-menu'] = subRules[' .lk-nav-menu'] || [];
                subRules[' .lk-nav-menu'].push( 'justify-content: ' + nmJc );
            }
            var nmGap = getDeviceExplicitValue( settings, 'item_spacing', device );
            if ( nmGap && typeof nmGap === 'object' && nmGap.size !== undefined && nmGap.size !== '' ) {
                subRules[' .lk-nav-menu'] = subRules[' .lk-nav-menu'] || [];
                subRules[' .lk-nav-menu'].push( 'gap: ' + nmGap.size + ( nmGap.unit || 'px' ) );
            }
            var nmColor = getDeviceExplicitValue( settings, 'text_color', device );
            if ( nmColor ) {
                subRules[' .lk-menu-link'] = subRules[' .lk-menu-link'] || [];
                subRules[' .lk-menu-link'].push( 'color: ' + resolveGlobalValue( nmColor ) );
            }
            var nmHovColor = getDeviceExplicitValue( settings, 'text_color_hover', device );
            if ( nmHovColor ) {
                subRules[' .lk-menu-link:hover'] = [ 'color: ' + resolveGlobalValue( nmHovColor ) ];
            }
            var nmPtrCol = getDeviceExplicitValue( settings, 'pointer_color', device );
            if ( nmPtrCol ) {
                baseRules.push( '--lk-pointer-color: ' + resolveGlobalValue( nmPtrCol ) );
            }
            var nmPtrW = getDeviceExplicitValue( settings, 'pointer_width', device );
            if ( nmPtrW && typeof nmPtrW === 'object' && nmPtrW.size !== undefined && nmPtrW.size !== '' ) {
                baseRules.push( '--lk-pointer-width: ' + nmPtrW.size + ( nmPtrW.unit || 'px' ) );
            }
            var nmSubBg = getDeviceExplicitValue( settings, 'dropdown_bg_color', device );
            if ( nmSubBg ) {
                subRules[' .lk-sub-menu'] = subRules[' .lk-sub-menu'] || [];
                subRules[' .lk-sub-menu'].push( 'background-color: ' + resolveGlobalValue( nmSubBg ) );
            }
            var nmSubWidth = getDeviceExplicitValue( settings, 'dropdown_width', device );
            if ( nmSubWidth && typeof nmSubWidth === 'object' && nmSubWidth.size !== undefined && nmSubWidth.size !== '' ) {
                subRules[' .lk-sub-menu'] = subRules[' .lk-sub-menu'] || [];
                subRules[' .lk-sub-menu'].push( 'min-width: ' + nmSubWidth.size + ( nmSubWidth.unit || 'px' ) );
            }
            var nmHambCol = getDeviceExplicitValue( settings, 'hamburger_color', device );
            if ( nmHambCol ) {
                subRules[' .lk-nav-menu-toggle'] = [ 'color: ' + resolveGlobalValue( nmHambCol ) ];
            }
        }

        // Search Form widget
        // Post widgets (Part 5) — declarative table above, mirrored in the PHP generator
        if ( elType === 'widget' && POST_WIDGET_CSS_RULES.hasOwnProperty( widgetType ) ) {
            applyDeclarativeRules( POST_WIDGET_CSS_RULES[ widgetType ], settings, device, subRules );
        }

        if ( elType === 'widget' && widgetType === 'search-form' ) {
            var sfAlign = getDeviceExplicitValue( settings, 'align', device );
            if ( sfAlign ) {
                var sfJcMap = { left: 'flex-start', center: 'center', right: 'flex-end' };
                var sfJc = sfJcMap[ sfAlign ] || sfAlign;
                subRules[' .lk-widget-container'] = [ 'display: flex', 'justify-content: ' + sfJc ];
            }
            var sfInpColor = getDeviceExplicitValue( settings, 'input_text_color', device );
            if ( sfInpColor ) {
                subRules[' .lk-search-form__input'] = subRules[' .lk-search-form__input'] || [];
                subRules[' .lk-search-form__input'].push( 'color: ' + resolveGlobalValue( sfInpColor ) );
            }
            var sfInpBg = getDeviceExplicitValue( settings, 'input_bg_color', device );
            if ( sfInpBg ) {
                subRules[' .lk-search-form__input'] = subRules[' .lk-search-form__input'] || [];
                subRules[' .lk-search-form__input'].push( 'background-color: ' + resolveGlobalValue( sfInpBg ) );
            }
            var sfBtnColor = getDeviceExplicitValue( settings, 'button_text_color', device );
            if ( sfBtnColor ) {
                subRules[' .lk-search-form__submit'] = subRules[' .lk-search-form__submit'] || [];
                subRules[' .lk-search-form__submit'].push( 'color: ' + resolveGlobalValue( sfBtnColor ) );
            }
            var sfBtnBg = getDeviceExplicitValue( settings, 'button_bg_color', device );
            if ( sfBtnBg ) {
                subRules[' .lk-search-form__submit'] = subRules[' .lk-search-form__submit'] || [];
                subRules[' .lk-search-form__submit'].push( 'background-color: ' + resolveGlobalValue( sfBtnBg ) );
            }
            var sfBtnBgHov = getDeviceExplicitValue( settings, 'button_bg_color_hover', device );
            if ( sfBtnBgHov ) {
                subRules[' .lk-search-form__submit:hover'] = [ 'background-color: ' + resolveGlobalValue( sfBtnBgHov ) ];
            }
            var sfIconSize = getDeviceExplicitValue( settings, 'button_icon_size', device );
            if ( sfIconSize && typeof sfIconSize === 'object' && sfIconSize.size !== undefined && sfIconSize.size !== '' ) {
                var isVal = sfIconSize.size + ( sfIconSize.unit || 'px' );
                subRules[' .lk-search-form__icon'] = [ 'font-size: ' + isVal, 'width: ' + isVal, 'height: ' + isVal ];
            }
        }

        // -------------------------------------------------------------
        // SHARED ADVANCED TAB: LAYOUT & SIZING (Section 11 & Problem 3)
        // -------------------------------------------------------------
        // Widget Width (widgets only)
        if ( elType === 'widget' ) {
            var wWidth = getDeviceExplicitValue( settings, 'widget_width', device );
            if ( wWidth === 'full' ) {
                baseRules.push( 'width: 100% !important' );
                baseRules.push( 'max-width: 100%' );
            } else if ( wWidth === 'inline' ) {
                baseRules.push( 'width: auto !important' );
                baseRules.push( 'max-width: 100%' );
                baseRules.push( 'display: inline-block' );
            } else if ( wWidth === 'custom' ) {
                var cWidth = getDeviceExplicitValue( settings, 'custom_width', device );
                if ( cWidth && typeof cWidth === 'object' && cWidth.size ) {
                    baseRules.push( 'width: ' + cWidth.size + ( cWidth.unit || 'px' ) + ' !important' );
                    baseRules.push( 'max-width: 100%' );
                }
            }
        }

        // Align Self (responsive)
        var alignSelf = getDeviceExplicitValue( settings, 'align_self', device );
        if ( alignSelf ) {
            var asVal = alignSelf;
            if ( asVal === 'start' ) asVal = 'flex-start';
            else if ( asVal === 'end' ) asVal = 'flex-end';
            baseRules.push( 'align-self: ' + asVal );
        }

        // Order (responsive)
        var order = getDeviceExplicitValue( settings, 'order', device );
        if ( order === 'start' ) {
            baseRules.push( 'order: -1' );
        } else if ( order === 'end' ) {
            baseRules.push( 'order: 9999' );
        } else if ( order === 'custom' ) {
            var orderCustom = getDeviceExplicitValue( settings, 'order_custom', device );
            if ( orderCustom !== undefined && orderCustom !== '' ) {
                baseRules.push( 'order: ' + orderCustom );
            }
        }

        // Size: Flex Grow & Shrink (responsive)
        var flexSize = getDeviceExplicitValue( settings, 'size', device );
        if ( flexSize === 'none' ) {
            baseRules.push( 'flex-grow: 0 !important' );
            baseRules.push( 'flex-shrink: 0 !important' );
        } else if ( flexSize === 'grow' ) {
            baseRules.push( 'flex-grow: 1 !important' );
        } else if ( flexSize === 'shrink' ) {
            baseRules.push( 'flex-shrink: 1 !important' );
        } else if ( flexSize === 'custom' ) {
            var fGrow = getDeviceExplicitValue( settings, 'flex_grow', device );
            var fShrink = getDeviceExplicitValue( settings, 'flex_shrink', device );
            if ( fGrow !== undefined && fGrow !== '' ) baseRules.push( 'flex-grow: ' + fGrow + ' !important' );
            if ( fShrink !== undefined && fShrink !== '' ) baseRules.push( 'flex-shrink: ' + fShrink + ' !important' );
        }

        // Position: Default, Absolute, Fixed
        var position = getDeviceExplicitValue( settings, 'position', device );
        if ( position && position !== 'default' ) {
            baseRules.push( 'position: ' + position );

            var hOrient = getDeviceExplicitValue( settings, 'offset_orientation_h', device ) || 'left';
            var hOffset = getDeviceExplicitValue( settings, 'offset_x', device );
            if ( hOffset && typeof hOffset === 'object' && hOffset.size !== undefined && hOffset.size !== '' ) {
                var hVal = hOffset.size + ( hOffset.unit || 'px' );
                if ( hOrient === 'right' ) {
                    baseRules.push( 'right: ' + hVal );
                    baseRules.push( 'left: auto' );
                } else {
                    baseRules.push( 'left: ' + hVal );
                    baseRules.push( 'right: auto' );
                }
            }

            var vOrient = getDeviceExplicitValue( settings, 'offset_orientation_v', device ) || 'top';
            var vOffset = getDeviceExplicitValue( settings, 'offset_y', device );
            if ( vOffset && typeof vOffset === 'object' && vOffset.size !== undefined && vOffset.size !== '' ) {
                var vVal = vOffset.size + ( vOffset.unit || 'px' );
                if ( vOrient === 'bottom' ) {
                    baseRules.push( 'bottom: ' + vVal );
                    baseRules.push( 'top: auto' );
                } else {
                    baseRules.push( 'top: ' + vVal );
                    baseRules.push( 'bottom: auto' );
                }
            }
        }

        // Z-Index (responsive)
        var zIndex = getDeviceExplicitValue( settings, 'z_index', device );
        if ( zIndex !== undefined && zIndex !== null && zIndex !== '' ) {
            baseRules.push( 'z-index: ' + parseInt( zIndex, 10 ) );
        }

        // Entrance Animation (responsive duration, delay, and per-device animation-name)
        var animDuration = getDeviceExplicitValue( settings, 'animation_duration', device );
        if ( animDuration !== undefined && animDuration !== null && animDuration !== '' ) {
            var durMap = { slow: '2s', normal: '1.25s', fast: '0.75s' };
            var durVal = durMap[ animDuration ] || ( isNaN( animDuration ) ? animDuration : animDuration + 's' );
            baseRules.push( '--lk-anim-duration: ' + durVal );
        }
        var animDelay = getDeviceExplicitValue( settings, 'animation_delay', device );
        if ( animDelay !== undefined && animDelay !== null && animDelay !== '' ) {
            var delayVal = parseInt( animDelay, 10 ) || 0;
            baseRules.push( '--lk-anim-delay: ' + delayVal + 'ms' );
        }
        var animName = getDeviceExplicitValue( settings, 'entrance_animation', device );
        if ( animName !== undefined && animName !== null && animName !== '' ) {
            if ( animName === 'none' ) {
                baseRules.push( 'animation-name: none !important' );
                baseRules.push( 'opacity: 1 !important' );
            } else {
                baseRules.push( 'animation-name: lk-anim-' + animName );
            }
        }

        // Sticky (Motion Effects)
        var stickyVal = getDeviceExplicitValue( settings, 'sticky', device );
        if ( stickyVal !== undefined && stickyVal !== null && stickyVal !== '' ) {
            if ( stickyVal === 'none' ) {
                baseRules.push( 'position: static' );
            } else if ( stickyVal === 'top' ) {
                baseRules.push( 'position: -webkit-sticky' );
                baseRules.push( 'position: sticky' );
                baseRules.push( 'top: var(--lk-sticky-offset, 0px)' );
                baseRules.push( 'bottom: auto' );
                baseRules.push( 'z-index: 100' );
            } else if ( stickyVal === 'bottom' ) {
                baseRules.push( 'position: -webkit-sticky' );
                baseRules.push( 'position: sticky' );
                baseRules.push( 'bottom: var(--lk-sticky-offset, 0px)' );
                baseRules.push( 'top: auto' );
                baseRules.push( 'z-index: 100' );
            }
        }
        var stickyOffset = getDeviceExplicitValue( settings, 'sticky_offset', device );
        if ( stickyOffset !== undefined && stickyOffset !== null && stickyOffset !== '' ) {
            baseRules.push( '--lk-sticky-offset: ' + parseInt( stickyOffset, 10 ) + 'px' );
        }
        var stickyEffectsOffset = getDeviceExplicitValue( settings, 'sticky_effects_offset', device );
        if ( stickyEffectsOffset !== undefined && stickyEffectsOffset !== null && stickyEffectsOffset !== '' ) {
            baseRules.push( '--lk-sticky-effects-offset: ' + parseInt( stickyEffectsOffset, 10 ) + 'px' );
        }

        // -------------------------------------------------------------
        // TRANSFORM (NORMAL & HOVER, 2D & 3D, FLIP, ANCHOR POINTS)
        // -------------------------------------------------------------
        var transformParts = [];
        var tRotate = getDeviceExplicitValue( settings, 'transform_rotate', device );
        if ( tRotate && typeof tRotate === 'object' && tRotate.size !== undefined && tRotate.size !== '' ) {
            transformParts.push( 'rotate(' + tRotate.size + ( tRotate.unit || 'deg' ) + ')' );
        }
        var tOffX = getDeviceExplicitValue( settings, 'transform_offset_x', device );
        if ( tOffX && typeof tOffX === 'object' && tOffX.size !== undefined && tOffX.size !== '' ) {
            transformParts.push( 'translateX(' + tOffX.size + ( tOffX.unit || 'px' ) + ')' );
        }
        var tOffY = getDeviceExplicitValue( settings, 'transform_offset_y', device );
        if ( tOffY && typeof tOffY === 'object' && tOffY.size !== undefined && tOffY.size !== '' ) {
            transformParts.push( 'translateY(' + tOffY.size + ( tOffY.unit || 'px' ) + ')' );
        }
        var tScale = getDeviceExplicitValue( settings, 'transform_scale', device );
        if ( tScale !== undefined && tScale !== null && tScale !== '' ) {
            var scaleVal = ( typeof tScale === 'object' ) ? tScale.size : tScale;
            if ( scaleVal !== undefined && scaleVal !== '' ) {
                transformParts.push( 'scale(' + scaleVal + ')' );
            }
        }
        var tSkewX = getDeviceExplicitValue( settings, 'transform_skew_x', device );
        if ( tSkewX && typeof tSkewX === 'object' && tSkewX.size !== undefined && tSkewX.size !== '' ) {
            transformParts.push( 'skewX(' + tSkewX.size + ( tSkewX.unit || 'deg' ) + ')' );
        }
        var tSkewY = getDeviceExplicitValue( settings, 'transform_skew_y', device );
        if ( tSkewY && typeof tSkewY === 'object' && tSkewY.size !== undefined && tSkewY.size !== '' ) {
            transformParts.push( 'skewY(' + tSkewY.size + ( tSkewY.unit || 'deg' ) + ')' );
        }

        // C4: 3D Rotate, Perspective, Flip (Normal)
        var tRotateX = getDeviceExplicitValue( settings, 'transform_rotate_x', device );
        var rxVal = ( tRotateX && typeof tRotateX === 'object' && tRotateX.size !== undefined ) ? parseFloat( tRotateX.size ) : parseFloat( tRotateX );
        if ( ! isNaN( rxVal ) && rxVal !== 0 ) {
            baseRules.push( '--lk-transform-rotate-x: ' + rxVal + 'deg' );
        }

        var tRotateY = getDeviceExplicitValue( settings, 'transform_rotate_y', device );
        var ryVal = ( tRotateY && typeof tRotateY === 'object' && tRotateY.size !== undefined ) ? parseFloat( tRotateY.size ) : parseFloat( tRotateY );
        if ( ! isNaN( ryVal ) && ryVal !== 0 ) {
            baseRules.push( '--lk-transform-rotate-y: ' + ryVal + 'deg' );
        }

        var tFlipX = settings.transform_flip_x;
        if ( tFlipX === 'yes' || tFlipX === true ) {
            baseRules.push( '--lk-transform-flip-x: -1' );
        }

        var tFlipY = settings.transform_flip_y;
        if ( tFlipY === 'yes' || tFlipY === true ) {
            baseRules.push( '--lk-transform-flip-y: -1' );
        }

        var has3D = ( ! isNaN( rxVal ) && rxVal !== 0 ) || ( ! isNaN( ryVal ) && ryVal !== 0 );
        var hasFlip = ( tFlipX === 'yes' || tFlipX === true || tFlipY === 'yes' || tFlipY === true );

        var tPerspective = getDeviceExplicitValue( settings, 'transform_perspective', device );
        var pVal = ( tPerspective && typeof tPerspective === 'object' && tPerspective.size !== undefined ) ? parseFloat( tPerspective.size ) : parseFloat( tPerspective );
        if ( has3D ) {
            baseRules.push( 'transform-style: preserve-3d' );
            if ( ! isNaN( pVal ) && pVal > 0 ) {
                parentRules.push( 'perspective: ' + pVal + 'px' );
            }
        }

        var hasScroll = ( settings.scrolling_effects === 'yes' || settings.scrolling_effects === true );
        var hasMouse  = ( settings.mouse_effects === 'yes' || settings.mouse_effects === true );

        if ( hasScroll || hasMouse || transformParts.length || has3D || hasFlip ) {
            var motionTransform = 'translateX(var(--lk-scroll-translate-x, 0)) translateY(var(--lk-scroll-translate-y, 0)) rotate(var(--lk-scroll-rotate, 0deg)) scale(var(--lk-scroll-scale, 1)) rotateX(var(--lk-mouse-rotate-x, 0deg)) rotateY(var(--lk-mouse-rotate-y, 0deg)) translateX(var(--lk-mouse-translate-x, 0)) translateY(var(--lk-mouse-translate-y, 0)) rotateX(var(--lk-transform-rotate-x, 0deg)) rotateY(var(--lk-transform-rotate-y, 0deg)) scaleX(var(--lk-transform-flip-x, 1)) scaleY(var(--lk-transform-flip-y, 1))';
            if ( transformParts.length ) {
                motionTransform += ' ' + transformParts.join( ' ' );
            }
            baseRules.push( 'transform: ' + motionTransform );
        }

        if ( hasScroll ) {
            baseRules.push( 'filter: blur(var(--lk-scroll-blur, 0px))' );
            baseRules.push( 'opacity: calc(1 + var(--lk-scroll-opacity, 0))' );
        }

        // C4 Anchor Point X/Y (merged with C3 scroll anchor point)
        var anchorX = getDeviceExplicitValue( settings, 'transform_anchor_x', device );
        if ( anchorX === undefined || anchorX === '' ) {
            anchorX = getDeviceExplicitValue( settings, 'scroll_anchor_x', device );
        }
        var axVal = ( anchorX && typeof anchorX === 'object' && anchorX.size !== undefined && anchorX.size !== '' ) ? anchorX.size : ( ( anchorX !== undefined && anchorX !== '' ) ? anchorX : 50 );

        var anchorY = getDeviceExplicitValue( settings, 'transform_anchor_y', device );
        if ( anchorY === undefined || anchorY === '' ) {
            anchorY = getDeviceExplicitValue( settings, 'scroll_anchor_y', device );
        }
        var ayVal = ( anchorY && typeof anchorY === 'object' && anchorY.size !== undefined && anchorY.size !== '' ) ? anchorY.size : ( ( anchorY !== undefined && anchorY !== '' ) ? anchorY : 50 );

        var axNum = parseFloat( axVal );
        var ayNum = parseFloat( ayVal );
        if ( isNaN( axNum ) ) axNum = 50;
        if ( isNaN( ayNum ) ) ayNum = 50;

        if ( hasScroll || axNum !== 50 || ayNum !== 50 ) {
            baseRules.push( 'transform-origin: ' + axNum + '% ' + ayNum + '%' );
        }

        if ( hasMouse && ( settings.mouse_tilt === 'yes' || settings.mouse_tilt === true ) ) {
            baseRules.push( 'transform-style: preserve-3d' );
            baseRules.push( 'perspective: 1000px' );
        }

        // C4 Transform Hover
        var hRot = getDeviceExplicitValue( settings, 'transform_rotate_hover', device );
        var hOffX = getDeviceExplicitValue( settings, 'transform_offset_x_hover', device );
        var hOffY = getDeviceExplicitValue( settings, 'transform_offset_y_hover', device );
        var hScale = getDeviceExplicitValue( settings, 'transform_scale_hover', device );
        var hSkewX = getDeviceExplicitValue( settings, 'transform_skew_x_hover', device );
        var hSkewY = getDeviceExplicitValue( settings, 'transform_skew_y_hover', device );
        var hRotX = getDeviceExplicitValue( settings, 'transform_rotate_x_hover', device );
        var hRotY = getDeviceExplicitValue( settings, 'transform_rotate_y_hover', device );
        var hPersp = getDeviceExplicitValue( settings, 'transform_perspective_hover', device );
        var hFlipX = settings.transform_flip_x_hover;
        var hFlipY = settings.transform_flip_y_hover;
        var hAncX = getDeviceExplicitValue( settings, 'transform_anchor_x_hover', device );
        var hAncY = getDeviceExplicitValue( settings, 'transform_anchor_y_hover', device );
        var tDur = getDeviceExplicitValue( settings, 'transform_transition_duration', device );

        function hasPropVal( v ) {
            if ( v === undefined || v === null || v === '' ) return false;
            if ( typeof v === 'object' ) return v.size !== undefined && v.size !== '';
            return true;
        }

        var hasHoverTransform = hasPropVal( hRot ) || hasPropVal( hOffX ) || hasPropVal( hOffY ) ||
                                hasPropVal( hScale ) || hasPropVal( hSkewX ) || hasPropVal( hSkewY ) ||
                                hasPropVal( hRotX ) || hasPropVal( hRotY ) || hasPropVal( hPersp ) ||
                                hasPropVal( hFlipX ) || hasPropVal( hFlipY ) || hasPropVal( hAncX ) ||
                                hasPropVal( hAncY ) || hasPropVal( tDur );

        if ( hasHoverTransform ) {
            var explicitDur = getDeviceExplicitValue( settings, 'transform_transition_duration', device );
            if ( device === 'desktop' || explicitDur !== undefined ) {
                var effDur = ( explicitDur !== undefined ) ? explicitDur : getDeviceExplicitValue( settings, 'transform_transition_duration', 'desktop' );
                var durNum = ( effDur && typeof effDur === 'object' && effDur.size !== undefined ) ? parseFloat( effDur.size ) : parseFloat( effDur );
                if ( isNaN( durNum ) ) durNum = 0.3;
                baseRules.push( 'transition: transform ' + durNum + 's ease, filter ' + durNum + 's ease' );
            }

            var hoverTransformParts = [];

            // Rotate
            var effHRot = hasPropVal( hRot ) ? hRot : tRotate;
            if ( effHRot && typeof effHRot === 'object' && effHRot.size !== undefined && effHRot.size !== '' ) {
                hoverTransformParts.push( 'rotate(' + effHRot.size + ( effHRot.unit || 'deg' ) + ')' );
            }

            // Offset X
            var effHOffX = hasPropVal( hOffX ) ? hOffX : tOffX;
            if ( effHOffX && typeof effHOffX === 'object' && effHOffX.size !== undefined && effHOffX.size !== '' ) {
                hoverTransformParts.push( 'translateX(' + effHOffX.size + ( effHOffX.unit || 'px' ) + ')' );
            }

            // Offset Y
            var effHOffY = hasPropVal( hOffY ) ? hOffY : tOffY;
            if ( effHOffY && typeof effHOffY === 'object' && effHOffY.size !== undefined && effHOffY.size !== '' ) {
                hoverTransformParts.push( 'translateY(' + effHOffY.size + ( effHOffY.unit || 'px' ) + ')' );
            }

            // Scale
            var effHScale = hasPropVal( hScale ) ? hScale : tScale;
            if ( effHScale !== undefined && effHScale !== null && effHScale !== '' ) {
                var hsVal = ( typeof effHScale === 'object' ) ? effHScale.size : effHScale;
                if ( hsVal !== undefined && hsVal !== '' ) {
                    hoverTransformParts.push( 'scale(' + hsVal + ')' );
                }
            }

            // Skew X
            var effHSkewX = hasPropVal( hSkewX ) ? hSkewX : tSkewX;
            if ( effHSkewX && typeof effHSkewX === 'object' && effHSkewX.size !== undefined && effHSkewX.size !== '' ) {
                hoverTransformParts.push( 'skewX(' + effHSkewX.size + ( effHSkewX.unit || 'deg' ) + ')' );
            }

            // Skew Y
            var effHSkewY = hasPropVal( hSkewY ) ? hSkewY : tSkewY;
            if ( effHSkewY && typeof effHSkewY === 'object' && effHSkewY.size !== undefined && effHSkewY.size !== '' ) {
                hoverTransformParts.push( 'skewY(' + effHSkewY.size + ( effHSkewY.unit || 'deg' ) + ')' );
            }

            // Rotate X
            var effHRotX = hasPropVal( hRotX ) ? hRotX : tRotateX;
            var hRxVal = ( effHRotX && typeof effHRotX === 'object' && effHRotX.size !== undefined ) ? parseFloat( effHRotX.size ) : parseFloat( effHRotX );
            if ( ! isNaN( hRxVal ) ) {
                hoverRules.push( '--lk-transform-rotate-x: ' + hRxVal + 'deg' );
            }

            // Rotate Y
            var effHRotY = hasPropVal( hRotY ) ? hRotY : tRotateY;
            var hRyVal = ( effHRotY && typeof effHRotY === 'object' && effHRotY.size !== undefined ) ? parseFloat( effHRotY.size ) : parseFloat( effHRotY );
            if ( ! isNaN( hRyVal ) ) {
                hoverRules.push( '--lk-transform-rotate-y: ' + hRyVal + 'deg' );
            }

            // Flip X
            var effHFlipX = hasPropVal( hFlipX ) ? hFlipX : tFlipX;
            if ( effHFlipX === 'yes' || effHFlipX === true ) {
                hoverRules.push( '--lk-transform-flip-x: -1' );
            } else if ( tFlipX === 'yes' || tFlipX === true ) {
                hoverRules.push( '--lk-transform-flip-x: 1' );
            }

            // Flip Y
            var effHFlipY = hasPropVal( hFlipY ) ? hFlipY : tFlipY;
            if ( effHFlipY === 'yes' || effHFlipY === true ) {
                hoverRules.push( '--lk-transform-flip-y: -1' );
            } else if ( tFlipY === 'yes' || tFlipY === true ) {
                hoverRules.push( '--lk-transform-flip-y: 1' );
            }

            // Anchor Point on Hover
            if ( hasPropVal( hAncX ) || hasPropVal( hAncY ) ) {
                var hAxNum = ( hAncX && typeof hAncX === 'object' && hAncX.size !== undefined ) ? parseFloat( hAncX.size ) : parseFloat( hAncX );
                var hAyNum = ( hAncY && typeof hAncY === 'object' && hAncY.size !== undefined ) ? parseFloat( hAncY.size ) : parseFloat( hAncY );
                if ( isNaN( hAxNum ) ) hAxNum = axNum;
                if ( isNaN( hAyNum ) ) hAyNum = ayNum;
                hoverRules.push( 'transform-origin: ' + hAxNum + '% ' + hAyNum + '%' );
            }

            // Perspective on Hover
            var effHPersp = hasPropVal( hPersp ) ? hPersp : tPerspective;
            var hPVal = ( effHPersp && typeof effHPersp === 'object' && effHPersp.size !== undefined ) ? parseFloat( effHPersp.size ) : parseFloat( effHPersp );
            if ( ! isNaN( hPVal ) && hPVal > 0 ) {
                hoverParentRules.push( 'perspective: ' + hPVal + 'px' );
            }

            var hoverMotionTransform = 'translateX(var(--lk-scroll-translate-x, 0)) translateY(var(--lk-scroll-translate-y, 0)) rotate(var(--lk-scroll-rotate, 0deg)) scale(var(--lk-scroll-scale, 1)) rotateX(var(--lk-mouse-rotate-x, 0deg)) rotateY(var(--lk-mouse-rotate-y, 0deg)) translateX(var(--lk-mouse-translate-x, 0)) translateY(var(--lk-mouse-translate-y, 0)) rotateX(var(--lk-transform-rotate-x, 0deg)) rotateY(var(--lk-transform-rotate-y, 0deg)) scaleX(var(--lk-transform-flip-x, 1)) scaleY(var(--lk-transform-flip-y, 1))';
            if ( hoverTransformParts.length ) {
                hoverMotionTransform += ' ' + hoverTransformParts.join( ' ' );
            }
            hoverRules.push( 'transform: ' + hoverMotionTransform );
        }

        // -------------------------------------------------------------
        // DIMENSIONS: PADDING & MARGIN (Section 3 & 8)
        // -------------------------------------------------------------
        var padding = getDeviceExplicitValue( settings, 'padding', device );
        if ( padding && typeof padding === 'object' ) {
            var pUnit = padding.unit || 'px';
            [ 'top', 'right', 'bottom', 'left' ].forEach( function( side ) {
                if ( padding[ side ] !== undefined && padding[ side ] !== '' ) {
                    baseRules.push( 'padding-' + side + ': ' + padding[ side ] + pUnit );
                }
            });
        }

        var margin = getDeviceExplicitValue( settings, 'margin', device );
        if ( margin && typeof margin === 'object' ) {
            var mUnit = margin.unit || 'px';
            [ 'top', 'right', 'bottom', 'left' ].forEach( function( side ) {
                if ( margin[ side ] !== undefined && margin[ side ] !== '' ) {
                    baseRules.push( 'margin-' + side + ': ' + margin[ side ] + mUnit );
                }
            });
        }

        // -------------------------------------------------------------
        // PART B: BACKGROUND (NORMAL & HOVER, CLASSIC & GRADIENT)
        // -------------------------------------------------------------
        var overlayRules = [];
        var overlayHoverRules = [];
        var dividerTopRules = [];
        var dividerTopSvgRules = [];
        var dividerBottomRules = [];
        var dividerBottomSvgRules = [];

        function buildBgRules( prefix, isHover ) {
            var rules = [];

            function getKey( base ) {
                if ( prefix === 'background_overlay_' ) {
                    var suffix = base.replace( /^background_/, '' );
                    var key1 = isHover ? ( 'background_overlay_' + suffix + '_hover' ) : ( 'background_overlay_' + suffix );
                    var key1Alt = isHover ? ( 'background_overlay_hover_' + suffix ) : key1;
                    var key2 = isHover ? ( 'background_overlay_background_' + suffix + '_hover' ) : ( 'background_overlay_background_' + suffix );
                    var key2Alt = isHover ? ( 'background_overlay_hover_background_' + suffix ) : key2;
                    if ( settings[ key1 ] !== undefined || settings[ key1 + '_' + device ] !== undefined ) {
                        return key1;
                    }
                    if ( settings[ key1Alt ] !== undefined || settings[ key1Alt + '_' + device ] !== undefined ) {
                        return key1Alt;
                    }
                    if ( settings[ key2 ] !== undefined || settings[ key2 + '_' + device ] !== undefined ) {
                        return key2;
                    }
                    if ( settings[ key2Alt ] !== undefined || settings[ key2Alt + '_' + device ] !== undefined ) {
                        return key2Alt;
                    }
                    return key1;
                }
                if ( ! isHover ) {
                    return prefix + base;
                }
                var keySuff = prefix + base + '_hover';
                var infixBase = base.replace( /^([a-z0-9]+)_/, '$1_hover_' );
                var keyInfix = prefix + infixBase;
                if ( settings[ keySuff ] !== undefined || settings[ keySuff + '_' + device ] !== undefined ) {
                    return keySuff;
                }
                if ( settings[ keyInfix ] !== undefined || settings[ keyInfix + '_' + device ] !== undefined ) {
                    return keyInfix;
                }
                return keySuff;
            }

            var typeKey = getKey( 'background_type' );
            var bgType = getDeviceExplicitValue( settings, typeKey, device ) || getDeviceExplicitValue( settings, getKey( 'background_background' ), device );
            if ( ! bgType ) {
                var gCol1Check = getDeviceExplicitValue( settings, getKey( 'background_gradient_color' ), device );
                var gCol2Check = getDeviceExplicitValue( settings, getKey( 'background_gradient_second_color' ), device );
                if ( gCol1Check || gCol2Check ) {
                    bgType = 'gradient';
                } else {
                    bgType = 'classic';
                }
            }

            if ( bgType === 'gradient' ) {
                var gCol1 = getDeviceExplicitValue( settings, getKey( 'background_gradient_color' ), device ) || '#38A8F5';
                var gLoc1 = getDeviceExplicitValue( settings, getKey( 'background_gradient_location' ), device );
                if ( gLoc1 === undefined || gLoc1 === '' ) gLoc1 = 0;
                var gCol2 = getDeviceExplicitValue( settings, getKey( 'background_gradient_second_color' ), device ) || '#9B51E0';
                var gLoc2 = getDeviceExplicitValue( settings, getKey( 'background_gradient_second_location' ), device );
                if ( gLoc2 === undefined || gLoc2 === '' ) gLoc2 = 100;
                var gType = getDeviceExplicitValue( settings, getKey( 'background_gradient_type' ), device ) || 'linear';

                if ( gType === 'radial' ) {
                    var gPos = getDeviceExplicitValue( settings, getKey( 'background_gradient_position' ), device ) || 'center center';
                    rules.push( 'background-image: radial-gradient(at ' + gPos + ', ' + resolveGlobalValue( gCol1 ) + ' ' + gLoc1 + '%, ' + resolveGlobalValue( gCol2 ) + ' ' + gLoc2 + '%)' );
                } else {
                    var gAngle = getDeviceExplicitValue( settings, getKey( 'background_gradient_angle' ), device );
                    if ( gAngle === undefined || gAngle === '' ) gAngle = 180;
                    rules.push( 'background-image: linear-gradient(' + gAngle + 'deg, ' + resolveGlobalValue( gCol1 ) + ' ' + gLoc1 + '%, ' + resolveGlobalValue( gCol2 ) + ' ' + gLoc2 + '%)' );
                }
            } else if ( bgType === 'classic' || ! bgType ) {
                var colorKey = getKey( 'background_color' );
                var col = getDeviceExplicitValue( settings, colorKey, device );
                if ( col ) rules.push( 'background-color: ' + resolveGlobalValue( col ) );

                var imgKey = getKey( 'background_image' );
                var bImg = getDeviceExplicitValue( settings, imgKey, device );
                var bUrl = '';
                if ( bImg ) {
                    if ( typeof bImg === 'object' && bImg.url ) bUrl = bImg.url;
                    else if ( typeof bImg === 'string' ) bUrl = bImg;
                }
                if ( bUrl && bUrl.trim() !== '' ) {
                    rules.push( 'background-image: url("' + bUrl.replace( /"/g, '\\"' ) + '")' );

                    var posKey = getKey( 'background_position' );
                    var bPos = getDeviceExplicitValue( settings, posKey, device );
                    if ( bPos === 'custom' ) {
                        var xKey = getKey( 'background_x_position' );
                        var yKey = getKey( 'background_y_position' );
                        var bX = getDeviceExplicitValue( settings, xKey, device );
                        var bY = getDeviceExplicitValue( settings, yKey, device );
                        var xS = '0%', yS = '0%';
                        if ( bX !== undefined && bX !== null && bX !== '' ) {
                            xS = ( typeof bX === 'object' && bX.size !== undefined ) ? bX.size + ( bX.unit || 'px' ) : bX + 'px';
                        }
                        if ( bY !== undefined && bY !== null && bY !== '' ) {
                            yS = ( typeof bY === 'object' && bY.size !== undefined ) ? bY.size + ( bY.unit || 'px' ) : bY + 'px';
                        }
                        rules.push( 'background-position: ' + xS + ' ' + yS );
                    } else if ( bPos && bPos !== 'default' ) {
                        rules.push( 'background-position: ' + bPos );
                    }

                    var attachKey = getKey( 'background_attachment' );
                    var bAttach = getDeviceExplicitValue( settings, attachKey, device );
                    if ( device === 'desktop' ) {
                        if ( bAttach && bAttach !== 'default' ) rules.push( 'background-attachment: ' + bAttach );
                    } else {
                        var effAtt = settings[ attachKey ];
                        if ( effAtt === 'fixed' || bAttach === 'fixed' ) rules.push( 'background-attachment: scroll' );
                        else if ( bAttach && bAttach !== 'default' ) rules.push( 'background-attachment: ' + bAttach );
                    }

                    var repKey = getKey( 'background_repeat' );
                    var bRep = getDeviceExplicitValue( settings, repKey, device );
                    if ( bRep && bRep !== 'default' ) rules.push( 'background-repeat: ' + bRep );

                    var sizeKey = getKey( 'background_size' );
                    var bSize = getDeviceExplicitValue( settings, sizeKey, device );
                    if ( bSize === 'custom' ) {
                        var cSizeKey = getKey( 'background_custom_size' );
                        var bCSize = getDeviceExplicitValue( settings, cSizeKey, device );
                        if ( bCSize !== undefined && bCSize !== null && bCSize !== '' ) {
                            var sStr = ( typeof bCSize === 'object' && bCSize.size !== undefined ) ? bCSize.size + ( bCSize.unit || 'px' ) : bCSize + 'px';
                            rules.push( 'background-size: ' + sStr + ' auto' );
                        }
                    } else if ( bSize && bSize !== 'default' ) {
                        rules.push( 'background-size: ' + bSize );
                    }
                } else if ( isHover && col ) {
                    // Hover has a classic solid color and no hover image:
                    // Clear any background-image from normal (gradient or image) so solid hover color displays cleanly
                    rules.push( 'background-image: none' );
                }
            }
            return rules;
        }

        // Element Normal Background
        var normalBgRules = buildBgRules( '', false );
        for ( var nbi = 0; nbi < normalBgRules.length; nbi++ ) {
            baseRules.push( normalBgRules[nbi] );
        }

        // Element Hover Background
        var hoverBgRules = buildBgRules( '', true );
        for ( var hbi = 0; hbi < hoverBgRules.length; hbi++ ) {
            hoverRules.push( hoverBgRules[hbi] );
        }

        // Hover Transition Duration (outputs transition on normal state)
        var bgTrans = getDeviceExplicitValue( settings, 'background_hover_transition_duration', device );
        var bdrTrans = getDeviceExplicitValue( settings, 'border_hover_transition_duration', device );
        var genTrans = getDeviceExplicitValue( settings, 'hover_transition_duration', device );
        var effTrans = bgTrans || bdrTrans || genTrans;
        if ( effTrans !== undefined && effTrans !== '' ) {
            baseRules.push( 'transition: all ' + parseFloat( effTrans ) + 's ease' );
        }

        // -------------------------------------------------------------
        // PART B: BORDER, BORDER RADIUS, BOX SHADOW (NORMAL & HOVER)
        // -------------------------------------------------------------
        function buildBorderRules( isHover ) {
            var rules = [];
            var prefix = isHover ? 'border_hover_' : '';
            var typeKey = isHover ? 'border_type_hover' : 'border_type';
            var widthKey = isHover ? 'border_width_hover' : 'border_width';
            var colorKey = isHover ? 'border_color_hover' : 'border_color';
            var radiusKey = isHover ? 'border_radius_hover' : 'border_radius';
            var shadowKey = isHover ? 'box_shadow_hover' : 'box_shadow';

            var bType = getDeviceExplicitValue( settings, typeKey, device );
            if ( ! bType && isHover ) bType = getDeviceExplicitValue( settings, 'border_hover_type', device );
            if ( ! bType ) bType = getDeviceExplicitValue( settings, isHover ? 'border_border_hover' : 'border_border', device );
            if ( ! bType && isHover ) bType = getDeviceExplicitValue( settings, 'border_hover_border', device );
            // Backward compatibility with legacy border object/string
            if ( ! isHover && ! bType ) {
                var legacyBorder = getDeviceExplicitValue( settings, 'border', device );
                if ( legacyBorder ) {
                    if ( typeof legacyBorder === 'object' && legacyBorder.type ) bType = legacyBorder.type;
                    else if ( typeof legacyBorder === 'string' ) bType = legacyBorder;
                }
            }

            if ( bType && bType !== 'none' && bType !== 'default' ) {
                rules.push( 'border-style: ' + bType );

                var bCol = getDeviceExplicitValue( settings, colorKey, device );
                if ( ! bCol && isHover ) bCol = getDeviceExplicitValue( settings, 'border_hover_color', device );
                if ( ! isHover && ! bCol && settings.border && settings.border.color ) bCol = settings.border.color;
                if ( bCol ) rules.push( 'border-color: ' + resolveGlobalValue( bCol ) );

                var bWidth = getDeviceExplicitValue( settings, widthKey, device );
                if ( ! bWidth && isHover ) bWidth = getDeviceExplicitValue( settings, 'border_hover_width', device );
                if ( ! isHover && ! bWidth && settings.border && settings.border.width ) bWidth = settings.border.width;
                if ( bWidth && typeof bWidth === 'object' ) {
                    var u = bWidth.unit || 'px';
                    var bTop = ( bWidth.top !== undefined && bWidth.top !== '' ) ? bWidth.top + u : '0';
                    var bRt  = ( bWidth.right !== undefined && bWidth.right !== '' ) ? bWidth.right + u : '0';
                    var bBtm = ( bWidth.bottom !== undefined && bWidth.bottom !== '' ) ? bWidth.bottom + u : '0';
                    var bLt  = ( bWidth.left !== undefined && bWidth.left !== '' ) ? bWidth.left + u : '0';
                    rules.push( 'border-width: ' + bTop + ' ' + bRt + ' ' + bBtm + ' ' + bLt );
                }
            }

            // Per-side border colour, mirroring the PHP generator. Emitted after the
            // single border-color so it wins for that side only. With none set,
            // nothing is emitted and existing sites are unaffected.
            [ 'top', 'right', 'bottom', 'left' ].forEach( function ( side ) {
                var sideVal = getDeviceExplicitValue( settings, ( isHover ? 'border_color_' + side + '_hover' : 'border_color_' + side ), device );
                if ( ! sideVal && isHover ) sideVal = getDeviceExplicitValue( settings, 'border_' + side + '_color_hover', device );
                if ( sideVal && 'none' !== sideVal ) {
                    rules.push( 'border-' + side + '-color: ' + sideVal );
                }
            } );

            var bRadius = getDeviceExplicitValue( settings, radiusKey, device );
            if ( ! isHover && ! bRadius && settings.border && settings.border.radius ) bRadius = settings.border.radius;
            if ( bRadius && typeof bRadius === 'object' ) {
                var ru = bRadius.unit || 'px';
                var rTop = ( bRadius.top !== undefined && bRadius.top !== '' ) ? bRadius.top + ru : '0';
                var rRt  = ( bRadius.right !== undefined && bRadius.right !== '' ) ? bRadius.right + ru : '0';
                var rBtm = ( bRadius.bottom !== undefined && bRadius.bottom !== '' ) ? bRadius.bottom + ru : '0';
                var rLt  = ( bRadius.left !== undefined && bRadius.left !== '' ) ? bRadius.left + ru : '0';
                rules.push( 'border-radius: ' + rTop + ' ' + rRt + ' ' + rBtm + ' ' + rLt );
            }

            var bShadow = getDeviceExplicitValue( settings, shadowKey, device );
            if ( bShadow && typeof bShadow === 'object' && bShadow.color ) {
                var bsH = bShadow.horizontal || 0;
                var bsV = bShadow.vertical || 0;
                var bsB = bShadow.blur || 0;
                var bsS = bShadow.spread || 0;
                var bsInset = bShadow.position === 'inset' ? 'inset ' : '';
                rules.push( 'box-shadow: ' + bsInset + bsH + 'px ' + bsV + 'px ' + bsB + 'px ' + bsS + 'px ' + resolveGlobalValue( bShadow.color ) );
            }

            return rules;
        }

        var normalBdrRules = buildBorderRules( false );
        for ( var nri = 0; nri < normalBdrRules.length; nri++ ) {
            baseRules.push( normalBdrRules[nri] );
        }

        var hoverBdrRules = buildBorderRules( true );
        for ( var hri = 0; hri < hoverBdrRules.length; hri++ ) {
            hoverRules.push( hoverBdrRules[hri] );
        }

        // -------------------------------------------------------------
        // PART B: BACKGROUND OVERLAY (NORMAL & HOVER)
        // -------------------------------------------------------------
        var ovImgHov = settings.background_overlay_image_hover || settings.background_overlay_hover_image;
        var ovTypeHov = settings.background_overlay_type_hover || settings.background_overlay_hover_type || settings.background_overlay_hover_background;
        var hasNormOverlayBg = ( settings.background_overlay_color && String( settings.background_overlay_color ).trim() !== '' ) ||
                               ( settings.background_overlay_image && ( ( typeof settings.background_overlay_image === 'object' && settings.background_overlay_image.url ) || ( typeof settings.background_overlay_image === 'string' && settings.background_overlay_image.trim() !== '' ) ) ) ||
                               ( settings.background_overlay_type === 'gradient' && ( settings.background_overlay_gradient_color || settings.background_overlay_gradient_second_color ) );

        var hasOverlay = ( elType === 'section' || elType === 'container' ) && (
            hasNormOverlayBg ||
            ( settings.background_overlay_color_hover && String( settings.background_overlay_color_hover ).trim() !== '' ) ||
            ( settings.background_overlay_hover_color && String( settings.background_overlay_hover_color ).trim() !== '' ) ||
            ( ovImgHov && ( ( typeof ovImgHov === 'object' && ovImgHov.url ) || ( typeof ovImgHov === 'string' && ovImgHov.trim() !== '' ) ) ) ||
            ( ovTypeHov === 'gradient' && ( settings.background_overlay_gradient_color_hover || settings.background_overlay_hover_gradient_color || settings.background_overlay_gradient_second_color_hover || settings.background_overlay_hover_gradient_second_color ) ) ||
            ( settings.background_overlay_blend_mode && settings.background_overlay_blend_mode !== 'normal' ) ||
            ( settings.background_overlay_css_filters && typeof settings.background_overlay_css_filters === 'object' ) ||
            ( settings.background_overlay_css_filters_hover && typeof settings.background_overlay_css_filters_hover === 'object' ) ||
            ( settings.background_overlay_hover_css_filters && typeof settings.background_overlay_hover_css_filters === 'object' ) ||
            ( settings.background_overlay_opacity_hover !== undefined && settings.background_overlay_opacity_hover !== '' ) ||
            ( settings.background_overlay_hover_opacity !== undefined && settings.background_overlay_hover_opacity !== '' ) ||
            ( settings.background_overlay_hover_transition_duration !== undefined && settings.background_overlay_hover_transition_duration !== '' )
        );

        if ( hasOverlay ) {
            if ( device === 'desktop' ) {
                overlayRules.push( 'position: absolute' );
                overlayRules.push( 'inset: 0' );
                overlayRules.push( 'width: 100%' );
                overlayRules.push( 'height: 100%' );
                overlayRules.push( 'pointer-events: none' );
                overlayRules.push( 'z-index: 0' );
                overlayRules.push( 'border-radius: inherit' );
            }

            // Normal Overlay Background
            var ovNormBg = buildBgRules( 'background_overlay_', false );
            for ( var oni = 0; oni < ovNormBg.length; oni++ ) overlayRules.push( ovNormBg[oni] );

            // Normal Overlay Opacity (safely handle plain float or legacy {size, unit} object)
            var ovOp = getDeviceExplicitValue( settings, 'background_overlay_opacity', device );
            if ( ovOp === undefined && device === 'desktop' && settings.background_overlay_opacity !== undefined ) ovOp = settings.background_overlay_opacity;
            if ( ovOp !== undefined && ovOp !== null && typeof ovOp === 'object' ) {
                ovOp = ( ovOp.size !== undefined ) ? ovOp.size : '';
            }
            if ( typeof ovOp === 'string' ) {
                var mOp = ovOp.match( /^([0-9]+(?:\.[0-9]+)?)/ );
                if ( mOp ) ovOp = parseFloat( mOp[1] );
            }
            if ( ovOp !== undefined && ovOp !== null && ovOp !== '' && ! isNaN( parseFloat( ovOp ) ) ) {
                overlayRules.push( 'opacity: ' + parseFloat( ovOp ) );
            } else if ( ! hasNormOverlayBg ) {
                overlayRules.push( 'opacity: 0' );
            } else if ( device === 'desktop' ) {
                overlayRules.push( 'opacity: 0.5' );
            }

            // Normal Overlay Blend Mode
            var ovBlend = getDeviceExplicitValue( settings, 'background_overlay_blend_mode', device );
            if ( ovBlend && ovBlend !== 'normal' ) overlayRules.push( 'mix-blend-mode: ' + ovBlend );

            // Normal Overlay CSS Filters
            var ovFilters = getDeviceExplicitValue( settings, 'background_overlay_css_filters', device );
            if ( ovFilters && typeof ovFilters === 'object' ) {
                var fParts = [];
                if ( ovFilters.blur ) fParts.push( 'blur(' + ovFilters.blur + 'px)' );
                if ( ovFilters.brightness !== undefined && ovFilters.brightness !== '' && ovFilters.brightness != 100 ) fParts.push( 'brightness(' + ovFilters.brightness + '%)' );
                if ( ovFilters.contrast !== undefined && ovFilters.contrast !== '' && ovFilters.contrast != 100 ) fParts.push( 'contrast(' + ovFilters.contrast + '%)' );
                if ( ovFilters.saturation !== undefined && ovFilters.saturation !== '' && ovFilters.saturation != 100 ) fParts.push( 'saturate(' + ovFilters.saturation + '%)' );
                if ( ovFilters.hue ) fParts.push( 'hue-rotate(' + ovFilters.hue + 'deg)' );
                if ( fParts.length ) overlayRules.push( 'filter: ' + fParts.join( ' ' ) );
            }

            // Normal Overlay Hover Transition
            var ovTrans = getDeviceExplicitValue( settings, 'background_overlay_hover_transition_duration', device );
            if ( ovTrans === undefined ) ovTrans = getDeviceExplicitValue( settings, 'background_overlay_transition_duration', device );
            if ( ovTrans !== undefined && ovTrans !== null && typeof ovTrans === 'object' ) {
                ovTrans = ( ovTrans.size !== undefined ) ? ovTrans.size : '';
            }
            if ( ovTrans !== undefined && ovTrans !== '' && ! isNaN( parseFloat( ovTrans ) ) ) {
                overlayRules.push( 'transition: all ' + parseFloat( ovTrans ) + 's ease' );
            }

            // Hover Overlay Rules
            var ovHovBg = buildBgRules( 'background_overlay_', true );
            for ( var ohi = 0; ohi < ovHovBg.length; ohi++ ) overlayHoverRules.push( ovHovBg[ohi] );

            var ovHovOp = getDeviceExplicitValue( settings, 'background_overlay_opacity_hover', device );
            if ( ovHovOp === undefined ) ovHovOp = getDeviceExplicitValue( settings, 'background_overlay_hover_opacity', device );
            if ( ovHovOp !== undefined && ovHovOp !== null && typeof ovHovOp === 'object' ) {
                ovHovOp = ( ovHovOp.size !== undefined ) ? ovHovOp.size : '';
            }
            if ( typeof ovHovOp === 'string' ) {
                var mHop = ovHovOp.match( /^([0-9]+(?:\.[0-9]+)?)/ );
                if ( mHop ) ovHovOp = parseFloat( mHop[1] );
            }
            if ( ovHovOp !== undefined && ovHovOp !== null && ovHovOp !== '' && ! isNaN( parseFloat( ovHovOp ) ) ) {
                overlayHoverRules.push( 'opacity: ' + parseFloat( ovHovOp ) );
            } else if ( overlayHoverRules.length ) {
                overlayHoverRules.push( 'opacity: 0.5' );
            }

            // If hover overlay exists but no transition duration was set, default to 0.3s
            if ( overlayHoverRules.length && ( ovTrans === undefined || ovTrans === '' ) ) {
                overlayRules.push( 'transition: all 0.3s ease' );
            }

            var ovHovFilters = getDeviceExplicitValue( settings, 'background_overlay_css_filters_hover', device );
            if ( ovHovFilters && typeof ovHovFilters === 'object' ) {
                var hfParts = [];
                if ( ovHovFilters.blur ) hfParts.push( 'blur(' + ovHovFilters.blur + 'px)' );
                if ( ovHovFilters.brightness !== undefined && ovHovFilters.brightness !== '' && ovHovFilters.brightness != 100 ) hfParts.push( 'brightness(' + ovHovFilters.brightness + '%)' );
                if ( ovHovFilters.contrast !== undefined && ovHovFilters.contrast !== '' && ovHovFilters.contrast != 100 ) hfParts.push( 'contrast(' + ovHovFilters.contrast + '%)' );
                if ( ovHovFilters.saturation !== undefined && ovHovFilters.saturation !== '' && ovHovFilters.saturation != 100 ) hfParts.push( 'saturate(' + ovHovFilters.saturation + '%)' );
                if ( ovHovFilters.hue ) hfParts.push( 'hue-rotate(' + ovHovFilters.hue + 'deg)' );
                if ( hfParts.length ) overlayHoverRules.push( 'filter: ' + hfParts.join( ' ' ) );
            }
        }

        // -------------------------------------------------------------
        // PART B: SHAPE DIVIDERS (TOP & BOTTOM)
        // -------------------------------------------------------------
        if ( elType === 'section' || elType === 'container' ) {
            // Top Divider
            var sTopType = getDeviceExplicitValue( settings, 'shape_divider_top_type', device ) || ( device === 'desktop' ? settings.shape_divider_top_type : '' );
            if ( sTopType && sTopType !== 'none' ) {
                var stHeight = getDeviceExplicitValue( settings, 'shape_divider_top_height', device ) || ( device === 'desktop' ? ( settings.shape_divider_top_height || 50 ) : '' );
                var stColor = getDeviceExplicitValue( settings, 'shape_divider_top_color', device ) || ( device === 'desktop' ? ( settings.shape_divider_top_color || '#FFFFFF' ) : '' );
                var stWidth = getDeviceExplicitValue( settings, 'shape_divider_top_width', device ) || ( device === 'desktop' ? ( settings.shape_divider_top_width || 100 ) : '' );
                var stFlip = ( settings.shape_divider_top_flip === 'yes' || settings.shape_divider_top_flip === true );
                var stInvert = ( settings.shape_divider_top_invert === 'yes' || settings.shape_divider_top_invert === true );
                var stFront = ( settings.shape_divider_top_front === 'yes' || settings.shape_divider_top_front === true );

                if ( stHeight ) dividerTopRules.push( 'height: ' + stHeight + 'px' );
                if ( stFront ) dividerTopRules.push( 'z-index: 10' );

                if ( stColor ) dividerTopSvgRules.push( 'fill: ' + resolveGlobalValue( stColor ) );
                if ( stHeight ) dividerTopSvgRules.push( 'height: ' + stHeight + 'px' );

                var tWidthNum = parseFloat( stWidth ) || 100;
                var tTransformParts = [];
                if ( tWidthNum !== 100 ) {
                    dividerTopSvgRules.push( 'width: ' + tWidthNum + '%' );
                    dividerTopSvgRules.push( 'left: 50%' );
                    tTransformParts.push( 'translateX(-50%)' );
                }
                if ( stFlip && stInvert ) tTransformParts.push( 'scale(-1, -1)' );
                else if ( stFlip ) tTransformParts.push( 'scaleX(-1)' );
                else if ( stInvert ) tTransformParts.push( 'scaleY(-1)' );

                if ( tTransformParts.length ) {
                    dividerTopSvgRules.push( 'transform: ' + tTransformParts.join( ' ' ) );
                    dividerTopSvgRules.push( 'transform-origin: center' );
                }
            }

            // Bottom Divider
            var sBtmType = getDeviceExplicitValue( settings, 'shape_divider_bottom_type', device ) || ( device === 'desktop' ? settings.shape_divider_bottom_type : '' );
            if ( sBtmType && sBtmType !== 'none' ) {
                var sbHeight = getDeviceExplicitValue( settings, 'shape_divider_bottom_height', device ) || ( device === 'desktop' ? ( settings.shape_divider_bottom_height || 50 ) : '' );
                var sbColor = getDeviceExplicitValue( settings, 'shape_divider_bottom_color', device ) || ( device === 'desktop' ? ( settings.shape_divider_bottom_color || '#FFFFFF' ) : '' );
                var sbWidth = getDeviceExplicitValue( settings, 'shape_divider_bottom_width', device ) || ( device === 'desktop' ? ( settings.shape_divider_bottom_width || 100 ) : '' );
                var sbFlip = ( settings.shape_divider_bottom_flip === 'yes' || settings.shape_divider_bottom_flip === true );
                var sbInvert = ( settings.shape_divider_bottom_invert === 'yes' || settings.shape_divider_bottom_invert === true );
                var sbFront = ( settings.shape_divider_bottom_front === 'yes' || settings.shape_divider_bottom_front === true );

                if ( sbHeight ) dividerBottomRules.push( 'height: ' + sbHeight + 'px' );
                if ( sbFront ) dividerBottomRules.push( 'z-index: 10' );

                if ( sbColor ) dividerBottomSvgRules.push( 'fill: ' + resolveGlobalValue( sbColor ) );
                if ( sbHeight ) dividerBottomSvgRules.push( 'height: ' + sbHeight + 'px' );

                var bWidthNum = parseFloat( sbWidth ) || 100;
                var bTransformParts = [];
                if ( bWidthNum !== 100 ) {
                    dividerBottomSvgRules.push( 'width: ' + bWidthNum + '%' );
                    dividerBottomSvgRules.push( 'left: 50%' );
                    bTransformParts.push( 'translateX(-50%)' );
                }
                if ( sbFlip && sbInvert ) bTransformParts.push( 'scale(-1, -1)' );
                else if ( sbFlip ) bTransformParts.push( 'scaleX(-1)' );
                else if ( sbInvert ) bTransformParts.push( 'scaleY(-1)' );

                if ( bTransformParts.length ) {
                    dividerBottomSvgRules.push( 'transform: ' + bTransformParts.join( ' ' ) );
                    dividerBottomSvgRules.push( 'transform-origin: center' );
                }
            }
        }
        // Responsive Visibility (Section 11: hide on specific device on live page)
        var hideOnDevice = settings[ 'hide_' + device ];
        if ( hideOnDevice === 'yes' || hideOnDevice === true ) {
            // Only output display: none if this is on the front end
            if ( ! window.loopkitEditor ) {
                baseRules.push( 'display: none !important' );
            }
        }

        return {
            baseRules:             baseRules,
            innerRules:            innerRules,
            hoverRules:            hoverRules,
            parentRules:           parentRules,
            hoverParentRules:      hoverParentRules,
            overlayRules:          overlayRules,
            overlayHoverRules:     overlayHoverRules,
            dividerTopRules:       dividerTopRules,
            dividerTopSvgRules:    dividerTopSvgRules,
            dividerBottomRules:    dividerBottomRules,
            dividerBottomSvgRules: dividerBottomSvgRules,
            subRules:              subRules,
        };
    }

    /**
     * Recursively collect all elements in a flat list.
     */
    function flattenElements( elements, list ) {
        list = list || [];
        if ( ! elements || ! elements.length ) return list;
        for ( var i = 0; i < elements.length; i++ ) {
            var el = elements[i];
            if ( ! el ) continue;
            // Skip global instances — their CSS is loaded from the global template
            if ( el.elType === 'global' ) {
                continue;
            }
            list.push( el );
            if ( el.elements && el.elements.length ) {
                flattenElements( el.elements, list );
            }
        }
        return list;
    }

    /**
     * Parse and compile element Custom CSS (Problem 4 & Section 11)
     * Replaces "selector" and "{{WRAPPER}}" with the element's wrapper selector.
     * Strips anything that could break style tags (such as </style>).
     *
     * @param {Object} el Element object.
     * @param {number|string} postId Current post ID.
     * @return {string} Compiled custom CSS.
     */
    function compileElementCustomCss( el, postId ) {
        if ( ! el || ! el.settings ) return '';
        var compiledAll = '';
        var raw = el.settings.custom_css;
        var wrapperSelector = ( postId ? '.loopkit-' + postId + ' ' : '' ) + '.lk-element-' + el.id;

        if ( typeof raw === 'string' && raw.trim() ) {
            // Strip style and script tags
            var clean = raw.replace( /<\/?(style|script)[^>]*>/gi, '' );
            var compiled = clean.replace( /\bselector\b|\{\{WRAPPER\}\}/gi, wrapperSelector );
            if ( el._id ) {
                compiled = compiled.replace( /\{\{CURRENT_ITEM\}\}/gi, '.lk-repeater-item-' + el._id );
            }
            compiledAll += compiled + '\n';
        }

        // Also compile any repeater field items that may contain custom_css
        if ( typeof el.settings === 'object' ) {
            Object.keys( el.settings ).forEach( function( key ) {
                var val = el.settings[ key ];
                if ( Array.isArray( val ) ) {
                    val.forEach( function( item ) {
                        if ( item && typeof item === 'object' && item._id && item.custom_css && typeof item.custom_css === 'string' ) {
                            var iClean = item.custom_css.replace( /<\/?(style|script)[^>]*>/gi, '' );
                            var iComp = iClean.replace( /\bselector\b|\{\{WRAPPER\}\}/gi, wrapperSelector );
                            iComp = iComp.replace( /\{\{CURRENT_ITEM\}\}/gi, '.lk-repeater-item-' + item._id );
                            compiledAll += iComp + '\n';
                        }
                    } );
                }
            } );
        }

        return compiledAll;
    }

    /**
     * Compile a selector template with {{WRAPPER}} and optional {{CURRENT_ITEM}}.
     */
    LoopkitCSS.compileSelector = function( selector, wrapperSelector, itemId ) {
        if ( ! selector ) return '';
        var s = selector.replace( /\bselector\b|\{\{WRAPPER\}\}/gi, wrapperSelector );
        if ( itemId ) {
            s = s.replace( /\{\{CURRENT_ITEM\}\}/gi, '.lk-repeater-item-' + itemId );
        }
        return s;
    };

    /**
     * Generate complete CSS for all elements across all responsive breakpoints.
     * Output order (Section 3):
     * 1. Desktop rules (base, no media query)
     * 2. Widescreen rules (min-width)
     * 3. Max-width media queries from largest to smallest (Laptop, Tablet Landscape, Tablet, Mobile Landscape, Mobile)
     * 4. Custom CSS for each element
     *
     * @param {Array} elements Root elements array.
     * @param {number|string} [postId] Optional post ID.
     * @return {string} Combined CSS string.
     */
    LoopkitCSS.generateAll = function( elements, postId ) {
        var allElements = flattenElements( elements );
        var activeDevices = getActiveDevices();
        postId = postId || ( window.loopkitEditor && window.loopkitEditor.postId ) || '';
        var wrapperPrefix = postId ? '.loopkit-' + postId + ' ' : '';

        // 1. Desktop rules (base)
        var desktopCss = '';
        for ( var i = 0; i < allElements.length; i++ ) {
            var el = allElements[i];
            if ( ! el || ! el.id ) continue;

            var elClass = '.lk-element-' + el.id;
            var selector = wrapperPrefix + elClass + ', ' + elClass;
            var innerSelector = wrapperPrefix + elClass + ' > .lk-section-inner,\n' +
                                elClass + ' > .lk-section-inner,\n' +
                                wrapperPrefix + elClass + ' > .lk-con-inner,\n' +
                                elClass + ' > .lk-con-inner,\n' +
                                wrapperPrefix + elClass + ' > .lk-container-inner,\n' +
                                elClass + ' > .lk-container-inner';

            var dRes = generateDeviceRules( el, 'desktop' );
            if ( dRes.baseRules.length ) {
                desktopCss += selector + ' {\n  ' + dRes.baseRules.join( ';\n  ' ) + ';\n}\n';
            }
            if ( dRes.innerRules.length ) {
                desktopCss += innerSelector + ' {\n  ' + dRes.innerRules.join( ';\n  ' ) + ';\n}\n';
            }
            if ( dRes.hoverRules && dRes.hoverRules.length ) {
                var hoverSelector = ( wrapperPrefix ? wrapperPrefix + elClass + ':hover, ' : '' ) + elClass + ':hover';
                desktopCss += hoverSelector + ' {\n  ' + dRes.hoverRules.join( ';\n  ' ) + ';\n}\n';
            }
            if ( dRes.parentRules && dRes.parentRules.length ) {
                desktopCss += ':has(> ' + elClass + ') {\n  ' + dRes.parentRules.join( ';\n  ' ) + ';\n}\n';
            }
            if ( dRes.hoverParentRules && dRes.hoverParentRules.length ) {
                desktopCss += ':has(> ' + elClass + ':hover) {\n  ' + dRes.hoverParentRules.join( ';\n  ' ) + ';\n}\n';
            }
            if ( dRes.overlayRules && dRes.overlayRules.length ) {
                desktopCss += wrapperPrefix + elClass + ' > .lk-background-overlay, ' + elClass + ' > .lk-background-overlay {\n  ' + dRes.overlayRules.join( ';\n  ' ) + ';\n}\n';
            }
            if ( dRes.overlayHoverRules && dRes.overlayHoverRules.length ) {
                desktopCss += wrapperPrefix + elClass + ':hover > .lk-background-overlay, ' + elClass + ':hover > .lk-background-overlay {\n  ' + dRes.overlayHoverRules.join( ';\n  ' ) + ';\n}\n';
            }
            if ( dRes.dividerTopRules && dRes.dividerTopRules.length ) {
                desktopCss += wrapperPrefix + elClass + ' > .lk-shape-divider--top, ' + elClass + ' > .lk-shape-divider--top {\n  ' + dRes.dividerTopRules.join( ';\n  ' ) + ';\n}\n';
            }
            if ( dRes.dividerTopSvgRules && dRes.dividerTopSvgRules.length ) {
                desktopCss += wrapperPrefix + elClass + ' > .lk-shape-divider--top svg, ' + elClass + ' > .lk-shape-divider--top svg {\n  ' + dRes.dividerTopSvgRules.join( ';\n  ' ) + ';\n}\n';
            }
            if ( dRes.dividerBottomRules && dRes.dividerBottomRules.length ) {
                desktopCss += wrapperPrefix + elClass + ' > .lk-shape-divider--bottom, ' + elClass + ' > .lk-shape-divider--bottom {\n  ' + dRes.dividerBottomRules.join( ';\n  ' ) + ';\n}\n';
            }
            if ( dRes.dividerBottomSvgRules && dRes.dividerBottomSvgRules.length ) {
                desktopCss += wrapperPrefix + elClass + ' > .lk-shape-divider--bottom svg, ' + elClass + ' > .lk-shape-divider--bottom svg {\n  ' + dRes.dividerBottomSvgRules.join( ';\n  ' ) + ';\n}\n';
            }
            if ( dRes.subRules ) {
                for ( var subSel in dRes.subRules ) {
                    if ( dRes.subRules.hasOwnProperty( subSel ) && dRes.subRules[ subSel ].length ) {
                        var fullSub = ( wrapperPrefix ? wrapperPrefix + elClass + subSel + ', ' : '' ) + elClass + subSel;
                        desktopCss += fullSub + ' {\n  ' + dRes.subRules[ subSel ].join( ';\n  ' ) + ';\n}\n';
                    }
                }
            }
        }

        var fullCss = desktopCss;

        // 2. Media queries for other active devices
        for ( var b = 0; b < BREAKPOINT_DEFS.length; b++ ) {
            var bp = BREAKPOINT_DEFS[b];
            var devId = bp.id;

            // Only output active breakpoints (Section 2 & 3)
            if ( activeDevices.indexOf( devId ) === -1 ) continue;

            var bpCss = '';
            for ( var j = 0; j < allElements.length; j++ ) {
                var bEl = allElements[j];
                if ( ! bEl || ! bEl.id ) continue;

                var bElClass = '.lk-element-' + bEl.id;
                var bSelector = wrapperPrefix + bElClass + ', ' + bElClass;
                var bInnerSelector = wrapperPrefix + bElClass + ' > .lk-section-inner,\n  ' +
                                     bElClass + ' > .lk-section-inner,\n  ' +
                                     wrapperPrefix + bElClass + ' > .lk-con-inner,\n  ' +
                                     bElClass + ' > .lk-con-inner,\n  ' +
                                     wrapperPrefix + bElClass + ' > .lk-container-inner,\n  ' +
                                     bElClass + ' > .lk-container-inner';

                var res = generateDeviceRules( bEl, devId );
                if ( res.baseRules.length ) {
                    bpCss += '  ' + bSelector + ' {\n    ' + res.baseRules.join( ';\n    ' ) + ';\n  }\n';
                }
                if ( res.innerRules.length ) {
                    bpCss += '  ' + bInnerSelector + ' {\n    ' + res.innerRules.join( ';\n    ' ) + ';\n  }\n';
                }
                if ( res.hoverRules && res.hoverRules.length ) {
                    var bHoverSelector = ( wrapperPrefix ? wrapperPrefix + bElClass + ':hover, ' : '' ) + bElClass + ':hover';
                    bpCss += '  ' + bHoverSelector + ' {\n    ' + res.hoverRules.join( ';\n    ' ) + ';\n  }\n';
                }
                if ( res.parentRules && res.parentRules.length ) {
                    bpCss += '  :has(> ' + bElClass + ') {\n    ' + res.parentRules.join( ';\n    ' ) + ';\n  }\n';
                }
                if ( res.hoverParentRules && res.hoverParentRules.length ) {
                    bpCss += '  :has(> ' + bElClass + ':hover) {\n    ' + res.hoverParentRules.join( ';\n    ' ) + ';\n  }\n';
                }
                if ( res.overlayRules && res.overlayRules.length ) {
                    bpCss += '  ' + wrapperPrefix + bElClass + ' > .lk-background-overlay, ' + bElClass + ' > .lk-background-overlay {\n    ' + res.overlayRules.join( ';\n    ' ) + ';\n  }\n';
                }
                if ( res.overlayHoverRules && res.overlayHoverRules.length ) {
                    bpCss += '  ' + wrapperPrefix + bElClass + ':hover > .lk-background-overlay, ' + bElClass + ':hover > .lk-background-overlay {\n    ' + res.overlayHoverRules.join( ';\n    ' ) + ';\n  }\n';
                }
                if ( res.dividerTopRules && res.dividerTopRules.length ) {
                    bpCss += '  ' + wrapperPrefix + bElClass + ' > .lk-shape-divider--top, ' + bElClass + ' > .lk-shape-divider--top {\n    ' + res.dividerTopRules.join( ';\n    ' ) + ';\n  }\n';
                }
                if ( res.dividerTopSvgRules && res.dividerTopSvgRules.length ) {
                    bpCss += '  ' + wrapperPrefix + bElClass + ' > .lk-shape-divider--top svg, ' + bElClass + ' > .lk-shape-divider--top svg {\n    ' + res.dividerTopSvgRules.join( ';\n    ' ) + ';\n  }\n';
                }
                if ( res.dividerBottomRules && res.dividerBottomRules.length ) {
                    bpCss += '  ' + wrapperPrefix + bElClass + ' > .lk-shape-divider--bottom, ' + bElClass + ' > .lk-shape-divider--bottom {\n    ' + res.dividerBottomRules.join( ';\n    ' ) + ';\n  }\n';
                }
                if ( res.dividerBottomSvgRules && res.dividerBottomSvgRules.length ) {
                    bpCss += '  ' + wrapperPrefix + bElClass + ' > .lk-shape-divider--bottom svg, ' + bElClass + ' > .lk-shape-divider--bottom svg {\n    ' + res.dividerBottomSvgRules.join( ';\n    ' ) + ';\n  }\n';
                }
                if ( res.subRules ) {
                    for ( var bSubSel in res.subRules ) {
                        if ( res.subRules.hasOwnProperty( bSubSel ) && res.subRules[ bSubSel ].length ) {
                            var bFullSub = ( wrapperPrefix ? wrapperPrefix + bElClass + bSubSel + ', ' : '' ) + bElClass + bSubSel;
                            bpCss += '  ' + bFullSub + ' {\n    ' + res.subRules[ bSubSel ].join( ';\n    ' ) + ';\n  }\n';
                        }
                    }
                }
            }

            if ( bpCss ) {
                fullCss += '\n@media ' + bp.query + ' {\n' + bpCss + '}\n';
            }
        }

        // 3. Custom CSS from elements (Problem 4 & Section 11)
        var customCssOutput = '';
        for ( var k = 0; k < allElements.length; k++ ) {
            var cEl = allElements[k];
            var compiled = compileElementCustomCss( cEl, postId );
            if ( compiled ) {
                customCssOutput += '/* Custom CSS: ' + cEl.id + ' */\n' + compiled + '\n';
            }
        }

        if ( customCssOutput ) {
            fullCss += '\n/* Element Custom CSS */\n' + customCssOutput;
        }

        return fullCss;
    };

    window.LoopkitCSS = LoopkitCSS;

})( window );
