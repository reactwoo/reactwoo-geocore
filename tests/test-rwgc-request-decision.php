<?php
/**
 * Gate D request-time Decision Runtime (cached manifest, no Cloud HTTP).
 *
 * Usage: php tests/test-rwgc-request-decision.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'RWGC_VERSION', '0.0-test' );

$GLOBALS['rwgc_test_options'] = array();
$GLOBALS['rwgc_test_filters'] = array();
$GLOBALS['rwgc_test_country'] = 'GB';
$GLOBALS['rwgc_http_calls']   = 0;

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['rwgc_test_options'] ) ? $GLOBALS['rwgc_test_options'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['rwgc_test_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['rwgc_test_options'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action() {}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
		$GLOBALS['rwgc_test_filters'][ $hook ][] = array(
			'cb'   => $cb,
			'args' => $args,
		);
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['rwgc_test_filters'][ $hook ] ) ) {
			return $value;
		}
		foreach ( $GLOBALS['rwgc_test_filters'][ $hook ] as $entry ) {
			$cb   = $entry['cb'];
			$n    = (int) $entry['args'];
			$call = array_slice( array_merge( array( $value ), $args ), 0, max( 1, $n ) );
			$value = call_user_func_array( $cb, $call );
		}
		return $value;
	}
}
if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( $min = 0, $max = 0 ) {
		return mt_rand( (int) $min, (int) $max );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'test-salt-' . $scheme;
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $s ) {
		return rtrim( (string) $s, '/\\' );
	}
}
if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	function rwgc_get_visitor_country() {
		return (string) $GLOBALS['rwgc_test_country'];
	}
}

if ( ! defined( 'RWGC_PATH' ) ) {
	define( 'RWGC_PATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__ ) . '/includes/decision/class-rwgc-decision.php';
RWGC_Decision::load();
require_once dirname( __DIR__ ) . '/includes/slots/class-rwgc-experience-slots.php';
RWGC_Experience_Slots::load();
require_once dirname( __DIR__ ) . '/includes/variants/class-rwgc-variants.php';
RWGC_Variants::load();
RWGC_Variants::init();
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud-http.php';
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud-config.php';
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud-credentials.php';
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud-manifest-store.php';
RWGC_Request_Decision::init();

add_filter(
	'rwgc_cloud_http_transport',
	static function () {
		++$GLOBALS['rwgc_http_calls'];
		return array(
			'ok'     => false,
			'status' => 500,
			'body'   => null,
			'raw'    => '',
			'error'  => 'should_not_call_cloud',
		);
	},
	10,
	2
);

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_rd_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

$slot_id = 'rw_homepage_hero_abc12';

/**
 * @return array<string, mixed>
 */
function rwgc_rd_cloud_style_manifest() {
	global $slot_id;
	return array(
		'schema'      => '1.0',
		'revision'    => 7,
		'site'        => 'site_gate_d',
		'audiences'   => array(
			array(
				'id'         => 'aud_uk',
				'name'       => 'UK',
				'conditions' => array(
					'all' => array(
						array(
							'type'  => 'geo.country',
							'op'    => 'in',
							'value' => array( 'GB' ),
						),
					),
				),
			),
		),
		'experiences' => array(
			array(
				'id'          => 'exp_uk',
				'name'        => 'UK Hero',
				'audience_id' => 'aud_uk',
				'slot_id'     => $slot_id,
				'variant_id'  => 'var_uk',
				'status'      => 'active',
				'priority'    => 50,
			),
		),
		'variants'    => array(
			array(
				'id'      => 'var_uk',
				'type'    => 'content',
				'payload' => array( 'html' => '<p>GATE-D-UK</p>' ),
			),
		),
		'experiments' => array(),
		'goals'       => array(),
		'slots'       => array(),
	);
}

RWGC_Experience_Slot_Registry::reset_cache();
RWGC_Variant_Store::reset_cache();
RWGC_Cloud_Manifest_Store::reset_request_cache();
RWGC_Cloud_Http::reset_attempt_count();
RWGC_Request_Decision::reset();

$none = apply_filters( 'reactwoo_current_decision_result', null );
rwgc_rd_assert( 'no manifest returns null', null === $none );
rwgc_rd_assert( 'no Cloud HTTP without manifest', 0 === RWGC_Cloud_Http::attempt_count() && 0 === $GLOBALS['rwgc_http_calls'] );

$installed = RWGC_Cloud_Manifest_Store::install( rwgc_rd_cloud_style_manifest(), 'site_gate_d' );
rwgc_rd_assert( 'manifest installs', ! empty( $installed['ok'] ) );

RWGC_Request_Decision::reset();
RWGC_Cloud_Http::begin_visitor_render();
$decision = apply_filters( 'reactwoo_current_decision_result', null );
rwgc_rd_assert( 'cached manifest yields Decision_Result', $decision instanceof RWGC_Decision_Result );
rwgc_rd_assert( 'UK audience matches GB via op/in', $decision instanceof RWGC_Decision_Result && in_array( 'aud_uk', $decision->matched_audiences(), true ) );
rwgc_rd_assert( 'slot maps to Cloud variant', $decision instanceof RWGC_Decision_Result && 'var_uk' === $decision->variant_for_slot( $slot_id ) );
rwgc_rd_assert( 'visitor render still has zero Cloud HTTP', 0 === RWGC_Cloud_Http::attempt_count() && 0 === $GLOBALS['rwgc_http_calls'] );

$html = reactwoo_render_experience_slot( $slot_id, '<p>NATIVE</p>', $decision );
rwgc_rd_assert( 'runtime slot overlay renders variant HTML', false !== strpos( $html, 'GATE-D-UK' ) );
rwgc_rd_assert( 'runtime overlay is not persisted', false === get_option( RWGC_Experience_Slot_Registry::OPTION, false ) || ! isset( get_option( RWGC_Experience_Slot_Registry::OPTION, array() )[ $slot_id ] ) );

$again = apply_filters( 'reactwoo_current_decision_result', null );
rwgc_rd_assert( 'second call reuses memo', $again === $decision );

$GLOBALS['rwgc_test_country'] = 'US';
RWGC_Request_Decision::reset();
$miss = apply_filters( 'reactwoo_current_decision_result', null );
rwgc_rd_assert(
	'non-matching country keeps default variant empty',
	$miss instanceof RWGC_Decision_Result && '' === $miss->variant_for_slot( $slot_id )
);
$miss_html = reactwoo_render_experience_slot( $slot_id, '<p>NATIVE</p>', $miss );
rwgc_rd_assert( 'non-match keeps native content', false !== strpos( $miss_html, 'NATIVE' ) );

$GLOBALS['rwgc_test_country'] = 'GB';
RWGC_Cloud_Credentials::store( 'site_other', 'secret_other', 'https://decision.example/api/v1' );
RWGC_Request_Decision::reset();
$foreign = apply_filters( 'reactwoo_current_decision_result', null );
rwgc_rd_assert( 'connected to another site does not apply foreign manifest', null === $foreign );

RWGC_Cloud_Credentials::clear();
RWGC_Request_Decision::reset();
$offline = apply_filters( 'reactwoo_current_decision_result', null );
rwgc_rd_assert(
	'disconnect still applies last cache (Gate D)',
	$offline instanceof RWGC_Decision_Result && 'var_uk' === $offline->variant_for_slot( $slot_id )
);

RWGC_Cloud_Manifest_Store::clear();
RWGC_Request_Decision::reset();
$GLOBALS['rwgc_test_country'] = 'GB';
$cleared = apply_filters( 'reactwoo_current_decision_result', null );
rwgc_rd_assert( 'cleared cache returns null (Cloud-off with no last manifest)', null === $cleared );

RWGC_Cloud_Http::end_visitor_render();

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll request-decision smoke tests passed.\n";
exit( 0 );
