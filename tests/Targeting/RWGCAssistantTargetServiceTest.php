<?php
/**
 * Assistant target service tests.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers RWGC_Assistant_Target_Service
 */
class RWGCAssistantTargetServiceTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$path = dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-assistant-target-service.php';
		require_once $path;
	}

	public function test_format_popup_row_shape(): void {
		$post              = new WP_Post();
		$post->ID          = 42;
		$post->post_status = 'draft';
		$post->post_title  = 'Popup America';

		$row = RWGC_Assistant_Target_Service::format_popup_row( $post );

		$this->assertSame( 42, $row['id'] );
		$this->assertSame( 'Popup America', $row['label'] );
		$this->assertSame( 'draft', $row['status'] );
		$this->assertStringContainsString( 'post.php?post=42', $row['edit_url'] );
	}

	public function test_normalize_popup_label_strips_suffix(): void {
		$this->assertSame( 'free delivery', RWGC_Assistant_Target_Service::normalize_popup_label( 'Free Delivery popup' ) );
		$this->assertSame( 'winter sale', RWGC_Assistant_Target_Service::normalize_popup_label( 'Winter Sale pop-up' ) );
		$this->assertSame( 'exit intent', RWGC_Assistant_Target_Service::normalize_popup_label( 'Exit Intent modal' ) );
		$this->assertSame( 'free delivery', RWGC_Assistant_Target_Service::normalize_popup_label( 'Free Delivery' ) );
	}
}
