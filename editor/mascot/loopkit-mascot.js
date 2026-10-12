/**
 * Loopkit mascot — behaviour.
 *
 * Moods are a class on the SVG, so the whole character is one asset. This
 * file decides which class is on it and when, and what it says.
 *
 * The restraint here is deliberate. A mascot that speaks on every hover is
 * charming for a day and infuriating by the third, so: a tip waits for the
 * pointer to settle, each widget speaks once per session, and there is an
 * off switch that is remembered.
 */
( function () {
    'use strict';

    var MOODS = [ 'is-happy', 'is-angry', 'is-thinking', 'is-sleeping', 'is-excited' ];

    var cfg   = window.loopkitMascot || {};
    var tips  = cfg.tips || {};
    var text  = cfg.text || {};

    var dock, svg, bubble, title, body, closeBtn;
    var moodTimer = null;
    var hoverTimer = null;
    var sleepTimer = null;
    var said = {};          // widget key -> already spoken this session
    var lastKey = '';
    var lastPick = {};      // so a line is not repeated back to back
    var shyTimer = null;

    /* ----------------------------------------------------------- *
     *  Remembering the off switch
     * ----------------------------------------------------------- */

    function isOff() {
        try {
            var saved = window.localStorage.getItem( 'loopkitMascot' );
            if ( ! saved ) {
                return false;
            }
            // A dismissal is stored as "off:<when>". If the site has switched
            // the mascot back on since, the site wins — otherwise turning it
            // on in settings looks like it does nothing, which is the trap
            // this whole mechanism exists to avoid.
            var when = parseInt( ( saved.split( ':' )[ 1 ] || '0' ), 10 );
            // wp_localize_script sends numbers as strings, and "0" is truthy.
            var onAt = parseInt( cfg.onAt || 0, 10 ) || 0;
            if ( onAt && when < onAt ) {
                window.localStorage.removeItem( 'loopkitMascot' );
                return false;
            }
            return 0 === saved.indexOf( 'off' );
        } catch ( e ) {
            return false; // storage blocked: show it rather than hide it
        }
    }

    function remember( state ) {
        try {
            window.localStorage.setItem(
                'loopkitMascot',
                state + ':' + Math.floor( Date.now() / 1000 )
            );
        } catch ( e ) {
            // Private browsing and the like. The session still works.
        }
    }

    /* ----------------------------------------------------------- *
     *  Moods
     * ----------------------------------------------------------- */

    function setMood( mood, holdFor ) {
        if ( ! svg ) {
            return;
        }
        MOODS.forEach( function ( m ) {
            svg.classList.remove( m );
        } );
        if ( mood ) {
            // Re-adding the class is what replays a one-shot animation.
            void svg.offsetWidth;
            svg.classList.add( mood );
        }
        window.clearTimeout( moodTimer );
        if ( holdFor ) {
            moodTimer = window.setTimeout( function () {
                setMood( '' );
            }, holdFor );
        }
        wake();
    }

    function wake() {
        window.clearTimeout( sleepTimer );
        sleepTimer = window.setTimeout( function () {
            if ( ! bubble || ! bubble.classList.contains( 'is-open' ) ) {
                MOODS.forEach( function ( m ) {
                    svg.classList.remove( m );
                } );
                svg.classList.add( 'is-sleeping' );
            }
        }, 4 * 60 * 1000 );
    }


    /**
     * One of a set, never the same one twice running.
     *
     * @param {Array}  list Lines to choose from.
     * @param {string} slot Where the last choice is remembered.
     * @return {string}
     */
    function pick( list, slot ) {
        if ( ! list || ! list.length ) {
            return '';
        }
        if ( 1 === list.length ) {
            return list[ 0 ];
        }
        var i;
        do {
            i = Math.floor( Math.random() * list.length );
        } while ( i === lastPick[ slot ] );
        lastPick[ slot ] = i;
        return list[ i ];
    }

    /* ----------------------------------------------------------- *
     *  Speaking
     * ----------------------------------------------------------- */

    function say( heading, line, mood ) {
        if ( ! bubble ) {
            return;
        }
        title.textContent = heading;
        body.textContent  = line;
        bubble.classList.add( 'is-open' );
        setMood( mood || 'is-thinking', 0 );
    }

    function hush() {
        if ( bubble ) {
            bubble.classList.remove( 'is-open' );
        }
        setMood( '' );
    }

    /**
     * Tip for a widget, once per session.
     *
     * @param {string} key Widget key.
     */
    function tipFor( key ) {
        if ( ! key || said[ key ] || ! tips[ key ] ) {
            return;
        }
        said[ key ] = true;
        lastKey     = key;
        say( tips[ key ].label || key, tips[ key ].tip, 'is-thinking' );
    }

    /* ----------------------------------------------------------- *
     *  Building the thing
     * ----------------------------------------------------------- */

    function svgMarkup() {
        return '' +
        '<svg class="lkm" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' +
          '<g class="lkm-all">' +
            '<ellipse class="lkm-shadow" cx="50" cy="90" rx="25" ry="4"/>' +
            '<path class="lkm-loop" d="M50 20 a7 7 0 1 1 0.01 0"/>' +
            '<line class="lkm-loop" x1="50" y1="27" x2="50" y2="33"/>' +
            '<rect class="lkm-body" x="20" y="33" width="60" height="52" rx="16"/>' +
            '<rect class="lkm-face" x="29" y="41" width="42" height="30" rx="12"/>' +
            '<path class="lkm-arm lkm-arm-l" d="M20 60 L11 68"/>' +
            '<path class="lkm-arm" d="M80 60 L89 68"/>' +
            '<ellipse class="lkm-cheek" cx="35" cy="60" rx="4" ry="2.6"/>' +
            '<ellipse class="lkm-cheek" cx="65" cy="60" rx="4" ry="2.6"/>' +
            '<path class="lkm-spark" d="M18 34 l2.2 4.6 4.6 2.2 -4.6 2.2 -2.2 4.6 -2.2 -4.6 -4.6 -2.2 4.6 -2.2 z"/>' +
            '<path class="lkm-spark" d="M82 38 l1.8 3.8 3.8 1.8 -3.8 1.8 -1.8 3.8 -1.8 -3.8 -3.8 -1.8 3.8 -1.8 z"/>' +
            '<path class="lkm-brow" d="M35 45 L45 48"/>' +
            '<path class="lkm-brow lkm-brow-r" d="M65 45 L55 48"/>' +
            '<text class="lkm-zzz" x="72" y="34">z</text>' +
            '<g class="lkm-eyes">' +
              '<circle class="lkm-eye" cx="41" cy="53" r="4.6"/>' +
              '<circle class="lkm-eye" cx="59" cy="53" r="4.6"/>' +
              '<path class="lkm-lid" d="M36 53 Q41 57 46 53"/>' +
              '<path class="lkm-lid" d="M54 53 Q59 57 64 53"/>' +
            '</g>' +
            '<path class="lkm-mouth" d="M44 62 Q50 65 56 62"/>' +
          '</g>' +
        '</svg>';
    }

    function build() {
        dock = document.createElement( 'div' );
        dock.className = 'lkm-dock';

        bubble = document.createElement( 'div' );
        bubble.className = 'lkm-bubble';
        bubble.setAttribute( 'role', 'status' );
        bubble.setAttribute( 'aria-live', 'polite' );

        closeBtn = document.createElement( 'button' );
        closeBtn.type = 'button';
        closeBtn.className = 'lkm-bubble-close';
        closeBtn.innerHTML = '&times;';
        closeBtn.setAttribute( 'aria-label', text.dismiss || 'Dismiss' );

        title = document.createElement( 'b' );
        body  = document.createElement( 'span' );

        bubble.appendChild( closeBtn );
        bubble.appendChild( title );
        bubble.appendChild( body );

        var holder = document.createElement( 'div' );
        holder.innerHTML = svgMarkup();
        svg = holder.firstChild;

        dock.appendChild( bubble );
        dock.appendChild( svg );
        document.body.appendChild( dock );

        bind();
        wake();
    }

    /* ----------------------------------------------------------- *
     *  What it listens to
     * ----------------------------------------------------------- */

    function bind() {
        // Close the bubble, and remember a second close as "leave me alone".
        var closes = 0;
        closeBtn.addEventListener( 'click', function ( e ) {
            e.stopPropagation();
            hush();
            closes++;
            if ( closes >= 3 ) {
                offer();
            }
        } );

        // Clicking the mascot hushes it, or wakes it from sleep.
        svg.addEventListener( 'click', function () {
            if ( bubble.classList.contains( 'is-open' ) ) {
                hush();
            } else {
                setMood( 'is-happy', 900 );
            }
        } );

        // Look at it for three seconds and it notices — every time, with a
        // different line each time so it does not read as a stuck recording.
        svg.addEventListener( 'mouseenter', function () {
            if ( bubble.classList.contains( 'is-open' ) ) {
                return;
            }
            shyTimer = window.setTimeout( function () {
                say( text.shyTitle || 'Oh', pick( text.shy, 'shy' ), 'is-happy' );
                // Let it go on its own, so the next look starts clean.
                window.setTimeout( function () {
                    if ( bubble.classList.contains( 'is-open' ) ) {
                        hush();
                    }
                }, 3000 );
            }, 3000 );
        } );
        svg.addEventListener( 'mouseleave', function () {
            window.clearTimeout( shyTimer );
        } );

        // A widget in the panel or on the canvas, under the pointer. The
        // delay matters: without it, crossing the panel fires twenty tips.
        document.addEventListener( 'mouseover', function ( e ) {
            var el = e.target.closest ? e.target.closest( '[data-widget],[data-type]' ) : null;
            if ( ! el ) {
                return;
            }
            var key = el.getAttribute( 'data-widget' ) || el.getAttribute( 'data-type' );
            if ( ! key || key === lastKey ) {
                return;
            }
            window.clearTimeout( hoverTimer );
            hoverTimer = window.setTimeout( function () {
                tipFor( key );
            }, 650 );
        }, true );

        document.addEventListener( 'mouseout', function () {
            window.clearTimeout( hoverTimer );
        }, true );

        // Dragging something in.
        document.addEventListener( 'dragstart', function () {
            setMood( 'is-excited', 0 );
        }, true );
        document.addEventListener( 'dragend', function () {
            setMood( '' );
        }, true );
        // No guess on drop any more: the editor announces loopkit:added once
        // the element is actually in, which a drop does not guarantee.

        // Anything the builder or an admin screen announces.
        document.addEventListener( 'loopkit:added', function () {
            setMood( 'is-happy', 1400 );
        } );
        document.addEventListener( 'loopkit:deleted', protest );
        document.addEventListener( 'loopkit:saved', function () {
            setMood( 'is-happy', 1400 );
        } );
        document.addEventListener( 'loopkit:moved', function () {
            setMood( 'is-excited', 900 );
        } );

        // The admin screens have no custom events, so read the buttons.
        // The admin screens have no events of their own, so their buttons are
        // read instead. Inside the builder the editor announces the real
        // thing, and reading buttons there would fire twice.
        document.addEventListener( 'click', function ( e ) {
            if ( document.getElementById( 'lk-canvas-iframe' ) ) {
                return;
            }
            var t = e.target.closest ? e.target.closest( 'button,a,.lk-row-act' ) : null;
            if ( ! t ) {
                return;
            }
            var cls = ( t.className || '' ).toString();
            if ( /delete|trash|remove/i.test( cls ) ) {
                protest();
            } else if ( /lk-cms-dup|add-row|lk-cms-add/i.test( cls ) ) {
                setMood( 'is-happy', 1400 );
            }
        }, true );

        [ 'click', 'keydown' ].forEach( function ( ev ) {
            document.addEventListener( ev, wake, true );
        } );
    }

    /**
     * Something was deleted — a widget, a page, a template, a row.
     *
     * It objects rather than just scowling, but briefly: this fires on
     * every delete, and a character that argues at length would be
     * unbearable by the second one.
     */
    function protest() {
        say( text.deleteTitle || 'Hey', pick( text.deletes, 'del' ), 'is-angry' );
        window.setTimeout( function () {
            if ( bubble.classList.contains( 'is-open' ) ) {
                hush();
            }
        }, 3200 );
    }

    /** After the third dismissal, stop guessing and ask. */
    function offer() {
        title.textContent = text.offerTitle || 'Too chatty?';
        body.textContent  = '';
        var btn = document.createElement( 'button' );
        btn.type = 'button';
        btn.className = 'button button-small';
        btn.textContent = text.turnOff || 'Turn me off';
        btn.addEventListener( 'click', function () {
            remember( 'off' );
            dock.classList.add( 'is-hidden' );
        } );
        body.appendChild( document.createTextNode( ( text.offerBody || 'You can switch me off and turn me back on in Loopkit settings.' ) + ' ' ) );
        bubble.appendChild( btn );
        bubble.classList.add( 'is-open' );
    }

    /* ----------------------------------------------------------- */

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', start );
    } else {
        start();
    }

    function start() {
        if ( isOff() || ! document.body ) {
            return;
        }
        build();
    }

    // A small handle, so other Loopkit code can nudge it without events.
    window.LoopkitMascot = {
        mood: setMood,
        say: say,
        hush: hush
    };
}() );
