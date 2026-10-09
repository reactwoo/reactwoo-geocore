<?php
/**
 * Geo Content block: advanced-targeting config keys and inner-content gating.
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * @param mixed $args     Args.
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

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * @param string $path Path.
	 * @return string
	 */
	function home_url( $path = '' ) {
		return 'http://example.test/' . ltrim( (string) $path, '/' );
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-surface-settings.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-gutenberg.php';

final class RWGCGeoContentBlockTest extends TestCase {

	private const INNER = '<p class="rwgc-inner-marker">Inner offer</p>';

	public function test_advanced_targeting_flags_use_both_spellings(): void {
		$on = RWGC_Targeting_Rule_Set_Schema::advanced_targeting_editor_flags( true );
		$this->assertTrue( $on['advanced_targeting'] );
		$this->assertTrue( $on['advancedTargeting'] );
		$this->assertSame( $on['advanced_targeting'], $on['advancedTargeting'] );

		$off = RWGC_Targeting_Rule_Set_Schema::advanced_targeting_editor_flags( false );
		$this->assertFalse( $off['advanced_targeting'] );
		$this->assertFalse( $off['advancedTargeting'] );
		$this->assertSame( $off['advanced_targeting'], $off['advancedTargeting'] );
	}

	public function test_editor_context_sends_both_advanced_targeting_keys(): void {
		$ctx = RWGC_Targeting_Rule_Set_Schema::get_editor_context();

		$this->assertArrayHasKey( 'advanced_targeting', $ctx );
		$this->assertArrayHasKey( 'advancedTargeting', $ctx );
		$this->assertSame( $ctx['advanced_targeting'], $ctx['advancedTargeting'] );
		$this->assertIsBool( $ctx['advanced_targeting'] );
	}

	public function test_inner_content_renders_when_targeting_is_off(): void {
		$html = RWGC_Gutenberg::render_geo_content_block( array(), self::INNER );

		$this->assertStringContainsString( 'class="rwgc-geo-content"', $html );
		$this->assertStringContainsString( self::INNER, $html );
	}

	public function test_inner_content_is_hidden_when_show_if_rule_does_not_match(): void {
		$html = RWGC_Gutenberg::render_geo_content_block(
			array(
				'enableVisibilityRules' => true,
				'visibilityRulesMode'   => 'show_if',
				'visibilityRuleLibrary' => '123',
			),
			self::INNER
		);

		$this->assertSame( '', $html );
	}

	public function test_inner_content_renders_when_hide_if_rule_does_not_match(): void {
		$html = RWGC_Gutenberg::render_geo_content_block(
			array(
				'enableVisibilityRules' => true,
				'visibilityRulesMode'   => 'hide_if',
				'visibilityRuleLibrary' => '123',
			),
			self::INNER
		);

		$this->assertStringContainsString( 'class="rwgc-geo-content"', $html );
		$this->assertStringContainsString( self::INNER, $html );
	}
}
