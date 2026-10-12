/**
 * Loopkit Pro widgets — Countdown, Animated Headline and Marquee.
 *
 * These three behaviours used to be written out twice: once here, and once again
 * verbatim inside loopkit-frontend.js. The copies drifted, so a fix made to one
 * never reached the other — which is how the Animated Headline bug survived two
 * releases. There is now a single implementation of each, in loopkit-frontend.js,
 * and this file only exposes them.
 *
 * If you change how one of these widgets behaves, change it in
 * loopkit-frontend.js — not here.
 */
( function () {
    'use strict';

    function init( root ) {
        var countdownAndHeadline = window.LoopkitPro;
        if ( countdownAndHeadline && typeof countdownAndHeadline.init === 'function' ) {
            countdownAndHeadline.init( root );
        }
        var marquee = window.LoopkitMarquee;
        if ( marquee && typeof marquee.init === 'function' ) {
            marquee.init( root );
        }
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', function () { init( document ); } );
    } else {
        init( document );
    }

    // The editor canvas re-renders widgets; re-scan when it does.
    window.LoopkitProWidgets = { init: init };
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
