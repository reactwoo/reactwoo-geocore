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
		testMode: 'rule',
		assignments: [],
		selectedAssignment: null,
		compatibility: null,
		lastPreviewUrl: '',
	};

	var PAGE_TYPES = [
		{ value: 'product', label: 'Product' },
		{ value: 'category', label: 'Product category' },
		{ value: 'homepage', label: 'Homepage' },
		{ value: 'page', label: 'Page' },
		{ value: 'post', label: 'Post' },
		{ value: 'shop', label: 'Shop' },
		{ value: 'cart', label: 'Cart' },
		{ value: 'checkout', label: 'Checkout' },
		{ value: 'search', label: 'Search' },
		{ value: 'other', label: 'Other / manual' },
	];

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

	function dialogEl() {
		var modal = modalEl();
		return modal ? modal.querySelector('.rwgc-rule-tester-modal__dialog') : null;
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

	function fetchAssignments(content) {
		var base = cfg().assignmentsUrl || '';
		if (!base || !content || !content.id) {
			return Promise.resolve({ assignments: [] });
		}
		var url = base + '?content_id=' + encodeURIComponent(String(content.id)) + '&content_type=' + encodeURIComponent(content.type || 'page');
		return window.fetch(url, {
			headers: { 'X-WP-Nonce': cfg().nonce || '' },
			credentials: 'same-origin',
		})
			.then(function (res) {
				return res.json();
			})
			.catch(function () {
				return { assignments: [] };
			});
	}

	function fetchCompatibility(ruleId, payload) {
		var url = cfg().compatibilityUrl || '';
		if (!url || !ruleId) {
			state.compatibility = null;
			return Promise.resolve(null);
		}
		var body = {
			rule_id: ruleId,
			content: payload.content || {},
			context: payload.context || {},
		};
		return window.fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg().nonce || '',
			},
			credentials: 'same-origin',
			body: JSON.stringify(body),
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (data) {
				state.compatibility = data;
				updateCompatibilityWarning();
				return data;
			})
			.catch(function () {
				state.compatibility = null;
				updateCompatibilityWarning();
				return null;
			});
	}

	function assignmentOptionsHtml() {
		var html = '<option value="">' + esc(labels().assignmentPlaceholder || '') + '</option>';
		(state.assignments || []).forEach(function (row) {
			var label = (row.element_type || 'element') + ': ' + (row.element_label || row.assignment_id || '');
			if (row.rule_label) {
				label += ' — ' + row.rule_label;
			}
			if (row.mode_label) {
				label += ' — ' + row.mode_label;
			}
			html += '<option value="' + esc(row.assignment_id || '') + '">' + esc(label) + '</option>';
		});
		return html;
	}

	function updateCompatibilityWarning() {
		var wrap = document.getElementById('rwgc-tester-compat-warning');
		if (!wrap) {
			return;
		}
		var compat = state.compatibility;
		var hasReasons = compat && compat.reasons && compat.reasons.length;
		var hasNote = compat && compat.content_note;
		if (!hasReasons && !hasNote) {
			wrap.classList.add('rwgc-is-hidden');
			wrap.innerHTML = '';
			return;
		}
		wrap.classList.remove('rwgc-is-hidden');
		var html = '';
		if (hasNote) {
			html += '<p class="description">' + esc(compat.content_note) + '</p>';
		}
		if (hasReasons) {
			html += '<strong>' + esc(labels().compatibilityWarning || 'Compatibility warning') + '</strong><ul>';
			compat.reasons.forEach(function (line) {
				if (line) {
					html += '<li>' + esc(line) + '</li>';
				}
			});
			html += '</ul>';
		}
		wrap.innerHTML = html;
	}

	function isAppliedMode() {
		return state.testMode === 'applied';
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


	function renderConditionsList(conditions) {
		if (!conditions || !conditions.length) {
			return '<p class="description">' + esc(labels().noConditions || '') + '</p>';
		}
		var html = '<ul>';
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

	function countriesOptionsHtml(selected) {
		var countries = (cfg().bootstrap || {}).countries || [];
		var html = '<option value="">' + esc(labels().countryPlaceholder || 'Choose a country') + '</option>';
		countries.forEach(function (row) {
			html += '<option value="' + esc(row.code) + '"' + (String(row.code) === String(selected) ? ' selected' : '') + '>' + esc(row.label + ' (' + row.code + ')') + '</option>';
		});
		return html;
	}

	function pageTypeOptionsHtml(selected) {
		var html = '';
		PAGE_TYPES.forEach(function (row) {
			html += '<option value="' + esc(row.value) + '"' + (row.value === selected ? ' selected' : '') + '>' + esc(row.label) + '</option>';
		});
		return html;
	}

	function contentOptionsHtml() {
		var boot = cfg().bootstrap || {};
		var html = '<option value="">' + esc(labels().contentNone || '') + '</option>';
		[
			['pages', labels().contentPage || 'Pages'],
			['posts', labels().contentPost || 'Posts'],
			['products', labels().contentProduct || 'Products'],
		].forEach(function (group) {
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
		html += '<option value="manual">' + esc(labels().contentManual || 'Manual URL / path') + '</option>';
		return html;
	}

	function rulesOptionsHtml(selectedId) {
		var rules = (cfg().bootstrap || {}).rules || [];
		var html = '<option value="">' + esc(labels().selectRulePlaceholder || 'Choose a visibility rule') + '</option>';
		rules.forEach(function (rule) {
			html += '<option value="' + esc(rule.id) + '"' + (String(rule.id) === String(selectedId) ? ' selected' : '') + '>' + esc(rule.title) + '</option>';
		});
		return html;
	}

	function countryHelperHtml(payload) {
		if (!payload || !payload.included_countries || !payload.included_countries.length) {
			return '';
		}
		return '<p class="description">' + esc(labels().countryHelper || 'Based on this rule\'s allowed countries.') + '</p>';
	}

	function renderSummaryAside(payload) {
		var title = payload && payload.title ? payload.title : '';
		var html = '<section class="rwgc-rule-tester-section rwgc-rule-tester-condition-summary" aria-live="polite">';
		if (title) {
			html += '<p class="rwgc-rule-tester-condition-summary__title">' + esc(title) + '</p>';
			if (payload.scope_summary) {
				html += '<p class="description"><strong>' + esc('Rule scope') + ':</strong> ' + esc(payload.scope_summary) + '</p>';
			}
			html += '<p class="description" style="margin:0 0 8px;">' + esc(labels().ruleConditions || 'Conditions') + '</p>';
			html += renderConditionsList(payload.conditions || []);
		} else {
			html += '<p class="description">' + esc(labels().selectRulePlaceholder || 'Choose a visibility rule') + '</p>';
		}
		html += '</section>';
		html += '<div id="rwgc-tester-result" class="rwgc-rule-tester-result" aria-live="polite">';
		html += '<p class="rwgc-rule-tester-result__placeholder">' + esc(labels().resultPlaceholder || 'Select a rule and visitor context to test whether this rule would match.') + '</p>';
		html += '</div>';
		return html;
	}

	function renderForm() {
		var payload = state.rulePayload || {};
		var defaults = payload.default_context || {};
		var dialog = dialogEl();
		if (!dialog) {
			return;
		}

		dialog.innerHTML =
			'<header class="rwgc-rule-tester-modal__header">' +
			'<div>' +
			'<h2 id="rwgc-rule-tester-title">' + esc(labels().title || 'Test visibility rule') + '</h2>' +
			'<p class="rwgc-rule-tester-modal__subtitle">' + esc(labels().subtitle || 'Choose a rule, choose where to test it, then simulate a visitor.') + '</p>' +
			'</div>' +
			'<button type="button" class="rwgc-rule-tester-modal__close" aria-label="' + esc(labels().close || 'Close') + '">&times;</button>' +
			'</header>' +
			'<form id="rwgc-rule-tester-form">' +
			'<div id="rwgc-rule-tester-body" class="rwgc-rule-tester-modal__body">' +
			'<div class="rwgc-rule-tester-modal__grid">' +
			'<div class="rwgc-rule-tester-modal__main">' +
			'<section class="rwgc-rule-tester-section">' +
			'<h3>' + esc(labels().testType || 'Test type') + '</h3>' +
			'<div class="rwgc-rule-tester-field">' +
			'<label><input type="radio" name="rwgc_tester_mode" value="rule"' + (!isAppliedMode() ? ' checked' : '') + '> ' + esc(labels().testModeRule || 'Visibility rule') + '</label> ' +
			'<label style="margin-left:12px;"><input type="radio" name="rwgc_tester_mode" value="applied"' + (isAppliedMode() ? ' checked' : '') + '> ' + esc(labels().testModeApplied || 'Applied target / element') + '</label>' +
			'</div>' +
			'</section>' +
			'<section class="rwgc-rule-tester-section' + (isAppliedMode() ? ' rwgc-is-hidden' : '') + '" id="rwgc-tester-rule-section">' +
			'<h3>' + esc(labels().stepRule || 'Rule') + '</h3>' +
			'<div class="rwgc-rule-tester-field rwgc-rule-tester-select">' +
			'<label for="rwgc-tester-rule-search">' + esc(labels().selectRule || 'Visibility rule') + '</label>' +
			'<input type="search" id="rwgc-tester-rule-search" class="rwgc-rule-tester-select__search" placeholder="' + esc(labels().searchPlaceholder || 'Search…') + '" autocomplete="off" />' +
			'<select id="rwgc-tester-rule" name="rule_id" class="rwgc-rule-tester-select__control">' + rulesOptionsHtml(state.openRuleId) + '</select>' +
			'</div>' +
			'<div id="rwgc-tester-rule-presets">' + renderRulePresets(payload) + '</div>' +
			'</section>' +
			'<section class="rwgc-rule-tester-section">' +
			'<h3>' + esc(labels().stepContent || 'Content') + '</h3>' +
			'<p class="rwgc-rule-tester-section__hint">' + esc(labels().contentHelp || '') + '</p>' +
			'<div class="rwgc-rule-tester-field rwgc-rule-tester-select">' +
			'<label for="rwgc-tester-content-search">' + esc(labels().selectContent || 'Content') + '</label>' +
			'<input type="search" id="rwgc-tester-content-search" class="rwgc-rule-tester-select__search" placeholder="' + esc(labels().searchPlaceholder || 'Search…') + '" autocomplete="off" />' +
			'<select id="rwgc-tester-content" name="content_select" class="rwgc-rule-tester-select__control">' + contentOptionsHtml() + '</select>' +
			'</div>' +
			'<div id="rwgc-tester-manual-wrap" class="rwgc-rule-tester-field rwgc-is-hidden">' +
			'<label for="rwgc-tester-manual-url">' + esc(labels().contentManual || 'Manual URL / path') + '</label>' +
			'<input type="text" id="rwgc-tester-manual-url" placeholder="/product/example" />' +
			'</div>' +
			'<div id="rwgc-tester-detected" class="rwgc-rule-tester-detected rwgc-is-hidden"></div>' +
			'</section>' +
			'<section class="rwgc-rule-tester-section' + (!isAppliedMode() ? ' rwgc-is-hidden' : '') + '" id="rwgc-tester-assignment-section">' +
			'<h3>' + esc(labels().stepAssignment || 'Applied target') + '</h3>' +
			'<p class="rwgc-rule-tester-section__hint">' + esc(labels().assignmentHelp || '') + '</p>' +
			'<div class="rwgc-rule-tester-field">' +
			'<label for="rwgc-tester-assignment">' + esc(labels().selectAssignment || 'Elementor assignment') + '</label>' +
			'<select id="rwgc-tester-assignment" class="rwgc-rule-tester-select__control">' + assignmentOptionsHtml() + '</select>' +
			'<p id="rwgc-tester-assignment-empty" class="description rwgc-is-hidden">' + esc(labels().assignmentEmpty || '') + '</p>' +
			'</div>' +
			'</section>' +
			'<section class="rwgc-rule-tester-section">' +
			'<h3>' + esc(labels().stepVisitor || 'Visitor context') + '</h3>' +
			'<div class="rwgc-rule-tester-field__row">' +
			'<div class="rwgc-rule-tester-field rwgc-rule-tester-select">' +
			'<label for="rwgc-tester-country-search">' + esc(labels().country || 'Country') + '</label>' +
			'<input type="search" id="rwgc-tester-country-search" class="rwgc-rule-tester-select__search" placeholder="' + esc(labels().searchPlaceholder || 'Search…') + '" autocomplete="off" />' +
			'<select id="rwgc-tester-country" class="rwgc-rule-tester-select__control">' + countriesOptionsHtml(defaults.country || '') + '</select>' +
			countryHelperHtml(payload) +
			'</div>' +
			'<div class="rwgc-rule-tester-field">' +
			'<label for="rwgc-tester-device">' + esc(labels().device || 'Device') + '</label>' +
			'<select id="rwgc-tester-device">' +
			'<option value="desktop"' + (defaults.device === 'desktop' ? ' selected' : '') + '>Desktop</option>' +
			'<option value="tablet"' + (defaults.device === 'tablet' ? ' selected' : '') + '>Tablet</option>' +
			'<option value="mobile"' + (defaults.device === 'mobile' ? ' selected' : '') + '>Mobile</option>' +
			'</select>' +
			'</div>' +
			'<div class="rwgc-rule-tester-field">' +
			'<label for="rwgc-tester-page-type">' + esc(labels().pageType || 'Page type') + '</label>' +
			'<select id="rwgc-tester-page-type">' + pageTypeOptionsHtml(defaults.page_type || 'product') + '</select>' +
			'</div>' +
			'</div>' +
			'</section>' +
			'<section class="rwgc-rule-tester-section">' +
			'<h3>' + esc(labels().stepTraffic || 'Traffic context') + '</h3>' +
			'<div class="rwgc-rule-tester-presets">' +
			'<strong>' + esc(labels().trafficPresets || 'Traffic presets') + '</strong>' +
			'<div class="rwgc-rule-tester-presets__buttons">' +
			'<button type="button" class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm" data-rwgc-traffic-preset="google_ads">' + esc(labels().presetGoogleAds || 'Google Ads standard UTM') + '</button>' +
			'<button type="button" class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm" data-rwgc-traffic-preset="winter_sale">' + esc(labels().presetWinterSale || 'Winter sale URL') + '</button>' +
			'<button type="button" class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm" data-rwgc-traffic-preset="no_campaign">' + esc(labels().presetNoCampaign || 'No campaign') + '</button>' +
			'</div>' +
			'</div>' +
			'<div class="rwgc-rule-tester-field__row" style="margin-top:12px;">' +
			'<div class="rwgc-rule-tester-field">' +
			'<label for="rwgc-tester-url">' + esc(labels().urlPath || 'URL / path') + '</label>' +
			'<input type="text" id="rwgc-tester-url" value="' + esc(defaults.request_uri || '') + '" />' +
			'</div>' +
			'<div class="rwgc-rule-tester-field">' +
			'<label for="rwgc-tester-utm-source">' + esc(labels().utmSource || 'UTM source') + '</label>' +
			'<input type="text" id="rwgc-tester-utm-source" value="' + esc(defaults.utm_source || '') + '" />' +
			'</div>' +
			'<div class="rwgc-rule-tester-field">' +
			'<label for="rwgc-tester-utm-medium">' + esc(labels().utmMedium || 'UTM medium') + '</label>' +
			'<input type="text" id="rwgc-tester-utm-medium" value="' + esc(defaults.utm_medium || '') + '" />' +
			'</div>' +
			'</div>' +
			'<div class="rwgc-rule-tester-field">' +
			'<label><input type="checkbox" id="rwgc-tester-gclid" /> ' + esc(labels().gclid || 'gclid present') + '</label>' +
			'</div>' +
			'</section>' +
			'</div>' +
			'<aside class="rwgc-rule-tester-modal__aside" id="rwgc-tester-aside">' + renderSummaryAside(payload) + '</aside>' +
			'</div>' +
			'<div id="rwgc-tester-compat-warning" class="rwgc-rule-tester-compat-warning rwgc-is-hidden" aria-live="polite"></div>' +
			'</div>' +
			'<footer class="rwgc-rule-tester-modal__footer">' +
			'<button type="button" class="rwgc-btn rwgc-btn--tertiary" id="rwgc-tester-reset">' + esc(labels().reset || 'Reset') + '</button>' +
			'<button type="button" class="rwgc-btn rwgc-btn--secondary rwgc-rule-tester__cancel">' + esc(labels().close || 'Close') + '</button>' +
			'<button type="submit" class="rwgc-btn rwgc-btn--primary" id="rwgc-tester-run" disabled>' + esc(labels().runTest || 'Run test') + '</button>' +
			'</footer>' +
			'</form>';

		bindFormEvents(payload);
		updateRunButtonState();
		updateDetectedContext();
		updateCompatibilityWarning();
		refreshCompatibilityCheck();
	}

	function renderRulePresets(payload) {
		var presets = (payload && payload.presets) || [];
		if (!presets.length) {
			return '';
		}
		var html = '<div class="rwgc-rule-tester-presets"><strong>' + esc(labels().presets || 'Quick presets') + '</strong><div class="rwgc-rule-tester-presets__buttons">';
		presets.forEach(function (preset, idx) {
			html += '<button type="button" class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm" data-rwgc-preset="' + idx + '">' + esc(preset.label || preset.id) + '</button>';
		});
		html += '</div></div>';
		return html;
	}

	function initSearchableSelect(searchInput, selectEl) {
		if (!searchInput || !selectEl) {
			return;
		}
		var options = Array.prototype.slice.call(selectEl.options);
		searchInput.addEventListener('input', function () {
			var q = searchInput.value.trim().toLowerCase();
			options.forEach(function (opt, idx) {
				if (idx === 0 && opt.value === '') {
					opt.hidden = false;
					return;
				}
				var text = (opt.textContent || '').toLowerCase();
				opt.hidden = q !== '' && text.indexOf(q) === -1;
			});
		});
	}

	function refreshCompatibilityCheck() {
		var payload = buildTesterContextPayload();
		if (isAppliedMode()) {
			var assignment = findSelectedAssignment();
			if (assignment && assignment.rule_id) {
				fetchCompatibility(assignment.rule_id, payload);
			} else {
				state.compatibility = null;
				updateCompatibilityWarning();
			}
			return;
		}
		var ruleId = parseInt((document.getElementById('rwgc-tester-rule') || {}).value, 10) || 0;
		if (!ruleId) {
			state.compatibility = null;
			updateCompatibilityWarning();
			return;
		}
		fetchCompatibility(ruleId, payload);
	}

	function findSelectedAssignment() {
		var select = document.getElementById('rwgc-tester-assignment');
		var val = select ? select.value : '';
		if (!val) {
			return null;
		}
		var found = null;
		(state.assignments || []).forEach(function (row) {
			if (row.assignment_id === val) {
				found = row;
			}
		});
		return found;
	}

	function loadAssignmentsForContent(content) {
		var select = document.getElementById('rwgc-tester-assignment');
		var emptyNote = document.getElementById('rwgc-tester-assignment-empty');
		if (!select) {
			return;
		}
		if (!content || !content.id || !content.type || content.type === 'manual') {
			state.assignments = [];
			state.selectedAssignment = null;
			select.innerHTML = assignmentOptionsHtml();
			if (emptyNote) {
				emptyNote.classList.add('rwgc-is-hidden');
			}
			updateRunButtonState();
			return;
		}
		select.innerHTML = '<option value="">' + esc(labels().loadingAssignments || 'Loading assignments…') + '</option>';
		fetchAssignments(content).then(function (data) {
			state.assignments = (data && data.assignments) || [];
			state.selectedAssignment = null;
			select.innerHTML = assignmentOptionsHtml();
			if (emptyNote) {
				if (!state.assignments.length) {
					emptyNote.classList.remove('rwgc-is-hidden');
				} else {
					emptyNote.classList.add('rwgc-is-hidden');
				}
			}
			updateRunButtonState();
		});
	}

	function bindFormEvents(payload) {
		var form = document.getElementById('rwgc-rule-tester-form');
		var ruleSelect = document.getElementById('rwgc-tester-rule');
		var contentSelect = document.getElementById('rwgc-tester-content');
		var manualWrap = document.getElementById('rwgc-tester-manual-wrap');
		var closeBtn = document.querySelector('.rwgc-rule-tester-modal__close');
		var cancelBtn = document.querySelector('.rwgc-rule-tester__cancel');
		var resetBtn = document.getElementById('rwgc-tester-reset');

		initSearchableSelect(document.getElementById('rwgc-tester-rule-search'), ruleSelect);
		initSearchableSelect(document.getElementById('rwgc-tester-content-search'), contentSelect);
		initSearchableSelect(document.getElementById('rwgc-tester-country-search'), document.getElementById('rwgc-tester-country'));

		if (closeBtn) {
			closeBtn.addEventListener('click', closeModal);
		}
		if (cancelBtn) {
			cancelBtn.addEventListener('click', closeModal);
		}
		if (resetBtn) {
			resetBtn.addEventListener('click', function () {
				state.openRuleId = 0;
				state.rulePayload = null;
				state.testMode = 'rule';
				state.assignments = [];
				state.selectedAssignment = null;
				state.compatibility = null;
				renderForm();
			});
		}
		document.querySelectorAll('input[name="rwgc_tester_mode"]').forEach(function (radio) {
			radio.addEventListener('change', function () {
				state.testMode = radio.value === 'applied' ? 'applied' : 'rule';
				renderForm();
			});
		});
		var assignmentSelect = document.getElementById('rwgc-tester-assignment');
		if (assignmentSelect) {
			assignmentSelect.addEventListener('change', function () {
				state.selectedAssignment = findSelectedAssignment();
				if (state.selectedAssignment && state.selectedAssignment.rule_id) {
					state.openRuleId = state.selectedAssignment.rule_id;
					fetchRule(state.selectedAssignment.rule_id).then(function (data) {
						state.rulePayload = data;
						var aside = document.getElementById('rwgc-tester-aside');
						if (aside) {
							aside.innerHTML = renderSummaryAside(data);
						}
						refreshCompatibilityCheck();
						updateRunButtonState();
					});
				} else {
					refreshCompatibilityCheck();
					updateRunButtonState();
				}
			});
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
			contentSelect.addEventListener('change', onContentChange);
		}

		['rwgc-tester-country', 'rwgc-tester-device', 'rwgc-tester-page-type', 'rwgc-tester-url', 'rwgc-tester-manual-url', 'rwgc-tester-content', 'rwgc-tester-utm-source', 'rwgc-tester-utm-medium', 'rwgc-tester-gclid'].forEach(function (id) {
			var el = document.getElementById(id);
			if (el) {
				el.addEventListener('change', function () {
					updateRunButtonState();
					updateDetectedContext();
					refreshCompatibilityCheck();
				});
				el.addEventListener('input', function () {
					updateRunButtonState();
					updateDetectedContext();
					refreshCompatibilityCheck();
				});
			}
		});

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

		document.querySelectorAll('[data-rwgc-traffic-preset]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				applyTrafficPreset(btn.getAttribute('data-rwgc-traffic-preset'));
			});
		});

		if (form) {
			form.addEventListener('submit', onSubmit);
		}

		function onContentChange() {
			var val = contentSelect.value;
			if ('manual' === val) {
				manualWrap.classList.remove('rwgc-is-hidden');
				loadAssignmentsForContent(null);
				updateDetectedContext();
				updateRunButtonState();
				refreshCompatibilityCheck();
				return;
			}
			manualWrap.classList.add('rwgc-is-hidden');
			if (!val) {
				loadAssignmentsForContent(null);
				updateDetectedContext();
				updateRunButtonState();
				refreshCompatibilityCheck();
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
			var contentPayload = buildContentPayload();
			loadAssignmentsForContent(contentPayload);
			updateDetectedContext();
			updateRunButtonState();
			refreshCompatibilityCheck();
		}
	}

	function applyTrafficPreset(id) {
		var urlInput = document.getElementById('rwgc-tester-url');
		var utmSource = document.getElementById('rwgc-tester-utm-source');
		var utmMedium = document.getElementById('rwgc-tester-utm-medium');
		var gclid = document.getElementById('rwgc-tester-gclid');
		if ('google_ads' === id) {
			if (utmSource) {
				utmSource.value = 'google';
			}
			if (utmMedium) {
				utmMedium.value = 'cpc';
			}
			if (gclid) {
				gclid.checked = true;
			}
		} else if ('winter_sale' === id) {
			if (urlInput) {
				var current = urlInput.value || '';
				urlInput.value = current.indexOf('/winter-sale') >= 0 ? current : '/winter-sale';
			}
			if (utmSource) {
				utmSource.value = '';
			}
			if (utmMedium) {
				utmMedium.value = '';
			}
			if (gclid) {
				gclid.checked = false;
			}
		} else if ('no_campaign' === id) {
			if (utmSource) {
				utmSource.value = '';
			}
			if (utmMedium) {
				utmMedium.value = '';
			}
			if (gclid) {
				gclid.checked = false;
			}
		}
		updateDetectedContext();
		updateRunButtonState();
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
		updateDetectedContext();
		updateRunButtonState();
	}

	function getSelectedContentLabel() {
		var select = document.getElementById('rwgc-tester-content');
		if (!select || !select.value || select.value === 'manual') {
			return '';
		}
		var opt = select.options[select.selectedIndex];
		return opt ? (opt.textContent || '').trim() : '';
	}

	function updateDetectedContext() {
		var wrap = document.getElementById('rwgc-tester-detected');
		if (!wrap) {
			return;
		}
		var contentSelect = document.getElementById('rwgc-tester-content');
		var pageTypeEl = document.getElementById('rwgc-tester-page-type');
		var urlEl = document.getElementById('rwgc-tester-url');
		var val = contentSelect ? contentSelect.value : '';
		var pageLabel = pageTypeEl ? pageTypeEl.options[pageTypeEl.selectedIndex].text : '';
		var url = urlEl ? urlEl.value : '';
		var chips = [];

		if ('manual' === val) {
			var manual = document.getElementById('rwgc-tester-manual-url');
			var manualUrl = manual ? manual.value : '';
			if (!manualUrl && !pageLabel) {
				wrap.classList.add('rwgc-is-hidden');
				wrap.innerHTML = '';
				return;
			}
			chips.push('<span class="rwgc-rule-chip">' + esc(labels().simulatedPageType || 'Simulated page type') + ': ' + esc(pageLabel) + '</span>');
			if (manualUrl) {
				chips.push('<span class="rwgc-rule-chip">' + esc(labels().detectedUrl || 'URL') + ': ' + esc(manualUrl) + '</span>');
			}
			wrap.classList.remove('rwgc-is-hidden');
			wrap.innerHTML = chips.join('');
			return;
		}

		if (!val) {
			wrap.classList.add('rwgc-is-hidden');
			wrap.innerHTML = '';
			return;
		}

		var contentLabel = getSelectedContentLabel();
		if (contentLabel) {
			chips.push('<span class="rwgc-rule-chip">' + esc(labels().selectedContent || 'Selected content') + ': ' + esc(contentLabel) + '</span>');
		}
		chips.push('<span class="rwgc-rule-chip">' + esc(labels().simulatedPageType || 'Simulated page type') + ': ' + esc(pageLabel) + '</span>');
		if (url) {
			chips.push('<span class="rwgc-rule-chip">' + esc(labels().detectedUrl || 'URL') + ': ' + esc(url) + '</span>');
		}
		wrap.classList.remove('rwgc-is-hidden');
		wrap.innerHTML = chips.join('');
	}

	function updateRunButtonState() {
		var btn = document.getElementById('rwgc-tester-run');
		if (!btn) {
			return;
		}
		var country = (document.getElementById('rwgc-tester-country') || {}).value || '';
		var device = (document.getElementById('rwgc-tester-device') || {}).value || '';
		var pageType = (document.getElementById('rwgc-tester-page-type') || {}).value || '';
		if (isAppliedMode()) {
			var assignment = findSelectedAssignment();
			btn.disabled = !(assignment && assignment.rule_id && country && device && pageType);
			return;
		}
		var ruleId = parseInt((document.getElementById('rwgc-tester-rule') || {}).value, 10) || 0;
		btn.disabled = !(ruleId && country && device && pageType);
	}

	function buildContentPayload() {
		var select = document.getElementById('rwgc-tester-content');
		var pageTypeEl = document.getElementById('rwgc-tester-page-type');
		var pageType = pageTypeEl ? pageTypeEl.value : '';
		var val = select ? select.value : '';
		if (!val) {
			return { type: '', id: 0, url: document.getElementById('rwgc-tester-url').value || '', page_type: pageType };
		}
		if ('manual' === val) {
			return {
				type: 'manual',
				id: 0,
				url: document.getElementById('rwgc-tester-manual-url').value || document.getElementById('rwgc-tester-url').value || '',
				page_type: pageType,
			};
		}
		var parts = val.split(':');
		return {
			type: parts[0] || '',
			id: parseInt(parts[1], 10) || 0,
			url: document.getElementById('rwgc-tester-url').value || '',
			page_type: pageType,
		};
	}

	function buildTesterContextPayload() {
		var pageTypeEl = document.getElementById('rwgc-tester-page-type');
		var pageType = pageTypeEl ? pageTypeEl.value : '';
		var content = buildContentPayload();
		var gclidEl = document.getElementById('rwgc-tester-gclid');
		var context = {
			country: (document.getElementById('rwgc-tester-country') || {}).value || '',
			device: (document.getElementById('rwgc-tester-device') || {}).value || '',
			page_type: pageType,
			request_uri: (document.getElementById('rwgc-tester-url') || {}).value || '',
			utm_source: (document.getElementById('rwgc-tester-utm-source') || {}).value || '',
			utm_medium: (document.getElementById('rwgc-tester-utm-medium') || {}).value || '',
			gclid: gclidEl && gclidEl.checked ? '1' : '',
		};
		var assignment = null;
		if (isAppliedMode()) {
			var row = findSelectedAssignment();
			if (row) {
				assignment = {
					assignment_id: row.assignment_id,
					rule_id: row.rule_id,
					mode: row.mode_internal || row.mode || 'show_if',
					element_type: row.element_type,
					element_label: row.element_label,
					product_id: row.product_id || 0,
				};
			}
		}
		return { content: content, context: context, assignment: assignment };
	}

	function onSubmit(e) {
		e.preventDefault();
		var result = document.getElementById('rwgc-tester-result');
		var testerPayload = buildTesterContextPayload();
		var payload;
		var restUrl = cfg().restUrl || '';
		if (isAppliedMode()) {
			var assignment = findSelectedAssignment();
			if (!assignment) {
				return;
			}
			payload = {
				assignment_id: assignment.assignment_id,
				rule_id: assignment.rule_id,
				mode: assignment.mode_internal || assignment.mode || 'show_if',
				target_label: assignment.rule_label || '',
				content: testerPayload.content,
				context: testerPayload.context,
				assignment: testerPayload.assignment,
			};
			restUrl = cfg().assignmentRestUrl || restUrl;
		} else {
			var ruleId = parseInt(document.getElementById('rwgc-tester-rule').value, 10) || 0;
			payload = {
				rule_id: ruleId,
				target_label: state.rulePayload && state.rulePayload.target_label ? state.rulePayload.target_label : '',
				content: testerPayload.content,
				context: testerPayload.context,
			};
		}
		var textarea = document.getElementById('rwgc_portable_targeting');
		if (textarea && document.getElementById('rwgc-visibility-rule-form')) {
			payload.portable_json = textarea.value;
		} else if (cfg().useEditorDraft && textarea && payload.rule_id === cfg().currentRuleId) {
			payload.portable_json = textarea.value;
		}
		result.className = 'rwgc-rule-tester-result';
		result.innerHTML = '<p class="rwgc-rule-tester-result__placeholder">' + esc(labels().testing || 'Testing…') + '</p>';
		window.fetch(restUrl, {
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
				result.className = 'rwgc-rule-tester-result rwgc-rule-tester-result--error';
				result.innerHTML = '<strong>' + esc(labels().errorTitle || 'Test failed') + '</strong>';
			});
	}

	function renderAppliedTargetsTable(targets) {
		if (!targets || !targets.length) {
			return '<p class="description">' + esc(labels().noAppliedTargets || 'This rule was evaluated successfully, but it is not applied to any detected section, product, block, popup, or element on this selected content.') + '</p>';
		}
		var html = '<table class="rwgc-rule-tester-targets"><thead><tr>';
		html += '<th>' + esc(labels().targetColumn || 'Target') + '</th>';
		html += '<th>' + esc(labels().targetTypeColumn || 'Type') + '</th>';
		html += '<th>' + esc(labels().sourceColumn || 'Source') + '</th>';
		html += '<th>' + esc(labels().modeColumn || 'Mode') + '</th>';
		html += '<th>' + esc(labels().outcomeColumn || 'Outcome') + '</th>';
		html += '</tr></thead><tbody>';
		targets.forEach(function (row) {
			var visible = row.visibility === 'visible';
			html += '<tr>';
			html += '<td>' + esc(row.target_label || row.assignment_id || '') + '</td>';
			html += '<td>' + esc(row.target_type || '') + '</td>';
			html += '<td>' + esc(row.source || '') + '</td>';
			html += '<td>' + esc(row.mode_label || row.mode || '') + '</td>';
			html += '<td class="' + (visible ? 'rwgc-outcome--visible' : 'rwgc-outcome--hidden') + '">' + esc(visible ? (labels().visibleTitle || 'Visible') : (labels().hiddenTitle || 'Hidden')) + '</td>';
			html += '</tr>';
			if (row.reason) {
				html += '<tr class="rwgc-rule-tester-targets__reason"><td colspan="5">' + esc(row.reason) + '</td></tr>';
			}
		});
		html += '</tbody></table>';
		return html;
	}

	function bindPreviewActions(resultEl) {
		if (!resultEl) {
			return;
		}
		var openBtn = resultEl.querySelector('[data-rwgc-open-preview]');
		var copyBtn = resultEl.querySelector('[data-rwgc-copy-preview]');
		if (openBtn) {
			openBtn.addEventListener('click', function () {
				if (state.lastPreviewUrl) {
					window.open(state.lastPreviewUrl, '_blank', 'noopener');
				}
			});
		}
		if (copyBtn) {
			copyBtn.addEventListener('click', function () {
				if (!state.lastPreviewUrl) {
					return;
				}
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(state.lastPreviewUrl);
					return;
				}
				var tmp = document.createElement('textarea');
				tmp.value = state.lastPreviewUrl;
				document.body.appendChild(tmp);
				tmp.select();
				try {
					document.execCommand('copy');
				} catch (err) {
					// ignore
				}
				document.body.removeChild(tmp);
			});
		}
	}

	function renderResult(data) {
		var result = document.getElementById('rwgc-tester-result');
		if (!result || !data) {
			return;
		}
		if (data.error && 'incomplete' !== data.status) {
			result.className = 'rwgc-rule-tester-result rwgc-rule-tester-result--error';
			result.innerHTML = '<strong>' + esc(labels().errorTitle || 'Error') + '</strong><p>' + esc(data.error) + '</p>';
			return;
		}
		if ('incomplete' === data.status) {
			result.className = 'rwgc-rule-tester-result rwgc-rule-tester-result--no-match';
			result.innerHTML = '<span class="rwgc-rule-tester-result__badge">' + esc(labels().incompleteTitle || 'CANNOT TEST') + '</span><p>' + esc(data.error || labels().missingContext || '') + '</p>';
			return;
		}
		var isMatch = !!data.matches;
		var title = isMatch ? (labels().matchTitle || 'MATCH') : (labels().noMatchTitle || 'NO MATCH');
		var cls = isMatch ? 'rwgc-rule-tester-result--match' : 'rwgc-rule-tester-result--no-match';
		var html = '';

		html += '<section class="rwgc-rule-tester-result__section">';
		html += '<h4>' + esc(labels().ruleEvaluationTitle || 'Rule evaluation') + '</h4>';

		if (data.visibility) {
			var visible = data.visibility === 'visible';
			html += '<p><strong>' + esc(labels().ruleMatchLabel || 'Rule match') + ':</strong> ' + esc(title) + '</p>';
			html += '<span class="rwgc-rule-tester-result__badge">' + esc(visible ? (labels().visibleTitle || 'VISIBLE') : (labels().hiddenTitle || 'HIDDEN')) + '</span>';
			if (data.mode_label) {
				html += '<p><strong>' + esc(labels().appliedModeLabel || 'Applied mode') + ':</strong> ' + esc(data.mode_label) + '</p>';
			}
			if (data.reason) {
				html += '<p>' + esc(data.reason) + '</p>';
			}
			cls = visible ? 'rwgc-rule-tester-result--match' : 'rwgc-rule-tester-result--no-match';
		} else {
			html += '<p><strong>' + esc(labels().ruleMatchLabel || 'Rule match') + ':</strong> <span class="rwgc-rule-tester-result__badge">' + esc(title) + '</span></p>';
		}

		var docCtx = data.document_context || (data.compatibility && data.compatibility.document_context) || null;
		if (docCtx && docCtx.content_note) {
			html += '<p class="description">' + esc(docCtx.content_note) + '</p>';
		}

		if (data.compatibility && data.compatibility.reasons && data.compatibility.reasons.length) {
			html += '<div class="rwgc-rule-tester-compat-warning"><strong>' + esc(labels().compatibilityWarning || 'Compatibility warning') + '</strong><ul>';
			data.compatibility.reasons.forEach(function (line) {
				if (line) {
					html += '<li>' + esc(line) + '</li>';
				}
			});
			html += '</ul></div>';
		}
		if (data.summary_intro) {
			html += '<p>' + esc(data.summary_intro) + '</p>';
		}
		var lines = data.summary_lines || [];
		if ((!lines || !lines.length) && data.conditions && data.conditions.length) {
			lines = data.conditions.map(function (row) {
				return row.message || '';
			}).filter(Boolean);
		}
		if (lines && lines.length) {
			html += '<ul>';
			lines.forEach(function (line) {
				if (line) {
					html += '<li>' + esc(line) + '</li>';
				}
			});
			html += '</ul>';
		}
		html += '</section>';

		if (!data.visibility) {
			html += '<section class="rwgc-rule-tester-result__section">';
			html += '<h4>' + esc(labels().appliedTargetsTitle || 'Applied targets on selected content') + '</h4>';
			html += renderAppliedTargetsTable(data.applied_targets || []);
			html += '</section>';
		}

		state.lastPreviewUrl = (data.preview && data.preview.url) ? data.preview.url : '';
		if (state.lastPreviewUrl) {
			html += '<section class="rwgc-rule-tester-result__section rwgc-rule-tester-result__preview">';
			html += '<h4>' + esc(labels().previewTitle || 'Preview') + '</h4>';
			html += '<div class="rwgc-rule-tester-presets__buttons">';
			html += '<button type="button" class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm" data-rwgc-open-preview>' + esc(labels().openPreview || 'Open simulated preview') + '</button>';
			html += '<button type="button" class="rwgc-btn rwgc-btn--tertiary rwgc-btn--sm" data-rwgc-copy-preview>' + esc(labels().copyPreview || 'Copy preview link') + '</button>';
			html += '</div>';
			html += '</section>';
		}

		result.innerHTML = html;
		result.className = 'rwgc-rule-tester-result ' + cls;
		bindPreviewActions(result);
	}

	function open(opts) {
		opts = opts || {};
		state.openRuleId = opts.ruleId || cfg().currentRuleId || 0;
		state.rulePayload = null;
		state.testMode = opts.testMode === 'applied' ? 'applied' : 'rule';
		state.assignments = [];
		state.selectedAssignment = null;
		state.compatibility = null;
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
