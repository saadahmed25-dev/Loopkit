/**
 * Loopkit Controls Engine
 * Reusable control renderers, container layout schemas, and interactive event bindings.
 *
 * @package Loopkit
 */

(function( $, window ) {
    'use strict';

    var LoopkitControls = {};

    var siteSettings = ( window.loopkitEditor && window.loopkitEditor.siteSettings ) || {};

    /* =========================================================
       RESPONSIVE BREAKPOINTS & INHERITANCE SYSTEM
       ========================================================= */
    var ACTIVE_DEVICES = [ 'desktop', 'tablet', 'mobile' ];

    var INHERITANCE_CHAIN = {
        widescreen:   [ 'desktop' ],
        desktop:      [ 'desktop' ],
        laptop:       [ 'desktop' ],
        tablet_extra: [ 'laptop', 'desktop' ],
        tablet:       [ 'tablet_extra', 'laptop', 'desktop' ],
        mobile_extra: [ 'tablet', 'tablet_extra', 'laptop', 'desktop' ],
        mobile:       [ 'mobile_extra', 'tablet', 'tablet_extra', 'laptop', 'desktop' ],
    };

    var DEVICE_INFO = {
        widescreen:   { label: 'Widescreen (2400px+)',       icon: 'dashicons-desktop' },
        desktop:      { label: 'Desktop',                   icon: 'dashicons-desktop' },
        laptop:       { label: 'Laptop (≤1366px)',          icon: 'dashicons-laptop' },
        tablet_extra: { label: 'Tablet Landscape (≤1200px)', icon: 'dashicons-tablet' },
        tablet:       { label: 'Tablet Portrait (≤1024px)',  icon: 'dashicons-tablet' },
        mobile_extra: { label: 'Mobile Landscape (≤880px)',  icon: 'dashicons-smartphone' },
        mobile:       { label: 'Mobile Portrait (≤767px)',   icon: 'dashicons-smartphone' },
    };

    /**
     * Get active devices list.
     */
    function getActiveDevices() {
        if ( window.loopkitEditor && window.loopkitEditor.activeBreakpoints && Array.isArray( window.loopkitEditor.activeBreakpoints ) ) {
            return window.loopkitEditor.activeBreakpoints;
        }
        return ACTIVE_DEVICES;
    }

    /**
     * Check if a device setting exists and has meaningful value for a specific device.
     */
    function getDeviceSetting( settings, key, device ) {
        if ( ! settings ) return undefined;
        var deviceKey = ( device === 'desktop' ) ? key : key + '_' + device;
        var val = settings[ deviceKey ];
        if ( val === undefined || val === null || val === '' ) return undefined;

        // For dimension objects, check if any side is non-empty
        if ( typeof val === 'object' && ! Array.isArray( val ) ) {
            if ( val.url !== undefined ) {
                if ( ! val.url || String( val.url ).trim() === '' ) return undefined;
            } else if ( val.top !== undefined || val.right !== undefined || val.bottom !== undefined || val.left !== undefined ) {
                var hasSide = ( val.top !== '' && val.top !== undefined ) ||
                              ( val.right !== '' && val.right !== undefined ) ||
                              ( val.bottom !== '' && val.bottom !== undefined ) ||
                              ( val.left !== '' && val.left !== undefined );
                if ( ! hasSide ) return undefined;
            } else if ( val.size !== undefined ) {
                if ( val.size === '' || val.size === undefined ) return undefined;
            } else if ( val.row !== undefined || val.column !== undefined ) {
                var hasGap = ( val.row !== '' && val.row !== undefined ) ||
                             ( val.column !== '' && val.column !== undefined );
                if ( ! hasGap ) return undefined;
            }
        }
        return val;
    }

    /**
     * Get inherited setting value by traversing the inheritance chain upward.
     */
    function getInheritedSetting( settings, key, device ) {
        if ( ! settings || device === 'desktop' ) return undefined;
        var chain = INHERITANCE_CHAIN[ device ] || [ 'desktop' ];
        var active = getActiveDevices();

        for ( var i = 0; i < chain.length; i++ ) {
            var anc = chain[i];
            // Skip inactive intermediate breakpoints unless it's desktop
            if ( anc !== 'desktop' && active.indexOf( anc ) === -1 ) continue;
            var val = getDeviceSetting( settings, key, anc );
            if ( val !== undefined ) return val;
        }
        return undefined;
    }

    /**
     * Get effective setting (device value if set, else inherited).
     */
    function getEffectiveSetting( settings, key, device ) {
        var dVal = getDeviceSetting( settings, key, device );
        if ( dVal !== undefined ) return dVal;
        return getInheritedSetting( settings, key, device );
    }

    /* =========================================================
       GLOBAL PRESETS
       ========================================================= */
    var GLOBAL_COLORS = [
        { id: 'primary',   name: 'Primary',   var: 'var(--lk-global-color-primary)',   value: siteSettings.color_primary || '#38A8F5' },
        { id: 'secondary', name: 'Secondary', var: 'var(--lk-global-color-secondary)', value: siteSettings.color_secondary || '#54595F' },
        { id: 'text',      name: 'Text',      var: 'var(--lk-global-color-text)',      value: siteSettings.color_text || '#333333' },
        { id: 'accent',    name: 'Accent',    var: 'var(--lk-global-color-accent)',    value: siteSettings.color_accent || '#38A8F5' },
    ];

    var GLOBAL_FONTS = [
        { id: 'primary',   name: 'Primary',   family: siteSettings.font_primary || 'Roboto' },
        { id: 'secondary', name: 'Secondary', family: siteSettings.font_secondary || 'Roboto Slab' },
        { id: 'text',      name: 'Text',      family: siteSettings.font_text || 'Roboto' },
        { id: 'accent',    name: 'Accent',    family: siteSettings.font_accent || 'Roboto' },
    ];

    /**
     * Options for the font picker: installed fonts first, then system, then Google.
     * Filtered live by the search box above it.
     */
    function fontOptionsHtml( selected, query ) {
        var cfg = window.loopkitEditor || {};
        var list = ( cfg.fontFamilies && cfg.fontFamilies.length ) ? cfg.fontFamilies : FONT_FAMILIES.slice( 1 ).map( function( n ) {
            return { name: n, value: n, group: 'Fonts' };
        } );
        query = ( query || '' ).toLowerCase();

        var html = '<option value=""' + ( selected === '' ? ' selected' : '' ) + '>Default</option>';
        var currentGroup = '';
        var openGroup = false;
        var shown = 0;
        for ( var i = 0; i < list.length; i++ ) {
            var f = list[i];
            var name = f.name || f.value || '';
            if ( query && name.toLowerCase().indexOf( query ) === -1 ) continue;
            if ( f.group !== currentGroup ) {
                if ( openGroup ) html += '</optgroup>';
                html += '<optgroup label="' + escapeHtml( f.group || 'Fonts' ) + '">';
                currentGroup = f.group;
                openGroup = true;
            }
            html += '<option value="' + escapeHtml( f.value || name ) + '"' + ( selected === ( f.value || name ) ? ' selected' : '' ) + '>' + escapeHtml( name ) + '</option>';
            shown++;
        }
        if ( openGroup ) html += '</optgroup>';
        if ( ! shown ) html += '<option value="" disabled>No fonts match that search</option>';
        return html;
    }

    /**
     * Grey out the weights a family does not actually ship. Google returns only the weights
     * that exist, so picking 800 on a font that stops at 700 renders as 700 — which looked
     * like "weight is not working".
     */
    function lkMarkAvailableWeights( $popover, family ) {
        var $weight = $popover.find( '.lk-typo-weight' ).first();
        if ( ! $weight.length ) return;

        $weight.find( 'option' ).prop( 'disabled', false ).each( function() {
            var t = $( this ).text().replace( / \(not in this font\)$/, '' );
            $( this ).text( t );
        } );
        $popover.find( '.lk-typo-weight-note' ).remove();

        if ( ! family ) return;
        var cfg = window.loopkitEditor || {};
        $.ajax({
            url: cfg.restBase + 'font-weights?family=' + encodeURIComponent( family ),
            method: 'GET',
            beforeSend: function( xhr ) { if ( cfg.restNonce ) xhr.setRequestHeader( 'X-WP-Nonce', cfg.restNonce ); }
        }).done( function( res ) {
            var list = ( res && res.weights ) || [];
            if ( ! list.length ) return;
            var missing = 0;
            $weight.find( 'option' ).each( function() {
                var v = parseInt( $( this ).val(), 10 );
                if ( ! v ) return;                       // "Default"
                if ( list.indexOf( v ) === -1 ) {
                    $( this ).prop( 'disabled', true ).text( $( this ).text() + ' (not in this font)' );
                    missing++;
                }
            } );
            if ( missing ) {
                $weight.closest( '.lk-typo-row' ).after( '<div class="lk-typo-weight-note">' + escapeHtml( family ) + ' ships ' + list.join( ', ' ) + ' only.</div>' );
            }
        } );
    }

    // Family changed: refresh which weights are available
    $( document ).on( 'change', '.lk-font-select', lkRefreshWeightNote );

    // Popover opened (or the panel re-rendered): check the family that is selected now
    function lkRefreshWeightNote() {
        setTimeout( function() {
            $( '.lk-typo-popover' ).each( function() {
                var $pop = $( this );
                var family = $pop.find( '.lk-font-select' ).val();
                if ( family ) lkMarkAvailableWeights( $pop, family );
            } );
        }, 250 );
    }

    $( document ).on( 'click', '.lk-typo-edit-btn', lkRefreshWeightNote );

    // Live filtering of the font list
    $( document ).on( 'input', '.lk-font-search', function() {
        var $wrap = $( this ).closest( '.lk-font-picker' );
        var $select = $wrap.find( '.lk-font-select' );
        $select.html( fontOptionsHtml( $select.val() || '', $( this ).val() ) );
    } );

    // Keep the search box showing the chosen family
    $( document ).on( 'change', '.lk-font-select', function() {
        $( this ).closest( '.lk-font-picker' ).find( '.lk-font-search' ).val( $( this ).val() );
    } );

    var FONT_FAMILIES = [
        'Default', 'Arial', 'Helvetica', 'Times New Roman', 'Georgia', 'Verdana',
        'Roboto', 'Roboto Slab', 'Open Sans', 'Lato', 'Montserrat', 'Poppins', 'Inter', 'Manrope',
        'Playfair Display', 'Oswald', 'Raleway', 'Merriweather', 'Nunito', 'Fira Code'
    ];

    /* =========================================================
       SHAPE DIVIDER SVGS (18 Shapes)
       ========================================================= */
    var LOOPKIT_SHAPES = {
        mountains: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 L200,80 L350,20 L500,90 L680,30 L850,75 L1000,0 L1000,100 L0,100 Z"></path></svg>',
        drops: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,100 C150,100 200,20 250,20 C300,20 350,100 500,100 C650,100 700,20 750,20 C800,20 850,100 1000,100 L1000,0 L0,0 Z"></path></svg>',
        clouds: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,100 C50,60 100,50 150,70 C200,40 280,30 330,60 C380,20 480,10 530,50 C580,20 680,30 730,65 C780,40 860,50 900,70 C940,55 980,70 1000,100 L1000,0 L0,0 Z"></path></svg>',
        zigzag: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 L50,100 L100,0 L150,100 L200,0 L250,100 L300,0 L350,100 L400,0 L450,100 L500,0 L550,100 L600,0 L650,100 L700,0 L750,100 L800,0 L850,100 L900,0 L950,100 L1000,0 L1000,100 L0,100 Z"></path></svg>',
        pyramids: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,100 L125,20 L250,100 L375,10 L500,100 L625,25 L750,100 L875,15 L1000,100 L1000,0 L0,0 Z"></path></svg>',
        triangle: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 L500,100 L1000,0 L1000,100 L0,100 Z"></path></svg>',
        triangle_asymmetrical: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 L750,100 L1000,0 L1000,100 L0,100 Z"></path></svg>',
        tilt: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,100 L1000,0 L1000,100 Z"></path></svg>',
        tilt_opacity: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,100 L1000,0 L1000,100 Z" opacity="0.4"></path><path d="M0,100 L1000,30 L1000,100 Z" opacity="0.7"></path><path d="M0,100 L1000,60 L1000,100 Z"></path></svg>',
        fan_opacity: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 Q500,100 1000,0 L1000,100 L0,100 Z" opacity="0.3"></path><path d="M0,20 Q500,110 1000,20 L1000,100 L0,100 Z" opacity="0.6"></path><path d="M0,40 Q500,120 1000,40 L1000,100 L0,100 Z"></path></svg>',
        curve: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 C300,120 700,120 1000,0 L1000,100 L0,100 Z"></path></svg>',
        curve_asymmetrical: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 C400,130 800,40 1000,0 L1000,100 L0,100 Z"></path></svg>',
        waves: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,50 C200,0 300,100 500,50 C700,0 800,100 1000,50 L1000,100 L0,100 Z"></path></svg>',
        waves_brush: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,40 C120,60 220,20 340,45 C460,70 560,30 680,50 C800,70 900,35 1000,45 L1000,100 L0,100 Z"></path></svg>',
        waves_pattern: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,60 Q125,20 250,60 T500,60 T750,60 T1000,60 L1000,100 L0,100 Z"></path></svg>',
        arrow: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 L500,80 L1000,0 L1000,100 L0,100 Z"></path></svg>',
        split: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,50 L500,0 L1000,50 L1000,100 L0,100 Z"></path></svg>',
        book: '<svg viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,100 C250,0 450,80 500,100 C550,80 750,0 1000,100 L1000,0 L0,0 Z"></path></svg>'
    };

    LoopkitControls.getShapeSvg = function( shape ) {
        return LOOPKIT_SHAPES[ shape ] || '';
    };

    /**
     * Evaluate control and section conditions
     */
    LoopkitControls.evaluateCondition = function( condition, settings, device ) {
        if ( ! condition || typeof condition !== 'object' ) return true;

        for ( var rawKey in condition ) {
            if ( ! condition.hasOwnProperty( rawKey ) ) continue;
            var expected = condition[ rawKey ];

            // Image conditions
            if ( rawKey === '_has_image' || rawKey === '_has_image_hover' || rawKey === '_has_overlay_image' || rawKey === '_has_overlay_image_hover' ) {
                var imgKey = ( rawKey === '_has_image' ) ? 'background_image' :
                             ( rawKey === '_has_image_hover' ) ? 'background_image_hover' :
                             ( rawKey === '_has_overlay_image' ) ? 'background_overlay_image' : 'background_overlay_image_hover';
                var bgImg = getEffectiveSetting( settings, imgKey, device );
                var hasImg = false;
                if ( bgImg ) {
                    if ( typeof bgImg === 'object' && bgImg.url && String( bgImg.url ).trim() !== '' ) {
                        hasImg = true;
                    } else if ( typeof bgImg === 'string' && String( bgImg ).trim() !== '' ) {
                        hasImg = true;
                    }
                }
                if ( hasImg !== expected ) return false;
                continue;
            }

            // Border conditions
            if ( rawKey === '_has_border' || rawKey === '_has_border_hover' ) {
                var bTypeKey = ( rawKey === '_has_border' ) ? 'border_type' : 'border_type_hover';
                var bTypeVal = getEffectiveSetting( settings, bTypeKey, device );
                var hasBorder = !!( bTypeVal && bTypeVal !== 'none' && bTypeVal !== 'default' );
                if ( hasBorder !== expected ) return false;
                continue;
            }

            // Shape divider conditions
            if ( rawKey === '_has_shape_top' || rawKey === '_has_shape_bottom' ) {
                var sTypeKey = ( rawKey === '_has_shape_top' ) ? 'shape_divider_top_type' : 'shape_divider_bottom_type';
                var sTypeVal = getEffectiveSetting( settings, sTypeKey, device );
                var hasShape = !!( sTypeVal && sTypeVal !== 'none' );
                if ( hasShape !== expected ) return false;
                continue;
            }

            // Motion effects conditions
            if ( rawKey === '_has_any_scroll_effect' ) {
                var hasAnyScroll = !!(
                    ( settings.scroll_vertical === 'yes' || settings.scroll_vertical === true ) ||
                    ( settings.scroll_horizontal === 'yes' || settings.scroll_horizontal === true ) ||
                    ( settings.scroll_transparency === 'yes' || settings.scroll_transparency === true ) ||
                    ( settings.scroll_blur === 'yes' || settings.scroll_blur === true ) ||
                    ( settings.scroll_rotate === 'yes' || settings.scroll_rotate === true ) ||
                    ( settings.scroll_scale === 'yes' || settings.scroll_scale === true )
                );
                if ( hasAnyScroll !== expected ) return false;
                continue;
            }

            if ( rawKey === '_has_any_mouse_effect' ) {
                var hasAnyMouse = !!(
                    ( settings.mouse_track === 'yes' || settings.mouse_track === true ) ||
                    ( settings.mouse_tilt === 'yes' || settings.mouse_tilt === true )
                );
                if ( hasAnyMouse !== expected ) return false;
                continue;
            }

            if ( rawKey === '_has_rotate_3d' || rawKey === '_has_rotate_3d_hover' ) {
                var isH = ( rawKey === '_has_rotate_3d_hover' );
                var kX = isH ? 'transform_rotate_x_hover' : 'transform_rotate_x';
                var kY = isH ? 'transform_rotate_y_hover' : 'transform_rotate_y';
                var rx = getEffectiveSetting( settings, kX, device );
                var ry = getEffectiveSetting( settings, kY, device );
                var rxVal = ( rx && typeof rx === 'object' && rx.size !== undefined ) ? parseFloat( rx.size ) : parseFloat( rx );
                var ryVal = ( ry && typeof ry === 'object' && ry.size !== undefined ) ? parseFloat( ry.size ) : parseFloat( ry );
                var has3D = ( ! isNaN( rxVal ) && rxVal !== 0 ) || ( ! isNaN( ryVal ) && ryVal !== 0 );
                if ( has3D !== expected ) return false;
                continue;
            }

            // Determine if negative condition (ends with !)
            var isNegative = false;
            var cleanKey = rawKey;
            if ( cleanKey.charAt( cleanKey.length - 1 ) === '!' ) {
                isNegative = true;
                cleanKey = cleanKey.slice( 0, -1 );
            }

            // Check for nested path: e.g. "selected_icon[value]" or "image[url]"
            var subKey = null;
            var bracketMatch = cleanKey.match( /^([^\[]+)\[([^\]]+)\]$/ );
            if ( bracketMatch ) {
                cleanKey = bracketMatch[1];
                subKey = bracketMatch[2];
            }

            var current = getEffectiveSetting( settings, cleanKey, device );
            if ( subKey && current && typeof current === 'object' ) {
                current = current[ subKey ];
            }

            if ( current === undefined && cleanKey === 'flex_wrap' ) current = 'nowrap';
            if ( current === undefined && cleanKey === 'container_type' ) current = 'flexbox';
            if ( ( current === undefined || current === '' ) && cleanKey === 'sticky' ) current = 'none';
            if ( ( current === undefined || current === '' ) &&
                 ( cleanKey === 'background_type' || cleanKey === 'background_type_hover' ||
                   cleanKey === 'background_overlay_type' || cleanKey === 'background_overlay_type_hover' ) ) {
                current = 'classic';
            }

            if ( current === undefined || current === null ) {
                current = '';
            }

            var matches = false;
            if ( Array.isArray( expected ) ) {
                for ( var a = 0; a < expected.length; a++ ) {
                    if ( String( current ) === String( expected[a] ) ) {
                        matches = true;
                        break;
                    }
                }
            } else {
                matches = ( String( current ) === String( expected ) );
            }

            if ( isNegative ) {
                if ( matches ) return false;
            } else {
                if ( ! matches ) return false;
            }
        }

        return true;
    };

    /* =========================================================
       CONTROL RENDERERS
       ========================================================= */

    /**
     * Render control header (Label + Responsive device switcher dropdown)
     */
    function renderControlHeader( def, device ) {
        var html = '<div class="lk-control-header">';
        html += '<label class="lk-control-label" for="lk-ctrl-' + escapeHtml( def.name ) + '">' + escapeHtml( def.label || '' ) + '</label>';

        if ( def.responsive ) {
            var currentInfo = DEVICE_INFO[ device ] || DEVICE_INFO.desktop;
            html += '<div class="lk-responsive-indicator" data-control-key="' + escapeHtml( def.name ) + '">';
            html += '  <button type="button" class="lk-res-btn" title="Switch device" data-device="' + escapeHtml( device ) + '">';
            html += '    <span class="dashicons ' + escapeHtml( currentInfo.icon ) + '"></span>';
            html += '  </button>';

            // Dropdown list of active devices
            var active = getActiveDevices();
            html += '  <div class="lk-res-dropdown" style="display:none;">';
            for ( var i = 0; i < active.length; i++ ) {
                var dName = active[i];
                var dInfo = DEVICE_INFO[ dName ] || { label: dName, icon: 'dashicons-desktop' };
                var isActive = ( dName === device ) ? ' lk-res-item-active' : '';
                html += '    <div class="lk-res-dropdown-item' + isActive + '" data-device="' + escapeHtml( dName ) + '">';
                html += '      <span class="dashicons ' + escapeHtml( dInfo.icon ) + '"></span>';
                html += '      <span>' + escapeHtml( dInfo.label ) + '</span>';
                html += '    </div>';
            }
            html += '  </div>';
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    /**
     * Render Description
     */
    function renderDescription( def ) {
        if ( ! def.description ) return '';
        return '<div class="lk-control-desc">' + escapeHtml( def.description ) + '</div>';
    }

    /**
     * TEXT Control
     */
    function renderText( def, deviceVal, inheritedVal, device ) {
        var val = ( deviceVal !== undefined && deviceVal !== null ) ? String( deviceVal ) : '';
        var placeholder = ( inheritedVal !== undefined && inheritedVal !== null && inheritedVal !== '' )
            ? String( inheritedVal )
            : ( def.placeholder || ( device === 'desktop' ? ( def.default || '' ) : '' ) );

        var html = '<div class="lk-control-field lk-has-dynamic">';
        html += '<input type="text" class="lk-input lk-input-text" id="lk-ctrl-' + escapeHtml( def.name ) + '" data-name="' + escapeHtml( def.name ) + '" value="' + escapeHtml( val ) + '" placeholder="' + escapeHtml( placeholder ) + '">';
        html += dynamicButtonHtml( def.name );
        html += '</div>';
        return html;
    }

    /**
     * Small "dynamic" button that inserts a {{lk:...}} tag into the field next to it.
     */
    function dynamicButtonHtml( name ) {
        var cfg = window.loopkitEditor || {};
        if ( ! cfg.dynamicTags || ! cfg.dynamicTags.length ) return '';
        return '<button type="button" class="lk-dynamic-btn" data-for="' + escapeHtml( name || '' ) + '" title="Insert dynamic tag (post, site, custom field)"><span class="dashicons dashicons-database"></span></button>'
            + '<button type="button" class="lk-dynamic-clear" data-for="' + escapeHtml( name || '' ) + '" title="Disconnect this field from its dynamic tag" hidden>&times;</button>';
    }

    /**
     * Build and open the dynamic tag menu for one field.
     */
    function openDynamicMenu( $btn ) {
        $( '.lk-dynamic-menu' ).remove();
        var cfg = window.loopkitEditor || {};
        var groups = cfg.dynamicTags || [];
        var html = '<div class="lk-dynamic-menu">';
        html += '<div class="lk-dynamic-menu-head">Dynamic tag</div>';
        groups.forEach( function( g ) {
            html += '<div class="lk-dynamic-group">' + escapeHtml( g.group ) + '</div>';

            // Tags can name a sub-group (an ACF field group, say); those get their own
            // smaller heading with their fields underneath.
            var lastSub = null;
            ( g.tags || [] ).forEach( function( t ) {
                var sub = t.subgroup || '';
                if ( sub !== lastSub ) {
                    if ( sub ) html += '<div class="lk-dynamic-subgroup">' + escapeHtml( sub ) + '</div>';
                    lastSub = sub;
                }
                html += '<button type="button" class="lk-dynamic-item" data-name="' + escapeHtml( t.name ) + '" data-arg="' + escapeHtml( t.arg || '' ) + '">' + escapeHtml( t.label ) + '</button>';
            } );
        } );
        html += '<div class="lk-dynamic-foot">Tip: add a fallback after a pipe, e.g. {{lk:post_title|Untitled}}</div>';
        html += '</div>';

        var $menu = $( html ).appendTo( document.body );
        var off = $btn.offset();
        var top = off.top + $btn.outerHeight() + 4;
        var left = Math.max( 8, Math.min( off.left - 190, $( window ).width() - 270 ) );
        $menu.css( { top: top + 'px', left: left + 'px' } );

        $menu.on( 'click', '.lk-dynamic-item', function() {
            var name = $( this ).data( 'name' );
            var arg = $( this ).data( 'arg' );
            var token = arg ? '{{lk:' + name + ':}}' : '{{lk:' + name + '}}';
            var $field = $btn.closest( '.lk-control-field' ).find( 'input, textarea' ).first();
            $field.val( token ).trigger( 'input' ).trigger( 'change' ).focus();
            syncDynamicClear( $field );
            if ( arg ) {
                // put the caret where the key/taxonomy goes
                var pos = token.indexOf( ':}}' ) + 1;
                var el = $field.get( 0 );
                if ( el && el.setSelectionRange ) el.setSelectionRange( pos, pos );
            }
            $menu.remove();
        } );

        setTimeout( function() {
            $( document ).one( 'click.lkdyn', function( e ) {
                if ( ! $( e.target ).closest( '.lk-dynamic-menu, .lk-dynamic-btn' ).length ) $menu.remove();
            } );
        }, 0 );
    }

    $( document ).on( 'click', '.lk-dynamic-btn', function( e ) {
        e.preventDefault();
        e.stopPropagation();
        openDynamicMenu( $( this ) );
    } );

    // Disconnect: empty the field so it can be typed in again
    $( document ).on( 'click', '.lk-dynamic-clear', function( e ) {
        e.preventDefault();
        e.stopPropagation();
        var $field = $( this ).closest( '.lk-control-field' ).find( 'input, textarea' ).first();
        $field.val( '' ).trigger( 'input' ).trigger( 'change' ).focus();
        syncDynamicClear( $field );
    } );

    /**
     * Show the X only while the field actually holds a dynamic tag.
     *
     * @param {jQuery} $field The field.
     */
    function syncDynamicClear( $field ) {
        var $wrap = $field.closest( '.lk-control-field' );
        var $clear = $wrap.find( '.lk-dynamic-clear' );
        if ( ! $clear.length ) return;
        var connected = /\{\{lk:/.test( String( $field.val() || '' ) );
        $clear.prop( 'hidden', ! connected );
        $wrap.toggleClass( 'lk-is-dynamic', connected );
    }

    $( document ).on( 'input change', '.lk-control-field input, .lk-control-field textarea', function() {
        syncDynamicClear( $( this ) );
    } );

    // and when the panel is (re)drawn
    $( document ).on( 'lk-panel-rendered', function() {
        $( '.lk-control-field' ).each( function() {
            syncDynamicClear( $( this ).find( 'input, textarea' ).first() );
        } );
    } );

    /**
     * TEXTAREA Control
     */
    function renderTextarea( def, deviceVal, inheritedVal, device ) {
        var val = ( deviceVal !== undefined && deviceVal !== null ) ? String( deviceVal ) : '';
        var placeholder = ( inheritedVal !== undefined && inheritedVal !== null && inheritedVal !== '' )
            ? String( inheritedVal )
            : ( def.placeholder || ( device === 'desktop' ? ( def.default || '' ) : '' ) );
        var rows = def.rows || 3;

        var html = '<div class="lk-control-field lk-has-dynamic">';
        html += '<textarea class="lk-input lk-input-textarea" id="lk-ctrl-' + escapeHtml( def.name ) + '" rows="' + rows + '" data-name="' + escapeHtml( def.name ) + '" placeholder="' + escapeHtml( placeholder ) + '">' + escapeHtml( val ) + '</textarea>';
        html += dynamicButtonHtml( def.name );
        html += '</div>';
        return html;
    }

    /**
     * NUMBER Control
     */
    function renderNumber( def, deviceVal, inheritedVal, device ) {
        var val = ( deviceVal !== undefined && deviceVal !== null && deviceVal !== '' ) ? deviceVal : '';
        var placeholder = ( inheritedVal !== undefined && inheritedVal !== null && inheritedVal !== '' )
            ? inheritedVal
            : ( device === 'desktop' && def.default !== undefined ? def.default : '' );

        var min = def.min !== undefined ? ' min="' + def.min + '"' : '';
        var max = def.max !== undefined ? ' max="' + def.max + '"' : '';
        var step = def.step !== undefined ? ' step="' + def.step + '"' : '';

        var html = '<div class="lk-control-field">';
        html += '<input type="number" class="lk-input lk-input-number" id="lk-ctrl-' + escapeHtml( def.name ) + '" data-name="' + escapeHtml( def.name ) + '" value="' + escapeHtml( val ) + '" placeholder="' + escapeHtml( placeholder ) + '"' + min + max + step + '>';
        html += '</div>';
        return html;
    }

    /**
     * SELECT Control
     */
    function renderSelect( def, deviceVal, inheritedVal, device ) {
        var val = ( deviceVal !== undefined && deviceVal !== null && deviceVal !== '' ) ? String( deviceVal ) : '';
        var effective = val || ( inheritedVal !== undefined && inheritedVal !== '' ? String( inheritedVal ) : ( def.default || '' ) );

        var html = '<div class="lk-control-field">';
        html += '<select class="lk-input lk-input-select" id="lk-ctrl-' + escapeHtml( def.name ) + '" data-name="' + escapeHtml( def.name ) + '">';
        if ( def.groups && Array.isArray( def.groups ) ) {
            for ( var g = 0; g < def.groups.length; g++ ) {
                var group = def.groups[g];
                if ( group.options ) {
                    html += '<optgroup label="' + escapeHtml( group.label ) + '">';
                    for ( var gVal in group.options ) {
                        var gSel = String( gVal ) === String( effective ) ? ' selected' : '';
                        html += '<option value="' + escapeHtml( gVal ) + '"' + gSel + '>' + escapeHtml( group.options[ gVal ] ) + '</option>';
                    }
                    html += '</optgroup>';
                } else if ( group.value !== undefined ) {
                    var s = String( group.value ) === String( effective ) ? ' selected' : '';
                    html += '<option value="' + escapeHtml( group.value ) + '"' + s + '>' + escapeHtml( group.label ) + '</option>';
                }
            }
        } else if ( def.options ) {
            for ( var optVal in def.options ) {
                if ( typeof def.options[ optVal ] === 'object' && def.options[ optVal ] !== null ) {
                    html += '<optgroup label="' + escapeHtml( optVal ) + '">';
                    for ( var subVal in def.options[ optVal ] ) {
                        var subSel = String( subVal ) === String( effective ) ? ' selected' : '';
                        html += '<option value="' + escapeHtml( subVal ) + '"' + subSel + '>' + escapeHtml( def.options[ optVal ][ subVal ] ) + '</option>';
                    }
                    html += '</optgroup>';
                } else {
                    var selected = String( optVal ) === String( effective ) ? ' selected' : '';
                    html += '<option value="' + escapeHtml( optVal ) + '"' + selected + '>' + escapeHtml( def.options[ optVal ] ) + '</option>';
                }
            }
        }
        html += '</select>';
        html += '</div>';
        return html;
    }

    /**
     * CHECKBOX LIST / MULTI-CHECKBOX Control
     */
    function renderCheckboxList( def, deviceVal, inheritedVal, device ) {
        var rawVal = ( deviceVal !== undefined && deviceVal !== null && deviceVal !== '' )
            ? deviceVal
            : ( inheritedVal !== undefined && inheritedVal !== null && inheritedVal !== '' ? inheritedVal : def.default );

        var selectedArr = [];
        if ( Array.isArray( rawVal ) ) {
            selectedArr = rawVal.map( function( v ) { return String( v ); } );
        } else if ( typeof rawVal === 'string' && rawVal !== '' ) {
            selectedArr = rawVal.split( ',' ).map( function( s ) { return s.trim(); } ).filter( Boolean );
        }

        var html = '<div class="lk-control-field lk-field-checkbox-list" data-name="' + escapeHtml( def.name ) + '">';
        html += '<div class="lk-checkbox-list-items">';

        if ( def.options ) {
            for ( var optVal in def.options ) {
                var isChecked = selectedArr.indexOf( String( optVal ) ) !== -1;
                var optLabel = def.options[ optVal ];
                html += '<label class="lk-checkbox-item" style="display:inline-flex;align-items:center;margin-right:12px;margin-bottom:6px;cursor:pointer;font-size:12px;user-select:none;">';
                html += '  <input type="checkbox" class="lk-input-checkbox-list" data-name="' + escapeHtml( def.name ) + '" value="' + escapeHtml( optVal ) + '"' + ( isChecked ? ' checked' : '' ) + ' style="margin-right:5px;cursor:pointer;">';
                html += '  <span>' + escapeHtml( optLabel ) + '</span>';
                html += '</label>';
            }
        }

        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * SWITCHER (Toggle) Control
     */
    function renderSwitcher( def, deviceVal, inheritedVal, device ) {
        var effective = ( deviceVal !== undefined ) ? deviceVal : ( inheritedVal !== undefined ? inheritedVal : def.default );
        var isChecked = effective === 'yes' || effective === true || effective === '1';

        var html = '<div class="lk-control-field lk-field-switcher">';
        html += '<label class="lk-switcher-label">';
        html += '  <input type="checkbox" class="lk-input-switcher" data-name="' + escapeHtml( def.name ) + '"' + ( isChecked ? ' checked' : '' ) + '>';
        html += '  <span class="lk-switcher-slider"></span>';
        html += '</label>';
        html += '</div>';
        return html;
    }

    /**
     * CHOOSE (Button Group) Control
     */
    function renderChoose( def, deviceVal, inheritedVal, device, settings, element ) {
        var dir = ( settings && settings.flex_direction ) ? settings.flex_direction : 'column';
        var hasExplicit = ( deviceVal !== undefined && deviceVal !== '' && deviceVal !== null );
        var activeVal = hasExplicit ? String( deviceVal ) : '';
        var inheritVal = ( ! hasExplicit && inheritedVal !== undefined && inheritedVal !== '' ) ? String( inheritedVal ) : '';
        if ( ! hasExplicit && ! inheritVal && device === 'desktop' && def.default !== undefined ) {
            activeVal = String( def.default );
        }

        var html = '<div class="lk-control-field">';
        html += '<div class="lk-choose-group" data-name="' + escapeHtml( def.name ) + '">';

        if ( def.options ) {
            for ( var key in def.options ) {
                var opt = def.options[ key ];
                var keyStr = String( key );
                var isActive = ( keyStr === activeVal ) ? ' lk-choose-active' : '';
                var isInherited = ( ! isActive && keyStr === inheritVal ) ? ' lk-choose-inherited' : '';
                var title = typeof opt === 'string' ? opt : ( opt.title || key );
                var icon = typeof opt === 'object' && opt.icon ? opt.icon : '';

                var rotateClass = '';
                if ( ( def.name === 'justify_content' || def.name === 'align_items' ) && ( dir === 'column' || dir === 'column-reverse' ) ) {
                    rotateClass = ' lk-icon-rotated';
                }

                html += '<button type="button" class="lk-choose-btn' + isActive + isInherited + '" data-value="' + escapeHtml( key ) + '" title="' + escapeHtml( title ) + '">';
                if ( icon ) {
                    html += '<span class="dashicons ' + escapeHtml( icon ) + rotateClass + '"></span>';
                } else {
                    html += '<span>' + escapeHtml( title ) + '</span>';
                }
                html += '</button>';
            }
        }

        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * SLIDER Control
     */
    function renderSlider( def, deviceVal, inheritedVal, device ) {
        var isUnitless = !! def.unitless || ( Array.isArray( def.units ) && def.units.length === 0 );
        var units = isUnitless ? [] : ( def.units || [ 'px', '%', 'em', 'rem', 'vw', 'vh', 'custom' ] );

        var hasExplicit = false;
        var size = '';
        var unit = isUnitless ? '' : ( def.default_unit || ( units.length ? units[0] : '' ) );

        if ( deviceVal !== undefined && deviceVal !== null && deviceVal !== '' ) {
            hasExplicit = true;
            if ( typeof deviceVal === 'object' ) {
                size = ( deviceVal.size !== undefined ) ? deviceVal.size : '';
                unit = isUnitless ? '' : ( deviceVal.unit || unit );
            } else {
                size = deviceVal;
            }
        } else if ( inheritedVal !== undefined && inheritedVal !== null && inheritedVal !== '' ) {
            if ( typeof inheritedVal === 'object' ) {
                unit = isUnitless ? '' : ( inheritedVal.unit || unit );
            }
        } else if ( device === 'desktop' && def.default !== undefined ) {
            size = def.default;
            unit = isUnitless ? '' : ( def.default_unit || unit );
        }

        // Migrate if size still contains unit in string (e.g. '0.1px' -> 0.1)
        if ( isUnitless && typeof size === 'string' ) {
            var parsed = parseFloat( size );
            if ( ! isNaN( parsed ) ) {
                size = parsed;
            }
        }

        var placeholder = '';
        if ( ! hasExplicit && inheritedVal !== undefined && inheritedVal !== null && inheritedVal !== '' ) {
            placeholder = ( typeof inheritedVal === 'object' ) ? ( inheritedVal.size !== undefined ? inheritedVal.size : '' ) : inheritedVal;
            if ( isUnitless && typeof placeholder === 'string' ) {
                var parsedPh = parseFloat( placeholder );
                if ( ! isNaN( parsedPh ) ) {
                    placeholder = parsedPh;
                }
            }
        }

        var min = def.min !== undefined ? def.min : 0;
        var max = def.max !== undefined ? def.max : ( unit === '%' || unit === 'vw' || unit === 'vh' ? 100 : 1600 );
        var step = def.step !== undefined ? def.step : 1;

        var html = '<div class="lk-control-field lk-field-slider' + ( isUnitless ? ' lk-slider-unitless' : '' ) + '" data-name="' + escapeHtml( def.name ) + '"' +
                   ( isUnitless ? ' data-unitless="true"' : ( ' data-unit="' + escapeHtml( unit ) + '"' ) ) + '>';

        // Unit selector (only when not unitless and more than 1 unit available)
        if ( ! isUnitless && units.length > 1 ) {
            html += '<div class="lk-slider-units">';
            for ( var u = 0; u < units.length; u++ ) {
                var activeUnit = ( units[u] === unit ) ? ' lk-unit-active' : '';
                html += '<button type="button" class="lk-unit-btn' + activeUnit + '" data-unit="' + escapeHtml( units[u] ) + '">' + escapeHtml( units[u] ) + '</button>';
            }
            html += '</div>';
        }

        var sliderRangeVal = ( size !== '' ) ? size : ( placeholder !== '' ? placeholder : min );

        html += '<div class="lk-slider-inputs">';
        html += '  <input type="range" class="lk-slider-range" min="' + min + '" max="' + max + '" step="' + step + '" value="' + sliderRangeVal + '">';
        html += '  <input type="number" class="lk-input lk-slider-number" value="' + escapeHtml( size ) + '" placeholder="' + escapeHtml( placeholder ) + '" min="' + min + '" max="' + max + '" step="' + step + '">';
        html += '</div>';

        html += '</div>';
        return html;
    }

    /**
     * DIMENSIONS Control (Top, Right, Bottom, Left)
     */
    function renderDimensions( def, deviceVal, inheritedVal, device ) {
        var units = def.units || [ 'px', '%', 'em', 'rem' ];
        var sides = [ { key: 'top', label: 'Top' }, { key: 'right', label: 'Right' }, { key: 'bottom', label: 'Bottom' }, { key: 'left', label: 'Left' } ];

        var hasExplicit = ( typeof deviceVal === 'object' && deviceVal !== null );
        var current = hasExplicit ? deviceVal : {};
        var inherited = ( typeof inheritedVal === 'object' && inheritedVal !== null ) ? inheritedVal : {};

        var unit = ( hasExplicit && current.unit ) ? current.unit : ( inherited.unit || def.default_unit || units[0] );
        var isLinked = ( hasExplicit && current.isLinked !== undefined ) ? current.isLinked : ( inherited.isLinked !== undefined ? inherited.isLinked : true );

        var html = '<div class="lk-control-field lk-field-dimensions" data-name="' + escapeHtml( def.name ) + '">';

        // Unit switcher bar
        html += '<div class="lk-dim-top">';
        if ( units.length > 1 ) {
            html += '<div class="lk-dim-units">';
            for ( var u = 0; u < units.length; u++ ) {
                var activeUnit = ( units[u] === unit ) ? ' lk-unit-active' : '';
                html += '<button type="button" class="lk-unit-btn' + activeUnit + '" data-unit="' + escapeHtml( units[u] ) + '">' + escapeHtml( units[u] ) + '</button>';
            }
            html += '</div>';
        }
        html += '</div>';

        // 4 boxes + Link button
        html += '<div class="lk-dim-inputs">';
        for ( var s = 0; s < sides.length; s++ ) {
            var sideKey = sides[s].key;
            var val = ( hasExplicit && current[ sideKey ] !== undefined && current[ sideKey ] !== null ) ? current[ sideKey ] : '';
            var placeholder = ( inherited[ sideKey ] !== undefined && inherited[ sideKey ] !== null && inherited[ sideKey ] !== '' ) ? inherited[ sideKey ] : '';

            html += '<div class="lk-dim-box">';
            html += '  <input type="number" class="lk-input lk-dim-input" data-side="' + sideKey + '" value="' + escapeHtml( val ) + '" placeholder="' + escapeHtml( placeholder ) + '">';
            html += '  <span class="lk-dim-label">' + sides[s].label + '</span>';
            html += '</div>';
        }

        html += '<button type="button" class="lk-dim-link-btn' + ( isLinked ? ' lk-linked' : '' ) + '" title="' + ( isLinked ? 'Unlink values' : 'Link values' ) + '">';
        html += '  <span class="dashicons ' + ( isLinked ? 'dashicons-admin-links' : 'dashicons-editor-unlink' ) + '"></span>';
        html += '</button>';

        html += '</div>'; // .lk-dim-inputs
        html += '</div>';
        return html;
    }

    /**
     * GAPS Control (Column and Row)
     */
    function renderGaps( def, deviceVal, inheritedVal, device ) {
        var units = def.units || [ 'px', 'em', 'rem', '%', 'custom' ];
        var hasExplicit = ( typeof deviceVal === 'object' && deviceVal !== null );
        var current = hasExplicit ? deviceVal : {};
        var inherited = ( typeof inheritedVal === 'object' && inheritedVal !== null ) ? inheritedVal : {};

        var unit = ( hasExplicit && current.unit ) ? current.unit : ( inherited.unit || def.default_unit || 'px' );
        var isLinked = ( hasExplicit && current.isLinked !== undefined ) ? current.isLinked : ( inherited.isLinked !== undefined ? inherited.isLinked : true );

        var rowVal = ( hasExplicit && current.row !== undefined && current.row !== null ) ? current.row : '';
        var colVal = ( hasExplicit && current.column !== undefined && current.column !== null ) ? current.column : '';

        var rowPlaceholder = ( inherited.row !== undefined && inherited.row !== '' ) ? inherited.row : ( def.default && def.default.row ? def.default.row : '20' );
        var colPlaceholder = ( inherited.column !== undefined && inherited.column !== '' ) ? inherited.column : ( def.default && def.default.column ? def.default.column : '20' );

        var html = '<div class="lk-control-field lk-field-gaps" data-name="' + escapeHtml( def.name ) + '">';

        html += '<div class="lk-dim-top">';
        if ( units.length > 1 ) {
            html += '<div class="lk-dim-units">';
            for ( var u = 0; u < units.length; u++ ) {
                var activeUnit = ( units[u] === unit ) ? ' lk-unit-active' : '';
                html += '<button type="button" class="lk-unit-btn' + activeUnit + '" data-unit="' + escapeHtml( units[u] ) + '">' + escapeHtml( units[u] ) + '</button>';
            }
            html += '</div>';
        }
        html += '</div>';

        html += '<div class="lk-gaps-inputs">';
        html += '  <div class="lk-dim-box">';
        html += '    <input type="number" class="lk-input lk-gap-input" data-gap="row" value="' + escapeHtml( rowVal ) + '" placeholder="' + escapeHtml( rowPlaceholder ) + '">';
        html += '    <span class="lk-dim-label">Row</span>';
        html += '  </div>';
        html += '  <div class="lk-dim-box">';
        html += '    <input type="number" class="lk-input lk-gap-input" data-gap="column" value="' + escapeHtml( colVal ) + '" placeholder="' + escapeHtml( colPlaceholder ) + '">';
        html += '    <span class="lk-dim-label">Column</span>';
        html += '  </div>';

        html += '  <button type="button" class="lk-dim-link-btn' + ( isLinked ? ' lk-linked' : '' ) + '" title="' + ( isLinked ? 'Unlink values' : 'Link values' ) + '">';
        html += '    <span class="dashicons ' + ( isLinked ? 'dashicons-admin-links' : 'dashicons-editor-unlink' ) + '"></span>';
        html += '  </button>';
        html += '</div>';

        html += '</div>';
        return html;
    }

    /**
     * URL Control (Input + Gear popover for target/nofollow/custom attributes)
     */
    function renderUrl( def, deviceVal ) {
        var current = typeof deviceVal === 'object' && deviceVal !== null ? deviceVal : { url: deviceVal || '', is_external: false, nofollow: false, custom_attributes: '' };

        var html = '<div class="lk-control-field lk-field-url" data-name="' + escapeHtml( def.name ) + '">';
        html += '<div class="lk-url-input-wrap">';
        html += '  <input type="text" class="lk-input lk-url-input" id="lk-ctrl-' + escapeHtml( def.name ) + '" value="' + escapeHtml( current.url || '' ) + '" placeholder="Paste URL or type">';
        html += '  <button type="button" class="lk-url-gear-btn" title="Link Options">';
        html += '    <span class="dashicons dashicons-admin-generic"></span>';
        html += '  </button>';
        html += '</div>';

        // Link options popover
        html += '<div class="lk-url-popover" style="display:none;">';
        html += '  <label class="lk-checkbox-label">';
        html += '    <input type="checkbox" class="lk-url-external"' + ( current.is_external ? ' checked' : '' ) + '> ' + 'Open in new window';
        html += '  </label>';
        html += '  <label class="lk-checkbox-label">';
        html += '    <input type="checkbox" class="lk-url-nofollow"' + ( current.nofollow ? ' checked' : '' ) + '> ' + 'Add nofollow';
        html += '  </label>';
        html += '  <div class="lk-url-custom-attrs">';
        html += '    <label class="lk-sub-label">Custom Attributes</label>';
        html += '    <input type="text" class="lk-input lk-url-attrs" value="' + escapeHtml( current.custom_attributes || '' ) + '" placeholder="key|value">';
        html += '  </div>';
        html += '</div>';

        html += '</div>';
        return html;
    }

    /**
     * COLOUR Control (Swatch + Hex + Opacity + Global Colors dropdown)
     */
    function renderColor( def, deviceVal, inheritedVal ) {
        var val = ( deviceVal !== undefined && deviceVal !== null ) ? deviceVal : '';
        var isGlobal = typeof val === 'string' && val.indexOf( 'var(--lk-global-color-' ) !== -1;
        var globalName = '';
        if ( isGlobal ) {
            for ( var g = 0; g < GLOBAL_COLORS.length; g++ ) {
                if ( val.indexOf( GLOBAL_COLORS[g].id ) !== -1 ) {
                    globalName = GLOBAL_COLORS[g].name;
                    break;
                }
            }
        }

        var html = '<div class="lk-control-field lk-field-color" data-name="' + escapeHtml( def.name ) + '">';
        html += '<div class="lk-color-row">';

        // Swatch
        html += '<div class="lk-color-swatch-wrap">';
        html += '  <button type="button" class="lk-color-swatch-btn" style="background-color: ' + ( val || 'transparent' ) + ';" title="Choose color"></button>';
        html += '</div>';

        // Display text or global tag
        if ( isGlobal && globalName ) {
            html += '<div class="lk-color-global-tag"><span class="dashicons dashicons-admin-site-alt3"></span> ' + escapeHtml( globalName ) + '</div>';
        } else {
            html += '<input type="text" class="lk-input lk-color-hex" value="' + escapeHtml( val ) + '" placeholder="#000000">';
        }

        // Global palette globe button
        html += '<button type="button" class="lk-color-globe-btn' + ( isGlobal ? ' lk-globe-active' : '' ) + '" title="Global Colors">';
        html += '  <span class="dashicons dashicons-admin-site-alt3"></span>';
        html += '</button>';

        // Clear button
        if ( val ) {
            html += '<button type="button" class="lk-color-clear-btn" title="Clear color">&times;</button>';
        }

        html += '</div>'; // .lk-color-row

        // Global colors dropdown list
        html += '<div class="lk-color-globals-menu" style="display:none;">';
        html += '  <div class="lk-globals-title">Global Colors</div>';
        for ( var k = 0; k < GLOBAL_COLORS.length; k++ ) {
            var gc = GLOBAL_COLORS[k];
            html += '<div class="lk-global-color-item" data-var="' + gc.var + '" data-val="' + gc.value + '" data-name="' + gc.name + '">';
            html += '  <span class="lk-gc-swatch" style="background-color: ' + gc.value + ';"></span>';
            html += '  <span class="lk-gc-name">' + gc.name + '</span>';
            html += '  <span class="lk-gc-hex">' + gc.value + '</span>';
            html += '</div>';
        }
        html += '</div>';

        // Picker popover
        html += '<div class="lk-color-popover" style="display:none;">';
        html += '  <div class="lk-color-palette-quick">';
        var quickColors = [ '#38A8F5', '#1E90E0', '#54595F', '#333333', '#FFFFFF', '#000000', '#E9F6FE', '#E74C3C', '#2ECC71', '#F1C40F' ];
        for ( var qc = 0; qc < quickColors.length; qc++ ) {
            html += '<button type="button" class="lk-quick-color" data-color="' + quickColors[qc] + '" style="background-color: ' + quickColors[qc] + ';"></button>';
        }
        html += '  </div>';
        html += '  <div class="lk-color-popover-inputs">';
        html += '    <input type="color" class="lk-native-color-picker" value="' + ( val && val[0] === '#' ? val : '#38a8f5' ) + '">';
        html += '    <input type="text" class="lk-input lk-popover-hex" value="' + escapeHtml( val ) + '" placeholder="#38A8F5">';
        html += '  </div>';
        html += '</div>';

        html += '</div>';
        return html;
    }

    /**
     * TYPOGRAPHY Popover Control
     */
    function renderTypography( def, deviceVal ) {
        var current = typeof deviceVal === 'object' && deviceVal !== null ? deviceVal : {};
        var summary = current.family ? current.family : 'Default';
        if ( current.size && current.size.size ) {
            summary += ', ' + current.size.size + ( current.size.unit || 'px' );
        }

        var html = '<div class="lk-control-field lk-field-typography" data-name="' + escapeHtml( def.name ) + '">';
        html += '<div class="lk-typo-bar">';
        html += '  <span class="lk-typo-summary">' + escapeHtml( summary ) + '</span>';
        html += '  <button type="button" class="lk-typo-edit-btn" title="Edit Typography"><span class="dashicons dashicons-edit"></span></button>';
        html += '  <button type="button" class="lk-typo-globe-btn" title="Global Fonts"><span class="dashicons dashicons-admin-site-alt3"></span></button>';
        html += '</div>';

        // Global fonts menu
        html += '<div class="lk-color-globals-menu lk-fonts-globals-menu" style="display:none;">';
        html += '  <div class="lk-globals-title">Global Fonts</div>';
        for ( var gf = 0; gf < GLOBAL_FONTS.length; gf++ ) {
            var gFont = GLOBAL_FONTS[gf];
            html += '<div class="lk-global-font-item" data-family="' + gFont.family + '" data-name="' + gFont.name + '">';
            html += '  <span class="lk-gc-name">' + gFont.name + '</span>';
            html += '  <span class="lk-gc-hex">' + gFont.family + '</span>';
            html += '</div>';
        }
        html += '</div>';

        // Typography Popover
        html += '<div class="lk-typo-popover" style="display:none;">';

        // Font family — searchable, and it includes fonts installed in WordPress
        // (Appearance > Fonts / the theme) alongside the system and Google lists.
        html += '<div class="lk-typo-row">';
        html += '  <label>Family</label>';
        html += '  <div class="lk-font-picker">';
        html += '    <input type="text" class="lk-input lk-font-search" placeholder="Search fonts…" value="' + escapeHtml( current.family || '' ) + '">';
        html += '    <select class="lk-input lk-typo-family lk-font-select">';
        html += fontOptionsHtml( current.family || '', '' );
        html += '    </select>';
        html += '  </div>';
        html += '</div>';

        // Size
        var sizeVal = current.size && current.size.size !== undefined ? current.size.size : '';
        var sizeUnit = current.size && current.size.unit ? current.size.unit : 'px';
        html += '<div class="lk-typo-row">';
        html += '  <label>Size</label>';
        html += '  <div class="lk-typo-size-wrap">';
        html += '    <input type="number" class="lk-input lk-typo-size-num" value="' + escapeHtml( sizeVal ) + '" placeholder="16">';
        html += '    <select class="lk-input lk-typo-size-unit">';
        [ 'px', 'em', 'rem', 'vw' ].forEach( function( un ) {
            html += '<option value="' + un + '"' + ( sizeUnit === un ? ' selected' : '' ) + '>' + un + '</option>';
        });
        html += '    </select>';
        html += '  </div>';
        html += '</div>';

        // Weight
        html += '<div class="lk-typo-row">';
        html += '  <label>Weight</label>';
        html += '  <select class="lk-input lk-typo-weight">';
        var weights = [ { v: '', l: 'Default' }, { v: '100', l: '100 - Thin' }, { v: '200', l: '200 - Extra Light' }, { v: '300', l: '300 - Light' }, { v: '400', l: '400 - Normal' }, { v: '500', l: '500 - Medium' }, { v: '600', l: '600 - Semi Bold' }, { v: '700', l: '700 - Bold' }, { v: '800', l: '800 - Extra Bold' }, { v: '900', l: '900 - Black' } ];
        weights.forEach( function( w ) {
            html += '<option value="' + w.v + '"' + ( ( current.weight || '' ) === w.v ? ' selected' : '' ) + '>' + w.l + '</option>';
        });
        html += '  </select>';
        html += '</div>';

        // Transform
        html += '<div class="lk-typo-row">';
        html += '  <label>Transform</label>';
        html += '  <select class="lk-input lk-typo-transform">';
        [ { v: '', l: 'Default' }, { v: 'uppercase', l: 'Uppercase' }, { v: 'lowercase', l: 'Lowercase' }, { v: 'capitalize', l: 'Capitalize' }, { v: 'none', l: 'Normal' } ].forEach( function( t ) {
            html += '<option value="' + t.v + '"' + ( ( current.transform || '' ) === t.v ? ' selected' : '' ) + '>' + t.l + '</option>';
        });
        html += '  </select>';
        html += '</div>';

        // Style
        html += '<div class="lk-typo-row">';
        html += '  <label>Style</label>';
        html += '  <select class="lk-input lk-typo-style">';
        [ { v: '', l: 'Default' }, { v: 'normal', l: 'Normal' }, { v: 'italic', l: 'Italic' }, { v: 'oblique', l: 'Oblique' } ].forEach( function( st ) {
            html += '<option value="' + st.v + '"' + ( ( current.style || '' ) === st.v ? ' selected' : '' ) + '>' + st.l + '</option>';
        });
        html += '  </select>';
        html += '</div>';

        // Line-Height
        var lhVal = current.line_height && current.line_height.size !== undefined ? current.line_height.size : '';
        var lhUnit = current.line_height && current.line_height.unit ? current.line_height.unit : 'em';
        html += '<div class="lk-typo-row">';
        html += '  <label>Line-Height</label>';
        html += '  <div class="lk-typo-size-wrap">';
        html += '    <input type="number" step="0.1" class="lk-input lk-typo-lh-num" value="' + lhVal + '" placeholder="1.5">';
        html += '    <select class="lk-input lk-typo-lh-unit"><option value="em"' + ( lhUnit === 'em' ? ' selected' : '' ) + '>em</option><option value="px"' + ( lhUnit === 'px' ? ' selected' : '' ) + '>px</option></select>';
        html += '  </div>';
        html += '</div>';

        // Letter Spacing
        var lsVal = current.letter_spacing && current.letter_spacing.size !== undefined ? current.letter_spacing.size : '';
        html += '<div class="lk-typo-row">';
        html += '  <label>Letter Spacing</label>';
        html += '  <div class="lk-typo-size-wrap">';
        html += '    <input type="number" step="0.1" class="lk-input lk-typo-ls-num" value="' + lsVal + '" placeholder="0">';
        html += '    <span class="lk-typo-static-unit">px</span>';
        html += '  </div>';
        html += '</div>';

        // Word Spacing
        var wsVal = current.word_spacing && current.word_spacing.size !== undefined ? current.word_spacing.size : '';
        html += '<div class="lk-typo-row">';
        html += '  <label>Word Spacing</label>';
        html += '  <div class="lk-typo-size-wrap">';
        html += '    <input type="number" step="0.1" class="lk-input lk-typo-ws-num" value="' + wsVal + '" placeholder="0">';
        html += '    <span class="lk-typo-static-unit">px</span>';
        html += '  </div>';
        html += '</div>';

        // Reset button
        html += '<div class="lk-typo-actions">';
        html += '  <button type="button" class="lk-typo-reset-btn">Reset</button>';
        html += '</div>';

        html += '</div>'; // .lk-typo-popover
        html += '</div>';
        return html;
    }

    /**
     * MEDIA Control (WordPress Media Library)
     */
    function renderMedia( def, deviceVal ) {
        var current = typeof deviceVal === 'object' && deviceVal !== null ? deviceVal : { url: deviceVal || '', id: '' };
        var hasImg = current.url ? true : false;

        var html = '<div class="lk-control-field lk-field-media" data-name="' + escapeHtml( def.name ) + '">';
        html += '<div class="lk-media-preview-box' + ( hasImg ? ' lk-has-image' : '' ) + '">';
        if ( hasImg ) {
            html += '<img src="' + escapeHtml( current.url ) + '" class="lk-media-img" alt="">';
        } else {
            html += '<span class="dashicons dashicons-format-image"></span>';
        }
        html += '</div>';

        html += '<input type="hidden" class="lk-media-url" value="' + escapeHtml( current.url || '' ) + '">';
        html += '<input type="hidden" class="lk-media-id" value="' + escapeHtml( String( current.id || '' ) ) + '">';

        html += '<div class="lk-media-actions">';
        html += '  <button type="button" class="lk-media-upload-btn">' + ( hasImg ? 'Change Image' : 'Choose Image' ) + '</button>';
        if ( hasImg ) {
            html += '  <button type="button" class="lk-media-remove-btn" title="Remove image">&times;</button>';
        }
        html += '</div>';

        html += '</div>';
        return html;
    }

    /**
     * CODE Control (wp.codeEditor / CodeMirror for Custom CSS and HTML)
     */
    function renderCode( def, deviceVal, inheritedVal, device ) {
        var val = ( deviceVal !== undefined && deviceVal !== null ) ? String( deviceVal ) : ( def.default || '' );
        var mode = def.mode || ( def.name === 'html' ? 'html' : 'css' );
        var isReadOnly = !!def.readonly || ( ( def.name === 'html' || mode === 'html' ) && window.loopkitEditor && window.loopkitEditor.canUnfilteredHtml === false );
        var readOnlyAttr = isReadOnly ? ' readonly="readonly"' : '';
        var extraCls = isReadOnly ? ' lk-control-readonly' : '';

        var html = '<div class="lk-control-field lk-field-code' + extraCls + '" data-name="' + escapeHtml( def.name ) + '" data-mode="' + escapeHtml( mode ) + '" data-readonly="' + ( isReadOnly ? 'true' : 'false' ) + '">';
        if ( isReadOnly ) {
            html += '<div class="lk-code-readonly-badge"><span class="dashicons dashicons-lock"></span> ' + escapeHtml( def.description || 'Read-only: unfiltered_html capability required.' ) + '</div>';
        } else if ( def.description ) {
            html += '<div class="lk-control-desc" style="font-size:11px;color:#6b7280;margin-bottom:6px;">' + escapeHtml( def.description ) + '</div>';
        }
        html += '<textarea class="lk-code-editor lk-control-input" data-name="' + escapeHtml( def.name ) + '" data-mode="' + escapeHtml( mode ) + '" rows="10" placeholder="' + escapeHtml( def.placeholder || '' ) + '"' + readOnlyAttr + '>' + escapeHtml( val ) + '</textarea>';
        html += '</div>';
        return html;
    }

    /**
     * POPOVER Control Renderer (Text Shadow, Box Shadow, Text Stroke, CSS Filters)
     * Pencil button highlighted when value set, reset inside, one open at once.
     */
    function renderPopover( def, deviceVal, inheritedVal, device, settings ) {
        var popType = def.popoverType || def.name;
        var curVal = ( deviceVal !== undefined && deviceVal !== null ) ? deviceVal : ( inheritedVal || {} );
        if ( typeof curVal !== 'object' || Array.isArray( curVal ) ) curVal = {};

        var hasVal = false;
        if ( popType === 'text_shadow' ) {
            hasVal = !!( curVal.color || curVal.blur || curVal.horizontal || curVal.vertical );
        } else if ( popType === 'box_shadow' ) {
            hasVal = !!( curVal.color || curVal.horizontal || curVal.vertical || curVal.blur || curVal.spread || ( curVal.position && curVal.position !== 'outline' ) );
        } else if ( popType === 'text_stroke' ) {
            hasVal = !!( ( curVal.width !== undefined && curVal.width !== '' ) || curVal.color );
        } else if ( popType === 'css_filters' ) {
            hasVal = !!( ( curVal.blur && curVal.blur != 0 ) ||
                         ( curVal.brightness !== undefined && curVal.brightness != 100 && curVal.brightness !== '' ) ||
                         ( curVal.contrast !== undefined && curVal.contrast != 100 && curVal.contrast !== '' ) ||
                         ( curVal.saturation !== undefined && curVal.saturation != 100 && curVal.saturation !== '' ) ||
                         ( curVal.hue && curVal.hue != 0 ) );
        }

        var html = '<div class="lk-control-field lk-field-popover" data-name="' + escapeHtml( def.name ) + '" data-popover-type="' + escapeHtml( popType ) + '">';
        html += '<div class="lk-popover-toggle-wrap">';
        html += '  <button type="button" class="lk-popover-toggle-btn' + ( hasVal ? ' lk-popover-active' : '' ) + '" data-popover="' + escapeHtml( def.name ) + '" title="Edit ' + escapeHtml( def.label || '' ) + '">';
        html += '    <span class="dashicons dashicons-edit"></span>';
        html += '  </button>';
        html += '</div>';

        // Popover panel
        html += '<div class="lk-popover-panel lk-popover-panel-' + escapeHtml( popType ) + '" data-popover-panel="' + escapeHtml( def.name ) + '" style="display:none;">';

        if ( popType === 'text_shadow' ) {
            var tsColor = curVal.color || 'rgba(0,0,0,0.3)';
            var tsBlur = curVal.blur !== undefined && curVal.blur !== '' ? curVal.blur : 10;
            var tsH = curVal.horizontal !== undefined && curVal.horizontal !== '' ? curVal.horizontal : 0;
            var tsV = curVal.vertical !== undefined && curVal.vertical !== '' ? curVal.vertical : 0;

            html += '<div class="lk-popover-row"><label>Color</label>';
            html += renderColor( { name: def.name + '__color' }, curVal.color || '', '' );
            html += '</div>';

            html += '<div class="lk-popover-row"><label>Blur</label>';
            html += '<div class="lk-control-field lk-field-slider"><input type="range" class="lk-slider-range lk-pop-input" data-key="blur" min="0" max="100" step="1" value="' + tsBlur + '"><input type="number" class="lk-input lk-slider-number lk-pop-input" data-key="blur" min="0" max="100" value="' + tsBlur + '"></div></div>';

            html += '<div class="lk-popover-row"><label>Horizontal</label>';
            html += '<div class="lk-control-field lk-field-slider"><input type="range" class="lk-slider-range lk-pop-input" data-key="horizontal" min="-100" max="100" step="1" value="' + tsH + '"><input type="number" class="lk-input lk-slider-number lk-pop-input" data-key="horizontal" min="-100" max="100" value="' + tsH + '"></div></div>';

            html += '<div class="lk-popover-row"><label>Vertical</label>';
            html += '<div class="lk-control-field lk-field-slider"><input type="range" class="lk-slider-range lk-pop-input" data-key="vertical" min="-100" max="100" step="1" value="' + tsV + '"><input type="number" class="lk-input lk-slider-number lk-pop-input" data-key="vertical" min="-100" max="100" value="' + tsV + '"></div></div>';

        } else if ( popType === 'box_shadow' ) {
            var bsH = curVal.horizontal !== undefined && curVal.horizontal !== '' ? curVal.horizontal : 0;
            var bsV = curVal.vertical !== undefined && curVal.vertical !== '' ? curVal.vertical : 0;
            var bsBlur = curVal.blur !== undefined && curVal.blur !== '' ? curVal.blur : 10;
            var bsSpread = curVal.spread !== undefined && curVal.spread !== '' ? curVal.spread : 0;
            var bsPos = curVal.position || 'outline';

            html += '<div class="lk-popover-row"><label>Color</label>';
            html += renderColor( { name: def.name + '__color' }, curVal.color || '', '' );
            html += '</div>';

            html += '<div class="lk-popover-row"><label>Horizontal</label>';
            html += '<div class="lk-control-field lk-field-slider"><input type="range" class="lk-slider-range lk-pop-input" data-key="horizontal" min="-100" max="100" step="1" value="' + bsH + '"><input type="number" class="lk-input lk-slider-number lk-pop-input" data-key="horizontal" min="-100" max="100" value="' + bsH + '"></div></div>';

            html += '<div class="lk-popover-row"><label>Vertical</label>';
            html += '<div class="lk-control-field lk-field-slider"><input type="range" class="lk-slider-range lk-pop-input" data-key="vertical" min="-100" max="100" step="1" value="' + bsV + '"><input type="number" class="lk-input lk-slider-number lk-pop-input" data-key="vertical" min="-100" max="100" value="' + bsV + '"></div></div>';

            html += '<div class="lk-popover-row"><label>Blur</label>';
            html += '<div class="lk-control-field lk-field-slider"><input type="range" class="lk-slider-range lk-pop-input" data-key="blur" min="0" max="100" step="1" value="' + bsBlur + '"><input type="number" class="lk-input lk-slider-number lk-pop-input" data-key="blur" min="0" max="100" value="' + bsBlur + '"></div></div>';

            html += '<div class="lk-popover-row"><label>Spread</label>';
            html += '<div class="lk-control-field lk-field-slider"><input type="range" class="lk-slider-range lk-pop-input" data-key="spread" min="-100" max="100" step="1" value="' + bsSpread + '"><input type="number" class="lk-input lk-slider-number lk-pop-input" data-key="spread" min="-100" max="100" value="' + bsSpread + '"></div></div>';

            html += '<div class="lk-popover-row"><label>Position</label>';
            html += '<select class="lk-input lk-pop-select" data-key="position">';
            html += '<option value="outline"' + ( bsPos !== 'inset' ? ' selected' : '' ) + '>Outline</option>';
            html += '<option value="inset"' + ( bsPos === 'inset' ? ' selected' : '' ) + '>Inset</option>';
            html += '</select></div>';

        } else if ( popType === 'text_stroke' ) {
            var strokeWidth = curVal.width !== undefined && curVal.width !== '' ? curVal.width : '';
            var strokeColor = curVal.color || '#000000';

            html += '<div class="lk-popover-row"><label>Width</label>';
            html += '<div class="lk-control-field lk-field-slider"><input type="range" class="lk-slider-range lk-pop-input" data-key="width" min="0" max="50" step="1" value="' + ( strokeWidth || 0 ) + '"><input type="number" class="lk-input lk-slider-number lk-pop-input" data-key="width" min="0" max="50" value="' + strokeWidth + '" placeholder="1"></div></div>';

            html += '<div class="lk-popover-row"><label>Color</label>';
            html += renderColor( { name: def.name + '__color' }, strokeColor, '' );
            html += '</div>';

        } else if ( popType === 'css_filters' ) {
            var fBlur = curVal.blur !== undefined && curVal.blur !== '' ? curVal.blur : 0;
            var fBright = curVal.brightness !== undefined && curVal.brightness !== '' ? curVal.brightness : 100;
            var fContrast = curVal.contrast !== undefined && curVal.contrast !== '' ? curVal.contrast : 100;
            var fSat = curVal.saturation !== undefined && curVal.saturation !== '' ? curVal.saturation : 100;
            var fHue = curVal.hue !== undefined && curVal.hue !== '' ? curVal.hue : 0;

            html += '<div class="lk-popover-row"><label>Blur (0-10px)</label>';
            html += '<div class="lk-control-field lk-field-slider"><input type="range" class="lk-slider-range lk-pop-input" data-key="blur" min="0" max="10" step="0.1" value="' + fBlur + '"><input type="number" class="lk-input lk-slider-number lk-pop-input" data-key="blur" min="0" max="10" step="0.1" value="' + fBlur + '"></div></div>';

            html += '<div class="lk-popover-row"><label>Brightness (%)</label>';
            html += '<div class="lk-control-field lk-field-slider"><input type="range" class="lk-slider-range lk-pop-input" data-key="brightness" min="0" max="200" step="1" value="' + fBright + '"><input type="number" class="lk-input lk-slider-number lk-pop-input" data-key="brightness" min="0" max="200" value="' + fBright + '"></div></div>';

            html += '<div class="lk-popover-row"><label>Contrast (%)</label>';
            html += '<div class="lk-control-field lk-field-slider"><input type="range" class="lk-slider-range lk-pop-input" data-key="contrast" min="0" max="200" step="1" value="' + fContrast + '"><input type="number" class="lk-input lk-slider-number lk-pop-input" data-key="contrast" min="0" max="200" value="' + fContrast + '"></div></div>';

            html += '<div class="lk-popover-row"><label>Saturation (%)</label>';
            html += '<div class="lk-control-field lk-field-slider"><input type="range" class="lk-slider-range lk-pop-input" data-key="saturation" min="0" max="200" step="1" value="' + fSat + '"><input type="number" class="lk-input lk-slider-number lk-pop-input" data-key="saturation" min="0" max="200" value="' + fSat + '"></div></div>';

            html += '<div class="lk-popover-row"><label>Hue (0-360°)</label>';
            html += '<div class="lk-control-field lk-field-slider"><input type="range" class="lk-slider-range lk-pop-input" data-key="hue" min="0" max="360" step="1" value="' + fHue + '"><input type="number" class="lk-input lk-slider-number lk-pop-input" data-key="hue" min="0" max="360" value="' + fHue + '"></div></div>';
        }

        html += '<div class="lk-popover-actions">';
        html += '  <button type="button" class="lk-popover-reset-btn" data-reset="' + escapeHtml( def.name ) + '">Reset</button>';
        html += '</div>';

        html += '</div>'; // .lk-popover-panel
        html += '</div>'; // .lk-field-popover
        return html;
    }

    /**
     * Determine if a subtab has any custom non-empty values configured.
     */
    LoopkitControls.subtabHasValues = function( subtabDef, settings, device ) {
        if ( ! subtabDef || ! subtabDef.controls || ! settings ) return false;
        var controls = subtabDef.controls;
        for ( var i = 0; i < controls.length; i++ ) {
            var ctrl = controls[i];
            var name = ctrl.name;
            if ( ! name ) continue;

            var val = getDeviceSetting( settings, name, device );
            if ( val === undefined || val === null || val === '' ) {
                val = settings[ name ];
            }
            if ( val === undefined || val === null || val === '' ) continue;

            if ( name.indexOf( 'background_type' ) !== -1 || name.indexOf( 'background_overlay_type' ) !== -1 ) {
                if ( val === 'gradient' ) return true;
                continue;
            }

            if ( name.indexOf( 'border_type' ) !== -1 ) {
                if ( val && val !== 'none' && val !== 'default' ) return true;
                continue;
            }

            if ( typeof val === 'string' ) {
                var trimmed = val.trim();
                if ( trimmed !== '' && trimmed !== 'none' && trimmed !== 'default' && trimmed !== 'inherit' ) {
                    return true;
                }
            } else if ( typeof val === 'number' ) {
                if ( ! isNaN( val ) ) return true;
            } else if ( typeof val === 'object' ) {
                if ( val.url && String( val.url ).trim() !== '' ) return true;
                if ( val.size !== undefined && String( val.size ).trim() !== '' ) return true;
                if ( ( val.top !== undefined && String( val.top ).trim() !== '' ) ||
                     ( val.right !== undefined && String( val.right ).trim() !== '' ) ||
                     ( val.bottom !== undefined && String( val.bottom ).trim() !== '' ) ||
                     ( val.left !== undefined && String( val.left ).trim() !== '' ) ) {
                    return true;
                }
                if ( val.color || val.horizontal || val.vertical || val.blur || val.spread ) return true;
                if ( val.brightness !== undefined || val.contrast !== undefined || val.saturation !== undefined || val.hue !== undefined ) return true;
            }
        }
        return false;
    };

    /**
     * SUBTABS (Normal / Hover, Top / Bottom) Control Renderer
     */
    function renderTabsGroup( def, settings, device, element, activeSubtabs ) {
        var subtabs = def.subtabs || [];
        if ( ! subtabs.length ) return '';

        var activePaneId = ( activeSubtabs && activeSubtabs[ def.name ] ) ? activeSubtabs[ def.name ] : '';

        var html = '<div class="lk-control-wrap lk-control-subtabs" data-control="' + escapeHtml( def.name ) + '">';
        html += '<div class="lk-subtabs-nav">';
        for ( var t = 0; t < subtabs.length; t++ ) {
            var st = subtabs[t];
            var paneId = def.name + '-' + st.id;
            var isTabActive = activePaneId ? ( paneId === activePaneId ) : ( t === 0 );
            var activeClass = isTabActive ? ' lk-subtab-active' : '';

            var hasValues = LoopkitControls.subtabHasValues( st, settings, device );
            var hasValClass = hasValues ? ' lk-subtab-has-value' : '';
            var dotHtml = hasValues ? '<span class="lk-subtab-dot"></span>' : '';

            html += '<button type="button" class="lk-subtab-btn' + activeClass + hasValClass + '" data-pane="' + escapeHtml( paneId ) + '">' + escapeHtml( st.label ) + dotHtml + '</button>';
        }
        html += '</div>';

        for ( var p = 0; p < subtabs.length; p++ ) {
            var pane = subtabs[p];
            var pId = def.name + '-' + pane.id;
            var isPaneActive = activePaneId ? ( pId === activePaneId ) : ( p === 0 );
            var paneActiveClass = isPaneActive ? ' lk-pane-active' : '';

            html += '<div class="lk-subtab-pane' + paneActiveClass + '" data-pane-id="' + escapeHtml( pId ) + '">';
            var pControls = pane.controls || [];
            for ( var c = 0; c < pControls.length; c++ ) {
                try {
                    html += LoopkitControls.render( pControls[c], settings, device, element, activeSubtabs );
                } catch ( err ) {
                    console.error( 'Loopkit: Error rendering control "' + ( pControls[c] && ( pControls[c].name || pControls[c].label ) ) + '" in subtab pane "' + pId + '":', err );
                }
            }
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    /* =========================================================
       NEW SHARED CONTROLS (PART E)
       ========================================================= */

    var HOVER_ANIMATIONS = [
        { value: '', label: 'None' },
        { value: 'grow', label: 'Grow' },
        { value: 'shrink', label: 'Shrink' },
        { value: 'pulse', label: 'Pulse' },
        { value: 'pulse-grow', label: 'Pulse Grow' },
        { value: 'pulse-shrink', label: 'Pulse Shrink' },
        { value: 'push', label: 'Push' },
        { value: 'pop', label: 'Pop' },
        { value: 'bounce-in', label: 'Bounce In' },
        { value: 'bounce-out', label: 'Bounce Out' },
        { value: 'rotate', label: 'Rotate' },
        { value: 'grow-rotate', label: 'Grow Rotate' },
        { value: 'float', label: 'Float' },
        { value: 'sink', label: 'Sink' },
        { value: 'bob', label: 'Bob' },
        { value: 'hang', label: 'Hang' },
        { value: 'skew', label: 'Skew' },
        { value: 'skew-forward', label: 'Skew Forward' },
        { value: 'skew-backward', label: 'Skew Backward' },
        { value: 'wobble-horizontal', label: 'Wobble Horizontal' },
        { value: 'wobble-vertical', label: 'Wobble Vertical' },
        { value: 'wobble-to-bottom-right', label: 'Wobble To Bottom Right' },
        { value: 'wobble-to-top-right', label: 'Wobble To Top Right' },
        { value: 'wobble-top', label: 'Wobble Top' },
        { value: 'wobble-bottom', label: 'Wobble Bottom' },
        { value: 'wobble-skew', label: 'Wobble Skew' },
        { value: 'buzz', label: 'Buzz' },
        { value: 'buzz-out', label: 'Buzz Out' }
    ];

    /**
     * HOVER ANIMATION Control
     */
    function renderHoverAnimation( def, deviceVal, inheritedVal, device ) {
        var defName = ( def && def.name ) ? def.name : 'hover_animation';
        var val = ( deviceVal !== undefined && deviceVal !== null ) ? String( deviceVal ) : ( ( def && def.default ) ? String( def.default ) : '' );
        var html = '<div class="lk-control-field">';
        html += '<select class="lk-input lk-input-select lk-hover-animation-select" data-name="' + escapeHtml( defName ) + '">';
        for ( var i = 0; i < HOVER_ANIMATIONS.length; i++ ) {
            var opt = HOVER_ANIMATIONS[i];
            var isSel = ( opt.value === val ) ? ' selected' : '';
            html += '<option value="' + escapeHtml( opt.value ) + '"' + isSel + '>' + escapeHtml( opt.label ) + '</option>';
        }
        html += '</select>';
        html += '</div>';
        return html;
    }

    /**
     * IMAGE SIZE Control
     */
    function renderImageSize( def, deviceVal, inheritedVal, device, settings ) {
        var defName = ( def && def.name ) ? def.name : 'image_size';
        var val = ( deviceVal !== undefined && deviceVal !== null ) ? deviceVal : ( ( def && def.default ) || 'large' );
        var sizeName = ( typeof val === 'object' && val !== null && val.size ) ? String( val.size ) : ( typeof val === 'string' ? val : 'large' );
        var customDim = ( typeof val === 'object' && val !== null && val.custom_dimension ) ? val.custom_dimension : ( settings && settings[ defName + '_custom_dimension' ] ) || { width: '', height: '' };

        var sizes = ( window.loopkitEditor && window.loopkitEditor.imageSizes ) || {
            thumbnail: 'Thumbnail - 150 x 150',
            medium: 'Medium - 300 x 300',
            medium_large: 'Medium Large - 768 x 0',
            large: 'Large - 1024 x 1024',
            full: 'Full',
            custom: 'Custom'
        };

        var html = '<div class="lk-control-field lk-field-image-size" data-name="' + escapeHtml( defName ) + '">';
        html += '<select class="lk-input lk-input-select lk-image-size-select" data-name="' + escapeHtml( defName ) + '">';
        for ( var k in sizes ) {
            var isSel = ( k === sizeName ) ? ' selected' : '';
            var sizeLabel = ( typeof sizes[k] === 'object' && sizes[k] !== null ) ? ( sizes[k].name || sizes[k].label || k ) : String( sizes[k] || k );
            html += '<option value="' + escapeHtml( k ) + '"' + isSel + '>' + escapeHtml( sizeLabel ) + '</option>';
        }
        html += '</select>';

        var isCustom = ( sizeName === 'custom' );
        html += '<div class="lk-image-size-custom-fields" style="' + ( isCustom ? '' : 'display:none;' ) + '">';
        html += '  <div class="lk-image-size-custom-col">';
        html += '    <label>Width (px)</label>';
        html += '    <input type="number" class="lk-input lk-input-number lk-image-size-custom-w" data-dimension="width" placeholder="Width" value="' + escapeHtml( ( customDim && customDim.width ) || '' ) + '">';
        html += '  </div>';
        html += '  <div class="lk-image-size-custom-col">';
        html += '    <label>Height (px)</label>';
        html += '    <input type="number" class="lk-input lk-input-number lk-image-size-custom-h" data-dimension="height" placeholder="Height" value="' + escapeHtml( ( customDim && customDim.height ) || '' ) + '">';
        html += '  </div>';
        html += '</div>';

        html += '</div>';
        return html;
    }

    /**
     * WYSIWYG Control
     */
    function renderWysiwyg( def, deviceVal, inheritedVal, device, settings, element ) {
        var defName = ( def && def.name ) ? def.name : 'wysiwyg';
        var val = ( deviceVal !== undefined && deviceVal !== null ) ? String( deviceVal ) : ( ( def && def.default ) || '' );
        var elId = ( element && element.id ) ? element.id : 'temp';
        var editorId = 'lk_wysiwyg_' + defName + '_' + elId;

        var html = '<div class="lk-control-field lk-field-wysiwyg" data-name="' + escapeHtml( defName ) + '">';
        html += '<div class="lk-wysiwyg-wrap" data-editor-id="' + escapeHtml( editorId ) + '">';
        html += '<textarea id="' + escapeHtml( editorId ) + '" class="lk-wysiwyg-textarea lk-input" data-name="' + escapeHtml( defName ) + '" rows="8">' + escapeHtml( val ) + '</textarea>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * ICON PICKER Control
     */
    function renderIconPicker( def, deviceVal, inheritedVal, device ) {
        var defName = ( def && def.name ) ? def.name : 'icon';
        var val = ( deviceVal !== undefined && deviceVal !== null ) ? deviceVal : ( ( def && def.default ) || { library: '', value: '' } );
        var library = ( typeof val === 'object' && val !== null && val.library ) ? String( val.library ) : '';
        var iconVal = ( typeof val === 'object' && val !== null && val.value !== undefined ) ? val.value : ( typeof val === 'string' ? val : '' );

        var hasIcon = !!( iconVal && ( typeof iconVal === 'string' ? iconVal.trim() : ( iconVal.url || iconVal.svg ) ) );

        var previewHtml = '';
        if ( hasIcon ) {
            if ( library === 'svg' ) {
                if ( typeof iconVal === 'object' && iconVal !== null && iconVal.svg ) {
                    previewHtml = iconVal.svg;
                } else if ( typeof iconVal === 'object' && iconVal !== null && iconVal.url ) {
                    previewHtml = '<img src="' + escapeHtml( iconVal.url ) + '" class="lk-svg-img" alt="">';
                } else if ( typeof iconVal === 'string' ) {
                    if ( iconVal.indexOf( '<svg' ) !== -1 ) {
                        previewHtml = iconVal;
                    } else {
                        previewHtml = '<img src="' + escapeHtml( iconVal ) + '" class="lk-svg-img" alt="">';
                    }
                }
            } else {
                // Font Awesome
                var iconName = ( typeof iconVal === 'object' && iconVal !== null ) ? ( iconVal.name || '' ) : String( iconVal );
                var style = library.replace( /^fa-/, '' ) || 'solid';
                previewHtml = '<span class="dashicons dashicons-star-filled"></span>';
                var iconEngine = ( typeof LoopkitIcons !== 'undefined' ? LoopkitIcons : ( window.LoopkitIcons || null ) );
                if ( iconEngine && typeof iconEngine.getPreviewSvg === 'function' ) {
                    var svg = iconEngine.getPreviewSvg( style, iconName );
                    if ( svg ) {
                        previewHtml = svg;
                    }
                }
            }
        } else {
            previewHtml = '<span class="dashicons dashicons-plus"></span>';
        }

        var jsonStr = typeof val === 'object' && val !== null ? JSON.stringify( val ) : JSON.stringify({ library: library, value: iconVal });

        var html = '<div class="lk-control-field lk-field-icon-picker" data-name="' + escapeHtml( defName ) + '" data-icon-val=\'' + escapeHtml( jsonStr ) + '\'>';
        html += '<div class="lk-icon-picker-wrap">';
        html += '  <div class="lk-icon-preview-box" title="Click to choose icon">';
        html += previewHtml;
        if ( hasIcon ) {
            html += '    <button type="button" class="lk-icon-remove-btn" title="Remove icon">&times;</button>';
        }
        html += '  </div>';
        html += '  <div class="lk-icon-picker-actions">';
        html += '    <button type="button" class="lk-icon-library-btn"><span class="dashicons dashicons-grid-view"></span> Icon Library</button>';
        // Paste SVG markup directly. The data model already understands
        // library:'svg' with a raw <svg> string — there was simply no way to
        // type one in, only to upload a file.
        html += '    <button type="button" class="lk-icon-write-svg-btn"><span class="dashicons dashicons-editor-code"></span> Write SVG</button>';
        if ( window.loopkitEditor && window.loopkitEditor.isAdmin ) {
            html += '    <button type="button" class="lk-icon-upload-svg-btn"><span class="dashicons dashicons-upload"></span> Upload SVG</button>';
            html += '    <input type="file" class="lk-icon-file-input" accept=".svg" style="display:none;">';
        }
        html += '  </div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * REPEATER Control
     */
    function renderRepeater( def, deviceVal, inheritedVal, device, settings, element ) {
        var defName = ( def && def.name ) ? def.name : 'repeater';
        var items = ( deviceVal !== undefined && Array.isArray( deviceVal ) ) ? deviceVal : ( ( def && Array.isArray( def.default ) ) ? def.default : [] );
        var titleField = ( def && def.title_field ) || 'title';
        var fields = ( def && Array.isArray( def.fields ) ) ? def.fields : [];

        var itemDefaults = {};
        fields.forEach( function( f ) {
            if ( f && f.name && f.default !== undefined ) itemDefaults[ f.name ] = f.default;
        } );

        var html = '<div class="lk-control-field lk-field-repeater" data-name="' + escapeHtml( defName ) + '"'
            + ' data-title-field="' + escapeHtml( titleField ) + '"'
            + " data-item-defaults='" + escapeHtml( JSON.stringify( itemDefaults ) ) + "'>";
        html += '<div class="lk-repeater-wrap">';
        html += '  <ul class="lk-repeater-list">';

        for ( var i = 0; i < items.length; i++ ) {
            var rawItem = items[i];
            var item = ( typeof rawItem === 'object' && rawItem !== null ) ? rawItem : {};
            if ( typeof rawItem !== 'object' || rawItem === null ) {
                item[ titleField ] = String( rawItem !== undefined && rawItem !== null ? rawItem : '' );
            }
            if ( ! item._id ) {
                item._id = 'lk_item_' + Math.random().toString(36).substr(2, 8);
            }
            var rowTitle = ( item[ titleField ] !== undefined && item[ titleField ] !== '' )
                ? String( item[ titleField ] )
                : ( 'Item #' + ( i + 1 ) );

            html += '    <li class="lk-repeater-row" data-id="' + escapeHtml( item._id ) + '" data-index="' + i + '" draggable="false">';
            html += '      <div class="lk-repeater-row-header">';
            html += '        <span class="dashicons dashicons-menu lk-repeater-drag-handle" title="Drag to reorder"></span>';
            html += '        <span class="lk-repeater-row-title">' + escapeHtml( rowTitle ) + '</span>';
            html += '        <div class="lk-repeater-actions">';
            html += '          <button type="button" class="lk-repeater-action lk-repeater-duplicate" title="Duplicate item"><span class="dashicons dashicons-admin-page"></span></button>';
            html += '          <button type="button" class="lk-repeater-action lk-repeater-delete" title="Delete item"><span class="dashicons dashicons-trash"></span></button>';
            html += '        </div>';
            html += '        <span class="dashicons dashicons-arrow-down-alt2 lk-repeater-chevron"></span>';
            html += '      </div>';

            html += '      <div class="lk-repeater-row-content">';
            for ( var f = 0; f < fields.length; f++ ) {
                var fDef = fields[f];
                if ( ! fDef || ! fDef.name ) continue;
                try {
                    var fVal = item[ fDef.name ];
                    var fInherited = undefined;

                    html += '<div class="lk-repeater-field-wrap lk-control-wrap lk-control-' + fDef.type + '" data-repeater-field="' + escapeHtml( fDef.name ) + '" data-item-id="' + escapeHtml( item._id ) + '">';
                    html += renderControlHeader( fDef, device );
                    switch ( fDef.type ) {
                        case 'text':
                            html += renderText( fDef, fVal, fInherited, device );
                            break;
                        case 'textarea':
                            html += renderTextarea( fDef, fVal, fInherited, device );
                            break;
                        case 'number':
                            html += renderNumber( fDef, fVal, fInherited, device );
                            break;
                        case 'select':
                            html += renderSelect( fDef, fVal, fInherited, device );
                            break;
                        case 'switcher':
                            html += renderSwitcher( fDef, fVal, fInherited, device );
                            break;
                        case 'icon':
                        case 'icons':
                        case 'icon_picker':
                            html += renderIconPicker( fDef, fVal, fInherited, device );
                            break;
                        case 'url':
                            html += renderUrl( fDef, fVal, fInherited, device );
                            break;
                        case 'color':
                            html += renderColor( fDef, fVal, fInherited, device );
                            break;
                        case 'media':
                            html += renderMedia( fDef, fVal, fInherited, device );
                            break;
                        case 'hover_animation':
                            html += renderHoverAnimation( fDef, fVal, fInherited, device );
                            break;
                        case 'image_size':
                            html += renderImageSize( fDef, fVal, fInherited, device, item );
                            break;
                        default:
                            html += renderText( fDef, fVal, fInherited, device );
                            break;
                    }
                    html += renderDescription( fDef );
                    html += '</div>';
                } catch ( fErr ) {
                    console.error( 'Loopkit: Failed to render repeater field "' + ( fDef && fDef.name ) + '":', fErr );
                    html += '<div class="lk-repeater-field-wrap lk-control-wrap lk-control-error"><div style="padding:4px 8px;font-size:11px;color:#c0392b;">Field failed: ' + escapeHtml( fDef.name ) + '</div></div>';
                }
            }
            html += '      </div>';
            html += '    </li>';
        }

        html += '  </ul>';
        html += '  <button type="button" class="lk-repeater-add"><span class="dashicons dashicons-plus"></span> ' + escapeHtml( ( def && def.button_text ) || '+ Add Item' ) + '</button>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /* =========================================================
       MAIN RENDER CONTROL DISPATCHER
       ========================================================= */
    LoopkitControls.render = function( def, settings, device, element, activeSubtabs ) {
        if ( ! def ) return '';
        try {
            var deviceVal   = getDeviceSetting( settings, def.name, device );
        var inheritedVal = getInheritedSetting( settings, def.name, device );

        var isHidden = false;

        // Condition and is_visible checking
        if ( typeof def.is_visible === 'function' ) {
            if ( ! def.is_visible( settings, device, element ) ) {
                isHidden = true;
            }
        } else if ( def.condition ) {
            if ( ! LoopkitControls.evaluateCondition( def.condition, settings, device ) ) {
                isHidden = true;
            }
        }

        var isResponsive = !! def.responsive;
        var html = '<div class="lk-control-wrap lk-control-' + def.type + ( isHidden ? ' lk-hidden' : '' ) + '" data-control="' + escapeHtml( def.name ) + '" data-responsive="' + ( isResponsive ? '1' : '0' ) + '"' + ( def.condition ? ' data-condition=\'' + JSON.stringify( def.condition ) + '\'' : '' ) + '>';

        html += renderControlHeader( def, device );

        switch ( def.type ) {
            case 'text':
                html += renderText( def, deviceVal, inheritedVal, device );
                break;
            case 'textarea':
                html += renderTextarea( def, deviceVal, inheritedVal, device );
                break;
            case 'number':
                html += renderNumber( def, deviceVal, inheritedVal, device );
                break;
            case 'select':
                html += renderSelect( def, deviceVal, inheritedVal, device );
                break;
            case 'switcher':
                html += renderSwitcher( def, deviceVal, inheritedVal, device );
                break;
            case 'choose':
                html += renderChoose( def, deviceVal, inheritedVal, device, settings, element );
                break;
            case 'slider':
                html += renderSlider( def, deviceVal, inheritedVal, device );
                break;
            case 'dimension': // alias used by some widgets (nav menu, site logo)
            case 'dimensions':
                html += renderDimensions( def, deviceVal, inheritedVal, device );
                break;
            case 'gaps':
                html += renderGaps( def, deviceVal, inheritedVal, device );
                break;
            case 'url':
                html += renderUrl( def, deviceVal, inheritedVal, device );
                break;
            case 'color':
                html += renderColor( def, deviceVal, inheritedVal, device );
                break;
            case 'typography':
                html += renderTypography( def, deviceVal, inheritedVal, device );
                break;
            case 'media':
                html += renderMedia( def, deviceVal, inheritedVal, device );
                break;
            case 'code':
                html += renderCode( def, deviceVal, inheritedVal, device );
                break;
            case 'popover':
                html += renderPopover( def, deviceVal, inheritedVal, device, settings );
                break;
            case 'subtabs':
                html += renderTabsGroup( def, settings, device, element, activeSubtabs );
                break;
            case 'html':
                html += ( def.raw || '' );
                break;
            case 'repeater':
                html += renderRepeater( def, deviceVal, inheritedVal, device, settings, element );
                break;
            case 'icon':
            case 'icons':
            case 'icon_picker':
                html += renderIconPicker( def, deviceVal, inheritedVal, device );
                break;
            case 'wysiwyg':
                html += renderWysiwyg( def, deviceVal, inheritedVal, device, settings, element );
                break;
            case 'hover_animation':
                html += renderHoverAnimation( def, deviceVal, inheritedVal, device );
                break;
            case 'image_size':
                html += renderImageSize( def, deviceVal, inheritedVal, device, settings );
                break;
            case 'checkbox_list':
                html += renderCheckboxList( def, deviceVal, inheritedVal, device );
                break;
            default:
                html += renderText( def, deviceVal, inheritedVal, device );
                break;
        }

            html += renderDescription( def );
            html += '</div>';
            return html;
        } catch ( err ) {
            var ctrlName = 'unknown';
            try {
                if ( def ) {
                    ctrlName = def.name || def.label || 'unknown';
                }
            } catch ( nErr ) {}
            console.error( 'Loopkit: Failed to render control "' + ctrlName + '":', err );
            return '<div class="lk-control-wrap lk-control-error" data-control="' + escapeHtml( ctrlName ) + '">' +
                   '<div style="padding:6px 10px;font-size:11px;color:#c0392b;background:#fde8e8;border-radius:3px;margin:4px 0;">' +
                   'Failed to render control: <strong>' + escapeHtml( ctrlName ) + '</strong></div></div>';
        }
    };

    /**
     * Render a single accordion section.
     */
    LoopkitControls.renderSection = function( sectionDef, settings, device, element, isOpen, activeSubtabs ) {
        /*
         * A section whose condition no longer matches must still be rendered, hidden.
         *
         * It used to `return ''` here. That removed every control in the section from
         * the DOM, and because the panel collects values by reading its own inputs,
         * those settings were no longer read on save -- so changing an upstream setting
         * so a section stopped matching silently destroyed whatever the user had typed
         * into it, with no warning and no undo. Control-level conditions already get
         * this right: they render with .lk-hidden and keep their inputs. Do the same.
         */
        var sectionHidden = false;
        if ( typeof sectionDef.is_visible === 'function' ) {
            if ( ! sectionDef.is_visible( settings, device, element ) ) {
                sectionHidden = true;
            }
        } else if ( sectionDef.condition ) {
            if ( ! LoopkitControls.evaluateCondition( sectionDef.condition, settings, device ) ) {
                sectionHidden = true;
            }
        }

        var openClass = isOpen ? ' lk-section-open' : '';
        var hiddenClass = sectionHidden ? ' lk-hidden' : '';
        var condAttr = sectionDef.condition ? ' data-condition=\'' + JSON.stringify( sectionDef.condition ) + '\'' : '';
        var html = '<div class="lk-control-section' + openClass + hiddenClass + '" data-section-id="' + escapeHtml( sectionDef.id ) + '"' + condAttr + '>';
        // A real button, not a div with a click handler. The browser gives
        // keyboard focus, Enter and Space for free; a div needs tabindex and
        // a keydown handler to reimplement the same thing, worse. Until this
        // changed, a collapsed section could not be opened without a mouse,
        // and everything inside it was unreachable.
        html += '  <button type="button" class="lk-section-header" aria-expanded="true">';
        html += '    <span class="lk-section-title">' + escapeHtml( sectionDef.title ) + '</span>';
        html += '    <span class="dashicons dashicons-arrow-down-alt2 lk-section-arrow" aria-hidden="true"></span>';
        html += '  </button>';
        html += '  <div class="lk-section-content">';

        if ( sectionDef.controls && sectionDef.controls.length ) {
            for ( var c = 0; c < sectionDef.controls.length; c++ ) {
                try {
                    html += LoopkitControls.render( sectionDef.controls[c], settings, device, element, activeSubtabs );
                } catch ( err ) {
                    var cName = 'index_' + c;
                    try {
                        if ( sectionDef.controls[c] ) {
                            cName = sectionDef.controls[c].name || sectionDef.controls[c].label || cName;
                        }
                    } catch ( nErr ) {}
                    console.error( 'Loopkit: Error rendering control at index ' + c + ' ("' + cName + '") in section "' + ( sectionDef && sectionDef.id ) + '":', err );
                    html += '<div class="lk-control-wrap lk-control-error" data-control="' + escapeHtml( cName ) + '">' +
                            '<div style="padding:6px 10px;font-size:11px;color:#c0392b;background:#fde8e8;border-radius:3px;margin:4px 0;">' +
                            'Failed to render control: <strong>' + escapeHtml( cName ) + '</strong></div></div>';
                }
            }
        }

        html += '  </div>';
        html += '</div>';
        return html;
    };

    /**
     * Render all sections for a given tab. First section open by default, or preserve open sections.
     */
    LoopkitControls.renderTabSections = function( sections, settings, device, element, openSectionIds, activeSubtabs ) {
        LoopkitControls.activeSettings = settings;
        LoopkitControls.activeElement  = element;
        if ( ! sections || ! sections.length ) return '';
        var html = '';
        var hasExplicitOpen = ( openSectionIds && openSectionIds.length > 0 );
        // Remembered open sections only count if one of them exists in this tab. Otherwise
        // (e.g. switching from Content to Style) open the first section, as before.
        if ( hasExplicitOpen ) {
            var anyMatch = false;
            for ( var m = 0; m < sections.length; m++ ) {
                if ( sections[m] && openSectionIds.indexOf( sections[m].id ) !== -1 ) { anyMatch = true; break; }
            }
            if ( ! anyMatch ) hasExplicitOpen = false;
        }
        var firstRendered = false;

        for ( var i = 0; i < sections.length; i++ ) {
            var section = sections[i];
            try {
                var isOpen = false;
                if ( hasExplicitOpen ) {
                    isOpen = ( openSectionIds.indexOf( section.id ) !== -1 );
                } else {
                    isOpen = ! firstRendered;
                }
                var sHtml = LoopkitControls.renderSection( section, settings, device, element, isOpen, activeSubtabs );
                if ( sHtml ) {
                    // A hidden section still renders so its values survive a save. It must
                    // not consume the "open the first section by default" flag though, or
                    // the first section the user can actually see would arrive collapsed.
                    if ( sHtml.indexOf( 'lk-hidden' ) === -1 ) {
                        firstRendered = true;
                    }
                    html += sHtml;
                }
            } catch ( err ) {
                console.error( 'Loopkit: Error rendering section "' + ( section && ( section.id || section.title ) ) + '":', err );
            }
        }

        return html;
    };

    /* =========================================================
       CONTAINER LAYOUT TAB DEFINITION
       ========================================================= */
    LoopkitControls.getContainerLayoutSections = function() {
        return [
            {
                id: 'section_container',
                title: 'Container',
                controls: [
                    {
                        name: 'container_type',
                        label: 'Container Layout',
                        type: 'choose',
                        default: 'flexbox',
                        options: {
                            flexbox: { title: 'Flexbox', icon: 'dashicons-networking' },
                            grid:    { title: 'Grid',    icon: 'dashicons-grid-view' },
                        },
                    },
                    {
                        name: 'content_width',
                        label: 'Content Width',
                        type: 'select',
                        default: 'boxed',
                        options: {
                            boxed: 'Boxed',
                            full:  'Full Width',
                        },
                    },
                    {
                        name: 'width',
                        label: 'Width',
                        type: 'slider',
                        responsive: true,
                        default: 1140,
                        default_unit: 'px',
                        units: [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
                        min: 300,
                        max: 2000,
                    },
                    {
                        name: 'min_height',
                        label: 'Min Height',
                        type: 'slider',
                        responsive: true,
                        units: [ 'px', 'em', 'rem', 'vh', 'custom' ],
                        default_unit: 'px',
                        min: 0,
                        max: 1000,
                        description: 'To achieve full height Container use 100vh.',
                    },
                ],
            },
            {
                id: 'section_items_flex',
                title: 'Items',
                condition: { container_type: 'flexbox' },
                controls: [
                    {
                        name: 'flex_direction',
                        label: 'Direction',
                        type: 'choose',
                        responsive: true,
                        default: 'column',
                        options: {
                            row:              { title: 'Row - horizontal', icon: 'dashicons-arrow-right-alt' },
                            column:           { title: 'Column - vertical', icon: 'dashicons-arrow-down-alt' },
                            'row-reverse':    { title: 'Row - reversed',   icon: 'dashicons-arrow-left-alt' },
                            'column-reverse': { title: 'Column - reversed',icon: 'dashicons-arrow-up-alt' },
                        },
                    },
                    {
                        name: 'justify_content',
                        label: 'Justify Content',
                        type: 'choose',
                        responsive: true,
                        default: '',
                        options: {
                            'flex-start':    { title: 'Start',         icon: 'dashicons-align-left' },
                            center:          { title: 'Center',        icon: 'dashicons-align-center' },
                            'flex-end':      { title: 'End',           icon: 'dashicons-align-right' },
                            'space-between': { title: 'Space Between', icon: 'dashicons-leftright' },
                            'space-around':  { title: 'Space Around',  icon: 'dashicons-image-flip-horizontal' },
                            'space-evenly':  { title: 'Space Evenly',  icon: 'dashicons-ellipsis' },
                        },
                    },
                    {
                        name: 'align_items',
                        label: 'Align Items',
                        type: 'choose',
                        responsive: true,
                        default: '',
                        options: {
                            'flex-start': { title: 'Start',   icon: 'dashicons-align-left' },
                            center:       { title: 'Center',  icon: 'dashicons-align-center' },
                            'flex-end':   { title: 'End',     icon: 'dashicons-align-right' },
                            stretch:      { title: 'Stretch', icon: 'dashicons-fullscreen-alt' },
                        },
                    },
                    {
                        name: 'flex_gap',
                        label: 'Gaps',
                        type: 'gaps',
                        responsive: true,
                        default: { row: '20', column: '20', isLinked: true, unit: 'px' },
                        units: [ 'px', 'em', 'rem', '%', 'custom' ],
                    },
                    {
                        name: 'flex_wrap',
                        label: 'Wrap',
                        type: 'choose',
                        responsive: true,
                        default: 'nowrap',
                        options: {
                            nowrap: { title: 'No Wrap' },
                            wrap:   { title: 'Wrap' },
                        },
                        description: 'Items within the container can be displayed in a single line or broken into multiple lines.',
                    },
                    {
                        name: 'align_content',
                        label: 'Align Content',
                        type: 'choose',
                        responsive: true,
                        condition: { flex_wrap: 'wrap' },
                        options: {
                            'flex-start':    { title: 'Start',         icon: 'dashicons-align-left' },
                            center:          { title: 'Center',        icon: 'dashicons-align-center' },
                            'flex-end':      { title: 'End',           icon: 'dashicons-align-right' },
                            'space-between': { title: 'Space Between', icon: 'dashicons-leftright' },
                            'space-around':  { title: 'Space Around',  icon: 'dashicons-image-flip-horizontal' },
                            'space-evenly':  { title: 'Space Evenly',  icon: 'dashicons-ellipsis' },
                        },
                    },
                ],
            },
            {
                id: 'section_items_grid',
                title: 'Items',
                condition: { container_type: 'grid' },
                controls: [
                    {
                        name: 'grid_columns',
                        label: 'Columns',
                        type: 'number',
                        responsive: true,
                        default: 3,
                        min: 1,
                        max: 12,
                    },
                    {
                        name: 'grid_rows',
                        label: 'Rows',
                        type: 'number',
                        responsive: true,
                        default: 2,
                        min: 1,
                        max: 12,
                    },
                    {
                        name: 'grid_gaps',
                        label: 'Gaps',
                        type: 'gaps',
                        responsive: true,
                        default: { row: '20', column: '20', isLinked: true, unit: 'px' },
                        units: [ 'px', 'em', 'rem', '%', 'custom' ],
                    },
                    {
                        name: 'grid_auto_flow',
                        label: 'Auto Flow',
                        type: 'choose',
                        responsive: true,
                        default: 'row',
                        options: {
                            row:    { title: 'Row' },
                            column: { title: 'Column' },
                        },
                    },
                    {
                        name: 'justify_items',
                        label: 'Justify Items',
                        type: 'choose',
                        responsive: true,
                        options: {
                            'start':   { title: 'Start' },
                            'center':  { title: 'Center' },
                            'end':     { title: 'End' },
                            'stretch': { title: 'Stretch' },
                        },
                    },
                    {
                        name: 'align_items_grid',
                        label: 'Align Items',
                        type: 'choose',
                        responsive: true,
                        options: {
                            'start':   { title: 'Start' },
                            'center':  { title: 'Center' },
                            'end':     { title: 'End' },
                            'stretch': { title: 'Stretch' },
                        },
                    },
                ],
            },
            {
                id: 'section_additional_options',
                title: 'Additional Options',
                controls: [
                    {
                        name: 'overflow',
                        label: 'Overflow',
                        type: 'select',
                        default: 'default',
                        options: {
                            default: 'Default',
                            hidden:  'Hidden',
                            auto:    'Auto',
                        },
                    },
                    {
                        name: 'html_tag',
                        label: 'HTML Tag',
                        type: 'select',
                        default: 'div',
                        options: {
                            div:     'div',
                            header:  'header',
                            footer:  'footer',
                            main:    'main',
                            article: 'article',
                            section: 'section',
                            aside:   'aside',
                            nav:     'nav',
                            a:       'a (link)',
                        },
                    },
                    {
                        name: 'link',
                        label: 'Link',
                        type: 'url',
                        condition: { html_tag: 'a' },
                    },
                ],
            },
        ];
    };

    /* =========================================================
       STYLE SECTION CONTROL BUILDERS (Part B)
       ========================================================= */

    /**
     * Background Group: Classic & Gradient
     */
    function getBackgroundGroupControls( prefix, label, responsive, isHover ) {
        prefix = prefix || '';
        label = label ? label + ' ' : '';

        function makeKey( base ) {
            if ( prefix === 'background_overlay_' ) {
                var suffix = base.replace( /^background_/, '' );
                return isHover ? ( 'background_overlay_' + suffix + '_hover' ) : ( 'background_overlay_' + suffix );
            }
            return isHover ? ( prefix + base + '_hover' ) : ( prefix + base );
        }

        var typeKey = makeKey( 'background_type' );
        var colorKey = makeKey( 'background_color' );
        var imgKey = makeKey( 'background_image' );
        var posKey = makeKey( 'background_position' );
        var xPosKey = makeKey( 'background_x_position' );
        var yPosKey = makeKey( 'background_y_position' );
        var attachKey = makeKey( 'background_attachment' );
        var repeatKey = makeKey( 'background_repeat' );
        var sizeKey = makeKey( 'background_size' );
        var cSizeKey = makeKey( 'background_custom_size' );

        var gColorKey = makeKey( 'background_gradient_color' );
        var gLocKey = makeKey( 'background_gradient_location' );
        var gSecColorKey = makeKey( 'background_gradient_second_color' );
        var gSecLocKey = makeKey( 'background_gradient_second_location' );
        var gTypeKey = makeKey( 'background_gradient_type' );
        var gAngleKey = makeKey( 'background_gradient_angle' );
        var gPosKey = makeKey( 'background_gradient_position' );

        var condImg = prefix === 'background_overlay_' ? ( isHover ? '_has_overlay_image_hover' : '_has_overlay_image' ) : ( isHover ? '_has_image_hover' : '_has_image' );

        var controls = [
            {
                name: typeKey,
                label: label + 'Background Type',
                type: 'choose',
                default: 'classic',
                options: {
                    classic:  { title: 'Classic', icon: 'dashicons-admin-customizer' },
                    gradient: { title: 'Gradient', icon: 'dashicons-image-filter' },
                },
            },
            // Classic controls
            {
                name: colorKey,
                label: 'Color',
                type: 'color',
                condition: (function() { var c = {}; c[ typeKey ] = 'classic'; return c; })(),
            },
            {
                name: imgKey,
                label: 'Image',
                type: 'media',
                responsive: !! responsive,
                condition: (function() { var c = {}; c[ typeKey ] = 'classic'; return c; })(),
            },
            {
                name: posKey,
                label: 'Position',
                type: 'select',
                responsive: !! responsive,
                default: '',
                options: {
                    '': 'Default',
                    'center center': 'Center Center',
                    'center left': 'Center Left',
                    'center right': 'Center Right',
                    'top center': 'Top Center',
                    'top left': 'Top Left',
                    'top right': 'Top Right',
                    'bottom center': 'Bottom Center',
                    'bottom left': 'Bottom Left',
                    'bottom right': 'Bottom Right',
                    'custom': 'Custom',
                },
                condition: (function() { var c = {}; c[ typeKey ] = 'classic'; c[ condImg ] = true; return c; })(),
            },
            {
                name: xPosKey,
                label: 'X Position',
                type: 'slider',
                responsive: !! responsive,
                units: [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
                default_unit: 'px',
                min: -1000,
                max: 1000,
                step: 1,
                condition: (function() { var c = {}; c[ typeKey ] = 'classic'; c[ condImg ] = true; c[ posKey ] = 'custom'; return c; })(),
            },
            {
                name: yPosKey,
                label: 'Y Position',
                type: 'slider',
                responsive: !! responsive,
                units: [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
                default_unit: 'px',
                min: -1000,
                max: 1000,
                step: 1,
                condition: (function() { var c = {}; c[ typeKey ] = 'classic'; c[ condImg ] = true; c[ posKey ] = 'custom'; return c; })(),
            },
            {
                name: attachKey,
                label: 'Attachment',
                type: 'select',
                responsive: false,
                default: '',
                options: {
                    '': 'Default',
                    'scroll': 'Scroll',
                    'fixed': 'Fixed',
                },
                condition: (function() { var c = {}; c[ typeKey ] = 'classic'; c[ condImg ] = true; return c; })(),
            },
            {
                name: repeatKey,
                label: 'Repeat',
                type: 'select',
                responsive: !! responsive,
                default: '',
                options: {
                    '': 'Default',
                    'no-repeat': 'No-repeat',
                    'repeat': 'Repeat',
                    'repeat-x': 'Repeat-x',
                    'repeat-y': 'Repeat-y',
                },
                condition: (function() { var c = {}; c[ typeKey ] = 'classic'; c[ condImg ] = true; return c; })(),
            },
            {
                name: sizeKey,
                label: 'Display Size',
                type: 'select',
                responsive: !! responsive,
                default: '',
                options: {
                    '': 'Default',
                    'auto': 'Auto',
                    'cover': 'Cover',
                    'contain': 'Contain',
                    'custom': 'Custom',
                },
                condition: (function() { var c = {}; c[ typeKey ] = 'classic'; c[ condImg ] = true; return c; })(),
            },
            {
                name: cSizeKey,
                label: 'Width',
                type: 'slider',
                responsive: !! responsive,
                units: [ 'px', 'em', '%', 'vw', 'custom' ],
                default_unit: 'px',
                min: 0,
                max: 1600,
                step: 1,
                condition: (function() { var c = {}; c[ typeKey ] = 'classic'; c[ condImg ] = true; c[ sizeKey ] = 'custom'; return c; })(),
            },

            // Gradient controls
            {
                name: gColorKey,
                label: 'Color',
                type: 'color',
                default: '#38A8F5',
                condition: (function() { var c = {}; c[ typeKey ] = 'gradient'; return c; })(),
            },
            {
                name: gLocKey,
                label: 'Location',
                type: 'slider',
                default: 0,
                min: 0,
                max: 100,
                units: [ '%' ],
                condition: (function() { var c = {}; c[ typeKey ] = 'gradient'; return c; })(),
            },
            {
                name: gSecColorKey,
                label: 'Second Color',
                type: 'color',
                default: '#9B51E0',
                condition: (function() { var c = {}; c[ typeKey ] = 'gradient'; return c; })(),
            },
            {
                name: gSecLocKey,
                label: 'Location',
                type: 'slider',
                default: 100,
                min: 0,
                max: 100,
                units: [ '%' ],
                condition: (function() { var c = {}; c[ typeKey ] = 'gradient'; return c; })(),
            },
            {
                name: gTypeKey,
                label: 'Type',
                type: 'select',
                default: 'linear',
                options: {
                    linear: 'Linear',
                    radial: 'Radial',
                },
                condition: (function() { var c = {}; c[ typeKey ] = 'gradient'; return c; })(),
            },
            {
                name: gAngleKey,
                label: 'Angle',
                type: 'slider',
                default: 180,
                min: 0,
                max: 360,
                units: [ 'deg' ],
                condition: (function() { var c = {}; c[ typeKey ] = 'gradient'; c[ gTypeKey ] = 'linear'; return c; })(),
            },
            {
                name: gPosKey,
                label: 'Position',
                type: 'select',
                default: 'center center',
                options: {
                    'center center': 'Center Center',
                    'center left':   'Center Left',
                    'center right':  'Center Right',
                    'top center':    'Top Center',
                    'top left':      'Top Left',
                    'top right':     'Top Right',
                    'bottom center': 'Bottom Center',
                    'bottom left':   'Bottom Left',
                    'bottom right':  'Bottom Right',
                },
                condition: (function() { var c = {}; c[ typeKey ] = 'gradient'; c[ gTypeKey ] = 'radial'; return c; })(),
            },
        ];
        return controls;
    }

    /**
     * Border Group: Type, Width, Color
     */
    function getBorderGroupControls( prefix, responsive, isHover ) {
        prefix = prefix || '';
        var typeKey = prefix + ( isHover ? 'border_type_hover' : 'border_type' );
        var widthKey = prefix + ( isHover ? 'border_width_hover' : 'border_width' );
        var colorKey = prefix + ( isHover ? 'border_color_hover' : 'border_color' );
        var condBorder = isHover ? '_has_border_hover' : '_has_border';

        var baseControls = [
            {
                name: typeKey,
                label: 'Border Type',
                type: 'select',
                default: '',
                options: {
                    '':       'Default',
                    'none':   'None',
                    'solid':  'Solid',
                    'double': 'Double',
                    'dotted': 'Dotted',
                    'dashed': 'Dashed',
                    'groove': 'Groove',
                },
            },
            {
                name: widthKey,
                label: 'Width',
                type: 'dimensions',
                responsive: !! responsive,
                condition: (function() { var c = {}; c[ condBorder ] = true; return c; })(),
            },
            {
                name: colorKey,
                label: 'Color',
                type: 'color',
                condition: (function() { var c = {}; c[ condBorder ] = true; return c; })(),
            },
        ];

        // Per-side border colour. Width has always been a top/right/bottom/left
        // box; colour had only ever been one value for all four sides, so a
        // hairline under a header meant drawing the same hairline all the way
        // round. These override the single colour for one side each.
        if ( ! isHover ) {
            [ 'top', 'right', 'bottom', 'left' ].forEach( function( side ) {
                baseControls.push( {
                    name: 'border_color_' + side,
                    label: side.charAt( 0 ).toUpperCase() + side.slice( 1 ) + ' Color',
                    type: 'color',
                    condition: (function() { var c = {}; c[ condBorder ] = true; return c; })(),
                } );
            } );
        }

        return baseControls;
    }

    /**
     * Shape Divider Controls for Top / Bottom
     */
    function getShapeDividerControls( position ) {
        var pos = position || 'top';
        var prefix = 'shape_divider_' + pos + '_';
        var condShape = pos === 'top' ? '_has_shape_top' : '_has_shape_bottom';

        return [
            {
                name: prefix + 'type',
                label: 'Type',
                type: 'select',
                default: '',
                options: {
                    '': 'None',
                    mountains: 'Mountains',
                    drops: 'Drops',
                    clouds: 'Clouds',
                    zigzag: 'Zigzag',
                    pyramids: 'Pyramids',
                    triangle: 'Triangle',
                    triangle_asymmetrical: 'Triangle Asymmetrical',
                    tilt: 'Tilt',
                    tilt_opacity: 'Tilt Opacity',
                    fan_opacity: 'Fan Opacity',
                    curve: 'Curve',
                    curve_asymmetrical: 'Curve Asymmetrical',
                    waves: 'Waves',
                    waves_brush: 'Waves Brush',
                    waves_pattern: 'Waves Pattern',
                    arrow: 'Arrow',
                    split: 'Split',
                    book: 'Book',
                },
            },
            {
                name: prefix + 'color',
                label: 'Color',
                type: 'color',
                default: '#FFFFFF',
                condition: (function() { var c = {}; c[ condShape ] = true; return c; })(),
            },
            {
                name: prefix + 'width',
                label: 'Width',
                type: 'slider',
                responsive: true,
                default: 100,
                min: 100,
                max: 300,
                units: [ '%' ],
                condition: (function() { var c = {}; c[ condShape ] = true; return c; })(),
            },
            {
                name: prefix + 'height',
                label: 'Height',
                type: 'slider',
                responsive: true,
                default: 50,
                min: 0,
                max: 500,
                units: [ 'px' ],
                condition: (function() { var c = {}; c[ condShape ] = true; return c; })(),
            },
            {
                name: prefix + 'flip',
                label: 'Flip',
                type: 'switcher',
                condition: (function() { var c = {}; c[ condShape ] = true; return c; })(),
            },
            {
                name: prefix + 'invert',
                label: 'Invert',
                type: 'switcher',
                condition: (function() { var c = {}; c[ condShape ] = true; return c; })(),
            },
            {
                name: prefix + 'front',
                label: 'Bring to Front',
                type: 'switcher',
                condition: (function() { var c = {}; c[ condShape ] = true; return c; })(),
            },
        ];
    }

    /**
     * Style tab sections for Section and Container (Part B - Exact Order):
     * 1. Background (Normal / Hover)
     * 2. Background Overlay (Normal / Hover)
     * 3. Border (Normal / Hover)
     * 4. Shape Divider (Top / Bottom)
     */
    LoopkitControls.getContainerStyleSections = function() {
        return [
            // 1. Background
            {
                id: 'section_background',
                title: 'Background',
                controls: [
                    {
                        type: 'subtabs',
                        name: 'bg_subtabs',
                        subtabs: [
                            {
                                id: 'normal',
                                label: 'Normal',
                                controls: getBackgroundGroupControls( '', '', true, false ),
                            },
                            {
                                id: 'hover',
                                label: 'Hover',
                                controls: getBackgroundGroupControls( '', '', true, true ).concat([
                                    {
                                        name: 'background_hover_transition_duration',
                                        label: 'Transition Duration (s)',
                                        type: 'slider',
                                        unitless: true,
                                        units: [],
                                        min: 0,
                                        max: 5,
                                        step: 0.1,
                                        default: 0.3,
                                    }
                                ]),
                            }
                        ]
                    }
                ]
            },

            // 2. Background Overlay
            {
                id: 'section_background_overlay',
                title: 'Background Overlay',
                controls: [
                    {
                        type: 'subtabs',
                        name: 'overlay_subtabs',
                        subtabs: [
                            {
                                id: 'normal',
                                label: 'Normal',
                                controls: getBackgroundGroupControls( 'background_overlay_', '', true, false ).concat([
                                    {
                                        name: 'background_overlay_opacity',
                                        label: 'Opacity',
                                        type: 'slider',
                                        responsive: true,
                                        unitless: true,
                                        units: [],
                                        min: 0,
                                        max: 1,
                                        step: 0.01,
                                        default: 0.5,
                                    },
                                    {
                                        name: 'background_overlay_css_filters',
                                        label: 'CSS Filters',
                                        type: 'popover',
                                        popoverType: 'css_filters',
                                    },
                                    {
                                        name: 'background_overlay_blend_mode',
                                        label: 'Blend Mode',
                                        type: 'select',
                                        default: 'normal',
                                        options: {
                                            normal:        'Normal',
                                            multiply:      'Multiply',
                                            screen:        'Screen',
                                            overlay:       'Overlay',
                                            darken:        'Darken',
                                            lighten:       'Lighten',
                                            'color-dodge': 'Color Dodge',
                                            saturation:    'Saturation',
                                            color:         'Color',
                                            luminosity:    'Luminosity',
                                            difference:    'Difference',
                                            exclusion:     'Exclusion',
                                            hue:           'Hue',
                                        },
                                    }
                                ]),
                            },
                            {
                                id: 'hover',
                                label: 'Hover',
                                controls: getBackgroundGroupControls( 'background_overlay_', '', true, true ).concat([
                                    {
                                        name: 'background_overlay_opacity_hover',
                                        label: 'Opacity',
                                        type: 'slider',
                                        responsive: true,
                                        unitless: true,
                                        units: [],
                                        min: 0,
                                        max: 1,
                                        step: 0.01,
                                        default: 0.5,
                                    },
                                    {
                                        name: 'background_overlay_css_filters_hover',
                                        label: 'CSS Filters',
                                        type: 'popover',
                                        popoverType: 'css_filters',
                                    },
                                    {
                                        name: 'background_overlay_hover_transition_duration',
                                        label: 'Transition Duration (s)',
                                        type: 'slider',
                                        unitless: true,
                                        units: [],
                                        min: 0,
                                        max: 5,
                                        step: 0.1,
                                        default: 0.3,
                                    }
                                ]),
                            }
                        ]
                    }
                ]
            },

            // 3. Border
            {
                id: 'section_border',
                title: 'Border',
                controls: [
                    {
                        type: 'subtabs',
                        name: 'border_subtabs',
                        subtabs: [
                            {
                                id: 'normal',
                                label: 'Normal',
                                controls: getBorderGroupControls( '', true, false ).concat([
                                    {
                                        name: 'border_radius',
                                        label: 'Border Radius',
                                        type: 'dimensions',
                                        responsive: true,
                                        units: [ 'px', '%', 'em', 'rem', 'custom' ],
                                    },
                                    {
                                        name: 'box_shadow',
                                        label: 'Box Shadow',
                                        type: 'popover',
                                        popoverType: 'box_shadow',
                                    }
                                ]),
                            },
                            {
                                id: 'hover',
                                label: 'Hover',
                                controls: getBorderGroupControls( '', true, true ).concat([
                                    {
                                        name: 'border_radius_hover',
                                        label: 'Border Radius',
                                        type: 'dimensions',
                                        responsive: true,
                                        units: [ 'px', '%', 'em', 'rem', 'custom' ],
                                    },
                                    {
                                        name: 'box_shadow_hover',
                                        label: 'Box Shadow',
                                        type: 'popover',
                                        popoverType: 'box_shadow',
                                    },
                                    {
                                        name: 'border_hover_transition_duration',
                                        label: 'Transition Duration (s)',
                                        type: 'slider',
                                        unitless: true,
                                        units: [],
                                        min: 0,
                                        max: 5,
                                        step: 0.1,
                                        default: 0.3,
                                    }
                                ]),
                            }
                        ]
                    }
                ]
            },

            // 4. Shape Divider
            {
                id: 'section_shape_divider',
                title: 'Shape Divider',
                controls: [
                    {
                        type: 'subtabs',
                        name: 'shape_divider_subtabs',
                        subtabs: [
                            {
                                id: 'top',
                                label: 'Top',
                                controls: getShapeDividerControls( 'top' ),
                            },
                            {
                                id: 'bottom',
                                label: 'Bottom',
                                controls: getShapeDividerControls( 'bottom' ),
                            }
                        ]
                    }
                ]
            }
        ];
    };

    /**
     * Transform Group Controls (Normal & Hover)
     */
    function getTransformGroupControls( isHover ) {
        var sfx = isHover ? '_hover' : '';
        var cond3D = isHover ? { _has_rotate_3d_hover: true } : { _has_rotate_3d: true };

        var controls = [
            {
                name: 'transform_rotate' + sfx,
                label: 'Rotate',
                type: 'slider',
                responsive: true,
                units: [ 'deg' ],
                min: -360,
                max: 360,
            },
            {
                name: 'transform_rotate_x' + sfx,
                label: 'Rotate X',
                type: 'slider',
                responsive: true,
                units: [ 'deg' ],
                min: -180,
                max: 180,
                default: 0,
            },
            {
                name: 'transform_rotate_y' + sfx,
                label: 'Rotate Y',
                type: 'slider',
                responsive: true,
                units: [ 'deg' ],
                min: -180,
                max: 180,
                default: 0,
            },
            {
                name: 'transform_perspective' + sfx,
                label: 'Perspective',
                type: 'slider',
                responsive: true,
                units: [ 'px' ],
                min: 0,
                max: 2000,
                default: 1000,
                condition: cond3D,
            },
            {
                name: 'transform_offset_x' + sfx,
                label: 'Offset X',
                type: 'slider',
                responsive: true,
                units: [ 'px', '%', 'vw', 'custom' ],
            },
            {
                name: 'transform_offset_y' + sfx,
                label: 'Offset Y',
                type: 'slider',
                responsive: true,
                units: [ 'px', '%', 'vh', 'custom' ],
            },
            {
                name: 'transform_scale' + sfx,
                label: 'Scale',
                type: 'slider',
                responsive: true,
                unitless: true,
                units: [],
                min: 0,
                max: 2,
                step: 0.1,
            },
            {
                name: 'transform_skew_x' + sfx,
                label: 'Skew X',
                type: 'slider',
                responsive: true,
                units: [ 'deg' ],
            },
            {
                name: 'transform_skew_y' + sfx,
                label: 'Skew Y',
                type: 'slider',
                responsive: true,
                units: [ 'deg' ],
            },
            {
                name: 'transform_flip_x' + sfx,
                label: 'Flip Horizontal',
                type: 'switcher',
            },
            {
                name: 'transform_flip_y' + sfx,
                label: 'Flip Vertical',
                type: 'switcher',
            },
            {
                name: 'transform_anchor_x' + sfx,
                label: 'Anchor Point X',
                type: 'slider',
                responsive: true,
                units: [ '%' ],
                min: 0,
                max: 100,
                default: 50,
            },
            {
                name: 'transform_anchor_y' + sfx,
                label: 'Anchor Point Y',
                type: 'slider',
                responsive: true,
                units: [ '%' ],
                min: 0,
                max: 100,
                default: 50,
            },
        ];

        if ( isHover ) {
            controls.push({
                name: 'transform_transition_duration',
                label: 'Transition Duration (s)',
                type: 'slider',
                responsive: true,
                unitless: true,
                units: [],
                min: 0,
                max: 10,
                step: 0.1,
                default: 0.3,
            });
        }

        return controls;
    }

    /* =========================================================
       SHARED ADVANCED TAB DEFINITIONS (Section 11 & Problem 4)
       Container sections: Layout, Motion Effects, Transform, Responsive, Attributes, Custom CSS.
       Heading sections: Layout, Motion Effects, Transform, Background, Border, Mask, Responsive, Attributes, Custom CSS.
       ========================================================= */
    LoopkitControls.getSharedAdvancedSections = function( elType ) {
        var isWidget = ( elType === 'widget' );

        // Layout controls
        var layoutControls = [
            {
                name: 'margin',
                label: 'Margin',
                type: 'dimensions',
                responsive: true,
            },
            {
                name: 'padding',
                label: 'Padding',
                type: 'dimensions',
                responsive: true,
            },
        ];

        if ( isWidget ) {
            layoutControls.push({
                name: 'widget_width',
                label: 'Width',
                type: 'select',
                responsive: true,
                default: 'default',
                options: {
                    default: 'Default',
                    full:    'Full Width (100%)',
                    inline:  'Inline (auto)',
                    custom:  'Custom',
                },
            });
            layoutControls.push({
                name: 'custom_width',
                label: 'Custom Width',
                type: 'slider',
                responsive: true,
                default: 100,
                default_unit: '%',
                units: [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
                condition: { widget_width: 'custom' },
            });
        }

        layoutControls.push(
            {
                name: 'align_self',
                label: 'Align Self',
                type: 'choose',
                responsive: true,
                options: {
                    start:   { title: 'Start',   icon: 'dashicons-align-left' },
                    center:  { title: 'Center',  icon: 'dashicons-align-center' },
                    end:     { title: 'End',     icon: 'dashicons-align-right' },
                    stretch: { title: 'Stretch', icon: 'dashicons-menu' },
                },
            },
            {
                name: 'order',
                label: 'Order',
                type: 'choose',
                responsive: true,
                description: 'This changes visual order only, not HTML order.',
                options: {
                    start:  { title: 'Start' },
                    end:    { title: 'End' },
                    custom: { title: 'Custom' },
                },
            },
            {
                name: 'order_custom',
                label: 'Custom Order',
                type: 'number',
                responsive: true,
                condition: { order: 'custom' },
            },
            {
                name: 'size',
                label: 'Size',
                type: 'choose',
                responsive: true,
                options: {
                    none:   { title: 'None' },
                    grow:   { title: 'Grow' },
                    shrink: { title: 'Shrink' },
                    custom: { title: 'Custom' },
                },
            },
            {
                name: 'flex_grow',
                label: 'Flex Grow',
                type: 'number',
                responsive: true,
                condition: { size: 'custom' },
            },
            {
                name: 'flex_shrink',
                label: 'Flex Shrink',
                type: 'number',
                responsive: true,
                condition: { size: 'custom' },
            },
            {
                name: 'position',
                label: 'Position',
                type: 'select',
                default: 'default',
                description: 'Sticky pins the element at its offset as the page scrolls. Absolute/fixed may not look right inside the editor.',
                options: {
                    default:  'Default',
                    sticky:   'Sticky',
                    absolute: 'Absolute',
                    fixed:    'Fixed',
                },
            },
            {
                name: 'offset_orientation_h',
                label: 'Horizontal Orientation',
                type: 'choose',
                responsive: true,
                default: 'left',
                options: {
                    left:  { title: 'Left' },
                    right: { title: 'Right' },
                },
            },
            {
                name: 'offset_x',
                label: 'Offset',
                type: 'slider',
                responsive: true,
                units: [ 'px', '%', 'vw', 'vh', 'custom' ],
            },
            {
                name: 'offset_orientation_v',
                label: 'Vertical Orientation',
                type: 'choose',
                responsive: true,
                default: 'top',
                options: {
                    top:    { title: 'Top' },
                    bottom: { title: 'Bottom' },
                },
            },
            {
                name: 'offset_y',
                label: 'Offset',
                type: 'slider',
                responsive: true,
                units: [ 'px', '%', 'vw', 'vh', 'custom' ],
            },
            {
                name: 'z_index',
                label: 'Z-Index',
                type: 'number',
                responsive: true,
            },
            {
                name: 'css_id',
                label: 'CSS ID',
                type: 'text',
                description: 'Without the #',
            },
            {
                name: 'css_classes',
                label: 'CSS Classes',
                type: 'text',
                description: 'Without the dot, separate with spaces',
            }
        );

        var sections = [
            {
                id: 'section_layout',
                title: 'Layout',
                controls: layoutControls,
            },
            {
                id: 'section_motion_effects',
                title: 'Motion Effects',
                controls: [
                    {
                        name: 'scrolling_effects',
                        label: 'Scrolling Effects',
                        type: 'switcher',
                    },
                    // --- Vertical Scroll ---
                    {
                        name: 'scroll_vertical',
                        label: 'Vertical Scroll',
                        type: 'switcher',
                        condition: {
                            scrolling_effects: 'yes',
                        },
                    },
                    {
                        name: 'scroll_vertical_direction',
                        label: 'Direction',
                        type: 'select',
                        default: 'up',
                        options: {
                            up:   'Up',
                            down: 'Down',
                        },
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_vertical: 'yes',
                        },
                    },
                    {
                        name: 'scroll_vertical_speed',
                        label: 'Speed',
                        type: 'slider',
                        unitless: true,
                        min: 0,
                        max: 10,
                        step: 0.1,
                        default: 4,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_vertical: 'yes',
                        },
                    },
                    {
                        name: 'scroll_vertical_viewport_enter',
                        label: 'Viewport Enter',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 0,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_vertical: 'yes',
                        },
                    },
                    {
                        name: 'scroll_vertical_viewport_exit',
                        label: 'Viewport Exit',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 100,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_vertical: 'yes',
                        },
                    },
                    // --- Horizontal Scroll ---
                    {
                        name: 'scroll_horizontal',
                        label: 'Horizontal Scroll',
                        type: 'switcher',
                        condition: {
                            scrolling_effects: 'yes',
                        },
                    },
                    {
                        name: 'scroll_horizontal_direction',
                        label: 'Direction',
                        type: 'select',
                        default: 'left',
                        options: {
                            left:  'Left',
                            right: 'Right',
                        },
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_horizontal: 'yes',
                        },
                    },
                    {
                        name: 'scroll_horizontal_speed',
                        label: 'Speed',
                        type: 'slider',
                        unitless: true,
                        min: 0,
                        max: 10,
                        step: 0.1,
                        default: 4,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_horizontal: 'yes',
                        },
                    },
                    {
                        name: 'scroll_horizontal_viewport_enter',
                        label: 'Viewport Enter',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 0,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_horizontal: 'yes',
                        },
                    },
                    {
                        name: 'scroll_horizontal_viewport_exit',
                        label: 'Viewport Exit',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 100,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_horizontal: 'yes',
                        },
                    },
                    // --- Transparency ---
                    {
                        name: 'scroll_transparency',
                        label: 'Transparency',
                        type: 'switcher',
                        condition: {
                            scrolling_effects: 'yes',
                        },
                    },
                    {
                        name: 'scroll_transparency_direction',
                        label: 'Direction',
                        type: 'select',
                        default: 'fade_in',
                        options: {
                            fade_in:     'Fade In',
                            fade_out:    'Fade Out',
                            fade_in_out: 'Fade In Out',
                        },
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_transparency: 'yes',
                        },
                    },
                    {
                        name: 'scroll_transparency_level',
                        label: 'Level',
                        type: 'slider',
                        unitless: true,
                        min: 0,
                        max: 10,
                        step: 0.1,
                        default: 5,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_transparency: 'yes',
                        },
                    },
                    {
                        name: 'scroll_transparency_viewport_enter',
                        label: 'Viewport Enter',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 0,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_transparency: 'yes',
                        },
                    },
                    {
                        name: 'scroll_transparency_viewport_exit',
                        label: 'Viewport Exit',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 100,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_transparency: 'yes',
                        },
                    },
                    // --- Blur ---
                    {
                        name: 'scroll_blur',
                        label: 'Blur',
                        type: 'switcher',
                        condition: {
                            scrolling_effects: 'yes',
                        },
                    },
                    {
                        name: 'scroll_blur_direction',
                        label: 'Direction',
                        type: 'select',
                        default: 'blur_in',
                        options: {
                            blur_in:     'Blur In',
                            blur_out:    'Blur Out',
                            blur_in_out: 'Blur In Out',
                        },
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_blur: 'yes',
                        },
                    },
                    {
                        name: 'scroll_blur_level',
                        label: 'Level',
                        type: 'slider',
                        unitless: true,
                        min: 0,
                        max: 10,
                        step: 0.1,
                        default: 5,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_blur: 'yes',
                        },
                    },
                    {
                        name: 'scroll_blur_viewport_enter',
                        label: 'Viewport Enter',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 0,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_blur: 'yes',
                        },
                    },
                    {
                        name: 'scroll_blur_viewport_exit',
                        label: 'Viewport Exit',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 100,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_blur: 'yes',
                        },
                    },
                    // --- Rotate ---
                    {
                        name: 'scroll_rotate',
                        label: 'Rotate',
                        type: 'switcher',
                        condition: {
                            scrolling_effects: 'yes',
                        },
                    },
                    {
                        name: 'scroll_rotate_direction',
                        label: 'Direction',
                        type: 'select',
                        default: 'clockwise',
                        options: {
                            clockwise:         'Clockwise',
                            counter_clockwise: 'Counter-clockwise',
                        },
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_rotate: 'yes',
                        },
                    },
                    {
                        name: 'scroll_rotate_speed',
                        label: 'Speed',
                        type: 'slider',
                        unitless: true,
                        min: 0,
                        max: 10,
                        step: 0.1,
                        default: 4,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_rotate: 'yes',
                        },
                    },
                    {
                        name: 'scroll_rotate_viewport_enter',
                        label: 'Viewport Enter',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 0,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_rotate: 'yes',
                        },
                    },
                    {
                        name: 'scroll_rotate_viewport_exit',
                        label: 'Viewport Exit',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 100,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_rotate: 'yes',
                        },
                    },
                    // --- Scale ---
                    {
                        name: 'scroll_scale',
                        label: 'Scale',
                        type: 'switcher',
                        condition: {
                            scrolling_effects: 'yes',
                        },
                    },
                    {
                        name: 'scroll_scale_direction',
                        label: 'Direction',
                        type: 'select',
                        default: 'scale_up',
                        options: {
                            scale_up:   'Scale Up',
                            scale_down: 'Scale Down',
                        },
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_scale: 'yes',
                        },
                    },
                    {
                        name: 'scroll_scale_speed',
                        label: 'Speed',
                        type: 'slider',
                        unitless: true,
                        min: 0,
                        max: 10,
                        step: 0.1,
                        default: 4,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_scale: 'yes',
                        },
                    },
                    {
                        name: 'scroll_scale_viewport_enter',
                        label: 'Viewport Enter',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 0,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_scale: 'yes',
                        },
                    },
                    {
                        name: 'scroll_scale_viewport_exit',
                        label: 'Viewport Exit',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 100,
                        condition: {
                            scrolling_effects: 'yes',
                            scroll_scale: 'yes',
                        },
                    },
                    // --- Effects Relative To & Devices & Anchor ---
                    {
                        name: 'scroll_relative_to',
                        label: 'Effects Relative To',
                        type: 'select',
                        default: 'default',
                        options: {
                            default:  'Default',
                            viewport: 'Viewport',
                            page:     'Entire Page',
                        },
                        condition: {
                            scrolling_effects: 'yes',
                            _has_any_scroll_effect: true,
                        },
                    },
                    {
                        name: 'scroll_effects_on',
                        label: 'Apply Effects On',
                        type: 'checkbox_list',
                        default: [ 'desktop', 'tablet', 'mobile' ],
                        options: {
                            desktop: 'Desktop',
                            tablet:  'Tablet',
                            mobile:  'Mobile',
                        },
                        condition: {
                            scrolling_effects: 'yes',
                            _has_any_scroll_effect: true,
                        },
                    },
                    {
                        name: 'scroll_anchor_x',
                        label: 'X Anchor Point',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 50,
                        condition: {
                            scrolling_effects: 'yes',
                            _has_any_scroll_effect: true,
                        },
                    },
                    {
                        name: 'scroll_anchor_y',
                        label: 'Y Anchor Point',
                        type: 'slider',
                        units: [ '%' ],
                        min: 0,
                        max: 100,
                        step: 1,
                        default: 50,
                        condition: {
                            scrolling_effects: 'yes',
                            _has_any_scroll_effect: true,
                        },
                    },
                    // ==========================================
                    // PART B: MOUSE EFFECTS
                    // ==========================================
                    {
                        name: 'mouse_effects',
                        label: 'Mouse Effects',
                        type: 'switcher',
                    },
                    {
                        name: 'mouse_track',
                        label: 'Mouse Track',
                        type: 'switcher',
                        condition: {
                            mouse_effects: 'yes',
                        },
                    },
                    {
                        name: 'mouse_track_direction',
                        label: 'Direction',
                        type: 'select',
                        default: 'opposite',
                        options: {
                            opposite: 'Opposite',
                            direct:   'Direct',
                        },
                        condition: {
                            mouse_effects: 'yes',
                            mouse_track: 'yes',
                        },
                    },
                    {
                        name: 'mouse_track_speed',
                        label: 'Speed',
                        type: 'slider',
                        unitless: true,
                        min: 1,
                        max: 10,
                        step: 1,
                        default: 2,
                        condition: {
                            mouse_effects: 'yes',
                            mouse_track: 'yes',
                        },
                    },
                    {
                        name: 'mouse_tilt',
                        label: '3D Tilt',
                        type: 'switcher',
                        condition: {
                            mouse_effects: 'yes',
                        },
                    },
                    {
                        name: 'mouse_tilt_direction',
                        label: 'Direction',
                        type: 'select',
                        default: 'direct',
                        options: {
                            direct:   'Direct',
                            opposite: 'Opposite',
                        },
                        condition: {
                            mouse_effects: 'yes',
                            mouse_tilt: 'yes',
                        },
                    },
                    {
                        name: 'mouse_tilt_speed',
                        label: 'Speed',
                        type: 'slider',
                        unitless: true,
                        min: 1,
                        max: 10,
                        step: 1,
                        default: 2,
                        condition: {
                            mouse_effects: 'yes',
                            mouse_tilt: 'yes',
                        },
                    },
                    {
                        name: 'mouse_effects_on',
                        label: 'Apply Effects On',
                        type: 'checkbox_list',
                        default: [ 'desktop', 'tablet', 'mobile' ],
                        options: {
                            desktop: 'Desktop',
                            tablet:  'Tablet',
                            mobile:  'Mobile',
                        },
                        condition: {
                            mouse_effects: 'yes',
                            _has_any_mouse_effect: true,
                        },
                    },
                    {
                        name: 'sticky',
                        label: 'Sticky',
                        type: 'select',
                        responsive: true,
                        default: 'none',
                        options: {
                            none:   'None',
                            top:    'Top',
                            bottom: 'Bottom',
                        },
                    },
                    {
                        name: 'sticky_on',
                        label: 'Sticky On',
                        type: 'checkbox_list',
                        default: [ 'desktop', 'tablet', 'mobile' ],
                        options: {
                            desktop: 'Desktop',
                            tablet:  'Tablet',
                            mobile:  'Mobile',
                        },
                        condition: {
                            'sticky!': 'none',
                        },
                    },
                    {
                        name: 'sticky_offset',
                        label: 'Offset',
                        type: 'number',
                        responsive: true,
                        default: 0,
                        min: 0,
                        condition: {
                            sticky: [ 'top', 'bottom' ],
                        },
                    },
                    {
                        name: 'sticky_effects_offset',
                        label: 'Effects Offset',
                        type: 'number',
                        responsive: true,
                        default: 0,
                        min: 0,
                        condition: {
                            sticky: [ 'top', 'bottom' ],
                        },
                    },
                    {
                        name: 'stay_in_container',
                        label: 'Stay In Container',
                        type: 'switcher',
                        default: '',
                        condition: {
                            'sticky!': 'none',
                        },
                    },
                    {
                        name: 'entrance_animation',
                        label: 'Entrance Animation',
                        type: 'select',
                        responsive: true,
                        default: 'none',
                        groups: [
                            {
                                label: 'None',
                                value: 'none'
                            },
                            {
                                label: 'Fading',
                                options: {
                                    fadeIn:      'Fade In',
                                    fadeInDown:  'Fade In Down',
                                    fadeInLeft:  'Fade In Left',
                                    fadeInRight: 'Fade In Right',
                                    fadeInUp:    'Fade In Up'
                                }
                            },
                            {
                                label: 'Zooming',
                                options: {
                                    zoomIn:      'Zoom In',
                                    zoomInDown:  'Zoom In Down',
                                    zoomInLeft:  'Zoom In Left',
                                    zoomInRight: 'Zoom In Right',
                                    zoomInUp:    'Zoom In Up'
                                }
                            },
                            {
                                label: 'Bouncing',
                                options: {
                                    bounceIn:      'Bounce In',
                                    bounceInDown:  'Bounce In Down',
                                    bounceInLeft:  'Bounce In Left',
                                    bounceInRight: 'Bounce In Right',
                                    bounceInUp:    'Bounce In Up'
                                }
                            },
                            {
                                label: 'Sliding',
                                options: {
                                    slideInDown:  'Slide In Down',
                                    slideInLeft:  'Slide In Left',
                                    slideInRight: 'Slide In Right',
                                    slideInUp:    'Slide In Up'
                                }
                            },
                            {
                                label: 'Rotating',
                                options: {
                                    rotateIn:          'Rotate In',
                                    rotateInDownLeft:  'Rotate In Down Left',
                                    rotateInDownRight: 'Rotate In Down Right',
                                    rotateInUpLeft:    'Rotate In Up Left',
                                    rotateInUpRight:   'Rotate In Up Right'
                                }
                            },
                            {
                                label: 'Attention Seekers',
                                options: {
                                    bounce:     'Bounce',
                                    flash:      'Flash',
                                    pulse:      'Pulse',
                                    rubberBand: 'Rubber Band',
                                    shake:      'Shake',
                                    headShake:  'Head Shake',
                                    swing:      'Swing',
                                    tada:       'Tada',
                                    wobble:     'Wobble',
                                    jello:      'Jello'
                                }
                            },
                            {
                                label: 'Light Speed In',
                                options: {
                                    lightSpeedIn: 'Light Speed In'
                                }
                            },
                            {
                                label: 'Roll In',
                                options: {
                                    rollIn: 'Roll In'
                                }
                            }
                        ],
                        options: {
                            none:              'None',
                            fadeIn:            'Fade In',
                            fadeInDown:        'Fade In Down',
                            fadeInLeft:        'Fade In Left',
                            fadeInRight:       'Fade In Right',
                            fadeInUp:          'Fade In Up',
                            zoomIn:            'Zoom In',
                            zoomInDown:        'Zoom In Down',
                            zoomInLeft:        'Zoom In Left',
                            zoomInRight:       'Zoom In Right',
                            zoomInUp:          'Zoom In Up',
                            bounceIn:          'Bounce In',
                            bounceInDown:      'Bounce In Down',
                            bounceInLeft:      'Bounce In Left',
                            bounceInRight:     'Bounce In Right',
                            bounceInUp:        'Bounce In Up',
                            slideInDown:       'Slide In Down',
                            slideInLeft:       'Slide In Left',
                            slideInRight:      'Slide In Right',
                            slideInUp:         'Slide In Up',
                            rotateIn:          'Rotate In',
                            rotateInDownLeft:  'Rotate In Down Left',
                            rotateInDownRight: 'Rotate In Down Right',
                            rotateInUpLeft:    'Rotate In Up Left',
                            rotateInUpRight:   'Rotate In Up Right',
                            bounce:            'Bounce',
                            flash:             'Flash',
                            pulse:             'Pulse',
                            rubberBand:        'Rubber Band',
                            shake:             'Shake',
                            headShake:         'Head Shake',
                            swing:             'Swing',
                            tada:              'Tada',
                            wobble:            'Wobble',
                            jello:             'Jello',
                            lightSpeedIn:      'Light Speed In',
                            rollIn:            'Roll In'
                        }
                    },
                    {
                        name: 'animation_duration',
                        label: 'Animation Duration',
                        type: 'select',
                        responsive: true,
                        default: 'normal',
                        options: {
                            slow:   'Slow (2s)',
                            normal: 'Normal (1.25s)',
                            fast:   'Fast (0.75s)'
                        }
                    },
                    {
                        name: 'animation_delay',
                        label: 'Animation Delay (ms)',
                        type: 'number',
                        responsive: true,
                        default: 0,
                        min: 0,
                        step: 50
                    },
                ],
            },
            {
                id: 'section_transform',
                title: 'Transform',
                controls: [
                    {
                        type: 'subtabs',
                        name: 'transform_subtabs',
                        subtabs: [
                            {
                                id: 'normal',
                                label: 'Normal',
                                controls: getTransformGroupControls( false ),
                            },
                            {
                                id: 'hover',
                                label: 'Hover',
                                controls: getTransformGroupControls( true ),
                            }
                        ]
                    }
                ],
            },
        ];

        if ( isWidget ) {
            sections.push({
                id: 'section_background',
                title: 'Background',
                controls: [
                    {
                        type: 'subtabs',
                        name: 'widget_bg_subtabs',
                        subtabs: [
                            {
                                id: 'normal',
                                label: 'Normal',
                                controls: getBackgroundGroupControls( '', '', false, false ),
                            },
                            {
                                id: 'hover',
                                label: 'Hover',
                                controls: getBackgroundGroupControls( '', '', false, true ).concat([
                                    {
                                        name: 'background_hover_transition_duration',
                                        label: 'Transition Duration (s)',
                                        type: 'slider',
                                        unitless: true,
                                        units: [],
                                        min: 0,
                                        max: 5,
                                        step: 0.1,
                                        default: 0.3,
                                    }
                                ]),
                            }
                        ]
                    }
                ]
            });
            sections.push({
                id: 'section_border',
                title: 'Border',
                controls: [
                    {
                        type: 'subtabs',
                        name: 'widget_border_subtabs',
                        subtabs: [
                            {
                                id: 'normal',
                                label: 'Normal',
                                controls: getBorderGroupControls( '', false, false ).concat([
                                    {
                                        name: 'border_radius',
                                        label: 'Border Radius',
                                        type: 'dimensions',
                                        responsive: true,
                                        units: [ 'px', '%', 'em', 'rem', 'custom' ],
                                    },
                                    {
                                        name: 'box_shadow',
                                        label: 'Box Shadow',
                                        type: 'popover',
                                        popoverType: 'box_shadow',
                                    }
                                ]),
                            },
                            {
                                id: 'hover',
                                label: 'Hover',
                                controls: getBorderGroupControls( '', false, true ).concat([
                                    {
                                        name: 'border_radius_hover',
                                        label: 'Border Radius',
                                        type: 'dimensions',
                                        responsive: true,
                                        units: [ 'px', '%', 'em', 'rem', 'custom' ],
                                    },
                                    {
                                        name: 'box_shadow_hover',
                                        label: 'Box Shadow',
                                        type: 'popover',
                                        popoverType: 'box_shadow',
                                    },
                                    {
                                        name: 'border_hover_transition_duration',
                                        label: 'Transition Duration (s)',
                                        type: 'slider',
                                        unitless: true,
                                        units: [],
                                        min: 0,
                                        max: 5,
                                        step: 0.1,
                                        default: 0.3,
                                    }
                                ]),
                            }
                        ]
                    }
                ]
            });
            sections.push({
                id: 'section_mask',
                title: 'Mask',
                controls: [
                    {
                        name: 'mask_switch',
                        label: 'Mask',
                        type: 'switcher',
                    },
                ],
            });
        }

        sections.push(
            {
                id: 'section_responsive',
                title: 'Responsive',
                controls: [
                    {
                        name: 'hide_widescreen',
                        label: 'Hide On Widescreen',
                        type: 'switcher',
                        description: 'Visibility settings apply in preview and on the live page, not while editing.',
                    },
                    {
                        name: 'hide_desktop',
                        label: 'Hide On Desktop',
                        type: 'switcher',
                    },
                    {
                        name: 'hide_laptop',
                        label: 'Hide On Laptop',
                        type: 'switcher',
                    },
                    {
                        name: 'hide_tablet_extra',
                        label: 'Hide On Tablet Landscape',
                        type: 'switcher',
                    },
                    {
                        name: 'hide_tablet',
                        label: 'Hide On Tablet Portrait',
                        type: 'switcher',
                    },
                    {
                        name: 'hide_mobile_extra',
                        label: 'Hide On Mobile Landscape',
                        type: 'switcher',
                    },
                    {
                        name: 'hide_mobile',
                        label: 'Hide On Mobile Portrait',
                        type: 'switcher',
                    },
                ],
            },
            {
                id: 'section_attributes',
                title: 'Attributes',
                controls: [
                    {
                        name: 'custom_attributes',
                        label: 'Custom Attributes',
                        type: 'textarea',
                        rows: 4,
                        placeholder: 'key|value',
                        description: 'One attribute per line, key and value separated by |. Example: key|value',
                    },
                ],
            },
            {
                id: 'section_custom_css',
                title: 'Custom CSS',
                controls: [
                    {
                        name: 'custom_css',
                        label: 'Custom CSS',
                        type: 'code',
                        placeholder: 'selector {\n  color: red;\n}',
                        description: 'Use "selector" to target this element, e.g. selector { color: red; } and selector .child { margin: 10px; }.',
                    },
                ],
            }
        );

        return sections;
    };

    /**
     * Check if a control setting is responsive.
     */
    LoopkitControls.isResponsiveControl = function( name ) {
        var responsiveNames = [
            'width', 'min_height', 'flex_direction', 'justify_content', 'align_items',
            'flex_gap', 'flex_wrap', 'align_content', 'grid_columns', 'grid_rows',
            'grid_gaps', 'grid_auto_flow', 'justify_items', 'align_items_grid',
            'margin', 'padding', 'align', 'typography', 'widget_width', 'custom_width', 'align_self',
            'order', 'order_custom', 'size', 'flex_grow', 'flex_shrink', 'offset_x',
            'offset_y', 'z_index', 'shape_divider_width', 'shape_divider_height',
            'border_width', 'border_radius', 'border_width_hover', 'border_radius_hover',
            'shape_divider_top_width', 'shape_divider_top_height',
            'shape_divider_bottom_width', 'shape_divider_bottom_height',
            'text_stroke',
            'background_overlay_opacity', 'background_overlay_opacity_hover',
            'transform_rotate', 'transform_rotate_hover',
            'transform_rotate_x', 'transform_rotate_x_hover',
            'transform_rotate_y', 'transform_rotate_y_hover',
            'transform_perspective', 'transform_perspective_hover',
            'transform_offset_x', 'transform_offset_x_hover',
            'transform_offset_y', 'transform_offset_y_hover',
            'transform_scale', 'transform_scale_hover',
            'transform_skew_x', 'transform_skew_x_hover',
            'transform_skew_y', 'transform_skew_y_hover',
            'transform_anchor_x', 'transform_anchor_x_hover',
            'transform_anchor_y', 'transform_anchor_y_hover',
            'transform_transition_duration'
        ];
        return responsiveNames.indexOf( name ) !== -1;
    };

    /* =========================================================
       EVENT BINDINGS
       ========================================================= */
    LoopkitControls.bindEvents = function( $panel, onControlChange, onDeviceSwitch ) {
        // Accordion toggle: opening one closes others
        $panel.off( 'click', '.lk-section-header' ).on( 'click', '.lk-section-header', function( e ) {
            e.stopPropagation();
            var $sec = $( this ).closest( '.lk-control-section' );
            var wasOpen = $sec.hasClass( 'lk-section-open' );
            $panel.find( '.lk-control-section' ).removeClass( 'lk-section-open' );
            if ( ! wasOpen ) {
                $sec.addClass( 'lk-section-open' );
            }
            // Say so. An aria-expanded that never changes is worse than none,
            // because it states something untrue rather than nothing.
            $panel.find( '.lk-section-header' ).attr( 'aria-expanded', 'false' );
            if ( ! wasOpen ) {
                $sec.find( '.lk-section-header' ).attr( 'aria-expanded', 'true' );
            }
        });

        // Responsive device button: toggle dropdown
        $panel.off( 'click', '.lk-res-btn' ).on( 'click', '.lk-res-btn', function( e ) {
            e.stopPropagation();
            var $dropdown = $( this ).siblings( '.lk-res-dropdown' );
            var isVisible = $dropdown.is( ':visible' );
            $( '.lk-res-dropdown' ).hide();
            if ( ! isVisible ) {
                $dropdown.show();
            }
        });

        // Responsive device dropdown item click: switch device
        $panel.off( 'click', '.lk-res-dropdown-item' ).on( 'click', '.lk-res-dropdown-item', function( e ) {
            e.stopPropagation();
            var newDevice = $( this ).data( 'device' );
            $( this ).closest( '.lk-res-dropdown' ).hide();
            if ( onDeviceSwitch ) onDeviceSwitch( newDevice );
        });

        // Helper to check if field is responsive
        function isControlResponsive( $el ) {
            var $wrap = $el.closest( '.lk-control-wrap' );
            if ( $wrap.length && $wrap.data( 'responsive' ) !== undefined ) {
                return $wrap.data( 'responsive' ) == 1;
            }
            var name = $el.data( 'name' ) || $wrap.data( 'control' );
            return LoopkitControls.isResponsiveControl( name );
        }

        // Text, Textarea, Number inputs
        /**
         * A control inside a repeater row belongs to that row, not to the widget: its value
         * has to go back through the repeater, or the row keeps its old value and the name
         * leaks out as a setting of its own.
         *
         * @param {jQuery} $el The control that changed.
         * @return {boolean} Whether the change was handled as a repeater row.
         */
        function handledByRepeater( $el ) {
            var $row = $el.closest( '.lk-repeater-row' );
            if ( ! $row.length ) return false;
            collectAndTriggerRepeater( $row.closest( '.lk-field-repeater' ) );
            return true;
        }

        $panel.off( 'input', '.lk-input-text, .lk-input-textarea, .lk-input-number' )
              .on( 'input', '.lk-input-text, .lk-input-textarea, .lk-input-number', function() {
            if ( handledByRepeater( $( this ) ) ) return;
            var name = $( this ).data( 'name' );
            if ( ! name ) return;
            var val = $( this ).val();
            var resp = isControlResponsive( $( this ) );
            if ( onControlChange ) onControlChange( name, val, resp, true );
        });

        $panel.off( 'change', '.lk-input-text, .lk-input-textarea, .lk-input-number' )
              .on( 'change', '.lk-input-text, .lk-input-textarea, .lk-input-number', function() {
            if ( handledByRepeater( $( this ) ) ) return;
            var name = $( this ).data( 'name' );
            if ( ! name ) return;
            var val = $( this ).val();
            var resp = isControlResponsive( $( this ) );
            if ( onControlChange ) onControlChange( name, val, resp, false );
        });

        // Select inputs
        $panel.off( 'change', '.lk-input-select' ).on( 'change', '.lk-input-select', function() {
            if ( handledByRepeater( $( this ) ) ) return;
            var name = $( this ).data( 'name' );
            if ( ! name ) return;
            var val = $( this ).val();
            var resp = isControlResponsive( $( this ) );
            if ( onControlChange ) onControlChange( name, val, resp, false );
        });

        // Switcher checkbox
        $panel.off( 'change', '.lk-input-switcher' ).on( 'change', '.lk-input-switcher', function() {
            if ( handledByRepeater( $( this ) ) ) return;
            var name = $( this ).data( 'name' );
            if ( ! name ) return;
            var val = $( this ).is( ':checked' ) ? 'yes' : 'no';
            var resp = isControlResponsive( $( this ) );
            if ( onControlChange ) onControlChange( name, val, resp, false );
        });

        // Checkbox list (multi-checkbox) change
        $panel.off( 'change', '.lk-input-checkbox-list' ).on( 'change', '.lk-input-checkbox-list', function() {
            var name = $( this ).data( 'name' );
            if ( ! name ) return;
            var $field = $( this ).closest( '.lk-field-checkbox-list' );
            var checkedVals = [];
            $field.find( '.lk-input-checkbox-list:checked' ).each( function() {
                checkedVals.push( $( this ).val() );
            });
            var resp = isControlResponsive( $field );
            if ( onControlChange ) onControlChange( name, checkedVals, resp, false );
        });

        // Choose button click (Section 8: Clicking active button again clears the value, except where one type is required)
        $panel.off( 'click', '.lk-choose-btn' ).on( 'click', '.lk-choose-btn', function( e ) {
            e.stopPropagation();
            var $btn = $( this );
            var $group = $btn.closest( '.lk-choose-group' );
            var name = $group.data( 'name' );
            var val = $btn.data( 'value' );
            var wasActive = $btn.hasClass( 'lk-choose-active' );
            var resp = isControlResponsive( $group );

            // Certain choose controls cannot be deselected to empty (one type is always active)
            var isNoClear = ( name === 'background_type' || name === 'background_type_hover' ||
                              name === 'background_overlay_type' || name === 'background_overlay_type_hover' ||
                              name === 'container_type' );

            if ( wasActive && isNoClear ) {
                return; // Keep active, never clear
            }

            $group.find( '.lk-choose-btn' ).removeClass( 'lk-choose-active lk-choose-inherited' );

            if ( wasActive ) {
                // Clear value
                if ( onControlChange ) onControlChange( name, '', resp, false );
            } else {
                $btn.addClass( 'lk-choose-active' );
                if ( onControlChange ) onControlChange( name, val, resp, false );
            }
        });

        // Slider range: input fires continuously mid-drag (isInput = true); change fires on mouseup/release (isInput = false)
        $panel.off( 'input', '.lk-slider-range' ).on( 'input', '.lk-slider-range', function() {
            var $wrap = $( this ).closest( '.lk-field-slider' );
            var name = $wrap.data( 'name' );
            var val = $( this ).val();
            $wrap.find( '.lk-slider-number' ).val( val );
            var isUnitless = $wrap.data( 'unitless' ) === true || $wrap.data( 'unitless' ) === 'true';
            var resp = isControlResponsive( $wrap );
            if ( isUnitless ) {
                var numVal = ( val !== '' && ! isNaN( parseFloat( val ) ) ) ? parseFloat( val ) : val;
                if ( onControlChange ) onControlChange( name, numVal, resp, true );
            } else {
                var activeUnit = $wrap.find( '.lk-unit-active' ).data( 'unit' ) || $wrap.data( 'unit' ) || 'px';
                if ( onControlChange ) onControlChange( name, { size: val, unit: activeUnit }, resp, true );
            }
        });

        $panel.off( 'change.lk-slider-commit', '.lk-slider-range' ).on( 'change.lk-slider-commit', '.lk-slider-range', function() {
            var $wrap = $( this ).closest( '.lk-field-slider' );
            var name = $wrap.data( 'name' );
            var val = $( this ).val();
            var isUnitless = $wrap.data( 'unitless' ) === true || $wrap.data( 'unitless' ) === 'true';
            var resp = isControlResponsive( $wrap );
            if ( isUnitless ) {
                var numVal = ( val !== '' && ! isNaN( parseFloat( val ) ) ) ? parseFloat( val ) : val;
                if ( onControlChange ) onControlChange( name, numVal, resp, false );
            } else {
                var activeUnit = $wrap.find( '.lk-unit-active' ).data( 'unit' ) || $wrap.data( 'unit' ) || 'px';
                if ( onControlChange ) onControlChange( name, { size: val, unit: activeUnit }, resp, false );
            }
        });

        $panel.off( 'input change', '.lk-slider-number' ).on( 'input change', '.lk-slider-number', function( e ) {
            var $wrap = $( this ).closest( '.lk-field-slider' );
            var name = $wrap.data( 'name' );
            var val = $( this ).val();
            $wrap.find( '.lk-slider-range' ).val( val );
            var isUnitless = $wrap.data( 'unitless' ) === true || $wrap.data( 'unitless' ) === 'true';
            var resp = isControlResponsive( $wrap );
            var isInput = ( e.type === 'input' );
            if ( isUnitless ) {
                var numVal = ( val !== '' && ! isNaN( parseFloat( val ) ) ) ? parseFloat( val ) : val;
                if ( onControlChange ) onControlChange( name, numVal, resp, isInput );
            } else {
                var activeUnit = $wrap.find( '.lk-unit-active' ).data( 'unit' ) || $wrap.data( 'unit' ) || 'px';
                if ( onControlChange ) onControlChange( name, { size: val, unit: activeUnit }, resp, isInput );
            }
        });

        // Slider Unit button click (Bug 1 Fix)
        $panel.off( 'click', '.lk-slider-units .lk-unit-btn' ).on( 'click', '.lk-slider-units .lk-unit-btn', function( e ) {
            e.stopPropagation();
            var $wrap = $( this ).closest( '.lk-field-slider' );
            var name = $wrap.data( 'name' );
            $wrap.find( '.lk-unit-btn' ).removeClass( 'lk-unit-active' );
            $( this ).addClass( 'lk-unit-active' );
            var newUnit = $( this ).data( 'unit' );
            $wrap.data( 'unit', newUnit );
            var currentSize = $wrap.find( '.lk-slider-number' ).val();
            var resp = isControlResponsive( $wrap );

            // With no value yet there is nothing to style, and saving an empty value made the
            // panel re-render and snap the unit back to px. Keep the chosen unit in the UI;
            // the number input reads it (lk-unit-active) as soon as a value is typed.
            if ( currentSize === '' || currentSize === null || currentSize === undefined ) {
                $wrap.find( '.lk-slider-range' ).attr( 'data-unit', newUnit );
                return;
            }

            if ( onControlChange ) onControlChange( name, { size: currentSize, unit: newUnit }, resp );
        });

        // Dimensions Unit button click (Bug 1 Fix)
        $panel.off( 'click', '.lk-field-dimensions .lk-dim-units .lk-unit-btn' ).on( 'click', '.lk-field-dimensions .lk-dim-units .lk-unit-btn', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-dimensions' );
            var name = $field.data( 'name' );
            $field.find( '.lk-dim-units .lk-unit-btn' ).removeClass( 'lk-unit-active' );
            $( this ).addClass( 'lk-unit-active' );
            var newUnit = $( this ).data( 'unit' );
            var isLinked = $field.find( '.lk-dim-link-btn' ).hasClass( 'lk-linked' );
            var dimObj = {
                top:      $field.find( '.lk-dim-input[data-side="top"]' ).val(),
                right:    $field.find( '.lk-dim-input[data-side="right"]' ).val(),
                bottom:   $field.find( '.lk-dim-input[data-side="bottom"]' ).val(),
                left:     $field.find( '.lk-dim-input[data-side="left"]' ).val(),
                isLinked: isLinked,
                unit:     newUnit,
            };
            var resp = isControlResponsive( $field );
            if ( onControlChange ) onControlChange( name, dimObj, resp );
        });

        // Gaps Unit button click (Bug 1 Fix)
        $panel.off( 'click', '.lk-field-gaps .lk-dim-units .lk-unit-btn' ).on( 'click', '.lk-field-gaps .lk-dim-units .lk-unit-btn', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-gaps' );
            var name = $field.data( 'name' );
            $field.find( '.lk-dim-units .lk-unit-btn' ).removeClass( 'lk-unit-active' );
            $( this ).addClass( 'lk-unit-active' );
            var newUnit = $( this ).data( 'unit' );
            var isLinked = $field.find( '.lk-dim-link-btn' ).hasClass( 'lk-linked' );
            var gapObj = {
                row:      $field.find( '.lk-gap-input[data-gap="row"]' ).val(),
                column:   $field.find( '.lk-gap-input[data-gap="column"]' ).val(),
                isLinked: isLinked,
                unit:     newUnit,
            };
            var resp = isControlResponsive( $field );
            if ( onControlChange ) onControlChange( name, gapObj, resp );
        });

        // Dimensions inputs & Link
        $panel.off( 'input change', '.lk-dim-input' ).on( 'input change', '.lk-dim-input', function() {
            var $field = $( this ).closest( '.lk-field-dimensions' );
            var name = $field.data( 'name' );
            var isLinked = $field.find( '.lk-dim-link-btn' ).hasClass( 'lk-linked' );
            var changedVal = $( this ).val();
            var unit = $field.find( '.lk-unit-active' ).data( 'unit' ) || 'px';

            if ( isLinked ) {
                $field.find( '.lk-dim-input' ).val( changedVal );
            }

            var dimObj = {
                top:      $field.find( '.lk-dim-input[data-side="top"]' ).val(),
                right:    $field.find( '.lk-dim-input[data-side="right"]' ).val(),
                bottom:   $field.find( '.lk-dim-input[data-side="bottom"]' ).val(),
                left:     $field.find( '.lk-dim-input[data-side="left"]' ).val(),
                isLinked: isLinked,
                unit:     unit,
            };
            var resp = isControlResponsive( $field );
            if ( onControlChange ) onControlChange( name, dimObj, resp );
        });

        $panel.off( 'click', '.lk-dim-link-btn' ).on( 'click', '.lk-dim-link-btn', function( e ) {
            e.stopPropagation();
            var $btn = $( this );
            var isLinked = $btn.hasClass( 'lk-linked' );
            $btn.toggleClass( 'lk-linked', ! isLinked );
            $btn.attr( 'title', ! isLinked ? 'Unlink values' : 'Link values' );
            var $icon = $btn.find( '.dashicons' );
            $icon.toggleClass( 'dashicons-admin-links', ! isLinked );
            $icon.toggleClass( 'dashicons-editor-unlink', isLinked );

            var $field = $btn.closest( '.lk-field-dimensions, .lk-field-gaps' );
            var name = $field.data( 'name' );
            var resp = isControlResponsive( $field );

            if ( $field.hasClass( 'lk-field-dimensions' ) ) {
                var unit = $field.find( '.lk-unit-active' ).data( 'unit' ) || 'px';
                if ( ! isLinked ) {
                    var topVal = $field.find( '.lk-dim-input[data-side="top"]' ).val();
                    $field.find( '.lk-dim-input' ).val( topVal );
                }
                var dimObj = {
                    top:      $field.find( '.lk-dim-input[data-side="top"]' ).val(),
                    right:    $field.find( '.lk-dim-input[data-side="right"]' ).val(),
                    bottom:   $field.find( '.lk-dim-input[data-side="bottom"]' ).val(),
                    left:     $field.find( '.lk-dim-input[data-side="left"]' ).val(),
                    isLinked: ! isLinked,
                    unit:     unit,
                };
                if ( onControlChange ) onControlChange( name, dimObj, resp );
            } else if ( $field.hasClass( 'lk-field-gaps' ) ) {
                var gUnit = $field.find( '.lk-unit-active' ).data( 'unit' ) || 'px';
                if ( ! isLinked ) {
                    var rVal = $field.find( '.lk-gap-input[data-gap="row"]' ).val();
                    $field.find( '.lk-gap-input[data-gap="column"]' ).val( rVal );
                }
                var gapObj = {
                    row:      $field.find( '.lk-gap-input[data-gap="row"]' ).val(),
                    column:   $field.find( '.lk-gap-input[data-gap="column"]' ).val(),
                    isLinked: ! isLinked,
                    unit:     gUnit,
                };
                if ( onControlChange ) onControlChange( name, gapObj, resp );
            }
        });

        // Gaps inputs
        $panel.off( 'input change', '.lk-gap-input' ).on( 'input change', '.lk-gap-input', function() {
            var $field = $( this ).closest( '.lk-field-gaps' );
            var name = $field.data( 'name' );
            var isLinked = $field.find( '.lk-dim-link-btn' ).hasClass( 'lk-linked' );
            var val = $( this ).val();
            var unit = $field.find( '.lk-unit-active' ).data( 'unit' ) || 'px';

            if ( isLinked ) {
                $field.find( '.lk-gap-input' ).val( val );
            }

            var gapObj = {
                row:      $field.find( '.lk-gap-input[data-gap="row"]' ).val(),
                column:   $field.find( '.lk-gap-input[data-gap="column"]' ).val(),
                isLinked: isLinked,
                unit:     unit,
            };
            var resp = isControlResponsive( $field );
            if ( onControlChange ) onControlChange( name, gapObj, resp );
        });

        // URL popover toggle
        $panel.off( 'click', '.lk-url-gear-btn' ).on( 'click', '.lk-url-gear-btn', function( e ) {
            e.stopPropagation();
            var $pop = $( this ).closest( '.lk-field-url' ).find( '.lk-url-popover' );
            $pop.toggle();
            $( this ).toggleClass( 'lk-gear-active', $pop.is( ':visible' ) );
        });

        $panel.off( 'input change', '.lk-url-input, .lk-url-external, .lk-url-nofollow, .lk-url-attrs' )
              .on( 'input change', '.lk-url-input, .lk-url-external, .lk-url-nofollow, .lk-url-attrs', function() {
            var $field = $( this ).closest( '.lk-field-url' );
            var name = $field.data( 'name' );
            var urlObj = {
                url:               $field.find( '.lk-url-input' ).val(),
                is_external:       $field.find( '.lk-url-external' ).is( ':checked' ),
                nofollow:          $field.find( '.lk-url-nofollow' ).is( ':checked' ),
                custom_attributes: $field.find( '.lk-url-attrs' ).val(),
            };
            if ( onControlChange ) onControlChange( name, urlObj, false );
        });

        // Color Swatch & Popover
        /**
         * Show a colour popover / globals menu above everything. The panel scrolls with
         * overflow:auto, which clipped the picker, so it is moved to <body> while open
         * and returned to its field when it closes.
         */
        function lkFloatPopover( $pop, $anchor ) {
            if ( ! $pop.length ) return;
            // position:fixed escapes the panel's overflow:auto clipping, and the popover stays
            // inside the panel in the DOM so every existing handler keeps working.
            var rect = $anchor.get( 0 ).getBoundingClientRect();
            // Outside the panel the popover has no parent to size it, so it stretched across
            // the screen. Give it the panel's width (capped) before measuring.
            var panelW = $( '#lk-panel' ).outerWidth() || 300;
            $pop.css( { width: Math.max( 240, Math.min( panelW - 24, 320 ) ) + 'px', maxWidth: 'calc(100vw - 16px)' } ).show();
            var w = $pop.outerWidth() || 280;
            var h = $pop.outerHeight() || 123;
            var top = rect.bottom + 6;
            if ( top + h > $( window ).height() - 8 ) top = Math.max( 8, rect.top - h - 6 );
            $pop.css({
                position: 'fixed',
                top: top + 'px',
                left: Math.max( 8, Math.min( rect.left, $( window ).width() - w - 8 ) ) + 'px',
                zIndex: 100001
            });
        }

        function lkUnfloatPopovers( $except ) {
            var closed = false;
            $( '.lk-color-popover, .lk-color-globals-menu' ).each( function() {
                var $p = $( this );
                if ( $except && $p.is( $except ) ) return;
                if ( $p.is( ':visible' ) ) closed = true;
                $p.hide().css({ position: '', top: '', left: '', width: '', maxWidth: '', zIndex: '' } );
            } );
            // Panel re-rendering is paused while a picker is open; refresh once it closes.
            if ( closed ) $( document ).trigger( 'lk-popovers-closed' );
        }

        // While the browser's native colour dialog is open, clicks land outside the page and
        // used to close the picker the moment you dragged the gradient. Ignore outside-closing
        // for a moment after touching the colour input.
        // The browser's own colour dialog lives outside the page: while it is open the page
        // only sees stray clicks on <body>, which used to close the picker the moment you
        // clicked the gradient. Stay open until that input is finished with.
        var lkNativeOpen = false;
        var lkNativeUntil = 0;
        $( document ).off( '.lkcolornative' )
            .on( 'mousedown.lkcolornative click.lkcolornative focus.lkcolornative', 'input[type="color"]', function() {
                lkNativeOpen = true;
                lkNativeUntil = Date.now() + 60000;
            } )
            .on( 'change.lkcolornative blur.lkcolornative', 'input[type="color"]', function() {
                lkNativeUntil = Date.now() + 800;
                setTimeout( function() { if ( Date.now() >= lkNativeUntil ) lkNativeOpen = false; }, 900 );
            } );

        $( document ).off( 'click.lkcolor' ).on( 'click.lkcolor', function( e ) {
            if ( lkNativeOpen && Date.now() < lkNativeUntil ) {
                // A real click inside the editor UI means the dialog is closed again.
                if ( $( e.target ).closest( '#lk-panel, .lk-color-popover, .lk-canvas-wrap, .lk-topbar' ).length ) {
                    lkNativeOpen = false;
                } else {
                    return;
                }
            }
            if ( ! $( e.target ).closest( '.lk-color-popover, .lk-color-globals-menu, .lk-color-swatch-btn, .lk-color-globe-btn' ).length ) {
                lkUnfloatPopovers();
            }
        } );

        $panel.off( 'click', '.lk-color-swatch-btn' ).on( 'click', '.lk-color-swatch-btn', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-color' );
            var $pop = $field.find( '.lk-color-popover' ).first();
            var isOpen = $pop.is( ':visible' );
            lkUnfloatPopovers();
            if ( ! isOpen ) lkFloatPopover( $pop, $( this ) );
        });

        $panel.off( 'click', '.lk-color-globe-btn' ).on( 'click', '.lk-color-globe-btn', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-color' );
            var $menu = $field.find( '.lk-color-globals-menu' ).first();
            var isOpen = $menu.is( ':visible' );
            lkUnfloatPopovers();
            if ( ! isOpen ) lkFloatPopover( $menu, $( this ) );
        });

        $panel.off( 'click', '.lk-global-color-item' ).on( 'click', '.lk-global-color-item', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-color' );
            var name = $field.data( 'name' );
            var varRef = $( this ).data( 'var' );
            var colVal = $( this ).data( 'val' );
            var gName = $( this ).data( 'name' );

            $field.find( '.lk-color-globals-menu' ).hide();
            $field.find( '.lk-color-swatch-btn' ).css( 'background-color', colVal );
            $field.find( '.lk-color-globe-btn' ).addClass( 'lk-globe-active' );

            $field.find( '.lk-color-hex' ).replaceWith( '<div class="lk-color-global-tag"><span class="dashicons dashicons-admin-site-alt3"></span> ' + escapeHtml( gName ) + '</div>' );

            if ( onControlChange ) onControlChange( name, varRef, false );
        });

        $panel.off( 'input change', '.lk-native-color-picker' ).on( 'input change', '.lk-native-color-picker', function() {
            var $field = $( this ).closest( '.lk-field-color' );
            var name = $field.data( 'name' );
            var hex = $( this ).val();
            $field.find( '.lk-popover-hex' ).val( hex );
            $field.find( '.lk-color-hex' ).val( hex );
            $field.find( '.lk-color-swatch-btn' ).css( 'background-color', hex );
            $field.find( '.lk-color-globe-btn' ).removeClass( 'lk-globe-active' );
            if ( onControlChange ) onControlChange( name, hex, false );
        });

        $panel.off( 'input change', '.lk-color-hex, .lk-popover-hex' ).on( 'input change', '.lk-color-hex, .lk-popover-hex', function() {
            var $field = $( this ).closest( '.lk-field-color' );
            var name = $field.data( 'name' );
            var hex = $( this ).val();
            $field.find( '.lk-native-color-picker' ).val( hex );
            $field.find( '.lk-color-swatch-btn' ).css( 'background-color', hex );
            $field.find( '.lk-color-globe-btn' ).removeClass( 'lk-globe-active' );
            if ( onControlChange ) onControlChange( name, hex, false );
        });

        $panel.off( 'click', '.lk-quick-color' ).on( 'click', '.lk-quick-color', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-color' );
            var name = $field.data( 'name' );
            var color = $( this ).data( 'color' );
            $field.find( '.lk-popover-hex' ).val( color );
            $field.find( '.lk-color-hex' ).val( color );
            $field.find( '.lk-native-color-picker' ).val( color );
            $field.find( '.lk-color-swatch-btn' ).css( 'background-color', color );
            $field.find( '.lk-color-globe-btn' ).removeClass( 'lk-globe-active' );
            if ( onControlChange ) onControlChange( name, color, false );
        });

        $panel.off( 'click', '.lk-color-clear-btn' ).on( 'click', '.lk-color-clear-btn', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-color' );
            var name = $field.data( 'name' );
            $field.find( '.lk-color-swatch-btn' ).css( 'background-color', 'transparent' );
            $field.find( '.lk-color-globe-btn' ).removeClass( 'lk-globe-active' );
            $field.find( '.lk-color-global-tag' ).replaceWith( '<input type="text" class="lk-input lk-color-hex" value="" placeholder="#000000">' );
            $field.find( '.lk-color-hex' ).val( '' );
            $field.find( '.lk-popover-hex' ).val( '' );
            if ( onControlChange ) onControlChange( name, '', false );
        });

        // Typography popover toggle
        $panel.off( 'click', '.lk-typo-edit-btn' ).on( 'click', '.lk-typo-edit-btn', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-typography' );
            $field.find( '.lk-fonts-globals-menu' ).hide();
            $field.find( '.lk-typo-popover' ).toggle();
        });

        $panel.off( 'click', '.lk-typo-globe-btn' ).on( 'click', '.lk-typo-globe-btn', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-typography' );
            $field.find( '.lk-typo-popover' ).hide();
            $field.find( '.lk-fonts-globals-menu' ).toggle();
        });

        $panel.off( 'click', '.lk-global-font-item' ).on( 'click', '.lk-global-font-item', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-typography' );
            var name = $field.data( 'name' );
            var family = $( this ).data( 'family' );
            var fontName = $( this ).data( 'name' );

            $field.find( '.lk-fonts-globals-menu' ).hide();
            $field.find( '.lk-typo-summary' ).text( fontName + ' (' + family + ')' );
            $field.find( '.lk-typo-family' ).val( family );

            if ( onControlChange ) onControlChange( name, { family: family }, false );
        });

        // Typography values change helper
        function collectTypography( $field ) {
            return {
                family:         $field.find( '.lk-typo-family' ).val(),
                size: {
                    size:       $field.find( '.lk-typo-size-num' ).val(),
                    unit:       $field.find( '.lk-typo-size-unit' ).val() || 'px',
                },
                weight:         $field.find( '.lk-typo-weight' ).val(),
                transform:      $field.find( '.lk-typo-transform' ).val(),
                style:          $field.find( '.lk-typo-style' ).val(),
                line_height: {
                    size:       $field.find( '.lk-typo-lh-num' ).val(),
                    unit:       $field.find( '.lk-typo-lh-unit' ).val() || 'em',
                },
                letter_spacing: {
                    size:       $field.find( '.lk-typo-ls-num' ).val(),
                    unit:       'px',
                },
                word_spacing: {
                    size:       $field.find( '.lk-typo-ws-num' ).val(),
                    unit:       'px',
                },
            };
        }

        $panel.off( 'input change', '.lk-typo-popover select, .lk-typo-popover input' )
              .on( 'input change', '.lk-typo-popover select, .lk-typo-popover input', function() {
            var $field = $( this ).closest( '.lk-field-typography' );
            var name = $field.data( 'name' );
            var typoObj = collectTypography( $field );
            var summary = typoObj.family || 'Default';
            if ( typoObj.size && typoObj.size.size ) {
                summary += ', ' + typoObj.size.size + typoObj.size.unit;
            }
            $field.find( '.lk-typo-summary' ).text( summary );
            if ( onControlChange ) onControlChange( name, typoObj, false );
        });

        $panel.off( 'click', '.lk-typo-reset-btn' ).on( 'click', '.lk-typo-reset-btn', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-typography' );
            var name = $field.data( 'name' );
            $field.find( '.lk-typo-family' ).val( '' );
            $field.find( '.lk-typo-size-num' ).val( '' );
            $field.find( '.lk-typo-weight' ).val( '' );
            $field.find( '.lk-typo-transform' ).val( '' );
            $field.find( '.lk-typo-style' ).val( '' );
            $field.find( '.lk-typo-lh-num' ).val( '' );
            $field.find( '.lk-typo-ls-num' ).val( '' );
            $field.find( '.lk-typo-ws-num' ).val( '' );
            $field.find( '.lk-typo-summary' ).text( 'Default' );
            if ( onControlChange ) onControlChange( name, {}, false );
        });

        // Media Library Frame
        $panel.off( 'click', '.lk-media-upload-btn' ).on( 'click', '.lk-media-upload-btn', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-media' );
            var name = $field.data( 'name' );

            if ( typeof wp === 'undefined' || ! wp.media ) {
                var $toast = $( '#lk-toast' );
                if ( $toast.length ) {
                    $toast.attr( 'class', 'lk-toast lk-toast-error' ).text( 'WordPress media library not available.' ).addClass( 'lk-toast-visible' );
                    setTimeout( function() { $toast.removeClass( 'lk-toast-visible' ); }, 2500 );
                }
                return;
            }

            var frame = wp.media({
                title: 'Select or Upload Media',
                button: { text: 'Use this media' },
                multiple: false,
            });

            frame.on( 'select', function() {
                var attachment = frame.state().get( 'selection' ).first().toJSON();
                var mediaObj = {
                    id: attachment.id,
                    url: attachment.url,
                };
                $field.find( '.lk-media-preview-box' ).addClass( 'lk-has-image' ).html( '<img src="' + escapeHtml( attachment.url ) + '" class="lk-media-img" alt="">' );
                $field.find( '.lk-media-upload-btn' ).text( 'Change Image' );
                $field.find( '.lk-media-url' ).val( attachment.url );
                $field.find( '.lk-media-id' ).val( attachment.id );
                if ( ! $field.find( '.lk-media-remove-btn' ).length ) {
                    $field.find( '.lk-media-actions' ).append( '<button type="button" class="lk-media-remove-btn" title="Remove image">&times;</button>' );
                }

                // Inside a repeater the value belongs to that row, not to a setting of its own.
                var $repeaterRow = $field.closest( '.lk-repeater-row' );
                if ( $repeaterRow.length ) {
                    $repeaterRow.find( '.lk-media-url' ).first().trigger( 'change' );
                    collectAndTriggerRepeater( $repeaterRow.closest( '.lk-field-repeater' ) );
                    return;
                }

                var isResp = isControlResponsive( $field );
                if ( onControlChange ) onControlChange( name, mediaObj, isResp );
            });

            frame.open();
        });

        $panel.off( 'click', '.lk-media-remove-btn' ).on( 'click', '.lk-media-remove-btn', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-media' );
            var name = $field.data( 'name' );
            $field.find( '.lk-media-preview-box' ).removeClass( 'lk-has-image' ).html( '<span class="dashicons dashicons-format-image"></span>' );
            $field.find( '.lk-media-upload-btn' ).text( 'Choose Image' );
            $field.find( '.lk-media-url' ).val( '' );
            $field.find( '.lk-media-id' ).val( '' );
            $( this ).remove();

            var $repeaterRow = $field.closest( '.lk-repeater-row' );
            if ( $repeaterRow.length ) {
                collectAndTriggerRepeater( $repeaterRow.closest( '.lk-field-repeater' ) );
                return;
            }

            var isResp = isControlResponsive( $field );
            if ( onControlChange ) onControlChange( name, { id: '', url: '' }, isResp );
        });

        // Code editor (Custom CSS & HTML) initialization
        $panel.find( '.lk-code-editor' ).each( function() {
            var $textarea = $( this );
            if ( $textarea.data( 'cm-init' ) ) return;
            $textarea.data( 'cm-init', true );
            var $field = $textarea.closest( '.lk-field-code' );
            var name = $field.data( 'name' ) || $textarea.data( 'name' ) || 'custom_css';
            var mode = $textarea.data( 'mode' ) || $field.data( 'mode' ) || ( name === 'html' ? 'html' : 'css' );
            var isReadOnly = $field.data( 'readonly' ) === true || $field.data( 'readonly' ) === 'true' || $textarea.prop( 'readonly' );
            var isHtml = ( mode === 'html' || mode === 'text/html' );

            if ( window.wp && window.wp.codeEditor ) {
                var baseSettings = isHtml
                    ? ( window.loopkitEditor && window.loopkitEditor.codeEditorHtmlSettings )
                    : ( window.loopkitEditor && window.loopkitEditor.codeEditorSettings );

                var editorSettings = $.extend( true, {}, baseSettings || {}, {
                    codemirror: {
                        mode: isHtml ? 'text/html' : 'css',
                        lineNumbers: true,
                        readOnly: isReadOnly ? true : false,
                        viewportMargin: Infinity
                    }
                });

                try {
                    var editorInst = window.wp.codeEditor.initialize( this, editorSettings );
                    if ( isReadOnly ) {
                        editorInst.codemirror.setOption( 'readOnly', true );
                    } else {
                        editorInst.codemirror.on( 'change', function( cm ) {
                            var val = cm.getValue();
                            if ( ! isHtml ) {
                                val = val.replace( /<\/?style[^>]*>/gi, '' );
                            }
                            if ( onControlChange ) onControlChange( name, val, false, true );
                        });
                        editorInst.codemirror.on( 'blur', function( cm ) {
                            var val = cm.getValue();
                            if ( ! isHtml ) {
                                val = val.replace( /<\/?style[^>]*>/gi, '' );
                            }
                            if ( onControlChange ) onControlChange( name, val, false, false );
                        });
                    }
                    setTimeout( function() {
                        editorInst.codemirror.refresh();
                    }, 60 );
                } catch ( err ) {
                    if ( ! isReadOnly ) {
                        $textarea.off( 'input' ).on( 'input', function() {
                            var val = $( this ).val();
                            if ( ! isHtml ) {
                                val = val.replace( /<\/?style[^>]*>/gi, '' );
                            }
                            if ( onControlChange ) onControlChange( name, val, false, true );
                        });
                        $textarea.off( 'change' ).on( 'change', function() {
                            var val = $( this ).val();
                            if ( ! isHtml ) {
                                val = val.replace( /<\/?style[^>]*>/gi, '' );
                            }
                            if ( onControlChange ) onControlChange( name, val, false, false );
                        });
                    }
                }
            } else {
                if ( ! isReadOnly ) {
                    $textarea.off( 'input' ).on( 'input', function() {
                        var val = $( this ).val();
                        if ( ! isHtml ) {
                            val = val.replace( /<\/?style[^>]*>/gi, '' );
                        }
                        if ( onControlChange ) onControlChange( name, val, false, true );
                    });
                    $textarea.off( 'change' ).on( 'change', function() {
                        var val = $( this ).val();
                        if ( ! isHtml ) {
                            val = val.replace( /<\/?style[^>]*>/gi, '' );
                        }
                        if ( onControlChange ) onControlChange( name, val, false, false );
                    });
                }
            }
        });

        // Subtabs switching
        $panel.off( 'click', '.lk-subtab-btn' ).on( 'click', '.lk-subtab-btn', function( e ) {
            e.stopPropagation();
            var $btn = $( this );
            var paneId = $btn.data( 'pane' );
            var $wrap = $btn.closest( '.lk-control-subtabs' );
            $wrap.find( '.lk-subtab-btn' ).removeClass( 'lk-subtab-active' );
            $btn.addClass( 'lk-subtab-active' );
            $wrap.find( '.lk-subtab-pane' ).removeClass( 'lk-pane-active' );
            $wrap.find( '.lk-subtab-pane[data-pane-id="' + paneId + '"]' ).addClass( 'lk-pane-active' );
        });

        // Popover toggle button click (only one popover open at a time)
        $panel.off( 'click', '.lk-popover-toggle-btn' ).on( 'click', '.lk-popover-toggle-btn', function( e ) {
            e.stopPropagation();
            var $btn = $( this );
            var popName = $btn.data( 'popover' );
            var $wrap = $btn.closest( '.lk-field-popover' );
            var $pop = $wrap.find( '.lk-popover-panel[data-popover-panel="' + popName + '"]' );
            var isVis = $pop.is( ':visible' );

            $( '.lk-popover-panel, .lk-color-popover, .lk-color-globals-menu, .lk-typo-popover, .lk-url-popover, .lk-res-dropdown' ).hide();
            if ( ! isVis ) {
                $pop.show();
            }
        });

        // Helper to collect popover values
        function collectPopoverValues( $pop ) {
            var $wrap = $pop.closest( '.lk-field-popover' );
            var popType = $wrap.data( 'popover-type' );
            var obj = {};

            if ( popType === 'text_shadow' ) {
                var c = $pop.find( '.lk-color-hex' ).val() || '';
                var b = parseFloat( $pop.find( '.lk-pop-input[data-key="blur"]' ).val() ) || 0;
                var h = parseFloat( $pop.find( '.lk-pop-input[data-key="horizontal"]' ).val() ) || 0;
                var v = parseFloat( $pop.find( '.lk-pop-input[data-key="vertical"]' ).val() ) || 0;
                if ( c || b || h || v ) {
                    obj = { color: c, blur: b, horizontal: h, vertical: v };
                }
            } else if ( popType === 'box_shadow' ) {
                var c = $pop.find( '.lk-color-hex' ).val() || '';
                var h = parseFloat( $pop.find( '.lk-pop-input[data-key="horizontal"]' ).val() ) || 0;
                var v = parseFloat( $pop.find( '.lk-pop-input[data-key="vertical"]' ).val() ) || 0;
                var b = parseFloat( $pop.find( '.lk-pop-input[data-key="blur"]' ).val() ) || 0;
                var s = parseFloat( $pop.find( '.lk-pop-input[data-key="spread"]' ).val() ) || 0;
                var p = $pop.find( '.lk-pop-select[data-key="position"]' ).val() || 'outline';
                if ( c || h || v || b || s || ( p && p !== 'outline' ) ) {
                    obj = { color: c, horizontal: h, vertical: v, blur: b, spread: s, position: p };
                }
            } else if ( popType === 'text_stroke' ) {
                var w = $pop.find( '.lk-pop-input[data-key="width"]' ).val();
                var c = $pop.find( '.lk-color-hex' ).val() || '';
                if ( ( w !== undefined && w !== '' ) || c ) {
                    obj = { width: w, color: c, unit: 'px' };
                }
            } else if ( popType === 'css_filters' ) {
                var b = parseFloat( $pop.find( '.lk-pop-input[data-key="blur"]' ).val() ) || 0;
                var br = parseFloat( $pop.find( '.lk-pop-input[data-key="brightness"]' ).val() );
                var co = parseFloat( $pop.find( '.lk-pop-input[data-key="contrast"]' ).val() );
                var sa = parseFloat( $pop.find( '.lk-pop-input[data-key="saturation"]' ).val() );
                var hu = parseFloat( $pop.find( '.lk-pop-input[data-key="hue"]' ).val() ) || 0;
                if ( isNaN( br ) ) br = 100;
                if ( isNaN( co ) ) co = 100;
                if ( isNaN( sa ) ) sa = 100;
                if ( b !== 0 || br !== 100 || co !== 100 || sa !== 100 || hu !== 0 ) {
                    obj = { blur: b, brightness: br, contrast: co, saturation: sa, hue: hu };
                }
            }

            return obj;
        }

        // Popover inputs change (sliders & selects)
        $panel.off( 'input change', '.lk-popover-panel .lk-pop-input, .lk-popover-panel .lk-pop-select' )
              .on( 'input change', '.lk-popover-panel .lk-pop-input, .lk-popover-panel .lk-pop-select', function() {
            var $input = $( this );
            var $pop = $input.closest( '.lk-popover-panel' );
            var popName = $pop.data( 'popover-panel' );
            var $wrap = $pop.closest( '.lk-field-popover' );

            // Sync range and number if slider pair
            var key = $input.data( 'key' );
            var val = $input.val();
            if ( $input.hasClass( 'lk-slider-range' ) ) {
                $pop.find( '.lk-slider-number[data-key="' + key + '"]' ).val( val );
            } else if ( $input.hasClass( 'lk-slider-number' ) ) {
                $pop.find( '.lk-slider-range[data-key="' + key + '"]' ).val( val );
            }

            var obj = collectPopoverValues( $pop );
            var hasVal = Object.keys( obj ).length > 0;
            $wrap.find( '.lk-popover-toggle-btn' ).toggleClass( 'lk-popover-active', hasVal );
            var resp = isControlResponsive( $wrap );
            if ( onControlChange ) onControlChange( popName, obj, resp );
        });

        // Popover Color changes inside popovers
        $panel.off( 'change.lk-pop-color', '.lk-popover-panel .lk-color-hex, .lk-popover-panel .lk-native-color-picker' )
              .on( 'change.lk-pop-color', '.lk-popover-panel .lk-color-hex, .lk-popover-panel .lk-native-color-picker', function() {
            var $pop = $( this ).closest( '.lk-popover-panel' );
            var popName = $pop.data( 'popover-panel' );
            var $wrap = $pop.closest( '.lk-field-popover' );
            var obj = collectPopoverValues( $pop );
            var hasVal = Object.keys( obj ).length > 0;
            $wrap.find( '.lk-popover-toggle-btn' ).toggleClass( 'lk-popover-active', hasVal );
            var resp = isControlResponsive( $wrap );
            if ( onControlChange ) onControlChange( popName, obj, resp );
        });

        // Popover Reset Button
        $panel.off( 'click', '.lk-popover-reset-btn' ).on( 'click', '.lk-popover-reset-btn', function( e ) {
            e.stopPropagation();
            var popName = $( this ).data( 'reset' );
            var $wrap = $( this ).closest( '.lk-field-popover' );
            var $pop = $wrap.find( '.lk-popover-panel' );
            var popType = $wrap.data( 'popover-type' );

            $pop.find( '.lk-slider-range, .lk-slider-number' ).each( function() {
                var k = $( this ).data( 'key' );
                var defVal = ( k === 'brightness' || k === 'contrast' || k === 'saturation' ) ? 100 : ( k === 'blur' && ( popType === 'text_shadow' || popType === 'box_shadow' ) ? 10 : 0 );
                $( this ).val( defVal );
            });
            $pop.find( '.lk-color-hex, .lk-popover-hex' ).val( '' );
            $pop.find( '.lk-color-swatch-btn' ).css( 'background-color', 'transparent' );
            $pop.find( '.lk-pop-select' ).val( 'outline' );

            $wrap.find( '.lk-popover-toggle-btn' ).removeClass( 'lk-popover-active' );
            $pop.hide();

            var resp = isControlResponsive( $wrap );
            if ( onControlChange ) onControlChange( popName, {}, resp );
        });

        // ---------------------------------------------------------
        // REPEATER & SHARED CONTROLS EVENT BINDINGS (PART E)
        // ---------------------------------------------------------

        function collectAndTriggerRepeater( $field ) {
            var repeaterName = $field.data( 'name' );
            var items = [];

            $field.find( '.lk-repeater-row' ).each( function( idx ) {
                var $row = $( this );
                var rId = $row.data( 'id' ) || ( 'lk_item_' + Math.random().toString(36).substr(2, 8) );
                $row.attr( 'data-id', rId );
                $row.attr( 'data-index', idx );

                var itemObj = { _id: rId };

                $row.find( '.lk-repeater-field-wrap' ).each( function() {
                    var fName = $( this ).data( 'repeater-field' );
                    if ( ! fName ) return;

                    var $icon = $( this ).find( '.lk-field-icon-picker' );
                    if ( $icon.length ) {
                        var iVal = $icon.data( 'icon-val' );
                        if ( iVal ) {
                            itemObj[ fName ] = iVal;
                            return;
                        }
                    }

                    var $imgSize = $( this ).find( '.lk-field-image-size' );
                    if ( $imgSize.length ) {
                        var sVal = $imgSize.find( '.lk-image-size-select' ).val();
                        if ( sVal === 'custom' ) {
                            itemObj[ fName ] = {
                                size: 'custom',
                                custom_dimension: {
                                    width:  $imgSize.find( '.lk-image-size-custom-w' ).val() || '',
                                    height: $imgSize.find( '.lk-image-size-custom-h' ).val() || ''
                                }
                            };
                        } else {
                            itemObj[ fName ] = sVal;
                        }
                        return;
                    }

                    var $inputs = $( this ).find( '.lk-input-text, .lk-input-textarea, .lk-input-number' );
                    if ( $inputs.length ) {
                        itemObj[ fName ] = $inputs.val();
                        return;
                    }

                    var $sel = $( this ).find( '.lk-input-select' );
                    if ( $sel.length ) {
                        itemObj[ fName ] = $sel.val();
                        return;
                    }

                    var $sw = $( this ).find( '.lk-input-switcher' );
                    if ( $sw.length ) {
                        itemObj[ fName ] = $sw.is( ':checked' ) ? 'yes' : 'no';
                        return;
                    }

                    var $col = $( this ).find( '.lk-color-hex' );
                    if ( $col.length ) {
                        itemObj[ fName ] = $col.val();
                        return;
                    }

                    var $url = $( this ).find( '.lk-url-input' );
                    if ( $url.length ) {
                        itemObj[ fName ] = {
                            url: $url.val(),
                            is_external: $( this ).find( '.lk-url-external' ).is( ':checked' ),
                            nofollow: $( this ).find( '.lk-url-nofollow' ).is( ':checked' )
                        };
                        return;
                    }

                    var $media = $( this ).find( '.lk-media-url' );
                    if ( $media.length ) {
                        itemObj[ fName ] = {
                            id: $( this ).find( '.lk-media-id' ).val(),
                            url: $media.val()
                        };
                        return;
                    }
                });

                // Update row title if a field value matches or fallback
                var rowTitle = '';
                for ( var k in itemObj ) {
                    if ( k !== '_id' && typeof itemObj[k] === 'string' && itemObj[k].trim() ) {
                        rowTitle = itemObj[k];
                        break;
                    }
                }
                if ( ! rowTitle ) {
                    rowTitle = 'Item #' + ( idx + 1 );
                }
                $row.find( '.lk-repeater-row-title' ).first().text( rowTitle );

                items.push( itemObj );
            });

            if ( onControlChange ) {
                onControlChange( repeaterName, items, false );
            }
        }

        // Repeater row header toggle
        $panel.off( 'click', '.lk-repeater-row-header' ).on( 'click', '.lk-repeater-row-header', function( e ) {
            if ( $( e.target ).closest( '.lk-repeater-action, .lk-repeater-drag-handle' ).length ) {
                return;
            }
            e.stopPropagation();
            var $row = $( this ).closest( '.lk-repeater-row' );
            var wasOpen = $row.hasClass( 'lk-open' );
            $row.closest( '.lk-repeater-list' ).find( '.lk-repeater-row' ).removeClass( 'lk-open' );
            if ( ! wasOpen ) {
                $row.addClass( 'lk-open' );
            }
        });

        // Repeater Add Item
        $panel.off( 'click', '.lk-repeater-add' ).on( 'click', '.lk-repeater-add', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-repeater' );
            var newId = 'lk_item_' + Math.random().toString(36).substr(2, 8);

            var $firstRow = $field.find( '.lk-repeater-row' ).first();
            if ( $firstRow.length ) {
                var $newRow = $firstRow.clone();
                $newRow.attr( 'data-id', newId );
                $newRow.attr( 'data-index', $field.find( '.lk-repeater-row' ).length );
                $newRow.removeClass( 'lk-dragging' ).addClass( 'lk-open' );
                var nextIndex = $field.find( '.lk-repeater-row' ).length + 1;
                var titleField = $field.attr( 'data-title-field' ) || 'title';
                var itemDefaults = {};
                try { itemDefaults = JSON.parse( $field.attr( 'data-item-defaults' ) || '{}' ); } catch ( err ) {}

                $newRow.find( '.lk-repeater-field-wrap' ).attr( 'data-item-id', newId );
                $newRow.find( 'input, textarea' ).val( '' );

                // Start the new row from the control's own defaults instead of leaving it blank,
                // and number its title (Row 3, Item 3…) from the title field.
                $newRow.find( '[data-name]' ).each( function() {
                    var fieldName = $( this ).attr( 'data-name' );
                    if ( itemDefaults[ fieldName ] === undefined ) return;
                    if ( $( this ).is( ':checkbox' ) ) {
                        $( this ).prop( 'checked', itemDefaults[ fieldName ] === 'yes' );
                    } else {
                        $( this ).val( itemDefaults[ fieldName ] );
                    }
                } );

                var $titleInput = $newRow.find( '[data-name="' + titleField + '"]' );
                var newTitle = 'Item #' + nextIndex;
                if ( $titleInput.length ) {
                    var base = String( itemDefaults[ titleField ] || $titleInput.val() || 'Item' ).replace( /\s*\d+$/, '' );
                    newTitle = base + ' ' + nextIndex;
                    $titleInput.val( newTitle );
                }
                $newRow.find( '.lk-repeater-row-title' ).text( newTitle );
                $newRow.find( '.lk-icon-preview-box' ).html( '<span class="dashicons dashicons-plus"></span>' );
                $newRow.find( '.lk-icon-remove-btn' ).remove();
                $field.find( '.lk-repeater-list' ).append( $newRow );
            }
            collectAndTriggerRepeater( $field );
        });

        // Repeater Duplicate Item
        $panel.off( 'click', '.lk-repeater-duplicate' ).on( 'click', '.lk-repeater-duplicate', function( e ) {
            e.stopPropagation();
            var $row = $( this ).closest( '.lk-repeater-row' );
            var $field = $( this ).closest( '.lk-field-repeater' );
            var newId = 'lk_item_' + Math.random().toString(36).substr(2, 8);
            var $clone = $row.clone();
            $clone.attr( 'data-id', newId );
            $clone.find( '.lk-repeater-field-wrap' ).attr( 'data-item-id', newId );
            $clone.removeClass( 'lk-dragging' );
            $row.after( $clone );
            collectAndTriggerRepeater( $field );
        });

        // Repeater Delete Item
        $panel.off( 'click', '.lk-repeater-delete' ).on( 'click', '.lk-repeater-delete', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-repeater' );
            $( this ).closest( '.lk-repeater-row' ).remove();
            collectAndTriggerRepeater( $field );
        });

        // Repeater Drag & Drop Reordering
        var draggedRow = null;
        $panel.off( 'mousedown', '.lk-repeater-drag-handle' ).on( 'mousedown', '.lk-repeater-drag-handle', function() {
            $( this ).closest( '.lk-repeater-row' ).attr( 'draggable', 'true' );
        });
        $panel.off( 'dragstart', '.lk-repeater-row' ).on( 'dragstart', '.lk-repeater-row', function( e ) {
            draggedRow = this;
            $( this ).addClass( 'lk-dragging' );
            if ( e.originalEvent && e.originalEvent.dataTransfer ) {
                e.originalEvent.dataTransfer.effectAllowed = 'move';
                e.originalEvent.dataTransfer.setData( 'text/plain', $( this ).data( 'id' ) || '' );
            }
        });
        $panel.off( 'dragover', '.lk-repeater-row' ).on( 'dragover', '.lk-repeater-row', function( e ) {
            e.preventDefault();
            if ( e.originalEvent && e.originalEvent.dataTransfer ) {
                e.originalEvent.dataTransfer.dropEffect = 'move';
            }
            if ( draggedRow && draggedRow !== this ) {
                var rect = this.getBoundingClientRect();
                var next = ( e.originalEvent.clientY - rect.top ) / ( rect.bottom - rect.top ) > 0.5;
                this.parentNode.insertBefore( draggedRow, next ? this.nextSibling : this );
            }
        });
        $panel.off( 'dragend', '.lk-repeater-row' ).on( 'dragend', '.lk-repeater-row', function() {
            $( this ).removeClass( 'lk-dragging' ).removeAttr( 'draggable' );
            draggedRow = null;
            var $field = $( this ).closest( '.lk-field-repeater' );
            collectAndTriggerRepeater( $field );
        });

        // Input changes inside repeater rows
        $panel.off( 'input change', '.lk-repeater-row-content input, .lk-repeater-row-content textarea, .lk-repeater-row-content select' )
              .on( 'input change', '.lk-repeater-row-content input, .lk-repeater-row-content textarea, .lk-repeater-row-content select', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-repeater' );
            collectAndTriggerRepeater( $field );
        });

        // Icon Library open
        $panel.off( 'click', '.lk-icon-preview-box, .lk-icon-library-btn' ).on( 'click', '.lk-icon-preview-box, .lk-icon-library-btn', function( e ) {
            if ( $( e.target ).is( '.lk-icon-remove-btn' ) ) return;
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-icon-picker' );
            var currentVal = $field.data( 'icon-val' );
            if ( typeof currentVal === 'string' ) {
                try { currentVal = JSON.parse( currentVal ); } catch ( err ) {}
            }

            LoopkitControls.openIconLibrary( currentVal, function( newIcon ) {
                $field.data( 'icon-val', newIcon );
                var svg = LoopkitIcons.render( newIcon ) || '<span class="dashicons dashicons-star-filled"></span>';
                var $preview = $field.find( '.lk-icon-preview-box' );
                $preview.html( svg );
                if ( ! $preview.find( '.lk-icon-remove-btn' ).length ) {
                    $preview.append( '<button type="button" class="lk-icon-remove-btn" title="Remove icon">&times;</button>' );
                }

                if ( $field.closest( '.lk-repeater-row' ).length ) {
                    collectAndTriggerRepeater( $field.closest( '.lk-field-repeater' ) );
                } else {
                    var name = $field.data( 'name' );
                    if ( onControlChange ) onControlChange( name, newIcon, false );
                }
            });
        });

        // Icon Remove
        $panel.off( 'click', '.lk-icon-remove-btn' ).on( 'click', '.lk-icon-remove-btn', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-icon-picker' );
            var emptyIcon = { library: '', value: '' };
            $field.data( 'icon-val', emptyIcon );
            $field.find( '.lk-icon-preview-box' ).html( '<span class="dashicons dashicons-plus"></span>' );

            if ( $field.closest( '.lk-repeater-row' ).length ) {
                collectAndTriggerRepeater( $field.closest( '.lk-field-repeater' ) );
            } else {
                var name = $field.data( 'name' );
                if ( onControlChange ) onControlChange( name, emptyIcon, false );
            }
        });

        // Icon SVG Upload (Admin only)
        // Write SVG: paste the markup instead of uploading a file.
        $panel.off( 'click', '.lk-icon-write-svg-btn' ).on( 'click', '.lk-icon-write-svg-btn', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-icon-picker' );
            if ( $field.find( '.lk-svg-editor' ).length ) {
                $field.find( '.lk-svg-editor' ).remove();
                return;
            }
            var cur = $field.data( 'icon-val' );
            var existing = ( cur && cur.library === 'svg' && cur.value && cur.value.svg ) ? cur.value.svg : '';

            var $ed = $(
                '<div class="lk-svg-editor">' +
                '  <textarea class="lk-svg-code" rows="7" spellcheck="false"' +
                '    placeholder="&lt;svg viewBox=&quot;0 0 24 24&quot;&gt;…&lt;/svg&gt;"></textarea>' +
                '  <p class="lk-svg-hint">Paste the whole &lt;svg&gt; tag. Remove any width and height so it takes the icon size, and use <code>fill="currentColor"</code> so it takes the icon colour.</p>' +
                '  <div class="lk-svg-editor-actions">' +
                '    <button type="button" class="lk-btn lk-btn-primary lk-svg-apply">Use this</button>' +
                '    <button type="button" class="lk-btn lk-svg-cancel">Cancel</button>' +
                '    <span class="lk-svg-error"></span>' +
                '  </div>' +
                '</div>'
            );
            $ed.find( '.lk-svg-code' ).val( existing );
            $field.append( $ed );
            $ed.find( '.lk-svg-code' ).focus();
        });

        $panel.off( 'click', '.lk-svg-cancel' ).on( 'click', '.lk-svg-cancel', function( e ) {
            e.stopPropagation();
            $( this ).closest( '.lk-svg-editor' ).remove();
        });

        $panel.off( 'click', '.lk-svg-apply' ).on( 'click', '.lk-svg-apply', function( e ) {
            e.stopPropagation();
            var $ed = $( this ).closest( '.lk-svg-editor' );
            var $field = $( this ).closest( '.lk-field-icon-picker' );
            var code = String( $ed.find( '.lk-svg-code' ).val() || '' ).trim();
            var $err = $ed.find( '.lk-svg-error' );

            // Enough of a check to catch a paste that went wrong. The server
            // sanitises what is saved; this is only so a mistake is caught
            // here rather than showing up as an empty box on the page.
            if ( ! code ) { $err.text( 'Nothing to use.' ); return; }
            if ( code.indexOf( '<svg' ) === -1 || code.indexOf( '</svg>' ) === -1 ) {
                $err.text( 'That does not look like an <svg> tag.' ); return;
            }
            if ( /<script|onload=|onerror=|javascript:/i.test( code ) ) {
                $err.text( 'Scripts are not allowed inside an icon.' ); return;
            }
            $err.text( '' );

            var svgIcon = { library: 'svg', value: { svg: code } };
            $field.data( 'icon-val', svgIcon );
            var $preview = $field.find( '.lk-icon-preview-box' );
            $preview.html( code );
            if ( ! $preview.find( '.lk-icon-remove-btn' ).length ) {
                $preview.append( '<button type="button" class="lk-icon-remove-btn" title="Remove icon">&times;</button>' );
            }
            if ( $field.closest( '.lk-repeater-row' ).length ) {
                collectAndTriggerRepeater( $field.closest( '.lk-field-repeater' ) );
            } else {
                var name = $field.data( 'name' );
                if ( onControlChange ) onControlChange( name, svgIcon, false );
            }
            $ed.remove();
        });

        $panel.off( 'click', '.lk-icon-upload-svg-btn' ).on( 'click', '.lk-icon-upload-svg-btn', function( e ) {
            e.stopPropagation();
            var $field = $( this ).closest( '.lk-field-icon-picker' );
            $field.find( '.lk-icon-file-input' ).click();
        });

        $panel.off( 'change', '.lk-icon-file-input' ).on( 'change', '.lk-icon-file-input', function() {
            var input = this;
            if ( ! input.files || ! input.files[0] ) return;
            var file = input.files[0];
            var $field = $( this ).closest( '.lk-field-icon-picker' );

            var formData = new FormData();
            formData.append( 'action', 'loopkit_upload_svg' );
            formData.append( 'nonce', ( window.loopkitEditor && window.loopkitEditor.editorNonce ) || '' );
            formData.append( 'svg_file', file );

            var ajaxUrl = ( window.loopkitEditor && window.loopkitEditor.ajaxUrl ) || '/wp-admin/admin-ajax.php';
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function( res ) {
                    if ( res && res.success && res.data ) {
                        var svgIcon = {
                            library: 'svg',
                            value: res.data.value
                        };
                        $field.data( 'icon-val', svgIcon );
                        var svgHtml = ( res.data.value && res.data.value.svg ) ? res.data.value.svg : '<img src="' + escapeHtml( res.data.value.url ) + '" class="lk-svg-img" alt="">';
                        var $preview = $field.find( '.lk-icon-preview-box' );
                        $preview.html( svgHtml );
                        if ( ! $preview.find( '.lk-icon-remove-btn' ).length ) {
                            $preview.append( '<button type="button" class="lk-icon-remove-btn" title="Remove icon">&times;</button>' );
                        }
                        if ( $field.closest( '.lk-repeater-row' ).length ) {
                            collectAndTriggerRepeater( $field.closest( '.lk-field-repeater' ) );
                        } else {
                            var name = $field.data( 'name' );
                            if ( onControlChange ) onControlChange( name, svgIcon, false );
                        }
                    } else {
                        console.error( ( res && res.data && res.data.message ) || 'Error uploading SVG.' );
                    }
                },
                error: function() {
                    console.error( 'Failed to upload SVG.' );
                }
            });
            $( this ).val( '' );
        });

        // WYSIWYG Initialization
        $panel.find( '.lk-wysiwyg-textarea' ).each( function() {
            var $textarea = $( this );
            var id = $textarea.attr( 'id' );
            if ( ! id || $textarea.data( 'wysiwyg-init' ) ) return;
            $textarea.data( 'wysiwyg-init', true );
            var name = $textarea.data( 'name' );

            if ( typeof wp !== 'undefined' && wp.editor && wp.editor.initialize ) {
                var debounceTimer = null;
                try {
                    wp.editor.initialize( id, {
                        tinymce: {
                            wpautop: true,
                            plugins: 'charmap,colorpicker,hr,lists,paste,tabfocus,textcolor,fullscreen,wordpress,wpautoresize,wpeditimage,wpemoji,wpgallery,wplink,wpdialogs,wptextpattern,wpview',
                            toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv',
                            toolbar2: 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
                            setup: function( ed ) {
                                ed.on( 'input keyup NodeChange', function() {
                                    clearTimeout( debounceTimer );
                                    debounceTimer = setTimeout( function() {
                                        var content = ed.getContent();
                                        $textarea.val( content );
                                        if ( onControlChange ) onControlChange( name, content, false, true );
                                    }, 300 );
                                });
                                ed.on( 'change blur', function() {
                                    clearTimeout( debounceTimer );
                                    var content = ed.getContent();
                                    $textarea.val( content );
                                    if ( onControlChange ) onControlChange( name, content, false, false );
                                });
                            }
                        },
                        quicktags: true,
                        mediaButtons: true
                    });
                } catch ( err ) {
                    console.warn( 'Loopkit WYSIWYG init fallback:', err );
                }
            }

            $textarea.off( 'input' ).on( 'input', function() {
                var val = $( this ).val();
                if ( onControlChange ) onControlChange( name, val, false, true );
            });
            $textarea.off( 'change' ).on( 'change', function() {
                var val = $( this ).val();
                if ( onControlChange ) onControlChange( name, val, false, false );
            });
        });

        // Image Size change
        $panel.off( 'change', '.lk-image-size-select' ).on( 'change', '.lk-image-size-select', function() {
            var $field = $( this ).closest( '.lk-field-image-size' );
            var name = $field.data( 'name' );
            var val = $( this ).val();
            var isCustom = ( val === 'custom' );
            $field.find( '.lk-image-size-custom-fields' ).toggle( isCustom );

            var customDim = {
                width:  $field.find( '.lk-image-size-custom-w' ).val() || '',
                height: $field.find( '.lk-image-size-custom-h' ).val() || ''
            };

            var outVal = isCustom ? { size: 'custom', custom_dimension: customDim } : val;

            if ( $field.closest( '.lk-repeater-row' ).length ) {
                collectAndTriggerRepeater( $field.closest( '.lk-field-repeater' ) );
            } else {
                if ( onControlChange ) onControlChange( name, outVal, false );
            }
        });

        // Custom dimension inputs
        $panel.off( 'input change', '.lk-image-size-custom-w, .lk-image-size-custom-h' ).on( 'input change', '.lk-image-size-custom-w, .lk-image-size-custom-h', function() {
            var $field = $( this ).closest( '.lk-field-image-size' );
            var name = $field.data( 'name' );
            var customDim = {
                width:  $field.find( '.lk-image-size-custom-w' ).val() || '',
                height: $field.find( '.lk-image-size-custom-h' ).val() || ''
            };
            var outVal = { size: 'custom', custom_dimension: customDim };

            if ( $field.closest( '.lk-repeater-row' ).length ) {
                collectAndTriggerRepeater( $field.closest( '.lk-field-repeater' ) );
            } else {
                if ( onControlChange ) onControlChange( name, outVal, false );
            }
        });

        // Close popovers and device dropdowns on click outside or Esc
        $( document ).off( 'click.lk-popovers' ).on( 'click.lk-popovers', function( e ) {
            if ( ! $( e.target ).closest( '.lk-responsive-indicator' ).length ) {
                $( '.lk-res-dropdown' ).hide();
            }
            if ( ! $( e.target ).closest( '.lk-field-color, .lk-field-typography, .lk-field-url, .lk-field-popover, .lk-popover-panel' ).length ) {
                $( '.lk-color-popover, .lk-color-globals-menu, .lk-typo-popover, .lk-url-popover, .lk-popover-panel' ).hide();
                $( '.lk-url-gear-btn' ).removeClass( 'lk-gear-active' );
            }
        });

        $( document ).off( 'keydown.lk-popovers' ).on( 'keydown.lk-popovers', function( e ) {
            if ( e.key === 'Escape' || e.keyCode === 27 ) {
                $( '.lk-color-popover, .lk-color-globals-menu, .lk-typo-popover, .lk-url-popover, .lk-popover-panel, .lk-res-dropdown' ).hide();
                $( '.lk-url-gear-btn' ).removeClass( 'lk-gear-active' );
            }
        });
    
    };

    /**
     * Dynamically update control visibility and subtab dots in place without re-rendering panel DOM.
     */
    LoopkitControls.updateConditions = function( $panel, settings, device ) {
        if ( ! $panel || ! $panel.length ) return;

        $panel.find( '.lk-control-wrap[data-condition], .lk-control-section[data-condition]' ).each( function() {
            var $el = $( this );
            var condData = $el.attr( 'data-condition' );
            if ( ! condData ) return;
            var cond = {};
            try {
                cond = JSON.parse( condData );
            } catch ( e ) {
                return;
            }

            var isHidden = ! LoopkitControls.evaluateCondition( cond, settings, device );

            if ( isHidden ) {
                $el.addClass( 'lk-hidden' );
            } else {
                $el.removeClass( 'lk-hidden' );
            }
        });

        // Update subtab dots dynamically
        $panel.find( '.lk-control-subtabs' ).each( function() {
            var $subtabs = $( this );
            $subtabs.find( '.lk-subtab-btn' ).each( function() {
                var $btn = $( this );
                var paneId = $btn.data( 'pane' );
                var $pane = $subtabs.find( '.lk-subtab-pane[data-pane-id="' + paneId + '"]' );
                if ( ! $pane.length ) return;

                var hasValue = false;
                $pane.find( '.lk-control-wrap:not(.lk-hidden)' ).each( function() {
                    var $cWrap = $( this );
                    var cName = $cWrap.data( 'control' ) || '';

                    // Check if setting is gradient
                    if ( ( cName.indexOf( 'background_type' ) !== -1 || cName.indexOf( 'background_overlay_type' ) !== -1 ) &&
                         $cWrap.find( '.lk-choose-btn[data-value="gradient"].lk-choose-active' ).length ) {
                        hasValue = true;
                    }
                    // Border type
                    if ( cName.indexOf( 'border_type' ) !== -1 ) {
                        var bSel = $cWrap.find( '.lk-input-select' ).val();
                        if ( bSel && bSel !== 'none' && bSel !== 'default' ) hasValue = true;
                    }
                    // Color hex
                    var hex = $cWrap.find( '.lk-color-hex' ).val();
                    if ( hex && hex.trim() !== '' ) hasValue = true;
                    if ( $cWrap.find( '.lk-color-global-tag' ).length ) hasValue = true;
                    // Media url
                    var mUrl = $cWrap.find( '.lk-media-url' ).val();
                    if ( mUrl && mUrl.trim() !== '' ) hasValue = true;
                    // Slider
                    var sVal = $cWrap.find( '.lk-slider-input' ).val();
                    if ( sVal !== undefined && sVal !== '' && sVal !== null && sVal !== '0' && sVal !== 0 ) hasValue = true;
                    // Dimensions
                    $cWrap.find( '.lk-dim-input' ).each( function() {
                        var dv = $( this ).val();
                        if ( dv && dv.trim() !== '' ) hasValue = true;
                    });
                    // Other inputs
                    $cWrap.find( '.lk-input-text, .lk-input-number' ).each( function() {
                        var v = $( this ).val();
                        if ( v && v.trim() !== '' ) hasValue = true;
                    });
                    // Popovers
                    if ( $cWrap.find( '.lk-popover-active' ).length ) hasValue = true;
                });

                if ( hasValue ) {
                    $btn.addClass( 'lk-subtab-has-value' );
                    if ( ! $btn.find( '.lk-subtab-dot' ).length ) {
                        $btn.append( '<span class="lk-subtab-dot"></span>' );
                    }
                } else {
                    $btn.removeClass( 'lk-subtab-has-value' );
                    $btn.find( '.lk-subtab-dot' ).remove();
                }
            });
        });
    };

    /**
     * Destroy WYSIWYG editors inside a scope before re-rendering
     */
    LoopkitControls.destroyWysiwyg = function( $scope ) {
        $scope = $scope || $( '#lk-controls-container' );
        if ( ! $scope || ! $scope.length ) return;
        if ( typeof wp === 'undefined' || ! wp.editor || typeof wp.editor.remove !== 'function' ) return;
        $scope.find( '.lk-wysiwyg-textarea' ).each( function() {
            var id = $( this ).attr( 'id' );
            if ( id ) {
                try {
                    wp.editor.remove( id );
                } catch ( e ) {}
            }
        });
    };

    /* =========================================================
       LOOPKIT ICONS & MODAL SYSTEM
       ========================================================= */
    var iconsDataCache = null;
    var iconsLoadingPromise = null;

    var LoopkitIcons = {
        loadIcons: function( callback ) {
            if ( iconsDataCache ) {
                if ( callback ) callback( iconsDataCache );
                return;
            }
            if ( iconsLoadingPromise ) {
                if ( iconsLoadingPromise.done ) {
                    iconsLoadingPromise.done( function( data ) {
                        if ( callback ) callback( data );
                    });
                }
                return;
            }
            var url = ( window.loopkitEditor && window.loopkitEditor.iconsUrl )
                ? window.loopkitEditor.iconsUrl + 'icons.json'
                : '/wp-content/plugins/loopkit/assets/lib/font-awesome/icons.json';

            try {
                var req = null;
                if ( typeof $ !== 'undefined' && $ && typeof $.getJSON === 'function' ) {
                    req = $.getJSON( url );
                } else if ( typeof $ !== 'undefined' && $ && typeof $.ajax === 'function' ) {
                    req = $.ajax({ url: url, dataType: 'json' });
                }
                if ( req && typeof req.done === 'function' ) {
                    iconsLoadingPromise = req;
                    req.done( function( data ) {
                        iconsDataCache = data || {};
                        if ( callback ) callback( iconsDataCache );
                    });
                    if ( typeof req.fail === 'function' ) {
                        req.fail( function() {
                            iconsDataCache = {};
                            if ( callback ) callback( iconsDataCache );
                        });
                    }
                } else {
                    iconsDataCache = {};
                    if ( callback ) callback( iconsDataCache );
                }
            } catch ( err ) {
                console.warn( 'Loopkit: loadIcons error:', err );
                iconsDataCache = {};
                if ( callback ) callback( iconsDataCache );
            }
        },
        getPreviewSvg: function( style, name ) {
            try {
                // Both arguments are coerced: callers in the canvas have historically
                // passed the whole { library, value } object as a single argument,
                // which made this line throw and left the user with a blank icon.
                style = ( typeof style === 'string' && style ? style : 'solid' ).replace( /^fa-/, '' );
                name = ( typeof name === 'string' ? name : ( name && name.name ) || '' ).toLowerCase();
                if ( ! iconsDataCache ) {
                    LoopkitIcons.loadIcons();
                    return '';
                }
                var key = style + '/' + name;
                var item = iconsDataCache[ key ];
                if ( ! item ) {
                    for ( var k in iconsDataCache ) {
                        if ( iconsDataCache[k] && iconsDataCache[k].name === name ) {
                            item = iconsDataCache[k];
                            break;
                        }
                    }
                }
                if ( item && item.path ) {
                    var vb = item.viewBox || '0 0 512 512';
                    return '<svg aria-hidden="true" class="lk-svg-icon" viewBox="' + escapeHtml( vb ) + '" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="' + escapeHtml( item.path ) + '"></path></svg>';
                }
            } catch ( e ) {
                console.warn( 'Loopkit: getPreviewSvg error:', e );
            }
            return '';
        },
        render: function( icon ) {
            if ( ! icon ) return '';
            var lib = icon.library || 'fa-solid';
            var val = icon.value || '';
            if ( lib === 'svg' ) {
                if ( typeof val === 'object' && val.svg ) return val.svg;
                if ( typeof val === 'object' && val.url ) return '<img src="' + escapeHtml( val.url ) + '" class="lk-svg-img" alt="">';
                if ( typeof val === 'string' && val.indexOf( '<svg' ) !== -1 ) return val;
                if ( typeof val === 'string' && val ) return '<img src="' + escapeHtml( val ) + '" class="lk-svg-img" alt="">';
                return '';
            }
            var style = lib.replace( /^fa-/, '' );
            var name = typeof val === 'object' ? ( val.name || '' ) : val;
            return LoopkitIcons.getPreviewSvg( style, name );
        }
    };
    window.LoopkitIcons = LoopkitIcons;

    /**
     * Open Font Awesome Free Icon Library Modal
     */
    LoopkitControls.openIconLibrary = function( currentIcon, onSelect ) {
        var $modal = $( '#lk-icon-library-modal' );
        if ( ! $modal.length ) {
            var modalHtml = [
                '<div id="lk-icon-library-modal" class="lk-icon-modal-overlay" style="display:none;">',
                '  <div class="lk-icon-modal-dialog">',
                '    <div class="lk-icon-modal-header">',
                '      <div class="lk-icon-modal-title">Icon Library</div>',
                '      <button type="button" class="lk-icon-modal-close" title="Close">&times;</button>',
                '    </div>',
                '    <div class="lk-icon-modal-body">',
                '      <div class="lk-icon-modal-sidebar">',
                '        <ul class="lk-icon-modal-tabs">',
                '          <li class="lk-icon-tab lk-active" data-tab="all">All Icons</li>',
                '          <li class="lk-icon-tab" data-tab="solid">Solid</li>',
                '          <li class="lk-icon-tab" data-tab="regular">Regular</li>',
                '          <li class="lk-icon-tab" data-tab="brands">Brands</li>',
                '        </ul>',
                '      </div>',
                '      <div class="lk-icon-modal-main">',
                '        <div class="lk-icon-search-bar">',
                '          <span class="dashicons dashicons-search"></span>',
                '          <input type="text" class="lk-icon-search-input" placeholder="Search icon...">',
                '        </div>',
                '        <div class="lk-icon-grid-container">',
                '          <div class="lk-icon-loading">Loading icons...</div>',
                '          <div class="lk-icon-grid" style="display:none;"></div>',
                '          <div class="lk-icon-empty" style="display:none;">No icons found matching your search.</div>',
                '        </div>',
                '      </div>',
                '    </div>',
                '    <div class="lk-icon-modal-footer">',
                '      <div class="lk-icon-selected-info"></div>',
                '      <button type="button" class="lk-btn lk-btn-secondary lk-icon-modal-cancel">Cancel</button>',
                '      <button type="button" class="lk-btn lk-btn-primary lk-icon-modal-insert" disabled>Insert</button>',
                '    </div>',
                '  </div>',
                '</div>'
            ].join('\n');
            $( 'body' ).append( modalHtml );
            $modal = $( '#lk-icon-library-modal' );
        }

        var activeTab = 'all';
        var searchQuery = '';
        var selectedIcon = null;
        var renderedKeys = [];
        var BATCH_SIZE = 120;
        var currentRenderCount = 0;

        if ( currentIcon ) {
            if ( typeof currentIcon === 'object' && currentIcon.value ) {
                var cLib = ( currentIcon.library || 'fa-solid' ).replace( /^fa-/, '' );
                var cVal = typeof currentIcon.value === 'object' ? ( currentIcon.value.name || '' ) : currentIcon.value;
                selectedIcon = { library: 'fa-' + cLib, style: cLib, name: cVal };
            } else if ( typeof currentIcon === 'string' && currentIcon ) {
                selectedIcon = { library: 'fa-solid', style: 'solid', name: currentIcon };
            }
        }

        function updateSelectedState() {
            var $insBtn = $modal.find( '.lk-icon-modal-insert' );
            var $info = $modal.find( '.lk-icon-selected-info' );
            if ( selectedIcon && selectedIcon.name ) {
                $insBtn.prop( 'disabled', false );
                $info.html( 'Selected: <strong>' + escapeHtml( selectedIcon.name ) + '</strong> (' + escapeHtml( selectedIcon.style || 'solid' ) + ')' );
                $modal.find( '.lk-icon-item' ).removeClass( 'lk-selected' );
                $modal.find( '.lk-icon-item[data-name="' + escapeHtml( selectedIcon.name ) + '"][data-style="' + escapeHtml( selectedIcon.style ) + '"]' ).addClass( 'lk-selected' );
            } else {
                $insBtn.prop( 'disabled', true );
                $info.empty();
                $modal.find( '.lk-icon-item' ).removeClass( 'lk-selected' );
            }
        }

        function filterKeys( icons ) {
            var q = searchQuery.toLowerCase().trim();
            var matches = [];
            for ( var key in icons ) {
                var icon = icons[ key ];
                if ( activeTab !== 'all' && icon.style !== activeTab ) {
                    continue;
                }
                if ( q ) {
                    var matchName = icon.name.toLowerCase().indexOf( q ) !== -1;
                    var matchLabel = icon.label && icon.label.toLowerCase().indexOf( q ) !== -1;
                    if ( ! matchName && ! matchLabel ) continue;
                }
                matches.push( key );
            }
            return matches;
        }

        function renderBatch( icons, isInitial ) {
            var $grid = $modal.find( '.lk-icon-grid' );
            var $empty = $modal.find( '.lk-icon-empty' );

            if ( isInitial ) {
                $grid.empty();
                currentRenderCount = 0;
            }

            if ( ! renderedKeys.length ) {
                $grid.hide();
                $empty.show();
                return;
            }

            $empty.hide();
            $grid.show();

            var start = currentRenderCount;
            var end = Math.min( start + BATCH_SIZE, renderedKeys.length );

            var frag = document.createDocumentFragment();
            for ( var i = start; i < end; i++ ) {
                var key = renderedKeys[i];
                var icon = icons[ key ];
                var itemEl = document.createElement( 'div' );
                itemEl.className = 'lk-icon-item' + ( selectedIcon && selectedIcon.name === icon.name && selectedIcon.style === icon.style ? ' lk-selected' : '' );
                itemEl.setAttribute( 'data-name', icon.name );
                itemEl.setAttribute( 'data-style', icon.style );
                itemEl.setAttribute( 'data-library', 'fa-' + icon.style );

                var vb = icon.viewBox || '0 0 512 512';
                itemEl.innerHTML = '<svg aria-hidden="true" class="lk-svg-icon" viewBox="' + vb + '" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="' + icon.path + '"></path></svg><span class="lk-icon-item-label">' + escapeHtml( icon.label || icon.name ) + '</span>';
                frag.appendChild( itemEl );
            }

            $grid[0].appendChild( frag );
            currentRenderCount = end;
        }

        LoopkitIcons.loadIcons( function( icons ) {
            $modal.find( '.lk-icon-loading' ).hide();
            renderedKeys = filterKeys( icons );
            renderBatch( icons, true );
            updateSelectedState();

            // Infinite scroll on grid container
            var $container = $modal.find( '.lk-icon-grid-container' );
            $container.off( 'scroll.iconscroll' ).on( 'scroll.iconscroll', function() {
                if ( currentRenderCount < renderedKeys.length ) {
                    var el = this;
                    if ( el.scrollHeight - el.scrollTop - el.clientHeight < 200 ) {
                        renderBatch( icons, false );
                    }
                }
            });

            // Search filter with debounce
            var searchTimer = null;
            $modal.find( '.lk-icon-search-input' ).off( 'input' ).on( 'input', function() {
                var val = $( this ).val();
                clearTimeout( searchTimer );
                searchTimer = setTimeout( function() {
                    searchQuery = val;
                    renderedKeys = filterKeys( icons );
                    renderBatch( icons, true );
                    updateSelectedState();
                }, 150 );
            });

            // Tab click
            $modal.find( '.lk-icon-tab' ).off( 'click' ).on( 'click', function() {
                $modal.find( '.lk-icon-tab' ).removeClass( 'lk-active' );
                $( this ).addClass( 'lk-active' );
                activeTab = $( this ).data( 'tab' );
                renderedKeys = filterKeys( icons );
                renderBatch( icons, true );
                updateSelectedState();
            });

            // Item click
            $modal.find( '.lk-icon-grid' ).off( 'click', '.lk-icon-item' ).on( 'click', '.lk-icon-item', function() {
                var name = $( this ).data( 'name' );
                var style = $( this ).data( 'style' );
                selectedIcon = { library: 'fa-' + style, style: style, name: name };
                updateSelectedState();
            });

            // Double click to insert immediately
            $modal.find( '.lk-icon-grid' ).off( 'dblclick', '.lk-icon-item' ).on( 'dblclick', '.lk-icon-item', function() {
                var name = $( this ).data( 'name' );
                var style = $( this ).data( 'style' );
                if ( onSelect ) {
                    onSelect({ library: 'fa-' + style, value: name });
                }
                $modal.hide();
            });

            // Insert button
            $modal.find( '.lk-icon-modal-insert' ).off( 'click' ).on( 'click', function() {
                if ( selectedIcon && selectedIcon.name && onSelect ) {
                    onSelect({ library: selectedIcon.library, value: selectedIcon.name });
                }
                $modal.hide();
            });
        });

        // Close handlers
        $modal.find( '.lk-icon-modal-close, .lk-icon-modal-cancel' ).off( 'click' ).on( 'click', function() {
            $modal.hide();
        });
        $modal.off( 'click' ).on( 'click', function( e ) {
            if ( $( e.target ).is( '#lk-icon-library-modal' ) ) {
                $modal.hide();
            }
        });

        $modal.show();
        $modal.find( '.lk-icon-search-input' ).val('').focus();
    };

    /**
     * Escape HTML helper
     */
    function escapeHtml( str ) {
        if ( typeof str !== 'string' ) return String( str !== undefined && str !== null ? str : '' );
        return str
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#039;' );
    }

    window.LoopkitControls = LoopkitControls;

})( jQuery, window );
