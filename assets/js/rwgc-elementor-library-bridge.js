/**
 * Elementor: apply saved visibility rules from library SELECT to portable JSON textarea.
 */
(function ($) {
	'use strict';

	var cfg = window.rwgcElementorLibrary || {};
	var rowsById = {};
	var labels = cfg.labels || {};

	function indexRows() {
		rowsById = {};
		(cfg.library || []).forEach(function (row) {
			if (row && row.id) {
				rowsById[String(row.id)] = row;
			}
		});
	}

	function portableTextarea($panel) {
		var $ta = $panel.find(
			'.elementor-control-egp_portable_geo_targeting textarea, .elementor-control-rwgc_portable_geo_targeting textarea'
		);
		return $ta.length ? $ta.first() : null;
	}

	function appliedRuleInput($panel) {
		var $inp = $panel.find('.elementor-control-rwgc_applied_visibility_rule_id input');
		return $inp.length ? $inp.first() : null;
	}

	function compatibilityNotice($panel) {
		var $wrap = $panel.find('.rwgc-library-compat-notice');
		if (!$wrap.length) {
			$wrap = $('<div class="rwgc-library-compat-notice description" style="margin-top:8px;"></div>');
			$panel.find('.elementor-control-rwgc_visibility_rule_library').after($wrap);
		}
		return $wrap;
	}

	function normalizeVisibilityMode(mode) {
		var raw = String(mode || '').toLowerCase();
		return raw === 'hide_if' || raw === 'hide' || raw === 'restrict' || raw === 'suppress' ? 'hide_if' : 'show_if';
	}

	function syncVisibilityModeControls($panel, mode) {
		var normalized = normalizeVisibilityMode(mode);
		var $rulesMode = $panel.find('.elementor-control-rwgc_visibility_rules_mode select');
		if ($rulesMode.length && $rulesMode.val() !== normalized) {
			$rulesMode.val(normalized).trigger('change');
		}
		var $legacy = $panel.find('.elementor-control-rwgc_visibility_mode input');
		if ($legacy.length) {
			$legacy.val(normalized).trigger('input').trigger('change');
		}
	}

	function syncVisibilityModeFromJson($panel, json) {
		if (!json) {
			return;
		}
		try {
			var doc = typeof json === 'string' ? JSON.parse(json) : json;
			if (doc && doc.mode) {
				syncVisibilityModeControls($panel, doc.mode);
			}
		} catch (e) {
			/* ignore invalid JSON */
		}
	}

	function applyLibraryJson($panel, json) {
		if (!json) {
			return;
		}
		syncVisibilityModeFromJson($panel, json);
		var $ta = portableTextarea($panel);
		if (!$ta || !$ta.length) {
			return;
		}
		if (window.ReactWooRuleBuilder && typeof window.ReactWooRuleBuilder.setValue === 'function') {
			window.ReactWooRuleBuilder.setValue($ta.get(0), json);
		} else {
			$ta.val(json).trigger('input').trigger('change');
		}
	}

	function persistAppliedRuleId($panel, id) {
		var $inp = appliedRuleInput($panel);
		if ($inp && $inp.length) {
			$inp.val(id).trigger('input').trigger('change');
		}
	}

	function syncVisibilityRulesToggle($panel) {
		var on = $panel.find('.elementor-control-rwgc_enable_visibility_rules input[type="checkbox"]').is(':checked');
		var $legacy = $panel.find('.elementor-control-rwgc_use_portable_geo_targeting input');
		if ($legacy.length) {
			var next = on ? 'yes' : '';
			if (String($legacy.val() || '') !== next) {
				$legacy.val(next).trigger('input').trigger('change');
			}
		}
	}

	function rebuildLibrarySelect($select) {
		if (!$select || !$select.length) {
			return;
		}
		if ($select.attr('data-rwgc-library-built') === '1') {
			return;
		}
		var current = String($select.val() || '');
		var compatible = [];
		var attention = [];
		var unavailable = [];

		(cfg.library || []).forEach(function (row) {
			if (!row || !row.id) {
				return;
			}
			var status = row.compatibility && row.compatibility.status ? row.compatibility.status : 'compatible';
			if (status === 'incompatible') {
				unavailable.push(row);
			} else if (status === 'warning') {
				attention.push(row);
			} else {
				compatible.push(row);
			}
		});

		$select.empty();
		$select.append(
			$('<option></option>').val('').text(labels.choosePlaceholder || '— Choose saved visibility rule —')
		);

		function appendGroup(groupLabel, rows, disableIncompatible) {
			if (!rows.length) {
				return;
			}
			var $group = $('<optgroup></optgroup>').attr('label', groupLabel);
			rows.forEach(function (row) {
				var title = row.title || String(row.id);
				if (row.scope_summary) {
					title += ' — ' + row.scope_summary;
				}
				var $opt = $('<option></option>').val(String(row.id)).text(title);
				if (disableIncompatible) {
					$opt.prop('disabled', true);
				}
				if (row.compatibility && row.compatibility.reason) {
					$opt.attr('title', row.compatibility.reason);
				}
				$group.append($opt);
			});
			$select.append($group);
		}

		appendGroup(labels.compatibleGroup || 'Compatible rules', compatible, false);
		appendGroup(labels.attentionGroup || 'Needs attention', attention, false);
		appendGroup(labels.unavailableGroup || 'Not available for this context', unavailable, true);

		if (current && rowsById[current]) {
			var rowStatus = rowsById[current].compatibility ? rowsById[current].compatibility.status : 'compatible';
			if (rowStatus !== 'incompatible') {
				$select.val(current);
			} else {
				$select.val('');
				persistAppliedRuleId($('#elementor-panel-inner'), '');
			}
		}
		$select.attr('data-rwgc-library-built', '1');
	}

	function showCompatibilityNotice($panel, row) {
		var $notice = compatibilityNotice($panel);
		if (!row || !row.compatibility || !row.compatibility.reason) {
			$notice.text('').hide();
			return;
		}
		if (row.compatibility.status === 'compatible') {
			$notice.text('').hide();
			return;
		}
		$notice.text(row.compatibility.reason).show();
	}

	function countryMap() {
		return cfg.countries || window.rwgcGeoCountryOptions || {};
	}

	/**
	 * Saved SELECT2 values are not in the DOM when the control rendered with an
	 * empty options list. Normalize the model value, never the blank DOM value.
	 *
	 * @param {*} value Model value.
	 * @param {boolean} multiple Multi-select.
	 * @return {string|string[]}
	 */
	function normalizeCountrySelection(value, multiple) {
		if (multiple) {
			if (value == null || value === '') {
				return [];
			}
			if (Array.isArray(value)) {
				return value.map(function (code) {
					return String(code);
				}).filter(Boolean);
			}
			if (typeof value === 'object') {
				return Object.keys(value).map(function (key) {
					return String(value[key]);
				}).filter(Boolean);
			}
			return [String(value)];
		}
		if (Array.isArray(value)) {
			return value.length ? String(value[0] || '') : '';
		}
		if (value && typeof value === 'object') {
			var keys = Object.keys(value);
			return keys.length ? String(value[keys[0]] || '') : '';
		}
		return value == null ? '' : String(value);
	}

	function settingFromModel(model, name) {
		if (!model || typeof model.get !== 'function' || typeof model.has !== 'function' || !model.has(name)) {
			return undefined;
		}
		return model.get(name);
	}

	function readSavedCountryValue(name) {
		if (!name || typeof elementor === 'undefined' || typeof elementor.getPanelView !== 'function') {
			return undefined;
		}
		var page = null;
		try {
			page = elementor.getPanelView().getCurrentPageView();
		} catch (e) {
			page = null;
		}
		if (!page || !page.model || typeof page.model.get !== 'function') {
			return undefined;
		}
		// Widget/element panels nest settings. Document settings are the model itself.
		if (page.model.get('elType') || page.model.get('widgetType')) {
			return settingFromModel(page.model.get('settings'), name);
		}
		return settingFromModel(page.model, name);
	}

	function hydrateOneCountrySelect($select, multiple) {
		var countries = countryMap();
		var codes = Object.keys(countries);
		if (!codes.length || !$select.length) {
			return;
		}
		$select.each(function () {
			var $one = $(this);
			if ($one.attr('data-rwgc-countries-built') === '1') {
				return;
			}
			var alreadyFilled = $one.find('option').length > 20;
			if (!alreadyFilled) {
				$one.empty();
				codes.forEach(function (code) {
					$one.append($('<option></option>').val(code).text(String(countries[code] || code)));
				});
			}
			var saved = readSavedCountryValue($one.attr('data-setting'));
			if (saved !== undefined) {
				$one.val(normalizeCountrySelection(saved, multiple));
				$one.attr('data-rwgc-countries-built', '1');
			} else if (alreadyFilled) {
				$one.attr('data-rwgc-countries-built', '1');
			}
			// Namespaced so Elementor's `change` handler does not write the model.
			// A bare `change` runs while options are still empty and saves [].
			if ($one.hasClass('select2-hidden-accessible') || $one.data('select2')) {
				try {
					$one.trigger('change.select2');
				} catch (e) {
					/* ignore */
				}
			}
		});
	}

	function countryChips(countries) {
		return Object.keys(countries).map(function (code) {
			return { value: code, label: String(countries[code] || code) };
		});
	}

	function isAtomicCountryControl(value) {
		if (!value || typeof value !== 'object' || !value.props) {
			return false;
		}
		if (value.bind === 'egp_countries') {
			return true;
		}
		return !!(value.meta && value.meta.rwgcCatalogue === 'countries');
	}

	function fillAtomicControls(node, chips) {
		if (!node || typeof node !== 'object') {
			return;
		}
		if (Array.isArray(node)) {
			for (var i = 0; i < node.length; i++) {
				fillAtomicControls(node[i], chips);
			}
			return;
		}
		if (isAtomicCountryControl(node.value)) {
			var options = node.value.props.options;
			if (!options || !options.length) {
				node.value.props.options = chips.slice();
			}
			return;
		}
		var keys = Object.keys(node);
		for (var k = 0; k < keys.length; k++) {
			var child = node[keys[k]];
			if (child && typeof child === 'object') {
				fillAtomicControls(child, chips);
			}
		}
	}

	function fillClassicCountryOptions(controls, countries) {
		if (!controls || typeof controls !== 'object') {
			return;
		}
		['egp_countries', 'rwgc_route_country_iso2'].forEach(function (name) {
			var control = controls[name];
			if (!control || control.type !== 'select2') {
				return;
			}
			var count = control.options && typeof control.options === 'object' ? Object.keys(control.options).length : 0;
			if (count > 20) {
				return;
			}
			var copy = {};
			Object.keys(countries).forEach(function (code) {
				copy[code] = String(countries[code] || code);
			});
			control.options = copy;
		});
	}

	var filledCatalogues = typeof WeakSet === 'function' ? new WeakSet() : null;

	function fillWidgetCatalogue(entry, countries, chips) {
		if (!entry || typeof entry !== 'object') {
			return;
		}
		if (filledCatalogues && filledCatalogues.has(entry)) {
			return;
		}
		if (entry.atomic_controls) {
			fillAtomicControls(entry.atomic_controls, chips);
		}
		if (entry.controls) {
			fillClassicCountryOptions(entry.controls, countries);
		}
		if (filledCatalogues) {
			filledCatalogues.add(entry);
		}
	}

	function fillLoadedCatalogues() {
		var countries = countryMap();
		if (!Object.keys(countries).length || typeof elementor === 'undefined' || !elementor.widgetsCache) {
			return;
		}
		var chips = countryChips(countries);
		Object.keys(elementor.widgetsCache).forEach(function (type) {
			fillWidgetCatalogue(elementor.widgetsCache[type], countries, chips);
		});
	}

	function installCatalogueFill() {
		fillLoadedCatalogues();
		if (typeof elementor === 'undefined' || typeof elementor.addWidgetsCache !== 'function' || elementor.addWidgetsCache.__rwgcCountries) {
			return;
		}
		var original = elementor.addWidgetsCache;
		function wrapped() {
			var result = original.apply(this, arguments);
			fillLoadedCatalogues();
			return result;
		}
		wrapped.__rwgcCountries = true;
		elementor.addWidgetsCache = wrapped;
	}

	function hydrateCountriesSelect($panel) {
		hydrateOneCountrySelect($panel.find('.elementor-control-egp_countries select'), true);
		hydrateOneCountrySelect($panel.find('.elementor-control-rwgc_route_country_iso2 select'), false);
		hydrateOneCountrySelect($panel.find('.egp-country-select'), true);
	}

	function bindLibrarySelect($panel) {
		syncVisibilityRulesToggle($panel);
		hydrateCountriesSelect($panel);
		var $select = $panel.find('.elementor-control-rwgc_visibility_rule_library select');
		if ($select.length) {
			rebuildLibrarySelect($select);
			var initial = rowsById[String($select.val() || '')];
			showCompatibilityNotice($panel, initial);
		}

		$panel
			.find('.elementor-control-rwgc_enable_visibility_rules input')
			.off('change.rwgcVisRules')
			.on('change.rwgcVisRules', function () {
				syncVisibilityRulesToggle($panel);
			});
		$panel
			.find('.elementor-control-rwgc_visibility_rule_library select')
			.off('change.rwgcLib')
			.on('change.rwgcLib', function () {
				var id = String($(this).val() || '');
				var row = rowsById[id];
				showCompatibilityNotice($panel, row);
				if (!id) {
					persistAppliedRuleId($panel, '');
					return;
				}
				if (row && row.compatibility && row.compatibility.status === 'incompatible') {
					$(this).val('');
					persistAppliedRuleId($panel, '');
					return;
				}
				if (row && row.json) {
					applyLibraryJson($panel, row.json);
				}
				persistAppliedRuleId($panel, id);
			});
	}

	function scan() {
		fillLoadedCatalogues();
		var $panel = $('#elementor-panel-inner');
		if (!$panel.length) {
			return;
		}
		bindLibrarySelect($panel);
	}

	indexRows();

	var scanTimer = null;
	function scheduleScan() {
		if (scanTimer) {
			return;
		}
		scanTimer = setTimeout(function () {
			scanTimer = null;
			scan();
		}, 80);
	}

	$(window).on('elementor:init', function () {
		installCatalogueFill();
		if (window.elementor && elementor.hooks && typeof elementor.hooks.addAction === 'function') {
			elementor.hooks.addAction('elementor/widgets/refreshed', fillLoadedCatalogues);
		}
		scheduleScan();
	});
	$(document).on('elementor:init', scheduleScan);
	installCatalogueFill();

	var root = document.getElementById('elementor-panel-inner');
	if (root) {
		new MutationObserver(scheduleScan).observe(root, { childList: true, subtree: true });
	}
	scheduleScan();
	setTimeout(scheduleScan, 400);
})(jQuery);
