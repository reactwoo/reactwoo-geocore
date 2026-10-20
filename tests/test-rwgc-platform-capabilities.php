<?php
/**
 * Platform capability registry smoke tests.
 *
 * Usage: php tests/test-rwgc-platform-capabilities.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  ...$args Args.
	 * @return void
	 */
	function do_action( $hook, ...$args ) {
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		return $value;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error for smoke tests.
	 */
	class WP_Error {
		/** @var string */
		public $code;
		/** @var string */
		public $message;

		/**
		 * @param string               $code Code.
		 * @param string               $message Message.
		 * @param array<string, mixed> $data Data.
		 */
		public function __construct( $code, $message, $data = array() ) {
			unset( $data );
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Thing.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @param string $cap Capability.
	 * @return bool
	 */
	function current_user_can( $cap ) {
		unset( $cap );
		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__ ) . '/includes/platform/class-rwgc-platform-capability-registry.php';
require_once dirname( __DIR__ ) . '/includes/platform/functions-reactwoo-capabilities.php';
require_once dirname( __DIR__ ) . '/includes/platform/class-rwgc-platform-capabilities-bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/platform/class-rwgc-wp-abilities-adapter.php';

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_cap_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

RWGC_Platform_Capability_Registry::reset();

rwgc_cap_assert(
	'register condition',
	reactwoo_register_condition(
		'geo.country',
		array(
			'label'    => 'Country',
			'provider' => 'reactwoo-geocore',
		)
	)
);

rwgc_cap_assert( 'has capability', reactwoo_has_capability( 'geo.country' ) );
rwgc_cap_assert( 'has via alias', reactwoo_has_capability( 'country' ) );

$dup = reactwoo_register_condition(
	'geo.country',
	array(
		'label'    => 'Country hijack',
		'provider' => 'evil-plugin',
	)
);
rwgc_cap_assert( 'collision rejected', false === $dup );
rwgc_cap_assert( 'collision recorded', count( RWGC_Platform_Capability_Registry::collisions() ) >= 1 );
rwgc_cap_assert(
	'original provider kept',
	'reactwoo-geocore' === reactwoo_get_capability( 'geo.country' )['provider']
);

reactwoo_register_action(
	'commerce.product.promote',
	array(
		'label'    => 'Promote product',
		'provider' => 'reactwoo-geo-commerce',
	)
);
rwgc_cap_assert( 'action registered', reactwoo_has_capability( 'commerce.product.promote' ) );

reactwoo_register_goal(
	'goal.purchase',
	array(
		'label'    => 'Purchase',
		'provider' => 'reactwoo-geo-optimise',
	)
);
rwgc_cap_assert( 'goal registered', reactwoo_has_capability( 'goal.purchase' ) );

rwgc_cap_assert(
	'ability name strips dots',
	'reactwoo/geo-country' === RWGC_WP_Abilities_Adapter::ability_name( 'geo.country' )
);
rwgc_cap_assert(
	'ability name strips underscores',
	'reactwoo/geo-country-group' === RWGC_WP_Abilities_Adapter::ability_name( 'geo.country_group' )
);
rwgc_cap_assert(
	'ability name add-to-cart',
	'reactwoo/goal-add-to-cart' === RWGC_WP_Abilities_Adapter::ability_name( 'goal.add_to_cart' )
);
rwgc_cap_assert(
	'ability name matches WP 6.9 pattern',
	(bool) preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/', RWGC_WP_Abilities_Adapter::ability_name( 'weather.precipitation_probability' ) )
);

rwgc_cap_assert( 'abilities unsupported here', false === RWGC_WP_Abilities_Adapter::is_supported() );

$tool_names = array_keys( RWGC_WP_Abilities_Adapter::tools() );
rwgc_cap_assert(
	'mcp tools are the three admin reads',
	array( 'reactwoo/list-capabilities', 'reactwoo/describe-capability', 'reactwoo/site-context' ) === $tool_names
);
rwgc_cap_assert(
	'price adjust is not an mcp tool',
	! in_array( 'reactwoo/commerce-price-adjust', $tool_names, true )
);
$list_schema = RWGC_WP_Abilities_Adapter::input_schema( 'reactwoo/list-capabilities' );
rwgc_cap_assert( 'list tool schema is an object', isset( $list_schema['type'] ) && 'object' === $list_schema['type'] );

$listed     = RWGC_WP_Abilities_Adapter::list_capabilities( null );
$listed_ids = array();
$has_callable = false;
foreach ( $listed as $row ) {
	$listed_ids[] = $row['id'];
	if ( isset( $row['availability'] ) ) {
		$has_callable = true;
	}
}
rwgc_cap_assert( 'list row has no callable', ! $has_callable );
rwgc_cap_assert( 'list includes promote action', in_array( 'commerce.product.promote', $listed_ids, true ) );
rwgc_cap_assert( 'list row has entitlement key', isset( $listed[0]['entitlement'] ) && isset( $listed[0]['available'] ) );

$described = RWGC_WP_Abilities_Adapter::describe_capability( 'geo.country' );
rwgc_cap_assert(
	'describe returns schema fields',
	is_array( $described ) && 'geo.country' === $described['id'] && isset( $described['input_schema'] ) && 'Country' === $described['label']
);
$missing = RWGC_WP_Abilities_Adapter::describe_capability( 'not.a.capability' );
rwgc_cap_assert( 'describe unknown is an error', is_wp_error( $missing ) );

$site = RWGC_WP_Abilities_Adapter::site_context();
rwgc_cap_assert(
	'site context is a safe snapshot',
	isset( $site['geo_ready'], $site['satellites'], $site['active_experiences'] ) && is_array( $site['active_experiences'] )
);

$executed = RWGC_WP_Abilities_Adapter::execute( 'reactwoo/list-capabilities', array( 'type' => 'action' ) );
rwgc_cap_assert(
	'execute list filters to actions',
	is_array( $executed ) && isset( $executed['capabilities'][0]['id'] ) && 'commerce.product.promote' === $executed['capabilities'][0]['id']
);

$report = RWGC_Platform_Capability_Registry::export_for_report();
rwgc_cap_assert( 'export count', count( $report ) >= 3 );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll platform capability smoke tests passed.\n";
exit( 0 );
