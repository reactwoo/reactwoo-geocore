<?php
/**
 * Regression tests for the Insights AI UX Reviewer embed.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'rwgc_uses_platform_shell' ) ) {
	function rwgc_uses_platform_shell() {
		return true;
	}
}

if ( ! function_exists( 'rwgc_is_geo_ai_active' ) ) {
	function rwgc_is_geo_ai_active() {
		return true;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 7;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return isset( $GLOBALS['rwgc_test_transients'][ $key ] )
			? $GLOBALS['rwgc_test_transients'][ $key ]
			: false;
	}
}

if ( ! function_exists( 'rwgc_get_suite_capability_map' ) ) {
	function rwgc_get_suite_capability_map() {
		return array( 'optimise' => array( 'ai_review' => true ) );
	}
}

if ( ! class_exists( 'RWGA_UX_Reviewer_UI', false ) ) {
	class RWGA_UX_Reviewer_UI {
		/** @var array<string, mixed> */
		public static $workspace_args = array();

		/**
		 * @param array<string, mixed> $args Workspace arguments.
		 * @return void
		 */
		public static function render_workspace( array $args = array() ) {
			self::$workspace_args = $args;
		}
	}
}

/**
 * @coversNothing
 */
class RWGCInsightsAiOpportunitiesViewTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rwgc_test_transients']       = array();
		RWGA_UX_Reviewer_UI::$workspace_args = array();
		$_GET                                 = array();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['rwgc_test_transients'] );
		$_GET = array();
	}

	private function render_view(): void {
		ob_start();
		require dirname( __DIR__, 2 ) . '/admin/views/insights-ai-opportunities-page.php';
		ob_end_clean();
	}

	public function test_cached_review_is_rendered_in_result_mode(): void {
		$cards = array( array( 'title' => 'Checkout friction' ) );
		$GLOBALS['rwgc_test_transients']['rwga_ux_review_7'] = $cards;

		$this->render_view();

		$this->assertSame( 'result', RWGA_UX_Reviewer_UI::$workspace_args['display_mode'] );
		$this->assertSame( $cards, RWGA_UX_Reviewer_UI::$workspace_args['cards'] );
	}

	public function test_empty_review_is_rendered_in_fresh_mode(): void {
		$this->render_view();

		$this->assertSame( 'fresh', RWGA_UX_Reviewer_UI::$workspace_args['display_mode'] );
		$this->assertSame( array(), RWGA_UX_Reviewer_UI::$workspace_args['cards'] );
	}
}
