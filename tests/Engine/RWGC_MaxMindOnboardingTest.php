<?php

use PHPUnit\Framework\TestCase;

/**
 * MaxMind onboarding copy stays aligned with the real download schedule.
 *
 * @coversNothing
 */
final class RWGC_MaxMindOnboardingTest extends TestCase {

	/**
	 * @param string $relative Path from the plugin root.
	 * @return string
	 */
	private function source( $relative ) {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $relative );
	}

	public function test_maxmind_screen_explains_the_free_key_and_real_download(): void {
		$view = $this->source( 'admin/views/integrations-maxmind-page.php' );
		$this->assertStringContainsString( 'How to get your free MaxMind key', $view );
		$this->assertStringContainsString( 'RWGC_MaxMind::GEO_LITE_SIGNUP_URL', $view );
		$this->assertStringContainsString( 'RWGC_MaxMind::LICENSE_KEYS_URL', $view );
		$this->assertStringContainsString( 'RWGC_MaxMind::ACCOUNT_INFO_URL', $view );
		$this->assertStringContainsString( 'MaxMind shows the full key only once.', $view );
		$this->assertStringContainsString( 'Saving credentials does not download it.', $view );
		$this->assertStringContainsString( 'RWGC_Admin_UI::render_numbered_steps(', $view );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $view );
		$this->assertStringContainsString( 'target="_blank"', $view );
		$this->assertStringContainsString( 'wp_kses(', $view );
	}

	public function test_verified_maxmind_urls_are_the_current_pages(): void {
		$src = $this->source( 'includes/class-rwgc-maxmind.php' );
		$this->assertStringContainsString( "const GEO_LITE_SIGNUP_URL = 'https://www.maxmind.com/en/create-account';", $src );
		$this->assertStringContainsString( "const LICENSE_KEYS_URL = 'https://www.maxmind.com/en/accounts/current/license-key';", $src );
		$this->assertStringContainsString( "const ACCOUNT_INFO_URL = 'https://www.maxmind.com/en/accounts/current/edit';", $src );
	}

	public function test_wizard_step_two_links_to_the_maxmind_screen(): void {
		$view = $this->source( 'admin/views/getting-started.php' );
		$this->assertStringContainsString( 'MaxMind screen', $view );
		$this->assertStringContainsString( 'rwgc_get_maxmind_admin_url', $view );
		$this->assertStringContainsString( 'MaxMind shows the full key only once.', $view );
		$this->assertStringContainsString( 'RWGC_Admin_UI::render_numbered_steps(', $view );
		$registry = $this->source( 'includes/class-rwgc-module-registry.php' );
		$this->assertStringContainsString( 'fallback_visitor_sentence()', $registry );
		$this->assertStringContainsString( 'MaxMind shows it only once', $registry );
	}

	public function test_missing_database_warning_names_the_fallback_country(): void {
		$admin = $this->source( 'includes/class-rwgc-admin.php' );
		$this->assertStringContainsString( 'fallback_visitor_sentence()', $admin );
		$this->assertStringContainsString( 'Until a database is added, every visitor is treated as %s.', $this->source( 'includes/class-rwgc-settings.php' ) );
	}

	public function test_dashboard_and_plugins_notice_is_dismissed_per_user(): void {
		$admin = $this->source( 'includes/class-rwgc-admin.php' );
		$this->assertStringContainsString( "array( 'dashboard', 'plugins' )", $admin );
		$this->assertStringContainsString( "const MAXMIND_DB_NOTICE_META = 'rwgc_dismissed_maxmind_db_notice';", $admin );
		$this->assertStringContainsString( "check_admin_referer( 'rwgc_dismiss_maxmind_db_notice' )", $admin );
		$this->assertStringContainsString( "check_ajax_referer( 'rwgc_dismiss_maxmind_db_notice' )", $admin );
		$this->assertStringContainsString( 'update_user_meta( $user_id, self::MAXMIND_DB_NOTICE_META, \'1\' )', $admin );
		$this->assertStringContainsString( '! self::can_manage()', $admin );
		$this->assertStringContainsString( 'class="button button-primary"', $admin );
		$this->assertStringContainsString( 'rwgc-integrations-maxmind', $admin );
		$ui = $this->source( 'includes/class-rwgc-admin-ui.php' );
		$this->assertStringContainsString( 'rwgc-platform-overview__steps', $ui );
		$this->assertStringContainsString( 'rwgc-suite-stepper__num', $ui );
		$this->assertStringNotContainsString( 'Pro', $this->source( 'admin/js/rwgc-maxmind-db-notice.js' ) );
	}

	public function test_dashboard_checklist_accepts_a_key_or_a_usable_database(): void {
		$view = $this->source( 'admin/views/dashboard-page.php' );
		$this->assertStringContainsString( '$rwgc_maxmind_ok       = $rwgc_has_license_key || $rwgc_db_usable;', $view );
		$this->assertStringContainsString( "empty( \$rwgc_status['usable'] )", $view );
	}

	public function test_readme_install_and_external_services_match_the_schedule(): void {
		$readme = $this->source( 'readme.txt' );
		$this->assertStringContainsString( 'https://www.maxmind.com/en/create-account', $readme );
		$this->assertStringContainsString( 'https://www.maxmind.com/en/accounts/current/license-key', $readme );
		$this->assertStringContainsString( '= Does this plugin include MaxMind? =', $readme );
		$faq = strstr( $readme, '= Does this plugin include MaxMind? =' );
		$this->assertIsString( $faq );
		$this->assertStringContainsString( 'https://www.maxmind.com/en/create-account', substr( (string) $faq, 0, 500 ) );
		$this->assertStringContainsString( 'about one to seven hours', $readme );
		$this->assertStringContainsString( 'Visitor page views do not contact MaxMind.', $readme );
		$this->assertStringContainsString( 'Stable tag: 1.9.25', $readme );
	}
}
