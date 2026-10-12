/**
 * Loopkit Canvas JavaScript
 * Runs inside the canvas iframe: handles selection, handles, inline editing, drag/drop, and postMessage bridge with editor.
 *
 * @package Loopkit
 */

(function() {
    'use strict';

    /* =========================================================
       The canvas must never navigate. A link inside any widget (a menu item, a button, a
       post title, an anchor in text) used to load that page into the canvas frame and the
       design disappeared from view. Capture phase, so it runs before anything else and
       covers widgets added later.
       ========================================================= */
    document.addEventListener( 'click', function( e ) {
        var link = e.target && e.target.closest ? e.target.closest( 'a[href]' ) : null;
        if ( ! link ) return;
        var href = link.getAttribute( 'href' ) || '';
        // Editor UI links (handles, "Edit Header" tabs) opt out with data-lk-allow-nav.
        if ( link.hasAttribute( 'data-lk-allow-nav' ) ) return;
        if ( href === '' || href.charAt( 0 ) === '#' ) { e.preventDefault(); return; }
        e.preventDefault();
        e.stopPropagation();
    }, true );

    // Same for forms: never submit from the canvas.
    document.addEventListener( 'submit', function( e ) {
        if ( e.target && e.target.closest && ! e.target.closest( '[data-lk-allow-submit]' ) ) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true );


    // The editor publishes its config as window.loopkitEditor (and calls it LK internally).
    // Older canvas code reads window.parent.LK, so expose the same object under that name.
    try {
        if ( window.parent && window.parent !== window && ! window.parent.LK && window.parent.loopkitEditor ) {
            window.parent.LK = window.parent.loopkitEditor;
        }
    } catch ( e ) {}

    var activeDropTarget = null;
    var dropIndicatorLine = null;
    var draggedMoveId = null;
    var dragDropInitialized = false;
    var currentElements = [];

    /**
     * Send a message to the parent editor window.
     */
    function sendToEditor( type, data ) {
        if ( window.parent && window.parent !== window ) {
            var msg = Object.assign( { source: 'loopkit-canvas', type: type }, data || {} );
            window.parent.postMessage( msg, '*' );
        }
    }

    /**
     * Listen for messages from the parent editor window.
     */
    window.addEventListener( 'message', function( event ) {
        var msg = event.data;
        if ( ! msg || typeof msg !== 'object' ) return;

        switch ( msg.type ) {
            case 'update-elements':
                renderElements( msg.elements );
                if ( msg.selectedId ) {
                    selectElement( msg.selectedId, true );
                }
                break;

            case 'update-dynamic-css':
                var styleTag = document.getElementById( 'lk-live-css' );
                if ( ! styleTag ) {
                    styleTag = document.createElement( 'style' );
                    styleTag.id = 'lk-live-css';
                    document.head.appendChild( styleTag );
                }
                styleTag.textContent = msg.css || '';
                break;

            case 'select-element':
                if ( msg.elementId ) {
                    selectElement( msg.elementId );
                } else {
                    deselectAll();
                }
                break;

            case 'preview-animation':
                var animTarget = document.querySelector( '[data-id="' + msg.elementId + '"], [data-lk-id="' + msg.elementId + '"]' );
                if ( animTarget ) {
                    var toRemove = [];
                    for ( var cl = 0; cl < animTarget.classList.length; cl++ ) {
                        var cName = animTarget.classList[cl];
                        if ( cName.indexOf( 'lk-anim' ) === 0 ) {
                            toRemove.push( cName );
                        }
                    }
                    for ( var tr = 0; tr < toRemove.length; tr++ ) {
                        animTarget.classList.remove( toRemove[tr] );
                    }

                    if ( msg.animation && msg.animation !== 'none' ) {
                        var durMap = { slow: '2s', normal: '1.25s', fast: '0.75s' };
                        var durVal = durMap[ msg.duration ] || ( ! isNaN( msg.duration ) ? msg.duration + 's' : '1.25s' );
                        var delayVal = ( parseInt( msg.delay, 10 ) || 0 ) + 'ms';
                        animTarget.style.setProperty( '--lk-anim-duration', durVal );
                        animTarget.style.setProperty( '--lk-anim-delay', delayVal );

                        requestAnimationFrame( function() {
                            requestAnimationFrame( function() {
                                animTarget.classList.add( 'lk-anim-' + msg.animation );
                            } );
                        } );
                    }
                }
                break;

            case 'update-element-title':
                var hWrap = document.querySelector( '[data-id="' + msg.elementId + '"], [data-lk-id="' + msg.elementId + '"]' );
                if ( hWrap ) {
                    var titleEl = hWrap.querySelector( '.lk-heading-title' );
                    if ( titleEl ) {
                        var linkEl = titleEl.querySelector( 'a' );
                        if ( linkEl ) {
                            linkEl.textContent = msg.title || '';
                        } else {
                            titleEl.textContent = msg.title || '';
                        }
                    }
                }
                break;

            case 'update-element-handle-title':
                var hTarget = document.querySelector( '[data-id="' + msg.elementId + '"], [data-lk-id="' + msg.elementId + '"]' );
                if ( hTarget ) {
                    var handleLabel = hTarget.querySelector( ':scope > .lk-element-handle > .lk-handle-label' );
                    if ( handleLabel ) {
                        handleLabel.textContent = msg.title || hTarget.getAttribute( 'data-element_type' ) || 'Element';
                    }
                }
                break;

            case 'scroll-to-element':
                var scrollTarget = document.querySelector( '[data-id="' + msg.elementId + '"], [data-lk-id="' + msg.elementId + '"]' );
                if ( scrollTarget ) {
                    scrollTarget.scrollIntoView( { behavior: 'smooth', block: 'center' } );
                }
                break;

            case 'hover-element':
                var hoverTarget = document.querySelector( '[data-id="' + msg.elementId + '"], [data-lk-id="' + msg.elementId + '"]' );
                if ( hoverTarget ) {
                    hoverTarget.classList.add( 'lk-canvas-hover-outline' );
                }
                break;

            case 'unhover-element':
                var allHovered = document.querySelectorAll( '.lk-canvas-hover-outline' );
                for ( var h = 0; h < allHovered.length; h++ ) {
                    allHovered[h].classList.remove( 'lk-canvas-hover-outline' );
                }
                break;

            case 'toggle-element-hidden':
                var hiddenTarget = document.querySelector( '[data-id="' + msg.elementId + '"], [data-lk-id="' + msg.elementId + '"]' );
                if ( hiddenTarget ) {
                    hiddenTarget.classList.toggle( 'lk-editor-hidden', !! msg.hidden );
                }
                break;

            case 'preview-revision':
                document.body.classList.add( 'lk-canvas-preview-mode' );
                renderElements( msg.elements );
                break;

            case 'exit-revision-preview':
                document.body.classList.remove( 'lk-canvas-preview-mode' );
                renderElements( msg.elements );
                break;

            case 'update-sticky-badges':
                updateStickyBadges();
                break;

            case 'update-site-settings-css':
                var ssTag = document.getElementById( 'lk-site-settings-preview' );
                if ( ! ssTag ) {
                    ssTag = document.createElement( 'style' );
                    ssTag.id = 'lk-site-settings-preview';
                    document.head.appendChild( ssTag );
                }
                ssTag.textContent = msg.css || '';
                break;

            case 'set-theme':
                // Canvas iframe stays light (renders actual page content, not chrome)
                document.body.classList.remove( 'lk-theme-dark' );
                break;

            case 'toggle-editing-handles':
                document.body.classList.toggle( 'lk-hide-editing-handles', msg.hide === true );
                break;
        }
    });

    /**
     * Render elements into the canvas root.
     */
    function renderElements( elements ) {
        var root = document.getElementById( 'lk-canvas-root' );
        if ( ! root ) return;

        currentElements = elements || [];
        checkCanvasWidgetRenderers();

        var html = '';
        if ( elements && elements.length > 0 ) {
            for ( var i = 0; i < elements.length; i++ ) {
                html += renderElement( elements[i] );
            }
        } else {
            html += buildEmptyState();
        }

        // Add New Section area at the bottom of the page
        html += buildAddSectionArea();

        root.innerHTML = html;

        bindElementInteractions();
        bindBottomSectionActions();
        initDragDrop();
        updateStickyBadges();
        if ( window.LoopkitAnimations && typeof window.LoopkitAnimations.init === 'function' ) {
            window.LoopkitAnimations.init();
        }
    }

    /**
     * Check if an element model has sticky enabled
     */
    function isElementSticky( el ) {
        if ( ! el ) return false;
        var s = el.settings || {};
        return !! ( s.sticky && s.sticky !== 'none' );
    }

    /**
     * Check if an element model has scrolling effects enabled
     */
    function isElementHasScroll( el ) {
        if ( ! el ) return false;
        var s = el.settings || {};
        if ( s.scrolling_effects !== 'yes' && s.scrolling_effects !== true ) return false;
        return !! ( ( s.scroll_vertical === 'yes' || s.scroll_vertical === true ) ||
                    ( s.scroll_horizontal === 'yes' || s.scroll_horizontal === true ) ||
                    ( s.scroll_transparency === 'yes' || s.scroll_transparency === true ) ||
                    ( s.scroll_blur === 'yes' || s.scroll_blur === true ) ||
                    ( s.scroll_rotate === 'yes' || s.scroll_rotate === true ) ||
                    ( s.scroll_scale === 'yes' || s.scroll_scale === true ) );
    }

    /**
     * Check if an element model has mouse effects enabled
     */
    function isElementHasMouse( el ) {
        if ( ! el ) return false;
        var s = el.settings || {};
        if ( s.mouse_effects !== 'yes' && s.mouse_effects !== true ) return false;
        return !! ( ( s.mouse_track === 'yes' || s.mouse_track === true ) ||
                    ( s.mouse_tilt === 'yes' || s.mouse_tilt === true ) );
    }

    /**
     * Build HTML for Motion badges (Sticky, Scroll, Mouse)
     */
    function buildMotionBadgesHtml( el ) {
        var html = '';
        if ( isElementSticky( el ) ) {
            html += '  <span class="lk-sticky-badge">Sticky</span>';
        }
        if ( isElementHasScroll( el ) ) {
            html += '  <span class="lk-scroll-badge">Scroll</span>';
        }
        if ( isElementHasMouse( el ) ) {
            html += '  <span class="lk-mouse-badge">Mouse</span>';
        }
        return html;
    }

    /**
     * Update Motion badges (Sticky, Scroll, Mouse) on canvas element handles
     */
    function updateStickyBadges() {
        var elements = document.querySelectorAll( '.lk-element' );
        for ( var i = 0; i < elements.length; i++ ) {
            var el = elements[i];
            var handle = el.querySelector( ':scope > .lk-element-handle' );
            if ( ! handle ) continue;

            var elId = el.getAttribute( 'data-id' ) || el.getAttribute( 'data-lk-id' );
            var elModel = findElementModel( currentElements, elId );
            if ( ! elModel && window.parent && window.parent.LoopkitEditor && window.parent.LoopkitEditor.state ) {
                elModel = findElementModel( window.parent.LoopkitEditor.state.elements, elId );
            }

            var isSticky = false;
            var isScroll = false;
            var isMouse  = false;

            if ( elModel ) {
                isSticky = isElementSticky( elModel ) || ( elModel.master && isElementSticky( elModel.master ) );
                isScroll = isElementHasScroll( elModel ) || ( elModel.master && isElementHasScroll( elModel.master ) );
                isMouse  = isElementHasMouse( elModel ) || ( elModel.master && isElementHasMouse( elModel.master ) );
            } else {
                if ( el.classList.contains( 'lk-sticky' ) || el.classList.contains( 'lk-sticky--top' ) || el.classList.contains( 'lk-sticky--bottom' ) ) {
                    isSticky = true;
                }
                if ( el.classList.contains( 'lk-has-scroll-effects' ) ) {
                    isScroll = true;
                }
                if ( el.classList.contains( 'lk-has-mouse-effects' ) ) {
                    isMouse = true;
                }
            }

            var label = handle.querySelector( '.lk-handle-label' );

            // Sync Sticky badge
            var sBadge = handle.querySelector( '.lk-sticky-badge' );
            if ( isSticky && ! sBadge ) {
                sBadge = document.createElement( 'span' );
                sBadge.className = 'lk-sticky-badge';
                sBadge.textContent = 'Sticky';
                if ( label && label.nextSibling ) {
                    handle.insertBefore( sBadge, label.nextSibling );
                } else {
                    handle.appendChild( sBadge );
                }
            } else if ( ! isSticky && sBadge ) {
                sBadge.parentNode.removeChild( sBadge );
            }

            // Sync Scroll badge
            var scBadge = handle.querySelector( '.lk-scroll-badge' );
            if ( isScroll && ! scBadge ) {
                scBadge = document.createElement( 'span' );
                scBadge.className = 'lk-scroll-badge';
                scBadge.textContent = 'Scroll';
                if ( label && label.nextSibling ) {
                    handle.insertBefore( scBadge, label.nextSibling );
                } else {
                    handle.appendChild( scBadge );
                }
            } else if ( ! isScroll && scBadge ) {
                scBadge.parentNode.removeChild( scBadge );
            }

            // Sync Mouse badge
            var mBadge = handle.querySelector( '.lk-mouse-badge' );
            if ( isMouse && ! mBadge ) {
                mBadge = document.createElement( 'span' );
                mBadge.className = 'lk-mouse-badge';
                mBadge.textContent = 'Mouse';
                if ( label && label.nextSibling ) {
                    handle.insertBefore( mBadge, label.nextSibling );
                } else {
                    handle.appendChild( mBadge );
                }
            } else if ( ! isMouse && mBadge ) {
                mBadge.parentNode.removeChild( mBadge );
            }
        }
    }

    /**
     * Helper to locate an element model by ID recursively
     */
    function findElementModel( list, id ) {
        if ( ! list || ! Array.isArray( list ) ) return null;
        for ( var i = 0; i < list.length; i++ ) {
            if ( list[i].id === id ) return list[i];
            if ( list[i].elements && list[i].elements.length ) {
                var found = findElementModel( list[i].elements, id );
                if ( found ) return found;
            }
        }
        return null;
    }

    var CANVAS_SHAPES = {
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

    function getShapeSvg( shape ) {
        if ( window.parent && window.parent.LoopkitControls && typeof window.parent.LoopkitControls.getShapeSvg === 'function' ) {
            var svg = window.parent.LoopkitControls.getShapeSvg( shape );
            if ( svg ) return svg;
        }
        return CANVAS_SHAPES[ shape ] || '';
    }

    function parseCustomAttributes( raw ) {
        if ( ! raw || typeof raw !== 'string' ) return '';
        var lines = raw.split( /\r?\n/ );
        var out = '';
        var forbidden = [ 'class', 'id', 'style', 'data-id', 'data-element_type' ];
        for ( var i = 0; i < lines.length; i++ ) {
            var line = lines[i].trim();
            if ( ! line ) continue;
            var parts = line.split( '|' );
            var key = parts[0].trim().replace( /[^a-zA-Z0-9_\-]/g, '' );
            var val = parts.slice( 1 ).join( '|' ).trim();
            if ( ! key ) continue;
            var lowerKey = key.toLowerCase();
            if ( lowerKey.indexOf( 'on' ) === 0 ) continue;
            if ( forbidden.indexOf( lowerKey ) !== -1 ) continue;
            if ( val.toLowerCase().indexOf( 'javascript:' ) === 0 ) continue;
            out += ' ' + escapeHtml( key ) + '="' + escapeHtml( val ) + '"';
        }
        return out;
    }

    function hasBackgroundOverlay( settings ) {
        if ( ! settings ) return false;
        // Classic normal
        if ( settings.background_overlay_color && String( settings.background_overlay_color ).trim() !== '' ) return true;
        if ( settings.background_overlay_image ) {
            if ( typeof settings.background_overlay_image === 'object' && settings.background_overlay_image.url && String( settings.background_overlay_image.url ).trim() !== '' ) return true;
            if ( typeof settings.background_overlay_image === 'string' && settings.background_overlay_image.trim() !== '' ) return true;
        }
        // Gradient normal
        if ( settings.background_overlay_type === 'gradient' && ( settings.background_overlay_gradient_color || settings.background_overlay_gradient_second_color ) ) return true;

        // Classic hover
        if ( ( settings.background_overlay_color_hover && String( settings.background_overlay_color_hover ).trim() !== '' ) ||
             ( settings.background_overlay_hover_color && String( settings.background_overlay_hover_color ).trim() !== '' ) ) return true;
        var hovImg = settings.background_overlay_image_hover || settings.background_overlay_hover_image;
        if ( hovImg ) {
            if ( typeof hovImg === 'object' && hovImg.url && String( hovImg.url ).trim() !== '' ) return true;
            if ( typeof hovImg === 'string' && hovImg.trim() !== '' ) return true;
        }
        // Gradient hover
        var hovType = settings.background_overlay_type_hover || settings.background_overlay_hover_type || settings.background_overlay_hover_background;
        if ( hovType === 'gradient' && ( settings.background_overlay_gradient_color_hover || settings.background_overlay_hover_gradient_color || settings.background_overlay_gradient_second_color_hover || settings.background_overlay_hover_gradient_second_color ) ) return true;

        // Custom filters or non-normal blend mode
        if ( settings.background_overlay_blend_mode && settings.background_overlay_blend_mode !== 'normal' ) return true;
        if ( settings.background_overlay_css_filters && typeof settings.background_overlay_css_filters === 'object' ) {
            var f = settings.background_overlay_css_filters;
            if ( f.blur || ( f.brightness !== undefined && f.brightness !== '' && f.brightness != 100 ) ||
                 ( f.contrast !== undefined && f.contrast !== '' && f.contrast != 100 ) ||
                 ( f.saturation !== undefined && f.saturation !== '' && f.saturation != 100 ) || f.hue ) {
                return true;
            }
        }
        var hf = settings.background_overlay_css_filters_hover || settings.background_overlay_hover_css_filters;
        if ( hf && typeof hf === 'object' ) {
            if ( hf.blur || ( hf.brightness !== undefined && hf.brightness !== '' && hf.brightness != 100 ) ||
                 ( hf.contrast !== undefined && hf.contrast !== '' && hf.contrast != 100 ) ||
                 ( hf.saturation !== undefined && hf.saturation !== '' && hf.saturation != 100 ) || hf.hue ) {
                return true;
            }
        }

        if ( settings.background_overlay_opacity_hover !== undefined && settings.background_overlay_opacity_hover !== '' ) return true;
        if ( settings.background_overlay_hover_opacity !== undefined && settings.background_overlay_hover_opacity !== '' ) return true;
        if ( settings.background_overlay_hover_transition_duration !== undefined && settings.background_overlay_hover_transition_duration !== '' ) return true;

        return false;
    }

    /**
     * Build HTML for a single element.
     */
    function renderElement( el ) {
        if ( ! el || ! el.id ) return '';

        var elType = el.elType || '';
        if ( elType === 'section' ) {
            return renderSection( el );
        } else if ( elType === 'container' ) {
            return renderContainer( el );
        } else if ( elType === 'widget' ) {
            var widgetType = el.widgetType || '';
            el = resolveDynamicElement( el );
            if ( widgetRenderers && typeof widgetRenderers[ widgetType ] === 'function' ) {
                return widgetRenderers[ widgetType ]( el );
            } else {
                console.warn( 'Loopkit: No canvas renderer registered for widget "' + widgetType + '"!' );
                return renderFallbackWidget( el, widgetType );
            }
        } else if ( elType === 'global' ) {
            return renderGlobal( el );
        }
        return '';
    }

    /**
     * Render Section element (outer layer, root only)
     * Markup: <section class="lk-element lk-element-{id} lk-section lk-section-boxed|lk-section-full" data-id="{id}" data-element_type="section">.
     * Boxed sections put children inside <div class="lk-section-inner">.
     */
    function renderSection( el ) {
        var id       = escapeHtml( el.id );
        var settings = el.settings || {};
        var children = el.elements || [];
        var tag      = sanitizeTag( settings.html_tag || 'section', [ 'section', 'div', 'header', 'footer', 'article', 'main', 'aside', 'nav' ] );
        var isBoxed  = ( settings.content_width !== 'full' );
        var conType  = settings.container_type === 'grid' ? 'grid' : 'flex';
        var isSticky = isElementSticky( el );

        var classes = 'lk-element lk-element-' + id + ' lk-section ' +
                      ( isBoxed ? 'lk-section-boxed' : 'lk-section-full' ) +
                      ' lk-con--' + conType + ' lk-editable';

        if ( isSticky ) {
            classes += ' lk-sticky lk-sticky--' + escapeHtml( settings.sticky );
        }
        if ( settings.css_classes ) {
            classes += ' ' + escapeHtml( settings.css_classes );
        }
        if ( settings.entrance_animation && settings.entrance_animation !== 'none' ) {
            classes += ' lk-anim lk-anim-' + escapeHtml( settings.entrance_animation );
        }

        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<' + tag + ' class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="section" data-lk-type="section"' + idAttr + customAttrs + '>';

        // Background Overlay (rendered as first child of outer element)
        if ( hasBackgroundOverlay( settings ) ) {
            html += '<div class="lk-background-overlay"></div>';
        }

        // Shape Dividers
        if ( settings.shape_divider_top_type && settings.shape_divider_top_type !== 'none' ) {
            html += '<div class="lk-shape-divider lk-shape-divider--top">' + getShapeSvg( settings.shape_divider_top_type ) + '</div>';
        }
        if ( settings.shape_divider_bottom_type && settings.shape_divider_bottom_type !== 'none' ) {
            html += '<div class="lk-shape-divider lk-shape-divider--bottom">' + getShapeSvg( settings.shape_divider_bottom_type ) + '</div>';
        }

        // Section handle: top center, background #1E90E0, label "Section", Add, Edit, Duplicate, Delete
        html += '<div class="lk-element-handle lk-handle-section">';
        html += '  <button type="button" class="lk-handle-btn lk-handle-add" data-action="add-section-before" data-id="' + id + '" title="Add Section"><span class="dashicons dashicons-plus-alt2"></span></button>';
        html += '  <span class="lk-handle-label">' + escapeHtml( settings._title || 'Section' ) + '</span>';
        html += buildMotionBadgesHtml( el );
        html += '  <button type="button" class="lk-handle-btn lk-handle-edit" data-action="edit" data-id="' + id + '" draggable="true" title="Edit Section"><span class="dashicons dashicons-edit"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-duplicate" data-action="duplicate" data-id="' + id + '" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-save-template" data-action="save-as-template" data-id="' + id + '" title="Save as Template"><span class="dashicons dashicons-portfolio"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-delete" data-action="delete" data-id="' + id + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
        html += '</div>';

        // Boxed inner wrapper: .lk-section-inner
        if ( isBoxed ) {
            html += '<div class="lk-section-inner lk-con--' + conType + '">';
        }

        if ( children.length > 0 ) {
            for ( var i = 0; i < children.length; i++ ) {
                html += renderElement( children[i] );
            }
        } else {
            // Empty section drop zone
            html += '<div class="lk-container-empty" data-parent-id="' + id + '">';
            html += '  <span class="dashicons dashicons-plus-alt2"></span>';
            html += '  <span>Drop or add widget here</span>';
            html += '</div>';
        }

        if ( isBoxed ) {
            html += '</div>'; // .lk-section-inner
        }

        html += '</' + tag + '>';
        return html;
    }

    /**
     * Render Container element (inner layout block)
     * Markup: <div class="lk-element lk-element-{id} lk-con lk-container ...">
     */
    function renderContainer( el ) {
        var id       = escapeHtml( el.id );
        var settings = el.settings || {};
        var children = el.elements || [];
        var tag      = sanitizeTag( settings.html_tag || 'div', [ 'div', 'section', 'header', 'footer', 'article', 'main', 'aside', 'nav', 'a' ] );
        var isBoxed  = ( settings.content_width === 'boxed' );
        var conType  = settings.container_type === 'grid' ? 'grid' : 'flex';
        var isSticky = isElementSticky( el );

        var classes = 'lk-element lk-element-' + id + ' lk-con lk-container ' +
                      ( isBoxed ? 'lk-con-boxed lk-container-boxed' : 'lk-con-full lk-container-full' ) +
                      ' lk-con--' + conType + ' lk-editable';

        if ( isSticky ) {
            classes += ' lk-sticky lk-sticky--' + escapeHtml( settings.sticky );
        }
        if ( settings.css_classes ) {
            classes += ' ' + escapeHtml( settings.css_classes );
        }
        if ( settings.entrance_animation && settings.entrance_animation !== 'none' ) {
            classes += ' lk-anim lk-anim-' + escapeHtml( settings.entrance_animation );
        }

        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';

        var extraAttrs = '';
        if ( tag === 'a' && settings.link && settings.link.url ) {
            extraAttrs += ' href="' + escapeHtml( settings.link.url ) + '"';
            if ( settings.link.is_external ) extraAttrs += ' target="_blank"';
            if ( settings.link.nofollow ) extraAttrs += ' rel="nofollow"';
        }

        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<' + tag + ' class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="container" data-lk-type="container"' + idAttr + extraAttrs + customAttrs + '>';

        // Background Overlay (rendered as first child of outer element)
        if ( hasBackgroundOverlay( settings ) ) {
            html += '<div class="lk-background-overlay"></div>';
        }

        // Shape Dividers
        if ( settings.shape_divider_top_type && settings.shape_divider_top_type !== 'none' ) {
            html += '<div class="lk-shape-divider lk-shape-divider--top">' + getShapeSvg( settings.shape_divider_top_type ) + '</div>';
        }
        if ( settings.shape_divider_bottom_type && settings.shape_divider_bottom_type !== 'none' ) {
            html += '<div class="lk-shape-divider lk-shape-divider--bottom">' + getShapeSvg( settings.shape_divider_bottom_type ) + '</div>';
        }

        // Container handle: top left, background #38A8F5, label "Container", Add, Edit, Duplicate, Delete
        html += '<div class="lk-element-handle lk-handle-container">';
        html += '  <span class="lk-handle-label">' + escapeHtml( settings._title || 'Container' ) + '</span>';
        html += buildMotionBadgesHtml( el );
        html += '  <button type="button" class="lk-handle-btn lk-handle-add" data-action="add-container-inside" data-id="' + id + '" title="Add"><span class="dashicons dashicons-plus-alt2"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-edit" data-action="edit" data-id="' + id + '" draggable="true" title="Edit Container"><span class="dashicons dashicons-edit"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-duplicate" data-action="duplicate" data-id="' + id + '" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-save-template" data-action="save-as-template" data-id="' + id + '" title="Save as Template"><span class="dashicons dashicons-portfolio"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-delete" data-action="delete" data-id="' + id + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
        html += '</div>';

        // Boxed inner wrapper: .lk-con-inner
        if ( isBoxed ) {
            html += '<div class="lk-con-inner lk-container-inner lk-con--' + conType + '">';
        }

        if ( children.length > 0 ) {
            for ( var i = 0; i < children.length; i++ ) {
                html += renderElement( children[i] );
            }
        } else {
            // Empty container drop zone
            html += '<div class="lk-container-empty" data-parent-id="' + id + '">';
            html += '  <span class="dashicons dashicons-plus-alt2"></span>';
            html += '  <span>Drop or add widget here</span>';
            html += '</div>';
        }

        if ( isBoxed ) {
            html += '</div>'; // .lk-con-inner
        }

        html += '</' + tag + '>';
        return html;
    }

    /**
     * Render Global Section or Container element.
     */
    function renderGlobal( el ) {
        var id         = escapeHtml( el.id );
        var templateId = escapeHtml( String( el.templateId || '' ) );
        var globalType = el.globalType || 'section';
        var globalName = escapeHtml( el.globalName || 'Global' );
        var master     = el.master;

        if ( ! master ) {
            return '<div class="lk-element lk-element-' + id + ' lk-global lk-global-missing lk-editable" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="global" data-lk-type="global">' +
                   '  <div class="lk-global-missing-box">' +
                   '    <span class="dashicons dashicons-warning"></span>' +
                   '    <span class="lk-missing-text">Global section not found</span>' +
                   '    <button type="button" class="lk-btn lk-btn-sm lk-btn-danger lk-global-remove-btn" data-action="delete" data-id="' + id + '">Remove</button>' +
                   '  </div>' +
                   '</div>';
        }

        var masterId       = escapeHtml( master.id || '' );
        var masterSettings = master.settings || {};
        var masterChildren = master.elements || [];
        var isSection      = ( globalType === 'section' );
        var tag            = sanitizeTag( masterSettings.html_tag || ( isSection ? 'section' : 'div' ), [ 'section', 'div', 'header', 'footer', 'article', 'main', 'aside', 'nav', 'a' ] );
        var isBoxed        = isSection ? ( masterSettings.content_width !== 'full' ) : ( masterSettings.content_width === 'boxed' );
        var conType        = masterSettings.container_type === 'grid' ? 'grid' : 'flex';

        var baseCls = isSection ? ( isBoxed ? 'lk-section-boxed' : 'lk-section-full' ) : ( isBoxed ? 'lk-con-boxed lk-container-boxed' : 'lk-con-full lk-container-full' );
        var typeCls = isSection ? 'lk-section' : 'lk-con lk-container';

        var isSticky = isElementSticky( master ) || isElementSticky( el );
        var stickyPos = ( masterSettings && masterSettings.sticky ) || ( el.settings && el.settings.sticky ) || 'top';

        var classes = 'lk-element lk-element-' + id + ' lk-element-' + masterId + ' ' + typeCls + ' ' + baseCls + ' lk-con--' + conType + ' lk-global lk-global-' + templateId + ' lk-global-instance-' + id + ' lk-editable';
        if ( isSticky ) {
            classes += ' lk-sticky lk-sticky--' + escapeHtml( stickyPos );
        }
        if ( masterSettings.css_classes ) {
            classes += ' ' + escapeHtml( masterSettings.css_classes );
        }

        var idAttr = masterSettings.css_id ? ' id="' + escapeHtml( masterSettings.css_id ) + '"' : '';

        var extraAttrs = '';
        if ( ! isSection && tag === 'a' && masterSettings.link && masterSettings.link.url ) {
            extraAttrs += ' href="' + escapeHtml( masterSettings.link.url ) + '"';
            if ( masterSettings.link.is_external ) extraAttrs += ' target="_blank"';
            if ( masterSettings.link.nofollow ) extraAttrs += ' rel="nofollow"';
        }

        var html = '<' + tag + ' class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="global" data-lk-type="global" data-global-type="' + globalType + '" data-loopkit-global="' + templateId + '"' + idAttr + extraAttrs + '>';

        // Handle with purple background and globe icon
        var handleTypeCls = isSection ? 'lk-handle-section' : 'lk-handle-container';
        html += '<div class="lk-element-handle ' + handleTypeCls + ' lk-handle-global">';
        if ( isSection ) {
            html += '  <button type="button" class="lk-handle-btn lk-handle-add" data-action="add-section-before" data-id="' + id + '" title="Add Section"><span class="dashicons dashicons-plus-alt2"></span></button>';
        } else {
            html += '  <button type="button" class="lk-handle-btn lk-handle-add" data-action="add-container-inside" data-id="' + id + '" title="Add"><span class="dashicons dashicons-plus-alt2"></span></button>';
        }
        html += '  <span class="dashicons dashicons-admin-site-alt3 lk-handle-globe-icon"></span>';
        html += '  <span class="lk-handle-label">' + escapeHtml( settings._title || globalName ) + '</span>';
        html += buildMotionBadgesHtml( el );
        html += '  <button type="button" class="lk-handle-btn lk-handle-edit" data-action="edit" data-id="' + id + '" draggable="true" title="Edit"><span class="dashicons dashicons-edit"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-duplicate" data-action="duplicate" data-id="' + id + '" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-delete" data-action="delete" data-id="' + id + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
        html += '</div>';

        // Boxed inner wrapper
        if ( isBoxed ) {
            var innerCls = isSection ? 'lk-section-inner lk-con--' + conType : 'lk-con-inner lk-container-inner lk-con--' + conType;
            html += '<div class="' + innerCls + '">';
        }

        if ( masterChildren.length > 0 ) {
            for ( var i = 0; i < masterChildren.length; i++ ) {
                html += renderElement( masterChildren[i] );
            }
        } else {
            // Empty global drop zone
            html += '<div class="lk-container-empty" data-parent-id="' + id + '">';
            html += '  <span class="dashicons dashicons-plus-alt2"></span>';
            html += '  <span>Drop or add widget here</span>';
            html += '</div>';
        }

        if ( isBoxed ) {
            html += '</div>'; // inner wrapper
        }

        html += '</' + tag + '>';
        return html;
    }

    /**
     * Render Heading widget
     */
    function renderHeading( el ) {
        var id       = escapeHtml( el.id );
        var settings = el.settings || {};
        var tag      = sanitizeTag( settings.header_size || 'h2', [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ] );
        var title    = settings.title !== undefined ? settings.title : 'This is the heading';
        var isSticky = isElementSticky( el );

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-heading lk-editable';
        if ( isSticky ) {
            classes += ' lk-sticky lk-sticky--' + escapeHtml( settings.sticky );
        }
        if ( settings.css_classes ) {
            classes += ' ' + escapeHtml( settings.css_classes );
        }

        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';

        var customAttrs = parseCustomAttributes( settings.custom_attributes );
        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="heading.default" data-lk-widget="heading"' + idAttr + customAttrs + '>';

        // Widget handle: top right, background #38A8F5, label "Heading", Edit, Duplicate, Delete
        html += '<div class="lk-element-handle lk-handle-widget">';
        html += '  <span class="lk-handle-label">' + escapeHtml( settings._title || 'Heading' ) + '</span>';
        html += buildMotionBadgesHtml( el );
        html += '  <button type="button" class="lk-handle-btn lk-handle-edit" data-action="edit" data-id="' + id + '" draggable="true" title="Edit Heading"><span class="dashicons dashicons-edit"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-duplicate" data-action="duplicate" data-id="' + id + '" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-delete" data-action="delete" data-id="' + id + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
        html += '</div>';

        // Heading title element
        html += '<' + tag + ' class="lk-heading-title" data-lk-inline-edit="title">';
        if ( settings.link && settings.link.url ) {
            var targetAttr = settings.link.is_external ? ' target="_blank"' : '';
            var nofollowAttr = settings.link.nofollow ? ' rel="nofollow"' : '';
            html += '<a href="' + escapeHtml( settings.link.url ) + '"' + targetAttr + nofollowAttr + '>';
            html += escapeHtml( title );
            html += '</a>';
        } else {
            html += escapeHtml( title );
        }
        html += '</' + tag + '>';

        html += '</div>';
        return html;
    }

    /**
     * Render Text Editor widget
     */
    function renderTextEditor( el ) {
        var id       = escapeHtml( el.id );
        var settings = el.settings || {};
        var editorContent = settings.editor !== undefined ? settings.editor : '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.</p>';
        var dropCap = ( settings.drop_cap === 'yes' || settings.drop_cap === true );
        var view    = escapeHtml( settings.drop_cap_view || 'default' );
        var isSticky = isElementSticky( el );

        if ( dropCap && editorContent ) {
            editorContent = editorContent.replace( /(<p[^>]*>)\s*([^<>\s&])/i, '$1<span class="lk-drop-cap lk-drop-cap-view-' + view + '">$2</span>' );
        }

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-text-editor lk-editable';
        if ( isSticky ) {
            classes += ' lk-sticky lk-sticky--' + escapeHtml( settings.sticky );
        }
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="text-editor.default" data-lk-widget="text-editor"' + idAttr + customAttrs + '>';

        html += '<div class="lk-element-handle lk-handle-widget">';
        html += '  <span class="lk-handle-label">' + escapeHtml( settings._title || 'Text Editor' ) + '</span>';
        html += buildMotionBadgesHtml( el );
        html += '  <button type="button" class="lk-handle-btn lk-handle-edit" data-action="edit" data-id="' + id + '" draggable="true" title="Edit Text Editor"><span class="dashicons dashicons-edit"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-duplicate" data-action="duplicate" data-id="' + id + '" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-delete" data-action="delete" data-id="' + id + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
        html += '</div>';

        var dropCapClass = dropCap ? ' lk-has-drop-cap' : '';
        html += '<div class="lk-widget-container">';
        html += '<div class="lk-text-editor lk-clearfix' + dropCapClass + '" data-lk-inline-edit="editor">';
        html += editorContent;
        html += '</div>';
        html += '</div>';

        html += '</div>';
        return html;
    }

    /**
     * Render Image widget
     */
    function renderImage( el ) {
        var id       = escapeHtml( el.id );
        var settings = el.settings || {};
        var imgData  = settings.image || {};
        var defaultPlaceholder = ( window.loopkitCanvas && window.loopkitCanvas.pluginUrl ) ? ( window.loopkitCanvas.pluginUrl + 'assets/images/placeholder.svg' ) : '/wp-content/plugins/loopkit/assets/images/placeholder.svg';
        var imgUrl   = ( typeof imgData === 'object' && imgData.url ) ? imgData.url : ( ( typeof imgData === 'string' && imgData ) ? imgData : defaultPlaceholder );
        var size     = ( typeof settings.image_size === 'object' ? settings.image_size.size : settings.image_size ) || 'large';
        var isSticky = isElementSticky( el );

        var hoverAnim  = settings.hover_animation ? ' lk-animation-' + escapeHtml( settings.hover_animation ) : '';
        var imgClasses = 'attachment-' + escapeHtml( size ) + ' size-' + escapeHtml( size ) + hoverAnim;

        var imgHtml = '<img src="' + escapeHtml( imgUrl ) + '" class="' + imgClasses + '" alt="' + escapeHtml( settings.caption || 'Image' ) + '" loading="lazy" />';

        var linkTo = settings.link_to || 'none';
        if ( linkTo === 'file' ) {
            imgHtml = '<a href="' + escapeHtml( imgUrl ) + '" data-loopkit-lightbox="yes">' + imgHtml + '</a>';
        } else if ( linkTo === 'custom' && settings.link && settings.link.url ) {
            var targetAttr = settings.link.is_external ? ' target="_blank"' : '';
            var nofollowAttr = settings.link.nofollow ? ' rel="nofollow"' : '';
            imgHtml = '<a href="' + escapeHtml( settings.link.url ) + '"' + targetAttr + nofollowAttr + '>' + imgHtml + '</a>';
        }

        var captionSource = settings.caption_source || 'none';
        var captionText   = ( captionSource === 'custom' && settings.caption ) ? settings.caption : '';

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-image lk-editable';
        if ( isSticky ) {
            classes += ' lk-sticky lk-sticky--' + escapeHtml( settings.sticky );
        }
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="image.default" data-lk-widget="image"' + idAttr + customAttrs + '>';

        html += '<div class="lk-element-handle lk-handle-widget">';
        html += '  <span class="lk-handle-label">' + escapeHtml( settings._title || 'Image' ) + '</span>';
        html += buildMotionBadgesHtml( el );
        html += '  <button type="button" class="lk-handle-btn lk-handle-edit" data-action="edit" data-id="' + id + '" draggable="true" title="Edit Image"><span class="dashicons dashicons-edit"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-duplicate" data-action="duplicate" data-id="' + id + '" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-delete" data-action="delete" data-id="' + id + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
        html += '</div>';

        html += '<div class="lk-widget-container">';
        if ( captionText ) {
            html += '<figure class="wp-caption lk-widget-image">';
            html += imgHtml;
            html += '<figcaption class="widget-image-caption wp-caption-text lk-image-caption">' + escapeHtml( captionText ) + '</figcaption>';
            html += '</figure>';
        } else {
            html += imgHtml;
        }
        html += '</div>';

        html += '</div>';
        return html;
    }

    /**
     * Render Button widget
     */
    function renderButton( el ) {
        var id       = escapeHtml( el.id );
        var settings = el.settings || {};
        var text     = settings.text !== undefined ? settings.text : 'Click here';
        var linkUrl  = ( settings.link && settings.link.url ) ? settings.link.url : '#';
        var targetAttr = ( settings.link && settings.link.is_external ) ? ' target="_blank"' : '';
        var nofollowAttr = ( settings.link && settings.link.nofollow ) ? ' rel="nofollow"' : '';
        var size     = escapeHtml( ( settings.icon_size !== undefined ? settings.icon_size : ( settings.button_size !== undefined ? settings.button_size : settings.size ) ) || 'sm' );
        var align    = escapeHtml( settings.align || 'left' );
        var hoverAnim = settings.hover_animation ? ' lk-animation-' + escapeHtml( settings.hover_animation ) : '';
        var btnCssId = settings.button_css_id ? ' id="' + escapeHtml( settings.button_css_id ) + '"' : '';
        var isSticky = isElementSticky( el );

        var iconHtml = '';
        var iconVal  = settings.selected_icon;
        var hasIcon  = iconVal && ( ( typeof iconVal === 'object' && iconVal.value ) || ( typeof iconVal === 'string' && iconVal ) );
        var iconAlign = settings.icon_align || 'left';
        if ( hasIcon ) {
            // Always go through the shared helper. Calling getPreviewSvg() directly here
            // passed the whole { library, value } setting where that function expects the
            // library name alone, so the lookup silently missed and the button's icon
            // never appeared in the canvas. One helper, one place that knows the shape.
            var svg = getIconSvg( iconVal );
            if ( svg ) {
                iconHtml = '<span class="lk-button-icon lk-align-icon-' + escapeHtml( iconAlign ) + '">' + svg + '</span>';
            }
        }

        var innerContent = '';
        if ( iconAlign === 'left' && iconHtml ) innerContent += iconHtml;
        innerContent += '<span class="lk-button-text" data-lk-inline-edit="text">' + escapeHtml( text ) + '</span>';
        if ( iconAlign === 'right' && iconHtml ) innerContent += iconHtml;

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-button lk-editable';
        if ( isSticky ) {
            classes += ' lk-sticky lk-sticky--' + escapeHtml( settings.sticky );
        }
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="button.default" data-lk-widget="button"' + idAttr + customAttrs + '>';

        html += '<div class="lk-element-handle lk-handle-widget">';
        html += '  <span class="lk-handle-label">' + escapeHtml( settings._title || 'Button' ) + '</span>';
        html += buildMotionBadgesHtml( el );
        html += '  <button type="button" class="lk-handle-btn lk-handle-edit" data-action="edit" data-id="' + id + '" draggable="true" title="Edit Button"><span class="dashicons dashicons-edit"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-duplicate" data-action="duplicate" data-id="' + id + '" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-delete" data-action="delete" data-id="' + id + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
        html += '</div>';

        html += '<div class="lk-widget-container">';
        html += '<div class="lk-button-wrapper lk-align-' + align + '">';
        html += '<a class="lk-button lk-size-' + size + hoverAnim + '" href="' + escapeHtml( linkUrl ) + '" role="button"' + btnCssId + targetAttr + nofollowAttr + '>';
        html += '<span class="lk-button-content-wrapper">' + innerContent + '</span>';
        html += '</a>';
        html += '</div>';
        html += '</div>';

        html += '</div>';
        return html;
    }

    /**
     * Get title for widget handle label
     */
    function getWidgetTitle( el, defaultTitle ) {
        if ( el && el.settings && el.settings._title ) return el.settings._title;
        if ( defaultTitle ) return defaultTitle;
        var wType = ( el && el.widgetType ) || '';
        if ( window.parent && window.parent.LK && window.parent.LK.widgets && window.parent.LK.widgets[ wType ] ) {
            return window.parent.LK.widgets[ wType ].title || wType;
        }
        return wType ? ( wType.charAt( 0 ).toUpperCase() + wType.slice( 1 ) ) : 'Widget';
    }

    /**
     * Build standard widget handle
     */
    function buildWidgetHandle( id, title, el ) {
        if ( ! el && id ) {
            el = findElementModel( currentElements, id );
            if ( ! el && window.parent && window.parent.LoopkitEditor && window.parent.LoopkitEditor.state ) {
                el = findElementModel( window.parent.LoopkitEditor.state.elements, id );
            }
        }
        var isSticky = isElementSticky( el );
        var html = '<div class="lk-element-handle lk-handle-widget">';
        html += '  <span class="lk-handle-label">' + escapeHtml( title ) + '</span>';
        html += buildMotionBadgesHtml( el );
        html += '  <button type="button" class="lk-handle-btn lk-handle-edit" data-action="edit" data-id="' + id + '" draggable="true" title="Edit ' + escapeHtml( title ) + '"><span class="dashicons dashicons-edit"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-duplicate" data-action="duplicate" data-id="' + id + '" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>';
        html += '  <button type="button" class="lk-handle-btn lk-handle-delete" data-action="delete" data-id="' + id + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
        html += '</div>';
        return html;
    }

    /**
     * Get icon SVG or HTML representation
     */
    function getIconSvg( iconVal ) {
        if ( ! iconVal ) return '';
        var iconsLib = window.LoopkitIcons || ( window.parent && window.parent.LoopkitIcons );
        if ( iconsLib ) {
            // getPreviewSvg( style, name ) takes the library and the glyph name as two
            // separate arguments, but here we are handed the whole { library, value }
            // object. Passing that object as `style` made the caller's
            // `style.replace()` fail, so every icon in the canvas fell back to a
            // placeholder glyph. Unpack it first.
            if ( typeof iconVal === 'object' ) {
                var lib = typeof iconVal.library === 'string' ? iconVal.library : 'fa-solid';
                var nm = typeof iconVal.value === 'object' && iconVal.value !== null
                    ? ( iconVal.value.name || '' )
                    : String( iconVal.value || '' );
                if ( typeof iconsLib.getPreviewSvg === 'function' ) {
                    var svg0 = iconsLib.getPreviewSvg( lib, nm );
                    if ( svg0 ) return svg0;
                }
            } else if ( typeof iconsLib.getPreviewSvg === 'function' ) {
                var svg = iconsLib.getPreviewSvg( 'fa-solid', String( iconVal ) );
                if ( svg ) return svg;
            }
            if ( typeof iconsLib.render === 'function' ) {
                var svg2 = iconsLib.render( iconVal );
                if ( svg2 ) return svg2;
            }
        }
        var iconStr = ( typeof iconVal === 'object' && iconVal.value ) ? iconVal.value : ( typeof iconVal === 'string' ? iconVal : '' );
        if ( iconStr ) {
            return '<i class="' + escapeHtml( iconStr ) + '" aria-hidden="true"></i>';
        }
        return '';
    }

    /**
     * Render Icon widget
     */
    function renderIcon( el ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var iconVal = settings.selected_icon || { value: 'star', library: 'fa-solid' };
        var svg = getIconSvg( iconVal ) || '<span class="dashicons dashicons-star-filled"></span>';
        var view = escapeHtml( settings.view || 'default' );
        var shape = escapeHtml( settings.shape || 'circle' );
        var align = escapeHtml( settings.align || 'center' );
        var title = getWidgetTitle( el, 'Icon' );

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-icon lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="icon.default" data-lk-widget="icon"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';
        html += '<div class="lk-icon-wrapper lk-align-' + align + ' lk-view-' + view + ' lk-shape-' + shape + '">';

        var tag = 'div';
        var linkAttr = '';
        if ( settings.link && settings.link.url ) {
            tag = 'a';
            linkAttr = ' href="' + escapeHtml( settings.link.url ) + '"';
            if ( settings.link.is_external ) linkAttr += ' target="_blank"';
            if ( settings.link.nofollow ) linkAttr += ' rel="nofollow"';
        }

        html += '<' + tag + ' class="lk-icon"' + linkAttr + '>';
        html += svg;
        html += '</' + tag + '>';

        html += '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * Render Spacer widget
     */
    function renderSpacer( el ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var space = ( settings.space && settings.space.size ) ? settings.space.size : ( typeof settings.space === 'number' ? settings.space : 50 );
        var title = getWidgetTitle( el, 'Spacer' );

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-spacer lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="spacer.default" data-lk-widget="spacer"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';
        html += '<div class="lk-spacer">';
        html += '<div class="lk-spacer-inner" style="height:' + escapeHtml( space ) + 'px;"></div>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * Render Divider widget
     */
    function renderDivider( el ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var style = escapeHtml( settings.style || 'solid' );
        var align = escapeHtml( settings.align || 'center' );
        var addElement = escapeHtml( settings.add_element || 'none' );
        var title = getWidgetTitle( el, 'Divider' );

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-divider lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="divider.default" data-lk-widget="divider"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';

        // Same markup the PHP renderer produces, so the canvas and the front end match.
        var sepStyle = ' style="border-top-style:' + style + ';"';
        var sep = '<div class="lk-divider-separator"' + sepStyle + '></div>';
        var pos = escapeHtml( addElement === 'text' ? ( settings.text_position || 'center' ) : ( settings.icon_position || 'center' ) );
        var content = '';

        if ( addElement === 'text' && settings.text ) {
            content = '<span class="lk-divider__content"><span class="lk-divider__text" data-lk-inline-edit="text">' + escapeHtml( settings.text ) + '</span></span>';
        } else if ( addElement === 'icon' && ( settings.selected_icon || settings.icon ) ) {
            // The control is named selected_icon (see the widget's own control list and
            // its PHP renderer). This read settings.icon, which nothing ever writes, so
            // the divider's icon was never drawn in the editor at all -- and because it
            // was never drawn, the .lk-divider__icon rules from 1.1.6 could not have had
            // anything to apply to. settings.icon is still honoured for older documents.
            var divIconVal = settings.selected_icon || settings.icon;
            var iconSvg = getIconSvg( divIconVal ) || '<span class="dashicons dashicons-star-filled"></span>';
            content = '<span class="lk-divider__content"><span class="lk-divider__icon">' + iconSvg + '</span></span>';
        }

        html += '<div class="lk-divider lk-align-' + align + ( content ? ' lk-divider--has-element lk-divider--pos-' + pos : '' ) + '">';

        if ( ! content ) {
            html += '<div class="lk-divider-separator lk-divider-no-element"' + sepStyle + '></div>';
        } else if ( pos === 'left' ) {
            html += content + sep;
        } else if ( pos === 'right' ) {
            html += sep + content;
        } else {
            html += sep + content + sep;
        }

        html += '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * Render Video widget
     */
    function renderVideo( el ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var videoType = settings.video_type || 'youtube';
        var title = getWidgetTitle( el, 'Video' );

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-video lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var placeholderHtml = '<div class="lk-video-placeholder">' +
            '<div class="lk-video-placeholder-inner">' +
            '<div class="lk-video-placeholder-play">' +
            '<svg viewBox="0 0 64 64" fill="currentColor" width="48" height="48">' +
            '<circle cx="32" cy="32" r="30" fill="rgba(255, 255, 255, 0.12)" stroke="rgba(255, 255, 255, 0.3)" stroke-width="2"/>' +
            '<polygon points="26,20 46,32 26,44" fill="#ffffff"/>' +
            '</svg>' +
            '</div>' +
            '<span class="lk-video-placeholder-text">Choose your video</span>' +
            '</div>' +
            '</div>';

        var embedHtml = '';
        if ( videoType === 'youtube' ) {
            var ytUrl = settings.youtube_url || '';
            var ytMatch = ytUrl ? ytUrl.match( /(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/ ) : null;
            if ( ytMatch && ytMatch[1] ) {
                embedHtml = '<iframe src="https://www.youtube-nocookie.com/embed/' + escapeHtml( ytMatch[1] ) + '?controls=1&autoplay=0" frameborder="0" allowfullscreen></iframe>';
            } else {
                embedHtml = placeholderHtml;
            }
        } else if ( videoType === 'vimeo' ) {
            var vimeoUrl = settings.vimeo_url || '';
            var vMatch = vimeoUrl ? vimeoUrl.match( /vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|)(\d+)(?:$|\/|\?)/ ) : null;
            if ( vMatch && vMatch[3] ) {
                embedHtml = '<iframe src="https://player.vimeo.com/video/' + escapeHtml( vMatch[3] ) + '?autoplay=0" frameborder="0" allowfullscreen></iframe>';
            } else {
                embedHtml = placeholderHtml;
            }
        } else if ( videoType === 'dailymotion' ) {
            var dmUrl = settings.dailymotion_url || '';
            var dmMatch = dmUrl ? dmUrl.match( /dailymotion\.com\/video\/([a-zA-Z0-9]+)/ ) : null;
            if ( dmMatch && dmMatch[1] ) {
                embedHtml = '<iframe src="https://www.dailymotion.com/embed/video/' + escapeHtml( dmMatch[1] ) + '?autoplay=0" frameborder="0" allowfullscreen></iframe>';
            } else {
                embedHtml = placeholderHtml;
            }
        } else {
            var hosted = ( settings.hosted_url && typeof settings.hosted_url === 'object' ) ? settings.hosted_url.url : settings.hosted_url;
            if ( hosted ) {
                embedHtml = '<video src="' + escapeHtml( hosted ) + '" controls preload="metadata"></video>';
            } else {
                embedHtml = placeholderHtml;
            }
        }

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="video.default" data-lk-widget="video"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';
        html += '<div class="lk-video-wrapper">';
        html += '<div class="lk-video-container">';
        html += embedHtml;
        html += '</div>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * Render Image Box widget
     */
    function renderImageBox( el ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var defaultPlaceholder = ( window.loopkitCanvas && window.loopkitCanvas.pluginUrl ) ? ( window.loopkitCanvas.pluginUrl + 'assets/images/placeholder.svg' ) : '/wp-content/plugins/loopkit/assets/images/placeholder.svg';
        var imgUrl = ( settings.image && settings.image.url ) ? settings.image.url : defaultPlaceholder;
        var titleText = settings.title_text !== undefined ? settings.title_text : 'This is the heading';
        var descText = settings.description_text !== undefined ? settings.description_text : 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.';
        var position = escapeHtml( ( settings.icon_position || settings.image_position || settings.tooltip_position || settings.position ) || 'top' );
        var align = escapeHtml( settings.alignment || 'center' );
        var titleTag = sanitizeTag( settings.title_size || 'h3', [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ] );
        var title = getWidgetTitle( el, 'Image Box' );

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-image-box lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="image-box.default" data-lk-widget="image-box"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';
        html += '<div class="lk-image-box-wrapper lk-image-box-position-' + position + ' lk-position-' + position + ' lk-align-' + align + '">';

        html += '<div class="lk-image-box-img">';
        html += '<img src="' + escapeHtml( imgUrl ) + '" alt="' + escapeHtml( titleText ) + '" loading="lazy" />';
        html += '</div>';

        html += '<div class="lk-image-box-content">';
        html += '<' + titleTag + ' class="lk-image-box-title" data-lk-inline-edit="title_text">';
        if ( settings.link && settings.link.url ) {
            html += '<a href="' + escapeHtml( settings.link.url ) + '">' + escapeHtml( titleText ) + '</a>';
        } else {
            html += escapeHtml( titleText );
        }
        html += '</' + titleTag + '>';

        html += '<p class="lk-image-box-description" data-lk-inline-edit="description_text">' + escapeHtml( descText ) + '</p>';
        html += '</div>';

        html += '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * Render Icon Box widget
     */
    function renderIconBox( el ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var iconVal = settings.selected_icon || { value: 'star', library: 'fa-solid' };
        var svg = getIconSvg( iconVal ) || '<span class="dashicons dashicons-star-filled"></span>';
        var titleText = settings.title_text !== undefined ? settings.title_text : 'This is the heading';
        var descText = settings.description_text !== undefined ? settings.description_text : 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.';
        var position = escapeHtml( ( settings.icon_position || settings.image_position || settings.tooltip_position || settings.position ) || 'top' );
        var align = escapeHtml( settings.alignment || 'center' );
        var view = escapeHtml( settings.view || 'default' );
        var shape = escapeHtml( settings.shape || 'circle' );
        var titleTag = sanitizeTag( settings.title_size || 'h3', [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ] );
        var title = getWidgetTitle( el, 'Icon Box' );

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-icon-box lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="icon-box.default" data-lk-widget="icon-box"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';
        html += '<div class="lk-icon-box-wrapper lk-icon-box-position-' + position + ' lk-position-' + position + ' lk-align-' + align + ' lk-icon-box-view-' + view + ' lk-icon-box-shape-' + shape + '">';

        html += '<div class="lk-icon-box-icon">';
        html += '<span class="lk-icon">' + svg + '</span>';
        html += '</div>';

        html += '<div class="lk-icon-box-content">';
        html += '<' + titleTag + ' class="lk-icon-box-title" data-lk-inline-edit="title_text">';
        if ( settings.link && settings.link.url ) {
            html += '<a href="' + escapeHtml( settings.link.url ) + '">' + escapeHtml( titleText ) + '</a>';
        } else {
            html += escapeHtml( titleText );
        }
        html += '</' + titleTag + '>';

        html += '<p class="lk-icon-box-description" data-lk-inline-edit="description_text">' + escapeHtml( descText ) + '</p>';
        html += '</div>';

        html += '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * Render Icon List widget
     */
    function renderIconList( el ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var layout = escapeHtml( settings.layout || 'default' );
        var align = escapeHtml( settings.alignment || 'left' );
        var title = getWidgetTitle( el, 'Icon List' );
        var items = settings.icon_list || [
            { text: 'List Item #1', selected_icon: { value: 'check', library: 'fa-solid' } },
            { text: 'List Item #2', selected_icon: { value: 'times', library: 'fa-solid' } },
            { text: 'List Item #3', selected_icon: { value: 'circle', library: 'fa-solid' } }
        ];

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-icon-list lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="icon-list.default" data-lk-widget="icon-list"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';
        html += '<ul class="lk-icon-list-items lk-icon-list-' + layout + ' lk-align-' + align + '">';

        for ( var i = 0; i < items.length; i++ ) {
            var item = items[i];
            var iSvg = getIconSvg( item.selected_icon ) || '<span class="dashicons dashicons-yes"></span>';
            var itemText = item.text || ( 'List Item #' + ( i + 1 ) );

            html += '<li class="lk-icon-list-item">';
            if ( item.link && item.link.url ) {
                html += '<a href="' + escapeHtml( item.link.url ) + '">';
            }
            html += '<span class="lk-icon-list-icon">' + iSvg + '</span>';
            html += '<span class="lk-icon-list-text">' + escapeHtml( itemText ) + '</span>';
            if ( item.link && item.link.url ) {
                html += '</a>';
            }
            html += '</li>';
        }

        html += '</ul>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * Render Social Icons widget
     */
    function renderSocialIcons( el ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var shape = escapeHtml( settings.shape || 'rounded' );
        var align = escapeHtml( settings.align || 'center' );
        var title = getWidgetTitle( el, 'Social Icons' );
        var items = settings.social_icon_list || [
            { social_icon: { value: 'facebook-f', library: 'fa-brands' }, link: { url: 'https://facebook.com' } },
            { social_icon: { value: 'x-twitter', library: 'fa-brands' }, link: { url: 'https://x.com' } },
            { social_icon: { value: 'youtube', library: 'fa-brands' }, link: { url: 'https://youtube.com' } }
        ];

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-social-icons lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="social-icons.default" data-lk-widget="social-icons"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';
        html += '<div class="lk-social-icons-wrapper lk-align-' + align + ' lk-shape-' + shape + '">';

        for ( var i = 0; i < items.length; i++ ) {
            var item = items[i];
            var iVal = item.social_icon || { value: 'facebook-f', library: 'fa-brands' };
            var svg = getIconSvg( iVal ) || '<span class="dashicons dashicons-share"></span>';
            var linkUrl = ( item.link && item.link.url ) ? item.link.url : '#';
            // The stored value is a bare glyph name ('facebook-f'). An SVG pick stores an
            // object instead, so coerce before using it as a string for the brand class.
            var rawName = ( typeof iVal === 'object' && iVal.value ) ? iVal.value : ( typeof iVal === 'string' ? iVal : '' );
            var iconName = ( typeof rawName === 'object' ) ? ( rawName.name || '' ) : String( rawName || '' );
            var brand = iconName.replace( /^(fab\s+fa-|fas\s+fa-|fa-|lk-icon-)/, '' );

            html += '<a class="lk-icon lk-social-icon lk-social-icon-' + escapeHtml( brand ) + '" href="' + escapeHtml( linkUrl ) + '" role="button">';
            html += svg;
            html += '</a>';
        }

        html += '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * Render Custom HTML widget
     */
    function renderHtml( el ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var customHtml = settings.html !== undefined ? settings.html : '';
        var title = getWidgetTitle( el, 'HTML' );

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-html lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="html.default" data-lk-widget="html"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';
        if ( ! customHtml || ! customHtml.trim() ) {
            html += '<div class="lk-html-placeholder"><span class="dashicons dashicons-html"></span><span>' + escapeHtml( 'Custom HTML widget - Enter your HTML in the settings panel' ) + '</span></div>';
        } else {
            var hasScript = /<script\b[^>]*>/i.test( customHtml );
            // In editor canvas, scripts do not run: neutralize them with type="text/plain"
            var safeHtml = customHtml.replace( /<script\b([^>]*)>/gi, '<script type="text/plain"$1>' );
            html += '<div class="lk-html-container">';
            html += safeHtml;
            if ( hasScript ) {
                html += '<div class="lk-html-script-notice"><span class="dashicons dashicons-info"></span> ' + escapeHtml( 'Scripts run on the front end and in Preview.' ) + '</div>';
            }
            html += '</div>';
        }
        html += '</div>';
        html += '</div>';
        return html;
    }

    var shortcodeTimers = {};
    window.LoopkitShortcodeCache = window.LoopkitShortcodeCache || {};

    function fetchShortcode( id, shortcode ) {
        if ( shortcodeTimers[ id ] ) {
            clearTimeout( shortcodeTimers[ id ] );
        }
        shortcodeTimers[ id ] = setTimeout( function() {
            // restBase is the loopkit/v1/ route base; restNonce is the wp_rest nonce REST requests need.
            // (restUrl is the site-wide /wp-json/ root and editorNonce is not a REST nonce.)
            var restUrl = ( window.parent && window.parent.loopkitEditor && window.parent.loopkitEditor.restBase ) ||
                          '/wp-json/loopkit/v1/';
            var nonce   = ( window.parent && window.parent.loopkitEditor && window.parent.loopkitEditor.restNonce ) || '';
            var postId  = ( window.loopkitCanvas && window.loopkitCanvas.postId ) ||
                          ( window.parent && window.parent.loopkitEditor && window.parent.loopkitEditor.postId ) || 0;

            var xhr = new XMLHttpRequest();
            xhr.open( 'POST', restUrl + 'render-shortcode', true );
            xhr.setRequestHeader( 'Content-Type', 'application/json' );
            if ( nonce ) {
                xhr.setRequestHeader( 'X-WP-Nonce', nonce );
            }
            xhr.onreadystatechange = function() {
                if ( xhr.readyState === 4 ) {
                    var html = '';
                    if ( xhr.status >= 200 && xhr.status < 300 ) {
                        try {
                            var res = JSON.parse( xhr.responseText );
                            html = res.html || '';
                        } catch ( e ) {
                            html = '';
                        }
                    }
                    if ( ! html ) {
                        html = '<div class="lk-shortcode-placeholder"><span class="dashicons dashicons-shortcode"></span><span>' + escapeHtml( shortcode ) + '</span></div>';
                    }
                    window.LoopkitShortcodeCache[ shortcode ] = html;
                    var container = document.querySelector( '.lk-shortcode-container[data-shortcode-id="' + id + '"]' );
                    if ( container ) {
                        container.innerHTML = html;
                    }
                }
            };
            xhr.send( JSON.stringify( { shortcode: shortcode, post_id: postId } ) );
        }, 300 );
    }

    /**
     * Render Shortcode widget
     */
    function renderShortcode( el ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var shortcode = ( settings.shortcode || '' ).trim();
        var title = getWidgetTitle( el, 'Shortcode' );

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-shortcode lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="shortcode.default" data-lk-widget="shortcode"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';
        html += '<div class="lk-shortcode-container" data-shortcode-id="' + id + '">';

        if ( ! shortcode ) {
            html += '<div class="lk-shortcode-placeholder"><span class="dashicons dashicons-shortcode"></span><span>' + escapeHtml( 'Enter your shortcode in the settings panel' ) + '</span></div>';
        } else if ( window.LoopkitShortcodeCache[ shortcode ] !== undefined ) {
            html += window.LoopkitShortcodeCache[ shortcode ];
        } else {
            html += '<div class="lk-shortcode-loading"><div class="lk-shortcode-spinner"></div><span>Loading shortcode...</span></div>';
            fetchShortcode( id, shortcode );
        }

        html += '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * Render Site Logo widget
     */
    function renderSiteLogo( el ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var title = getWidgetTitle( el, 'Site Logo' );

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-site-logo lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var isOverride = settings.custom_logo_override === 'yes';
        var imgUrl = '';
        if ( isOverride && settings.image ) {
            imgUrl = ( typeof settings.image === 'object' && settings.image.url ) ? settings.image.url : ( typeof settings.image === 'string' ? settings.image : '' );
        }
        if ( ! imgUrl ) {
            if ( window.parent && window.parent.LK && window.parent.LK.customLogoUrl ) {
                imgUrl = window.parent.LK.customLogoUrl;
            } else if ( window.loopkitCanvas && window.loopkitCanvas.customLogoUrl ) {
                imgUrl = window.loopkitCanvas.customLogoUrl;
            }
        }

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="site-logo.default" data-lk-widget="site-logo"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';

        if ( ! imgUrl ) {
            html += '<div class="lk-site-logo-placeholder">';
            html += '  <span class="dashicons dashicons-format-image lk-site-logo-placeholder-icon"></span>';
            html += '  <span class="lk-site-logo-placeholder-text">Site Logo &mdash; No logo set in WordPress Customiser. Click to choose an override image.</span>';
            html += '</div>';
        } else {
            var size = ( typeof settings.image_size === 'object' ? settings.image_size.size : settings.image_size ) || 'full';
            var linkUrl = ( settings.link && settings.link.url ) ? settings.link.url : ( ( window.parent && window.parent.LK && window.parent.LK.homeUrl ) || '/' );
            var targetAttr = ( settings.link && settings.link.is_external ) ? ' target="_blank"' : '';
            var relAttr = ( settings.link && settings.link.nofollow ) ? ' rel="nofollow"' : '';
            var imgHtml = '<img src="' + escapeHtml( imgUrl ) + '" class="lk-site-logo-img attachment-' + escapeHtml( size ) + '" alt="' + escapeHtml( ( window.parent && window.parent.LK && window.parent.LK.siteTitle ) || 'Site Logo' ) + '" />';

            var captionSource = settings.caption_source || 'none';
            var captionText = ( captionSource === 'custom' && settings.caption ) ? settings.caption : '';

            if ( captionText ) {
                html += '<figure class="lk-site-logo-figure">';
                html += '<a href="' + escapeHtml( linkUrl ) + '"' + targetAttr + relAttr + '>' + imgHtml + '</a>';
                html += '<figcaption class="lk-site-logo-caption">' + escapeHtml( captionText ) + '</figcaption>';
                html += '</figure>';
            } else {
                html += '<a href="' + escapeHtml( linkUrl ) + '"' + targetAttr + relAttr + '>' + imgHtml + '</a>';
            }
        }

        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * Render Site Title widget
     */
    function renderSiteTitle( el ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var title = getWidgetTitle( el, 'Site Title' );

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-site-title lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var headerTag = sanitizeTag( settings.header_size || 'h1', [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ] );
        var defaultSiteName = ( window.parent && window.parent.LK && window.parent.LK.siteTitle ) || 'WordPress Site';
        var resolvedTitle = settings.title || defaultSiteName;
        var linkToHome = settings.link_to_home !== 'no' && settings.link_to_home !== '';
        var homeUrl = ( window.parent && window.parent.LK && window.parent.LK.homeUrl ) || '/';
        var sizeClass = ( ( settings.icon_size !== undefined ? settings.icon_size : ( settings.button_size !== undefined ? settings.button_size : settings.size ) ) && ( settings.icon_size !== undefined ? settings.icon_size : ( settings.button_size !== undefined ? settings.button_size : settings.size ) ) !== 'default' ) ? ' lk-size-' + escapeHtml( ( settings.icon_size !== undefined ? settings.icon_size : ( settings.button_size !== undefined ? settings.button_size : settings.size ) ) ) : '';

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="site-title.default" data-lk-widget="site-title"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';
        html += '<div class="lk-site-title-wrapper' + sizeClass + '">';

        var contentHtml = '<' + headerTag + ' class="lk-site-title-heading" data-lk-inline-edit="title">';
        if ( linkToHome ) {
            contentHtml += '<a href="' + escapeHtml( homeUrl ) + '">' + escapeHtml( resolvedTitle ) + '</a>';
        } else {
            contentHtml += escapeHtml( resolvedTitle );
        }
        contentHtml += '</' + headerTag + '>';

        html += contentHtml;
        html += '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }


    /**
     * Render Search Form widget
     */
    function renderSearchForm( el ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var title = getWidgetTitle( el, 'Search Form' );

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-search-form lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );

        var placeholder = escapeHtml( settings.placeholder || 'Search...' );
        var buttonType = escapeHtml( settings.button_type || 'icon' );
        var buttonText = escapeHtml( settings.button_text || 'Search' );
        var icon = escapeHtml( settings.icon || 'dashicons-search' );
        var layout = escapeHtml( settings.layout || 'row' );

        var formClasses = 'lk-search-form' + ( layout === 'column' ? ' lk-search-form--stacked' : '' );

        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="search-form.default" data-lk-widget="search-form"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';
        html += '<form class="' + formClasses + '" role="search" onsubmit="return false;">';
        html += '  <div class="lk-search-form__input-wrapper">';
        html += '    <input type="search" class="lk-search-form__input" placeholder="' + placeholder + '" name="s" autocomplete="off" aria-label="' + placeholder + '" />';
        html += '  </div>';

        if ( buttonType !== 'none' ) {
            html += '  <button type="button" class="lk-search-form__submit lk-search-form__submit--' + buttonType + '" aria-label="' + buttonText + '">';
            if ( buttonType === 'icon' || buttonType === 'icon_text' ) {
                html += '    <span class="lk-search-form__icon dashicons ' + icon + '" aria-hidden="true"></span>';
            }
            if ( buttonType === 'text' || buttonType === 'icon_text' ) {
                html += '    <span class="lk-search-form__text">' + buttonText + '</span>';
            }
            html += '  </button>';
        }

        html += '</form>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /**
     * Render fallback widget when renderer is not found
     */
    function renderFallbackWidget( el, widgetType ) {
        var id = escapeHtml( el.id );
        var title = getWidgetTitle( el, widgetType || 'Widget' );

        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-' + escapeHtml( widgetType || 'unknown' ) + ' lk-editable';
        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="' + escapeHtml( widgetType ) + '.default" data-lk-widget="' + escapeHtml( widgetType ) + '">';
        html += buildWidgetHandle( id, title );
        html += '<div class="lk-widget-container">';
        html += '<div class="lk-widget-fallback">';
        html += '<div class="lk-widget-fallback-title">Widget: ' + escapeHtml( title ) + '</div>';
        html += '<div class="lk-widget-fallback-desc">No canvas renderer registered for widget type "' + escapeHtml( widgetType ) + '"</div>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    /* =========================================================
       POST WIDGETS (Part 5)
       Markup mirrors the PHP classes in widgets/class-loopkit-widget-post-*.php
       and reads window.parent.LK.previewPost (same data array as the PHP render).
       ========================================================= */

    // The editor config is window.loopkitEditor (the editor aliases it as LK internally).
    function getEditorConfig() {
        if ( ! window.parent ) return null;
        return window.parent.loopkitEditor || window.parent.LK || null;
    }

    function getPreviewPost() {
        var cfg = getEditorConfig();
        return ( cfg && cfg.previewPost ) || null;
    }

    function postTrimWords( text, length ) {
        text = String( text || '' ).trim();
        length = Math.max( 1, parseInt( length, 10 ) || 1 );
        if ( ! text ) return '';
        var words = text.split( /\s+/ );
        if ( words.length > length ) {
            return words.slice( 0, length ).join( ' ' ) + '\u2026';
        }
        return words.join( ' ' );
    }

    function openPostWidget( el, type, defaultTitle ) {
        var id = escapeHtml( el.id );
        var settings = el.settings || {};
        var classes = 'lk-element lk-element-' + id + ' lk-widget lk-widget-' + type + ' lk-editable';
        if ( settings.css_classes ) classes += ' ' + escapeHtml( settings.css_classes );
        var idAttr = settings.css_id ? ' id="' + escapeHtml( settings.css_id ) + '"' : '';
        var customAttrs = parseCustomAttributes( settings.custom_attributes );
        var html = '<div class="' + classes + '" data-id="' + id + '" data-lk-id="' + id + '" data-element_type="widget" data-lk-type="widget" data-widget_type="' + type + '.default" data-lk-widget="' + type + '"' + idAttr + customAttrs + '>';
        html += buildWidgetHandle( id, getWidgetTitle( el, defaultTitle ) );
        html += '<div class="lk-widget-container">';
        return html;
    }

    function closePostWidget() {
        return '</div></div>';
    }

    /**
     * Render Post Title widget
     */
    function renderPostTitle( el ) {
        var settings = el.settings || {};
        var data = getPreviewPost();
        var html = openPostWidget( el, 'post-title', 'Post Title' );
        if ( data ) {
            var tag = sanitizeTag( settings.header_size || 'h1', [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ] );
            var size = ( settings.icon_size !== undefined ? settings.icon_size : ( settings.button_size !== undefined ? settings.button_size : settings.size ) ) || 'default';
            var sizeClass = ( size && size !== 'default' ) ? ' lk-size-' + escapeHtml( size ) : '';
            var inner = escapeHtml( data.title || '' );
            if ( settings.link_to_post === 'yes' ) {
                inner = '<a href="' + escapeHtml( data.permalink || '#' ) + '">' + inner + '</a>';
            }
            html += '<div class="lk-post-title-wrapper' + sizeClass + '"><' + tag + ' class="lk-post-title">' + inner + '</' + tag + '></div>';
        }
        html += closePostWidget();
        return html;
    }

    /**
     * Render Featured Image widget
     */
    function renderPostFeaturedImage( el ) {
        var settings = el.settings || {};
        var data = getPreviewPost();
        var image = data && data.image;
        var html = openPostWidget( el, 'post-featured-image', 'Featured Image' );

        if ( ! image || ! image.sizes || ! Object.keys( image.sizes ).length ) {
            var cfg = getEditorConfig();
            var ph = ( ( cfg && cfg.pluginUrl ) || '' ) + 'assets/images/placeholder.svg';
            html += '<figure class="lk-post-featured-image"><div class="lk-post-featured-image-placeholder"><img class="lk-post-featured-image-img" src="' + escapeHtml( ph ) + '" alt=""><span>' + escapeHtml( 'This post has no featured image' ) + '</span></div></figure>';
            html += closePostWidget();
            return html;
        }

        var size = settings.image_size || 'large';
        var src = image.sizes[ size ] || image.sizes.large || image.sizes.full || image.sizes[ Object.keys( image.sizes )[0] ];
        var img = '<img class="lk-post-featured-image-img" src="' + escapeHtml( src ) + '" alt="' + escapeHtml( image.alt || '' ) + '">';

        var linkTo = settings.link_to || 'none';
        var href = '';
        if ( linkTo === 'post' ) href = data.permalink || '';
        else if ( linkTo === 'file' ) href = image.sizes.full || src;
        if ( href ) img = '<a href="' + escapeHtml( href ) + '">' + img + '</a>';

        var caption = '';
        if ( settings.show_caption === 'yes' && image.caption ) {
            caption = '<figcaption class="lk-post-featured-image-caption">' + escapeHtml( image.caption ) + '</figcaption>';
        }

        html += '<figure class="lk-post-featured-image">' + img + caption + '</figure>';
        html += closePostWidget();
        return html;
    }

    /**
     * Render Post Excerpt widget
     */
    function renderPostExcerpt( el ) {
        var settings = el.settings || {};
        var data = getPreviewPost();
        var html = openPostWidget( el, 'post-excerpt', 'Post Excerpt' );
        if ( data ) {
            var length = parseInt( settings.excerpt_length, 10 );
            if ( isNaN( length ) ) length = 25;
            length = Math.max( 1, length );
            var custom = String( data.custom_excerpt || '' );
            var text;
            if ( custom.trim() ) {
                text = ( settings.apply_to_custom === 'yes' ) ? postTrimWords( custom, length ) : custom.trim();
            } else {
                text = postTrimWords( data.content_text || '', length );
            }
            if ( text ) {
                html += '<div class="lk-post-excerpt">' + escapeHtml( text ) + '</div>';
            } else {
                html += '<div class="lk-post-excerpt lk-post-excerpt-empty">' + escapeHtml( 'This post has no excerpt or text content.' ) + '</div>';
            }
        }
        html += closePostWidget();
        return html;
    }

    var POST_INFO_DEFAULT_ICONS = {
        author:   { library: 'fa-regular', value: 'user' },
        date:     { library: 'fa-regular', value: 'calendar' },
        time:     { library: 'fa-regular', value: 'clock' },
        comments: { library: 'fa-regular', value: 'comment' },
        terms:    { library: 'fa-regular', value: 'folder' },
        modified: { library: 'fa-regular', value: 'pen-to-square' }
    };

    /**
     * Render Post Info widget
     */
    function renderPostInfo( el ) {
        var settings = el.settings || {};
        var data = getPreviewPost();
        var html = openPostWidget( el, 'post-info', 'Post Info' );
        if ( ! data ) {
            html += closePostWidget();
            return html;
        }

        var items = Array.isArray( settings.info_items ) ? settings.info_items : [
            { type: 'author', link: 'yes' },
            { type: 'date', link: 'yes' },
            { type: 'time', link: 'yes' },
            { type: 'comments', link: 'yes' }
        ];

        var itemsHtml = '';
        for ( var i = 0; i < items.length; i++ ) {
            var item = items[i];
            if ( ! item || typeof item !== 'object' ) continue;
            var type = item.type || 'date';
            var linkOn = item.link !== 'no';
            var text = '';
            var url = '';
            var isHtml = false;
            var fmt;

            if ( type === 'author' ) {
                text = ( data.author && data.author.name ) || '';
                url = ( data.author && data.author.url ) || '';
            } else if ( type === 'date' ) {
                fmt = item.date_format || 'default';
                text = ( data.dates && ( data.dates[ fmt ] || data.dates['default'] ) ) || '';
                url = data.day_archive_url || '';
            } else if ( type === 'modified' ) {
                fmt = item.date_format || 'default';
                text = ( data.modified && ( data.modified[ fmt ] || data.modified['default'] ) ) || '';
            } else if ( type === 'time' ) {
                fmt = item.time_format || 'default';
                text = ( data.times && ( data.times[ fmt ] || data.times['default'] ) ) || '';
            } else if ( type === 'comments' ) {
                text = ( data.comments && data.comments.text ) || '';
                url = ( data.comments && data.comments.url ) || '';
            } else if ( type === 'terms' ) {
                var tax = ( item.taxonomy === 'post_tag' ) ? 'post_tag' : 'category';
                var terms = ( data.terms && data.terms[ tax ] ) || [];
                if ( ! terms.length ) continue;
                var parts = [];
                for ( var t = 0; t < terms.length; t++ ) {
                    parts.push( ( linkOn && terms[t].url ) ? '<a href="' + escapeHtml( terms[t].url ) + '">' + escapeHtml( terms[t].name ) + '</a>' : escapeHtml( terms[t].name ) );
                }
                text = parts.join( ', ' );
                isHtml = true;
            } else {
                continue;
            }

            if ( ! text ) continue;

            var icon = ( item.selected_icon && item.selected_icon.value ) ? item.selected_icon : ( POST_INFO_DEFAULT_ICONS[ type ] || POST_INFO_DEFAULT_ICONS.date );
            var inner = '<span class="lk-post-info-icon">' + ( getIconSvg( icon ) || '' ) + '</span>';
            var before = String( item.before || '' ).trim();
            if ( before ) inner += '<span class="lk-post-info-before">' + escapeHtml( before ) + '</span>';
            inner += '<span class="lk-post-info-text">' + ( isHtml ? text : escapeHtml( text ) ) + '</span>';
            if ( ! isHtml && linkOn && url ) {
                inner = '<a href="' + escapeHtml( url ) + '">' + inner + '</a>';
            }
            itemsHtml += '<li class="lk-post-info-item lk-post-info-item-' + escapeHtml( type ) + '">' + inner + '</li>';
        }

        var layout = ( settings.layout === 'list' ) ? 'list' : 'inline';
        var classes = 'lk-post-info-items lk-post-info-' + layout;
        if ( settings.divider === 'yes' ) classes += ' lk-post-info-has-divider';
        html += '<ul class="' + classes + '">' + itemsHtml + '</ul>';
        html += closePostWidget();
        return html;
    }

    // Never submit a real comment from inside the editor canvas (Enter key / submit button).
    document.addEventListener( 'submit', function( e ) {
        if ( e.target && e.target.closest && e.target.closest( '.lk-widget-post-comments' ) ) {
            e.preventDefault();
        }
    }, true );

    /**
     * Render Post Content widget (server-rendered snapshot of the preview post)
     */
    function renderPostContent( el ) {
        var data = getPreviewPost();
        var html = openPostWidget( el, 'post-content', 'Post Content' );
        var body = ( data && data.content_html ) ? data.content_html : '';
        if ( ! String( body ).trim() ) {
            body = '<p>' + escapeHtml( 'This is sample post content. Publish a post, or choose one under Template Settings > Preview With, to see real content here.' ) + '</p>';
        }
        html += '<div class="lk-post-content">' + body + '</div>';
        html += closePostWidget();
        return html;
    }

    /**
     * Render Author Box widget
     */
    function renderAuthorBox( el ) {
        var s = el.settings || {};
        var data = getPreviewPost();
        var html = openPostWidget( el, 'author-box', 'Author Box' );
        var a = data && data.author;
        if ( a && a.name ) {
            var linkTo = s.link_to || 'none';
            var href = linkTo === 'posts' ? ( a.url || '' ) : ( linkTo === 'website' ? ( a.website || '' ) : '' );
            var tag = sanitizeTag( s.name_tag || 'h4', [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' ] );
            var layout = s.layout === 'above' ? 'above' : 'left';
            html += '<div class="lk-author-box lk-author-box-' + layout + '">';
            if ( s.show_avatar !== 'no' && a.avatar ) {
                var img = '<img src="' + escapeHtml( a.avatar ) + '" alt="' + escapeHtml( a.name ) + '">';
                html += '<div class="lk-author-box-avatar">' + ( href ? '<a href="' + escapeHtml( href ) + '">' + img + '</a>' : img ) + '</div>';
            }
            html += '<div class="lk-author-box-text">';
            if ( s.show_name !== 'no' ) {
                var nm = escapeHtml( a.name );
                html += '<' + tag + ' class="lk-author-box-name">' + ( href ? '<a href="' + escapeHtml( href ) + '">' + nm + '</a>' : nm ) + '</' + tag + '>';
            }
            if ( s.show_bio !== 'no' && a.bio ) {
                html += '<div class="lk-author-box-bio">' + escapeHtml( a.bio ) + '</div>';
            }
            if ( s.show_link === 'yes' && a.url ) {
                var lt = ( s.link_text !== undefined && s.link_text !== '' ) ? s.link_text : 'All Posts';
                html += '<a class="lk-author-box-link" href="' + escapeHtml( a.url ) + '">' + escapeHtml( lt ) + '</a>';
            }
            html += '</div></div>';
        }
        html += closePostWidget();
        return html;
    }

    var POST_NAV_ARROW_PREV = '<svg aria-hidden="true" viewBox="0 0 320 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M41.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l160 160c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L109.3 256 246.6 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-160 160z"/></svg>';
    var POST_NAV_ARROW_NEXT = '<svg aria-hidden="true" viewBox="0 0 320 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M278.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-160 160c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L210.7 256 73.4 118.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l160 160z"/></svg>';

    function postNavSide( item, dir, s ) {
        var html = '<div class="lk-post-nav-' + dir + '">';
        if ( item && item.url ) {
            var text = '';
            if ( s.show_label !== 'no' ) {
                var lbl = s[ dir + '_label' ];
                if ( lbl === undefined || lbl === '' ) lbl = ( dir === 'prev' ) ? 'Previous' : 'Next';
                text += '<span class="lk-post-nav-label">' + escapeHtml( lbl ) + '</span>';
            }
            if ( s.show_title !== 'no' ) {
                text += '<span class="lk-post-nav-title">' + escapeHtml( item.title || '' ) + '</span>';
            }
            var arrow = ( s.show_arrow !== 'no' ) ? '<span class="lk-post-nav-arrow">' + ( dir === 'prev' ? POST_NAV_ARROW_PREV : POST_NAV_ARROW_NEXT ) + '</span>' : '';
            var inner = '<span class="lk-post-nav-text">' + text + '</span>';
            inner = ( dir === 'prev' ) ? arrow + inner : inner + arrow;
            html += '<a class="lk-post-nav-link" href="' + escapeHtml( item.url ) + '" rel="' + dir + '">' + inner + '</a>';
        }
        return html + '</div>';
    }

    /**
     * Render Post Navigation widget
     */
    function renderPostNavigation( el ) {
        var s = el.settings || {};
        var data = getPreviewPost();
        var html = openPostWidget( el, 'post-navigation', 'Post Navigation' );
        var nav = ( data && data.nav ) || {};
        var suffix = ( s.in_same_term === 'yes' ) ? '_same' : '_all';
        html += '<nav class="lk-post-navigation" aria-label="Posts">' + postNavSide( nav[ 'prev' + suffix ], 'prev', s ) + postNavSide( nav[ 'next' + suffix ], 'next', s ) + '</nav>';
        html += closePostWidget();
        return html;
    }

    /**
     * Render Comments widget (server-rendered snapshot of the preview post's comments)
     */
    function renderPostComments( el ) {
        var s = el.settings || {};
        var data = getPreviewPost();
        var parts = ( data && data.comments_html ) || { list: '', form: '', open: false, count: 0 };
        var html = openPostWidget( el, 'post-comments', 'Comments' );
        // `parts.list` and `parts.form` are deliberate raw HTML, not a mistake: they are the
        // markup WordPress itself produced (wp_list_comments() and comment_form()), already
        // run through the core comment filters, and comments are not meant to be escaped or
        // they would render as visible tag soup. Do not "fix" this, and do not copy the
        // pattern to values that have not been through that same filtering.
        var body = ( parts.list || '' ) + ( s.show_form !== 'no' ? ( parts.form || '' ) : '' );
        if ( ! body.trim() ) {
            body = '<p class="lk-post-comments-empty">' + escapeHtml( 'Comments are closed for this post and it has no comments.' ) + '</p>';
        }
        var title = '';
        if ( s.show_title !== 'no' && parts.count > 0 ) {
            var tag = sanitizeTag( s.title_tag || 'h3', [ 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ] );
            var ct = parts.count === 1 ? 'One Comment' : ( parts.count + ' Comments' );
            title = '<' + tag + ' class="lk-comments-title">' + escapeHtml( ct ) + '</' + tag + '>';
        }
        html += '<div class="lk-post-comments">' + title + body + '</div>';
        html += closePostWidget();
        return html;
    }

    /* =========================================================
       ARCHIVE WIDGETS (Part 7) â€” mirror widgets/class-loopkit-widget-archive-*.php,
       reading loopkitEditor.previewArchive (same array as the PHP render).
       ========================================================= */
    function getPreviewArchive() {
        var cfg = getEditorConfig();
        return ( cfg && cfg.previewArchive ) || null;
    }

    function renderArchiveTitle( el ) {
        var s = el.settings || {};
        var data = getPreviewArchive();
        var html = openPostWidget( el, 'archive-title', 'Archive Title' );
        if ( data && data.title ) {
            var tag = sanitizeTag( s.header_size || 'h1', [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' ] );
            var inner = '';
            if ( s.show_prefix !== 'no' && data.prefix ) {
                inner += '<span class="lk-archive-title-prefix">' + escapeHtml( data.prefix ) + ' </span>';
            }
            inner += '<span class="lk-archive-title-name">' + escapeHtml( data.title ) + '</span>';
            html += '<div class="lk-archive-title-wrapper"><' + tag + ' class="lk-archive-title">' + inner + '</' + tag + '></div>';
        }
        html += closePostWidget();
        return html;
    }

    function renderArchiveDescription( el ) {
        var data = getPreviewArchive();
        var html = openPostWidget( el, 'archive-description', 'Archive Description' );
        var desc = ( data && data.description ) ? String( data.description ) : '';
        if ( desc ) {
            // The description arrives already through wp_kses_post() in
            // Loopkit_Archive_Data::make(). Escape here anyway: the empty-state branch below
            // does, and an unmatched pair of branches is how raw HTML reaches a canvas.
            html += '<div class="lk-archive-description">' + escapeHtml( desc ) + '</div>';
        } else {
            html += '<div class="lk-archive-description lk-archive-description-empty">' + escapeHtml( 'This archive has no description.' ) + '</div>';
        }
        html += closePostWidget();
        return html;
    }

    /* =========================================================
       SERVER-RENDERED WIDGETS (Part 8: Posts, Pagination)
       The canvas asks the server to render these with the same PHP class the front end
       uses (POST loopkit/v1/render-widget), caches the HTML per settings + preview, and
       swaps it into the widget when it arrives.
       ========================================================= */
    window.LoopkitServerWidgetCache = window.LoopkitServerWidgetCache || {};
    var serverWidgetTimers = {};
    var serverWidgetLastHtml = {}; // keeps the previous preview visible while a new one loads (no flicker)

    function serverWidgetKey( type, settings ) {
        var cfg = getEditorConfig() || {};
        return type + '|' + JSON.stringify( settings || {} ) + '|' + ( cfg.previewArchiveKey || '' ) + '|' + ( cfg.previewPostId || '' );
    }

    function fetchServerWidget( id, type, settings, key ) {
        if ( serverWidgetTimers[ id ] ) clearTimeout( serverWidgetTimers[ id ] );
        serverWidgetTimers[ id ] = setTimeout( function() {
            var cfg = getEditorConfig() || {};
            var xhr = new XMLHttpRequest();
            xhr.open( 'POST', ( cfg.restBase || '/wp-json/loopkit/v1/' ) + 'render-widget', true );
            xhr.setRequestHeader( 'Content-Type', 'application/json' );
            if ( cfg.restNonce ) xhr.setRequestHeader( 'X-WP-Nonce', cfg.restNonce );
            xhr.onreadystatechange = function() {
                if ( xhr.readyState !== 4 ) return;
                var html = '';
                if ( xhr.status >= 200 && xhr.status < 300 ) {
                    try { html = JSON.parse( xhr.responseText ).html || ''; } catch ( e ) { html = ''; }
                } else {
                    html = '<div class="lk-server-widget-error">' + escapeHtml( 'Preview could not be loaded (' + xhr.status + ').' ) + '</div>';
                }
                window.LoopkitServerWidgetCache[ key ] = html;
                serverWidgetLastHtml[ id ] = html;
                var holder = document.querySelector( '.lk-server-widget[data-server-id="' + id + '"]' );
                if ( holder ) {
                    holder.innerHTML = html;
                    // Widgets with behaviour (marquee speed, countdown, headline) were rendered
                    // after those scripts ran, so start them on the new markup.
                    try {
                        if ( window.LoopkitMarquee ) window.LoopkitMarquee.init( holder );
                        if ( window.LoopkitPro ) window.LoopkitPro.init( holder );
                        if ( window.LoopkitPro2 ) window.LoopkitPro2.init( holder );
                        if ( window.LoopkitWidgets && window.LoopkitWidgets.init ) window.LoopkitWidgets.init( holder );
                    } catch ( e ) {}
                }
            };
            xhr.send( JSON.stringify( {
                widget: type,
                element_id: id,
                settings: settings || {},
                document_id: cfg.postId || 0,
                preview_archive: cfg.previewArchiveKey || '',
                preview_id: cfg.previewPostId || ''
            } ) );
        }, 250 );
    }

    function renderServerWidget( el, type, defaultTitle ) {
        var settings = el.settings || {};
        var id = escapeHtml( el.id );
        var key = serverWidgetKey( type, settings );
        var html = openPostWidget( el, type, defaultTitle );
        html += '<div class="lk-server-widget" data-server-id="' + id + '">';
        if ( window.LoopkitServerWidgetCache[ key ] !== undefined ) {
            serverWidgetLastHtml[ el.id ] = window.LoopkitServerWidgetCache[ key ];
            html += window.LoopkitServerWidgetCache[ key ];
        } else {
            html += ( serverWidgetLastHtml[ el.id ] !== undefined )
                ? serverWidgetLastHtml[ el.id ]
                : '<div class="lk-server-widget-loading">' + escapeHtml( 'Loading ' + defaultTitle + 'â€¦' ) + '</div>';
            fetchServerWidget( el.id, type, settings, key );
        }
        html += '</div>';
        html += closePostWidget();
        return html;
    }

    function renderPosts( el ) {
        return renderServerWidget( el, 'posts', 'Posts' );
    }

    function renderPagination( el ) {
        return renderServerWidget( el, 'pagination', 'Pagination' );
    }

    /* =========================================================
       DYNAMIC TAGS in the canvas â€” {{lk:post_title}} etc.
       Mirrors includes/class-loopkit-dynamic.php so the preview matches the front end.
       Server-rendered widgets (Posts, Loop Grid, interactive) resolve on the server instead.
       ========================================================= */
    function dynamicValue( name, arg ) {
        var cfg = getEditorConfig() || {};
        var d = cfg.previewPost || null;
        var a = cfg.previewArchive || null;
        switch ( name ) {
            case 'post_title':           return d ? d.title : '';
            case 'post_excerpt':         return d ? postTrimWords( ( d.custom_excerpt && d.custom_excerpt.trim() ) ? d.custom_excerpt : ( d.content_text || '' ), 25 ) : '';
            case 'post_url':             return d ? d.permalink : '';
            case 'post_id':              return d ? String( d.id ) : '';
            case 'post_date':            return d && d.dates ? d.dates['default'] : '';
            case 'post_time':            return d && d.times ? d.times['default'] : '';
            case 'post_modified':        return d && d.modified ? d.modified['default'] : '';
            case 'post_author':          return d && d.author ? d.author.name : '';
            case 'post_author_url':      return d && d.author ? d.author.url : '';
            case 'post_featured_image':  return ( d && d.image && d.image.sizes ) ? ( d.image.sizes.large || d.image.sizes.full || '' ) : '';
            case 'post_terms':
                var tax = ( arg === 'post_tag' ) ? 'post_tag' : 'category';
                var list = ( d && d.terms && d.terms[ tax ] ) ? d.terms[ tax ] : [];
                return list.map( function( t ) { return t.name; } ).join( ', ' );
            case 'post_comments_count':  return ( d && d.comments ) ? String( d.comments.count ) : '';
            case 'archive_title':        return a ? ( a.prefix ? a.prefix + ' ' + a.title : a.title ) : '';
            case 'archive_description':  return a ? String( a.description || '' ).replace( /<[^>]+>/g, '' ) : '';
            case 'site_title':           return cfg.siteTitle || '';
            case 'site_tagline':         return cfg.siteTagline || '';
            case 'site_url':             return cfg.homeUrl || '';
            case 'site_logo':            return cfg.customLogoUrl || '';
            case 'current_year':         return String( new Date().getFullYear() );
            case 'current_date':         return new Date().toLocaleDateString();
            // Custom fields are only known on the server: return null so the fallback (if any)
            // is used in the canvas; the real value appears on the front end.
            case 'custom_field':         return null;
            case 'user_name':            return cfg.userName || '';
        }
        return '';
    }

    function resolveDynamicString( text ) {
        if ( typeof text !== 'string' || text.indexOf( '{{' ) === -1 ) return text;
        return text.replace( /\{\{\s*lk:([a-z0-9_]+)(?::([^|}]*))?(?:\|([^}]*))?\}\}/gi, function( all, name, arg, fallback ) {
            var key = String( name ).toLowerCase();
            var a = ( arg || '' ).trim();
            var v = dynamicValue( key, a );
            if ( v && String( v ).trim() ) return v;
            if ( fallback && fallback.trim() ) return fallback.trim();
            // No value and no fallback: show a hint for custom fields so the field is not blank.
            return ( key === 'custom_field' && a ) ? '[' + a + ']' : '';
        } );
    }

    function resolveDynamicValue( value ) {
        if ( typeof value === 'string' ) return resolveDynamicString( value );
        if ( Array.isArray( value ) ) return value.map( resolveDynamicValue );
        if ( value && typeof value === 'object' ) {
            var out = {};
            for ( var k in value ) { if ( Object.prototype.hasOwnProperty.call( value, k ) ) out[k] = resolveDynamicValue( value[k] ); }
            return out;
        }
        return value;
    }

    function hasDynamic( value ) {
        if ( typeof value === 'string' ) return value.indexOf( '{{' ) !== -1;
        if ( value && typeof value === 'object' ) {
            for ( var k in value ) { if ( Object.prototype.hasOwnProperty.call( value, k ) && hasDynamic( value[k] ) ) return true; }
        }
        return false;
    }

    function resolveDynamicElement( el ) {
        if ( ! el || ! el.settings || ! hasDynamic( el.settings ) ) return el;
        var clone = {};
        for ( var k in el ) { if ( Object.prototype.hasOwnProperty.call( el, k ) ) clone[k] = el[k]; }
        clone.settings = resolveDynamicValue( el.settings );
        return clone;
    }

    /**
     * Canvas Widget Renderers Registry Map
     */
    var widgetRenderers = {
        'heading':      renderHeading,
        'text-editor':  renderTextEditor,
        'image':        renderImage,
        'button':       renderButton,
        'icon':         renderIcon,
        'spacer':       renderSpacer,
        'divider':      renderDivider,
        'video':        renderVideo,
        'image-box':    renderImageBox,
        'icon-box':     renderIconBox,
        'icon-list':    renderIconList,
        'social-icons': renderSocialIcons,
        'html':         renderHtml,
        'shortcode':    renderShortcode,
        'site-logo':    renderSiteLogo,
        'site-title':   renderSiteTitle,
        // Real menu from Appearance > Menus, rendered by the server (was a mock list before)
        'nav-menu':     function( el ) { return renderServerWidget( el, 'nav-menu', 'Nav Menu' ); },
        'search-form':  function( el ) { return renderServerWidget( el, 'search-form', 'Search Form' ); },
        'post-title':          renderPostTitle,
        'post-featured-image': renderPostFeaturedImage,
        'post-excerpt':        renderPostExcerpt,
        'post-info':           renderPostInfo,
        'post-content':        renderPostContent,
        'author-box':          renderAuthorBox,
        'post-navigation':     renderPostNavigation,
        'post-comments':       renderPostComments,
        'archive-title':       renderArchiveTitle,
        'archive-description': renderArchiveDescription,
        'posts':               renderPosts,
        'loop-grid':           function( el ) { return renderServerWidget( el, 'loop-grid', 'Loop Grid' ); },
        'marquee':             function( el ) { return renderServerWidget( el, 'marquee', 'Marquee' ); },
        'product-grid': function( el ) { return renderServerWidget( el, 'product-grid', 'Product Grid' ); },
        'add-to-cart': function( el ) { return renderServerWidget( el, 'add-to-cart', 'Add to Cart' ); },
        'product-price': function( el ) { return renderServerWidget( el, 'product-price', 'Product Price' ); },
        'product-gallery': function( el ) { return renderServerWidget( el, 'product-gallery', 'Product Gallery' ); },
        'cart-icon': function( el ) { return renderServerWidget( el, 'cart-icon', 'Cart Icon' ); },
        'cart-table': function( el ) { return renderServerWidget( el, 'cart-table', 'Cart' ); },
        'checkout-form': function( el ) { return renderServerWidget( el, 'checkout-form', 'Checkout' ); },
        'product-categories': function( el ) { return renderServerWidget( el, 'product-categories', 'Product Categories' ); },
        'flip-box':            function( el ) { return renderServerWidget( el, 'flip-box', 'Flip Box' ); },
        'price-table':         function( el ) { return renderServerWidget( el, 'price-table', 'Price Table' ); },
        'countdown':           function( el ) { return renderServerWidget( el, 'countdown', 'Countdown' ); },
        'animated-headline':   function( el ) { return renderServerWidget( el, 'animated-headline', 'Animated Headline' ); },
        'call-to-action':      function( el ) { return renderServerWidget( el, 'call-to-action', 'Call to Action' ); },
        'share-buttons':       function( el ) { return renderServerWidget( el, 'share-buttons', 'Share Buttons' ); },
        'team-member':         function( el ) { return renderServerWidget( el, 'team-member', 'Team Member' ); },
        'testimonial-carousel': function( el ) { return renderServerWidget( el, 'testimonial-carousel', 'Testimonial Carousel' ); },
        'blockquote':          function( el ) { return renderServerWidget( el, 'blockquote', 'Blockquote' ); },
        'business-hours':      function( el ) { return renderServerWidget( el, 'business-hours', 'Business Hours' ); },
        'hotspot':             function( el ) { return renderServerWidget( el, 'hotspot', 'Image Hotspots' ); },
        'portfolio':           function( el ) { return renderServerWidget( el, 'portfolio', 'Filterable Portfolio' ); },
        'login-form':          function( el ) { return renderServerWidget( el, 'login-form', 'Login Form' ); },
        'product-filters':     function( el ) { return renderServerWidget( el, 'product-filters', 'Product Filters' ); },
        'mini-cart':           function( el ) { return renderServerWidget( el, 'mini-cart', 'Mini Cart' ); },
        'product-rating':      function( el ) { return renderServerWidget( el, 'product-rating', 'Product Rating' ); },
        'my-account':          function( el ) { return renderServerWidget( el, 'my-account', 'My Account' ); },
        'pagination':          renderPagination,
        'accordion': function( el ) { return renderServerWidget( el, 'accordion', 'Accordion' ); },
        'toggle': function( el ) { return renderServerWidget( el, 'toggle', 'Toggle' ); },
        'tabs': function( el ) { return renderServerWidget( el, 'tabs', 'Tabs' ); },
        'counter': function( el ) { return renderServerWidget( el, 'counter', 'Counter' ); },
        'progress-bar': function( el ) { return renderServerWidget( el, 'progress-bar', 'Progress Bar' ); },
        'alert': function( el ) { return renderServerWidget( el, 'alert', 'Alert' ); },
        'star-rating': function( el ) { return renderServerWidget( el, 'star-rating', 'Star Rating' ); },
        'testimonial': function( el ) { return renderServerWidget( el, 'testimonial', 'Testimonial' ); },
        'image-carousel': function( el ) { return renderServerWidget( el, 'image-carousel', 'Image Carousel' ); },
        'image-gallery': function( el ) { return renderServerWidget( el, 'image-gallery', 'Image Gallery' ); },
        'google-maps': function( el ) { return renderServerWidget( el, 'google-maps', 'Google Maps' ); },
        'contact-form': function( el ) { return renderServerWidget( el, 'contact-form', 'Contact Form' ); },
        'tooltip': function( el ) { return renderServerWidget( el, 'tooltip', 'Tooltip' ); },
        'table-of-contents': function( el ) { return renderServerWidget( el, 'table-of-contents', 'Table of Contents' ); },
        'collection-list': function( el ) { return renderServerWidget( el, 'collection-list', 'Collection List' ); }
    };

    /**
     * Guard to verify all registered widgets have canvas renderers
     */
    function checkCanvasWidgetRenderers() {
        if ( window.parent && window.parent.LK && window.parent.LK.widgets ) {
            var pWidgets = window.parent.LK.widgets;
            for ( var wType in pWidgets ) {
                if ( ! pWidgets.hasOwnProperty( wType ) ) continue;
                if ( ! widgetRenderers[ wType ] ) {
                    console.warn( 'Loopkit Canvas Guard: Registered widget "' + wType + '" has NO canvas renderer!' );
                }
            }
        }
    }


    /**
     * Build empty state HTML.
     */
    function buildEmptyState() {
        var html = '<div class="lk-canvas-empty" id="lk-canvas-empty">';
        html += '  <div class="lk-empty-inner">';
        html += '    <div class="lk-empty-icon"><span class="dashicons dashicons-plus-alt2"></span></div>';
        html += '    <div class="lk-empty-text">Drag widget here</div>';
        html += '  </div>';
        html += '</div>';
        return html;
    }

    /**
     * Build Add New Section area and structure picker at page bottom.
     */
    function buildAddSectionArea() {
        var html = '<div class="lk-add-section-area" id="lk-add-section-area">';
        html += '  <div class="lk-add-section-box">';
        html += '    <div class="lk-add-section-buttons" id="lk-add-section-buttons">';
        html += '      <button type="button" class="lk-add-section-circle-btn lk-btn-add-section" id="lk-btn-open-structure" title="Add New Section">';
        html += '        <span class="dashicons dashicons-plus-alt2"></span>';
        html += '      </button>';
        html += '    </div>';
        html += '    <div class="lk-structure-picker" id="lk-structure-picker">';
        html += '      <div class="lk-structure-title">Select your structure</div>';
        html += '      <div class="lk-structure-presets">';
        html += '        <div class="lk-structure-preset" data-preset="100" title="1 Column"><div class="lk-preset-col" style="flex:1;"></div></div>';
        html += '        <div class="lk-structure-preset" data-preset="50-50" title="2 Columns (50/50)"><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:1;"></div></div>';
        html += '        <div class="lk-structure-preset" data-preset="33-33-33" title="3 Columns (33/33/33)"><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:1;"></div></div>';
        html += '        <div class="lk-structure-preset" data-preset="25-25-25-25" title="4 Columns (25/25/25/25)"><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:1;"></div></div>';
        html += '        <div class="lk-structure-preset" data-preset="33-66" title="2 Columns (33/66)"><div class="lk-preset-col" style="flex:1;"></div><div class="lk-preset-col" style="flex:2;"></div></div>';
        html += '        <div class="lk-structure-preset" data-preset="66-33" title="2 Columns (66/33)"><div class="lk-preset-col" style="flex:2;"></div><div class="lk-preset-col" style="flex:1;"></div></div>';
        html += '      </div>';
        html += '      <button type="button" class="lk-structure-close" id="lk-structure-close" title="Close"><span class="dashicons dashicons-no-alt"></span></button>';
        html += '    </div>';
        html += '  </div>';
        html += '</div>';
        return html;
    }

    /**
     * Bind actions for bottom Add New Section area and structure picker.
     */
    function bindBottomSectionActions() {
        var openBtn = document.getElementById( 'lk-btn-open-structure' );
        var picker  = document.getElementById( 'lk-structure-picker' );
        var btns    = document.getElementById( 'lk-add-section-buttons' );
        var closeBtn = document.getElementById( 'lk-structure-close' );

        if ( openBtn && picker && btns ) {
            openBtn.onclick = function( e ) {
                e.stopPropagation();
                btns.style.display = 'none';
                picker.style.display = 'block';
            };
        }

        if ( closeBtn && picker && btns ) {
            closeBtn.onclick = function( e ) {
                e.stopPropagation();
                picker.style.display = 'none';
                btns.style.display = 'flex';
            };
        }

        var presets = document.querySelectorAll( '.lk-structure-preset' );
        for ( var i = 0; i < presets.length; i++ ) {
            presets[i].onclick = function( e ) {
                e.stopPropagation();
                var preset = this.getAttribute( 'data-preset' );
                sendToEditor( 'add-preset-section', { preset: preset } );
                if ( picker && btns ) {
                    picker.style.display = 'none';
                    btns.style.display = 'flex';
                }
            };
        }
    }

    /**
     * Bind click, inline edit, and handle interactions.
     */
    function bindElementInteractions() {
        // Single click anywhere on element selects it
        var editables = document.querySelectorAll( '.lk-editable' );
        for ( var i = 0; i < editables.length; i++ ) {
            editables[i].onclick = function( e ) {
                e.stopPropagation();
                var elId = this.getAttribute( 'data-id' ) || this.getAttribute( 'data-lk-id' );
                selectElement( elId );
            };
        }

        // Handle buttons (Add, Edit, Duplicate, Delete)
        var handleBtns = document.querySelectorAll( '.lk-handle-btn' );
        for ( var j = 0; j < handleBtns.length; j++ ) {
            handleBtns[j].onclick = function( e ) {
                e.stopPropagation();
                var action = this.getAttribute( 'data-action' );
                var elId   = this.getAttribute( 'data-id' );
                handleAction( action, elId );
            };
        }

        // Empty container placeholder click
        var emptyContainers = document.querySelectorAll( '.lk-container-empty' );
        for ( var k = 0; k < emptyContainers.length; k++ ) {
            emptyContainers[k].onclick = function( e ) {
                e.stopPropagation();
                // Open the Elements panel and remember this container, so the next widget the
                // user clicks or drags lands here (it used to insert a Heading straight away).
                var parentId = this.getAttribute( 'data-parent-id' );
                sendToEditor( 'open-elements-panel', { containerId: parentId } );
            };
        }

        // Inline editing for Headings
        var headings = document.querySelectorAll( '[data-lk-inline-edit="title"]' );
        for ( var h = 0; h < headings.length; h++ ) {
            headings[h].ondblclick = function( e ) {
                e.stopPropagation();
                this.setAttribute( 'contenteditable', 'true' );
                this.focus();
                this.classList.add( 'lk-inline-editing' );
            };

            headings[h].oninput = function() {
                var parent = this.closest( '.lk-widget' );
                var id = parent ? ( parent.getAttribute( 'data-id' ) || parent.getAttribute( 'data-lk-id' ) ) : null;
                if ( ! id ) return;
                var clone = this.cloneNode( true );
                var handle = clone.querySelector( '.lk-element-handle' );
                if ( handle ) handle.remove();
                var newTitle = clone.innerText.trim();
                sendToEditor( 'update-element-setting', { elementId: id, key: 'title', value: newTitle } );
            };

            headings[h].onblur = function() {
                this.removeAttribute( 'contenteditable' );
                this.classList.remove( 'lk-inline-editing' );
                var parent = this.closest( '.lk-widget' );
                var id = parent ? ( parent.getAttribute( 'data-id' ) || parent.getAttribute( 'data-lk-id' ) ) : null;
                if ( ! id ) return;
                var clone = this.cloneNode( true );
                var handle = clone.querySelector( '.lk-element-handle' );
                if ( handle ) handle.remove();
                var newTitle = clone.innerText.trim();
                sendToEditor( 'update-element-setting', { elementId: id, key: 'title', value: newTitle } );
            };

            headings[h].onkeydown = function( e ) {
                if ( e.key === 'Enter' ) {
                    e.preventDefault();
                    this.blur();
                }
            };
        }
    }

    /**
     * Initialize drag and drop events ONCE on document to avoid duplicate event listeners.
     */
    function initDragDrop() {
        if ( dragDropInitialized ) return;
        dragDropInitialized = true;

        // Listen for dragging existing elements by their edit handle
        document.addEventListener( 'dragstart', function( e ) {
            var handle = e.target.closest( '.lk-handle-edit' );
            if ( handle ) {
                var id = handle.getAttribute( 'data-id' );
                draggedMoveId = id;
                if ( window.parent ) {
                    window.parent.loopkitDraggedElement = { type: 'move', id: id };
                    window.parent.loopkitDraggedWidget = null;
                }
                if ( e.dataTransfer ) {
                    e.dataTransfer.setData( 'text/plain', 'move:' + id );
                    e.dataTransfer.effectAllowed = 'move';
                }
            }
        });

        document.addEventListener( 'dragend', function() {
            draggedMoveId = null;
            if ( window.parent ) {
                window.parent.loopkitDraggedElement = null;
            }
            clearIndicators();
        });

        // Dragover: auto-scroll and calculate drop indicators
        document.addEventListener( 'dragover', function( e ) {
            e.preventDefault();

            // Auto-scroll when dragging near top or bottom edges
            if ( e.clientY < 40 ) {
                window.scrollBy( 0, -10 );
            } else if ( e.clientY > window.innerHeight - 40 ) {
                window.scrollBy( 0, 10 );
            }

            var dragInfo = getDraggedInfo( e );
            if ( ! dragInfo ) {
                clearIndicators();
                return;
            }

            if ( e.dataTransfer ) {
                e.dataTransfer.dropEffect = ( dragInfo.type === 'move' ) ? 'move' : 'copy';
            }

            // Find innermost valid target under cursor
            var target = document.elementFromPoint( e.clientX, e.clientY );
            if ( ! target ) return;

            // Global header/footer template shown on page canvas cannot receive drops
            if ( target.closest( '.lk-global-part-wrap, .loopkit-location-header, .loopkit-location-footer' ) ) {
                clearIndicators();
                return;
            }

            var dropZone = target.closest( '.lk-widget, .lk-container-empty, .lk-con-inner, .lk-section-inner, .lk-con, .lk-section, .lk-canvas-empty, #lk-canvas-root' );
            if ( ! dropZone || dropZone.closest( '.lk-global-part-wrap, .loopkit-location-header, .loopkit-location-footer' ) ) {
                clearIndicators();
                return;
            }

            // If moving, cannot drop into itself or its own children
            if ( dragInfo.type === 'move' && dragInfo.id ) {
                var draggedEl = document.querySelector( '[data-id="' + dragInfo.id + '"], [data-lk-id="' + dragInfo.id + '"]' );
                if ( draggedEl && ( draggedEl === dropZone || draggedEl.contains( dropZone ) ) ) {
                    if ( e.dataTransfer ) e.dataTransfer.dropEffect = 'none';
                    clearIndicators();
                    return;
                }
            }

            updateDropIndicator( dropZone, e.clientX, e.clientY, dragInfo );
        });

        document.addEventListener( 'dragleave', function( e ) {
            // Only clear if leaving document or canvas root
            if ( ! e.relatedTarget || e.relatedTarget.nodeName === 'HTML' ) {
                clearIndicators();
            }
        });

        // Drop: execute insertion/move exactly ONCE
        document.addEventListener( 'drop', function( e ) {
            e.preventDefault();
            e.stopPropagation();

            if ( e.target && e.target.closest( '.lk-global-part-wrap, .loopkit-location-header, .loopkit-location-footer' ) ) {
                clearIndicators();
                return;
            }

            var dragInfo = getDraggedInfo( e );
            var targetData = activeDropTarget;

            clearIndicators();

            if ( ! dragInfo ) return;

            if ( dragInfo.type === 'move' && dragInfo.id && targetData && targetData.targetId ) {
                var draggedEl = document.querySelector( '[data-id="' + dragInfo.id + '"], [data-lk-id="' + dragInfo.id + '"]' );
                if ( draggedEl && draggedEl.contains( document.querySelector( '[data-id="' + targetData.targetId + '"]' ) ) ) {
                    return;
                }
            }

            var payload = {
                dragType:   dragInfo.type,
                widgetType: dragInfo.widgetType || '',
                templateId: dragInfo.templateId || null,
                globalType: dragInfo.globalType || null,
                elementId:  dragInfo.id || '',
                targetId:   targetData ? targetData.targetId : null,
                position:   targetData ? targetData.position : 'inside',
                atRoot:     targetData ? !! targetData.atRoot : true,
            };

            sendToEditor( 'canvas-drop', payload );

            draggedMoveId = null;
            if ( window.parent ) {
                window.parent.loopkitDraggedWidget = null;
                window.parent.loopkitDraggedGlobalId = null;
                window.parent.loopkitDraggedGlobalType = null;
                window.parent.loopkitDraggedTemplateId = null;
                window.parent.loopkitDraggedTemplateType = null;
                window.parent.loopkitDraggedElement = null;
            }
        });
    }

    /**
     * Get information about the currently dragged item.
     */
    function getDraggedInfo( e ) {
        if ( draggedMoveId ) {
            return { type: 'move', id: draggedMoveId };
        }
        if ( window.parent && window.parent.loopkitDraggedElement && window.parent.loopkitDraggedElement.type === 'move' ) {
            return { type: 'move', id: window.parent.loopkitDraggedElement.id };
        }
        if ( window.parent && window.parent.loopkitDraggedWidget ) {
            return {
                type: 'new',
                widgetType: window.parent.loopkitDraggedWidget,
                templateId: window.parent.loopkitDraggedGlobalId || window.parent.loopkitDraggedTemplateId || null,
                globalType: window.parent.loopkitDraggedGlobalType || null,
                templateType: window.parent.loopkitDraggedTemplateType || null
            };
        }
        if ( e.dataTransfer ) {
            var dataText = e.dataTransfer.getData( 'text/plain' );
            if ( dataText && dataText.indexOf( 'move:' ) === 0 ) {
                return { type: 'move', id: dataText.replace( 'move:', '' ) };
            }
            if ( dataText && dataText.indexOf( 'global:' ) === 0 ) {
                var parts = dataText.split( ':' );
                var gid = parts[1];
                var gtype = parts[2] || ( window.parent && window.parent.loopkitDraggedGlobalType ) || 'section';
                return {
                    type: 'new',
                    widgetType: 'global',
                    templateId: gid,
                    globalType: gtype
                };
            }
            if ( dataText && dataText.indexOf( 'template:' ) === 0 ) {
                var tparts = dataText.split( ':' );
                var tid = tparts[1];
                var ttype = tparts[2] || ( window.parent && window.parent.loopkitDraggedTemplateType ) || 'section';
                return {
                    type: 'new',
                    widgetType: 'template',
                    templateId: tid,
                    templateType: ttype
                };
            }
            if ( dataText ) {
                return { type: 'new', widgetType: dataText };
            }
        }
        return null;
    }

    /**
     * Update drop indicators based on target element and cursor position.
     */
    function updateDropIndicator( dropZone, clientX, clientY, dragInfo ) {
        // Clear previous active highlights
        var actives = document.querySelectorAll( '.lk-drop-zone-active' );
        for ( var a = 0; a < actives.length; a++ ) {
            if ( actives[a] !== dropZone ) {
                actives[a].classList.remove( 'lk-drop-zone-active' );
            }
        }

        // Case 1: Empty state placeholder
        if ( dropZone.classList.contains( 'lk-canvas-empty' ) ) {
            dropZone.classList.add( 'lk-drop-zone-active' );
            hideLineIndicator();
            activeDropTarget = { targetId: null, position: 'inside', atRoot: true };
            return;
        }

        if ( dropZone.classList.contains( 'lk-container-empty' ) ) {
            dropZone.classList.add( 'lk-drop-zone-active' );
            hideLineIndicator();
            var pId = dropZone.getAttribute( 'data-parent-id' );
            activeDropTarget = { targetId: pId, position: 'inside', atRoot: false };
            return;
        }

        // Case 2: Child element (Heading widget or child Container)
        var isChildElement = dropZone.classList.contains( 'lk-widget' ) ||
                             ( dropZone.classList.contains( 'lk-con' ) && ! dropZone.classList.contains( 'lk-section' ) );

        if ( isChildElement ) {
            var rect = dropZone.getBoundingClientRect();
            var parentEl = dropZone.parentElement;
            var isRow = false;

            if ( parentEl ) {
                var computed = window.getComputedStyle( parentEl );
                isRow = ( computed.flexDirection.indexOf( 'row' ) !== -1 ) || parentEl.classList.contains( 'lk-con--row' );
            }

            var position = 'after';
            if ( isRow ) {
                var relX = clientX - rect.left;
                position = ( relX < rect.width / 2 ) ? 'before' : 'after';
                showLineIndicator( dropZone, position, 'vertical' );
            } else {
                var relY = clientY - rect.top;
                position = ( relY < rect.height / 2 ) ? 'before' : 'after';
                showLineIndicator( dropZone, position, 'horizontal' );
            }

            var targetId = dropZone.getAttribute( 'data-id' ) || dropZone.getAttribute( 'data-lk-id' );
            activeDropTarget = { targetId: targetId, position: position, atRoot: false };
            return;
        }

        // Case 3: Root Section element or Section / Container padding area
        if ( dropZone.classList.contains( 'lk-section' ) || dropZone.classList.contains( 'lk-section-inner' ) ||
             dropZone.classList.contains( 'lk-con' ) || dropZone.classList.contains( 'lk-con-inner' ) ) {

            // If dragging a Section: sections can ONLY sit at root
            if ( dragInfo.widgetType === 'section' || ( dragInfo.widgetType === 'global' && dragInfo.globalType === 'section' ) || ( dragInfo.type === 'move' && dropZone.classList.contains( 'lk-section' ) ) ) {
                var secRect = dropZone.getBoundingClientRect();
                var secPos = ( ( clientY - secRect.top ) < secRect.height / 2 ) ? 'before' : 'after';
                showLineIndicator( dropZone, secPos, 'horizontal' );
                var secId = dropZone.getAttribute( 'data-id' ) || dropZone.getAttribute( 'data-lk-id' );
                activeDropTarget = { targetId: secId, position: secPos, atRoot: true };
                return;
            }

            // Hovering container/section padding: whole area gets light tint (#E9F6FE)
            var containerEl = dropZone.closest( '.lk-section, .lk-con' );
            if ( containerEl ) {
                containerEl.classList.add( 'lk-drop-zone-active' );
                hideLineIndicator();
                var cId = containerEl.getAttribute( 'data-id' ) || containerEl.getAttribute( 'data-lk-id' );
                activeDropTarget = { targetId: cId, position: 'inside', atRoot: false };
                return;
            }
        }

        // Case 4: Canvas root
        hideLineIndicator();
        activeDropTarget = { targetId: null, position: 'inside', atRoot: true };
    }

    /**
     * Show drop indicator line relative to target element.
     */
    function showLineIndicator( targetEl, position, orientation ) {
        if ( ! dropIndicatorLine ) {
            dropIndicatorLine = document.createElement( 'div' );
            dropIndicatorLine.className = 'lk-drop-indicator-line';
            document.body.appendChild( dropIndicatorLine );
        }

        var rect = targetEl.getBoundingClientRect();
        var scrollY = window.pageYOffset || document.documentElement.scrollTop;
        var scrollX = window.pageXOffset || document.documentElement.scrollLeft;

        dropIndicatorLine.className = 'lk-drop-indicator-line lk-drop-indicator-line--' + orientation;
        dropIndicatorLine.style.display = 'block';

        if ( orientation === 'horizontal' ) {
            var top = ( position === 'before' ) ? ( rect.top + scrollY ) : ( rect.bottom + scrollY );
            dropIndicatorLine.style.top = top + 'px';
            dropIndicatorLine.style.left = ( rect.left + scrollX ) + 'px';
            dropIndicatorLine.style.width = rect.width + 'px';
            dropIndicatorLine.style.height = '4px';
        } else {
            var left = ( position === 'before' ) ? ( rect.left + scrollX ) : ( rect.right + scrollX );
            dropIndicatorLine.style.left = left + 'px';
            dropIndicatorLine.style.top = ( rect.top + scrollY ) + 'px';
            dropIndicatorLine.style.height = rect.height + 'px';
            dropIndicatorLine.style.width = '4px';
        }
    }

    function hideLineIndicator() {
        if ( dropIndicatorLine ) {
            dropIndicatorLine.style.display = 'none';
        }
    }

    function clearIndicators() {
        hideLineIndicator();
        activeDropTarget = null;
        var actives = document.querySelectorAll( '.lk-drop-zone-active' );
        for ( var i = 0; i < actives.length; i++ ) {
            actives[i].classList.remove( 'lk-drop-zone-active' );
        }
    }

    /**
     * Select an element visually and notify the editor.
     */
    function selectElement( id, silent ) {
        deselectAll();
        var el = document.querySelector( '[data-id="' + id + '"], [data-lk-id="' + id + '"]' );
        if ( el ) {
            el.classList.add( 'lk-selected' );
            var parentGlobal = el.closest( '.lk-global' );
            if ( parentGlobal && parentGlobal !== el ) {
                parentGlobal.classList.add( 'lk-global-child-selected' );
            }
            if ( ! silent ) {
                var elType = el.getAttribute( 'data-element_type' ) || el.getAttribute( 'data-lk-type' ) || 'section';
                var widgetType = el.getAttribute( 'data-widget_type' ) || el.getAttribute( 'data-lk-widget' ) || '';
                if ( widgetType.indexOf( '.' ) !== -1 ) {
                    widgetType = widgetType.split( '.' )[0];
                }
                sendToEditor( 'element-selected', { elementId: id, elType: elType, widgetType: widgetType } );
            }
        }
    }

    /**
     * Deselect all elements.
     */
    function deselectAll() {
        var selected = document.querySelectorAll( '.lk-selected' );
        for ( var i = 0; i < selected.length; i++ ) {
            selected[i].classList.remove( 'lk-selected' );
        }
        var globalSelected = document.querySelectorAll( '.lk-global-child-selected' );
        for ( var j = 0; j < globalSelected.length; j++ ) {
            globalSelected[j].classList.remove( 'lk-global-child-selected' );
        }
    }

    /**
     * Handle element action (edit, duplicate, delete, add-section-before, save-as-global).
     */
    function handleAction( action, id ) {
        switch ( action ) {
            case 'edit':
                selectElement( id );
                break;
            case 'delete':
                sendToEditor( 'element-deleted', { elementId: id } );
                break;
            case 'duplicate':
                sendToEditor( 'element-duplicated', { elementId: id } );
                break;
            case 'save-as-global':
                sendToEditor( 'save-as-global', { elementId: id } );
                break;
            case 'save-as-template':
                sendToEditor( 'save-as-template', { elementId: id } );
                break;
            case 'copy':
                sendToEditor( 'copy-element', { elementId: id } );
                break;
            case 'paste':
                sendToEditor( 'paste-element', { elementId: id } );
                break;
            case 'detach':
                sendToEditor( 'detach-element', { elementId: id } );
                break;
            case 'copy-style':
                sendToEditor( 'copy-style', { elementId: id } );
                break;
            case 'paste-style':
                sendToEditor( 'paste-style', { elementId: id } );
                break;
            case 'reset-style':
                sendToEditor( 'reset-style', { elementId: id } );
                break;
            case 'add-section-before':
                sendToEditor( 'add-section-before', { sectionId: id } );
                break;
            case 'add-container-inside':
                sendToEditor( 'add-container', { containerId: id } );
                break;
        }
    }

    // Click outside to deselect
    document.addEventListener( 'click', function( e ) {
        if ( ! e.target.closest( '.lk-editable, .lk-add-section-area, .lk-context-menu' ) ) {
            deselectAll();
            sendToEditor( 'element-deselected' );
        }
    });

    /**
     * Context Menu support (right click on element).
     */
    document.addEventListener( 'contextmenu', function( e ) {
        var el = e.target.closest( '.lk-editable' );
        if ( ! el ) return;

        e.preventDefault();
        e.stopPropagation();

        var existingMenu = document.getElementById( 'lk-context-menu' );
        if ( existingMenu ) existingMenu.remove();

        var id = el.getAttribute( 'data-id' ) || el.getAttribute( 'data-lk-id' );
        var elType = el.getAttribute( 'data-element_type' ) || el.getAttribute( 'data-lk-type' ) || 'element';
        var isGlobal = el.classList.contains( 'lk-global' ) || ( elType === 'global' );
        var isSectionOrContainer = ( elType === 'section' || elType === 'container' );
        var label = ( elType === 'section' ) ? 'Section' : ( elType === 'container' ? 'Container' : ( isGlobal ? 'Global' : 'Heading' ) );

        selectElement( id );

        var menu = document.createElement( 'div' );
        menu.id = 'lk-context-menu';
        menu.className = 'lk-context-menu';
        menu.style.left = Math.min( e.clientX, window.innerWidth - 190 ) + 'px';
        menu.style.top = Math.min( e.clientY, window.innerHeight - 200 ) + 'px';

        var menuItems = [
            '<div class="lk-context-item" data-action="edit"><span class="dashicons dashicons-edit"></span> Edit ' + label + '</div>',
            '<div class="lk-context-item" data-action="duplicate"><span class="dashicons dashicons-admin-page"></span> Duplicate</div>',
            '<div class="lk-context-item" data-action="copy"><span class="dashicons dashicons-admin-page"></span> Copy</div>',
            '<div class="lk-context-item" data-action="paste"><span class="dashicons dashicons-clipboard"></span> Paste</div>'
        ];

        if ( isSectionOrContainer ) {
            menuItems.push( '<div class="lk-context-item" data-action="save-as-template"><span class="dashicons dashicons-portfolio"></span> Save as a Template</div>' );
            if ( ! isGlobal ) {
                menuItems.push( '<div class="lk-context-item" data-action="save-as-global"><span class="dashicons dashicons-admin-site-alt3"></span> Save as Global</div>' );
            }
        }
        if ( isGlobal ) {
            menuItems.push( '<div class="lk-context-item" data-action="detach"><span class="dashicons dashicons-editor-unlink"></span> Detach</div>' );
        }

        menuItems.push( '<div class="lk-context-sep"></div>' );
        menuItems.push( '<div class="lk-context-item" data-action="copy-style"><span class="dashicons dashicons-admin-appearance"></span> Copy Style</div>' );
        menuItems.push( '<div class="lk-context-item" data-action="paste-style"><span class="dashicons dashicons-clipboard"></span> Paste Style</div>' );
        menuItems.push( '<div class="lk-context-item" data-action="reset-style"><span class="dashicons dashicons-undo"></span> Reset Style</div>' );
        menuItems.push( '<div class="lk-context-sep"></div>' );
        menuItems.push( '<div class="lk-context-item" data-action="add-section"><span class="dashicons dashicons-plus-alt2"></span> Add Section</div>' );
        menuItems.push( '<div class="lk-context-sep"></div>' );
        menuItems.push( '<div class="lk-context-item lk-context-item-danger" data-action="delete"><span class="dashicons dashicons-trash"></span> Delete</div>' );

        menu.innerHTML = menuItems.join( '' );

        document.body.appendChild( menu );

        var items = menu.querySelectorAll( '.lk-context-item' );
        for ( var i = 0; i < items.length; i++ ) {
            items[i].onclick = function( evt ) {
                evt.stopPropagation();
                var action = this.getAttribute( 'data-action' );
                menu.remove();
                if ( action === 'add-section' ) {
                    sendToEditor( 'add-section' );
                } else {
                    handleAction( action, id );
                }
            };
        }
    });

    document.addEventListener( 'click', function( e ) {
        if ( ! e.target.closest( '#lk-context-menu' ) ) {
            var menu = document.getElementById( 'lk-context-menu' );
            if ( menu ) menu.remove();
        }


        // Prevent navigation on links inside nav-menu, site-logo, site-title
        var navLink = e.target.closest( '.lk-widget-nav-menu a, .lk-widget-site-title a, .lk-widget-site-logo a, .lk-widget-post-title a, .lk-widget-post-featured-image a, .lk-widget-post-info a, .lk-widget-post-content a, .lk-widget-author-box a, .lk-widget-post-navigation a, .lk-widget-post-comments a, .lk-widget-archive-description a, .lk-widget-posts a, .lk-widget-loop-grid a, .lk-widget-pagination a, .lk-widget-image-carousel a, .lk-widget-image-gallery a, .lk-widget-table-of-contents a' );
        if ( navLink ) {
            e.preventDefault();
        }

        // Prevent submit on search form
        var searchBtn = e.target.closest( '.lk-widget-search-form button, .lk-widget-search-form input[type="submit"], .lk-widget-post-comments button, .lk-widget-post-comments input[type="submit"]' );
        if ( searchBtn ) {
            e.preventDefault();
        }
    });

    /**
     * Sanitize HTML tag.
     */
    function sanitizeTag( tag, allowed ) {
        return allowed.indexOf( tag ) !== -1 ? tag : allowed[0];
    }

    /**
     * Escape HTML to prevent XSS.
     */
    function escapeHtml( str ) {
        if ( typeof str !== 'string' ) return '';
        var div = document.createElement( 'div' );
        div.appendChild( document.createTextNode( str ) );
        return div.innerHTML;
    }

    /**
     * Preload the icon library, then repaint so late-arriving icons appear.
     *
     * The canvas draws as soon as the editor posts its elements, which is
     * before the 2 MB icons.json has finished downloading. On that first pass
     * every icon call hits getPreviewSvg() against an empty cache, which fires
     * the load and returns ''. getIconSvg() then falls back to <i> with a bare
     * class name, which draws nothing. Nothing was listening for the load to
     * finish, so icons stayed blank until the user happened to open the icon
     * picker and something else triggered a repaint.
     *
     * Ask for the library up front and, once it lands, draw the same elements
     * again. If the elements have not arrived yet there is nothing to repaint
     * and the next update-elements message will draw them with icons warm.
     */
    function preloadIconLibrary() {
        var iconsLib = window.LoopkitIcons || ( window.parent && window.parent.LoopkitIcons );
        if ( ! iconsLib || typeof iconsLib.loadIcons !== 'function' ) return;

        var repainted = false;
        iconsLib.loadIcons( function () {
            if ( repainted ) return;
            repainted = true;
            if ( ! currentElements || ! currentElements.length ) return;

            // Keep whatever was selected; a bare repaint would drop it.
            var selectedEl = document.querySelector( '.lk-selected' );
            var selectedId = selectedEl
                ? ( selectedEl.getAttribute( 'data-lk-id' ) || selectedEl.getAttribute( 'data-id' ) )
                : null;

            renderElements( currentElements );

            if ( selectedId ) selectElement( selectedId, true );
        } );
    }

    /**
     * Initialize: notify parent that canvas is ready.
     */
    function init() {
        initDragDrop();
        preloadIconLibrary();

        window.addEventListener( 'keydown', function( e ) {
            if ( ( e.ctrlKey || e.metaKey ) && ( e.key === 'e' || e.key === 'E' ) ) {
                e.preventDefault();
                sendToEditor( 'finder-shortcut' );
            }
        });

        sendToEditor( 'canvas-ready' );
    }

    if ( document.readyState === 'complete' || document.readyState === 'interactive' ) {
        setTimeout( init, 50 );
    } else {
        document.addEventListener( 'DOMContentLoaded', function() {
            setTimeout( init, 50 );
        });
    }

})();
