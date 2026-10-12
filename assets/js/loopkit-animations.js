/**
 * LoopKit Entrance Animations Trigger Script
 * Handles progressive enhancement, IntersectionObserver scroll detection, and prefers-reduced-motion.
 *
 * @package Loopkit
 */
(function() {
    'use strict';

    function initAnimations() {
        // Safe progressive enhancement: mark document as animation-ready
        document.documentElement.classList.add( 'lk-anim-ready' );

        // If user prefers reduced motion, remove all .lk-anim classes immediately
        if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
            var reducedEls = document.querySelectorAll( '.lk-anim' );
            for ( var r = 0; r < reducedEls.length; r++ ) {
                reducedEls[r].classList.remove( 'lk-anim' );
            }
            return;
        }

        var animElements = document.querySelectorAll( '.lk-anim' );
        if ( ! animElements.length ) {
            return;
        }

        // Fallback for environments without IntersectionObserver
        if ( ! ( 'IntersectionObserver' in window ) ) {
            for ( var f = 0; f < animElements.length; f++ ) {
                animElements[f].classList.remove( 'lk-anim' );
            }
            return;
        }

        // Single IntersectionObserver for all animated elements
        var observer = new IntersectionObserver( function( entries, obs ) {
            entries.forEach( function( entry ) {
                if ( entry.isIntersecting ) {
                    var el = entry.target;
                    obs.unobserve( el );
                    requestAnimationFrame( function() {
                        el.classList.remove( 'lk-anim' );
                    } );
                }
            } );
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -40px 0px'
        } );

        for ( var i = 0; i < animElements.length; i++ ) {
            observer.observe( animElements[i] );
        }
    }

    window.LoopkitAnimations = {
        init: initAnimations
    };

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initAnimations );
    } else {
        initAnimations();
    }
})();
