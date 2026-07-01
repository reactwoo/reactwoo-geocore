<?php
/**
 * Modal rule tester: content + visitor context → detailed evaluator results.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates visibility rule test runs for the admin modal tester.
 */
class RWGC_Visibility_Rule_Tester {

	/**
	 * @return array<string,mixed>
	 */
	public static function bootstrap_config() {
		$rules = array();
		foreach ( RWGC_Visibility_Rule_Repository::query() as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$rules[] = array(
				'id'    => (int) $post->ID,
				'title' => (string) $post->post_title,
			);
		}

		$countries = array();
		if ( class_exists( 'RWGC_Countries', false ) ) {
			foreach ( RWGC_Countries::get_options() as $code => $label ) {
				$countries[] = array(
					'code'  => strtoupper( (string) $code ),
					'label' => (string) $label,
				);
			}
		}

		return array(
			'rules'     => $rules,
			'pages'     => self::content_options( 'page' ),
			'posts'     => self::content_options( 'post' ),
			'products'  => function_exists( 'wc_get_product' ) ? self::content_options( 'product' ) : array(),
			'countries' => $countries,
		);
	}

	/**
	 * @param int $rule_id Rule post ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_rule_payload( $rule_id ) {
		$rule_id = absint( $rule_id );
		$post    = RWGC_Visibility_Rule_Repository::get_post( $rule_id );
		if ( ! $post ) {
			return new WP_Error( 'rwgc_rule_not_found', __( 'Rule not found.', 'reactwoo-geocore' ), array( 'status' => 404 ) );
		}

		$raw     = (string) get_post_meta( $rule_id, RWGC_Visibility_Rule_CPT::META_PORTABLE, true );
		$decoded = json_decode( $raw, true );
		$set     = is_array( $decoded ) ? $decoded : null;
		$target  = '';
		if ( class_exists( 'RWGC_Visibility_Rule_Editor_Presenter', false ) ) {
			$presenter = RWGC_Visibility_Rule_Editor_Presenter::build( $rule_id, $post->post_title, $post->post_status, $raw );
			$target    = (string) ( $presenter['target_label'] ?? '' );
		}

		return array(
			'id'                 => $rule_id,
			'title'              => (string) $post->post_title,
			'target_label'       => $target,
			'portable_json'      => $raw,
			'conditions'         => class_exists( 'RWGC_Visibility_Rule_Logic_Preview', false )
				? RWGC_Visibility_Rule_Logic_Preview::build_compact( $set )
				: array(),
			'default_context'    => self::default_context_from_rule( $set ),
			'presets'            => self::presets_for_rule( $set ),
			'included_countries' => self::rule_included_countries( $set ),
			'excluded_countries' => self::rule_excluded_countries( $set ),
		);
	}

	/**
	 * @param array<string,mixed> $request Test request body.
	 * @return array<string,mixed>
	 */
	public static function run( array $request ) {
		$portable = self::resolve_portable_json( $request );
		if ( is_wp_error( $portable ) ) {
			return array(
				'status'  => 'error',
				'matches' => false,
				'error'   => $portable->get_error_message(),
			);
		}

		$decoded = json_decode( $portable, true );
		if ( ! is_array( $decoded ) ) {
			return array(
				'status'  => 'error',
				'matches' => false,
				'error'   => __( 'Rule data is not valid JSON.', 'reactwoo-geocore' ),
			);
		}

		$context  = isset( $request['context'] ) && is_array( $request['context'] ) ? $request['context'] : array();
		$content  = isset( $request['content'] ) && is_array( $request['content'] ) ? $request['content'] : array();
		$resolved = self::merge_content_into_context( $content, $context );
		$missing  = self::missing_context_fields( $decoded, $resolved );

		if ( ! empty( $missing ) ) {
			return array(
				'status'            => 'incomplete',
				'matches'           => false,
				'error'             => __( 'Cannot test — required context is missing.', 'reactwoo-geocore' ),
				'missing'           => $missing,
				'condition_results' => array(),
			);
		}

		$detailed = RWGC_Visibility_Rule_Preview::evaluate_detailed( $decoded, $resolved );
		$target   = isset( $request['target_label'] ) ? sanitize_text_field( (string) $request['target_label'] ) : '';
		$logic    = class_exists( 'RWGC_Visibility_Rule_Logic_Preview', false )
			? RWGC_Visibility_Rule_Logic_Preview::build( $decoded, $target )
			: array( 'intro' => '', 'lines' => array() );

		return array_merge(
			$detailed,
			array(
				'logic_preview' => $logic,
			)
		);
	}

	/**
	 * @param array<string,mixed> $request Request.
	 * @return string|\WP_Error
	 */
	private static function resolve_portable_json( array $request ) {
		if ( ! empty( $request['portable_json'] ) && is_string( $request['portable_json'] ) && '' !== trim( $request['portable_json'] ) ) {
			return (string) $request['portable_json'];
		}
		$rule_id = isset( $request['rule_id'] ) ? absint( $request['rule_id'] ) : 0;
		if ( $rule_id <= 0 ) {
			return new WP_Error( 'rwgc_rule_required', __( 'Select a visibility rule to test.', 'reactwoo-geocore' ) );
		}
		$post = RWGC_Visibility_Rule_Repository::get_post( $rule_id );
		if ( ! $post ) {
			return new WP_Error( 'rwgc_rule_not_found', __( 'Rule not found.', 'reactwoo-geocore' ) );
		}
		return (string) get_post_meta( $rule_id, RWGC_Visibility_Rule_CPT::META_PORTABLE, true );
	}

	/**
	 * @param string $post_type page|post|product.
	 * @return array<int,array<string,mixed>>
	 */
	private static function content_options( $post_type ) {
		$post_type = sanitize_key( (string) $post_type );
		$posts     = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ( (array) $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$out[] = array(
				'id'        => (int) $post->ID,
				'title'     => (string) $post->post_title,
				'type'      => $post_type,
				'page_type' => self::page_type_for_post( $post ),
				'url'       => self::path_from_post( $post ),
			);
		}
		return $out;
	}

	/**
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function path_from_post( WP_Post $post ) {
		$link = get_permalink( $post );
		if ( ! is_string( $link ) || '' === $link ) {
			return '';
		}
		$path = wp_parse_url( $link, PHP_URL_PATH );
		return is_string( $path ) ? $path : '';
	}

	/**
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function page_type_for_post( WP_Post $post ) {
		if ( 'product' === $post->post_type ) {
			return 'product';
		}
		if ( 'page' === $post->post_type ) {
			if ( function_exists( 'wc_get_page_id' ) && (int) wc_get_page_id( 'shop' ) === (int) $post->ID ) {
				return 'shop';
			}
			if ( (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
				return 'homepage';
			}
			return 'other';
		}
		if ( 'post' === $post->post_type ) {
			return 'other';
		}
		return 'other';
	}

	/**
	 * @param array<string,mixed> $content Content selector payload.
	 * @param array<string,mixed> $context Visitor context.
	 * @return array<string,string>
	 */
	private static function merge_content_into_context( array $content, array $context ) {
		$type = sanitize_key( (string) ( $content['type'] ?? '' ) );
		$id   = isset( $content['id'] ) ? absint( $content['id'] ) : 0;
		$url  = isset( $content['url'] ) ? (string) $content['url'] : '';

		if ( in_array( $type, array( 'page', 'post', 'product' ), true ) && $id > 0 ) {
			$post = get_post( $id );
			if ( $post instanceof WP_Post ) {
				if ( '' === trim( (string) ( $context['page_type'] ?? '' ) ) ) {
					$context['page_type'] = self::page_type_for_post( $post );
				}
				if ( '' === trim( (string) ( $context['request_uri'] ?? '' ) ) ) {
					$context['request_uri'] = self::path_from_post( $post );
				}
			}
		} elseif ( 'manual' === $type && '' !== trim( $url ) ) {
			if ( '' === trim( (string) ( $context['request_uri'] ?? '' ) ) ) {
				$context['request_uri'] = $url;
			}
		}

		return array(
			'country'     => isset( $context['country'] ) ? (string) $context['country'] : '',
			'device'      => isset( $context['device'] ) ? (string) $context['device'] : '',
			'page_type'   => isset( $context['page_type'] ) ? (string) $context['page_type'] : '',
			'request_uri' => isset( $context['request_uri'] ) ? (string) $context['request_uri'] : '',
			'utm_source'  => isset( $context['utm_source'] ) ? (string) $context['utm_source'] : '',
			'utm_medium'  => isset( $context['utm_medium'] ) ? (string) $context['utm_medium'] : '',
			'gclid'       => ! empty( $context['gclid'] ) ? '1' : '',
		);
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @param array<string,string>     $context Context.
	 * @return array<int,string>
	 */
	private static function missing_context_fields( $set, array $context ) {
		unset( $set );
		$missing = array();
		if ( '' === trim( (string) ( $context['country'] ?? '' ) ) ) {
			$missing[] = 'country';
		}
		if ( '' === trim( (string) ( $context['device'] ?? '' ) ) ) {
			$missing[] = 'device';
		}
		if ( '' === trim( (string) ( $context['page_type'] ?? '' ) ) ) {
			$missing[] = 'page_type';
		}
		return $missing;
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @return array<string,string>
	 */
	public static function default_context_from_rule( $set ) {
		$defaults = array(
			'country'     => '',
			'device'      => '',
			'page_type'   => '',
			'request_uri' => '',
			'utm_source'  => '',
			'utm_medium'  => '',
		);
		if ( ! is_array( $set ) || empty( $set['rules'][0]['conditions'] ) ) {
			return $defaults;
		}
		foreach ( (array) $set['rules'][0]['conditions'] as $cond ) {
			if ( ! is_array( $cond ) ) {
				continue;
			}
			$type = (string) ( $cond['type'] ?? '' );
			$op   = (string) ( $cond['operator'] ?? '' );
			$val  = $cond['value'] ?? array();
			$list = is_array( $val ) ? $val : array( $val );

			if ( 'country' === $type && in_array( $op, array( 'in', 'is' ), true ) && empty( $defaults['country'] ) ) {
				$defaults['country'] = strtoupper( substr( sanitize_text_field( (string) ( $list[0] ?? '' ) ), 0, 2 ) );
			}
			if ( in_array( $type, array( 'device', 'device_type' ), true ) && in_array( $op, array( 'in', 'is' ), true ) && empty( $defaults['device'] ) ) {
				$defaults['device'] = sanitize_key( (string) ( $list[0] ?? '' ) );
			}
			if ( 'page_type' === $type && in_array( $op, array( 'in', 'is' ), true ) && empty( $defaults['page_type'] ) ) {
				$defaults['page_type'] = sanitize_key( (string) ( $list[0] ?? '' ) );
			}
		}
		if ( self::rule_has_google_ads_branch( $set ) ) {
			$defaults['utm_source'] = 'google';
			$defaults['utm_medium'] = 'cpc';
		}
		return $defaults;
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @return array<int,array<string,mixed>>
	 */
	public static function presets_for_rule( $set ) {
		$presets = array();
		if ( ! is_array( $set ) ) {
			return $presets;
		}
		$defaults = self::default_context_from_rule( $set );
		$has_ads  = self::rule_has_google_ads_branch( $set );
		$has_sale = self::rule_has_winter_sale_branch( $set );
		$excluded = self::rule_excluded_countries( $set );

		if ( $has_ads ) {
			$presets[] = array(
				'id'      => 'google_ads_match',
				'label'   => __( 'Matching Google Ads visitor', 'reactwoo-geocore' ),
				'context' => array_merge(
					$defaults,
					array(
						'country'     => $defaults['country'] ?: 'IE',
						'device'      => $defaults['device'] ?: 'desktop',
						'page_type'   => $defaults['page_type'] ?: 'product',
						'utm_source'  => 'google',
						'utm_medium'  => 'cpc',
						'request_uri' => '',
					)
				),
			);
		}
		if ( $has_sale ) {
			$presets[] = array(
				'id'      => 'winter_sale_url',
				'label'   => __( 'Matching winter sale URL', 'reactwoo-geocore' ),
				'context' => array_merge(
					$defaults,
					array(
						'country'     => in_array( 'GB', self::rule_included_countries( $set ), true ) ? 'GB' : ( $defaults['country'] ?: 'GB' ),
						'device'      => $defaults['device'] ?: 'desktop',
						'page_type'   => $defaults['page_type'] ?: 'product',
						'utm_source'  => '',
						'utm_medium'  => '',
						'request_uri' => '/winter-sale',
					)
				),
			);
		}
		if ( ! empty( $excluded ) ) {
			$presets[] = array(
				'id'      => 'excluded_country',
				'label'   => __( 'Excluded country', 'reactwoo-geocore' ),
				'context' => array_merge(
					$defaults,
					array(
						'country'     => strtoupper( (string) $excluded[0] ),
						'device'      => $defaults['device'] ?: 'desktop',
						'page_type'   => $defaults['page_type'] ?: 'product',
						'utm_source'  => '',
						'utm_medium'  => '',
						'request_uri' => '/winter-sale',
					)
				),
			);
		}
		return $presets;
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @return bool
	 */
	private static function rule_has_google_ads_branch( $set ) {
		return self::rule_branch_matches( $set, static function ( array $branch ) {
			$conds = (array) ( $branch['conditions'] ?? array() );
			return class_exists( 'RWGC_Visibility_Rule_Logic_Preview', false )
				&& RWGC_Visibility_Rule_Logic_Preview::is_google_ads_branch( $conds );
		} );
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @return bool
	 */
	private static function rule_has_winter_sale_branch( $set ) {
		return self::rule_branch_matches(
			$set,
			static function ( array $branch ) {
				foreach ( (array) ( $branch['conditions'] ?? array() ) as $cond ) {
					if ( ! is_array( $cond ) || 'request_uri' !== (string) ( $cond['type'] ?? '' ) ) {
						continue;
					}
					$val = is_array( $cond['value'] ?? null ) ? implode( '', $cond['value'] ) : (string) ( $cond['value'] ?? '' );
					if ( false !== strpos( $val, 'winter-sale' ) ) {
						return true;
					}
				}
				return false;
			}
		);
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @param callable               $match Branch matcher.
	 * @return bool
	 */
	private static function rule_branch_matches( $set, callable $match ) {
		if ( ! is_array( $set ) || empty( $set['rules'][0]['conditions'] ) ) {
			return false;
		}
		foreach ( (array) $set['rules'][0]['conditions'] as $cond ) {
			if ( ! is_array( $cond ) || 'condition_group' !== (string) ( $cond['type'] ?? '' ) ) {
				continue;
			}
			$val = $cond['value'] ?? array();
			if ( ! is_array( $val ) ) {
				continue;
			}
			foreach ( (array) ( $val['branches'] ?? array() ) as $branch ) {
				if ( is_array( $branch ) && $match( $branch ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @return array<int,string>
	 */
	private static function rule_included_countries( $set ) {
		$codes = array();
		if ( ! is_array( $set ) || empty( $set['rules'][0]['conditions'] ) ) {
			return $codes;
		}
		foreach ( (array) $set['rules'][0]['conditions'] as $cond ) {
			if ( ! is_array( $cond ) || 'country' !== (string) ( $cond['type'] ?? '' ) ) {
				continue;
			}
			if ( ! in_array( (string) ( $cond['operator'] ?? '' ), array( 'in', 'is' ), true ) ) {
				continue;
			}
			foreach ( (array) ( $cond['value'] ?? array() ) as $code ) {
				$code = strtoupper( substr( sanitize_text_field( (string) $code ), 0, 2 ) );
				if ( '' !== $code ) {
					$codes[] = $code;
				}
			}
		}
		return array_values( array_unique( $codes ) );
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @return array<int,string>
	 */
	private static function rule_excluded_countries( $set ) {
		$codes = array();
		if ( ! is_array( $set ) || empty( $set['rules'][0]['conditions'] ) ) {
			return $codes;
		}
		foreach ( (array) $set['rules'][0]['conditions'] as $cond ) {
			if ( ! is_array( $cond ) || 'country' !== (string) ( $cond['type'] ?? '' ) ) {
				continue;
			}
			if ( ! in_array( (string) ( $cond['operator'] ?? '' ), array( 'not_in', 'is_not' ), true ) ) {
				continue;
			}
			foreach ( (array) ( $cond['value'] ?? array() ) as $code ) {
				$code = strtoupper( substr( sanitize_text_field( (string) $code ), 0, 2 ) );
				if ( '' !== $code ) {
					$codes[] = $code;
				}
			}
		}
		return array_values( array_unique( $codes ) );
	}
}
