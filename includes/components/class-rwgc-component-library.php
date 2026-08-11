<?php
/**
 * Seeds the initial ReactWoo Component library (six types).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers definitions + default PHP HTML renderer.
 */
final class RWGC_Component_Library {

	/** @var bool */
	private static $booted = false;

	/**
	 * @return void
	 */
	public static function reset() {
		self::$booted = false;
		RWGC_Component_Registry::reset();
	}

	/**
	 * @return void
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		RWGC_Component_Registry::register_renderer( new RWGC_Php_Html_Component_Renderer() );

		foreach ( self::definitions() as $definition ) {
			RWGC_Component_Registry::register( $definition );
		}

		/**
		 * Allow satellites to register additional component definitions.
		 */
		do_action( 'reactwoo_register_components' );
	}

	/**
	 * @return list<RWGC_Component_Definition>
	 */
	public static function definitions() {
		$tokens = array(
			'color'  => array( '--rw-color-text', '--rw-color-accent', '--rw-color-surface' ),
			'font'   => array( '--rw-font-sans', '--rw-font-display' ),
			'radius' => array( '--rw-radius-md' ),
			'space'  => array( '--rw-space-2', '--rw-space-4', '--rw-space-6' ),
			'width'  => array( '--rw-content-width' ),
		);
		$responsive = array(
			'breakpoints' => array( 'desktop', 'tablet', 'mobile' ),
			'strategy'    => 'css_custom_properties',
		);
		$a11y = array(
			'wcag'           => '2.2-AA',
			'keyboard'       => true,
			'reduced_motion' => true,
			'focus_visible'  => true,
		);

		return array(
			new RWGC_Component_Definition(
				'hero',
				1,
				array(
					'headline'    => array( 'type' => 'string' ),
					'subheadline' => array( 'type' => 'string' ),
					'cta_label'   => array( 'type' => 'string' ),
					'cta_url'     => array( 'type' => 'string', 'format' => 'uri' ),
					'image_url'   => array( 'type' => 'string', 'format' => 'uri' ),
				),
				$tokens,
				$responsive,
				array_merge( $a11y, array( 'landmark' => 'region' ) ),
				'php_html',
				array( 'label' => 'Hero' )
			),
			new RWGC_Component_Definition(
				'cta',
				1,
				array(
					'label' => array( 'type' => 'string' ),
					'url'   => array( 'type' => 'string', 'format' => 'uri' ),
				),
				$tokens,
				$responsive,
				$a11y,
				'php_html',
				array( 'label' => 'CTA' )
			),
			new RWGC_Component_Definition(
				'promotion_banner',
				1,
				array(
					'text' => array( 'type' => 'string' ),
					'url'  => array( 'type' => 'string', 'format' => 'uri' ),
				),
				$tokens,
				$responsive,
				array_merge( $a11y, array( 'landmark' => 'complementary' ) ),
				'php_html',
				array( 'label' => 'Promotion Banner' )
			),
			new RWGC_Component_Definition(
				'notice',
				1,
				array(
					'text' => array( 'type' => 'string' ),
					'tone' => array( 'type' => 'string', 'enum' => array( 'info', 'success', 'warning', 'error' ) ),
				),
				$tokens,
				$responsive,
				array_merge( $a11y, array( 'live_region' => 'status' ) ),
				'php_html',
				array( 'label' => 'Notice' )
			),
			new RWGC_Component_Definition(
				'product_rail',
				1,
				array(
					'title' => array( 'type' => 'string' ),
					'items' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'name' => array( 'type' => 'string' ),
								'url'  => array( 'type' => 'string' ),
							),
						),
					),
				),
				$tokens,
				$responsive,
				$a11y,
				'php_html',
				array( 'label' => 'Product Rail' )
			),
			new RWGC_Component_Definition(
				'popup',
				1,
				array(
					'title'   => array( 'type' => 'string' ),
					'content' => array( 'type' => 'string' ),
				),
				$tokens,
				$responsive,
				array_merge( $a11y, array( 'js_required' => false, 'pattern' => 'details_summary' ) ),
				'php_html',
				array( 'label' => 'Popup / Slide-in' )
			),
		);
	}
}
