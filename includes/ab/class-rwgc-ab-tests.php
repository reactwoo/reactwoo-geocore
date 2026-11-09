<?php
/**
 * Free A/B test store (CPT plus repository).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Non-public rwgc_ab_test posts. Config in _rwgc_ab matches RWGC_Contract_Experiment.
 */
class RWGC_AB_Tests {

	const POST_TYPE = 'rwgc_ab_test';

	const META_KEY = '_rwgc_ab';

	const STATUS_ENDED = 'rwgc_ab_ended';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 8 );
		add_action( 'init', array( __CLASS__, 'register_meta' ), 9 );
	}

	/**
	 * @return void
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'A/B tests', 'reactwoo-geocore' ),
					'singular_name' => __( 'A/B test', 'reactwoo-geocore' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);

		register_post_status(
			self::STATUS_ENDED,
			array(
				'label'                     => __( 'Ended', 'reactwoo-geocore' ),
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
				'label_count'               => _n_noop(
					'Ended <span class="count">(%s)</span>',
					'Ended <span class="count">(%s)</span>',
					'reactwoo-geocore'
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			self::META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'auth_callback'     => static function () {
					return current_user_can( 'manage_options' );
				},
				'sanitize_callback' => array( __CLASS__, 'sanitize_stored_json' ),
			)
		);
	}

	/**
	 * Accept a JSON object and leave its fields intact.
	 *
	 * Field sanitizing happens in the repository, which knows the post ID.
	 *
	 * @param mixed $value Raw meta.
	 * @return string
	 */
	public static function sanitize_stored_json( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}
		$decoded = json_decode( $value, true );
		if ( ! is_array( $decoded ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Human label for a stored post_status.
	 *
	 * @param string $status Post status.
	 * @return string
	 */
	public static function status_label( $status ) {
		switch ( (string) $status ) {
			case 'publish':
				return __( 'Running', 'reactwoo-geocore' );
			case self::STATUS_ENDED:
				return __( 'Ended', 'reactwoo-geocore' );
			case 'draft':
			default:
				return __( 'Draft', 'reactwoo-geocore' );
		}
	}

	/**
	 * Insert a test.
	 *
	 * @param array<string, mixed> $args title, status (draft|publish|rwgc_ab_ended), config.
	 * @return int Post ID, or 0 on failure.
	 */
	public static function create( array $args ) {
		self::ensure_registered();
		$title  = self::sanitize_title( isset( $args['title'] ) ? $args['title'] : '' );
		$status = self::sanitize_status( isset( $args['status'] ) ? $args['status'] : 'draft' );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $title,
				'post_status' => $status,
			),
			true
		);
		if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $post_id ) ) || ! $post_id ) {
			return 0;
		}
		$post_id = (int) $post_id;

		$config = self::sanitize_config( isset( $args['config'] ) ? $args['config'] : array(), $post_id );
		if ( 'publish' === $status && '' === $config['started_at'] ) {
			$config['started_at'] = self::now_mysql();
		}
		self::write_config( $post_id, $config );

		return $post_id;
	}

	/**
	 * Update title, status, and/or config. Unknown config keys are kept.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $args    title, status, config.
	 * @return int Post ID, or 0 on failure.
	 */
	public static function update( $post_id, array $args ) {
		self::ensure_registered();
		$post = self::get_post( $post_id );
		if ( ! $post ) {
			return 0;
		}
		$post_id = (int) $post->ID;
		$title   = array_key_exists( 'title', $args ) ? self::sanitize_title( $args['title'] ) : (string) $post->post_title;
		$status  = array_key_exists( 'status', $args ) ? self::sanitize_status( $args['status'] ) : (string) $post->post_status;

		$raw = self::read_config( $post_id );
		if ( isset( $args['config'] ) && is_array( $args['config'] ) ) {
			$raw = array_merge( $raw, $args['config'] );
		}
		$config = self::sanitize_config( $raw, $post_id );
		if ( 'publish' === $status && '' === $config['started_at'] ) {
			$config['started_at'] = self::now_mysql();
		}

		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_title'  => $title,
				'post_status' => $status,
			),
			true
		);
		if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) || ! $result ) {
			return 0;
		}
		self::write_config( $post_id, $config );
		return $post_id;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array{id:int,title:string,status:string,config:array<string,mixed>}|null
	 */
	public static function get( $post_id ) {
		$post = self::get_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		$post_id = (int) $post->ID;
		return array(
			'id'     => $post_id,
			'title'  => (string) $post->post_title,
			'status' => (string) $post->post_status,
			'config' => self::sanitize_config( self::read_config( $post_id ), $post_id ),
		);
	}

	/**
	 * Newest tests first. Includes draft, running, and ended.
	 *
	 * @return array<int, array{id:int,title:string,status:string,config:array<string,mixed>}>
	 */
	public static function query() {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => array( 'draft', 'publish', self::STATUS_ENDED ),
				'posts_per_page'   => -1,
				'orderby'          => 'ID',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);
		if ( ! is_array( $posts ) ) {
			return array();
		}
		$rows = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$row = self::get( (int) $post->ID );
			if ( null !== $row ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	/**
	 * Stop a test. Optional $kept must be one of the stored variant ids.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $kept    Variant id to keep, or empty.
	 * @return bool
	 */
	public static function end( $post_id, $kept = '' ) {
		$row = self::get( $post_id );
		if ( null === $row ) {
			return false;
		}
		$config = $row['config'];
		$kept   = self::sanitize_variant_id( $kept );
		$ids    = array();
		foreach ( $config['variants'] as $variant ) {
			if ( isset( $variant['id'] ) ) {
				$ids[] = (string) $variant['id'];
			}
		}
		if ( '' !== $kept && ! in_array( $kept, $ids, true ) ) {
			$kept = '';
		}
		if ( self::STATUS_ENDED !== $row['status'] ) {
			$config['ended_at'] = self::now_mysql();
		}
		if ( '' !== $kept ) {
			$config['kept'] = $kept;
		}
		return self::update(
			(int) $row['id'],
			array(
				'status' => self::STATUS_ENDED,
				'config' => $config,
			)
		) > 0;
	}

	/**
	 * Permanently delete one test. Refuses any other post type.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function delete( $post_id ) {
		$post = self::get_post( $post_id );
		if ( ! $post || ! function_exists( 'wp_delete_post' ) ) {
			return false;
		}
		$post_id = (int) $post->ID;
		$result  = wp_delete_post( $post_id, true );
		if ( false === $result || null === $result ) {
			return false;
		}
		if ( class_exists( 'RWGC_AB_DB', false ) ) {
			RWGC_AB_DB::delete_for_test( $post_id );
		}
		return true;
	}

	/**
	 * Build a document RWGC_Contract_Experiment::from_array() can read.
	 *
	 * @param mixed $raw     Proposed config.
	 * @param int   $post_id Owning post ID. Written into id.
	 * @return array<string, mixed>
	 */
	public static function sanitize_config( $raw, $post_id = 0 ) {
		$raw     = is_array( $raw ) ? $raw : array();
		$post_id = absint( $post_id );

		$variants = self::sanitize_variants( isset( $raw['variants'] ) ? $raw['variants'] : array() );
		$ids      = array();
		foreach ( $variants as $variant ) {
			$ids[] = $variant['id'];
		}
		$control = self::sanitize_variant_id( isset( $raw['control'] ) ? $raw['control'] : 'a' );
		if ( ! in_array( $control, $ids, true ) ) {
			$control = 'a';
		}

		$goal_raw  = isset( $raw['goal'] ) && is_array( $raw['goal'] ) ? $raw['goal'] : array();
		$goal_type = sanitize_key( isset( $goal_raw['type'] ) ? (string) $goal_raw['type'] : 'click' );
		if ( '' === $goal_type ) {
			$goal_type = 'click';
		}
		if ( strlen( $goal_type ) > 32 ) {
			$goal_type = substr( $goal_type, 0, 32 );
		}

		$adapter = sanitize_key( isset( $raw['adapter'] ) ? (string) $raw['adapter'] : 'gutenberg' );
		if ( '' === $adapter ) {
			$adapter = 'gutenberg';
		}
		if ( strlen( $adapter ) > 32 ) {
			$adapter = substr( $adapter, 0, 32 );
		}

		$type = sanitize_key( isset( $raw['type'] ) ? (string) $raw['type'] : 'element' );
		if ( '' === $type ) {
			$type = 'element';
		}
		if ( strlen( $type ) > 32 ) {
			$type = substr( $type, 0, 32 );
		}

		$version = isset( $raw['version'] ) ? absint( $raw['version'] ) : 1;
		if ( $version < 1 ) {
			$version = 1;
		}

		$instance = isset( $raw['instance_id'] ) ? strtolower( (string) $raw['instance_id'] ) : '';
		$instance = (string) preg_replace( '/[^a-z0-9_\-]/', '', $instance );
		if ( strlen( $instance ) > 64 ) {
			$instance = substr( $instance, 0, 64 );
		}

		$kept = self::sanitize_variant_id( isset( $raw['kept'] ) ? $raw['kept'] : '' );
		if ( '' !== $kept && ! in_array( $kept, $ids, true ) ) {
			$kept = '';
		}

		$config = array(
			'version'        => $version,
			'id'             => $post_id > 0 ? (string) $post_id : '',
			'type'           => $type,
			'adapter'        => $adapter,
			'source_post_id' => isset( $raw['source_post_id'] ) ? absint( $raw['source_post_id'] ) : 0,
			'instance_id'    => $instance,
			'control'        => $control,
			'variants'       => $variants,
			'goal'           => array(
				'type'     => $goal_type,
				'selector' => self::sanitize_line( isset( $goal_raw['selector'] ) ? $goal_raw['selector'] : '', 191 ),
				'url'      => self::sanitize_url( isset( $goal_raw['url'] ) ? $goal_raw['url'] : '' ),
			),
			'rule_id'        => isset( $raw['rule_id'] ) ? absint( $raw['rule_id'] ) : 0,
			'started_at'     => self::sanitize_datetime( isset( $raw['started_at'] ) ? $raw['started_at'] : '' ),
			'ended_at'       => self::sanitize_datetime( isset( $raw['ended_at'] ) ? $raw['ended_at'] : '' ),
			'kept'           => $kept,
		);

		$known = array_fill_keys( array_keys( $config ), true );
		foreach ( $raw as $key => $value ) {
			if ( ! is_string( $key ) || isset( $known[ $key ] ) ) {
				continue;
			}
			if ( ! preg_match( '/^[a-z][a-z0-9_]{0,40}$/', $key ) ) {
				continue;
			}
			if ( ! self::is_plain_value( $value ) ) {
				continue;
			}
			$config[ $key ] = $value;
		}

		return $config;
	}

	/**
	 * @param string $status Proposed status.
	 * @return string
	 */
	public static function sanitize_status( $status ) {
		$status = sanitize_key( (string) $status );
		if ( in_array( $status, array( 'draft', 'publish', self::STATUS_ENDED ), true ) ) {
			return $status;
		}
		return 'draft';
	}

	/**
	 * Register the CPT and ended status before a write, including WP-CLI eval.
	 *
	 * @return void
	 */
	private static function ensure_registered() {
		if ( ! function_exists( 'register_post_type' ) ) {
			return;
		}
		$type_ready   = function_exists( 'post_type_exists' ) && post_type_exists( self::POST_TYPE );
		$status_ready = function_exists( 'get_post_status_object' ) && get_post_status_object( self::STATUS_ENDED );
		if ( $type_ready && $status_ready ) {
			return;
		}
		self::register_post_type();
	}

	/**
	 * @param int $post_id Post ID.
	 * @return WP_Post|null
	 */
	private static function get_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! function_exists( 'get_post' ) ) {
			return null;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== (string) $post->post_type ) {
			return null;
		}
		return $post;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private static function read_config( $post_id ) {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return array();
		}
		$raw = get_post_meta( absint( $post_id ), self::META_KEY, true );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $config  Sanitized config.
	 * @return void
	 */
	private static function write_config( $post_id, array $config ) {
		if ( ! function_exists( 'update_post_meta' ) ) {
			return;
		}
		$json = wp_json_encode( $config );
		if ( ! is_string( $json ) || '' === $json ) {
			return;
		}
		// update_metadata() unslashes before the sanitize callback. Slash first so
		// JSON escapes (quotes, \uXXXX, backslashes) survive that pass.
		update_post_meta( absint( $post_id ), self::META_KEY, wp_slash( $json ) );
	}

	/**
	 * @param mixed $title Proposed title.
	 * @return string
	 */
	private static function sanitize_title( $title ) {
		$title = sanitize_text_field( is_scalar( $title ) ? (string) $title : '' );
		if ( '' === $title ) {
			$title = __( 'A/B test', 'reactwoo-geocore' );
		}
		return $title;
	}

	/**
	 * @param mixed $raw Variant rows.
	 * @return list<array{id:string,allocation:float}>
	 */
	private static function sanitize_variants( $raw ) {
		$ordered = array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id = self::sanitize_variant_id( isset( $row['id'] ) ? $row['id'] : '' );
				if ( '' === $id || isset( $ordered[ $id ] ) ) {
					continue;
				}
				$allocation = isset( $row['allocation'] ) ? (float) $row['allocation'] : 0.0;
				if ( $allocation < 0 ) {
					$allocation = 0.0;
				} elseif ( $allocation > 100 ) {
					$allocation = 100.0;
				}
				$ordered[ $id ] = array(
					'id'         => $id,
					'allocation' => $allocation,
				);
			}
		}
		if ( ! isset( $ordered['a'] ) ) {
			$ordered = array(
				'a' => array(
					'id'         => 'a',
					'allocation' => 50.0,
				),
			) + $ordered;
		}
		if ( ! isset( $ordered['b'] ) ) {
			$with_b = array();
			foreach ( $ordered as $id => $row ) {
				$with_b[ $id ] = $row;
				if ( 'a' === $id ) {
					$with_b['b'] = array(
						'id'         => 'b',
						'allocation' => 50.0,
					);
				}
			}
			if ( ! isset( $with_b['b'] ) ) {
				$with_b['b'] = array(
					'id'         => 'b',
					'allocation' => 50.0,
				);
			}
			$ordered = $with_b;
		}
		return array_values( $ordered );
	}

	/**
	 * @param mixed $value Variant id.
	 * @return string
	 */
	private static function sanitize_variant_id( $value ) {
		$id = sanitize_key( is_scalar( $value ) ? (string) $value : '' );
		if ( strlen( $id ) > 16 ) {
			$id = substr( $id, 0, 16 );
		}
		return $id;
	}

	/**
	 * @param mixed $value Proposed datetime.
	 * @return string MySQL datetime or empty.
	 */
	private static function sanitize_datetime( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
			return $value;
		}
		return '';
	}

	/**
	 * @param mixed $value Proposed text.
	 * @param int   $max   Maximum length.
	 * @return string
	 */
	private static function sanitize_line( $value, $max ) {
		$text = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		if ( strlen( $text ) > $max ) {
			$text = substr( $text, 0, $max );
		}
		return $text;
	}

	/**
	 * @param mixed $value Proposed URL.
	 * @return string
	 */
	private static function sanitize_url( $value ) {
		$url = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $url ) {
			return '';
		}
		if ( function_exists( 'esc_url_raw' ) ) {
			return (string) esc_url_raw( $url );
		}
		return $url;
	}

	/**
	 * @param mixed $value Extra config value.
	 * @return bool
	 */
	private static function is_plain_value( $value ) {
		if ( is_scalar( $value ) || null === $value ) {
			return true;
		}
		if ( ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $item ) {
			if ( ! self::is_plain_value( $item ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return string
	 */
	private static function now_mysql() {
		$now = function_exists( 'current_time' ) ? (string) current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );
		$now = self::sanitize_datetime( $now );
		if ( '' === $now ) {
			$now = gmdate( 'Y-m-d H:i:s' );
		}
		return $now;
	}
}
