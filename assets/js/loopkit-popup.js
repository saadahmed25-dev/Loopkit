/**
 * Loopkit Popups — front end.
 *
 * Opens a popup when one of its triggers fires (page load, scroll depth, exit intent,
 * inactivity, or a click on a chosen selector), respects how often it may appear, and
 * handles closing (button, overlay, Escape). Focus is trapped while a popup is open and
 * returned to whatever was focused before.
 *
 * Any element with the class lk-open-popup-123 (or data-lk-open-popup="123") opens that
 * popup, so buttons and menu links can open popups too.
 */
( function () {
    'use strict';

    var STORE_PREFIX = 'lk_popup_';
    var openPopup = null;
    var lastFocus = null;

    function readSeen( id ) {
        try {
            var session = window.sessionStorage.getItem( STORE_PREFIX + id );
            var local = window.localStorage.getItem( STORE_PREFIX + id );
            return { session: session, local: local ? parseInt( local, 10 ) : 0 };
        } catch ( e ) {
            return { session: null, local: 0 };
        }
    }

    function markSeen( id, config ) {
        try {
            if ( config.frequency === 'session' ) {
                window.sessionStorage.setItem( STORE_PREFIX + id, '1' );
            } else if ( config.frequency === 'days' ) {
                window.localStorage.setItem( STORE_PREFIX + id, String( Date.now() ) );
            }
        } catch ( e ) {}
    }

    function mayShow( id, config ) {
        if ( config.frequency === 'always' ) return true;
        var seen = readSeen( id );
        if ( config.frequency === 'session' ) return ! seen.session;
        if ( config.frequency === 'days' ) {
            if ( ! seen.local ) return true;
            var days = config.frequencyDays || 7;
            return ( Date.now() - seen.local ) > days * 24 * 60 * 60 * 1000;
        }
        return true;
    }

    function open( popup ) {
        if ( openPopup === popup || popup.getAttribute( 'data-lk-open' ) === '1' ) return;
        var config = getConfig( popup );

        lastFocus = document.activeElement;
        popup.removeAttribute( 'hidden' );
        popup.setAttribute( 'aria-hidden', 'false' );
        popup.setAttribute( 'data-lk-open', '1' );
        // next frame so the entrance animation runs
        window.requestAnimationFrame( function () {
            popup.classList.add( 'lk-popup-open' );
        } );

        if ( config.preventScroll ) {
            document.documentElement.style.overflow = 'hidden';
        }

        openPopup = popup;
        markSeen( config.id, config );

        var box = popup.querySelector( '.lk-popup-box' );
        if ( box ) {
            var focusable = box.querySelector( 'input, textarea, select, button, a[href]' );
            ( focusable || box ).focus( { preventScroll: true } );
        }

        popup.dispatchEvent( new CustomEvent( 'loopkit:popup:open', { bubbles: true } ) );
    }

    function close( popup ) {
        if ( ! popup ) return;
        popup.classList.remove( 'lk-popup-open' );
        popup.setAttribute( 'aria-hidden', 'true' );
        popup.removeAttribute( 'data-lk-open' );
        document.documentElement.style.overflow = '';

        window.setTimeout( function () {
            if ( ! popup.classList.contains( 'lk-popup-open' ) ) popup.setAttribute( 'hidden', '' );
        }, 300 );

        if ( openPopup === popup ) openPopup = null;
        if ( lastFocus && lastFocus.focus ) lastFocus.focus( { preventScroll: true } );

        var config = getConfig( popup );
        if ( config.closeStopsIt ) {
            popup.setAttribute( 'data-lk-done', '1' );
        }
        popup.dispatchEvent( new CustomEvent( 'loopkit:popup:close', { bubbles: true } ) );
    }

    function getConfig( popup ) {
        if ( popup.__lkConfig ) return popup.__lkConfig;
        var config = {};
        try {
            config = JSON.parse( popup.getAttribute( 'data-lk-popup' ) || '{}' );
        } catch ( e ) {}
        popup.__lkConfig = config;
        return config;
    }

    function canTrigger( popup ) {
        if ( popup.getAttribute( 'data-lk-done' ) === '1' ) return false;
        if ( openPopup ) return false;                       // one at a time
        return mayShow( getConfig( popup ).id, getConfig( popup ) );
    }

    function setupTriggers( popup ) {
        var config = getConfig( popup );
        var t = config.triggers || {};

        if ( t.load !== false && t.load !== undefined ) {
            window.setTimeout( function () {
                if ( canTrigger( popup ) ) open( popup );
            }, ( t.load || 0 ) * 1000 );
        }

        if ( t.scroll !== false && t.scroll !== undefined ) {
            var onScroll = function () {
                var doc = document.documentElement;
                var max = doc.scrollHeight - window.innerHeight;
                var pct = max > 0 ? ( window.scrollY / max ) * 100 : 100;
                if ( pct >= t.scroll && canTrigger( popup ) ) {
                    open( popup );
                    window.removeEventListener( 'scroll', onScroll );
                }
            };
            window.addEventListener( 'scroll', onScroll, { passive: true } );
        }

        if ( t.exit ) {
            var onLeave = function ( e ) {
                if ( e.clientY <= 0 && canTrigger( popup ) ) {
                    open( popup );
                    document.removeEventListener( 'mouseout', onLeave );
                }
            };
            document.addEventListener( 'mouseout', onLeave );
        }

        if ( t.idle !== false && t.idle !== undefined ) {
            var timer = null;
            var reset = function () {
                window.clearTimeout( timer );
                timer = window.setTimeout( function () {
                    if ( canTrigger( popup ) ) open( popup );
                }, ( t.idle || 30 ) * 1000 );
            };
            [ 'mousemove', 'keydown', 'scroll', 'touchstart', 'click' ].forEach( function ( ev ) {
                document.addEventListener( ev, reset, { passive: true } );
            } );
            reset();
        }

        if ( t.click ) {
            document.addEventListener( 'click', function ( e ) {
                var hit = e.target.closest ? e.target.closest( t.click ) : null;
                if ( hit ) {
                    e.preventDefault();
                    // An explicit click is the visitor asking for it, so ignore "closed already".
                    popup.removeAttribute( 'data-lk-done' );
                    open( popup );
                }
            } );
        }
    }

    function init() {
        var popups = document.querySelectorAll( '.lk-popup[data-lk-popup]' );
        Array.prototype.forEach.call( popups, setupTriggers );

        // Close button / overlay
        document.addEventListener( 'click', function ( e ) {
            if ( ! e.target.closest ) return;
            if ( e.target.closest( '[data-lk-popup-close]' ) ) {
                e.preventDefault();
                close( e.target.closest( '.lk-popup' ) );
                return;
            }
            var overlay = e.target.closest( '[data-lk-popup-close-overlay]' );
            if ( overlay ) {
                var popup = overlay.closest( '.lk-popup' );
                if ( popup && getConfig( popup ).closeOnOverlay ) close( popup );
                return;
            }
            // Anything can open a popup: class lk-open-popup-123 or data-lk-open-popup="123"
            var opener = e.target.closest( '[data-lk-open-popup], [class*="lk-open-popup-"]' );
            if ( opener ) {
                var id = opener.getAttribute( 'data-lk-open-popup' );
                if ( ! id ) {
                    var match = ( opener.className || '' ).match( /lk-open-popup-(\d+)/ );
                    id = match ? match[1] : '';
                }
                var target = id ? document.getElementById( 'lk-popup-' + id ) : null;
                if ( target ) {
                    e.preventDefault();
                    target.removeAttribute( 'data-lk-done' );
                    open( target );
                }
            }
        } );

        // Escape closes, Tab stays inside
        document.addEventListener( 'keydown', function ( e ) {
            if ( ! openPopup ) return;
            if ( e.key === 'Escape' && getConfig( openPopup ).closeOnEsc ) {
                close( openPopup );
                return;
            }
            if ( e.key !== 'Tab' ) return;
            var box = openPopup.querySelector( '.lk-popup-box' );
            var items = box ? box.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])' ) : [];
            if ( ! items.length ) return;
            var first = items[0];
            var last = items[ items.length - 1 ];
            if ( e.shiftKey && document.activeElement === first ) {
                e.preventDefault();
                last.focus();
            } else if ( ! e.shiftKey && document.activeElement === last ) {
                e.preventDefault();
                first.focus();
            }
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    // Small public API: LoopkitPopup.open(123) / .close(123)
    window.LoopkitPopup = {
        open: function ( id ) {
            var el = document.getElementById( 'lk-popup-' + id );
            if ( el ) { el.removeAttribute( 'data-lk-done' ); open( el ); }
        },
        close: function ( id ) {
            var el = id ? document.getElementById( 'lk-popup-' + id ) : openPopup;
            close( el );
        }
    };
} )();
