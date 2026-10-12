/**
 * Loopkit Block Editor Button
 * Injects an "Edit with Loopkit" button into the Gutenberg editor header toolbar.
 * Uses MutationObserver to survive React re-renders and prevents duplicates.
 *
 * @package Loopkit
 */

(function () {
    'use strict';

    var config  = window.loopkitEditorButton || {};
    var WRAP_ID = 'lk-editor-buttons-wrap';

    /**
     * Build the wrapper containing the Loopkit button(s).
     */
    function createWrapper() {
        var wrapper = document.createElement( 'div' );
        wrapper.id  = WRAP_ID;

        // --- Primary button: "Edit with Loopkit" ---
        var editBtn       = document.createElement( 'button' );
        editBtn.type      = 'button';
        editBtn.className = 'lk-editor-btn';
        editBtn.innerHTML = '<span class="dashicons dashicons-edit-large"></span> ' +
                            escapeHtml( config.buttonText || 'Edit with Loopkit' );
        editBtn.addEventListener( 'click', handleEditClick );
        wrapper.appendChild( editBtn );

        // --- Secondary button: "Back to WordPress Editor" (only in Loopkit mode) ---
        if ( config.isLoopkitMode ) {
            var backBtn       = document.createElement( 'button' );
            backBtn.type      = 'button';
            backBtn.className = 'lk-editor-btn lk-editor-btn-back';
            backBtn.textContent = config.backText || 'Back to WordPress Editor';
            backBtn.addEventListener( 'click', function () {
                window.location.href = config.backUrl;
            });
            wrapper.appendChild( backBtn );
        }

        return wrapper;
    }

    /**
     * Handle "Edit with Loopkit" click.
     * If the post has unsaved changes or is a new draft, save first then redirect.
     */
    function handleEditClick( e ) {
        e.preventDefault();

        var select  = wp.data.select( 'core/editor' );
        var isDirty = select.isEditedPostDirty();
        var isNew   = select.isCleanNewPost();

        if ( isDirty || isNew ) {
            wp.data.dispatch( 'core/editor' ).savePost().then( function () {
                window.location.href = config.builderUrl;
            });
        } else {
            window.location.href = config.builderUrl;
        }
    }

    /**
     * Try to inject the button wrapper into the editor header toolbar.
     * Returns true on success.
     */
    function injectButton() {
        // Never duplicate
        if ( document.getElementById( WRAP_ID ) ) {
            return true;
        }

        // WordPress 7.x / 6.6+
        var toolbar = document.querySelector( '.editor-header__toolbar' );

        // WordPress 6.x fallback
        if ( ! toolbar ) {
            toolbar = document.querySelector( '.edit-post-header-toolbar' );
        }

        // WordPress 6.5+ alternative
        if ( ! toolbar ) {
            toolbar = document.querySelector( '.editor-header__start' );
        }

        if ( ! toolbar ) {
            return false;
        }

        toolbar.appendChild( createWrapper() );
        return true;
    }

    /**
     * Start a MutationObserver that re-injects the button if React
     * re-renders the header and removes it.
     */
    function observe() {
        var target = document.querySelector( '.editor-header' ) ||
                     document.querySelector( '.edit-post-header' ) ||
                     document.getElementById( 'editor' ) ||
                     document.body;

        var observer = new MutationObserver( function () {
            if ( ! document.getElementById( WRAP_ID ) ) {
                injectButton();
            }
        });

        observer.observe( target, { childList: true, subtree: true } );
    }

    /**
     * Minimal HTML-escape helper.
     */
    function escapeHtml( str ) {
        var div = document.createElement( 'div' );
        div.appendChild( document.createTextNode( str ) );
        return div.innerHTML;
    }

    /**
     * Bootstrap: wait for DOM, then inject + observe.
     */
    function boot() {
        if ( injectButton() ) {
            observe();
        } else {
            // Toolbar not in DOM yet — poll briefly then observe
            var attempts = 0;
            var interval = setInterval( function () {
                attempts++;
                if ( injectButton() ) {
                    clearInterval( interval );
                    observe();
                }
                if ( attempts > 40 ) {          // give up after ~8 s
                    clearInterval( interval );
                }
            }, 200 );
        }
    }

    // Use wp.domReady, with a short delay so React has rendered the header
    if ( wp && wp.domReady ) {
        wp.domReady( function () {
            setTimeout( boot, 300 );
        });
    } else {
        document.addEventListener( 'DOMContentLoaded', function () {
            setTimeout( boot, 800 );
        });
    }

})();
