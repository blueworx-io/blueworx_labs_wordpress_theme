/**
 * Klaviyo connection: the Website forms editor.
 *
 * Adds a row from the template, removes a row, and keeps the "nothing here"
 * line honest. The rows are ordinary form fields, so saving needs nothing
 * from here.
 */
(function () {
	'use strict';

	var list = document.querySelector( '[data-forms-list]' );
	var template = document.querySelector( '[data-form-template]' );
	var add = document.querySelector( '[data-add-form]' );

	if ( ! list || ! template || ! add ) {
		return;
	}

	var empty = list.querySelector( '[data-forms-empty]' );

	function refresh() {
		if ( empty ) {
			empty.hidden = list.querySelectorAll( '[data-form-row]' ).length > 0;
		}
	}

	function nextIndex() {
		var highest = -1;
		list.querySelectorAll( '[data-form-row] [name^="blueworx_klaviyo_forms["]' ).forEach( function ( field ) {
			var match = /blueworx_klaviyo_forms\[(\d+)\]/.exec( field.name );
			if ( match ) {
				highest = Math.max( highest, parseInt( match[ 1 ], 10 ) );
			}
		} );
		return highest + 1;
	}

	add.addEventListener( 'click', function () {
		var html = template.innerHTML.split( '__index__' ).join( String( nextIndex() ) );
		var holder = document.createElement( 'div' );
		holder.innerHTML = html;
		var row = holder.firstElementChild;
		list.insertBefore( row, empty || null );
		refresh();
		var first = row.querySelector( '[data-match]' );
		if ( first ) {
			first.focus();
		}
	} );

	list.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-remove-form]' );
		if ( ! button ) {
			return;
		}
		var row = button.closest( '[data-form-row]' );
		if ( row ) {
			row.remove();
			refresh();
		}
	} );

	refresh();
})();
