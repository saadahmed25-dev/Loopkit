
;/**
 * Loopkit interactive widgets (Part 9): Accordion, Toggle, Tabs, Alert, Counter, Progress Bar.
 * Vanilla JS, no jQuery. Click behaviour uses event delegation, so it also works for widgets
 * the editor canvas inserts after page load. Counters and progress bars animate once when
 * scrolled into view; without JS (or with reduced motion) they simply show the final value.
 *
 * @package Loopkit
 */
( function() {
    'use strict';

    function setOpen( item, open ) {
        var btn = item.querySelector( '.lk-accordion-title' );
        var panel = item.querySelector( '.lk-accordion-content' );
        item.classList.toggle( 'lk-open', open );
        if ( btn ) btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
        if ( panel ) {
            if ( open ) panel.removeAttribute( 'hidden' );
            else panel.setAttribute( 'hidden', '' );
        }
    }

    function activateTab( tabs, btn ) {
        var titles = tabs.querySelectorAll( ':scope > .lk-tabs-nav > .lk-tab-title' );
        var panels = tabs.querySelectorAll( ':scope > .lk-tabs-panels > .lk-tab-panel' );
        for ( var i = 0; i < titles.length; i++ ) {
            var active = titles[ i ] === btn;
            titles[ i ].classList.toggle( 'lk-active', active );
            titles[ i ].setAttribute( 'aria-selected', active ? 'true' : 'false' );
            titles[ i ].setAttribute( 'tabindex', active ? '0' : '-1' );
            if ( panels[ i ] ) {
                panels[ i ].classList.toggle( 'lk-active', active );
                if ( active ) panels[ i ].removeAttribute( 'hidden' );
                else panels[ i ].setAttribute( 'hidden', '' );
            }
        }
    }

    document.addEventListener( 'click', function( e ) {
        var t = e.target;
        if ( ! t || ! t.closest ) return;

        var accBtn = t.closest( '.lk-accordion-title' );
        if ( accBtn ) {
            var item = accBtn.closest( '.lk-accordion-item' );
            var acc = accBtn.closest( '[data-lk-accordion]' );
            if ( item && acc ) {
                var willOpen = ! item.classList.contains( 'lk-open' );
                if ( willOpen && acc.getAttribute( 'data-multiple' ) !== 'yes' ) {
                    var siblings = acc.querySelectorAll( ':scope > .lk-accordion-item.lk-open' );
                    for ( var s = 0; s < siblings.length; s++ ) setOpen( siblings[ s ], false );
                }
                setOpen( item, willOpen );
            }
            return;
        }

        var tabBtn = t.closest( '.lk-tab-title' );
        if ( tabBtn ) {
            var tabs = tabBtn.closest( '[data-lk-tabs]' );
            if ( tabs ) activateTab( tabs, tabBtn );
            return;
        }

        var dismiss = t.closest( '.lk-alert-dismiss' );
        if ( dismiss ) {
            // In the editor canvas the alert stays visible so it can still be edited.
            if ( document.body.classList.contains( 'lk-canvas-body' ) ) return;
            var alertEl = dismiss.closest( '.lk-alert' );
            if ( alertEl ) alertEl.style.display = 'none';
        }
    } );

    // Keyboard: arrow keys move between tabs.
    document.addEventListener( 'keydown', function( e ) {
        var btn = e.target && e.target.closest ? e.target.closest( '.lk-tab-title' ) : null;
        if ( ! btn || ( e.key !== 'ArrowRight' && e.key !== 'ArrowLeft' && e.key !== 'ArrowDown' && e.key !== 'ArrowUp' ) ) return;
        var list = Array.prototype.slice.call( btn.parentNode.querySelectorAll( '.lk-tab-title' ) );
        var idx = list.indexOf( btn ) + ( ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ) ? 1 : -1 );
        var next = list[ ( idx + list.length ) % list.length ];
        activateTab( btn.closest( '[data-lk-tabs]' ), next );
        next.focus();
        e.preventDefault();
    } );

    function formatNumber( n, decimals, separator ) {
        var s = n.toFixed( decimals );
        if ( separator ) {
            var parts = s.split( '.' );
            parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, ',' );
            s = parts.join( '.' );
        }
        return s;
    }

    function runCounter( el ) {
        var numEl = el.querySelector( '.lk-counter-number' );
        if ( ! numEl ) return;
        var start = parseFloat( el.getAttribute( 'data-start' ) ) || 0;
        var end = parseFloat( el.getAttribute( 'data-end' ) ) || 0;
        var dur = parseInt( el.getAttribute( 'data-duration' ), 10 ) || 2000;
        var dec = parseInt( el.getAttribute( 'data-decimals' ), 10 ) || 0;
        var sep = el.getAttribute( 'data-separator' ) === 'yes';
        var t0 = null;
        function step( ts ) {
            if ( t0 === null ) t0 = ts;
            var p = Math.min( 1, ( ts - t0 ) / dur );
            var eased = 1 - Math.pow( 1 - p, 3 );
            numEl.textContent = formatNumber( start + ( end - start ) * eased, dec, sep );
            if ( p < 1 ) requestAnimationFrame( step );
        }
        numEl.textContent = formatNumber( start, dec, sep );
        requestAnimationFrame( step );
    }

    function runProgress( el ) {
        var fill = el.querySelector( '.lk-progress-fill' );
        if ( ! fill ) return;
        var pct = el.getAttribute( 'data-percent' ) || '0';
        fill.style.transition = 'none';
        fill.style.width = '0%';
        void fill.offsetWidth;
        fill.style.transition = '';
        fill.style.width = pct + '%';
    }

    function initAnimations() {
        if ( document.body.classList.contains( 'lk-canvas-body' ) ) return; // editor: show final values
        if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) return;
        var els = document.querySelectorAll( '[data-lk-counter], [data-lk-progress]' );
        if ( ! els.length ) return;
        var run = function( el ) {
            if ( el.hasAttribute( 'data-lk-counter' ) ) runCounter( el );
            else runProgress( el );
        };
        if ( ! ( 'IntersectionObserver' in window ) ) {
            for ( var i = 0; i < els.length; i++ ) run( els[ i ] );
            return;
        }
        var io = new IntersectionObserver( function( entries ) {
            entries.forEach( function( entry ) {
                if ( entry.isIntersecting ) {
                    io.unobserve( entry.target );
                    run( entry.target );
                }
            } );
        }, { threshold: 0.3 } );
        for ( var j = 0; j < els.length; j++ ) {
            // Start progress bars at 0 so they visibly fill when reached.
            if ( els[ j ].hasAttribute( 'data-lk-progress' ) ) {
                var f = els[ j ].querySelector( '.lk-progress-fill' );
                if ( f ) { f.style.transition = 'none'; f.style.width = '0%'; }
            }
            io.observe( els[ j ] );
        }
    }

    /* ---------- Image Carousel (Part 10) ---------- */
    function carouselState( c ) {
        var track = c.querySelector( '.lk-carousel-track' );
        var slides = track ? track.children : [];
        var width = slides.length ? slides[0].getBoundingClientRect().width : 0;
        var gap = track ? parseFloat( getComputedStyle( track ).columnGap || getComputedStyle( track ).gap ) || 0 : 0;
        var step = width + gap;
        var perView = step > 0 ? Math.max( 1, Math.round( ( track.clientWidth + gap ) / step ) ) : 1;
        var index = step > 0 ? Math.round( track.scrollLeft / step ) : 0;
        return { track: track, count: slides.length, step: step, perView: perView, index: index, last: Math.max( 0, slides.length - perView ) };
    }

    function carouselGo( c, i ) {
        var st = carouselState( c );
        if ( ! st.track || ! st.step ) return;
        if ( i > st.last ) i = ( c.getAttribute( 'data-loop' ) === 'yes' ) ? 0 : st.last;
        if ( i < 0 ) i = ( c.getAttribute( 'data-loop' ) === 'yes' ) ? st.last : 0;
        st.track.scrollTo( { left: i * st.step, behavior: 'smooth' } );
    }

    function carouselDots( c ) {
        var st = carouselState( c );
        var dots = c.querySelectorAll( '.lk-carousel-dot' );
        for ( var d = 0; d < dots.length; d++ ) dots[ d ].classList.toggle( 'lk-active', d === Math.min( st.index, dots.length - 1 ) );
    }

    document.addEventListener( 'click', function( e ) {
        var t = e.target && e.target.closest ? e.target : null;
        if ( ! t ) return;
        var arrow = t.closest( '.lk-carousel-arrow' );
        var dot = t.closest( '.lk-carousel-dot' );
        var c = ( arrow || dot ) ? ( arrow || dot ).closest( '[data-lk-carousel]' ) : null;
        if ( ! c ) return;
        if ( arrow ) carouselGo( c, carouselState( c ).index + ( arrow.classList.contains( 'lk-carousel-next' ) ? 1 : -1 ) );
        if ( dot ) carouselGo( c, Array.prototype.indexOf.call( dot.parentNode.children, dot ) );
    } );

    document.addEventListener( 'scroll', function( e ) {
        var track = e.target && e.target.classList && e.target.classList.contains( 'lk-carousel-track' ) ? e.target : null;
        if ( track ) carouselDots( track.closest( '[data-lk-carousel]' ) );
    }, true );

    function initCarousels() {
        if ( document.body.classList.contains( 'lk-canvas-body' ) ) return; // no autoplay while editing
        if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) return;
        var list = document.querySelectorAll( '[data-lk-carousel][data-autoplay="yes"]' );
        Array.prototype.forEach.call( list, function( c ) {
            var speed = parseInt( c.getAttribute( 'data-speed' ), 10 ) || 5000;
            var paused = false;
            c.addEventListener( 'mouseenter', function() { paused = true; } );
            c.addEventListener( 'mouseleave', function() { paused = false; } );
            c.addEventListener( 'focusin', function() { paused = true; } );
            c.addEventListener( 'focusout', function() { paused = false; } );
            setInterval( function() { if ( ! paused && ! document.hidden ) carouselGo( c, carouselState( c ).index + 1 ); }, speed );
        } );
    }

    /* ---------- Table of Contents (Part 10) ---------- */
    function slugify( text ) {
        return ( text || '' ).toLowerCase().trim().replace( /[^\w\s-]/g, '' ).replace( /\s+/g, '-' ).replace( /-+/g, '-' ).slice( 0, 60 ) || 'section';
    }

    function initTocs() {
        if ( document.body.classList.contains( 'lk-canvas-body' ) ) return; // editor keeps its sample list
        var tocs = document.querySelectorAll( '[data-lk-toc]' );
        if ( ! tocs.length ) return;
        var root = document.querySelector( 'main' ) || document.body;
        Array.prototype.forEach.call( tocs, function( toc ) {
            var list = toc.querySelector( '.lk-toc-list' );
            if ( ! list ) return;
            var sel = ( toc.getAttribute( 'data-tags' ) || 'h2,h3' );
            var heads = Array.prototype.filter.call( root.querySelectorAll( sel ), function( h ) {
                return ! h.closest( '[data-lk-toc]' ) && ! h.closest( '.loopkit-location-header, .loopkit-location-footer, .lk-fallback-header, .lk-fallback-footer' ) && h.textContent.trim();
            } );
            if ( ! heads.length ) { toc.style.display = 'none'; return; }
            var used = {};
            var levels = sel.split( ',' ).map( function( s ) { return parseInt( s.replace( 'h', '' ), 10 ); } );
            var minLevel = Math.min.apply( null, levels );
            var tag = toc.getAttribute( 'data-list' ) === 'ol' ? 'ol' : 'ul';
            list.innerHTML = '';
            var stack = [ { level: minLevel, el: list } ];
            heads.forEach( function( h ) {
                if ( ! h.id ) {
                    var base = slugify( h.textContent ), id = base, n = 2;
                    while ( used[ id ] || document.getElementById( id ) ) { id = base + '-' + n++; }
                    h.id = id;
                }
                used[ h.id ] = true;
                var level = parseInt( h.tagName.substring( 1 ), 10 );
                while ( stack.length > 1 && level < stack[ stack.length - 1 ].level ) stack.pop();
                if ( level > stack[ stack.length - 1 ].level && stack[ stack.length - 1 ].el.lastElementChild ) {
                    var sub = document.createElement( tag );
                    sub.className = 'lk-toc-list lk-toc-sublist';
                    stack[ stack.length - 1 ].el.lastElementChild.appendChild( sub );
                    stack.push( { level: level, el: sub } );
                }
                var li = document.createElement( 'li' );
                li.className = 'lk-toc-item';
                var a = document.createElement( 'a' );
                a.href = '#' + h.id;
                a.textContent = h.textContent.trim();
                li.appendChild( a );
                stack[ stack.length - 1 ].el.appendChild( li );
            } );
        } );
    }

    /* ---------- Contact Form (Part 10) ---------- */
    document.addEventListener( 'submit', function( e ) {
        var form = e.target && e.target.closest ? e.target.closest( '[data-lk-form]' ) : null;
        if ( ! form ) return;
        e.preventDefault();
        if ( document.body.classList.contains( 'lk-canvas-body' ) ) return; // never send from the editor
        var msg = form.querySelector( '.lk-form-message' );
        var btn = form.querySelector( '.lk-form-submit' );
        var cfg = window.loopkitWidgets || {};
        var host = form.closest( '.loopkit' );
        var m = host ? ( host.className.match( /(?:^|\s)loopkit-(\d+)(?:\s|$)/ ) ) : null;
        if ( ! form.checkValidity() ) { form.reportValidity(); return; }
        var data = new FormData( form );
        data.append( 'action', 'loopkit_contact_form' );
        data.append( 'nonce', cfg.formNonce || '' );
        data.append( 'post_id', m ? m[1] : '' );
        data.append( 'element_id', form.getAttribute( 'data-element' ) || '' );
        if ( btn ) btn.disabled = true;
        form.classList.remove( 'lk-form-sent', 'lk-form-failed' );
        fetch( cfg.ajaxUrl || '/wp-admin/admin-ajax.php', { method: 'POST', body: data, credentials: 'same-origin' } )
            .then( function( r ) { return r.json().catch( function() { return { success: false, data: {} }; } ); } )
            .then( function( res ) {
                var text = ( res && res.data && res.data.message ) || '';
                if ( res && res.success ) {
                    form.classList.add( 'lk-form-sent' );
                    form.reset();
                } else {
                    form.classList.add( 'lk-form-failed' );
                }
                if ( msg ) msg.textContent = text;
            } )
            .catch( function() { form.classList.add( 'lk-form-failed' ); } )
            .then( function() { if ( btn ) btn.disabled = false; } );
    } );

    function initAll() {
        initAnimations();
        initCarousels();
        initTocs();
    }

    if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', initAll );
    else initAll();
} )();

;
;/**
 * Loopkit Pro widgets — Countdown and Animated Headline.
 *
 * Both are rendered by PHP first, so they are correct before this file runs.
 */
( function () {
    'use strict';

    /* ---------------- Countdown ---------------- */
    function pad( n ) { return ( n < 10 ? '0' : '' ) + n; }

    function tick( box ) {
        var target = parseInt( box.getAttribute( 'data-lk-countdown' ), 10 );
        var left = Math.max( 0, Math.floor( ( target - Date.now() ) / 1000 ) );

        var values = {
            days: Math.floor( left / 86400 ),
            hours: Math.floor( ( left % 86400 ) / 3600 ),
            minutes: Math.floor( ( left % 3600 ) / 60 ),
            seconds: left % 60
        };

        box.querySelectorAll( '[data-lk-unit]' ).forEach( function ( el ) {
            var unit = el.getAttribute( 'data-lk-unit' );
            var value = values[ unit ];
            var text = unit === 'days' ? String( value ) : pad( value );
            if ( el.textContent !== text ) el.textContent = text;
        } );

        if ( left <= 0 ) {
            var items = box.querySelector( '.lk-countdown-items' );
            var done = box.querySelector( '.lk-countdown-expired' );
            if ( done && done.textContent.trim() ) {
                if ( items ) items.hidden = true;
                done.hidden = false;
            }
            box.dispatchEvent( new CustomEvent( 'loopkit:countdown:end', { bubbles: true } ) );
            return false;
        }
        return true;
    }

    function startCountdowns( root ) {
        ( root || document ).querySelectorAll( '.lk-countdown[data-lk-countdown]' ).forEach( function ( box ) {
            if ( box.__lkRunning ) return;
            box.__lkRunning = true;
            if ( ! tick( box ) ) return;
            var timer = window.setInterval( function () {
                if ( ! document.body.contains( box ) || ! tick( box ) ) window.clearInterval( timer );
            }, 1000 );
        } );
    }

    /* ---------------- Animated Headline ---------------- */
    function startHeadlines( root ) {
        ( root || document ).querySelectorAll( '.lk-animated-headline[data-lk-headline]' ).forEach( function ( head ) {
            if ( head.__lkRunning ) return;
            head.__lkRunning = true;

            var config = {};
            try { config = JSON.parse( head.getAttribute( 'data-lk-headline' ) || '{}' ); } catch ( e ) { return; }
            var words = config.words || [];
            var slot = head.querySelector( '.lk-ah-word' );
            if ( ! slot || words.length < 2 ) return;

            // The speed travels as JSON in a data attribute, so it can arrive as a
            // number, a numeric string, or (from a malformed value) an object.
            // setInterval throws on a non-numeric delay, which silently stopped the
            // headline animating, so coerce it here.
            var speed = parseInt( config.speed, 10 ) || 2200;
            var index = 0;

            if ( config.effect === 'typing' ) {
                var chars = 0, deleting = false;
                var type = function () {
                    var word = words[ index ];
                    chars += deleting ? -1 : 1;
                    slot.textContent = word.slice( 0, chars );
                    var wait = deleting ? 45 : 90;
                    if ( ! deleting && chars === word.length ) { deleting = true; wait = speed; }
                    else if ( deleting && chars === 0 ) { deleting = false; index = ( index + 1 ) % words.length; wait = 240; }
                    window.setTimeout( type, wait );
                };
                slot.textContent = '';
                chars = 0;
                window.setTimeout( type, 300 );
                return;
            }

            window.setInterval( function () {
                index = ( index + 1 ) % words.length;
                slot.textContent = words[ index ];
                // restart the entrance animation
                slot.style.animation = 'none';
                void slot.offsetWidth;
                slot.style.animation = '';
            }, speed );
        } );
    }

    function init( root ) {
        startCountdowns( root );
        startHeadlines( root );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', function () { init( document ); } );
    } else {
        init( document );
    }

    // The editor canvas re-renders widgets; re-scan when it does.
    window.LoopkitPro = { init: init };
} )();

/* ---------------- Marquee ----------------
 * The CSS animation moves the track by exactly half its length (the content is duplicated),
 * so duration = half-length / speed gives a real, consistent pixels-per-second speed no
 * matter how much content each marquee holds.
 */
( function () {
    'use strict';

    function sizeMarquee( marquee ) {
        var baseSpeed = parseFloat( marquee.getAttribute( 'data-lk-marquee-speed' ) ) || 70;
        var fill = marquee.classList.contains( 'lk-marquee-fill' );

        marquee.querySelectorAll( '.lk-marquee-row' ).forEach( function ( row ) {
            var track = row.querySelector( '.lk-marquee-track' );
            if ( ! track ) return;

            var vertical = marquee.classList.contains( 'lk-marquee-vertical' ) ||
                row.classList.contains( 'lk-marquee-vertical-row' ) ||
                row.classList.contains( 'lk-marquee-up' ) || row.classList.contains( 'lk-marquee-down' );

            var speed = parseFloat( row.getAttribute( 'data-lk-row-speed' ) ) || baseSpeed;
            var groups = track.querySelectorAll( '.lk-marquee-group' );
            if ( ! groups.length ) return;

            // Remember one clean copy of the items the first time we touch this row.
            if ( ! track.__lkSource ) track.__lkSource = groups[0].innerHTML;

            var one = groups[0];
            var unit = vertical ? one.offsetHeight : one.offsetWidth;
            if ( ! unit ) return;

            // Fill: repeat the items until one half is at least as long as the visible area,
            // so the strip carries straight on instead of leaving a gap.
            if ( fill ) {
                var visible = vertical ? row.offsetHeight : row.offsetWidth;
                var perCopy = unit / ( track.__lkCopies || 1 );
                var needed = Math.max( 1, Math.ceil( visible / Math.max( 1, perCopy ) ) );

                if ( needed !== ( track.__lkCopies || 1 ) ) {
                    var html = '';
                    for ( var i = 0; i < needed; i++ ) html += track.__lkSource;
                    groups.forEach( function ( g ) { g.innerHTML = html; } );
                    track.__lkCopies = needed;
                    unit = vertical ? one.offsetHeight : one.offsetWidth;
                }
            }

            var seconds = unit / speed;
            track.style.setProperty( '--lk-marquee-duration', seconds.toFixed( 2 ) + 's' );
            track.style.animationDuration = seconds.toFixed( 2 ) + 's';
        } );
    }

    function initMarquees( root ) {
        ( root || document ).querySelectorAll( '.lk-marquee[data-lk-marquee-speed]' ).forEach( sizeMarquee );
    }

    function ready() {
        initMarquees( document );

        // Re-measure when images load, on resize, and when the editor re-renders.
        window.addEventListener( 'resize', function () { initMarquees( document ); } );
        document.querySelectorAll( '.lk-marquee img' ).forEach( function ( img ) {
            if ( ! img.complete ) img.addEventListener( 'load', function () { initMarquees( document ); } );
        } );
        if ( document.fonts && document.fonts.ready ) {
            document.fonts.ready.then( function () { initMarquees( document ); } );
        }
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', ready );
    } else {
        ready();
    }

    window.LoopkitMarquee = { init: initMarquees };
} )();

/* ---------- Testimonial carousel, hotspots and portfolio filters ---------- */
( function () {
    'use strict';

    function initTestimonials( root ) {
        ( root || document ).querySelectorAll( '[data-lk-testimonials]' ).forEach( function ( box ) {
            if ( box.__lkReady ) return;
            box.__lkReady = true;

            var slides = box.querySelectorAll( '.lk-tc-slide' );
            var dots = box.querySelectorAll( '.lk-tc-dot' );
            var index = 0;
            var timer = null;

            function show( next ) {
                index = ( next + slides.length ) % slides.length;
                slides.forEach( function ( slide, i ) { slide.classList.toggle( 'lk-active', i === index ); } );
                dots.forEach( function ( dot, i ) { dot.classList.toggle( 'lk-active', i === index ); } );
            }

            function play() {
                if ( box.dataset.autoplay !== 'yes' || slides.length < 2 ) return;
                stop();
                timer = setInterval( function () { show( index + 1 ); }, parseInt( box.dataset.speed, 10 ) || 5000 );
            }
            function stop() { if ( timer ) clearInterval( timer ); timer = null; }

            var prev = box.querySelector( '.lk-tc-prev' );
            var next = box.querySelector( '.lk-tc-next' );
            if ( prev ) prev.addEventListener( 'click', function () { show( index - 1 ); play(); } );
            if ( next ) next.addEventListener( 'click', function () { show( index + 1 ); play(); } );
            dots.forEach( function ( dot ) {
                dot.addEventListener( 'click', function () { show( parseInt( dot.dataset.index, 10 ) || 0 ); play(); } );
            } );

            box.addEventListener( 'mouseenter', stop );
            box.addEventListener( 'mouseleave', play );
            play();
        } );
    }

    function initHotspots( root ) {
        ( root || document ).querySelectorAll( '[data-lk-hotspot]' ).forEach( function ( box ) {
            if ( box.__lkReady ) return;
            box.__lkReady = true;
            if ( ! box.classList.contains( 'lk-hs-click' ) ) return;

            box.addEventListener( 'click', function ( e ) {
                var marker = e.target.closest( '.lk-hs-marker' );
                if ( ! marker ) return;
                var point = marker.closest( '.lk-hs-point' );
                var open = point.classList.contains( 'lk-open' );

                box.querySelectorAll( '.lk-hs-point' ).forEach( function ( p ) {
                    p.classList.remove( 'lk-open' );
                    var button = p.querySelector( '.lk-hs-marker' );
                    if ( button ) button.setAttribute( 'aria-expanded', 'false' );
                } );

                if ( ! open ) {
                    point.classList.add( 'lk-open' );
                    marker.setAttribute( 'aria-expanded', 'true' );
                }
            } );
        } );
    }

    function initPortfolio( root ) {
        ( root || document ).querySelectorAll( '[data-lk-portfolio]' ).forEach( function ( box ) {
            if ( box.__lkReady ) return;
            box.__lkReady = true;

            box.addEventListener( 'click', function ( e ) {
                var button = e.target.closest( '.lk-pf-filter' );
                if ( ! button ) return;

                var filter = button.dataset.filter;
                box.querySelectorAll( '.lk-pf-filter' ).forEach( function ( b ) { b.classList.toggle( 'lk-active', b === button ); } );
                box.querySelectorAll( '.lk-pf-item' ).forEach( function ( item ) {
                    var terms = ( item.dataset.terms || '' ).split( ' ' );
                    item.classList.toggle( 'lk-hidden', filter !== '*' && terms.indexOf( filter ) === -1 );
                } );
            } );
        } );
    }

    function initAll( root ) {
        initTestimonials( root );
        initHotspots( root );
        initPortfolio( root );
    }

    document.addEventListener( 'DOMContentLoaded', function () { initAll( document ); } );
    if ( document.readyState !== 'loading' ) initAll( document );

    window.LoopkitPro2 = { init: initAll };
} )();

;
;/**
 * Loopkit Nav Menu Interactivity (Vanilla JS, Zero jQuery)
 * Accessible keyboard navigation (Tab, Enter, Space, Esc, Arrows) and mobile toggle.
 *
 * @package Loopkit
 */
(function() {
    'use strict';

    // Delegated click handling for toggle and submenus
    document.addEventListener( 'click', function( e ) {
        var toggle = e.target.closest( '.lk-nav-menu-toggle' );
        if ( toggle ) {
            e.preventDefault();
            var wrapper = toggle.closest( '.lk-nav-menu-wrapper' );
            if ( wrapper ) {
                var open = wrapper.classList.toggle( 'lk-menu-open' );
                toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
            }
            return;
        }

        var ind = e.target.closest( '.lk-submenu-indicator' );
        if ( ind ) {
            e.preventDefault();
            e.stopPropagation();
            var item = ind.closest( '.lk-menu-item' );
            if ( item ) {
                var link = item.querySelector( '> a, > .lk-menu-link' );
                var expanded = item.classList.toggle( 'lk-submenu-open' );
                if ( link ) link.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
            }
            return;
        }

        // Outside click: close open menus
        var openMenus = document.querySelectorAll( '.lk-nav-menu-wrapper.lk-menu-open' );
        openMenus.forEach( function( wrapper ) {
            if ( ! wrapper.contains( e.target ) ) {
                wrapper.classList.remove( 'lk-menu-open' );
                var t = wrapper.querySelector( '.lk-nav-menu-toggle' );
                if ( t ) t.setAttribute( 'aria-expanded', 'false' );
            }
        });
    });

    // Keyboard navigation
    document.addEventListener( 'keydown', function( e ) {
        var activeEl = document.activeElement;
        if ( ! activeEl ) return;

        var menuItem = activeEl.closest( '.lk-menu-item' );
        if ( ! menuItem ) return;

        var wrapper = menuItem.closest( '.lk-nav-menu-wrapper' );
        if ( ! wrapper ) return;

        var subMenu = menuItem.querySelector( '> ul, > .lk-sub-menu' );

        if ( e.key === 'Enter' || e.key === ' ' ) {
            if ( subMenu && ! menuItem.classList.contains( 'lk-submenu-open' ) ) {
                e.preventDefault();
                menuItem.classList.add( 'lk-submenu-open' );
                activeEl.setAttribute( 'aria-expanded', 'true' );
                var firstSub = subMenu.querySelector( 'a' );
                if ( firstSub ) firstSub.focus();
            }
        } else if ( e.key === 'Escape' ) {
            var parentItem = activeEl.closest( '.lk-sub-menu' ) ? activeEl.closest( '.lk-sub-menu' ).closest( '.lk-menu-item' ) : menuItem;
            if ( parentItem ) {
                parentItem.classList.remove( 'lk-submenu-open' );
                var parentLink = parentItem.querySelector( '> a, > .lk-menu-link' );
                if ( parentLink ) {
                    parentLink.setAttribute( 'aria-expanded', 'false' );
                    parentLink.focus();
                }
            }
        } else if ( e.key === 'ArrowDown' ) {
            if ( subMenu && ! activeEl.closest( '.lk-sub-menu' ) ) {
                e.preventDefault();
                menuItem.classList.add( 'lk-submenu-open' );
                activeEl.setAttribute( 'aria-expanded', 'true' );
                var firstLink = subMenu.querySelector( 'a' );
                if ( firstLink ) firstLink.focus();
            } else if ( activeEl.closest( '.lk-sub-menu' ) ) {
                e.preventDefault();
                var subList = Array.from( activeEl.closest( '.lk-sub-menu' ).querySelectorAll( 'a' ) );
                var idx = subList.indexOf( activeEl );
                var nextLink = subList[ ( idx + 1 ) % subList.length ];
                if ( nextLink ) nextLink.focus();
            }
        } else if ( e.key === 'ArrowUp' && activeEl.closest( '.lk-sub-menu' ) ) {
            e.preventDefault();
            var prevList = Array.from( activeEl.closest( '.lk-sub-menu' ).querySelectorAll( 'a' ) );
            var prevIdx = prevList.indexOf( activeEl );
            var prevItem = prevList[ ( prevIdx - 1 + prevList.length ) % prevList.length ];
            if ( prevItem ) prevItem.focus();
        }
    });

})();

;