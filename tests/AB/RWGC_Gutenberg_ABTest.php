<?php
/**
 * Gutenberg A/B block: one arm on the front, one test per block, clone-safe sync.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

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

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * @return bool
	 */
	function is_admin() {
		return ! empty( $GLOBALS['rwgc_test_is_admin'] );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param mixed $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		$url = trim( (string) $url );
		if ( preg_match( '#^\s*javascript:#i', $url ) ) {
			return '';
		}
		return $url;
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	/**
	 * @param string $handle Handle.
	 * @param string $src    Source.
	 * @return void
	 */
	function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) {
		$GLOBALS['rwgc_test_registered_scripts'][ $handle ] = array(
			'src'    => $src,
			'deps'   => $deps,
			'ver'    => $ver,
			'footer' => $footer,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * @param string $handle Handle.
	 * @return void
	 */
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		$GLOBALS['rwgc_test_scripts'][ $handle ] = array(
			'src'       => $src,
			'deps'      => $deps,
			'ver'       => $ver,
			'in_footer' => $in_footer,
		);
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	/**
	 * @param string               $handle Handle.
	 * @param string               $object Object.
	 * @param array<string, mixed> $data   Data.
	 * @return void
	 */
	function wp_localize_script( $handle, $object, $data ) {
		$GLOBALS['rwgc_test_localized'][ $handle ] = array(
			'object' => $object,
			'data'   => $data,
		);
	}
}

if ( ! function_exists( 'wp_script_is' ) ) {
	/**
	 * @param string $handle Handle.
	 * @param string $status Status.
	 * @return bool
	 */
	function wp_script_is( $handle, $status = 'enqueued' ) {
		unset( $status );
		return ! empty( $GLOBALS['rwgc_test_scripts'][ $handle ] );
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	/**
	 * @param string $path Path.
	 * @return string
	 */
	function rest_url( $path = '' ) {
		return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'has_blocks' ) ) {
	/**
	 * JSON block lists stand in for parsed post content in this suite.
	 *
	 * @param string $content Content.
	 * @return bool
	 */
	function has_blocks( $content ) {
		$content = (string) $content;
		if ( '' !== $content && '[' === $content[0] ) {
			$decoded = json_decode( $content, true );
			return is_array( $decoded ) && array() !== $decoded;
		}
		return false !== strpos( $content, '<!-- wp:' );
	}
}

if ( ! function_exists( 'parse_blocks' ) ) {
	/**
	 * @param string $content JSON block list.
	 * @return array<int, mixed>
	 */
	function parse_blocks( $content ) {
		$content = (string) $content;
		if ( '' === $content || '[' !== $content[0] ) {
			return array();
		}
		$decoded = json_decode( $content, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}

if ( ! function_exists( 'serialize_blocks' ) ) {
	/**
	 * @param array<int, mixed> $blocks Blocks.
	 * @return string
	 */
	function serialize_blocks( $blocks ) {
		$GLOBALS['rwgc_test_serialize_count'] = isset( $GLOBALS['rwgc_test_serialize_count'] ) ? (int) $GLOBALS['rwgc_test_serialize_count'] : 0;
		$GLOBALS['rwgc_test_serialize_count']++;
		$json = json_encode( $blocks, JSON_UNESCAPED_SLASHES );
		return is_string( $json ) ? $json : '';
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function wp_is_post_revision( $post_id ) {
		unset( $post_id );
		return ! empty( $GLOBALS['rwgc_test_is_revision'] );
	}
}

if ( ! function_exists( 'wp_is_post_autosave' ) ) {
	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function wp_is_post_autosave( $post_id ) {
		unset( $post_id );
		return ! empty( $GLOBALS['rwgc_test_is_autosave'] );
	}
}

/**
 * @covers RWGC_Gutenberg_AB
 */
final class RWGC_Gutenberg_ABTest extends TestCase {

	/**
	 * @var array<string, mixed>
	 */
	private $server = array();

	/**
	 * @var array<string, mixed>
	 */
	private $cookie = array();

	/**
	 * @var bool
	 */
	private $had_filters = false;

	/**
	 * @var array<string, mixed>|null
	 */
	private $filters = null;

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
		require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-tests.php';
		require_once dirname( __DIR__, 2 ) . '/includes/integrations/class-rwgc-cache-compat.php';
		require_once dirname( __DIR__, 2 ) . '/includes/decision/class-rwgc-decision-experiment-assigner.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-assignment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-cache.php';
		require_once dirname( __DIR__, 2 ) . '/includes/integrations/gutenberg/class-rwgc-gutenberg-ab.php';
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
		$_SERVER['REQUEST_METHOD']  = 'GET';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120.0.0.0';
		unset( $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );
		$GLOBALS['rwgc_test_is_admin']        = false;
		$GLOBALS['rwgc_test_is_revision']     = false;
		$GLOBALS['rwgc_test_is_autosave']     = false;
		$GLOBALS['rwgc_test_serialize_count'] = 0;
		$GLOBALS['rwgc_test_filters']         = array(
			'rwgc_ab_emit_http_headers' => static function () {
				return false;
			},
		);
		$GLOBALS['rwgc_ab_test_posts'] = array();
		$GLOBALS['rwgc_ab_test_meta']  = array();
		$GLOBALS['rwgc_ab_test_seq']   = 0;
		$GLOBALS['rwgc_test_scripts']  = array();
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Cache::reset_request_state();
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
		$GLOBALS['rwgc_test_is_admin']    = false;
		$GLOBALS['rwgc_test_is_revision'] = false;
		$GLOBALS['rwgc_test_is_autosave'] = false;
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Cache::reset_request_state();
		parent::tearDown();
	}

	public function test_saving_a_page_creates_one_running_test_and_is_stable(): void {
		$post_id = $this->insert_page(
			array(
				$this->ab_block(
					array(
						'testName'     => 'Hero',
						'goalType'     => 'click',
						'goalSelector' => '.buy',
					)
				),
			)
		);

		RWGC_Gutenberg_AB::sync_on_save( $post_id );

		$blocks  = $this->blocks_of( $post_id );
		$test_id = (int) $blocks[0]['attrs']['testId'];
		$this->assertGreaterThan( 0, $test_id );
		$this->assertNotSame( '', (string) $blocks[0]['attrs']['instanceId'] );
		$row = RWGC_AB_Tests::get( $test_id );
		$this->assertIsArray( $row );
		$this->assertSame( 'publish', $row['status'] );
		$this->assertSame( 'Hero', $row['title'] );
		$this->assertSame( 'element', $row['config']['type'] );
		$this->assertSame( 'gutenberg', $row['config']['adapter'] );
		$this->assertSame( $post_id, $row['config']['source_post_id'] );
		$this->assertSame( (string) $blocks[0]['attrs']['instanceId'], $row['config']['instance_id'] );
		$this->assertSame( 'click', $row['config']['goal']['type'] );
		$this->assertSame( '.buy', $row['config']['goal']['selector'] );
		$this->assertSame( 'a', $row['config']['variants'][0]['id'] );
		$this->assertSame( 'b', $row['config']['variants'][1]['id'] );
		$this->assertSame( 50.0, $row['config']['variants'][0]['allocation'] );
		$this->assertSame( 50.0, $row['config']['variants'][1]['allocation'] );
		$this->assertSame( 1, $GLOBALS['rwgc_test_serialize_count'] );

		$saved = (string) get_post( $post_id )->post_content;
		RWGC_Gutenberg_AB::sync_on_save( $post_id );
		$this->assertSame( $saved, (string) get_post( $post_id )->post_content );
		$this->assertSame( 1, $GLOBALS['rwgc_test_serialize_count'] );
		$this->assertSame( $test_id, (int) RWGC_AB_Tests::get( $test_id )['id'] );
	}

	public function test_a_nested_block_is_synced(): void {
		$post_id = $this->insert_page(
			array(
				array(
					'blockName'   => 'core/group',
					'attrs'       => array(),
					'innerBlocks' => array(
						$this->ab_block(
							array(
								'testName' => 'Nested',
								'goalType' => 'click',
							)
						),
					),
				),
			)
		);

		RWGC_Gutenberg_AB::sync_on_save( $post_id );
		$blocks  = $this->blocks_of( $post_id );
		$test_id = (int) $blocks[0]['innerBlocks'][0]['attrs']['testId'];
		$this->assertGreaterThan( 0, $test_id );
		$this->assertSame( $post_id, RWGC_AB_Tests::get( $test_id )['config']['source_post_id'] );
	}

	public function test_duplicating_a_post_creates_a_new_test(): void {
		$original = $this->insert_page(
			array(
				$this->ab_block(
					array(
						'testName' => 'Hero',
						'goalType' => 'url',
						'goalUrl'  => 'https://example.test/thanks',
					)
				),
			)
		);
		RWGC_Gutenberg_AB::sync_on_save( $original );
		$first = $this->blocks_of( $original );

		$copy = $this->insert_page_content( (string) get_post( $original )->post_content, 'Hero copy' );
		RWGC_Gutenberg_AB::sync_on_save( $copy );
		$second = $this->blocks_of( $copy );

		$first_id  = (int) $first[0]['attrs']['testId'];
		$second_id = (int) $second[0]['attrs']['testId'];
		$this->assertGreaterThan( 0, $second_id );
		$this->assertNotSame( $first_id, $second_id );
		$this->assertNotSame( (string) $first[0]['attrs']['instanceId'], (string) $second[0]['attrs']['instanceId'] );
		$this->assertSame( $original, RWGC_AB_Tests::get( $first_id )['config']['source_post_id'] );
		$this->assertSame( $copy, RWGC_AB_Tests::get( $second_id )['config']['source_post_id'] );
		$this->assertSame( 'https://example.test/thanks', RWGC_AB_Tests::get( $second_id )['config']['goal']['url'] );
		$this->assertSame( 'publish', RWGC_AB_Tests::get( $first_id )['status'] );
	}

	public function test_two_copies_in_one_post_do_not_share_a_test(): void {
		$post_id = $this->insert_page(
			array(
				$this->ab_block(
					array(
						'testName' => 'Twice',
						'goalType' => 'click',
					)
				),
			)
		);
		RWGC_Gutenberg_AB::sync_on_save( $post_id );
		$blocks   = $this->blocks_of( $post_id );
		$blocks[] = $blocks[0];
		$this->write_blocks( $post_id, $blocks );
		RWGC_Gutenberg_AB::sync_on_save( $post_id );
		$blocks = $this->blocks_of( $post_id );

		$first  = (int) $blocks[0]['attrs']['testId'];
		$second = (int) $blocks[1]['attrs']['testId'];
		$this->assertGreaterThan( 0, $first );
		$this->assertGreaterThan( 0, $second );
		$this->assertNotSame( $first, $second );
		$this->assertNotSame( (string) $blocks[0]['attrs']['instanceId'], (string) $blocks[1]['attrs']['instanceId'] );
		$this->assertSame( $post_id, RWGC_AB_Tests::get( $first )['config']['source_post_id'] );
		$this->assertSame( $post_id, RWGC_AB_Tests::get( $second )['config']['source_post_id'] );
		$this->assertSame( 'publish', RWGC_AB_Tests::get( $first )['status'] );
		$this->assertSame( 'publish', RWGC_AB_Tests::get( $second )['status'] );
	}

	public function test_an_ended_test_stays_ended_and_keeps_its_winner(): void {
		$post_id = $this->insert_page(
			array(
				$this->ab_block(
					array(
						'testName'     => 'Hero',
						'goalType'     => 'click',
						'goalSelector' => '.buy',
					)
				),
			)
		);
		RWGC_Gutenberg_AB::sync_on_save( $post_id );
		$test_id = (int) $this->blocks_of( $post_id )[0]['attrs']['testId'];
		$this->assertTrue( RWGC_AB_Tests::end( $test_id, 'b' ) );

		$blocks = $this->blocks_of( $post_id );
		$blocks[0]['attrs']['goalSelector'] = '.other';
		$this->write_blocks( $post_id, $blocks );
		$before = $GLOBALS['rwgc_test_serialize_count'];
		RWGC_Gutenberg_AB::sync_on_save( $post_id );

		$row = RWGC_AB_Tests::get( $test_id );
		$this->assertSame( RWGC_AB_Tests::STATUS_ENDED, $row['status'] );
		$this->assertSame( 'b', $row['config']['kept'] );
		$this->assertSame( '.other', $row['config']['goal']['selector'] );
		$this->assertSame( $before, $GLOBALS['rwgc_test_serialize_count'] );

		RWGC_AB_Cache::reset_request_state();
		RWGC_AB_Assignment::reset_request_state();
		$html = $this->render( $test_id );
		$this->assertStringContainsString( 'ARM-B-ONLY', $html );
		$this->assertStringNotContainsString( 'ARM-A-ONLY', $html );
		$this->assertStringContainsString( 'data-rwgc-ab="' . $test_id . '"', $html );
		$this->assertStringContainsString( 'data-rwgc-arm="b"', $html );
		$this->assertStringNotContainsString( 'data-rwgc-arm="a"', $html );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertSame( '', RWGC_AB_Cache::last_directives()['cache_control'] );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );
	}

	public function test_an_ended_test_without_a_winner_renders_a(): void {
		$post_id = $this->insert_page(
			array(
				$this->ab_block(
					array(
						'testName' => 'Hero',
						'goalType' => 'click',
					)
				),
			)
		);
		RWGC_Gutenberg_AB::sync_on_save( $post_id );
		$test_id = (int) $this->blocks_of( $post_id )[0]['attrs']['testId'];
		$this->assertTrue( RWGC_AB_Tests::end( $test_id, '' ) );

		$html = $this->render( $test_id );
		$this->assertStringContainsString( 'ARM-A-ONLY', $html );
		$this->assertStringNotContainsString( 'ARM-B-ONLY', $html );
		$this->assertStringContainsString( 'data-rwgc-arm="a"', $html );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );
	}

	public function test_a_missing_test_renders_a_and_does_not_change_the_cache(): void {
		$html = RWGC_Gutenberg_AB::filter_render_block(
			'<p>ARM-A-ONLY</p><p>ARM-B-ONLY</p>',
			array(
				'blockName'   => RWGC_Gutenberg_AB::BLOCK,
				'attrs'       => array( 'testId' => 0 ),
				'innerBlocks' => array(
					$this->variant_block( 'a', '<p onclick="alert(1)">ARM-A-ONLY</p><script>alert(1)</script>' ),
					$this->variant_block( 'b', '<p>ARM-B-ONLY</p>' ),
				),
			)
		);
		$this->assertStringContainsString( 'ARM-A-ONLY', $html );
		$this->assertStringNotContainsString( 'ARM-B-ONLY', $html );
		$this->assertStringNotContainsString( '<script', strtolower( $html ) );
		$this->assertStringNotContainsString( 'onclick', strtolower( $html ) );
		$this->assertStringContainsString( 'data-rwgc-ab="0"', $html );
		$this->assertStringContainsString( 'data-rwgc-arm="a"', $html );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );
	}

	public function test_a_running_test_prints_only_the_assigned_arm_and_bypasses_a_shared_cache(): void {
		$test_id = $this->running_test_id();
		$this->seed( $test_id, 'b' );
		$html = $this->render( $test_id );

		$this->assertStringContainsString( 'ARM-B-ONLY', $html );
		$this->assertStringNotContainsString( 'ARM-A-ONLY', $html );
		$this->assertStringContainsString( 'data-rwgc-ab="' . $test_id . '"', $html );
		$this->assertStringContainsString( 'data-rwgc-arm="b"', $html );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="server"', $html );
		$this->assertSame( 'private, no-store', RWGC_AB_Cache::last_directives()['cache_control'] );
		$this->assertTrue( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertFalse( RWGC_AB_Cache::fragment_script_queued() );
	}

	public function test_fragment_mode_prints_only_a_and_can_stay_cached(): void {
		$test_id = $this->running_test_id();
		$this->seed( $test_id, 'b' );
		$GLOBALS['rwgc_test_filters']['rwgc_ab_cache_mode'] = static function () {
			return 'fragment';
		};
		$html = $this->render( $test_id );

		$this->assertStringContainsString( 'ARM-A-ONLY', $html );
		$this->assertStringNotContainsString( 'ARM-B-ONLY', $html );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="fragment"', $html );
		$this->assertStringContainsString( 'data-rwgc-arm="a"', $html );
		$this->assertStringNotContainsString( 'data-rwgc-arm="b"', $html );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertTrue( RWGC_AB_Cache::fragment_script_queued() );
	}

	public function test_other_blocks_and_pages_without_a_test_do_not_set_cache_headers(): void {
		$this->assertSame(
			'<p>Keep</p>',
			RWGC_Gutenberg_AB::filter_render_block(
				'<p>Keep</p>',
				array(
					'blockName' => 'core/paragraph',
				)
			)
		);
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertSame( '', RWGC_AB_Cache::last_directives()['cache_control'] );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );

		$post_id = $this->insert_page(
			array(
				array(
					'blockName'   => 'core/paragraph',
					'attrs'       => array(),
					'innerBlocks' => array(),
				),
			)
		);
		RWGC_Gutenberg_AB::sync_on_save( $post_id );
		$this->assertSame( array(), RWGC_AB_Tests::query() );
	}

	public function test_revisions_autosaves_and_trash_do_not_create_a_test(): void {
		$blocks = array(
			$this->ab_block(
				array(
					'testName' => 'Skip',
					'goalType' => 'click',
				)
			),
		);
		$post_id = $this->insert_page( $blocks );

		$GLOBALS['rwgc_test_is_revision'] = true;
		RWGC_Gutenberg_AB::sync_on_save( $post_id );
		$GLOBALS['rwgc_test_is_revision'] = false;
		$this->assertSame( array(), RWGC_AB_Tests::query() );

		$GLOBALS['rwgc_test_is_autosave'] = true;
		RWGC_Gutenberg_AB::sync_on_save( $post_id );
		$GLOBALS['rwgc_test_is_autosave'] = false;
		$this->assertSame( array(), RWGC_AB_Tests::query() );

		get_post( $post_id )->post_status = 'trash';
		RWGC_Gutenberg_AB::sync_on_save( $post_id );
		$this->assertSame( array(), RWGC_AB_Tests::query() );
	}

	public function test_goal_url_saved_on_the_block_matches_the_stored_test(): void {
		$post_id = $this->insert_page(
			array(
				$this->ab_block(
					array(
						'testName' => 'Hero',
						'goalType' => 'url',
						'goalUrl'  => '  https://example.test/thanks  ',
					)
				),
			)
		);
		RWGC_Gutenberg_AB::sync_on_save( $post_id );
		$blocks = $this->blocks_of( $post_id );
		$row    = RWGC_AB_Tests::get( (int) $blocks[0]['attrs']['testId'] );
		$this->assertSame( 'url', $row['config']['goal']['type'] );
		$this->assertSame( 'https://example.test/thanks', $row['config']['goal']['url'] );
		$this->assertSame( 'https://example.test/thanks', $blocks[0]['attrs']['goalUrl'] );
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @param string                           $title  Title.
	 * @return int
	 */
	private function insert_page( array $blocks, $title = 'Landing' ) {
		$json = json_encode( $blocks, JSON_UNESCAPED_SLASHES );
		return $this->insert_page_content( is_string( $json ) ? $json : '[]', $title );
	}

	/**
	 * @param string $content Content.
	 * @param string $title   Title.
	 * @return int
	 */
	private function insert_page_content( $content, $title ) {
		return (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
	}

	/**
	 * @param int                              $post_id Post ID.
	 * @param array<int, array<string, mixed>> $blocks  Blocks.
	 * @return void
	 */
	private function write_blocks( $post_id, array $blocks ) {
		$json = json_encode( $blocks, JSON_UNESCAPED_SLASHES );
		get_post( $post_id )->post_content = is_string( $json ) ? $json : '[]';
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function blocks_of( $post_id ) {
		$decoded = json_decode( (string) get_post( $post_id )->post_content, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * @param array<string, mixed> $attrs Attributes.
	 * @return array<string, mixed>
	 */
	private function ab_block( array $attrs ) {
		return array(
			'blockName'   => RWGC_Gutenberg_AB::BLOCK,
			'attrs'       => $attrs,
			'innerBlocks' => array(
				$this->variant_block( 'a', '<p>ARM-A-ONLY</p>' ),
				$this->variant_block( 'b', '<p>ARM-B-ONLY</p>' ),
			),
			'innerHTML'   => '',
		);
	}

	/**
	 * @param string $variant a|b.
	 * @param string $html    Inner HTML.
	 * @return array<string, mixed>
	 */
	private function variant_block( $variant, $html ) {
		return array(
			'blockName'   => RWGC_Gutenberg_AB::VARIANT,
			'attrs'       => array( 'variant' => $variant ),
			'innerBlocks' => array(
				array(
					'blockName'   => 'core/paragraph',
					'attrs'       => array(),
					'innerBlocks' => array(),
					'innerHTML'   => $html,
				),
			),
			'innerHTML'   => '',
		);
	}

	/**
	 * @return int
	 */
	private function running_test_id() {
		$post_id = $this->insert_page(
			array(
				$this->ab_block(
					array(
						'testName' => 'Live',
						'goalType' => 'click',
					)
				),
			)
		);
		RWGC_Gutenberg_AB::sync_on_save( $post_id );
		return (int) $this->blocks_of( $post_id )[0]['attrs']['testId'];
	}

	/**
	 * @param int $test_id Test id.
	 * @return string
	 */
	private function render( $test_id ) {
		return RWGC_Gutenberg_AB::filter_render_block(
			'<p>ARM-A-ONLY</p><p>ARM-B-ONLY</p>',
			array(
				'blockName'   => RWGC_Gutenberg_AB::BLOCK,
				'attrs'       => array( 'testId' => $test_id ),
				'innerBlocks' => array(
					$this->variant_block( 'a', '<p>ARM-A-ONLY</p>' ),
					$this->variant_block( 'b', '<p>ARM-B-ONLY</p>' ),
				),
			)
		);
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $arm     Arm.
	 * @return void
	 */
	private function seed( $test_id, $arm ) {
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
