/**
 * Qpedia Glossary — popover + archive filter.
 *
 * نسخهٔ ۲:
 * - روی دسکتاپ تولتیپ با CSS نمایش داده می‌شود و کلیک کاربر مستقیم به صفحهٔ اصطلاح می‌رود.
 * - روی لمسی: لمس اول پاپ‌اور را باز می‌کند، لمس دوم لینک را دنبال می‌کند.
 * - فیلتر الفبایی و جست‌وجوی آرشیو /glossary/ (بدون بارگذاری دوباره)
 */
( function () {
	'use strict';

	var finePointer = window.matchMedia
		? window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches
		: false;

	/* ── پاپ‌اور لمسی ── */
	var pop = null;
	var active = null;
	var lastFocus = null;
	var MARGIN = 10;
	var GAP = 10;

	function makePopover() {
		if ( pop ) {
			return pop;
		}

		pop = document.createElement( 'aside' );
		pop.className = 'qpedia-glossary-popover';
		pop.setAttribute( 'role', 'dialog' );
		pop.setAttribute( 'aria-hidden', 'true' );
		pop.innerHTML =
			'<div class="qpedia-glossary-popover__head">' +
				'<strong class="qpedia-glossary-popover__title"></strong>' +
				'<button class="qpedia-glossary-popover__close" type="button" aria-label="بستن تعریف">×</button>' +
			'</div>' +
			'<p class="qpedia-glossary-popover__definition"></p>' +
			'<a class="qpedia-glossary-popover__more" href="#">مطالعهٔ کامل اصطلاح ←</a>';

		document.body.appendChild( pop );

		return pop;
	}

	function place() {
		if ( ! active || ! pop ) {
			return;
		}

		var rect = active.getBoundingClientRect();
		var popRect = pop.getBoundingClientRect();
		var vw = document.documentElement.clientWidth;

		var left = rect.left + rect.width / 2 - popRect.width / 2;
		left = Math.max( MARGIN, Math.min( left, vw - popRect.width - MARGIN ) );

		var side = rect.top > popRect.height + GAP + MARGIN ? 'top' : 'bottom';
		var top = side === 'top'
			? window.scrollY + rect.top - popRect.height - GAP
			: window.scrollY + rect.bottom + GAP;

		var arrow = Math.max( 14, Math.min( rect.left + rect.width / 2 - left, popRect.width - 14 ) );

		pop.dataset.side = side;
		pop.style.left = Math.round( window.scrollX + left ) + 'px';
		pop.style.top = Math.round( top ) + 'px';
		pop.style.setProperty( '--qpedia-glossary-arrow-x', arrow + 'px' );
	}

	function open( term ) {
		if ( active && active !== term ) {
			active.setAttribute( 'aria-expanded', 'false' );
		}

		active = term;
		lastFocus = term;

		var p = makePopover();
		p.querySelector( '.qpedia-glossary-popover__title' ).textContent = term.textContent.trim();
		p.querySelector( '.qpedia-glossary-popover__definition' ).textContent = term.getAttribute( 'data-glossary-definition' ) || '';

		var more = p.querySelector( '.qpedia-glossary-popover__more' );
		var url = term.getAttribute( 'data-glossary-url' ) || term.getAttribute( 'href' );

		if ( url ) {
			more.href = url;
			more.hidden = false;
		} else {
			more.hidden = true;
		}

		term.setAttribute( 'aria-expanded', 'true' );
		p.setAttribute( 'aria-hidden', 'false' );
		p.classList.add( 'is-open' );
		place();
	}

	function close( restore ) {
		if ( ! active || ! pop ) {
			return;
		}

		active.setAttribute( 'aria-expanded', 'false' );
		active = null;
		pop.classList.remove( 'is-open' );
		pop.setAttribute( 'aria-hidden', 'true' );

		if ( restore && lastFocus && lastFocus.focus ) {
			lastFocus.focus( { preventScroll: true } );
		}
	}

	if ( ! finePointer ) {
		document.addEventListener( 'click', function ( event ) {
			var term = event.target.closest ? event.target.closest( '.qpedia-glossary-term' ) : null;

			if ( term ) {
				if ( active === term ) {
					return; // لمس دوم → لینک دنبال شود
				}

				event.preventDefault();
				open( term );
				return;
			}

			if ( pop && pop.contains( event.target ) ) {
				if ( event.target.closest( '.qpedia-glossary-popover__close' ) ) {
					event.preventDefault();
					close( true );
				}
				return;
			}

			close( false );
		}, true );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				close( true );
			}
		} );

		window.addEventListener( 'resize', function () {
			if ( active ) {
				place();
			}
		}, { passive: true } );
	}

	/* ── فیلتر آرشیو ── */
	var grid = document.querySelector( '[data-qp-glossary-grid]' );

	if ( grid ) {
		var cards = Array.prototype.slice.call( grid.querySelectorAll( '.qp-glossary-card' ) );
		var search = document.querySelector( '[data-qp-glossary-search]' );
		var buttons = Array.prototype.slice.call( document.querySelectorAll( '.qp-glossary-alpha__btn' ) );
		var empty = document.querySelector( '[data-qp-glossary-empty]' );
		var currentLetter = 'all';

		var normalize = function ( value ) {
			return ( value || '' )
				.toString()
				.toLowerCase()
				.replace( /ي/g, 'ی' )
				.replace( /ك/g, 'ک' )
				.replace( /\u200c/g, ' ' )
				.trim();
		};

		var apply = function () {
			var query = search ? normalize( search.value ) : '';
			var shown = 0;

			cards.forEach( function ( card ) {
				var matchesLetter = 'all' === currentLetter
					|| card.dataset.fa === currentLetter
					|| card.dataset.en === currentLetter;

				var blob = normalize( ( card.dataset.search || '' ) + ' ' + card.textContent );
				var matchesQuery = '' === query || blob.indexOf( query ) !== -1;

				var visible = matchesLetter && matchesQuery;

				card.hidden = ! visible;

				if ( visible ) {
					shown++;
				}
			} );

			if ( empty ) {
				empty.hidden = shown > 0;
			}
		};

		buttons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				buttons.forEach( function ( other ) {
					other.classList.toggle( 'is-active', other === button );
				} );

				currentLetter = button.dataset.letter || 'all';
				apply();
			} );
		} );

		if ( search ) {
			var timer = null;
			search.addEventListener( 'input', function () {
				window.clearTimeout( timer );
				timer = window.setTimeout( apply, 120 );
			} );

			search.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
					window.clearTimeout( timer );
					apply();
				}
			} );
		}
	}
} )();
