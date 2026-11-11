<?php
/**
 * Gutenberg A/B Test block.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the two-variant A/B block and keeps one rwgc_ab_test per instance.
 *
 * Front-end HTML for a running test comes from rwgc_ab_render_test(), so the
 * cookie and cache headers follow that helper. A missing or ended test does
 * not call it. Fragment mode loads one arm from the render route through
 * rwgc_ab_variant_html, which reads the block and does not call that helper.
 */
final class RWGC_Gutenberg_AB {

	const BLOCK = 'reactwoo-geocore/ab-test';

	const VARIANT = 'reactwoo-geocore/ab-variant';

	/**
	 * @var bool
	 */
	private static $updating = false;

	/**
	 * True while the fragment route is rendering one arm.
	 *
	 * render_block on a nested test must not call rwgc_ab_render_test().
	 *
	 * @var int
	 */
	private static $supplying_variant_html = 0;

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ), 30 );
		add_filter( 'render_block', array( __CLASS__, 'filter_render_block' ), 12, 2 );
		add_filter( 'rwgc_ab_variant_html', array( __CLASS__, 'filter_variant_html' ), 10, 3 );
		add_filter( 'get_the_excerpt', array( __CLASS__, 'filter_get_the_excerpt' ), 9, 2 );
		add_action( 'save_post', array( __CLASS__, 'sync_on_save' ), 25, 2 );
	}

	/**
	 * @return void
	 */
	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		foreach ( array( 'ab-variant', 'ab-test' ) as $slug ) {
			$dir = trailingslashit( RWGC_PATH ) . 'blocks/' . $slug;
			if ( ! is_dir( $dir ) || ! file_exists( $dir . '/block.json' ) ) {
				continue;
			}
			register_block_type( $dir );
		}
	}

	/**
	 * Replace the saved pair with one arm.
	 *
	 * The saved markup contains both variants. This returns only the arm from
	 * rwgc_ab_render_test(), or A / the kept arm when the test is not running.
	 *
	 * @param string               $block_content Rendered HTML of both arms.
	 * @param array<string, mixed> $block         Parsed block.
	 * @return string
	 */
	public static function filter_render_block( $block_content, $block ) {
		if ( ! is_array( $block ) || empty( $block['blockName'] ) || self::BLOCK !== $block['blockName'] ) {
			return is_string( $block_content ) ? $block_content : '';
		}

		if ( self::$supplying_variant_html > 0 ) {
			return self::escape_variant_html( self::static_variant_html( $block, 'a' ) );
		}

		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		return self::render_from_arms( $attrs, self::arms_from_block( $block ) );
	}

	/**
	 * HTML for one arm on the fragment render route.
	 *
	 * Another provider may already have filled $html. A Gutenberg test is read
	 * from its source post. This does not call rwgc_ab_render_test().
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
		if ( '' === $rendered ) {
			return is_string( $html ) ? $html : '';
		}
		return $rendered;
	}

	/**
	 * Auto excerpts use variant A only.
	 *
	 * Runs before wp_trim_excerpt (priority 10). A manual excerpt is left as
	 * saved. Plugins that read raw post_content still see both arms in the
	 * stored markup; the rendered page does not.
	 *
	 * @param mixed         $excerpt Excerpt text.
	 * @param WP_Post|mixed $post    Post.
	 * @return string
	 */
	public static function filter_get_the_excerpt( $excerpt, $post = null ) {
		$excerpt = is_string( $excerpt ) ? $excerpt : '';
		if ( '' !== trim( $excerpt ) ) {
			return $excerpt;
		}
		if ( ! $post instanceof WP_Post && is_numeric( $post ) && function_exists( 'get_post' ) ) {
			$post = get_post( (int) $post );
		}
		if ( ! $post instanceof WP_Post || false === strpos( (string) $post->post_content, self::BLOCK ) ) {
			return $excerpt;
		}
		$text = self::control_plain_text( (string) $post->post_content );
		if ( '' === $text ) {
			return $excerpt;
		}
		return $text;
	}

	/**
	 * HTML for one test. Running tests delegate to rwgc_ab_render_test().
	 *
	 * @param array<string, mixed>  $attrs Block attributes.
	 * @param array<string, string> $arms  Arm id => HTML.
	 * @return string
	 */
	public static function render_from_arms( array $attrs, array $arms ) {
		$arms    = self::normal_arms( $arms );
		$test_id = isset( $attrs['testId'] ) ? absint( $attrs['testId'] ) : 0;
		$row     = self::stored_test( $test_id );

		if ( self::row_is_running( $row ) && function_exists( 'rwgc_ab_render_test' ) ) {
			$html = rwgc_ab_render_test( $test_id, $arms );
			if ( is_string( $html ) && false !== strpos( $html, 'data-rwgc-ab=' ) ) {
				return self::escape_block_html( $html );
			}
			$fallback = is_string( $html ) && '' !== $html ? $html : $arms['a'];
			return self::fallback_html( $test_id, 'a', $fallback );
		}

		$arm  = self::row_is_ended( $row ) ? self::kept_arm( $row ) : 'a';
		$html = isset( $arms[ $arm ] ) ? $arms[ $arm ] : $arms['a'];
		return self::fallback_html( $test_id, $arm, $html );
	}

	/**
	 * Read variant HTML from the parsed block. The first A and the first B win.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return array{a:string,b:string}
	 */
	public static function arms_from_block( array $block ) {
		$arms   = array(
			'a' => '',
			'b' => '',
		);
		$extra  = '';
		$inners = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : array();
		foreach ( $inners as $inner ) {
			if ( ! is_array( $inner ) ) {
				continue;
			}
			$name = isset( $inner['blockName'] ) ? (string) $inner['blockName'] : '';
			if ( self::VARIANT !== $name ) {
				$extra .= self::render_inner_block( $inner );
				continue;
			}
			$variant = self::variant_attr( isset( $inner['attrs'] ) ? $inner['attrs'] : array() );
			if ( '' !== $arms[ $variant ] ) {
				continue;
			}
			$arms[ $variant ] = self::render_inner_block( $inner );
		}
		if ( '' === $arms['a'] && '' !== $extra ) {
			$arms['a'] = $extra;
		}
		return $arms;
	}

	/**
	 * Create or update one test for each A/B block. A duplicated post gets a new test.
	 *
	 * @param int           $post_id Post ID.
	 * @param WP_Post|null  $post    Post.
	 * @return void
	 */
	public static function sync_on_save( $post_id, $post = null ) {
		if ( self::$updating || ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) ) {
			return;
		}
		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! $post instanceof WP_Post ) {
			$post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		}
		if ( ! $post instanceof WP_Post || 'trash' === $post->post_status ) {
			return;
		}
		$content = (string) $post->post_content;
		if ( ! function_exists( 'has_blocks' ) || ! has_blocks( $content ) || false === strpos( $content, self::BLOCK ) ) {
			return;
		}
		if ( ! function_exists( 'parse_blocks' ) || ! function_exists( 'serialize_blocks' ) ) {
			return;
		}

		$blocks  = parse_blocks( $content );
		if ( ! is_array( $blocks ) ) {
			return;
		}
		$changed = false;
		$blocks  = self::sync_blocks( $blocks, (int) $post_id, $changed );
		if ( ! $changed ) {
			return;
		}

		$serialized     = serialize_blocks( $blocks );
		self::$updating = true;
		wp_update_post(
			array(
				'ID'           => (int) $post_id,
				'post_content' => $serialized,
			)
		);
		self::$updating = false;
	}

	/**
	 * Sync every A/B block in a parsed document.
	 *
	 * @param array<int, array<string, mixed>> $blocks  Parsed blocks.
	 * @param int                               $post_id Post ID.
	 * @param bool                              $changed Set when attributes change.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sync_blocks( array $blocks, $post_id, &$changed = false ) {
		$seen_instances = array();
		$seen_tests     = array();
		return self::walk_blocks( $blocks, absint( $post_id ), $changed, $seen_instances, $seen_tests );
	}

	/**
	 * Sync one block against the test store.
	 *
	 * A copy is a new test when this post does not own the stored test, or when
	 * the same instance or test id already appeared in this document.
	 *
	 * @param array<string, mixed> $attrs             Block attributes.
	 * @param int                  $post_id           Post that contains the block.
	 * @param array<string, bool>  $seen_instances    Instance ids already used.
	 * @param array<int, bool>     $seen_test_ids     Test ids already used.
	 * @return array{attrs: array<string, mixed>, changed: bool, created: bool}
	 */
	public static function sync_attributes( array $attrs, $post_id = 0, array &$seen_instances = array(), array &$seen_test_ids = array() ) {
		$post_id         = absint( $post_id );
		$before_instance = self::sanitize_instance_id( isset( $attrs['instanceId'] ) ? $attrs['instanceId'] : '' );
		$before_test     = isset( $attrs['testId'] ) ? absint( $attrs['testId'] ) : 0;
		$before          = $attrs;

		$instance = $before_instance;
		$test_id  = $before_test;
		$fresh    = ( '' === $instance )
			|| isset( $seen_instances[ $instance ] )
			|| ( $test_id > 0 && isset( $seen_test_ids[ $test_id ] ) );

		if ( ! $fresh && $test_id > 0 ) {
			$row = self::stored_test( $test_id );
			if ( ! is_array( $row ) ) {
				$test_id = 0;
			} else {
				$source = isset( $row['config']['source_post_id'] ) ? absint( $row['config']['source_post_id'] ) : 0;
				$bound  = isset( $row['config']['instance_id'] ) ? self::sanitize_instance_id( $row['config']['instance_id'] ) : '';
				if ( ( $source > 0 && $source !== $post_id ) || ( '' !== $bound && $bound !== $instance ) ) {
					$fresh = true;
				}
			}
		}

		if ( $fresh ) {
			do {
				$instance = self::generate_instance_id();
			} while ( isset( $seen_instances[ $instance ] ) );
			$test_id = 0;
		}
		$seen_instances[ $instance ] = true;

		$config  = self::config_from_attrs( $attrs, $post_id, $instance );
		$created = false;
		$title   = isset( $attrs['testName'] ) ? $attrs['testName'] : '';

		if ( $test_id > 0 && is_array( self::stored_test( $test_id ) ) ) {
			$existing = self::stored_test( $test_id );
			$update   = array(
				'title'  => $title,
				'config' => $config,
			);
			if ( ! self::row_is_ended( $existing ) ) {
				$update['status'] = 'publish';
			}
			$saved = class_exists( 'RWGC_AB_Tests', false ) ? RWGC_AB_Tests::update( $test_id, $update ) : 0;
			if ( $saved <= 0 ) {
				$test_id = 0;
			}
		} else {
			$test_id = 0;
		}

		if ( $test_id <= 0 && class_exists( 'RWGC_AB_Tests', false ) ) {
			$test_id = RWGC_AB_Tests::create(
				array(
					'title'  => $title,
					'status' => 'publish',
					'config' => $config,
				)
			);
			$created = $test_id > 0;
		}

		if ( $test_id > 0 ) {
			$seen_test_ids[ $test_id ] = true;
		}

		$attrs['instanceId'] = $instance;
		$attrs['testId']     = (int) $test_id;
		$stored              = self::stored_test( $test_id );
		if ( is_array( $stored ) ) {
			$goal                    = isset( $stored['config']['goal'] ) && is_array( $stored['config']['goal'] ) ? $stored['config']['goal'] : array();
			$attrs['testName']       = (string) $stored['title'];
			$attrs['goalType']       = ( isset( $goal['type'] ) && 'url' === $goal['type'] ) ? 'url' : 'click';
			$attrs['goalSelector']   = isset( $goal['selector'] ) ? (string) $goal['selector'] : '';
			$attrs['goalUrl']        = isset( $goal['url'] ) ? (string) $goal['url'] : '';
		}

		return array(
			'attrs'   => $attrs,
			'changed' => $created || self::sync_attrs_changed( $before, $attrs ),
			'created' => $created,
		);
	}

	/**
	 * @return string
	 */
	public static function generate_instance_id() {
		$seed = function_exists( 'wp_rand' ) ? (string) wp_rand() : (string) mt_rand( 0, PHP_INT_MAX );
		return 'g_' . substr( md5( uniqid( $seed, true ) ), 0, 12 );
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks         Blocks.
	 * @param int                               $post_id        Post ID.
	 * @param bool                              $changed        Changed flag.
	 * @param array<string, bool>               $seen_instances Seen instances.
	 * @param array<int, bool>                  $seen_tests     Seen tests.
	 * @return array<int, array<string, mixed>>
	 */
	private static function walk_blocks( array $blocks, $post_id, &$changed, array &$seen_instances, array &$seen_tests ) {
		foreach ( $blocks as $i => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( isset( $block['blockName'] ) && self::BLOCK === $block['blockName'] ) {
				$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
				$sync  = self::sync_attributes( $attrs, $post_id, $seen_instances, $seen_tests );
				if ( ! empty( $sync['changed'] ) ) {
					$changed = true;
				}
				$block['attrs'] = $sync['attrs'];
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::walk_blocks( $block['innerBlocks'], $post_id, $changed, $seen_instances, $seen_tests );
			}
			$blocks[ $i ] = $block;
		}
		return $blocks;
	}

	/**
	 * @param array<string, mixed> $attrs    Block attributes.
	 * @param int                  $post_id  Source post.
	 * @param string               $instance Instance id.
	 * @return array<string, mixed>
	 */
	private static function config_from_attrs( array $attrs, $post_id, $instance ) {
		$type = isset( $attrs['goalType'] ) ? sanitize_key( (string) $attrs['goalType'] ) : 'click';
		if ( 'url' !== $type ) {
			$type = 'click';
		}
		return array(
			'version'        => 1,
			'type'           => 'element',
			'adapter'        => 'gutenberg',
			'source_post_id' => absint( $post_id ),
			'instance_id'    => self::sanitize_instance_id( $instance ),
			'control'        => 'a',
			'variants'       => array(
				array(
					'id'         => 'a',
					'allocation' => 50,
				),
				array(
					'id'         => 'b',
					'allocation' => 50,
				),
			),
			'goal'           => array(
				'type'     => $type,
				'selector' => isset( $attrs['goalSelector'] ) && is_scalar( $attrs['goalSelector'] ) ? (string) $attrs['goalSelector'] : '',
				'url'      => isset( $attrs['goalUrl'] ) && is_scalar( $attrs['goalUrl'] ) ? (string) $attrs['goalUrl'] : '',
			),
		);
	}

	/**
	 * @param array<string, mixed> $before Attributes before sync.
	 * @param array<string, mixed> $after  Attributes after sync.
	 * @return bool
	 */
	private static function sync_attrs_changed( array $before, array $after ) {
		$before_id = isset( $before['testId'] ) ? absint( $before['testId'] ) : 0;
		$after_id  = isset( $after['testId'] ) ? absint( $after['testId'] ) : 0;
		if ( $before_id !== $after_id ) {
			return true;
		}
		$keys = array( 'instanceId', 'testName', 'goalType', 'goalSelector', 'goalUrl' );
		foreach ( $keys as $key ) {
			$left  = isset( $before[ $key ] ) && is_scalar( $before[ $key ] ) ? (string) $before[ $key ] : '';
			$right = isset( $after[ $key ] ) && is_scalar( $after[ $key ] ) ? (string) $after[ $key ] : '';
			if ( $left !== $right ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param int $test_id Test id.
	 * @return array{id:int,title:string,status:string,config:array<string,mixed>}|null
	 */
	private static function stored_test( $test_id ) {
		$test_id = absint( $test_id );
		if ( $test_id <= 0 || ! class_exists( 'RWGC_AB_Tests', false ) ) {
			return null;
		}
		$row = RWGC_AB_Tests::get( $test_id );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string, mixed>|null $row Test row.
	 * @return bool
	 */
	private static function row_is_running( $row ) {
		return is_array( $row ) && 'publish' === (string) ( isset( $row['status'] ) ? $row['status'] : '' );
	}

	/**
	 * @param array<string, mixed>|null $row Test row.
	 * @return bool
	 */
	private static function row_is_ended( $row ) {
		return is_array( $row )
			&& class_exists( 'RWGC_AB_Tests', false )
			&& RWGC_AB_Tests::STATUS_ENDED === (string) ( isset( $row['status'] ) ? $row['status'] : '' );
	}

	/**
	 * @param array<string, mixed>|null $row Ended test.
	 * @return string a|b
	 */
	private static function kept_arm( $row ) {
		$kept = '';
		if ( is_array( $row ) && isset( $row['config']['kept'] ) ) {
			$kept = sanitize_key( (string) $row['config']['kept'] );
		}
		return ( 'a' === $kept || 'b' === $kept ) ? $kept : 'a';
	}

	/**
	 * @param mixed $attrs Variant attributes.
	 * @return string a|b
	 */
	private static function variant_attr( $attrs ) {
		$variant = 'a';
		if ( is_array( $attrs ) && isset( $attrs['variant'] ) ) {
			$variant = sanitize_key( (string) $attrs['variant'] );
		}
		return 'b' === $variant ? 'b' : 'a';
	}

	/**
	 * @param array<string, string> $arms Proposed arms.
	 * @return array{a:string,b:string}
	 */
	private static function normal_arms( array $arms ) {
		return array(
			'a' => isset( $arms['a'] ) && is_string( $arms['a'] ) ? $arms['a'] : '',
			'b' => isset( $arms['b'] ) && is_string( $arms['b'] ) ? $arms['b'] : '',
		);
	}

	/**
	 * @param array<string, mixed> $block Parsed inner block.
	 * @return string
	 */
	private static function render_inner_block( array $block ) {
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		if ( self::$supplying_variant_html > 0 && self::BLOCK === $name ) {
			return self::escape_variant_html( self::static_variant_html( $block, 'a' ) );
		}
		if ( function_exists( 'render_block' ) ) {
			$html = render_block( $block );
			return is_string( $html ) ? $html : '';
		}
		return self::static_block_html( $block );
	}

	/**
	 * Inner HTML of one saved arm, without rendering the test block.
	 *
	 * @param int|string $test_id Test post ID.
	 * @param string     $arm     a|b.
	 * @return string
	 */
	private static function variant_html_for_test( $test_id, $arm ) {
		$row = self::stored_test( $test_id );
		if ( ! is_array( $row ) ) {
			return '';
		}
		$config  = isset( $row['config'] ) && is_array( $row['config'] ) ? $row['config'] : array();
		$adapter = isset( $config['adapter'] ) ? sanitize_key( (string) $config['adapter'] ) : '';
		if ( '' !== $adapter && 'gutenberg' !== $adapter ) {
			return '';
		}
		$post_id  = isset( $config['source_post_id'] ) ? absint( $config['source_post_id'] ) : 0;
		$instance = self::sanitize_instance_id( isset( $config['instance_id'] ) ? $config['instance_id'] : '' );
		if ( $post_id <= 0 || '' === $instance || ! function_exists( 'get_post' ) || ! function_exists( 'parse_blocks' ) ) {
			return '';
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}
		$blocks = parse_blocks( (string) $post->post_content );
		if ( ! is_array( $blocks ) ) {
			return '';
		}
		$block = self::find_test_block( $blocks, $instance );
		if ( ! is_array( $block ) ) {
			return '';
		}
		$arm      = ( 'b' === sanitize_key( (string) $arm ) ) ? 'b' : 'a';
		$children = self::variant_children( $block, $arm );
		if ( ! is_array( $children ) ) {
			return '';
		}
		return self::render_variant_children( $children );
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks   Parsed blocks.
	 * @param string                           $instance Instance id.
	 * @return array<string, mixed>|null
	 */
	private static function find_test_block( array $blocks, $instance ) {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( isset( $block['blockName'] ) && self::BLOCK === $block['blockName'] ) {
				$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
				$found = self::sanitize_instance_id( isset( $attrs['instanceId'] ) ? $attrs['instanceId'] : '' );
				if ( $found === $instance ) {
					return $block;
				}
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$nested = self::find_test_block( $block['innerBlocks'], $instance );
				if ( is_array( $nested ) ) {
					return $nested;
				}
			}
		}
		return null;
	}

	/**
	 * Inner blocks of the requested variant, or null when that variant is absent.
	 *
	 * @param array<string, mixed> $block Parsed test block.
	 * @param string               $arm   a|b.
	 * @return array<int, array<string, mixed>>|null
	 */
	private static function variant_children( array $block, $arm ) {
		$inners = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : array();
		foreach ( $inners as $inner ) {
			if ( ! is_array( $inner ) ) {
				continue;
			}
			$name = isset( $inner['blockName'] ) ? (string) $inner['blockName'] : '';
			if ( self::VARIANT !== $name ) {
				continue;
			}
			$attrs = isset( $inner['attrs'] ) && is_array( $inner['attrs'] ) ? $inner['attrs'] : array();
			if ( self::variant_attr( $attrs ) !== $arm ) {
				continue;
			}
			$children = isset( $inner['innerBlocks'] ) && is_array( $inner['innerBlocks'] ) ? $inner['innerBlocks'] : array();
			return $children;
		}
		return null;
	}

	/**
	 * Render one variant's inner blocks. The test block itself is not rendered.
	 *
	 * @param array<int, mixed> $children Inner blocks.
	 * @return string
	 */
	private static function render_variant_children( array $children ) {
		$html = '';
		++self::$supplying_variant_html;
		try {
			if ( array() === $children ) {
				return '';
			}
			foreach ( $children as $child ) {
				if ( is_array( $child ) ) {
					$html .= self::render_inner_block( $child );
				}
			}
		} finally {
			--self::$supplying_variant_html;
		}
		return self::escape_variant_html( $html );
	}

	/**
	 * Saved HTML of one arm, without render_block.
	 *
	 * @param array<string, mixed> $block Parsed test block.
	 * @param string               $arm   a|b.
	 * @return string
	 */
	private static function static_variant_html( array $block, $arm ) {
		$children = self::variant_children( $block, ( 'b' === $arm ) ? 'b' : 'a' );
		if ( ! is_array( $children ) ) {
			return '';
		}
		$html = '';
		foreach ( $children as $child ) {
			if ( is_array( $child ) ) {
				$html .= self::static_block_html( $child );
			}
		}
		return $html;
	}

	/**
	 * Plain text for an auto excerpt: normal blocks, plus variant A only.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	private static function control_plain_text( $content ) {
		if ( ! function_exists( 'parse_blocks' ) ) {
			return '';
		}
		$blocks = parse_blocks( (string) $content );
		if ( ! is_array( $blocks ) ) {
			return '';
		}
		return self::trim_excerpt_words( self::plain_text_from_blocks( $blocks ) );
	}

	/**
	 * @param array<int, mixed> $blocks Parsed blocks.
	 * @return string
	 */
	private static function plain_text_from_blocks( array $blocks ) {
		$parts = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			if ( self::BLOCK === $name ) {
				$parts[] = self::plain_line( self::static_variant_html( $block, 'a' ) );
				continue;
			}
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			if ( self::VARIANT === $name && 'b' === self::variant_attr( $attrs ) ) {
				continue;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$parts[] = self::plain_text_from_blocks( $block['innerBlocks'] );
				continue;
			}
			if ( isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
				$parts[] = self::plain_line( $block['innerHTML'] );
			}
		}
		$text = trim( (string) preg_replace( '/\s+/', ' ', implode( ' ', $parts ) ) );
		return $text;
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	private static function plain_line( $html ) {
		$html = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $html ) : strip_tags( (string) $html );
		return trim( (string) preg_replace( '/\s+/', ' ', $html ) );
	}

	/**
	 * @param string $text Plain text.
	 * @return string
	 */
	private static function trim_excerpt_words( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}
		$length = 55;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'excerpt_length', 55 );
			if ( is_numeric( $filtered ) && (int) $filtered > 0 ) {
				$length = (int) $filtered;
			}
		}
		if ( function_exists( 'wp_trim_words' ) ) {
			return wp_trim_words( $text, $length, ' [&hellip;]' );
		}
		$words = preg_split( '/\s+/', $text );
		if ( ! is_array( $words ) || count( $words ) <= $length ) {
			return $text;
		}
		return implode( ' ', array_slice( $words, 0, $length ) ) . ' [&hellip;]';
	}

	/**
	 * Inner HTML used when WordPress block rendering is not loaded (tests).
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return string
	 */
	private static function static_block_html( array $block ) {
		$nested = '';
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner ) {
				if ( is_array( $inner ) ) {
					$nested .= self::static_block_html( $inner );
				}
			}
		}
		if ( '' !== $nested ) {
			return $nested;
		}
		return isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ? $block['innerHTML'] : '';
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
	 * Escape the block, keeping the arm markers rwgc_ab_render_test() adds.
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
		$allowed['div']['data-rwgc-ab']      = true;
		$allowed['div']['data-rwgc-arm']     = true;
		$allowed['div']['data-rwgc-ab-mode'] = true;
		return rwgc_kses_geo_content_html( $html, $allowed );
	}

	/**
	 * @param mixed $value Proposed instance id.
	 * @return string
	 */
	private static function sanitize_instance_id( $value ) {
		$instance = strtolower( is_scalar( $value ) ? (string) $value : '' );
		$instance = (string) preg_replace( '/[^a-z0-9_\-]/', '', $instance );
		if ( strlen( $instance ) > 64 ) {
			$instance = substr( $instance, 0, 64 );
		}
		return $instance;
	}
}
