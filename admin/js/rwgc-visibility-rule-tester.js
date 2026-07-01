/**
 * Modal rule tester for visibility rules library.
 *
 * @package ReactWoo_Geo_Core
 */
(function (window, document) {
	'use strict';

	var state = {
		openRuleId: 0,
		rulePayload: null,
	};

	function cfg() {
		return window.rwgcRuleTester || {};
	}

	function labels() {
		return cfg().labels || {};
	}

	function esc(text) {
		var d = document.createElement('div');
		d.textContent = text == null ? '' : String(text);
		return d.innerHTML;
	}

	function modalEl() {
		return document.getElementById('rwgc-rule-tester-modal');
	}

	function bodyEl() {
		return document.getElementById('rwgc-rule-tester-body');
	}

	function openModal() {
		var modal = modalEl();
		if (!modal) {
			return;
		}
		modal.classList.remove('rwgc-is-hidden');
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('rwgc-modal-open');
	}

	function closeModal() {
		var modal = modalEl();
		if (!modal) {
			return;
		}
		modal.classList.add('rwgc-is-hidden');
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('rwgc-modal-open');
	}

	function fetchRule(ruleId) {
		var base = cfg().ruleUrl || '';
		if (!base || !ruleId) {
			return Promise.resolve(null);
		}
		return window.fetch(base + ruleId, {
			headers: { 'X-WP-Nonce': cfg().nonce || '' },
			credentials: 'same-origin',
		})
			.then(function (res) {
				return res.json();
			})
			.catch(function () {
				return null;
			});
	}

	function renderConditions(conditions) {
		if (!conditions || !conditions.length) {
			return '<p class="description">' + esc(labels().noConditions || '') + '</p>';
		}
		var html = '<ul class="rwgc-rule-tester__conditions">';
		conditions.forEach(function (item) {
			html += '<li>' + esc(item.text || '');
			if (item.children && item.children.length) {
				html += '<ul>';
				item.children.forEach(function (child) {
					html += '<li>' + esc(child) + '</li>';
				});
				html += '</ul>';
			}
			html += '</li>';
		});
		html += '</ul>';
		return html;
	}

	function renderPresets(presets) {
		if (!presets || !presets.length) {
			return '';
		}
		var html = '<div class="rwgc-rule-tester__presets"><strong>' + esc(labels().presets || 'Presets') + '</strong><div class="rwgc-rule-tester__preset-buttons">';
		presets.forEach(function (preset, idx) {
			html += '<button type="button" class="button button-small" data-rwgc-preset="' + idx + '">' + esc(preset.label || preset.id) + '</button>';
		});
		html += '</div></div>';
		return html;
	}

	function contentOptionsHtml() {
		var boot = cfg().bootstrap || {};
		var html = '<option value="">' + esc(labels().contentNone || '') + '</option>';
		[['pages', 'Page'], ['posts', 'Post'], ['products', 'Product']].forEach(function (group) {
			var key = group[0];
			var label = group[1];
			var items = boot[key] || [];
			if (!items.length) {
				return;
			}
			html += '<optgroup label="' + esc(label) + '">';
			items.forEach(function (item) {
				html += '<option value="' + esc(item.type + ':' + item.id) + '" data-page-type="' + esc(item.page_type || '') + '" data-url="' + esc(item.url || '') + '">' + esc(item.title || '') + '</option>';
			});
			html += '</optgroup>';
		});
		html += '<option value="manual">' + esc(labels().contentManual || 'Manual URL') + '</option>';
		return html;
	}

	function rulesOptionsHtml(selectedId) {
		var rules = (cfg().bootstrap || {}).rules || [];
		var html = '<option value="">' + esc(labels().selectRule || '') + '</option>';
		rules.forEach(function (rule) {
			html += '<option value="' + esc(rule.id) + '"' + (String(rule.id) === String(selectedId) ? ' selected' : '') + '>' + esc(rule.title) + '</option>';
		});
		return html;
	}

	function renderForm() {
		var payload = state.rulePayload || {};
		var defaults = payload.default_context || {};
		var body = bodyEl();
		if (!body) {
			return;
		}
		body.innerHTML =
			'<div class="rwgc-rule-tester__header">' +
			'<h2 id="rwgc-rule-tester-title">' + esc(labels().title || 'Test visibility rule') + '</h2>' +
			'<button type="button" class="rwgc-rule-tester__close" aria-label="' + esc(labels().close || 'Close') + '">&times;</button>' +
			'</div>' +
			'<form id="rwgc-rule-tester-form" class="rwgc-rule-tester__form">' +
			'<section class="rwgc-rule-tester__section">' +
			'<h3>' + esc(labels().stepRule || 'Rule') + '</h3>' +
			'<p><label for="rwgc-tester-rule">' + esc(labels().selectRule || '') + '</label><br />' +
			'<select id="rwgc-tester-rule" name="rule_id" class="regular-text">' + rulesOptionsHtml(state.openRuleId) + '</select></p>' +
			'<div id="rwgc-tester-conditions" class="rwgc-rule-tester__conditions-wrap">' + renderConditions(payload.conditions || []) + '</div>' +
			renderPresets(payload.presets || []) +
			'</section>' +
			'<section class="rwgc-rule-tester__section">' +
			'<h3>' + esc(labels().stepContent || 'Content') + '</h3>' +
			'<p class="description">' + esc(labels().contentHelp || '') + '</p>' +
			'<p><label for="rwgc-tester-content">' + esc(labels().selectContent || '') + '</label><br />' +
			'<select id="rwgc-tester-content" name="content_select" class="regular-text">' + contentOptionsHtml() + '</select></p>' +
			'<p id="rwgc-tester-manual-wrap" class="rwgc-is-hidden"><label for="rwgc-tester-manual-url">' + esc(labels().contentManual || '') + '</label><br />' +
			'<input type="text" id="rwgc-tester-manual-url" class="regular-text" placeholder="/product/example" /></p>' +
			'</section>' +
			'<section class="rwgc-rule-tester__section">' +
			'<h3>' + esc(labels().stepContext || 'Context') + '</h3>' +
			'<div class="rwgc-rule-tester__grid">' +
			'<p><label for="rwgc-tester-country">' + esc(labels().country || '') + '</label><br /><input type="text" id="rwgc-tester-country" maxlength="2" value="' + esc(defaults.country || '') + '" /></p>' +
			'<p><label for="rwgc-tester-device">' + esc(labels().device || '') + '</label><br />' +
			'<select id="rwgc-tester-device"><option value="desktop"' + (defaults.device === 'desktop' ? ' selected' : '') + '>Desktop</option>' +
			'<option value="mobile"' + (defaults.device === 'mobile' ? ' selected' : '') + '>Mobile</option>' +
			'<option value="tablet"' + (defaults.device === 'tablet' ? ' selected' : '') + '>Tablet</option></select></p>' +
			'<p><label for="rwgc-tester-page-type">' + esc(labels().pageType || '') + '</label><br />' +
			'<select id="rwgc-tester-page-type">' +
			'<option value="product"' + (defaults.page_type === 'product' ? ' selected' : '') + '>Product</option>' +
			'<option value="homepage"' + (defaults.page_type === 'homepage' ? ' selected' : '') + '>Homepage</option>' +
			'<option value="shop"' + (defaults.page_type === 'shop' ? ' selected' : '') + '>Shop</option>' +
			'<option value="category"' + (defaults.page_type === 'category' ? ' selected' : '') + '>Category</option>' +
			'<option value="other"' + (defaults.page_type === 'other' ? ' selected' : '') + '>Other</option>' +
			'</select></p>' +
			'<p><label for="rwgc-tester-url">' + esc(labels().urlPath || '') + '</label><br /><input type="text" id="rwgc-tester-url" class="regular-text" value="' + esc(defaults.request_uri || '') + '" /></p>' +
			'<p><label for="rwgc-tester-utm-source">' + esc(labels().utmSource || '') + '</label><br /><input type="text" id="rwgc-tester-utm-source" value="' + esc(defaults.utm_source || '') + '" /></p>' +
			'<p><label for="rwgc-tester-utm-medium">' + esc(labels().utmMedium || '') + '</label><br /><input type="text" id="rwgc-tester-utm-medium" value="' + esc(defaults.utm_medium || '') + '" /></p>' +
			'</div></section>' +
			'<div class="rwgc-rule-tester__actions">' +
			'<button type="submit" class="button button-primary">' + esc(labels().runTest || 'Run test') + '</button>' +
			'<button type="button" class="button rwgc-rule-tester__cancel">' + esc(labels().close || 'Close') + '</button>' +
			'</div>' +
			'<div id="rwgc-tester-result" class="rwgc-rule-test__result" aria-live="polite"></div>' +
			'</form>';

		bindFormEvents(payload);
	}

	function bindFormEvents(payload) {
		var form = document.getElementById('rwgc-rule-tester-form');
		var ruleSelect = document.getElementById('rwgc-tester-rule');
		var contentSelect = document.getElementById('rwgc-tester-content');
		var manualWrap = document.getElementById('rwgc-tester-manual-wrap');
		var closeBtn = document.querySelector('.rwgc-rule-tester__close');
		var cancelBtn = document.querySelector('.rwgc-rule-tester__cancel');

		if (closeBtn) {
			closeBtn.addEventListener('click', closeModal);
		}
		if (cancelBtn) {
			cancelBtn.addEventListener('click', closeModal);
		}
		if (ruleSelect) {
			ruleSelect.addEventListener('change', function () {
				var id = parseInt(ruleSelect.value, 10) || 0;
				state.openRuleId = id;
				if (!id) {
					state.rulePayload = null;
					renderForm();
					return;
				}
				fetchRule(id).then(function (data) {
					state.rulePayload = data;
					renderForm();
				});
			});
		}
		if (contentSelect) {
			contentSelect.addEventListener('change', function () {
				var val = contentSelect.value;
				if ('manual' === val) {
					manualWrap.classList.remove('rwgc-is-hidden');
					return;
				}
				manualWrap.classList.add('rwgc-is-hidden');
				if (!val) {
					return;
				}
				var opt = contentSelect.options[contentSelect.selectedIndex];
				if (!opt) {
					return;
				}
				var pageType = opt.getAttribute('data-page-type');
				var url = opt.getAttribute('data-url');
				if (pageType) {
					document.getElementById('rwgc-tester-page-type').value = pageType;
				}
				if (url) {
					document.getElementById('rwgc-tester-url').value = url;
				}
			});
		}
		document.querySelectorAll('[data-rwgc-preset]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var idx = parseInt(btn.getAttribute('data-rwgc-preset'), 10);
				var preset = (payload.presets || [])[idx];
				if (!preset || !preset.context) {
					return;
				}
				applyContext(preset.context);
			});
		});
		if (form) {
			form.addEventListener('submit', onSubmit);
		}
	}

	function applyContext(context) {
		if (!context) {
			return;
		}
		if (context.country) {
			document.getElementById('rwgc-tester-country').value = context.country;
		}
		if (context.device) {
			document.getElementById('rwgc-tester-device').value = context.device;
		}
		if (context.page_type) {
			document.getElementById('rwgc-tester-page-type').value = context.page_type;
		}
		if (context.request_uri) {
			document.getElementById('rwgc-tester-url').value = context.request_uri;
		}
		if (context.utm_source) {
			document.getElementById('rwgc-tester-utm-source').value = context.utm_source;
		}
		if (context.utm_medium) {
			document.getElementById('rwgc-tester-utm-medium').value = context.utm_medium;
		}
	}

	function buildContentPayload() {
		var select = document.getElementById('rwgc-tester-content');
		var val = select ? select.value : '';
		if (!val) {
			return { type: '', id: 0, url: document.getElementById('rwgc-tester-url').value || '' };
		}
		if ('manual' === val) {
			return {
				type: 'manual',
				id: 0,
				url: document.getElementById('rwgc-tester-manual-url').value || document.getElementById('rwgc-tester-url').value || '',
			};
		}
		var parts = val.split(':');
		return {
			type: parts[0] || '',
			id: parseInt(parts[1], 10) || 0,
			url: document.getElementById('rwgc-tester-url').value || '',
		};
	}

	function onSubmit(e) {
		e.preventDefault();
		var result = document.getElementById('rwgc-tester-result');
		var ruleId = parseInt(document.getElementById('rwgc-tester-rule').value, 10) || 0;
		var payload = {
			rule_id: ruleId,
			target_label: state.rulePayload && state.rulePayload.target_label ? state.rulePayload.target_label : '',
			content: buildContentPayload(),
			context: {
				country: document.getElementById('rwgc-tester-country').value,
				device: document.getElementById('rwgc-tester-device').value,
				page_type: document.getElementById('rwgc-tester-page-type').value,
				request_uri: document.getElementById('rwgc-tester-url').value,
				utm_source: document.getElementById('rwgc-tester-utm-source').value,
				utm_medium: document.getElementById('rwgc-tester-utm-medium').value,
			},
		};
		var textarea = document.getElementById('rwgc_portable_targeting');
		if (textarea && document.getElementById('rwgc-visibility-rule-form')) {
			payload.portable_json = textarea.value;
		} else if (cfg().useEditorDraft && textarea && ruleId === cfg().currentRuleId) {
			payload.portable_json = textarea.value;
		}
		result.textContent = labels().testing || 'Testing…';
		result.className = 'rwgc-rule-test__result';
		window.fetch(cfg().restUrl || '', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg().nonce || '',
			},
			credentials: 'same-origin',
			body: JSON.stringify(payload),
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (data) {
				renderResult(data);
			})
			.catch(function () {
				result.textContent = labels().errorTitle || 'Test failed';
				result.className = 'rwgc-rule-test__result rwgc-rule-test__result--error';
			});
	}

	function renderResult(data) {
		var result = document.getElementById('rwgc-tester-result');
		if (!result || !data) {
			return;
		}
		if (data.error && 'incomplete' !== data.status) {
			result.innerHTML = '<strong>' + esc(labels().errorTitle || 'Error') + '</strong><p>' + esc(data.error) + '</p>';
			result.className = 'rwgc-rule-test__result rwgc-rule-test__result--error';
			return;
		}
		if ('incomplete' === data.status) {
			result.innerHTML = '<strong>' + esc(labels().incompleteTitle || 'Cannot test') + '</strong><p>' + esc(data.error || labels().missingContext || '') + '</p>';
			result.className = 'rwgc-rule-test__result rwgc-rule-test__result--nomatch';
			return;
		}
		var title = data.matches ? labels().matchTitle : labels().noMatchTitle;
		var cls = data.matches ? 'rwgc-rule-test__result--match' : 'rwgc-rule-test__result--nomatch';
		var html = '<strong>' + esc(title || '') + '</strong>';
		if (data.summary_intro) {
			html += '<p>' + esc(data.summary_intro) + '</p>';
		}
		if (data.summary_lines && data.summary_lines.length) {
			html += '<ul class="rwgc-rule-tester__summary">';
			data.summary_lines.forEach(function (line) {
				if (line) {
					html += '<li>' + esc(line) + '</li>';
				}
			});
			html += '</ul>';
		}
		result.innerHTML = html;
		result.className = 'rwgc-rule-test__result ' + cls;
	}

	function open(opts) {
		opts = opts || {};
		state.openRuleId = opts.ruleId || cfg().currentRuleId || 0;
		state.rulePayload = null;
		openModal();
		if (state.openRuleId) {
			fetchRule(state.openRuleId).then(function (data) {
				state.rulePayload = data;
				renderForm();
			});
		} else {
			renderForm();
		}
	}

	function bindTriggers() {
		document.addEventListener('click', function (e) {
			var trigger = e.target.closest('[data-rwgc-open-rule-tester]');
			if (!trigger) {
				return;
			}
			e.preventDefault();
			open({
				ruleId: parseInt(trigger.getAttribute('data-rule-id') || '0', 10) || 0,
			});
		});
		var modal = modalEl();
		if (modal) {
			modal.addEventListener('click', function (e) {
				if (e.target === modal) {
					closeModal();
				}
			});
		}
		document.addEventListener('keydown', function (e) {
			if ('Escape' === e.key && modal && !modal.classList.contains('rwgc-is-hidden')) {
				closeModal();
			}
		});
	}

	window.RWGCRuleTester = { open: open, close: closeModal };

	document.addEventListener('DOMContentLoaded', bindTriggers);
})(window, document);
