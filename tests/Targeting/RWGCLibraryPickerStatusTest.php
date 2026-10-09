<?php
/**
 * Choose-from-library lists published visibility rules only.
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'post_type_exists' ) ) {
	/**
	 * @param string $post_type Post type.
	 * @return bool
	 */
	function post_type_exists( $post_type ) {
		unset( $post_type );
		return false;
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-rule-registry.php';

final class RWGCLibraryPickerStatusTest extends TestCase {

	protected function tearDown(): void {
		$this->setLibraryCache( null );
	}

	public function test_picker_omits_draft_and_trashed_rules(): void {
		$this->setLibraryCache(
			array(
				$this->row( '42', 'Published offer', 'publish' ),
				$this->row( '13797', 'QA draft', 'draft' ),
				$this->row( '8', 'Trashed offer', 'trash' ),
				$this->row( '9', 'Missing status', '' ),
			)
		);

		$picker = RWGC_Rule_Registry::get_library_picker_rows();
		$ids    = array_map(
			static function ( $row ) {
				return (string) $row['id'];
			},
			$picker
		);

		$this->assertSame( array( '42' ), $ids );
		$this->assertSame( 'Published offer', $picker[0]['title'] );
	}

	public function test_builder_registry_still_includes_unpublished_rows(): void {
		$this->setLibraryCache(
			array(
				$this->row( '42', 'Published offer', 'publish' ),
				$this->row( '13797', 'QA draft', 'draft' ),
			)
		);

		$ids = array();
		foreach ( RWGC_Rule_Registry::get_rules_for_builder() as $row ) {
			if ( is_array( $row ) && isset( $row['id'] ) ) {
				$ids[] = (string) $row['id'];
			}
		}

		$this->assertContains( '42', $ids );
		$this->assertContains( '13797', $ids );
	}

	/**
	 * @param string $id     Rule ID.
	 * @param string $title  Title.
	 * @param string $status Post status.
	 * @return array<string, string>
	 */
	private function row( $id, $title, $status ) {
		$row = array(
			'id'    => $id,
			'label' => $title,
			'json'  => '{"mode":"show_if"}',
		);
		if ( '' !== $status ) {
			$row['status'] = $status;
		}
		return $row;
	}

	/**
	 * @param array<int, array<string, mixed>>|null $rows Cached library rows.
	 * @return void
	 */
	private function setLibraryCache( $rows ) {
		$property = new ReflectionProperty( RWGC_Rule_Registry::class, 'rwgc_library_rows_cache' );
		$property->setAccessible( true );
		$property->setValue( null, $rows );
	}
}
