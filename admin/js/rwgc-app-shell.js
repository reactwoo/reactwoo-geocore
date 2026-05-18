/**
 * ReactWoo Geo app shell — responsive section nav scroll hint.
 */
(function () {
	'use strict';

	function initSectionScroll() {
		var nav = document.querySelector('.rwgc-app-shell__section-scroll');
		if (!nav) {
			return;
		}
		var active = nav.querySelector('.rwgc-app-shell__section-link.is-active');
		if (active && typeof active.scrollIntoView === 'function') {
			active.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initSectionScroll);
	} else {
		initSectionScroll();
	}
})();
