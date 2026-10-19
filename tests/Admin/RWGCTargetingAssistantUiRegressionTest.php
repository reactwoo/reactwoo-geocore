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
		return $this->source_between( $js, 'function ' . $start, 'function ' . $end );
	}

	/**
	 * Slice source from an exact marker up to the next exact marker.
	 *
	 * @param string $js    Script source.
	 * @param string $start Exact start marker.
	 * @param string $end   Exact end marker.
	 * @return string
	 */
	private function source_between( $js, $start, $end ) {
		$from = strpos( $js, $start );
		$this->assertNotFalse( $from, 'Missing marker ' . $start );
		$to = strpos( $js, $end, $from + strlen( $start ) );
		$this->assertNotFalse( $to, 'Missing marker ' . $end );
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
		$this->assertStringContainsString( 'rwgc-modal-overlay', $js );
		$this->assertStringContainsString( "class: 'rwgc-modal-overlay rwgc-resolution-drawer rwgc-is-hidden'", $js );
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
		$js      = $this->assistant_js();
		$card    = $this->js_between( $js, 'renderCard', 'renderActionCards' );
		$field   = $this->js_between( $js, 'fieldBlock', 'dashicon' );
		$buttons = $this->js_between( $js, 'fieldActionButton', 'targetPicker' );

		$this->assertStringContainsString( 'resolve_popup', $card );
		$this->assertStringContainsString( 'remove_action', $card );
		$this->assertStringContainsString( "t.type === 'popup'", $card );
		$this->assertStringContainsString( 'isPopup: t.type === \'popup\'', $card );
		// Generic popup search is filtered out of the actions offered on the card.
		$this->assertStringContainsString( "act !== 'search_popups'", $card );

		$this->assertStringContainsString( 'popupTargetHint', $field );
		$this->assertStringContainsString( 'opts.isPopup && opts.actions && opts.actions.length', $field );

		$this->assertStringContainsString( "resolve_popup: 'open_resolver'", $buttons );
		$this->assertStringNotContainsString( 'search_popups', $buttons );
	}

	public function test_create_rule_resolver_journey_wiring(): void {
		$js = $this->assistant_js();

		$this->assertStringContainsString( 'RESOLVER_FIELD_ORDER', $js );
		$this->assertStringContainsString( 'openPopupTargetResolver', $js );
		$this->assertStringContainsString( 'renderPopupResolverStart', $js );
		$this->assertStringContainsString( 'renderPopupResolverChoose', $js );
		$this->assertStringContainsString( 'renderPopupResolverCreate', $js );
		$this->assertStringContainsString( 'renderPopupResolverConfirmRemove', $js );
		$this->assertStringContainsString( 'recalculateClientActionState', $js );
		$this->assertStringContainsString( 'openFirstUnresolvedDrawer', $js );
		$this->assertStringContainsString( 'targetSearchUrl', $js );
		$this->assertStringContainsString( 'targetCreateUrl', $js );

		$opener = $this->js_between( $js, 'openPopupTargetResolver', 'openFirstUnresolvedDrawer' );
		$this->assertStringContainsString( 'renderPopupResolver()', $opener );
		$this->assertStringNotContainsString( 'search_popups', $opener );
		$this->assertStringNotContainsString( 'popupTargetResolverOptions', $opener );

		$start = $this->js_between( $js, 'renderPopupResolverStart', 'renderPopupResolverCreate' );
		$this->assertStringContainsString( 'popupCreateNew', $start );
		$this->assertStringContainsString( 'popupChooseExisting', $start );
		$this->assertStringContainsString( 'goto_confirm_remove', $start );
		$this->assertStringNotContainsString( 'search_popups', $start );
		$this->assertStringNotContainsString( 'popupTargetResolverOptions', $start );
	}

	public function test_popup_resolver_modal_button_styles(): void {
		$css = $this->targeting_css();

		$this->assertStringContainsString( '.rwgc-modal-overlay', $css );
		$this->assertStringContainsString( 'align-items: center', $css );
		$this->assertStringContainsString( 'z-index: 100000', $css );
		$this->assertStringContainsString( '.rwgc-modal-actions', $css );
		$this->assertStringContainsString( '.rwgc-modal-footer', $css );
		$this->assertStringContainsString( '.rwgc-popup-resolver__error', $css );
		$this->assertStringContainsString( '.rwgc-button--primary', $css );
		$this->assertStringContainsString( '.rwgc-button--danger', $css );
		$this->assertStringContainsString( '.rwgc-popup-resolver__row', $css );
	}

	public function test_popup_resolver_start_options(): void {
		$js     = $this->assistant_js();
		$start  = $this->js_between( $js, 'renderPopupResolverStart', 'renderPopupResolverCreate' );

		$this->assertStringContainsString( 'goto_create', $start );
		$this->assertStringContainsString( 'goto_choose', $start );
		$this->assertStringContainsString( 'goto_confirm_remove', $start );
		$this->assertStringContainsString( 'rwgcModalFooter( [], true )', $start );
		$this->assertStringNotContainsString( 'search_popups', $start );
		$this->assertStringNotContainsString( 'rwgc-popup-search-input', $start );

		$footer = $this->js_between( $js, 'rwgcModalFooter', 'proposalIdForRequest' );
		$this->assertStringContainsString( 'includeCancel !== false', $footer );
		$this->assertStringContainsString( "'cancel'", $footer );
	}

	public function test_create_popup_auto_selects_target(): void {
		$js = $this->assistant_js();

		$this->assertStringContainsString( 'updateActionTargetFromResolution', $js );
		$this->assertStringContainsString( "card.target.status = 'matched'", $js );
		$this->assertStringContainsString( 'created_by_assistant', $js );
		$this->assertStringContainsString( 'attach_to_action', $js );
		$this->assertStringContainsString( 'syncProposalPayload', $js );
		$this->assertStringContainsString( 'recalculateClientActionState', $js );

		$create = $this->js_between( $js, 'submitCreatePopup', 'submitChoosePopup' );
		$this->assertStringNotContainsString( 'create_setup', $create );
		$this->assertStringNotContainsString( 'executeUrl', $create );
		$this->assertStringNotContainsString( 'window.alert', $create );
		$this->assertStringContainsString( 'proposalIdForRequest', $create );
		$this->assertStringContainsString( 'setPopupCreateError', $create );
		$this->assertStringContainsString( 'applyPopupTargetResolution', $create );

		$setter = $this->js_between( $js, 'setPopupCreateError', 'isPopupDuplicateResponse' );
		$this->assertStringContainsString( 'renderPopupResolver()', $setter );

		$render = $this->js_between( $js, 'renderPopupResolverCreate', 'renderPopupResolverCreateError' );
		$this->assertStringContainsString( 'renderPopupResolverCreateError( $panel, pr )', $render );
	}

	public function test_modal_mounted_to_body_and_centered(): void {
		$js  = $this->assistant_js();
		$css = $this->targeting_css();

		$this->assertStringContainsString( ".append( \$drawer )", $js );
		$this->assertStringContainsString( 'openResolutionDrawerShell', $js );
		$overlay_pos = strpos( $css, '.rwgc-modal-overlay' );
		$this->assertNotFalse( $overlay_pos );
		$overlay_chunk = substr( $css, $overlay_pos, 420 );
		$this->assertStringContainsString( 'position: fixed', $overlay_chunk );
		$this->assertStringContainsString( 'align-items: center', $overlay_chunk );
		$this->assertStringContainsString( 'justify-content: center', $overlay_chunk );
		$this->assertStringContainsString( 'z-index: 100000', $overlay_chunk );
	}

	public function test_remove_action_link_has_card_footer_padding(): void {
		$js  = $this->assistant_js();
		$css = $this->targeting_css();
		$card = $this->js_between( $js, 'renderCard', 'renderActionCards' );

		$this->assertStringContainsString( 'rwgc-action-card__footer', $card );
		$this->assertStringContainsString( 'rwgc-action-card__footer-left', $card );
		$this->assertStringContainsString( 'rwgc-action-card__footer-right', $card );
		$this->assertStringContainsString( 'rwgc-link-button--danger', $card );
		$this->assertStringContainsString( 'rwgc-button--primary', $card );
		$this->assertStringContainsString( 'create_setup', $card );
		$this->assertStringContainsString( 'review_items', $card );
		$this->assertStringNotContainsString( 'rwgc-geo-card__row-actions', $card );
		$this->assertStringContainsString( '.rwgc-action-card__footer', $css );
		$this->assertStringContainsString( 'padding: 14px 20px 18px', $css );
	}

	public function test_popup_target_auto_match_helpers(): void {
		$js = $this->assistant_js();

		$this->assertStringContainsString( 'normalizePopupTargetLabel', $js );
		$this->assertStringContainsString( 'autoMatchPopupTargets', $js );
		$this->assertStringContainsString( 'findExactPopupMatch', $js );
		$this->assertStringContainsString( 'popupTargetRegistry', $js );
		$this->assertStringContainsString( "ctx.targets = { popups: popups }", $js );
		$this->assertStringContainsString( 'cardChangePopup', $js );
	}

	public function test_setup_status_label_ready_when_resolved(): void {
		$js = $this->assistant_js();
		$fn = $this->js_between( $js, 'setupStatusLabel', 'normalizePopupTargetLabel' );

		$this->assertStringContainsString( 'remainingResolutions', $fn );
		$this->assertStringContainsString( 'responseCanExecute', $fn );
		$this->assertStringContainsString( 'cardReady', $fn );
		$this->assertStringNotContainsString( "return i18n.statusPending || 'Pending confirmation'", $fn );
	}

	public function test_popup_target_rest_routes_registered(): void {
		$path = dirname( __DIR__, 2 ) . '/includes/class-rwgc-rest.php';
		$this->assertFileExists( $path );
		$rest = (string) file_get_contents( $path );
		$this->assertStringContainsString( '/targets/search', $rest );
		$this->assertStringContainsString( '/targets/create', $rest );
		$this->assertStringContainsString( 'get_targets_search', $rest );
		$this->assertStringContainsString( 'post_targets_create', $rest );
		$this->assertStringContainsString( 'attach_to_action', $rest );
		$this->assertStringContainsString( 'force_create', $rest );
		$this->assertStringContainsString( 'target_create_failure', $rest );
		$this->assertStringContainsString( "'code'    =>", $rest );
	}

	public function test_target_service_duplicate_guard(): void {
		$path = dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-assistant-target-service.php';
		$this->assertFileExists( $path );
		$service = (string) file_get_contents( $path );
		$this->assertStringContainsString( 'find_similar_popups', $service );
		$this->assertStringContainsString( 'possible_duplicate', $service );
		$this->assertStringContainsString( 'duplicate_found', $service );
	}

	public function test_google_ads_mapping_resolver_uses_card_options(): void {
		$js  = $this->assistant_js();
		$css = $this->targeting_css();

		$this->assertStringContainsString( 'renderGoogleAdsMappingDrawer', $js );
		$this->assertStringContainsString( 'googleAdsMappingMeta', $js );
		$this->assertStringContainsString( 'defaultGoogleAdsMappingKey', $js );
		$this->assertStringContainsString( 'partitionGoogleAdsOptions', $js );
		$this->assertStringContainsString( 'renderGoogleAdsValidUrlSection', $js );
		$this->assertStringContainsString( 'renderUrlMatchDrawer', $js );
		$this->assertStringContainsString( 'edit_url_match', $js );
		$this->assertStringContainsString( 'rwgc-mapping-options', $js );
		$this->assertStringContainsString( 'rwgc-mapping-option', $js );
		$this->assertStringContainsString( 'rwgc-mapping-danger', $js );
		$this->assertStringContainsString( 'rwgc-mapping-also-valid', $js );
		$this->assertStringContainsString( 'rwgc-mapping-advanced', $js );
		$this->assertStringContainsString( 'utm_source_google_and_medium_cpc', $js );
		$this->assertStringContainsString( 'GOOGLE_ADS_STANDARD_KEY', $js );
		$this->assertStringContainsString( 'updateResolutionDrawerApplyButton', $js );
		$this->assertStringContainsString( 'useMappingCards', $js );

		$drawer = $this->js_between( $js, 'renderGoogleAdsMappingDrawer', 'renderUrlMatchDrawer' );
		$this->assertStringContainsString( 'name: \'rwgc-mapping-choice\'', $drawer );
		$this->assertStringContainsString( 'rwgc-mapping-danger', $drawer );
		$this->assertStringContainsString( 'renderGoogleAdsValidUrlSection', $drawer );
		$this->assertStringNotContainsString( 'opt.recommended', $drawer );
		$this->assertStringNotContainsString( 'rwgc-resolution-drawer__options', $drawer );

		$apply = $this->js_between( $js, 'applyResolutionDrawer', 'conditionOptionButton' );
		$this->assertStringContainsString( 'googleAdsResolutionShortLabel', $apply );
		$this->assertStringContainsString( 'syncProposalPayload', $apply );
		$this->assertStringNotContainsString( 'window.prompt', $apply );

		$this->assertStringContainsString( '.rwgc-mapping-options', $css );
		$this->assertStringContainsString( '.rwgc-mapping-option--selected', $css );
		$this->assertStringContainsString( '.rwgc-mapping-option__badge', $css );
		$this->assertStringContainsString( '.rwgc-mapping-also-valid', $css );
	}

	public function test_execute_flow_syncs_resolutions_to_payload(): void {
		$js = $this->assistant_js();

		$this->assertStringContainsString( 'findFieldResolution', $js );
		$this->assertStringContainsString( 'collectCardResolutions', $js );
		$this->assertStringContainsString( 'applyResolutionsToProposalCards', $js );
		$this->assertStringContainsString( 'unresolvedExecuteItems', $js );
		$this->assertStringContainsString( 'showExecuteBlockedMessage', $js );
		$this->assertStringContainsString( 'applyTrafficResolutionToCard', $js );

		$collect = $this->js_between( $js, 'collectCardResolutions', 'recordConditionLearning' );
		$this->assertStringContainsString( 'findFieldResolution', $collect );
		$this->assertStringContainsString( 'cardResolutionEntries', $collect );
		$this->assertStringContainsString( 'collectPopupTargetResolution', $collect );

		$finalize = $this->js_between( $js, 'finalizeCardSetup', 'updateSetupPanel' );
		$this->assertStringContainsString( 'syncProposalPayload', $finalize );
		$this->assertStringContainsString( 'unresolvedExecuteItems', $finalize );
		$this->assertStringContainsString( 'executePayloadTargetMismatches', $finalize );

		$execute = $this->js_between( $js, 'executeProposal', 'renderExecutionSummary' );
		$this->assertStringContainsString( 'unresolvedExecuteItems', $execute );
		$this->assertStringContainsString( 'unresolved_details', $execute );
		$this->assertStringContainsString( 'logExecutePayloadDebug', $execute );
		$this->assertStringContainsString( 'executePayloadTargetMismatches', $execute );
		$this->assertStringContainsString( 'showExecuteFailedMessage', $execute );
		$fail = $this->source_between( $js, '.fail( function ( jqxhr )', '.always( function ()' );
		$this->assertStringContainsString( 'showExecuteFailedMessage( serverMsg, jqxhr )', $fail );
		$this->assertStringNotContainsString( 'goWorkflowFromProposal', $fail );
	}

	public function test_popup_target_execute_sync_helpers(): void {
		$js = $this->assistant_js();

		$this->assertStringContainsString( 'popupTargetRawCandidates', $js );
		$this->assertStringContainsString( 'popupTargetResolvedFromCard', $js );
		$this->assertStringContainsString( 'seedPopupTargetCardResolutions', $js );
		$this->assertStringContainsString( 'ensurePopupTargetCardResolutions', $js );
		$this->assertStringContainsString( 'syncPopupTargetActionState', $js );
		$this->assertStringContainsString( 'collectPopupTargetResolution', $js );
		$this->assertStringContainsString( 'showTargetExecuteMismatchMessage', $js );

		$auto = $this->js_between( $js, 'autoMatchPopupTargets', 'locationOptionLabel' );
		$this->assertStringContainsString( 'ensurePopupTargetCardResolutions', $auto );

		$collect = $this->js_between( $js, 'collectPopupTargetResolution', 'executePayloadTargetMismatches' );
		$this->assertStringContainsString( "type: 'popup'", $collect );
		$this->assertStringContainsString( "status: 'valid'", $collect );
		$this->assertStringContainsString( 'popupTargetResolvedFromCard', $collect );

		$target = $this->js_between( $js, 'applyTargetResolutionToCard', 'applyResolutionsToProposalCards' );
		$this->assertStringContainsString( 'ensurePopupTargetCardResolutions', $target );
		$this->assertStringContainsString( 'syncPopupTargetActionState', $target );

		$update = $this->js_between( $js, 'updateActionTargetFromResolution', 'syncProposalPayload' );
		$this->assertStringContainsString( "card.target.status = 'matched'", $update );
		$this->assertStringContainsString( 'seedPopupTargetCardResolutions', $update );
	}

}
