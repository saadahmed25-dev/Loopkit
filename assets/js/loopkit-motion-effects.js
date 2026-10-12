/**
 * LoopKit Motion Effects Script
 * Handles Sticky positioning, Scroll Effects, and Mouse Effects engine.
 *
 * @package Loopkit
 */
(function() {
    'use strict';

    // In editor canvas, disable actual sticking and motion effects so elements remain editable in normal flow
    if (
        ( window.loopkit && window.loopkit.isCanvas === true ) ||
        ( window.loopkitCanvas !== undefined ) ||
        ( document.documentElement && document.documentElement.getAttribute( 'data-lk-canvas' ) === '1' ) ||
        ( document.body && document.body.classList.contains( 'lk-canvas-body' ) )
    ) {
        return;
    }

    var ticking = false;
    var stickyElements = [];
    var scrollElements = [];
    var mouseElements  = [];
    var scrollObserver = null;

    /**
     * Check if user prefers reduced motion
     */
    function isReducedMotion() {
        return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
    }

    /**
     * Determine device category from current window viewport width
     */
    function getCurrentDevice() {
        var w = window.innerWidth;
        if ( w <= 767 ) return 'mobile';
        if ( w <= 1024 ) return 'tablet';
        return 'desktop';
    }

    /**
     * Check if an element effect is allowed on the current viewport device
     */
    function isEffectActiveOnDevice( allowedDevices ) {
        if ( ! allowedDevices || ! Array.isArray( allowedDevices ) || allowedDevices.length === 0 ) return true;
        var current = getCurrentDevice();
        return allowedDevices.indexOf( current ) !== -1;
    }

    /**
     * Reset scroll CSS variables to defaults
     */
    function resetScrollVariables( el ) {
        el.style.removeProperty( '--lk-scroll-translate-x' );
        el.style.removeProperty( '--lk-scroll-translate-y' );
        el.style.removeProperty( '--lk-scroll-rotate' );
        el.style.removeProperty( '--lk-scroll-scale' );
        el.style.removeProperty( '--lk-scroll-opacity' );
        el.style.removeProperty( '--lk-scroll-blur' );
    }

    /**
     * Reset mouse CSS variables to defaults
     */
    function resetMouseVariables( el ) {
        el.style.removeProperty( '--lk-mouse-translate-x' );
        el.style.removeProperty( '--lk-mouse-translate-y' );
        el.style.removeProperty( '--lk-mouse-rotate-x' );
        el.style.removeProperty( '--lk-mouse-rotate-y' );
    }

    /**
     * Calculate individual effect progress from raw element progress p
     */
    function calcEffectProgress( rawP, enter, exit ) {
        var enterVal = ( enter !== undefined && enter !== '' ) ? parseFloat( enter ) / 100 : 0;
        var exitVal  = ( exit !== undefined && exit !== '' ) ? parseFloat( exit ) / 100 : 1;

        if ( exitVal <= enterVal ) {
            return rawP >= enterVal ? 1 : 0;
        }
        var p = ( rawP - enterVal ) / ( exitVal - enterVal );
        return Math.min( 1, Math.max( 0, p ) );
    }

    /* ==========================================================================
       STICKY POSITIONING ENGINE
       ========================================================================== */

    function isStickyActiveOnDevice( el ) {
        var stickyOnAttr = el.getAttribute( 'data-lk-sticky-on' );
        if ( ! stickyOnAttr || stickyOnAttr.trim() === '' ) return true;
        var allowed = stickyOnAttr.split( ',' ).map( function( s ) { return s.trim().toLowerCase(); } );
        var current = getCurrentDevice();
        return allowed.indexOf( current ) !== -1;
    }

    function refreshStickyElements() {
        var nodes = document.querySelectorAll( '.lk-sticky' );
        stickyElements = [];
        for ( var i = 0; i < nodes.length; i++ ) {
            var node = nodes[i];
            var inContainer = node.getAttribute( 'data-lk-sticky-in-container' ) === '1' || node.classList.contains( 'lk-sticky--in-container' );
            var parent = inContainer ? node.parentElement : null;
            if ( inContainer && parent ) {
                var p = node.closest( '.lk-section, .lk-con, .lk-container' );
                if ( p && p !== node ) {
                    parent = p;
                }
            }

            // Ensure intermediate ancestors allow overflow for sticky
            var ancestor = node.parentElement;
            while ( ancestor ) {
                try {
                    var comp = window.getComputedStyle( ancestor );
                    if ( comp.overflow !== 'visible' || comp.overflowX !== 'visible' || comp.overflowY !== 'visible' ) {
                        ancestor.style.setProperty( 'overflow', 'visible', 'important' );
                        ancestor.style.setProperty( 'overflow-x', 'visible', 'important' );
                        ancestor.style.setProperty( 'overflow-y', 'visible', 'important' );
                    }
                } catch ( e ) {}
                ancestor = ancestor.parentElement;
            }

            var isTop = node.classList.contains( 'lk-sticky--top' ) || ! node.classList.contains( 'lk-sticky--bottom' );
            if ( isTop ) {
                node.style.setProperty( 'align-self', 'flex-start', 'important' );
            } else {
                node.style.setProperty( 'align-self', 'flex-end', 'important' );
            }

            stickyElements.push( {
                el: node,
                inContainer: inContainer,
                parent: parent,
                isTop: isTop
            } );
        }
    }

    function updateSticky() {
        for ( var i = 0; i < stickyElements.length; i++ ) {
            var item = stickyElements[i];
            var el = item.el;

            var active = isStickyActiveOnDevice( el );
            if ( ! active ) {
                el.style.position = 'static';
                el.classList.remove( 'lk-sticky--effects' );
                el.style.removeProperty( '--lk-sticky-translate-y' );
                continue;
            } else {
                el.style.removeProperty( 'position' );
            }

            var rect = el.getBoundingClientRect();
            var styles = window.getComputedStyle( el );

            var effectsOffset = parseFloat( el.style.getPropertyValue( '--lk-sticky-effects-offset' ) ) ||
                                parseFloat( styles.getPropertyValue( '--lk-sticky-effects-offset' ) ) || 0;

            if ( effectsOffset > 0 ) {
                if ( item.isTop ) {
                    var stickyOffset = parseFloat( el.style.getPropertyValue( '--lk-sticky-offset' ) ) ||
                                       parseFloat( styles.getPropertyValue( '--lk-sticky-offset' ) ) || 0;
                    if ( rect.top <= ( stickyOffset + 1 ) ) {
                        var distanceFromTop = window.pageYOffset || document.documentElement.scrollTop || 0;
                        if ( distanceFromTop >= effectsOffset ) {
                            el.classList.add( 'lk-sticky--effects' );
                        } else {
                            el.classList.remove( 'lk-sticky--effects' );
                        }
                    } else {
                        el.classList.remove( 'lk-sticky--effects' );
                    }
                } else {
                    el.classList.add( 'lk-sticky--effects' );
                }
            }

            if ( item.inContainer && item.parent ) {
                var parentRect = item.parent.getBoundingClientRect();
                var sOffset = parseFloat( el.style.getPropertyValue( '--lk-sticky-offset' ) ) ||
                              parseFloat( styles.getPropertyValue( '--lk-sticky-offset' ) ) || 0;
                var elHeight = rect.height;
                var parentHeight = parentRect.height;

                if ( item.isTop ) {
                    if ( parentHeight <= elHeight ) {
                        el.style.setProperty( '--lk-sticky-translate-y', '0px' );
                    } else {
                        var maxShift = parentHeight - elHeight;
                        var diff = parentRect.bottom - ( sOffset + elHeight );
                        if ( diff < 0 ) {
                            var clampedY = Math.max( -maxShift, diff );
                            el.style.setProperty( '--lk-sticky-translate-y', clampedY + 'px' );
                        } else {
                            el.style.setProperty( '--lk-sticky-translate-y', '0px' );
                        }
                    }
                } else {
                    if ( parentHeight <= elHeight ) {
                        el.style.setProperty( '--lk-sticky-translate-y', '0px' );
                    } else {
                        var maxShift = parentHeight - elHeight;
                        var diff = parentRect.top + sOffset;
                        if ( diff > 0 ) {
                            var clampedY = Math.min( maxShift, diff );
                            el.style.setProperty( '--lk-sticky-translate-y', clampedY + 'px' );
                        } else {
                            el.style.setProperty( '--lk-sticky-translate-y', '0px' );
                        }
                    }
                }
            }
        }
    }

    /* ==========================================================================
       SCROLL EFFECTS ENGINE
       ========================================================================== */

    function refreshScrollElements() {
        if ( scrollObserver ) {
            scrollObserver.disconnect();
        }

        var nodes = document.querySelectorAll( '.lk-has-scroll-effects, [data-lk-scroll-settings]' );
        scrollElements = [];

        var canObserve = typeof window.IntersectionObserver === 'function';
        if ( canObserve && ! scrollObserver ) {
            scrollObserver = new IntersectionObserver( function( entries ) {
                for ( var i = 0; i < entries.length; i++ ) {
                    var entry = entries[i];
                    for ( var j = 0; j < scrollElements.length; j++ ) {
                        if ( scrollElements[j].el === entry.target ) {
                            scrollElements[j].inView = entry.isIntersecting;
                            break;
                        }
                    }
                }
            }, {
                rootMargin: '100px 0px 100px 0px'
            } );
        }

        for ( var i = 0; i < nodes.length; i++ ) {
            var node = nodes[i];
            var raw = node.getAttribute( 'data-lk-scroll-settings' );
            var config = null;
            if ( raw ) {
                try {
                    config = JSON.parse( raw );
                } catch ( e ) {}
            }
            if ( ! config || ! config.effects ) {
                continue;
            }

            // Set transform-origin once from anchor_x and anchor_y
            var ax = ( config.anchor_x !== undefined ) ? parseFloat( config.anchor_x ) : 50;
            var ay = ( config.anchor_y !== undefined ) ? parseFloat( config.anchor_y ) : 50;
            node.style.setProperty( 'transform-origin', ax + '% ' + ay + '%' );

            var item = {
                el: node,
                config: config,
                inView: ! canObserve
            };

            scrollElements.push( item );
            if ( canObserve ) {
                scrollObserver.observe( node );
            }
        }
    }

    function updateScrollEffects() {
        if ( isReducedMotion() ) {
            for ( var i = 0; i < scrollElements.length; i++ ) {
                resetScrollVariables( scrollElements[i].el );
            }
            return;
        }

        var vh = window.innerHeight || document.documentElement.clientHeight || 0;
        var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        var docH = Math.max(
            document.body.scrollHeight,
            document.documentElement.scrollHeight,
            document.body.offsetHeight,
            document.documentElement.offsetHeight,
            document.documentElement.clientHeight
        );

        for ( var i = 0; i < scrollElements.length; i++ ) {
            var item = scrollElements[i];
            var el = item.el;
            var config = item.config;

            // Apply Effects On device check
            if ( ! isEffectActiveOnDevice( config.devices ) ) {
                resetScrollVariables( el );
                continue;
            }

            // Throttle: only compute for elements in view
            if ( ! item.inView ) {
                continue;
            }

            var rect = el.getBoundingClientRect();
            var relativeTo = config.relative_to || 'default';
            var rawP = 0;

            if ( relativeTo === 'entire_page' ) {
                var maxScroll = Math.max( 1, docH - vh );
                rawP = scrollY / maxScroll;
            } else if ( relativeTo === 'viewport' ) {
                rawP = ( 2 * vh > 0 ) ? ( vh - rect.top ) / ( 2 * vh ) : 0;
            } else {
                // Default: element's own box
                var totalDist = vh + rect.height;
                rawP = ( totalDist > 0 ) ? ( vh - rect.top ) / totalDist : 0;
            }

            var effects = config.effects;

            // 1. Vertical Scroll
            if ( effects.vertical ) {
                var vEff = effects.vertical;
                var p = calcEffectProgress( rawP, vEff.enter, vEff.exit );
                var speed = ( vEff.speed !== undefined ) ? parseFloat( vEff.speed ) : 4.0;
                var dirSign = ( vEff.direction === 'down' ) ? 1 : -1;
                var translateY = dirSign * ( 0.5 - p ) * speed * 100;
                el.style.setProperty( '--lk-scroll-translate-y', translateY.toFixed( 2 ) + 'px' );
            }

            // 2. Horizontal Scroll
            if ( effects.horizontal ) {
                var hEff = effects.horizontal;
                var p = calcEffectProgress( rawP, hEff.enter, hEff.exit );
                var speed = ( hEff.speed !== undefined ) ? parseFloat( hEff.speed ) : 4.0;
                var dirSign = ( hEff.direction === 'right' ) ? 1 : -1;
                var translateX = dirSign * ( 0.5 - p ) * speed * 100;
                el.style.setProperty( '--lk-scroll-translate-x', translateX.toFixed( 2 ) + 'px' );
            }

            // 3. Transparency
            if ( effects.transparency ) {
                var tEff = effects.transparency;
                var p = calcEffectProgress( rawP, tEff.enter, tEff.exit );
                var level = ( tEff.level !== undefined ) ? parseFloat( tEff.level ) : 5.0;
                var targetOpacity = 1;

                if ( tEff.direction === 'fade_out' ) {
                    targetOpacity = 1 - p * ( level / 10 );
                } else if ( tEff.direction === 'fade_in' ) {
                    targetOpacity = p * ( level / 10 );
                } else if ( tEff.direction === 'fade_out_in' ) {
                    targetOpacity = Math.abs( p - 0.5 ) * 2 * ( level / 10 );
                } else {
                    targetOpacity = 1 - Math.abs( p - 0.5 ) * 2 * ( level / 10 );
                }

                targetOpacity = Math.min( 1, Math.max( 0, targetOpacity ) );
                el.style.setProperty( '--lk-scroll-opacity', ( targetOpacity - 1 ).toFixed( 4 ) );
            }

            // 4. Blur
            if ( effects.blur ) {
                var bEff = effects.blur;
                var p = calcEffectProgress( rawP, bEff.enter, bEff.exit );
                var level = ( bEff.level !== undefined ) ? parseFloat( bEff.level ) : 5.0;
                var blur = 0;

                if ( bEff.direction === 'blur_in' ) {
                    blur = p * level;
                } else if ( bEff.direction === 'blur_out' ) {
                    blur = ( 1 - p ) * level;
                } else if ( bEff.direction === 'blur_out_in' ) {
                    blur = Math.abs( p - 0.5 ) * 2 * level;
                } else {
                    blur = ( 1 - Math.abs( p - 0.5 ) * 2 ) * level;
                }

                blur = Math.max( 0, blur );
                el.style.setProperty( '--lk-scroll-blur', blur.toFixed( 2 ) + 'px' );
            }

            // 5. Rotate
            if ( effects.rotate ) {
                var rEff = effects.rotate;
                var p = calcEffectProgress( rawP, rEff.enter, rEff.exit );
                var speed = ( rEff.speed !== undefined ) ? parseFloat( rEff.speed ) : 4.0;
                var dirSign = ( rEff.direction === 'counter_clockwise' || rEff.direction === 'counter-clockwise' ) ? -1 : 1;
                var rotate = dirSign * p * speed * 36;
                el.style.setProperty( '--lk-scroll-rotate', rotate.toFixed( 2 ) + 'deg' );
            }

            // 6. Scale
            if ( effects.scale ) {
                var sEff = effects.scale;
                var p = calcEffectProgress( rawP, sEff.enter, sEff.exit );
                var speed = ( sEff.speed !== undefined ) ? parseFloat( sEff.speed ) : 4.0;
                var scale = 1;

                if ( sEff.direction === 'scale_down' ) {
                    scale = 1 - p * ( speed / 10 );
                } else {
                    scale = 1 + p * ( speed / 10 );
                }

                scale = Math.max( 0, scale );
                el.style.setProperty( '--lk-scroll-scale', scale.toFixed( 4 ) );
            }
        }
    }

    /* ==========================================================================
       MOUSE EFFECTS ENGINE
       ========================================================================== */

    function refreshMouseElements() {
        for ( var i = 0; i < mouseElements.length; i++ ) {
            var item = mouseElements[i];
            if ( item.onMove ) item.target.removeEventListener( 'mousemove', item.onMove );
            if ( item.onLeave ) item.target.removeEventListener( 'mouseleave', item.onLeave );
        }
        mouseElements = [];

        var nodes = document.querySelectorAll( '.lk-has-mouse-effects, [data-lk-mouse-settings]' );
        for ( var i = 0; i < nodes.length; i++ ) {
            var node = nodes[i];
            var raw = node.getAttribute( 'data-lk-mouse-settings' );
            var config = null;
            if ( raw ) {
                try {
                    config = JSON.parse( raw );
                } catch ( e ) {}
            }
            if ( ! config || ! config.effects ) {
                continue;
            }

            bindMouseEffects( node, config );
        }
    }

    function bindMouseEffects( el, config ) {
        if ( config.effects.tilt ) {
            if ( el.parentElement ) {
                el.parentElement.style.setProperty( 'perspective', '1000px' );
            }
            el.style.setProperty( 'transform-style', 'preserve-3d' );
        }

        var onMove = function( e ) {
            if ( isReducedMotion() ) {
                resetMouseVariables( el );
                return;
            }

            if ( ! isEffectActiveOnDevice( config.devices ) ) {
                resetMouseVariables( el );
                return;
            }

            var rect = el.getBoundingClientRect();
            if ( rect.width <= 0 || rect.height <= 0 ) return;

            var centerX = rect.left + rect.width / 2;
            var centerY = rect.top + rect.height / 2;

            var normX = ( e.clientX - centerX ) / ( rect.width / 2 );
            var normY = ( e.clientY - centerY ) / ( rect.height / 2 );

            normX = Math.min( 1, Math.max( -1, normX ) );
            normY = Math.min( 1, Math.max( -1, normY ) );

            // Mouse Track
            if ( config.effects.track ) {
                var t = config.effects.track;
                var dirSign = ( t.direction === 'direct' ) ? 1 : -1;
                var speed = ( t.speed !== undefined ) ? parseFloat( t.speed ) : 2.0;

                var tx = dirSign * normX * speed;
                var ty = dirSign * normY * speed;

                el.style.setProperty( '--lk-mouse-translate-x', tx.toFixed( 2 ) + 'px' );
                el.style.setProperty( '--lk-mouse-translate-y', ty.toFixed( 2 ) + 'px' );
            }

            // 3D Tilt
            if ( config.effects.tilt ) {
                var tilt = config.effects.tilt;
                var dirSign = ( tilt.direction === 'direct' ) ? 1 : -1;
                var speed = ( tilt.speed !== undefined ) ? parseFloat( tilt.speed ) : 2.0;

                var rotX = dirSign * ( -normY ) * speed * 5;
                var rotY = dirSign * normX * speed * 5;

                el.style.setProperty( '--lk-mouse-rotate-x', rotX.toFixed( 2 ) + 'deg' );
                el.style.setProperty( '--lk-mouse-rotate-y', rotY.toFixed( 2 ) + 'deg' );
            }
        };

        var onLeave = function() {
            resetMouseVariables( el );
        };

        el.addEventListener( 'mousemove', onMove, { passive: true } );
        el.addEventListener( 'mouseleave', onLeave, { passive: true } );

        mouseElements.push( {
            el: el,
            target: el,
            config: config,
            onMove: onMove,
            onLeave: onLeave
        } );
    }

    /* ==========================================================================
       CENTRAL REQUESTANIMATIONFRAME LOOP & INITIALIZATION
       ========================================================================== */

    function onScrollFrame() {
        ticking = false;

        if ( stickyElements.length > 0 ) {
            updateSticky();
        }

        if ( scrollElements.length > 0 ) {
            updateScrollEffects();
        }
    }

    function onScroll() {
        if ( ! ticking ) {
            window.requestAnimationFrame( onScrollFrame );
            ticking = true;
        }
    }

    var resizeTimer = null;
    function onResize() {
        clearTimeout( resizeTimer );
        resizeTimer = setTimeout( function() {
            refreshStickyElements();
            refreshScrollElements();
            refreshMouseElements();
            onScrollFrame();
        }, 100 );
    }

    function init() {
        refreshStickyElements();
        refreshScrollElements();
        refreshMouseElements();

        if ( stickyElements.length > 0 || scrollElements.length > 0 ) {
            onScrollFrame();
            window.addEventListener( 'scroll', onScroll, { passive: true } );
            window.addEventListener( 'resize', onResize, { passive: true } );
        }
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    window.LoopkitMotionEffects = {
        init: init,
        refresh: function() {
            refreshStickyElements();
            refreshScrollElements();
            refreshMouseElements();
        },
        update: onScrollFrame,
        updateSticky: updateSticky,
        updateScroll: updateScrollEffects,
        calcEffectProgress: calcEffectProgress
    };

})();
