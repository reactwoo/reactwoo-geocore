<?php
/**
 * Regression coverage for Elementor popup fallback routing.
 *
 * @package ReactWoo_Geo_Core
 */

namespace ElementorPro\Modules\Popup {
	class Module {
		/**
		 * @var int[]
		 */
		public static $added = array();

		/**
		 * @param int $popup_id Popup template ID.
		 * @return void
		 */
		public static function add_popup_to_location( $popup_id ) {
			self::$added[] = (int) $popup_id;
		}
	}
}

namespace {
	use ElementorPro\Modules\Popup\Module;
	use PHPUnit\Framework\TestCase;

	if ( ! function_exists( 'rwgc_is_builder_edit_request' ) ) {
		/**
		 * @return bool
		 */
		function rwgc_is_builder_edit_request() {
			return false;
		}
	}

	if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
		/**
		 * @return string
		 */
		function rwgc_get_visitor_country() {
			return (string) ( $GLOBALS['rwgc_test_visitor_country'] ?? '' );
		}
	}

	if ( ! function_exists( 'rwgc_get_visitor_data' ) ) {
		/**
		 * @return array<string, string>
		 */
		function rwgc_get_visitor_data() {
			return array( 'country_code' => rwgc_get_visitor_country() );
		}
	}

	if ( ! function_exists( 'get_posts' ) ) {
		/**
		 * @param array<string, mixed> $args Query args.
		 * @return array<int, int>
		 */
		function get_posts( $args ) {
			unset( $args );
			return array( 123 );
		}
	}

	if ( ! function_exists( 'get_post_meta' ) ) {
		/**
		 * @param int    $post_id Post ID.
		 * @param string $key Meta key.
		 * @param bool   $single Whether to return a single value.
		 * @return mixed
		 */
		function get_post_meta( $post_id, $key, $single = false ) {
			unset( $single );
			if ( 123 !== (int) $post_id || '_elementor_page_settings' !== $key ) {
				return '';
			}

			return array(
				'egp_enable_geo_targeting'     => 'yes',
				'egp_countries'                => array( 'GB' ),
				'rwgc_country_visibility_mode' => 'show_if',
			);
		}
	}

	if ( ! function_exists( 'post_type_exists' ) ) {
		/**
		 * @param string $post_type Post type.
		 * @return bool
		 */
		function post_type_exists( $post_type ) {
			unset( $post_type );
			return false;
		}
	}

	if ( ! function_exists( 'get_the_title' ) ) {
		/**
		 * @param int $post_id Post ID.
		 * @return string
		 */
		function get_the_title( $post_id ) {
			return 'Popup ' . (int) $post_id;
		}
	}

	if ( ! function_exists( 'get_query_var' ) ) {
		/**
		 * @param string $key Query var key.
		 * @return string
		 */
		function get_query_var( $key ) {
			unset( $key );
			return '';
		}
	}

	if ( ! function_exists( 'wp_parse_url' ) ) {
		/**
		 * @param string $url URL.
		 * @param int    $component URL component.
		 * @return mixed
		 */
		function wp_parse_url( $url, $component = -1 ) {
			return parse_url( $url, $component );
		}
	}

	if ( ! function_exists( 'home_url' ) ) {
		/**
		 * @param string $path Path.
		 * @return string
		 */
		function home_url( $path = '' ) {
			return 'https://example.test' . $path;
		}
	}

	if ( ! function_exists( 'trailingslashit' ) ) {
		/**
		 * @param string $value Path.
		 * @return string
		 */
		function trailingslashit( $value ) {
			return rtrim( $value, '/' ) . '/';
		}
	}

	require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';
	require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-page-version.php';
	require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-page-version-routing.php';
	require_once dirname( __DIR__, 2 ) . '/includes/integrations/elementor/class-rwgc-elementor-popups.php';

	/**
	 * @covers RWGC_Elementor_Popups
	 */
	final class RWGC_ElementorPopupsTest extends TestCase {

		protected function setUp(): void {
			parent::setUp();
			Module::$added                         = array();
			$GLOBALS['rwgc_test_visitor_country'] = 'GB';
			$_SERVER['REQUEST_URI']                = '/landing-page/';
			$this->reset_page_version_cache();
		}

		public function test_geo_allowed_popup_is_not_manually_queued_on_normal_requests(): void {
			$this->assertTrue( \RWGC_Elementor_Popups::popup_should_display( 123 ) );

			\RWGC_Elementor_Popups::ensure_allowed_popups_in_location();

			$this->assertSame( array(), Module::$added );
		}

		public function test_geo_allowed_popup_is_manually_queued_on_page_version_requests(): void {
			$_SERVER['REQUEST_URI'] = '/landing-page/_gc/summer/';
			$this->reset_page_version_cache();

			$this->assertTrue( \RWGC_Page_Version_Routing::is_page_version_request() );

			\RWGC_Elementor_Popups::ensure_allowed_popups_in_location();

			$this->assertSame( array( 123 ), Module::$added );
		}

		/**
		 * @return void
		 */
		private function reset_page_version_cache() {
			$ref  = new \ReflectionClass( \RWGC_Page_Version_Routing::class );
			$prop = $ref->getProperty( 'parsed_request' );
			$prop->setAccessible( true );
			$prop->setValue( false );
		}
	}
}
