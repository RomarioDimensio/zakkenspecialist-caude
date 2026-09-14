/**
 * Filtert de vacature-kaarten op vakgebied als er op een tab geklikt wordt.
 * Puur client-side; er is geen extra API-call nodig omdat alle vacatures van
 * deze entiteit al in de pagina staan.
 */
( function () {
	'use strict';

	function initVacaturesFilter( wrapper ) {
		var tabs  = wrapper.querySelectorAll( '.dzs-vacatures-tab' );
		var cards = wrapper.querySelectorAll( '.dzs-vacature-card' );

		if ( ! tabs.length ) {
			return;
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				var selected = tab.getAttribute( 'data-vakgebied' );

				tabs.forEach( function ( t ) {
					t.classList.toggle( 'is-active', t === tab );
				} );

				cards.forEach( function ( card ) {
					var matches = 'alle' === selected || card.getAttribute( 'data-vakgebied' ) === selected;
					card.classList.toggle( 'dzs-is-hidden', ! matches );
				} );
			} );
		} );
	}

	function init() {
		document.querySelectorAll( '.dzs-vacatures' ).forEach( initVacaturesFilter );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
