
/* Repeater rows: add and remove */
( function () {
    'use strict';

    document.addEventListener( 'click', function ( e ) {
        var add = e.target.closest( '.lk-rep-add' );
        if ( add ) {
            e.preventDefault();
            var wrap = add.closest( '.lk-rep' );
            var rows = wrap.querySelector( '.lk-rep-rows' );
            var row = rows.children[ rows.children.length - 1 ].cloneNode( true );
            var index = rows.children.length;

            row.querySelectorAll( 'input' ).forEach( function ( field ) {
                field.value = '';
                field.name = field.name.replace( /\[(\d+)\]/, '[' + index + ']' );
            } );
            rows.appendChild( row );
            return;
        }

        var remove = e.target.closest( '.lk-rep-remove' );
        if ( ! remove ) return;
        e.preventDefault();
        var list = remove.closest( '.lk-rep-rows' );
        var current = remove.closest( '.lk-rep-row' );

        if ( list.children.length === 1 ) {
            current.querySelectorAll( 'input' ).forEach( function ( f ) { f.value = ''; } );
            return;
        }
        current.remove();

        // keep the row numbers in order so nothing is lost on save
        Array.prototype.forEach.call( list.children, function ( row, i ) {
            row.querySelectorAll( 'input' ).forEach( function ( field ) {
                field.name = field.name.replace( /\[(\d+)\]/, '[' + i + ']' );
            } );
        } );
    } );
} )();
