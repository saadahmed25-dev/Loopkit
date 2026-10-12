/* Loopkit Dashboard counters. No libraries. */
( function () {
'use strict';

var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

function format( n ) {
return n >= 10000 ? n.toLocaleString() : String( n );
}

function countUp( el ) {
var target = parseInt( el.getAttribute( 'data-count' ), 10 );
if ( isNaN( target ) ) return;

// Reduced motion: show the final number immediately, no animation.
if ( reduced || target === 0 ) {
el.textContent = format( target );
return;
}

var duration = 900;
var start = null;

function step( ts ) {
if ( start === null ) start = ts;
var p = Math.min( ( ts - start ) / duration, 1 );
var eased = 1 - Math.pow( 1 - p, 3 );
el.textContent = format( Math.round( target * eased ) );
if ( p < 1 ) window.requestAnimationFrame( step );
}
window.requestAnimationFrame( step );
}

function run() {
var nodes = document.querySelectorAll( '[data-count]' );
for ( var i = 0; i < nodes.length; i++ ) {
countUp( nodes[i] );
}
}

if ( document.readyState === 'loading' ) {
document.addEventListener( 'DOMContentLoaded', run );
} else {
run();
}

// If the preference changes while the page is open, stop animating.
if ( window.matchMedia ) {
var mq = window.matchMedia( '(prefers-reduced-motion: reduce)' );
var onChange = function() { reduced = mq.matches; run(); };
if ( mq.addEventListener ) { mq.addEventListener( 'change', onChange ); }
else if ( mq.addListener ) { mq.addListener( onChange ); }
}
} )();
