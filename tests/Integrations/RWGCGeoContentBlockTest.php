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

	public function test_editor_script_handle_follows_the_handle_wordpress_registered(): void {
		$this->assertSame(
			'rwgc-geo-content-editor',
			RWGC_Gutenberg::normalize_editor_script_handle( array( 'rwgc-geo-content-editor' ) )
		);
		$this->assertSame(
			'reactwoo-geocore-geo-content-editor-script',
			RWGC_Gutenberg::normalize_editor_script_handle( array( 'reactwoo-geocore-geo-content-editor-script' ) )
		);
		$this->assertSame(
			'rwgc-geo-content-editor',
			RWGC_Gutenberg::normalize_editor_script_handle( 'rwgc-geo-content-editor' )
		);
	}

	public function test_editor_script_handle_falls_back_when_block_metadata_has_none(): void {
		$this->assertSame( 'rwgc-geo-content-editor', RWGC_Gutenberg::normalize_editor_script_handle( array() ) );
		$this->assertSame( 'rwgc-geo-content-editor', RWGC_Gutenberg::normalize_editor_script_handle( '' ) );
		$this->assertSame( 'rwgc-geo-content-editor', RWGC_Gutenberg::normalize_editor_script_handle( null ) );
		$this->assertSame( 'rwgc-geo-content-editor', RWGC_Gutenberg::normalize_editor_script_handle( false ) );
	}

	public function test_country_options_follow_the_registered_editor_handle(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-gutenberg.php' );

		$this->assertStringContainsString( 'editor_script_handle()', $src );
		$this->assertStringNotContainsString( "wp_script_is( 'rwgc-geo-content-editor', 'enqueued' )", $src );
		$this->assertStringNotContainsString( "wp_add_inline_script(\n\t\t\t'rwgc-geo-content-editor'", $src );
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

	public function test_geo_content_allowlist_adds_embeds_and_blocks_handlers(): void {
		$allowed = rwgc_geo_content_allowed_html();

		$this->assertArrayHasKey( 'iframe', $allowed );
		foreach ( array( 'src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'frameborder', 'loading', 'referrerpolicy', 'style', 'class', 'id' ) as $attr ) {
			$this->assertArrayHasKey( $attr, $allowed['iframe'] );
		}
		$this->assertArrayHasKey( 'form', $allowed );
		foreach ( array( 'action', 'method', 'class', 'id', 'target' ) as $attr ) {
			$this->assertArrayHasKey( $attr, $allowed['form'] );
		}
		foreach ( array( 'input', 'select', 'option', 'textarea', 'button', 'label' ) as $tag ) {
			$this->assertArrayHasKey( $tag, $allowed );
			foreach ( array( 'type', 'name', 'value', 'placeholder', 'required', 'checked', 'selected', 'disabled', 'for', 'class', 'id' ) as $attr ) {
				$this->assertArrayHasKey( $attr, $allowed[ $tag ] );
			}
		}
		$this->assertArrayHasKey( 'fieldset', $allowed );
		$this->assertArrayHasKey( 'legend', $allowed );
		$this->assertArrayNotHasKey( 'script', $allowed );
		foreach ( $allowed as $attrs ) {
			$this->assertIsArray( $attrs );
			foreach ( array_keys( $attrs ) as $attr ) {
				$this->assertFalse( str_starts_with( strtolower( (string) $attr ), 'on' ) );
			}
		}

		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php' );
		$this->assertStringContainsString( "apply_filters( 'rwgc_geo_content_allowed_html'", $src );
	}

	public function test_youtube_iframe_and_simple_form_survive_geo_content_escape(): void {
		$html = RWGC_Gutenberg::render_geo_content_block(
			array(),
			'<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="560" height="315" title="YouTube video" allow="accelerometer; autoplay" allowfullscreen frameborder="0" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" style="border:0" class="yt" id="yt1"></iframe>'
			. '<form action="/join" method="post" class="signup" id="signup" target="_self">'
			. '<fieldset class="fields" id="fields"><legend class="legend" id="legend">Join</legend>'
			. '<label for="email" class="lbl" id="lbl">Email</label>'
			. '<input type="email" name="email" value="" placeholder="you@example.com" required class="email" id="email" aria-required="true" data-field="email">'
			. '<select name="plan" id="plan"><option value="pro" selected>Pro</option></select>'
			. '<textarea name="note" placeholder="Note" id="note"></textarea>'
			. '<button type="submit" class="btn" id="go">Join</button>'
			. '</fieldset></form>'
		);

		$this->assertStringContainsString( 'https://www.youtube.com/embed/dQw4w9WgXcQ', $html );
		$this->assertStringContainsString( '<iframe', $html );
		$this->assertStringContainsString( 'allowfullscreen', $html );
		$this->assertStringContainsString( '<form', $html );
		$this->assertStringContainsString( 'action="/join"', $html );
		$this->assertStringContainsString( 'method="post"', $html );
		$this->assertStringContainsString( '<fieldset', $html );
		$this->assertStringContainsString( '<legend', $html );
		$this->assertStringContainsString( '<label', $html );
		$this->assertStringContainsString( 'for="email"', $html );
		$this->assertStringContainsString( '<input', $html );
		$this->assertStringContainsString( 'aria-required="true"', $html );
		$this->assertStringContainsString( 'data-field="email"', $html );
		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( '<option', $html );
		$this->assertStringContainsString( 'selected', $html );
		$this->assertStringContainsString( '<textarea', $html );
		$this->assertStringContainsString( '<button', $html );
	}

	public function test_script_onclick_and_javascript_src_are_stripped(): void {
		$html = RWGC_Gutenberg::render_geo_content_block(
			array(),
			'<script>RWGC_SCRIPT_TOKEN</script>'
			. '<button type="submit" onclick="alert(1)">Go</button>'
			. '<iframe src="javascript:alert(1)" title="bad"></iframe>'
			. '<iframe src="http://evil.example/embed" title="http"></iframe>'
			. '<form action="javascript:alert(2)" method="post"><input type="text" name="q"></form>'
		);

		$this->assertStringNotContainsString( '<script', strtolower( $html ) );
		$this->assertStringNotContainsString( 'RWGC_SCRIPT_TOKEN', $html );
		$this->assertStringNotContainsString( 'onclick', strtolower( $html ) );
		$this->assertStringNotContainsString( 'javascript:', strtolower( $html ) );
		$this->assertStringNotContainsString( 'http://evil.example/embed', $html );
		$this->assertStringContainsString( '<button', $html );
		$this->assertStringContainsString( '>Go</button>', $html );
	}
}
