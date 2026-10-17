<?php
/**
 * Admin colour contract (1.9.14): no partial dark theme, AA muted text.
 *
 * Rendered checks (390px/1280px, dark and light) live in qa/1914/contrast/audit.js;
 * this guards the stylesheet against the 1.9.13 regression coming back.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
final class RWGCAdminContrastTest extends TestCase {

	/**
	 * @param string $name File under admin/css.
	 * @return string
	 */
	private function css( $name ) {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/css/' . $name );
	}

	/**
	 * WCAG relative-luminance contrast ratio.
	 *
	 * @param string $a Hex colour.
	 * @param string $b Hex colour.
	 * @return float
	 */
	private function ratio( $a, $b ) {
		$lum = static function ( $hex ) {
			$hex = ltrim( $hex, '#' );
			$out = 0.0;
			foreach ( array( 0.2126, 0.7152, 0.0722 ) as $i => $w ) {
				$c    = hexdec( substr( $hex, $i * 2, 2 ) ) / 255;
				$c    = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
				$out += $w * $c;
			}
			return $out;
		};
		$l1 = $lum( $a );
		$l2 = $lum( $b );
		return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
	}

	/**
	 * @return void
	 */
	public function test_no_partial_dark_theme_recolours_text_inside_light_cards(): void {
		foreach ( glob( dirname( __DIR__, 2 ) . '/admin/css/*.css' ) as $file ) {
			$css = (string) file_get_contents( $file );
			$this->assertDoesNotMatchRegularExpression( '/@media\s*\(\s*prefers-color-scheme:\s*dark\s*\)/', $css, basename( $file ) . ' must not ship a partial dark theme.' );
		}
	}

	/**
	 * @return void
	 */
	public function test_containers_set_their_own_foreground_from_tokens(): void {
		$css = $this->css( 'admin.css' );
		$this->assertMatchesRegularExpression( '/\.rwgc-wrap \.rwgc-card,\s*\.rwgc-wrap \.rwgc-addon-card,\s*\.rwgc-wrap \.notice,\s*\.rwgc-app-shell \.notice \{\s*color: var\(--rwgc-surface-fg\);/', $css );
		$this->assertMatchesRegularExpression( '/\.rwgc-wrap \{\s*color: var\(--rwgc-canvas-fg\);/', $css );
	}

	/**
	 * @return void
	 */
	public function test_tokens_meet_wcag_aa_on_their_backgrounds(): void {
		$css = $this->css( 'admin.css' );
		preg_match_all( '/--rwgc-(canvas|surface)-(fg|muted):\s*(#[0-9a-fA-F]{6})/', $css, $m, PREG_SET_ORDER );
		$this->assertCount( 4, $m );
		foreach ( $m as $token ) {
			$backgrounds = 'canvas' === $token[1] ? array( '#f0f0f1', '#f0f4f8' ) : array( '#ffffff' );
			foreach ( $backgrounds as $bg ) {
				$this->assertGreaterThanOrEqual( 4.5, $this->ratio( $token[3], $bg ), $token[0] . ' on ' . $bg );
			}
		}
	}

	/**
	 * #6b7280 is 4.4:1 on the WordPress canvas grey; muted text uses #4b5563.
	 *
	 * @return void
	 */
	public function test_muted_grey_is_aa_on_canvas_and_white(): void {
		foreach ( glob( dirname( __DIR__, 2 ) . '/admin/css/*.css' ) as $file ) {
			$css = preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $file ) );
			$this->assertStringNotContainsStringIgnoringCase( '#6b7280', (string) $css, basename( $file ) );
		}
		$this->assertGreaterThanOrEqual( 4.5, $this->ratio( '#4b5563', '#f0f4f8' ) );
		$this->assertGreaterThanOrEqual( 4.5, $this->ratio( '#1d4ed8', '#dbeafe' ) );
		$this->assertGreaterThanOrEqual( 4.5, $this->ratio( '#047857', '#ffffff' ) );
		$this->assertStringContainsString( '--rw-suite-text-muted: #4b5563;', $this->css( 'rwgc-suite.css' ) );
	}
}
