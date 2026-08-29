<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers RWGC_Context_Resolver
 */
final class RWGC_ContextResolverTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$_GET    = array();
		$_COOKIE = array();
		RWGC_Context_Resolver::reset_cache();
		remove_all_filters( 'rwgc_target_provider_classes' );
		remove_all_filters( 'rwgc_matched_experience_profile' );
		remove_all_filters( 'rwgc_profile_match_candidates' );
		remove_all_filters( 'rwgc_context_snapshot_values' );
		add_filter(
			'rwgc_target_provider_classes',
			static function () {
				return array( 'RWGC_Target_Provider_Analytics' );
			}
		);
	}

	protected function tearDown(): void {
		remove_all_filters( 'rwgc_target_provider_classes' );
		remove_all_filters( 'rwgc_matched_experience_profile' );
		remove_all_filters( 'rwgc_profile_match_candidates' );
		remove_all_filters( 'rwgc_context_snapshot_values' );
		RWGC_Context_Resolver::reset_cache();
		parent::tearDown();
	}

	public function test_profile_id_alias_is_attached_after_runtime_profile_match(): void {
		add_filter(
			'rwgc_matched_experience_profile',
			static function ( $matched, $candidates, $context ) {
				unset( $matched, $candidates, $context );
				return array(
					'profile_id' => 'Enterprise-UK',
				);
			},
			10,
			3
		);

		$snapshot = RWGC_Context_Resolver::resolve_current();

		$this->assertSame( 'enterprise-uk', $snapshot->get( 'profile_id' ) );
		$this->assertSame( 'enterprise-uk', RWGC_Context_Resolver::resolve_target_value( 'profile_id' ) );
	}
}
