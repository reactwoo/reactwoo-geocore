/**
 * Geo Core → Targeting: copy audience/campaign id to clipboard.
 */
(function () {
	'use strict';

	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'absolute';
		ta.style.left = '-9999px';
		document.body.appendChild(ta);
		ta.select();
		try {
			document.execCommand('copy');
		} finally {
			document.body.removeChild(ta);
		}
		return Promise.resolve();
	}

	document.addEventListener('click', function (ev) {
		var btn = ev.target.closest('.rwgc-portable-copy-id');
		if (!btn) {
			return;
		}
		ev.preventDefault();
		var id = btn.getAttribute('data-copy') || '';
		if (!id) {
			return;
		}
		copyText(id).then(function () {
			var orig = btn.textContent;
			btn.textContent =
				(window.rwgcPortableTargetingAdmin &&
					window.rwgcPortableTargetingAdmin.strings &&
					window.rwgcPortableTargetingAdmin.strings.copied) ||
				'Copied';
			setTimeout(function () {
				btn.textContent = orig;
			}, 1500);
		});
	});
})();
