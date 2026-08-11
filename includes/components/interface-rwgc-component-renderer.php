<?php
/**
 * Component renderer contract.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a ComponentDefinition + props to HTML (or other string payload).
 */
interface RWGC_Component_Renderer_Interface {

	/**
	 * @return string Renderer id (e.g. php_html).
	 */
	public function id();

	/**
	 * @param RWGC_Component_Definition $definition Definition.
	 * @param array<string, mixed>      $props Props.
	 * @param array<string, mixed>      $context Render context (tokens, locale, …).
	 * @return string
	 */
	public function render( RWGC_Component_Definition $definition, array $props, array $context = array() );
}
