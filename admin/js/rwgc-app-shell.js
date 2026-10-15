/**
 * ReactWoo Geo app shell — responsive scroll alignment and overflow affordances.
 */
(function () {
	'use strict';

	function alignActiveItem(container) {
		if (!container) {
			return;
		}
		var active = container.querySelector('.is-active');
		if (!active || typeof active.scrollIntoView !== 'function') {
			return;
		}
		// Instant alignment. A smooth scroll on load aborts Chrome's
		// cross-document view transition and rejects with InvalidStateError.
		var align = function () {
			active.scrollIntoView({
				inline: 'center',
				block: 'nearest',
				behavior: 'auto'
			});
		};
		var transition = document.activeViewTransition;
		if (transition && transition.finished && typeof transition.finished.finally === 'function') {
			transition.finished.finally(align);
			return;
		}
		align();
	}

	function syncFadeState(container) {
		if (!container) {
			return;
		}
		var overflow = container.scrollWidth > container.clientWidth + 4;
		container.classList.toggle('rwgc-scroll-fade', overflow);
	}

	function initScrollableNav(container) {
		if (!container) {
			return;
		}
		alignActiveItem(container);
		syncFadeState(container);
		container.addEventListener('scroll', function () {
			syncFadeState(container);
		});
		window.addEventListener('resize', function () {
			syncFadeState(container);
		});
	}

	function initShellNavs() {
		var selectors = [
			'.rwgc-app-shell__module-list',
			'.rwgc-app-shell__section-scroll',
			'.rwgc-app-shell__settings-subnav-scroll'
		];
		selectors.forEach(function (selector) {
			var nodes = document.querySelectorAll(selector);
			nodes.forEach(initScrollableNav);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initShellNavs);
	} else {
		initShellNavs();
	}
})();
