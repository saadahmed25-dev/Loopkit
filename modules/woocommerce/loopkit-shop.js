/**
 * Loopkit Shop — cart and checkout behaviour.
 */
( function () {
    'use strict';

    function post( data ) {
        var body = new URLSearchParams();
        Object.keys( data ).forEach( function ( k ) { body.append( k, data[ k ] ); } );
        body.append( 'nonce', window.loopkitShop.nonce );
        return fetch( window.loopkitShop.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        } ).then( function ( r ) { return r.json(); } );
    }

    function refreshTotals( data ) {
        [ 'subtotal', 'discount', 'shipping', 'total' ].forEach( function ( key ) {
            document.querySelectorAll( '[data-lk-' + key + ']' ).forEach( function ( el ) {
                el.textContent = ( key === 'discount' ? '-' : '' ) + data[ key ];
            } );
        } );
        document.querySelectorAll( '[data-lk-cart-count]' ).forEach( function ( el ) {
            el.textContent = data.count;
            el.classList.toggle( 'lk-cart-empty-count', data.count === 0 );
        } );
    }

    function repaintCart( html ) {
        var cart = document.querySelector( '.lk-cart' );
        if ( cart && html ) cart.innerHTML = html;
    }

    function say( el, message, isError ) {
        if ( ! el ) { if ( message ) window.alert( message ); return; }
        el.textContent = message || '';
        el.classList.toggle( 'lk-shop-error', !! isError );
    }

    document.addEventListener( 'click', function ( e ) {
        var target = e.target.closest ? e.target : null;
        if ( ! target ) return;

        // Add to cart (works for any button carrying the data attributes)
        var add = target.closest( '[data-lk-add-to-cart]' );
        if ( add ) {
            e.preventDefault();
            var wrap = add.closest( '[data-lk-product]' ) || document;
            var variation = wrap.querySelector( '[data-lk-variation]' );
            var qtyField = wrap.querySelector( '[data-lk-qty]' );
            var message = wrap.querySelector( '[data-lk-message]' );
            add.disabled = true;
            post( {
                action: 'loopkit_cart',
                do: 'add',
                product_id: add.getAttribute( 'data-lk-add-to-cart' ),
                qty: qtyField ? qtyField.value : 1,
                variation: variation ? variation.value : ''
            } ).then( function ( res ) {
                add.disabled = false;
                if ( ! res.success ) { say( message, res.data.message, true ); return; }
                say( message, add.getAttribute( 'data-lk-added-text' ) || 'Added to cart', false );
                refreshTotals( res.data );
                repaintCart( res.data.html );
                document.dispatchEvent( new CustomEvent( 'loopkit:cart:updated', { detail: res.data } ) );
            } );
            return;
        }

        // Remove a line
        var remove = target.closest( '.lk-cart-remove' );
        if ( remove ) {
            e.preventDefault();
            post( { action: 'loopkit_cart', do: 'remove', key: remove.getAttribute( 'data-key' ) } ).then( function ( res ) {
                if ( res.success ) { refreshTotals( res.data ); repaintCart( res.data.html ); }
            } );
        }
    } );

    // Quantity changes
    document.addEventListener( 'change', function ( e ) {
        if ( ! e.target.classList || ! e.target.classList.contains( 'lk-cart-qty' ) ) return;
        post( { action: 'loopkit_cart', do: 'qty', key: e.target.getAttribute( 'data-key' ), qty: e.target.value } )
            .then( function ( res ) { if ( res.success ) { refreshTotals( res.data ); repaintCart( res.data.html ); } } );
    } );

    // Shipping choice changes the totals
    document.addEventListener( 'change', function ( e ) {
        if ( e.target.name !== 'shipping' ) return;
        post( { action: 'loopkit_cart', do: 'totals', shipping: e.target.value } )
            .then( function ( res ) { if ( res.success ) refreshTotals( res.data ); } );
    } );

    document.addEventListener( 'submit', function ( e ) {
        // Coupon
        if ( e.target.classList.contains( 'lk-coupon-form' ) ) {
            e.preventDefault();
            var code = e.target.querySelector( '.lk-coupon-code' ).value;
            var msg = e.target.querySelector( '.lk-coupon-message' );
            post( { action: 'loopkit_cart', do: code ? 'coupon' : 'remove_coupon', code: code } ).then( function ( res ) {
                if ( ! res.success ) { say( msg, res.data.message, true ); return; }
                say( msg, '', false );
                refreshTotals( res.data );
                repaintCart( res.data.html );
            } );
            return;
        }

        // Checkout
        if ( e.target.classList.contains( 'lk-checkout' ) ) {
            e.preventDefault();
            var form = e.target;
            var button = form.querySelector( '.lk-place-order' );
            var note = form.querySelector( '.lk-checkout-message' );
            var data = { action: 'loopkit_checkout' };
            new FormData( form ).forEach( function ( value, key ) { data[ key ] = value; } );

            button.disabled = true;
            say( note, form.getAttribute( 'data-lk-placing' ) || 'Placing your order…', false );

            // Paying by card on this page: charge the card first, then place the order.
            if ( data.payment === 'stripe' && window.LoopkitCard && window.LoopkitCard.ready() ) {
                say( note, 'Confirming your card…', false );
                window.LoopkitCard.pay( data.shipping ).then( function ( intentId ) {
                    data.payment_intent = intentId;
                    post( data ).then( function ( res ) {
                        if ( ! res.success ) { button.disabled = false; say( note, res.data.message, true ); return; }
                        window.location.href = res.data.redirect;
                    } );
                } ).catch( function ( err ) {
                    button.disabled = false;
                    say( note, err && err.message ? err.message : 'The card could not be charged.', true );
                } );
                return;
            }

            post( data ).then( function ( res ) {
                if ( ! res.success ) {
                    button.disabled = false;
                    say( note, res.data.message, true );
                    return;
                }
                window.location.href = res.data.redirect;
            } ).catch( function () {
                button.disabled = false;
                say( note, 'Something went wrong. Please try again.', true );
            } );
        }
    } );
} )();

/* Product gallery thumbnails */
( function () {
    document.addEventListener( 'click', function ( e ) {
        var thumb = e.target.closest ? e.target.closest( '.lk-pgallery-thumb' ) : null;
        if ( ! thumb ) return;
        var gallery = thumb.closest( '.lk-pgallery' );
        var main = gallery.querySelector( '.lk-pgallery-main img' );
        if ( main ) main.src = thumb.getAttribute( 'data-full' );
        gallery.querySelectorAll( '.lk-pgallery-thumb' ).forEach( function ( t ) { t.classList.remove( 'is-active' ); } );
        thumb.classList.add( 'is-active' );
    } );
} )();

/* ---------------- Card payments on this page (Stripe Elements) ----------------
 * The card field is rendered by Stripe inside an iframe, so card numbers go straight to
 * Stripe and never reach this site. We only ever see whether the payment succeeded.
 */
( function () {
    'use strict';

    var stripe = null;
    var card = null;
    var mounted = false;

    function box() {
        return document.querySelector( '[data-lk-card-box]' );
    }

    function mount() {
        var wrap = box();
        if ( mounted || ! wrap || ! window.Stripe || ! window.loopkitShop || ! window.loopkitShop.stripeKey ) return;

        stripe = window.Stripe( window.loopkitShop.stripeKey );
        var elements = stripe.elements();
        var styles = window.getComputedStyle( document.querySelector( '.lk-checkout' ) || document.body );

        card = elements.create( 'card', {
            style: {
                base: {
                    color: styles.color,
                    fontFamily: styles.fontFamily,
                    fontSize: '15px',
                    '::placeholder': { color: '#9ca3af' }
                }
            }
        } );
        card.mount( '#lk-card-element' );
        card.on( 'change', function ( event ) {
            var error = wrap.querySelector( '.lk-card-error' );
            if ( error ) error.textContent = event.error ? event.error.message : '';
        } );
        mounted = true;
    }

    // The card field only appears once card payment is the chosen method.
    function sync() {
        var wrap = box();
        if ( ! wrap ) return;
        var chosen = document.querySelector( 'input[name="payment"]:checked' );
        var show = chosen && chosen.value === 'stripe';
        wrap.hidden = ! show;
        if ( show ) mount();
    }

    document.addEventListener( 'change', function ( e ) {
        if ( e.target.name === 'payment' ) sync();
    } );
    document.addEventListener( 'DOMContentLoaded', sync );
    if ( document.readyState !== 'loading' ) sync();

    window.LoopkitCard = {
        ready: function () {
            return !! ( box() && window.Stripe && window.loopkitShop && window.loopkitShop.stripeKey );
        },
        pay: function ( shipping ) {
            mount();
            if ( ! stripe || ! card ) return Promise.reject( new Error( 'Card field is not ready.' ) );

            var body = new URLSearchParams();
            body.append( 'action', 'loopkit_payment_intent' );
            body.append( 'nonce', window.loopkitShop.nonce );
            body.append( 'shipping', shipping || '' );

            return fetch( window.loopkitShop.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            } ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
                if ( ! res.success ) throw new Error( res.data && res.data.message ? res.data.message : 'Could not start the payment.' );

                return stripe.confirmCardPayment( res.data.clientSecret, {
                    payment_method: { card: card }
                } ).then( function ( result ) {
                    if ( result.error ) throw new Error( result.error.message );
                    return result.paymentIntent.id;
                } );
            } );
        }
    };
} )();

/* ---------------- Product filters and mini cart ---------------- */
( function () {
    'use strict';

    /* Filters apply to every product grid on the page, with no reload. */
    function initFilters( root ) {
        ( root || document ).querySelectorAll( '[data-lk-product-filters]' ).forEach( function ( box ) {
            if ( box.__lkReady ) return;
            box.__lkReady = true;

            var state = { cat: '*', max: parseFloat( box.dataset.max ) || Infinity, stock: false, sort: '' };

            function cards() {
                return Array.prototype.slice.call( document.querySelectorAll( '.lk-product-card[data-lk-product]' ) );
            }

            function apply() {
                var shown = 0;

                cards().forEach( function ( card ) {
                    var cats = ( card.dataset.cats || '' ).split( ' ' );
                    var price = parseFloat( card.dataset.price ) || 0;
                    var inStock = card.dataset.instock === '1';

                    var ok = ( state.cat === '*' || cats.indexOf( state.cat ) !== -1 ) &&
                        price <= state.max &&
                        ( ! state.stock || inStock );

                    card.style.display = ok ? '' : 'none';
                    if ( ok ) shown++;
                } );

                if ( state.sort ) {
                    document.querySelectorAll( '.lk-product-grid' ).forEach( function ( grid ) {
                        var items = Array.prototype.slice.call( grid.querySelectorAll( '.lk-product-card' ) );
                        items.sort( function ( a, b ) {
                            if ( state.sort === 'title' ) return ( a.dataset.title || '' ).localeCompare( b.dataset.title || '' );
                            var pa = parseFloat( a.dataset.price ) || 0, pb = parseFloat( b.dataset.price ) || 0;
                            return state.sort === 'price-asc' ? pa - pb : pb - pa;
                        } );
                        items.forEach( function ( item ) { grid.appendChild( item ); } );
                    } );
                }

                var count = box.querySelector( '[data-lk-filter-count]' );
                if ( count ) count.textContent = shown + ( shown === 1 ? ' product' : ' products' );
            }

            box.addEventListener( 'click', function ( e ) {
                var chip = e.target.closest( '.lk-pfilter-chip' );
                if ( ! chip ) return;
                box.querySelectorAll( '.lk-pfilter-chip' ).forEach( function ( c ) { c.classList.toggle( 'lk-active', c === chip ); } );
                state.cat = chip.dataset.cat;
                apply();
            } );

            var price = box.querySelector( '[data-lk-price]' );
            if ( price ) {
                price.addEventListener( 'input', function () {
                    state.max = parseFloat( price.value );
                    var out = box.querySelector( '[data-lk-price-out]' );
                    if ( out && window.loopkitShop && window.loopkitShop.formatPrice ) {
                        out.textContent = window.loopkitShop.formatPrice( state.max );
                    } else if ( out ) {
                        out.textContent = price.value;
                    }
                    apply();
                } );
            }

            var stock = box.querySelector( '[data-lk-instock]' );
            if ( stock ) stock.addEventListener( 'change', function () { state.stock = stock.checked; apply(); } );

            var sort = box.querySelector( '[data-lk-sort]' );
            if ( sort ) sort.addEventListener( 'change', function () { state.sort = sort.value; apply(); } );

            apply();
        } );
    }

    /* The mini cart panel opens under its button and closes on an outside click. */
    function initMiniCart( root ) {
        ( root || document ).querySelectorAll( '[data-lk-mini-cart]' ).forEach( function ( box ) {
            if ( box.__lkReady ) return;
            box.__lkReady = true;

            var toggle = box.querySelector( '.lk-mc-toggle' );
            var panel = box.querySelector( '.lk-mc-panel' );
            if ( ! toggle || ! panel ) return;

            function close() {
                panel.hidden = true;
                toggle.setAttribute( 'aria-expanded', 'false' );
            }

            toggle.addEventListener( 'click', function ( e ) {
                e.stopPropagation();
                var open = ! panel.hidden;
                if ( open ) { close(); return; }
                panel.hidden = false;
                toggle.setAttribute( 'aria-expanded', 'true' );
            } );

            document.addEventListener( 'click', function ( e ) {
                if ( ! box.contains( e.target ) ) close();
            } );
            document.addEventListener( 'keydown', function ( e ) {
                if ( e.key === 'Escape' ) close();
            } );
        } );
    }

    function initAll( root ) {
        initFilters( root );
        initMiniCart( root );
    }

    document.addEventListener( 'DOMContentLoaded', function () { initAll( document ); } );
    if ( document.readyState !== 'loading' ) initAll( document );

    /* ---------------------------------------------------------------
     *  Quick view
     *
     *  A real dialog: focus moves into it, Tab stays inside while it is
     *  open, Escape closes it, and focus returns to the button that
     *  opened it. A panel you cannot leave with the keyboard is a trap,
     *  and that is worse than not having one.
     * --------------------------------------------------------------- */

    var qvEl = null, qvOpener = null, qvKeydown = null;

    function qvClose() {
        if ( ! qvEl ) return;
        qvEl.remove();
        qvEl = null;
        document.removeEventListener( 'keydown', qvKeydown, true );
        document.documentElement.style.overflow = '';
        if ( qvOpener && document.contains( qvOpener ) ) {
            qvOpener.focus();
        }
        qvOpener = null;
    }

    function qvTrap( e ) {
        if ( ! qvEl ) return;
        if ( e.key === 'Escape' ) { e.preventDefault(); qvClose(); return; }
        if ( e.key !== 'Tab' ) return;
        var f = qvEl.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        if ( ! f.length ) return;
        var first = f[0], last = f[ f.length - 1 ];
        if ( e.shiftKey && document.activeElement === first ) { e.preventDefault(); last.focus(); }
        else if ( ! e.shiftKey && document.activeElement === last ) { e.preventDefault(); first.focus(); }
    }

    function qvOpen( html, title ) {
        qvClose();
        qvEl = document.createElement( 'div' );
        qvEl.className = 'lk-qv-overlay';
        qvEl.setAttribute( 'role', 'dialog' );
        qvEl.setAttribute( 'aria-modal', 'true' );
        qvEl.setAttribute( 'aria-label', title || 'Product' );
        qvEl.innerHTML =
            '<div class="lk-qv-panel">' +
            '  <button type="button" class="lk-qv-close" aria-label="Close">&times;</button>' +
            html +
            '</div>';
        document.body.appendChild( qvEl );
        document.documentElement.style.overflow = 'hidden';

        qvEl.addEventListener( 'click', function ( e ) {
            // The backdrop closes it; a click inside the panel does not.
            if ( e.target === qvEl || e.target.closest( '.lk-qv-close' ) ) qvClose();
        } );
        qvKeydown = qvTrap;
        document.addEventListener( 'keydown', qvKeydown, true );

        var focusable = qvEl.querySelector( '.lk-qv-close' );
        if ( focusable ) focusable.focus();
    }

    document.addEventListener( 'click', function ( e ) {
        var btn = e.target.closest ? e.target.closest( '[data-lk-quick-view]' ) : null;
        if ( ! btn ) return;
        e.preventDefault();
        qvOpener = btn;
        btn.classList.add( 'is-loading' );
        post( {
            action:  'loopkit_quick_view',
            product: btn.getAttribute( 'data-lk-quick-view' ),
            nonce:   ( window.loopkitShop && window.loopkitShop.qvNonce ) || ''
        } ).then( function ( res ) {
            btn.classList.remove( 'is-loading' );
            if ( res && res.success && res.data ) qvOpen( res.data.html, res.data.title );
        } ).catch( function () {
            btn.classList.remove( 'is-loading' );
        } );
    } );

    window.LoopkitShopUI = { init: initAll, quickViewClose: qvClose };
} )();
