<?php
/**
 * Admin preview + test panel for visibility rule editor.
 *
 * @package ReactWoo_Geo_Core
 */
(function (window, document) {
	'use strict';

	function cfg() {
		return window.rwgcVisibilityRulePreview || {};
	}

	function restUrl() {
		var c = cfg();
		return c.restUrl || '';
	}

	function nonce() {
		var c = cfg();
		return c.nonce || '';
	}

	function targetLabel() {
		var form = document.getElementById('rwgc-visibility-rule-form');
		if (form && form.getAttribute('data-rwgc-target-label')) {
			return form.getAttribute('data-rwgc-target-label');
		}
		var c = cfg();
		return c.targetLabel || '';
	}

	function esc(text) {
		var d = document.createElement('div');
		d.textContent = text == null ? '' : String(text);
		return d.innerHTML;
	}

	function renderLogicPreview(container, logic) {
		if (!container || !logic) {
			return;
		}
		var html = '';
		if (logic.intro) {
			html += '<p class="rwgc-logic-preview__intro">' + esc(logic.intro) + '</p>';
		}
		if (logic.lines && logic.lines.length) {
			html += '<ol class="rwgc-logic-preview__list">';
			logic.lines.forEach(function (line) {
				html += '<li><span>' + esc(line.text || '') + '</span>';
				if (line.children && line.children.length) {
					html += '<ul class="rwgc-logic-preview__sublist">';
					line.children.forEach(function (child) {
						html += '<li>' + esc(child) + '</li>';
					});
					html += '</ul>';
				}
				html += '</li>';
			});
			html += '</ol>';
		}
		container.innerHTML = html || '<p class="description">' + esc(cfg().emptyLogic || '') + '</p>';
	}

	function fetchPreview(scenario) {
		var textarea = document.getElementById('rwgc_portable_targeting');
		if (!textarea || !restUrl()) {
			return Promise.resolve(null);
		}
		return window.fetch(restUrl(), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce(),
			},
			credentials: 'same-origin',
			body: JSON.stringify({
				portable_json: textarea.value,
				target_label: targetLabel(),
				scenario: scenario || {},
			}),
		})
			.then(function (res) {
				return res.json();
			})
			.catch(function () {
				return null;
			});
	}

	function refreshLogicPreview() {
		var logicBox = document.getElementById('rwgc-rule-logic-preview-body');
		if (!logicBox) {
			return;
		}
		fetchPreview({}).then(function (data) {
			if (data && data.logic_preview) {
				renderLogicPreview(logicBox, data.logic_preview);
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		bindTextareaRefresh();
	});
		var textarea = document.getElementById('rwgc_portable_targeting');
		if (!textarea) {
			return;
		}
		var timer = null;
		var schedule = function () {
			if (timer) {
				window.clearTimeout(timer);
			}
			timer = window.setTimeout(refreshLogicPreview, 350);
		};
		textarea.addEventListener('input', schedule);
		textarea.addEventListener('change', schedule);
	}

	document.addEventListener('DOMContentLoaded', function () {
		bindTestPanel();
		bindTextareaRefresh();
	});
})(window, document);
