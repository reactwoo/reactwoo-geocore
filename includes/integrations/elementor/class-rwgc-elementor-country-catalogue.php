<?php
/**
 * One country catalogue for the Elementor editor.
 *
 * Classic SELECT2 controls and Atomic chips ship with empty options. This class
 * places the ISO list on ElementorConfig once, then fills each country control
 * in the browser after Atomic expands shared config references.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared country catalogue for Elementor V3 and V4 controls.
 */
class RWGC_Elementor_Country_Catalogue {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'elementor/editor/localize_settings', array( __CLASS__, 'filter_config' ), 110000 );
		// After Atomic's expander (priority 0), which runs once ElementorConfig exists.
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'print_expander' ), 1 );
	}

	/**
	 * @param mixed $config Editor config.
	 * @return mixed
	 */
	public static function filter_config( $config ) {
		if ( ! is_array( $config ) ) {
			return $config;
		}

		$countries = array();
		if ( class_exists( 'RWGC_Elementor_Options', false ) ) {
			$countries = RWGC_Elementor_Options::countries();
		} elseif ( class_exists( 'RWGC_Elementor_Elements', false ) ) {
			$countries = RWGC_Elementor_Elements::get_country_options();
		}
		if ( ! is_array( $countries ) ) {
			$countries = array();
		}

		$config['rwgcShared'] = array(
			'countries' => $countries,
		);

		return $config;
	}

	/**
	 * @return void
	 */
	public static function print_expander() {
		$js = <<<'JS'
(function (root) {
	'use strict';
	var cfg = root.ElementorConfig;
	if (!cfg || !cfg.rwgcShared || !cfg.rwgcShared.countries) {
		return;
	}
	var map = cfg.rwgcShared.countries;
	var codes = Object.keys(map);
	if (!codes.length) {
		return;
	}
	var chips = [];
	for (var i = 0; i < codes.length; i++) {
		chips.push({ value: codes[i], label: String(map[codes[i]] || codes[i]) });
	}
	function isCountryControl(value) {
		if (!value || typeof value !== 'object' || !value.props) {
			return false;
		}
		if (value.bind === 'egp_countries') {
			return true;
		}
		return !!(value.meta && value.meta.rwgcCatalogue === 'countries');
	}
	function walk(node) {
		if (!node || typeof node !== 'object') {
			return;
		}
		if (Array.isArray(node)) {
			for (var n = 0; n < node.length; n++) {
				walk(node[n]);
			}
			return;
		}
		if (isCountryControl(node.value)) {
			node.value.props.options = chips.slice();
			return;
		}
		var keys = Object.keys(node);
		for (var k = 0; k < keys.length; k++) {
			walk(node[keys[k]]);
		}
	}
	function fillEntry(entry) {
		if (entry && entry.atomic_controls) {
			walk(entry.atomic_controls);
		}
	}
	var elements = cfg.elements || {};
	Object.keys(elements).forEach(function (type) {
		fillEntry(elements[type]);
	});
	var widgets = (cfg.initial_document && cfg.initial_document.widgets) || {};
	Object.keys(widgets).forEach(function (type) {
		fillEntry(widgets[type]);
	});
})(typeof window !== 'undefined' ? window : globalThis);
JS;
		wp_add_inline_script( 'elementor-editor', $js, 'before' );
	}
}
