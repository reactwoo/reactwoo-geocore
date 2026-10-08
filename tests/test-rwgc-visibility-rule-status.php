<?php
/**
 * Editor status lookup for library rules: published, draft, trashed, deleted, nonexistent.
 *
 * The lookup is read-only. It must not remove a stale reference.
 *
 * Usage: php tests/test-rwgc-visibility-rule-status.php
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'RWGC_VERSION', '0.0-test' );

$GLOBALS['rwgc_status_posts']      = array();
$GLOBALS['rwgc_status_meta']       = array();
$GLOBALS['rwgc_status_post_calls'] = 0;
$GLOBALS['rwgc_status_queries']    = array();
$GLOBALS['rwgc_status_can_edit']   = array();

if ( ! class_exists( 'WP_Post', false ) ) {
	class WP_Post {
		/** @var int */
		public $ID = 0;
		/** @var string */
		public $post_status = '';
		/** @var string */
		public $post_title = '';
		/** @var string */
		public $post_type = '';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return is_scalar( $str ) ? (string) $str : '';
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return (int) abs( (float) $maybeint );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		unset( $hook, $args );
		return $value;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id ) {
		$id = (int) $id;
		return isset( $GLOBALS['rwgc_status_posts'][ $id ] ) ? $GLOBALS['rwgc_status_posts'][ $id ] : null;
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key, $single = false ) {
		unset( $single );
		$id  = (int) $id;
		$key = (string) $key;
		if ( ! isset( $GLOBALS['rwgc_status_meta'][ $id ][ $key ] ) ) {
			return '';
		}
		return $GLOBALS['rwgc_status_meta'][ $id ][ $key ];
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @param string $cap  Capability.
	 * @param mixed  ...$args Extra arguments.
	 * @return bool
	 */
	function current_user_can( $cap, ...$args ) {
		if ( 'edit_post' === $cap ) {
			$id = isset( $args[0] ) ? (int) $args[0] : 0;
			return ! empty( $GLOBALS['rwgc_status_can_edit'][ $id ] );
		}
		return false;
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * Honour post_type the way WordPress does: `any` (and a missing type) drops
	 * types registered with exclude_from_search, including rwgc_visibility_rule.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, WP_Post>
	 */
	function get_posts( $args = array() ) {
		++$GLOBALS['rwgc_status_post_calls'];
		$GLOBALS['rwgc_status_queries'][]  = $args;
		$GLOBALS['rwgc_status_last_query'] = $args;
		$wanted   = isset( $args['post__in'] ) ? array_map( 'intval', (array) $args['post__in'] ) : array();
		$statuses = isset( $args['post_status'] ) ? (array) $args['post_status'] : array( 'publish' );
		$type_arg = $args['post_type'] ?? '';
		$types    = is_array( $type_arg ) ? $type_arg : array( (string) $type_arg );
		$any      = in_array( 'any', $types, true ) || in_array( '', $types, true );
		$out      = array();
		foreach ( $wanted as $id ) {
			if ( ! isset( $GLOBALS['rwgc_status_posts'][ $id ] ) ) {
				continue;
			}
			$post = $GLOBALS['rwgc_status_posts'][ $id ];
			if ( ! in_array( (string) $post->post_status, $statuses, true ) ) {
				continue;
			}
			if ( $any && RWGC_Visibility_Rule_CPT::POST_TYPE === (string) $post->post_type ) {
				continue;
			}
			if ( ! $any && ! in_array( (string) $post->post_type, $types, true ) ) {
				continue;
			}
			$out[] = $post;
		}
		return $out;
	}
}

require_once dirname( __DIR__ ) . '/includes/functions-rwgc.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-visibility-rule-cpt.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-target-operators.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-visibility-rule-repository.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-variant-rule-applications.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-visibility-rule-editor-status.php';

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok    Result.
 * @return void
 */
function rwgc_status_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

/**
 * @param int    $id     Post ID.
 * @param string $status Post status.
 * @param string $type   Post type.
 * @param string $source Source type meta.
 * @param bool   $with_rules Include a portable rule set.
 * @return void
 */
function rwgc_status_seed( $id, $status, $type = '', $source = '', $with_rules = true ) {
	if ( '' === $type ) {
		$type = RWGC_Visibility_Rule_CPT::POST_TYPE;
	}
	$post              = new WP_Post();
	$post->ID          = $id;
	$post->post_status = $status;
	$post->post_type   = $type;
	$post->post_title  = 'Rule ' . $id;
	$GLOBALS['rwgc_status_posts'][ $id ] = $post;
	$meta = array(
		RWGC_Variant_Rule_Applications::META_SOURCE_TYPE => $source,
		RWGC_Variant_Rule_Applications::META_LIFECYCLE   => '',
	);
	if ( $with_rules ) {
		$meta[ RWGC_Visibility_Rule_CPT::META_PORTABLE ] = wp_json_encode(
			array(
				'enabled' => true,
				'mode'    => 'show_if',
				'match'   => 'any',
				'rules'   => array(
					array(
						'id'         => 'r1',
						'label'      => 'UK',
						'match'      => 'any',
						'conditions' => array(
							array(
								'type'     => 'country',
								'operator' => 'in',
								'value'    => array( 'GB' ),
							),
						),
					),
				),
			)
		);
	}
	$GLOBALS['rwgc_status_meta'][ $id ] = $meta;
}

rwgc_status_seed( 501, 'publish' );
rwgc_status_seed( 502, 'draft' );
rwgc_status_seed( 503, 'trash' );
rwgc_status_seed( 505, 'private' );
rwgc_status_seed( 506, 'publish', 'page' );
rwgc_status_seed( 507, 'publish', '', '', false );
rwgc_status_seed( 508, 'publish', '', 'page_variant' );
$GLOBALS['rwgc_status_meta'][508][ RWGC_Variant_Rule_Applications::META_LIFECYCLE ] = 'archived';

$before = array(
	501 => $GLOBALS['rwgc_status_posts'][501]->post_status,
	502 => $GLOBALS['rwgc_status_posts'][502]->post_status,
	503 => $GLOBALS['rwgc_status_posts'][503]->post_status,
);

$GLOBALS['rwgc_status_post_calls'] = 0;
$GLOBALS['rwgc_status_queries']    = array();
$rows = RWGC_Visibility_Rule_Editor_Status::lookup(
	array( 501, '502', 503, 9999, 0, 'nope', 505, 506, 507, 508, 501 )
);

$rule_query = null;
foreach ( $GLOBALS['rwgc_status_queries'] as $query ) {
	$in = isset( $query['post__in'] ) ? array_map( 'intval', (array) $query['post__in'] ) : array();
	if ( in_array( 501, $in, true ) ) {
		$rule_query = $query;
		break;
	}
}
$rule_type = $rule_query['post_type'] ?? null;
rwgc_status_assert( 'rule lookup query is present', is_array( $rule_query ) );
rwgc_status_assert( 'rule lookup post_type is not any', 'any' !== $rule_type );
rwgc_status_assert( 'rule lookup post_type is not missing', null !== $rule_type && '' !== $rule_type );
rwgc_status_assert(
	'rule lookup queries the visibility rule post type',
	RWGC_Visibility_Rule_CPT::POST_TYPE === $rule_type
);
$query_statuses = isset( $rule_query['post_status'] ) ? (array) $rule_query['post_status'] : array();
foreach ( array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'auto-draft' ) as $needed_status ) {
	rwgc_status_assert( 'rule lookup includes status ' . $needed_status, in_array( $needed_status, $query_statuses, true ) );
}

rwgc_status_assert( 'published status', isset( $rows[501]['status'] ) && 'published' === $rows[501]['status'] );
rwgc_status_assert( 'published is resolvable', ! empty( $rows[501]['resolvable'] ) );
rwgc_status_assert( 'published has no warning payload', empty( $rows[501]['messages'] ) );
rwgc_status_assert( 'published rule returns its title', 'Rule 501' === ( $rows[501]['title'] ?? '' ) );

rwgc_status_assert( 'draft status', isset( $rows[502]['status'] ) && 'draft' === $rows[502]['status'] );
rwgc_status_assert( 'draft is not resolvable', empty( $rows[502]['resolvable'] ) );
rwgc_status_assert( 'draft title is hidden without edit_post', '' === ( $rows[502]['title'] ?? 'x' ) );
rwgc_status_assert(
	'draft show-if warning uses the rule id and says content stays hidden',
	isset( $rows[502]['messages']['show_if'] )
		&& false !== strpos( $rows[502]['messages']['show_if'], 'The rule #502 was deleted or is unpublished.' )
		&& false === strpos( $rows[502]['messages']['show_if'], 'Rule 502' )
		&& false !== strpos( $rows[502]['messages']['show_if'], 'This content is now hidden for everyone.' )
);
rwgc_status_assert(
	'draft hide-if warning says content is never hidden',
	isset( $rows[502]['messages']['hide_if'] )
		&& false !== strpos( $rows[502]['messages']['hide_if'], 'This content is now never hidden.' )
);

rwgc_status_assert( 'trashed status', isset( $rows[503]['status'] ) && 'trashed' === $rows[503]['status'] );
rwgc_status_assert( 'trashed is not resolvable', empty( $rows[503]['resolvable'] ) );
rwgc_status_assert( 'trashed title is hidden without edit_post', '' === ( $rows[503]['title'] ?? 'x' ) );
rwgc_status_assert( 'private title is hidden without edit_post', '' === ( $rows[505]['title'] ?? 'x' ) );

rwgc_status_assert( 'deleted status', isset( $rows[9999]['status'] ) && 'deleted' === $rows[9999]['status'] );
rwgc_status_assert( 'deleted is not resolvable', empty( $rows[9999]['resolvable'] ) );
rwgc_status_assert(
	'deleted warning uses the rule id',
	isset( $rows[9999]['messages']['show_if'] )
		&& false !== strpos( $rows[9999]['messages']['show_if'], 'The rule #9999 was deleted or is unpublished.' )
);

rwgc_status_assert( 'zero id is nonexistent', isset( $rows[0]['status'] ) && 'nonexistent' === $rows[0]['status'] );
rwgc_status_assert( 'non-numeric id is nonexistent', isset( $rows['nope']['status'] ) && 'nonexistent' === $rows['nope']['status'] );
rwgc_status_assert( 'nonexistent is not resolvable', empty( $rows['nope']['resolvable'] ) );

rwgc_status_assert( 'private rule is unpublished', isset( $rows[505]['status'] ) && 'unpublished' === $rows[505]['status'] );
rwgc_status_assert( 'private rule is not resolvable', empty( $rows[505]['resolvable'] ) );
rwgc_status_assert( 'wrong post type is unresolvable', isset( $rows[506]['status'] ) && 'unresolvable' === $rows[506]['status'] );
rwgc_status_assert( 'published rule with no rule set is not resolvable', isset( $rows[507]['status'] ) && 'published' === $rows[507]['status'] && empty( $rows[507]['resolvable'] ) );

rwgc_status_assert( 'archived page-variant rule is not resolvable', empty( $rows[508]['resolvable'] ) );
rwgc_status_assert( 'archived page-variant flag', ! empty( $rows[508]['page_variant'] ) );
rwgc_status_assert(
	'page-variant warning says visitors see the default page',
	isset( $rows[508]['messages']['variant'] )
		&& false !== strpos( $rows[508]['messages']['variant'], 'Visitors see the default page.' )
);

rwgc_status_assert( 'lookup does not trash a published rule', 'publish' === $GLOBALS['rwgc_status_posts'][501]->post_status );
rwgc_status_assert( 'lookup does not change a draft', $before[502] === $GLOBALS['rwgc_status_posts'][502]->post_status );
rwgc_status_assert( 'lookup does not restore trash', $before[503] === $GLOBALS['rwgc_status_posts'][503]->post_status );
rwgc_status_assert( 'lookup does not invent a deleted post', ! isset( $GLOBALS['rwgc_status_posts'][9999] ) );

$GLOBALS['rwgc_status_can_edit'][502] = true;
$GLOBALS['rwgc_status_can_edit'][503] = true;
$GLOBALS['rwgc_status_can_edit'][505] = true;
$editable = RWGC_Visibility_Rule_Editor_Status::lookup( array( 501, 502, 503, 505 ) );
rwgc_status_assert( 'published title does not depend on edit_post', 'Rule 501' === ( $editable[501]['title'] ?? '' ) );
rwgc_status_assert(
	'draft title is returned when the user can edit that rule',
	'Rule 502' === ( $editable[502]['title'] ?? '' )
		&& isset( $editable[502]['messages']['show_if'] )
		&& false !== strpos( $editable[502]['messages']['show_if'], "The rule 'Rule 502' was deleted or is unpublished." )
);
rwgc_status_assert( 'trashed title is returned when the user can edit that rule', 'Rule 503' === ( $editable[503]['title'] ?? '' ) );
rwgc_status_assert( 'private title is returned when the user can edit that rule', 'Rule 505' === ( $editable[505]['title'] ?? '' ) );

$GLOBALS['rwgc_status_post_calls'] = 0;
$none = RWGC_Visibility_Rule_Editor_Status::lookup( array( 0, 'abc', '' ) );
rwgc_status_assert( 'nonexistent-only lookup skips get_posts', 0 === $GLOBALS['rwgc_status_post_calls'] );
rwgc_status_assert( 'blank ids are ignored', ! isset( $none[''] ) );

$extracted = RWGC_Visibility_Rule_Editor_Status::extract_referenced_ids(
	'{"rwgc_visibility_rule_library":"502","settings":{"rwgc_applied_visibility_rule_id":"88"}}'
	. '<!-- wp:reactwoo-geocore/geo-content {"visibilityRuleLibrary":"41","appliedVisibilityRuleId":"41"} /-->'
);
sort( $extracted );
rwgc_status_assert( 'extracts elementor and block rule ids', array( 41, 88, 502 ) === $extracted );

$page_settings = array(
	'rwgc_visibility_rule_library' => '508',
	'elements'                     => array(
		array(
			'settings' => array(
				'rwgc_applied_visibility_rule_id' => 9999,
			),
		),
	),
);
$from_settings = RWGC_Visibility_Rule_Editor_Status::extract_referenced_ids( $page_settings );
sort( $from_settings );
rwgc_status_assert( 'extracts nested page-setting ids', array( 508, 9999 ) === $from_settings );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll visibility-rule status tests passed.\n";
exit( 0 );
