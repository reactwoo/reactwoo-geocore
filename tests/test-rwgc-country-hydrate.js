/**
 * Country catalogue hydration must not publish an empty DOM value.
 *
 * Elementor SELECT2 only renders <option> tags from control.options. Geo Core
 * ships those empty and fills them in the browser. Reading .val() at that
 * moment is null even when the model still holds ['FR'].
 */
'use strict';

const fs = require('fs');
const path = require('path');

const src = fs.readFileSync(
	path.join(__dirname, '../assets/js/rwgc-elementor-library-bridge.js'),
	'utf8'
);

function extract(startMarker, endMarker) {
	const start = src.indexOf(startMarker);
	const end = src.indexOf(endMarker);
	if (start < 0 || end <= start) {
		throw new Error('missing slice ' + startMarker);
	}
	return src.slice(start, end);
}

const hydrateStart = src.indexOf('function hydrateOneCountrySelect');
const hydrateEnd = src.indexOf('function countryChips');
if (hydrateStart < 0 || hydrateEnd <= hydrateStart) {
	throw new Error('hydrate function missing');
}
const hydrateBody = src.slice(hydrateStart, hydrateEnd);
if (hydrateBody.includes(".trigger('change')") || hydrateBody.includes('.trigger("change")')) {
	throw new Error('hydrate still fires a model-writing change event');
}
if (!hydrateBody.includes("trigger('change.select2')")) {
	throw new Error('hydrate no longer refreshes select2');
}

const api = new Function(
	extract('function normalizeCountrySelection', 'function settingFromModel') +
		extract('function countryChips', 'function isAtomicCountryControl') +
		extract('function isAtomicCountryControl', 'function fillAtomicControls') +
		extract('function fillAtomicControls', 'function fillClassicCountryOptions') +
		extract('function fillClassicCountryOptions', 'var filledCatalogues') +
		'\nreturn { normalizeCountrySelection: normalizeCountrySelection, countryChips: countryChips, fillAtomicControls: fillAtomicControls, fillClassicCountryOptions: fillClassicCountryOptions };'
)();
const normalizeCountrySelection = api.normalizeCountrySelection;
const countryChips = api.countryChips;
const fillAtomicControls = api.fillAtomicControls;
const fillClassicCountryOptions = api.fillClassicCountryOptions;

function assert(cond, msg) {
	if (!cond) {
		throw new Error(msg);
	}
}

assert(
	JSON.stringify(normalizeCountrySelection(['FR', 'DE'], true)) === '["FR","DE"]',
	'multiple model value is preserved'
);
assert(
	JSON.stringify(normalizeCountrySelection(null, true)) === '[]',
	'missing multiple value stays an empty list'
);
assert(normalizeCountrySelection('DE', false) === 'DE', 'single country is preserved');
assert(normalizeCountrySelection(['DE'], false) === 'DE', 'single value stored as a list uses the first code');
assert(
	JSON.stringify(normalizeCountrySelection({ 0: 'FR', 1: 'GB' }, true)) === '["FR","GB"]',
	'object-shaped multiple values are preserved'
);

const countries = { FR: 'France', DE: 'Germany', GB: 'United Kingdom' };
const chips = countryChips(countries);
const atomic = {
	atomic_controls: [
		{
			type: 'section',
			value: {
				items: [
					{
						type: 'control',
						value: {
							type: 'chips',
							bind: 'egp_countries',
							props: { options: [], freeChips: false },
							meta: { rwgcCatalogue: 'countries' },
						},
					},
				],
			},
		},
	],
};
fillAtomicControls(atomic.atomic_controls, chips);
const filled = atomic.atomic_controls[0].value.items[0].value.props.options;
assert(filled.length === 3 && filled[0].value === 'FR', 'atomic chips receive the shared catalogue');

const classic = {
	egp_countries: { type: 'select2', options: [] },
	rwgc_route_country_iso2: { type: 'select2', options: {} },
	rwgc_enable_visibility_rules: { type: 'switcher', options: [] },
};
fillClassicCountryOptions(classic, countries);
assert(classic.egp_countries.options.FR === 'France', 'classic country select receives catalogue options');
assert(classic.rwgc_route_country_iso2.options.DE === 'Germany', 'variant country select receives catalogue options');
assert(
	Array.isArray(classic.rwgc_enable_visibility_rules.options) && classic.rwgc_enable_visibility_rules.options.length === 0,
	'non-select controls are left alone'
);

console.log('OK: country hydrate keeps saved countries');
