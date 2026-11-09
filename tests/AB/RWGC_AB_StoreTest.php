<?php
/**
 * Free A/B test store: repository, experiment contract, and daily table.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * @param string $type Type.
	 * @param int    $gmt  GMT flag.
	 * @return string
	 */
	function current_time( $type, $gmt = 0 ) {
		unset( $type, $gmt );
		return '2026-11-09 10:00:00';
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	/**
	 * @param string $queries SQL.
	 * @param bool   $execute Execute.
	 * @return array<string, string>
	 */
	function dbDelta( $queries = '', $execute = true ) {
		unset( $execute );
		if ( ! isset( $GLOBALS['rwgc_ab_dbdelta_calls'] ) || ! is_array( $GLOBALS['rwgc_ab_dbdelta_calls'] ) ) {
			$GLOBALS['rwgc_ab_dbdelta_calls'] = array();
		}
		$GLOBALS['rwgc_ab_dbdelta_calls'][] = $queries;
		return array();
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

if ( ! function_exists( 'get_edit_post_link' ) ) {
	/**
	 * @param int    $id      Post ID.
	 * @param string $context Context.
	 * @return string
	 */
	function get_edit_post_link( $id, $context = 'display' ) {
		unset( $context );
		return 'http://example.test/wp-admin/post.php?post=' . (int) $id . '&action=edit';
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	/**
	 * @param array<string, mixed> $postarr Post fields.
	 * @param bool                 $wp_error Return an error.
	 * @param bool                 $fire_after_hooks Fire hooks.
	 * @return int
	 */
	function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
		unset( $wp_error, $fire_after_hooks );
		$id                            = isset( $GLOBALS['rwgc_ab_test_seq'] ) ? (int) $GLOBALS['rwgc_ab_test_seq'] : 0;
		$id                            = $id + 1;
		$GLOBALS['rwgc_ab_test_seq']   = $id;
		$post                          = new WP_Post();
		$post->ID                      = $id;
		$post->post_type               = isset( $postarr['post_type'] ) ? (string) $postarr['post_type'] : '';
		$post->post_status             = isset( $postarr['post_status'] ) ? (string) $postarr['post_status'] : 'draft';
		$post->post_title              = isset( $postarr['post_title'] ) ? (string) $postarr['post_title'] : '';
		$GLOBALS['rwgc_ab_test_posts'][ $id ] = $post;
		return $id;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	/**
	 * @param array<string, mixed> $postarr Post fields.
	 * @param bool                 $wp_error Return an error.
	 * @param bool                 $fire_after_hooks Fire hooks.
	 * @return int
	 */
	function wp_update_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
		unset( $wp_error, $fire_after_hooks );
		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $id <= 0 || ! isset( $GLOBALS['rwgc_ab_test_posts'][ $id ] ) || ! $GLOBALS['rwgc_ab_test_posts'][ $id ] instanceof WP_Post ) {
			return 0;
		}
		$post = $GLOBALS['rwgc_ab_test_posts'][ $id ];
		if ( isset( $postarr['post_title'] ) ) {
			$post->post_title = (string) $postarr['post_title'];
		}
		if ( isset( $postarr['post_status'] ) ) {
			$post->post_status = (string) $postarr['post_status'];
		}
		return $id;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * @param mixed $post Post ID.
	 * @return WP_Post|null
	 */
	function get_post( $post = null ) {
		$id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
		if ( isset( $GLOBALS['rwgc_ab_test_posts'][ $id ] ) && $GLOBALS['rwgc_ab_test_posts'][ $id ] instanceof WP_Post ) {
			return $GLOBALS['rwgc_ab_test_posts'][ $id ];
		}
		return null;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * @param array<string, mixed>|null $args Query.
	 * @return array<int, WP_Post>
	 */
	function get_posts( $args = null ) {
		$args     = is_array( $args ) ? $args : array();
		$type     = isset( $args['post_type'] ) ? (string) $args['post_type'] : '';
		$statuses = isset( $args['post_status'] ) ? (array) $args['post_status'] : array( 'publish' );
		$posts    = array();
		$all      = isset( $GLOBALS['rwgc_ab_test_posts'] ) && is_array( $GLOBALS['rwgc_ab_test_posts'] ) ? $GLOBALS['rwgc_ab_test_posts'] : array();
		foreach ( $all as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			if ( '' !== $type && $post->post_type !== $type ) {
				continue;
			}
			if ( ! in_array( (string) $post->post_status, $statuses, true ) ) {
				continue;
			}
			$posts[] = $post;
		}
		usort(
			$posts,
			static function ( $a, $b ) {
				return (int) $b->ID <=> (int) $a->ID;
			}
		);
		return $posts;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single value.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$post_id = (int) $post_id;
		$bag     = isset( $GLOBALS['rwgc_ab_test_meta'][ $post_id ] ) && is_array( $GLOBALS['rwgc_ab_test_meta'][ $post_id ] )
			? $GLOBALS['rwgc_ab_test_meta'][ $post_id ]
			: array();
		if ( '' === (string) $key ) {
			return $bag;
		}
		if ( ! array_key_exists( $key, $bag ) ) {
			return $single ? '' : array();
		}
		return $single ? $bag[ $key ] : array( $bag[ $key ] );
	}
}

if ( ! function_exists( 'wp_slash' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_slash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_slash', $value );
		}
		return is_string( $value ) ? addslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * Store meta. `_rwgc_ab` is unslashed and sanitized, matching update_metadata().
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Key.
	 * @param mixed  $meta_value Value.
	 * @return bool
	 */
	function update_post_meta( $post_id, $meta_key, $meta_value ) {
		$post_id = (int) $post_id;
		if ( class_exists( 'RWGC_AB_Tests', false ) && RWGC_AB_Tests::META_KEY === (string) $meta_key && is_string( $meta_value ) ) {
			// Core unslashes, then runs the registered sanitize_callback.
			$meta_value = stripslashes( $meta_value );
			$meta_value = RWGC_AB_Tests::sanitize_stored_json( $meta_value );
		}
		if ( ! isset( $GLOBALS['rwgc_ab_test_meta'][ $post_id ] ) || ! is_array( $GLOBALS['rwgc_ab_test_meta'][ $post_id ] ) ) {
			$GLOBALS['rwgc_ab_test_meta'][ $post_id ] = array();
		}
		$GLOBALS['rwgc_ab_test_meta'][ $post_id ][ (string) $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	/**
	 * @param int  $postid       Post ID.
	 * @param bool $force_delete Force delete.
	 * @return WP_Post|false
	 */
	function wp_delete_post( $postid = 0, $force_delete = false ) {
		unset( $force_delete );
		$postid = (int) $postid;
		if ( ! isset( $GLOBALS['rwgc_ab_test_posts'][ $postid ] ) || ! $GLOBALS['rwgc_ab_test_posts'][ $postid ] instanceof WP_Post ) {
			return false;
		}
		$post = $GLOBALS['rwgc_ab_test_posts'][ $postid ];
		unset( $GLOBALS['rwgc_ab_test_posts'][ $postid ], $GLOBALS['rwgc_ab_test_meta'][ $postid ] );
		return $post;
	}
}

/**
 * In-memory $wpdb for schema SQL.
 */
class RWGC_AB_Test_Wpdb {

	/** @var string */
	public $prefix = 'wp_';

	/** @var string */
	public $posts = 'wp_posts';

	/**
	 * @return string
	 */
	public function get_charset_collate() {
		return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
	}
}

/**
 * @covers RWGC_AB_Tests
 * @covers RWGC_AB_DB
 */
final class RWGC_AB_StoreTest extends TestCase {

	/** @var array<string, mixed>|null */
	private $previous_options;

	/** @var mixed */
	private $previous_wpdb;

	/** @var bool */
	private $had_wpdb = false;

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-db.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-tests.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-admin.php';
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->previous_options = isset( $GLOBALS['rwgc_test_options'] ) && is_array( $GLOBALS['rwgc_test_options'] )
			? $GLOBALS['rwgc_test_options']
			: null;
		if ( ! isset( $GLOBALS['rwgc_test_options'] ) || ! is_array( $GLOBALS['rwgc_test_options'] ) ) {
			$GLOBALS['rwgc_test_options'] = array();
		}
		unset( $GLOBALS['rwgc_test_options'][ RWGC_AB_DB::OPTION ] );

		$this->had_wpdb      = array_key_exists( 'wpdb', $GLOBALS );
		$this->previous_wpdb = $this->had_wpdb ? $GLOBALS['wpdb'] : null;
		$GLOBALS['wpdb']     = new RWGC_AB_Test_Wpdb();

		$GLOBALS['rwgc_ab_test_posts']      = array();
		$GLOBALS['rwgc_ab_test_meta']       = array();
		$GLOBALS['rwgc_ab_test_seq']        = 0;
		$GLOBALS['rwgc_ab_dbdelta_calls']   = array();
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		if ( null === $this->previous_options ) {
			unset( $GLOBALS['rwgc_test_options'] );
		} else {
			$GLOBALS['rwgc_test_options'] = $this->previous_options;
		}
		if ( $this->had_wpdb ) {
			$GLOBALS['wpdb'] = $this->previous_wpdb;
		} else {
			unset( $GLOBALS['wpdb'] );
		}
		parent::tearDown();
	}

	public function test_repository_round_trip(): void {
		$first = RWGC_AB_Tests::create(
			array(
				'title'  => 'Hero',
				'status' => 'publish',
				'config' => array(
					'adapter'        => 'elementor',
					'source_post_id' => 42,
					'instance_id'    => 'g_abc123',
					'goal'           => array(
						'type' => 'url',
						'url'  => 'https://example.test/thanks',
					),
					'variants'       => array(
						array(
							'id'         => 'a',
							'allocation' => 50,
						),
						array(
							'id'         => 'b',
							'allocation' => 50,
						),
						array(
							'id'         => 'c',
							'allocation' => 10,
						),
					),
					'managed_by'     => 'optimise',
				),
			)
		);
		$second = RWGC_AB_Tests::create(
			array(
				'title' => '',
			)
		);

		$this->assertGreaterThan( 0, $first );
		$this->assertGreaterThan( $first, $second );

		$saved = RWGC_AB_Tests::get( $first );
		$this->assertNotNull( $saved );
		$this->assertSame( 'Hero', $saved['title'] );
		$this->assertSame( 'publish', $saved['status'] );
		$this->assertSame( (string) $first, $saved['config']['id'] );
		$this->assertSame( 'a', $saved['config']['control'] );
		$this->assertSame( 'elementor', $saved['config']['adapter'] );
		$this->assertSame( 42, $saved['config']['source_post_id'] );
		$this->assertSame( 'g_abc123', $saved['config']['instance_id'] );
		$this->assertSame( 'https://example.test/thanks', $saved['config']['goal']['url'] );
		$this->assertSame( '2026-11-09 10:00:00', $saved['config']['started_at'] );
		$this->assertSame( 'optimise', $saved['config']['managed_by'] );
		$this->assertSame( 'c', $saved['config']['variants'][2]['id'] );
		$this->assertSame( 10.0, $saved['config']['variants'][2]['allocation'] );

		$draft = RWGC_AB_Tests::get( $second );
		$this->assertSame( 'A/B test', $draft['title'] );
		$this->assertSame( 'draft', $draft['status'] );
		$this->assertSame( '', $draft['config']['started_at'] );

		$listed = RWGC_AB_Tests::query();
		$this->assertSame( array( $second, $first ), array( $listed[0]['id'], $listed[1]['id'] ) );

		$renamed = RWGC_AB_Tests::update(
			$first,
			array(
				'title'  => 'Hero updated',
				'config' => array(
					'rule_id' => 9,
				),
			)
		);
		$this->assertSame( $first, $renamed );
		$saved = RWGC_AB_Tests::get( $first );
		$this->assertSame( 'Hero updated', $saved['title'] );
		$this->assertSame( 9, $saved['config']['rule_id'] );
		$this->assertSame( 'optimise', $saved['config']['managed_by'] );
		$this->assertSame( 'publish', $saved['status'] );

		$this->assertTrue( RWGC_AB_Tests::end( $first, 'b' ) );
		$ended = RWGC_AB_Tests::get( $first );
		$this->assertSame( RWGC_AB_Tests::STATUS_ENDED, $ended['status'] );
		$this->assertSame( 'b', $ended['config']['kept'] );
		$this->assertSame( '2026-11-09 10:00:00', $ended['config']['ended_at'] );
		$this->assertSame( 'Ended', RWGC_AB_Tests::status_label( $ended['status'] ) );

		$this->assertTrue( RWGC_AB_Tests::end( $first, 'not-a-variant' ) );
		$ended_again = RWGC_AB_Tests::get( $first );
		$this->assertSame( 'b', $ended_again['config']['kept'] );
		$this->assertSame( '2026-11-09 10:00:00', $ended_again['config']['ended_at'] );

		$this->assertFalse( RWGC_AB_Tests::end( 0 ) );
		$this->assertFalse( RWGC_AB_Tests::delete( 99999 ) );
		$this->assertTrue( RWGC_AB_Tests::delete( $first ) );
		$this->assertNull( RWGC_AB_Tests::get( $first ) );
		$this->assertFalse( RWGC_AB_Tests::delete( $first ) );
		$this->assertCount( 1, RWGC_AB_Tests::query() );

		$other             = new WP_Post();
		$other->ID         = 80;
		$other->post_type  = 'page';
		$other->post_title = 'Page';
		$GLOBALS['rwgc_ab_test_posts'][80] = $other;
		$this->assertNull( RWGC_AB_Tests::get( 80 ) );
		$this->assertFalse( RWGC_AB_Tests::delete( 80 ) );
		$this->assertNotNull( get_post( 80 ) );
	}

	public function test_quoted_and_unicode_goal_survives_meta_unslash(): void {
		$selector = '[data-goal="café"]';
		$id       = RWGC_AB_Tests::create(
			array(
				'title'  => 'Quoted goal',
				'status' => 'publish',
				'config' => array(
					'adapter'        => 'elementor',
					'source_post_id' => 7,
					'goal'           => array(
						'type'     => 'click',
						'selector' => $selector,
					),
					'managed_by'     => 'optimise',
				),
			)
		);

		$this->assertGreaterThan( 0, $id );
		$saved = RWGC_AB_Tests::get( $id );
		$this->assertNotNull( $saved );
		$this->assertSame( $selector, $saved['config']['goal']['selector'] );
		$this->assertSame( 7, $saved['config']['source_post_id'] );
		$this->assertSame( 'elementor', $saved['config']['adapter'] );
		$this->assertSame( 'optimise', $saved['config']['managed_by'] );
		$this->assertNotSame( '', $saved['config']['started_at'] );

		$this->assertTrue( RWGC_AB_Tests::end( $id, 'a' ) );
		$ended = RWGC_AB_Tests::get( $id );
		$this->assertSame( $selector, $ended['config']['goal']['selector'] );
		$this->assertSame( 7, $ended['config']['source_post_id'] );
		$this->assertSame( 'optimise', $ended['config']['managed_by'] );
		$this->assertSame( 'a', $ended['config']['kept'] );
	}

	public function test_stored_config_parses_as_experiment_contract(): void {
		$id = RWGC_AB_Tests::create(
			array(
				'title'  => 'Contract',
				'status' => 'draft',
				'config' => array(
					'type'    => 'element',
					'adapter' => 'gutenberg',
					'goal'    => array(
						'type'     => 'click',
						'selector' => '.rwgc-goal',
					),
					'version' => 1,
				),
			)
		);

		$row        = RWGC_AB_Tests::get( $id );
		$experiment = RWGC_Contract_Experiment::from_array( $row['config'] );

		$this->assertSame( (string) $id, $experiment->id() );
		$this->assertSame( 'a', $experiment->control() );
		$this->assertSame(
			array(
				array(
					'id'         => 'a',
					'allocation' => 50.0,
				),
				array(
					'id'         => 'b',
					'allocation' => 50.0,
				),
			),
			$experiment->variants()
		);
		$this->assertSame( 1, $experiment->extras()['version'] );
		$this->assertSame( 'element', $experiment->extras()['type'] );
		$this->assertSame( 'gutenberg', $experiment->extras()['adapter'] );
		$this->assertSame( 'click', $experiment->extras()['goal']['type'] );
		$this->assertSame( '.rwgc-goal', $experiment->extras()['goal']['selector'] );
		$this->assertArrayNotHasKey( 'id', $experiment->extras() );
		$this->assertArrayNotHasKey( 'control', $experiment->extras() );
		$this->assertArrayNotHasKey( 'variants', $experiment->extras() );

		$round = RWGC_Contract_Experiment::from_array( $experiment->to_array() );
		$this->assertSame( $experiment->id(), $round->id() );
		$this->assertSame( 'gutenberg', $round->extras()['adapter'] );
	}

	public function test_contract_rejects_a_config_without_an_id(): void {
		$this->expectException( RWGC_Contract_Exception::class );
		RWGC_Contract_Experiment::from_array( RWGC_AB_Tests::sanitize_config( array(), 0 ) );
	}

	public function test_daily_table_is_created_once(): void {
		$this->assertSame( 'wp_rwgc_ab_daily', RWGC_AB_DB::table_name() );

		RWGC_AB_DB::maybe_upgrade();
		$this->assertCount( 1, $GLOBALS['rwgc_ab_dbdelta_calls'] );
		$this->assertSame( RWGC_AB_DB::VERSION, $GLOBALS['rwgc_test_options'][ RWGC_AB_DB::OPTION ] );

		$sql = (string) $GLOBALS['rwgc_ab_dbdelta_calls'][0];
		$this->assertStringContainsString( 'CREATE TABLE wp_rwgc_ab_daily', $sql );
		$this->assertStringContainsString( 'test_id bigint(20) unsigned NOT NULL', $sql );
		$this->assertStringContainsString( 'variant varchar(16) NOT NULL', $sql );
		$this->assertStringContainsString( 'day date NOT NULL', $sql );
		$this->assertStringContainsString( 'views int(10) unsigned NOT NULL default 0', $sql );
		$this->assertStringContainsString( 'conversions int(10) unsigned NOT NULL default 0', $sql );
		$this->assertStringContainsString( 'PRIMARY KEY  (test_id,variant,day)', $sql );
		$this->assertStringNotContainsString( 'rwgo_', $sql );

		RWGC_AB_DB::maybe_upgrade();
		$this->assertCount( 1, $GLOBALS['rwgc_ab_dbdelta_calls'] );

		$GLOBALS['rwgc_test_options'][ RWGC_AB_DB::OPTION ] = '0';
		RWGC_AB_DB::maybe_upgrade();
		$this->assertCount( 2, $GLOBALS['rwgc_ab_dbdelta_calls'] );
		$this->assertSame( '1', $GLOBALS['rwgc_test_options'][ RWGC_AB_DB::OPTION ] );
	}

	public function test_source_link_uses_the_stored_post(): void {
		$link = RWGC_AB_Admin::source_link(
			array(
				'source_post_id' => 42,
			)
		);
		$this->assertSame( 'http://example.test/wp-admin/post.php?post=42&action=edit', $link['url'] );
		$this->assertSame( '#42', $link['label'] );
		$this->assertSame(
			array(
				'url'   => '',
				'label' => '',
			),
			RWGC_AB_Admin::source_link( array() )
		);
	}

	public function test_admin_end_and_delete_require_a_nonce_and_manage_options(): void {
		$admin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-admin.php' );
		$this->assertStringContainsString( "current_user_can( 'manage_options' )", $admin );
		$this->assertStringContainsString( "check_admin_referer( 'rwgc_ab_end_test_' . \$test_id )", $admin );
		$this->assertStringContainsString( "check_admin_referer( 'rwgc_ab_delete_test_' . \$test_id )", $admin );
		$this->assertStringContainsString( "'capability' => 'manage_options'", $admin );

		$view = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/views/ab-tests-list.php' );
		$this->assertStringContainsString( 'rwgc_ab_end_test_', $view );
		$this->assertStringContainsString( 'rwgc_ab_delete_test_', $view );
		$this->assertStringContainsString( 'source_link', $view );
	}

	public function test_plugin_wires_store_and_uninstall_touches_only_core_ab_data(): void {
		$root     = dirname( __DIR__, 2 );
		$plugin   = (string) file_get_contents( $root . '/includes/class-rwgc-plugin.php' );
		$registry = (string) file_get_contents( $root . '/includes/class-rwgc-admin-route-registry.php' );
		$nav      = (string) file_get_contents( $root . '/includes/class-rwgc-admin-experiences-nav.php' );
		$tests    = (string) file_get_contents( $root . '/includes/ab/class-rwgc-ab-tests.php' );
		$uninstall = (string) file_get_contents( $root . '/uninstall.php' );

		$this->assertStringContainsString( 'includes/ab/class-rwgc-ab-db.php', $plugin );
		$this->assertStringContainsString( 'includes/ab/class-rwgc-ab-tests.php', $plugin );
		$this->assertStringContainsString( 'includes/ab/class-rwgc-ab-admin.php', $plugin );
		$this->assertStringContainsString( 'RWGC_AB_DB::init()', $plugin );
		$this->assertStringContainsString( 'RWGC_AB_Tests::init()', $plugin );
		$this->assertStringContainsString( 'RWGC_AB_Admin::init()', $plugin );
		$this->assertStringContainsString( 'RWGC_AB_DB::install()', $plugin );

		$this->assertStringContainsString( "'menu_slug' => 'rwgc-ab-tests'", $registry );
		$this->assertStringContainsString( "'section'   => 'experiences'", $registry );
		$this->assertDoesNotMatchRegularExpression( '/\$rwgc_opt_slugs\s*=\s*array\([^;]*rwgc-ab-tests/s', $registry );
		$this->assertStringContainsString( "'rwgc-ab-tests'", $nav );

		$this->assertStringContainsString( "'public'              => false", $tests );
		$this->assertStringContainsString( "'show_ui'             => false", $tests );
		$this->assertStringContainsString( 'rwgc_ab_ended', $tests );

		$this->assertStringContainsString( 'rwgc_ab_daily', $uninstall );
		$this->assertStringContainsString( 'RWGC_AB_Tests::POST_TYPE', $uninstall );
		$this->assertStringContainsString( 'RWGC_AB_DB::OPTION', $uninstall );
		$this->assertSame( 1, substr_count( $uninstall, 'DROP TABLE' ) );
		$this->assertStringNotContainsString( 'rwgo_', $uninstall );

		foreach ( glob( $root . '/includes/ab/*.php' ) as $file ) {
			$src = strtolower( (string) file_get_contents( $file ) );
			$this->assertStringNotContainsString( 'license', $src, $file );
			$this->assertStringNotContainsString( 'licence', $src, $file );
			$this->assertStringNotContainsString( 'entitle', $src, $file );
		}
	}
}
