<?php
/**
 * Registry for ReactWoo Component Definitions and renderers.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * In-memory registry (request lifetime + bootstrap seed).
 */
final class RWGC_Component_Registry {

	/**
	 * @var array<string, RWGC_Component_Definition>
	 */
	private static $definitions = array();

	/**
	 * @var array<string, RWGC_Component_Renderer_Interface>
	 */
	private static $renderers = array();

	/**
	 * @return void
	 */
	public static function reset() {
		self::$definitions = array();
		self::$renderers   = array();
	}

	/**
	 * @param RWGC_Component_Definition $definition Definition.
	 * @return bool False if type already owned by a different schema (collision).
	 */
	public static function register( RWGC_Component_Definition $definition ) {
		$type = $definition->type();
		if ( '' === $type ) {
			return false;
		}
		if ( isset( self::$definitions[ $type ] ) ) {
			$existing = self::$definitions[ $type ];
			if ( $existing->schema_version() !== $definition->schema_version() ) {
				return false;
			}
		}
		self::$definitions[ $type ] = $definition;
		return true;
	}

	/**
	 * @param string $type Type.
	 * @return RWGC_Component_Definition|null
	 */
	public static function get( $type ) {
		$type = strtolower( trim( (string) $type ) );
		return isset( self::$definitions[ $type ] ) ? self::$definitions[ $type ] : null;
	}

	/**
	 * @return array<string, RWGC_Component_Definition>
	 */
	public static function all() {
		return self::$definitions;
	}

	/**
	 * @param RWGC_Component_Renderer_Interface $renderer Renderer.
	 * @return void
	 */
	public static function register_renderer( RWGC_Component_Renderer_Interface $renderer ) {
		self::$renderers[ $renderer->id() ] = $renderer;
	}

	/**
	 * @param string $id Renderer id.
	 * @return RWGC_Component_Renderer_Interface|null
	 */
	public static function get_renderer( $id ) {
		$id = (string) $id;
		return isset( self::$renderers[ $id ] ) ? self::$renderers[ $id ] : null;
	}

	/**
	 * Render a component type with props.
	 *
	 * @param string               $type Type.
	 * @param array<string, mixed> $props Props.
	 * @param array<string, mixed> $context Context.
	 * @return string Empty string on failure (never throws to callers).
	 */
	public static function render( $type, array $props = array(), array $context = array() ) {
		try {
			$definition = self::get( $type );
			if ( ! $definition ) {
				return '';
			}
			$renderer_id = isset( $context['renderer_id'] ) ? (string) $context['renderer_id'] : $definition->renderer_id();
			$renderer    = self::get_renderer( $renderer_id );
			if ( ! $renderer ) {
				return '';
			}
			$html = $renderer->render( $definition, $props, $context );
			return is_string( $html ) ? $html : '';
		} catch ( \Throwable $e ) { // phpcs:ignore WordPress.CodeAnalysis.ExceptionDocumented
			return '';
		}
	}
}
