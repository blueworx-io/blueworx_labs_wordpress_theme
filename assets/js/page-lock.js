/**
 * The Page lock panel in the block editor's sidebar.
 *
 * Administrators get a switch bound to the lock. Everyone else sees, when the
 * page is locked, a plain line saying what they can still change. Plain
 * JavaScript on the React WordPress already loads: no build step.
 */
(function () {
	'use strict';

	if ( ! window.wp || ! window.blueworxPageLock ) {
		return;
	}

	var config = window.blueworxPageLock;
	var h = wp.element.createElement;
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! Panel || ! wp.plugins || ! wp.data ) {
		return;
	}

	function PageLockPanel() {
		var meta = wp.data.useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var editor = wp.data.useDispatch( 'core/editor' );
		var editPost = editor.editPost;
		var locked = !! meta[ config.metaKey ];

		// A locked page's notice has to be readable without a click, so the
		// panel opens itself the first time it draws a locked page. The
		// editor remembers a panel the person then closes.
		var panelName = 'blueworx-page-lock/blueworx-page-lock';
		var isOpen = wp.data.useSelect( function ( select ) {
			return select( 'core/editor' ).isEditorPanelOpened( panelName );
		}, [] );
		wp.element.useEffect( function () {
			if ( locked && ! isOpen ) {
				editor.toggleEditorPanelOpened( panelName );
			}
		}, [ locked ] );

		var body;
		if ( config.canManage ) {
			body = h( wp.components.ToggleControl, {
				__nextHasNoMarginBottom: true,
				label: config.copy.label,
				help: locked ? config.copy.help : config.copy.unlocked,
				checked: locked,
				onChange: function ( value ) {
					var next = {};
					next[ config.metaKey ] = !! value;
					editPost( { meta: next } );
				},
			} );
		} else if ( locked ) {
			body = h( wp.components.Notice, { status: 'info', isDismissible: false }, config.copy.locked );
		} else {
			return null;
		}

		return h( Panel, { name: 'blueworx-page-lock', title: config.copy.title, className: 'blueworx-page-lock' }, body );
	}

	wp.plugins.registerPlugin( 'blueworx-page-lock', {
		render: PageLockPanel,
		icon: null,
	} );
}() );
