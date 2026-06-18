<?php
/**
 * Tests for the targeting assistant REST endpoint.
 */

if ( ! function_exists( 'rest_ensure_response' ) ) {
	/**
	 * @param mixed $response Response payload.
	 * @return mixed
	 */
	function rest_ensure_response( $response ) {
		return $response;
	}
}

if ( ! function_exists( 'rwgc_get_portable_targeting_editor_context' ) ) {
	/**
	 * @return array<string, mixed>
	 */
	function rwgc_get_portable_targeting_editor_context() {
		return array(
			'pro'                => true,
			'visibility_library' => array(
				array(
					'id'    => 123,
					'title' => 'Sensitive rule',
					'rules' => array( 'private' => true ),
				),
			),
		);
	}
}

if ( ! class_exists( 'RWGC_Admin', false ) ) {
	class RWGC_Admin {
		/**
		 * @var bool
		 */
		public static $can_manage = false;

		/**
		 * @return bool
		 */
		public static function can_manage() {
			return self::$can_manage;
		}
	}
}

if ( ! class_exists( 'RWGA_Local_Intent_Interpreter', false ) ) {
	class RWGA_Local_Intent_Interpreter {
		/**
		 * @var array<string, mixed>
		 */
		public static $last_context = array();

		/**
		 * @param string              $phrase  Phrase.
		 * @param array<string,mixed> $context Context.
		 * @return array<string, mixed>
		 */
		public static function interpret( $phrase, $context ) {
			self::$last_context = $context;
			return array(
				'matched_action' => 'create_rule',
				'summary'        => 'Create a targeting rule.',
			);
		}
	}
}

class RWGC_Targeting_Assistant_Test_Request {
	/**
	 * @var array<string, mixed>
	 */
	private $params;

	/**
	 * @param array<string, mixed> $params Request params.
	 */
	public function __construct( array $params ) {
		$this->params = $params;
	}

	/**
	 * @param string $key Param name.
	 * @return mixed
	 */
	public function get_param( $key ) {
		return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgc-rest.php';

class TargetingAssistantRestTest extends \PHPUnit\Framework\TestCase {
	/**
	 * @return void
	 */
	public function test_permissions_targeting_assistant_uses_geo_core_admin_capability() {
		RWGC_Admin::$can_manage = false;
		$this->assertFalse( RWGC_REST::permissions_targeting_assistant() );

		RWGC_Admin::$can_manage = true;
		$this->assertTrue( RWGC_REST::permissions_targeting_assistant() );
	}

	/**
	 * @return void
	 */
	public function test_interpret_response_does_not_expose_editor_context() {
		$request = new RWGC_Targeting_Assistant_Test_Request(
			array(
				'phrase'  => 'Show this to Australian mobile visitors',
				'context' => array(
					'country_override' => 'AU',
					'device_override'  => 'mobile',
				),
			)
		);

		$response = RWGC_REST::post_targeting_interpret( $request );

		$this->assertIsArray( $response );
		$this->assertArrayNotHasKey( 'editor_context', $response );
		$this->assertTrue( RWGA_Local_Intent_Interpreter::$last_context['pro'] );
	}
}
