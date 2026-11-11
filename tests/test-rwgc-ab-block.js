/**
 * A/B Test block editor: two locked variants, goal setting, InnerBlocks save.
 *
 * The repo runs editor checks as plain Node scripts (see test:geo-content-block).
 * This file follows that setup. WordPress itself is not loaded.
 */
'use strict';

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const editor = fs.readFileSync(path.join(root, 'blocks/ab-test/editor.js'), 'utf8');
const variantEditor = fs.readFileSync(path.join(root, 'blocks/ab-variant/editor.js'), 'utf8');
const block = JSON.parse(fs.readFileSync(path.join(root, 'blocks/ab-test/block.json'), 'utf8'));
const variant = JSON.parse(fs.readFileSync(path.join(root, 'blocks/ab-variant/block.json'), 'utf8'));
const asset = fs.readFileSync(path.join(root, 'blocks/ab-test/editor.asset.php'), 'utf8');
const variantAsset = fs.readFileSync(path.join(root, 'blocks/ab-variant/editor.asset.php'), 'utf8');
const plugin = fs.readFileSync(path.join(root, 'reactwoo-geocore.php'), 'utf8');

function assert(condition, message) {
	if (!condition) {
		throw new Error(message);
	}
}

const version = (plugin.match(/\*\s*Version:\s*([0-9.]+)/) || [])[1];
assert(version, 'plugin version header is missing');
assert(asset.indexOf("'" + version + "'") !== -1, 'ab-test editor asset version must match the plugin');
assert(variantAsset.indexOf("'" + version + "'") !== -1, 'ab-variant editor asset version must match the plugin');

assert(block.name === 'reactwoo-geocore/ab-test', 'test block uses the reactwoo-geocore namespace');
assert(block.apiVersion === 3, 'block apiVersion stays on the version Plugin Check expects');
assert(block.supports && block.supports.html === false, 'test block must not allow custom HTML');
assert(block.editorScript === 'file:./editor.js', 'editor script must be a block.json file reference');
assert(variant.name === 'reactwoo-geocore/ab-variant', 'variant block name');
assert(Array.isArray(variant.parent) && variant.parent.length === 1 && variant.parent[0] === 'reactwoo-geocore/ab-test', 'variant parent is the test block');
assert(variant.supports && variant.supports.inserter === false, 'variant stays out of the inserter');
assert(variant.supports.html === false, 'variant block must not allow custom HTML');

const preludeStart = editor.indexOf('var RWGC_AB_BLOCK');
const preludeEnd = editor.indexOf('function newInstanceId');
assert(preludeStart !== -1 && preludeEnd > preludeStart, 'template constants are missing');
const shape = new Function(
	editor.slice(preludeStart, preludeEnd) + '\nreturn { block: RWGC_AB_BLOCK, variant: RWGC_AB_VARIANT, template: RWGC_AB_TEMPLATE };'
)();

assert(shape.block === 'reactwoo-geocore/ab-test', 'editor registers the test block name');
assert(shape.variant === 'reactwoo-geocore/ab-variant', 'editor template uses the variant block');
assert(Array.isArray(shape.template) && shape.template.length === 2, 'template has exactly two inner blocks');
assert(shape.template[0][0] === 'reactwoo-geocore/ab-variant', 'first inner block is a variant');
assert(shape.template[1][0] === 'reactwoo-geocore/ab-variant', 'second inner block is a variant');
assert(shape.template[0][1].variant === 'a', 'first arm is A');
assert(shape.template[1][1].variant === 'b', 'second arm is B');
assert(shape.template[0][1].lock && shape.template[0][1].lock.remove === true && shape.template[0][1].lock.move === true, 'variant A is locked');
assert(shape.template[1][1].lock && shape.template[1][1].lock.remove === true && shape.template[1][1].lock.move === true, 'variant B is locked');

assert(editor.indexOf("templateLock: 'all'") !== -1, 'the pair is template-locked');
assert(editor.indexOf('allowedBlocks: [ RWGC_AB_VARIANT ]') !== -1, 'only variant blocks are allowed inside the test');
assert(editor.indexOf("value: 'click'") !== -1, 'click goal is offered');
assert(editor.indexOf("value: 'url'") !== -1, 'URL goal is offered');
assert(editor.indexOf('goalSelector') !== -1, 'click goal stores a selector');
assert(editor.indexOf('goalUrl') !== -1, 'URL goal stores a URL');

const saveStart = editor.indexOf('function saveAbTestBlock');
const saveEnd = editor.indexOf('registerBlockType( RWGC_AB_BLOCK');
assert(saveStart !== -1 && saveEnd > saveStart, 'saveAbTestBlock is missing');
const saveBody = editor.slice(saveStart, saveEnd);
assert(saveBody.indexOf('InnerBlocks.Content') !== -1, 'save must return InnerBlocks content');
assert(saveBody.indexOf('useBlockProps') === -1, 'save must not use useBlockProps');
assert(saveBody.indexOf('data-rwgc') === -1, 'save must not write front-end markers');
assert(saveBody.indexOf('<div') === -1, 'save must not add a wrapper element');

assert(variantEditor.indexOf('function saveAbVariantBlock') !== -1, 'variant save is missing');
assert(variantEditor.indexOf('InnerBlocks.Content') !== -1, 'variant save must return InnerBlocks content');
assert(variantEditor.indexOf('templateLock: false') !== -1, 'variant content stays editable');
assert(variantEditor.indexOf("'reactwoo-geocore/ab-variant'") !== -1, 'variant editor registers its block');

console.log('A/B block editor checks passed.');
