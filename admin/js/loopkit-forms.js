/* Loopkit admin form controls: progressive enhancement only.
   Adds a button + filename around file inputs so they stop looking native.
   Nothing about behaviour, labels, names or values is changed: the real input
   stays in the DOM, keeps its name, and is still the thing that is clicked,
   focused and read. Checkboxes, radios, selects and the colour picker are
   handled entirely in CSS. */
( function () {
'use strict';

var LABEL = 'Choose File';
var LABEL_NO = 'No file chosen';

function enhanceFileInputs( root ) {
var inputs = ( root || document ).querySelectorAll( 'input[type="file"]' );
for ( var i = 0; i < inputs.length; i++ ) {
var input = inputs[ i ];
if ( input.dataset.lkEnhanced === '1' ) continue;
input.dataset.lkEnhanced = '1';

// If something already wrapped it, leave it alone.
if ( input.parentNode && input.parentNode.classList.contains( 'lk-file-wrap' ) ) continue;

var wrap  = document.createElement( 'span' );
wrap.className = 'lk-file-wrap';

var button = document.createElement( 'button' );
button.type = 'button';
button.className = 'lk-file-button';
button.textContent = LABEL;

var name = document.createElement( 'span' );
name.className = 'lk-file-name';
name.textContent = input.value ? input.value.split( /[\\/]/ ).pop() : LABEL_NO;

input.parentNode.insertBefore( wrap, input );
wrap.appendChild( button );
wrap.appendChild( name );
wrap.appendChild( input );
input.classList.add( 'lk-file-input' );

// The button proxies a click on the real input. That is the whole
// behaviour: the browser dialog, the name attribute and the value are
// all still the native input's.
button.addEventListener( 'click', function ( e ) {
e.preventDefault();
input.click();
} );

input.addEventListener( 'change', function () {
name.textContent = input.value ? input.value.split( /[\\/]/ ).pop() : LABEL_NO;
} );
}
}

function run() { enhanceFileInputs( document ); }

if ( document.readyState === 'loading' ) {
document.addEventListener( 'DOMContentLoaded', run );
} else {
run();
}

// Re-run when a panel or modal injects new markup.
if ( window.MutationObserver ) {
var pending = null;
var obs = new MutationObserver( function () {
if ( pending ) return;
pending = window.setTimeout( function () { pending = null; run(); }, 300 );
} );
var start = function () {
if ( document.body ) obs.observe( document.body, { childList: true, subtree: true } );
};
if ( document.body ) start(); else document.addEventListener( 'DOMContentLoaded', start );
}
} )();
