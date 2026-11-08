/**
 * The Klaviyo panel in the block editor's sidebar: a switch and an event
 * name, saved as post meta with the page. Plain JavaScript on the React
 * WordPress already loads: no build step.
 */
(function () {
	'use strict';

	if ( ! window.wp || ! window.blueworxKlaviyoPageEvent ) {
		return;
	}

	var config = window.blueworxKlaviyoPageEvent;
	var h = wp.element.createElement;
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! Panel || ! wp.plugins || ! wp.data ) {
		return;
	}

	function KlaviyoEventPanel() {
		var meta = wp.data.useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var editPost = wp.data.useDispatch( 'core/editor' ).editPost;
		var name = meta[ config.metaKey ] || '';

		// The switch can be on with the name still empty while somebody types,
		// so it has a state of its own; an empty name on save is simply off.
		var onState = wp.element.useState( name !== '' );
		var on = onState[ 0 ];
		var setOn = onState[ 1 ];

		function setName( value ) {
			var next = {};
			next[ config.metaKey ] = value;
			editPost( { meta: next } );
		}

		var children = [
			h( wp.components.ToggleControl, {
				key: 'toggle',
				__nextHasNoMarginBottom: true,
				label: config.copy.label,
				help: on ? config.copy.help : config.copy.off,
				checked: on,
				onChange: function ( value ) {
					setOn( !! value );
					if ( ! value ) {
						setName( '' );
					}
				},
			} ),
		];

		if ( on ) {
			children.push(
				h( wp.components.TextControl, {
					key: 'name',
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
					label: config.copy.name,
					help: config.copy.nameHelp,
					placeholder: config.copy.placeholder,
					value: name,
					onChange: setName,
				} )
			);
		}

		return h( Panel, { name: 'blueworx-klaviyo-page-event', title: config.copy.title, className: 'blueworx-klaviyo-page-event' }, children );
	}

	wp.plugins.registerPlugin( 'blueworx-klaviyo-page-event', {
		render: KlaviyoEventPanel,
		icon: null,
	} );
}() );
