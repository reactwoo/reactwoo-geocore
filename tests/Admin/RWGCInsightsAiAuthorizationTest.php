<?php
/**
 * Regression tests for Insights AI UX Reviewer authorization.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'RWGA_Plugin', false ) ) {
	class RWGA_Plugin {}
}

if ( ! class_exists( 'RWGA_Capabilities', false ) ) {
	class RWGA_Capabilities {
		const CAP_VIEW_REPORTS = 'rwga_view_ai_reports';
	}
}

if ( ! class_exists( 'RWGC_Suite_Capability_Map', false ) ) {
	class RWGC_Suite_Capability_Map {
		public static function get_map() {
			return array( 'geo_ai_active' => true );
		}
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return ! empty( $GLOBALS['rwgc_test_user_caps'][ (string) $capability ] );
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

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! class_exists( 'RWGC_Admin_UI', false ) ) {
	class RWGC_Admin_UI {
		public static function render_page_header( $title, $description ) {
			unset( $title, $description );
		}
	}
}

if ( ! class_exists( 'RWGA_UX_Reviewer_UI', false ) ) {
	class RWGA_UX_Reviewer_UI {
		public static $render_calls = 0;
		public static $workspace_args = array();

		public static function render_workspace( array $args = array() ) {
			self::$render_calls++;
			self::$workspace_args = $args;
		}
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';

/**
 * @coversNothing
 */
class RWGCInsightsAiAuthorizationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rwgc_test_user_caps']  = array();
		$GLOBALS['rwgc_test_transients'] = array(
			'rwga_ux_review_7' => array( array( 'title' => 'Sensitive finding' ) ),
		);
		RWGA_UX_Reviewer_UI::$render_calls   = 0;
		RWGA_UX_Reviewer_UI::$workspace_args = array();
		$_GET                                = array();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['rwgc_test_user_caps'], $GLOBALS['rwgc_test_transients'] );
		$_GET = array();
	}

	private function render_view(): string {
		ob_start();
		require dirname( __DIR__, 2 ) . '/admin/views/insights-ai-opportunities-page.php';
		return (string) ob_get_clean();
	}

	public function test_user_without_report_capability_cannot_render_workspace(): void {
		$output = $this->render_view();

		$this->assertSame( 0, RWGA_UX_Reviewer_UI::$render_calls );
		$this->assertStringContainsString( 'Access restricted', $output );
	}

	public function test_user_with_report_capability_can_render_workspace(): void {
		$GLOBALS['rwgc_test_user_caps'][ RWGA_Capabilities::CAP_VIEW_REPORTS ] = true;

		$this->render_view();

		$this->assertSame( 1, RWGA_UX_Reviewer_UI::$render_calls );
		$this->assertSame(
			$GLOBALS['rwgc_test_transients']['rwga_ux_review_7'],
			RWGA_UX_Reviewer_UI::$workspace_args['cards']
		);
	}
}
