<?php
/**
 * Gutenberg Experience Slot block (WP7).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers reactwoo/experience-slot and syncs the Core slot registry.
 */
final class RWGC_Gutenberg_Experience_Slot {

	const BLOCK = 'reactwoo/experience-slot';

	/** @var bool */
	private static $updating = false;

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ), 30 );
		add_filter( 'render_block', array( __CLASS__, 'filter_render_block' ), 12, 2 );
		add_action( 'save_post', array( __CLASS__, 'sync_on_save' ), 25, 2 );
	}

	/**
	 * @return void
	 */
	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		$dir = trailingslashit( RWGC_PATH ) . 'blocks/experience-slot';
		if ( ! is_dir( $dir ) || ! file_exists( $dir . '/block.json' ) ) {
			return;
		}
		register_block_type( $dir );
	}

	/**
	 * Gate B: pass rendered InnerBlocks through the Experience Slot renderer.
	 *
	 * @param string               $block_content HTML.
	 * @param array<string, mixed> $block Block.
	 * @return string
	 */
	public static function filter_render_block( $block_content, $block ) {
		if ( ! is_array( $block ) || empty( $block['blockName'] ) || self::BLOCK !== $block['blockName'] ) {
			return $block_content;
		}

		$attrs   = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$slot_id = isset( $attrs['slotId'] ) ? (string) $attrs['slotId'] : '';
		if ( '' === $slot_id ) {
			return is_string( $block_content ) ? $block_content : '';
		}

		/**
		 * Optional Decision Runtime result for the current request.
		 *
		 * @param RWGC_Decision_Result|null $decision Decision.
		 */
		$decision = apply_filters( 'reactwoo_current_decision_result', null );
		if ( ! ( $decision instanceof RWGC_Decision_Result ) ) {
			$decision = null;
		}

		$default = is_string( $block_content ) ? $block_content : '';
		return reactwoo_render_experience_slot( $slot_id, $default, $decision );
	}

	/**
	 * Sync slot IDs into post content after save (clone-safe).
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function sync_on_save( $post_id, $post = null ) {
		if ( self::$updating || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post_id );
		}
		if ( ! $post instanceof WP_Post || 'trash' === $post->post_status ) {
			return;
		}
		if ( ! has_blocks( $post->post_content ) || false === strpos( $post->post_content, 'reactwoo/experience-slot' ) ) {
			return;
		}

		$page    = self::page_reference_for_post( (int) $post_id );
		$blocks  = parse_blocks( $post->post_content );
		$changed = false;
		$seen    = array();
		$blocks  = self::walk_blocks( $blocks, $page, $changed, $seen );
		if ( ! $changed ) {
			return;
		}

		$serialized = serialize_blocks( $blocks );
		self::$updating = true;
		// wp_update_post() expects slashed data; serialize_blocks() is unslashed
		// JSON. Without wp_slash(), wp_insert_post()'s wp_unslash() strips \" from
		// sibling block attributes (e.g. image alt) on the first Slot ID write.
		wp_update_post(
			array(
				'ID'           => (int) $post_id,
				'post_content' => wp_slash( $serialized ),
			)
		);
		self::$updating = false;
	}

	/**
	 * Sync one block's attributes against the registry (public for tests).
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @param string               $page Page reference.
	 * @param array<string, bool>  $seen_instance_ids Instance IDs already used in this document.
	 * @return array{attrs: array<string, mixed>, regenerated: bool, changed: bool}
	 */
	public static function sync_attributes( array $attrs, $page = '/', array &$seen_instance_ids = array() ) {
		$before_instance = isset( $attrs['instanceId'] ) ? (string) $attrs['instanceId'] : '';
		$before_slot     = isset( $attrs['slotId'] ) ? trim( (string) $attrs['slotId'] ) : '';
		$before_name     = isset( $attrs['slotName'] ) ? trim( (string) $attrs['slotName'] ) : '';

		$instance = $before_instance;
		if ( '' === $instance || isset( $seen_instance_ids[ $instance ] ) ) {
			$instance = self::generate_instance_id();
		}
		$seen_instance_ids[ $instance ] = true;

		$name = $before_name;
		if ( '' === $name ) {
			$name = 'Experience Slot';
		}

		$mode = isset( $attrs['managementMode'] ) ? (string) $attrs['managementMode'] : 'local';
		if ( ! in_array( $mode, array( 'local', 'managed' ), true ) ) {
			$mode = 'local';
		}

		$binding = 'gutenberg:' . $instance;

		$result = reactwoo_register_experience_slot(
			array(
				'id'            => $before_slot,
				'name'          => $name,
				'adapter'       => 'gutenberg',
				'page'          => (string) $page,
				'variant_types' => array( 'content', 'reactwoo_component', 'native_reference' ),
				'status'        => 'active',
				'metadata'      => array(
					'binding_key'  => $binding,
					'cloud_status' => $mode,
					'instance_id'  => $instance,
				),
			)
		);

		$regenerated = false;
		$new_id      = $before_slot;
		if ( is_array( $result ) && isset( $result['slot'] ) ) {
			$new_id      = $result['slot']->id();
			$regenerated = ! empty( $result['regenerated'] );
		} elseif ( '' === $new_id || ! RWGC_Experience_Slot_Id::is_valid( $new_id ) ) {
			$new_id      = RWGC_Experience_Slot_Id::generate( $name );
			$regenerated = true;
		}

		$attrs['slotId']          = $new_id;
		$attrs['instanceId']      = $instance;
		$attrs['slotName']        = $name;
		$attrs['managementMode']  = $mode;

		$changed = $regenerated
			|| $new_id !== $before_slot
			|| $instance !== $before_instance
			|| $name !== $before_name;

		return array(
			'attrs'       => $attrs,
			'regenerated' => $regenerated,
			'changed'     => $changed,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @param string                           $page Page.
	 * @param bool                             $changed Changed flag.
	 * @param array<string, bool>              $seen Seen instance IDs.
	 * @return array<int, array<string, mixed>>
	 */
	private static function walk_blocks( array $blocks, $page, &$changed, array &$seen ) {
		foreach ( $blocks as $i => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( isset( $block['blockName'] ) && self::BLOCK === $block['blockName'] ) {
				$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
				$sync  = self::sync_attributes( $attrs, $page, $seen );
				if ( $sync['changed'] || $sync['regenerated'] ) {
					$changed = true;
				}
				$block['attrs'] = $sync['attrs'];
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::walk_blocks( $block['innerBlocks'], $page, $changed, $seen );
			}
			$blocks[ $i ] = $block;
		}
		return $blocks;
	}

	/**
	 * @return string
	 */
	public static function generate_instance_id() {
		return 'g_' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 12 );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function page_reference_for_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id > 0 && function_exists( 'get_permalink' ) ) {
			$link = get_permalink( $post_id );
			if ( is_string( $link ) && '' !== $link ) {
				$path = wp_parse_url( $link, PHP_URL_PATH );
				return is_string( $path ) && '' !== $path ? $path : '/';
			}
		}
		return '/';
	}
}
