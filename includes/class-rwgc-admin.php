<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI controller for ReactWoo Geo Core.
 */
class RWGC_Admin {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_admin_notices' ) );
		add_action( 'admin_post_rwgc_upload_mmdb', array( __CLASS__, 'handle_upload_mmdb' ) );
		add_action( 'add_meta_boxes_page', array( __CLASS__, 'register_page_meta_box' ) );
		add_action( 'save_post_page', array( __CLASS__, 'save_page_meta_box' ) );
	}

	/**
	 * Register top-level menu and submenus.
	 *
	 * @return void
	 */
	public static function register_menu() {
		$cap = 'manage_options';

		add_menu_page(
			__( 'ReactWoo Geo Core', 'reactwoo-geocore' ),
			__( 'Geo Core', 'reactwoo-geocore' ),
			$cap,
			'rwgc-dashboard',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-location-alt',
			58
		);

		add_submenu_page(
			'rwgc-dashboard',
			__( 'Dashboard', 'reactwoo-geocore' ),
			__( 'Dashboard', 'reactwoo-geocore' ),
			$cap,
			'rwgc-dashboard',
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			'rwgc-dashboard',
			__( 'Settings', 'reactwoo-geocore' ),
			__( 'Settings', 'reactwoo-geocore' ),
			$cap,
			'rwgc-settings',
			array( __CLASS__, 'render_settings' )
		);

		add_submenu_page(
			'rwgc-dashboard',
			__( 'Tools', 'reactwoo-geocore' ),
			__( 'Tools', 'reactwoo-geocore' ),
			$cap,
			'rwgc-tools',
			array( __CLASS__, 'render_tools' )
		);

		add_submenu_page(
			'rwgc-dashboard',
			__( 'Usage', 'reactwoo-geocore' ),
			__( 'Usage', 'reactwoo-geocore' ),
			$cap,
			'rwgc-usage',
			array( __CLASS__, 'render_usage' )
		);

		add_submenu_page(
			'rwgc-dashboard',
			__( 'Add-ons', 'reactwoo-geocore' ),
			__( 'Add-ons', 'reactwoo-geocore' ),
			$cap,
			'rwgc-addons',
			array( __CLASS__, 'render_addons' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'rwgc-' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'rwgc-admin',
			RWGC_URL . 'admin/css/admin.css',
			array(),
			RWGC_VERSION
		);
		wp_enqueue_script(
			'rwgc-admin',
			RWGC_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			RWGC_VERSION,
			true
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = RWGC_Settings::get_settings();
		$status   = RWGC_MaxMind::get_status();
		$data     = RWGC_API::get_visitor_data();

		include RWGC_PATH . 'admin/views/dashboard-page.php';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = RWGC_Settings::get_settings();
		include RWGC_PATH . 'admin/views/settings-page.php';
	}

	/**
	 * Render tools page.
	 *
	 * @return void
	 */
	public static function render_tools() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = RWGC_Settings::get_settings();
		$status   = RWGC_MaxMind::get_status();
		$data     = RWGC_API::get_visitor_data();
		include RWGC_PATH . 'admin/views/tools-page.php';
	}

	/**
	 * Render usage page.
	 *
	 * @return void
	 */
	public static function render_usage() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status = RWGC_MaxMind::get_status();
		include RWGC_PATH . 'admin/views/usage-page.php';
	}

	/**
	 * Handle manual .mmdb upload from Tools page.
	 *
	 * @return void
	 */
	public static function handle_upload_mmdb() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}
		check_admin_referer( 'rwgc_upload_mmdb' );

		if ( empty( $_FILES['rwgc_mmdb']['tmp_name'] ) || ! is_uploaded_file( $_FILES['rwgc_mmdb']['tmp_name'] ) ) {
			add_settings_error( 'rwgc_tools', 'rwgc_upload_missing', __( 'No file uploaded or upload failed.', 'reactwoo-geocore' ), 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=rwgc-tools' ) );
			exit;
		}

		$file     = $_FILES['rwgc_mmdb'];
		$filename = isset( $file['name'] ) ? (string) $file['name'] : '';
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( 'mmdb' !== $ext ) {
			add_settings_error( 'rwgc_tools', 'rwgc_upload_ext', __( 'Invalid file type. Please upload a .mmdb MaxMind database file.', 'reactwoo-geocore' ), 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=rwgc-tools' ) );
			exit;
		}

		RWGC_MaxMind::ensure_storage_dir();
		$dest_dir  = RWGC_MaxMind::get_storage_dir();
		$dest_path = trailingslashit( $dest_dir ) . 'GeoLite2-Country.mmdb';

		if ( ! @move_uploaded_file( $file['tmp_name'], $dest_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Detected
			add_settings_error( 'rwgc_tools', 'rwgc_upload_move', __( 'Failed to move uploaded file into storage directory.', 'reactwoo-geocore' ), 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=rwgc-tools' ) );
			exit;
		}

		$settings                    = RWGC_Settings::get_settings();
		$settings['db_file_path']    = $dest_path;
		$settings['db_last_updated'] = gmdate( 'c' );
		$settings['db_last_error']   = '';
		RWGC_Settings::update( $settings );

		add_settings_error( 'rwgc_tools', 'rwgc_upload_success', __( 'MaxMind database uploaded successfully.', 'reactwoo-geocore' ), 'updated' );
		wp_safe_redirect( admin_url( 'admin.php?page=rwgc-tools' ) );
		exit;
	}

	/**
	 * Render add-ons page.
	 *
	 * @return void
	 */
	public static function render_addons() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$addons = RWGC_Upsells::get_addons();
		include RWGC_PATH . 'admin/views/addons-page.php';
	}

	/**
	 * Show admin notices for missing license/DB/etc.
	 *
	 * @return void
	 */
	public static function maybe_show_admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'rwgc-' ) === false ) {
			return;
		}

		$status   = RWGC_MaxMind::get_status();
		$settings = RWGC_Settings::get_settings();

		if ( empty( $settings['maxmind_license_key'] ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'ReactWoo Geo Core: MaxMind license key is not configured. GeoIP lookups will use fallback values.', 'reactwoo-geocore' )
			);
		} elseif ( ! $status['exists'] ) {
			if ( ! empty( $status['last_error'] ) ) {
				printf(
					'<div class="notice notice-warning"><p>%s</p><p><code>%s</code></p></div>',
					esc_html__( 'ReactWoo Geo Core: MaxMind database not found. Last error:', 'reactwoo-geocore' ),
					esc_html( $status['last_error'] )
				);
			} else {
				printf(
					'<div class="notice notice-warning"><p>%s</p></div>',
					esc_html__( 'ReactWoo Geo Core: MaxMind database not found. Run a manual update from the Tools tab.', 'reactwoo-geocore' )
				);
			}
		} elseif ( $status['is_stale'] ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				esc_html__( 'ReactWoo Geo Core: MaxMind database may be stale. Consider updating from the Tools tab.', 'reactwoo-geocore' )
			);
		}
	}

	/**
	 * Register page-level variant routing meta box.
	 *
	 * @return void
	 */
	public static function register_page_meta_box() {
		add_meta_box(
			'rwgc-page-routing',
			__( 'Geo Variant Routing (Free)', 'reactwoo-geocore' ),
			array( __CLASS__, 'render_page_meta_box' ),
			'page',
			'side',
			'default'
		);
	}

	/**
	 * Render page-level routing controls.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public static function render_page_meta_box( $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		$config = RWGC_Routing::get_page_route_config( (int) $post->ID );
		wp_nonce_field( 'rwgc_page_routing_save', 'rwgc_page_routing_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="rwgc_route_enabled" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> />
				<?php esc_html_e( 'Enable geo routing for this page', 'reactwoo-geocore' ); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Free limit: one default fallback page and one additional country mapping.', 'reactwoo-geocore' ); ?></p>

		<p><strong><?php esc_html_e( 'Default fallback page', 'reactwoo-geocore' ); ?></strong></p>
		<p>
			<?php
			wp_dropdown_pages(
				array(
					'name'             => 'rwgc_route_default_page_id',
					'id'               => 'rwgc_route_default_page_id',
					'show_option_none' => __( '-- No fallback --', 'reactwoo-geocore' ),
					'option_none_value'=> '0',
					'selected'         => (int) $config['default_page_id'],
				)
			);
			?>
		</p>

		<p><strong><?php esc_html_e( 'Additional country mapping', 'reactwoo-geocore' ); ?></strong></p>
		<p>
			<label for="rwgc_route_country_iso2"><?php esc_html_e( 'Country code (ISO2)', 'reactwoo-geocore' ); ?></label><br />
			<input type="text" name="rwgc_route_country_iso2" id="rwgc_route_country_iso2" value="<?php echo esc_attr( (string) $config['country_iso2'] ); ?>" maxlength="2" class="small-text" placeholder="GB" />
		</p>
		<p>
			<?php
			wp_dropdown_pages(
				array(
					'name'             => 'rwgc_route_country_page_id',
					'id'               => 'rwgc_route_country_page_id',
					'show_option_none' => __( '-- No mapped page --', 'reactwoo-geocore' ),
					'option_none_value'=> '0',
					'selected'         => (int) $config['country_page_id'],
				)
			);
			?>
		</p>
		<p class="description"><?php esc_html_e( 'Need multiple country variants per page? Use GeoElementor advanced routing.', 'reactwoo-geocore' ); ?></p>
		<?php
	}

	/**
	 * Save page-level routing controls.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save_page_meta_box( $post_id ) {
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST['rwgc_page_routing_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgc_page_routing_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'rwgc_page_routing_save' ) ) {
			return;
		}

		$config = array(
			'enabled'         => ! empty( $_POST['rwgc_route_enabled'] ),
			'default_page_id' => isset( $_POST['rwgc_route_default_page_id'] ) ? absint( wp_unslash( $_POST['rwgc_route_default_page_id'] ) ) : 0,
			'country_iso2'    => isset( $_POST['rwgc_route_country_iso2'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgc_route_country_iso2'] ) ) : '',
			'country_page_id' => isset( $_POST['rwgc_route_country_page_id'] ) ? absint( wp_unslash( $_POST['rwgc_route_country_page_id'] ) ) : 0,
		);

		RWGC_Routing::save_page_route_config( $post_id, $config );
	}
}

