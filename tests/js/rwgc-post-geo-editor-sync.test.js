/**
 * Static regression checks for Gutenberg post Geo Rule Builder → meta sync.
 *
 * Run: node tests/js/rwgc-post-geo-editor-sync.test.js
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const file = path.join( __dirname, '..', '..', 'assets', 'js', 'rwgc-post-geo-editor.js' );
const src = fs.readFileSync( file, 'utf8' );

function assert( condition, message ) {
	if ( ! condition ) {
		console.error( 'FAIL:', message );
		process.exitCode = 1;
	} else {
		console.log( 'OK:', message );
	}
}

assert(
	/ReactWooRuleBuilder\.mount\s*\(\s*\{[\s\S]*?onChange:\s*function\s*\(\s*json\s*\)/.test( src ),
	'RuleBuilder.mount must pass onChange(json) so portable JSON reaches post meta'
);

assert(
	/metaValuesRef\.current\[ meta\.portable \]\s*===\s*json/.test( src ),
	'onChange must compare against current portable meta before writing'
);

assert(
	/function updateMeta\s*\(\s*patch\s*\)/.test( src ),
	'updateMeta must accept a patch object (batch writes)'
);

assert(
	/Object\.assign\s*\(\s*\{\s*\}\s*,\s*metaValuesRef\.current\s*,\s*patch\s*\)/.test( src ),
	'updateMeta must merge onto metaValuesRef to avoid clobbering sibling keys'
);

assert(
	/patch\[\s*meta\.visibilityEnabled\s*\]\s*=\s*val \? 'yes' : ''[\s\S]*?patch\[\s*meta\.usePortable\s*\]\s*=\s*val \? 'yes' : ''[\s\S]*?updateMeta\s*\(\s*patch\s*\)/.test(
		src
	),
	'Enable visibility rules must set visibilityEnabled + usePortable in one updateMeta call'
);

assert(
	/getMode:\s*function\s*\(\s*\)\s*\{\s*return visibilityModeRef\.current/.test( src ),
	'getMode must read visibilityModeRef (not a stale mount closure)'
);

if ( process.exitCode ) {
	process.exit( process.exitCode );
}
console.log( 'All post-geo editor sync checks passed.' );
