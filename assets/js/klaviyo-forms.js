/**
 * Klaviyo connection: catching plain HTML forms.
 *
 * Watches the forms an admin connected by id, and as one submits, sends a
 * copy of what was typed to the site's own address for it. The form's own
 * handler is left alone, and the visitor never waits: the copy goes with
 * sendBeacon where the browser has it, so it survives the page changing.
 *
 * Only text-like fields and tick boxes are sent, each with the label the
 * visitor saw, so the server can find the email and the name by label.
 */
(function () {
	'use strict';

	var config = window.blueworxKlaviyoForms || {};

	if ( ! config.endpoint || ! Array.isArray( config.forms ) ) {
		return;
	}

	var SKIP = [ 'password', 'hidden', 'submit', 'button', 'reset', 'file', 'image' ];

	function labelFor( field, form ) {
		if ( field.id ) {
			var label = form.querySelector( 'label[for="' + field.id.replace( /"/g, '\\"' ) + '"]' );
			if ( label ) {
				return label.textContent.trim();
			}
		}
		var wrapper = field.closest( 'label' );
		if ( wrapper ) {
			return wrapper.textContent.trim();
		}
		return field.getAttribute( 'aria-label' ) || field.getAttribute( 'placeholder' ) || '';
	}

	function fieldsOf( form ) {
		var fields = [];
		Array.prototype.forEach.call( form.elements, function ( field ) {
			if ( ! field.name || SKIP.indexOf( field.type ) !== -1 ) {
				return;
			}
			var entry = { name: field.name, label: labelFor( field, form ), value: '', checked: null };
			if ( field.type === 'checkbox' ) {
				entry.checked = field.checked;
				entry.value = field.checked ? field.value : '';
			} else if ( field.type === 'radio' ) {
				if ( ! field.checked ) {
					return;
				}
				entry.value = field.value;
			} else {
				entry.value = String( field.value || '' ).slice( 0, 500 );
			}
			fields.push( entry );
		} );
		return fields.slice( 0, 40 );
	}

	function send( form ) {
		var payload = JSON.stringify( {
			form: form.id,
			page: window.location.href,
			fields: fieldsOf( form ),
		} );

		if ( navigator.sendBeacon ) {
			navigator.sendBeacon( config.endpoint, new Blob( [ payload ], { type: 'application/json' } ) );
			return;
		}

		fetch( config.endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: payload,
			keepalive: true,
		} ).catch( function () {} );
	}

	config.forms.forEach( function ( id ) {
		var form = document.getElementById( id );
		if ( form && form.tagName === 'FORM' ) {
			form.addEventListener( 'submit', function () {
				send( form );
			} );
		}
	} );
})();
