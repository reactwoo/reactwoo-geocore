<?php
/**
 * ReactWoo Component System smoke tests (WP8).
 *
 * Usage: php tests/test-rwgc-components.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

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

require_once dirname( __DIR__ ) . '/includes/components/class-rwgc-components.php';
RWGC_Components::load();
RWGC_Component_Library::reset();
RWGC_Component_Library::boot();

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_comp_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

$expected = array( 'hero', 'cta', 'promotion_banner', 'notice', 'product_rail', 'popup' );
$all      = RWGC_Component_Registry::all();
rwgc_comp_assert( 'six library types registered', count( $all ) === 6 );
foreach ( $expected as $type ) {
	rwgc_comp_assert( "definition:$type", null !== reactwoo_get_component_definition( $type ) );
}

$hero = reactwoo_render_component(
	'hero',
	array(
		'headline'    => 'Welcome',
		'subheadline' => 'Sub',
		'cta_label'   => 'Shop',
		'cta_url'     => 'https://example.com/shop',
	)
);
rwgc_comp_assert( 'hero has data-rw-component', false !== strpos( $hero, 'data-rw-component="hero"' ) );
rwgc_comp_assert( 'hero has rw- namespace class', false !== strpos( $hero, 'rw-component--hero' ) );
rwgc_comp_assert( 'hero escapes content', false !== strpos( $hero, 'Welcome' ) && false === strpos( $hero, '<script>' ) );

$popup = reactwoo_render_component(
	'popup',
	array(
		'title'   => 'Offer',
		'content' => 'Details here',
	)
);
rwgc_comp_assert( 'popup works without JS (details)', false !== strpos( $popup, '<details' ) );

$missing = reactwoo_render_component( 'not_a_real_type', array() );
rwgc_comp_assert( 'unknown type returns empty', '' === $missing );

$xss = reactwoo_render_component(
	'notice',
	array(
		'text' => '<script>alert(1)</script>',
		'tone' => 'info',
	)
);
rwgc_comp_assert( 'notice escapes HTML', false === strpos( $xss, '<script>alert' ) );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll component system smoke tests passed.\n";
exit( 0 );
