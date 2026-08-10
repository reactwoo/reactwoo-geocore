<?php
/**
 * Atomic library-only attachments must inherit the saved rule mode.
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-surface-settings.php';

if ( ! class_exists( 'RWGC_Rule_Registry', false ) ) {
	/**
	 * Minimal stub for library mode lookup in isolation tests.
	 */
	class RWGC_Rule_Registry {
		/**
		 * @var array<string, array<string, mixed>>
		 */
		public static $sets = array();

		/**
		 * @param int|string $rule_id Rule id.
		 * @return array<string, mixed>|null
		 */
		public static function get_rule_set_by_id( $rule_id ) {
			$key = (string) $rule_id;
			return isset( self::$sets[ $key ] ) && is_array( self::$sets[ $key ] ) ? self::$sets[ $key ] : null;
		}
	}
}

final class RWGCSurfaceSettingsAtomicLibraryModeTest extends TestCase {

	protected function setUp(): void {
		RWGC_Rule_Registry::$sets = array();
	}

	public function test_library_only_attachment_adopts_hide_if_mode(): void {
		RWGC_Rule_Registry::$sets['42'] = array(
			'mode'  => 'hide_if',
			'rules' => array(),
		);

		$normalized = RWGC_Surface_Settings::normalize(
			array(
				'rwgc_enable_visibility_rules'  => true,
				'rwgc_visibility_rules_mode'    => 'show_if', // persisted Atomic schema default
				'rwgc_visibility_rule_library'  => '42',
			)
		);

		$this->assertSame( '42', $normalized['rwgc_applied_visibility_rule_id'] );
		$this->assertSame( 'hide_if', $normalized['rwgc_visibility_rules_mode'] );
	}

	public function test_inline_portable_keeps_explicit_surface_mode(): void {
		RWGC_Rule_Registry::$sets['42'] = array(
			'mode'  => 'hide_if',
			'rules' => array(),
		);

		$normalized = RWGC_Surface_Settings::normalize(
			array(
				'rwgc_enable_visibility_rules' => 'yes',
				'rwgc_visibility_rules_mode'   => 'show_if',
				'rwgc_visibility_rule_library' => '42',
				'rwgc_portable_geo_targeting'  => '{"mode":"hide_if","rules":[]}',
			)
		);

		// Classic Elementor path: portable JSON present — do not clobber saved surface mode.
		$this->assertSame( 'show_if', $normalized['rwgc_visibility_rules_mode'] );
	}

	public function test_legacy_country_hide_if_recovered_when_country_mode_unset(): void {
		$normalized = RWGC_Surface_Settings::normalize(
			array(
				'egp_enable_geo_targeting' => 'yes',
				'rwgc_visibility_mode'     => 'hide_if',
				'egp_countries'            => array( 'FR' ),
			)
		);

		$this->assertSame( 'hide_if', $normalized['rwgc_country_visibility_mode'] );
	}
}
