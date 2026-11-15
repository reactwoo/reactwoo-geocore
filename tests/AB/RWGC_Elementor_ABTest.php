<?php
/**
 * Elementor A/B pairing and which sibling is rendered.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param mixed $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * @param string $scheme Scheme.
	 * @return string
	 */
	function wp_salt( $scheme = 'auth' ) {
		unset( $scheme );
		return 'rwgc-ab-test-salt';
	}
}

/**
 * Element stand-in. Records wrapper attributes and exposes settings.
 */
class RWGC_Elementor_AB_Element {

	/** @var string */
	public $id;

	/** @var array<string, mixed> */
	public $settings;

	/** @var array<string, mixed> */
	public $attrs = array();

	/**
	 * @param string               $id       Element id.
	 * @param array<string, mixed> $settings Settings.
	 */
	public function __construct( $id, array $settings ) {
		$this->id       = $id;
		$this->settings = $settings;
	}

	/** @return string */
	public function get_id() {
		return $this->id;
	}

	/** @return array<string, mixed> */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * @param string                    $element Wrapper key.
	 * @param array<string, mixed>|null $key     Attributes.
	 * @param mixed                     $value   Unused.
	 * @return void
	 */
	public function add_render_attribute( $element, $key = null, $value = null ) {
		unset( $element, $value );
		if ( is_array( $key ) ) {
			foreach ( $key as $name => $attr ) {
				$this->attrs[ $name ] = $attr;
			}
		}
	}
}

/**
 * @covers RWGC_Elementor_AB
 */
final class RWGC_Elementor_ABTest extends TestCase {

	/** @var array<string, mixed> */
	private $server = array();

	/** @var array<string, mixed> */
	private $cookie = array();

	/** @var bool */
	private $had_filters = false;

	/** @var array<string, mixed>|null */
	private $filters = null;

	/** @var array<int, mixed> */
	private $posts = array();

	/** @var array<int, mixed> */
	private $meta = array();

	/** @var int */
	private $seq = 0;

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		if ( ! defined( 'RWGC_PATH' ) ) {
			define( 'RWGC_PATH', dirname( __DIR__, 2 ) . '/' );
		}
		if ( ! defined( 'RWGC_URL' ) ) {
			define( 'RWGC_URL', 'https://example.test/wp-content/plugins/reactwoo-geocore/' );
		}
		if ( ! defined( 'RWGC_VERSION' ) ) {
			define( 'RWGC_VERSION', '1.9.26' );
		}
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-tests.php';
		require_once dirname( __DIR__, 2 ) . '/includes/integrations/class-rwgc-cache-compat.php';
		require_once dirname( __DIR__, 2 ) . '/includes/decision/class-rwgc-decision-experiment-assigner.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-assignment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-cache.php';
		require_once dirname( __DIR__, 2 ) . '/includes/integrations/elementor/class-rwgc-elementor-ab.php';
		require_once dirname( __DIR__, 2 ) . '/includes/integrations/elementor/class-rwgc-elementor-atomic-geo.php';
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->server      = $_SERVER;
		$this->cookie      = $_COOKIE;
		$this->had_filters = isset( $GLOBALS['rwgc_test_filters'] );
		$this->filters     = $this->had_filters && is_array( $GLOBALS['rwgc_test_filters'] ) ? $GLOBALS['rwgc_test_filters'] : null;
		$this->posts       = isset( $GLOBALS['rwgc_ab_test_posts'] ) && is_array( $GLOBALS['rwgc_ab_test_posts'] ) ? $GLOBALS['rwgc_ab_test_posts'] : array();
		$this->meta        = isset( $GLOBALS['rwgc_ab_test_meta'] ) && is_array( $GLOBALS['rwgc_ab_test_meta'] ) ? $GLOBALS['rwgc_ab_test_meta'] : array();
		$this->seq         = isset( $GLOBALS['rwgc_ab_test_seq'] ) ? (int) $GLOBALS['rwgc_ab_test_seq'] : 0;
		$_SERVER['REQUEST_METHOD']  = 'GET';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120.0.0.0';
		unset( $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );
		$GLOBALS['rwgc_test_is_admin'] = false;
		$GLOBALS['rwgc_test_filters']  = array(
			'rwgc_ab_emit_http_headers' => static function () {
				return false;
			},
		);
		$GLOBALS['rwgc_ab_test_posts'] = array();
		$GLOBALS['rwgc_ab_test_meta']  = array();
		$GLOBALS['rwgc_ab_test_seq']   = 0;
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Cache::reset_request_state();
		RWGC_Elementor_AB::reset_for_tests();
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		$_SERVER = $this->server;
		$_COOKIE = $this->cookie;
		if ( $this->had_filters ) {
			$GLOBALS['rwgc_test_filters'] = $this->filters;
		} else {
			unset( $GLOBALS['rwgc_test_filters'] );
		}
		$GLOBALS['rwgc_ab_test_posts'] = $this->posts;
		$GLOBALS['rwgc_ab_test_meta']  = $this->meta;
		$GLOBALS['rwgc_ab_test_seq']   = $this->seq;
		$GLOBALS['rwgc_test_is_admin'] = false;
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Cache::reset_request_state();
		RWGC_Elementor_AB::reset_for_tests();
		parent::tearDown();
	}

	public function test_init_does_not_fatal_when_elementor_is_inactive(): void {
		RWGC_Elementor_AB::init();
		RWGC_Elementor_AB::register_hooks();
		$element = new RWGC_Elementor_AB_Element( 'abc', array() );
		RWGC_Elementor_AB::register_controls( $element );
		$this->assertSame( array(), $element->attrs );
		$this->assertFalse( class_exists( '\Elementor\Plugin', false ) );
	}

	public function test_new_sibling_pair_creates_one_elementor_test(): void {
		$synced = RWGC_Elementor_AB::sync_tree( $this->pair( 'heroA', 'heroB', 'new', 'new' ), 40 );
		$this->assertTrue( $synced['changed'] );
		$a = $synced['elements'][0]['settings'];
		$b = $synced['elements'][1]['settings'];
		$this->assertSame( $a['rwgc_ab_test'], $b['rwgc_ab_test'] );
		$this->assertGreaterThan( 0, (int) $a['rwgc_ab_test'] );
		$this->assertSame( 'yes', $a['rwgc_ab_pair_ok'] );
		$this->assertSame( 'yes', $b['rwgc_ab_pair_ok'] );
		$this->assertSame( '', $a['rwgc_ab_notice'] ?? '' );
		$row = RWGC_AB_Tests::get( (int) $a['rwgc_ab_test'] );
		$this->assertIsArray( $row );
		$this->assertSame( 'publish', $row['status'] );
		$this->assertSame( 'elementor', $row['config']['adapter'] );
		$this->assertSame( 40, $row['config']['source_post_id'] );
		$this->assertSame( 'heroA', $row['config']['element_a'] );
		$this->assertSame( 'heroB', $row['config']['element_b'] );
		$this->assertSame( 'click', $row['config']['goal']['type'] );
		$this->assertSame( '.buy', $row['config']['goal']['selector'] );
		$this->assertSame( 'Homepage hero', $row['title'] );
	}

	public function test_atomic_value_wrappers_and_url_goal_are_stored(): void {
		$elements = array(
			array(
				'id'       => 'atomA',
				'settings' => array(
					'rwgc_ab_role'           => array(
						'$$type' => 'string',
						'value'  => 'a',
					),
					'rwgc_ab_test'           => array(
						'$$type' => 'string',
						'value'  => 'new',
					),
					'rwgc_ab_goal_type'      => 'url',
					'rwgc_ab_goal_url'       => 'javascript:alert(1)',
					'rwgc_ab_test_name'      => 'Atomic',
				),
				'elements' => array(),
			),
			array(
				'id'       => 'atomB',
				'settings' => array(
					'rwgc_ab_role' => 'b',
					'rwgc_ab_test' => 'new',
				),
				'elements' => array(),
			),
		);
		$synced = RWGC_Elementor_AB::sync_tree( $elements, 41 );
		$test   = $synced['elements'][0]['settings']['rwgc_ab_test'];
		$this->assertIsArray( $test );
		$this->assertSame( $test['value'], $synced['elements'][1]['settings']['rwgc_ab_test'] );
		$row = RWGC_AB_Tests::get( (int) $test['value'] );
		$this->assertSame( 'url', $row['config']['goal']['type'] );
		$this->assertSame( '', $row['config']['goal']['url'] );
		$this->assertSame( 'atomA', $row['config']['element_a'] );
		$this->assertSame( 'atomB', $row['config']['element_b'] );
	}

	public function test_second_pair_with_the_same_test_gets_its_own_test(): void {
		$first = RWGC_Elementor_AB::sync_tree( $this->pair( 'a1', 'b1', 'new', 'new' ), 42 );
		$id    = (string) $first['elements'][0]['settings']['rwgc_ab_test'];
		$tree  = array_merge(
			$this->pair( 'a1', 'b1', $id, $id ),
			$this->pair( 'a2', 'b2', $id, $id )
		);
		$synced = RWGC_Elementor_AB::sync_tree( $tree, 42 );
		$kept   = (string) $synced['elements'][0]['settings']['rwgc_ab_test'];
		$forked = (string) $synced['elements'][2]['settings']['rwgc_ab_test'];
		$this->assertSame( $id, $kept );
		$this->assertNotSame( $kept, $forked );
		$this->assertGreaterThan( 0, (int) $forked );
		$this->assertSame( 'yes', $synced['elements'][2]['settings']['rwgc_ab_pair_ok'] );
		$this->assertSame( 'a2', RWGC_AB_Tests::get( (int) $forked )['config']['element_a'] );
	}

	public function test_copying_a_document_does_not_overwrite_the_original_test(): void {
		$original = RWGC_Elementor_AB::sync_tree( $this->pair( 'a1', 'b1', 'new', 'new' ), 50 );
		$id       = (int) $original['elements'][0]['settings']['rwgc_ab_test'];
		$copy     = RWGC_Elementor_AB::sync_tree( $this->pair( 'a1', 'b1', (string) $id, (string) $id ), 51 );
		$new_id   = (int) $copy['elements'][0]['settings']['rwgc_ab_test'];
		$this->assertNotSame( $id, $new_id );
		$kept = RWGC_AB_Tests::get( $id );
		$this->assertSame( 50, $kept['config']['source_post_id'] );
		$this->assertSame( 'a1', $kept['config']['element_a'] );
		$this->assertSame( 51, RWGC_AB_Tests::get( $new_id )['config']['source_post_id'] );
	}

	public function test_a_gutenberg_test_is_not_reused(): void {
		$gutenberg = RWGC_AB_Tests::create(
			array(
				'title'  => 'Block',
				'status' => 'publish',
				'config' => array(
					'adapter'        => 'gutenberg',
					'source_post_id' => 60,
					'goal'           => array(
						'type'     => 'click',
						'selector' => '.block',
					),
				),
			)
		);
		$synced = RWGC_Elementor_AB::sync_tree( $this->pair( 'a1', 'b1', (string) $gutenberg, (string) $gutenberg ), 60 );
		$new_id = (int) $synced['elements'][0]['settings']['rwgc_ab_test'];
		$this->assertNotSame( $gutenberg, $new_id );
		$this->assertSame( 'gutenberg', RWGC_AB_Tests::get( $gutenberg )['config']['adapter'] );
		$this->assertSame( 'elementor', RWGC_AB_Tests::get( $new_id )['config']['adapter'] );
	}

	public function test_unpaired_and_extra_elements_are_warned_and_not_a_pair(): void {
		$tree   = $this->pair( 'onlyA', 'spare', 'new', 'new' );
		$tree[1]['settings']['rwgc_ab_role'] = 'a';
		$synced = RWGC_Elementor_AB::sync_tree( $tree, 70 );
		$this->assertSame( '', $synced['elements'][0]['settings']['rwgc_ab_pair_ok'] ?? '' );
		$this->assertSame( RWGC_Elementor_AB::notice_unpaired(), $synced['elements'][0]['settings']['rwgc_ab_notice'] );
		$this->assertSame( array(), RWGC_AB_Tests::query() );

		$extra = $this->pair( 'a1', 'b1', 'new', 'new' );
		$extra[] = array(
			'id'       => 'b2',
			'settings' => array(
				'rwgc_ab_role' => 'b',
				'rwgc_ab_test' => 'new',
			),
			'elements' => array(),
		);
		$synced = RWGC_Elementor_AB::sync_tree( $extra, 71 );
		$this->assertSame( 'yes', $synced['elements'][0]['settings']['rwgc_ab_pair_ok'] );
		$this->assertSame( '', $synced['elements'][2]['settings']['rwgc_ab_pair_ok'] ?? '' );
		$this->assertSame( RWGC_Elementor_AB::notice_extra(), $synced['elements'][2]['settings']['rwgc_ab_notice'] );
	}

	public function test_nested_pair_is_ignored(): void {
		$tree = $this->pair( 'outerA', 'outerB', 'new', 'new' );
		$tree[0]['elements'] = $this->pair( 'innerA', 'innerB', 'new', 'new' );
		$synced = RWGC_Elementor_AB::sync_tree( $tree, 80 );
		$this->assertSame( 'yes', $synced['elements'][0]['settings']['rwgc_ab_pair_ok'] );
		$inner = $synced['elements'][0]['elements'][0]['settings'];
		$this->assertSame( '', $inner['rwgc_ab_pair_ok'] ?? '' );
		$this->assertSame( RWGC_Elementor_AB::notice_nested(), $inner['rwgc_ab_notice'] );
		$this->assertCount( 1, RWGC_AB_Tests::query() );
	}

	public function test_an_ended_test_stays_ended(): void {
		$synced = RWGC_Elementor_AB::sync_tree( $this->pair( 'a1', 'b1', 'new', 'new' ), 90 );
		$id     = (int) $synced['elements'][0]['settings']['rwgc_ab_test'];
		$this->assertTrue( RWGC_AB_Tests::end( $id, 'b' ) );
		$again = RWGC_Elementor_AB::sync_tree( $this->pair( 'a1', 'b1', (string) $id, (string) $id ), 90 );
		$this->assertSame( (string) $id, (string) $again['elements'][0]['settings']['rwgc_ab_test'] );
		$this->assertSame( RWGC_AB_Tests::STATUS_ENDED, RWGC_AB_Tests::get( $id )['status'] );
		$this->assertSame( 'b', RWGC_AB_Tests::get( $id )['config']['kept'] );
	}

	public function test_after_editor_save_uses_the_latest_document_meta(): void {
		$live = $this->pair( 'liveA', 'liveB', 'new', 'new' );
		$GLOBALS['rwgc_ab_test_meta'][100]['_elementor_data'] = wp_json_encode( $live );
		RWGC_Elementor_AB::after_editor_save( 100, array( array( 'id' => 'stale' ) ) );
		$saved = json_decode( (string) $GLOBALS['rwgc_ab_test_meta'][100]['_elementor_data'], true );
		$this->assertSame( 'liveA', $saved[0]['id'] );
		$this->assertGreaterThan( 0, (int) $saved[0]['settings']['rwgc_ab_test'] );
		$this->assertSame( 'yes', $saved[1]['settings']['rwgc_ab_pair_ok'] );
	}

	public function test_render_decision_selects_one_arm(): void {
		$settings_a = array(
			'rwgc_ab_role'    => 'a',
			'rwgc_ab_test'    => '15',
			'rwgc_ab_pair_ok' => 'yes',
		);
		$settings_b = array(
			'rwgc_ab_role'    => 'b',
			'rwgc_ab_test'    => '15',
			'rwgc_ab_pair_ok' => 'yes',
		);
		$server = array(
			'surface'  => 'frontend',
			'running'  => true,
			'ended'    => false,
			'kept'     => 'a',
			'assigned' => 'b',
			'mode'     => 'server',
			'nested'   => false,
		);
		$this->assertSame( 'suppress', RWGC_Elementor_AB::render_decision( $settings_a, $server )['action'] );
		$this->assertSame( 'emit', RWGC_Elementor_AB::render_decision( $settings_b, $server )['action'] );

		$fragment         = $server;
		$fragment['mode'] = 'fragment';
		$this->assertSame( 'emit', RWGC_Elementor_AB::render_decision( $settings_a, $fragment )['action'] );
		$this->assertSame( 'suppress', RWGC_Elementor_AB::render_decision( $settings_b, $fragment )['action'] );

		foreach ( array( 'editor', 'preview' ) as $surface ) {
			$builder            = $server;
			$builder['surface'] = $surface;
			$this->assertSame( 'label', RWGC_Elementor_AB::render_decision( $settings_a, $builder )['action'] );
			$this->assertSame( 'label', RWGC_Elementor_AB::render_decision( $settings_b, $builder )['action'] );
			$this->assertSame( '', RWGC_Elementor_AB::render_decision( $settings_a, $builder )['warning'] );
		}

		$unpaired = $settings_a;
		$unpaired['rwgc_ab_pair_ok'] = '';
		$this->assertSame( 'passthrough', RWGC_Elementor_AB::render_decision( $unpaired, $server )['action'] );
		$editor = $server;
		$editor['surface'] = 'editor';
		$warned = RWGC_Elementor_AB::render_decision( $unpaired, $editor );
		$this->assertSame( 'label', $warned['action'] );
		$this->assertSame( RWGC_Elementor_AB::notice_unpaired(), $warned['warning'] );

		$nested         = $server;
		$nested['nested'] = true;
		$this->assertSame( 'passthrough', RWGC_Elementor_AB::render_decision( $settings_a, $nested )['action'] );

		$ended            = $server;
		$ended['running'] = false;
		$ended['ended']   = true;
		$ended['kept']    = 'a';
		$this->assertSame( 'emit', RWGC_Elementor_AB::render_decision( $settings_a, $ended )['action'] );
		$this->assertSame( 'raw', RWGC_Elementor_AB::render_decision( $settings_a, $ended )['mode'] );
		$this->assertSame( 'suppress', RWGC_Elementor_AB::render_decision( $settings_b, $ended )['action'] );

		$off = array( 'rwgc_ab_role' => 'off' );
		$this->assertSame( 'passthrough', RWGC_Elementor_AB::render_decision( $off, $server )['action'] );
	}

	public function test_frontend_server_mode_prints_only_the_assigned_element(): void {
		$id = $this->running_pair_id();
		$this->seed_arm( $id, 'b' );
		$html = $this->capture_pair( $id );
		$this->assertStringContainsString( 'ARM-B', $html );
		$this->assertStringNotContainsString( 'ARM-A', $html );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="server"', $html );
		$this->assertStringContainsString( 'data-rwgc-arm="b"', $html );
	}

	public function test_frontend_fragment_mode_prints_only_variant_a(): void {
		$id = $this->running_pair_id();
		$this->seed_arm( $id, 'b' );
		$GLOBALS['rwgc_test_filters']['rwgc_ab_cache_mode'] = static function () {
			return 'fragment';
		};
		$html = $this->capture_pair( $id );
		$this->assertStringContainsString( 'ARM-A', $html );
		$this->assertStringNotContainsString( 'ARM-B', $html );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="fragment"', $html );
	}

	public function test_editor_and_preview_show_both_labelled(): void {
		$id = $this->running_pair_id();
		foreach ( array( 'editor', 'preview' ) as $surface ) {
			$GLOBALS['rwgc_test_filters']['rwgc_ab_elementor_surface'] = static function () use ( $surface ) {
				return $surface;
			};
			$a = new RWGC_Elementor_AB_Element( 'a1', $this->arm_settings( 'a', $id ) );
			$b = new RWGC_Elementor_AB_Element( 'b1', $this->arm_settings( 'b', $id ) );
			ob_start();
			$this->print_element( $a, '<div>ARM-A</div>' );
			$this->print_element( $b, '<div>ARM-B</div>' );
			$html = ob_get_clean();
			$this->assertStringContainsString( 'ARM-A', $html );
			$this->assertStringContainsString( 'ARM-B', $html );
			$this->assertStringNotContainsString( 'data-rwgc-ab-mode', $html );
			$this->assertSame( 'A', $a->attrs['data-rwgc-ab-label'] );
			$this->assertSame( 'B', $b->attrs['data-rwgc-ab-label'] );
			$this->assertStringNotContainsString( 'rwgc-ab-warning', (string) $a->attrs['class'] );
		}
	}

	public function test_unpaired_element_renders_and_is_labelled_in_the_editor(): void {
		$settings = array(
			'rwgc_ab_role'    => 'a',
			'rwgc_ab_test'    => 'new',
			'rwgc_ab_pair_ok' => '',
			'rwgc_ab_notice'  => RWGC_Elementor_AB::notice_unpaired(),
		);
		$element = new RWGC_Elementor_AB_Element( 'solo', $settings );
		ob_start();
		$this->print_element( $element, '<div>SOLO</div>' );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'SOLO', $html );

		$GLOBALS['rwgc_test_filters']['rwgc_ab_elementor_surface'] = static function () {
			return 'editor';
		};
		$element = new RWGC_Elementor_AB_Element( 'solo', $settings );
		$this->print_element( $element, '' );
		$this->assertSame( '1', $element->attrs['data-rwgc-ab-warning'] );
		$this->assertStringContainsString( 'rwgc-ab-warning', (string) $element->attrs['class'] );
	}

	public function test_nested_arm_stays_inside_the_parent_output(): void {
		$outer = $this->running_pair_id();
		$inner = $this->running_pair_id();
		$this->seed_arm( $outer, 'a' );
		$parent = new RWGC_Elementor_AB_Element( 'outerA', $this->arm_settings( 'a', $outer ) );
		$child  = new RWGC_Elementor_AB_Element( 'innerB', $this->arm_settings( 'b', $inner ) );
		$this->assertTrue( RWGC_Elementor_AB::filter_should_render( true, $parent ) );
		ob_start();
		RWGC_Elementor_AB::before_render( $parent );
		echo '<div>OUTER';
		$this->print_element( $child, '<span>INNER-B</span>' );
		echo '</div>';
		RWGC_Elementor_AB::after_render( $parent );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'INNER-B', $html );
		$this->assertStringContainsString( 'OUTER', $html );
		$this->assertSame( 1, substr_count( $html, 'data-rwgc-ab=' ) );
	}

	public function test_variant_html_hook_returns_the_elementor_arm_only(): void {
		$synced = RWGC_Elementor_AB::sync_tree( $this->pair( 'elA', 'elB', 'new', 'new' ), 110 );
		$id     = (int) $synced['elements'][0]['settings']['rwgc_ab_test'];
		$GLOBALS['rwgc_ab_test_meta'][110]['_elementor_data'] = wp_json_encode( $synced['elements'] );
		$GLOBALS['rwgc_test_filters']['rwgc_ab_elementor_render_node'] = static function ( $html, $node ) {
			unset( $html );
			$element = new RWGC_Elementor_AB_Element(
				$node['id'],
				isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array()
			);
			ob_start();
			RWGC_Elementor_AB::before_render( $element );
			echo '<div id="' . $node['id'] . '">arm</div>';
			RWGC_Elementor_AB::after_render( $element );
			return (string) ob_get_clean();
		};

		$arm_b = RWGC_Elementor_AB::filter_variant_html( '', $id, 'b' );
		$this->assertStringContainsString( 'id="elB"', $arm_b );
		$this->assertStringNotContainsString( 'elA', $arm_b );
		$this->assertStringNotContainsString( 'data-rwgc-ab=', $arm_b );
		$this->assertSame( '<p>kept</p>', RWGC_Elementor_AB::filter_variant_html( '<p>kept</p>', $id, 'a' ) );

		$gutenberg = RWGC_AB_Tests::create(
			array(
				'title'  => 'Block',
				'status' => 'publish',
				'config' => array(
					'adapter' => 'gutenberg',
				),
			)
		);
		$this->assertSame( '', RWGC_Elementor_AB::filter_variant_html( '', $gutenberg, 'a' ) );

		$GLOBALS['rwgc_test_filters']['rwgc_ab_variant_html'] = array( 'RWGC_Elementor_AB', 'filter_variant_html' );
		RWGC_AB_Assignment::reset_request_state();
		$response = RWGC_AB_Cache::build_render_response( $id, '203.0.113.50' );
		$this->assertSame( 200, $response['status'] );
		$this->assertContains( $response['body']['arm'], array( 'a', 'b' ) );
		$this->assertStringContainsString( 'el' . strtoupper( $response['body']['arm'] ), $response['body']['html'] );
		$this->assertStringNotContainsString( 'data-rwgc-ab=', $response['body']['html'] );
	}

	public function test_atomic_section_is_a_sibling_and_schema_keys_are_guarded(): void {
		$this->assertSame( array( 'keep' ), RWGC_Elementor_AB::filter_atomic_props_schema( array( 'keep' ) ) );
		$this->assertSame( 'not-a-list', RWGC_Elementor_AB::filter_atomic_controls( 'not-a-list' ) );

		require_once __DIR__ . '/elementor-ab-stubs.php';
		$schema = RWGC_Elementor_AB::filter_atomic_props_schema(
			array(
				'existing' => 'stay',
			)
		);
		$this->assertSame( 'stay', $schema['existing'] );
		$this->assertInstanceOf( Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type::class, $schema['rwgc_ab_role'] );

		$settings = new class() {
			/** @var int */
			public $added = 0;

			/** @return string */
			public function get_id() {
				return 'settings';
			}

			/**
			 * @param mixed $item Item.
			 * @return void
			 */
			public function add_item( $item ) {
				unset( $item );
				++$this->added;
			}
		};
		$controls = RWGC_Elementor_AB::filter_atomic_controls( array( $settings ) );
		$this->assertSame( 0, $settings->added );
		$this->assertCount( 2, $controls );
		$section = $controls[1];
		$this->assertSame( 'rwgc_ab_test', $section->get_id() );
		$this->assertSame( 'A/B test', $section->get_label() );
		$binds = array();
		foreach ( $section->get_items() as $item ) {
			$binds[] = $item->bind;
		}
		$this->assertContains( 'rwgc_ab_role', $binds );
		$this->assertContains( 'rwgc_ab_test', $binds );
		$this->assertContains( 'rwgc_ab_goal_type', $binds );
		$again = RWGC_Elementor_AB::filter_atomic_controls( $controls );
		$this->assertCount( 2, $again );

		$geo = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/integrations/elementor/class-rwgc-elementor-atomic-geo.php' );
		$this->assertStringContainsString( 'filter_atomic_controls', $geo );
		$this->assertStringContainsString( 'filter_atomic_props_schema', $geo );
		$loader = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/integrations/class-rwgc-integrations-loader.php' );
		$this->assertStringContainsString( 'class-rwgc-elementor-ab.php', $loader );
		$this->assertStringContainsString( 'RWGC_Elementor_AB::init()', $loader );
	}

	/**
	 * @param string $id_a    Variant A id.
	 * @param string $id_b    Variant B id.
	 * @param string $picker_a Test picker.
	 * @param string $picker_b Test picker.
	 * @return array<int, array<string, mixed>>
	 */
	private function pair( $id_a, $id_b, $picker_a, $picker_b ) {
		return array(
			array(
				'id'       => $id_a,
				'settings' => array(
					'rwgc_ab_role'           => 'a',
					'rwgc_ab_test'           => $picker_a,
					'rwgc_ab_test_name'      => 'Homepage hero',
					'rwgc_ab_goal_type'      => 'click',
					'rwgc_ab_goal_selector'  => '.buy',
				),
				'elements' => array(),
			),
			array(
				'id'       => $id_b,
				'settings' => array(
					'rwgc_ab_role' => 'b',
					'rwgc_ab_test' => $picker_b,
				),
				'elements' => array(),
			),
		);
	}

	/**
	 * @return int
	 */
	private function running_pair_id() {
		$synced = RWGC_Elementor_AB::sync_tree( $this->pair( 'a1', 'b1', 'new', 'new' ), 200 );
		return (int) $synced['elements'][0]['settings']['rwgc_ab_test'];
	}

	/**
	 * @param string $role Role.
	 * @param int    $id   Test id.
	 * @return array<string, string>
	 */
	private function arm_settings( $role, $id ) {
		return array(
			'rwgc_ab_role'    => $role,
			'rwgc_ab_test'    => (string) $id,
			'rwgc_ab_pair_ok' => 'yes',
			'rwgc_ab_notice'  => '',
		);
	}

	/**
	 * @param int $id Test id.
	 * @return string
	 */
	private function capture_pair( $id ) {
		$a = new RWGC_Elementor_AB_Element( 'a1', $this->arm_settings( 'a', $id ) );
		$b = new RWGC_Elementor_AB_Element( 'b1', $this->arm_settings( 'b', $id ) );
		ob_start();
		$this->print_element( $a, '<div>ARM-A</div>' );
		$this->print_element( $b, '<div>ARM-B</div>' );
		return (string) ob_get_clean();
	}

	/**
	 * @param RWGC_Elementor_AB_Element $element Element.
	 * @param string                    $html    Inner HTML.
	 * @return void
	 */
	private function print_element( $element, $html ) {
		if ( ! RWGC_Elementor_AB::filter_should_render( true, $element ) ) {
			return;
		}
		RWGC_Elementor_AB::before_render( $element );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test fixture.
		RWGC_Elementor_AB::after_render( $element );
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $arm     a|b.
	 * @return void
	 */
	private function seed_arm( $test_id, $arm ) {
		$_COOKIE[ RWGC_AB_Assignment::COOKIE ] = RWGC_AB_Assignment::encode_cookie(
			str_repeat( 'ab', 16 ),
			array(
				(int) $test_id => array(
					'variant'   => $arm,
					'viewed'    => false,
					'converted' => false,
				),
			)
		);
		RWGC_AB_Assignment::reset_request_state();
	}
}
