<?php
/**
 * WooCommerce product data tab — GeoCore workspace for product-level geo controls.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the GeoCore tab in WooCommerce product data panels.
 */
class RWGC_Admin_WooCommerce_Product_Tab {

	/**
	 * @return void
	 */
	public static function init() {
		if ( ! function_exists( 'rwgc_is_woocommerce_active' ) || ! rwgc_is_woocommerce_active() ) {
			return;
		}
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'register_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product' ), 15, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $tabs Product data tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_tab( $tabs ) {
		$tabs['geocore'] = array(
			'label'    => __( 'GeoCore', 'reactwoo-geocore' ),
			'target'   => 'geocore_product_data',
			'class'    => array(),
			'priority' => 65,
		);
		return $tabs;
	}

	/**
	 * @return void
	 */
	public static function render_panel() {
		global $post;
		$post_id = ( $post instanceof WP_Post ) ? (int) $post->ID : 0;

		echo '<div id="geocore_product_data" class="panel woocommerce_options_panel hidden rwgc-product-geocore-tab">';

		self::render_section_open(
			'weather',
			__( 'Weather Relevance', 'reactwoo-geocore' ),
			__( 'Tag this product with the weather conditions it suits. GeoCore can use this for product boosts, recommendations, and weather-based merchandising.', 'reactwoo-geocore' )
		);
		/**
		 * Render weather facet controls inside the GeoCore product tab.
		 *
		 * @param int $post_id Product ID.
		 */
		do_action( 'geocore_product_tab_weather', $post_id );
		do_action( 'geocore_product_tab_after_weather', $post_id );
		self::render_section_close();

		self::render_section_open(
			'targeting',
			__( 'Geo Targeting', 'reactwoo-geocore' ),
			__( 'Override global GeoCore rules for this product. Country lists use ISO country codes; attach saved targeting rules from your GeoCore library.', 'reactwoo-geocore' )
		);
		self::render_targeting_fields( $post_id );
		do_action( 'geocore_product_tab_after_targeting', $post_id );
		self::render_section_close();

		self::render_section_open(
			'boost',
			__( 'Catalogue Boosting', 'reactwoo-geocore' ),
			__( 'Control whether this product participates in weather-based catalog boosting. Inherit uses your Geo Commerce merchandising defaults.', 'reactwoo-geocore' )
		);
		self::render_boost_fields( $post_id );
		do_action( 'geocore_product_tab_after_boost', $post_id );
		self::render_section_close();

		self::render_section_open(
			'preview',
			__( 'Storefront Preview', 'reactwoo-geocore' ),
			__( 'Simulate visitor location and weather to see how this product would behave on the storefront.', 'reactwoo-geocore' )
		);
		self::render_preview_fields( $post_id );
		do_action( 'geocore_product_tab_after_preview', $post_id );
		self::render_section_close();

		echo '</div>';
	}

	/**
	 * @param string $slug        Section slug for CSS.
	 * @param string $title       Section title.
	 * @param string $description Section description.
	 * @return void
	 */
	private static function render_section_open( $slug, $title, $description ) {
		echo '<div class="rwgc-product-geocore-card rwgc-product-geocore-card--' . esc_attr( $slug ) . '">';
		echo '<div class="rwgc-product-geocore-card__head">';
		echo '<h4 class="rwgc-product-geocore-card__title">' . esc_html( $title ) . '</h4>';
		echo '<p class="rwgc-product-geocore-card__desc description">' . esc_html( $description ) . '</p>';
		echo '</div>';
		echo '<div class="rwgc-product-geocore-card__body">';
	}

	/**
	 * @return void
	 */
	private static function render_section_close() {
		echo '</div></div>';
	}

	/**
	 * @param int $post_id Product ID.
	 * @return void
	 */
	private static function render_targeting_fields( $post_id ) {
		$geo_mode         = RWGC_Product_Meta::get_geo_mode( $post_id );
		$countries        = RWGC_Product_Meta::get_countries( $post_id );
		$rule_ids         = RWGC_Product_Meta::get_rule_ids( $post_id );
		$visibility_mode  = RWGC_Product_Meta::get_visibility_mode( $post_id );
		$selected_rule_id = ! empty( $rule_ids ) ? (int) $rule_ids[0] : 0;

		echo '<fieldset class="form-field rwgc-product-geo-mode">';
		echo '<legend><strong>' . esc_html__( 'Product visibility by location', 'reactwoo-geocore' ) . '</strong></legend>';
		$modes = array(
			RWGC_Product_Meta::GEO_MODE_GLOBAL       => __( 'Use global GeoCore rules', 'reactwoo-geocore' ),
			RWGC_Product_Meta::GEO_MODE_HIDE_IN      => __( 'Hide this product in selected countries', 'reactwoo-geocore' ),
			RWGC_Product_Meta::GEO_MODE_SHOW_ONLY_IN => __( 'Show this product only in selected countries', 'reactwoo-geocore' ),
		);
		foreach ( $modes as $value => $label ) {
			printf(
				'<label class="rwgc-product-geo-mode__option"><input type="radio" name="_geocore_product_geo_mode" value="%1$s" %2$s /> %3$s</label>',
				esc_attr( $value ),
				checked( $geo_mode, $value, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';

		$country_style = RWGC_Product_Meta::GEO_MODE_GLOBAL === $geo_mode ? ' style="display:none;"' : '';
		echo '<p class="form-field rwgc-product-countries-wrap"' . $country_style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<label for="_geocore_product_countries"><strong>' . esc_html__( 'Countries / regions', 'reactwoo-geocore' ) . '</strong></label><br />';
		if ( class_exists( 'RWGC_Experience_Workflow', false ) ) {
			RWGC_Experience_Workflow::render_country_multi_select(
				'_geocore_product_countries',
				$countries,
				array(
					'id'    => '_geocore_product_countries',
					'class' => 'rwgc-select-country rwgc-product-country-select',
					'size'  => 6,
				)
			);
		}
		echo '</p>';

		echo '<p class="form-field">';
		echo '<label for="_geocore_product_rule_id"><strong>' . esc_html__( 'Attach existing GeoCore rule', 'reactwoo-geocore' ) . '</strong></label>';
		echo '<select name="_geocore_product_rule_id" id="_geocore_product_rule_id" class="rwgc-product-rule-select">';
		echo '<option value="">' . esc_html__( '— None —', 'reactwoo-geocore' ) . '</option>';
		foreach ( self::get_rule_options() as $rule_id => $rule_label ) {
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $rule_id,
				selected( $selected_rule_id, (int) $rule_id, false ),
				esc_html( $rule_label )
			);
		}
		echo '</select>';
		echo '</p>';

		echo '<p class="form-field rwgc-product-rule-mode-wrap"' . ( $selected_rule_id > 0 ? '' : ' style="display:none;"' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<label><strong>' . esc_html__( 'Attached rule visibility', 'reactwoo-geocore' ) . '</strong></label><br />';
		printf(
			'<label><input type="radio" name="_geocore_product_visibility_mode" value="show_if" %1$s /> %2$s</label> ',
			checked( $visibility_mode, 'show_if', false ),
			esc_html__( 'Show when rule matches', 'reactwoo-geocore' )
		);
		printf(
			'<label><input type="radio" name="_geocore_product_visibility_mode" value="hide_if" %1$s /> %2$s</label>',
			checked( $visibility_mode, 'hide_if', false ),
			esc_html__( 'Hide when rule matches', 'reactwoo-geocore' )
		);
		echo '</p>';
	}

	/**
	 * @return array<int, string>
	 */
	private static function get_rule_options() {
		if ( ! class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			return array();
		}
		$options = array();
		foreach ( RWGC_Visibility_Rule_Repository::query() as $rule_post ) {
			if ( ! $rule_post instanceof WP_Post ) {
				continue;
			}
			$options[ (int) $rule_post->ID ] = $rule_post->post_title ? $rule_post->post_title : sprintf(
				/* translators: %d: rule post ID */
				__( 'Rule #%d', 'reactwoo-geocore' ),
				(int) $rule_post->ID
			);
		}
		return $options;
	}

	/**
	 * @param int $post_id Product ID.
	 * @return void
	 */
	private static function render_boost_fields( $post_id ) {
		$boost = RWGC_Product_Meta::get_boost_enabled( $post_id );
		$choices = array(
			RWGC_Product_Meta::BOOST_INHERIT => __( 'Inherit global catalogue boost setting', 'reactwoo-geocore' ),
			RWGC_Product_Meta::BOOST_YES     => __( 'Enable weather catalogue boost for this product', 'reactwoo-geocore' ),
			RWGC_Product_Meta::BOOST_NO      => __( 'Exclude from weather catalogue boost', 'reactwoo-geocore' ),
		);
		echo '<p class="form-field">';
		foreach ( $choices as $value => $label ) {
			printf(
				'<label class="rwgc-product-boost__option"><input type="radio" name="_geocore_product_boost_enabled" value="%1$s" %2$s /> %3$s</label>',
				esc_attr( $value ),
				checked( $boost, $value, false ),
				esc_html( $label )
			);
		}
		echo '</p>';
		if ( class_exists( 'RWGCM_Admin', false ) ) {
			$url = admin_url( 'admin.php?page=rwgcm-merchandising' );
			echo '<p class="description"><a href="' . esc_url( $url ) . '">' . esc_html__( 'Geo Commerce merchandising settings', 'reactwoo-geocore' ) . '</a></p>';
		}
	}

	/**
	 * @param int $post_id Product ID.
	 * @return void
	 */
	private static function render_preview_fields( $post_id ) {
		unset( $post_id );
		$visitor_country = function_exists( 'rwgc_get_visitor_country' ) ? strtoupper( (string) rwgc_get_visitor_country() ) : '';

		echo '<p class="form-field">';
		echo '<label for="rwgc-preview-visitor-country"><strong>' . esc_html__( 'Simulate visitor location', 'reactwoo-geocore' ) . '</strong></label><br />';
		if ( class_exists( 'RWGC_Admin', false ) ) {
			RWGC_Admin::render_country_select(
				'rwgc_preview_visitor_country',
				'',
				array(
					'id'                => 'rwgc-preview-visitor-country',
					'class'             => 'rwgc-select-country rwgc-preview-country',
					'show_option_none'  => $visitor_country
						? sprintf(
							/* translators: %s: ISO country code */
							__( '— Live visitor (%s) —', 'reactwoo-geocore' ),
							$visitor_country
						)
						: __( '— Live visitor —', 'reactwoo-geocore' ),
					'option_none_value' => '',
				)
			);
		}
		echo '</p>';

		echo '<p class="form-field">';
		echo '<label><input type="checkbox" id="rwgc-preview-simulate-weather" value="1" /> ';
		echo esc_html__( 'Simulate visitor weather', 'reactwoo-geocore' );
		echo '</label></p>';

		echo '<div class="rwgc-preview-weather-grid" id="rwgc-preview-weather-grid" style="display:none;">';
		$facets = self::get_weather_facet_labels();
		foreach ( $facets as $slug => $label ) {
			printf(
				'<label class="rwgc-preview-weather-grid__item"><input type="checkbox" name="rwgc_preview_visitor_facets[]" value="%1$s" /> %2$s</label>',
				esc_attr( $slug ),
				esc_html( $label )
			);
		}
		echo '</div>';

		echo '<p class="form-field rwgc-preview-result-wrap">';
		echo '<strong>' . esc_html__( 'Preview result', 'reactwoo-geocore' ) . '</strong> ';
		echo '<span id="rwgc-preview-status-badge" class="rwgc-preview-badge rwgc-preview-badge--neutral">' . esc_html__( '—', 'reactwoo-geocore' ) . '</span>';
		echo '</p>';
		echo '<p id="rwgc-preview-status-detail" class="description"></p>';
	}

	/**
	 * @return array<string, string>
	 */
	private static function get_weather_facet_labels() {
		if ( class_exists( 'RWGCM_Weather_Affinity', false ) ) {
			$labels = array();
			foreach ( RWGCM_Weather_Affinity::get_facet_definitions() as $row ) {
				if ( is_array( $row ) && ! empty( $row['slug'] ) ) {
					$labels[ (string) $row['slug'] ] = isset( $row['label'] ) ? (string) $row['label'] : (string) $row['slug'];
				}
			}
			return $labels;
		}
		return array();
	}

	/**
	 * @param int     $post_id Product ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_product( $post_id, $post ) {
		unset( $post );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['_geocore_product_geo_mode'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC product save.
			return;
		}

		RWGC_Product_Meta::save_from_request(
			(int) $post_id,
			array(
				'geo_mode'         => isset( $_POST['_geocore_product_geo_mode'] ) ? wp_unslash( $_POST['_geocore_product_geo_mode'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'countries'        => isset( $_POST['_geocore_product_countries'] ) ? wp_unslash( $_POST['_geocore_product_countries'] ) : array(), // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'rule_id'          => isset( $_POST['_geocore_product_rule_id'] ) ? wp_unslash( $_POST['_geocore_product_rule_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'visibility_mode'  => isset( $_POST['_geocore_product_visibility_mode'] ) ? wp_unslash( $_POST['_geocore_product_visibility_mode'] ) : 'show_if', // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'boost_enabled'    => isset( $_POST['_geocore_product_boost_enabled'] ) ? wp_unslash( $_POST['_geocore_product_boost_enabled'] ) : RWGC_Product_Meta::BOOST_INHERIT, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			)
		);
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'rwgc-product-geocore-tab',
			RWGC_URL . 'assets/css/admin-product-geocore-tab.css',
			array(),
			RWGC_VERSION
		);

		wp_enqueue_script(
			'rwgc-product-geocore-preview',
			RWGC_URL . 'assets/js/admin-product-geocore-preview.js',
			array( 'jquery' ),
			RWGC_VERSION,
			true
		);

		$facet_labels = self::get_weather_facet_labels();
		$visitor_facets = array();
		if ( class_exists( 'RWGCM_Weather_Affinity', false ) ) {
			$visitor_facets = RWGCM_Weather_Affinity::get_visitor_facets();
		}

		wp_localize_script(
			'rwgc-product-geocore-preview',
			'rwgcProductGeocorePreview',
			array(
				'visitorFacets' => $visitor_facets,
				'facetLabels'   => $facet_labels,
				'geoModes'      => array(
					'global'       => RWGC_Product_Meta::GEO_MODE_GLOBAL,
					'hideIn'       => RWGC_Product_Meta::GEO_MODE_HIDE_IN,
					'showOnlyIn'   => RWGC_Product_Meta::GEO_MODE_SHOW_ONLY_IN,
				),
				'boost'         => array(
					'inherit' => RWGC_Product_Meta::BOOST_INHERIT,
					'yes'     => RWGC_Product_Meta::BOOST_YES,
					'no'      => RWGC_Product_Meta::BOOST_NO,
				),
				'i18n'          => array(
					'visible'        => __( 'Visible', 'reactwoo-geocore' ),
					'hidden'         => __( 'Hidden', 'reactwoo-geocore' ),
					'boosted'        => __( 'Boosted', 'reactwoo-geocore' ),
					'noWeatherMatch' => __( 'No weather match', 'reactwoo-geocore' ),
					'noProductFacets'=> __( 'Tag at least one weather facet to preview weather match.', 'reactwoo-geocore' ),
					'globalGeo'      => __( 'Using global GeoCore rules — product is visible unless commerce rules hide it.', 'reactwoo-geocore' ),
					'hiddenCountry'  => __( 'Hidden for the simulated country based on product geo targeting.', 'reactwoo-geocore' ),
					'visibleCountry' => __( 'Visible for the simulated country.', 'reactwoo-geocore' ),
					'weatherMatch'   => __( 'Weather overlap: %s', 'reactwoo-geocore' ),
					'weatherNoMatch' => __( 'No overlap with simulated visitor weather.', 'reactwoo-geocore' ),
					'boostEnabled'   => __( 'Would be boosted in catalog when weather matches.', 'reactwoo-geocore' ),
					'boostDisabled'  => __( 'Catalogue boost excluded for this product.', 'reactwoo-geocore' ),
				),
			)
		);
	}
}
