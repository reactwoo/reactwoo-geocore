/**
 * Elementor: apply saved visibility rules from library SELECT to portable JSON textarea.
 */
(function ($) {
	'use strict';

	var cfg = window.rwgcElementorLibrary || {};
	var rowsById = {};
	var labels = cfg.labels || {};

	var statusById = {};
	var pendingStatusIds = [];
	var pendingStatusCallbacks = [];
	var statusTimer = null;
	var statusInflight = {};

	function indexRows() {
		rowsById = {};
		(cfg.library || []).forEach(function (row) {
			if (row && row.id) {
				rowsById[String(row.id)] = row;
			}
		});
	}

	function indexStatuses(map) {
		Object.keys(map || {}).forEach(function (id) {
			if (map[id]) {
				statusById[String(id)] = map[id];
			}
		});
	}

	function fallbackMessages(id) {
		function fill(template) {
			return String(template || '').replace('%s', String(id)).replace('%d', String(id));
		}
		return {
			show_if: fill(labels.missingShowIf),
			hide_if: fill(labels.missingHideIf),
			variant: fill(labels.missingVariant),
		};
	}

	function unresolvedStatus(id) {
		return {
			id: id,
			status: 'deleted',
			title: '',
			resolvable: false,
			page_variant: false,
			messages: fallbackMessages(id),
		};
	}

	function requestStatuses(ids, done) {
		var missing = false;
		(ids || []).forEach(function (id) {
			id = String(id || '');
			if (!id || statusById[id]) {
				return;
			}
			missing = true;
			if (!statusInflight[id]) {
				pendingStatusIds.push(id);
				statusInflight[id] = true;
			}
		});
		if (!missing) {
			if (typeof done === 'function') {
				done();
			}
			return;
		}
		if (typeof done === 'function') {
			pendingStatusCallbacks.push(done);
		}
		if (!pendingStatusIds.length || statusTimer) {
			return;
		}
		statusTimer = setTimeout(flushStatuses, 40);
	}

	function finishStatusBatch(ids, callbacks) {
		ids.forEach(function (id) {
			if (!statusById[String(id)]) {
				statusById[String(id)] = unresolvedStatus(id);
			}
			delete statusInflight[id];
		});
		callbacks.forEach(function (fn) {
			fn();
		});
		if (pendingStatusIds.length) {
			if (!statusTimer) {
				statusTimer = setTimeout(flushStatuses, 40);
			}
			return;
		}
		if (pendingStatusCallbacks.length && !Object.keys(statusInflight).length) {
			var rest = pendingStatusCallbacks.splice(0, pendingStatusCallbacks.length);
			rest.forEach(function (fn) {
				fn();
			});
		}
	}

	function flushStatuses() {
		statusTimer = null;
		var ids = pendingStatusIds.splice(0, 100);
		var callbacks = pendingStatusCallbacks.splice(0, pendingStatusCallbacks.length);
		var lookup = cfg.statusLookup || {};
		if (!ids.length || !lookup.ajaxUrl) {
			finishStatusBatch(ids, callbacks);
			return;
		}
		$.post(lookup.ajaxUrl, {
			action: lookup.action,
			nonce: lookup.nonce,
			ids: ids.join(','),
		})
			.done(function (res) {
				var rules = res && res.success && res.data && res.data.rules ? res.data.rules : {};
				indexStatuses(rules);
			})
			.always(function () {
				finishStatusBatch(ids, callbacks);
			});
	}

	function librarySelect($panel) {
		var $select = $panel.find('.elementor-control-rwgc_visibility_rule_library select');
		if ($select.length) {
			return $select;
		}
		return $panel.find('[data-setting="rwgc_visibility_rule_library"]');
	}

	function currentRuleId($panel) {
		var fromSelect = String(librarySelect($panel).val() || '');
		if (fromSelect) {
			return fromSelect;
		}
		var $applied = $panel.find(
			'.elementor-control-rwgc_applied_visibility_rule_id input, [data-setting="rwgc_applied_visibility_rule_id"]'
		);
		var fromApplied = String($applied.first().val() || '');
		if (fromApplied) {
			return fromApplied;
		}
		// Elementor leaves the SELECT blank when the saved id is not one of its options.
		// The model still holds the id; reading it is what keeps a stale reference.
		var fromLibrary = readSavedCountryValue('rwgc_visibility_rule_library');
		if (fromLibrary) {
			return String(fromLibrary);
		}
		var fromAppliedModel = readSavedCountryValue('rwgc_applied_visibility_rule_id');
		return fromAppliedModel ? String(fromAppliedModel) : '';
	}

	function statusNotice($panel) {
		var $anchor = $panel.find('.elementor-control-rwgc_visibility_rule_library');
		if (!$anchor.length) {
			$anchor = $panel.find('[data-setting="rwgc_visibility_rule_library"]').closest('.elementor-control');
		}
		if (!$anchor.length) {
			return $();
		}
		var $wrap = $anchor.next('.rwgc-library-rule-status');
		if (!$wrap.length) {
			$wrap = $('<div class="rwgc-library-rule-status" style="margin:8px 20px 4px;"></div>');
			$anchor.after($wrap);
		}
		return $wrap;
	}

	function renderStatusWarning($panel) {
		var $notice = statusNotice($panel);
		if (!$notice.length) {
			return;
		}
		var id = currentRuleId($panel);
		if (!id) {
			$notice.attr('data-rwgc-status-sig', '').empty().hide();
			return;
		}
		var row = statusById[String(id)];
		if (!row) {
			if ($notice.attr('data-rwgc-status-wait') !== String(id)) {
				$notice.attr('data-rwgc-status-wait', String(id));
				requestStatuses([id], function () {
					$notice.attr('data-rwgc-status-wait', '');
					renderStatusWarning($panel);
				});
			}
			return;
		}
		if (row.resolvable) {
			$notice.attr('data-rwgc-status-sig', '').empty().hide();
			return;
		}
		var mode = normalizeVisibilityMode(
			$panel.find('.elementor-control-rwgc_visibility_rules_mode select, [data-setting="rwgc_visibility_rules_mode"]').val()
		);
		var key = row.page_variant ? 'variant' : mode;
		var messages = row.messages || fallbackMessages(id);
		var text = messages[key] || messages.show_if || '';
		var signature = String(id) + '|' + key + '|' + text;
		// Collapsed Elementor sections and inactive tabs keep this node in the DOM
		// but not :visible. Rebuilding it mutates #elementor-panel-inner, and the
		// panel MutationObserver would call this again on every tick.
		if ($notice.attr('data-rwgc-status-sig') === signature) {
			if ('none' === $notice.css('display')) {
				$notice.show();
			}
			return;
		}
		$notice.attr('data-rwgc-status-sig', signature);
		$notice.empty().show();
		$notice.append(
			$('<div role="alert"></div>')
				.text(text)
				.attr(
					'style',
					'margin:0;padding:8px 10px;border:1px solid #f0c36d;background:#fff8e5;color:#6b4e16;border-radius:3px;font-size:12px;line-height:1.45;'
				)
		);
		$notice.append(
			$('<p class="elementor-control-field-description" style="margin:6px 0 0;"></p>').text(
				labels.pickAnother || ''
			)
		);
		var $clear = $('<button type="button" class="elementor-button elementor-button-default"></button>')
			.text(labels.clearRule || 'Clear rule')
			.css({ marginTop: '8px' });
		$clear.on('click', function (event) {
			event.preventDefault();
			var $select = librarySelect($panel);
			$select.find('option[data-rwgc-stale="1"]').remove();
			$select.val('');
			persistAppliedRuleId($panel, '');
			$select.trigger('change');
			renderStatusWarning($panel);
		});
		$notice.append($clear);
		var $stale = librarySelect($panel).find('option[data-rwgc-stale="1"]');
		if ($stale.length) {
			$stale.text(staleOptionLabel(id));
		}
	}

	function rowIsPublishedChoice(row) {
		if (!row || !row.id) {
			return false;
		}
		var postStatus = row.status ? String(row.status) : '';
		if (postStatus && postStatus !== 'publish' && postStatus !== 'published') {
			return false;
		}
		var known = statusById[String(row.id)];
		if (known && known.status && String(known.status) !== 'published') {
			return false;
		}
		return true;
	}

	function staleOptionLabel(id) {
		var known = statusById[String(id)];
		var status = known && known.status ? String(known.status) : '';
		var deleted =
			!known || status === 'deleted' || status === 'nonexistent' || status === 'unresolvable';
		var prefix = labels.missingRuleOption || 'Rule #';
		if (deleted) {
			return prefix + String(id) + (labels.deletedSuffix || ' (deleted)');
		}
		var suffix = labels.unpublishedSuffix || ' (unpublished)';
		if (known && known.title) {
			return String(known.title) + suffix;
		}
		return prefix + String(id) + suffix;
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

	function rebuildLibrarySelect($select, preferredId) {
		if (!$select || !$select.length) {
			return;
		}
		if ($select.attr('data-rwgc-library-built') === '1') {
			return;
		}
		var current = String($select.val() || preferredId || '');
		var compatible = [];
		var attention = [];
		var unavailable = [];

		(cfg.library || []).forEach(function (row) {
			if (!rowIsPublishedChoice(row)) {
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

		var chosen = rowIsPublishedChoice(rowsById[current]) ? rowsById[current] : null;
		if (chosen) {
			var rowStatus = chosen.compatibility ? chosen.compatibility.status : 'compatible';
			if (rowStatus !== 'incompatible') {
				$select.val(current);
			} else {
				$select.val('');
				persistAppliedRuleId(panelRoot(), '');
			}
		} else if (current) {
			// Keep a deleted or unpublished id selected. Clearing it here would drop the reference.
			$select.append(
				$('<option></option>')
					.val(current)
					.text(staleOptionLabel(current))
					.attr('data-rwgc-stale', '1')
			);
			$select.val(current);
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
		var $select = librarySelect($panel);
		if ($select.length && $select.is('select')) {
			var preferredId = currentRuleId($panel);
			rebuildLibrarySelect($select, preferredId);
			var initial = rowsById[String($select.val() || '')];
			showCompatibilityNotice($panel, initial);
		}
		renderStatusWarning($panel);

		$panel
			.find('.elementor-control-rwgc_enable_visibility_rules input')
			.off('change.rwgcVisRules')
			.on('change.rwgcVisRules', function () {
				syncVisibilityRulesToggle($panel);
			});
		$panel
			.find('.elementor-control-rwgc_visibility_rule_library select, [data-setting="rwgc_visibility_rule_library"]')
			.off('change.rwgcLib')
			.on('change.rwgcLib', function () {
				var id = String($(this).val() || '');
				var row = rowsById[id];
				showCompatibilityNotice($panel, row);
				if (!id) {
					persistAppliedRuleId($panel, '');
					renderStatusWarning($panel);
					return;
				}
				if (row && row.compatibility && row.compatibility.status === 'incompatible') {
					$(this).val('');
					persistAppliedRuleId($panel, '');
					renderStatusWarning($panel);
					return;
				}
				if (row && row.json) {
					applyLibraryJson($panel, row.json);
				}
				persistAppliedRuleId($panel, id);
				renderStatusWarning($panel);
			});
		$panel
			.find('.elementor-control-rwgc_visibility_rules_mode select, [data-setting="rwgc_visibility_rules_mode"]')
			.off('change.rwgcRuleStatus')
			.on('change.rwgcRuleStatus', function () {
				renderStatusWarning($panel);
			});
	}

	function panelNode() {
		return (
			document.getElementById('elementor-panel-inner') ||
			document.getElementById('elementor-panel') ||
			null
		);
	}

	function panelRoot() {
		var node = panelNode();
		return node ? $(node) : $();
	}

	function scan() {
		fillLoadedCatalogues();
		var $panel = panelRoot();
		if (!$panel.length) {
			return;
		}
		bindLibrarySelect($panel);
	}

	indexRows();
	indexStatuses(cfg.ruleStatuses || {});

	var scanTimer = null;
	function scheduleScan() {
		if (scanTimer) {
			return;
		}
		scanTimer = setTimeout(function () {
			scanTimer = null;
			ensurePanelObserver();
			scan();
		}, 80);
	}

	var panelObserverRoot = null;
	function ensurePanelObserver() {
		var root = panelNode();
		if (!root || panelObserverRoot === root || typeof MutationObserver !== 'function') {
			return;
		}
		panelObserverRoot = root;
		new MutationObserver(scheduleScan).observe(root, { childList: true, subtree: true });
	}

	function watchEditorView(view) {
		if (!view || view.__rwgcRuleStatus || typeof view.on !== 'function') {
			return;
		}
		view.__rwgcRuleStatus = true;
		view.on('render', scheduleScan);
		view.on('section:activated', scheduleScan);
		view.on('childview:section:activated', scheduleScan);
	}

	function watchOpenPanel() {
		ensurePanelObserver();
		if (!window.elementor || typeof elementor.getPanelView !== 'function') {
			return;
		}
		var panelView = null;
		try {
			panelView = elementor.getPanelView();
		} catch (err) {
			panelView = null;
		}
		if (!panelView) {
			return;
		}
		watchEditorView(panelView);
		if (!panelView.__rwgcPageListener && typeof panelView.on === 'function') {
			panelView.__rwgcPageListener = true;
			panelView.on('set:page', function () {
				watchOpenPanel();
				scheduleScan();
			});
		}
		if (typeof panelView.getCurrentPageView === 'function') {
			try {
				watchEditorView(panelView.getCurrentPageView());
			} catch (err) {
				/* The panel has no page view until an element is selected. */
			}
		}
	}

	function onOpenEditor(panel) {
		watchEditorView(panel);
		watchOpenPanel();
		scheduleScan();
	}

	function installEditorHooks() {
		installCatalogueFill();
		ensurePanelObserver();
		if (!window.elementor) {
			return;
		}
		if (elementor.hooks && typeof elementor.hooks.addAction === 'function' && !installEditorHooks.bound) {
			installEditorHooks.bound = true;
			// Classic Elementor 3 and Elementor 4 both open the panel through these hooks.
			['widget', 'section', 'column', 'container', 'page', 'popup', 'wp-post', 'wp-page'].forEach(
				function (type) {
					elementor.hooks.addAction('panel/open_editor/' + type, onOpenEditor);
				}
			);
			elementor.hooks.addAction('elementor/widgets/refreshed', fillLoadedCatalogues);
		}
		if (typeof elementor.on === 'function' && !installEditorHooks.preview) {
			installEditorHooks.preview = true;
			elementor.on('preview:loaded', function () {
				ensurePanelObserver();
				watchOpenPanel();
				scheduleScan();
			});
		}
		if (
			elementor.channels &&
			elementor.channels.editor &&
			typeof elementor.channels.editor.on === 'function' &&
			!installEditorHooks.channel
		) {
			installEditorHooks.channel = true;
			elementor.channels.editor.on('section:activated', scheduleScan);
		}
		watchOpenPanel();
		scheduleScan();
	}

	// #elementor-panel-inner is created when the panel view renders, which is after
	// this script runs. Observing it here used to no-op, so selecting a widget never
	// refreshed the rule notice. Attach from Elementor's own init and panel hooks.
	$(window).on('elementor:init', installEditorHooks);
	$(document).on('elementor:init', installEditorHooks);
	if (window.elementor) {
		installEditorHooks();
	}
})(jQuery);
