/**
 * Loopkit Editor JavaScript
 * Main editor logic: top bar, panel switching, selection, controls live sync, canvas, history, saving.
 *
 * @package Loopkit
 */

(function( $ ) {
    'use strict';

    var LK = window.loopkitEditor || {};

    /* =========================================================
       STATE
       ========================================================= */
    var state = {
        elements:               ensureSections( LK.elements || [] ),
        dirty:                  false,
        saving:                 false,
        device:                 'desktop',
        panelCollapsed:         false,
        selectedId:             null,
        activeTab:              'layout',
        canvasReady:            false,
        history:                [],
        historyIndex:           -1,
        historyDebounce:        null,
        activePanel:            'elements',
        activeHistoryTab:       'actions',
        structureOpen:          false,
        structureDocked:        false,
        treeExpanded:           {},
        hiddenElementIds:       {},
        revisions:              [],
        previewingRevisionId:   null,
        preRevisionElements:    null,
        preRevisionSettings:    null,
        activePreviewRev:       null,
        pageSettings:           ( LK && LK.pageSettings ) || {},
        conditions:             ( LK && LK.conditions ) || [],
        globals:                ( LK && LK.globals ) || {},
        templates:              [],
        activeTemplateFilter:   'all',
        modalTemplateFilter:    'all',
        selectedTemplateIdsForExport: [],
        activeElementsTab:      'elements',
        savingGlobalElementId:  null,
        siteSettings:           JSON.parse( JSON.stringify( ( LK && LK.siteSettings ) || {} ) ),
        siteIdentity:           JSON.parse( JSON.stringify( ( LK && LK.siteIdentity ) || {} ) ),
        userPreferences:        JSON.parse( JSON.stringify( ( LK && LK.userPreferences ) || {} ) ),
        siteSettingsDirty:      false,
        activeSiteSubscreen:    null,
    };

    /* =========================================================
       SECTION & ROOT AUTO-CONVERSION
       "Sections are only allowed at the page root.
        On editor load, every root-level container is converted
        to a Section, keeping its id and ALL its settings."
       ========================================================= */
    function ensureSections( elements ) {
        if ( ! Array.isArray( elements ) ) return [];
        var siteSettings = ( LK && LK.siteSettings ) || {};
        var contentWidth = parseInt( siteSettings.container_width, 10 ) || 1140;

        var wrapped = [];
        for ( var i = 0; i < elements.length; i++ ) {
            var el = elements[i];
            if ( ! el ) continue;

            if ( el.elType === 'widget' ) {
                var newSec = createSectionElement();
                newSec.elements = [ el ];
                wrapped.push( newSec );
            } else if ( el.elType === 'container' ) {
                // Convert root container to Section preserving id and all settings
                var converted = JSON.parse( JSON.stringify( el ) );
                converted.elType = 'section';
                converted.settings = converted.settings || {};
                if ( ! converted.settings.html_tag || converted.settings.html_tag === 'div' ) {
                    converted.settings.html_tag = 'section';
                }
                wrapped.push( converted );
            } else if ( el.elType === 'global' ) {
                // Global section stays at root; global container at root wrapped in section
                if ( el.globalType === 'container' ) {
                    var newSec = createSectionElement();
                    newSec.elements = [ el ];
                    wrapped.push( newSec );
                } else {
                    wrapped.push( el );
                }
            } else {
                wrapped.push( el );
            }
        }
        return wrapped;
    }

    /* =========================================================
       GLOBAL ELEMENTS HELPERS
       ========================================================= */
    function getGlobalData( templateId ) {
        if ( ! state.globals ) return null;
        var tid = parseInt( templateId, 10 );
        if ( state.globals[ tid ] ) return state.globals[ tid ];
        if ( state.globals[ String( tid ) ] ) return state.globals[ String( tid ) ];
        if ( Array.isArray( state.globals ) ) {
            for ( var i = 0; i < state.globals.length; i++ ) {
                if ( state.globals[i] && parseInt( state.globals[i].id, 10 ) === tid ) {
                    return state.globals[i];
                }
            }
        } else if ( typeof state.globals === 'object' ) {
            for ( var k in state.globals ) {
                if ( state.globals[k] && parseInt( state.globals[k].id, 10 ) === tid ) {
                    return state.globals[k];
                }
            }
        }
        return null;
    }

    function hasCircularGlobal( elements, targetTemplateId ) {
        if ( ! Array.isArray( elements ) ) return false;
        for ( var i = 0; i < elements.length; i++ ) {
            var el = elements[i];
            if ( ! el ) continue;
            if ( el.elType === 'global' && String( el.templateId ) === String( targetTemplateId ) ) {
                return true;
            }
            if ( el.elements && hasCircularGlobal( el.elements, targetTemplateId ) ) {
                return true;
            }
        }
        return false;
    }

    function collectGlobalIds( elements ) {
        var ids = [];
        function scan( list ) {
            if ( ! Array.isArray( list ) ) return;
            for ( var i = 0; i < list.length; i++ ) {
                var el = list[i];
                if ( ! el ) continue;
                if ( el.elType === 'global' && el.templateId ) {
                    var tid = parseInt( el.templateId, 10 );
                    if ( ids.indexOf( tid ) === -1 ) {
                        ids.push( tid );
                    }
                }
                if ( el.elements ) scan( el.elements );
            }
        }
        scan( elements );
        return ids;
    }

    function resolveGlobals( elements ) {
        if ( ! Array.isArray( elements ) ) return [];
        return elements.map( function( el ) {
            if ( ! el ) return el;
            if ( el.elType === 'global' ) {
                var gClone = JSON.parse( JSON.stringify( el ) );
                var gData = getGlobalData( el.templateId );
                if ( gData && gData.master ) {
                    gClone.master = JSON.parse( JSON.stringify( gData.master ) );
                    gClone.globalName = gData.title || 'Global';
                } else {
                    gClone.master = null;
                    gClone.globalName = 'Global (Missing)';
                }
                return gClone;
            }
            if ( el.elements && el.elements.length ) {
                var clone = JSON.parse( JSON.stringify( el ) );
                clone.elements = resolveGlobals( el.elements );
                return clone;
            }
            return el;
        });
    }

    function replaceElementById( list, id, newElement ) {
        for ( var i = 0; i < list.length; i++ ) {
            if ( list[i].id === id ) {
                list[i] = newElement;
                return true;
            }
            if ( list[i].elements && list[i].elements.length ) {
                if ( replaceElementById( list[i].elements, id, newElement ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    /* =========================================================
       HELPERS & TOAST
       ========================================================= */
    function generateId() {
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var id = '';
        for ( var i = 0; i < 7; i++ ) {
            id += chars.charAt( Math.floor( Math.random() * chars.length ) );
        }
        return id;
    }

    function markDirty() {
        if ( ! state.dirty ) {
            state.dirty = true;
            $( '#lk-publish-btn' ).prop( 'disabled', false );
        }
    }

    function markClean() {
        state.dirty = false;
        $( '#lk-publish-btn' ).prop( 'disabled', true );
    }

    function showToast( message, type ) {
        type = type || 'success';
        var $toast = $( '#lk-toast' );
        if ( ! $toast.length ) {
            $toast = $( '<div id="lk-toast" class="lk-toast"></div>' ).appendTo( 'body' );
        }
        $toast.attr( 'class', 'lk-toast lk-toast-' + type ).text( message );
        $toast[0].offsetHeight; // eslint-disable-line no-unused-expressions
        $toast.addClass( 'lk-toast-visible' );
        clearTimeout( $toast.data( 'timer' ) );
        $toast.data( 'timer', setTimeout( function() {
            $toast.removeClass( 'lk-toast-visible' );
        }, 2500 ) );
    }

    function escapeHtml( str ) {
        if ( typeof str !== 'string' ) {
            if ( str === null || str === undefined ) return '';
            str = String( str );
        }
        var div = document.createElement( 'div' );
        div.appendChild( document.createTextNode( str ) );
        return div.innerHTML;
    }

    function selectElement( id ) {
        if ( ! id ) {
            showElementsPanel();
            return;
        }
        state.selectedId = id;
        state.activeTab = state.activeTab || 'layout';
        renderPanelForSelected();
        sendToCanvas( 'select-element', { elementId: id } );
        highlightInStructureTree( id );
    }

    function renderPageSettings() {
        showSettingsPanel();
    }

    var WIDGET_TITLES = {
        'heading':      'Heading',
        'text-editor':  'Text Editor',
        'image':        'Image',
        'button':       'Button',
        'divider':      'Divider',
        'spacer':       'Spacer',
        'icon':         'Icon',
        'video':        'Video',
        'image-box':    'Image Box',
        'icon-box':     'Icon Box',
        'icon-list':    'Icon List',
        'social-icons': 'Social Icons',
        'html':         'HTML',
        'shortcode':    'Shortcode',
        'site-logo':    'Site Logo',
        'site-title':   'Site Title',
        'nav-menu':     'Nav Menu',
        'search-form':  'Search Form',
        'post-title':          'Post Title',
        'post-featured-image': 'Featured Image',
        'post-excerpt':        'Post Excerpt',
        'post-info':           'Post Info',
        'post-content':        'Post Content',
        'author-box':          'Author Box',
        'post-navigation':     'Post Navigation',
        'post-comments':       'Comments',
        'archive-title':       'Archive Title',
        'archive-description': 'Archive Description',
        'posts':               'Posts',
        'loop-grid':           'Loop Grid',
        'marquee':             'Marquee',
        'product-grid': 'Product Grid',
        'add-to-cart': 'Add to Cart',
        'product-price': 'Product Price',
        'product-gallery': 'Product Gallery',
        'cart-icon': 'Cart Icon',
        'cart-table': 'Cart',
        'checkout-form': 'Checkout',
        'product-categories': 'Product Categories',
        'flip-box':            'Flip Box',
        'price-table':         'Price Table',
        'countdown':           'Countdown',
        'animated-headline':   'Animated Headline',
        'call-to-action':      'Call to Action',
        'share-buttons':       'Share Buttons',
        'pagination':          'Pagination',
        'accordion': 'Accordion',
        'toggle': 'Toggle',
        'tabs': 'Tabs',
        'counter': 'Counter',
        'progress-bar': 'Progress Bar',
        'alert': 'Alert',
        'star-rating': 'Star Rating',
        'testimonial': 'Testimonial',
        'image-carousel': 'Image Carousel',
        'image-gallery': 'Image Gallery',
        'google-maps': 'Google Maps',
        'contact-form': 'Contact Form',
        'tooltip': 'Tooltip',
        'table-of-contents': 'Table of Contents'
    };

    function formatWidgetTitle( widgetType ) {
        if ( ! widgetType ) return 'Widget';
        if ( WIDGET_TITLES[ widgetType ] ) return WIDGET_TITLES[ widgetType ];
        if ( LK && LK.widgets && LK.widgets[ widgetType ] && LK.widgets[ widgetType ].title ) {
            return LK.widgets[ widgetType ].title;
        }
        if ( widgetType.toLowerCase() === 'html' ) return 'HTML';
        return widgetType.replace( /[-_]+/g, ' ' ).replace( /\b[a-z]/g, function( l ) {
            return l.toUpperCase();
        });
    }

    /* =========================================================
       HISTORY (Actions Stack & Revisions)
       ========================================================= */
    function getElementTitle( el ) {
        if ( ! el ) return 'Element';
        if ( el.settings && el.settings._title ) return el.settings._title;
        if ( el.elType === 'section' ) return 'Section';
        if ( el.elType === 'container' ) return 'Container';
        if ( el.elType === 'global' ) {
            var g = getGlobalData( el.templateId );
            return ( g && g.title ) ? g.title : 'Global';
        }
        if ( el.elType === 'widget' ) {
            return formatWidgetTitle( el.widgetType );
        }
        return 'Element';
    }

    function formatControlLabel( name ) {
        if ( ! name ) return '';
        var map = {
            'title':            'Title',
            'padding':          'Padding',
            'margin':           'Margin',
            'background_color': 'Background Colour',
            'background_image': 'Background Image',
            'width':            'Width',
            'min_height':       'Min Height',
            'flex_direction':   'Direction',
            'justify_content':  'Justify Content',
            'align_items':      'Align Items',
            'flex_gap':         'Gaps',
            'flex_wrap':        'Wrap',
            'html_tag':         'HTML Tag',
            'align':            'Alignment',
            'color':            'Text Colour',
            'typography':       'Typography',
            'css_id':           'CSS ID',
            'css_classes':      'CSS Classes',
            'page_layout':      'Page Layout',
            'post_status':      'Status',
        };
        if ( map[ name ] ) return map[ name ];
        return name.replace( /_/g, ' ' ).replace( /\b\w/g, function( l ) { return l.toUpperCase(); } );
    }

    /**
     * Announce a change on the document, for anything listening.
     *
     * The mascot is the first listener; before this it had to infer what
     * happened from drag and click events, which is a guess.
     *
     * @param {string} action What happened: Added, Removed, Duplicated...
     * @param {string} title  What it happened to.
     */
    function announceChange( action, title ) {
        var map = {
            'Added':             'loopkit:added',
            'Duplicated':        'loopkit:added',
            'Pasted':            'loopkit:added',
            'Template Inserted': 'loopkit:added',
            'Removed':           'loopkit:deleted',
            'Moved':             'loopkit:moved',
        };
        var name = map[ action ];
        if ( ! name ) {
            return; // style changes and the like are not worth announcing
        }
        try {
            document.dispatchEvent( new CustomEvent( name, {
                detail: { action: action, title: title || '' }
            } ) );
        } catch ( e ) {
            // A browser without CustomEvent is a browser this editor does
            // not run in anyway; never let this break the edit itself.
        }
    }

    function pushHistory( actionMeta ) {
        // Truncate any redo / future history
        if ( state.historyIndex < state.history.length - 1 ) {
            state.history = state.history.slice( 0, state.historyIndex + 1 );
        }

        var entry = {
            data:         JSON.stringify( state.elements ),
            pageSettings: JSON.stringify( collectPageSettingsForSave() ),
            title:        ( actionMeta && actionMeta.title ) ? actionMeta.title : 'Page',
            action:       ( actionMeta && actionMeta.action ) ? actionMeta.action : 'Edited',
            sub:          ( actionMeta && actionMeta.sub ) ? actionMeta.sub : '',
            time:         new Date(),
        };

        // Tell the rest of the page what just happened. Emitted here rather
        // than at each of the nineteen call sites, because this is the one
        // place every real change already passes through — and a change that
        // skipped history would be a bug in its own right.
        announceChange( entry.action, entry.title );

        state.history.push( entry );
        if ( state.history.length > 100 ) {
            state.history.shift();
        } else {
            state.historyIndex++;
        }

        renderHistoryActionsUI();
        if ( typeof renderStructureTree === 'function' ) {
            renderStructureTree();
        }
    }

    function restoreHistoryIndex( index ) {
        if ( index < 0 || index >= state.history.length ) return;
        state.historyIndex = index;
        var step = state.history[ index ];
        if ( ! step ) return;

        var dataStr = typeof step === 'string' ? step : step.data;
        if ( dataStr ) {
            state.elements = JSON.parse( dataStr );
        }
        if ( step.pageSettings ) {
            state.pageSettings = JSON.parse( step.pageSettings );
        }

        markDirty();
        sendToCanvas( 'update-elements', { elements: state.elements, selectedId: state.selectedId } );
        sendDynamicCssToCanvas();

        if ( state.selectedId ) {
            renderPanelForSelected();
        }

        renderHistoryActionsUI();
        if ( typeof renderStructureTree === 'function' ) {
            renderStructureTree();
        }
    }

    function undo() {
        if ( state.historyIndex > 0 ) {
            restoreHistoryIndex( state.historyIndex - 1 );
            showToast( 'Undo', 'success' );
        }
    }

    function redo() {
        if ( state.historyIndex < state.history.length - 1 ) {
            restoreHistoryIndex( state.historyIndex + 1 );
            showToast( 'Redo', 'success' );
        }
    }

    function renderHistoryActionsUI() {
        var $list = $( '#lk-history-actions-list' );
        var $empty = $( '#lk-history-actions-empty' );
        if ( ! $list.length ) return;

        $list.empty();
        if ( ! state.history || state.history.length <= 1 ) {
            $empty.show();
            return;
        }
        $empty.hide();

        // Newest at top, Editing Started at bottom
        for ( var i = state.history.length - 1; i >= 0; i-- ) {
            var step = state.history[ i ];
            var isCurrent = ( i === state.historyIndex );
            var isFuture  = ( i > state.historyIndex );

            var itemCls = 'lk-history-item';
            if ( isCurrent ) itemCls += ' lk-history-item--current';
            if ( isFuture )  itemCls += ' lk-history-item--future';

            var title = step.title || ( step.isInitial ? ( LK.strings.editingStarted || 'Editing Started' ) : 'Page' );
            var actionText = '';
            if ( step.action ) {
                actionText = step.action + ( step.sub ? ' · ' + step.sub : '' );
            }

            var $item = $(
                '<div class="' + itemCls + '" data-index="' + i + '">' +
                  '<div class="lk-history-item-main">' +
                    '<span class="lk-history-item-title">' + escapeHtml( title ) + '</span>' +
                    ( actionText ? '<span class="lk-history-item-action">' + escapeHtml( actionText ) + '</span>' : '' ) +
                  '</div>' +
                  ( isCurrent ? '<span class="lk-history-item-badge">Current</span>' : '' ) +
                '</div>'
            );

            (function( targetIdx ) {
                $item.on( 'click', function() {
                    restoreHistoryIndex( targetIdx );
                });
            })( i );

            $list.append( $item );
        }
    }

    function fetchRevisions() {
        var $list = $( '#lk-history-revisions-list' );
        var $empty = $( '#lk-history-revisions-empty' );
        $list.html( '<div style="padding:20px;text-align:center;color:#6B7280;">Loading revisions...</div>' );
        $empty.hide();

        var revisionsUrl = ( LK.restBase ? ( LK.restBase + 'document/' + LK.postId + '/revisions' ) : ( ( LK.restUrl || '/wp-json/' ) + 'loopkit/v1/document/' + LK.postId + '/revisions' ) );

        $.ajax({
            url: revisionsUrl,
            method: 'GET',
            headers: {
                'X-WP-Nonce': LK.restNonce || ''
            },
            beforeSend: function( xhr ) {
                if ( LK.restNonce ) {
                    xhr.setRequestHeader( 'X-WP-Nonce', LK.restNonce );
                }
            },
            success: function( res ) {
                state.revisions = ( res && res.revisions ) || [];
                renderRevisionsUI();
            },
            error: function( jqXHR ) {
                var errorMsg = 'Failed to load revisions.';
                if ( jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message ) {
                    errorMsg = jqXHR.responseJSON.message;
                } else if ( jqXHR && jqXHR.statusText && jqXHR.statusText !== 'error' ) {
                    errorMsg = 'Error ' + jqXHR.status + ': ' + jqXHR.statusText;
                }
                $list.html( '<div style="padding:20px;text-align:center;color:#EF4444;">' + escapeHtml( errorMsg ) + '</div>' );
            }
        });
    }

    function renderRevisionsUI() {
        var $list = $( '#lk-history-revisions-list' );
        var $empty = $( '#lk-history-revisions-empty' );
        $list.empty();

        if ( ! state.revisions || state.revisions.length === 0 ) {
            $empty.show();
            return;
        }
        $empty.hide();

        state.revisions.forEach( function( rev ) {
            var isSelected = ( state.previewingRevisionId === rev.id );
            var cardCls = 'lk-revision-card' + ( isSelected ? ' lk-revision-card--selected' : '' );
            var badgeCls = 'lk-revision-badge' + ( rev.type === 'Autosave' ? ' lk-revision-badge--autosave' : '' );

            var $card = $(
                '<div class="' + cardCls + '" data-rev-id="' + rev.id + '">' +
                  '<img class="lk-revision-avatar" src="' + escapeHtml( rev.avatar_url || '' ) + '" alt="" />' +
                  '<div class="lk-revision-info">' +
                    '<div class="lk-revision-author">' + escapeHtml( rev.author_name ) + '</div>' +
                    '<div class="lk-revision-meta">' + escapeHtml( rev.time_ago ) + ' (' + escapeHtml( rev.date_str ) + ')</div>' +
                  '</div>' +
                  '<span class="' + badgeCls + '">' + escapeHtml( rev.type ) + '</span>' +
                '</div>'
            );

            $card.on( 'click', function() {
                previewRevision( rev );
            });

            $list.append( $card );
        });
    }

    function previewRevision( rev ) {
        if ( ! state.previewingRevisionId ) {
            state.preRevisionElements = JSON.parse( JSON.stringify( state.elements ) );
            state.preRevisionSettings = JSON.parse( JSON.stringify( state.pageSettings || {} ) );
        }
        state.previewingRevisionId = rev.id;
        state.activePreviewRev = rev;

        $( '.lk-revision-card' ).removeClass( 'lk-revision-card--selected' );
        $( '.lk-revision-card[data-rev-id="' + rev.id + '"]' ).addClass( 'lk-revision-card--selected' );

        $( '#lk-revision-preview-label' ).text( 'Previewing revision (' + rev.date_str + ')' );
        $( '#lk-revision-preview-bar' ).show();

        sendToCanvas( 'preview-revision', { elements: rev.elements || [] } );
    }

    function discardRevisionPreview() {
        if ( state.preRevisionElements ) {
            state.elements = JSON.parse( JSON.stringify( state.preRevisionElements ) );
        }
        if ( state.preRevisionSettings ) {
            state.pageSettings = JSON.parse( JSON.stringify( state.preRevisionSettings ) );
        }
        state.previewingRevisionId = null;
        state.activePreviewRev = null;

        $( '.lk-revision-card' ).removeClass( 'lk-revision-card--selected' );
        $( '#lk-revision-preview-bar' ).hide();

        sendToCanvas( 'exit-revision-preview', { elements: state.elements } );
        sendDynamicCssToCanvas();
    }

    function applyRevisionPreview() {
        if ( ! state.activePreviewRev ) return;
        var rev = state.activePreviewRev;

        state.elements = ensureSections( rev.elements || [] );
        if ( rev.settings ) {
            state.pageSettings = rev.settings;
        }
        state.previewingRevisionId = null;
        state.activePreviewRev = null;
        state.preRevisionElements = null;
        state.preRevisionSettings = null;

        $( '.lk-revision-card' ).removeClass( 'lk-revision-card--selected' );
        $( '#lk-revision-preview-bar' ).hide();

        markDirty();
        pushHistory( { title: 'Revision', action: 'Applied', sub: rev.date_str } );

        sendToCanvas( 'exit-revision-preview', { elements: state.elements } );
        sendDynamicCssToCanvas();
        showToast( 'Revision loaded as unsaved changes', 'success' );
    }

    function showHistoryPanel() {
        closeAllDropdowns();
        state.activePanel = 'history';

        $( '#lk-history-toggle' ).addClass( 'lk-topbar-active' );

        $( '#lk-panel-back-to-elements' ).show();
        $( '#lk-panel-header-icon' ).attr( 'class', 'dashicons dashicons-backup' );
        $( '#lk-panel-header-text' ).text( LK.strings.history || 'History' );

        $( '#lk-panel-tabs' ).hide();
        $( '#lk-panel-elements' ).hide();
        $( '#lk-panel-controls' ).hide();
        $( '#lk-panel-site-settings' ).hide();
        $( '#lk-panel-user-prefs' ).hide();
        $( '#lk-panel-history' ).show();

        renderHistoryActionsUI();
        if ( state.activeHistoryTab === 'revisions' ) {
            fetchRevisions();
        }
    }

    function toggleHistoryPanel() {
        if ( state.activePanel === 'history' ) {
            showElementsPanel();
        } else {
            showHistoryPanel();
        }
    }

    function initHistoryUI() {
        $( document ).on( 'click', '.lk-history-tab-bar .lk-elements-tab-btn', function() {
            var tab = $( this ).data( 'history-tab' );
            state.activeHistoryTab = tab;
            $( '.lk-history-tab-bar .lk-elements-tab-btn' ).removeClass( 'lk-tab-active' );
            $( this ).addClass( 'lk-tab-active' );

            if ( tab === 'actions' ) {
                $( '#lk-pane-history-revisions' ).hide();
                $( '#lk-pane-history-actions' ).show();
                renderHistoryActionsUI();
            } else {
                $( '#lk-pane-history-actions' ).hide();
                $( '#lk-pane-history-revisions' ).show();
                fetchRevisions();
            }
        });

        $( '#lk-revision-discard-btn' ).on( 'click', function() {
            discardRevisionPreview();
        });

        $( '#lk-revision-apply-btn' ).on( 'click', function() {
            applyRevisionPreview();
        });
    }

    /* =========================================================
       DATA STRUCTURE BUILDERS (Section & Container & Heading)
       ========================================================= */

    /**
     * Create Section element (outer layer, root only)
     * Defaults: Content Width Boxed, Width = Site Settings Content Width (1140px),
     * Direction Column, Gap 20px, Padding 10px, HTML Tag section.
     */
    function createSectionElement() {
        var siteSettings = ( LK && LK.siteSettings ) || {};
        var contentWidth = parseInt( siteSettings.container_width, 10 ) || 1140;

        return {
            id: generateId(),
            elType: 'section',
            isInner: false,
            settings: {
                container_type: 'flexbox',
                content_width:  'boxed',
                width:          { size: contentWidth, unit: 'px' },
                min_height:     { size: '', unit: 'px' },
                flex_direction: 'column',
                justify_content: '',
                align_items:    '',
                flex_gap:       { column: '20', row: '20', unit: 'px', isLinked: true },
                flex_wrap:      'nowrap',
                html_tag:       'section',
                padding:        { top: '10', right: '10', bottom: '10', left: '10', unit: 'px', isLinked: true },
                margin:         { top: '', right: '', bottom: '', left: '', unit: 'px', isLinked: true },
                css_classes:    '',
                css_id:         '',
            },
            elements: [],
        };
    }

    /**
     * Create Container element (inner layout block)
     * Defaults: Content Width Full Width (100% of its parent),
     * Direction Column, Gap 20px, Padding 10px, HTML Tag div.
     */
    function createContainerElement( widthPercent ) {
        var widthSize = ( widthPercent !== undefined ) ? widthPercent : 100;

        return {
            id: generateId(),
            elType: 'container',
            isInner: false,
            settings: {
                container_type: 'flexbox',
                content_width:  'full',
                width:          { size: widthSize, unit: '%' },
                min_height:     { size: '', unit: 'px' },
                flex_direction: 'column',
                justify_content: '',
                align_items:    '',
                flex_gap:       { column: '20', row: '20', unit: 'px', isLinked: true },
                flex_wrap:      'nowrap',
                html_tag:       'div',
                padding:        { top: '10', right: '10', bottom: '10', left: '10', unit: 'px', isLinked: true },
                margin:         { top: '', right: '', bottom: '', left: '', unit: 'px', isLinked: true },
                css_classes:    '',
                css_id:         '',
            },
            elements: [],
        };
    }

    /**
     * Create Heading widget
     */
    function createHeadingElement() {
        return {
            id: generateId(),
            elType: 'widget',
            widgetType: 'heading',
            isInner: false,
            settings: {
                title: 'Add Your Heading Text Here',
                tag: 'h2',
                align: 'left',
                title_color: '',
                typography: {},
                text_shadow: { horizontal: 0, vertical: 0, blur: 0, color: '' },
                blend_mode: '',
                margin: { top: '', right: '', bottom: '', left: '', unit: 'px', isLinked: true },
                padding: { top: '', right: '', bottom: '', left: '', unit: 'px', isLinked: true },
                css_classes: '',
                css_id: '',
            },
            elements: [],
        };
    }

    /**
     * Create Widget element with default settings
     */
    /**
     * Is this a widget Loopkit actually has?
     *
     * Nothing used to check. A drag that carried something else — an image
     * dragged in from the media library, a file from the desktop — produced
     * an element whose type was a URL, which no renderer can draw. It showed
     * up on the canvas as "No canvas renderer registered for widget type
     * http://…", and it was saved into the page like that.
     *
     * @param {string} type Widget name.
     * @return {boolean}
     */
    function isKnownWidget( type ) {
        if ( typeof type !== 'string' || ! type ) {
            return false;
        }
        if ( type === 'section' || type === 'container' ) {
            return true;
        }
        // A real widget name is a slug. A URL or a file path never is.
        if ( ! /^[a-z0-9][a-z0-9-]*$/.test( type ) ) {
            return false;
        }
        return !! ( LK.widgets && LK.widgets[ type ] );
    }

    function createWidgetElement( widgetType ) {
        if ( widgetType === 'heading' ) {
            return createHeadingElement();
        }
        var wDef = ( LK.widgets && LK.widgets[ widgetType ] ) || null;
        var defaultSettings = {};
        if ( wDef && wDef.default_settings ) {
            try {
                defaultSettings = JSON.parse( JSON.stringify( wDef.default_settings ) );
            } catch ( e ) {
                defaultSettings = {};
            }
        }
        return {
            id: generateId(),
            elType: 'widget',
            widgetType: widgetType,
            isInner: false,
            settings: defaultSettings,
            elements: [],
        };
    }

    /**
     * Add a Widget inside selected Section/Container,
     * or wrap in a new Section at the end of the page if nothing is selected.
     */
    function addWidget( widgetType, targetId ) {
        if ( widgetType === 'heading' ) {
            addHeading( targetId );
            return;
        }
        var widget = createWidgetElement( widgetType );
        var targetEl = targetId ? findElementById( state.elements, targetId ) : null;
        if ( ! targetEl && state.selectedId ) {
            targetEl = findElementById( state.elements, state.selectedId );
        }

        if ( targetEl ) {
            if ( targetEl.elType === 'section' || targetEl.elType === 'container' ) {
                targetEl.elements = targetEl.elements || [];
                targetEl.elements.push( widget );
            } else if ( targetEl.elType === 'widget' ) {
                var parentInfo = findParentOf( state.elements, targetEl.id );
                if ( parentInfo && parentInfo.list ) {
                    parentInfo.list.splice( parentInfo.index + 1, 0, widget );
                } else {
                    targetEl.elements = targetEl.elements || [];
                    targetEl.elements.push( widget );
                }
            }
        } else {
            // Nothing selected: wrap in a new Section at end of page
            var section = createSectionElement();
            section.elements.push( widget );
            state.elements.push( section );
        }

        state.selectedId = widget.id;
        markDirty();
        var wDef = ( LK.widgets && LK.widgets[ widgetType ] ) || null;
        var wTitle = ( wDef && wDef.title ) ? wDef.title : widgetType;
        pushHistory( { title: wTitle, action: 'Added' } );
        sendToCanvas( 'update-elements', { elements: state.elements, selectedId: widget.id } );
        sendDynamicCssToCanvas();
        renderPanelForSelected();
        showToast( wTitle + ' added', 'success' );
    }

    /* =========================================================
       TREE SEARCH & MANIPULATION HELPERS
       ========================================================= */
    function findElementById( list, id ) {
        if ( ! list || ! list.length ) return null;
        for ( var i = 0; i < list.length; i++ ) {
            if ( list[i].id === id ) return list[i];
            if ( list[i].elements && list[i].elements.length ) {
                var found = findElementById( list[i].elements, id );
                if ( found ) return found;
            }
        }
        return null;
    }

    function findParentOf( list, targetId, parent ) {
        if ( ! list || ! list.length ) return null;
        for ( var i = 0; i < list.length; i++ ) {
            if ( list[i].id === targetId ) {
                return { parent: parent, index: i, list: list };
            }
            if ( list[i].elements && list[i].elements.length ) {
                var found = findParentOf( list[i].elements, targetId, list[i] );
                if ( found ) return found;
            }
        }
        return null;
    }

    function findRootSectionOf( list, targetId ) {
        if ( ! list || ! list.length ) return null;
        for ( var i = 0; i < list.length; i++ ) {
            if ( list[i].id === targetId ) return list[i];
            if ( list[i].elements && list[i].elements.length ) {
                if ( findElementById( list[i].elements, targetId ) ) {
                    return list[i];
                }
            }
        }
        return null;
    }

    function isDescendant( ancestorEl, targetId ) {
        if ( ! ancestorEl ) return false;
        if ( ancestorEl.id === targetId ) return true;
        if ( ancestorEl.elements && ancestorEl.elements.length ) {
            for ( var i = 0; i < ancestorEl.elements.length; i++ ) {
                if ( isDescendant( ancestorEl.elements[i], targetId ) ) return true;
            }
        }
        return false;
    }

    function removeElementById( list, id ) {
        if ( ! list || ! list.length ) return null;
        for ( var i = 0; i < list.length; i++ ) {
            if ( list[i].id === id ) {
                return list.splice( i, 1 )[0];
            }
            if ( list[i].elements && list[i].elements.length ) {
                var removed = removeElementById( list[i].elements, id );
                if ( removed ) return removed;
            }
        }
        return null;
    }

    /* =========================================================
       ADD ELEMENT FUNCTIONS
       ========================================================= */

    /**
     * Add a new Section at the end of the page.
     */
    function addSection( beforeId ) {
        var section = createSectionElement();
        if ( beforeId ) {
            var idx = -1;
            for ( var i = 0; i < state.elements.length; i++ ) {
                if ( state.elements[i].id === beforeId ) {
                    idx = i;
                    break;
                }
            }
            if ( idx !== -1 ) {
                state.elements.splice( idx, 0, section );
            } else {
                state.elements.push( section );
            }
        } else {
            state.elements.push( section );
        }

        state.selectedId = section.id;
        markDirty();
        pushHistory( { title: 'Section', action: 'Added' } );
        sendToCanvas( 'update-elements', { elements: state.elements, selectedId: section.id } );
        sendDynamicCssToCanvas();
        renderPanelForSelected();
        showToast( 'Section added', 'success' );
    }

    /**
     * Add a Section from a structure preset (e.g. 50/50, 33/33/33).
     */
    function addPresetSection( preset ) {
        var section = createSectionElement();

        if ( preset === '50-50' ) {
            section.settings.flex_direction = 'row';
            section.elements = [
                createContainerElement( 50 ),
                createContainerElement( 50 )
            ];
        } else if ( preset === '33-33-33' ) {
            section.settings.flex_direction = 'row';
            section.elements = [
                createContainerElement( 33.33 ),
                createContainerElement( 33.33 ),
                createContainerElement( 33.33 )
            ];
        } else if ( preset === '25-25-25-25' ) {
            section.settings.flex_direction = 'row';
            section.elements = [
                createContainerElement( 25 ),
                createContainerElement( 25 ),
                createContainerElement( 25 ),
                createContainerElement( 25 )
            ];
        } else if ( preset === '33-66' ) {
            section.settings.flex_direction = 'row';
            section.elements = [
                createContainerElement( 33.33 ),
                createContainerElement( 66.66 )
            ];
        } else if ( preset === '66-33' ) {
            section.settings.flex_direction = 'row';
            section.elements = [
                createContainerElement( 66.66 ),
                createContainerElement( 33.33 )
            ];
        } else {
            // Default 100% column
            section.elements = [ createContainerElement( 100 ) ];
        }

        state.elements.push( section );
        state.selectedId = section.id;
        markDirty();
        pushHistory( { title: 'Section', action: 'Added', sub: preset } );
        sendToCanvas( 'update-elements', { elements: state.elements, selectedId: section.id } );
        sendDynamicCssToCanvas();
        renderPanelForSelected();
        showToast( 'Section added', 'success' );
    }

    /**
     * Add a Container inside selected Section/Container,
     * or wrap in a new Section at the end of the page if nothing is selected.
     */
    function addContainer( targetId ) {
        var container = createContainerElement();

        var targetEl = targetId ? findElementById( state.elements, targetId ) : null;
        if ( ! targetEl && state.selectedId ) {
            targetEl = findElementById( state.elements, state.selectedId );
        }

        if ( targetEl ) {
            if ( targetEl.elType === 'section' || targetEl.elType === 'container' ) {
                targetEl.elements = targetEl.elements || [];
                targetEl.elements.push( container );
            } else if ( targetEl.elType === 'widget' ) {
                var parentInfo = findParentOf( state.elements, targetEl.id );
                if ( parentInfo && parentInfo.list ) {
                    parentInfo.list.splice( parentInfo.index + 1, 0, container );
                } else {
                    targetEl.elements = targetEl.elements || [];
                    targetEl.elements.push( container );
                }
            }
        } else {
            // Nothing selected: wrap in a new Section at end of page
            var section = createSectionElement();
            section.elements.push( container );
            state.elements.push( section );
        }

        state.selectedId = container.id;
        markDirty();
        pushHistory( { title: 'Container', action: 'Added' } );
        sendToCanvas( 'update-elements', { elements: state.elements, selectedId: container.id } );
        sendDynamicCssToCanvas();
        renderPanelForSelected();
        showToast( 'Container added', 'success' );
    }

    /**
     * Add a Heading inside selected Section/Container,
     * or wrap in a new Section at the end of the page if nothing is selected.
     */
    function addHeading( targetId ) {
        var heading = createHeadingElement();

        var targetEl = targetId ? findElementById( state.elements, targetId ) : null;
        if ( ! targetEl && state.selectedId ) {
            targetEl = findElementById( state.elements, state.selectedId );
        }

        if ( targetEl ) {
            if ( targetEl.elType === 'section' || targetEl.elType === 'container' ) {
                targetEl.elements = targetEl.elements || [];
                targetEl.elements.push( heading );
            } else if ( targetEl.elType === 'widget' ) {
                var parentInfo = findParentOf( state.elements, targetEl.id );
                if ( parentInfo && parentInfo.list ) {
                    parentInfo.list.splice( parentInfo.index + 1, 0, heading );
                } else {
                    targetEl.elements = targetEl.elements || [];
                    targetEl.elements.push( heading );
                }
            }
        } else {
            // Nothing selected: wrap in a new Section at end of page
            var section = createSectionElement();
            section.elements.push( heading );
            state.elements.push( section );
        }

        state.selectedId = heading.id;
        markDirty();
        pushHistory( { title: 'Heading', action: 'Added' } );
        sendToCanvas( 'update-elements', { elements: state.elements, selectedId: heading.id } );
        sendDynamicCssToCanvas();
        renderPanelForSelected();
        showToast( 'Heading added', 'success' );
    }

    /**
     * Add a Global instance element.
     * Follows placement rules: Section at root, Container inside or wrapped.
     */
    function addGlobalInstance( templateId, targetId ) {
        var tid = parseInt( templateId, 10 );
        var gData = getGlobalData( tid );

        if ( ! gData ) {
            showToast( 'Global template not found', 'error' );
            return;
        }

        if ( LK.postId && String( LK.postId ) === String( tid ) ) {
            showToast( 'Cannot insert a global into itself', 'error' );
            return;
        }
        if ( gData.master && hasCircularGlobal( [ gData.master ], LK.postId ) ) {
            showToast( 'Circular global reference blocked', 'error' );
            return;
        }

        var gType = gData.type || 'section';
        var instance = {
            id: generateId(),
            elType: 'global',
            globalType: gType,
            templateId: tid,
            overrides: {},
            elements: []
        };

        if ( gType === 'section' ) {
            // Global section added at page root
            state.elements.push( instance );
        } else {
            // Global container
            var targetEl = targetId ? findElementById( state.elements, targetId ) : null;
            if ( ! targetEl && state.selectedId ) {
                targetEl = findElementById( state.elements, state.selectedId );
            }

            if ( targetEl && ( targetEl.elType === 'section' || targetEl.elType === 'container' ) ) {
                targetEl.elements = targetEl.elements || [];
                targetEl.elements.push( instance );
            } else {
                // Wrap in new Section at end of page
                var section = createSectionElement();
                section.elements.push( instance );
                state.elements.push( section );
            }
        }

        state.selectedId = instance.id;
        markDirty();
        pushHistory( { title: ( gData && gData.title ) || 'Global', action: 'Template Inserted' } );
        sendToCanvas( 'update-elements', { elements: state.elements, selectedId: instance.id } );
        sendDynamicCssToCanvas();
        renderPanelForSelected();
        showToast( 'Global ' + ( gType === 'section' ? 'Section' : 'Container' ) + ' added', 'success' );
    }

    /* =========================================================
       PART E2: UNLINKED TEMPLATE INSERTION
       ========================================================= */
    /**
     * Deep-clone template elements, recursively generating fresh 7-character IDs
     * for every element and fresh _id for repeater rows, while preserving settings._title.
     */
    function deepCloneTemplateElements( elements ) {
        if ( ! Array.isArray( elements ) ) return [];

        function cloneItem( el ) {
            if ( ! el || typeof el !== 'object' ) return el;
            var clone = JSON.parse( JSON.stringify( el ) );
            clone.id = generateId();

            // Refresh repeater item _ids if present in settings
            if ( clone.settings && typeof clone.settings === 'object' ) {
                for ( var k in clone.settings ) {
                    var val = clone.settings[ k ];
                    if ( Array.isArray( val ) ) {
                        for ( var r = 0; r < val.length; r++ ) {
                            if ( val[r] && typeof val[r] === 'object' && val[r]._id !== undefined ) {
                                val[r]._id = 'lk_item_' + Math.random().toString( 36 ).substr( 2, 8 );
                            }
                        }
                    }
                }
            }

            // Recurse children
            if ( Array.isArray( clone.elements ) ) {
                for ( var i = 0; i < clone.elements.length; i++ ) {
                    clone.elements[i] = cloneItem( clone.elements[i] );
                }
            }

            return clone;
        }

        var result = [];
        for ( var i = 0; i < elements.length; i++ ) {
            result.push( cloneItem( elements[i] ) );
        }
        return result;
    }

    /**
     * Insert an unlinked copy of a template into the page.
     * Follows placement rules: Section at root, Container inside parent or wrapped in new Section.
     */
    function insertTemplate( templateId, targetId, position ) {
        var tid = parseInt( templateId, 10 );
        var tpl = null;
        for ( var i = 0; i < state.templates.length; i++ ) {
            if ( parseInt( state.templates[i].id, 10 ) === tid ) {
                tpl = state.templates[i];
                break;
            }
        }

        if ( ! tpl ) {
            // Try fetching from REST if not cached
            fetchTemplatesFromRest( function() {
                var found = null;
                for ( var j = 0; j < state.templates.length; j++ ) {
                    if ( parseInt( state.templates[j].id, 10 ) === tid ) {
                        found = state.templates[j];
                        break;
                    }
                }
                if ( found ) {
                    performInsertTemplate( found, targetId, position );
                } else {
                    showToast( 'Template not found', 'error' );
                }
            });
            return;
        }

        performInsertTemplate( tpl, targetId, position );
    }

    // The starter library hands element data straight to this.
    window.LoopkitEditorInsert = function ( tpl, targetId, position ) {
        performInsertTemplate( tpl, targetId || state.selectedId, position );
    };

    function performInsertTemplate( tpl, targetId, position ) {
        var rawElements = tpl.elements || [];
        if ( ! Array.isArray( rawElements ) || ! rawElements.length ) {
            // Create a default element based on type if template was empty
            if ( tpl.type === 'section' ) {
                rawElements = [ createSectionElement() ];
            } else {
                rawElements = [ createContainerElement() ];
            }
        }

        // Deep clone with 100% fresh IDs
        var cloned = deepCloneTemplateElements( rawElements );
        if ( ! cloned.length ) return;

        var rootItem = cloned[0];
        var isSection = ( tpl.type === 'section' ) || ( rootItem.elType === 'section' );

        if ( isSection ) {
            // Section template: ONLY allowed at page root
            if ( targetId ) {
                var rootSec = findRootSectionOf( state.elements, targetId );
                if ( rootSec ) {
                    var rIdx = state.elements.indexOf( rootSec );
                    if ( rIdx !== -1 ) {
                        var insPos = ( position === 'before' ) ? rIdx : rIdx + 1;
                        for ( var s = 0; s < cloned.length; s++ ) {
                            state.elements.splice( insPos + s, 0, cloned[s] );
                        }
                    } else {
                        for ( var s2 = 0; s2 < cloned.length; s2++ ) {
                            state.elements.push( cloned[s2] );
                        }
                    }
                } else {
                    for ( var s3 = 0; s3 < cloned.length; s3++ ) {
                        state.elements.push( cloned[s3] );
                    }
                }
            } else {
                for ( var s4 = 0; s4 < cloned.length; s4++ ) {
                    state.elements.push( cloned[s4] );
                }
            }

        } else {
            // Container template
            var targetEl = targetId ? findElementById( state.elements, targetId ) : null;
            if ( ! targetEl && state.selectedId ) {
                targetEl = findElementById( state.elements, state.selectedId );
            }

            if ( targetEl && ( targetEl.elType === 'section' || targetEl.elType === 'container' ) ) {
                targetEl.elements = targetEl.elements || [];
                if ( position === 'before' || position === 'after' ) {
                    var pInfo = findParentOf( state.elements, targetEl.id );
                    if ( pInfo && pInfo.list ) {
                        var pIns = ( position === 'before' ) ? pInfo.index : pInfo.index + 1;
                        for ( var c = 0; c < cloned.length; c++ ) {
                            pInfo.list.splice( pIns + c, 0, cloned[c] );
                        }
                    } else {
                        for ( var c1 = 0; c1 < cloned.length; c1++ ) {
                            targetEl.elements.push( cloned[c1] );
                        }
                    }
                } else {
                    for ( var c2 = 0; c2 < cloned.length; c2++ ) {
                        targetEl.elements.push( cloned[c2] );
                    }
                }
            } else {
                // Dropped at root or no target: Auto-wrap in a new Section
                var newSec = createSectionElement();
                newSec.elements = cloned;
                if ( targetEl ) {
                    var rootSecWrap = findRootSectionOf( state.elements, targetEl.id );
                    if ( rootSecWrap ) {
                        var rwIdx = state.elements.indexOf( rootSecWrap );
                        var rwIns = ( position === 'before' ) ? rwIdx : rwIdx + 1;
                        state.elements.splice( rwIns, 0, newSec );
                    } else {
                        state.elements.push( newSec );
                    }
                } else {
                    state.elements.push( newSec );
                }
            }
        }

        state.selectedId = rootItem.id;
        markDirty();
        pushHistory( { title: tpl.title || 'Template', action: 'Template Inserted' } );
        sendToCanvas( 'update-elements', { elements: state.elements, selectedId: rootItem.id } );
        sendDynamicCssToCanvas();
        renderPanelForSelected();
        showToast( 'Template inserted', 'success' );
    }

    /* =========================================================
       DRAG & DROP HANDLER (Exact 1 Drop = 1 Element = 1 History = 1 Toast)
       ========================================================= */
    function handleCanvasDrop( data ) {
        if ( ! data ) return;

        var dragType   = data.dragType || 'new';
        var widgetType = data.widgetType || '';
        var moveId     = data.elementId || '';
        var targetId   = data.targetId || null;
        var position   = data.position || 'inside';
        var atRoot     = !! data.atRoot;

        var elementToInsert = null;
        var toastMessage = '';

        if ( dragType === 'move' ) {
            if ( ! moveId ) return;
            var movedEl = findElementById( state.elements, moveId );
            if ( ! movedEl ) return;

            // Cannot drop into itself or its own children
            if ( targetId && isDescendant( movedEl, targetId ) ) {
                return;
            }

            // Remove from existing location
            elementToInsert = removeElementById( state.elements, moveId );
            if ( ! elementToInsert ) return;

            var elTypeName = elementToInsert.elType === 'section' ? 'Section' : ( elementToInsert.elType === 'container' ? 'Container' : ( elementToInsert.elType === 'global' ? 'Global' : 'Heading' ) );
            toastMessage = elTypeName + ' moved';

        } else if ( widgetType === 'global' ) {
            var templateId = parseInt( data.templateId, 10 );
            var gData = getGlobalData( templateId );

            if ( ! gData ) {
                showToast( 'Global template not found', 'error' );
                return;
            }

            // Circular check
            if ( LK.postId && String( LK.postId ) === String( templateId ) ) {
                showToast( 'Cannot insert a global into itself', 'error' );
                return;
            }
            if ( gData.master && hasCircularGlobal( [ gData.master ], LK.postId ) ) {
                showToast( 'Circular global reference blocked', 'error' );
                return;
            }

            var gType = gData.type || data.globalType || 'section';
            elementToInsert = {
                id: generateId(),
                elType: 'global',
                globalType: gType,
                templateId: templateId,
                overrides: {},
                elements: []
            };
            toastMessage = 'Global ' + ( gType === 'section' ? 'Section' : 'Container' ) + ' added';

        } else if ( widgetType === 'template' ) {
            var tId = parseInt( data.templateId, 10 );
            insertTemplate( tId, targetId, position );
            return;

        } else {
            // New element from panel
            if ( widgetType === 'section' ) {
                elementToInsert = createSectionElement();
                toastMessage = 'Section added';
            } else if ( widgetType === 'container' ) {
                elementToInsert = createContainerElement();
                toastMessage = 'Container added';
            } else if ( widgetType === 'heading' ) {
                elementToInsert = createHeadingElement();
                toastMessage = 'Heading added';
            } else if ( widgetType ) {
                if ( ! isKnownWidget( widgetType ) ) {
                    // Refuse rather than save an element nothing can draw.
                    showToast( 'That is not a widget Loopkit knows.', 'error' );
                    return;
                }
                elementToInsert = createWidgetElement( widgetType );
                var wDef = ( LK.widgets && LK.widgets[ widgetType ] ) || null;
                var wTitle = ( wDef && wDef.title ) ? wDef.title : widgetType;
                toastMessage = wTitle + ' added';
            } else {
                return;
            }
        }

        if ( ! elementToInsert ) return;

        // Rule: Section is ONLY allowed at page root!
        // Global Sections follow the same placement rule as normal Sections.
        var isSectionLike = ( elementToInsert.elType === 'section' ) || ( elementToInsert.elType === 'global' && elementToInsert.globalType === 'section' );
        if ( isSectionLike ) {
            if ( targetId ) {
                var rootSection = findRootSectionOf( state.elements, targetId );
                if ( rootSection ) {
                    var rootIdx = state.elements.indexOf( rootSection );
                    if ( rootIdx !== -1 ) {
                        var insIdx = ( position === 'before' ) ? rootIdx : rootIdx + 1;
                        state.elements.splice( insIdx, 0, elementToInsert );
                    } else {
                        state.elements.push( elementToInsert );
                    }
                } else {
                    state.elements.push( elementToInsert );
                }
            } else {
                state.elements.push( elementToInsert );
            }

        } else {
            // Container or Heading (not a Section)
            // Rule: "Dropping a Container or a Heading at the page root: wrap it in a new Section."
            if ( atRoot || ! targetId ) {
                var wrapperSection = createSectionElement();
                wrapperSection.elements = [ elementToInsert ];

                if ( targetId ) {
                    var targetRootSec = findRootSectionOf( state.elements, targetId );
                    if ( targetRootSec ) {
                        var tIdx = state.elements.indexOf( targetRootSec );
                        var insertIndex = ( position === 'before' ) ? tIdx : tIdx + 1;
                        state.elements.splice( insertIndex, 0, wrapperSection );
                    } else {
                        state.elements.push( wrapperSection );
                    }
                } else {
                    state.elements.push( wrapperSection );
                }

            } else {
                // Inside a Section or Container
                var targetEl = findElementById( state.elements, targetId );
                if ( ! targetEl ) {
                    // Fallback to wrapping at root
                    var fallbackSection = createSectionElement();
                    fallbackSection.elements = [ elementToInsert ];
                    state.elements.push( fallbackSection );
                } else if ( position === 'inside' ) {
                    targetEl.elements = targetEl.elements || [];
                    targetEl.elements.push( elementToInsert );
                } else {
                    // 'before' or 'after' relative to targetEl
                    var parentInfo = findParentOf( state.elements, targetId );
                    if ( parentInfo && parentInfo.list ) {
                        var insertPos = ( position === 'before' ) ? parentInfo.index : parentInfo.index + 1;
                        parentInfo.list.splice( insertPos, 0, elementToInsert );
                    } else {
                        // If targetEl is at root level and we are before/after it
                        var rootSecWrapper = createSectionElement();
                        rootSecWrapper.elements = [ elementToInsert ];
                        var rIndex = state.elements.indexOf( targetEl );
                        if ( rIndex !== -1 ) {
                            var rInsertPos = ( position === 'before' ) ? rIndex : rIndex + 1;
                            state.elements.splice( rInsertPos, 0, rootSecWrapper );
                        } else {
                            state.elements.push( rootSecWrapper );
                        }
                    }
                }
            }
        }

        state.selectedId = elementToInsert.id;
        markDirty();
        if ( dragType === 'move' ) {
            pushHistory( { title: elTypeName || getElementTitle( elementToInsert ), action: 'Moved' } );
        } else if ( widgetType === 'global' ) {
            pushHistory( { title: ( gData && gData.title ) || 'Global', action: 'Template Inserted' } );
        } else {
            pushHistory( { title: getElementTitle( elementToInsert ), action: 'Added' } );
        }
        sendToCanvas( 'update-elements', { elements: state.elements, selectedId: elementToInsert.id } );
        sendDynamicCssToCanvas();
        renderPanelForSelected();
        showToast( toastMessage, 'success' );
    }

    /* =========================================================
       DUPLICATE & DELETE
       ========================================================= */
    function deleteElement( id ) {
        if ( ! id ) return;

        var removed = removeElementById( state.elements, id );
        if ( removed ) {
            if ( state.selectedId === id ) {
                showElementsPanel();
            }
            markDirty();
            pushHistory( { title: getElementTitle( removed ), action: 'Removed' } );
            sendToCanvas( 'update-elements', { elements: state.elements } );
            sendDynamicCssToCanvas();
            showToast( 'Element deleted', 'success' );
        }
    }

    function duplicateElement( id ) {
        if ( ! id ) return;

        function cloneWithNewIds( el ) {
            if ( el.elType === 'global' ) {
                return {
                    id: generateId(),
                    elType: 'global',
                    globalType: el.globalType,
                    templateId: el.templateId,
                    overrides: JSON.parse( JSON.stringify( el.overrides || {} ) ),
                    elements: []
                };
            }
            var clone = JSON.parse( JSON.stringify( el ) );
            clone.id = generateId();
            if ( clone.elements && clone.elements.length ) {
                for ( var i = 0; i < clone.elements.length; i++ ) {
                    clone.elements[i] = cloneWithNewIds( clone.elements[i] );
                }
            }
            return clone;
        }

        function duplicateRecursive( list ) {
            for ( var i = 0; i < list.length; i++ ) {
                if ( list[i].id === id ) {
                    var clone = cloneWithNewIds( list[i] );
                    list.splice( i + 1, 0, clone );
                    return clone.id;
                }
                if ( list[i].elements && list[i].elements.length ) {
                    var newId = duplicateRecursive( list[i].elements );
                    if ( newId ) return newId;
                }
            }
            return null;
        }

        var originalEl = findElementById( state.elements, id );
        var duplicatedId = duplicateRecursive( state.elements );
        if ( duplicatedId ) {
            markDirty();
            pushHistory( { title: getElementTitle( originalEl ), action: 'Duplicated' } );
            sendToCanvas( 'update-elements', { elements: state.elements, selectedId: duplicatedId } );
            sendDynamicCssToCanvas();
            showToast( 'Element duplicated', 'success' );
        }
    }

    /* =========================================================
       STYLE COPY / PASTE / RESET
       ========================================================= */
    var copiedStyle = null;

    var STYLE_KEYS = [
        'background_color',
        'background_image',
        'background_position', 'background_position_tablet', 'background_position_mobile',
        'background_xpos', 'background_xpos_tablet', 'background_xpos_mobile',
        'background_ypos', 'background_ypos_tablet', 'background_ypos_mobile',
        'background_attachment',
        'background_repeat', 'background_repeat_tablet', 'background_repeat_mobile',
        'background_size', 'background_size_tablet', 'background_size_mobile',
        'background_custom_width', 'background_custom_width_tablet', 'background_custom_width_mobile',
        'border_type', 'border_type_tablet', 'border_type_mobile',
        'border_width', 'border_width_tablet', 'border_width_mobile',
        'border_color',
        'border_radius', 'border_radius_tablet', 'border_radius_mobile',
        'box_shadow', 'box_shadow_position', 'box_shadow_color',
        'box_shadow_horizontal', 'box_shadow_vertical', 'box_shadow_blur', 'box_shadow_spread',
        'title_color', 'typography',
        'text_color', 'text_shadow', 'space', 'space_tablet', 'space_mobile',
        'link_color', 'link_hover_color', 'drop_cap_view', 'drop_cap_primary_color', 'drop_cap_secondary_color',
        'drop_cap_size', 'drop_cap_space', 'drop_cap_border_radius', 'drop_cap_border_width',
        'width', 'width_tablet', 'width_mobile', 'max_width', 'max_width_tablet', 'max_width_mobile',
        'height', 'height_tablet', 'height_mobile', 'object_fit', 'object_position',
        'opacity', 'opacity_hover', 'css_filters', 'css_filters_hover', 'hover_transition_duration', 'hover_animation',
        'caption_align', 'caption_color', 'caption_typography', 'caption_space',
        'button_text_color', 'button_background_color', 'button_hover_text_color', 'button_hover_background_color',
        'button_background_type', 'button_hover_background_type', 'button_hover_animation',
        'button_border_color', 'button_hover_border_color'
    ];

    function copyElementStyle( elementId ) {
        var el = findElementById( state.elements, elementId || state.selectedId );
        if ( ! el ) return;
        var styleData = {};
        var srcSettings = el.settings || {};
        if ( el.elType === 'global' ) {
            var gDataObj = getGlobalData( el.templateId );
            var gMaster = ( gDataObj && gDataObj.master && gDataObj.master.settings ) || {};
            var gOverrides = ( el.overrides && el.overrides.settings ) || {};
            srcSettings = $.extend( {}, gMaster, el.settings || {}, gOverrides );
        }
        STYLE_KEYS.forEach( function( key ) {
            if ( srcSettings[ key ] !== undefined ) {
                styleData[ key ] = JSON.parse( JSON.stringify( srcSettings[ key ] ) );
            }
        });
        copiedStyle = styleData;
        showToast( 'Style copied', 'success' );
    }

    function pasteElementStyle( elementId ) {
        if ( ! copiedStyle ) {
            showToast( 'No style copied yet', 'info' );
            return;
        }
        var el = findElementById( state.elements, elementId || state.selectedId );
        if ( ! el ) return;

        if ( el.elType === 'global' ) {
            el.overrides = el.overrides || {};
            el.overrides.settings = el.overrides.settings || {};
            Object.keys( copiedStyle ).forEach( function( key ) {
                el.overrides.settings[ key ] = JSON.parse( JSON.stringify( copiedStyle[ key ] ) );
            });
        } else {
            el.settings = el.settings || {};
            Object.keys( copiedStyle ).forEach( function( key ) {
                el.settings[ key ] = JSON.parse( JSON.stringify( copiedStyle[ key ] ) );
            });
        }

        markDirty();
        pushHistory( { title: getElementTitle( el ), action: 'Pasted Style' } );
        sendToCanvas( 'update-elements', { elements: state.elements, selectedId: el.id } );
        sendDynamicCssToCanvas();
        renderPanelForSelected();
        showToast( 'Style pasted', 'success' );
    }

    function resetElementStyle( elementId ) {
        var el = findElementById( state.elements, elementId || state.selectedId );
        if ( ! el ) return;

        if ( el.elType === 'global' ) {
            if ( el.overrides && el.overrides.settings ) {
                STYLE_KEYS.forEach( function( key ) {
                    delete el.overrides.settings[ key ];
                });
            }
        } else {
            if ( el.settings ) {
                STYLE_KEYS.forEach( function( key ) {
                    delete el.settings[ key ];
                });
            }
        }

        markDirty();
        pushHistory( { title: getElementTitle( el ), action: 'Reset Style' } );
        sendToCanvas( 'update-elements', { elements: state.elements, selectedId: el.id } );
        sendDynamicCssToCanvas();
        renderPanelForSelected();
        showToast( 'Style reset', 'success' );
    }

    var copiedElementData = null;

    function copyElement( elementId ) {
        var el = findElementById( state.elements, elementId || state.selectedId );
        if ( ! el ) return;
        copiedElementData = JSON.parse( JSON.stringify( el ) );
        showToast( getElementTitle( el ) + ' copied', 'success' );
    }

    function pasteElement( targetElementId ) {
        if ( ! copiedElementData ) {
            showToast( 'No element copied yet', 'info' );
            return;
        }

        function cloneWithNewIds( el ) {
            var clone = JSON.parse( JSON.stringify( el ) );
            clone.id = generateId();
            if ( clone.elements && clone.elements.length ) {
                for ( var i = 0; i < clone.elements.length; i++ ) {
                    clone.elements[i] = cloneWithNewIds( clone.elements[i] );
                }
            }
            return clone;
        }

        var newEl = cloneWithNewIds( copiedElementData );
        var targetId = targetElementId || state.selectedId;

        if ( targetId ) {
            var targetEl = findElementById( state.elements, targetId );
            if ( targetEl ) {
                if ( newEl.elType === 'section' ) {
                    var secIdx = state.elements.indexOf( targetEl );
                    if ( secIdx !== -1 ) {
                        state.elements.splice( secIdx + 1, 0, newEl );
                    } else {
                        state.elements.push( newEl );
                    }
                } else {
                    if ( targetEl.elType === 'section' || targetEl.elType === 'container' ) {
                        targetEl.elements = targetEl.elements || [];
                        targetEl.elements.push( newEl );
                    } else {
                        var pInfo = findParentOf( state.elements, targetId );
                        if ( pInfo && pInfo.list ) {
                            pInfo.list.splice( pInfo.index + 1, 0, newEl );
                        } else {
                            var wrapper = createSectionElement();
                            wrapper.elements = [ newEl ];
                            state.elements.push( wrapper );
                        }
                    }
                }
            } else {
                if ( newEl.elType === 'section' ) {
                    state.elements.push( newEl );
                } else {
                    var wrapper = createSectionElement();
                    wrapper.elements = [ newEl ];
                    state.elements.push( wrapper );
                }
            }
        } else {
            if ( newEl.elType === 'section' ) {
                state.elements.push( newEl );
            } else {
                var wrapper = createSectionElement();
                wrapper.elements = [ newEl ];
                state.elements.push( wrapper );
            }
        }

        state.elements = ensureSections( state.elements );
        state.selectedId = newEl.id;
        markDirty();
        pushHistory( { title: getElementTitle( newEl ), action: 'Pasted' } );
        sendToCanvas( 'update-elements', { elements: state.elements, selectedId: newEl.id } );
        sendDynamicCssToCanvas();
        renderPanelForSelected();
        if ( typeof renderStructureTree === 'function' ) {
            renderStructureTree();
        }
        showToast( getElementTitle( newEl ) + ' pasted', 'success' );
    }

    function detachElement( elementId ) {
        var el = findElementById( state.elements, elementId || state.selectedId );
        if ( ! el || el.elType !== 'global' ) return;

        var gData = getGlobalData( el.templateId );
        var gMaster = ( gData && gData.master ) || {};
        var masterSettings = gMaster.settings ? JSON.parse( JSON.stringify( gMaster.settings ) ) : {};
        var overrideSettings = ( el.overrides && el.overrides.settings ) ? JSON.parse( JSON.stringify( el.overrides.settings ) ) : {};
        var masterChildren = gMaster.elements ? JSON.parse( JSON.stringify( gMaster.elements ) ) : [];

        function assignNewIds( list ) {
            for ( var i = 0; i < list.length; i++ ) {
                list[i].id = generateId();
                if ( list[i].elements && list[i].elements.length ) {
                    assignNewIds( list[i].elements );
                }
            }
        }
        assignNewIds( masterChildren );

        var newType = el.globalType || 'section';
        el.elType = newType;
        el.settings = $.extend( {}, masterSettings, el.settings || {}, overrideSettings );
        el.elements = masterChildren;
        delete el.templateId;
        delete el.globalType;
        delete el.overrides;

        markDirty();
        pushHistory( { title: getElementTitle( el ), action: 'Detached' } );
        sendToCanvas( 'update-elements', { elements: state.elements, selectedId: el.id } );
        sendDynamicCssToCanvas();
        renderPanelForSelected();
        if ( typeof renderStructureTree === 'function' ) {
            renderStructureTree();
        }
        showToast( 'Global detached to local ' + newType, 'success' );
    }

    /* =========================================================
       CSS ENGINE SYNC (Section 3)
       ========================================================= */
    function sendDynamicCssToCanvas() {
        if ( window.LoopkitCSS && window.LoopkitCSS.generateAll ) {
            var css = window.LoopkitCSS.generateAll( state.elements );
            var gIds = collectGlobalIds( state.elements );
            for ( var i = 0; i < gIds.length; i++ ) {
                var gid = gIds[i];
                var gData = getGlobalData( gid );
                if ( gData && gData.css ) {
                    css += '\n' + gData.css;
                }
            }
            sendToCanvas( 'update-dynamic-css', { css: css } );
        }
    }

    /* =========================================================
       PANEL VIEW & CONTROL RENDERING
       ========================================================= */

    /**
     * Show the Elements library list in the left panel
     */
    function showElementsPanel() {
        state.selectedId = null;
        state.activePanel = 'elements';
        $( '#lk-panel-back-to-elements' ).hide();
        $( '#lk-panel-tabs' ).hide();
        $( '#lk-panel-controls' ).hide().empty();
        $( '#lk-panel-history' ).hide();
        $( '#lk-panel-site-settings' ).hide();
        $( '#lk-panel-user-prefs' ).hide();
        $( '#lk-history-toggle' ).removeClass( 'lk-topbar-active' );
        $( '#lk-panel-elements' ).show();

        if ( state.activeElementsTab === 'globals' ) {
            $( '#lk-pane-elements' ).hide();
            $( '#lk-pane-templates' ).hide();
            $( '#lk-pane-globals' ).show();
            renderGlobalsList();
        } else if ( state.activeElementsTab === 'templates' ) {
            $( '#lk-pane-elements' ).hide();
            $( '#lk-pane-globals' ).hide();
            $( '#lk-pane-templates' ).show();
            renderTemplatesList();
        } else {
            $( '#lk-pane-globals' ).hide();
            $( '#lk-pane-templates' ).hide();
            $( '#lk-pane-elements' ).show();
        }

        $( '#lk-panel-header-icon' ).attr( 'class', 'dashicons dashicons-screenoptions' );
        $( '#lk-panel-header-text' ).text( LK.strings.elementsTitle || 'Elements' );

        sendToCanvas( 'select-element', { elementId: null } );
        $( '.lk-tree-row' ).removeClass( 'lk-tree-row--active' );
    }

    /**
     * Show Page / Template Settings in the left panel
     */
    function showSettingsPanel() {
        state.selectedId = null;
        state.activePanel = 'settings';
        sendToCanvas( 'select-element', { elementId: null } );
        $( '.lk-tree-row' ).removeClass( 'lk-tree-row--active' );

        if ( state.panelCollapsed ) {
            $( '#lk-panel' ).removeClass( 'lk-panel-collapsed' );
            state.panelCollapsed = false;
        }

        $( '#lk-panel-elements' ).hide();
        $( '#lk-panel-history' ).hide();
        $( '#lk-panel-site-settings' ).hide();
        $( '#lk-panel-user-prefs' ).hide();
        $( '#lk-history-toggle' ).removeClass( 'lk-topbar-active' );
        $( '#lk-panel-back-to-elements' ).show();
        $( '#lk-panel-tabs' ).hide();
        var $controls = $( '#lk-panel-controls' ).show().empty();

        var isTpl = !! LK.isTemplate;
        var tplType = LK.templateType || 'page';

        $( '#lk-panel-header-icon' ).attr( 'class', 'dashicons dashicons-admin-generic' );
        $( '#lk-panel-header-text' ).text( isTpl ? ( LK.strings.templateSettings || 'Template Settings' ) : ( LK.strings.pageSettings || 'Page Settings' ) );

        state.pageSettings = state.pageSettings || LK.pageSettings || {};

        var sections = [];
        if ( isTpl ) {
            var tagDefault = 'div';
            var tagOptions = { div: 'div', section: 'section' };
            if ( tplType === 'header' ) {
                tagDefault = 'header';
                tagOptions = { header: 'header', div: 'div', section: 'section' };
            } else if ( tplType === 'footer' ) {
                tagDefault = 'footer';
                tagOptions = { footer: 'footer', div: 'div', section: 'section' };
            } else if ( tplType === 'single' || tplType === 'archive' ) {
                tagDefault = 'div';
                tagOptions = { div: 'div', article: 'article', main: 'main', section: 'section' };
            }

            sections.push({
                id: 'section_template_general',
                title: 'General',
                controls: [
                    {
                        name: 'post_title',
                        label: 'Title',
                        type: 'text',
                        default: LK.postTitle || '',
                    },
                    {
                        name: 'post_status',
                        label: 'Status',
                        type: 'select',
                        default: LK.postStatus || 'draft',
                        options: {
                            draft: 'Draft',
                            pending: 'Pending Review',
                            private: 'Private',
                            publish: 'Publish',
                        },
                    },
                    {
                        name: 'wrapper_tag',
                        label: 'Wrapper HTML Tag',
                        type: 'select',
                        default: tagDefault,
                        options: tagOptions,
                    },
                ],
            });

            // Single templates: choose which post the canvas previews (Post widgets read it)
            if ( ( tplType === 'single' || tplType === 'loop-item' ) && LK.previewPostOptions && Object.keys( LK.previewPostOptions ).length ) {
                sections.push({
                    id: 'section_template_preview',
                    title: 'Preview Settings',
                    controls: [
                        {
                            name: 'preview_id',
                            label: 'Preview With',
                            type: 'select',
                            default: String( LK.previewPostId || '' ),
                            options: LK.previewPostOptions,
                            description: 'The post shown in the editor while you design this template.',
                        },
                    ],
                });
            }

            // Archive templates: choose which archive the canvas previews (Archive widgets read it)
            if ( tplType === 'archive' && LK.previewArchiveOptions && Object.keys( LK.previewArchiveOptions ).length ) {
                sections.push({
                    id: 'section_template_preview_archive',
                    title: 'Preview Settings',
                    controls: [
                        {
                            name: 'preview_archive',
                            label: 'Preview With',
                            type: 'select',
                            default: String( LK.previewArchiveKey || 'blog' ),
                            options: LK.previewArchiveOptions,
                            description: 'The archive shown in the editor while you design this template.',
                        },
                    ],
                });
            }
        } else {
            // Normal page
            sections.push({
                id: 'section_page_general',
                title: 'General',
                controls: [
                    {
                        name: 'post_title',
                        label: 'Title',
                        type: 'text',
                        default: LK.postTitle || '',
                    },
                    {
                        name: 'post_status',
                        label: 'Status',
                        type: 'select',
                        default: LK.postStatus || 'publish',
                        options: {
                            draft: 'Draft',
                            pending: 'Pending Review',
                            private: 'Private',
                            publish: 'Publish',
                        },
                    },
                    {
                        name: 'page_layout',
                        label: 'Page Layout',
                        type: 'select',
                        default: state.pageSettings.page_layout || 'loopkit-full-width',
                        options: {
                            default: 'Default',
                            'loopkit-full-width': 'Loopkit Full Width',
                            'loopkit-canvas': 'Loopkit Canvas',
                        },
                    },
                ],
            });
        }


        // Popup templates get their own settings: size, position, triggers, how often.
        if ( tplType === 'popup' ) {
            sections.push({
                id: 'section_popup_layout',
                title: 'Popup Layout',
                controls: [
                    { name: 'popup__width', label: 'Width (px)', type: 'number', default: 600 },
                    { name: 'popup__height', label: 'Height (px, empty = auto)', type: 'number', default: '' },
                    { name: 'popup__position', label: 'Position', type: 'select', default: 'center', options: {
                        'center': 'Center', 'top': 'Top', 'bottom': 'Bottom', 'left': 'Left', 'right': 'Right',
                        'top-left': 'Top Left', 'top-right': 'Top Right', 'bottom-left': 'Bottom Left', 'bottom-right': 'Bottom Right'
                    } },
                    { name: 'popup__animation', label: 'Entrance Animation', type: 'select', default: 'fade', options: {
                        'fade': 'Fade', 'zoom': 'Zoom', 'slide-up': 'Slide Up', 'slide-down': 'Slide Down',
                        'slide-left': 'Slide Left', 'slide-right': 'Slide Right', 'none': 'None'
                    } },
                    { name: 'popup__background', label: 'Background', type: 'color', default: '#ffffff' },
                    { name: 'popup__padding', label: 'Padding (px)', type: 'number', default: 30 },
                    { name: 'popup__border_radius', label: 'Border Radius (px)', type: 'number', default: 8 },
                    { name: 'popup__overlay', label: 'Overlay', type: 'switcher', default: 'yes' },
                    { name: 'popup__overlay_color', label: 'Overlay Colour', type: 'color', default: 'rgba(0,0,0,0.75)' },
                    { name: 'popup__close_button', label: 'Close Button', type: 'switcher', default: 'yes' },
                    { name: 'popup__close_button_position', label: 'Close Button Position', type: 'select', default: 'inside', options: { 'inside': 'Inside', 'outside': 'Outside' } },
                    { name: 'popup__close_on_overlay', label: 'Close When Overlay Clicked', type: 'switcher', default: 'yes' },
                    { name: 'popup__close_on_esc', label: 'Close With Escape Key', type: 'switcher', default: 'yes' },
                    { name: 'popup__prevent_scroll', label: 'Stop Page Scrolling', type: 'switcher', default: 'yes' },
                ],
            });
            sections.push({
                id: 'section_popup_triggers',
                title: 'Triggers',
                controls: [
                    { name: 'popup__trigger_load', label: 'On Page Load', type: 'switcher', default: 'yes' },
                    { name: 'popup__trigger_load_delay', label: 'After (seconds)', type: 'number', default: 2, condition: { 'popup__trigger_load': 'yes' } },
                    { name: 'popup__trigger_scroll', label: 'On Scroll', type: 'switcher', default: 'no' },
                    { name: 'popup__trigger_scroll_at', label: 'Scrolled (%)', type: 'number', default: 50, condition: { 'popup__trigger_scroll': 'yes' } },
                    { name: 'popup__trigger_exit', label: 'On Exit Intent', type: 'switcher', default: 'no' },
                    { name: 'popup__trigger_idle', label: 'After Inactivity', type: 'switcher', default: 'no' },
                    { name: 'popup__trigger_idle_after', label: 'Inactive For (seconds)', type: 'number', default: 30, condition: { 'popup__trigger_idle': 'yes' } },
                    { name: 'popup__trigger_click', label: 'On Click', type: 'switcher', default: 'no' },
                    { name: 'popup__trigger_click_selector', label: 'CSS Selector (e.g. .open-offer)', type: 'text', default: '', condition: { 'popup__trigger_click': 'yes' } },
                ],
            });
            sections.push({
                id: 'section_popup_rules',
                title: 'How Often',
                controls: [
                    { name: 'popup__frequency', label: 'Show', type: 'select', default: 'session', options: {
                        'always': 'Every time', 'session': 'Once per session', 'days': 'Once every X days'
                    } },
                    { name: 'popup__frequency_days', label: 'Days', type: 'number', default: 7, condition: { 'popup__frequency': 'days' } },
                    { name: 'popup__close_stops_it', label: 'Closing Stops It For This Page View', type: 'switcher', default: 'yes' },
                ],
            });
        }

        // Page / Template JS — like Custom CSS, but JavaScript for this document only.
        sections.push({
            id: 'section_page_js',
            title: 'Custom JS',
            controls: [
                {
                    name: 'custom_js',
                    label: 'Page JS',
                    type: 'code',
                    placeholder: '/* Page JS \u2014 runs on this page only, just before </body>.\n * jQuery is available as $ inside this function.\n * Example:\n *   document.querySelectorAll(".my-box").forEach(function (el) {\n *     el.addEventListener("click", function () { el.classList.toggle("is-open"); });\n *   });\n */',
                    description: 'Runs on the front end of this page only, just before </body>. Wrapped for you, so $ is jQuery. Administrators only.',
                },
            ],
        });

        // Popup settings are stored separately (their own meta), so seed the panel fields.
        if ( tplType === 'popup' && LK.popupSettings ) {
            Object.keys( LK.popupSettings ).forEach( function( key ) {
                if ( state.pageSettings[ 'popup__' + key ] === undefined ) {
                    state.pageSettings[ 'popup__' + key ] = LK.popupSettings[ key ];
                }
            } );
        }

        var curSettings = $.extend( {}, state.pageSettings, {
            custom_js: ( state.pageSettings.custom_js !== undefined && state.pageSettings.custom_js !== '' ) ? state.pageSettings.custom_js : ( LK.canEditJs ? '/* Page JS \u2014 runs on this page only, just before </body>.\n * jQuery is available as $ inside this function.\n * Example:\n *   document.querySelectorAll(".my-box").forEach(function (el) {\n *     el.addEventListener("click", function () { el.classList.toggle("is-open"); });\n *   });\n */\n' : '' ),
            post_title: state.pageSettings.post_title || LK.postTitle || '',
            post_status: state.pageSettings.post_status || LK.postStatus || 'draft',
            wrapper_tag: state.pageSettings.wrapper_tag || ( isTpl ? ( tplType === 'header' ? 'header' : ( tplType === 'footer' ? 'footer' : 'div' ) ) : 'div' ),
            preview_id: state.pageSettings.preview_id || String( LK.previewPostId || '' ),
            preview_archive: state.pageSettings.preview_archive || String( LK.previewArchiveKey || 'blog' ),
        });

        var html = window.LoopkitControls.renderTabSections( sections, curSettings, 'desktop' );
        $controls.html( html );

        // bindEvents( $panel, onControlChange, onDeviceSwitch ) — the change handler is the 2nd argument.
        window.LoopkitControls.bindEvents( $controls, function( name, val ) {
            state.pageSettings[ name ] = val;
            if ( name === 'preview_id' ) {
                loadPreviewPost( val );
            }
            if ( name === 'preview_archive' ) {
                loadPreviewArchive( val );
            }
            if ( name === 'post_title' ) {
                LK.postTitle = val;
                $( '#lk-topbar-title' ).text( val );
            } else if ( name === 'post_status' ) {
                var isHF = isTpl && [ 'header', 'footer', 'single', 'archive', 'popup' ].indexOf( tplType ) !== -1;
                if ( val === 'publish' && isHF && ( ! state.conditions || state.conditions.length === 0 ) ) {
                    openDisplayConditionsModal( function() {
                        LK.postStatus = 'publish';
                        state.pageSettings.post_status = 'publish';
                        $( '#lk-publish-btn' ).text( LK.strings.update || 'Update' );
                        saveDocument( 'publish' );
                    }, function() {
                        LK.postStatus = 'draft';
                        state.pageSettings.post_status = 'draft';
                        $( '#lk-publish-btn' ).text( LK.strings.saveDraft || 'Save Draft' );
                        renderPageSettings();
                    } );
                    return;
                }
                LK.postStatus = val;
                $( '#lk-publish-btn' ).text( val === 'publish' ? ( LK.strings.update || 'Update' ) : ( LK.strings.saveDraft || 'Save Draft' ) );
            }
            markDirty();
            clearTimeout( state.historyDebounce );
            state.historyDebounce = setTimeout( function() {
                pushHistory({
                    title: 'Page Settings',
                    action: 'Edited',
                    sub: formatControlLabel( name )
                });
            }, 600 );
        });
    }

    /**
     * Get sections definition for Heading Content Tab
     */
    function getHeadingContentSections() {
        return [
            {
                id: 'section_title',
                title: 'Title',
                controls: [
                    {
                        name: 'title',
                        label: 'Title',
                        type: 'textarea',
                        default: 'Add Your Heading Text Here',
                    },
                    {
                        name: 'link',
                        label: 'Link',
                        type: 'url',
                    },
                    {
                        name: 'tag',
                        label: 'HTML Tag',
                        type: 'select',
                        default: 'h2',
                        options: {
                            h1:   'H1',
                            h2:   'H2',
                            h3:   'H3',
                            h4:   'H4',
                            h5:   'H5',
                            h6:   'H6',
                            div:  'div',
                            span: 'span',
                            p:    'p',
                        },
                    },
                    {
                        name: 'align',
                        label: 'Alignment',
                        type: 'choose',
                        responsive: true,
                        default: 'left',
                        options: {
                            left:    { title: 'Left',      icon: 'dashicons-editor-alignleft' },
                            center:  { title: 'Center',    icon: 'dashicons-editor-aligncenter' },
                            right:   { title: 'Right',     icon: 'dashicons-editor-alignright' },
                            justify: { title: 'Justified', icon: 'dashicons-editor-justify' },
                        },
                    },
                ],
            },
        ];
    }

    /**
     * Render the controls panel for the currently selected element and active tab.
     */
    $( document ).on( 'lk-popovers-closed', function() {
        if ( state.selectedId ) renderPanelForSelected();
    } );

    /**
     * Page settings for saving. Popup fields are prefixed popup__ in the panel and are
     * sent as a nested "popup" object, which the REST endpoint stores in its own meta.
     */
    function collectPageSettingsForSave() {
        var out = $.extend( {}, state.pageSettings || {} );
        var popup = {};
        Object.keys( out ).forEach( function( key ) {
            if ( key.indexOf( 'popup__' ) === 0 ) {
                popup[ key.slice( 7 ) ] = out[ key ];
                delete out[ key ];
            }
        } );
        if ( Object.keys( popup ).length ) out.popup = popup;
        return out;
    }

    function renderPanelForSelected() {
        // Do not rebuild the panel while a colour picker (or its Global Colors menu) is open:
        // re-rendering removed the picker mid-use, so clicking the gradient closed it.
        if ( $( '.lk-color-popover:visible, .lk-color-globals-menu:visible' ).length ) {
            return;
        }

        if ( ! state.selectedId ) {
            showElementsPanel();
            return;
        }

        var el = findElementById( state.elements, state.selectedId );
        if ( ! el ) {
            showElementsPanel();
            return;
        }

        // Expand panel if collapsed
        if ( state.panelCollapsed ) {
            $( '#lk-panel' ).removeClass( 'lk-panel-collapsed' );
            state.panelCollapsed = false;
        }

        state.activePanel = 'controls';
        $( '#lk-panel-history' ).hide();
        $( '#lk-panel-site-settings' ).hide();
        $( '#lk-panel-user-prefs' ).hide();
        $( '#lk-history-toggle' ).removeClass( 'lk-topbar-active' );

        // Header
        $( '#lk-panel-back-to-elements' ).show();
        $( '#lk-panel-elements' ).hide();
        $( '#lk-panel-tabs' ).show();
        var $controls = $( '#lk-panel-controls' ).show();

        var isSection   = ( el.elType === 'section' );
        var isContainer = ( el.elType === 'container' );
        var isGlobal    = ( el.elType === 'global' );
        var isWidget    = ( el.elType === 'widget' );

        if ( isGlobal ) {
            var gData = getGlobalData( el.templateId );
            var gTitle = ( el.settings && el.settings._title ) || ( gData && gData.title ) || ( 'Global ' + ( el.globalType === 'section' ? 'Section' : 'Container' ) );
            $( '#lk-panel-header-icon' ).attr( 'class', 'dashicons dashicons-admin-site-alt3' );
            $( '#lk-panel-header-text' ).text( gTitle );
            $( '#lk-tab-first' ).text( 'Layout' );
        } else if ( isSection ) {
            $( '#lk-panel-header-icon' ).attr( 'class', 'dashicons dashicons-format-aside' );
            $( '#lk-panel-header-text' ).text( ( el.settings && el.settings._title ) ? ( 'Edit ' + el.settings._title ) : ( LK.strings.editSection || 'Edit Section' ) );
            $( '#lk-tab-first' ).text( 'Layout' );
        } else if ( isContainer ) {
            $( '#lk-panel-header-icon' ).attr( 'class', 'dashicons dashicons-layout' );
            $( '#lk-panel-header-text' ).text( ( el.settings && el.settings._title ) ? ( 'Edit ' + el.settings._title ) : ( LK.strings.editContainer || 'Edit Container' ) );
            $( '#lk-tab-first' ).text( 'Layout' );
        } else if ( isWidget ) {
            var wDef = ( LK.widgets && LK.widgets[ el.widgetType ] ) || null;
            var wTitle = formatWidgetTitle( el.widgetType );
            var iconClass = 'dashicons dashicons-admin-generic';
            if ( el.widgetType === 'heading' ) iconClass = 'dashicons dashicons-heading';
            else if ( el.widgetType === 'text-editor' ) iconClass = 'dashicons dashicons-editor-paragraph';
            else if ( el.widgetType === 'image' ) iconClass = 'dashicons dashicons-format-image';
            else if ( el.widgetType === 'button' ) iconClass = 'dashicons dashicons-button';
            else if ( el.widgetType === 'icon' ) iconClass = 'dashicons dashicons-star-filled';
            else if ( el.widgetType === 'spacer' ) iconClass = 'dashicons dashicons-minus';
            else if ( el.widgetType === 'divider' ) iconClass = 'dashicons dashicons-ellipsis';
            else if ( el.widgetType === 'video' ) iconClass = 'dashicons dashicons-video-alt3';
            else if ( el.widgetType === 'image-box' ) iconClass = 'dashicons dashicons-format-gallery';
            else if ( el.widgetType === 'icon-box' ) iconClass = 'dashicons dashicons-layout';
            else if ( el.widgetType === 'icon-list' ) iconClass = 'dashicons dashicons-editor-ul';
            else if ( el.widgetType === 'social-icons' ) iconClass = 'dashicons dashicons-share';
            else if ( el.widgetType === 'html' ) iconClass = 'dashicons dashicons-editor-code';
            else if ( el.widgetType === 'shortcode' ) iconClass = 'dashicons dashicons-shortcode';
            else if ( el.widgetType === 'site-logo' ) iconClass = 'dashicons dashicons-format-image';
            else if ( el.widgetType === 'site-title' ) iconClass = 'dashicons dashicons-admin-site';
            else if ( el.widgetType === 'nav-menu' ) iconClass = 'dashicons dashicons-menu-alt3';
            else if ( el.widgetType === 'search-form' ) iconClass = 'dashicons dashicons-search';
            else if ( el.widgetType === 'post-title' ) iconClass = 'dashicons dashicons-editor-textcolor';
            else if ( el.widgetType === 'post-featured-image' ) iconClass = 'dashicons dashicons-format-image';
            else if ( el.widgetType === 'post-excerpt' ) iconClass = 'dashicons dashicons-text';
            else if ( el.widgetType === 'post-info' ) iconClass = 'dashicons dashicons-info-outline';
            else if ( el.widgetType === 'post-content' ) iconClass = 'dashicons dashicons-media-document';
            else if ( el.widgetType === 'author-box' ) iconClass = 'dashicons dashicons-id';
            else if ( el.widgetType === 'post-navigation' ) iconClass = 'dashicons dashicons-leftright';
            else if ( el.widgetType === 'post-comments' ) iconClass = 'dashicons dashicons-admin-comments';
            else if ( el.widgetType === 'archive-title' ) iconClass = 'dashicons dashicons-editor-textcolor';
            else if ( el.widgetType === 'archive-description' ) iconClass = 'dashicons dashicons-text';
            else if ( el.widgetType === 'posts' ) iconClass = 'dashicons dashicons-grid-view';
            else if ( el.widgetType === 'loop-grid' ) iconClass = 'dashicons dashicons-screenoptions';
            else if ( el.widgetType === 'pagination' ) iconClass = 'dashicons dashicons-ellipsis';
            else if ( el.widgetType === 'accordion' ) iconClass = 'dashicons dashicons-list-view';
            else if ( el.widgetType === 'toggle' ) iconClass = 'dashicons dashicons-arrow-down-alt2';
            else if ( el.widgetType === 'tabs' ) iconClass = 'dashicons dashicons-index-card';
            else if ( el.widgetType === 'counter' ) iconClass = 'dashicons dashicons-performance';
            else if ( el.widgetType === 'progress-bar' ) iconClass = 'dashicons dashicons-chart-bar';
            else if ( el.widgetType === 'alert' ) iconClass = 'dashicons dashicons-warning';
            else if ( el.widgetType === 'star-rating' ) iconClass = 'dashicons dashicons-star-half';
            else if ( el.widgetType === 'testimonial' ) iconClass = 'dashicons dashicons-format-quote';
            else if ( el.widgetType === 'image-carousel' ) iconClass = 'dashicons dashicons-slides';
            else if ( el.widgetType === 'image-gallery' ) iconClass = 'dashicons dashicons-format-gallery';
            else if ( el.widgetType === 'google-maps' ) iconClass = 'dashicons dashicons-location-alt';
            else if ( el.widgetType === 'contact-form' ) iconClass = 'dashicons dashicons-feedback';
            else if ( el.widgetType === 'tooltip' ) iconClass = 'dashicons dashicons-testimonial';
            else if ( el.widgetType === 'table-of-contents' ) iconClass = 'dashicons dashicons-editor-ol';
            else if ( wDef && wDef.icon && wDef.icon.indexOf( 'dashicons' ) !== -1 ) iconClass = 'dashicons ' + wDef.icon;
            $( '#lk-panel-header-icon' ).attr( 'class', iconClass );
            $( '#lk-panel-header-text' ).text( ( el.settings && el.settings._title ) ? ( 'Edit ' + el.settings._title ) : ( 'Edit ' + wTitle ) );
            $( '#lk-tab-first' ).text( 'Content' );

            // Guard: warn if widget control schema is missing
            if ( el.widgetType !== 'heading' && ( ! wDef || ! wDef.controls || ( ! wDef.controls.content && ! wDef.controls.style ) ) ) {
                console.warn( 'Loopkit: Widget "' + el.widgetType + '" has no registered control schema in LK.widgets!' );
            }
        } else {
            $( '#lk-panel-header-icon' ).attr( 'class', 'dashicons dashicons-heading' );
            $( '#lk-panel-header-text' ).text( ( el.settings && el.settings._title ) ? ( 'Edit ' + el.settings._title ) : ( LK.strings.editHeading || 'Edit Heading' ) );
            $( '#lk-tab-first' ).text( 'Content' );
        }

        if ( typeof highlightInStructureTree === 'function' ) {
            highlightInStructureTree( el.id );
        }

        // Active tab
        $( '.lk-tab-btn' ).removeClass( 'lk-tab-active' );
        $( '.lk-tab-btn[data-tab="' + state.activeTab + '"]' ).addClass( 'lk-tab-active' );

        // Determine sections to render based on element type and tab
        var sections = [];
        if ( isGlobal || isSection || isContainer ) {
            if ( state.activeTab === 'layout' ) {
                sections = window.LoopkitControls.getContainerLayoutSections();
            } else if ( state.activeTab === 'style' ) {
                sections = window.LoopkitControls.getContainerStyleSections();
            } else if ( state.activeTab === 'advanced' ) {
                sections = window.LoopkitControls.getSharedAdvancedSections( 'container' );
            }
        } else if ( isWidget ) {
            var wDef = ( LK.widgets && LK.widgets[ el.widgetType ] ) || null;
            if ( state.activeTab === 'layout' ) {
                // Content tab
                if ( wDef && wDef.controls && wDef.controls.content ) {
                    sections = wDef.controls.content;
                } else if ( el.widgetType === 'heading' ) {
                    sections = getHeadingContentSections();
                }
            } else if ( state.activeTab === 'style' ) {
                // Style tab
                if ( wDef && wDef.controls && wDef.controls.style ) {
                    sections = wDef.controls.style;
                } else if ( el.widgetType === 'heading' ) {
                    sections = [
                        {
                            id: 'section_heading_style',
                            title: 'Title',
                            controls: [
                                {
                                    name: 'title_color',
                                    label: 'Text Color',
                                    type: 'color',
                                },
                                {
                                    name: 'typography',
                                    label: 'Typography',
                                    type: 'typography',
                                },
                                {
                                    name: 'text_stroke',
                                    label: 'Text Stroke',
                                    type: 'popover',
                                    popoverType: 'text_stroke',
                                    responsive: true,
                                },
                                {
                                    name: 'text_shadow',
                                    label: 'Text Shadow',
                                    type: 'popover',
                                    popoverType: 'text_shadow',
                                },
                                {
                                    name: 'blend_mode',
                                    label: 'Blend Mode',
                                    type: 'select',
                                    default: 'normal',
                                    options: {
                                        normal:        'Normal',
                                        multiply:      'Multiply',
                                        screen:        'Screen',
                                        overlay:       'Overlay',
                                        darken:        'Darken',
                                        lighten:       'Lighten',
                                        'color-dodge': 'Color Dodge',
                                        saturation:    'Saturation',
                                        color:         'Color',
                                        difference:    'Difference',
                                        exclusion:     'Exclusion',
                                        hue:           'Hue',
                                        luminosity:    'Luminosity',
                                    },
                                },
                            ],
                        },
                    ];
                }
            } else if ( state.activeTab === 'advanced' ) {
                sections = window.LoopkitControls.getSharedAdvancedSections( 'widget' );
            }
        }

        // Capture currently open sections, active subtabs, scroll position, and focused control BEFORE replacing DOM
        var openSectionIds = [];
        $controls.find( '.lk-control-section.lk-section-open' ).each( function() {
            var sId = $( this ).data( 'section-id' );
            if ( sId ) openSectionIds.push( sId );
        });

        var activeSubtabs = {};
        $controls.find( '.lk-subtab-pane.lk-pane-active' ).each( function() {
            var paneId = $( this ).data( 'pane-id' );
            if ( paneId ) {
                var $wrap = $( this ).closest( '.lk-control-subtabs' );
                var ctrlName = $wrap.data( 'control' );
                if ( ctrlName ) activeSubtabs[ ctrlName ] = paneId;
            }
        });

        var prevScrollTop = $controls.scrollTop();

        // Capture active focused element before re-render so focus is seamlessly restored
        var activeCtrlKey = null;
        var activeTagName = null;
        var activeInputType = null;
        var activeInputVal = null;
        var activeSelStart = null;
        var activeSelEnd = null;
        var activeEl = document.activeElement;
        if ( activeEl && $controls[0] && $controls[0].contains( activeEl ) ) {
            var $cWrap = $( activeEl ).closest( '.lk-control-wrap, .lk-field-slider, .lk-field-checkbox-list, .lk-field-dimensions, .lk-field-wysiwyg, .lk-field-code' );
            activeCtrlKey = $cWrap.data( 'control' ) || $cWrap.data( 'name' );
            activeTagName = activeEl.tagName.toLowerCase();
            activeInputType = activeEl.type || '';
            activeInputVal = activeEl.value;
            if ( activeTagName === 'input' || activeTagName === 'textarea' ) {
                try {
                    activeSelStart = activeEl.selectionStart;
                    activeSelEnd = activeEl.selectionEnd;
                } catch ( e ) {}
            }
        }

        // Effective settings for element (include master + overrides if global)
        var curSettings = el.settings || {};
        if ( isGlobal ) {
            var gDataObj = getGlobalData( el.templateId );
            var masterSettings = ( gDataObj && gDataObj.master && gDataObj.master.settings ) || {};
            var overridesSettings = ( el.overrides && el.overrides.settings ) || {};
            curSettings = $.extend( {}, masterSettings, el.settings || {}, overridesSettings );
        }

        // Render tab sections with preserved open sections and active subtabs
        var html = '';

        // Safe WYSIWYG teardown
        try {
            if ( window.LoopkitControls && typeof window.LoopkitControls.destroyWysiwyg === 'function' ) {
                window.LoopkitControls.destroyWysiwyg( $controls );
            }
        } catch ( wysiwygErr ) {
            console.warn( 'Loopkit: destroyWysiwyg error:', wysiwygErr );
        }

        // Render sections HTML
        try {
            if ( window.LoopkitControls && typeof window.LoopkitControls.renderTabSections === 'function' ) {
                html = window.LoopkitControls.renderTabSections( sections, curSettings, state.device, el, openSectionIds, activeSubtabs );
            }
        } catch ( renderErr ) {
            console.error( 'Loopkit: Error rendering panel for element "' + ( el && el.id ) + '":', renderErr );
            html = '<div class="lk-panel-error" style="padding:16px;font-size:13px;color:#c0392b;">An error occurred while rendering controls. Check browser console for details.</div>';
        }

        // Output rendered HTML
        $controls.html( html );
        $controls.scrollTop( prevScrollTop );

        // Restore DOM focus if an input had focus
        if ( activeCtrlKey ) {
            var $targetWrap = $controls.find( '.lk-control-wrap[data-control="' + activeCtrlKey + '"], .lk-control-wrap[data-name="' + activeCtrlKey + '"], .lk-field-slider[data-name="' + activeCtrlKey + '"], .lk-field-checkbox-list[data-name="' + activeCtrlKey + '"], .lk-field-wysiwyg[data-name="' + activeCtrlKey + '"], .lk-field-code[data-name="' + activeCtrlKey + '"]' );
            if ( $targetWrap.length ) {
                var $toFocus = null;
                if ( activeTagName === 'select' ) {
                    $toFocus = $targetWrap.find( 'select' );
                } else if ( activeTagName === 'textarea' ) {
                    $toFocus = $targetWrap.find( 'textarea' );
                } else if ( activeTagName === 'input' ) {
                    if ( activeInputType === 'checkbox' ) {
                        $toFocus = $targetWrap.find( 'input[type="checkbox"][value="' + activeInputVal + '"]' );
                        if ( ! $toFocus.length ) $toFocus = $targetWrap.find( 'input[type="checkbox"]' );
                    } else if ( activeInputType === 'range' ) {
                        $toFocus = $targetWrap.find( '.lk-slider-range' );
                    } else {
                        $toFocus = $targetWrap.find( 'input' );
                    }
                }
                if ( ! $toFocus || ! $toFocus.length ) {
                    $toFocus = $targetWrap.find( 'select, input, textarea, button' ).first();
                }
                if ( $toFocus && $toFocus.length ) {
                    try {
                        $toFocus[0].focus({ preventScroll: true });
                    } catch ( fErr ) {
                        $toFocus.trigger( 'focus' );
                    }
                    if ( activeSelStart !== null && ( activeTagName === 'input' || activeTagName === 'textarea' ) ) {
                        try {
                            if ( curSettings && curSettings[ activeCtrlKey ] !== undefined ) {
                                var modelVal = curSettings[ activeCtrlKey ];
                                if ( typeof modelVal === 'string' || typeof modelVal === 'number' ) {
                                    $toFocus[0].value = modelVal;
                                }
                            }
                            var len = ( $toFocus[0].value || '' ).length;
                            var pos = Math.min( activeSelStart, len );
                            var endPos = Math.min( activeSelEnd !== null ? activeSelEnd : activeSelStart, len );
                            $toFocus[0].setSelectionRange( pos, endPos );
                        } catch ( sErr ) {}
                    }
                }
            }
        }

        // Bind all interactive events for controls in this panel
        try {
            if ( window.LoopkitControls && typeof window.LoopkitControls.bindEvents === 'function' ) {
                window.LoopkitControls.bindEvents( $controls, function( controlName, controlValue, isResponsiveFromEvent, isInputEvent ) {
                    onControlSettingChange( el, controlName, controlValue, isResponsiveFromEvent, isInputEvent );
                }, function( newDevice ) {
                    setDevice( newDevice );
                });
            }
        } catch ( bindErr ) {
            console.error( 'Loopkit: Error binding controls events for element "' + ( el && el.id ) + '":', bindErr );
        }

        $( document ).trigger( 'lk-panel-rendered' );
    }

    /**
     * Debounced requestAnimationFrame panel re-render
     * Updates dependent controls (guarded by condition / is_visible) across the editor instantly
     * while guarding against infinite recursion and preserving focus & scroll position.
     */
    var panelRerenderRaf = null;
    function schedulePanelRerender() {
        if ( state.isRerenderingPanel ) return;
        if ( panelRerenderRaf ) {
            cancelAnimationFrame( panelRerenderRaf );
        }
        panelRerenderRaf = requestAnimationFrame( function() {
            panelRerenderRaf = null;
            if ( state.isRerenderingPanel ) return;
            state.isRerenderingPanel = true;
            try {
                renderPanelForSelected();
            } finally {
                state.isRerenderingPanel = false;
            }
        });
    }

    /**
     * Handle control setting changes from user interaction
     */
    function onControlSettingChange( el, controlName, controlValue, isResponsiveFromEvent, isInputEvent ) {
        el.settings = el.settings || {};

        var storeKey = controlName;
        if ( isResponsiveFromEvent && state.device !== 'desktop' ) {
            storeKey = controlName + '_' + state.device;
        }

        if ( el.elType === 'global' ) {
            el.overrides = el.overrides || {};
            el.overrides.settings = el.overrides.settings || {};
            el.overrides.settings[ storeKey ] = controlValue;
        }
        el.settings[ storeKey ] = controlValue;
        markDirty();

        // Initialize sticky dependent defaults when sticky is enabled
        if ( controlName === 'sticky' && controlValue !== 'none' ) {
            if ( ! el.settings.sticky_on || ! el.settings.sticky_on.length ) {
                el.settings.sticky_on = [ 'desktop', 'tablet', 'mobile' ];
            }
            if ( el.settings.sticky_offset === undefined || el.settings.sticky_offset === '' ) {
                el.settings.sticky_offset = 0;
            }
            if ( el.settings.sticky_effects_offset === undefined || el.settings.sticky_effects_offset === '' ) {
                el.settings.sticky_effects_offset = 0;
            }
        }

        // Live update title in canvas if changed
        if ( controlName === 'title' ) {
            sendToCanvas( 'update-element-title', { elementId: el.id, title: controlValue } );
        }

        // Live preview entrance animation in canvas when chosen
        if ( controlName === 'entrance_animation' || controlName === 'entrance_animation_tablet' || controlName === 'entrance_animation_mobile' ) {
            sendToCanvas( 'preview-animation', {
                elementId: el.id,
                animation: controlValue,
                duration: el.settings.animation_duration || 'normal',
                delay: el.settings.animation_delay || 0
            } );
        }

        // Synchronously update dependent control conditions in place immediately
        if ( window.LoopkitControls && typeof window.LoopkitControls.updateConditions === 'function' ) {
            window.LoopkitControls.updateConditions( $( '#lk-panel-controls' ), el.settings, state.device );
        }

        // Global debounced panel re-render on change (BUG 1 Fix)
        // Re-renders the current panel in the same frame so all dependent controls show/hide instantly.
        // Never re-renders on mid-drag input events (sliders, typing) to avoid breaking drag interactions.
        if ( ! isInputEvent && ! state.isRerenderingPanel ) {
            var curActive = document.activeElement;
            var isTyping = curActive && ( curActive.tagName === 'INPUT' || curActive.tagName === 'TEXTAREA' ) && ( curActive.type === 'text' || curActive.type === 'textarea' || curActive.type === 'number' || curActive.type === 'search' || ! curActive.type );
            if ( ! isTyping ) {
                schedulePanelRerender();
            }
        }

        // Live update canvas elements if custom attributes, shape divider, or background overlay changed
        var isWidgetContent = ( el.elType === 'widget' && ( state.activeTab === 'layout' || state.activeTab === 'content' ) );
        var isMarkupControl = (
            isWidgetContent ||
            controlName === 'alignment' ||
            controlName.indexOf( 'alignment' ) === 0 ||
            controlName === 'align' ||
            controlName.indexOf( 'align' ) === 0 ||
            controlName === 'custom_attributes' ||
            controlName.indexOf( 'shape_divider_' ) !== -1 ||
            controlName.indexOf( 'background_overlay_' ) !== -1 ||
            controlName === 'tag' ||
            controlName === 'link' ||
            controlName === 'editor' ||
            controlName === 'drop_cap' ||
            controlName === 'columns' ||
            controlName === 'columns_gap' ||
            controlName === 'image' ||
            controlName === 'image_size' ||
            controlName === 'caption_source' ||
            controlName === 'caption' ||
            controlName === 'link_to' ||
            controlName === 'open_lightbox' ||
            controlName === 'text' ||
            controlName === 'size' ||
            controlName === 'selected_icon' ||
            controlName === 'icon_align' ||
            controlName === 'icon_indent' ||
            controlName === 'button_css_id' ||
            controlName === 'html_tag' ||
            controlName === 'space' ||
            controlName === 'style' ||
            controlName === 'add_element' ||
            controlName === 'video_type' ||
            controlName === 'youtube_url' ||
            controlName === 'vimeo_url' ||
            controlName === 'dailymotion_url' ||
            controlName === 'hosted_url' ||
            controlName === 'title_text' ||
            controlName === 'description_text' ||
            controlName === 'position' ||
            controlName === 'title_size' ||
            controlName === 'view' ||
            controlName === 'shape' ||
            controlName === 'icon_list' ||
            controlName === 'social_icon_list' ||
            controlName === 'html' ||
            controlName === 'shortcode' ||
            controlName === 'sticky' ||
            controlName.indexOf( 'sticky' ) === 0 ||
            controlName === 'stay_in_container'
        );
        if ( isMarkupControl ) {
            sendToCanvas( 'update-elements', { elements: state.elements, selectedId: el.id, silent: true } );
        }

        // Live update sticky badges in canvas handle
        if ( controlName === 'sticky' || controlName.indexOf( 'sticky' ) === 0 ) {
            sendToCanvas( 'update-sticky-badges', {} );
        }


        // Re-generate and push dynamic CSS
        sendDynamicCssToCanvas();

        // Push history debounced
        clearTimeout( state.historyDebounce );
        state.historyDebounce = setTimeout( function() {
            var actionTitle = getElementTitle( el );
            var isOverride = ( el.elType === 'global' );
            pushHistory({
                title: actionTitle,
                action: isOverride ? 'Override' : 'Edited',
                sub: formatControlLabel( controlName )
            });
        }, 600 );
    }

    /* =========================================================
       DEVICE & RESPONSIVE SWITCHER
       ========================================================= */
    function applyCanvasScale() {
        var scaleVal = $( '#lk-rbar-scale' ).val();
        var $frame = $( '#lk-canvas-iframe' );
        if ( ! scaleVal || scaleVal === '100' || state.device === 'desktop' ) {
            $frame.css({
                'transform': '',
                'transform-origin': ''
            });
        } else {
            var factor = parseFloat( scaleVal ) / 100;
            $frame.css({
                'transform': 'scale(' + factor + ')',
                'transform-origin': 'top center'
            });
        }
    }

    function setDevice( device ) {
        state.device = device;

        // Top bar buttons (the ONLY place to switch device)
        $( '#lk-responsive-switcher .lk-device-btn' ).removeClass( 'lk-device-active' );
        $( '#lk-responsive-switcher .lk-device-btn[data-device="' + device + '"]' ).addClass( 'lk-device-active' );

        var $rBar = $( '#lk-responsive-bar' );
        var $frame = $( '#lk-canvas-iframe' );
        var $frameWrap = $( '#lk-canvas-frame-wrap' );
        var $widthInput = $( '#lk-rbar-width' );
        var $heightInput = $( '#lk-rbar-height' );

        $frameWrap.removeClass( 'lk-device-tablet lk-device-mobile' );

        if ( device === 'desktop' ) {
            $rBar.hide();
            $frame.css({
                'width': '100%',
                'height': '',
                'transform': '',
                'transform-origin': ''
            });
            $( '#lk-rbar-scale' ).val( '100' );
            $heightInput.val( '' );
        } else if ( device === 'tablet' ) {
            $rBar.show();
            $frameWrap.addClass( 'lk-device-tablet' );
            var tabletW = ( LK.breakpoints && LK.breakpoints.tablet ) ? LK.breakpoints.tablet : 1024;
            $widthInput.val( tabletW );
            $frame.css({
                'width': tabletW + 'px',
                'height': $heightInput.val() ? ( $heightInput.val() + 'px' ) : ''
            });
            applyCanvasScale();
        } else if ( device === 'mobile' ) {
            $rBar.show();
            $frameWrap.addClass( 'lk-device-mobile' );
            var mobileW = 375; // Standard mobile phone width
            $widthInput.val( mobileW );
            $frame.css({
                'width': mobileW + 'px',
                'height': $heightInput.val() ? ( $heightInput.val() + 'px' ) : ''
            });
            applyCanvasScale();
        }

        // Re-render control panel for the new device
        if ( state.selectedId ) {
            renderPanelForSelected();
        }
    }

    // Only top bar switcher changes device
    $( document ).on( 'click', '#lk-responsive-switcher .lk-device-btn', function() {
        var device = $( this ).data( 'device' );
        if ( device ) {
            setDevice( device );
        }
    });

    // Responsive bar controls: Width field (resizes canvas live)
    $( document ).on( 'input change', '#lk-rbar-width', function() {
        var val = parseInt( $( this ).val(), 10 );
        if ( ! isNaN( val ) && val >= 200 && val <= 3840 ) {
            $( '#lk-canvas-iframe' ).css( 'width', val + 'px' );
        }
    });

    // Responsive bar controls: Height field
    $( document ).on( 'input change', '#lk-rbar-height', function() {
        var val = parseInt( $( this ).val(), 10 );
        if ( ! isNaN( val ) && val >= 100 ) {
            $( '#lk-canvas-iframe' ).css( 'height', val + 'px' );
        } else {
            $( '#lk-canvas-iframe' ).css( 'height', '' );
        }
    });

    // Responsive bar controls: Scale dropdown
    $( document ).on( 'change', '#lk-rbar-scale', function() {
        applyCanvasScale();
    });

    // Responsive bar controls: Gear icon (opens Breakpoint Settings modal)
    $( document ).on( 'click', '#lk-rbar-settings', function( e ) {
        e.preventDefault();
        $( '#lk-breakpoints-modal' ).fadeIn( 150 );
    });

    // Responsive bar controls: X icon (returns to Desktop)
    $( document ).on( 'click', '#lk-rbar-close', function( e ) {
        e.preventDefault();
        setDevice( 'desktop' );
    });

    /* =========================================================
       TOP BAR ACTIONS & DROPDOWNS
       ========================================================= */
    function closeAllDropdowns() {
        $( '.lk-dropdown' ).removeClass( 'lk-dropdown-open' );
    }

    $( document ).on( 'click', function( e ) {
        if ( ! $( e.target ).closest( '.lk-dropdown, .lk-topbar-btn, .lk-topbar-title-btn, .lk-publish-arrow' ).length ) {
            closeAllDropdowns();
        }
    });

    $( '#lk-menu-toggle' ).on( 'click', function( e ) {
        e.stopPropagation();
        var $menu = $( '#lk-main-menu' );
        var isOpen = $menu.hasClass( 'lk-dropdown-open' );
        closeAllDropdowns();
        $menu.toggleClass( 'lk-dropdown-open', ! isOpen );
    });

    $( '#lk-title-toggle' ).on( 'click', function( e ) {
        e.stopPropagation();
        var $menu = $( '#lk-title-menu' );
        var isOpen = $menu.hasClass( 'lk-dropdown-open' );
        closeAllDropdowns();
        $menu.toggleClass( 'lk-dropdown-open', ! isOpen );
    });

    $( '#lk-publish-arrow' ).on( 'click', function( e ) {
        e.stopPropagation();
        var $menu = $( '#lk-publish-menu' );
        var isOpen = $menu.hasClass( 'lk-dropdown-open' );
        closeAllDropdowns();
        $menu.toggleClass( 'lk-dropdown-open', ! isOpen );
    });

    $( '#lk-add-element-toggle' ).on( 'click', function() {
        showElementsPanel();
    });

    $( '#lk-page-settings-btn, #lk-title-page-settings' ).on( 'click', function( e ) {
        e.preventDefault();
        closeAllDropdowns();
        showSettingsPanel();
    });

    $( '#lk-panel-back-to-elements' ).on( 'click', function() {
        if ( state.activePanel === 'site_settings' ) {
            confirmSiteSettingsExit( function() {
                showElementsPanel();
            });
        } else {
            showElementsPanel();
        }
    });

    $( '.lk-tab-btn' ).on( 'click', function() {
        var tab = $( this ).data( 'tab' );
        state.activeTab = tab;
        renderPanelForSelected();
    });

    $( '#lk-history-toggle' ).on( 'click', function() {
        toggleHistoryPanel();
    });

    $( '#lk-navigator-toggle' ).on( 'click', function( e ) {
        var forceReset = !! ( e && e.shiftKey );
        toggleStructurePanel( forceReset );
    });

    $( '#lk-publish-btn' ).on( 'click', function() {
        var isHF = LK.isTemplate && [ 'header', 'footer', 'single', 'archive', 'popup' ].indexOf( LK.templateType ) !== -1;
        if ( isHF && ( ! state.conditions || state.conditions.length === 0 ) ) {
            openDisplayConditionsModal( function() {
                LK.postStatus = 'publish';
                if ( state.pageSettings ) state.pageSettings.post_status = 'publish';
                saveDocument( 'publish' );
            }, function() {
                LK.postStatus = 'draft';
                if ( state.pageSettings ) state.pageSettings.post_status = 'draft';
                $( '#lk-publish-btn' ).text( LK.strings.saveDraft || 'Save Draft' );
            } );
            return;
        }
        saveDocument( LK.postStatus === 'publish' ? 'publish' : 'draft' );
    });

    $( '#lk-display-conditions-btn' ).on( 'click', function() {
        closeAllDropdowns();
        openDisplayConditionsModal( function() {
            saveDocument( LK.postStatus === 'publish' ? 'publish' : 'draft' );
        } );
    });

    $( '#lk-save-draft' ).on( 'click', function() {
        closeAllDropdowns();
        saveDocument( 'draft' );
    });

    $( '#lk-preview-btn' ).on( 'click', function() {
        if ( LK.previewUrl ) {
            window.open( LK.previewUrl, '_blank' );
        }
    });

    $( '#lk-menu-shortcuts' ).on( 'click', function() {
        closeAllDropdowns();
        $( '#lk-shortcuts-modal' ).show();
    });

    $( '#lk-menu-site-settings' ).on( 'click', function() {
        closeAllDropdowns();
        showSiteSettingsPanel();
    });

    $( '#lk-menu-user-prefs' ).on( 'click', function() {
        closeAllDropdowns();
        showUserPreferencesPanel();
    });

    $( '#lk-menu-finder' ).on( 'click', function() {
        closeAllDropdowns();
        openFinderModal();
    });

    $( '#lk-site-settings-save' ).on( 'click', function() {
        saveSiteSettingsViaRest();
    });

    $( document ).on( 'click', '.lk-modal-close, [data-close], .lk-modal-overlay', function( e ) {
        if ( e.target === this || $( this ).hasClass( 'lk-modal-close' ) || $( this ).is( '[data-close]' ) ) {
            var closeTarget = $( this ).data( 'close' );
            if ( closeTarget ) {
                $( '#' + closeTarget ).hide();
            } else {
                $( this ).closest( '.lk-modal-overlay' ).hide();
            }
        }
    });

    /* =========================================================
       LEFT PANEL: Search & Filtering
       ========================================================= */
    $( '#lk-widget-search' ).on( 'input', function() {
        var query = $( this ).val().toLowerCase().trim();
        $( '.lk-widget-item' ).each( function() {
            var name = $( this ).find( '.lk-widget-name' ).text().toLowerCase();
            var keywords = ( $( this ).attr( 'data-keywords' ) || '' ).toLowerCase();
            var widgetSlug = ( $( this ).data( 'widget' ) || '' ).toLowerCase();
            var matches = ( name.indexOf( query ) !== -1 || keywords.indexOf( query ) !== -1 || widgetSlug.indexOf( query ) !== -1 );
            $( this ).toggleClass( 'lk-hidden', query.length > 0 && ! matches );
        });
        $( '.lk-widget-category' ).each( function() {
            var visibleWidgets = $( this ).find( '.lk-widget-item:not(.lk-hidden)' ).length;
            $( this ).toggle( visibleWidgets > 0 );
        });
    });

    $( document ).on( 'click', '.lk-category-header', function() {
        $( this ).closest( '.lk-widget-category' ).toggleClass( 'lk-category-collapsed' );
        // Say whether it is open. A state attribute that never changes
        // states something untrue, which is worse than saying nothing.
        var $h = $( this );
        $h.attr( 'aria-expanded', $h.closest( '.lk-widget-category' ).hasClass( 'lk-category-collapsed' ) ? 'false' : 'true' );
    });

    /* =========================================================
       LEFT PANEL: Click or Drag to Add Widgets
       "Clicking a tile in the Elements panel:
        Section adds a new section at the end of the page.
        Container and Heading add inside the currently selected
        Section/Container, or into a new Section at the end of the
        page if nothing is selected."
       ========================================================= */
    $( document ).on( 'click', '.lk-widget-item', function() {
        var widget = $( this ).data( 'widget' );
        // A container was clicked while empty: put this widget inside it.
        var target = state.pendingContainerId || state.selectedId;
        state.pendingContainerId = null;
        if ( widget === 'section' ) {
            addSection();
        } else if ( widget === 'container' ) {
            addContainer( target );
        } else if ( widget === 'heading' ) {
            addHeading( target );
        } else if ( widget ) {
            if ( ! isKnownWidget( widget ) ) {
                showToast( 'That is not a widget Loopkit knows.', 'error' );
                return;
            }
            addWidget( widget, target );
        }
    });

    $( document ).on( 'dragstart', '.lk-widget-item', function( e ) {
        var widget = $( this ).data( 'widget' );
        window.loopkitDraggedWidget = widget;
        window.loopkitDraggedElement = null;
        if ( e.originalEvent && e.originalEvent.dataTransfer ) {
            e.originalEvent.dataTransfer.setData( 'text/plain', widget );
            e.originalEvent.dataTransfer.effectAllowed = 'copy';
        }
    });

    $( document ).on( 'dragend', '.lk-widget-item', function() {
        window.loopkitDraggedWidget = null;
    });

    /* =========================================================
       LEFT PANEL: Collapse / Expand & Resize
       ========================================================= */
    $( '#lk-panel-collapse' ).on( 'click', function() {
        var $panel = $( '#lk-panel' );
        state.panelCollapsed = ! state.panelCollapsed;
        $panel.toggleClass( 'lk-panel-collapsed', state.panelCollapsed );
    });

    (function() {
        var resizing = false;
        var startX, startWidth;
        var $panel = $( '#lk-panel' );
        var $handle = $( '#lk-panel-resize' );

        $handle.on( 'mousedown', function( e ) {
            e.preventDefault();
            resizing = true;
            startX = e.clientX;
            startWidth = $panel.outerWidth();
            $handle.addClass( 'lk-resizing' );
            $( 'body' ).css( 'cursor', 'col-resize' );
        });

        $( document ).on( 'mousemove', function( e ) {
            if ( ! resizing ) return;
            var newWidth = startWidth + ( e.clientX - startX );
            newWidth = Math.max( 240, Math.min( 500, newWidth ) );
            $panel.css( 'width', newWidth + 'px' );
        });

        $( document ).on( 'mouseup', function() {
            if ( resizing ) {
                resizing = false;
                $handle.removeClass( 'lk-resizing' );
                $( 'body' ).css( 'cursor', '' );
            }
        });
    })();

    /* =========================================================
       CANVAS: Load iframe & Communication
       ========================================================= */
    function loadCanvas() {
        setupCanvasCommunication();

        var $iframe = $( '#lk-canvas-iframe' );
        $iframe.attr( 'src', LK.canvasUrl );

        // If the canvas frame ever navigates away (an old link, a redirect), reload the
        // canvas instead of leaving the editor showing another page. Nothing is lost:
        // the layout lives in the editor's state until you press Update.
        $iframe.on( 'load', function() {
            try {
                var loaded = this.contentWindow.location.href;
                if ( loaded && loaded.indexOf( 'loopkit_canvas' ) === -1 && loaded.indexOf( 'about:blank' ) === -1 ) {
                    showToast( 'That link opened in the preview — bringing your design back', 'info' );
                    this.src = LK.canvasUrl;
                    return;
                }
            } catch ( err ) {}
        } );

        $iframe.on( 'load', function() {
            state.canvasReady = true;
            sendToCanvas( 'update-elements', { elements: state.elements } );
            sendDynamicCssToCanvas();
            updateCanvasSiteSettingsLive();
            if ( state.userPreferences ) {
                if ( state.userPreferences.ui_theme ) {
                    applyUserTheme( state.userPreferences.ui_theme );
                }
                if ( state.userPreferences.show_edit_handles === false ) {
                    sendToCanvas( 'toggle-editing-handles', { hide: true } );
                }
            }
        });
    }

    var communicationSetup = false;
    function setupCanvasCommunication() {
        if ( communicationSetup ) return;
        communicationSetup = true;

        window.addEventListener( 'message', function( e ) {
            if ( ! e.data ) return;

            var isLoopkitCanvas = ( e.data.source === 'loopkit-canvas' );
            var validTypes = [
                'canvas-ready', 'element-selected', 'element-deselected', 'elements-updated',
                'element-deleted', 'element-duplicated', 'update-element-setting',
                'add-section', 'add-preset-section', 'add-section-before',
                'add-container', 'add-widget', 'add-widget-to-container', 'canvas-drop',
                'save-as-global', 'save-as-template', 'copy-style', 'paste-style', 'reset-style',
                'open-elements-panel',
                'copy-element', 'paste-element', 'detach-element', 'finder-shortcut'
            ];
            if ( ! isLoopkitCanvas && validTypes.indexOf( e.data.type ) === -1 ) return;

            var msg = e.data;

            switch ( msg.type ) {
                case 'canvas-ready':
                    sendToCanvas( 'update-elements', { elements: state.elements } );
                    sendDynamicCssToCanvas();
                    updateCanvasSiteSettingsLive();
                    if ( state.userPreferences ) {
                        if ( state.userPreferences.ui_theme ) {
                            applyUserTheme( state.userPreferences.ui_theme );
                        }
                        if ( state.userPreferences.show_edit_handles === false ) {
                            sendToCanvas( 'toggle-editing-handles', { hide: true } );
                        }
                    }
                    break;

                case 'element-selected':
                    state.selectedId = msg.elementId;
                    state.activeTab = state.activeTab || 'layout';
                    renderPanelForSelected();
                    break;

                case 'element-deselected':
                    showElementsPanel();
                    break;

                case 'open-elements-panel':
                    // Empty container clicked: show the widget list and drop the next widget in it.
                    state.pendingContainerId = msg.containerId || null;
                    if ( state.pendingContainerId ) {
                        state.selectedId = state.pendingContainerId;
                    }
                    showElementsPanel();
                    showToast( 'Choose a widget, or drag one into the container', 'info' );
                    break;

                case 'elements-updated':
                    state.elements = ensureSections( msg.elements );
                    markDirty();
                    pushHistory( { title: 'Element', action: 'Moved' } );
                    sendDynamicCssToCanvas();
                    break;

                case 'element-deleted':
                    deleteElement( msg.elementId );
                    break;

                case 'element-duplicated':
                    duplicateElement( msg.elementId );
                    break;

                case 'copy-element':
                    copyElement( msg.elementId );
                    break;

                case 'paste-element':
                    pasteElement( msg.elementId );
                    break;

                case 'detach-element':
                    detachElement( msg.elementId );
                    break;

                case 'save-as-global':
                    openSaveAsGlobalModal( msg.elementId );
                    break;

                case 'save-as-template':
                    openSaveAsTemplateModal( msg.elementId );
                    break;

                case 'copy-style':
                    copyElementStyle( msg.elementId );
                    break;

                case 'paste-style':
                    pasteElementStyle( msg.elementId );
                    break;

                case 'reset-style':
                    resetElementStyle( msg.elementId );
                    break;

                case 'update-element-setting':
                    var el = findElementById( state.elements, msg.elementId );
                    if ( el ) {
                        el.settings = el.settings || {};
                        el.settings[ msg.key ] = msg.value;
                        markDirty();
                        if ( msg.key === 'title' && state.selectedId === msg.elementId ) {
                            $( '#lk-panel-controls' ).find( '.lk-input-textarea[data-name="title"], .lk-input-text[data-name="title"]' ).val( msg.value );
                        }
                    }
                    break;

                case 'add-section':
                    addSection();
                    break;

                case 'add-preset-section':
                    addPresetSection( msg.preset );
                    break;

                case 'add-section-before':
                    addSection( msg.sectionId );
                    break;

                case 'add-container':
                    addContainer( msg.containerId );
                    break;

                case 'add-widget':
                    if ( msg.widgetType === 'section' ) {
                        addSection();
                    } else if ( msg.widgetType === 'container' ) {
                        addContainer();
                    } else if ( msg.widgetType === 'heading' ) {
                        addHeading();
                    } else if ( msg.widgetType ) {
                        if ( ! isKnownWidget( msg.widgetType ) ) { return; }
                        addWidget( msg.widgetType );
                    }
                    break;

                case 'add-widget-to-container':
                    if ( msg.widgetType === 'section' ) {
                        addSection();
                    } else if ( msg.widgetType === 'container' ) {
                        addContainer( msg.containerId );
                    } else if ( msg.widgetType === 'heading' ) {
                        addHeading( msg.containerId );
                    } else if ( msg.widgetType ) {
                        if ( ! isKnownWidget( msg.widgetType ) ) { return; }
                        addWidget( msg.widgetType, msg.containerId );
                    }
                    break;

                case 'canvas-drop':
                    handleCanvasDrop( msg );
                    break;
            }
        });
    }

    /**
     * Fetch data for the chosen preview post and re-render the canvas so Post widgets
     * (Post Title, Featured Image, Post Excerpt, Post Info) show that post.
     */
    function loadPreviewPost( postId ) {
        postId = parseInt( postId, 10 );
        if ( ! postId ) return;
        $.ajax({
            url: LK.restBase + 'post-preview-data?id=' + postId,
            method: 'GET',
            beforeSend: function( xhr ) { xhr.setRequestHeader( 'X-WP-Nonce', LK.restNonce ); },
        }).done( function( data ) {
            if ( data && typeof data === 'object' ) {
                LK.previewPost = data;
                LK.previewPostId = postId;
                sendToCanvas( 'update-elements', { elements: state.elements, selectedId: state.selectedId } );
            }
        }).fail( function() {
            if ( typeof showToast === 'function' ) showToast( 'Could not load the preview post', 'error' );
        });
    }

    /**
     * Fetch data for the chosen preview archive and re-render the canvas (Archive widgets).
     */
    function loadPreviewArchive( key ) {
        if ( ! key ) return;
        $.ajax({
            url: LK.restBase + 'archive-preview-data?key=' + encodeURIComponent( key ),
            method: 'GET',
            beforeSend: function( xhr ) { xhr.setRequestHeader( 'X-WP-Nonce', LK.restNonce ); },
        }).done( function( data ) {
            if ( data && typeof data === 'object' ) {
                LK.previewArchive = data;
                LK.previewArchiveKey = key;
                sendToCanvas( 'update-elements', { elements: state.elements, selectedId: state.selectedId } );
            }
        }).fail( function() {
            if ( typeof showToast === 'function' ) showToast( 'Could not load the preview archive', 'error' );
        });
    }

    function sendToCanvas( type, data ) {
        var iframe = document.getElementById( 'lk-canvas-iframe' );
        if ( iframe && iframe.contentWindow ) {
            var payload = $.extend( { source: 'loopkit-editor', type: type }, data );
            if ( type === 'update-elements' && payload.elements ) {
                payload.elements = resolveGlobals( payload.elements );
            }
            iframe.contentWindow.postMessage( payload, '*' );
        }
    }

    /* =========================================================
       DISPLAY CONDITIONS MODAL (Header & Footer Theme Builder)
       ========================================================= */
    var conditionsCallbackSave = null;
    var conditionsCallbackCancel = null;

    function createEditorConditionRow( data ) {
        data = data || { type: 'include', name: 'general', sub: 'all', id: '' };
        var type = data.type || 'include';
        var name = data.name || 'general';
        var sub  = data.sub || 'all';
        var id   = data.id || data.item || '';

        var $row = $( '<div class="lk-cond-row"></div>' );

        // Type select
        var $typeSelect = $( '<select class="lk-cond-select lk-cond-type"></select>' );
        $typeSelect.append( '<option value="include">INCLUDE</option>' );
        $typeSelect.append( '<option value="exclude">EXCLUDE</option>' );
        $typeSelect.val( type );
        $typeSelect.addClass( type === 'exclude' ? 'lk-type-exclude' : 'lk-type-include' );
        $typeSelect.on( 'change', function() {
            var v = $( this ).val();
            $( this ).removeClass( 'lk-type-include lk-type-exclude' )
                   .addClass( v === 'exclude' ? 'lk-type-exclude' : 'lk-type-include' );
        } );

        // Name select
        var $nameSelect = $( '<select class="lk-cond-select lk-cond-name"></select>' );
        $nameSelect.append( '<option value="general">Entire Site</option>' );
        $nameSelect.append( '<option value="singular">Singular</option>' );
        $nameSelect.append( '<option value="archive">Archives</option>' );
        $nameSelect.val( name );

        // Sub select
        var $subSelect = $( '<select class="lk-cond-select lk-cond-sub"></select>' );

        // Item select
        var $itemSelect = $( '<select class="lk-cond-select lk-cond-item"></select>' );

        function updateSubOptions() {
            var n = $nameSelect.val();
            $subSelect.empty();
            if ( n === 'general' ) {
                $subSelect.hide();
                $itemSelect.hide();
            } else if ( n === 'singular' ) {
                $subSelect.show();
                $subSelect.append( '<option value="all">All Singular</option>' );
                ( LK.conditionPostTypes || [] ).forEach( function( pt ) {
                    if ( pt.slug === 'post' || pt.slug === 'page' ) return; // added below with their own labels
                    $subSelect.append( '<option value="' + pt.slug + '">' + pt.label + '</option>' );
                } );
                $subSelect.append( '<option value="front_page">Front Page</option>' );
                $subSelect.append( '<option value="post">Posts</option>' );
                $subSelect.append( '<option value="page">Pages</option>' );
                $subSelect.append( '<option value="404">404 Page</option>' );
                if ( sub ) $subSelect.val( sub );
                updateItemOptions();
            } else if ( n === 'archive' ) {
                $subSelect.show();
                $subSelect.append( '<option value="all">All Archives</option>' );
                ( LK.conditionPostTypes || [] ).forEach( function( pt ) {
                    if ( pt.slug === 'post' || pt.slug === 'page' ) return;
                    $subSelect.append( '<option value="' + pt.slug + '">' + pt.label + ' Archive</option>' );
                } );
                ( LK.conditionTaxonomies || [] ).forEach( function( tx ) {
                    if ( tx.slug === 'category' || tx.slug === 'post_tag' ) return;
                    $subSelect.append( '<option value="' + tx.slug + '">' + tx.label + '</option>' );
                } );
                $subSelect.append( '<option value="posts_archive">Posts Archive</option>' );
                $subSelect.append( '<option value="category">Categories</option>' );
                $subSelect.append( '<option value="post_tag">Tags</option>' );
                $subSelect.append( '<option value="author">Author</option>' );
                $subSelect.append( '<option value="date">Date</option>' );
                $subSelect.append( '<option value="search">Search Results</option>' );
                if ( sub ) $subSelect.val( sub );
                updateItemOptions();
            }
        }

        function updateItemOptions() {
            var s = $subSelect.val();
            if ( s === 'page' || s === 'post' || s === 'category' || s === 'post_tag' || s === 'author' ) {
                $itemSelect.show().empty().append( '<option value="">All</option>' );
                $.get( LK.ajaxUrl, {
                    action: 'loopkit_search_condition_items',
                    nonce: LK.templatesNonce,
                    sub: s,
                    q: ''
                }, function( res ) {
                    if ( res.success && res.data && res.data.results ) {
                        res.data.results.forEach( function( item ) {
                            var $opt = $( '<option></option>' ).val( item.id ).text( item.text );
                            if ( id && parseInt( id, 10 ) === parseInt( item.id, 10 ) ) {
                                $opt.prop( 'selected', true );
                            }
                            $itemSelect.append( $opt );
                        } );
                        if ( id ) {
                            $itemSelect.val( id );
                        }
                    }
                } );
            } else {
                $itemSelect.hide().empty();
            }
        }

        $nameSelect.on( 'change', function() {
            sub = 'all';
            id = '';
            updateSubOptions();
        } );

        $subSelect.on( 'change', function() {
            id = '';
            updateItemOptions();
        } );

        updateSubOptions();

        // Remove button
        var $removeBtn = $( '<button type="button" class="lk-cond-remove-btn" title="Remove condition">&times;</button>' );
        $removeBtn.on( 'click', function() {
            $row.remove();
            if ( $( '#lk-conditions-list' ).children().length === 0 ) {
                $( '#lk-conditions-list' ).append( createEditorConditionRow( { type: 'include', name: 'general' } ) );
            }
        } );

        $row.append( $typeSelect ).append( $nameSelect ).append( $subSelect ).append( $itemSelect ).append( $removeBtn );
        return $row;
    }

    function openDisplayConditionsModal( onSave, onCancel ) {
        conditionsCallbackSave = onSave || null;
        conditionsCallbackCancel = onCancel || null;

        var $modal = $( '#lk-conditions-modal' );
        var $list = $( '#lk-conditions-list' );
        var $notice = $( '#lk-conditions-conflict-notice' );

        $list.empty();
        $notice.hide().empty();

        var conditions = ( state.conditions && state.conditions.length > 0 )
            ? state.conditions
            : [ { type: 'include', name: 'general' } ];

        conditions.forEach( function( cond ) {
            $list.append( createEditorConditionRow( cond ) );
        } );

        $modal.show();

        // Check conflicts via AJAX
        if ( LK.ajaxUrl && LK.templatesNonce ) {
            $.get( LK.ajaxUrl, {
                action: 'loopkit_get_conditions',
                nonce: LK.templatesNonce,
                template_id: LK.postId
            } ).done( function( res ) {
                if ( res.success && res.data && res.data.conflicts && res.data.conflicts.length > 0 ) {
                    var noticeHtml = '';
                    res.data.conflicts.forEach( function( c ) {
                        noticeHtml += '<p><span class="dashicons dashicons-warning"></span> ' + c + '</p>';
                    } );
                    $notice.html( noticeHtml ).show();
                }
            } );
        }
    }

    function getEditorConditionsFromModal() {
        var conditions = [];
        $( '#lk-conditions-list' ).find( '.lk-cond-row' ).each( function() {
            var type = $( this ).find( '.lk-cond-type' ).val();
            var name = $( this ).find( '.lk-cond-name' ).val();
            var sub  = $( this ).find( '.lk-cond-sub' ).val();
            var item = $( this ).find( '.lk-cond-item' ).val();

            var row = { type: type, name: name };
            if ( name !== 'general' ) {
                row.sub = sub || 'all';
                if ( item ) {
                    row.id   = parseInt( item, 10 );
                    row.item = parseInt( item, 10 );
                }
            }
            conditions.push( row );
        } );
        if ( conditions.length === 0 ) {
            conditions = [ { type: 'include', name: 'general' } ];
        }
        return conditions;
    }

    $( '#lk-add-condition-btn' ).on( 'click', function( e ) {
        e.preventDefault();
        $( '#lk-conditions-list' ).append( createEditorConditionRow( { type: 'include', name: 'general' } ) );
    } );

    $( '#lk-conditions-save-btn' ).on( 'click', function() {
        state.conditions = getEditorConditionsFromModal();
        markDirty();
        $( '#lk-conditions-modal' ).hide();
        if ( typeof conditionsCallbackSave === 'function' ) {
            var cb = conditionsCallbackSave;
            conditionsCallbackSave = null;
            conditionsCallbackCancel = null;
            cb();
        }
    } );

    $( '#lk-conditions-cancel-btn' ).on( 'click', function() {
        $( '#lk-conditions-modal' ).hide();
        if ( typeof conditionsCallbackCancel === 'function' ) {
            var cb = conditionsCallbackCancel;
            conditionsCallbackSave = null;
            conditionsCallbackCancel = null;
            cb();
        }
    } );

    $( document ).on( 'click', '#lk-conditions-modal .lk-modal-close, #lk-conditions-modal', function( e ) {
        if ( e.target === this || $( e.target ).closest( '.lk-modal-close' ).length ) {
            $( '#lk-conditions-modal' ).hide();
            if ( typeof conditionsCallbackCancel === 'function' ) {
                var cb = conditionsCallbackCancel;
                conditionsCallbackSave = null;
                conditionsCallbackCancel = null;
                cb();
            }
        }
    } );

    /* =========================================================
       SAVING: REST API (Section 19)
       ========================================================= */
    function saveDocument( status ) {
        if ( state.saving ) return;
        state.saving = true;

        var $btn = $( '#lk-publish-btn' );
        var origText = $btn.text();
        $btn.addClass( 'lk-btn-saving' ).text( LK.strings.saving || 'Saving...' );

        var postStatus = ( state.pageSettings && state.pageSettings.post_status )
            ? state.pageSettings.post_status
            : ( status || ( LK.postStatus === 'publish' ? 'publish' : 'draft' ) );
        var postTitle = ( state.pageSettings && state.pageSettings.post_title )
            ? state.pageSettings.post_title
            : LK.postTitle;

        var isHF = LK.isTemplate && [ 'header', 'footer', 'single', 'archive', 'popup' ].indexOf( LK.templateType ) !== -1;
        if ( isHF && postStatus === 'publish' && ( ! state.conditions || state.conditions.length === 0 ) ) {
            state.saving = false;
            $btn.removeClass( 'lk-btn-saving' ).text( origText );
            openDisplayConditionsModal( function() {
                LK.postStatus = 'publish';
                if ( state.pageSettings ) state.pageSettings.post_status = 'publish';
                saveDocument( 'publish' );
            }, function() {
                LK.postStatus = 'draft';
                if ( state.pageSettings ) state.pageSettings.post_status = 'draft';
                $btn.text( LK.strings.saveDraft || 'Save Draft' );
                if ( $( '#lk-panel-page-settings' ).is( ':visible' ) ) {
                    renderPageSettings();
                }
            } );
            return;
        }

        var payload = {
            status: postStatus,
            title: postTitle,
            elements: state.elements,
            pageSettings: collectPageSettingsForSave(),
            conditions: state.conditions || [],
        };

        var saveUrl = LK.restBase + 'document/' + LK.postId;

        $.ajax({
            url: saveUrl,
            method: 'POST',
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': LK.restNonce,
            },
            data: JSON.stringify( payload ),
        })
        .done( function( res ) {
            if ( res && res.success ) {
                markClean();
                if ( payload.title ) {
                    LK.postTitle = payload.title;
                    $( '#lk-topbar-title' ).text( payload.title );
                }
                if ( payload.status ) {
                    LK.postStatus = payload.status;
                }
                if ( LK.postStatus === 'publish' ) {
                    $btn.text( LK.strings.update || 'Update' );
                } else if ( LK.postStatus === 'draft' ) {
                    $btn.text( LK.strings.saveDraft || 'Save Draft' );
                } else {
                    $btn.text( origText );
                }
                showToast( LK.strings.saved || 'Saved', 'success' );
            } else {
                var errorMsg = ( res && res.message ) ? res.message : 'Save failed. Please try again.';
                showToast( errorMsg, 'error' );
                $btn.text( origText );
            }
        })
        .fail( function( jqXHR ) {
            var errorMsg = 'Save failed. Please try again.';
            if ( jqXHR.responseJSON ) {
                if ( jqXHR.responseJSON.message ) {
                    errorMsg = jqXHR.responseJSON.message;
                } else if ( jqXHR.responseJSON.code ) {
                    errorMsg = jqXHR.responseJSON.code;
                }
            } else if ( jqXHR.statusText && jqXHR.statusText !== 'error' ) {
                errorMsg = 'Error ' + jqXHR.status + ': ' + jqXHR.statusText;
            }
            showToast( errorMsg, 'error' );
            $btn.text( origText );
        })
        .always( function() {
            state.saving = false;
            $btn.removeClass( 'lk-btn-saving' );
        });
    }

    /* =========================================================
       STRUCTURE (NAVIGATOR) PANEL
       ========================================================= */
    function buildStructureTreeNode( el, depth ) {
        if ( ! el || ! el.id ) return '';

        var id = el.id;
        var elType = el.elType || '';
        var isGlobal = ( elType === 'global' );
        var title = getElementTitle( el );
        var children = el.elements || [];

        if ( isGlobal ) {
            var g = getGlobalData( el.templateId );
            if ( g && g.master && g.master.elements ) {
                children = g.master.elements;
            }
        }

        var hasChildren = ( children && children.length > 0 );
        var isExpanded = ( state.treeExpanded[ id ] !== undefined ) ? state.treeExpanded[ id ] : true;
        var isHidden = !! ( state.hiddenElementIds && state.hiddenElementIds[ id ] );
        var isSelected = ( state.selectedId === id );

        var iconClass = 'dashicons-admin-generic';
        if ( isGlobal ) {
            iconClass = 'dashicons-admin-site-alt3 lk-tree-icon--global';
        } else if ( elType === 'section' ) {
            iconClass = 'dashicons-format-aside';
        } else if ( elType === 'container' ) {
            iconClass = 'dashicons-layout';
        } else if ( elType === 'widget' ) {
            if ( el.widgetType === 'heading' ) iconClass = 'dashicons-heading';
            else if ( el.widgetType === 'text-editor' ) iconClass = 'dashicons-editor-paragraph';
            else if ( el.widgetType === 'image' ) iconClass = 'dashicons-format-image';
            else if ( el.widgetType === 'button' ) iconClass = 'dashicons-button';
            else if ( el.widgetType === 'icon' ) iconClass = 'dashicons-star-filled';
            else if ( el.widgetType === 'spacer' ) iconClass = 'dashicons-minus';
            else if ( el.widgetType === 'divider' ) iconClass = 'dashicons-ellipsis';
            else if ( el.widgetType === 'video' ) iconClass = 'dashicons-video-alt3';
            else if ( el.widgetType === 'image-box' ) iconClass = 'dashicons-format-gallery';
            else if ( el.widgetType === 'icon-box' ) iconClass = 'dashicons-layout';
            else if ( el.widgetType === 'icon-list' ) iconClass = 'dashicons-editor-ul';
            else if ( el.widgetType === 'social-icons' ) iconClass = 'dashicons-share';
            else if ( el.widgetType === 'html' ) iconClass = 'dashicons-editor-code';
            else if ( el.widgetType === 'shortcode' ) iconClass = 'dashicons-shortcode';
            else if ( el.widgetType === 'site-logo' ) iconClass = 'dashicons-format-image';
            else if ( el.widgetType === 'site-title' ) iconClass = 'dashicons-admin-site';
            else if ( el.widgetType === 'nav-menu' ) iconClass = 'dashicons-menu-alt3';
            else if ( el.widgetType === 'search-form' ) iconClass = 'dashicons-search';
            else if ( el.widgetType === 'post-title' ) iconClass = 'dashicons-editor-textcolor';
            else if ( el.widgetType === 'post-featured-image' ) iconClass = 'dashicons-format-image';
            else if ( el.widgetType === 'post-excerpt' ) iconClass = 'dashicons-text';
            else if ( el.widgetType === 'post-info' ) iconClass = 'dashicons-info-outline';
            else if ( el.widgetType === 'post-content' ) iconClass = 'dashicons-media-document';
            else if ( el.widgetType === 'author-box' ) iconClass = 'dashicons-id';
            else if ( el.widgetType === 'post-navigation' ) iconClass = 'dashicons-leftright';
            else if ( el.widgetType === 'post-comments' ) iconClass = 'dashicons-admin-comments';
            else if ( el.widgetType === 'archive-title' ) iconClass = 'dashicons-editor-textcolor';
            else if ( el.widgetType === 'archive-description' ) iconClass = 'dashicons-text';
            else if ( el.widgetType === 'posts' ) iconClass = 'dashicons-grid-view';
            else if ( el.widgetType === 'loop-grid' ) iconClass = 'dashicons-screenoptions';
            else if ( el.widgetType === 'pagination' ) iconClass = 'dashicons-ellipsis';
            else if ( el.widgetType === 'accordion' ) iconClass = 'dashicons-list-view';
            else if ( el.widgetType === 'toggle' ) iconClass = 'dashicons-arrow-down-alt2';
            else if ( el.widgetType === 'tabs' ) iconClass = 'dashicons-index-card';
            else if ( el.widgetType === 'counter' ) iconClass = 'dashicons-performance';
            else if ( el.widgetType === 'progress-bar' ) iconClass = 'dashicons-chart-bar';
            else if ( el.widgetType === 'alert' ) iconClass = 'dashicons-warning';
            else if ( el.widgetType === 'star-rating' ) iconClass = 'dashicons-star-half';
            else if ( el.widgetType === 'testimonial' ) iconClass = 'dashicons-format-quote';
            else if ( el.widgetType === 'image-carousel' ) iconClass = 'dashicons-slides';
            else if ( el.widgetType === 'image-gallery' ) iconClass = 'dashicons-format-gallery';
            else if ( el.widgetType === 'google-maps' ) iconClass = 'dashicons-location-alt';
            else if ( el.widgetType === 'contact-form' ) iconClass = 'dashicons-feedback';
            else if ( el.widgetType === 'tooltip' ) iconClass = 'dashicons-testimonial';
            else if ( el.widgetType === 'table-of-contents' ) iconClass = 'dashicons-editor-ol';
            else iconClass = 'dashicons-admin-generic';
        }

        var rowCls = 'lk-tree-row';
        if ( isSelected ) rowCls += ' lk-tree-row--active';
        if ( isHidden ) rowCls += ' lk-tree-row--hidden';

        var titleCls = 'lk-tree-title';
        if ( isGlobal ) titleCls += ' lk-tree-title--global';

        var indent = depth * 16 + 6;

        var html = '<div class="lk-tree-item" data-id="' + id + '" data-type="' + elType + '">';
        html += '  <div class="' + rowCls + '" data-id="' + id + '" draggable="true" style="padding-left: ' + indent + 'px;">';

        if ( hasChildren ) {
            var caretCls = 'lk-tree-caret dashicons dashicons-arrow-right-alt2' + ( isExpanded ? ' lk-tree-caret--open' : '' );
            html += '    <span class="' + caretCls + '" data-toggle-id="' + id + '"></span>';
        } else {
            html += '    <span class="lk-tree-caret lk-tree-caret--placeholder"></span>';
        }

        html += '    <span class="lk-tree-icon dashicons ' + iconClass + '"></span>';
        html += '    <span class="' + titleCls + '" data-id="' + id + '" title="Double-click to rename">' + escapeHtml( title ) + '</span>';

        html += '    <div class="lk-tree-actions">';
        var eyeIcon = isHidden ? 'dashicons-hidden' : 'dashicons-visibility';
        html += '      <button type="button" class="lk-tree-action-btn lk-tree-eye-btn" data-id="' + id + '" title="Hide/Show in editor"><span class="dashicons ' + eyeIcon + '"></span></button>';
        html += '    </div>';

        html += '  </div>';

        if ( hasChildren ) {
            var childStyle = isExpanded ? '' : 'style="display:none;"';
            html += '  <div class="lk-tree-children" id="lk-tree-children-' + id + '" ' + childStyle + '>';
            for ( var c = 0; c < children.length; c++ ) {
                html += buildStructureTreeNode( children[c], depth + 1 );
            }
            html += '  </div>';
        }

        html += '</div>';
        return html;
    }

    function isDescendant( parentEl, targetId ) {
        if ( ! parentEl || ! parentEl.elements ) return false;
        for ( var i = 0; i < parentEl.elements.length; i++ ) {
            var child = parentEl.elements[i];
            if ( child.id === targetId ) return true;
            if ( isDescendant( child, targetId ) ) return true;
        }
        return false;
    }

    function findParentGlobal( elements, targetId ) {
        for ( var i = 0; i < elements.length; i++ ) {
            var el = elements[i];
            if ( el.id === targetId ) return null;
            if ( el.elType === 'global' ) {
                if ( isDescendant( el, targetId ) ) return el;
            }
            if ( el.elements ) {
                var res = findParentGlobal( el.elements, targetId );
                if ( res ) return res;
            }
        }
        return null;
    }

    function highlightInStructureTree( id ) {
        $( '.lk-tree-row' ).removeClass( 'lk-tree-row--active' );
        if ( ! id ) return;

        var $row = $( '.lk-tree-row[data-id="' + id + '"]' );
        if ( ! $row.length ) return;

        $row.addClass( 'lk-tree-row--active' );

        $row.parents( '.lk-tree-children' ).each( function() {
            var childWrapId = this.id;
            var parentId = childWrapId.replace( 'lk-tree-children-', '' );
            state.treeExpanded = state.treeExpanded || {};
            state.treeExpanded[ parentId ] = true;
            $( this ).show();
            $( '.lk-tree-caret[data-toggle-id="' + parentId + '"]' ).addClass( 'lk-tree-caret--open' );
        });

        var bodyEl = document.getElementById( 'lk-structure-body' );
        if ( bodyEl && $row[0] ) {
            var rowTop = $row[0].offsetTop;
            var bodyScroll = bodyEl.scrollTop;
            var bodyHeight = bodyEl.clientHeight;
            if ( rowTop < bodyScroll || rowTop > bodyScroll + bodyHeight - 40 ) {
                bodyEl.scrollTop = Math.max( 0, rowTop - 40 );
            }
        }
    }

    function renderStructureTree() {
        var $tree = $( '#lk-structure-tree' );
        var $empty = $( '#lk-structure-empty' );
        if ( ! $tree.length ) return;

        $tree.empty();
        if ( ! state.elements || state.elements.length === 0 ) {
            $empty.show();
            return;
        }
        $empty.hide();

        var html = '';
        for ( var i = 0; i < state.elements.length; i++ ) {
            html += buildStructureTreeNode( state.elements[i], 0 );
        }
        $tree.html( html );

        if ( state.selectedId ) {
            highlightInStructureTree( state.selectedId );
        }
    }

    function getDefaultStructurePosition() {
        var topbarH = $( '#lk-topbar' ).outerHeight() || 48;
        var defW = 320;
        var defH = 480;
        var defTop = topbarH + 16;
        var defLeft = Math.max( 8, window.innerWidth - defW - 30 );
        return {
            left:   Math.round( defLeft ),
            top:    Math.round( defTop ),
            width:  defW,
            height: defH
        };
    }

    function clampStructurePanelPosition() {
        var $panel = $( '#lk-structure-panel' );
        var panelEl = $panel[0];
        if ( ! panelEl || state.structureDocked ) return;

        var topbarH = $( '#lk-topbar' ).outerHeight() || 48;
        var minTop = topbarH + 8; // Top can never be less than topbar height plus 8px

        var curW = $panel.outerWidth() || 320;
        var curH = $panel.outerHeight() || 480;

        curW = Math.max( 240, Math.min( window.innerWidth - 16, curW ) );
        curH = Math.max( 200, Math.min( Math.max( 200, window.innerHeight - minTop - 16 ), curH ) );

        var maxTop = Math.max( minTop, ( window.innerHeight >= minTop + curH + 8 ) ? ( window.innerHeight - curH - 8 ) : ( window.innerHeight - 46 ) );
        var minLeft = 8;
        var maxLeft = Math.max( minLeft, window.innerWidth - curW - 8 );

        var rect = panelEl.getBoundingClientRect();
        var curLeft = ( panelEl.style.left ) ? parseInt( panelEl.style.left, 10 ) : rect.left;
        var curTop  = ( panelEl.style.top )  ? parseInt( panelEl.style.top, 10 )  : rect.top;

        if ( isNaN( curLeft ) || isNaN( curTop ) || ( curLeft === 0 && curTop === 0 ) || curTop < minTop ) {
            var def = getDefaultStructurePosition();
            curLeft = def.left;
            curTop = def.top;
        }

        var clampedLeft = Math.max( minLeft, Math.min( maxLeft, curLeft ) );
        var clampedTop  = Math.max( minTop,  Math.min( maxTop,  curTop ) );

        $panel.css({
            width:  curW + 'px',
            height: curH + 'px',
            left:   clampedLeft + 'px',
            top:    clampedTop + 'px',
            right:  'auto',
            bottom: 'auto'
        });
    }

    function resetStructurePanelToDefault() {
        if ( state.structureDocked ) {
            undockStructurePanel();
        }
        var def = getDefaultStructurePosition();
        var $panel = $( '#lk-structure-panel' );
        $panel.css({
            width:  def.width + 'px',
            height: def.height + 'px',
            left:   def.left + 'px',
            top:    def.top + 'px',
            right:  'auto',
            bottom: 'auto'
        });
        saveStructureSettings();
        if ( typeof showToast === 'function' ) {
            showToast( 'Structure panel position reset', 'info' );
        }
    }

    function openStructurePanel( forceReset ) {
        state.structureOpen = true;
        var $panel = $( '#lk-structure-panel' );
        if ( forceReset ) {
            resetStructurePanelToDefault();
        } else {
            clampStructurePanelPosition();
        }
        $panel.show();
        $( '#lk-navigator-toggle' ).addClass( 'lk-topbar-active' );
        renderStructureTree();
        if ( state.selectedId ) {
            highlightInStructureTree( state.selectedId );
        }
        saveStructureSettings();
    }

    function closeStructurePanel() {
        state.structureOpen = false;
        $( '#lk-structure-panel' ).hide();
        $( '#lk-navigator-toggle' ).removeClass( 'lk-topbar-active' );
        saveStructureSettings();
    }

    function toggleStructurePanel( forceReset ) {
        if ( state.structureOpen && ! forceReset ) {
            closeStructurePanel();
        } else {
            openStructurePanel( forceReset );
        }
    }

    function dockStructurePanel() {
        state.structureDocked = true;
        var $panel = $( '#lk-structure-panel' );
        $panel.addClass( 'lk-structure-docked' );
        $( '#lk-editor-main' ).addClass( 'lk-has-structure-docked' );
        $panel.css({ left: '', top: '', right: '', height: '' });
        saveStructureSettings();
    }

    function undockStructurePanel() {
        state.structureDocked = false;
        var $panel = $( '#lk-structure-panel' );
        $panel.removeClass( 'lk-structure-docked' );
        $( '#lk-editor-main' ).removeClass( 'lk-has-structure-docked' );
        $( '.lk-editor-main .lk-canvas-wrap' ).css( 'margin-right', '' );
        var def = getDefaultStructurePosition();
        $panel.css({
            width:  def.width + 'px',
            height: def.height + 'px',
            left:   def.left + 'px',
            top:    def.top + 'px',
            right:  'auto',
            bottom: 'auto'
        });
        saveStructureSettings();
    }

    function saveStructureSettings() {
        var $panel = $( '#lk-structure-panel' );
        if ( ! $panel.length ) return;

        var prevSettings = null;
        try {
            prevSettings = JSON.parse( localStorage.getItem( 'lk_structure_settings' ) );
        } catch(e) {}

        var isDocked = !! state.structureDocked;
        var isOpen = !! state.structureOpen;

        var left, top, width, height;

        if ( isDocked ) {
            left = ( prevSettings && prevSettings.left !== undefined ) ? prevSettings.left : undefined;
            top = ( prevSettings && prevSettings.top !== undefined ) ? prevSettings.top : undefined;
            width = ( prevSettings && prevSettings.width ) ? prevSettings.width : 320;
            height = ( prevSettings && prevSettings.height ) ? prevSettings.height : 480;
        } else {
            var panelEl = $panel[0];
            var rect = ( panelEl && $panel.is( ':visible' ) ) ? panelEl.getBoundingClientRect() : null;

            if ( rect && rect.width > 0 && rect.height > 0 ) {
                left = ( panelEl.style.left ) ? parseInt( panelEl.style.left, 10 ) : rect.left;
                top = ( panelEl.style.top ) ? parseInt( panelEl.style.top, 10 ) : rect.top;
                width = $panel.outerWidth() || 320;
                height = $panel.outerHeight() || 480;
            } else if ( prevSettings && prevSettings.left !== undefined && prevSettings.top !== undefined ) {
                left = prevSettings.left;
                top = prevSettings.top;
                width = prevSettings.width || 320;
                height = prevSettings.height || 480;
            } else {
                var def = getDefaultStructurePosition();
                left = def.left;
                top = def.top;
                width = def.width;
                height = def.height;
            }

            var topbarH = $( '#lk-topbar' ).outerHeight() || 48;
            var minTop = topbarH + 8;
            var maxTop = Math.max( minTop, ( window.innerHeight >= minTop + height + 8 ) ? ( window.innerHeight - height - 8 ) : ( window.innerHeight - 46 ) );
            var minLeft = 8;
            var maxLeft = Math.max( minLeft, window.innerWidth - width - 8 );

            left = Math.max( minLeft, Math.min( maxLeft, left ) );
            top = Math.max( minTop, Math.min( maxTop, top ) );
        }

        var settings = {
            isOpen:   isOpen,
            isDocked: isDocked,
            left:     Math.round( left ),
            top:      Math.round( top ),
            width:    Math.round( width ),
            height:   Math.round( height )
        };

        try {
            localStorage.setItem( 'lk_structure_settings', JSON.stringify( settings ) );
        } catch(e) {}
    }

    function initStructurePanel() {
        var $panel = $( '#lk-structure-panel' );
        var panelEl = $panel[0];
        if ( ! panelEl ) return;

        state.treeExpanded = state.treeExpanded || {};

        var saved = null;
        try {
            saved = JSON.parse( localStorage.getItem( 'lk_structure_settings' ) );
        } catch(e) {}

        var topbarH = $( '#lk-topbar' ).outerHeight() || 48;
        var minTop = topbarH + 8;

        var def = getDefaultStructurePosition();

        var isInvalid = false;
        if ( saved && ! saved.isDocked ) {
            if (
                saved.left === undefined || saved.top === undefined ||
                isNaN( saved.left ) || isNaN( saved.top ) ||
                ( saved.left === 0 && saved.top === 0 ) ||
                saved.top < minTop ||
                saved.left < -50 || saved.left > window.innerWidth ||
                saved.top > window.innerHeight
            ) {
                isInvalid = true;
            }
        }

        if ( saved && ! isInvalid ) {
            var w = ( saved.width && saved.width >= 240 ) ? saved.width : def.width;
            var h = ( saved.height && saved.height >= 200 ) ? saved.height : def.height;
            $panel.css({ width: w + 'px', height: h + 'px' });

            if ( ! saved.isDocked ) {
                var maxTop = Math.max( minTop, ( window.innerHeight >= minTop + h + 8 ) ? ( window.innerHeight - h - 8 ) : ( window.innerHeight - 46 ) );
                var minLeft = 8;
                var maxLeft = Math.max( minLeft, window.innerWidth - w - 8 );

                var clampedLeft = Math.max( minLeft, Math.min( maxLeft, saved.left ) );
                var clampedTop  = Math.max( minTop,  Math.min( maxTop,  saved.top ) );

                $panel.css({ left: clampedLeft + 'px', top: clampedTop + 'px', right: 'auto', bottom: 'auto' });
            }

            if ( saved.isDocked ) {
                dockStructurePanel();
            }
            if ( saved.isOpen ) {
                openStructurePanel();
            }
        } else {
            // Invalid or missing: open at default position
            $panel.css({
                width:  def.width + 'px',
                height: def.height + 'px',
                left:   def.left + 'px',
                top:    def.top + 'px',
                right:  'auto',
                bottom: 'auto'
            });
            if ( saved && saved.isOpen ) {
                openStructurePanel();
            }
        }

        // Immediately update localStorage with the sanitized, clamped settings so any stuck panel is fixed on reload
        saveStructureSettings();

        // Listen to window resize to maintain panel clamping
        $( window ).on( 'resize.lkStructure', function() {
            if ( ! state.structureDocked && state.structureOpen ) {
                clampStructurePanelPosition();
            }
        });

        $( '#lk-structure-close' ).on( 'click', function() {
            closeStructurePanel();
        });

        // Expand / Collapse all
        $( '#lk-structure-expand-all' ).on( 'click', function() {
            state.structureAllExpanded = ! state.structureAllExpanded;
            var expand = state.structureAllExpanded;
            state.treeExpanded = {};
            function setExpanded( list ) {
                for ( var i = 0; i < list.length; i++ ) {
                    state.treeExpanded[ list[i].id ] = expand;
                    if ( list[i].elements ) setExpanded( list[i].elements );
                }
            }
            setExpanded( state.elements );
            renderStructureTree();
        });

        // Caret toggle
        $( document ).on( 'click', '.lk-tree-caret', function( e ) {
            e.stopPropagation();
            var id = $( this ).data( 'toggle-id' );
            if ( ! id ) return;
            state.treeExpanded = state.treeExpanded || {};
            var wasExpanded = ( state.treeExpanded[ id ] !== undefined ) ? state.treeExpanded[ id ] : true;
            state.treeExpanded[ id ] = ! wasExpanded;
            $( this ).toggleClass( 'lk-tree-caret--open', ! wasExpanded );
            $( '#lk-tree-children-' + id ).toggle( ! wasExpanded );
        });

        // Row click: selects element, scrolls canvas
        $( document ).on( 'click', '.lk-tree-row', function( e ) {
            if ( $( e.target ).closest( '.lk-tree-caret, .lk-tree-eye-btn, .lk-tree-rename-input' ).length ) return;
            var id = $( this ).data( 'id' );
            if ( ! id ) return;
            state.selectedId = id;
            state.activeTab = state.activeTab || 'layout';
            renderPanelForSelected();
            sendToCanvas( 'select-element', { elementId: id } );
            sendToCanvas( 'scroll-to-element', { elementId: id } );
            highlightInStructureTree( id );
        });

        // Row hover: outlines canvas element
        $( document ).on( 'mouseenter', '.lk-tree-row', function() {
            var id = $( this ).data( 'id' );
            if ( id ) {
                sendToCanvas( 'hover-element', { elementId: id } );
            }
        });
        $( document ).on( 'mouseleave', '.lk-tree-row', function() {
            var id = $( this ).data( 'id' );
            if ( id ) {
                sendToCanvas( 'unhover-element', { elementId: id } );
            }
        });

        // Double click rename
        $( document ).on( 'dblclick', '.lk-tree-title', function( e ) {
            e.stopPropagation();
            var $title = $( this );
            var id = $title.data( 'id' );
            var el = findElementById( state.elements, id );
            if ( ! el ) return;

            var currentVal = ( el.settings && el.settings._title ) ? el.settings._title : $title.text();
            var $input = $( '<input type="text" class="lk-tree-rename-input" />' ).val( currentVal );

            $title.hide().after( $input );
            $input.focus().select();

            function finishRename( save ) {
                var newVal = $input.val().trim();
                $input.remove();
                $title.show();
                if ( save && newVal !== currentVal ) {
                    el.settings = el.settings || {};
                    el.settings._title = newVal;
                    markDirty();
                    pushHistory({
                        title: newVal || getElementTitle( el ),
                        action: 'Renamed',
                        sub: ''
                    });
                    $title.text( newVal || getElementTitle( el ) );
                    sendToCanvas( 'update-element-handle-title', { elementId: id, title: newVal } );
                    if ( state.selectedId === id ) {
                        $( '#lk-panel-header-text' ).text( newVal ? ( 'Edit ' + newVal ) : ( LK.strings.editElement || 'Edit Element' ) );
                    }
                }
            }

            $input.on( 'keydown', function( evt ) {
                if ( evt.key === 'Enter' ) {
                    evt.preventDefault();
                    finishRename( true );
                } else if ( evt.key === 'Escape' ) {
                    evt.preventDefault();
                    finishRename( false );
                }
            });

            $input.on( 'blur', function() {
                finishRename( true );
            });
        });

        // Eye button: hide / show in editor
        $( document ).on( 'click', '.lk-tree-eye-btn', function( e ) {
            e.stopPropagation();
            var id = $( this ).data( 'id' );
            if ( ! id ) return;
            state.hiddenElementIds = state.hiddenElementIds || {};
            var isHidden = ! state.hiddenElementIds[ id ];
            state.hiddenElementIds[ id ] = isHidden;

            var $row = $( '.lk-tree-row[data-id="' + id + '"]' );
            $row.toggleClass( 'lk-tree-row--hidden', isHidden );
            $( this ).find( '.dashicons' ).attr( 'class', 'dashicons ' + ( isHidden ? 'dashicons-hidden' : 'dashicons-visibility' ) );

            sendToCanvas( 'toggle-element-hidden', { elementId: id, hidden: isHidden } );
        });

        // Right-click context menu on row
        $( document ).on( 'contextmenu', '.lk-tree-row', function( e ) {
            e.preventDefault();
            e.stopPropagation();

            var id = $( this ).data( 'id' );
            var el = findElementById( state.elements, id );
            if ( ! el ) return;

            state.selectedId = id;
            renderPanelForSelected();
            sendToCanvas( 'select-element', { elementId: id } );
            highlightInStructureTree( id );

            $( '#lk-tree-context-menu' ).remove();

            var elType = el.elType;
            var isGlobal = ( elType === 'global' );
            var isSecOrCon = ( elType === 'section' || elType === 'container' );
            var label = getElementTitle( el );

            var $menu = $( '<div class="lk-context-menu" id="lk-tree-context-menu"></div>' );
            $menu.css({
                left: Math.min( e.clientX, window.innerWidth - 190 ) + 'px',
                top: Math.min( e.clientY, window.innerHeight - 220 ) + 'px'
            });

            var items = [
                '<div class="lk-context-item" data-action="edit"><span class="dashicons dashicons-edit"></span> Edit ' + escapeHtml( label ) + '</div>',
                '<div class="lk-context-item" data-action="duplicate"><span class="dashicons dashicons-admin-page"></span> Duplicate</div>',
                '<div class="lk-context-item" data-action="copy"><span class="dashicons dashicons-admin-page"></span> Copy</div>',
                '<div class="lk-context-item" data-action="paste"><span class="dashicons dashicons-clipboard"></span> Paste</div>'
            ];

            if ( isSecOrCon ) {
                items.push( '<div class="lk-context-item" data-action="save-as-template"><span class="dashicons dashicons-portfolio"></span> Save as a Template</div>' );
                if ( ! isGlobal ) {
                    items.push( '<div class="lk-context-item" data-action="save-as-global"><span class="dashicons dashicons-admin-site-alt3"></span> Save as Global</div>' );
                }
            }
            if ( isGlobal ) {
                items.push( '<div class="lk-context-item" data-action="detach"><span class="dashicons dashicons-editor-unlink"></span> Detach</div>' );
            }

            items.push( '<div class="lk-context-sep"></div>' );
            items.push( '<div class="lk-context-item" data-action="copy-style"><span class="dashicons dashicons-admin-appearance"></span> Copy Style</div>' );
            items.push( '<div class="lk-context-item" data-action="paste-style"><span class="dashicons dashicons-clipboard"></span> Paste Style</div>' );
            items.push( '<div class="lk-context-item" data-action="reset-style"><span class="dashicons dashicons-undo"></span> Reset Style</div>' );
            items.push( '<div class="lk-context-sep"></div>' );
            items.push( '<div class="lk-context-item lk-context-item-danger" data-action="delete"><span class="dashicons dashicons-trash"></span> Delete</div>' );

            $menu.html( items.join( '' ) );
            $( 'body' ).append( $menu );

            $menu.on( 'click', '.lk-context-item', function( evt ) {
                evt.stopPropagation();
                var act = $( this ).data( 'action' );
                $menu.remove();
                switch ( act ) {
                    case 'edit':
                        selectElement( id );
                        break;
                    case 'duplicate':
                        duplicateElement( id );
                        break;
                    case 'copy':
                        copyElement( id );
                        break;
                    case 'paste':
                        pasteElement( id );
                        break;
                    case 'detach':
                        detachElement( id );
                        break;
                    case 'save-as-global':
                    case 'save-as-template':
                        openSaveAsTemplateModal( id );
                        break;
                    case 'copy-style':
                        copyElementStyle( id );
                        break;
                    case 'paste-style':
                        pasteElementStyle( id );
                        break;
                    case 'reset-style':
                        resetElementStyle( id );
                        break;
                    case 'delete':
                        deleteElement( id );
                        break;
                }
            });
        });

        $( document ).on( 'click', function( e ) {
            if ( ! $( e.target ).closest( '#lk-tree-context-menu' ).length ) {
                $( '#lk-tree-context-menu' ).remove();
            }
        });

        // Drag and drop reordering in tree
        var draggedTreeElId = null;

        $( document ).on( 'dragstart', '.lk-tree-row', function( e ) {
            var id = $( this ).data( 'id' );
            draggedTreeElId = id;
            e.originalEvent.dataTransfer.setData( 'text/plain', id );
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            $( this ).css( 'opacity', '0.4' );
        });

        $( document ).on( 'dragend', '.lk-tree-row', function() {
            $( this ).css( 'opacity', '1' );
            $( '.lk-tree-row' ).removeClass( 'lk-tree-drop--before lk-tree-drop--after lk-tree-drop--inside' );
            draggedTreeElId = null;
        });

        $( document ).on( 'dragover', '.lk-tree-row', function( e ) {
            e.preventDefault();
            e.stopPropagation();
            var targetId = $( this ).data( 'id' );
            if ( ! draggedTreeElId || targetId === draggedTreeElId ) return;

            var draggedEl = findElementById( state.elements, draggedTreeElId );
            var targetEl  = findElementById( state.elements, targetId );
            if ( ! draggedEl || ! targetEl ) return;

            if ( isDescendant( draggedEl, targetId ) ) return;

            var rect = this.getBoundingClientRect();
            var relY = e.originalEvent.clientY - rect.top;
            var h = rect.height;

            $( '.lk-tree-row' ).removeClass( 'lk-tree-drop--before lk-tree-drop--after lk-tree-drop--inside' );

            var canNest = ( targetEl.elType === 'section' || targetEl.elType === 'container' );
            if ( draggedEl.elType === 'section' ) {
                canNest = false;
            }

            if ( canNest && relY > h * 0.25 && relY < h * 0.75 ) {
                $( this ).addClass( 'lk-tree-drop--inside' );
            } else if ( relY < h * 0.5 ) {
                if ( draggedEl.elType === 'section' ) {
                    if ( targetEl.elType === 'section' && state.elements.indexOf( targetEl ) !== -1 ) {
                        $( this ).addClass( 'lk-tree-drop--before' );
                    }
                } else {
                    $( this ).addClass( 'lk-tree-drop--before' );
                }
            } else {
                if ( draggedEl.elType === 'section' ) {
                    if ( targetEl.elType === 'section' && state.elements.indexOf( targetEl ) !== -1 ) {
                        $( this ).addClass( 'lk-tree-drop--after' );
                    }
                } else {
                    $( this ).addClass( 'lk-tree-drop--after' );
                }
            }
        });

        $( document ).on( 'drop', '.lk-tree-row', function( e ) {
            e.preventDefault();
            e.stopPropagation();

            var targetId = $( this ).data( 'id' );
            if ( ! draggedTreeElId || targetId === draggedTreeElId ) return;

            var draggedEl = findElementById( state.elements, draggedTreeElId );
            var targetEl  = findElementById( state.elements, targetId );
            if ( ! draggedEl || ! targetEl ) return;

            if ( isDescendant( draggedEl, targetId ) ) return;

            var isInside = $( this ).hasClass( 'lk-tree-drop--inside' );
            var isBefore = $( this ).hasClass( 'lk-tree-drop--before' );
            var isAfter  = $( this ).hasClass( 'lk-tree-drop--after' );

            $( '.lk-tree-row' ).removeClass( 'lk-tree-drop--before lk-tree-drop--after lk-tree-drop--inside' );

            if ( draggedEl.elType === 'section' && isInside ) return;

            if ( ( draggedEl.elType === 'container' || draggedEl.elType === 'widget' ) ) {
                var targetParent = findParentOf( state.elements, targetId );
                if ( ! isInside && ( ! targetParent || ! targetParent.parent ) ) {
                    return;
                }
            }

            var parentGlobal = findParentGlobal( state.elements, targetId );
            if ( parentGlobal ) {
                showToast( 'Cannot alter structure inside a global template', 'error' );
                return;
            }

            var removed = removeElementById( state.elements, draggedTreeElId );
            if ( ! removed ) return;

            if ( isInside ) {
                targetEl.elements = targetEl.elements || [];
                targetEl.elements.push( removed );
            } else {
                var pInfo = findParentOf( state.elements, targetId );
                if ( pInfo && pInfo.list ) {
                    var idx = pInfo.index;
                    if ( isAfter ) idx++;
                    pInfo.list.splice( idx, 0, removed );
                }
            }

            state.elements = ensureSections( state.elements );
            markDirty();
            pushHistory({
                title: getElementTitle( removed ),
                action: 'Moved',
                sub: ''
            });
            sendToCanvas( 'update-elements', { elements: state.elements, selectedId: removed.id } );
            sendDynamicCssToCanvas();
            renderStructureTree();
            highlightInStructureTree( removed.id );
            showToast( 'Element moved', 'success' );
        });

        // Header dragging
        var isDragging = false;
        var dragStartX = 0, dragStartY = 0;
        var startLeft = 0, startTop = 0;

        $( '#lk-structure-header' ).on( 'mousedown', function( e ) {
            if ( $( e.target ).closest( '.lk-structure-btn' ).length ) return;
            e.preventDefault();

            isDragging = true;
            dragStartX = e.clientX;
            dragStartY = e.clientY;

            var rect = panelEl.getBoundingClientRect();
            startLeft = ( panelEl.style.left ) ? parseInt( panelEl.style.left, 10 ) : rect.left;
            startTop  = ( panelEl.style.top )  ? parseInt( panelEl.style.top, 10 )  : rect.top;

            $( document ).on( 'mousemove.lkStructDrag', function( ev ) {
                if ( ! isDragging ) return;
                var dx = ev.clientX - dragStartX;
                var dy = ev.clientY - dragStartY;

                var topbarH = $( '#lk-topbar' ).outerHeight() || 48;
                var minTop  = topbarH + 8;

                if ( state.structureDocked ) {
                    if ( Math.abs( dx ) > 20 ) {
                        undockStructurePanel();
                        startLeft = ev.clientX - 150;
                        startTop = Math.max( minTop, ev.clientY - 20 );
                        $panel.css({ left: startLeft + 'px', top: startTop + 'px', right: 'auto', bottom: 'auto' });
                    }
                    return;
                }

                var panelW  = panelEl.offsetWidth || 320;
                var panelH  = panelEl.offsetHeight || 480;
                var maxTop  = Math.max( minTop, ( window.innerHeight >= minTop + panelH + 8 ) ? ( window.innerHeight - panelH - 8 ) : ( window.innerHeight - 46 ) );
                var minLeft = 8;
                var maxLeft = Math.max( minLeft, window.innerWidth - panelW - 8 );

                var newLeft = Math.max( minLeft, Math.min( maxLeft, startLeft + dx ) );
                var newTop  = Math.max( minTop,  Math.min( maxTop,  startTop + dy ) );

                $panel.css({ left: newLeft + 'px', top: newTop + 'px', right: 'auto', bottom: 'auto' });
            });

            $( document ).on( 'mouseup.lkStructDrag', function( ev ) {
                if ( ! isDragging ) return;
                isDragging = false;
                $( document ).off( '.lkStructDrag' );

                if ( ! state.structureDocked && ev.clientX >= window.innerWidth - 60 ) {
                    dockStructurePanel();
                } else {
                    clampStructurePanelPosition();
                    saveStructureSettings();
                }
            });
        });

        // Edge resizing
        var resizers = [
            { cls: '.lk-sr-e', edge: 'e' },
            { cls: '.lk-sr-w', edge: 'w' },
            { cls: '.lk-sr-s', edge: 's' },
            { cls: '.lk-sr-se', edge: 'se' }
        ];

        resizers.forEach( function( r ) {
            $( r.cls ).on( 'mousedown', function( e ) {
                if ( state.structureDocked && r.edge !== 'w' ) return;
                e.preventDefault();
                e.stopPropagation();

                var startX = e.clientX;
                var startY = e.clientY;
                var startW = panelEl.offsetWidth;
                var startH = panelEl.offsetHeight;
                var startL = panelEl.offsetLeft;
                var startT = panelEl.offsetTop;

                var topbarH = $( '#lk-topbar' ).outerHeight() || 48;
                var minTop  = topbarH + 8;

                $( document ).on( 'mousemove.lkStructResize', function( ev ) {
                    var dw = ev.clientX - startX;
                    var dh = ev.clientY - startY;

                    if ( r.edge === 'e' ) {
                        var maxW = window.innerWidth - startL - 8;
                        $panel.css( 'width', Math.max( 240, Math.min( maxW, startW + dw ) ) + 'px' );
                    } else if ( r.edge === 'w' ) {
                        var nw = Math.max( 240, Math.min( startL + startW - 8, startW - dw ) );
                        var nl = startL + ( startW - nw );
                        $panel.css({ width: nw + 'px', left: nl + 'px' });
                        if ( state.structureDocked ) {
                            $( '.lk-editor-main.lk-has-structure-docked .lk-canvas-wrap' ).css( 'margin-right', nw + 'px' );
                        }
                    } else if ( r.edge === 's' ) {
                        var maxH = window.innerHeight - startT - 8;
                        $panel.css( 'height', Math.max( 200, Math.min( maxH, startH + dh ) ) + 'px' );
                    } else if ( r.edge === 'se' ) {
                        var maxW = window.innerWidth - startL - 8;
                        var maxH = window.innerHeight - startT - 8;
                        $panel.css({
                            width:  Math.max( 240, Math.min( maxW, startW + dw ) ) + 'px',
                            height: Math.max( 200, Math.min( maxH, startH + dh ) ) + 'px'
                        });
                    }
                });

                $( document ).on( 'mouseup.lkStructResize', function() {
                    $( document ).off( '.lkStructResize' );
                    clampStructurePanelPosition();
                    saveStructureSettings();
                });
            });
        });
    }

    /* =========================================================
       KEYBOARD SHORTCUTS (Section 7)
       ========================================================= */
    $( document ).on( 'keydown', function( e ) {
        var ctrl = e.ctrlKey || e.metaKey;
        var activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
        var isTyping = ( activeTag === 'input' || activeTag === 'textarea' || ( document.activeElement && document.activeElement.isContentEditable ) );

        // Ctrl+S — Save
        if ( ctrl && e.key === 's' ) {
            e.preventDefault();
            if ( state.dirty ) {
                saveDocument( LK.postStatus === 'publish' ? 'publish' : 'draft' );
            }
            return;
        }

        // Ctrl+Z — Undo
        if ( ctrl && ! e.shiftKey && e.key === 'z' && ! isTyping ) {
            e.preventDefault();
            undo();
            return;
        }

        // Ctrl+Y or Ctrl+Shift+Z — Redo
        if ( ( ( ctrl && e.shiftKey && ( e.key === 'Z' || e.key === 'z' ) ) || ( ctrl && ( e.key === 'y' || e.key === 'Y' ) ) ) && ! isTyping ) {
            e.preventDefault();
            redo();
            return;
        }

        // Ctrl+P — Toggle panel
        if ( ctrl && e.key === 'p' ) {
            e.preventDefault();
            $( '#lk-panel-collapse' ).trigger( 'click' );
            return;
        }

        // Ctrl+Shift+M — Cycle responsive mode
        if ( ctrl && e.shiftKey && ( e.key === 'M' || e.key === 'm' ) ) {
            e.preventDefault();
            var devices = [ 'desktop', 'tablet', 'mobile' ];
            var idx = devices.indexOf( state.device );
            setDevice( devices[ ( idx + 1 ) % devices.length ] );
            return;
        }

        // Ctrl+D — Duplicate selected
        if ( ctrl && ( e.key === 'd' || e.key === 'D' ) ) {
            e.preventDefault();
            if ( state.selectedId ) {
                duplicateElement( state.selectedId );
            }
            return;
        }

        // Ctrl+C — Copy Element
        if ( ctrl && ! e.shiftKey && ( e.key === 'c' || e.key === 'C' ) && ! isTyping ) {
            if ( state.selectedId ) {
                e.preventDefault();
                copyElement( state.selectedId );
            }
            return;
        }

        // Ctrl+V — Paste Element
        if ( ctrl && ! e.shiftKey && ( e.key === 'v' || e.key === 'V' ) && ! isTyping ) {
            if ( copiedElementData ) {
                e.preventDefault();
                pasteElement( state.selectedId );
            }
            return;
        }

        // Ctrl+Shift+C — Copy Style
        if ( ctrl && e.shiftKey && ( e.key === 'C' || e.key === 'c' ) && ! isTyping ) {
            e.preventDefault();
            if ( state.selectedId ) {
                copyElementStyle( state.selectedId );
            }
            return;
        }

        // Ctrl+Shift+V — Paste Style
        if ( ctrl && e.shiftKey && ( e.key === 'V' || e.key === 'v' ) && ! isTyping ) {
            e.preventDefault();
            if ( state.selectedId ) {
                pasteElementStyle( state.selectedId );
            }
            return;
        }

        // Delete / Backspace — Delete selected (when not editing text)
        if ( ( e.key === 'Delete' || e.key === 'Backspace' ) && ! isTyping ) {
            if ( state.selectedId ) {
                e.preventDefault();
                deleteElement( state.selectedId );
            }
            return;
        }

        // Ctrl+H or Ctrl+Shift+H — History
        if ( ctrl && ( e.key === 'h' || e.key === 'H' ) ) {
            e.preventDefault();
            toggleHistoryPanel();
            return;
        }

        // Ctrl+K or Ctrl+I — Navigator / Structure (holding Shift resets position to default)
        if ( ctrl && ( e.key === 'k' || e.key === 'K' || e.key === 'i' || e.key === 'I' ) ) {
            e.preventDefault();
            var forceReset = !! e.shiftKey;
            toggleStructurePanel( forceReset );
            return;
        }

        // Ctrl+E — Finder
        if ( ctrl && ( e.key === 'e' || e.key === 'E' ) ) {
            e.preventDefault();
            openFinderModal();
            return;
        }

        // Ctrl+Shift+L — Templates Library
        if ( ctrl && e.shiftKey && ( e.key === 'l' || e.key === 'L' ) ) {
            e.preventDefault();
            openTemplatesLibraryModal();
            return;
        }

        // Ctrl+? or Ctrl+/ — Shortcuts modal
        if ( ctrl && ! e.shiftKey && ( e.key === '?' || e.key === '/' ) ) {
            e.preventDefault();
            $( '#lk-shortcuts-modal' ).toggle();
            return;
        }

        // Escape — close modals / deselect
        if ( e.key === 'Escape' ) {
            $( '.lk-modal-overlay:visible' ).hide();
            $( '.lk-res-dropdown' ).hide();
            closeAllDropdowns();
            showElementsPanel();
            return;
        }
    });

    /* =========================================================
       UNSAVED CHANGES WARNING
       ========================================================= */
    window.addEventListener( 'beforeunload', function( e ) {
        if ( state.dirty ) {
            e.preventDefault();
            e.returnValue = LK.strings.unsavedChanges;
            return LK.strings.unsavedChanges;
        }
    });

    /* =========================================================
       GLOBALS PANEL & SAVE AS GLOBAL MODAL
       ========================================================= */
    /**
     * Save a Section/Container as a reusable Template (Templates tab and Library modal).
     * Same modal as Save as Global, but unlinked: inserting it makes an independent copy.
     */
    function openSaveAsTemplateModal( elementId ) {
        if ( ! elementId ) return;
        var el = findElementById( state.elements, elementId );
        if ( ! el ) return;
        if ( el.elType !== 'section' && el.elType !== 'container' ) {
            showToast( 'Only Sections and Containers can be saved as a Template', 'info' );
            return;
        }

        state.savingGlobalElementId = elementId;
        state.savingAsTemplate = true;
        $( '#lk-save-global-title' ).text( el.elType === 'section' ? 'Save Section as Template' : 'Save Container as Template' );
        $( '#lk-global-name-input' ).val( '' );
        $( '#lk-save-global-error' ).hide().text( '' );
        $( '#lk-save-global-save-btn' ).prop( 'disabled', false ).find( '.lk-btn-text' ).text( 'Save' );
        $( '#lk-save-global-modal' ).show();
        setTimeout( function() { $( '#lk-global-name-input' ).focus(); }, 100 );
    }

    function openSaveAsGlobalModal( elementId ) {
        state.savingAsTemplate = false;
        if ( ! elementId ) return;
        var el = findElementById( state.elements, elementId );
        if ( ! el ) return;

        if ( el.elType === 'global' ) {
            showToast( 'Element is already a Global', 'info' );
            return;
        }

        if ( el.elType !== 'section' && el.elType !== 'container' ) {
            showToast( 'Only Sections and Containers can be saved as Global', 'info' );
            return;
        }

        state.savingGlobalElementId = elementId;
        var isSection = ( el.elType === 'section' );
        var title = isSection ? 'Save as Global Section' : 'Save as Global Container';
        $( '#lk-save-global-title' ).text( title );
        $( '#lk-global-name-input' ).val( '' );
        $( '#lk-save-global-error' ).hide().text( '' );
        $( '#lk-save-global-save-btn' ).prop( 'disabled', false ).find( '.lk-btn-text' ).text( 'Save' );
        $( '#lk-save-global-modal' ).show();
        setTimeout( function() {
            $( '#lk-global-name-input' ).focus();
        }, 100 );
    }

    function initSaveAsGlobalModal() {
        $( '#lk-save-global-cancel, #lk-save-global-modal .lk-modal-close' ).on( 'click', function() {
            $( '#lk-save-global-modal' ).hide();
            state.savingGlobalElementId = null;
        });

        $( '#lk-global-name-input' ).on( 'keydown', function( e ) {
            if ( e.key === 'Enter' ) {
                e.preventDefault();
                $( '#lk-save-global-save-btn' ).trigger( 'click' );
            }
        });

        $( '#lk-save-global-save-btn' ).on( 'click', function() {
            var name = $( '#lk-global-name-input' ).val().trim();
            if ( ! name ) {
                $( '#lk-save-global-error' ).text( 'Please enter a name for the global element.' ).show();
                $( '#lk-global-name-input' ).focus();
                return;
            }

            if ( ! state.savingGlobalElementId ) return;
            var el = findElementById( state.elements, state.savingGlobalElementId );
            if ( ! el ) {
                $( '#lk-save-global-modal' ).hide();
                return;
            }

            var $btn = $( this );
            $btn.prop( 'disabled', true ).find( '.lk-btn-text' ).text( 'Saving...' );
            $( '#lk-save-global-error' ).hide();

            var elClone = JSON.parse( JSON.stringify( el ) );

            $.ajax({
                url: ( LK.restBase || ( ( LK.restUrl || '/wp-json/' ) + 'loopkit/v1/' ) ) + 'global',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    name: name,
                    elementData: elClone,
                    sourcePostId: LK.postId || 0,
                    isGlobal: ! state.savingAsTemplate
                }),
                beforeSend: function( xhr ) {
                    // REST needs the wp_rest nonce (restNonce); LK.nonce is the editor nonce
                    // and was rejected with 401, so Save as Global / Save as Template failed.
                    var restNonce = LK.restNonce || LK.nonce;
                    if ( restNonce ) {
                        xhr.setRequestHeader( 'X-WP-Nonce', restNonce );
                    }
                },
                success: function( response ) {
                    $btn.prop( 'disabled', false ).find( '.lk-btn-text' ).text( 'Save' );
                    $( '#lk-save-global-modal' ).hide();

                    if ( ! response || ! response.templateId ) {
                        showToast( state.savingAsTemplate ? 'Failed to save template' : 'Failed to save global template', 'error' );
                        return;
                    }

                    // Plain template: the page does not change; it just joins the Templates tab.
                    if ( state.savingAsTemplate ) {
                        state.savingAsTemplate = false;
                        state.savingGlobalElementId = null;
                        showToast( '"' + ( response.name || name ) + '" saved to Templates', 'success' );
                        if ( typeof fetchTemplatesFromRest === 'function' ) fetchTemplatesFromRest();
                        return;
                    }

                    var templateId = parseInt( response.templateId, 10 );
                    var globalType = response.type || el.elType;

                    // Update state globals store
                    state.globals = state.globals || {};
                    state.globals[ templateId ] = {
                        id: templateId,
                        title: response.name || name,
                        type: globalType,
                        usageCount: 1,
                        master: elClone,
                        css: response.css || ''
                    };

                    // Replace element on canvas with linked instance
                    var instance = {
                        id: el.id,
                        elType: 'global',
                        globalType: globalType,
                        templateId: templateId,
                        overrides: {},
                        elements: []
                    };

                    replaceElementById( state.elements, el.id, instance );

                    state.selectedId = instance.id;
                    markDirty();
                    pushHistory( { title: title, action: 'Saved as Global' } );
                    sendToCanvas( 'update-elements', { elements: state.elements, selectedId: instance.id } );
                    sendDynamicCssToCanvas();
                    renderPanelForSelected();
                    renderGlobalsList();
                    showToast( 'Saved as Global ' + ( globalType === 'section' ? 'Section' : 'Container' ), 'success' );
                    state.savingGlobalElementId = null;
                },
                error: function( xhr ) {
                    $btn.prop( 'disabled', false ).find( '.lk-btn-text' ).text( 'Save' );
                    var msg = 'Failed to save global element.';
                    if ( xhr.responseJSON && xhr.responseJSON.message ) {
                        msg = xhr.responseJSON.message;
                    }
                    $( '#lk-save-global-error' ).text( msg ).show();
                }
            });
        });
    }

    function initElementsTabs() {
        $( document ).on( 'click', '.lk-elements-tab-btn', function() {
            var tab = $( this ).data( 'elements-tab' );
            switchElementsSubTab( tab );
        });

        $( '#lk-global-search' ).on( 'input', function() {
            renderGlobalsList();
        });

        // Drag start for global cards in panel
        $( document ).on( 'dragstart', '.lk-global-card', function( e ) {
            var gid = $( this ).data( 'global-id' );
            var gtype = $( this ).data( 'global-type' );
            window.loopkitDraggedWidget = 'global';
            window.loopkitDraggedGlobalId = gid;
            window.loopkitDraggedGlobalType = gtype;
            window.loopkitDraggedElement = null;

            if ( e.originalEvent && e.originalEvent.dataTransfer ) {
                e.originalEvent.dataTransfer.setData( 'text/plain', 'global:' + gid );
                e.originalEvent.dataTransfer.effectAllowed = 'copy';
            }
        });

        $( document ).on( 'dragend', '.lk-global-card', function() {
            window.loopkitDraggedWidget = null;
            window.loopkitDraggedGlobalId = null;
            window.loopkitDraggedGlobalType = null;
        });

        // Click on global card to insert
        $( document ).on( 'click', '.lk-global-card', function() {
            var gid = $( this ).data( 'global-id' );
            addGlobalInstance( gid, state.selectedId );
        });
    }

    function switchElementsSubTab( tab ) {
        state.activeElementsTab = tab || 'elements';
        $( '.lk-elements-tab-btn' ).removeClass( 'lk-tab-active' );
        $( '.lk-elements-tab-btn[data-elements-tab="' + state.activeElementsTab + '"]' ).addClass( 'lk-tab-active' );

        if ( state.activeElementsTab === 'globals' ) {
            $( '#lk-pane-elements' ).hide();
            $( '#lk-pane-templates' ).hide();
            $( '#lk-pane-globals' ).show();
            fetchGlobalsFromRest();
        } else if ( state.activeElementsTab === 'templates' ) {
            $( '#lk-pane-elements' ).hide();
            $( '#lk-pane-globals' ).hide();
            $( '#lk-pane-templates' ).show();
            fetchTemplatesFromRest();
        } else {
            $( '#lk-pane-globals' ).hide();
            $( '#lk-pane-templates' ).hide();
            $( '#lk-pane-elements' ).show();
        }
    }

    function fetchGlobalsFromRest( callback ) {
        $.ajax({
            url: ( LK.restBase || ( ( LK.restUrl || '/wp-json/' ) + 'loopkit/v1/' ) ) + 'globals',
            method: 'GET',
            beforeSend: function( xhr ) {
                var nonce = LK.restNonce || LK.nonce;
                if ( nonce ) {
                    xhr.setRequestHeader( 'X-WP-Nonce', nonce );
                }
            },
            success: function( response ) {
                if ( response && response.globals ) {
                    state.globals = response.globals;
                }
                renderGlobalsList();
                if ( typeof callback === 'function' ) callback();
            },
            error: function() {
                renderGlobalsList();
                if ( typeof callback === 'function' ) callback();
            }
        });
    }

    function renderGlobalsList() {
        var $list = $( '#lk-globals-list' );
        var $empty = $( '#lk-globals-empty' );
        if ( ! $list.length ) return;

        $list.empty();

        var globals = Object.keys( state.globals || {} ).map( function( k ) {
            return state.globals[ k ];
        });

        if ( ! globals.length ) {
            $list.hide();
            $empty.show();
            return;
        }

        $empty.hide();
        $list.show();

        var query = ( $( '#lk-global-search' ).val() || '' ).toLowerCase().trim();
        var visibleCount = 0;

        for ( var i = 0; i < globals.length; i++ ) {
            var g = globals[i];
            if ( ! g || ! g.id ) continue;
            var title = g.title || ( 'Global ' + g.id );
            if ( query.length > 0 && title.toLowerCase().indexOf( query ) === -1 ) {
                continue;
            }
            visibleCount++;

            var typeBadge = ( g.type === 'section' ) ? 'Section' : 'Container';
            var usageCount = parseInt( g.usageCount || 0, 10 );
            var usageText = ( usageCount === 1 ) ? 'Used on 1 page' : ( 'Used on ' + usageCount + ' pages' );

            var $card = $(
                '<div class="lk-global-card" data-global-id="' + g.id + '" data-global-type="' + ( g.type || 'section' ) + '" draggable="true">' +
                '  <div class="lk-global-card-icon"><span class="dashicons dashicons-admin-site-alt3"></span></div>' +
                '  <div class="lk-global-card-info">' +
                '    <div class="lk-global-card-title">' + $( '<div>' ).text( title ).html() + '</div>' +
                '    <div class="lk-global-card-meta">' +
                '      <span class="lk-global-type-badge">' + typeBadge + '</span>' +
                '      <span class="lk-global-usage-badge">' + usageText + '</span>' +
                '    </div>' +
                '  </div>' +
                '  <button type="button" class="lk-global-card-delete" title="Delete Global" aria-label="Delete Global">&times;</button>' +
                '</div>'
            );
            $list.append( $card );
        }

        if ( visibleCount === 0 && query.length > 0 ) {
            $list.append( '<div class="lk-globals-no-match" style="padding:20px; text-align:center; color:#9CA3AF; font-size:13px;">No globals match your search</div>' );
        }
    }

    var deletingGlobalId = null;
    function openDeleteGlobalModal( globalId ) {
        deletingGlobalId = globalId;
        $( '#lk-delete-global-error' ).hide().text( '' );
        $( '#lk-delete-global-confirm-btn' ).prop( 'disabled', false ).find( '.lk-btn-text' ).text( 'Delete' );
        $( '#lk-delete-global-modal' ).show();
    }

    function initDeleteGlobalModal() {
        $( document ).on( 'click', '.lk-global-card-delete', function( e ) {
            e.stopPropagation();
            var $card = $( this ).closest( '.lk-global-card' );
            var gid = $card.data( 'global-id' );
            openDeleteGlobalModal( gid );
        });

        $( '#lk-delete-global-cancel, #lk-delete-global-modal .lk-modal-close' ).on( 'click', function() {
            $( '#lk-delete-global-modal' ).hide();
            deletingGlobalId = null;
        });

        $( '#lk-delete-global-confirm-btn' ).on( 'click', function() {
            if ( ! deletingGlobalId ) return;
            var gid = deletingGlobalId;
            var $btn = $( this );
            $btn.prop( 'disabled', true ).find( '.lk-btn-text' ).text( 'Deleting...' );
            $( '#lk-delete-global-error' ).hide();

            $.ajax({
                url: ( LK.restBase || ( ( LK.restUrl || '/wp-json/' ) + 'loopkit/v1/' ) ) + 'global/' + gid,
                method: 'DELETE',
                beforeSend: function( xhr ) {
                    var nonce = LK.restNonce || LK.nonce;
                    if ( nonce ) {
                        xhr.setRequestHeader( 'X-WP-Nonce', nonce );
                    }
                },
                success: function() {
                    $btn.prop( 'disabled', false ).find( '.lk-btn-text' ).text( 'Delete' );
                    $( '#lk-delete-global-modal' ).hide();
                    if ( state.globals ) {
                        delete state.globals[ gid ];
                        delete state.globals[ String( gid ) ];
                    }
                    deletingGlobalId = null;
                    fetchGlobalsFromRest();
                    showToast( 'Global template deleted', 'success' );
                },
                error: function() {
                    // Fallback to AJAX delete
                    $.ajax({
                        url: LK.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'loopkit_delete_template',
                            template_id: gid,
                            nonce: LK.templatesNonce
                        },
                        success: function() {
                            $btn.prop( 'disabled', false ).find( '.lk-btn-text' ).text( 'Delete' );
                            $( '#lk-delete-global-modal' ).hide();
                            if ( state.globals ) {
                                delete state.globals[ gid ];
                                delete state.globals[ String( gid ) ];
                            }
                            deletingGlobalId = null;
                            fetchGlobalsFromRest();
                            showToast( 'Global template deleted', 'success' );
                        },
                        error: function( fallbackXhr ) {
                            $btn.prop( 'disabled', false ).find( '.lk-btn-text' ).text( 'Delete' );
                            var msg = 'Failed to delete global template.';
                            if ( fallbackXhr.responseJSON && fallbackXhr.responseJSON.message ) {
                                msg = fallbackXhr.responseJSON.message;
                            }
                            $( '#lk-delete-global-error' ).text( msg ).show();
                        }
                    });
                }
            });
        });
    }

    /* =========================================================
       PART E: TEMPLATES TAB & TEMPLATES LIBRARY MODAL
       ========================================================= */

    function initTemplatesTab() {
        // Search filter in panel tab
        $( '#lk-template-search' ).on( 'input', function() {
            renderTemplatesList();
        });

        // Filter chips in panel tab
        $( '#lk-panel-template-filters' ).on( 'click', '.lk-filter-chip', function() {
            $( '#lk-panel-template-filters .lk-filter-chip' ).removeClass( 'lk-chip-active' );
            $( this ).addClass( 'lk-chip-active' );
            state.activeTemplateFilter = $( this ).data( 'filter' ) || 'all';
            renderTemplatesList();
        });

        // Drag start for template cards in panel
        $( document ).on( 'dragstart', '.lk-template-card', function( e ) {
            var tid = $( this ).data( 'template-id' );
            var ttype = $( this ).data( 'template-type' );
            window.loopkitDraggedWidget = 'template';
            window.loopkitDraggedTemplateId = tid;
            window.loopkitDraggedTemplateType = ttype;
            window.loopkitDraggedElement = null;

            if ( e.originalEvent && e.originalEvent.dataTransfer ) {
                e.originalEvent.dataTransfer.setData( 'text/plain', 'template:' + tid + ':' + ttype );
                e.originalEvent.dataTransfer.effectAllowed = 'copy';
            }
        });

        $( document ).on( 'dragend', '.lk-template-card', function() {
            window.loopkitDraggedWidget = null;
            window.loopkitDraggedTemplateId = null;
            window.loopkitDraggedTemplateType = null;
        });

        // Click template card directly (outside 3-dot) to insert
        $( document ).on( 'click', '.lk-template-card', function( e ) {
            if ( $( e.target ).closest( '.lk-template-3dot-wrap' ).length ) return;
            var tid = $( this ).data( 'template-id' );
            insertTemplate( tid, state.selectedId );
        });

        // Toggle 3-dot menu
        $( document ).on( 'click', '.lk-template-3dot-btn', function( e ) {
            e.stopPropagation();
            var $menu = $( this ).siblings( '.lk-template-dropdown-menu' );
            $( '.lk-template-dropdown-menu' ).not( $menu ).hide();
            $menu.toggle();
        });

        // Close 3-dot menus on outside click
        $( document ).on( 'click', function() {
            $( '.lk-template-dropdown-menu' ).hide();
        });

        // 3-dot menu actions: Insert
        $( document ).on( 'click', '.lk-template-action-insert', function( e ) {
            e.stopPropagation();
            $( '.lk-template-dropdown-menu' ).hide();
            var tid = $( this ).data( 'template-id' );
            insertTemplate( tid, state.selectedId );
        });

        // 3-dot menu actions: Rename
        $( document ).on( 'click', '.lk-template-action-rename', function( e ) {
            e.stopPropagation();
            $( '.lk-template-dropdown-menu' ).hide();
            var tid = $( this ).data( 'template-id' );
            var ttitle = $( this ).data( 'template-title' );
            openRenameTemplateModal( tid, ttitle );
        });

        // 3-dot menu actions: Delete
        $( document ).on( 'click', '.lk-template-action-delete', function( e ) {
            e.stopPropagation();
            $( '.lk-template-dropdown-menu' ).hide();
            var tid = $( this ).data( 'template-id' );
            var ttitle = $( this ).data( 'template-title' );
            openDeleteTemplateModal( tid, ttitle );
        });

        // 3-dot menu actions: Export single
        $( document ).on( 'click', '.lk-template-action-export', function( e ) {
            e.stopPropagation();
            $( '.lk-template-dropdown-menu' ).hide();
            var tid = $( this ).data( 'template-id' );
            exportTemplateById( tid );
        });
    }

    function fetchTemplatesFromRest( callback ) {
        $.ajax({
            url: ( LK.restBase || ( ( LK.restUrl || '/wp-json/' ) + 'loopkit/v1/' ) ) + 'templates',
            method: 'GET',
            beforeSend: function( xhr ) {
                var nonce = LK.restNonce || LK.nonce;
                if ( nonce ) {
                    xhr.setRequestHeader( 'X-WP-Nonce', nonce );
                }
            },
            success: function( response ) {
                if ( response && Array.isArray( response.templates ) ) {
                    state.templates = response.templates;
                }
                renderTemplatesList();
                renderModalTemplatesList();
                if ( typeof callback === 'function' ) callback();
            },
            error: function() {
                renderTemplatesList();
                renderModalTemplatesList();
                if ( typeof callback === 'function' ) callback();
            }
        });
    }

    function renderTemplatesList() {
        var $list = $( '#lk-templates-list' );
        var $empty = $( '#lk-templates-empty' );
        if ( ! $list.length ) return;

        $list.empty();

        var templates = state.templates || [];
        if ( ! templates.length ) {
            $list.hide();
            $empty.show();
            return;
        }

        $empty.hide();
        $list.show();

        var query = ( $( '#lk-template-search' ).val() || '' ).toLowerCase().trim();
        var filter = state.activeTemplateFilter || 'all';
        var visibleCount = 0;

        for ( var i = 0; i < templates.length; i++ ) {
            var t = templates[i];
            if ( ! t || ! t.id ) continue;

            var tType = ( t.type || 'section' ).toLowerCase();
            if ( filter !== 'all' && tType !== filter ) {
                continue;
            }

            var title = t.title || ( 'Template #' + t.id );
            if ( query.length > 0 && title.toLowerCase().indexOf( query ) === -1 ) {
                continue;
            }

            visibleCount++;

            var typeBadge = ( tType === 'section' ) ? 'Section' : 'Container';
            var iconClass = ( tType === 'section' ) ? 'dashicons-format-aside' : 'dashicons-layout';
            var modDate = t.date || '';

            var $card = $(
                '<div class="lk-template-card" data-template-id="' + t.id + '" data-template-type="' + tType + '" draggable="true">' +
                '  <div class="lk-template-card-icon"><span class="dashicons ' + iconClass + '"></span></div>' +
                '  <div class="lk-template-card-info">' +
                '    <div class="lk-template-card-title">' + $( '<div>' ).text( title ).html() + '</div>' +
                '    <div class="lk-template-card-meta">' +
                '      <span class="lk-template-type-badge lk-type-' + tType + '">' + typeBadge + '</span>' +
                ( modDate ? '      <span class="lk-template-date">' + $( '<div>' ).text( modDate ).html() + '</span>' : '' ) +
                '    </div>' +
                '  </div>' +
                '  <div class="lk-template-3dot-wrap">' +
                '    <button type="button" class="lk-template-3dot-btn" title="Template actions" aria-label="Template actions">&#8942;</button>' +
                '    <div class="lk-template-dropdown-menu" style="display:none;">' +
                '      <button type="button" class="lk-template-dropdown-item lk-template-action-insert" data-template-id="' + t.id + '">' +
                '        <span class="dashicons dashicons-plus-alt"></span> ' + ( LK.strings.insert || 'Insert' ) +
                '      </button>' +
                '      <button type="button" class="lk-template-dropdown-item lk-template-action-rename" data-template-id="' + t.id + '" data-template-title="' + $( '<div>' ).text( title ).html() + '">' +
                '        <span class="dashicons dashicons-edit"></span> ' + ( LK.strings.rename || 'Rename' ) +
                '      </button>' +
                '      <button type="button" class="lk-template-dropdown-item lk-template-action-export" data-template-id="' + t.id + '">' +
                '        <span class="dashicons dashicons-download"></span> ' + ( LK.strings.export || 'Export' ) +
                '      </button>' +
                '      <div class="lk-template-dropdown-sep"></div>' +
                '      <button type="button" class="lk-template-dropdown-item lk-action-danger lk-template-action-delete" data-template-id="' + t.id + '" data-template-title="' + $( '<div>' ).text( title ).html() + '">' +
                '        <span class="dashicons dashicons-trash"></span> ' + ( LK.strings.delete || 'Delete' ) +
                '      </button>' +
                '    </div>' +
                '  </div>' +
                '</div>'
            );

            $list.append( $card );
        }

        if ( visibleCount === 0 && ( query.length > 0 || filter !== 'all' ) ) {
            $list.append( '<div class="lk-templates-no-match" style="padding:20px; text-align:center; color:var(--lk-panel-tab-fg); font-size:13px;">No templates match your filter</div>' );
        }
    }

    /* --- Rename Template Modal Logic --- */
    var renamingTemplateId = null;
    function openRenameTemplateModal( templateId, currentTitle ) {
        renamingTemplateId = templateId;
        $( '#lk-rename-template-error' ).hide().text( '' );
        $( '#lk-rename-template-title-input' ).val( currentTitle || '' );
        $( '#lk-rename-template-confirm-btn' ).prop( 'disabled', false ).text( 'Save' );
        $( '#lk-rename-template-modal' ).show();
        setTimeout( function() {
            $( '#lk-rename-template-title-input' ).focus().select();
        }, 50 );
    }

    function initRenameTemplateModal() {
        $( '#lk-rename-template-modal .lk-modal-close' ).on( 'click', function() {
            $( '#lk-rename-template-modal' ).hide();
            renamingTemplateId = null;
        });

        $( '#lk-rename-template-title-input' ).on( 'keydown', function( e ) {
            if ( e.key === 'Enter' ) {
                e.preventDefault();
                $( '#lk-rename-template-confirm-btn' ).trigger( 'click' );
            }
        });

        $( '#lk-rename-template-confirm-btn' ).on( 'click', function() {
            if ( ! renamingTemplateId ) return;
            var newTitle = $( '#lk-rename-template-title-input' ).val().trim();
            if ( ! newTitle ) {
                $( '#lk-rename-template-error' ).text( 'Please enter a template name.' ).show();
                return;
            }

            var $btn = $( this );
            $btn.prop( 'disabled', true ).text( 'Saving...' );
            $( '#lk-rename-template-error' ).hide();

            $.ajax({
                url: LK.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'loopkit_rename_template',
                    template_id: renamingTemplateId,
                    title: newTitle,
                    nonce: LK.templatesNonce
                },
                success: function( res ) {
                    $btn.prop( 'disabled', false ).text( 'Save' );
                    if ( res && res.success ) {
                        $( '#lk-rename-template-modal' ).hide();
                        renamingTemplateId = null;
                        fetchTemplatesFromRest();
                        showToast( 'Template renamed', 'success' );
                    } else {
                        var msg = ( res && res.data && res.data.message ) ? res.data.message : 'Could not rename template.';
                        $( '#lk-rename-template-error' ).text( msg ).show();
                    }
                },
                error: function( xhr ) {
                    $btn.prop( 'disabled', false ).text( 'Save' );
                    var msg = 'Failed to rename template.';
                    if ( xhr.responseJSON && xhr.responseJSON.message ) {
                        msg = xhr.responseJSON.message;
                    }
                    $( '#lk-rename-template-error' ).text( msg ).show();
                }
            });
        });
    }

    /* --- Delete Template Modal Logic --- */
    var deletingTemplateId = null;
    function openDeleteTemplateModal( templateId, templateTitle ) {
        deletingTemplateId = templateId;
        $( '#lk-delete-template-error' ).hide().text( '' );
        $( '#lk-delete-template-confirm-btn' ).prop( 'disabled', false ).text( 'Delete' );
        $( '#lk-delete-template-confirm-text' ).text(
            templateTitle ? 'Are you sure you want to delete "' + templateTitle + '"? This action cannot be undone.' : 'Are you sure you want to delete this template? This action cannot be undone.'
        );
        $( '#lk-delete-template-modal' ).show();
    }

    function initDeleteTemplateModal() {
        $( '#lk-delete-template-modal .lk-modal-close' ).on( 'click', function() {
            $( '#lk-delete-template-modal' ).hide();
            deletingTemplateId = null;
        });

        $( '#lk-delete-template-confirm-btn' ).on( 'click', function() {
            if ( ! deletingTemplateId ) return;
            var tid = deletingTemplateId;
            var $btn = $( this );
            $btn.prop( 'disabled', true ).text( 'Deleting...' );
            $( '#lk-delete-template-error' ).hide();

            $.ajax({
                url: LK.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'loopkit_delete_template',
                    template_id: tid,
                    nonce: LK.templatesNonce
                },
                success: function( res ) {
                    $btn.prop( 'disabled', false ).text( 'Delete' );
                    if ( res && res.success ) {
                        $( '#lk-delete-template-modal' ).hide();
                        deletingTemplateId = null;
                        fetchTemplatesFromRest();
                        showToast( 'Template deleted', 'success' );
                    } else {
                        var msg = ( res && res.data && res.data.message ) ? res.data.message : 'Could not delete template.';
                        $( '#lk-delete-template-error' ).text( msg ).show();
                    }
                },
                error: function( xhr ) {
                    $btn.prop( 'disabled', false ).text( 'Delete' );
                    var msg = 'Failed to delete template.';
                    if ( xhr.responseJSON && xhr.responseJSON.message ) {
                        msg = xhr.responseJSON.message;
                    }
                    $( '#lk-delete-template-error' ).text( msg ).show();
                }
            });
        });
    }

    /* --- Template Error Modal Logic (Zero Alert) --- */
    function showTemplateErrorModal( title, message ) {
        $( '#lk-template-error-title' ).text( title || 'Template Error' );
        $( '#lk-template-error-message' ).text( message || 'An error occurred.' );
        $( '#lk-template-error-modal' ).show();
    }

    /* --- Templates Library Modal Logic (Part E3) --- */
    function openTemplatesLibraryModal() {
        state.selectedTemplateIdsForExport = [];
        updateModalExportButton();
        $( '#lk-templates-library-modal' ).show();
        fetchTemplatesFromRest();
    }

    function closeTemplatesLibraryModal() {
        $( '#lk-templates-library-modal' ).hide();
    }

    function renderModalTemplatesList() {
        var $grid = $( '#lk-modal-templates-grid' );
        var $empty = $( '#lk-modal-templates-empty' );
        if ( ! $grid.length ) return;

        $grid.empty();

        var templates = state.templates || [];
        var query = ( $( '#lk-modal-template-search' ).val() || '' ).toLowerCase().trim();
        var filter = state.modalTemplateFilter || 'all';
        var visibleCount = 0;

        for ( var i = 0; i < templates.length; i++ ) {
            var t = templates[i];
            if ( ! t || ! t.id ) continue;

            var tType = ( t.type || 'section' ).toLowerCase();
            if ( filter !== 'all' && tType !== filter ) {
                continue;
            }

            var title = t.title || ( 'Template #' + t.id );
            if ( query.length > 0 && title.toLowerCase().indexOf( query ) === -1 ) {
                continue;
            }

            visibleCount++;

            var typeBadge = ( tType === 'section' ) ? 'Section' : 'Container';
            var iconClass = ( tType === 'section' ) ? 'dashicons-format-aside' : 'dashicons-layout';
            var modDate = t.date || '';
            var isChecked = state.selectedTemplateIdsForExport.indexOf( t.id ) !== -1;

            var $card = $(
                '<div class="lk-template-modal-card" data-template-id="' + t.id + '" data-template-type="' + tType + '">' +
                '  <div class="lk-template-modal-card-top">' +
                '    <label class="lk-template-checkbox-label" onclick="event.stopPropagation();">' +
                '      <input type="checkbox" class="lk-template-select-cb" data-template-id="' + t.id + '" ' + ( isChecked ? 'checked' : '' ) + ' />' +
                '    </label>' +
                '    <div class="lk-template-modal-card-icon"><span class="dashicons ' + iconClass + '"></span></div>' +
                '    <span class="lk-template-type-badge lk-type-' + tType + '">' + typeBadge + '</span>' +
                '  </div>' +
                '  <div class="lk-template-modal-card-title">' + $( '<div>' ).text( title ).html() + '</div>' +
                '  <div class="lk-template-modal-card-meta">' +
                ( modDate ? '<span class="lk-template-date">' + $( '<div>' ).text( modDate ).html() + '</span>' : '<span></span>' ) +
                '    <button type="button" class="lk-btn lk-btn-primary lk-btn-sm lk-template-modal-insert-btn" data-template-id="' + t.id + '">' +
                '      ' + ( LK.strings.insert || 'Insert' ) +
                '    </button>' +
                '  </div>' +
                '</div>'
            );

            $grid.append( $card );
        }

        if ( visibleCount === 0 ) {
            $grid.hide();
            $empty.show();
        } else {
            $empty.hide();
            $grid.show();
        }
    }

    function updateModalExportButton() {
        var count = state.selectedTemplateIdsForExport.length;
        var $btn = $( '#lk-template-export-selected-btn' );
        $btn.find( '.lk-export-btn-text' ).text( 'Export Selected (' + count + ')' );
        $btn.prop( 'disabled', count === 0 );
    }

    function initTemplatesLibraryModal() {
        // Top bar toggle button
        $( '#lk-templates-library-toggle' ).on( 'click', function() {
            openTemplatesLibraryModal();
        });

        // Search input
        $( '#lk-modal-template-search' ).on( 'input', function() {
            renderModalTemplatesList();
        });

        // Filter chips in modal
        $( '#lk-modal-template-filters' ).on( 'click', '.lk-filter-chip', function() {
            $( '#lk-modal-template-filters .lk-filter-chip' ).removeClass( 'lk-chip-active' );
            $( this ).addClass( 'lk-chip-active' );
            state.modalTemplateFilter = $( this ).data( 'filter' ) || 'all';
            renderModalTemplatesList();
        });

        // Click card to insert
        $( document ).on( 'click', '.lk-template-modal-card', function( e ) {
            if ( $( e.target ).is( 'input, label, .lk-template-select-cb' ) ) return;
            var tid = $( this ).data( 'template-id' );
            closeTemplatesLibraryModal();
            insertTemplate( tid, state.selectedId );
        });

        // Insert button inside card
        $( document ).on( 'click', '.lk-template-modal-insert-btn', function( e ) {
            e.stopPropagation();
            var tid = $( this ).data( 'template-id' );
            closeTemplatesLibraryModal();
            insertTemplate( tid, state.selectedId );
        });

        // Checkbox selection for export
        $( document ).on( 'change', '.lk-template-select-cb', function( e ) {
            e.stopPropagation();
            var tid = parseInt( $( this ).data( 'template-id' ), 10 );
            var idx = state.selectedTemplateIdsForExport.indexOf( tid );
            if ( this.checked ) {
                if ( idx === -1 ) state.selectedTemplateIdsForExport.push( tid );
            } else {
                if ( idx !== -1 ) state.selectedTemplateIdsForExport.splice( idx, 1 );
            }
            updateModalExportButton();
        });

        // Export Selected button
        $( '#lk-template-export-selected-btn' ).on( 'click', function() {
            if ( ! state.selectedTemplateIdsForExport.length ) return;
            exportSelectedTemplates( state.selectedTemplateIdsForExport );
        });

        // Import JSON trigger button
        $( '#lk-template-import-trigger' ).on( 'click', function() {
            $( '#lk-template-file-input' ).val( '' ).trigger( 'click' );
        });

        // File input change handler for Import
        $( '#lk-template-file-input' ).on( 'change', function( e ) {
            var file = e.target.files && e.target.files[0];
            if ( ! file ) return;
            handleImportTemplateFile( file );
        });

        // Outside click to close (unless file picker or submodal was active)
        $( '#lk-templates-library-modal' ).on( 'click', function( e ) {
            if ( $( e.target ).is( '#lk-templates-library-modal' ) ) {
                closeTemplatesLibraryModal();
            }
        });

        $( document ).on( 'keydown.lkTemplatesLibrary', function( e ) {
            if ( e.key === 'Escape' && $( '#lk-templates-library-modal' ).is( ':visible' ) ) {
                closeTemplatesLibraryModal();
            }
        });
    }

    /* --- Export Template Logic --- */
    function exportTemplateById( templateId ) {
        var tid = parseInt( templateId, 10 );
        var tpl = null;
        for ( var i = 0; i < state.templates.length; i++ ) {
            if ( parseInt( state.templates[i].id, 10 ) === tid ) {
                tpl = state.templates[i];
                break;
            }
        }
        if ( ! tpl ) {
            showToast( 'Template not found for export', 'error' );
            return;
        }

        var payload = {
            version:       LK.pluginVersion || '1.0.26',
            title:         tpl.title || ( 'Template ' + tid ),
            type:          tpl.type || 'section',
            elements:      tpl.elements || [],
            page_settings: tpl.pageSettings || {}
        };

        downloadJsonFile( payload, ( tpl.title ? tpl.title.toLowerCase().replace(/[^a-z0-9]+/g, '-') : 'template' ) + '-' + tid + '.json' );
        showToast( 'Template exported', 'success' );
    }

    function exportSelectedTemplates( idList ) {
        if ( idList.length === 1 ) {
            exportTemplateById( idList[0] );
            return;
        }

        var exportItems = [];
        for ( var i = 0; i < state.templates.length; i++ ) {
            if ( idList.indexOf( parseInt( state.templates[i].id, 10 ) ) !== -1 ) {
                var t = state.templates[i];
                exportItems.push({
                    id:            t.id,
                    title:         t.title,
                    type:          t.type,
                    elements:      t.elements || [],
                    page_settings: t.pageSettings || {}
                });
            }
        }

        var payload = {
            version:   LK.pluginVersion || '1.0.26',
            type:      'bundle',
            templates: exportItems
        };

        downloadJsonFile( payload, 'loopkit-templates-export.json' );
        showToast( idList.length + ' templates exported', 'success' );
    }

    function downloadJsonFile( obj, filename ) {
        var jsonStr = JSON.stringify( obj, null, 2 );
        var blob = new Blob( [ jsonStr ], { type: 'application/json' } );
        var url = URL.createObjectURL( blob );
        var a = document.createElement( 'a' );
        a.href = url;
        a.download = filename;
        document.body.appendChild( a );
        a.click();
        document.body.removeChild( a );
        URL.revokeObjectURL( url );
    }

    /* --- Import Template Logic with Schema Validation & Error Modal --- */
    function handleImportTemplateFile( file ) {
        var reader = new FileReader();
        reader.onload = function( event ) {
            var text = event.target.result;
            var json = null;
            try {
                json = JSON.parse( text );
            } catch ( err ) {
                showTemplateErrorModal( 'Invalid JSON Format', 'The selected file could not be parsed as valid JSON. Please verify the file and try again.' );
                return;
            }

            // Check if single template or bundle
            if ( json && Array.isArray( json.templates ) ) {
                // Bundle of templates
                var importCount = 0;
                var errors = [];
                function importNext( index ) {
                    if ( index >= json.templates.length ) {
                        fetchTemplatesFromRest();
                        if ( importCount > 0 ) {
                            showToast( importCount + ' templates imported', 'success' );
                        }
                        if ( errors.length > 0 ) {
                            showTemplateErrorModal( 'Import Notice', errors.join( "\n" ) );
                        }
                        return;
                    }
                    validateAndPostTemplate( json.templates[ index ], function( err ) {
                        if ( err ) errors.push( ( json.templates[ index ].title || 'Template #' + ( index + 1 ) ) + ': ' + err );
                        else importCount++;
                        importNext( index + 1 );
                    });
                }
                importNext( 0 );

            } else {
                // Single template import
                validateAndPostTemplate( json, function( err, result ) {
                    if ( err ) {
                        showTemplateErrorModal( 'Schema Validation Error', err );
                    } else {
                        fetchTemplatesFromRest();
                        showToast( 'Template "' + result.title + '" imported successfully', 'success' );
                    }
                });
            }
        };

        reader.readAsText( file );
    }

    function validateAndPostTemplate( data, callback ) {
        if ( ! data || typeof data !== 'object' ) {
            callback( 'Template data is not a valid object.' );
            return;
        }

        var type = ( data.type || '' ).toLowerCase().trim();
        if ( type !== 'section' && type !== 'container' ) {
            callback( 'Invalid template type "' + type + '". Top-level type must be "Section" or "Container".' );
            return;
        }

        if ( ! Array.isArray( data.elements ) ) {
            callback( 'Template "elements" property must be a valid array.' );
            return;
        }

        // Validate each element schema
        for ( var i = 0; i < data.elements.length; i++ ) {
            var el = data.elements[i];
            if ( ! el || typeof el !== 'object' ) {
                callback( 'Element at index ' + i + ' is not a valid element object.' );
                return;
            }
            if ( ! el.elType || typeof el.elType !== 'string' ) {
                callback( 'Element at index ' + i + ' is missing a valid "elType".' );
                return;
            }
            if ( ! el.id || typeof el.id !== 'string' ) {
                callback( 'Element at index ' + i + ' is missing a valid "id".' );
                return;
            }
        }

        // POST to REST API
        $.ajax({
            url: ( LK.restBase || ( ( LK.restUrl || '/wp-json/' ) + 'loopkit/v1/' ) ) + 'templates/import',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                title:         data.title || ( 'Imported ' + ( type === 'section' ? 'Section' : 'Container' ) ),
                type:          type,
                elements:      data.elements,
                page_settings: data.page_settings || data.pageSettings || {}
            }),
            beforeSend: function( xhr ) {
                var nonce = LK.restNonce || LK.nonce;
                if ( nonce ) {
                    xhr.setRequestHeader( 'X-WP-Nonce', nonce );
                }
            },
            success: function( res ) {
                if ( res && res.success ) {
                    callback( null, res );
                } else {
                    var msg = ( res && res.message ) ? res.message : 'Server could not create imported template.';
                    callback( msg );
                }
            },
            error: function( xhr ) {
                var msg = 'Failed to import template to server.';
                if ( xhr.responseJSON && xhr.responseJSON.message ) {
                    msg = xhr.responseJSON.message;
                }
                callback( msg );
            }
        });
    }

    function getSiteSettingsCategories() {
        return [
            {
                group: 'Design System',
                items: [
                    { id: 'global_colors', label: 'Global Colors', icon: 'dashicons-art' },
                    { id: 'global_fonts', label: 'Global Fonts', icon: 'dashicons-editor-textcolor' }
                ]
            },
            {
                group: 'Theme Style',
                items: [
                    { id: 'theme_typography', label: 'Typography', icon: 'dashicons-editor-paragraph' },
                    { id: 'theme_buttons', label: 'Buttons', icon: 'dashicons-button' },
                    { id: 'theme_images', label: 'Images', icon: 'dashicons-format-image' },
                    { id: 'theme_form_fields', label: 'Form Fields', icon: 'dashicons-feedback' }
                ]
            },
            {
                group: 'Settings',
                items: [
                    { id: 'site_identity', label: 'Site Identity', icon: 'dashicons-admin-site-alt3' },
                    { id: 'background', label: 'Background', icon: 'dashicons-format-image' },
                    { id: 'layout', label: 'Layout', icon: 'dashicons-layout' },
                    { id: 'lightbox', label: 'Lightbox', icon: 'dashicons-format-gallery' },
                    { id: 'custom_css', label: 'Custom CSS', icon: 'dashicons-editor-code' }
                ]
            }
        ];
    }

    function renderSiteSettingsNav() {
        var $nav = $( '#lk-site-settings-nav' ).empty();
        var cats = getSiteSettingsCategories();

        cats.forEach( function( grp ) {
            var $grpWrap = $( '<div class="lk-site-settings-group"></div>' );
            $grpWrap.append( '<div class="lk-site-settings-group-title">' + escapeHtml( grp.group ) + '</div>' );

            grp.items.forEach( function( itm ) {
                var $row = $(
                    '<div class="lk-site-settings-item" data-screen="' + itm.id + '">' +
                        '<span class="dashicons ' + itm.icon + '"></span>' +
                        '<span>' + escapeHtml( itm.label ) + '</span>' +
                        '<span class="dashicons dashicons-arrow-right-alt2 lk-site-settings-item-arrow"></span>' +
                    '</div>'
                );
                $row.on( 'click', function() {
                    openSiteSettingsSubscreen( itm.id, itm.label );
                });
                $grpWrap.append( $row );
            });

            $nav.append( $grpWrap );
        });
    }

    function openSiteSettingsSubscreen( screenId, screenLabel ) {
        state.activeSiteSubscreen = screenId;
        $( '#lk-site-settings-nav' ).hide();
        var $sub = $( '#lk-site-settings-subscreen' ).show().empty();

        var $hdr = $(
            '<div class="lk-site-subscreen-header">' +
                '<button type="button" class="lk-site-subscreen-back-btn" id="lk-site-subscreen-back">' +
                    '<span class="dashicons dashicons-arrow-left-alt2"></span>' +
                '</button>' +
                '<span class="lk-site-subscreen-title">' + escapeHtml( screenLabel ) + '</span>' +
            '</div>'
        );
        $hdr.find( '#lk-site-subscreen-back' ).on( 'click', function() {
            closeSiteSettingsSubscreen();
        });
        $sub.append( $hdr );

        var $body = $( '<div class="lk-site-subscreen-body"></div>' ).appendTo( $sub );
        renderSiteSettingsSubscreenContent( screenId, $body );
    }

    function closeSiteSettingsSubscreen() {
        state.activeSiteSubscreen = null;
        $( '#lk-site-settings-subscreen' ).hide().empty();
        $( '#lk-site-settings-nav' ).show();
    }

    function markSiteSettingsDirty() {
        state.siteSettingsDirty = true;
        $( '#lk-site-settings-save' ).prop( 'disabled', false ).addClass( 'lk-dirty' );
        updateCanvasSiteSettingsLive();
    }

    function updateCanvasSiteSettingsLive() {
        var s = state.siteSettings || {};
        var css = '';

        // CSS Variables for Colors
        var sysCols = s.system_colors || {};
        var custCols = s.custom_colors || [];
        var vars = [];

        Object.keys( sysCols ).forEach( function( k ) {
            vars.push( '--lk-global-color-' + k + ': ' + ( sysCols[k] || 'transparent' ) + ';' );
        });
        custCols.forEach( function( item ) {
            if ( item && item.id && item.color ) {
                vars.push( '--lk-global-color-' + item.id + ': ' + item.color + ';' );
            }
        });

        // CSS Variables for Fonts
        var sysFonts = s.system_fonts || {};
        var custFonts = s.custom_fonts || [];
        Object.keys( sysFonts ).forEach( function( k ) {
            var f = sysFonts[k] || {};
            if ( f.family ) vars.push( '--lk-global-font-' + k + '-family: "' + f.family + '", sans-serif;' );
            if ( f.weight ) vars.push( '--lk-global-font-' + k + '-weight: ' + f.weight + ';' );
        });
        custFonts.forEach( function( item ) {
            if ( item && item.id && item.typography ) {
                var t = item.typography;
                if ( t.family ) vars.push( '--lk-global-font-' + item.id + '-family: "' + t.family + '", sans-serif;' );
                if ( t.weight ) vars.push( '--lk-global-font-' + item.id + '-weight: ' + t.weight + ';' );
            }
        });

        if ( vars.length ) {
            css += ':root {\n  ' + vars.join( '\n  ' ) + '\n}\n';
        }

        // Page background live update
        var bg = s.background || {};
        if ( bg.color ) {
            css += 'body { background-color: ' + bg.color + ' !important; }\n';
        }

        // Custom CSS
        if ( s.site_custom_css ) {
            css += '\n' + s.site_custom_css + '\n';
        }

        sendToCanvas( 'update-site-settings-css', { css: css } );
    }

    function renderSiteSettingsSubscreenContent( screenId, $container ) {
        var s = state.siteSettings = state.siteSettings || {};

        if ( screenId === 'global_colors' ) {
            renderGlobalColorsEditor( $container );
        } else if ( screenId === 'global_fonts' ) {
            renderGlobalFontsEditor( $container );
        } else if ( screenId === 'site_identity' ) {
            renderSiteIdentityEditor( $container );
        } else if ( screenId === 'layout' ) {
            renderLayoutSettingsEditor( $container );
        } else if ( screenId === 'background' ) {
            renderBackgroundSettingsEditor( $container );
        } else if ( screenId === 'lightbox' ) {
            renderLightboxSettingsEditor( $container );
        } else if ( screenId === 'custom_css' ) {
            renderCustomCssEditor( $container );
        } else if ( screenId === 'theme_typography' || screenId === 'theme_buttons' || screenId === 'theme_images' || screenId === 'theme_form_fields' ) {
            renderThemeStyleEditor( screenId, $container );
        } else {
            $container.html( '<p style="color:#64748b; font-size:13px;">Coming soon.</p>' );
        }
    }

    function renderGlobalColorsEditor( $container ) {
        var s = state.siteSettings = state.siteSettings || {};
        s.system_colors = s.system_colors || {
            primary: '#38A8F5', secondary: '#54595F', text: '#7A7A7A', accent: '#61CE70'
        };
        s.custom_colors = s.custom_colors || [];

        var $wrap = $( '<div class="lk-global-colors-mgr"></div>' ).appendTo( $container );

        // System Colors
        $wrap.append( '<div class="lk-control-header" style="margin-bottom:8px;"><label style="font-weight:600; font-size:12px; text-transform:uppercase; color:#64748b;">System Colors</label></div>' );
        var $sysList = $( '<div class="lk-global-list"></div>' ).appendTo( $wrap );

        var sysKeys = [ 'primary', 'secondary', 'text', 'accent' ];
        sysKeys.forEach( function( key ) {
            var col = s.system_colors[ key ] || '#38A8F5';
            var $item = $(
                '<div class="lk-global-item">' +
                    '<input type="color" class="lk-global-color-swatch" value="' + col + '" />' +
                    '<div class="lk-global-info">' +
                        '<div class="lk-global-label">' + key.charAt(0).toUpperCase() + key.slice(1) + '</div>' +
                        '<div class="lk-global-slug">--lk-global-color-' + key + '</div>' +
                    '</div>' +
                '</div>'
            );
            $item.find( 'input[type="color"]' ).on( 'input change', function() {
                s.system_colors[ key ] = $( this ).val();
                markSiteSettingsDirty();
            });
            $sysList.append( $item );
        });

        // Custom Colors
        $wrap.append( '<div class="lk-control-header" style="margin:16px 0 8px;"><label style="font-weight:600; font-size:12px; text-transform:uppercase; color:#64748b;">Custom Colors</label></div>' );
        var $custList = $( '<div class="lk-global-list" id="lk-custom-colors-list"></div>' ).appendTo( $wrap );

        function renderCustomColorItems() {
            $custList.empty();
            s.custom_colors.forEach( function( itm, idx ) {
                var $row = $(
                    '<div class="lk-global-item">' +
                        '<input type="color" class="lk-global-color-swatch" value="' + ( itm.color || '#38A8F5' ) + '" />' +
                        '<div class="lk-global-info">' +
                            '<input type="text" class="lk-input" style="height:26px; font-size:12px;" value="' + escapeHtml( itm.title || 'Color ' + ( idx + 1 ) ) + '" />' +
                        '</div>' +
                        '<div class="lk-global-actions">' +
                            '<button type="button" class="lk-global-action-btn lk-del-color" title="Delete"><span class="dashicons dashicons-trash"></span></button>' +
                        '</div>' +
                    '</div>'
                );
                $row.find( 'input[type="color"]' ).on( 'input change', function() {
                    itm.color = $( this ).val();
                    markSiteSettingsDirty();
                });
                $row.find( 'input[type="text"]' ).on( 'input change', function() {
                    itm.title = $( this ).val();
                    markSiteSettingsDirty();
                });
                $row.find( '.lk-del-color' ).on( 'click', function() {
                    s.custom_colors.splice( idx, 1 );
                    renderCustomColorItems();
                    markSiteSettingsDirty();
                });
                $custList.append( $row );
            });
        }
        renderCustomColorItems();

        var $addBtn = $( '<button type="button" class="lk-btn lk-btn-default" style="width:100%; margin-top:8px;"><span class="dashicons dashicons-plus"></span> Add Color</button>' );
        $addBtn.on( 'click', function() {
            var newId = 'custom_' + generateId();
            s.custom_colors.push({ id: newId, title: 'New Color', color: '#38A8F5' });
            renderCustomColorItems();
            markSiteSettingsDirty();
        });
        $wrap.append( $addBtn );
    }

    function renderGlobalFontsEditor( $container ) {
        var s = state.siteSettings = state.siteSettings || {};
        s.system_fonts = s.system_fonts || {
            primary: { family: 'Inter', weight: '600' },
            secondary: { family: 'Inter', weight: '400' },
            text: { family: 'Inter', weight: '400' },
            accent: { family: 'Inter', weight: '500' }
        };
        s.custom_fonts = s.custom_fonts || [];

        var $wrap = $( '<div class="lk-global-fonts-mgr"></div>' ).appendTo( $container );
        $wrap.append( '<div class="lk-control-header" style="margin-bottom:8px;"><label style="font-weight:600; font-size:12px; text-transform:uppercase; color:#64748b;">System Fonts</label></div>' );
        var $sysList = $( '<div class="lk-global-list"></div>' ).appendTo( $wrap );

        var sysKeys = [ 'primary', 'secondary', 'text', 'accent' ];
        sysKeys.forEach( function( key ) {
            var f = s.system_fonts[ key ] || { family: 'Inter', weight: '400' };
            var $item = $(
                '<div class="lk-global-item" style="flex-direction:column; align-items:stretch; gap:6px;">' +
                    '<div style="display:flex; justify-content:space-between; align-items:center;">' +
                        '<span class="lk-global-label">' + key.charAt(0).toUpperCase() + key.slice(1) + '</span>' +
                        '<span class="lk-global-slug">--lk-global-font-' + key + '</span>' +
                    '</div>' +
                    '<div style="display:flex; gap:8px;">' +
                        '<input type="text" class="lk-input lk-f-family" style="flex:2; height:28px; font-size:12px;" placeholder="Font Family" value="' + escapeHtml( f.family || '' ) + '" />' +
                        '<select class="lk-select lk-f-weight" style="flex:1; height:28px; font-size:12px;">' +
                            '<option value="300"' + ( f.weight === '300' ? ' selected' : '' ) + '>300</option>' +
                            '<option value="400"' + ( f.weight === '400' ? ' selected' : '' ) + '>400</option>' +
                            '<option value="500"' + ( f.weight === '500' ? ' selected' : '' ) + '>500</option>' +
                            '<option value="600"' + ( f.weight === '600' ? ' selected' : '' ) + '>600</option>' +
                            '<option value="700"' + ( f.weight === '700' ? ' selected' : '' ) + '>700</option>' +
                        '</select>' +
                    '</div>' +
                '</div>'
            );
            $item.find( '.lk-f-family' ).on( 'input change', function() {
                f.family = $( this ).val();
                markSiteSettingsDirty();
            });
            $item.find( '.lk-f-weight' ).on( 'change', function() {
                f.weight = $( this ).val();
                markSiteSettingsDirty();
            });
            $sysList.append( $item );
        });

        // Custom Fonts
        $wrap.append( '<div class="lk-control-header" style="margin:16px 0 8px;"><label style="font-weight:600; font-size:12px; text-transform:uppercase; color:#64748b;">Custom Fonts</label></div>' );
        var $custList = $( '<div class="lk-global-list" id="lk-custom-fonts-list"></div>' ).appendTo( $wrap );

        function renderCustomFontItems() {
            $custList.empty();
            s.custom_fonts.forEach( function( itm, idx ) {
                itm.typography = itm.typography || { family: 'Inter', weight: '400' };
                var $row = $(
                    '<div class="lk-global-item" style="flex-direction:column; align-items:stretch; gap:6px;">' +
                        '<div style="display:flex; justify-content:space-between; align-items:center;">' +
                            '<input type="text" class="lk-input lk-cf-title" style="height:26px; font-size:12px; width:140px;" value="' + escapeHtml( itm.title || 'Font ' + ( idx + 1 ) ) + '" />' +
                            '<button type="button" class="lk-global-action-btn lk-del-font" title="Delete"><span class="dashicons dashicons-trash"></span></button>' +
                        '</div>' +
                        '<div style="display:flex; gap:8px;">' +
                            '<input type="text" class="lk-input lk-cf-family" style="flex:2; height:28px; font-size:12px;" placeholder="Font Family" value="' + escapeHtml( itm.typography.family || '' ) + '" />' +
                            '<select class="lk-select lk-cf-weight" style="flex:1; height:28px; font-size:12px;">' +
                                '<option value="300"' + ( itm.typography.weight === '300' ? ' selected' : '' ) + '>300</option>' +
                                '<option value="400"' + ( itm.typography.weight === '400' ? ' selected' : '' ) + '>400</option>' +
                                '<option value="500"' + ( itm.typography.weight === '500' ? ' selected' : '' ) + '>500</option>' +
                                '<option value="600"' + ( itm.typography.weight === '600' ? ' selected' : '' ) + '>600</option>' +
                                '<option value="700"' + ( itm.typography.weight === '700' ? ' selected' : '' ) + '>700</option>' +
                            '</select>' +
                        '</div>' +
                    '</div>'
                );
                $row.find( '.lk-cf-title' ).on( 'input change', function() {
                    itm.title = $( this ).val();
                    markSiteSettingsDirty();
                });
                $row.find( '.lk-cf-family' ).on( 'input change', function() {
                    itm.typography.family = $( this ).val();
                    markSiteSettingsDirty();
                });
                $row.find( '.lk-cf-weight' ).on( 'change', function() {
                    itm.typography.weight = $( this ).val();
                    markSiteSettingsDirty();
                });
                $row.find( '.lk-del-font' ).on( 'click', function() {
                    s.custom_fonts.splice( idx, 1 );
                    renderCustomFontItems();
                    markSiteSettingsDirty();
                });
                $custList.append( $row );
            });
        }
        renderCustomFontItems();

        var $addBtn = $( '<button type="button" class="lk-btn lk-btn-default" style="width:100%; margin-top:8px;"><span class="dashicons dashicons-plus"></span> Add Font</button>' );
        $addBtn.on( 'click', function() {
            var newId = 'custom_' + generateId();
            s.custom_fonts.push({ id: newId, title: 'New Font', typography: { family: 'Inter', weight: '400' } });
            renderCustomFontItems();
            markSiteSettingsDirty();
        });
        $wrap.append( $addBtn );
    }

    function renderSiteIdentityEditor( $container ) {
        var ident = state.siteIdentity = state.siteIdentity || {};
        var $wrap = $( '<div class="lk-site-identity-mgr"></div>' ).appendTo( $container );

        var $form = $(
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">Site Title</label>' +
                '<input type="text" class="lk-input" id="lk-ident-title" value="' + escapeHtml( ident.title || '' ) + '" />' +
            '</div>' +
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">Site Tagline</label>' +
                '<input type="text" class="lk-input" id="lk-ident-tagline" value="' + escapeHtml( ident.tagline || '' ) + '" />' +
            '</div>' +
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">Site Icon (Favicon)</label>' +
                '<div style="display:flex; align-items:center; gap:10px; margin-top:6px;">' +
                    '<img id="lk-ident-icon-preview" src="' + escapeHtml( ident.site_icon_url || '' ) + '" style="width:40px; height:40px; border:1px solid #cbd5e1; border-radius:4px; object-fit:cover; background:#f8fafc; ' + ( ident.site_icon_url ? '' : 'display:none;' ) + '" />' +
                    '<button type="button" class="lk-btn lk-btn-default" id="lk-ident-choose-icon">Choose Image</button>' +
                    ( ident.site_icon ? '<button type="button" class="lk-btn lk-btn-secondary" id="lk-ident-remove-icon">Remove</button>' : '' ) +
                '</div>' +
            '</div>'
        );

        $form.find( '#lk-ident-title' ).on( 'input change', function() {
            ident.title = $( this ).val();
            markSiteSettingsDirty();
        });
        $form.find( '#lk-ident-tagline' ).on( 'input change', function() {
            ident.tagline = $( this ).val();
            markSiteSettingsDirty();
        });
        $form.find( '#lk-ident-choose-icon' ).on( 'click', function() {
            if ( window.wp && wp.media ) {
                var frame = wp.media({
                    title: 'Select Site Icon',
                    button: { text: 'Use Site Icon' },
                    multiple: false
                });
                frame.on( 'select', function() {
                    var attachment = frame.state().get( 'selection' ).first().toJSON();
                    ident.site_icon = attachment.id;
                    ident.site_icon_url = attachment.url;
                    renderSiteIdentityEditor( $container.empty() );
                    markSiteSettingsDirty();
                });
                frame.open();
            }
        });
        $form.find( '#lk-ident-remove-icon' ).on( 'click', function() {
            ident.site_icon = 0;
            ident.site_icon_url = '';
            renderSiteIdentityEditor( $container.empty() );
            markSiteSettingsDirty();
        });

        $wrap.append( $form );
    }

    function renderLayoutSettingsEditor( $container ) {
        var s = state.siteSettings = state.siteSettings || {};
        s.container_width = parseInt( s.container_width, 10 ) || 1140;
        s.container_padding = parseInt( s.container_padding, 10 ) || 10;
        s.widgets_space = parseInt( s.widgets_space, 10 ) || 20;
        s.breakpoints = s.breakpoints || {
            widescreen: 2400, laptop: 1366, tablet_extra: 1200, tablet: 1024, mobile_extra: 880, mobile: 767
        };

        var $wrap = $( '<div class="lk-layout-settings-mgr"></div>' ).appendTo( $container );

        var $dims = $(
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">Content Width (px)</label>' +
                '<input type="number" class="lk-input" id="lk-lay-width" min="300" max="2500" value="' + s.container_width + '" />' +
            '</div>' +
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">Default Container Padding (px)</label>' +
                '<input type="number" class="lk-input" id="lk-lay-pad" min="0" max="200" value="' + s.container_padding + '" />' +
            '</div>' +
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">Widgets Space (Gap px)</label>' +
                '<input type="number" class="lk-input" id="lk-lay-space" min="0" max="200" value="' + s.widgets_space + '" />' +
            '</div>'
        );

        $dims.find( '#lk-lay-width' ).on( 'input change', function() {
            s.container_width = parseInt( $( this ).val(), 10 ) || 1140;
            markSiteSettingsDirty();
        });
        $dims.find( '#lk-lay-pad' ).on( 'input change', function() {
            s.container_padding = parseInt( $( this ).val(), 10 ) || 10;
            markSiteSettingsDirty();
        });
        $dims.find( '#lk-lay-space' ).on( 'input change', function() {
            s.widgets_space = parseInt( $( this ).val(), 10 ) || 20;
            markSiteSettingsDirty();
        });

        $wrap.append( $dims );

        // Breakpoints Editor
        $wrap.append( '<div class="lk-control-header" style="margin:20px 0 8px;"><label style="font-weight:600; font-size:12px; text-transform:uppercase; color:#64748b;">Responsive Breakpoints</label></div>' );
        var $bpErr = $( '<div class="lk-breakpoint-error" id="lk-bp-error" style="display:none;"></div>' ).appendTo( $wrap );

        var bpMeta = [
            { key: 'widescreen', label: 'Widescreen', icon: 'dashicons-desktop', note: 'Min width' },
            { key: 'laptop', label: 'Laptop', icon: 'dashicons-laptop', note: 'Max width' },
            { key: 'tablet_extra', label: 'Tablet Landscape', icon: 'dashicons-tablet', note: 'Max width' },
            { key: 'tablet', label: 'Tablet Portrait', icon: 'dashicons-tablet', note: 'Max width' },
            { key: 'mobile_extra', label: 'Mobile Landscape', icon: 'dashicons-smartphone', note: 'Max width' },
            { key: 'mobile', label: 'Mobile Portrait', icon: 'dashicons-smartphone', note: 'Max width' }
        ];

        var $bpList = $( '<div class="lk-breakpoints-editor-list"></div>' ).appendTo( $wrap );
        bpMeta.forEach( function( bm ) {
            var curVal = s.breakpoints[ bm.key ] || '';
            var $row = $(
                '<div class="lk-breakpoint-item" data-bp="' + bm.key + '">' +
                    '<div class="lk-breakpoint-meta">' +
                        '<span class="dashicons ' + bm.icon + '"></span>' +
                        '<span>' + escapeHtml( bm.label ) + '</span>' +
                    '</div>' +
                    '<div class="lk-breakpoint-val-wrap">' +
                        '<input type="number" class="lk-breakpoint-val-input" data-bp="' + bm.key + '" value="' + curVal + '" min="200" max="3840" />' +
                        '<span style="font-size:11px; color:#64748b;">px</span>' +
                    '</div>' +
                '</div>'
            );
            $bpList.append( $row );
        });

        $bpList.find( '.lk-breakpoint-val-input' ).on( 'input change', function() {
            var bpKey = $( this ).data( 'bp' );
            var val = parseInt( $( this ).val(), 10 );
            s.breakpoints[ bpKey ] = val;
            validateBreakpointsUI();
            markSiteSettingsDirty();
        });

        function validateBreakpointsUI() {
            var order = [ 'widescreen', 'laptop', 'tablet_extra', 'tablet', 'mobile_extra', 'mobile' ];
            var vals = [];
            for ( var i = 0; i < order.length; i++ ) {
                var v = parseInt( s.breakpoints[ order[i] ], 10 );
                if ( isNaN( v ) || v <= 0 ) {
                    $bpErr.text( 'All breakpoints must be positive numbers.' ).show();
                    return false;
                }
                vals.push( v );
            }
            for ( var j = 0; j < vals.length - 1; j++ ) {
                if ( vals[j] <= vals[ j + 1 ] ) {
                    $bpErr.text( 'Breakpoints must strictly descend in pixel values (Widescreen > Laptop > Tablet Landscape > Tablet > Mobile Landscape > Mobile).' ).show();
                    return false;
                }
            }
            $bpErr.hide();
            return true;
        }
    }

    function renderBackgroundSettingsEditor( $container ) {
        var s = state.siteSettings = state.siteSettings || {};
        s.background = s.background || { background: 'classic', color: '#ffffff' };

        var $wrap = $( '<div class="lk-bg-settings-mgr"></div>' ).appendTo( $container );
        var $row = $(
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">Background Color</label>' +
                '<div style="display:flex; align-items:center; gap:8px;">' +
                    '<input type="color" class="lk-global-color-swatch" id="lk-bg-col" value="' + ( s.background.color || '#ffffff' ) + '" />' +
                    '<input type="text" class="lk-input" id="lk-bg-col-text" value="' + escapeHtml( s.background.color || '#ffffff' ) + '" />' +
                '</div>' +
            '</div>'
        );

        $row.find( '#lk-bg-col' ).on( 'input change', function() {
            s.background.color = $( this ).val();
            $row.find( '#lk-bg-col-text' ).val( $( this ).val() );
            markSiteSettingsDirty();
        });
        $row.find( '#lk-bg-col-text' ).on( 'input change', function() {
            s.background.color = $( this ).val();
            $row.find( '#lk-bg-col' ).val( $( this ).val() );
            markSiteSettingsDirty();
        });

        $wrap.append( $row );
    }

    function renderLightboxSettingsEditor( $container ) {
        var s = state.siteSettings = state.siteSettings || {};
        s.lightbox = s.lightbox || { enabled: 'yes', ui_color: '#ffffff', overlay_color: 'rgba(0,0,0,0.85)' };

        var $wrap = $( '<div class="lk-lightbox-settings-mgr"></div>' ).appendTo( $container );
        var $row = $(
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">Enable Image Lightbox</label>' +
                '<select class="lk-select" id="lk-lb-enabled">' +
                    '<option value="yes"' + ( s.lightbox.enabled !== 'no' ? ' selected' : '' ) + '>Yes</option>' +
                    '<option value="no"' + ( s.lightbox.enabled === 'no' ? ' selected' : '' ) + '>No</option>' +
                '</select>' +
            '</div>' +
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">UI Icon Color</label>' +
                '<input type="color" class="lk-global-color-swatch" id="lk-lb-ui-color" value="' + ( s.lightbox.ui_color || '#ffffff' ) + '" />' +
            '</div>'
        );

        $row.find( '#lk-lb-enabled' ).on( 'change', function() {
            s.lightbox.enabled = $( this ).val();
            markSiteSettingsDirty();
        });
        $row.find( '#lk-lb-ui-color' ).on( 'input change', function() {
            s.lightbox.ui_color = $( this ).val();
            markSiteSettingsDirty();
        });

        $wrap.append( $row );
    }

    function renderCustomCssEditor( $container ) {
        var s = state.siteSettings = state.siteSettings || {};
        var $wrap = $( '<div class="lk-custom-css-mgr"></div>' ).appendTo( $container );

        var $area = $(
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">Site-Wide Custom CSS</label>' +
                '<p class="lk-user-prefs-desc">CSS written here applies globally across your entire website.</p>' +
                '<textarea class="lk-textarea" id="lk-site-custom-css" rows="12" style="font-family:monospace; font-size:12px; width:100%;">' + escapeHtml( s.site_custom_css || '' ) + '</textarea>' +
            '</div>'
        );

        $area.find( '#lk-site-custom-css' ).on( 'input change', function() {
            s.site_custom_css = $( this ).val();
            markSiteSettingsDirty();
        });

        $wrap.append( $area );
    }

    function renderThemeStyleEditor( screenId, $container ) {
        var s = state.siteSettings = state.siteSettings || {};
        s[ screenId ] = s[ screenId ] || {};

        var $wrap = $( '<div class="lk-theme-style-mgr"></div>' ).appendTo( $container );

        if ( screenId === 'theme_typography' ) {
            var tags = [ 'body', 'link', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ];
            tags.forEach( function( tag ) {
                s[ screenId ][ tag ] = s[ screenId ][ tag ] || {};
                var $sec = $(
                    '<div class="lk-user-prefs-row">' +
                        '<label class="lk-user-prefs-label" style="text-transform:uppercase;">' + tag + '</label>' +
                        '<div style="display:flex; gap:8px;">' +
                            '<input type="color" class="lk-global-color-swatch lk-tt-col" value="' + ( s[ screenId ][ tag ].color || '#333333' ) + '" />' +
                            '<input type="text" class="lk-input lk-tt-family" style="flex:2;" placeholder="Font Family" value="' + escapeHtml( s[ screenId ][ tag ].font_family || '' ) + '" />' +
                        '</div>' +
                    '</div>'
                );
                $sec.find( '.lk-tt-col' ).on( 'input change', function() {
                    s[ screenId ][ tag ].color = $( this ).val();
                    markSiteSettingsDirty();
                });
                $sec.find( '.lk-tt-family' ).on( 'input change', function() {
                    s[ screenId ][ tag ].font_family = $( this ).val();
                    markSiteSettingsDirty();
                });
                $wrap.append( $sec );
            });
        } else if ( screenId === 'theme_buttons' ) {
            var b = s[ screenId ];
            var $sec = $(
                '<div class="lk-user-prefs-row">' +
                    '<label class="lk-user-prefs-label">Background Color</label>' +
                    '<input type="color" class="lk-global-color-swatch" id="lk-tb-bg" value="' + ( b.bg_color || '#38A8F5' ) + '" />' +
                '</div>' +
                '<div class="lk-user-prefs-row">' +
                    '<label class="lk-user-prefs-label">Text Color</label>' +
                    '<input type="color" class="lk-global-color-swatch" id="lk-tb-col" value="' + ( b.text_color || '#ffffff' ) + '" />' +
                '</div>' +
                '<div class="lk-user-prefs-row">' +
                    '<label class="lk-user-prefs-label">Border Radius (px)</label>' +
                    '<input type="number" class="lk-input" id="lk-tb-rad" min="0" max="100" value="' + ( parseInt( b.border_radius, 10 ) || 4 ) + '" />' +
                '</div>'
            );
            $sec.find( '#lk-tb-bg' ).on( 'input change', function() {
                b.bg_color = $( this ).val();
                markSiteSettingsDirty();
            });
            $sec.find( '#lk-tb-col' ).on( 'input change', function() {
                b.text_color = $( this ).val();
                markSiteSettingsDirty();
            });
            $sec.find( '#lk-tb-rad' ).on( 'input change', function() {
                b.border_radius = $( this ).val();
                markSiteSettingsDirty();
            });
            $wrap.append( $sec );
        } else {
            $wrap.append( '<p style="color:#64748b; font-size:13px;">Style preferences configured for ' + escapeHtml( screenId ) + '.</p>' );
        }
    }

    function saveSiteSettingsViaRest() {
        var $btn = $( '#lk-site-settings-save' );
        $btn.prop( 'disabled', true ).text( 'Saving...' );

        var payload = {
            settings: state.siteSettings || {},
            site_identity: state.siteIdentity || {}
        };

        $.ajax({
            url: LK.restBase + 'site-settings',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify( payload ),
            beforeSend: function( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', LK.restNonce );
            },
            success: function( res ) {
                $btn.prop( 'disabled', false ).text( 'Save' ).removeClass( 'lk-dirty' );
                state.siteSettingsDirty = false;
                showToast( 'Site Settings saved', 'success' );
                updateCanvasSiteSettingsLive();
            },
            error: function( xhr ) {
                $btn.prop( 'disabled', false ).text( 'Save' );
                var err = 'Failed to save Site Settings.';
                if ( xhr.responseJSON && xhr.responseJSON.message ) {
                    err = xhr.responseJSON.message;
                }
                showToast( err, 'error' );
            }
        });
    }

    function showSiteSettingsPanel() {
        closeAllDropdowns();
        state.activePanel = 'site_settings';
        state.selectedId = null;

        $( '#lk-panel-tabs' ).hide();
        $( '#lk-panel-elements' ).hide();
        $( '#lk-panel-controls' ).hide();
        $( '#lk-panel-history' ).hide();
        $( '#lk-panel-user-prefs' ).hide();
        $( '#lk-history-toggle' ).removeClass( 'lk-topbar-active' );

        $( '#lk-panel-back-to-elements' ).show();
        $( '#lk-panel-header-icon' ).attr( 'class', 'dashicons dashicons-admin-settings' );
        $( '#lk-panel-header-text' ).text( 'Site Settings' );

        $( '#lk-panel-site-settings' ).show();
        renderSiteSettingsNav();
        closeSiteSettingsSubscreen();
    }

    function confirmSiteSettingsExit( onConfirm ) {
        if ( ! state.siteSettingsDirty ) {
            onConfirm();
            return;
        }
        var $modal = $( '#lk-unsaved-modal' ).show();
        $modal.find( '#lk-unsaved-save' ).off('click').on( 'click', function() {
            $modal.hide();
            saveSiteSettingsViaRest();
            onConfirm();
        });
        $modal.find( '#lk-unsaved-discard' ).off('click').on( 'click', function() {
            $modal.hide();
            state.siteSettingsDirty = false;
            $( '#lk-site-settings-save' ).removeClass( 'lk-dirty' );
            onConfirm();
        });
        $modal.find( '#lk-unsaved-cancel' ).off('click').on( 'click', function() {
            $modal.hide();
        });
    }

    /* =========================================================
       PART D2: USER PREFERENCES CONTROLLER
       ========================================================= */

    function renderUserPreferencesPanel() {
        var p = state.userPreferences = state.userPreferences || {};
        p.ui_theme = p.ui_theme || 'auto';
        p.panel_width = parseInt( p.panel_width, 10 ) || 300;
        p.show_edit_handles = ( p.show_edit_handles !== false );
        p.lightbox_in_editor = ( p.lightbox_in_editor !== false );
        p.exit_to = p.exit_to || 'dashboard';

        var $panel = $( '#lk-panel-user-prefs' ).empty();

        var $content = $(
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">UI Theme</label>' +
                '<p class="lk-user-prefs-desc">Choose your preferred editor color appearance.</p>' +
                '<select class="lk-select" id="lk-pref-theme">' +
                    '<option value="auto"' + ( p.ui_theme === 'auto' ? ' selected' : '' ) + '>Auto (System)</option>' +
                    '<option value="light"' + ( p.ui_theme === 'light' ? ' selected' : '' ) + '>Light</option>' +
                    '<option value="dark"' + ( p.ui_theme === 'dark' ? ' selected' : '' ) + '>Dark</option>' +
                '</select>' +
            '</div>' +
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">Panel Width: <span id="lk-pref-panel-val">' + p.panel_width + 'px</span></label>' +
                '<p class="lk-user-prefs-desc">Adjust the default width of the left editor panel (280px–500px).</p>' +
                '<input type="range" min="280" max="500" value="' + p.panel_width + '" id="lk-pref-panel-slider" style="width:100%;" />' +
            '</div>' +
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">Show Editing Handles</label>' +
                '<p class="lk-user-prefs-desc">Display visual hover handles and borders around canvas containers.</p>' +
                '<div class="lk-switcher ' + ( p.show_edit_handles ? 'lk-active' : '' ) + '" id="lk-pref-handles-switch">' +
                    '<div class="lk-switcher-slider"></div>' +
                '</div>' +
            '</div>' +
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">Lightbox in Editor</label>' +
                '<p class="lk-user-prefs-desc">Open clicked image widgets inside a lightbox preview while designing.</p>' +
                '<div class="lk-switcher ' + ( p.lightbox_in_editor ? 'lk-active' : '' ) + '" id="lk-pref-lightbox-switch">' +
                    '<div class="lk-switcher-slider"></div>' +
                '</div>' +
            '</div>' +
            '<div class="lk-user-prefs-row">' +
                '<label class="lk-user-prefs-label">Exit To</label>' +
                '<p class="lk-user-prefs-desc">Default destination when clicking "Exit to WordPress".</p>' +
                '<select class="lk-select" id="lk-pref-exit">' +
                    '<option value="dashboard"' + ( p.exit_to === 'dashboard' ? ' selected' : '' ) + '>Loopkit Dashboard</option>' +
                    '<option value="pages"' + ( p.exit_to === 'pages' ? ' selected' : '' ) + '>All Pages / Posts</option>' +
                    '<option value="frontend"' + ( p.exit_to === 'frontend' ? ' selected' : '' ) + '>Front-End Page</option>' +
                '</select>' +
            '</div>'
        );

        $content.find( '#lk-pref-theme' ).on( 'change', function() {
            p.ui_theme = $( this ).val();
            applyUserTheme( p.ui_theme );
            saveUserPreferencesViaRest();
        });

        $content.find( '#lk-pref-panel-slider' ).on( 'input', function() {
            var w = parseInt( $( this ).val(), 10 );
            p.panel_width = w;
            $panel.find( '#lk-pref-panel-val' ).text( w + 'px' );
            $( '#lk-panel' ).css( 'width', w + 'px' );
        }).on( 'change', function() {
            saveUserPreferencesViaRest();
        });

        $content.find( '#lk-pref-handles-switch' ).on( 'click', function() {
            $( this ).toggleClass( 'lk-active' );
            p.show_edit_handles = $( this ).hasClass( 'lk-active' );
            sendToCanvas( 'toggle-editing-handles', { hide: ! p.show_edit_handles } );
            saveUserPreferencesViaRest();
        });

        $content.find( '#lk-pref-lightbox-switch' ).on( 'click', function() {
            $( this ).toggleClass( 'lk-active' );
            p.lightbox_in_editor = $( this ).hasClass( 'lk-active' );
            saveUserPreferencesViaRest();
        });

        $content.find( '#lk-pref-exit' ).on( 'change', function() {
            p.exit_to = $( this ).val();
            updateExitToUrl( p.exit_to );
            saveUserPreferencesViaRest();
        });

        $panel.append( $content );
    }

    var autoThemeMediaQuery = null;
    var autoThemeListener = null;

    function applyUserTheme( theme ) {
        // Clean up previous media query listener if any
        if ( autoThemeMediaQuery && autoThemeListener ) {
            if ( autoThemeMediaQuery.removeEventListener ) {
                autoThemeMediaQuery.removeEventListener( 'change', autoThemeListener );
            } else if ( autoThemeMediaQuery.removeListener ) {
                autoThemeMediaQuery.removeListener( autoThemeListener );
            }
            autoThemeMediaQuery = null;
            autoThemeListener = null;
        }

        function setThemeClasses( isDark ) {
            var $target = $( 'html, body' );
            if ( isDark ) {
                $target.addClass( 'lk-theme-dark' ).removeClass( 'lk-theme-light' );
            } else {
                $target.removeClass( 'lk-theme-dark' );
                if ( theme === 'light' ) {
                    $target.addClass( 'lk-theme-light' );
                } else {
                    $target.removeClass( 'lk-theme-light' );
                }
            }
        }

        if ( theme === 'dark' ) {
            setThemeClasses( true );
        } else if ( theme === 'light' ) {
            setThemeClasses( false );
        } else if ( theme === 'auto' ) {
            autoThemeMediaQuery = window.matchMedia ? window.matchMedia( '(prefers-color-scheme: dark)' ) : null;
            if ( autoThemeMediaQuery ) {
                setThemeClasses( autoThemeMediaQuery.matches );
                autoThemeListener = function( e ) {
                    setThemeClasses( e.matches );
                };
                if ( autoThemeMediaQuery.addEventListener ) {
                    autoThemeMediaQuery.addEventListener( 'change', autoThemeListener );
                } else if ( autoThemeMediaQuery.addListener ) {
                    autoThemeMediaQuery.addListener( autoThemeListener );
                }
            } else {
                setThemeClasses( false );
            }
        }
    }

    function updateExitToUrl( target ) {
        var url = LK.dashboardUrl || 'admin.php?page=loopkit';
        if ( target === 'pages' ) {
            url = 'edit.php?post_type=page';
        } else if ( target === 'frontend' ) {
            url = LK.previewUrl || '/';
        }
        $( '#lk-exit-wordpress-btn' ).attr( 'href', url );
    }

    function saveUserPreferencesViaRest() {
        $.ajax({
            url: LK.restBase + 'user-preferences',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify( { preferences: state.userPreferences || {} } ),
            beforeSend: function( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', LK.restNonce );
            },
            success: function() {
                // saved silently
            }
        });
    }

    function showUserPreferencesPanel() {
        closeAllDropdowns();
        state.activePanel = 'user_prefs';
        state.selectedId = null;

        $( '#lk-panel-tabs' ).hide();
        $( '#lk-panel-elements' ).hide();
        $( '#lk-panel-controls' ).hide();
        $( '#lk-panel-history' ).hide();
        $( '#lk-panel-site-settings' ).hide();
        $( '#lk-history-toggle' ).removeClass( 'lk-topbar-active' );

        $( '#lk-panel-back-to-elements' ).show();
        $( '#lk-panel-header-icon' ).attr( 'class', 'dashicons dashicons-admin-users' );
        $( '#lk-panel-header-text' ).text( 'User Preferences' );

        $( '#lk-panel-user-prefs' ).show();
        renderUserPreferencesPanel();
    }

    /* =========================================================
       PART D3: FINDER (Ctrl+E / Cmd+E) CONTROLLER
       ========================================================= */
    var finderCache = null;

    function getStaticFinderItems() {
        return [
            { id: 'create_page', group: 'Create', title: 'Add New Page', icon: 'dashicons-plus-alt2', action: 'url', url: 'post-new.php?post_type=page' },
            { id: 'create_template', group: 'Create', title: 'Add New Template', icon: 'dashicons-portfolio', action: 'url', url: 'admin.php?page=loopkit-templates&action=new' },
            { id: 'panel_elements', group: 'Editor Panels', title: 'Elements library', icon: 'dashicons-screenoptions', action: 'panel', panel: 'elements' },
            { id: 'panel_settings', group: 'Editor Panels', title: 'Page / Template Settings', icon: 'dashicons-admin-generic', action: 'panel', panel: 'settings' },
            { id: 'panel_history', group: 'Editor Panels', title: 'History (Actions & Revisions)', icon: 'dashicons-backup', action: 'panel', panel: 'history' },
            { id: 'panel_site_settings', group: 'Site Settings', title: 'Site Settings', icon: 'dashicons-admin-settings', action: 'panel', panel: 'site_settings' },
            { id: 'panel_user_prefs', group: 'Settings', title: 'User Preferences', icon: 'dashicons-admin-users', action: 'panel', panel: 'user_prefs' },
            { id: 'site_dashboard', group: 'Site', title: 'Loopkit Dashboard', icon: 'dashicons-dashboard', action: 'url', url: LK.dashboardUrl || 'admin.php?page=loopkit' },
            { id: 'site_wp_pages', group: 'Site', title: 'All Pages', icon: 'dashicons-admin-page', action: 'url', url: 'edit.php?post_type=page' }
        ];
    }

    function openFinderModal() {
        closeAllDropdowns();
        var $modal = $( '#lk-finder-modal' ).show();
        var $input = $( '#lk-finder-input' ).val('').focus();
        renderFinderResults( '' );

        if ( ! finderCache ) {
            $.ajax({
                url: LK.restBase + 'finder-items',
                method: 'GET',
                beforeSend: function( xhr ) {
                    xhr.setRequestHeader( 'X-WP-Nonce', LK.restNonce );
                },
                success: function( res ) {
                    if ( res && res.items ) {
                        finderCache = res.items;
                        renderFinderResults( $input.val() );
                    }
                }
            });
        }
    }

    function closeFinderModal() {
        $( '#lk-finder-modal' ).hide();
    }

    function renderFinderResults( query ) {
        query = ( query || '' ).toLowerCase().trim();
        var $res = $( '#lk-finder-results' ).empty();

        var allItems = getStaticFinderItems().concat( finderCache || [] );
        var filtered = allItems.filter( function( itm ) {
            if ( ! query ) return true;
            return (
                ( itm.title && itm.title.toLowerCase().indexOf( query ) !== -1 ) ||
                ( itm.group && itm.group.toLowerCase().indexOf( query ) !== -1 )
            );
        });

        if ( ! filtered.length ) {
            $res.html( '<div class="lk-finder-empty">No results found for "' + escapeHtml( query ) + '"</div>' );
            return;
        }

        var groups = {};
        filtered.forEach( function( itm ) {
            var g = itm.group || 'General';
            groups[ g ] = groups[ g ] || [];
            groups[ g ].push( itm );
        });

        var isFirst = true;
        Object.keys( groups ).forEach( function( gName ) {
            $res.append( '<div class="lk-finder-group-title">' + escapeHtml( gName ) + '</div>' );
            groups[ gName ].forEach( function( item ) {
                var activeCls = isFirst ? ' lk-active' : '';
                isFirst = false;

                var $row = $(
                    '<div class="lk-finder-item' + activeCls + '" data-action="' + item.action + '">' +
                        '<span class="dashicons ' + ( item.icon || 'dashicons-arrow-right-alt2' ) + '"></span>' +
                        '<span class="lk-finder-item-title">' + escapeHtml( item.title ) + '</span>' +
                        ( item.hint ? '<span class="lk-finder-item-hint">' + escapeHtml( item.hint ) + '</span>' : '' ) +
                    '</div>'
                );

                $row.on( 'click', function() {
                    executeFinderItem( item );
                });
                $row.on( 'mouseenter', function() {
                    $( '.lk-finder-item' ).removeClass( 'lk-active' );
                    $( this ).addClass( 'lk-active' );
                });

                $res.append( $row );
            });
        });
    }

    function executeFinderItem( item ) {
        closeFinderModal();
        if ( item.action === 'panel' ) {
            if ( item.panel === 'site_settings' ) {
                showSiteSettingsPanel();
            } else if ( item.panel === 'user_prefs' ) {
                showUserPreferencesPanel();
            } else if ( item.panel === 'settings' ) {
                showSettingsPanel();
            } else if ( item.panel === 'history' ) {
                showHistoryPanel();
            } else {
                showElementsPanel();
            }
        } else if ( item.action === 'url' && item.url ) {
            window.open( item.url, '_blank' );
        }
    }

    function initFinderKeyboardNav() {
        // Global Ctrl+E / Cmd+E handler on window
        $( window ).on( 'keydown', function( e ) {
            if ( ( e.ctrlKey || e.metaKey ) && ( e.key === 'e' || e.key === 'E' ) ) {
                e.preventDefault();
                openFinderModal();
            }
        });

        // Also bridge canvas iframe keydown
        window.addEventListener( 'message', function( e ) {
            if ( e.data && e.data.type === 'finder-shortcut' ) {
                openFinderModal();
            }
        });

        var $modal = $( '#lk-finder-modal' );
        var $input = $( '#lk-finder-input' );

        $input.on( 'input', function() {
            renderFinderResults( $( this ).val() );
        });

        $input.on( 'keydown', function( e ) {
            var $items = $( '.lk-finder-item' );
            var $active = $( '.lk-finder-item.lk-active' );
            var idx = $items.index( $active );

            if ( e.key === 'ArrowDown' ) {
                e.preventDefault();
                if ( idx < $items.length - 1 ) {
                    $items.removeClass( 'lk-active' );
                    $items.eq( idx + 1 ).addClass( 'lk-active' )[0].scrollIntoView({ block: 'nearest' });
                }
            } else if ( e.key === 'ArrowUp' ) {
                e.preventDefault();
                if ( idx > 0 ) {
                    $items.removeClass( 'lk-active' );
                    $items.eq( idx - 1 ).addClass( 'lk-active' )[0].scrollIntoView({ block: 'nearest' });
                }
            } else if ( e.key === 'Enter' ) {
                e.preventDefault();
                if ( $active.length ) {
                    $active.trigger( 'click' );
                }
            } else if ( e.key === 'Escape' ) {
                e.preventDefault();
                closeFinderModal();
            }
        });

        $( '#lk-finder-overlay' ).on( 'click', function() {
            closeFinderModal();
        });
    }

    function checkWidgetSchemas() {
        if ( ! LK.widgets ) return;
        for ( var wKey in LK.widgets ) {
            if ( ! LK.widgets.hasOwnProperty( wKey ) ) continue;
            var w = LK.widgets[ wKey ];
            if ( ! w || ! w.controls || ( ! w.controls.content && ! w.controls.style ) ) {
                console.warn( 'Loopkit Editor Guard: Widget "' + wKey + '" has no registered control schema!' );
            }
        }
    }

    /* =========================================================
       INIT
       ========================================================= */
    $( function() {
        checkWidgetSchemas();

        // Initial history state
        state.history.push({
            data:         JSON.stringify( state.elements ),
            pageSettings: JSON.stringify( collectPageSettingsForSave() ),
            title:        LK.strings.editingStarted || 'Editing Started',
            action:       '',
            sub:          '',
            isInitial:    true,
            time:         new Date()
        });
        state.historyIndex = 0;

        initHistoryUI();
        initStructurePanel();
        initElementsTabs();
        initTemplatesTab();
        initRenameTemplateModal();
        initDeleteTemplateModal();
        initTemplatesLibraryModal();
        fetchTemplatesFromRest();
        initSaveAsGlobalModal();
        initDeleteGlobalModal();
        renderGlobalsList();
        fetchGlobalsFromRest();
        initFinderKeyboardNav();

        // Apply saved user preferences on load
        if ( state.userPreferences ) {
            if ( state.userPreferences.ui_theme ) {
                applyUserTheme( state.userPreferences.ui_theme );
            }
            if ( state.userPreferences.panel_width ) {
                $( '#lk-panel' ).css( 'width', state.userPreferences.panel_width + 'px' );
            }
            if ( state.userPreferences.exit_to ) {
                updateExitToUrl( state.userPreferences.exit_to );
            }
        }

        loadCanvas();

        var $btn = $( '#lk-publish-btn' );
        if ( LK.postStatus === 'publish' ) {
            $btn.text( LK.strings.update );
        } else {
            $btn.text( LK.strings.publish );
        }
    });

})( jQuery );

/* ---------------- Starter library ----------------
 * Ready-made sections and whole pages. Each one arrives as plain element data and goes in
 * through the same path as a saved template, so everything is editable afterwards.
 */
( function ( $ ) {
    'use strict';

    var cache = null;
    var mode = 'blocks';

    function cfg() {
        return window.loopkitEditor || {};
    }

    function request( what, slug ) {
        return $.post( cfg().ajaxUrl, {
            action: 'loopkit_library',
            nonce: cfg().libraryNonce,
            what: what,
            slug: slug || ''
        } );
    }

    function modal() {
        var $box = $( '#lk-starter-modal' );
        if ( $box.length ) return $box;

        $box = $(
            '<div class="lk-modal-overlay" id="lk-starter-modal" style="display:none">' +
                '<div class="lk-modal lk-starter-modal">' +
                    '<div class="lk-modal-head">' +
                        '<h2>Starter Library</h2>' +
                        '<button type="button" class="lk-modal-close" aria-label="Close">&times;</button>' +
                    '</div>' +
                    '<div class="lk-starter-tabs">' +
                        '<button type="button" class="lk-starter-tab lk-active" data-mode="blocks">Sections</button>' +
                        '<button type="button" class="lk-starter-tab" data-mode="pages">Pages</button>' +
                        '<input type="search" class="lk-starter-search" placeholder="Search…">' +
                    '</div>' +
                    '<div class="lk-starter-body"><p class="lk-starter-loading">Loading…</p></div>' +
                '</div>' +
            '</div>'
        ).appendTo( 'body' );

        $box.on( 'click', '.lk-modal-close', function () { $box.hide(); } );
        $box.on( 'click', function ( e ) { if ( e.target === $box[0] ) $box.hide(); } );

        $box.on( 'click', '.lk-starter-tab', function () {
            mode = $( this ).data( 'mode' );
            $box.find( '.lk-starter-tab' ).removeClass( 'lk-active' );
            $( this ).addClass( 'lk-active' );
            paint();
        } );

        $box.on( 'input', '.lk-starter-search', paint );

        $box.on( 'click', '.lk-starter-card', function () {
            var slug = $( this ).data( 'slug' );
            var what = $( this ).data( 'what' );
            var $card = $( this );

            $card.addClass( 'lk-busy' );
            request( what, slug ).done( function ( res ) {
                $card.removeClass( 'lk-busy' );
                if ( ! res || ! res.success ) return;

                // Straight through the template insert path: fresh ids, history, redraw.
                if ( typeof window.LoopkitEditorInsert === 'function' ) {
                    window.LoopkitEditorInsert( { type: 'section', elements: res.data.elements } );
                }
                $box.hide();
            } ).fail( function () { $card.removeClass( 'lk-busy' ); } );
        } );

        return $box;
    }

    function paint() {
        var $box = modal();
        var $body = $box.find( '.lk-starter-body' );
        var term = ( $box.find( '.lk-starter-search' ).val() || '' ).toLowerCase();

        if ( ! cache ) {
            $body.html( '<p class="lk-starter-loading">Loading…</p>' );
            return;
        }

        var html = '';

        if ( mode === 'pages' ) {
            var pages = cache.pages.filter( function ( p ) {
                return ! term || p.title.toLowerCase().indexOf( term ) !== -1;
            } );
            if ( ! pages.length ) {
                $body.html( '<p class="lk-starter-loading">Nothing matches that.</p>' );
                return;
            }
            html += '<div class="lk-starter-grid">';
            pages.forEach( function ( page ) {
                html += '<button type="button" class="lk-starter-card" data-what="page" data-slug="' + page.slug + '">' +
                    '<span class="lk-starter-card-title">' + page.title + '</span>' +
                    '<span class="lk-starter-card-meta">' + page.count + ' sections</span>' +
                    '</button>';
            } );
            html += '</div>';
            $body.html( html );
            return;
        }

        var groups = {};
        cache.blocks.forEach( function ( block ) {
            var hay = ( block.title + ' ' + ( block.keywords || '' ) ).toLowerCase();
            if ( term && hay.indexOf( term ) === -1 ) return;
            ( groups[ block.group ] = groups[ block.group ] || [] ).push( block );
        } );

        var names = Object.keys( groups );
        if ( ! names.length ) {
            $body.html( '<p class="lk-starter-loading">Nothing matches that.</p>' );
            return;
        }

        names.forEach( function ( name ) {
            html += '<h3 class="lk-starter-group">' + name + '</h3><div class="lk-starter-grid">';
            groups[ name ].forEach( function ( block ) {
                html += '<button type="button" class="lk-starter-card" data-what="block" data-slug="' + block.slug + '">' +
                    '<span class="lk-starter-card-title">' + block.title + '</span>' +
                    '<span class="lk-starter-card-meta">Insert</span>' +
                    '</button>';
            } );
            html += '</div>';
        } );

        $body.html( html );
    }

    $( document ).on( 'click', '#lk-starter-library-toggle', function () {
        var $box = modal();
        $box.css( 'display', 'flex' );

        if ( cache ) { paint(); return; }
        request( 'list' ).done( function ( res ) {
            if ( res && res.success ) {
                cache = res.data;
                paint();
            }
        } );
    } );
} )( jQuery );
