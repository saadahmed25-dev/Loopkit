/**
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
