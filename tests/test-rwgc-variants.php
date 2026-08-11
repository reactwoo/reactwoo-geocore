<?php
/**
 * Variant Engine smoke tests (WP9) — every fallback scenario + Gate C.
 *
 * Usage: php tests/test-rwgc-variants.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['rwgc_test_options'] = array();
$GLOBALS['rwgc_test_filters'] = array();

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['rwgc_test_options'] ) ? $GLOBALS['rwgc_test_options'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	function update_option( $key, $value ) {
		$GLOBALS['rwgc_test_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'wp_rand' ) ) {
	/**
	 * @param int $min Min.
	 * @param int $max Max.
	 * @return int
	 */
	function wp_rand( $min = 0, $max = 0 ) {
		return mt_rand( (int) $min, (int) $max );
	}
}
if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @return void
	 */
	function do_action() {}
}
if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @return true
	 */
	function add_action() {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @param string   $hook Hook.
	 * @param callable $cb Callback.
	 * @param int      $prio Priority.
	 * @param int      $args Args.
	 * @return true
	 */
	function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
		$GLOBALS['rwgc_test_filters'][ $hook ][] = array(
			'cb'   => $cb,
			'args' => $args,
		);
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Args.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['rwgc_test_filters'][ $hook ] ) ) {
			return $value;
		}
		foreach ( $GLOBALS['rwgc_test_filters'][ $hook ] as $entry ) {
			$cb   = $entry['cb'];
			$n    = (int) $entry['args'];
			$call = array_merge( array( $value ), $args );
			$call = array_slice( $call, 0, max( 1, $n ) );
			$value = call_user_func_array( $cb, $call );
		}
		return $value;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * @param string $t Text.
	 * @return string
	 */
	function esc_html( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param string $t Text.
	 * @return string
	 */
	function esc_attr( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * @param string $t URL.
	 * @return string
	 */
	function esc_url( $t ) {
		return (string) $t;
	}
}

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__ ) . '/includes/decision/class-rwgc-decision.php';
RWGC_Decision::load();
require_once dirname( __DIR__ ) . '/includes/slots/class-rwgc-experience-slots.php';
RWGC_Experience_Slots::load();
require_once dirname( __DIR__ ) . '/includes/components/class-rwgc-components.php';
RWGC_Components::load();
RWGC_Component_Library::reset();
RWGC_Component_Library::boot();
require_once dirname( __DIR__ ) . '/includes/variants/class-rwgc-variants.php';
RWGC_Variants::load();
RWGC_Variants::init();

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_var_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

RWGC_Experience_Slot_Registry::reset_cache();
RWGC_Variant_Store::reset_cache();
RWGC_Variant_Diagnostics::reset();

$slot_reg = reactwoo_register_experience_slot(
	array(
		'name'          => 'Homepage Hero',
		'adapter'       => 'elementor',
		'page'          => '/',
		'variant_types' => array( 'content', 'reactwoo_component', 'native_reference' ),
		'metadata'      => array( 'binding_key' => 'test:hero' ),
	)
);
$slot_id = $slot_reg['slot']->id();
$slot    = reactwoo_get_experience_slot( $slot_id );
$default = '<div class="native-hero">DEFAULT HERO</div>';

// Register variants.
reactwoo_register_variant(
	array(
		'id'      => 'var_content',
		'type'    => 'content',
		'payload' => array( 'html' => '<div class="content-hero">CONTENT HERO</div>' ),
	)
);
reactwoo_register_variant(
	array(
		'id'        => 'var_component',
		'type'      => 'reactwoo_component',
		'component' => 'hero',
		'props'     => array(
			'headline'  => 'COMPONENT HERO',
			'cta_label' => 'Go',
			'cta_url'   => 'https://example.com',
		),
	)
);
reactwoo_register_variant(
	array(
		'id'               => 'var_native',
		'type'             => 'native_reference',
		'native_reference' => 'test:native_hero',
	)
);
reactwoo_register_variant(
	array(
		'id'        => 'var_bad_component',
		'type'      => 'reactwoo_component',
		'component' => 'does_not_exist',
		'props'     => array(),
	)
);
reactwoo_register_variant(
	array(
		'id'      => 'var_empty_content',
		'type'    => 'content',
		'payload' => array( 'html' => '' ),
	)
);

add_filter(
	'reactwoo_resolve_native_reference',
	static function ( $html, $ref ) {
		if ( 'test:native_hero' === $ref ) {
			return '<div class="native-ref">NATIVE REF HERO</div>';
		}
		return $html;
	},
	10,
	2
);

// --- Fallback scenarios ---
$html = reactwoo_render_variant( 'missing_id', $default, $slot );
rwgc_var_assert( 'missing → default', $default === $html );
rwgc_var_assert( 'diag missing', RWGC_Variant_Diagnostics::count_code( 'missing' ) >= 1 );

$html = reactwoo_render_variant( 'default', $default, $slot );
rwgc_var_assert( 'explicit default → default', $default === $html );

$html = reactwoo_render_variant( 'var_bad_component', $default, $slot );
rwgc_var_assert( 'incompatible component → default', $default === $html );
rwgc_var_assert( 'diag incompatible', RWGC_Variant_Diagnostics::count_code( 'incompatible' ) >= 1 );

$html = reactwoo_render_variant( 'var_empty_content', $default, $slot );
rwgc_var_assert( 'empty content → default', $default === $html );

// Restrict slot types to content only.
$restricted = RWGC_Contract_Experience_Slot::from_array(
	array(
		'id'            => $slot_id,
		'name'          => 'Homepage Hero',
		'adapter'       => 'elementor',
		'variant_types' => array( 'content' ),
	)
);
$html = reactwoo_render_variant( 'var_component', $default, $restricted );
rwgc_var_assert( 'slot-incompatible type → default', $default === $html );

// --- Gate C: same slot, switch variants without page edits ---
$decision_content = new RWGC_Decision_Result( array(), array(), array( $slot_id => 'var_content' ), array(), array(), array(), 1.0 );
$decision_comp    = new RWGC_Decision_Result( array(), array(), array( $slot_id => 'var_component' ), array(), array(), array(), 1.0 );
$decision_native  = new RWGC_Decision_Result( array(), array(), array( $slot_id => 'var_native' ), array(), array(), array(), 1.0 );
$decision_default = new RWGC_Decision_Result( array(), array(), array( $slot_id => 'default' ), array(), array(), array(), 1.0 );

$out_default = reactwoo_render_experience_slot( $slot_id, $default, $decision_default );
$out_content = reactwoo_render_experience_slot( $slot_id, $default, $decision_content );
$out_comp    = reactwoo_render_experience_slot( $slot_id, $default, $decision_comp );
$out_native  = reactwoo_render_experience_slot( $slot_id, $default, $decision_native );

rwgc_var_assert( 'Gate C default', false !== strpos( $out_default, 'DEFAULT HERO' ) );
rwgc_var_assert( 'Gate C content', false !== strpos( $out_content, 'CONTENT HERO' ) );
rwgc_var_assert( 'Gate C component', false !== strpos( $out_comp, 'COMPONENT HERO' ) && false !== strpos( $out_comp, 'data-rw-component="hero"' ) );
rwgc_var_assert( 'Gate C native', false !== strpos( $out_native, 'NATIVE REF HERO' ) );

// Missing decision variant → default via slot renderer.
$out_miss = reactwoo_render_experience_slot(
	$slot_id,
	$default,
	new RWGC_Decision_Result( array(), array(), array( $slot_id => 'nope' ), array(), array(), array(), 1.0 )
);
rwgc_var_assert( 'Gate C missing variant keeps default', false !== strpos( $out_miss, 'DEFAULT HERO' ) );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll variant engine smoke tests passed (Gate C).\n";
exit( 0 );
