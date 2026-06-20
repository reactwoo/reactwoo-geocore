<?php
/**
 * Regression coverage for admin UI safety checks.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class AdminUiRegressionTest extends TestCase {

	/**
	 * @return string
	 */
	private function targeting_assistant_js() {
		return (string) file_get_contents( dirname( __DIR__ ) . '/admin/js/rwgc-targeting-assistant.js' );
	}

	public function test_targeting_assistant_execute_fails_closed_on_unconfirmed_responses() {
		$js = $this->targeting_assistant_js();

		$this->assertMatchesRegularExpression(
			'/if \( ! state\.proposalId \|\| ! cfg\.executeUrl \) \{\s*showExecutionError\(\);\s*return;\s*\}/',
			$js
		);
		$this->assertMatchesRegularExpression(
			'/\.done\( function \( response \) \{\s*if \( ! response \|\| ! response\.success \) \{\s*showExecutionError/',
			$js
		);
		$this->assertMatchesRegularExpression(
			'/\.fail\( function \( xhr \) \{\s*showExecutionError/',
			$js
		);
		$this->assertDoesNotMatchRegularExpression(
			'/\.fail\( function \( xhr \) \{\s*goWorkflowFromProposal\(\);/',
			$js
		);
	}

	public function test_suite_admin_pages_use_shared_manage_capability() {
		$suite_admin = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgc-suite-admin.php' );

		$this->assertStringContainsString( 'private static function can_manage_suite()', $suite_admin );
		foreach ( array( 'render_suite_home', 'render_getting_started', 'render_suite_variants', 'handle_save_wizard', 'handle_dismiss_welcome' ) as $method ) {
			$this->assertMatchesRegularExpression(
				'/public static function ' . preg_quote( $method, '/' ) . '\(\) \{\s*if \( ! self::can_manage_suite\(\) \)/',
				$suite_admin
			);
		}
	}

	public function test_maxmind_maintenance_actions_use_shared_manage_capability() {
		$view = (string) file_get_contents( dirname( __DIR__ ) . '/admin/views/integrations-maxmind-page.php' );

		$this->assertStringContainsString( '$can_manage   = class_exists( \'RWGC_Admin\', false ) ? RWGC_Admin::can_manage() : current_user_can( \'manage_options\' );', $view );
		$this->assertStringContainsString( 'isset( $_GET[\'rwgc_action\'], $_GET[\'_wpnonce\'] ) && $can_manage', $view );
	}
}
