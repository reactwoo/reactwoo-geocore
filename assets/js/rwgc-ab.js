(function () {
	'use strict';

	var clicksBound = false;

	function endpoint() {
		if (window.rwgcAb && window.rwgcAb.endpoint) {
			return String(window.rwgcAb.endpoint);
		}
		return '';
	}

	function eventEndpoint() {
		if (window.rwgcAb && window.rwgcAb.eventEndpoint) {
			return String(window.rwgcAb.eventEndpoint);
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
					done(null);
					return null;
				}
				return response.json();
			}).then(function (body) {
				if (body) {
					done(body);
				}
			}).catch(function () {
				done(null);
			});
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

	/*
	 * Best-effort beacon. A content blocker can drop it. The server still
	 * counts a goal URL, and it ignores a second view from the same browser.
	 */
	function postEvent(testId, type) {
		var url = eventEndpoint();
		if (!url || !window.fetch || !testId) {
			return;
		}
		window.fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			cache: 'no-store',
			keepalive: true,
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({
				test: testId,
				type: type
			})
		}).catch(function () {});
	}

	function watchView(node) {
		if (!node || node.getAttribute('data-rwgc-ab-view') === '1') {
			return;
		}
		node.setAttribute('data-rwgc-ab-view', '1');
		var testId = parseInt(node.getAttribute('data-rwgc-ab') || '0', 10);
		if (!testId || testId < 1) {
			return;
		}
		function send() {
			postEvent(testId, 'view');
		}
		if (!window.IntersectionObserver) {
			send();
			return;
		}
		var observer = new window.IntersectionObserver(function (entries) {
			var i;
			for (i = 0; i < entries.length; i++) {
				if (entries[i].isIntersecting) {
					observer.disconnect();
					send();
					return;
				}
			}
		});
		observer.observe(node);
	}

	function bindClicks() {
		if (clicksBound || !document.addEventListener) {
			return;
		}
		clicksBound = true;
		document.addEventListener('click', function (event) {
			var target = event.target;
			if (!target || !target.closest) {
				return;
			}
			var wrapper = target.closest('[data-rwgc-ab]');
			if (!wrapper) {
				return;
			}
			var selector = wrapper.getAttribute('data-rwgc-ab-goal') || '';
			if (!selector) {
				return;
			}
			var goal = null;
			try {
				goal = target.closest(selector);
			} catch (error) {
				goal = null;
			}
			if (!goal || !wrapper.contains(goal)) {
				return;
			}
			var testId = parseInt(wrapper.getAttribute('data-rwgc-ab') || '0', 10);
			if (!testId || testId < 1) {
				return;
			}
			postEvent(testId, 'conversion');
		});
	}

	function run() {
		bindClicks();
		var root = endpoint();
		var nodes = document.querySelectorAll('[data-rwgc-ab-mode]');
		var i;
		for (i = 0; i < nodes.length; i++) {
			(function (node) {
				var mode = node.getAttribute('data-rwgc-ab-mode');
				var testId = parseInt(node.getAttribute('data-rwgc-ab') || '0', 10);
				if (!testId || testId < 1) {
					return;
				}
				if (mode === 'server') {
					watchView(node);
					return;
				}
				if (mode !== 'fragment' || !root || !window.fetch) {
					return;
				}
				var joiner = root.indexOf('?') === -1 ? '?' : '&';
				var settled = false;
				function finish(body) {
					if (settled) {
						return;
					}
					settled = true;
					if (body) {
						apply(node, body);
					}
					watchView(node);
				}
				request(root + joiner + 'test=' + encodeURIComponent(String(testId)), finish);
			})(nodes[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}
})();
