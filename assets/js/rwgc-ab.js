(function () {
	'use strict';

	function endpoint() {
		if (window.rwgcAb && window.rwgcAb.endpoint) {
			return String(window.rwgcAb.endpoint);
		}
		return '';
	}

	function request(url, done) {
		var attempts = 0;
		function send() {
			attempts++;
			window.fetch(url, {
				method: 'GET',
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { Accept: 'application/json' }
			}).then(function (response) {
				if (response.status === 429 && attempts < 2) {
					var retry = parseInt(response.headers.get('Retry-After') || '1', 10);
					if (!retry || retry < 1) {
						retry = 1;
					}
					if (retry > 2) {
						retry = 2;
					}
					window.setTimeout(send, retry * 1000);
					return null;
				}
				if (!response.ok) {
					return null;
				}
				return response.json();
			}).then(function (body) {
				if (body) {
					done(body);
				}
			}).catch(function () {});
		}
		send();
	}

	/*
	 * The placeholder already shows A. Replace it only for arm B so an A
	 * visitor never flashes B, and a browser without JavaScript stays on A.
	 */
	function apply(node, body) {
		if (!body || body.arm !== 'b' || typeof body.html !== 'string' || body.html === '') {
			return;
		}
		node.innerHTML = body.html;
		node.setAttribute('data-rwgc-arm', 'b');
	}

	function run() {
		var root = endpoint();
		if (!root || !window.fetch) {
			return;
		}
		var nodes = document.querySelectorAll('[data-rwgc-ab-mode="fragment"]');
		var i;
		for (i = 0; i < nodes.length; i++) {
			(function (node) {
				var testId = parseInt(node.getAttribute('data-rwgc-ab') || '0', 10);
				if (!testId || testId < 1) {
					return;
				}
				var joiner = root.indexOf('?') === -1 ? '?' : '&';
				request(root + joiner + 'test=' + encodeURIComponent(String(testId)), function (body) {
					apply(node, body);
				});
			})(nodes[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}
})();
