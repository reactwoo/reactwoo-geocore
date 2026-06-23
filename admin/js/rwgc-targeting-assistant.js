/**
 * Geo Targeting Assistant — chat-style smart action UI.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.rwgcTargetingAssistant || {};
	var i18n = cfg.i18n || {};
	var state = {
		proposal: null,
		proposalId: '',
		preview: null,
		debug: null,
		lastMessage: '',
		lastResponse: null,
		ambiguities: null,
		aiInterpretation: null,
		previewTimer: null,
		previewSeq: 0,
		sendSeq: 0,
		cardResolutions: {},
	};

	function esc( text ) {
		return $( '<div>' ).text( text == null ? '' : String( text ) ).html();
	}

	function chip( label, type ) {
		return $( '<span>' ).addClass( 'rwgc-targeting-chip rwgc-targeting-chip--' + ( type || 'neutral' ) ).text( label );
	}

	function renderChips( detected ) {
		var $wrap = $( '<div>' ).addClass( 'rwgc-targeting-assistant__chips' );
		if ( ! detected ) {
			return $wrap;
		}
		( detected.intents || [] ).forEach( function ( row ) {
			$wrap.append( chip( row.label || row.key, 'intent' ) );
		} );
		( detected.keywords || [] ).forEach( function ( row ) {
			$wrap.append( chip( row.text, row.type || 'keyword' ) );
		} );
		if ( detected.source_targeting && detected.source_targeting.label ) {
			$wrap.append( chip( detected.source_targeting.label, 'source-targeting' ) );
		}
		( detected.entities || [] ).forEach( function ( row ) {
			if ( row.type === 'country' && ( ( detected.variant_groups && detected.variant_groups.length ) || ( detected.source_targeting && detected.source_targeting.label ) ) ) {
				return;
			}
			$wrap.append( chip( row.label || row.value, row.type || 'entity' ) );
		} );
		( detected.variant_groups || [] ).forEach( function ( row ) {
			$wrap.append( chip( row.label, 'variant-group' ) );
		} );
		return $wrap;
	}

	function assistantBubble( html ) {
		return $( '<div>', { class: 'rwgc-targeting-assistant__bubble rwgc-targeting-assistant__bubble--assistant' } )
			.append(
				$( '<span>', { class: 'rwgc-targeting-assistant__avatar', 'aria-hidden': 'true' } ).text( 'G' ),
				$( '<div>', { class: 'rwgc-targeting-assistant__bubble-body' } ).html( html )
			);
	}

	function userBubble( text, detected ) {
		var $bubble = $( '<div>', { class: 'rwgc-targeting-assistant__bubble rwgc-targeting-assistant__bubble--user' } );
		$bubble.append( $( '<div>', { class: 'rwgc-targeting-assistant__bubble-body', text: text } ) );
		if ( detected && ( ( detected.source_targeting && detected.source_targeting.label ) || ( detected.variant_groups && detected.variant_groups.length ) || ( detected.entities && detected.entities.length ) || ( detected.keywords && detected.keywords.length ) || ( detected.intents && detected.intents.length ) ) ) {
			$bubble.append( $( '<div>', { class: 'rwgc-targeting-assistant__detected-label', text: i18n.detectedLabel || 'Detected:' } ) );
			$bubble.append( renderChips( detected ) );
		}
		return $bubble;
	}

	function scrollThread() {
		var el = document.getElementById( 'rwgc-targeting-thread' );
		if ( el ) {
			el.scrollTop = el.scrollHeight;
		}
	}

	function appendAssistant( html, $actions ) {
		var $thread = $( '#rwgc-targeting-thread' );
		var $bubble = assistantBubble( html );
		$thread.append( $bubble );
		if ( $actions && $actions.length ) {
			$bubble.find( '.rwgc-targeting-assistant__bubble-body' ).append( $actions );
		}
		scrollThread();
	}

	function appendUser( text, detected ) {
		$( '#rwgc-targeting-thread' ).append( userBubble( text, detected ) );
		scrollThread();
	}

	function updateLivePreview( data ) {
		var $panel = $( '#rwgc-targeting-live-preview' );
		if ( ! data || ! data.summary ) {
			$panel.addClass( 'rwgc-is-hidden' ).empty();
			return;
		}
		var html = '<strong>' + esc( i18n.livePreview || 'Likely intent' ) + ':</strong> ' + esc( data.summary );
		if ( data.confidence ) {
			html += ' <span class="rwgc-targeting-assistant__confidence">' + Math.round( data.confidence * 100 ) + '%</span>';
		}
		$panel.removeClass( 'rwgc-is-hidden' ).html( html ).append( renderChips( data.detected ) );
	}

	function setupStatusLabel( response, proposal ) {
		if ( response && ( response.status === 'needs_confirmation' || response.status === 'needs_clarification' ) ) {
			return i18n.statusNeedsConfirmation || 'Needs confirmation';
		}
		if ( proposal && proposal.can_execute === false ) {
			return i18n.statusNeedsConfirmation || 'Needs confirmation';
		}
		return i18n.statusPending || 'Pending confirmation';
	}

	function locationOptionLabel( value ) {
		if ( ! value ) {
			return '';
		}
		if ( value === 'GB' ) {
			return i18n.useUkCountry || 'United Kingdom country targeting';
		}
		if ( String( value ).indexOf( 'region:' ) === 0 ) {
			var region = String( value ).slice( 7 ).replace( /[-_]/g, ' ' );
			return region.charAt( 0 ).toUpperCase() + region.slice( 1 ) + ' region targeting';
		}
		return value + ' country targeting';
	}

	function audienceOptionLabel( value ) {
		if ( value === 'any_audience' ) {
			return i18n.anyAudience || 'Any audience';
		}
		if ( value === 'selected_audience_groups' ) {
			return i18n.selectedAudiences || 'Choose audience groups';
		}
		return value;
	}

	function ambiguityFieldLabel( field ) {
		if ( field === 'location' ) {
			return i18n.locationLabel || 'Location';
		}
		if ( field === 'campaign' ) {
			return i18n.campaignLabel || 'Campaign';
		}
		return i18n.audienceLabel || 'Audience';
	}

	function ambiguityRowScope( row, fallback ) {
		var parts = [];
		if ( row.action_index ) {
			parts.push( ( i18n.actionWord || 'Action' ) + ' ' + row.action_index );
		}
		var target = row.target_label || row.action_label || '';
		if ( target ) {
			parts.push( target );
		}
		if ( parts.length ) {
			return parts.join( ' · ' );
		}
		return fallback || '';
	}

	function renderAmbiguitiesHtml( response ) {
		var ambiguities = response.ambiguities || ( response.proposal && response.proposal.ambiguities ) || [];
		var aiInterp = response.ai_interpretation || ( response.proposal && response.proposal.ai_interpretation );
		if ( ! ambiguities.length && ! aiInterp ) {
			return '';
		}
		var html = '<div class="rwgc-targeting-assistant__ambiguity-wrap">';
		if ( aiInterp && aiInterp.likely_meaning ) {
			html += '<p><strong>' + esc( i18n.intelligenceThinks || 'The intelligence layer thinks you mean:' ) + '</strong></p>';
			html += '<p>' + esc( aiInterp.likely_meaning ) + '</p>';
		}
		if ( ambiguities.length ) {
			html += '<ul class="rwgc-targeting-assistant__ambiguity-list">';
			ambiguities.forEach( function ( row ) {
				html += '<li><strong>' + esc( ambiguityFieldLabel( row.field || '' ) ) + ':</strong> ' + esc( row.raw || '' );
				var scope = ambiguityRowScope( row, '' );
				if ( scope ) {
					html += ' <span class="description">(' + esc( ( i18n.forScope || 'for' ) + ' ' + scope ) + ')</span>';
				}
				if ( row.likely ) {
					html += ' → <em>' + esc( row.field === 'location' ? locationOptionLabel( row.likely ) : audienceOptionLabel( row.likely ) ) + '</em>';
				}
				if ( row.question ) {
					html += '<br><span class="description">' + esc( row.question ) + '</span>';
				}
				html += '</li>';
			} );
			html += '</ul>';
		}
		if ( aiInterp && aiInterp.reason ) {
			html += '<p><strong>' + esc( i18n.whyAsking || 'Why I’m asking:' ) + '</strong></p>';
			html += '<p>' + esc( aiInterp.reason ) + '</p>';
		} else if ( ambiguities.length ) {
			var notes = [];
			ambiguities.forEach( function ( row ) {
				( row.notes || [] ).forEach( function ( note ) {
					notes.push( note );
				} );
			} );
			if ( notes.length ) {
				html += '<p><strong>' + esc( i18n.whyAsking || 'Why I’m asking:' ) + '</strong></p><ul>';
				notes.forEach( function ( note ) {
					html += '<li>' + esc( note ) + '</li>';
				} );
				html += '</ul>';
			}
		}
		if ( aiInterp && aiInterp.proposal_draft && aiInterp.proposal_draft.rule && aiInterp.proposal_draft.rule.conditions ) {
			html += '<p><strong>' + esc( i18n.thinkYouMean || 'Proposed conditions:' ) + '</strong></p><ul>';
			aiInterp.proposal_draft.rule.conditions.forEach( function ( cond ) {
				html += '<li>' + esc( cond.label || cond.type || '' ) + '</li>';
			} );
			html += '</ul>';
		}
		html += '<p><strong>' + esc( i18n.isInterpretationCorrect || 'Is this interpretation correct?' ) + '</strong></p>';
		html += '</div>';
		return html;
	}

	function renderInferredPlanHtml( inferredPlan ) {
		if ( ! inferredPlan ) {
			return '';
		}
		var html = '<div class="rwgc-targeting-assistant__inferred-plan-wrap">';
		html += '<p><strong>' + esc( i18n.thinkYouMean || 'I think you mean:' ) + '</strong></p>';
		html += '<ul class="rwgc-targeting-assistant__inferred-plan">';
		if ( inferredPlan.source_targeting ) {
			var source = inferredPlan.source_targeting;
			html += '<li><strong>' + esc( source.label || 'Original homepage' ) + '</strong><ul>';
			html += '<li>' + esc( ( source.countries && source.countries.length > 1 ? 'Countries: ' : 'Country: ' ) + ( source.countries || [] ).join( ', ' ) ) + '</li>';
			if ( source.weather ) {
				if ( source.weather.mode === 'any' ) {
					html += '<li>' + esc( 'Weather: All weather conditions' ) + '</li>';
				} else if ( source.weather.condition ) {
					html += '<li>' + esc( 'Weather: ' + source.weather.condition ) + '</li>';
				}
			}
			html += '</ul></li>';
		}
		( inferredPlan.variants || [] ).forEach( function ( variant, idx ) {
			html += '<li><strong>' + esc( variant.label || ( ( i18n.variantLabel || 'Variant' ) + ' ' + ( idx + 1 ) ) ) + '</strong><ul>';
			html += '<li>' + esc( ( variant.countries && variant.countries.length > 1 ? 'Countries: ' : 'Country: ' ) + ( variant.countries || [] ).join( ' + ' ) ) + '</li>';
			if ( variant.weather ) {
				if ( variant.weather.mode === 'any' ) {
					html += '<li>' + esc( 'Weather: All weather conditions' ) + '</li>';
				} else if ( variant.weather.condition ) {
					html += '<li>' + esc( 'Weather: ' + variant.weather.condition ) + '</li>';
				}
			}
			html += '</ul></li>';
		} );
		html += '</ul>';
		html += '<p><strong>' + esc( i18n.isCorrect || 'Is this correct?' ) + '</strong></p>';
		html += '</div>';
		return html;
	}

	/* ---- Action review cards --------------------------------------------- */

	function actionTypeLabel( type ) {
		var map = {
			update_campaign_targeting: i18n.cardTypeUpdateCampaign || 'Update campaign targeting',
			update_original_targeting: i18n.cardTypeUpdateOriginal || 'Update targeting',
			create_variant: i18n.cardTypeCreateVariant || 'Create variant',
			update_variant: i18n.cardTypeUpdateVariant || 'Update variant',
			create_rule: i18n.cardTypeCreateRule || 'Create rule',
			update_rule: i18n.cardTypeUpdateRule || 'Update rule',
			hide: i18n.cardTypeHide || 'Hide',
			show: i18n.cardTypeShow || 'Show',
			create_test: i18n.cardTypeTest || 'Preview / test',
			diagnose: i18n.cardTypeTest || 'Preview / test',
		};
		return map[ type ] || ( type ? String( type ).replace( /_/g, ' ' ) : ( i18n.cardActionWord || 'Action' ) );
	}

	function cardKey( idx ) {
		return 'card_' + idx;
	}

	function fieldKey( idx, field, raw ) {
		return cardKey( idx ) + '|' + field + '|' + ( raw || '' );
	}

	function isCardRemoved( idx ) {
		return !! state.cardResolutions[ 'removed_' + cardKey( idx ) ];
	}

	function fieldResolution( idx, field, raw ) {
		return state.cardResolutions[ fieldKey( idx, field, raw ) ] || null;
	}

	function requiresField( card, field ) {
		return ( ( card && card.requiredResolutions ) || [] ).some( function ( r ) {
			return r.field === field;
		} );
	}

	function remainingForCard( idx, card ) {
		if ( isCardRemoved( idx ) ) {
			return 0;
		}
		var n = 0;
		( ( card && card.requiredResolutions ) || [] ).forEach( function ( req ) {
			if ( ! fieldResolution( idx, req.field, req.raw ) ) {
				n++;
			}
		} );
		return n;
	}

	function remainingResolutions( proposal ) {
		var total = 0;
		( ( proposal && proposal.action_cards ) || [] ).forEach( function ( card, idx ) {
			total += remainingForCard( idx, card );
		} );
		return total;
	}

	function statusText( status ) {
		var map = {
			not_found: i18n.cardNotFound || 'Not found',
			not_defined: i18n.cardNotFound || 'Not found',
			ambiguous: i18n.cardAmbiguous || 'Possible matches found',
			inherited_unresolved: i18n.cardInheritedUnresolved || 'Inherited target is not defined',
			registry_unavailable: i18n.cardUnverified || 'Could not be verified automatically',
			sync_unavailable: i18n.cardSyncUnavailable || 'No synced list available yet',
			matched: i18n.cardMatched || 'Matched',
		};
		return map[ status ] || ( status ? String( status ).replace( /_/g, ' ' ) : '' );
	}

	function summariseConditions( grp ) {
		var parts = [];
		grp = grp || {};
		if ( grp.countries && grp.countries.length ) {
			parts.push( ( i18n.cardCountries || 'Countries' ) + ': ' + grp.countries.join( ', ' ) );
		}
		if ( grp.regions && grp.regions.length ) {
			parts.push( ( i18n.cardRegions || 'Regions' ) + ': ' + grp.regions.join( ', ' ) );
		}
		if ( grp.devices && grp.devices.length ) {
			parts.push( ( i18n.cardDevices || 'Devices' ) + ': ' + grp.devices.join( ', ' ) );
		}
		if ( grp.utm && grp.utm.length ) {
			parts.push( 'UTM: ' + grp.utm.map( function ( u ) {
				return ( u.key || '' ) + '=' + ( u.value || '' );
			} ).join( ', ' ) );
		}
		if ( grp.urls && grp.urls.length ) {
			parts.push( 'URL: ' + grp.urls.join( ', ' ) );
		}
		if ( grp.weather && grp.weather.length ) {
			parts.push( ( i18n.cardWeather || 'Weather' ) + ': ' + grp.weather.join( ', ' ) );
		}
		if ( grp.visitorStates && grp.visitorStates.length ) {
			parts.push( ( i18n.cardVisitor || 'Visitor' ) + ': ' + grp.visitorStates.join( ', ' ) );
		}
		return parts.join( ' · ' );
	}

	function conditionRow( label, text ) {
		return $( '<div>', { class: 'rwgc-geo-card__cond' } ).append(
			$( '<span>', { class: 'rwgc-geo-card__cond-label' } ).text( label ),
			$( '<span>', { class: 'rwgc-geo-card__cond-value' } ).text( text )
		);
	}

	function fieldActionButton( idx, field, raw, act ) {
		var labels = {
			choose_campaign: i18n.cardChooseCampaign || 'Choose campaign',
			ignore_campaign: i18n.cardIgnore || 'Ignore',
			refresh_campaigns: i18n.cardRefresh || 'Refresh synced',
			choose_audience: i18n.cardChooseAudience || 'Choose audience',
			ignore_audience: i18n.cardIgnore || 'Ignore',
			refresh_audiences: i18n.cardRefresh || 'Refresh synced',
			choose_target: i18n.cardChooseTarget || 'Choose page/category',
			search_targets: i18n.cardSearchTargets || 'Search',
			remove_action: i18n.cardRemoveAction || 'Remove action',
		};
		var map = {
			ignore_campaign: 'ignore_field',
			ignore_audience: 'ignore_field',
			refresh_campaigns: 'refresh',
			refresh_audiences: 'refresh',
			choose_campaign: 'choose_manual',
			choose_audience: 'choose_manual',
			choose_target: 'toggle_picker',
			search_targets: 'toggle_picker',
			remove_action: 'remove_action',
		};
		return $( '<button>', {
			type: 'button',
			class: 'button rwgc-geo-card__act' + ( act === 'remove_action' ? ' rwgc-geo-card__act--danger' : '' ),
			text: labels[ act ] || act,
			'data-card-action': map[ act ] || act,
			'data-card': idx,
			'data-field': field,
			'data-raw': raw || '',
		} );
	}

	function targetPicker( idx, raw, suggestions ) {
		var $wrap = $( '<div>', { class: 'rwgc-geo-card__picker rwgc-is-hidden', 'data-picker-card': idx } );
		var $sel = $( '<select>', { class: 'rwgc-geo-card__picker-select' } );
		$sel.append( $( '<option>', { value: '', text: i18n.cardPickerPlaceholder || 'Select a page or category…' } ) );
		( suggestions || [] ).forEach( function ( s ) {
			$sel.append( $( '<option>', { value: 'sug:' + ( s.id || '' ) + ':' + s.name, text: s.name } ) );
		} );
		( cfg.pages || [] ).forEach( function ( p ) {
			$sel.append( $( '<option>', { value: 'page:' + p.id + ':' + p.title, text: p.title } ) );
		} );
		$wrap.append( $sel );
		$wrap.append( $( '<button>', {
			type: 'button',
			class: 'button rwgc-geo-card__picker-use',
			text: i18n.cardUse || 'Use',
			'data-card-action': 'use_picker',
			'data-card': idx,
			'data-field': 'target',
			'data-raw': raw || '',
		} ) );
		return $wrap;
	}

	function fieldBlock( idx, opts ) {
		var $b = $( '<div>', { class: 'rwgc-geo-card__field' } );
		$b.append( $( '<span>', { class: 'rwgc-geo-card__field-label' } ).text( opts.label ) );

		if ( opts.resolved ) {
			$b.append( $( '<span>', { class: 'rwgc-geo-card__field-value' } ).text( opts.resolved ) );
			$b.append( $( '<span>', { class: 'rwgc-geo-card__field-ok' } ).text( i18n.cardMatched || 'Matched' ) );
			return $b;
		}

		var resolution = fieldResolution( idx, opts.field, opts.value );
		if ( resolution ) {
			$b.append( $( '<span>', { class: 'rwgc-geo-card__field-value' } ).text( opts.value || '—' ) );
			var label = resolution.kind === 'ignored'
				? ( i18n.cardIgnored || 'Ignored' )
				: ( ( i18n.cardSetTo || 'Set to' ) + ' ' + resolution.label );
			$b.append( $( '<span>', { class: 'rwgc-geo-card__field-ok' } ).text( label ) );
			$b.append( $( '<button>', {
				type: 'button',
				class: 'button-link rwgc-geo-card__undo',
				text: i18n.cardUndo || 'Undo',
				'data-card-action': 'undo_field',
				'data-card': idx,
				'data-field': opts.field,
				'data-raw': opts.value || '',
			} ) );
			return $b;
		}

		$b.append( $( '<span>', { class: 'rwgc-geo-card__field-value' } ).text( opts.value || '—' ) );
		$b.append( $( '<span>', { class: 'rwgc-geo-card__field-status' } ).text( statusText( opts.status ) ) );

		if ( opts.suggestions && opts.suggestions.length ) {
			var $sug = $( '<div>', { class: 'rwgc-geo-card__suggestions' } );
			opts.suggestions.forEach( function ( s ) {
				$sug.append( $( '<button>', {
					type: 'button',
					class: 'button rwgc-geo-card__chip',
					text: s.name + ( s.source ? ' — ' + s.source : '' ),
					'data-card-action': 'choose_suggestion',
					'data-card': idx,
					'data-field': opts.field,
					'data-raw': opts.value || '',
					'data-id': s.id || '',
					'data-label': s.name,
				} ) );
			} );
			$b.append( $sug );
		}

		if ( opts.actions && opts.actions.length ) {
			var $acts = $( '<div>', { class: 'rwgc-geo-card__field-actions' } );
			opts.actions.forEach( function ( act ) {
				$acts.append( fieldActionButton( idx, opts.field, opts.value, act ) );
			} );
			$b.append( $acts );
		}

		if ( 'target' === opts.field ) {
			$b.append( targetPicker( idx, opts.value, opts.suggestions ) );
		}

		return $b;
	}

	function renderCard( card, idx ) {
		var removed = isCardRemoved( idx );
		var $card = $( '<div>', { class: 'rwgc-geo-card' + ( removed ? ' rwgc-geo-card--removed' : '' ) } );

		var remaining = remainingForCard( idx, card );
		var $head = $( '<div>', { class: 'rwgc-geo-card__head' } );
		$head.append( $( '<strong>' ).text( ( i18n.cardActionWord || 'Action' ) + ' ' + ( idx + 1 ) + ' — ' + actionTypeLabel( card.type ) ) );
		var badgeKind = removed ? 'removed' : ( remaining > 0 ? 'warn' : 'ok' );
		var badgeLabel = removed
			? ( i18n.cardActionRemoved || 'Removed' )
			: ( remaining > 0 ? ( i18n.cardNeedsResolution || 'Needs resolution' ) : ( i18n.cardReady || 'Ready' ) );
		$head.append( $( '<span>', { class: 'rwgc-geo-card__badge rwgc-geo-card__badge--' + badgeKind } ).text( badgeLabel ) );
		$card.append( $head );

		if ( removed ) {
			$card.append( $( '<button>', {
				type: 'button',
				class: 'button-link rwgc-geo-card__undo',
				text: i18n.cardRestore || 'Restore action',
				'data-card-action': 'restore_action',
				'data-card': idx,
			} ) );
			return $card;
		}

		var t = card.target || {};
		if ( t.raw || ( t.resolved && t.resolved.name ) ) {
			var targetValue = ( t.inherited && t.inheritedFrom )
				? ( ( i18n.cardSameAs || 'Same as' ) + ' ' + t.inheritedFrom )
				: t.raw;
			$card.append( fieldBlock( idx, {
				field: 'target',
				label: i18n.cardTargetLabel || 'Target',
				value: targetValue,
				status: t.status,
				resolved: t.resolved ? t.resolved.name : '',
				suggestions: t.suggestions || [],
				actions: requiresField( card, 'target' ) ? [ 'choose_target', 'search_targets', 'remove_action' ] : [],
			} ) );
		}

		if ( card.campaign ) {
			$card.append( fieldBlock( idx, {
				field: 'campaign',
				label: i18n.campaignLabel || 'Campaign',
				value: card.campaign.raw,
				status: card.campaign.status,
				resolved: card.campaign.resolved ? card.campaign.resolved.name : '',
				suggestions: card.campaign.suggestions || [],
				actions: requiresField( card, 'campaign' ) ? [ 'choose_campaign', 'ignore_campaign', 'refresh_campaigns' ] : [],
			} ) );
		}

		( card.audiences || [] ).forEach( function ( a ) {
			$card.append( fieldBlock( idx, {
				field: 'audience',
				label: i18n.audienceLabel || 'Audience',
				value: a.raw,
				status: a.status,
				resolved: a.resolved ? a.resolved.name : '',
				suggestions: a.suggestions || [],
				actions: ( a.status && 'matched' !== a.status ) ? [ 'choose_audience', 'ignore_audience', 'refresh_audiences' ] : [],
			} ) );
		} );

		var incText = summariseConditions( card.conditions && card.conditions.include );
		if ( incText ) {
			$card.append( conditionRow( i18n.cardInclude || 'Include', incText ) );
		}
		var excText = summariseConditions( card.conditions && card.conditions.exclude );
		if ( excText ) {
			$card.append( conditionRow( i18n.cardExclude || 'Exclude', excText ) );
		}

		( card.warnings || [] ).forEach( function ( w ) {
			$card.append( $( '<p>', { class: 'rwgc-geo-card__warning' } ).text( w ) );
		} );

		$card.append( $( '<div>', { class: 'rwgc-geo-card__row-actions' } ).append(
			$( '<button>', {
				type: 'button',
				class: 'button-link rwgc-geo-card__act--danger',
				text: i18n.cardRemoveAction || 'Remove action',
				'data-card-action': 'remove_action',
				'data-card': idx,
			} )
		) );

		return $card;
	}

	function renderActionCards( proposal, $plan ) {
		var cards = proposal.action_cards || [];
		$plan.append( $( '<h3>' ).text( i18n.setupHeading || 'Setup' ) );

		var detectedCount = cards.filter( function ( c, i ) {
			return ! isCardRemoved( i );
		} ).length;
		$plan.append( $( '<p>', { class: 'rwgc-geo-card__count' } ).text(
			detectedCount + ' ' + ( detectedCount === 1 ? ( i18n.actionDetected || 'action detected' ) : ( i18n.actionsDetected || 'actions detected' ) )
		) );

		var remaining = remainingResolutions( proposal );
		if ( remaining > 0 ) {
			$plan.append( $( '<p>', { class: 'rwgc-geo-card__attention' } ).text(
				remaining + ' ' + ( remaining === 1 ? ( i18n.fieldNeedsAttention || 'field needs attention' ) : ( i18n.fieldsNeedAttention || 'fields need attention' ) )
			) );
		} else {
			$plan.append( $( '<p>', { class: 'rwgc-geo-card__ready-note' } ).text( i18n.allResolved || 'All fields resolved' ) );
		}

		cards.forEach( function ( card, idx ) {
			$plan.append( renderCard( card, idx ) );
		} );

		var $footer = $( '<div>', { class: 'rwgc-geo-card__footer' } );
		if ( remaining > 0 ) {
			$footer.append( $( '<button>', {
				type: 'button',
				class: 'button button-primary rwgc-geo-btn',
				disabled: 'disabled',
				text: ( i18n.continueAfter || 'Resolve' ) + ' ' + remaining + ' ' + ( remaining === 1 ? ( i18n.itemWord || 'item' ) : ( i18n.itemsWord || 'items' ) ),
			} ) );
		} else {
			$footer.append( $( '<button>', {
				type: 'button',
				class: 'button button-primary rwgc-geo-btn',
				text: i18n.createSetup || 'Create setup',
				'data-card-action': 'create_setup',
			} ) );
		}
		$plan.append( $footer );
	}

	function rerenderCards() {
		if ( state.proposal ) {
			updateSetupPanel( state.proposal, setupStatusLabel( state.lastResponse, state.proposal ) );
		}
	}

	function collectCardResolutions() {
		var out = [];
		var cards = ( state.proposal && state.proposal.action_cards ) || [];
		cards.forEach( function ( card, idx ) {
			if ( isCardRemoved( idx ) ) {
				out.push( { card: idx, action: 'remove_action' } );
				return;
			}
			( ( card && card.requiredResolutions ) || [] ).forEach( function ( req ) {
				var res = fieldResolution( idx, req.field, req.raw );
				if ( res ) {
					out.push( {
						card: idx,
						field: req.field,
						raw: req.raw,
						action: res.kind === 'ignored' ? 'ignore' : 'choose',
						id: res.id || '',
						label: res.label || '',
					} );
				}
			} );
		} );
		return out;
	}

	function handleCardAction( $btn ) {
		var action = $btn.data( 'card-action' );
		var idx = parseInt( $btn.data( 'card' ), 10 );
		var field = $btn.data( 'field' );
		var raw = $btn.data( 'raw' ) != null ? String( $btn.data( 'raw' ) ) : '';

		if ( 'choose_suggestion' === action ) {
			state.cardResolutions[ fieldKey( idx, field, raw ) ] = {
				kind: 'chosen',
				id: $btn.data( 'id' ) || '',
				label: $btn.data( 'label' ) || '',
			};
			rerenderCards();
		} else if ( 'ignore_field' === action ) {
			state.cardResolutions[ fieldKey( idx, field, raw ) ] = { kind: 'ignored' };
			rerenderCards();
		} else if ( 'undo_field' === action ) {
			delete state.cardResolutions[ fieldKey( idx, field, raw ) ];
			rerenderCards();
		} else if ( 'remove_action' === action ) {
			state.cardResolutions[ 'removed_' + cardKey( idx ) ] = true;
			rerenderCards();
		} else if ( 'restore_action' === action ) {
			delete state.cardResolutions[ 'removed_' + cardKey( idx ) ];
			rerenderCards();
		} else if ( 'toggle_picker' === action ) {
			$( '.rwgc-geo-card__picker[data-picker-card="' + idx + '"]' ).toggleClass( 'rwgc-is-hidden' );
		} else if ( 'use_picker' === action ) {
			var $sel = $( '.rwgc-geo-card__picker[data-picker-card="' + idx + '"] .rwgc-geo-card__picker-select' );
			var val = String( $sel.val() || '' );
			if ( ! val ) {
				return;
			}
			var parts = val.split( ':' );
			state.cardResolutions[ fieldKey( idx, field, raw ) ] = {
				kind: 'chosen',
				id: parts[1] || '',
				label: parts.slice( 2 ).join( ':' ) || $sel.find( 'option:selected' ).text(),
			};
			rerenderCards();
		} else if ( 'choose_manual' === action ) {
			var entered = window.prompt( i18n.cardEnterExact || 'Enter the exact name to use:', raw );
			if ( entered && entered.trim() ) {
				state.cardResolutions[ fieldKey( idx, field, raw ) ] = {
					kind: 'chosen',
					id: '',
					label: entered.trim(),
				};
				rerenderCards();
			}
		} else if ( 'refresh' === action ) {
			if ( state.lastMessage ) {
				sendMessage( state.lastMessage );
			}
		} else if ( 'create_setup' === action ) {
			finalizeCardSetup();
		}
	}

	function cardResolutionsToFieldMap() {
		var map = {};
		var cards = ( state.proposal && state.proposal.action_cards ) || [];
		cards.forEach( function ( card, idx ) {
			if ( isCardRemoved( idx ) ) {
				return;
			}
			( ( card && card.requiredResolutions ) || [] ).forEach( function ( req ) {
				var res = fieldResolution( idx, req.field, req.raw );
				if ( res && 'chosen' === res.kind && res.label ) {
					// Server resolution path keys by field name; the last chosen
					// value per field wins for re-interpretation.
					map[ req.field ] = res.label;
				}
			} );
		} );
		return map;
	}

	function finalizeCardSetup() {
		// If the server already produced an executable proposal, run it directly.
		if ( state.proposalId ) {
			executeProposal();
			return;
		}
		// Otherwise push the user's resolutions back through the interpreter so the
		// chosen synced entities / locations are applied server-side, then execute.
		var resolutions = cardResolutionsToFieldMap();
		if ( cfg.confirmInterpretationUrl ) {
			var payload = buildInterpretationPayload( resolutions );
			payload.card_resolutions = collectCardResolutions();
			apiPost( cfg.confirmInterpretationUrl, payload ).done( function ( response ) {
				if ( response && response.success ) {
					state.ambiguities = null;
					state.aiInterpretation = null;
					applyInterpretResponse( response );
					if ( responseCanExecute( state.proposal ) && state.proposalId ) {
						executeProposal();
					}
				}
			} );
			return;
		}
		executeProposal();
	}

	function updateSetupPanel( proposal, status ) {
		var $empty = $( '#rwgc-targeting-setup-empty' );
		var $hint = $( '#rwgc-targeting-setup-hint' );
		var $plan = $( '#rwgc-targeting-setup-plan' );
		var $summary = $( '#rwgc-targeting-summary' );

		if ( ! proposal ) {
			$empty.removeClass( 'rwgc-is-hidden' );
			$hint.removeClass( 'rwgc-is-hidden' );
			$plan.addClass( 'rwgc-is-hidden' ).empty();
			$summary.addClass( 'rwgc-is-hidden' );
			return;
		}

		$empty.addClass( 'rwgc-is-hidden' );
		$hint.addClass( 'rwgc-is-hidden' );
		$plan.removeClass( 'rwgc-is-hidden' ).empty();

		if ( proposal.action_cards && proposal.action_cards.length ) {
			renderActionCards( proposal, $plan );
			$summary.removeClass( 'rwgc-is-hidden' );
			$( '#rwgc-targeting-summary dd[data-key="status"]' )
				.text( status || i18n.statusPending || 'Pending confirmation' )
				.removeClass( 'is-empty' );
			return;
		}

		var title = i18n.setupPlan || 'Targeting plan';
		if ( proposal.interpretation_plan && proposal.interpretation_plan.actions && proposal.interpretation_plan.actions.length ) {
			title = i18n.setupPlan || 'Setup';
		} else if ( proposal.intent === 'create_geo_variant_plan' ) {
			var planRef = proposal.params && proposal.params.source_page_ref ? proposal.params.source_page_ref : '';
			title = planRef ? ( String( planRef ).charAt( 0 ).toUpperCase() + String( planRef ).slice( 1 ) + ' targeting plan' ) : ( i18n.setupPlan || 'Targeting plan' );
		} else if ( proposal.intent === 'create_geo_variants' ) {
			var pageRef = proposal.params && proposal.params.source_page_ref ? proposal.params.source_page_ref : ( proposal.params && proposal.params.page_ref ? proposal.params.page_ref : '' );
			title = pageRef ? ( String( pageRef ).charAt( 0 ).toUpperCase() + String( pageRef ).slice( 1 ) + ' variants' ) : ( i18n.setupVariants || 'Page variants' );
		} else if ( proposal.params && proposal.params.page_ref ) {
			title = proposal.params.page_ref;
		}
		$plan.append( $( '<h3>' ).text( title ) );

		if ( proposal.setup_summary ) {
			proposal.setup_summary.split( '\n' ).forEach( function ( line ) {
				if ( line ) {
					$plan.append( $( '<p>' ).text( line ) );
				}
			} );
		} else if ( proposal.interpretation_plan && proposal.interpretation_plan.actions && proposal.interpretation_plan.actions.length ) {
			var actions = proposal.interpretation_plan.actions;
			$plan.append( $( '<p>' ).text( actions.length + ' ' + ( actions.length === 1 ? ( i18n.actionDetected || 'action detected' ) : ( i18n.actionsDetected || 'actions detected' ) ) ) );
			actions.forEach( function ( action, idx ) {
				var loc = '';
				if ( action.conditions ) {
					if ( action.conditions.regions && action.conditions.regions.length ) {
						loc = action.conditions.regions.join( ', ' );
					} else if ( action.conditions.countries && action.conditions.countries.length ) {
						loc = action.conditions.countries.join( ' + ' );
					}
				}
				var block = $( '<div>', { class: 'rwgc-geo-setup-action' } );
				block.append( $( '<strong>' ).text( ( idx + 1 ) + '. ' + ( action.target && action.target.label ? action.target.label : 'Action' ) ) );
				if ( action.type ) {
					block.append( $( '<p>' ).text( String( action.type ).replace( /_/g, ' ' ) ) );
				}
				if ( loc ) {
					block.append( $( '<p>' ).text( ( i18n.locationLabel || 'Location' ) + ': ' + loc ) );
				}
				$plan.append( block );
			} );
		} else if ( proposal.inferred_plan ) {
			renderInferredPlanHtml( proposal.inferred_plan ).replace( /<[^>]+>/g, '\n' ).split( '\n' ).forEach( function ( line ) {
				line = line.trim();
				if ( line ) {
					$plan.append( $( '<p>' ).text( line ) );
				}
			} );
		} else if ( proposal.steps && proposal.steps.length ) {
			proposal.steps.forEach( function ( step, idx ) {
				$plan.append(
					$( '<div>', { class: 'rwgc-geo-setup-variant' } ).append(
						$( '<strong>' ).text( ( i18n.variantLabel || 'Variant' ) + ' ' + ( idx + 1 ) ),
						$( '<p>' ).text( step.label || '' )
					)
				);
			} );
		} else if ( proposal.summary ) {
			$plan.append( $( '<p>' ).text( proposal.summary ) );
		}

		$summary.removeClass( 'rwgc-is-hidden' );
		$( '#rwgc-targeting-summary dd[data-key="status"]' ).text( status || i18n.statusPending || 'Pending confirmation' ).removeClass( 'is-empty' );
	}

	function actionLabel( key, fallback ) {
		var map = {
			confirm: i18n.createSetup || 'Create setup',
			edit: i18n.editSetup || 'Edit setup',
			debug: i18n.showDebug || 'Show debug',
			cancel: i18n.cancel || 'Cancel',
			use_split: i18n.useSplit || 'Yes, use this split',
			edit_split: i18n.editSplit || 'Edit split',
			ask_ai: i18n.askAiCheck || i18n.askAi || 'Ask AI to check',
			ask_ai_again: i18n.askAiAgain || 'Ask AI again',
			choose_split: i18n.chooseSplit || 'Choose split',
			edit_manually: i18n.editManually || 'Edit manually',
			accept_likely_interpretation: i18n.useInterpretation || 'This looks correct',
			edit_ambiguities: i18n.chooseLocationAudience || 'Choose location/audience',
		};
		return map[ key ] || fallback || key;
	}

	function proposalActions( proposalId, response ) {
		response = response || state.lastResponse || {};
		var proposal = ( response.proposal || state.proposal || {} );
		var actions = response.actions || [];
		var $wrap = $( '<div>', { class: 'rwgc-targeting-assistant__actions' } );
		var hasInferred = !!( response.inferred_plan || proposal.inferred_plan );
		var hasAmbiguity = response.status === 'needs_confirmation' || !!( response.ambiguities && response.ambiguities.length );

		// Action review cards own the primary controls (resolve / create) inside
		// the setup panel, so the chat bubble only offers secondary actions.
		if ( proposal.action_cards && proposal.action_cards.length ) {
			$wrap.append(
				$( '<button>', { type: 'button', class: 'button rwgc-geo-btn', text: i18n.showDebug || 'Show debug', 'data-action': 'debug' } ),
				$( '<button>', { type: 'button', class: 'button-link rwgc-geo-btn', text: i18n.cancel || 'Cancel', 'data-action': 'cancel' } )
			);
			$wrap.data( 'proposal-id', proposalId );
			return $wrap;
		}

		if ( actions.length ) {
			actions.forEach( function ( row ) {
				var key = row.key || '';
				var primary = ( 'confirm' === key || 'accept_likely_interpretation' === key || ( 'use_split' === key && hasInferred ) );
				if ( 'use_split' === key && ! hasInferred ) {
					return;
				}
				if ( 'accept_likely_interpretation' === key && ! hasAmbiguity ) {
					return;
				}
				$wrap.append(
					$( '<button>', {
						type: 'button',
						class: 'button rwgc-geo-btn' + ( primary ? ' button-primary' : '' ) + ( 'cancel' === key ? ' button-link' : '' ),
						text: row.label || actionLabel( key, key ),
						'data-action': key,
					} )
				);
			} );
		} else {
			var ready = responseCanExecute( proposal );
			if ( ready ) {
				$wrap.append(
					$( '<button>', { type: 'button', class: 'button button-primary rwgc-geo-btn', text: i18n.createSetup || 'Create setup', 'data-action': 'confirm' } )
				);
			} else if ( hasInferred ) {
				$wrap.append(
					$( '<button>', { type: 'button', class: 'button button-primary rwgc-geo-btn', text: i18n.useSplit || 'Yes, use this split', 'data-action': 'use_split' } )
				);
			}
			if ( ! ready ) {
				$wrap.append(
					$( '<button>', { type: 'button', class: 'button rwgc-geo-btn', text: hasInferred ? ( i18n.editSplit || 'Edit split' ) : ( i18n.chooseSplit || 'Choose split' ), 'data-action': hasInferred ? 'edit_split' : 'choose_split' } )
				);
			}
			if ( response.ai_available ) {
				$wrap.append(
					$( '<button>', { type: 'button', class: 'button rwgc-geo-btn', text: hasInferred ? ( i18n.askAiCheck || 'Ask AI to check' ) : ( i18n.askAi || 'Ask AI' ), 'data-action': 'ask_ai' } )
				);
			}
			if ( ready ) {
				$wrap.append(
					$( '<button>', { type: 'button', class: 'button rwgc-geo-btn', text: i18n.editSetup || 'Edit setup', 'data-action': 'edit' } ),
					$( '<button>', { type: 'button', class: 'button rwgc-geo-btn', text: i18n.showDebug || 'Show debug', 'data-action': 'debug' } )
				);
			}
			$wrap.append(
				$( '<button>', { type: 'button', class: 'button-link rwgc-geo-btn', text: i18n.cancel || 'Cancel', 'data-action': 'cancel' } )
			);
		}
		$wrap.data( 'proposal-id', proposalId );
		return $wrap;
	}

	function responseCanExecute( proposal ) {
		if ( typeof proposal.can_execute === 'boolean' ) {
			return proposal.can_execute;
		}
		return proposal.proposal_ready !== false;
	}

	function formatProposalHtml( response ) {
		var proposal = response.proposal || {};
		var message = response.message || proposal.summary || '';

		// Card flow: state the action count and how many fields still need work,
		// instead of asking the user to accept a guessed interpretation.
		if ( proposal.action_cards && proposal.action_cards.length ) {
			var count = proposal.action_cards.length;
			var attention = remainingResolutions( proposal );
			var line = ( i18n.foundActionsPrefix || 'I found' ) + ' ' + count + ' ' +
				( count === 1 ? ( i18n.actionWord2 || 'action' ) : ( i18n.actionsWord2 || 'actions' ) ) + '.';
			if ( attention > 0 ) {
				line += ' ' + attention + ' ' +
					( attention === 1 ? ( i18n.fieldNeedsAttention || 'field needs attention' ) : ( i18n.fieldsNeedAttention || 'fields need attention' ) ) +
					' ' + ( i18n.beforeCreate || 'before this can be created.' );
			} else {
				line += ' ' + ( i18n.allResolvedReady || 'Everything is resolved — you can create the setup.' );
			}
			var cardHtml = '<p><strong>' + esc( line ) + '</strong></p>';
			cardHtml += '<p class="description">' + esc( i18n.reviewInPanel || 'Review and resolve each action in the setup panel on the right.' ) + '</p>';
			return cardHtml;
		}

		var html = '<p><strong>' + esc( message.split( '\n\n' )[0] || message ) + '</strong></p>';
		if ( response.status === 'needs_confirmation' || ( response.ambiguities && response.ambiguities.length ) ) {
			html += renderAmbiguitiesHtml( response );
		} else {
			var inferred = response.inferred_plan || proposal.inferred_plan;
			if ( inferred && response.status === 'needs_clarification' ) {
				html += renderInferredPlanHtml( inferred );
			} else if ( inferred ) {
				html += renderInferredPlanHtml( inferred );
			}
		}
		if ( response.badge || proposal.interpretation_badge ) {
			html += '<p><em class="rwgc-targeting-assistant__badge">' + esc( response.badge || proposal.interpretation_badge ) + '</em></p>';
		}
		if ( proposal.steps && proposal.steps.length && responseCanExecute( proposal ) ) {
			html += '<ol class="rwgc-targeting-assistant__steps">';
			proposal.steps.forEach( function ( step ) {
				html += '<li>' + esc( step.label || '' ) + '</li>';
			} );
			html += '</ol>';
		}
		if ( proposal.warnings && proposal.warnings.length ) {
			html += '<ul class="rwgc-targeting-assistant__warnings">';
			proposal.warnings.forEach( function ( w ) {
				html += '<li>' + esc( w ) + '</li>';
			} );
			html += '</ul>';
		}
		return html;
	}

	function applyInterpretResponse( response ) {
		state.lastResponse = response;
		state.proposal = response.proposal || null;
		state.cardResolutions = {};
		if ( typeof response.can_execute === 'boolean' && state.proposal ) {
			state.proposal.can_execute = response.can_execute;
		}
		if ( response.inferred_plan && state.proposal ) {
			state.proposal.inferred_plan = response.inferred_plan;
		}
		if ( response.ambiguities ) {
			state.ambiguities = response.ambiguities;
		}
		if ( response.ai_interpretation ) {
			state.aiInterpretation = response.ai_interpretation;
		}
		state.proposalId = response.proposal_id || '';
		state.debug = response.debug || null;
		updateSetupPanel( state.proposal, setupStatusLabel( response, state.proposal ) );
		appendAssistant( formatProposalHtml( response ), proposalActions( state.proposalId, response ) );
	}

	function buildContext() {
		return { screen: 'targeting_assistant' };
	}

	function apiPost( url, payload ) {
		return $.ajax( {
			url: url,
			method: 'POST',
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', cfg.restNonce );
			},
			contentType: 'application/json',
			data: JSON.stringify( payload ),
		} );
	}

	function schedulePreview() {
		clearTimeout( state.previewTimer );
		var phrase = $( '#rwgc-targeting-phrase' ).val();
		phrase = phrase ? String( phrase ).trim() : '';
		if ( ! phrase || ! cfg.previewUrl ) {
			state.preview = null;
			++state.previewSeq;
			updateLivePreview( null );
			return;
		}
		state.previewTimer = setTimeout( function () {
			var seq = ++state.previewSeq;
			state.preview = null;
			$( '#rwgc-targeting-detecting' ).removeClass( 'rwgc-is-hidden' );
			apiPost( cfg.previewUrl, { message: phrase, context: buildContext() } )
				.done( function ( data ) {
					if ( seq !== state.previewSeq ) {
						return;
					}
					state.preview = data;
					updateLivePreview( data );
				} )
				.always( function () {
					if ( seq === state.previewSeq ) {
						$( '#rwgc-targeting-detecting' ).addClass( 'rwgc-is-hidden' );
					}
				} );
		}, cfg.previewDebounce || 600 );
	}

	function sendMessage() {
		var phrase = $( '#rwgc-targeting-phrase' ).val();
		phrase = phrase ? String( phrase ).trim() : '';
		if ( ! phrase ) {
			return;
		}
		if ( ! cfg.interpretUrl ) {
			appendAssistant( esc( i18n.geoAiRequired || 'ReactWoo Geo AI is required.' ) );
			return;
		}

		++state.previewSeq;
		var detected = state.preview && state.preview.detected ? state.preview.detected : null;
		state.lastMessage = phrase;
		appendUser( phrase, detected );
		$( '#rwgc-targeting-phrase' ).val( '' );
		state.preview = null;
		updateLivePreview( null );

		var $loading = assistantBubble( '<p>' + esc( i18n.checking || 'Checking what Geo Core can build…' ) + '</p>' );
		$loading.addClass( 'is-loading' );
		$( '#rwgc-targeting-thread' ).append( $loading );
		scrollThread();

		var seq = ++state.sendSeq;
		apiPost( cfg.interpretUrl, { message: phrase, context: buildContext(), debug: true } )
			.done( function ( response ) {
				if ( seq !== state.sendSeq ) {
					return;
				}
				$loading.remove();
				if ( ! response || ! response.success ) {
					appendAssistant( esc( ( response && response.message ) ? response.message : ( i18n.lowConfidence || 'Could not interpret that command.' ) ) );
					if ( response && response.debug ) {
						state.debug = response.debug;
					}
					return;
				}
				state.proposal = response.proposal || null;
				applyInterpretResponse( response );
			} )
			.fail( function ( xhr ) {
				$loading.remove();
				var msg = i18n.geoAiRequired || 'Could not interpret that command.';
				if ( xhr.responseJSON && xhr.responseJSON.message ) {
					msg = xhr.responseJSON.message;
				}
				appendAssistant( esc( msg ) );
			} );
	}

	function recordLearningFeedback( outcome, extra ) {
		if ( ! cfg.learningEventUrl || ! state.proposal ) {
			return;
		}
		extra = extra || {};
		var proposal = state.proposal;
		var payload = {
			raw_phrase: proposal.original_message || state.lastMessage || '',
			normalised_phrase: proposal.original_message || state.lastMessage || '',
			intent_key: proposal.intent || '',
			action_key: proposal.matched_action || '',
			params: extra.params || proposal.params || {},
			confidence: proposal.confidence || 0,
			outcome: outcome,
			approved_by_user: outcome === 'executed' || outcome === 'accepted' || outcome === 'accepted_inferred_split' || outcome === 'accepted_ai_split' || outcome === 'accepted_likely_interpretation',
			interpretation_source: extra.source || proposal.interpretation_source || ( state.lastResponse && state.lastResponse.source ) || '',
		};
		if ( extra.correction ) {
			payload.correction = extra.correction;
		}
		if ( proposal.inferred_plan || ( state.lastResponse && state.lastResponse.inferred_plan ) ) {
			payload.inferred_plan = extra.inferred_plan || proposal.inferred_plan || state.lastResponse.inferred_plan;
		}
		if ( state.ambiguities && state.ambiguities.length ) {
			payload.ambiguities = state.ambiguities;
		}
		if ( state.aiInterpretation ) {
			payload.ai_likely_interpretation = state.aiInterpretation;
		}
		if ( extra.user_confirmed_interpretation ) {
			payload.user_confirmed_interpretation = extra.user_confirmed_interpretation;
		}
		apiPost( cfg.learningEventUrl, payload );
	}

	function buildInterpretationPayload( resolutions ) {
		resolutions = resolutions || {};
		var ambiguities = ( state.ambiguities || ( state.lastResponse && state.lastResponse.ambiguities ) || [] ).map( function ( row ) {
			var copy = Object.assign( {}, row );
			if ( resolutions[ copy.field ] ) {
				copy.likely = resolutions[ copy.field ];
			}
			return copy;
		} );
		return {
			message: state.lastMessage || ( state.proposal && state.proposal.original_message ) || '',
			ambiguities: ambiguities,
			resolutions: resolutions,
			ai_interpretation: state.aiInterpretation || ( state.lastResponse && state.lastResponse.ai_interpretation ) || {},
			base: {
				conditions: state.proposal && state.proposal.conditions ? state.proposal.conditions : [],
				condition_match: state.proposal && state.proposal.condition_match ? state.proposal.condition_match : 'all',
			},
			source: ( state.lastResponse && state.lastResponse.source ) || ( state.proposal && state.proposal.interpretation_source ) || 'local_parser',
			context: buildContext(),
			debug: true,
		};
	}

	function confirmInterpretation( resolutions ) {
		if ( ! cfg.confirmInterpretationUrl ) {
			return;
		}
		var payload = buildInterpretationPayload( resolutions || {} );
		apiPost( cfg.confirmInterpretationUrl, payload ).done( function ( response ) {
			recordLearningFeedback( 'accepted_likely_interpretation', {
				source: payload.source,
				user_confirmed_interpretation: {
					resolutions: payload.resolutions,
					ambiguities: payload.ambiguities,
				},
			} );
			if ( response && response.success ) {
				state.ambiguities = null;
				state.aiInterpretation = null;
				applyInterpretResponse( response );
			}
		} );
	}

	function collectAmbiguityResolutionsFromForm() {
		var resolutions = {};
		$( '#rwgc-targeting-edit-fields [data-ambiguity-field]' ).each( function () {
			var field = $( this ).data( 'ambiguity-field' );
			var value = $( this ).val();
			if ( field && value ) {
				resolutions[ field ] = value;
			}
		} );
		var logic = $( '#rwgc-edit-logic' ).val();
		if ( state.proposal ) {
			state.proposal.condition_match = logic || state.proposal.condition_match || 'all';
		}
		return resolutions;
	}

	function confirmInferredSplit() {
		var inferred = ( state.proposal && state.proposal.inferred_plan ) || ( state.lastResponse && state.lastResponse.inferred_plan );
		if ( ! inferred || ! cfg.confirmSplitUrl ) {
			return;
		}
		var source = ( state.lastResponse && state.lastResponse.source ) || ( state.proposal && state.proposal.interpretation_source ) || 'local_parser';
		apiPost( cfg.confirmSplitUrl, {
			message: state.lastMessage || ( state.proposal && state.proposal.original_message ) || '',
			inferred_plan: inferred,
			source: source,
			context: buildContext(),
			debug: true,
		} ).done( function ( response ) {
			recordLearningFeedback( 'accepted_inferred_split', {
				source: source,
				params: inferred,
				inferred_plan: inferred,
			} );
			if ( response && response.success ) {
				applyInterpretResponse( response );
			}
		} );
	}

	function askAiToCheck() {
		var phrase = state.lastMessage || ( state.proposal && state.proposal.original_message ) || '';
		if ( ! phrase || ! cfg.interpretUrl ) {
			return;
		}
		var $loading = assistantBubble( '<p>' + esc( i18n.askAiCheck || 'Ask AI to check' ) + '…</p>' );
		$loading.addClass( 'is-loading' );
		$( '#rwgc-targeting-thread' ).append( $loading );
		scrollThread();
		apiPost( cfg.interpretUrl, {
			message: phrase,
			context: Object.assign( {}, buildContext(), { force_ai: true } ),
			debug: true,
		} ).done( function ( response ) {
			$loading.remove();
			if ( response && response.success ) {
				applyInterpretResponse( response );
			}
		} ).fail( function () {
			$loading.remove();
		} );
	}

	function executeProposal() {
		if ( ! state.proposalId || ! cfg.executeUrl ) {
			goWorkflowFromProposal();
			return;
		}
		apiPost( cfg.executeUrl, { proposal_id: state.proposalId, resolutions: collectCardResolutions() } )
			.done( function ( response ) {
				recordLearningFeedback( 'executed' );
				var result = response && response.result ? response.result : {};
				// Legacy redirect path (Geo Core executor not available).
				if ( result.redirect_steps && result.redirect_steps.length ) {
					persistPortableAndGo( result.redirect_steps[0].url );
					return;
				}
				if ( result.created_rules || result.manual_steps || result.preview_only || result.needs_attention ) {
					renderExecutionSummary( result );
					return;
				}
				goWorkflowFromProposal();
				appendAssistant( esc( result.message || i18n.setupConfirmed || 'Setup confirmed.' ) );
				updateSetupPanel( state.proposal, i18n.statusConfirmed || 'Confirmed' );
			} )
			.fail( function ( jqxhr ) {
				var data = jqxhr && jqxhr.responseJSON && jqxhr.responseJSON.data ? jqxhr.responseJSON.data : {};
				if ( data.requires_resolution && data.action_cards ) {
					if ( state.proposal ) {
						state.proposal.action_cards = data.action_cards;
						state.proposal.fields_needing_attention = data.fields_needing_attention || 0;
						state.proposal.requires_resolution = true;
					}
					state.cardResolutions = {};
					var msg = ( jqxhr.responseJSON && jqxhr.responseJSON.message ) || i18n.cardResolveRemaining || 'Some fields still need resolving.';
					appendAssistant( esc( msg ) );
					updateSetupPanel( state.proposal, i18n.statusNeedsResolution || 'Needs resolution' );
					return;
				}
				goWorkflowFromProposal();
			} );
	}

	function renderExecutionSummary( result ) {
		var $plan = $( '#rwgc-targeting-setup-plan' );
		$( '#rwgc-targeting-setup-empty' ).addClass( 'rwgc-is-hidden' );
		$( '#rwgc-targeting-setup-hint' ).addClass( 'rwgc-is-hidden' );
		$plan.removeClass( 'rwgc-is-hidden' ).empty();

		var $wrap = $( '<div>', { class: 'rwgc-geo-result' } );
		$wrap.append( $( '<p>', { class: 'rwgc-geo-result__message' } ).text( result.message || '' ) );

		( result.created_rules || [] ).forEach( function ( rule ) {
			var $row = $( '<div>', { class: 'rwgc-geo-result__row rwgc-geo-result__row--ok' } );
			if ( rule.edit_url ) {
				$row.append( $( '<a>', { href: rule.edit_url, target: '_blank', rel: 'noopener' } ).text( rule.title || ( 'Rule #' + rule.id ) ) );
			} else {
				$row.append( document.createTextNode( rule.title || ( 'Rule #' + rule.id ) ) );
			}
			( rule.warnings || [] ).forEach( function ( w ) {
				$row.append( $( '<span>', { class: 'rwgc-geo-result__warn' } ).text( w ) );
			} );
			$wrap.append( $row );
		} );

		( result.manual_steps || [] ).forEach( function ( step ) {
			$wrap.append(
				$( '<div>', { class: 'rwgc-geo-result__row rwgc-geo-result__row--manual' } )
					.text( ( step.label ? step.label + ' — ' : '' ) + ( step.reason || '' ) )
			);
		} );

		( result.needs_attention || [] ).forEach( function ( item ) {
			$wrap.append(
				$( '<div>', { class: 'rwgc-geo-result__row rwgc-geo-result__row--attention' } )
					.text( ( item.label ? item.label + ' — ' : '' ) + ( item.reason || '' ) )
			);
		} );

		( result.preview_only || [] ).forEach( function ( item ) {
			$wrap.append(
				$( '<div>', { class: 'rwgc-geo-result__row rwgc-geo-result__row--preview' } )
					.text( ( item.label || '' ) + ' — ' + ( i18n.cardPreviewSkipped || 'preview only, nothing created' ) )
			);
		} );

		$plan.append( $wrap );
		$( '#rwgc-targeting-summary' ).removeClass( 'rwgc-is-hidden' );
		appendAssistant( esc( result.message || i18n.setupConfirmed || 'Setup confirmed.' ) );
	}

	function persistPortableAndGo( url ) {
		if ( state.proposal && state.proposal.portable_rule_set ) {
			try {
				sessionStorage.setItem(
					'rwgc_targeting_assistant_portable',
					JSON.stringify( { portable_rule_set: state.proposal.portable_rule_set } )
				);
			} catch ( e ) {}
		}
		window.location.href = url || $( '#rwgc-targeting-assistant' ).data( 'variant-url' );
	}

	function goWorkflowFromProposal() {
		var $root = $( '#rwgc-targeting-assistant' );
		var url = $root.data( 'variant-url' );
		var proposal = state.proposal || {};
		var pageId = proposal.resolved_target && proposal.resolved_target.id ? proposal.resolved_target.id : 0;
		if ( pageId ) {
			url += ( url.indexOf( '?' ) >= 0 ? '&' : '?' ) + 'rwgc_master_page_id=' + pageId;
		}
		if ( proposal.steps && proposal.steps.length > 1 ) {
			url += ( url.indexOf( '?' ) >= 0 ? '&' : '?' ) + 'rwgc_condition_type=create_rule';
		} else if ( proposal.params && proposal.params.countries ) {
			url += ( url.indexOf( '?' ) >= 0 ? '&' : '?' ) + 'rwgc_condition_type=countries';
		}
		persistPortableAndGo( url );
	}

	function showDebug() {
		if ( ! state.debug ) {
			return;
		}
		$( '#rwgc-targeting-debug-body' ).text( JSON.stringify( state.debug, null, 2 ) );
		$( '#rwgc-targeting-debug-panel' ).removeClass( 'rwgc-is-hidden' ).prop( 'hidden', false );
	}

	function editTargetLabel( proposal ) {
		if ( ! proposal ) {
			return '';
		}
		if ( proposal.resolved_target && proposal.resolved_target.label ) {
			return proposal.resolved_target.label;
		}
		var plan = proposal.interpretation_plan;
		if ( plan && plan.actions && plan.actions.length === 1 && plan.actions[0].target && plan.actions[0].target.label ) {
			return plan.actions[0].target.label;
		}
		if ( proposal.params && proposal.params.page_ref ) {
			return proposal.params.page_ref;
		}
		return '';
	}

	function editContextLine( labelText, targetText ) {
		var $line = $( '<p>', { class: 'rwgc-targeting-assistant__edit-context' } );
		$line.append( document.createTextNode( labelText + ' ' ) );
		$line.append( $( '<strong>' ).text( targetText ) );
		return $line;
	}

	function showEditPanel() {
		var proposal = state.proposal;
		var ambiguities = state.ambiguities || ( state.lastResponse && state.lastResponse.ambiguities ) || [];
		if ( ambiguities.length ) {
			showAmbiguityEditPanel( ambiguities );
			return;
		}
		if ( ! proposal ) {
			return;
		}
		var $panel = $( '#rwgc-targeting-edit-panel' );
		$panel.find( 'h3' ).text( i18n.editSetup || 'Edit setup' );
		$panel.find( '.rwgc-targeting-assistant__edit-inner > .description' ).text( i18n.editSetupHint || 'Adjust detected values before confirming.' );
		$panel.find( '#rwgc-targeting-edit-save' ).text( i18n.applyChanges || 'Apply changes' );
		var $fields = $( '#rwgc-targeting-edit-fields' ).empty();

		var contextLabel = editTargetLabel( proposal );
		if ( contextLabel ) {
			$fields.append( editContextLine( i18n.editingFor || 'You are editing:', contextLabel ) );
		}

		var $group = $( '<div>', { class: 'rwgc-targeting-assistant__edit-group' } );
		var $page = $( '<select>', { id: 'rwgc-edit-page', class: 'widefat' } );
		$page.append( $( '<option>', { value: '', text: i18n.choosePage || 'Choose a page…' } ) );
		( cfg.pages || [] ).forEach( function ( p ) {
			$page.append( $( '<option>', { value: p.id, text: p.title } ) );
		} );
		if ( proposal.resolved_target && proposal.resolved_target.id ) {
			$page.val( String( proposal.resolved_target.id ) );
		}
		$group.append(
			$( '<label>', { 'for': 'rwgc-edit-page', class: 'rwgc-targeting-assistant__edit-label' } ).text( i18n.pageLabel || 'Page' ),
			$page
		);
		$fields.append( $group );
		$panel.removeClass( 'rwgc-is-hidden' ).prop( 'hidden', false );
	}

	function showAmbiguityEditPanel( ambiguities ) {
		var $panel = $( '#rwgc-targeting-edit-panel' );
		$panel.find( 'h3' ).text( i18n.editInterpretation || 'Edit interpretation' );
		$panel.find( '.rwgc-targeting-assistant__edit-inner > .description' ).text( i18n.editInterpretationHint || 'Choose the right location or audience for each action below.' );
		$panel.find( '#rwgc-targeting-edit-save' ).text( i18n.applyInterpretation || 'Apply interpretation' );
		var $fields = $( '#rwgc-targeting-edit-fields' ).empty();
		var draft = ( state.aiInterpretation && state.aiInterpretation.proposal_draft ) || {};
		var targetLabel = ( draft.target && draft.target.label ) || ( state.proposal && state.proposal.params && state.proposal.params.page_ref ) || 'Home page';
		$fields.append( editContextLine( i18n.choosingFor || 'Choosing for:', targetLabel ) );

		ambiguities.forEach( function ( row ) {
			var field = row.field || '';
			var $group = $( '<div>', { class: 'rwgc-targeting-assistant__edit-group' } );
			var $head = $( '<div>', { class: 'rwgc-targeting-assistant__edit-head' } );
			$head.append( $( '<span>', { class: 'rwgc-targeting-assistant__edit-label' } ).text( ambiguityFieldLabel( field ) ) );
			var rowScope = ambiguityRowScope( row, targetLabel );
			if ( rowScope ) {
				$head.append( $( '<span>', { class: 'rwgc-targeting-assistant__edit-scope' } ).text( ( i18n.forScope || 'for' ) + ' ' + rowScope ) );
			}
			$group.append( $head );
			$group.append( $( '<p>', { class: 'description' } ).text( ( i18n.detectedPrefix || 'Detected:' ) + ' ' + ( row.raw || '' ) ) );
			var $select = $( '<select>', { class: 'widefat', 'data-ambiguity-field': field } );
			( row.alternatives || [] ).forEach( function ( alt ) {
				var label = field === 'location' ? locationOptionLabel( alt ) : audienceOptionLabel( alt );
				$select.append( $( '<option>', { value: alt, text: label } ) );
			} );
			$select.append( $( '<option>', { value: '', text: i18n.removeCondition || 'Remove condition' } ) );
			if ( row.likely ) {
				$select.val( row.likely );
			}
			$group.append( $select );
			$fields.append( $group );
		} );

		if ( draft.rule && draft.rule.conditions ) {
			draft.rule.conditions.forEach( function ( cond ) {
				if ( cond.type === 'weather_condition' ) {
					$fields.append( $( '<p>' ).append( $( '<strong>' ).text( i18n.weatherLabel || 'Weather' ), document.createTextNode( ' ' + ( cond.label || '' ) ) ) );
				}
			} );
		}

		var $logic = $( '<select>', { id: 'rwgc-edit-logic', class: 'widefat' } );
		$logic.append( $( '<option>', { value: 'all', text: i18n.matchAll || 'Match all conditions' } ) );
		$logic.append( $( '<option>', { value: 'any', text: i18n.matchAny || 'Match any condition' } ) );
		if ( draft.rule && draft.rule.logic ) {
			$logic.val( draft.rule.logic );
		}
		$fields.append( $( '<label>', { 'for': 'rwgc-edit-logic' } ).text( i18n.logicLabel || 'Logic' ), $logic );
		$panel.removeClass( 'rwgc-is-hidden' ).prop( 'hidden', false );
	}

	function buildHintCloud() {
		var $hints = $( '#rwgc-targeting-hints' ).empty();
		( cfg.keywordHints || [] ).forEach( function ( group ) {
			if ( ! group.items || ! group.items.length ) {
				return;
			}
			var $row = $( '<div>', { class: 'rwgc-targeting-assistant__hint-group' } );
			$row.append( $( '<span>', { class: 'rwgc-targeting-assistant__hint-label', text: group.label || '' } ) );
			group.items.forEach( function ( item ) {
				$row.append(
					$( '<button>', {
						type: 'button',
						class: 'rwgc-targeting-assistant__hint-chip',
						text: item.label || item.text,
						'data-insert': item.insert || item.label || item.text,
					} )
				);
			} );
			$hints.append( $row );
		} );
	}

	function start() {
		state.proposal = null;
		state.proposalId = '';
		state.preview = null;
		state.debug = null;
		state.lastMessage = '';
		state.lastResponse = null;
		state.ambiguities = null;
		state.aiInterpretation = null;
		state.cardResolutions = {};
		$( '#rwgc-targeting-thread' ).empty();
		$( '#rwgc-targeting-phrase' ).val( '' );
		updateLivePreview( null );
		updateSetupPanel( null );
		appendAssistant(
			'<p>' + esc( i18n.opening || 'Tell me what you want to target. I can detect countries, devices, pages, variants, weather, campaigns, URLs, popups and product rules.' ) + '</p>'
		);
	}

	$( function () {
		if ( ! $( '#rwgc-targeting-assistant' ).length ) {
			return;
		}
		buildHintCloud();
		start();

		$( '#rwgc-targeting-phrase' ).on( 'input', schedulePreview );
		$( '#rwgc-targeting-send-btn' ).on( 'click', sendMessage );
		$( '#rwgc-targeting-reset-btn' ).on( 'click', start );
		$( '#rwgc-targeting-phrase' ).on( 'keydown', function ( e ) {
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				sendMessage();
			}
		} );

		$( '#rwgc-targeting-hints' ).on( 'click', '.rwgc-targeting-assistant__hint-chip', function () {
			var insert = $( this ).data( 'insert' ) || $( this ).text();
			var $ta = $( '#rwgc-targeting-phrase' );
			var cur = $ta.val() ? String( $ta.val() ) : '';
			$ta.val( cur + ( cur && ! /\s$/.test( cur ) ? ' ' : '' ) + insert ).trigger( 'input' ).focus();
		} );

		$( '#rwgc-targeting-thread' ).on( 'click', '[data-action]', function () {
			var action = $( this ).data( 'action' );
			if ( 'confirm' === action ) {
				executeProposal();
			} else if ( 'accept_likely_interpretation' === action ) {
				confirmInterpretation();
			} else if ( 'use_split' === action ) {
				confirmInferredSplit();
			} else if ( 'ask_ai' === action || 'ask_ai_again' === action ) {
				askAiToCheck();
			} else if ( 'edit' === action || 'edit_split' === action || 'edit_manually' === action || 'edit_ambiguities' === action ) {
				showEditPanel();
			} else if ( 'debug' === action ) {
				showDebug();
			} else if ( 'cancel' === action ) {
				recordLearningFeedback( 'rejected' );
				start();
			}
		} );

		$( '#rwgc-targeting-setup-plan' ).on( 'click', '[data-card-action]', function () {
			handleCardAction( $( this ) );
		} );

		$( '#rwgc-targeting-debug-close, #rwgc-targeting-edit-cancel' ).on( 'click', function () {
			$( '#rwgc-targeting-debug-panel, #rwgc-targeting-edit-panel' ).addClass( 'rwgc-is-hidden' ).prop( 'hidden', true );
		} );

		$( '#rwgc-targeting-edit-save' ).on( 'click', function () {
			var ambiguities = state.ambiguities || ( state.lastResponse && state.lastResponse.ambiguities ) || [];
			if ( ambiguities.length ) {
				var resolutions = collectAmbiguityResolutionsFromForm();
				$( '#rwgc-targeting-edit-panel' ).addClass( 'rwgc-is-hidden' ).prop( 'hidden', true );
				confirmInterpretation( resolutions );
				recordLearningFeedback( 'corrected', {
					user_confirmed_interpretation: { resolutions: resolutions },
				} );
				return;
			}
			var pageId = parseInt( $( '#rwgc-edit-page' ).val(), 10 );
			if ( state.proposal && pageId ) {
				state.proposal.resolved_target = { type: 'page', id: pageId };
				var match = ( cfg.pages || [] ).filter( function ( p ) {
					return parseInt( p.id, 10 ) === pageId;
				} );
				if ( match.length && state.proposal.params ) {
					state.proposal.params.page_ref = match[0].title;
				}
				updateSetupPanel( state.proposal, setupStatusLabel( state.lastResponse, state.proposal ) );
			}
			$( '#rwgc-targeting-edit-panel' ).addClass( 'rwgc-is-hidden' ).prop( 'hidden', true );
			if ( state.lastResponse && state.lastResponse.inferred_plan ) {
				recordLearningFeedback( 'corrected', {
					correction: {
						original: state.lastResponse.inferred_plan,
						corrected: state.proposal.params || {},
					},
				} );
			}
		} );
	} );
}( jQuery ) );
