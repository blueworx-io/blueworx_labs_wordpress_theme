/**
 * Klaviyo connection: the page's own calls to Klaviyo's script.
 *
 * Klaviyo's script loads async, so the page talks to it through the stand-in
 * object Klaviyo publishes: calls made before the script arrives are queued
 * and run when it does. The stand-in is Klaviyo's own, unchanged.
 *
 * What to say comes from the server in blueworxKlaviyoTracking: who the
 * signed-in person is, which events this page sends, and the product viewed.
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
