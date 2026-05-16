const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '../../assets/js/rwgc-rule-builder.js'), 'utf8');
const sandbox = {
	window: {
		RWGC_RULE_BUILDER_TESTS: true,
	},
	document: {},
	jQuery: function () {
		return {
			on: function () {},
			trigger: function () {
				return this;
			},
		};
	},
};
sandbox.window.window = sandbox.window;
sandbox.window.document = sandbox.document;
sandbox.window.jQuery = sandbox.jQuery;

vm.runInNewContext(source, sandbox, { filename: 'rwgc-rule-builder.js' });

const setMembership = sandbox.window.ReactWooRuleBuilder._test.setMembership;

assert.deepStrictEqual(
	setMembership(['US', 'GB'], 'CA', true),
	['US', 'GB', 'CA'],
	'adding a filtered visible value must preserve hidden selections'
);
assert.deepStrictEqual(
	setMembership(['US', 'GB', 'CA'], 'CA', false),
	['US', 'GB'],
	'removing a visible value must not drop unrelated selections'
);
assert.deepStrictEqual(
	setMembership(['US', 'GB'], 'GB', true),
	['US', 'GB'],
	'checking an already-selected value must not duplicate it'
);
