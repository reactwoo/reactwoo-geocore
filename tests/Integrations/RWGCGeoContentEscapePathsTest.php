<?php
/**
 * Geo Content escaping on the block, shortcode, slots, and Elementor popup helper.
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		if ( isset( $GLOBALS['rwgc_test_options'] ) && is_array( $GLOBALS['rwgc_test_options'] ) && array_key_exists( $key, $GLOBALS['rwgc_test_options'] ) ) {
			return $GLOBALS['rwgc_test_options'][ $key ];
		}
		if ( 'rwgc_settings' === $key ) {
			return array( 'enabled' => 0 );
		}
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	function update_option( $key, $value ) {
		if ( ! isset( $GLOBALS['rwgc_test_options'] ) || ! is_array( $GLOBALS['rwgc_test_options'] ) ) {
			$GLOBALS['rwgc_test_options'] = array();
		}
		$GLOBALS['rwgc_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	/**
	 * @param int $min Min.
	 * @param int $max Max.
	 * @return int
	 */
	function wp_rand( $min = 0, $max = 0 ) {
		return mt_rand( (int) $min, (int) $max );
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	/**
	 * @param array<string, mixed> $pairs Defaults.
	 * @param mixed                $atts Attributes.
	 * @param string               $shortcode Shortcode.
	 * @return array<string, mixed>
	 */
	function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		unset( $shortcode );
		$atts = is_array( $atts ) ? $atts : array();
		$out  = array();
		foreach ( $pairs as $name => $default ) {
			$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
		}
		return $out;
	}
}

if ( ! function_exists( 'get_the_ID' ) ) {
	/**
	 * @return int
	 */
	function get_the_ID() {
		return 0;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * @param mixed $args Args.
	 * @param array $defaults Defaults.
	 * @return array<string, mixed>
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		} elseif ( ! is_array( $args ) ) {
			$args = array();
		}
		if ( ! is_array( $defaults ) ) {
			$defaults = array();
		}
		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'do_shortcode' ) ) {
	/**
	 * @param mixed $content Content.
	 * @return string
	 */
	function do_shortcode( $content ) {
		return (string) $content;
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-settings.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-geoip.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-api.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-shortcodes.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-surface-settings.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-gutenberg.php';
require_once dirname( __DIR__, 2 ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__, 2 ) . '/includes/decision/class-rwgc-decision.php';
RWGC_Decision::load();
require_once dirname( __DIR__, 2 ) . '/includes/slots/class-rwgc-experience-slots.php';
RWGC_Experience_Slots::load();
require_once dirname( __DIR__, 2 ) . '/includes/integrations/gutenberg/class-rwgc-gutenberg-experience-slot.php';
require_once dirname( __DIR__, 2 ) . '/includes/integrations/elementor/class-rwgc-elementor-experience-slots.php';

/**
 * Element stand-in for the Elementor slot render hooks.
 */
final class RWGC_Test_Elementor_Element {

	/** @var array<string, mixed> */
	public $settings;

	/** @var string */
	public $id;

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $id Element id.
	 */
	public function __construct( array $settings, $id = 'abc123' ) {
		$this->settings = $settings;
		$this->id       = $id;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_settings_for_display() {
		return $this->settings;
	}

	/**
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @return void
	 */
	public function set_settings( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function add_render_attribute( $key, $value ) {
		unset( $key, $value );
	}
}

final class RWGCGeoContentEscapePathsTest extends TestCase {

	private const YOUTUBE = 'https://www.youtube.com/embed/dQw4w9WgXcQ';

	protected function tearDown(): void {
		unset( $GLOBALS['rwgc_test_filters'] );
	}

	public function test_block_keeps_embed_and_form_and_strips_hostile_markup(): void {
		$html = RWGC_Gutenberg::render_geo_content_block( array(), $this->sample_markup() );
		$this->assert_embed_and_form_kept( $html );
	}

	public function test_shortcode_keeps_embed_and_form_and_strips_hostile_markup(): void {
		$html = RWGC_Shortcodes::render_conditional(
			array(
				'exclude' => 'ZZ',
			),
			$this->sample_markup()
		);
		$this->assert_embed_and_form_kept( $html );
	}

	public function test_gutenberg_slot_keeps_embed_and_form_and_strips_hostile_markup(): void {
		RWGC_Experience_Slot_Registry::reset_cache();
		$seen = array();
		$sync = RWGC_Gutenberg_Experience_Slot::sync_attributes(
			array(
				'slotName'       => 'Homepage Hero',
				'managementMode' => 'local',
			),
			'/',
			$seen
		);

		$html = RWGC_Gutenberg_Experience_Slot::filter_render_block(
			$this->sample_markup(),
			array(
				'blockName' => 'reactwoo/experience-slot',
				'attrs'     => array(
					'slotId' => $sync['attrs']['slotId'],
				),
			)
		);
		$this->assert_embed_and_form_kept( $html );
	}

	public function test_elementor_slot_keeps_embed_and_form_and_strips_hostile_markup(): void {
		RWGC_Experience_Slot_Registry::reset_cache();
		$element = new RWGC_Test_Elementor_Element(
			array(
				'rwgc_use_experience_slot' => 'yes',
				'rwgc_experience_slot_name' => 'Hero',
			)
		);

		ob_start();
		RWGC_Elementor_Experience_Slots::before_render( $element );
		echo $this->sample_markup();
		RWGC_Elementor_Experience_Slots::after_render( $element );
		$html = (string) ob_get_clean();

		$this->assert_embed_and_form_kept( $html );
	}

	public function test_elementor_popup_escaper_keeps_embed_and_form_and_strips_hostile_markup(): void {
		$html = rwgc_kses_rendered_html( $this->builder_sample() . $this->sample_markup() );
		$this->assert_embed_and_form_kept( $html );
		$this->assertStringContainsString( 'data-elementor-type="popup"', $html );
		$this->assertStringContainsString( 'data-elementor-id="99"', $html );

		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/integrations/elementor/class-rwgc-elementor-popups.php' );
		$this->assertStringContainsString( 'rwgc_kses_rendered_html( $html )', $src );
	}

	public function test_hostile_filter_cannot_add_script_handlers_or_srcdoc(): void {
		$GLOBALS['rwgc_test_filters']['rwgc_geo_content_allowed_html'] = static function ( $allowed ) {
			$allowed['script']            = array( 'src' => true );
			$allowed['iframe']['srcdoc']  = true;
			$allowed['iframe']['onload']  = true;
			$allowed['img']['onerror']    = true;
			return $allowed;
		};

		$allowed = rwgc_geo_content_allowed_html();
		$this->assertArrayNotHasKey( 'script', $allowed );
		$this->assertArrayNotHasKey( 'srcdoc', $allowed['iframe'] );
		$this->assertArrayNotHasKey( 'onload', $allowed['iframe'] );
		$this->assertArrayNotHasKey( 'onerror', $allowed['img'] );

		$html = rwgc_kses_geo_content_html(
			'<script src="https://evil.example/a.js">RWGC_SCRIPT_TOKEN</script>'
			. '<iframe src="' . self::YOUTUBE . '" srcdoc="<script>alert(1)</script>" onload="alert(1)"></iframe>'
			. '<img src="https://example.com/a.png" onerror="alert(1)" alt="pic">'
		);

		$this->assertStringContainsString( self::YOUTUBE, $html );
		$this->assertStringNotContainsString( '<script', strtolower( $html ) );
		$this->assertStringNotContainsString( 'RWGC_SCRIPT_TOKEN', $html );
		$this->assertStringNotContainsString( 'srcdoc', strtolower( $html ) );
		$this->assertStringNotContainsString( 'onload', strtolower( $html ) );
		$this->assertStringNotContainsString( 'onerror', strtolower( $html ) );
	}

	/**
	 * @param string $html Escaped HTML.
	 * @return void
	 */
	private function assert_embed_and_form_kept( $html ): void {
		$this->assertStringContainsString( self::YOUTUBE, $html );
		$this->assertStringContainsString( 'action="/join"', $html );
		$this->assertStringContainsString( 'method="post"', $html );
		$this->assertStringContainsString( 'name="plan"', $html );
		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'href="https://example.com/offer"', $html );
		$this->assertStringNotContainsString( '<script', strtolower( $html ) );
		$this->assertStringNotContainsString( 'RWGC_SCRIPT_TOKEN', $html );
		$this->assertStringNotContainsString( 'onerror', strtolower( $html ) );
		$this->assertStringNotContainsString( 'javascript:', strtolower( $html ) );
		$this->assertStringNotContainsString( 'http://evil.example/embed', $html );
		$this->assertStringNotContainsString( 'data:text/html', $html );
		$this->assertSame( 1, substr_count( strtolower( $html ), '<iframe' ) );
	}

	/**
	 * @return string
	 */
	private function sample_markup(): string {
		return '<iframe src="' . self::YOUTUBE . '" width="560" height="315" title="YouTube video" allowfullscreen></iframe>'
			. '<form action="/join" method="post" class="signup" id="signup" target="_self">'
			. '<label for="email">Email</label>'
			. '<input type="email" name="email" id="email">'
			. '<select name="plan" id="plan"><option value="pro" selected>Pro</option></select>'
			. '<button type="submit">Join</button></form>'
			. '<a href="https://example.com/offer">Offer</a>'
			. '<script>RWGC_SCRIPT_TOKEN</script>'
			. '<img src="https://example.com/a.png" onerror="alert(1)" alt="pic">'
			. '<a href="javascript:alert(1)">click</a>'
			. '<iframe src="javascript:alert(2)"></iframe>'
			. '<iframe src="http://evil.example/embed"></iframe>'
			. '<iframe src="data:text/html,hi"></iframe>'
			. '<iframe title="nosrc"></iframe>';
	}

	/**
	 * @return string
	 */
	private function builder_sample(): string {
		return '<div class="elementor-popup" data-elementor-type="popup" data-elementor-id="99">';
	}

}
