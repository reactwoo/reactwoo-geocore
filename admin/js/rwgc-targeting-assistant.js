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
		cardLogic: {},
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

	function remainingForCard( idx, card, proposal ) {
		if ( isCardRemoved( idx ) ) {
			return 0;
		}
		var n = 0;
		( ( card && card.requiredResolutions ) || [] ).forEach( function ( req ) {
			if ( card.uses_shared_target && req.field === 'target' ) {
				return;
			}
			if ( ! fieldResolution( idx, req.field, req.raw ) ) {
				n++;
			}
		} );
		return n;
	}

	function remainingResolutions( proposal ) {
		var total = 0;
		( ( proposal && proposal.action_cards ) || [] ).forEach( function ( card, idx ) {
			total += remainingForCard( idx, card, proposal );
		} );
		( ( proposal && proposal.shared_targets ) || [] ).forEach( function ( group ) {
			if ( ! sharedTargetResolved( group ) ) {
				total++;
			}
		} );
		return total;
	}

	function cardsReadyCount( proposal ) {
		var ready = 0;
		( ( proposal && proposal.action_cards ) || [] ).forEach( function ( card, idx ) {
			if ( isCardRemoved( idx ) ) {
				return;
			}
			if ( 'ready' === ( card.status || '' ) && remainingForCard( idx, card, proposal ) === 0 ) {
				ready++;
			}
		} );
		return ready;
	}

	function firstUnresolvedCardIndex( proposal ) {
		var cards = ( proposal && proposal.action_cards ) || [];
		for ( var i = 0; i < cards.length; i++ ) {
			if ( isCardRemoved( i ) ) {
				continue;
			}
			if ( remainingForCard( i, cards[ i ], proposal ) > 0 ) {
				return i;
			}
		}
		if ( hasUnresolvedSharedTarget( proposal ) ) {
			return 0;
		}
		return -1;
	}

	function isInvalidCreateRuleSplit( proposal ) {
		if ( proposal && proposal.invalid_interpretation ) {
			return true;
		}
		var cards = ( proposal && proposal.action_cards ) || [];
		if ( cards.length <= 1 ) {
			return false;
		}
		var phantom = { show: true, hide: true, update_original_targeting: true };
		var hasPhantom = cards.some( function ( card ) {
			return !!phantom[ card.type ];
		} );
		var phrase = ( state.lastMessage || ( proposal && proposal.original_message ) || '' ).toLowerCase();
		return hasPhantom && /\bcreate\s+(?:a\s+)?(?:[\w-]+\s+)?rule\b/.test( phrase );
	}

	function hubNeedsLabels( proposal ) {
		var labels = [];
		( ( proposal && proposal.action_cards ) || [] ).forEach( function ( card, idx ) {
			if ( isCardRemoved( idx ) ) {
				return;
			}
			( ( card && card.requiredResolutions ) || [] ).forEach( function ( req ) {
				if ( fieldResolution( idx, req.field, req.raw ) ) {
					return;
				}
				var label = req.raw || req.field;
				if ( req.field === 'target' ) {
					label = card.target && card.target.type === 'popup'
						? ( i18n.hubNeedPopup || 'Popup target' )
						: ( i18n.hubNeedTarget || 'Target page' );
				} else if ( req.field === 'audience' ) {
					label = i18n.hubNeedAudience || 'Audience segments';
				} else if ( req.field === 'campaign' ) {
					label = i18n.hubNeedCampaign || 'Campaign';
				} else if ( req.field === 'location' ) {
					label = i18n.hubNeedLocation || 'Location';
				} else if ( req.field === 'traffic_source' ) {
					label = i18n.hubNeedTraffic || 'Google Ads mapping';
				}
				if ( labels.indexOf( label ) === -1 ) {
					labels.push( label );
				}
			} );
		} );
		if ( hasUnresolvedSharedTarget( proposal ) ) {
			var sharedLabel = i18n.hubNeedTarget || 'Target page';
			if ( labels.indexOf( sharedLabel ) === -1 ) {
				labels.push( sharedLabel );
			}
		}
		return labels;
	}

	function hubReadyLabels( proposal ) {
		var labels = [];
		var cards = ( proposal && proposal.action_cards ) || [];
		if ( ! cards.length ) {
			return labels;
		}
		var card = cards[ 0 ];
		if ( isCardRemoved( 0 ) ) {
			return labels;
		}
		( card.condition_rows || [] ).forEach( function ( row ) {
			if ( row.type === 'condition_group' ) {
				if ( row.children && row.children.length ) {
					row.children.forEach( function ( child ) {
						if ( child.status === 'valid' && child.label && labels.indexOf( child.label ) === -1 ) {
							labels.push( child.label );
						}
					} );
				} else if ( row.status === 'valid' && row.label ) {
					labels.push( row.label );
				}
				return;
			}
			if ( row.status === 'valid' && row.label && labels.indexOf( row.label ) === -1 ) {
				labels.push( row.label );
			}
		} );
		return labels;
	}

	function primaryCreateRuleLabel( proposal ) {
		var cards = ( ( proposal && proposal.action_cards ) || [] ).filter( function ( c, i ) {
			return ! isCardRemoved( i );
		} );
		if ( 1 === cards.length && cards[ 0 ].type === 'create_rule' ) {
			return i18n.createRule || 'Create rule';
		}
		return ( i18n.createNActions || 'Create {n} actions' ).replace( '{n}', String( cards.length ) );
	}

	function jumpToActionReview() {
		var el = document.getElementById( 'rwgc-targeting-action-review' );
		if ( ! el ) {
			el = document.querySelector( '.rwgc-geo-review__head' );
		}
		if ( ! el ) {
			return;
		}
		if ( el.scrollIntoView ) {
			el.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
		el.classList.add( 'rwgc-geo-card--flash' );
		window.setTimeout( function () {
			el.classList.remove( 'rwgc-geo-card--flash' );
		}, 1200 );
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

	function dashicon( name ) {
		return $( '<span>', { class: 'dashicons dashicons-' + ( name || 'marker' ), 'aria-hidden': 'true' } );
	}

	function conditionTypeLabel( type ) {
		var map = {
			location: i18n.locationLabel || 'Location',
			weather: i18n.cardWeather || 'Weather',
			audience: i18n.audienceLabel || 'Audience',
			device: i18n.cardDevices || 'Device',
			url: 'URL',
			utm: 'UTM',
			visitor: i18n.cardVisitor || 'Visitor',
		};
		return map[ type ] || ( type ? String( type ).replace( /_/g, ' ' ) : '' );
	}

	// Which server resolution field a condition row maps to (only some types are
	// resolvable inline; the rest are display-only).
	function conditionResolutionField( type ) {
		if ( type === 'location' ) {
			return 'location';
		}
		if ( type === 'audience' ) {
			return 'audience';
		}
		return '';
	}

	function conditionOptionButton( idx, field, raw, opt ) {
		var kind = 'choose';
		var id = '';
		if ( opt.key === 'remove' || opt.key === 'any_audience' ) {
			kind = 'ignore';
		} else if ( opt.picker || opt.key === 'choose_audiences' ) {
			kind = 'pick_manual';
		} else if ( opt.key === 'refresh' ) {
			kind = 'refresh';
		} else if ( opt.value && opt.value.type && opt.value.code ) {
			id = opt.value.type + ':' + opt.value.code;
		}
		return $( '<button>', {
			type: 'button',
			class: 'button rwgc-resolution-option' + ( opt.key === 'remove' ? ' rwgc-resolution-option--danger' : '' ),
			text: opt.label || opt.key,
			'data-card-action': 'choose_condition',
			'data-card': idx,
			'data-field': field,
			'data-raw': raw || '',
			'data-kind': kind,
			'data-id': id,
			'data-label': opt.label || '',
		} );
	}

	function renderConditionCard( card, idx, row ) {
		var field = conditionResolutionField( row.type );
		var resolution = field ? fieldResolution( idx, field, row.raw ) : null;
		var resolved = ( row.status === 'valid' ) || !! resolution;

		var $cc = $( '<div>', {
			class: 'rwgc-condition-card' + ( resolved ? '' : ' rwgc-condition-card--warning' ),
		} );

		var $head = $( '<div>', { class: 'rwgc-condition-card__head' } );
		$head.append( dashicon( row.icon ) );
		var $meta = $( '<div>', { class: 'rwgc-condition-card__meta' } );
		var label = conditionTypeLabel( row.type ) + ( row.mode === 'exclude' ? ' (' + ( i18n.cardExclude || 'Exclude' ) + ')' : '' );
		$meta.append( $( '<span>', { class: 'rwgc-condition-card__type' } ).text( label ) );

		var valueText = row.label || row.raw || '';
		if ( resolution ) {
			valueText = resolution.kind === 'ignored'
				? ( i18n.anyAudience && row.type === 'audience' ? i18n.anyAudience : ( i18n.cardIgnored || 'Removed' ) )
				: ( resolution.label || valueText );
		}
		$meta.append( $( '<span>', { class: 'rwgc-condition-card__value' } ).text( valueText ) );
		$head.append( $meta );

		var pillKind = resolved ? 'ready' : 'needs-resolution';
		var pillText = resolved ? ( i18n.statusValid || 'Valid' ) : ( i18n.statusNeedsAttention || 'Needs attention' );
		$head.append( $( '<span>', { class: 'rwgc-status-pill rwgc-status-pill--' + pillKind } ).text( pillText ) );
		$cc.append( $head );

		if ( ! resolved && row.warning ) {
			$cc.append( $( '<p>', { class: 'rwgc-condition-card__warning' } ).text( row.warning ) );
		}

		if ( row.type === 'condition_group' && row.children && row.children.length ) {
			var $children = $( '<ul>', { class: 'rwgc-condition-card__children' } );
			row.children.forEach( function ( child ) {
				var childResolved = child.status === 'valid';
				var childLine = child.label || child.type || '';
				if ( ! childResolved ) {
					childLine += ' — ' + ( i18n.statusNeedsAttention || 'Needs attention' );
				}
				$children.append( $( '<li>' ).text( childLine ) );
			} );
			$cc.append( $children );
		}

		if ( resolution ) {
			$cc.append( $( '<button>', {
				type: 'button',
				class: 'button-link rwgc-geo-card__undo',
				text: i18n.cardUndo || 'Undo',
				'data-card-action': 'undo_field',
				'data-card': idx,
				'data-field': field,
				'data-raw': row.raw || '',
			} ) );
			return $cc;
		}

		if ( ! resolved && field && row.resolution_options && row.resolution_options.length ) {
			var $opts = $( '<div>', { class: 'rwgc-condition-card__options' } );
			row.resolution_options.forEach( function ( opt ) {
				$opts.append( conditionOptionButton( idx, field, row.raw, opt ) );
			} );
			$cc.append( $opts );
		}

		return $cc;
	}

	function renderConditionRows( card, idx ) {
		var rows = card.condition_rows || [];
		if ( ! rows.length ) {
			return null;
		}
		var $section = $( '<div>', { class: 'rwgc-action-card__section' } );
		$section.append( $( '<p>', { class: 'rwgc-action-card__section-label' } ).text( i18n.includeConditions || 'Conditions' ) );
		var $grid = $( '<div>', { class: 'rwgc-condition-grid' } );
		rows.forEach( function ( row ) {
			$grid.append( renderConditionCard( card, idx, row ) );
		} );
		$section.append( $grid );
		return $section;
	}

	function cardLogicOperator( card, idx ) {
		if ( state.cardLogic[ idx ] ) {
			return state.cardLogic[ idx ];
		}
		return ( card.logic && card.logic.operator ) || 'AND';
	}

	function renderLogicSection( card, idx ) {
		var op = cardLogicOperator( card, idx );
		var $section = $( '<div>', { class: 'rwgc-action-card__section rwgc-action-card__section--logic' } );
		$section.append( $( '<p>', { class: 'rwgc-action-card__section-label' } ).text( i18n.logicLabel || 'Logic' ) );
		var $toggle = $( '<div>', { class: 'rwgc-logic-toggle' } );
		[
			{ key: 'AND', label: i18n.matchAll || 'Match all conditions' },
			{ key: 'OR', label: i18n.matchAny || 'Match any condition' },
		].forEach( function ( choice ) {
			$toggle.append( $( '<button>', {
				type: 'button',
				class: 'button rwgc-logic-toggle__btn' + ( op === choice.key ? ' is-active' : '' ),
				text: choice.label,
				'data-card-action': 'set_logic',
				'data-card': idx,
				'data-id': choice.key,
			} ) );
		} );
		$section.append( $toggle );
		return $section;
	}

	function renderCard( card, idx ) {
		var removed = isCardRemoved( idx );
		var $card = $( '<div>', {
			class: 'rwgc-geo-card rwgc-action-card' + ( removed ? ' rwgc-geo-card--removed' : '' ),
			id: 'rwgc-geo-card-' + idx,
			'data-card-index': idx,
		} );

		var remaining = remainingForCard( idx, card, state.proposal );
		var $head = $( '<div>', { class: 'rwgc-geo-card__head rwgc-action-card__header' } );
		var cardTitle = card.label || actionTypeLabel( card.type );
		$head.append( $( '<strong>' ).text( ( i18n.cardActionWord || 'Action' ) + ' ' + ( idx + 1 ) + ' — ' + cardTitle ) );
		var pillKind = removed ? 'removed' : ( remaining > 0 || waitingForSharedTarget( state.proposal, idx, card ) ? 'needs-resolution' : 'ready' );
		var pillLabel = removed
			? ( i18n.cardActionRemoved || 'Removed' )
			: ( waitingForSharedTarget( state.proposal, idx, card )
				? ( i18n.waitingSharedTarget || 'Waiting for shared target' )
				: ( remaining > 0 ? ( i18n.cardNeedsResolution || 'Needs resolution' ) : ( i18n.cardReady || 'Ready to create' ) ) );
		$head.append( $( '<span>', { class: 'rwgc-status-pill rwgc-status-pill--' + pillKind } ).text( pillLabel ) );
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
		if ( ! card.uses_shared_target && ( t.raw || ( t.resolved && t.resolved.name ) ) ) {
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

		$card.append( renderLogicSection( card, idx ) );

		var $rows = renderConditionRows( card, idx );
		if ( $rows ) {
			$card.append( $rows );
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

		var $head = $( '<header>', { class: 'rwgc-geo-review__head', id: 'rwgc-targeting-action-review' } );
		$head.append( $( '<h2>', { class: 'rwgc-geo-review__title' } ).text( i18n.actionReview || 'Action Review' ) );

		var detectedCount = cards.filter( function ( c, i ) {
			return ! isCardRemoved( i );
		} ).length;
		var remaining = remainingResolutions( proposal );
		var $meta = $( '<p>', { class: 'rwgc-geo-review__meta' } );
		$meta.append( $( '<span>' ).text(
			detectedCount + ' ' + ( detectedCount === 1 ? ( i18n.actionDetected || 'action detected' ) : ( i18n.actionsDetected || 'actions detected' ) )
		) );
		if ( remaining > 0 ) {
			$meta.append( $( '<span>', { class: 'rwgc-geo-review__attention' } ).text(
				remaining + ' ' + ( remaining === 1 ? ( i18n.fieldNeedsAttention || 'field needs attention' ) : ( i18n.fieldsNeedAttention || 'fields need attention' ) )
			) );
		} else {
			$meta.append( $( '<span>', { class: 'rwgc-geo-review__ready' } ).text( i18n.allResolved || 'All fields resolved' ) );
		}
		$head.append( $meta );
		$plan.append( $head );

		renderSharedTargets( proposal, $plan, false );

		cards.forEach( function ( card, idx ) {
			$plan.append( renderCard( card, idx ) );
		} );
	}

	function originActionForDependency( proposal, depId ) {
		var cards = proposal.action_cards || [];
		for ( var i = 0; i < cards.length; i++ ) {
			var t = cards[ i ].target || {};
			if ( depId && t.dependencyId === depId && ! t.inherited ) {
				return i;
			}
		}
		return -1;
	}

	function actionStatusLine( proposal, card, idx ) {
		if ( isCardRemoved( idx ) ) {
			return { kind: 'removed', text: i18n.cardActionRemoved || 'Removed' };
		}
		if ( waitingForSharedTarget( proposal, idx, card ) ) {
			return { kind: 'warn', text: i18n.waitingSharedTarget || 'Waiting for shared target' };
		}
		var t = card.target || {};
		var targetUnresolved = requiresField( card, 'target' ) && ! fieldResolution( idx, 'target', t.raw );
		if ( t.inherited && targetUnresolved ) {
			var origin = originActionForDependency( proposal, t.dependencyId );
			var txt = origin >= 0
				? ( i18n.blockedByTarget || 'Blocked by target from Action {n}' ).replace( '{n}', origin + 1 )
				: ( i18n.blockedTargetGeneric || 'Blocked until target is resolved' );
			return { kind: 'blocked', text: txt };
		}

		var fields = [];
		( card.requiredResolutions || [] ).forEach( function ( req ) {
			if ( ! fieldResolution( idx, req.field, req.raw ) && fields.indexOf( req.field ) === -1 ) {
				fields.push( req.field );
			}
		} );
		if ( ! fields.length ) {
			return { kind: 'ok', text: i18n.cardReady || 'Ready' };
		}
		var labelMap = {
			target: i18n.fieldTarget || 'target',
			campaign: i18n.fieldCampaign || 'campaign',
			audience: i18n.fieldAudience || 'audience',
			location: i18n.fieldLocation || 'location',
		};
		var names = fields.map( function ( f ) {
			return labelMap[ f ] || f;
		} );
		return { kind: 'warn', text: ( i18n.needsWord || 'Needs' ) + ' ' + names.join( ', ' ) };
	}

	function resolvedSummary( card, idx ) {
		var parts = [];
		var t = card.target || {};
		var tres = fieldResolution( idx, 'target', t.raw );
		var targetText = tres ? ( tres.label || t.raw ) : ( ( t.resolved && t.resolved.name ) || t.raw || '' );
		if ( targetText ) {
			parts.push( targetText );
		}
		( card.condition_rows || [] ).forEach( function ( row ) {
			var field = conditionResolutionField( row.type );
			var res = field ? fieldResolution( idx, field, row.raw ) : null;
			if ( res ) {
				parts.push( res.kind === 'ignored'
					? ( row.type === 'audience' ? ( i18n.anyAudience || 'Any audience' ) : '' )
					: ( res.label || '' ) );
			} else if ( row.status === 'valid' ) {
				parts.push( row.label || row.raw );
			}
		} );
		parts.push( cardLogicOperator( card, idx ) === 'OR' ? ( i18n.matchAny || 'Match any' ) : ( i18n.matchAll || 'Match all' ) );
		return parts.filter( function ( p ) { return p; } ).join( ' · ' );
	}

	function sharedTargetResolved( group ) {
		var linked = group.linkedActions || [];
		for ( var i = 0; i < linked.length; i++ ) {
			var idx = linked[ i ] - 1;
			if ( ! isCardRemoved( idx ) && ! fieldResolution( idx, 'target', group.raw ) ) {
				return false;
			}
		}
		return linked.length > 0;
	}

	function hasUnresolvedSharedTarget( proposal ) {
		return ( ( proposal && proposal.shared_targets ) || [] ).some( function ( group ) {
			return ! sharedTargetResolved( group );
		} );
	}

	function cardUsesSharedTarget( proposal, idx ) {
		var groups = ( proposal && proposal.shared_targets ) || [];
		for ( var g = 0; g < groups.length; g++ ) {
			if ( ( groups[ g ].linkedActions || [] ).indexOf( idx + 1 ) >= 0 ) {
				return groups[ g ];
			}
		}
		return null;
	}

	function waitingForSharedTarget( proposal, idx, card ) {
		if ( card && card.uses_shared_target ) {
			var group = cardUsesSharedTarget( proposal, idx );
			return group && ! sharedTargetResolved( group );
		}
		return false;
	}

	function sharedTargetActionLabels( proposal, group ) {
		return ( group.linkedActions || [] ).map( function ( n ) {
			var card = ( ( proposal && proposal.action_cards ) || [] )[ n - 1 ] || {};
			var label = card.label || actionTypeLabel( card.type );
			return ( i18n.cardActionWord || 'Action' ) + ' ' + n + ' — ' + label;
		} );
	}

	function renderSharedTargets( proposal, $container, compact ) {
		var groups = ( proposal.shared_targets || [] ).filter( function ( g ) {
			return ! sharedTargetResolved( g );
		} );
		if ( ! groups.length ) {
			return;
		}
		var $section = $( '<div>', {
			class: 'rwgc-geo-shared-targets' + ( compact ? ' rwgc-geo-shared-targets--compact' : '' ),
			id: compact ? '' : 'rwgc-shared-target',
		} );
		groups.forEach( function ( group ) {
			var $block = $( '<div>', { class: 'rwgc-geo-shared-targets__item' } );
			$block.append( $( '<p>', { class: 'rwgc-geo-shared-targets__title' } ).text( i18n.sharedTargetTitle || 'Shared Target' ) );
			$block.append( $( '<p>', { class: 'rwgc-geo-shared-targets__detected' } ).html(
				'<strong>' + esc( i18n.detectedPrefix || 'Detected:' ) + '</strong> ' + esc( group.raw || '' )
			) );
			if ( group.status ) {
				$block.append( $( '<p>', { class: 'rwgc-geo-shared-targets__status' } ).text(
					( i18n.possibleMatchesFound || 'Possible matches found' ) + ' — ' + statusText( group.status )
				) );
			}
			var used = sharedTargetActionLabels( proposal, group );
			if ( used.length ) {
				$block.append( $( '<p>', { class: 'rwgc-geo-shared-targets__used-label' } ).text( i18n.usedByActions || 'This target will be used by:' ) );
				var $used = $( '<ul>', { class: 'rwgc-geo-shared-targets__used' } );
				used.forEach( function ( line ) {
					$used.append( $( '<li>' ).text( line ) );
				} );
				$block.append( $used );
			}

			if ( compact ) {
				$section.append( $block );
				return;
			}

			var linkedAttr = ( group.linkedActions || [] ).join( ',' );
			if ( group.suggestions && group.suggestions.length ) {
				var $chips = $( '<div>', { class: 'rwgc-geo-card__suggestions' } );
				group.suggestions.forEach( function ( s ) {
					$chips.append( $( '<button>', {
						type: 'button',
						class: 'button rwgc-geo-card__chip',
						text: s.name,
						'data-card-action': 'choose_shared',
						'data-raw': group.raw || '',
						'data-linked': linkedAttr,
						'data-id': s.id || '',
						'data-label': s.name,
					} ) );
				} );
				$block.append( $chips );
			}

			var $picker = $( '<div>', { class: 'rwgc-geo-shared-targets__picker' } );
			var $sel = $( '<select>', { class: 'rwgc-geo-card__picker-select' } );
			$sel.append( $( '<option>', { value: '', text: i18n.cardPickerPlaceholder || 'Select a page or category…' } ) );
			( group.suggestions || [] ).forEach( function ( s ) {
				$sel.append( $( '<option>', { value: 'sug:' + ( s.id || '' ) + ':' + s.name, text: s.name } ) );
			} );
			( cfg.pages || [] ).forEach( function ( p ) {
				$sel.append( $( '<option>', { value: 'page:' + p.id + ':' + p.title, text: p.title } ) );
			} );
			$picker.append( $sel );
			$picker.append( $( '<button>', {
				type: 'button',
				class: 'button rwgc-geo-card__picker-use',
				text: i18n.cardUse || 'Use',
				'data-card-action': 'use_shared_picker',
				'data-raw': group.raw || '',
				'data-linked': linkedAttr,
			} ) );
			$block.append( $picker );
			$section.append( $block );
		} );
		$container.append( $section );
	}

	function renderRail( proposal, $rail, status ) {
		$rail.empty();
		var cards = proposal.action_cards || [];
		var detectedCount = cards.filter( function ( c, i ) {
			return ! isCardRemoved( i );
		} ).length;
		var remaining = remainingResolutions( proposal );
		var invalidSplit = isInvalidCreateRuleSplit( proposal );
		var readyCount = cardsReadyCount( proposal );
		var hubTitle = $( '<h3>', { class: 'rwgc-geo-rail__title' } ).text( i18n.resolutionHub || 'Resolution Hub' );
		$rail.append( hubTitle );

		var $counts = $( '<div>', { class: 'rwgc-geo-rail__counts' } );
		if ( invalidSplit ) {
			$counts.append( $( '<p>', { class: 'rwgc-geo-rail__invalid' } ).text(
				( proposal.invalid_interpretation && proposal.invalid_interpretation.message )
					? proposal.invalid_interpretation.message
					: ( i18n.invalidCreateRuleSplit || 'This was split into multiple actions, but it looks like one rule. Ask AI to re-check?' )
			) );
		} else if ( remaining > 0 ) {
			$counts.append( $( '<p>', { class: 'rwgc-geo-rail__count' } ).text(
				detectedCount + ' ' + ( detectedCount === 1 ? ( i18n.actionDetected || 'action detected' ) : ( i18n.actionsDetected || 'actions detected' ) )
			) );
			$counts.append( $( '<p>', { class: 'rwgc-geo-rail__attention' } ).text(
				remaining + ' ' + ( remaining === 1 ? ( i18n.fieldNeedsAttention || 'field needs attention' ) : ( i18n.fieldsNeedAttention || 'fields need attention' ) )
			) );
		} else {
			$counts.append( $( '<p>', { class: 'rwgc-geo-rail__count is-ok' } ).text(
				readyCount + ' ' + ( readyCount === 1 ? ( i18n.actionReady || 'action ready' ) : ( i18n.actionsReady || 'actions ready' ) )
			) );
		}
		$rail.append( $counts );

		if ( ! invalidSplit ) {
			var needs = hubNeedsLabels( proposal );
			var ready = hubReadyLabels( proposal );
			if ( needs.length || ready.length ) {
				var $hubLists = $( '<div>', { class: 'rwgc-geo-rail__hub-lists' } );
				if ( needs.length ) {
					var $needs = $( '<div>', { class: 'rwgc-geo-rail__hub-section' } );
					$needs.append( $( '<p>', { class: 'rwgc-geo-rail__hub-label' } ).text( i18n.hubNeeds || 'Needs' ) );
					var $needsUl = $( '<ul>', { class: 'rwgc-geo-rail__hub-items' } );
					needs.forEach( function ( label ) {
						$needsUl.append( $( '<li>' ).text( label ) );
					} );
					$needs.append( $needsUl );
					$hubLists.append( $needs );
				}
				if ( ready.length ) {
					var $ready = $( '<div>', { class: 'rwgc-geo-rail__hub-section' } );
					$ready.append( $( '<p>', { class: 'rwgc-geo-rail__hub-label' } ).text( i18n.hubReady || 'Ready' ) );
					var $readyUl = $( '<ul>', { class: 'rwgc-geo-rail__hub-items' } );
					ready.forEach( function ( label ) {
						$readyUl.append( $( '<li>' ).text( label ) );
					} );
					$ready.append( $readyUl );
					$hubLists.append( $ready );
				}
				$rail.append( $hubLists );
			}
		}

		renderSharedTargets( proposal, $rail, true );

		var $list = $( '<ol>', { class: 'rwgc-geo-rail__actions' } );
		cards.forEach( function ( card, idx ) {
			var line = actionStatusLine( proposal, card, idx );
			var $row = $( '<li>', {
				class: 'rwgc-geo-rail__action rwgc-geo-rail__action--' + line.kind,
				'data-jump': idx,
				role: 'button',
				tabindex: 0,
			} );
			var cardTitle = card.label || actionTypeLabel( card.type );
			$row.append( $( '<span>', { class: 'rwgc-geo-rail__action-title' } ).text(
				( i18n.cardActionWord || 'Action' ) + ' ' + ( idx + 1 ) + ' — ' + cardTitle
			) );
			$row.append( $( '<span>', { class: 'rwgc-geo-rail__action-status' } ).text( line.text ) );
			if ( line.kind === 'ok' ) {
				var summary = resolvedSummary( card, idx );
				if ( summary ) {
					$row.append( $( '<span>', { class: 'rwgc-geo-rail__action-summary' } ).text( summary ) );
				}
			}
			$list.append( $row );
		} );
		$rail.append( $list );

		var $cta = $( '<div>', { class: 'rwgc-geo-rail__cta' } );
		if ( invalidSplit ) {
			if ( state.lastResponse && state.lastResponse.ai_available ) {
				$cta.append( $( '<button>', {
					type: 'button',
					class: 'button button-primary rwgc-geo-btn',
					text: i18n.askAiCheck || 'Ask AI to re-check',
					'data-card-action': 'ask_ai_recheck',
				} ) );
			}
		} else if ( hasUnresolvedSharedTarget( proposal ) ) {
			$cta.append( $( '<button>', {
				type: 'button',
				class: 'button button-primary rwgc-geo-btn',
				text: i18n.resolveSharedTarget || 'Resolve shared target',
				'data-card-action': 'jump_shared_target',
			} ) );
		} else if ( remaining > 0 ) {
			$cta.append( $( '<button>', {
				type: 'button',
				class: 'button button-primary rwgc-geo-btn',
				text: ( i18n.resolveItems || 'Resolve' ) + ' ' + remaining + ' ' + ( remaining === 1 ? ( i18n.itemWord || 'item' ) : ( i18n.itemsWord || 'items' ) ),
				'data-card-action': 'resolve_items',
			} ) );
		} else {
			$cta.append( $( '<button>', {
				type: 'button',
				class: 'button button-primary rwgc-geo-btn',
				text: primaryCreateRuleLabel( proposal ),
				'data-card-action': 'create_setup',
			} ) );
			$cta.append( $( '<button>', {
				type: 'button',
				class: 'button rwgc-geo-btn',
				text: i18n.editRule || 'Edit rule',
				'data-card-action': 'review_items',
			} ) );
		}
		$cta.append( $( '<button>', {
			type: 'button',
			class: 'button rwgc-geo-btn',
			text: i18n.showDebug || 'Show debug',
			'data-action': 'debug',
		} ) );
		$cta.append( $( '<button>', {
			type: 'button',
			class: 'button-link rwgc-geo-btn',
			text: i18n.cancel || 'Cancel',
			'data-action': 'cancel',
		} ) );
		$rail.append( $cta );

		if ( status ) {
			$rail.append( $( '<p>', { class: 'rwgc-geo-rail__status' } ).text( status ) );
		}
	}

	function jumpToCard( idx ) {
		var el = document.getElementById( 'rwgc-geo-card-' + idx );
		if ( ! el ) {
			return;
		}
		if ( el.scrollIntoView ) {
			el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
		el.classList.add( 'rwgc-geo-card--flash' );
		window.setTimeout( function () {
			el.classList.remove( 'rwgc-geo-card--flash' );
		}, 1200 );
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
			if ( state.cardLogic[ idx ] ) {
				out.push( { card: idx, field: 'logic', action: 'set', id: state.cardLogic[ idx ] } );
			}
		} );
		return out;
	}

	// Persist a confirmed condition mapping so the assistant can suggest it next
	// time the same raw phrase is seen.
	function recordConditionLearning( idx, field, raw, res ) {
		if ( ! cfg.learningEventUrl || ! res ) {
			return;
		}
		var mapping = res.kind === 'ignored'
			? { type: field, mode: 'any' }
			: { type: field, value: res.id || res.label || '' };
		apiPost( cfg.learningEventUrl, {
			raw_phrase: state.lastMessage || '',
			normalised_phrase: raw || '',
			intent_key: ( state.proposal && state.proposal.intent ) || '',
			action_key: ( state.proposal && state.proposal.matched_action ) || '',
			outcome: 'confirmed',
			approved_by_user: true,
			interpretation_source: ( state.lastResponse && state.lastResponse.source ) || 'local_parser',
			correction: {
				field: field,
				raw: raw || '',
				confirmed_mapping: mapping,
				context: field + '_condition',
			},
		} );
	}

	function handleCardAction( $btn ) {
		var action = $btn.data( 'card-action' );
		var idx = parseInt( $btn.data( 'card' ), 10 );
		var field = $btn.data( 'field' );
		var raw = $btn.data( 'raw' ) != null ? String( $btn.data( 'raw' ) ) : '';

		if ( 'choose_condition' === action ) {
			var ckind = String( $btn.data( 'kind' ) || 'choose' );
			if ( 'refresh' === ckind ) {
				if ( state.lastMessage ) {
					sendMessage( state.lastMessage );
				}
				return;
			}
			if ( 'ignore' === ckind ) {
				state.cardResolutions[ fieldKey( idx, field, raw ) ] = { kind: 'ignored' };
			} else if ( 'pick_manual' === ckind ) {
				var picked = window.prompt( i18n.cardEnterExact || 'Enter the exact name to use:', '' );
				if ( ! picked || ! picked.trim() ) {
					return;
				}
				state.cardResolutions[ fieldKey( idx, field, raw ) ] = { kind: 'chosen', id: '', label: picked.trim() };
			} else {
				state.cardResolutions[ fieldKey( idx, field, raw ) ] = {
					kind: 'chosen',
					id: $btn.data( 'id' ) || '',
					label: $btn.data( 'label' ) || '',
				};
			}
			recordConditionLearning( idx, field, raw, state.cardResolutions[ fieldKey( idx, field, raw ) ] );
			rerenderCards();
		} else if ( 'set_logic' === action ) {
			state.cardLogic[ idx ] = String( $btn.data( 'id' ) || 'AND' );
			rerenderCards();
		} else if ( 'choose_suggestion' === action ) {
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
		} else if ( 'choose_shared' === action ) {
			applySharedTarget( $btn.data( 'linked' ), raw, {
				id: $btn.data( 'id' ) || '',
				label: $btn.data( 'label' ) || '',
			} );
		} else if ( 'use_shared_picker' === action ) {
			var $ssel = $btn.closest( '.rwgc-geo-shared-targets__picker, .rwgc-geo-rail__shared-picker' ).find( '.rwgc-geo-card__picker-select' );
			var sval = String( $ssel.val() || '' );
			if ( ! sval ) {
				return;
			}
			var sparts = sval.split( ':' );
			applySharedTarget( $btn.data( 'linked' ), raw, {
				id: sparts[1] || '',
				label: sparts.slice( 2 ).join( ':' ) || $ssel.find( 'option:selected' ).text(),
			} );
		} else if ( 'jump_shared_target' === action ) {
			var $target = $( '#rwgc-shared-target' );
			if ( $target.length ) {
				$target[ 0 ].scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} else if ( 'create_setup' === action ) {
			if ( isInvalidCreateRuleSplit( state.proposal ) ) {
				return;
			}
			if ( remainingResolutions( state.proposal ) > 0 ) {
				jumpToActionReview();
				jumpToCard( firstUnresolvedCardIndex( state.proposal ) );
				return;
			}
			finalizeCardSetup();
		} else if ( 'resolve_items' === action || 'review_items' === action ) {
			jumpToActionReview();
			var unresolved = firstUnresolvedCardIndex( state.proposal );
			jumpToCard( unresolved >= 0 ? unresolved : 0 );
		} else if ( 'ask_ai_recheck' === action ) {
			askAiToCheck();
		}
	}

	function applySharedTarget( linked, raw, chosen ) {
		String( linked || '' ).split( ',' ).forEach( function ( n ) {
			var idx = parseInt( n, 10 ) - 1;
			if ( isNaN( idx ) || idx < 0 ) {
				return;
			}
			state.cardResolutions[ fieldKey( idx, 'target', raw ) ] = {
				kind: 'chosen',
				id: chosen.id || '',
				label: chosen.label || '',
			};
		} );
		rerenderCards();
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
		var $review = $( '#rwgc-targeting-review' );
		var $rail = $( '#rwgc-targeting-rail' );

		if ( ! proposal ) {
			$empty.removeClass( 'rwgc-is-hidden' );
			$hint.removeClass( 'rwgc-is-hidden' );
			$plan.empty();
			$review.addClass( 'rwgc-is-hidden' );
			$rail.addClass( 'rwgc-is-hidden' ).empty();
			$summary.addClass( 'rwgc-is-hidden' );
			return;
		}

		$empty.addClass( 'rwgc-is-hidden' );
		$hint.addClass( 'rwgc-is-hidden' );
		$plan.empty();
		$review.removeClass( 'rwgc-is-hidden' );

		if ( proposal.action_cards && proposal.action_cards.length ) {
			renderActionCards( proposal, $plan );
			renderRail( proposal, $rail, status );
			$rail.removeClass( 'rwgc-is-hidden' );
			$summary.addClass( 'rwgc-is-hidden' );
			return;
		}

		$rail.addClass( 'rwgc-is-hidden' ).empty();

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
				$( '<button>', { type: 'button', class: 'button button-primary rwgc-geo-btn', text: i18n.reviewAction || 'Review action', 'data-action': 'review_action' } )
			);
			if ( response.ai_available ) {
				$wrap.append(
					$( '<button>', { type: 'button', class: 'button rwgc-geo-btn', text: i18n.askAiCheck || 'Ask AI to check', 'data-action': 'ask_ai' } )
				);
			}
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
		if ( proposal && proposal.invalid_interpretation ) {
			return false;
		}
		if ( proposal && proposal.requires_resolution ) {
			return false;
		}
		if ( proposal && proposal.action_cards && proposal.action_cards.length ) {
			if ( remainingResolutions( proposal ) > 0 ) {
				return false;
			}
			return proposal.action_cards.every( function ( card, idx ) {
				return isCardRemoved( idx ) || 'ready' === ( card.status || '' );
			} );
		}
		if ( typeof proposal.can_execute === 'boolean' ) {
			return proposal.can_execute;
		}
		return proposal.proposal_ready !== false;
	}

	function sourceBadgeHtml( source ) {
		var map = {
			local_parser: { label: i18n.sourceLocal || 'Local smart action', kind: 'local' },
			local_memory: { label: i18n.sourceLearned || 'Learned interpretation', kind: 'learned' },
			remote_memory: { label: i18n.sourceLearned || 'Learned interpretation', kind: 'learned' },
			ai_fallback: { label: i18n.sourceAi || 'AI-assisted interpretation', kind: 'ai' },
			clarification: { label: i18n.sourceClarify || 'Needs clarification', kind: 'clarify' },
		};
		var badge = map[ source ] || map.local_parser;
		return '<p><span class="rwgc-source-badge rwgc-source-badge--' + badge.kind + '">' + esc( badge.label ) + '</span></p>';
	}

	function chatActionBreakdown( proposal ) {
		var cards = proposal.action_cards || [];
		if ( cards.length !== 1 ) {
			return '';
		}
		var card = cards[0];
		var html = '<ul class="rwgc-chat-breakdown">';
		var t = card.target || {};
		html += '<li><strong>' + esc( i18n.cardTargetLabel || 'Target' ) + ':</strong> ' + esc( ( t.resolved && t.resolved.name ) || t.raw || '—' ) + '</li>';
		var detected = [];
		var needs = [];
		( card.condition_rows || [] ).forEach( function ( row ) {
			detected.push( conditionTypeLabel( row.type ) + ': ' + ( row.label || row.raw ) );
			if ( row.status !== 'valid' && row.warning ) {
				needs.push( row.warning );
			}
		} );
		if ( detected.length ) {
			html += '<li><strong>' + esc( i18n.conditionsDetected || 'Conditions detected' ) + ':</strong> ' + esc( detected.join( ' · ' ) ) + '</li>';
		}
		html += '</ul>';
		if ( needs.length ) {
			html += '<p><strong>' + esc( i18n.needConfirmationFor || 'I need confirmation for:' ) + '</strong></p><ul class="rwgc-chat-breakdown">';
			needs.forEach( function ( n ) {
				html += '<li>' + esc( n ) + '</li>';
			} );
			html += '</ul>';
		}
		return html;
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
			var cardHtml = sourceBadgeHtml( response.source );
			cardHtml += '<p><strong>' + esc( line ) + '</strong></p>';
			cardHtml += chatActionBreakdown( proposal );
			cardHtml += '<p class="description">' + esc( i18n.reviewInPanel || 'Review and resolve each action in the Action Review panel below.' ) + '</p>';
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
		state.cardLogic = {};
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
		if ( ! responseCanExecute( state.proposal ) ) {
			jumpToActionReview();
			jumpToCard( firstUnresolvedCardIndex( state.proposal ) );
			return;
		}
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
		state.cardLogic = {};
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
			} else if ( 'review_action' === action ) {
				jumpToActionReview();
				var unresolvedIdx = firstUnresolvedCardIndex( state.proposal );
				jumpToCard( unresolvedIdx >= 0 ? unresolvedIdx : 0 );
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

		$( '#rwgc-targeting-setup-plan, #rwgc-targeting-rail' ).on( 'click', '[data-card-action]', function () {
			handleCardAction( $( this ) );
		} );

		$( '#rwgc-targeting-rail' ).on( 'click', '[data-action]', function () {
			var action = $( this ).data( 'action' );
			if ( 'debug' === action ) {
				showDebug();
			} else if ( 'cancel' === action ) {
				recordLearningFeedback( 'rejected' );
				start();
			}
		} );

		$( '#rwgc-targeting-rail' ).on( 'click', '.rwgc-geo-rail__action[data-jump]', function () {
			jumpToCard( parseInt( $( this ).data( 'jump' ), 10 ) );
		} ).on( 'keydown', '.rwgc-geo-rail__action[data-jump]', function ( e ) {
			if ( 13 === e.which || 32 === e.which ) {
				e.preventDefault();
				jumpToCard( parseInt( $( this ).data( 'jump' ), 10 ) );
			}
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
