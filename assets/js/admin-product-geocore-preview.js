(function ($) {
	'use strict';

	function cfg() {
		return window.rwgcProductGeocorePreview || {};
	}

	function selectedProductFacets() {
		var out = [];
		$('input[name="rwgcm_weather_facets[]"]:checked').each(function () {
			out.push(String($(this).val()));
		});
		return out;
	}

	function simulatedVisitorFacets() {
		if (!$('#rwgc-preview-simulate-weather').is(':checked')) {
			var live = cfg().visitorFacets;
			return Array.isArray(live) ? live.slice() : [];
		}
		var sim = [];
		$('#rwgc-preview-weather-grid input:checked').each(function () {
			sim.push(String($(this).val()));
		});
		return sim;
	}

	function selectedGeoMode() {
		var val = $('input[name="_geocore_product_geo_mode"]:checked').val();
		return val ? String(val) : (cfg().geoModes && cfg().geoModes.global) || 'global';
	}

	function selectedCountries() {
		var out = [];
		$('#_geocore_product_countries option:selected').each(function () {
			out.push(String($(this).val()).toUpperCase());
		});
		return out;
	}

	function simulatedCountry() {
		var val = $('#rwgc-preview-visitor-country').val();
		return val ? String(val).toUpperCase() : '';
	}

	function selectedBoostMode() {
		var val = $('input[name="_geocore_product_boost_enabled"]:checked').val();
		return val ? String(val) : (cfg().boost && cfg().boost.inherit) || 'inherit';
	}

	function countryVisible() {
		var mode = selectedGeoMode();
		var countries = selectedCountries();
		var visitor = simulatedCountry();
		var modes = cfg().geoModes || {};

		if (mode === modes.global || !countries.length) {
			return true;
		}
		if (!visitor) {
			return true;
		}
		var inList = countries.indexOf(visitor) !== -1;
		if (mode === modes.hideIn) {
			return !inList;
		}
		if (mode === modes.showOnlyIn) {
			return inList;
		}
		return true;
	}

	function weatherOverlap() {
		var product = selectedProductFacets();
		var visitor = simulatedVisitorFacets();
		if (!product.length || !visitor.length) {
			return [];
		}
		return product.filter(function (slug) {
			return visitor.indexOf(slug) !== -1;
		});
	}

	function setBadge(type, label) {
		var $badge = $('#rwgc-preview-status-badge');
		$badge
			.removeClass('rwgc-preview-badge--visible rwgc-preview-badge--hidden rwgc-preview-badge--boosted rwgc-preview-badge--no-weather rwgc-preview-badge--neutral')
			.addClass('rwgc-preview-badge--' + type)
			.text(label);
	}

	function updatePreview() {
		var i18n = cfg().i18n || {};
		var $detail = $('#rwgc-preview-status-detail');
		var details = [];
		var visible = countryVisible();
		var overlap = weatherOverlap();
		var boostMode = selectedBoostMode();
		var boost = cfg().boost || {};
		var hasWeatherTags = selectedProductFacets().length > 0;
		var hasVisitorWeather = simulatedVisitorFacets().length > 0;

		if (!visible) {
			setBadge('hidden', i18n.hidden || 'Hidden');
			$detail.text(i18n.hiddenCountry || '');
			return;
		}

		if (boostMode === boost.no) {
			setBadge('visible', i18n.visible || 'Visible');
			details.push(i18n.boostDisabled || '');
		} else if (overlap.length && boostMode !== boost.no) {
			setBadge('boosted', i18n.boosted || 'Boosted');
			var labels = overlap.map(function (slug) {
				var map = cfg().facetLabels || {};
				return map[slug] || slug;
			});
			details.push((i18n.weatherMatch || 'Weather overlap: %s').replace('%s', labels.join(', ')));
			details.push(i18n.boostEnabled || '');
		} else if (hasWeatherTags && hasVisitorWeather && !overlap.length) {
			setBadge('no-weather', i18n.noWeatherMatch || 'No weather match');
			details.push(i18n.weatherNoMatch || '');
		} else {
			setBadge('visible', i18n.visible || 'Visible');
			if (selectedGeoMode() === (cfg().geoModes && cfg().geoModes.global)) {
				details.push(i18n.globalGeo || '');
			} else {
				details.push(i18n.visibleCountry || '');
			}
			if (!hasWeatherTags) {
				details.push(i18n.noProductFacets || '');
			}
		}

		$detail.text(details.filter(Boolean).join(' '));
	}

	function toggleGeoCountryField() {
		var mode = selectedGeoMode();
		var globalMode = (cfg().geoModes && cfg().geoModes.global) || 'global';
		$('.rwgc-product-countries-wrap').toggle(mode !== globalMode);
	}

	function toggleRuleModeField() {
		var ruleId = $('#_geocore_product_rule_id').val();
		$('.rwgc-product-rule-mode-wrap').toggle(!!ruleId);
	}

	$(function () {
		if (!$('#geocore_product_data').length) {
			return;
		}

		$('#geocore_product_data').on('change', 'input, select', updatePreview);
		$('input[name="_geocore_product_geo_mode"]').on('change', toggleGeoCountryField);
		$('#_geocore_product_rule_id').on('change', toggleRuleModeField);
		$('#rwgc-preview-simulate-weather').on('change', function () {
			$('#rwgc-preview-weather-grid').toggle($(this).is(':checked'));
			updatePreview();
		});

		toggleGeoCountryField();
		toggleRuleModeField();
		updatePreview();
	});
})(jQuery);
