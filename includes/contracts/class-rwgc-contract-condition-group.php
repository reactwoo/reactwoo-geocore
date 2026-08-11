<?php
/**
 * Nested AND/OR condition group.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Condition tree node: `{ "all": [...] }` or `{ "any": [...] }`.
 */
final class RWGC_Contract_Condition_Group extends RWGC_Contract {

	const MATCH_ALL = 'all';
	const MATCH_ANY = 'any';

	/** @var string */
	private $match;
	/** @var list<RWGC_Contract_Condition|RWGC_Contract_Condition_Group> */
	private $items;

	/**
	 * @param string                                                       $match Match mode.
	 * @param list<RWGC_Contract_Condition|RWGC_Contract_Condition_Group> $items Children.
	 * @param array<string, mixed>                                         $extras Extras.
	 */
	private function __construct( $match, array $items, array $extras ) {
		$this->match  = $match;
		$this->items  = $items;
		$this->extras = $extras;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return self
	 * @throws RWGC_Contract_Exception On invalid input.
	 */
	public static function from_array( array $data ) {
		$has_all = isset( $data['all'] ) && is_array( $data['all'] );
		$has_any = isset( $data['any'] ) && is_array( $data['any'] );
		if ( $has_all === $has_any ) {
			throw new RWGC_Contract_Exception( 'Condition group must define exactly one of "all" or "any".' );
		}

		$match = $has_all ? self::MATCH_ALL : self::MATCH_ANY;
		$raw   = $has_all ? $data['all'] : $data['any'];
		list( , $extras ) = RWGC_Schema::partition( $data, array( 'all', 'any' ) );

		$items = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				throw new RWGC_Contract_Exception( 'Condition group items must be objects.' );
			}
			if ( isset( $row['all'] ) || isset( $row['any'] ) ) {
				$items[] = self::from_array( $row );
			} else {
				$items[] = RWGC_Contract_Condition::from_array( $row );
			}
		}

		return new self( $match, $items, $extras );
	}

	/** @return string */
	public function match() {
		return $this->match;
	}

	/**
	 * @return list<RWGC_Contract_Condition|RWGC_Contract_Condition_Group>
	 */
	public function items() {
		return $this->items;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		$serialized = array();
		foreach ( $this->items as $item ) {
			$serialized[] = $item->to_array();
		}
		return $this->with_extras(
			array(
				$this->match => $serialized,
			)
		);
	}
}
