<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcodes for ReactWoo Geo Core.
 */
class RWGC_Shortcodes {

	/**
	 * Post meta on the test that points at the post whose content holds [rwgc_ab].
	 */
	const AB_SOURCE_META = '_rwgc_ab_shortcode_post';

	/**
	 * True while the fragment route is rendering one shortcode arm.
	 *
	 * A nested [rwgc_ab] must not call rwgc_ab_render_test().
	 *
	 * @var int
	 */
	private static $supplying_variant_html = 0;

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'rwgc_country', array( __CLASS__, 'render_country' ) );
		add_shortcode( 'rwgc_country_code', array( __CLASS__, 'render_country_code' ) );
		add_shortcode( 'rwgc_currency', array( __CLASS__, 'render_currency' ) );
		add_shortcode( 'rwgc_city', array( __CLASS__, 'render_city' ) );
		add_shortcode( 'rwgc_region', array( __CLASS__, 'render_region' ) );
		add_shortcode( 'rwgc_if', array( __CLASS__, 'render_conditional' ) );
		add_shortcode( 'rwgc_ab', array( __CLASS__, 'render_ab' ) );
		add_shortcode( 'rwgc_ab_variant', array( __CLASS__, 'render_ab_variant' ) );
		add_filter( 'rwgc_ab_variant_html', array( __CLASS__, 'filter_variant_html' ), 10, 3 );
		add_action( 'save_post', array( __CLASS__, 'sync_on_save' ), 20, 2 );
	}

	public static function render_country() {
		self::enqueue_styles();
		return esc_html( rwgc_get_visitor_country_name() );
	}

	public static function render_country_code() {
		self::enqueue_styles();
		return esc_html( rwgc_get_visitor_country() );
	}

	public static function render_currency() {
		self::enqueue_styles();
		return esc_html( rwgc_get_visitor_currency() );
	}

	public static function render_city() {
		self::enqueue_styles();
		return esc_html( rwgc_get_visitor_city() );
	}

	public static function render_region() {
		self::enqueue_styles();
		return esc_html( rwgc_get_visitor_region() );
	}

	/**
	 * @return void
	 */
	private static function enqueue_styles() {
		if ( class_exists( 'RWGC_Components', false ) ) {
			RWGC_Components::enqueue_stylesheet();
		}
	}

	/**
	 * Conditional shortcode based on visitor country (aligned with {@see RWGC_Rule_Condition_Evaluator} when includes are set).
	 *
	 * Include list (visitor must match):
	 * `[rwgc_if country="GB,US"]…[/rwgc_if]`
	 * `[rwgc_if groups="slug_a,slug_b"]…[/rwgc_if]` — uses {@see RWGC_Country_Groups}.
	 *
	 * Exclude (combined with includes): visitor must match include rules and not be in exclude lists:
	 * `[rwgc_if country="GB,DE" exclude="FR"]…[/rwgc_if]`
	 * `[rwgc_if groups="eu" groups_exclude="uk"]…[/rwgc_if]`
	 *
	 * Exclude-only (visitor must have a country and it must not be listed):
	 * `[rwgc_if exclude="US,CA"]…[/rwgc_if]`
	 * `[rwgc_if groups_exclude="blocked"]…[/rwgc_if]`
	 *
	 * Split model (same as Elementor Geo Visibility), via {@see RWGC_Targeting_Surface_Evaluator}:
	 * `[rwgc_if country_on="yes" country="GB,US" country_mode="show_if"]…[/rwgc_if]`
	 * `[rwgc_if visibility_on="yes" rule="my-rule-slug" mode="show_if"]…[/rwgc_if]`
	 * `[rwgc_if visibility_on="yes" portable='{"version":1}']…[/rwgc_if]`
	 *
	 * @param array       $atts    Attributes.
	 * @param string|null $content Wrapped content.
	 * @return string
	 */
	public static function render_conditional( $atts, $content = null ) {
		self::enqueue_styles();
		$atts = shortcode_atts(
			array(
				'country'          => '',
				'exclude'          => '', // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- [rwgc_if] attribute, not a WP_Query exclusion.
				'groups'           => '',
				'groups_exclude'   => '',
				'country_on'       => '',
				'country_mode'     => 'show_if',
				'visibility_on'    => '',
				'mode'             => 'show_if',
				'rule'             => '',
				'visibility_rule'  => '',
				'portable'         => '',
			),
			$atts,
			'rwgc_if'
		);

		if ( self::should_use_surface_evaluator( $atts ) ) {
			return self::render_conditional_surface( $atts, $content );
		}

		$country_csv         = trim( (string) $atts['country'] );
		$exclude_csv         = trim( (string) $atts['exclude'] );
		$groups_csv          = trim( (string) $atts['groups'] );
		$groups_exclude_csv  = trim( (string) $atts['groups_exclude'] );

		$has_include = '' !== $country_csv || '' !== $groups_csv;
		$has_exclude_attrs = '' !== $exclude_csv || '' !== $groups_exclude_csv;

		if ( ! $has_include && ! $has_exclude_attrs ) {
			return '';
		}

		if ( ! $has_include && $has_exclude_attrs ) {
			return self::render_conditional_exclude_only( $exclude_csv, $groups_exclude_csv, $content );
		}

		if ( ! class_exists( 'RWGC_Rule_Condition_Evaluator', false ) || ! class_exists( 'RWGC_Context', false ) ) {
			return '';
		}

		$conditions = array();
		if ( '' !== $country_csv ) {
			$conditions['countries_include'] = self::parse_iso_csv( $country_csv );
		}
		if ( '' !== $exclude_csv ) {
			$conditions['countries_exclude'] = self::parse_iso_csv( $exclude_csv );
		}
		if ( '' !== $groups_csv ) {
			$conditions['country_groups_include'] = self::parse_slug_csv( $groups_csv );
		}
		if ( '' !== $groups_exclude_csv ) {
			$conditions['country_groups_exclude'] = self::parse_slug_csv( $groups_exclude_csv );
		}

		$context = RWGC_Context::from_visitor();
		$match   = RWGC_Rule_Condition_Evaluator::context_matches_conditions( $conditions, $context );

		if ( ! $match ) {
			return '';
		}

		return rwgc_kses_geo_content_html( do_shortcode( (string) $content ) );
	}

	/**
	 * Whether to evaluate via the shared surface evaluator (visibility rules / explicit split attrs).
	 *
	 * @param array<string, string> $atts Shortcode attributes.
	 * @return bool
	 */
	private static function should_use_surface_evaluator( array $atts ) {
		if ( '' !== trim( (string) ( $atts['rule'] ?? '' ) ) || '' !== trim( (string) ( $atts['visibility_rule'] ?? '' ) ) ) {
			return true;
		}
		if ( '' !== trim( (string) ( $atts['portable'] ?? '' ) ) ) {
			return true;
		}
		if ( in_array( strtolower( (string) ( $atts['visibility_on'] ?? '' ) ), array( '1', 'yes', 'true' ), true ) ) {
			return true;
		}
		if ( in_array( strtolower( (string) ( $atts['country_on'] ?? '' ) ), array( '1', 'yes', 'true' ), true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param array<string, string> $atts    Attributes.
	 * @param string|null           $content Inner content.
	 * @return string
	 */
	private static function render_conditional_surface( array $atts, $content ) {
		if ( ! class_exists( 'RWGC_Surface_Settings', false ) || ! class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) ) {
			return '';
		}

		$surface_atts = array(
			'country_on'    => in_array( strtolower( (string) ( $atts['country_on'] ?? '' ) ), array( '1', 'yes', 'true' ), true ),
			'country_mode'  => (string) ( $atts['country_mode'] ?? 'show_if' ),
			'country'       => (string) ( $atts['country'] ?? '' ),
			'visibility_on' => in_array( strtolower( (string) ( $atts['visibility_on'] ?? '' ) ), array( '1', 'yes', 'true' ), true ),
			'mode'          => (string) ( $atts['mode'] ?? 'show_if' ),
			'rule'          => (string) ( $atts['rule'] ?? '' ),
			'visibility_rule' => (string) ( $atts['visibility_rule'] ?? '' ),
			'portable'      => (string) ( $atts['portable'] ?? '' ),
		);

		$settings = RWGC_Surface_Settings::from_shortcode_atts( $surface_atts );
		if ( ! RWGC_Targeting_Surface_Evaluator::is_surface_active( $settings ) ) {
			return '';
		}

		$result = RWGC_Targeting_Surface_Evaluator::evaluate( $settings );
		if ( empty( $result['should_render'] ) ) {
			return '';
		}

		return rwgc_kses_geo_content_html( do_shortcode( (string) $content ) );
	}

	/**
	 * Show inner content when visitor country exists and is not in exclude lists.
	 *
	 * @param string      $exclude_csv        Comma ISO2 codes.
	 * @param string      $groups_exclude_csv Comma group slugs.
	 * @param string|null $content            Inner content.
	 * @return string
	 */
	private static function render_conditional_exclude_only( $exclude_csv, $groups_exclude_csv, $content ) {
		$visitor = strtoupper( (string) rwgc_get_visitor_country() );
		if ( '' === $visitor ) {
			return '';
		}

		$exclude = self::parse_iso_csv( $exclude_csv );
		if ( class_exists( 'RWGC_Country_Groups', false ) && '' !== $groups_exclude_csv ) {
			$exclude = array_merge(
				$exclude,
				RWGC_Country_Groups::expand_groups_to_countries( self::parse_slug_csv( $groups_exclude_csv ) )
			);
		}
		if ( class_exists( 'RWGC_Rule_Condition_Evaluator', false ) ) {
			$exclude = RWGC_Rule_Condition_Evaluator::normalize_iso2_list( $exclude );
		} else {
			$exclude = array_unique( array_filter( array_map( 'strtoupper', $exclude ) ) );
		}

		if ( ! empty( $exclude ) && in_array( $visitor, $exclude, true ) ) {
			return '';
		}

		return rwgc_kses_geo_content_html( do_shortcode( (string) $content ) );
	}

	/**
	 * @param string $csv Comma-separated ISO2 codes.
	 * @return string[]
	 */
	private static function parse_iso_csv( $csv ) {
		$parts = array_filter( array_map( 'trim', explode( ',', strtoupper( (string) $csv ) ) ) );
		if ( class_exists( 'RWGC_Rule_Condition_Evaluator', false ) ) {
			return RWGC_Rule_Condition_Evaluator::normalize_iso2_list( $parts );
		}
		return $parts;
	}

	/**
	 * @param string $csv Comma-separated slugs.
	 * @return string[]
	 */
	private static function parse_slug_csv( $csv ) {
		$parts = array_filter( array_map( 'trim', explode( ',', (string) $csv ) ) );
		return array_map( 'sanitize_key', $parts );
	}

	/**
	 * Shortcode an author pastes for one test. Variants are filled in the editor.
	 *
	 * Divi, Bricks, and the classic editor use this. The A/B tests list copies it.
	 *
	 * @param int|string $test_id Test id.
	 * @return string
	 */
	public static function shortcode_snippet( $test_id ) {
		$test_id = absint( $test_id );
		return '[rwgc_ab id="' . $test_id . '"][rwgc_ab_variant][/rwgc_ab_variant][rwgc_ab_variant][/rwgc_ab_variant][/rwgc_ab]';
	}

	/**
	 * [rwgc_ab id="123"][rwgc_ab_variant]A[/rwgc_ab_variant][rwgc_ab_variant]B[/rwgc_ab_variant][/rwgc_ab]
	 *
	 * A running test is printed by rwgc_ab_render_test(), so the wrapper, the
	 * cookie, and the cache headers match the Gutenberg block. An unknown or
	 * inactive id prints the first variant and does not call that helper.
	 * Fragment mode reads each arm back through rwgc_ab_variant_html.
	 *
	 * @param array<string, mixed>|string $atts    Attributes.
	 * @param string|null                 $content Enclosed content.
	 * @return string
	 */
	public static function render_ab( $atts, $content = null ) {
		$atts    = shortcode_atts(
			array(
				'id' => '0',
			),
			is_array( $atts ) ? $atts : array(),
			'rwgc_ab'
		);
		$test_id = absint( $atts['id'] );
		$parsed  = self::variant_arms( (string) $content );

		if ( self::$supplying_variant_html > 0 ) {
			return self::escape_variant_html( self::expand_shortcodes( $parsed['first'] ) );
		}

		if ( $test_id > 0 ) {
			self::remember_rendered_source( $test_id );
		}

		if ( self::test_is_running( $test_id ) && function_exists( 'rwgc_ab_render_test' ) ) {
			$arms = array(
				'a' => self::expand_shortcodes( $parsed['a'] ),
				'b' => self::expand_shortcodes( $parsed['b'] ),
			);
			$html = rwgc_ab_render_test( $test_id, $arms );
			if ( is_string( $html ) && false !== strpos( $html, 'data-rwgc-ab=' ) ) {
				return self::escape_block_html( $html );
			}
		}

		return self::fallback_html( $test_id, 'a', self::expand_shortcodes( $parsed['first'] ) );
	}

	/**
	 * Variant tags are read by [rwgc_ab]. On their own they print their content.
	 *
	 * @param array<string, mixed>|string $atts    Attributes.
	 * @param string|null                 $content Enclosed content.
	 * @return string
	 */
	public static function render_ab_variant( $atts, $content = null ) {
		unset( $atts );
		return self::escape_variant_html( self::expand_shortcodes( (string) $content ) );
	}

	/**
	 * HTML for one arm on the fragment render route.
	 *
	 * Another provider may already have filled $html. A shortcode test is read
	 * from the post that contains [rwgc_ab id]. This does not call
	 * rwgc_ab_render_test().
	 *
	 * @param mixed      $html    HTML from earlier filters.
	 * @param int|string $test_id Test post ID.
	 * @param string     $arm     Requested arm.
	 * @return string
	 */
	public static function filter_variant_html( $html, $test_id, $arm ) {
		if ( is_string( $html ) && '' !== trim( $html ) ) {
			return $html;
		}
		$rendered = self::variant_html_for_test( $test_id, $arm );
		if ( null === $rendered ) {
			return is_string( $html ) ? $html : '';
		}
		return $rendered;
	}

	/**
	 * Remember which post holds each [rwgc_ab id] so the render route can find it.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post.
	 * @return void
	 */
	public static function sync_on_save( $post_id, $post = null ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $post instanceof WP_Post && function_exists( 'get_post' ) ) {
			$post = get_post( $post_id );
		}
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$status = (string) $post->post_status;
		if ( 'trash' === $status || 'auto-draft' === $status ) {
			return;
		}
		self::index_content( $post_id, (string) $post->post_content );
	}

	/**
	 * Split enclosed content into arm A, arm B, and the first variant.
	 *
	 * The first [rwgc_ab_variant] is A and the second is B. variant="a" or
	 * variant="b" selects the arm. A later tag for an arm that is already
	 * filled is ignored. With no variant tags, the whole content is the first
	 * variant.
	 *
	 * @param string $content Enclosed shortcode content.
	 * @return array{a:string,b:string,first:string}
	 */
	public static function variant_arms( $content ) {
		$content = (string) $content;
		$arms    = array(
			'a'     => '',
			'b'     => '',
			'first' => '',
		);
		$matches = self::match_shortcode( 'rwgc_ab_variant', $content );
		if ( array() === $matches ) {
			$arms['a']     = $content;
			$arms['first'] = $content;
			return $arms;
		}

		$next = 'a';
		foreach ( $matches as $match ) {
			$atts = self::shortcode_attributes( isset( $match[3] ) ? $match[3] : '' );
			$slot = '';
			if ( isset( $atts['variant'] ) ) {
				$slot = sanitize_key( (string) $atts['variant'] );
			}
			if ( 'a' !== $slot && 'b' !== $slot ) {
				$slot = $next;
				$next = ( 'a' === $next ) ? 'b' : '';
			}
			$inner = isset( $match[5] ) ? (string) $match[5] : '';
			if ( '' === $arms['first'] ) {
				$arms['first'] = $inner;
			}
			if ( ( 'a' === $slot || 'b' === $slot ) && '' === $arms[ $slot ] ) {
				$arms[ $slot ] = $inner;
			}
		}
		if ( '' === $arms['a'] ) {
			$arms['a'] = $arms['first'];
		}
		return $arms;
	}

	/**
	 * @param int    $post_id Post that contains the shortcode.
	 * @param string $content Post content.
	 * @return void
	 */
	private static function index_content( $post_id, $content ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || false === strpos( (string) $content, '[rwgc_ab' ) ) {
			return;
		}
		foreach ( self::match_shortcode( 'rwgc_ab', $content ) as $match ) {
			$atts    = self::shortcode_attributes( isset( $match[3] ) ? $match[3] : '' );
			$test_id = isset( $atts['id'] ) ? absint( $atts['id'] ) : 0;
			if ( $test_id > 0 ) {
				self::store_source( $test_id, $post_id );
			}
		}
	}

	/**
	 * @param int $test_id Test id.
	 * @param int $post_id Source post.
	 * @return void
	 */
	private static function store_source( $test_id, $post_id ) {
		$test_id = absint( $test_id );
		$post_id = absint( $post_id );
		if ( $test_id <= 0 || $post_id <= 0 || ! function_exists( 'update_post_meta' ) ) {
			return;
		}
		$existing = function_exists( 'get_post_meta' ) ? absint( get_post_meta( $test_id, self::AB_SOURCE_META, true ) ) : 0;
		if ( $existing === $post_id ) {
			return;
		}
		update_post_meta( $test_id, self::AB_SOURCE_META, $post_id );
	}

	/**
	 * Point the test at the current post when that post actually contains it.
	 *
	 * @param int $test_id Test id.
	 * @return void
	 */
	private static function remember_rendered_source( $test_id ) {
		if ( ! function_exists( 'get_the_ID' ) || ! function_exists( 'get_post' ) ) {
			return;
		}
		$post_id = absint( get_the_ID() );
		if ( $post_id <= 0 ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( null === self::inner_content_for_test( (string) $post->post_content, $test_id ) ) {
			return;
		}
		self::store_source( $test_id, $post_id );
	}

	/**
	 * @param int|string $test_id Test id.
	 * @param string     $arm     a|b.
	 * @return string|null Null when this id is not a shortcode test.
	 */
	private static function variant_html_for_test( $test_id, $arm ) {
		$raw = self::raw_arm_from_source( $test_id, $arm );
		if ( null === $raw ) {
			return null;
		}
		++self::$supplying_variant_html;
		try {
			return self::escape_variant_html( self::expand_shortcodes( $raw ) );
		} finally {
			if ( self::$supplying_variant_html > 0 ) {
				--self::$supplying_variant_html;
			}
		}
	}

	/**
	 * @param int|string $test_id Test id.
	 * @param string     $arm     a|b.
	 * @return string|null
	 */
	private static function raw_arm_from_source( $test_id, $arm ) {
		$test_id = absint( $test_id );
		if ( $test_id <= 0 || ! function_exists( 'get_post_meta' ) || ! function_exists( 'get_post' ) ) {
			return null;
		}
		$post_id = absint( get_post_meta( $test_id, self::AB_SOURCE_META, true ) );
		if ( $post_id <= 0 ) {
			return null;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		if ( class_exists( 'RWGC_AB_Cache', false ) && ! RWGC_AB_Cache::source_post_is_publicly_viewable( $post ) ) {
			return null;
		}
		$inner = self::inner_content_for_test( (string) $post->post_content, $test_id );
		if ( null === $inner ) {
			return null;
		}
		$parsed = self::variant_arms( $inner );
		$arm    = ( 'b' === sanitize_key( (string) $arm ) ) ? 'b' : 'a';
		return isset( $parsed[ $arm ] ) ? (string) $parsed[ $arm ] : '';
	}

	/**
	 * Enclosed content of the first [rwgc_ab] with this id, or null when absent.
	 *
	 * @param string $content Post content.
	 * @param int    $test_id Test id.
	 * @return string|null
	 */
	private static function inner_content_for_test( $content, $test_id ) {
		$test_id = absint( $test_id );
		foreach ( self::match_shortcode( 'rwgc_ab', $content ) as $match ) {
			$atts = self::shortcode_attributes( isset( $match[3] ) ? $match[3] : '' );
			$id   = isset( $atts['id'] ) ? absint( $atts['id'] ) : 0;
			if ( $id !== $test_id ) {
				continue;
			}
			return isset( $match[5] ) ? (string) $match[5] : '';
		}
		return null;
	}

	/**
	 * @param int $test_id Test id.
	 * @return bool
	 */
	private static function test_is_running( $test_id ) {
		$test_id = absint( $test_id );
		if ( $test_id <= 0 || ! class_exists( 'RWGC_AB_Tests', false ) ) {
			return false;
		}
		$row = RWGC_AB_Tests::get( $test_id );
		return is_array( $row ) && 'publish' === (string) ( isset( $row['status'] ) ? $row['status'] : '' );
	}

	/**
	 * @param string $content Content.
	 * @return string
	 */
	private static function expand_shortcodes( $content ) {
		$content = (string) $content;
		if ( function_exists( 'do_shortcode' ) && false !== strpos( $content, '[' ) ) {
			$expanded = do_shortcode( $content );
			if ( is_string( $expanded ) ) {
				$content = $expanded;
			}
		}
		return $content;
	}

	/**
	 * @param string $tag  Shortcode name.
	 * @param string $text Text that may contain the shortcode.
	 * @return array<int, array<int, string>>
	 */
	private static function match_shortcode( $tag, $text ) {
		$text = (string) $text;
		if ( '' === $text || false === strpos( $text, '[' ) ) {
			return array();
		}
		$regex = self::shortcode_regex( array( (string) $tag ) );
		if ( ! preg_match_all( '/' . $regex . '/', $text, $matches, PREG_SET_ORDER ) ) {
			return array();
		}
		$found = array();
		foreach ( $matches as $match ) {
			if ( ! is_array( $match ) ) {
				continue;
			}
			if ( ( isset( $match[1] ) && '' !== $match[1] ) || ( isset( $match[6] ) && '' !== $match[6] ) ) {
				continue;
			}
			$found[] = $match;
		}
		return $found;
	}

	/**
	 * WordPress shortcode regex for one or more tags.
	 *
	 * @param string[] $tags Tag names.
	 * @return string
	 */
	private static function shortcode_regex( array $tags ) {
		$names = array();
		foreach ( $tags as $tag ) {
			$tag = (string) $tag;
			if ( '' !== $tag ) {
				$names[] = preg_quote( $tag, '/' );
			}
		}
		$tagregexp = implode( '|', $names );
		return '\\['
			. '(\\[?)'
			. '(' . $tagregexp . ')'
			. '(?![\\w-])'
			. '('
			.     '[^\\]\\/]*'
			.     '(?:'
			.         '\\/(?!\\])'
			.         '[^\\]\\/]*'
			.     ')*?'
			. ')'
			. '(?:'
			.     '(\\/)'
			.     '\\]'
			. '|'
			.     '\\]'
			.     '(?:'
			.         '('
			.             '[^\\[]*+'
			.             '(?:'
			.                 '\\[(?!\\/\\2\\])'
			.                 '[^\\[]*+'
			.             ')*+'
			.         ')'
			.         '\\[\\/\\2\\]'
			.     ')?'
			. ')'
			. '(\\]?)';
	}

	/**
	 * @param string $text Attribute string from the shortcode regex.
	 * @return array<string, string>
	 */
	private static function shortcode_attributes( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return array();
		}
		if ( function_exists( 'shortcode_parse_atts' ) ) {
			$parsed = shortcode_parse_atts( $text );
			if ( ! is_array( $parsed ) ) {
				return array();
			}
			$atts = array();
			foreach ( $parsed as $key => $value ) {
				if ( ! is_string( $key ) ) {
					continue;
				}
				$atts[ strtolower( $key ) ] = is_scalar( $value ) ? (string) $value : '';
			}
			return $atts;
		}
		$atts = array();
		if ( preg_match_all( '/([\w-]+)\s*=\s*"([^"]*)"/', $text, $double, PREG_SET_ORDER ) ) {
			foreach ( $double as $match ) {
				$atts[ strtolower( $match[1] ) ] = $match[2];
			}
		}
		if ( preg_match_all( "/([\\w-]+)\\s*=\\s*'([^']*)'/", $text, $single, PREG_SET_ORDER ) ) {
			foreach ( $single as $match ) {
				$key = strtolower( $match[1] );
				if ( ! isset( $atts[ $key ] ) ) {
					$atts[ $key ] = $match[2];
				}
			}
		}
		return $atts;
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $arm     a|b.
	 * @param string $html    Arm HTML, escaped with the wrapper.
	 * @return string
	 */
	private static function fallback_html( $test_id, $arm, $html ) {
		$arm = ( 'b' === $arm ) ? 'b' : 'a';
		$out = '<div class="rwgc-ab" data-rwgc-ab="' . esc_attr( (string) absint( $test_id ) ) . '" data-rwgc-arm="' . esc_attr( $arm ) . '">' . (string) $html . '</div>';
		return self::escape_block_html( $out );
	}

	/**
	 * Escape one arm. The fragment route does this again.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function escape_variant_html( $html ) {
		$html = (string) $html;
		if ( function_exists( 'rwgc_kses_geo_content_html' ) ) {
			return rwgc_kses_geo_content_html( $html );
		}
		return $html;
	}

	/**
	 * Escape the shortcode, keeping the arm markers rwgc_ab_render_test() adds.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function escape_block_html( $html ) {
		$html = (string) $html;
		if ( ! function_exists( 'rwgc_kses_geo_content_html' ) ) {
			return $html;
		}
		$allowed = function_exists( 'rwgc_geo_content_allowed_html' ) ? rwgc_geo_content_allowed_html() : array();
		if ( ! is_array( $allowed ) ) {
			$allowed = array();
		}
		if ( ! isset( $allowed['div'] ) || ! is_array( $allowed['div'] ) ) {
			$allowed['div'] = array();
		}
		$allowed['div']['class']             = true;
		$allowed['div']['data-rwgc-ab']       = true;
		$allowed['div']['data-rwgc-arm']      = true;
		$allowed['div']['data-rwgc-ab-mode']  = true;
		$allowed['div']['data-rwgc-ab-goal']  = true;
		return rwgc_kses_geo_content_html( $html, $allowed );
	}
}

