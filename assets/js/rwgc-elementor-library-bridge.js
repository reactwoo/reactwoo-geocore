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

	function applyLibraryJson($panel, json) {
		if (!json) {
			return;
		}
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

	function bindLibrarySelect($panel) {
		$panel
			.find('.elementor-control-rwgc_visibility_rule_library select')
			.off('change.rwgcLib')
			.on('change.rwgcLib', function () {
				var id = String($(this).val() || '');
				if (!id) {
					return;
				}
				var row = rowsById[id];
				if (row && row.json) {
					applyLibraryJson($panel, row.json);
				}
				$(this).val('');
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
