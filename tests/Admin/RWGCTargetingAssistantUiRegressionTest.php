<?php
/**
 * Structural UI regression tests for the Geo Assistant Action Review surface.
 *
 * Guards layout and resolver wiring without a browser runner.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class RWGCTargetingAssistantUiRegressionTest extends TestCase {

	/**
	 * @return string
	 */
	private function assistant_js() {
		$path = dirname( __DIR__, 2 ) . '/admin/js/rwgc-targeting-assistant.js';
		$this->assertFileExists( $path );
		return (string) file_get_contents( $path );
	}

	/**
	 * @return string
	 */
	private function targeting_css() {
		$path = dirname( __DIR__, 2 ) . '/admin/css/rwgc-targeting.css';
		$this->assertFileExists( $path );
		return (string) file_get_contents( $path );
	}

	/**
	 * @param string $js     Assistant script source.
	 * @param string $start  Function name to slice from.
	 * @param string $end    Function name to slice until.
	 * @return string
	 */
	private function js_between( $js, $start, $end ) {
		$from = strpos( $js, 'function ' . $start );
		$this->assertNotFalse( $from, 'Missing function ' . $start );
		$to = strpos( $js, 'function ' . $end, $from + 1 );
		$this->assertNotFalse( $to, 'Missing function ' . $end );
		return substr( $js, $from, $to - $from );
	}

	public function test_resolution_hub_sticky_right_sidebar_layout(): void {
		$css = $this->targeting_css();

		$this->assertStringContainsString( '.rwgc-resolution-hub', $css );
		$this->assertStringContainsString( 'position: sticky', $css );
		$this->assertStringContainsString( 'top: 96px', $css );
		$this->assertStringContainsString( 'grid-template-columns: minmax(720px, 1fr) 320px', $css );
		$this->assertStringContainsString( '@media (max-width: 1180px)', $css );
	}

	public function test_google_ads_resolver_not_expanded_inside_condition_card(): void {
		$js   = $this->assistant_js();
		$card = $this->js_between( $js, 'renderConditionCard', 'renderConditionRows' );

		$this->assertStringContainsString( "'data-card-action': 'open_resolver'", $card );
		$this->assertStringContainsString( 'renderConditionGroupChildren', $card );
		$this->assertStringNotContainsString( 'trafficOptionButton', $card );
		$this->assertStringNotContainsString( 'condition-card__options', $card );
		$this->assertStringNotContainsString( 'resolution_options', $card );
		$this->assertStringContainsString( 'rwgc-resolution-drawer', $js );
		$this->assertStringContainsString( "class: 'rwgc-resolution-drawer rwgc-is-hidden'", $js );
	}

	public function test_create_rule_button_hidden_until_executable(): void {
		$js   = $this->assistant_js();
		$rail = $this->js_between( $js, 'renderRail', 'jumpToCard' );

		$this->assertStringContainsString( 'function responseCanExecute', $js );
		$this->assertStringContainsString( 'remainingResolutions( proposal )', $js );
		$this->assertStringContainsString( 'remaining > 0', $rail );
		$this->assertStringContainsString( 'responseCanExecute( proposal )', $rail );
		$this->assertStringContainsString( "'data-card-action': 'create_setup'", $rail );
		$this->assertStringContainsString( "'data-card-action': 'resolve_items'", $rail );

		$resolve_pos = strpos( $rail, 'resolve_items' );
		$create_pos  = strpos( $rail, 'create_setup' );
		$this->assertNotFalse( $resolve_pos );
		$this->assertNotFalse( $create_pos );
		$this->assertLessThan( $create_pos, $resolve_pos, 'Resolve CTA should be evaluated before Create rule.' );
	}

	public function test_popup_target_actions_are_popup_specific(): void {
		$js   = $this->assistant_js();
		$card = $this->js_between( $js, 'renderCard', 'renderActionCards' );

		$this->assertStringContainsString( 'choose_popup', $card );
		$this->assertStringContainsString( 'search_popups', $card );
		$this->assertStringContainsString( 'popupTargetHint', $card );
		$this->assertStringContainsString( 'remove_action', $card );
		$this->assertStringContainsString( "choose_popup: 'open_resolver'", $card );
	}

	public function test_create_rule_resolver_journey_wiring(): void {
		$js = $this->assistant_js();

		$this->assertStringContainsString( 'RESOLVER_FIELD_ORDER', $js );
		$this->assertStringContainsString( 'openPopupTargetResolver', $js );
		$this->assertStringContainsString( 'recalculateClientActionState', $js );
		$this->assertStringContainsString( 'openFirstUnresolvedDrawer', $js );

		$drawer = $this->js_between( $js, 'openPopupTargetResolver', 'openFirstUnresolvedDrawer' );
		$this->assertStringContainsString( 'resolvePopupTarget', $drawer );
		$this->assertStringContainsString( 'search_popups', $drawer );
		$this->assertStringContainsString( 'remove_action', $drawer );
	}

}
