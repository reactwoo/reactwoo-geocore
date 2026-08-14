<?php
/**
 * Explicit local → Cloud import (WP16). Pairing never flips management_mode.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detect, preview, backup, import, and switch Cloud management mode.
 */
final class RWGC_Cloud_Migration {

	const BACKUP_OPTION = 'rwgc_cloud_migration_backup';
	const STATE_OPTION  = 'rwgc_cloud_migration_state';

	/**
	 * Detect local audiences/rules/slots/variants/experiments. Never writes.
	 *
	 * @return array<string, mixed>
	 */
	public static function detect() {
		$items = array(
			'visibility_rules' => self::detect_visibility_rules(),
			'slots'            => self::detect_slots(),
			'variants'         => self::detect_variants(),
			'experiments'      => self::detect_experiments(),
			'commerce_rules'   => self::detect_commerce_rules(),
		);

		/**
		 * Replace or extend the detected inventory (tests + satellites).
		 *
		 * @param array<string, mixed> $items Inventory.
		 */
		return apply_filters( 'rwgc_cloud_migration_inventory', $items );
	}

	/**
	 * Import preview. Local config is not mutated.
	 *
	 * @param array<string, mixed>|null $inventory Optional inventory override.
	 * @return array<string, mixed>
	 */
	public static function preview( $inventory = null ) {
		$detected = is_array( $inventory ) ? $inventory : self::detect();
		$preview  = RWGC_Cloud_Migration_Translator::preview( $detected );
		$preview['detected'] = array(
			'visibility_rules' => self::count_list( $detected['visibility_rules'] ?? array() ),
			'slots'            => self::count_list( $detected['slots'] ?? array() ),
			'variants'         => self::count_list( $detected['variants'] ?? array() ),
			'experiments'      => self::count_list( $detected['experiments'] ?? array() ),
			'commerce_rules'   => self::count_list( $detected['commerce_rules'] ?? array() ),
		);
		$preview['management_mode'] = (string) RWGC_Cloud_Connection::get()['management_mode'];
		$preview['imported']        = self::is_imported();
		return $preview;
	}

	/**
	 * Store a local backup, then POST supported resources to Cloud.
	 * Does not switch management_mode.
	 *
	 * @param array<string, mixed>|null $inventory Optional inventory override.
	 * @return array{ok: bool, error: string, preview: array<string, mixed>}
	 */
	public static function import( $inventory = null ) {
		if ( ! RWGC_Cloud_Connection::is_connected() ) {
			return array(
				'ok'      => false,
				'error'   => 'not_connected',
				'preview' => array(),
			);
		}

		$detected = is_array( $inventory ) ? $inventory : self::detect();
		$preview  = RWGC_Cloud_Migration_Translator::preview( $detected );
		self::store_backup( $detected, $preview );

		$creds = RWGC_Cloud_Credentials::get();
		if ( ! $creds ) {
			return array(
				'ok'      => false,
				'error'   => 'missing_credentials',
				'preview' => $preview,
			);
		}

		$response = RWGC_Cloud_Http::request(
			'POST',
			'/sites/' . rawurlencode( (string) $creds['site_id'] ) . '/migration/import',
			array(
				'dry_run'   => false,
				'resources' => $preview['resources'],
			),
			array(),
			array(
				'site_secret' => $creds['site_secret'],
				'api_base'    => $creds['api_base'],
			)
		);

		if ( ! $response['ok'] ) {
			return array(
				'ok'      => false,
				'error'   => $response['error'] ? $response['error'] : 'import_failed',
				'preview' => $preview,
			);
		}

		update_option(
			self::STATE_OPTION,
			array(
				'imported_at' => gmdate( 'c' ),
				'site_id'     => (string) $creds['site_id'],
			),
			false
		);

		return array(
			'ok'      => true,
			'error'   => '',
			'preview' => $preview,
		);
	}

	/**
	 * Explicit switch after import. Connecting Cloud never calls this.
	 *
	 * @param string $mode local|cloud.
	 * @return array{ok: bool, error: string, management_mode: string}
	 */
	public static function switch_mode( $mode ) {
		$mode = 'cloud' === $mode ? 'cloud' : 'local';
		if ( 'cloud' === $mode && ! self::is_imported() ) {
			return array(
				'ok'              => false,
				'error'           => 'import_required',
				'management_mode' => (string) RWGC_Cloud_Connection::get()['management_mode'],
			);
		}
		if ( ! RWGC_Cloud_Connection::is_connected() ) {
			return array(
				'ok'              => false,
				'error'           => 'not_connected',
				'management_mode' => 'local',
			);
		}

		$creds = RWGC_Cloud_Credentials::get();
		if ( ! $creds ) {
			return array(
				'ok'              => false,
				'error'           => 'missing_credentials',
				'management_mode' => (string) RWGC_Cloud_Connection::get()['management_mode'],
			);
		}

		$response = RWGC_Cloud_Http::request(
			'POST',
			'/sites/' . rawurlencode( (string) $creds['site_id'] ) . '/management-mode',
			array( 'mode' => $mode ),
			array(),
			array(
				'site_secret' => $creds['site_secret'],
				'api_base'    => $creds['api_base'],
			)
		);
		if ( ! $response['ok'] ) {
			return array(
				'ok'              => false,
				'error'           => $response['error'] ? $response['error'] : 'mode_switch_failed',
				'management_mode' => (string) RWGC_Cloud_Connection::get()['management_mode'],
			);
		}

		RWGC_Cloud_Connection::update( array( 'management_mode' => $mode ) );
		return array(
			'ok'              => true,
			'error'           => '',
			'management_mode' => $mode,
		);
	}

	/**
	 * @return bool
	 */
	public static function is_imported() {
		$state = get_option( self::STATE_OPTION, array() );
		return is_array( $state ) && ! empty( $state['imported_at'] );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function backup() {
		$stored = get_option( self::BACKUP_OPTION, null );
		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * @param array<string, mixed> $inventory Inventory.
	 * @param array<string, mixed> $preview Preview.
	 * @return void
	 */
	public static function store_backup( array $inventory, array $preview ) {
		update_option(
			self::BACKUP_OPTION,
			array(
				'created_at' => gmdate( 'c' ),
				'inventory'  => $inventory,
				'preview'    => array(
					'supported'   => $preview['supported'],
					'unsupported' => $preview['unsupported'],
				),
			),
			false
		);
	}

	/**
	 * @param mixed $value List.
	 * @return int
	 */
	private static function count_list( $value ) {
		return is_array( $value ) ? count( $value ) : 0;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function detect_visibility_rules() {
		if ( ! class_exists( 'RWGC_Rule_Registry', false ) ) {
			return array();
		}
		$rows = RWGC_Rule_Registry::get_rules_for_builder();
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function detect_slots() {
		if ( ! class_exists( 'RWGC_Experience_Slot_Registry', false ) ) {
			return array();
		}
		$raw = RWGC_Experience_Slot_Registry::all_raw();
		return is_array( $raw ) ? array_values( $raw ) : array();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function detect_variants() {
		if ( ! class_exists( 'RWGC_Variant_Store', false ) ) {
			return array();
		}
		$raw = RWGC_Variant_Store::all_raw();
		return is_array( $raw ) ? array_values( $raw ) : array();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function detect_experiments() {
		$found = array();
		if ( class_exists( 'RWGO_Experiment_Repository', false ) && method_exists( 'RWGO_Experiment_Repository', 'query_experiments' ) ) {
			$posts = RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => 200 ) );
			foreach ( is_array( $posts ) ? $posts : array() as $post ) {
				if ( is_object( $post ) && isset( $post->ID ) ) {
					$found[] = array(
						'id'    => (string) $post->ID,
						'name'  => isset( $post->post_title ) ? (string) $post->post_title : '',
						'title' => isset( $post->post_title ) ? (string) $post->post_title : '',
					);
				}
			}
		}
		/**
		 * @param array<int, array<string, mixed>> $found Experiments.
		 */
		$filtered = apply_filters( 'rwgc_cloud_migration_experiments', $found );
		return is_array( $filtered ) ? $filtered : $found;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function detect_commerce_rules() {
		$found = array();
		/**
		 * @param array<int, array<string, mixed>> $found Commerce rules.
		 */
		$filtered = apply_filters( 'rwgc_cloud_migration_commerce_rules', $found );
		return is_array( $filtered ) ? $filtered : $found;
	}
}
