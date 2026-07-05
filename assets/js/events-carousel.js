/**
 * ATX Events carousel — turns .atx-events--carousel into a horizontal slider
 * with prev/next buttons, drag-to-scroll and scroll-snap. Vanilla + no deps.
 * Structural CSS is injected inline so the slider works even when the plugin's
 * stylesheet is switched off (Display → "Use plugin styling").
 */
(function () {
	'use strict';

	var STYLE_ID = 'atx-events-carousel-css';

	function injectCriticalCss() {
		if (document.getElementById(STYLE_ID)) {
			return;
		}
		// The plugin stylesheet already carries these rules; only inject the
		// fallback when it is not loaded (Display → plugin styling off).
		if (document.getElementById('atx-ticketing-frontend-css')) {
			return;
		}
		var css =
			'.atx-events--carousel{position:relative;display:block;}' +
			'.atx-events--carousel .atx-events__track{display:flex;gap:1.5rem;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;padding-bottom:.5rem;scrollbar-width:thin;}' +
			'.atx-events--carousel .atx-events__card{scroll-snap-align:start;flex:0 0 auto;}' +
			'.atx-events--carousel.atx-events--cols-1 .atx-events__card{width:100%;}' +
			'.atx-events--carousel.atx-events--cols-2 .atx-events__card{width:calc((100% - 1.5rem)/2);}' +
			'.atx-events--carousel.atx-events--cols-3 .atx-events__card{width:calc((100% - 3rem)/3);}' +
			'.atx-events--carousel.atx-events--cols-4 .atx-events__card{width:calc((100% - 4.5rem)/4);}' +
			'@media(max-width:782px){.atx-events--carousel .atx-events__card{width:80% !important;}}' +
			'.atx-events__nav{position:absolute;top:38%;z-index:2;width:2.4rem;height:2.4rem;border-radius:999px;border:1px solid rgba(0,0,0,.15);background:#fff;color:#111;font-size:1.4rem;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 4px rgba(0,0,0,.15);}' +
			'.atx-events__nav--prev{left:-.6rem;}' +
			'.atx-events__nav--next{right:-.6rem;}' +
			'.atx-events__nav[disabled]{opacity:.35;cursor:default;}';
		var style = document.createElement('style');
		style.id = STYLE_ID;
		style.textContent = css;
		document.head.appendChild(style);
	}

	function initCarousel(root) {
		if (root.dataset.atxCarouselReady === '1') {
			return;
		}
		root.dataset.atxCarouselReady = '1';

		var track = root.querySelector('.atx-events__track');
		var prev = root.querySelector('.atx-events__nav--prev');
		var next = root.querySelector('.atx-events__nav--next');

		if (!track) {
			return;
		}

		function step() {
			return Math.max(240, Math.round(track.clientWidth * 0.85));
		}

		function updateButtons() {
			if (!prev || !next) {
				return;
			}
			var maxScroll = track.scrollWidth - track.clientWidth - 2;
			prev.disabled = track.scrollLeft <= 2;
			next.disabled = track.scrollLeft >= maxScroll;
		}

		if (prev) {
			prev.addEventListener('click', function () {
				track.scrollBy({ left: -step(), behavior: 'smooth' });
			});
		}
		if (next) {
			next.addEventListener('click', function () {
				track.scrollBy({ left: step(), behavior: 'smooth' });
			});
		}

		track.addEventListener('scroll', function () {
			window.requestAnimationFrame(updateButtons);
		});
		window.addEventListener('resize', updateButtons);

		// Pointer drag-to-scroll (desktop niceness; touch already scrolls).
		var dragging = false;
		var startX = 0;
		var startScroll = 0;

		track.addEventListener('pointerdown', function (e) {
			if (e.pointerType === 'mouse' && e.button !== 0) {
				return;
			}
			dragging = true;
			startX = e.clientX;
			startScroll = track.scrollLeft;
			track.style.scrollBehavior = 'auto';
		});
		window.addEventListener('pointermove', function (e) {
			if (!dragging) {
				return;
			}
			track.scrollLeft = startScroll - (e.clientX - startX);
		});
		window.addEventListener('pointerup', function () {
			if (!dragging) {
				return;
			}
			dragging = false;
			track.style.scrollBehavior = 'smooth';
		});

		updateButtons();
	}

	function initAll() {
		injectCriticalCss();
		var roots = document.querySelectorAll('.atx-events--carousel');
		for (var i = 0; i < roots.length; i++) {
			initCarousel(roots[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})();
