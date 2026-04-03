<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical page-route view: default target + ordered variants + legacy role hints.
 *
 * Built from legacy post meta via RWGC_Legacy_Route_Mapper until dedicated rule storage exists.
 */
class RWGC_Page_Route_Bundle {

	/**
	 * Page this bundle describes (queried page id).
	 *
	 * @var int
	 */
	public $page_id = 0;

	/**
	 * Whether routing is enabled for this page.
	 *
	 * @var bool
	 */
	public $enabled = false;

	/**
	 * Legacy role: master | variant.
	 *
	 * @var string
	 */
	public $role = 'master';

	/**
	 * Default content page when no variant matches (master: typically this page).
	 *
	 * @var int
	 */
	public $default_page_id = 0;

	/**
	 * Variant pages keyed for master resolution (visitor country -> page id).
	 *
	 * @var RWGC_Variant[]
	 */
	public $variants = array();

	/**
	 * When current page is a variant: ISO2 country this page is intended for.
	 *
	 * @var string
	 */
	public $variant_country_iso2 = '';

	/**
	 * When current page is a variant: fallback master page id.
	 *
	 * @var int
	 */
	public $master_page_id = 0;

	/**
	 * Raw legacy config array for filters/debug.
	 *
	 * @var array<string, mixed>
	 */
	public $legacy_config = array();
}
