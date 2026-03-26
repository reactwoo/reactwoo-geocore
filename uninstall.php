<?php

/**
 * Uninstall handler for ReactWoo Geo Core.
 *
 * Removes settings and cache. Leaves downloaded DB by default.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'rwgc_settings' );
delete_option( 'rwgc_cache_version' );

