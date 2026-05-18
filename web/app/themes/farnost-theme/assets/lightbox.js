/**
 * Shared lightbox carousel pre `farnost/gallery` blok aj `core/image`.
 *
 * Princíp: jeden overlay v <body>, delegated click handler na document scanuje
 * targety s `[data-farnost-lightbox]`. Pri otvorení sa zostaví items[] zo
 * všetkých „súrodencov" v rovnakom kontajneri — tým gallery item otvorí carousel
 * cez celú galériu, kým core/image otvorí carousel s jedným itemom (counter
 * a šípky skryté).
 *
 * Kontajner je `.farnost-gallery` pre gallery items; pre core/image je „kontajner"
 * sám element (single-item carousel).
 *
 * Klávesové skratky: Esc = close, ←/→ = nav, Tab = focus loop.
 * Touch: horizontálny swipe (threshold 60px) prepína fotky.
 */

( function () {
	'use strict';

	const ATTR = 'data-farnost-lightbox';
	const FULL = 'data-full-src';
	let overlay = null;
	let imgEl = null;
	let captionEl = null;
	let counterEl = null;
	let prevBtn = null;
	let nextBtn = null;
	let items = [];
	let idx = 0;
	let prevFocus = null;

	function ensureOverlay() {
		if ( overlay ) return overlay;
		overlay = document.createElement( 'div' );
		overlay.className = 'farnost-lightbox';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-label', 'Galéria fotiek' );
		overlay.hidden = true;
		overlay.innerHTML = [
			'<button type="button" class="farnost-lightbox__close" aria-label="Zavrieť">✕</button>',
			'<button type="button" class="farnost-lightbox__nav farnost-lightbox__nav--prev" aria-label="Predchádzajúca">‹</button>',
			'<figure class="farnost-lightbox__stage">',
			'  <img class="farnost-lightbox__img" alt="" />',
			'  <figcaption class="farnost-lightbox__caption"></figcaption>',
			'</figure>',
			'<button type="button" class="farnost-lightbox__nav farnost-lightbox__nav--next" aria-label="Ďalšia">›</button>',
			'<div class="farnost-lightbox__counter" aria-live="polite"></div>',
		].join( '' );
		document.body.appendChild( overlay );

		imgEl = overlay.querySelector( '.farnost-lightbox__img' );
		captionEl = overlay.querySelector( '.farnost-lightbox__caption' );
		counterEl = overlay.querySelector( '.farnost-lightbox__counter' );
		prevBtn = overlay.querySelector( '.farnost-lightbox__nav--prev' );
		nextBtn = overlay.querySelector( '.farnost-lightbox__nav--next' );

		overlay.querySelector( '.farnost-lightbox__close' ).addEventListener( 'click', close );
		prevBtn.addEventListener( 'click', () => step( -1 ) );
		nextBtn.addEventListener( 'click', () => step( 1 ) );
		overlay.addEventListener( 'click', ( e ) => {
			if ( e.target === overlay ) close();
		} );
		bindSwipe();
		return overlay;
	}

	function bindSwipe() {
		let startX = null;
		overlay.addEventListener( 'touchstart', ( e ) => {
			if ( e.touches.length !== 1 ) return;
			startX = e.touches[ 0 ].clientX;
		}, { passive: true } );
		overlay.addEventListener( 'touchend', ( e ) => {
			if ( startX === null ) return;
			const dx = e.changedTouches[ 0 ].clientX - startX;
			startX = null;
			if ( Math.abs( dx ) < 60 ) return;
			step( dx < 0 ? 1 : -1 );
		} );
	}

	function gatherItems( target ) {
		// Gallery items zdieľajú spoločný .farnost-gallery container — všetky
		// jeho figury s lightbox atribútom sa stanú carouselom. Pre samostatný
		// obrázok (core/image) je „carousel" jednoprvkový.
		const container = target.closest( '.farnost-gallery' );
		if ( container ) {
			return Array.from( container.querySelectorAll( '[' + ATTR + ']' ) );
		}
		return [ target ];
	}

	function open( target ) {
		ensureOverlay();
		items = gatherItems( target );
		idx = items.indexOf( target );
		if ( idx < 0 ) idx = 0;
		prevFocus = document.activeElement;
		overlay.hidden = false;
		document.body.classList.add( 'farnost-lightbox-open' );
		document.addEventListener( 'keydown', onKey );
		render();
		// Focus na close button — bezpečný štart pre keyboard navigáciu.
		overlay.querySelector( '.farnost-lightbox__close' ).focus();
	}

	function close() {
		if ( ! overlay || overlay.hidden ) return;
		overlay.hidden = true;
		document.body.classList.remove( 'farnost-lightbox-open' );
		document.removeEventListener( 'keydown', onKey );
		if ( prevFocus && prevFocus.focus ) prevFocus.focus();
	}

	function step( delta ) {
		if ( items.length < 2 ) return;
		idx = ( idx + delta + items.length ) % items.length;
		render();
	}

	function render() {
		const node = items[ idx ];
		const full = node.getAttribute( FULL )
			|| ( node.querySelector( 'img' ) || {} ).src
			|| node.getAttribute( 'href' )
			|| '';
		const inner = node.querySelector( 'img' );
		const alt = inner ? inner.alt || '' : '';
		// Caption priority: data-caption atribút (našíme tam textom z gallery
		// editora, dostupný aj keď showCaptions=false) → figcaption → core
		// .wp-element-caption sibling pre core/image.
		let captionText = node.getAttribute( 'data-caption' ) || '';
		if ( ! captionText ) {
			const captionNode = node.querySelector( 'figcaption, .farnost-gallery__caption, .wp-element-caption' );
			captionText = captionNode ? captionNode.textContent.trim() : '';
		}

		imgEl.src = full;
		imgEl.alt = alt;
		captionEl.textContent = captionText;
		captionEl.hidden = captionText === '';

		const many = items.length > 1;
		prevBtn.hidden = ! many;
		nextBtn.hidden = ! many;
		counterEl.hidden = ! many;
		if ( many ) {
			counterEl.textContent = ( idx + 1 ) + ' / ' + items.length;
		}
	}

	function onKey( e ) {
		if ( e.key === 'Escape' ) { close(); return; }
		if ( e.key === 'ArrowLeft' ) { step( -1 ); return; }
		if ( e.key === 'ArrowRight' ) { step( 1 ); return; }
	}

	function onClick( e ) {
		const target = e.target.closest( '[' + ATTR + ']' );
		if ( ! target ) return;
		// Ak je item zároveň <a> (napr. feed grid linkne na detail článku),
		// rešpektujeme defaultnú navigáciu — lightbox v takom prípade
		// nevhadzujeme.
		if ( target.tagName === 'A' ) return;
		e.preventDefault();
		open( target );
	}

	document.addEventListener( 'click', onClick );
} )();
