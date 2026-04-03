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

		if ( class_exists( 'RWGC_Suite_Admin', false ) ) {
			add_submenu_page(
				'rwgc-dashboard',
				__( 'Suite Home', 'reactwoo-geocore' ),
				__( 'Suite Home', 'reactwoo-geocore' ),
				$cap,
				'rwgc-suite-home',
				array( 'RWGC_Suite_Admin', 'render_suite_home' )
			);
			add_submenu_page(
				'rwgc-dashboard',
				__( 'Getting Started', 'reactwoo-geocore' ),
				__( 'Getting Started', 'reactwoo-geocore' ),
				$cap,
				'rwgc-getting-started',
				array( 'RWGC_Suite_Admin', 'render_getting_started' )
			);
			add_submenu_page(
				'rwgc-dashboard',
				__( 'Create country page version', 'reactwoo-geocore' ),
				__( 'Create page version', 'reactwoo-geocore' ),
				$cap,
				'rwgc-workflow-variant',
				array( 'RWGC_Suite_Admin', 'render_workflow_variant' )
			);
			add_submenu_page(
				'rwgc-dashboard',
				__( 'Geo Suite — Page versions', 'reactwoo-geocore' ),
				__( 'Page versions', 'reactwoo-geocore' ),
				$cap,
				'rwgc-suite-variants',
				array( 'RWGC_Suite_Admin', 'render_suite_variants' )
			);
		}

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
		wp_enqueue_style(
			'rwgc-suite',
			RWGC_URL . 'admin/css/rwgc-suite.css',
			array( 'rwgc-admin' ),
			RWGC_VERSION
		);
		if ( preg_match( '/(rwgc-suite-home|rwgc-getting-started|rwgc-workflow-variant|rwgc-suite-variants)/', $hook ) ) {
			wp_enqueue_style(
				'rwgc-suite-shell',
				RWGC_URL . 'admin/css/suite-admin.css',
				array( 'rwgc-suite' ),
				RWGC_VERSION
			);
		}
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
		$status                = RWGC_MaxMind::get_status();
		$rwgc_rest_enabled     = (bool) RWGC_Settings::get( 'rest_enabled', 1 );
		$rwgc_location_url     = function_exists( 'rwgc_get_rest_location_url' ) ? rwgc_get_rest_location_url() : '';
		$rwgc_capabilities_url = function_exists( 'rwgc_get_rest_capabilities_url' ) ? rwgc_get_rest_capabilities_url() : '';
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
	 * Render an inner navigation row for Geo Core pages.
	 *
	 * @param string $current Current page slug.
	 * @return void
	 */
	public static function render_inner_nav( $current ) {
		$items = array(
			'rwgc-dashboard' => __( 'Dashboard', 'reactwoo-geocore' ),
			'rwgc-settings'  => __( 'Settings', 'reactwoo-geocore' ),
			'rwgc-tools'     => __( 'Tools', 'reactwoo-geocore' ),
			'rwgc-usage'     => __( 'Usage', 'reactwoo-geocore' ),
			'rwgc-addons'    => __( 'Add-ons', 'reactwoo-geocore' ),
		);

		/**
		 * Extra Geo Core submenu pages (e.g. satellite plugins under the same menu).
		 *
		 * @param array  $items   Admin page slug => label.
		 * @param string $current Current page slug (for context).
		 */
		$items = apply_filters( 'rwgc_inner_nav_items', $items, $current );

		echo '<nav class="rwgc-inner-nav" aria-label="' . esc_attr__( 'Geo Core section navigation', 'reactwoo-geocore' ) . '">';
		foreach ( $items as $slug => $label ) {
			$class = 'rwgc-inner-nav__link' . ( $slug === $current ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
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
		<p class="description"><?php esc_html_e( 'Free flow: set one page as Master (default), then create one Variant page per country linked to that Master.', 'reactwoo-geocore' ); ?></p>

		<p><strong><?php esc_html_e( 'Page role', 'reactwoo-geocore' ); ?></strong></p>
		<p>
			<select name="rwgc_route_role" id="rwgc_route_role">
				<option value="master" <?php selected( 'master', (string) $config['role'] ); ?>><?php esc_html_e( 'Master (default page)', 'reactwoo-geocore' ); ?></option>
				<option value="variant" <?php selected( 'variant', (string) $config['role'] ); ?>><?php esc_html_e( 'Secondary (country-specific page)', 'reactwoo-geocore' ); ?></option>
			</select>
		</p>

		<p><strong><?php esc_html_e( 'Secondary country', 'reactwoo-geocore' ); ?></strong></p>
		<p>
			<label for="rwgc_route_country_iso2" class="screen-reader-text"><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?></label>
			<?php
			self::render_country_select(
				'rwgc_route_country_iso2',
				(string) $config['country_iso2'],
				array(
					'id'                => 'rwgc_route_country_iso2',
					'class'             => 'rwgc-select-country widefat',
					'show_option_none'  => __( '-- Select country --', 'reactwoo-geocore' ),
					'option_none_value' => '',
				)
			);
			?>
		</p>
		<p><strong><?php esc_html_e( 'Secondary links to this master page', 'reactwoo-geocore' ); ?></strong></p>
		<p>
			<?php
			wp_dropdown_pages(
				array(
					'name'             => 'rwgc_route_master_page_id',
					'id'               => 'rwgc_route_master_page_id',
					'show_option_none' => __( '-- Select master page --', 'reactwoo-geocore' ),
					'option_none_value'=> '0',
					'selected'         => (int) $config['master_page_id'],
				)
			);
			?>
		</p>
		<p class="description"><?php esc_html_e( 'Tip: leave this page as Master for your default audience. On secondary pages, set role to Secondary and select this master page + a country code.', 'reactwoo-geocore' ); ?></p>
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
			'role'            => isset( $_POST['rwgc_route_role'] ) ? sanitize_key( wp_unslash( $_POST['rwgc_route_role'] ) ) : 'master',
			'master_page_id'  => isset( $_POST['rwgc_route_master_page_id'] ) ? absint( wp_unslash( $_POST['rwgc_route_master_page_id'] ) ) : 0,
		);

		if ( ! empty( $config['enabled'] ) && 'variant' === $config['role'] ) {
			if ( empty( $config['master_page_id'] ) || empty( $config['country_iso2'] ) ) {
				add_settings_error( 'rwgc_tools', 'rwgc_variant_missing_fields', __( 'Secondary page requires both a master page and a country code.', 'reactwoo-geocore' ), 'error' );
				$config['enabled'] = false;
			} elseif ( RWGC_Routing::master_has_variant( (int) $config['master_page_id'], (int) $post_id ) ) {
				add_settings_error( 'rwgc_tools', 'rwgc_variant_limit_reached', __( 'Free limit reached: this master page already has one variant. Upgrade to GeoElementor for multiple variants.', 'reactwoo-geocore' ), 'error' );
				$config['enabled'] = false;
			} elseif ( RWGC_Routing::is_variant_country_taken( (int) $config['master_page_id'], (string) $config['country_iso2'], (int) $post_id ) ) {
				add_settings_error( 'rwgc_tools', 'rwgc_variant_duplicate_country', __( 'That country is already assigned to another variant for this master page.', 'reactwoo-geocore' ), 'error' );
				$config['enabled'] = false;
			}
		}

		RWGC_Routing::save_page_route_config( $post_id, $config );
	}

	/**
	 * Output a prepopulated country &lt;select&gt; (no free-typed ISO2).
	 *
	 * @param string       $name     Input name.
	 * @param string       $selected Current ISO2 (uppercase).
	 * @param array<string, mixed> $args {
	 *   @type string $id               Element id (default: $name).
	 *   @type string $class            CSS classes.
	 *   @type string $show_option_none Label for empty option; empty string to omit.
	 *   @type string $option_none_value Value for empty option.
	 * }
	 * @return void
	 */
	public static function render_country_select( $name, $selected, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'                 => $name,
				'class'              => 'rwgc-select-country regular-text',
				'show_option_none'   => __( '-- Select country --', 'reactwoo-geocore' ),
				'option_none_value'  => '',
			)
		);
		$countries = RWGC_Countries::get_options();
		$selected  = strtoupper( substr( (string) $selected, 0, 2 ) );
		printf(
			'<select name="%1$s" id="%2$s" class="%3$s">',
			esc_attr( $name ),
			esc_attr( $args['id'] ),
			esc_attr( $args['class'] )
		);
		if ( '' !== $args['show_option_none'] ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $args['option_none_value'] ),
				selected( $selected, (string) $args['option_none_value'], false ),
				esc_html( $args['show_option_none'] )
			);
		}
		foreach ( $countries as $code => $label ) {
			$code = strtoupper( (string) $code );
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $code ),
				selected( $selected, $code, false ),
				esc_html( $label . ' (' . $code . ')' )
			);
		}
		echo '</select>';
	}

	/**
	 * Output a prepopulated currency &lt;select&gt; (ISO3).
	 *
	 * @param string               $name     Input name.
	 * @param string               $selected Current ISO3.
	 * @param array<string, mixed> $args     Same shape as {@see render_country_select()}.
	 * @return void
	 */
	public static function render_currency_select( $name, $selected, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'                => $name,
				'class'             => 'rwgc-select-currency regular-text',
				'show_option_none'  => '',
				'option_none_value' => '',
			)
		);
		$currencies = RWGC_Countries::get_currency_options();
		$selected   = strtoupper( substr( (string) $selected, 0, 3 ) );
		printf(
			'<select name="%1$s" id="%2$s" class="%3$s">',
			esc_attr( $name ),
			esc_attr( $args['id'] ),
			esc_attr( $args['class'] )
		);
		if ( '' !== $args['show_option_none'] ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $args['option_none_value'] ),
				selected( $selected, (string) $args['option_none_value'], false ),
				esc_html( $args['show_option_none'] )
			);
		}
		foreach ( $currencies as $code => $label ) {
			$code = strtoupper( substr( (string) $code, 0, 3 ) );
			$lab  = is_string( $label ) ? wp_strip_all_tags( $label ) : $code;
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $code ),
				selected( $selected, $code, false ),
				esc_html( $lab )
			);
		}
		echo '</select>';
	}
}

