<?php
/**
 * Regression tests for Page Version URL routing and portable targeting renderers.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
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

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	/**
	 * @return bool
	 */
	function wp_doing_ajax() {
		return ! empty( $GLOBALS['rwgc_test_doing_ajax'] );
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	/**
	 * @return bool
	 */
	function is_singular() {
		return ! empty( $GLOBALS['rwgc_test_is_singular'] );
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	/**
	 * @return int
	 */
	function get_queried_object_id() {
		return isset( $GLOBALS['rwgc_test_queried_object_id'] ) ? (int) $GLOBALS['rwgc_test_queried_object_id'] : 0;
	}
}

if ( ! function_exists( 'rwgc_is_builder_edit_request' ) ) {
	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function rwgc_is_builder_edit_request( $post_id ) {
		unset( $post_id );
		return false;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Whether to return one value.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key, $single = false ) {
		$value = isset( $GLOBALS['rwgc_test_post_meta'][ $post_id ][ $key ] ) ? $GLOBALS['rwgc_test_post_meta'][ $post_id ][ $key ] : '';
		return $single ? $value : array( $value );
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	/**
	 * @return string
	 */
	function rwgc_get_visitor_country() {
		return isset( $GLOBALS['rwgc_test_visitor_country'] ) ? (string) $GLOBALS['rwgc_test_visitor_country'] : '';
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * @param mixed $args     Args.
	 * @param array $defaults Defaults.
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, is_array( $args ) ? $args : array() );
	}
}

if ( ! function_exists( 'do_shortcode' ) ) {
	/**
	 * @param string $content Content.
	 * @return string
	 */
	function do_shortcode( $content ) {
		return $content;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * @param int $post_id Post ID.
	 * @return WP_Post|null
	 */
	function get_post( $post_id ) {
		return isset( $GLOBALS['rwgc_test_posts'][ (int) $post_id ] ) ? $GLOBALS['rwgc_test_posts'][ (int) $post_id ] : null;
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	/**
	 * @param int $post_id Post ID.
	 * @return string|false
	 */
	function get_post_type( $post_id ) {
		$post = get_post( $post_id );
		return $post ? $post->post_type : false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @return bool
	 */
	function current_user_can() {
		return false;
	}
}

if ( ! function_exists( 'get_page_by_path' ) ) {
	/**
	 * @param string $path      Path.
	 * @param string $output    Output type.
	 * @param mixed  $post_type Post type.
	 * @return WP_Post|null
	 */
	function get_page_by_path( $path, $output = OBJECT, $post_type = 'page' ) {
		unset( $output, $post_type );
		$key = trim( (string) $path, '/' );
		return isset( $GLOBALS['rwgc_test_path_posts'][ $key ] ) ? $GLOBALS['rwgc_test_path_posts'][ $key ] : null;
	}
}

if ( ! function_exists( 'get_query_var' ) ) {
	/**
	 * @param string $var Query var.
	 * @return mixed
	 */
	function get_query_var( $var ) {
		return isset( $GLOBALS['rwgc_test_query_vars'][ $var ] ) ? $GLOBALS['rwgc_test_query_vars'][ $var ] : '';
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal post object for tests.
	 */
	class WP_Post {
		/**
		 * @var int
		 */
		public $ID = 0;

		/**
		 * @var string
		 */
		public $post_type = 'page';

		/**
		 * @var string
		 */
		public $post_status = 'publish';

		/**
		 * @var string
		 */
		public $post_title = '';

		/**
		 * @param array<string, mixed> $props Properties.
		 */
		public function __construct( array $props = array() ) {
			foreach ( $props as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = $value;
				}
			}
		}
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	/**
	 * Minimal main query object for routing tests.
	 */
	class WP_Query {
		/**
		 * @var array<string, mixed>
		 */
		public $query_vars = array();

		/**
		 * @var bool
		 */
		public $main = true;

		/**
		 * @var bool
		 */
		public $is_page = false;

		/**
		 * @var bool
		 */
		public $is_single = false;

		/**
		 * @var bool
		 */
		public $is_singular = false;

		/**
		 * @var bool
		 */
		public $is_404 = false;

		/**
		 * @var bool
		 */
		public $is_home = true;

		/**
		 * @return bool
		 */
		public function is_main_query() {
			return $this->main;
		}

		/**
		 * @param string $key   Query var key.
		 * @param mixed  $value Query var value.
		 * @return void
		 */
		public function set( $key, $value ) {
			$this->query_vars[ $key ] = $value;
		}

		/**
		 * @return void
		 */
		public function set_404() {
			$this->is_404 = true;
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-context-snapshot.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-target-operators.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-page-version.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-rule-evaluator.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-context-resolver.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-page-version-routing.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-elementor.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-gutenberg.php';

/**
 * @covers RWGC_Elementor
 * @covers RWGC_Gutenberg
 * @covers RWGC_Page_Version_Routing
 */
class PageVersionPortableTargetingTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['rwgc_test_is_admin']          = false;
		$GLOBALS['rwgc_test_doing_ajax']        = false;
		$GLOBALS['rwgc_test_is_singular']       = true;
		$GLOBALS['rwgc_test_queried_object_id'] = 11;
		$GLOBALS['rwgc_test_visitor_country']   = 'US';
		$GLOBALS['rwgc_test_post_meta']         = array();
		$GLOBALS['rwgc_test_posts']             = array();
		$GLOBALS['rwgc_test_path_posts']        = array();
		$GLOBALS['rwgc_test_query_vars']        = array();
	}

	public function test_elementor_hides_when_enabled_portable_rule_is_unusable() {
		$GLOBALS['rwgc_test_post_meta'][11]['_elementor_page_settings'] = array(
			'egp_enable_geo_targeting'          => 'yes',
			'rwgc_use_portable_geo_targeting'   => 'yes',
			'rwgc_portable_geo_targeting'       => $this->invalid_page_version_rule_json(),
			'egp_countries'                     => array(),
		);

		$this->assertSame( '', RWGC_Elementor::filter_document_content( 'restricted' ) );
	}

	public function test_gutenberg_hides_when_enabled_portable_rule_is_unusable() {
		$attrs = array(
			'usePortableTargeting' => true,
			'portableTargeting'    => $this->invalid_page_version_rule_json(),
			'showCountries'        => array(),
			'hideCountries'        => array(),
		);

		$this->assertSame( '', RWGC_Gutenberg::render_geo_content_block( $attrs, 'restricted' ) );
	}

	public function test_page_version_routes_posts_as_single_posts() {
		$post = new WP_Post(
			array(
				'ID'          => 22,
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);
		$GLOBALS['rwgc_test_posts'][22]               = $post;
		$GLOBALS['rwgc_test_path_posts']['hello']     = $post;
		$GLOBALS['rwgc_test_query_vars']['pagename']  = 'hello';
		$GLOBALS['rwgc_test_query_vars']['rwgc_page_version'] = 'campaign';

		$query = new WP_Query();
		RWGC_Page_Version_Routing::pre_get_posts( $query );

		$this->assertSame( 22, $query->query_vars['p'] );
		$this->assertSame( 0, $query->query_vars['page_id'] );
		$this->assertSame( '', $query->query_vars['pagename'] );
		$this->assertTrue( $query->is_single );
		$this->assertFalse( $query->is_page );
		$this->assertTrue( $query->is_singular );
		$this->assertFalse( $query->is_404 );
	}

	public function test_page_version_routes_pages_as_pages() {
		$page = new WP_Post(
			array(
				'ID'          => 33,
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$GLOBALS['rwgc_test_posts'][33]               = $page;
		$GLOBALS['rwgc_test_path_posts']['landing']   = $page;
		$GLOBALS['rwgc_test_query_vars']['pagename']  = 'landing';
		$GLOBALS['rwgc_test_query_vars']['rwgc_page_version'] = 'campaign';

		$query = new WP_Query();
		RWGC_Page_Version_Routing::pre_get_posts( $query );

		$this->assertSame( 33, $query->query_vars['page_id'] );
		$this->assertSame( 0, $query->query_vars['p'] );
		$this->assertSame( '', $query->query_vars['pagename'] );
		$this->assertTrue( $query->is_page );
		$this->assertFalse( $query->is_single );
		$this->assertTrue( $query->is_singular );
		$this->assertFalse( $query->is_404 );
	}

	/**
	 * @return string
	 */
	private function invalid_page_version_rule_json() {
		return wp_json_encode(
			array(
				'enabled' => true,
				'mode'    => 'show',
				'match'   => 'all',
				'rules'   => array(
					array(
						'id'         => 'page_version',
						'match'      => 'all',
						'conditions' => array(
							array(
								'type'     => 'page_version_url',
								'operator' => 'equals',
								'value'    => array(
									'page_id' => 999,
									'version' => 'campaign',
								),
							),
						),
					),
				),
			)
		);
	}
}
