/**
 * Loopkit Lightbox JavaScript
 *
 * @package Loopkit
 */

(function() {
    'use strict';

    function initLightbox() {
        document.addEventListener( 'click', function( e ) {
            var target = e.target.closest( '[data-loopkit-lightbox]' );
            if ( ! target ) return;

            var href = target.getAttribute( 'href' );
            if ( ! href || href === '#' ) return;

            // Check if link points to an image
            if ( ! href.match( /\.(jpg|jpeg|png|gif|webp|svg)(\?.*)?$/i ) && ! target.hasAttribute( 'data-loopkit-lightbox' ) ) {
                return;
            }

            e.preventDefault();

            var backdrop = document.querySelector( '.lk-lightbox-backdrop' );
            if ( ! backdrop ) {
                backdrop = document.createElement( 'div' );
                backdrop.className = 'lk-lightbox-backdrop';
                backdrop.innerHTML = '<div class="lk-lightbox-container">' +
                    '<button type="button" class="lk-lightbox-close" aria-label="Close">&times;</button>' +
                    '<img class="lk-lightbox-image" src="" alt="" />' +
                    '</div>';
                document.body.appendChild( backdrop );

                backdrop.addEventListener( 'click', function( evt ) {
                    if ( evt.target === backdrop || evt.target.classList.contains( 'lk-lightbox-close' ) ) {
                        closeLightbox();
                    }
                });

                document.addEventListener( 'keydown', function( evt ) {
                    if ( evt.key === 'Escape' && backdrop.classList.contains( 'lk-lightbox-open' ) ) {
                        closeLightbox();
                    }
                });
            }

            var img = backdrop.querySelector( '.lk-lightbox-image' );
            img.src = href;
            backdrop.classList.add( 'lk-lightbox-open' );
            document.body.style.overflow = 'hidden';

            function closeLightbox() {
                backdrop.classList.remove( 'lk-lightbox-open' );
                document.body.style.overflow = '';
                setTimeout( function() {
                    if ( ! backdrop.classList.contains( 'lk-lightbox-open' ) ) {
                        img.src = '';
                    }
                }, 300 );
            }
        });
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initLightbox );
    } else {
        initLightbox();
    }
})();
