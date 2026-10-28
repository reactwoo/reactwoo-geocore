<?php
/**
 * Asset metadata for the Experience Slot editor script.
 *
 * WordPress 6.2 calls register_block_script_handle() for block.json editorScript
 * and logs a notice when this file is missing.
 *
 * @package ReactWooGeoCore
 */

return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-element',
		'wp-block-editor',
		'wp-components',
		'wp-i18n',
	),
	'version'      => '1.9.22',
);
