/**
 * Geo Content editor: advanced-targeting key and inner-block save contract.
 *
 * PHP sends advanced_targeting. Older editor code reads advancedTargeting.
 * The block must treat either spelling as enabled, keep the stale-rule controls,
 * and save inner blocks without invalidating previously self-closing blocks.
 */
'use strict';

const fs = require('fs');
const path = require('path');

const src = fs.readFileSync(path.join(__dirname, '../blocks/geo-content/index.js'), 'utf8');

function assert(condition, message) {
	if (!condition) {
		throw new Error(message);
	}
}

const start = src.indexOf('function rwgcGeoContentAdvancedEnabled');
const end = src.indexOf('function GeoContentEdit');
assert(start !== -1 && end > start, 'advanced-targeting reader is missing');

const readAdvanced = new Function(
	src.slice(start, end) + '\nreturn rwgcGeoContentAdvancedEnabled;'
)();

assert(readAdvanced({ advancedTargeting: true }) === true, 'camelCase flag should enable rules');
assert(readAdvanced({ advanced_targeting: true }) === true, 'snake_case flag should enable rules');
assert(
	readAdvanced({ advancedTargeting: false, advanced_targeting: true }) === true,
	'snake_case alone should enable rules when camelCase is false'
);
assert(
	readAdvanced({ advancedTargeting: true, advanced_targeting: false }) === true,
	'camelCase alone should enable rules when snake_case is false'
);
assert(readAdvanced({}) === false, 'missing flags should stay off');
assert(readAdvanced(null) === false, 'null config should stay off');
assert(readAdvanced({ advancedTargeting: 0, advanced_targeting: '' }) === false, 'empty flags should stay off');

assert(src.indexOf('rwgc-library-rule-status') !== -1, 'stale-rule warning is missing');
assert(src.indexOf('Choose another saved rule') !== -1, 'replacement select is missing');
assert(src.indexOf('Clear rule') !== -1, 'Clear rule button is missing');
assert(src.indexOf('visibilityOn ? ruleWarningElement() : null') !== -1, 'warning must stay inside visibility rules');

assert(src.indexOf('InnerBlocks') !== -1, 'edit must render InnerBlocks');
assert(src.indexOf('InnerBlocks.Content') !== -1, 'save must persist InnerBlocks.Content');
assert(src.indexOf('deprecated:') !== -1, 'previous self-closing save needs a deprecated entry');

const deprecatedAt = src.indexOf('deprecated:');
assert(deprecatedAt !== -1, 'deprecated entry missing');
const deprecatedSave = src.slice(deprecatedAt);
assert(
	/save:\s*function\s*\(\)\s*\{\s*return null;\s*\}/.test(deprecatedSave),
	'deprecated save must still return null so existing blocks stay valid'
);

const currentSave = src.slice(src.indexOf("registerBlockType('reactwoo-geocore/geo-content'"), deprecatedAt);
assert(currentSave.indexOf('InnerBlocks.Content') !== -1, 'current save must emit inner content');
assert(!/return null;/.test(currentSave), 'current save must no longer be the null dynamic save');

console.log('OK geo content block editor contract');
