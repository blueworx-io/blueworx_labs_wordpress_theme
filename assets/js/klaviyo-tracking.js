/**
 * Klaviyo connection: the page's own calls to Klaviyo's script.
 *
 * Klaviyo's script loads async, so the page talks to it through the stand-in
 * object Klaviyo publishes: calls made before the script arrives are queued
 * and run when it does. The stand-in is Klaviyo's own, unchanged.
 *
 * What to say comes from the server in blueworxKlaviyoTracking: who the
 * signed-in person is, which events this page sends, and the product viewed.
 *
 * With cookie consent on, this whole script is held back by the consent tool
 * until the visitor agrees to marketing cookies, so by the time it runs they
 * have. If they later withdraw, the consent tool says so with a
 * surecookie_changed event: Klaviyo's cookie is cleared and nothing more is
 * sent from this page.
 */
(function () {
	'use strict';

	// Klaviyo's published stand-in for the klaviyo object.
	/* eslint-disable */
	!function(){if(!window.klaviyo){window._klOnsite=window._klOnsite||[];try{window.klaviyo=new Proxy({},{get:function(n,i){return"push"===i?function(){var n;(n=window._klOnsite).push.apply(n,arguments)}:function(){for(var n=arguments.length,o=new Array(n),w=0;w<n;w++)o[w]=arguments[w];var t="function"==typeof o[o.length-1]?o.pop():void 0,e=new Promise((function(n){window._klOnsite.push([i].concat(o,[function(i){t&&t(i),n(i)}]))}));return e}}})}catch(n){window.klaviyo=window.klaviyo||[],window.klaviyo.push=function(){var n;(n=window._klOnsite).push.apply(n,arguments)}}}}();
	/* eslint-enable */

	var config = window.blueworxKlaviyoTracking || {};
	var klaviyo = window.klaviyo;

	if ( ! klaviyo ) {
		return;
	}

	/**
	 * Clears a cookie on this host and every parent domain, which is where
	 * Klaviyo sets its own.
	 *
	 * @param {string} name Cookie name.
	 */
	function clearCookie( name ) {
		var parts = window.location.hostname.split( '.' );
		var expired = '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';

		document.cookie = name + expired;

		while ( parts.length > 1 ) {
			document.cookie = name + expired + '; domain=.' + parts.join( '.' );
			document.cookie = name + expired + '; domain=' + parts.join( '.' );
			parts.shift();
		}
	}

	// Consent withdrawn: Klaviyo's cookie goes, and this page says nothing more.
	window.addEventListener( 'surecookie_changed', function ( event ) {
		var detail = event && event.detail;
		var marketing = detail && detail.preferences && detail.preferences.marketing === true;

		if ( marketing ) {
			return;
		}

		window.blueworxKlaviyoStopped = true;
		clearCookie( '__kla_id' );
	} );

	if ( window.blueworxKlaviyoStopped ) {
		return;
	}

	if ( config.identify && config.identify.email ) {
		klaviyo.identify( config.identify );
	}

	( config.events || [] ).forEach( function ( event ) {
		if ( event && event.name ) {
			klaviyo.track( event.name, event.properties || {} );
		}
	} );

	if ( config.viewedItem && config.viewedItem.Title ) {
		klaviyo.trackViewedItem( config.viewedItem );
	}
})();
