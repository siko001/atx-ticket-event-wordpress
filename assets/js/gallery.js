/**
 * Gallery lightbox: clicking a gallery item opens a modal slider
 * (images and videos, arrow keys / swipe friendly). Vanilla JS.
 */
(function () {
	'use strict';

	var items = [];
	var current = 0;
	var overlay = null;

	function collectItems() {
		return Array.prototype.map.call(document.querySelectorAll('.atx-gallery__item'), function (element) {
			var video = element.querySelector('video');
			if (video) {
				return { type: 'video', src: video.currentSrc || video.src };
			}
			return { type: 'image', src: element.getAttribute('href') };
		});
	}

	function render() {
		var stage = overlay.querySelector('.atx-lightbox__stage');
		var item = items[current];
		stage.innerHTML = '';

		var media;
		if (item.type === 'video') {
			media = document.createElement('video');
			media.controls = true;
			media.autoplay = true;
			media.src = item.src;
		} else {
			media = document.createElement('img');
			media.src = item.src;
			media.alt = '';
		}
		media.className = 'atx-lightbox__media';
		media.style.cssText = 'max-width:100%;max-height:86vh;object-fit:contain;border-radius:.5rem;';
		stage.appendChild(media);

		overlay.querySelector('.atx-lightbox__counter').textContent = (current + 1) + ' / ' + items.length;
	}

	function step(delta) {
		current = (current + delta + items.length) % items.length;
		render();
	}

	function close() {
		if (overlay) {
			overlay.remove();
			overlay = null;
			document.removeEventListener('keydown', onKey);
			document.body.style.overflow = '';
		}
	}

	function onKey(event) {
		if (event.key === 'Escape') {
			close();
		} else if (event.key === 'ArrowRight') {
			step(1);
		} else if (event.key === 'ArrowLeft') {
			step(-1);
		}
	}

	function open(index) {
		items = collectItems();
		if (!items.length) {
			return;
		}
		current = index;

		overlay = document.createElement('div');
		overlay.className = 'atx-lightbox';
		// Critical styles inline so the modal works even when the theme
		// overrides or a stale cached stylesheet lacks .atx-lightbox rules.
		overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.9);display:flex;align-items:center;justify-content:center;';
		overlay.innerHTML =
			'<button type="button" class="atx-lightbox__close" aria-label="Close">&times;</button>' +
			'<button type="button" class="atx-lightbox__nav atx-lightbox__nav--prev" aria-label="Previous">&#10094;</button>' +
			'<div class="atx-lightbox__stage"></div>' +
			'<button type="button" class="atx-lightbox__nav atx-lightbox__nav--next" aria-label="Next">&#10095;</button>' +
			'<span class="atx-lightbox__counter"></span>';
		document.body.appendChild(overlay);
		document.body.style.overflow = 'hidden';

		var stage = overlay.querySelector('.atx-lightbox__stage');
		stage.style.cssText = 'max-width:88vw;max-height:86vh;display:flex;align-items:center;justify-content:center;';

		overlay.querySelectorAll('.atx-lightbox__close, .atx-lightbox__nav').forEach(function (control) {
			control.style.cssText += ';position:absolute;background:rgba(255,255,255,.15);color:#fff;border:0;border-radius:999px;width:44px;height:44px;font-size:22px;cursor:pointer;';
		});
		overlay.querySelector('.atx-lightbox__close').style.cssText += ';top:1rem;right:1rem;';
		overlay.querySelector('.atx-lightbox__nav--prev').style.cssText += ';left:1rem;top:50%;transform:translateY(-50%);';
		overlay.querySelector('.atx-lightbox__nav--next').style.cssText += ';right:1rem;top:50%;transform:translateY(-50%);';
		overlay.querySelector('.atx-lightbox__counter').style.cssText = 'position:absolute;bottom:1rem;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.75);font-size:.85rem;';

		overlay.querySelector('.atx-lightbox__close').addEventListener('click', close);
		overlay.querySelector('.atx-lightbox__nav--prev').addEventListener('click', function () { step(-1); });
		overlay.querySelector('.atx-lightbox__nav--next').addEventListener('click', function () { step(1); });
		overlay.addEventListener('click', function (event) {
			if (event.target === overlay) {
				close();
			}
		});

		// Basic swipe support.
		var touchX = null;
		overlay.addEventListener('touchstart', function (event) { touchX = event.touches[0].clientX; }, { passive: true });
		overlay.addEventListener('touchend', function (event) {
			if (touchX === null) {
				return;
			}
			var delta = event.changedTouches[0].clientX - touchX;
			if (Math.abs(delta) > 40) {
				step(delta < 0 ? 1 : -1);
			}
			touchX = null;
		}, { passive: true });

		document.addEventListener('keydown', onKey);
		render();
	}

	document.addEventListener('click', function (event) {
		var link = event.target.closest('.atx-gallery__item');
		if (!link) {
			return;
		}
		event.preventDefault();
		var all = Array.prototype.slice.call(document.querySelectorAll('.atx-gallery__item'));
		open(all.indexOf(link));
	});
})();
