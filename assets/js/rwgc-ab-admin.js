(function () {
	'use strict';

	function closestButton(node) {
		while (node && node.nodeType === 1) {
			if (node.classList && node.classList.contains('rwgc-ab-copy-shortcode')) {
				return node;
			}
			node = node.parentNode;
		}
		return null;
	}

	document.addEventListener('click', function (event) {
		var button = closestButton(event.target);
		if (!button) {
			return;
		}
		event.preventDefault();
		var text = button.getAttribute('data-rwgc-ab-shortcode') || '';
		var mark = function () {
			button.setAttribute('data-rwgc-copied', '1');
		};
		var fallbackCopy = function () {
			var area = document.createElement('textarea');
			area.value = text;
			document.body.appendChild(area);
			area.select();
			try {
				document.execCommand('copy');
			} catch (error) {
				void error;
			}
			document.body.removeChild(area);
		};
		if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
			window.navigator.clipboard.writeText(text).then(mark).catch(function () {
				fallbackCopy();
				mark();
			});
			return;
		}
		fallbackCopy();
		mark();
	});
}());
