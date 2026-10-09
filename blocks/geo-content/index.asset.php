<?php
/**
 * Asset metadata for the Geo Content editor script.
 *
 * The handle stays rwgc-geo-content-editor so editor inline data and the rule-builder dependency can find it.
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
	'handle'       => 'rwgc-geo-content-editor',
	'version'      => '1.9.0',
);
