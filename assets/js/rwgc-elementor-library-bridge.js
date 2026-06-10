/**
 * Elementor: apply saved visibility rules from library SELECT to portable JSON textarea.
 */
(function ($) {
	'use strict';

	var cfg = window.rwgcElementorLibrary || {};
	var rowsById = {};

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
			$legacy.val(on ? 'yes' : '').trigger('input').trigger('change');
		}
	}

	function bindLibrarySelect($panel) {
		syncVisibilityRulesToggle($panel);
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
				if (!id) {
					persistAppliedRuleId($panel, '');
					return;
				}
				var row = rowsById[id];
				if (row && row.json) {
					applyLibraryJson($panel, row.json);
				}
				persistAppliedRuleId($panel, id);
			});
	}

	function scan() {
		var $panel = $('#elementor-panel-inner');
		if (!$panel.length) {
			return;
		}
		bindLibrarySelect($panel);
	}

	indexRows();

	$(window).on('elementor:init', scan);
	$(document).on('elementor:init', scan);

	var root = document.getElementById('elementor-panel-inner');
	if (root) {
		new MutationObserver(scan).observe(root, { childList: true, subtree: true });
	}
	setTimeout(scan, 400);
})(jQuery);
