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
		resolutionDrawer: null,
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
		if ( ! el ) {
			return;
		}
		var count = el.querySelectorAll( '.rwgc-targeting-assistant__bubble' ).length;
		el.classList.toggle( 'rwgc-targeting-assistant__thread--long', count > 4 );
		if ( el.classList.contains( 'rwgc-targeting-assistant__thread--long' ) ) {
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
		if ( proposal && proposal.action_cards && proposal.action_cards.length ) {
			var remaining = remainingResolutions( proposal );
			if ( remaining === 0 && responseCanExecute( proposal ) ) {
				var cards = proposal.action_cards.filter( function ( c, i ) {
					return ! isCardRemoved( i );
				} );
				var needsManual = cards.some( function ( c ) {
					return c.confirmation_instruction && c.confirmation_instruction.requires_confirmation;
				} );
				if ( needsManual ) {
					return i18n.statusReadyManual || 'Ready to create — manual confirmation required';
				}
				return i18n.cardReady || 'Ready to create';
			}
			if ( proposal.can_execute === false || remaining > 0 ) {
				return i18n.statusNeedsConfirmation || 'Needs confirmation';
			}
			return i18n.cardReady || 'Ready to create';
		}
		if ( proposal && proposal.can_execute === false ) {
			return i18n.statusNeedsConfirmation || 'Needs confirmation';
		}
		return i18n.cardReady || 'Ready to create';
	}

	function normalizePopupTargetLabel( raw ) {
		var s = String( raw || '' ).trim().toLowerCase().replace( /\s+/g, ' ' );
		var suffixes = [ 'banner popup', 'geo popup', 'pop-up', 'popup', 'modal', 'banner' ];
		suffixes.forEach( function ( suffix ) {
			if ( s === suffix ) {
				s = '';
				return;
			}
			var withSpace = ' ' + suffix;
			if ( s.slice( -withSpace.length ) === withSpace ) {
				s = s.slice( 0, -withSpace.length ).trim();
			}
		} );
		return s.replace( /\s+/g, ' ' ).trim();
	}

	function popupTargetRegistry() {
		return ( cfg.popups || [] ).map( function ( popup ) {
			var title = String( popup.title || popup.label || '' ).trim();
			if ( ! title ) {
				return null;
			}
			return {
				id: String( popup.id || '' ),
				name: title,
				source: 'elementor',
				aliases: [
					title + ' popup',
					title + ' pop-up',
					title + ' modal',
					title + ' banner popup',
					title + ' geo popup',
				],
			};
		} ).filter( Boolean );
	}

	function findExactPopupMatch( raw ) {
		var norm = normalizePopupTargetLabel( raw );
		if ( ! norm ) {
			return null;
		}
		var matches = [];
		( cfg.popups || [] ).forEach( function ( popup ) {
			var title = String( popup.title || popup.label || '' ).trim();
			if ( ! title ) {
				return;
			}
			if ( normalizePopupTargetLabel( title ) === norm ) {
				matches.push( popup );
			}
		} );
		return matches.length === 1 ? matches[ 0 ] : null;
	}

	function popupTargetRawCandidates( card, idx ) {
		var out = [];
		var seen = {};
		function add( value ) {
			var v = String( value || '' ).trim();
			if ( ! v || seen[ v ] ) {
				return;
			}
			seen[ v ] = true;
			out.push( v );
		}
		( ( card && card.requiredResolutions ) || [] ).forEach( function ( req ) {
			if ( req.field === 'target' || req.type === 'target' ) {
				add( req.raw );
			}
		} );
		var t = ( card && card.target ) || {};
		add( t.raw );
		add( t.label );
		add( targetFieldRaw( card, idx ) );
		if ( t.resolved && t.resolved.name ) {
			add( t.resolved.name );
			add( t.resolved.name + ' popup' );
		}
		if ( t.label ) {
			add( t.label + ' popup' );
		}
		return out;
	}

	function seedPopupTargetCardResolutions( card, idx, id, label ) {
		if ( ! id ) {
			return;
		}
		var resolution = {
			kind: 'chosen',
			id: String( id ),
			label: label || '',
		};
		popupTargetRawCandidates( card, idx ).forEach( function ( raw ) {
			if ( ! fieldResolution( idx, 'target', raw ) ) {
				state.cardResolutions[ fieldKey( idx, 'target', raw ) ] = resolution;
			}
		} );
	}

	function popupTargetResolvedFromCard( card, idx ) {
		if ( ! card || ! card.target || card.target.type !== 'popup' ) {
			return null;
		}
		var candidates = popupTargetRawCandidates( card, idx );
		var i;
		for ( i = 0; i < candidates.length; i++ ) {
			var raw = candidates[ i ];
			var stored = fieldResolution( idx, 'target', raw );
			if ( stored && stored.kind !== 'ignored' && stored.id ) {
				return {
					raw: targetFieldRaw( card, idx ) || raw,
					res: stored,
				};
			}
		}
		var normWanted = '';
		candidates.forEach( function ( candidate ) {
			if ( ! normWanted ) {
				normWanted = normalizePopupTargetLabel( candidate );
			}
		} );
		var loose = null;
		cardResolutionEntries( idx ).forEach( function ( entry ) {
			if ( entry.field !== 'target' || loose || ! entry.res || entry.res.kind === 'ignored' || ! entry.res.id ) {
				return;
			}
			if ( ! normWanted || normalizePopupTargetLabel( entry.raw ) === normWanted
				|| normalizePopupTargetLabel( entry.res.label ) === normWanted ) {
				loose = { raw: targetFieldRaw( card, idx ) || entry.raw, res: entry.res };
			}
		} );
		if ( loose ) {
			return loose;
		}
		var t = card.target;
		var id = ( t.resolved && t.resolved.id ) || t.id || '';
		if ( ! id ) {
			return null;
		}
		if ( t.status !== 'matched' && t.status !== 'valid' && t.status !== 'resolved' ) {
			return null;
		}
		var popupLabel = ( t.resolved && t.resolved.name ) || t.label || '';
		return {
			raw: targetFieldRaw( card, idx ) || t.raw || t.label || popupLabel,
			res: {
				kind: 'chosen',
				id: String( id ),
				label: popupLabel,
			},
		};
	}

	function ensurePopupTargetCardResolutions( card, idx ) {
		if ( ! card || ! card.target || card.target.type !== 'popup' ) {
			return;
		}
		var resolved = popupTargetResolvedFromCard( card, idx );
		if ( ! resolved || ! resolved.res || ! resolved.res.id ) {
			return;
		}
		seedPopupTargetCardResolutions( card, idx, resolved.res.id, resolved.res.label );
	}

	function syncPopupTargetActionState( card, idx, id, label ) {
		if ( ! card.target ) {
			card.target = { type: 'popup' };
		}
		card.target.type = 'popup';
		card.target.id = String( id || '' );
		card.target.label = label || card.target.label || '';
		card.target.status = 'matched';
		card.target.resolved = {
			id: String( id || '' ),
			name: label || '',
		};
		seedPopupTargetCardResolutions( card, idx, id, label );
	}

	function applyPopupTargetMatch( card, idx, popup ) {
		var label = String( popup.title || popup.label || '' ).trim();
		syncPopupTargetActionState( card, idx, popup.id, label );
	}

	function autoMatchPopupTargets( proposal ) {
		if ( ! proposal || ! proposal.action_cards ) {
			return;
		}
		proposal.action_cards.forEach( function ( card, idx ) {
			if ( isCardRemoved( idx ) ) {
				return;
			}
			var t = card.target || {};
			if ( t.type !== 'popup' ) {
				return;
			}
			if ( t.status === 'matched' && t.resolved && t.resolved.id ) {
				ensurePopupTargetCardResolutions( card, idx );
				return;
			}
			var raw = targetFieldRaw( card, idx );
			if ( fieldResolution( idx, 'target', raw ) ) {
				return;
			}
			var match = findExactPopupMatch( t.raw || t.label || raw );
			if ( match ) {
				applyPopupTargetMatch( card, idx, match );
			}
		} );
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

	function targetFieldRaw( card, idx ) {
		var req = ( ( card && card.requiredResolutions ) || [] ).find( function ( r ) {
			return r.field === 'target';
		} );
		if ( req && req.raw ) {
			return req.raw;
		}
		var t = ( card && card.target ) || {};
		return t.raw || t.label || '';
	}

	function isCardRemoved( idx ) {
		return !! state.cardResolutions[ 'removed_' + cardKey( idx ) ];
	}

	function fieldResolution( idx, field, raw ) {
		return state.cardResolutions[ fieldKey( idx, field, raw ) ] || null;
	}

	function cardResolutionEntries( idx ) {
		var prefix = cardKey( idx ) + '|';
		var out = [];
		Object.keys( state.cardResolutions ).forEach( function ( key ) {
			if ( key.indexOf( prefix ) !== 0 ) {
				return;
			}
			var tail = key.slice( prefix.length );
			var sep = tail.indexOf( '|' );
			if ( sep < 0 ) {
				return;
			}
			out.push( {
				field: tail.slice( 0, sep ),
				raw: tail.slice( sep + 1 ),
				res: state.cardResolutions[ key ],
			} );
		} );
		return out;
	}

	function trafficResolutionRaw( card, idx ) {
		var raw = '';
		( ( card && card.requiredResolutions ) || [] ).forEach( function ( req ) {
			if ( req.field === 'traffic_source' && req.raw ) {
				raw = req.raw;
			}
		} );
		if ( raw ) {
			return raw;
		}
		( ( card && card.condition_rows ) || [] ).forEach( function ( row ) {
			var meta = conditionGroupResolution( row );
			if ( meta.field === 'traffic_source' && meta.raw ) {
				raw = meta.raw;
			}
		} );
		return raw;
	}

	function findFieldResolution( idx, field, preferredRaw, card ) {
		if ( preferredRaw && fieldResolution( idx, field, preferredRaw ) ) {
			return { raw: preferredRaw, res: fieldResolution( idx, field, preferredRaw ) };
		}
		if ( field === 'traffic_source' && card ) {
			var canonical = trafficResolutionRaw( card, idx );
			if ( canonical && fieldResolution( idx, field, canonical ) ) {
				return { raw: canonical, res: fieldResolution( idx, field, canonical ) };
			}
		}
		if ( field === 'target' && card ) {
			var popupResolved = popupTargetResolvedFromCard( card, idx );
			if ( popupResolved ) {
				return popupResolved;
			}
			var targetRaw = targetFieldRaw( card, idx );
			if ( targetRaw && fieldResolution( idx, field, targetRaw ) ) {
				return { raw: targetRaw, res: fieldResolution( idx, field, targetRaw ) };
			}
		}
		var found = null;
		cardResolutionEntries( idx ).forEach( function ( entry ) {
			if ( entry.field === field && ! found ) {
				found = { raw: entry.raw, res: entry.res };
			}
		} );
		return found;
	}

	function resolutionFieldLabel( field, card ) {
		if ( field === 'target' ) {
			return card && card.target && card.target.type === 'popup'
				? ( i18n.hubNeedPopup || 'Popup target' )
				: ( i18n.hubNeedTarget || 'Target page' );
		}
		if ( field === 'traffic_source' ) {
			return i18n.hubNeedTraffic || 'Google Ads mapping';
		}
		if ( field === 'audience' ) {
			return i18n.hubNeedAudience || 'Audience segments';
		}
		if ( field === 'campaign' ) {
			return i18n.hubNeedCampaign || 'Campaign';
		}
		if ( field === 'location' ) {
			return i18n.hubNeedLocation || 'Location';
		}
		return field ? String( field ).replace( /_/g, ' ' ) : '';
	}

	function unresolvedExecuteItems( proposal ) {
		var items = [];
		var seen = {};
		( ( proposal && proposal.action_cards ) || [] ).forEach( function ( card, idx ) {
			if ( isCardRemoved( idx ) ) {
				return;
			}
			function pushItem( field, raw ) {
				var key = idx + '|' + field;
				if ( seen[ key ] || findFieldResolution( idx, field, raw, card ) ) {
					return;
				}
				seen[ key ] = true;
				items.push( {
					card: idx,
					field: field,
					raw: raw || '',
					key: field === 'traffic_source' ? 'google_ads_mapping' : field,
					label: resolutionFieldLabel( field, card ),
				} );
			}
			( ( card && card.requiredResolutions ) || [] ).forEach( function ( req ) {
				if ( card.uses_shared_target && req.field === 'target' ) {
					return;
				}
				pushItem( req.field, req.raw );
			} );
			if ( ! card.uses_shared_target && card.target && card.target.status
				&& 'matched' !== card.target.status && 'ignored' !== card.target.status ) {
				pushItem( 'target', card.target.raw || card.target.label || '' );
			}
			( card.condition_rows || [] ).forEach( function ( row ) {
				if ( ! row || row.is_note || effectiveRowStatus( row, idx ) === 'valid' ) {
					return;
				}
				var meta = conditionGroupResolution( row );
				if ( meta.field ) {
					pushItem( meta.field, meta.raw );
				} else if ( row.type === 'audience' ) {
					pushItem( 'audience', row.raw );
				}
			} );
		} );
		return items;
	}

	function applyTrafficResolutionToCard( card, idx ) {
		var match = findFieldResolution( idx, 'traffic_source', trafficResolutionRaw( card, idx ), card );
		if ( ! match || match.res.kind === 'ignored' ) {
			return;
		}
		( card.condition_rows || [] ).forEach( function ( row ) {
			if ( row.type !== 'condition_group' ) {
				return;
			}
			var meta = conditionGroupResolution( row );
			if ( meta.field !== 'traffic_source' ) {
				return;
			}
			( row.children || [] ).forEach( function ( child ) {
				if ( child.type !== 'traffic_source' ) {
					return;
				}
				child.status = 'valid';
				child.mapping_key = match.res.id || child.mapping_key || '';
				if ( match.res.label ) {
					child.label = match.res.label;
				}
				delete child.warning;
			} );
			var allValid = ( row.children || [] ).every( function ( child ) {
				return effectiveChildStatus( child, idx, row ) === 'valid';
			} );
			if ( allValid ) {
				row.status = 'valid';
				delete row.warning;
			}
		} );
		card.requiredResolutions = ( card.requiredResolutions || [] ).filter( function ( req ) {
			if ( req.field !== 'traffic_source' ) {
				return true;
			}
			return ! findFieldResolution( idx, 'traffic_source', req.raw, card );
		} );
	}

	function applyTargetResolutionToCard( card, idx ) {
		ensurePopupTargetCardResolutions( card, idx );
		var raw = targetFieldRaw( card, idx );
		var match = findFieldResolution( idx, 'target', raw, card );
		if ( ! match || match.res.kind === 'ignored' ) {
			return;
		}
		if ( ! card.target ) {
			card.target = {};
		}
		if ( card.target.type === 'popup' && match.res.id ) {
			syncPopupTargetActionState( card, idx, match.res.id, match.res.label || '' );
		} else {
			card.target.status = 'matched';
			card.target.resolved = {
				id: match.res.id || '',
				name: match.res.label || '',
			};
		}
		card.requiredResolutions = ( card.requiredResolutions || [] ).filter( function ( req ) {
			if ( req.field !== 'target' && req.type !== 'target' ) {
				return true;
			}
			return ! findFieldResolution( idx, 'target', req.raw, card );
		} );
	}

	function applyResolutionsToProposalCards() {
		if ( ! state.proposal || ! state.proposal.action_cards ) {
			return;
		}
		state.proposal.action_cards.forEach( function ( card, idx ) {
			if ( isCardRemoved( idx ) ) {
				return;
			}
			applyTargetResolutionToCard( card, idx );
			applyTrafficResolutionToCard( card, idx );
		} );
	}

	function showExecuteFailedMessage( serverMessage, jqxhr ) {
		var status = jqxhr && jqxhr.status ? jqxhr.status : 0;
		var $wrap = $( '<div>', { class: 'rwgc-targeting-assistant__execute-blocked rwgc-targeting-assistant__execute-failed' } );
		$wrap.append( $( '<p>' ).html( '<strong>' + esc( i18n.couldNotCreateRule || 'Could not create rule' ) + '</strong>' ) );
		var detail = serverMessage || '';
		if ( ! detail && status >= 500 ) {
			detail = i18n.executeServerError || 'The server could not create this rule. Check the site error log and try again.';
		}
		if ( ! detail ) {
			detail = i18n.cardResolveRemaining || 'Some fields still need resolving before this setup can be created.';
		}
		$wrap.append( $( '<p>' ).text( detail ) );
		if ( status ) {
			$wrap.append( $( '<p>', { class: 'rwgc-targeting-assistant__execute-blocked-detail' } ).text(
				( i18n.executeHttpStatus || 'HTTP status' ) + ': ' + String( status )
			) );
		}
		var $actions = $( '<div>', { class: 'rwgc-targeting-assistant__execute-blocked-actions' } );
		$actions.append( $( '<button>', {
			type: 'button',
			class: 'button rwgc-geo-btn',
			text: i18n.showDebug || 'Show debug',
			'data-action': 'debug',
		} ) );
		$actions.append( $( '<button>', {
			type: 'button',
			class: 'button-link rwgc-geo-btn',
			text: i18n.cancel || 'Cancel',
			'data-action': 'cancel',
		} ) );
		$wrap.append( $actions );
		var $thread = $( '#rwgc-targeting-thread' );
		var $bubble = assistantBubble( '' );
		$bubble.find( '.rwgc-targeting-assistant__bubble-body' ).append( $wrap );
		$thread.append( $bubble );
		scrollThread();
	}

	function showExecuteBlockedMessage( items, serverMessage ) {
		var $wrap = $( '<div>', { class: 'rwgc-targeting-assistant__execute-blocked' } );
		$wrap.append( $( '<p>' ).html( '<strong>' + esc( i18n.couldNotCreateRule || 'Could not create rule' ) + '</strong>' ) );
		$wrap.append( $( '<p>' ).text( i18n.executeStillNeeds || 'This still needs:' ) );
		var $list = $( '<ul>', { class: 'rwgc-targeting-assistant__execute-blocked-list' } );
		( items || [] ).forEach( function ( item ) {
			var $li = $( '<li>' );
			$li.append( $( '<span>' ).text( item.label || item.field || '' ) );
			var resolveLabel = i18n.resolveField || 'Resolve';
			if ( item.field === 'traffic_source' ) {
				resolveLabel = i18n.resolveGoogleAds || 'Resolve Google Ads mapping';
			} else if ( item.field === 'target' ) {
				resolveLabel = i18n.resolvePopupTarget || 'Resolve popup target';
			}
			$li.append( $( '<button>', {
				type: 'button',
				class: 'button-link',
				text: resolveLabel,
				'data-card-action': 'open_resolver',
				'data-card': item.card,
				'data-field': item.field,
				'data-raw': item.raw || '',
			} ) );
			$list.append( $li );
		} );
		$wrap.append( $list );
		if ( serverMessage ) {
			$wrap.append( $( '<p>', { class: 'rwgc-targeting-assistant__execute-blocked-detail' } ).text( serverMessage ) );
		}
		var $actions = $( '<div>', { class: 'rwgc-targeting-assistant__execute-blocked-actions' } );
		$actions.append( $( '<button>', {
			type: 'button',
			class: 'button rwgc-geo-btn',
			text: i18n.showDebug || 'Show debug',
			'data-action': 'debug',
		} ) );
		$actions.append( $( '<button>', {
			type: 'button',
			class: 'button-link rwgc-geo-btn',
			text: i18n.cancel || 'Cancel',
			'data-action': 'cancel',
		} ) );
		$wrap.append( $actions );
		var $thread = $( '#rwgc-targeting-thread' );
		var $bubble = assistantBubble( '' );
		$bubble.find( '.rwgc-targeting-assistant__bubble-body' ).append( $wrap );
		$thread.append( $bubble );
		scrollThread();
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
		var seen = {};
		var n = 0;
		function bump( field, raw ) {
			var key = field + '|' + ( raw || '' );
			if ( seen[ key ] ) {
				return;
			}
			if ( findFieldResolution( idx, field, raw, card ) ) {
				return;
			}
			seen[ key ] = true;
			n++;
		}
		( ( card && card.requiredResolutions ) || [] ).forEach( function ( req ) {
			if ( card.uses_shared_target && req.field === 'target' ) {
				return;
			}
			bump( req.field, req.raw );
		} );
		if ( ! card.uses_shared_target && card.target && card.target.status && 'matched' !== card.target.status && 'ignored' !== card.target.status ) {
			bump( 'target', card.target.raw || card.target.label || '' );
		}
		( card.condition_rows || [] ).forEach( function ( row ) {
			if ( ! row || row.is_note ) {
				return;
			}
			if ( effectiveRowStatus( row, idx ) === 'valid' ) {
				return;
			}
			var meta = conditionGroupResolution( row );
			if ( meta.field ) {
				bump( meta.field, meta.raw );
				return;
			}
			if ( row.type === 'audience' ) {
				bump( 'audience', row.raw );
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
		var serverCount = parseInt( ( proposal && proposal.fields_needing_attention ) || 0, 10 );
		if ( Object.keys( state.cardResolutions ).length === 0 && ! isNaN( serverCount ) && serverCount > total ) {
			total = serverCount;
		}
		return total;
	}

	function cardsReadyCount( proposal ) {
		var ready = 0;
		( ( proposal && proposal.action_cards ) || [] ).forEach( function ( card, idx ) {
			if ( isCardRemoved( idx ) ) {
				return;
			}
			if ( remainingForCard( idx, card, proposal ) === 0 ) {
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
		var t = card.target || {};
		var targetLabel = resolvedTargetLabel( card, 0 );
		var targetRaw = targetFieldRaw( card, 0 );
		if ( targetLabel && ( ! requiresField( card, 'target' ) || fieldResolution( 0, 'target', targetRaw ) ) ) {
			if ( t.type === 'popup' ) {
				labels.push( ( i18n.hubNeedPopup || 'Popup target' ) + ': ' + targetLabel );
			} else {
				labels.push( targetLabel );
			}
		}
		( card.condition_rows || [] ).forEach( function ( row ) {
			if ( effectiveRowStatus( row, 0 ) !== 'valid' ) {
				return;
			}
			if ( row.type === 'condition_group' ) {
				if ( row.label && labels.indexOf( row.label ) === -1 ) {
					labels.push( row.label );
				}
				return;
			}
			if ( row.label && labels.indexOf( row.label ) === -1 ) {
				labels.push( row.label );
			}
		} );
		return labels;
	}

	function hubReadyRuleSummary( proposal ) {
		var cards = ( ( proposal && proposal.action_cards ) || [] ).filter( function ( c, i ) {
			return ! isCardRemoved( i );
		} );
		if ( cards.length !== 1 ) {
			return null;
		}
		var card = cards[ 0 ];
		var include = [];
		var exclude = [];
		( card.condition_rows || [] ).forEach( function ( row ) {
			if ( effectiveRowStatus( row, 0 ) !== 'valid' || row.is_note ) {
				return;
			}
			if ( row.mode === 'exclude' ) {
				if ( row.label && exclude.indexOf( row.label ) === -1 ) {
					exclude.push( row.label );
				}
			} else if ( row.type === 'device' ) {
				include.push( row.label || row.raw );
			} else if ( row.type === 'page_type' ) {
				include.push( row.label || row.raw );
			} else if ( row.type === 'condition_group' ) {
				include.push( row.label || row.raw );
			} else if ( row.type === 'location' ) {
				include.push( row.label || row.raw );
			} else if ( row.label ) {
				include.push( row.label );
			}
		} );
		return {
			target: resolvedTargetLabel( card, 0 ),
			include: include,
			exclude: exclude,
		};
	}

	function resolvedTargetLabel( card, idx ) {
		var t = ( card && card.target ) || {};
		var res = fieldResolution( idx, 'target', targetFieldRaw( card, idx ) );
		if ( res && res.kind !== 'ignored' && res.label ) {
			return res.label;
		}
		if ( t.resolved && t.resolved.name ) {
			return t.resolved.name;
		}
		return t.raw || t.label || '';
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
		var el = document.getElementById( 'rwgc-action-review' ) || document.getElementById( 'rwgc-targeting-action-review' );
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
			needs_mapping: i18n.statusNeedsMapping || 'Needs mapping',
			needs_resolution: i18n.statusNeedsAttention || 'Needs attention',
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
			resolve_popup: i18n.resolvePopup || 'Resolve popup',
			choose_popup: i18n.resolvePopup || 'Resolve popup',
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
			resolve_popup: 'open_resolver',
			choose_popup: 'open_resolver',
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

	function targetPicker( idx, raw, suggestions, isPopup ) {
		var $wrap = $( '<div>', { class: 'rwgc-geo-card__picker rwgc-is-hidden', 'data-picker-card': idx } );
		var $sel = $( '<select>', { class: 'rwgc-geo-card__picker-select' } );
		$sel.append( $( '<option>', {
			value: '',
			text: isPopup
				? ( i18n.cardPopupPickerPlaceholder || 'Select a popup…' )
				: ( i18n.cardPickerPlaceholder || 'Select a page or category…' ),
		} ) );
		( suggestions || [] ).forEach( function ( s ) {
			$sel.append( $( '<option>', { value: 'sug:' + ( s.id || '' ) + ':' + s.name, text: s.name } ) );
		} );
		if ( isPopup ) {
			( cfg.popups || [] ).forEach( function ( p ) {
				$sel.append( $( '<option>', { value: 'popup:' + p.id + ':' + p.title, text: p.title } ) );
			} );
		} else {
			( cfg.pages || [] ).forEach( function ( p ) {
				$sel.append( $( '<option>', { value: 'page:' + p.id + ':' + p.title, text: p.title } ) );
			} );
		}
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

		var resolution = fieldResolution( idx, opts.field, opts.value );
		var popupResolvedName = '';
		if ( opts.isPopup ) {
			if ( resolution && resolution.kind !== 'ignored' && resolution.label ) {
				popupResolvedName = resolution.label;
			} else if ( opts.resolved ) {
				popupResolvedName = opts.resolved;
			}
		}

		if ( popupResolvedName ) {
			$b.append( $( '<span>', { class: 'rwgc-geo-card__field-value' } ).text( opts.value || popupResolvedName ) );
			$b.append( $( '<span>', { class: 'rwgc-geo-card__field-ok' } ).text( ( i18n.cardSetTo || 'Set to' ) + ' ' + popupResolvedName ) );
			var $popupActs = $( '<div>', { class: 'rwgc-geo-card__field-actions' } );
			$popupActs.append( $( '<button>', {
				type: 'button',
				class: 'button rwgc-geo-card__act',
				text: i18n.cardChangePopup || 'Change popup',
				'data-card-action': 'open_resolver',
				'data-card': idx,
				'data-field': opts.field,
				'data-raw': opts.value || '',
			} ) );
			if ( resolution ) {
				$popupActs.append( $( '<button>', {
					type: 'button',
					class: 'button-link rwgc-geo-card__undo',
					text: i18n.cardUndo || 'Undo',
					'data-card-action': 'undo_field',
					'data-card': idx,
					'data-field': opts.field,
					'data-raw': opts.value || '',
				} ) );
			}
			$b.append( $popupActs );
			return $b;
		}

		if ( opts.resolved ) {
			$b.append( $( '<span>', { class: 'rwgc-geo-card__field-value' } ).text( opts.resolved ) );
			$b.append( $( '<span>', { class: 'rwgc-geo-card__field-ok' } ).text( i18n.cardMatched || 'Matched' ) );
			return $b;
		}

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
		$b.append( $( '<span>', { class: 'rwgc-geo-card__field-status rwgc-status-pill rwgc-status-pill--needs-resolution' } ).text( statusText( opts.status ) ) );

		if ( opts.isPopup && opts.actions && opts.actions.length ) {
			$b.append( $( '<p>', { class: 'rwgc-geo-card__field-hint' } ).text(
				i18n.popupTargetHint || 'This looks like a popup target. Choose the matching popup before creating the rule.'
			) );
		}

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

		if ( 'target' === opts.field && ! opts.isPopup ) {
			$b.append( targetPicker( idx, opts.value, opts.suggestions, false ) );
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
		if ( type === 'traffic_source' ) {
			return 'traffic_source';
		}
		return '';
	}

	function conditionGroupResolution( row ) {
		if ( row.type !== 'condition_group' || ! row.children || ! row.children.length ) {
			return {
				field: conditionResolutionField( row.type ),
				raw: row.raw || '',
			};
		}
		var trafficChild = null;
		row.children.forEach( function ( child ) {
			if ( child.type === 'traffic_source' && child.status !== 'valid' ) {
				trafficChild = child;
			}
		} );
		if ( trafficChild ) {
			return {
				field: 'traffic_source',
				raw: trafficChild.label || row.raw || '',
			};
		}
		return {
			field: conditionResolutionField( row.type ),
			raw: row.raw || '',
		};
	}

	function childStatusLabel( status ) {
		if ( status === 'valid' ) {
			return i18n.statusValid || 'Valid';
		}
		if ( status === 'needs_mapping' ) {
			return i18n.statusNeedsMapping || 'Needs mapping';
		}
		return i18n.statusNeedsAttention || 'Needs attention';
	}

	function conditionTypeDisplayLabel( type ) {
		if ( type === 'condition_group' ) {
			return i18n.conditionGroupLabel || 'Condition group';
		}
		if ( type === 'page_type' ) {
			return i18n.pageTypeLabel || 'Page type';
		}
		return conditionTypeLabel( type );
	}

	function effectiveChildStatus( child, idx, row ) {
		if ( child.status === 'valid' ) {
			return 'valid';
		}
		if ( child.type === 'traffic_source' ) {
			var meta = conditionGroupResolution( row );
			if ( fieldResolution( idx, 'traffic_source', meta.raw ) ) {
				return 'valid';
			}
		}
		return child.status;
	}

	function effectiveRowStatus( row, idx ) {
		if ( row.type === 'condition_group' ) {
			var children = row.children || [];
			if ( children.length ) {
				var allValid = true;
				children.forEach( function ( child ) {
					if ( effectiveChildStatus( child, idx, row ) !== 'valid' ) {
						allValid = false;
					}
				} );
				if ( allValid ) {
					return 'valid';
				}
			}
			var meta = conditionGroupResolution( row );
			if ( meta.field && fieldResolution( idx, meta.field, meta.raw ) ) {
				return 'valid';
			}
			return row.status;
		}
		var field = conditionResolutionField( row.type );
		if ( field && fieldResolution( idx, field, row.raw ) ) {
			return 'valid';
		}
		return row.status;
	}

	function conditionGroupChildLine( child, idx, row ) {
		var name = conditionChildDisplayName( child );
		var value = conditionChildDisplayValue( child, idx, row );
		return name + ': ' + value;
	}

	function conditionChildDisplayName( child ) {
		if ( child.type === 'traffic_source' ) {
			return i18n.googleAdsChildLabel || 'Google Ads';
		}
		if ( child.type === 'url' || child.type === 'page_url' ) {
			return i18n.urlChildLabel || 'URL';
		}
		return conditionTypeLabel( child.type ) || child.label || child.type || '';
	}

	function conditionChildDisplayValue( child, idx, row ) {
		var st = effectiveChildStatus( child, idx, row );
		if ( child.type === 'traffic_source' ) {
			var meta = conditionGroupResolution( row );
			var res = fieldResolution( idx, 'traffic_source', meta.raw );
			if ( res && res.kind === 'ignored' ) {
				return i18n.cardIgnored || 'Removed';
			}
			if ( res && res.kind === 'chosen' ) {
				return googleAdsResolutionShortLabel( res.id, res.label );
			}
			return i18n.childNeedsMapping || 'needs mapping';
		}
		if ( st === 'valid' ) {
			if ( child.type === 'url' || child.type === 'page_url' ) {
				return effectiveUrlChildDisplay( child, idx );
			}
			var lab = child.label || child.raw || '';
			return lab || ( i18n.childStatusValid || 'valid' );
		}
		if ( st === 'needs_mapping' ) {
			return i18n.childNeedsMapping || 'needs mapping';
		}
		return i18n.childNeedsAttention || 'needs attention';
	}

	function conditionGroupSummary( row, idx ) {
		var parts = [];
		( row.children || [] ).forEach( function ( child ) {
			parts.push( conditionGroupChildLine( child, idx, row ) );
		} );
		return parts.join( ' ' );
	}

	function renderConditionGroupChildren( row, idx ) {
		var $list = $( '<ul>', { class: 'rwgc-condition-card__children' } );
		( row.children || [] ).forEach( function ( child ) {
			var st = effectiveChildStatus( child, idx, row );
			var $item = $( '<li>', {
				class: 'rwgc-condition-card__child rwgc-condition-card__child--' + ( st === 'valid' ? 'valid' : 'needs-resolution' ),
			} );
			$item.append( $( '<span>', { class: 'rwgc-condition-card__child-name' } ).text( conditionChildDisplayName( child ) + ':' ) );
			$item.append( $( '<span>', { class: 'rwgc-condition-card__child-value' } ).text( conditionChildDisplayValue( child, idx, row ) ) );
			$list.append( $item );
		} );
		return $list;
	}

	function resolutionOptionsForField( card, field, raw ) {
		var options = [];
		( card.requiredResolutions || [] ).forEach( function ( req ) {
			if ( req.field === field && ( ! raw || req.raw === raw ) && req.options && req.options.length ) {
				options = req.options;
			}
		} );
		if ( options.length ) {
			return options;
		}
		( card.condition_rows || [] ).forEach( function ( row ) {
			if ( row.resolution_options && row.resolution_options.length ) {
				var meta = conditionGroupResolution( row );
				if ( meta.field === field && ( ! raw || meta.raw === raw ) ) {
					options = row.resolution_options;
				}
			}
		} );
		return options;
	}

	function googleAdsMappingMeta( key ) {
		var map = {
			utm_source_google_and_medium_cpc: {
				title: i18n.googleAdsOptStandard || 'Standard Google Ads UTM tracking',
				description: i18n.googleAdsOptStandardDesc || 'Matches utm_source=google AND utm_medium=cpc',
				shortLabel: i18n.googleAdsShortStandard || 'Standard UTM tracking',
			},
			utm_source_google: {
				title: i18n.googleAdsOptSource || 'Google source only',
				description: i18n.googleAdsOptSourceDesc || 'Matches utm_source=google',
				shortLabel: i18n.googleAdsShortSource || 'Google source only',
			},
			utm_medium_cpc: {
				title: i18n.googleAdsOptMedium || 'Paid click medium only',
				description: i18n.googleAdsOptMediumDesc || 'Matches utm_medium=cpc',
				shortLabel: i18n.googleAdsShortMedium || 'Paid click medium only',
			},
			gclid_exists: {
				title: i18n.googleAdsOptGclid || 'Google click ID',
				description: i18n.googleAdsOptGclidDesc || 'Matches when gclid exists',
				shortLabel: i18n.googleAdsShortGclid || 'Google click ID (gclid)',
			},
			configure_google_ads_mapping: {
				title: i18n.googleAdsOptCustom || 'Custom mapping',
				description: i18n.googleAdsOptCustomDesc || 'Use this if your Google Ads links use non-standard tracking parameters.',
				shortLabel: i18n.googleAdsShortCustom || 'Custom mapping',
			},
		};
		return map[ key ] || {
			title: key,
			description: '',
			shortLabel: key,
		};
	}

	function googleAdsResolutionShortLabel( key, fallback ) {
		var meta = googleAdsMappingMeta( key );
		return meta.shortLabel || fallback || key;
	}

	var GOOGLE_ADS_STANDARD_KEY = 'utm_source_google_and_medium_cpc';
	var GOOGLE_ADS_STANDARD_ORDER = [
		'utm_source_google_and_medium_cpc',
		'utm_source_google',
		'utm_medium_cpc',
		'gclid_exists',
	];

	function splitGoogleAdsResolutionOptions( options ) {
		var mapping = [];
		var danger = null;
		( options || [] ).forEach( function ( opt ) {
			if ( opt.key === 'remove_google_ads_condition' ) {
				danger = opt;
			} else {
				mapping.push( opt );
			}
		} );
		return { mapping: mapping, danger: danger };
	}

	function defaultGoogleAdsMappingKey( options ) {
		var keys = ( options || [] ).map( function ( opt ) {
			return opt.key;
		} );
		if ( keys.indexOf( GOOGLE_ADS_STANDARD_KEY ) !== -1 ) {
			return GOOGLE_ADS_STANDARD_KEY;
		}
		var i;
		for ( i = 0; i < GOOGLE_ADS_STANDARD_ORDER.length; i++ ) {
			if ( keys.indexOf( GOOGLE_ADS_STANDARD_ORDER[ i ] ) !== -1 ) {
				return GOOGLE_ADS_STANDARD_ORDER[ i ];
			}
		}
		for ( i = 0; i < ( options || [] ).length; i++ ) {
			var key = options[ i ].key;
			if ( key && key !== 'configure_google_ads_mapping' && key !== 'remove_google_ads_condition' ) {
				return key;
			}
		}
		return GOOGLE_ADS_STANDARD_KEY;
	}

	function partitionGoogleAdsOptions( options ) {
		var split = splitGoogleAdsResolutionOptions( options );
		var byKey = {};
		var custom = null;
		split.mapping.forEach( function ( opt ) {
			if ( opt.key === 'configure_google_ads_mapping' ) {
				custom = opt;
			} else {
				byKey[ opt.key ] = opt;
			}
		} );
		var standard = [];
		GOOGLE_ADS_STANDARD_ORDER.forEach( function ( key ) {
			if ( byKey[ key ] ) {
				standard.push( byKey[ key ] );
			}
		} );
		split.mapping.forEach( function ( opt ) {
			if ( opt.key === 'configure_google_ads_mapping' || GOOGLE_ADS_STANDARD_ORDER.indexOf( opt.key ) !== -1 ) {
				return;
			}
			standard.push( opt );
		} );
		return { standard: standard, custom: custom, danger: split.danger };
	}

	function findTrafficConditionGroupRow( card, raw ) {
		var found = null;
		( ( card && card.condition_rows ) || [] ).forEach( function ( row ) {
			if ( row.type !== 'condition_group' ) {
				return;
			}
			var meta = conditionGroupResolution( row );
			if ( meta.field === 'traffic_source' && ( ! raw || meta.raw === raw ) ) {
				found = row;
			}
		} );
		return found;
	}

	function validUrlChildrenFromRow( row ) {
		if ( ! row || row.type !== 'condition_group' ) {
			return [];
		}
		return ( row.children || [] ).filter( function ( child ) {
			return ( child.type === 'url' || child.type === 'page_url' ) && child.status === 'valid';
		} );
	}

	function parseUrlPathFromChild( child, idx ) {
		var res = fieldResolution( idx, 'url_match', child.label || child.raw || '' );
		if ( res && res.value ) {
			return String( res.value );
		}
		var lab = String( child.label || child.raw || '' );
		var m = lab.match( /URL\s+contains\s+(\S+)/i );
		if ( m ) {
			return m[ 1 ];
		}
		if ( child.value ) {
			return String( child.value );
		}
		m = lab.match( /(\/[\w-]+(?:\/[\w-]+)*)/ );
		return m ? m[ 1 ] : lab.replace( /^URL\s+/i, '' ).trim();
	}

	function urlMatchTypeFromResolution( child, idx ) {
		var res = fieldResolution( idx, 'url_match', child.label || child.raw || '' );
		return ( res && res.id ) ? String( res.id ) : 'contains';
	}

	function urlMatchLabel( matchType, path ) {
		path = String( path || '' ).trim();
		var labels = {
			contains: i18n.urlMatchContains || 'contains',
			path_starts_with: i18n.urlMatchStartsWith || 'path starts with',
			exact_path: i18n.urlMatchExact || 'exact path',
			wildcard: i18n.urlMatchWildcard || 'path wildcard',
		};
		var op = labels[ matchType ] || labels.contains;
		var displayPath = path;
		if ( matchType === 'wildcard' && displayPath.indexOf( '/*' ) === -1 ) {
			displayPath = displayPath + '/*';
		}
		return 'URL ' + op + ' ' + displayPath;
	}

	function urlMatchExamples( path, matchType ) {
		path = String( path || '' ).trim();
		if ( ! path ) {
			return [];
		}
		matchType = matchType || 'contains';
		if ( matchType === 'wildcard' ) {
			var base = path.replace( /\/\*$/, '' );
			return [ base + '/*', base + '/coats', base + '/products' ];
		}
		if ( matchType === 'path_starts_with' ) {
			return [ path, path + '/coats', path + '/products' ];
		}
		if ( matchType === 'exact_path' ) {
			return [ path ];
		}
		if ( path.indexOf( '/' ) === 0 ) {
			return [ path, '/shop' + path, path + '/products' ];
		}
		return [ path, '/shop/' + path, '/' + path + '/products' ];
	}

	function effectiveUrlChildDisplay( child, idx ) {
		var res = fieldResolution( idx, 'url_match', child.label || child.raw || '' );
		if ( res && res.label ) {
			var edited = String( res.label );
			if ( /^url\s+/i.test( edited ) ) {
				return edited.replace( /^url\s+/i, '' );
			}
			return edited;
		}
		var lab = child.label || child.raw || '';
		if ( ( child.type === 'url' || child.type === 'page_url' ) && /^url\s+/i.test( lab ) ) {
			return lab.replace( /^url\s+/i, '' );
		}
		return lab || ( i18n.childStatusValid || 'valid' );
	}

	function updateResolutionDrawerApplyButton() {
		var drawer = state.resolutionDrawer;
		if ( ! drawer ) {
			return;
		}
		var $btn = $( '#rwgc-resolution-drawer [data-drawer-action="apply"]' );
		if ( ! $btn.length ) {
			return;
		}
		var enabled = !! drawer.selected;
		if ( enabled && drawer.selected === 'configure_google_ads_mapping' ) {
			var custom = String( drawer.customMapping || '' ).trim();
			enabled = custom.length > 0;
		}
		$btn.prop( 'disabled', ! enabled );
	}

	function renderGoogleAdsValidUrlSection( $panel, urlChildren, idx ) {
		if ( ! urlChildren.length ) {
			return;
		}
		urlChildren.forEach( function ( child ) {
			var label = child.label || child.raw || '';
			var displayLabel = effectiveUrlChildDisplay( child, idx );
			if ( ! /^URL/i.test( displayLabel ) ) {
				displayLabel = 'URL ' + displayLabel;
			}
			var matchType = urlMatchTypeFromResolution( child, idx );
			var path = parseUrlPathFromChild( child, idx );
			var examples = urlMatchExamples( path, matchType );

			var $section = $( '<div>', { class: 'rwgc-mapping-also-valid' } );
			$section.append( $( '<p>', { class: 'rwgc-mapping-also-valid__heading' } ).html(
				'<strong>' + esc( i18n.alsoDetectedLabel || 'Also detected' ) + ':</strong> ' + esc( displayLabel )
			) );
			$section.append( $( '<p>', { class: 'rwgc-mapping-also-valid__status' } ).html(
				'<strong>' + esc( i18n.alsoValidStatusLabel || 'Status' ) + ':</strong> ' + esc( i18n.statusValid || 'Valid' )
			) );
			if ( examples.length ) {
				var $examples = $( '<ul>', { class: 'rwgc-mapping-also-valid__examples' } );
				examples.forEach( function ( example ) {
					$examples.append( $( '<li>' ).text( example ) );
				} );
				$section.append( $( '<p>', { class: 'rwgc-mapping-also-valid__examples-label' } ).text( i18n.urlExamplesLabel || 'Examples matched' ) );
				$section.append( $examples );
			}
			$section.append( $( '<button>', {
				type: 'button',
				class: 'rwgc-link-button rwgc-mapping-also-valid__edit',
				text: i18n.editUrlMatch || 'Edit URL match',
				'data-drawer-action': 'edit_url_match',
				'data-url-child-label': child.label || child.raw || '',
			} ) );
			$panel.append( $section );
		} );
	}

	function appendGoogleAdsMappingOption( $list, opt, selected, showRecommendedBadge ) {
		var meta = googleAdsMappingMeta( opt.key );
		var isSelected = opt.key === selected;
		var $item = $( '<label>', {
			class: 'rwgc-mapping-option' + ( isSelected ? ' rwgc-mapping-option--selected' : '' ),
			'data-option-key': opt.key || '',
		} );
		$item.append( $( '<input>', {
			type: 'radio',
			name: 'rwgc-mapping-choice',
			value: opt.key || '',
			checked: isSelected,
		} ) );
		var $body = $( '<span>', { class: 'rwgc-mapping-option__body' } );
		$body.append( $( '<span>', { class: 'rwgc-mapping-option__title' } ).text( meta.title ) );
		if ( meta.description ) {
			$body.append( $( '<span>', { class: 'rwgc-mapping-option__description' } ).text( meta.description ) );
		}
		if ( showRecommendedBadge ) {
			$body.append( $( '<span>', { class: 'rwgc-mapping-option__badge' } ).text( i18n.recommendedBadge || 'Recommended' ) );
		}
		$item.append( $body );
		$list.append( $item );
	}

	function renderGoogleAdsMappingDrawer( $panel, opts ) {
		var parts = partitionGoogleAdsOptions( opts.options || [] );
		var selected = defaultGoogleAdsMappingKey( opts.options || [] );
		if ( opts.preservedSelection && opts.preservedSelection.selected ) {
			selected = opts.preservedSelection.selected;
		}
		var customMapping = ( opts.preservedSelection && opts.preservedSelection.customMapping ) ? opts.preservedSelection.customMapping : '';
		var urlChildren = validUrlChildrenFromRow( opts.conditionRow );

		state.resolutionDrawer = {
			mode: 'google_ads',
			card: opts.card,
			field: 'traffic_source',
			raw: opts.raw || '',
			selected: selected,
			customMapping: customMapping,
			conditionRow: opts.conditionRow || null,
			drawerOpts: opts,
		};

		$panel.addClass( 'rwgc-google-ads-resolver' );
		$panel.append( $( '<h3>', { class: 'rwgc-resolution-drawer__title' } ).text( opts.title || ( i18n.resolveGoogleAds || 'Resolve Google Ads mapping' ) ) );
		$panel.append( $( '<p>', { class: 'rwgc-resolution-drawer__label' } ).html(
			'<strong>' + esc( i18n.drawerDetected || 'Detected' ) + ':</strong> ' + esc( opts.detected || ( i18n.trafficSourceLabel || 'Google Ads traffic' ) )
		) );
		$panel.append( $( '<p>', { class: 'rwgc-resolution-drawer__why' } ).html(
			'<strong>' + esc( i18n.drawerExplanation || 'Explanation' ) + ':</strong> ' + esc( opts.why || ( i18n.googleAdsWhy || 'Geo Core needs to know how to recognise visitors from Google Ads.' ) )
		) );

		var standardMeta = googleAdsMappingMeta( GOOGLE_ADS_STANDARD_KEY );
		$panel.append( $( '<div>', { class: 'rwgc-mapping-recommended' } ).append(
			$( '<p>', { class: 'rwgc-mapping-recommended__label' } ).text( i18n.drawerRecommended || 'Recommended' ),
			$( '<p>', { class: 'rwgc-mapping-recommended__title' } ).text( standardMeta.title ),
			$( '<p>', { class: 'rwgc-mapping-recommended__description' } ).text( standardMeta.description )
		) );

		renderGoogleAdsValidUrlSection( $panel, urlChildren, opts.card );

		$panel.append( $( '<p>', { class: 'rwgc-mapping-options__heading' } ).text( i18n.mappingOptionsLabel || 'Options' ) );
		var $list = $( '<div>', { class: 'rwgc-mapping-options', role: 'radiogroup' } );
		parts.standard.forEach( function ( opt ) {
			appendGoogleAdsMappingOption( $list, opt, selected, opt.key === GOOGLE_ADS_STANDARD_KEY );
		} );
		$panel.append( $list );

		if ( parts.custom ) {
			var customMeta = googleAdsMappingMeta( 'configure_google_ads_mapping' );
			var $advanced = $( '<div>', { class: 'rwgc-mapping-advanced' } );
			$advanced.append( $( '<p>', { class: 'rwgc-mapping-advanced__heading' } ).text( i18n.googleAdsAdvancedLabel || 'Advanced' ) );
			var $customOption = $( '<label>', {
				class: 'rwgc-mapping-option rwgc-mapping-option--advanced' + ( selected === 'configure_google_ads_mapping' ? ' rwgc-mapping-option--selected' : '' ),
				'data-option-key': 'configure_google_ads_mapping',
			} );
			$customOption.append( $( '<input>', {
				type: 'radio',
				name: 'rwgc-mapping-choice',
				value: 'configure_google_ads_mapping',
				checked: selected === 'configure_google_ads_mapping',
			} ) );
			var $customBody = $( '<span>', { class: 'rwgc-mapping-option__body' } );
			$customBody.append( $( '<span>', { class: 'rwgc-mapping-option__title' } ).text( customMeta.title ) );
			$customBody.append( $( '<span>', { class: 'rwgc-mapping-option__description' } ).text( customMeta.description ) );
			$customOption.append( $customBody );
			$advanced.append( $customOption );

			var $custom = $( '<div>', {
				class: 'rwgc-mapping-custom' + ( selected === 'configure_google_ads_mapping' ? '' : ' rwgc-is-hidden' ),
			} );
			$custom.append( $( '<input>', {
				type: 'text',
				class: 'rwgc-mapping-custom__input',
				value: customMapping,
				placeholder: i18n.googleAdsCustomMappingPlaceholder || 'Example: utm_source=google-ads, source=adwords, or custom query rule',
				'data-drawer-field': 'custom_mapping',
			} ) );
			$advanced.append( $custom );
			$panel.append( $advanced );
		}

		if ( parts.danger ) {
			var $danger = $( '<div>', { class: 'rwgc-mapping-danger' } );
			$danger.append( $( '<button>', {
				type: 'button',
				class: 'rwgc-button rwgc-button--danger',
				text: parts.danger.label || ( i18n.removeGoogleAdsCondition || 'Remove Google Ads condition' ),
				'data-drawer-action': 'remove_google_ads',
			} ) );
			$panel.append( $danger );
		}

		var $applyBtn = $( '<button>', {
			type: 'button',
			class: 'rwgc-button rwgc-button--primary',
			text: i18n.drawerApply || 'Apply mapping',
			'data-drawer-action': 'apply',
		} );
		$panel.append( rwgcModalFooter( [ $applyBtn ], true, 'drawer' ) );
		updateResolutionDrawerApplyButton();
	}

	function renderUrlMatchDrawer( $panel, opts ) {
		var child = opts.urlChild || {};
		var childLabel = child.label || child.raw || '';
		var matchType = opts.matchType || urlMatchTypeFromResolution( child, opts.card );
		var path = opts.path || parseUrlPathFromChild( child, opts.card );
		var examples = urlMatchExamples( path, matchType );

		state.resolutionDrawer = {
			mode: 'url_match',
			card: opts.card,
			urlChild: child,
			urlChildLabel: childLabel,
			matchType: matchType,
			path: path,
			returnToGoogleAds: !! opts.returnToGoogleAds,
			googleAdsOpts: opts.googleAdsOpts || null,
		};

		$panel.addClass( 'rwgc-url-match-resolver' );
		$panel.append( $( '<h3>', { class: 'rwgc-resolution-drawer__title' } ).text( i18n.editUrlMatchTitle || 'Edit URL match' ) );

		var matchTypes = [
			{ key: 'contains', label: i18n.urlMatchContains || 'Contains', recommended: true },
			{ key: 'path_starts_with', label: i18n.urlMatchStartsWith || 'Path starts with' },
			{ key: 'exact_path', label: i18n.urlMatchExact || 'Exact path' },
			{ key: 'wildcard', label: i18n.urlMatchWildcard || 'Wildcard' },
		];

		$panel.append( $( '<p>', { class: 'rwgc-url-match__label' } ).text( i18n.urlMatchTypeLabel || 'Match type' ) );
		var $types = $( '<div>', { class: 'rwgc-mapping-options rwgc-url-match__types', role: 'radiogroup' } );
		matchTypes.forEach( function ( type ) {
			var isSelected = type.key === matchType;
			var $item = $( '<label>', {
				class: 'rwgc-mapping-option' + ( isSelected ? ' rwgc-mapping-option--selected' : '' ),
			} );
			$item.append( $( '<input>', {
				type: 'radio',
				name: 'rwgc-url-match-type',
				value: type.key,
				checked: isSelected,
			} ) );
			var $body = $( '<span>', { class: 'rwgc-mapping-option__body' } );
			$body.append( $( '<span>', { class: 'rwgc-mapping-option__title' } ).text( type.label ) );
			if ( type.recommended ) {
				$body.append( $( '<span>', { class: 'rwgc-mapping-option__badge' } ).text( i18n.recommendedBadge || 'Recommended' ) );
			}
			$item.append( $body );
			$types.append( $item );
		} );
		$panel.append( $types );

		if ( matchType === 'contains' ) {
			$panel.append( $( '<p>', { class: 'rwgc-url-match__hint' } ).text(
				( i18n.urlMatchContainsHint || 'This matches any URL where %s appears.' ).replace( '%s', path )
			) );
		} else if ( matchType === 'wildcard' ) {
			$panel.append( $( '<p>', { class: 'rwgc-url-match__hint' } ).text(
				i18n.urlMatchWildcardHint || 'This matches child paths under the path but may not match parent segments like /shop/winter-sale.'
			) );
		}

		var $pathRow = $( '<label>', { class: 'rwgc-url-match__field' } );
		$pathRow.append( $( '<span>', { class: 'rwgc-url-match__field-label' } ).text( i18n.urlMatchValueLabel || 'Detected value' ) );
		$pathRow.append( $( '<input>', {
			type: 'text',
			class: 'rwgc-url-match__input',
			value: path,
			'data-drawer-field': 'url_path',
		} ) );
		$panel.append( $pathRow );

		if ( examples.length ) {
			var $examples = $( '<ul>', { class: 'rwgc-mapping-also-valid__examples' } );
			examples.forEach( function ( example ) {
				$examples.append( $( '<li>' ).text( example ) );
			} );
			$panel.append( $( '<p>', { class: 'rwgc-url-match__examples-label' } ).text( i18n.urlExamplesLabel || 'Examples' ) );
			$panel.append( $examples );
		}

		var $applyBtn = $( '<button>', {
			type: 'button',
			class: 'rwgc-button rwgc-button--primary',
			text: i18n.urlMatchApply || 'Apply URL match',
			'data-drawer-action': 'apply_url_match',
		} );
		var $backBtn = $( '<button>', {
			type: 'button',
			class: 'rwgc-button rwgc-button--ghost',
			text: i18n.popupBack || 'Back',
			'data-drawer-action': 'back_to_google_ads',
		} );
		$panel.append( rwgcModalFooter( [ $applyBtn, $backBtn ], true, 'drawer' ) );
	}

	function openUrlMatchEditor( idx, child, googleAdsOpts ) {
		var $drawer = ensureResolutionDrawer();
		var $panel = $drawer.find( '.rwgc-resolution-drawer__panel' );
		$panel.empty().removeClass( 'rwgc-popup-resolver' ).addClass( 'rwgc-google-ads-resolver' );
		renderUrlMatchDrawer( $panel, {
			card: idx,
			urlChild: child,
			returnToGoogleAds: !! googleAdsOpts,
			googleAdsOpts: googleAdsOpts || null,
		} );
		openResolutionDrawerShell();
	}

	function openResolverForField( idx, field, raw ) {
		var card = ( state.proposal && state.proposal.action_cards ) ? state.proposal.action_cards[ idx ] : null;
		if ( ! card ) {
			return;
		}
		if ( field === 'target' && card.target && card.target.type === 'popup' ) {
			openPopupTargetResolver( idx, raw || card.target.raw || card.target.label || '' );
			return;
		}
		var options = resolutionOptionsForField( card, field, raw );
		var conditionRow = field === 'traffic_source' ? findTrafficConditionGroupRow( card, raw ) : null;
		var standardKey = field === 'traffic_source' ? defaultGoogleAdsMappingKey( options ) : '';
		var recommended = null;
		options.forEach( function ( opt ) {
			if ( opt.recommended && opt.key !== 'configure_google_ads_mapping' ) {
				recommended = opt;
			}
		} );
		openResolutionDrawer( {
			title: field === 'traffic_source'
				? ( i18n.resolveGoogleAds || 'Resolve Google Ads mapping' )
				: ( i18n.resolveField || 'Resolve' ),
			detected: raw || ( field === 'traffic_source' ? ( i18n.trafficSourceLabel || 'Google Ads traffic' ) : field ),
			why: field === 'traffic_source'
				? ( i18n.googleAdsWhy || 'Geo Core needs to know how to recognise visitors from Google Ads.' )
				: '',
			recommendedLabel: recommended ? recommended.label : ( standardKey ? googleAdsMappingMeta( standardKey ).title : '' ),
			recommendedKey: standardKey,
			options: options,
			card: idx,
			field: field,
			raw: raw,
			conditionRow: conditionRow,
			useMappingCards: field === 'traffic_source',
		} );
	}

	var RESOLVER_FIELD_ORDER = [ 'target', 'location', 'campaign', 'audience', 'traffic_source' ];

	function popupDetectedLabel( raw ) {
		var s = String( raw || '' ).trim();
		if ( ! s ) {
			return i18n.popupTargetDefault || 'Popup';
		}
		if ( ! /\bpopup$/i.test( s ) ) {
			s = s + ' popup';
		}
		return s;
	}

	function popupCreateName( raw ) {
		var s = String( raw || '' ).trim();
		s = s.replace( /\s+popup$/i, '' ).trim();
		return s || String( raw || '' ).trim() || 'New popup';
	}

	function rwgcModalButton( text, variant, action, extra ) {
		var attrs = {
			type: 'button',
			class: 'rwgc-button rwgc-button--' + ( variant || 'secondary' ),
			text: text,
			'data-popup-action': action,
		};
		if ( extra ) {
			Object.keys( extra ).forEach( function ( key ) {
				attrs[ key ] = extra[ key ];
			} );
		}
		return $( '<button>', attrs );
	}

	function rwgcModalFooter( buttons, includeCancel, cancelMode ) {
		var $footer = $( '<div>', { class: 'rwgc-modal-footer' } );
		if ( buttons && buttons.length ) {
			var $actions = $( '<div>', { class: 'rwgc-modal-actions' } );
			buttons.forEach( function ( $btn ) {
				$actions.append( $btn );
			} );
			$footer.append( $actions );
		}
		if ( includeCancel !== false ) {
			if ( 'drawer' === cancelMode ) {
				$footer.append( $( '<button>', {
					type: 'button',
					class: 'rwgc-button rwgc-button--ghost',
					text: i18n.cancel || 'Cancel',
					'data-drawer-action': 'cancel',
				} ) );
			} else {
				$footer.append( rwgcModalButton( i18n.cancel || 'Cancel', 'ghost', 'cancel' ) );
			}
		}
		return $footer;
	}

	function proposalIdForRequest() {
		if ( state.proposalId ) {
			return String( state.proposalId );
		}
		if ( state.proposal && state.proposal.id ) {
			return String( state.proposal.id );
		}
		return '';
	}

	function actionIdForCard( idx ) {
		var card = ( state.proposal && state.proposal.action_cards ) ? state.proposal.action_cards[ idx ] : null;
		if ( card && card.action_id ) {
			return String( card.action_id );
		}
		return 'action_' + ( idx + 1 );
	}

	function parseTargetCreateError( xhr ) {
		var resp = xhr && xhr.responseJSON ? xhr.responseJSON : null;
		var fallback = {
			code: 'create_failed',
			message: i18n.popupCreateFailed || 'Could not create the popup. Try again or choose an existing popup.',
			details: {},
		};
		if ( ! resp ) {
			return fallback;
		}
		if ( resp.success === false && ( resp.code || resp.message ) ) {
			return {
				code: resp.code || resp.reason || 'create_failed',
				message: resp.message || fallback.message,
				details: resp.details || {},
				matches: resp.matches || ( resp.details && resp.details.matches ) || [],
			};
		}
		if ( resp.code && resp.message ) {
			var codeMap = {
				rwgc_invalid_attach_context: 'attach_failed',
				rwgc_forbidden: 'capability_failed',
				rwgc_popup_unavailable: 'unsupported_target_type',
				rwgc_invalid_title: 'create_failed',
				rwgc_popup_create_failed: 'create_failed',
				rwgc_unsupported_target_type: 'unsupported_target_type',
				rest_cookie_invalid_nonce: 'invalid_nonce',
				rest_forbidden: 'capability_failed',
			};
			return {
				code: codeMap[ resp.code ] || resp.code,
				message: resp.message,
				details: resp.data || {},
			};
		}
		return fallback;
	}

	function setPopupCreateError( pr, err ) {
		pr.createLoading = false;
		pr.createError = err || null;
		renderPopupResolver();
	}

	function isPopupDuplicateResponse( resp ) {
		if ( ! resp || resp.success !== false ) {
			return false;
		}
		return resp.code === 'duplicate_found'
			|| resp.reason === 'possible_duplicate'
			|| ( resp.details && resp.details.matches && resp.details.matches.length );
	}

	function applyPopupDuplicateResponse( pr, resp ) {
		pr.createLoading = false;
		pr.createError = null;
		pr.duplicateMatches = resp.matches || ( resp.details && resp.details.matches ) || [];
		pr.duplicateMessage = resp.message || '';
		pr.view = 'duplicate';
		renderPopupResolver();
	}

	function fallbackPopupSearch( q ) {
		var ql = String( q || '' ).toLowerCase();
		return ( cfg.popups || [] ).filter( function ( p ) {
			if ( ! ql ) {
				return true;
			}
			return String( p.title || '' ).toLowerCase().indexOf( ql ) >= 0;
		} ).map( function ( p ) {
			return {
				id: p.id,
				label: p.title,
				status: 'publish',
				status_label: i18n.statusValid || 'Published',
			};
		} );
	}

	function fetchPopupSearch( q ) {
		var base = cfg.targetSearchUrl || '';
		if ( ! base ) {
			return $.Deferred().resolve( fallbackPopupSearch( q ) ).promise();
		}
		var url = base + ( base.indexOf( '?' ) >= 0 ? '&' : '?' ) + 'target_type=popup&q=' + encodeURIComponent( q || '' );
		return $.ajax( {
			url: url,
			method: 'GET',
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', cfg.restNonce || '' );
			},
		} ).then( function ( resp ) {
			return ( resp && resp.results ) ? resp.results : [];
		}, function () {
			return fallbackPopupSearch( q );
		} );
	}

	function schedulePopupSearch( q ) {
		if ( state.popupSearchTimer ) {
			clearTimeout( state.popupSearchTimer );
		}
		if ( ! state.popupResolver ) {
			return;
		}
		state.popupResolver.searchLoading = true;
		state.popupResolver.searchQuery = q;
		renderPopupResolverChoosePanel();
		state.popupSearchTimer = setTimeout( function () {
			state.popupSearchTimer = null;
			fetchPopupSearch( q ).done( function ( results ) {
				if ( ! state.popupResolver || state.popupResolver.view !== 'choose' ) {
					return;
				}
				state.popupResolver.searchResults = results || [];
				state.popupResolver.searchLoading = false;
				renderPopupResolverChoosePanel();
			} );
		}, 320 );
	}

	function updateActionTargetFromResolution( idx, target, rawKey ) {
		var card = ( state.proposal && state.proposal.action_cards ) ? state.proposal.action_cards[ idx ] : null;
		if ( ! card ) {
			return;
		}
		var raw = rawKey || targetFieldRaw( card, idx );
		var resolution = {
			kind: 'chosen',
			id: String( target.id || '' ),
			label: target.label || '',
			created_by_assistant: !! target.created_by_assistant,
			created_status: target.created_status || '',
			edit_url: target.edit_url || '',
		};
		state.cardResolutions[ fieldKey( idx, 'target', raw ) ] = resolution;
		if ( ! card.target ) {
			card.target = {};
		}
		card.target.type = target.type || card.target.type || 'popup';
		card.target.id = target.id;
		card.target.label = target.label || card.target.label;
		card.target.status = 'matched';
		card.target.resolved = {
			id: String( target.id || '' ),
			name: target.label || card.target.label || '',
		};
		card.target.created_by_assistant = !! target.created_by_assistant;
		if ( target.created_status ) {
			card.target.created_status = target.created_status;
		}
		if ( target.edit_url ) {
			card.target.edit_url = target.edit_url;
		}
		seedPopupTargetCardResolutions( card, idx, target.id, target.label || card.target.label );
		recordConditionLearning( idx, 'target', raw, resolution );
	}

	function syncProposalPayload() {
		applyResolutionsToProposalCards();
		recalculateClientActionState();
	}

	function showPopupCreateToast( target ) {
		var $toast = $( '#rwgc-popup-create-toast' );
		if ( ! $toast.length ) {
			$toast = $( '<div>', {
				id: 'rwgc-popup-create-toast',
				class: 'rwgc-popup-resolver__success',
				role: 'status',
			} );
			$( '#rwgc-targeting-review' ).prepend( $toast );
		}
		$toast.empty().append( $( '<p>' ).text( i18n.popupCreatedSelected || 'Popup created and selected as the target.' ) );
		if ( target && target.edit_url ) {
			var $links = $( '<div>', { class: 'rwgc-popup-resolver__success-links' } );
			$links.append( $( '<a>', {
				href: target.edit_url,
				target: '_blank',
				rel: 'noopener noreferrer',
				text: i18n.popupOpenEditor || 'Open popup editor',
			} ) );
			$links.append( $( '<button>', {
				type: 'button',
				class: 'rwgc-link-button',
				text: i18n.popupContinueSetup || 'Continue setup',
				'data-popup-toast-action': 'dismiss',
			} ) );
			$toast.append( $links );
		}
		$toast.removeClass( 'rwgc-is-hidden' );
	}

	function applyPopupTargetResolution( idx, raw, target, opts ) {
		opts = opts || {};
		var card = ( state.proposal && state.proposal.action_cards ) ? state.proposal.action_cards[ idx ] : null;
		var rawKey = raw || ( card ? targetFieldRaw( card, idx ) : '' );
		updateActionTargetFromResolution( idx, target, rawKey );
		closeResolutionDrawer();
		syncProposalPayload();
		if ( opts.showToast ) {
			showPopupCreateToast( target );
		}
		rerenderCards();
	}

	function removePopupAction( idx ) {
		state.cardResolutions[ 'removed_' + cardKey( idx ) ] = true;
		closeResolutionDrawer();
		rerenderCards();
	}

	function renderPopupResolverStart( $panel, pr ) {
		var detected = popupDetectedLabel( pr.raw );
		var exactName = popupCreateName( pr.raw );
		$panel.append( $( '<h3>', { class: 'rwgc-resolution-drawer__title' } ).text( i18n.resolvePopupTarget || 'Resolve popup target' ) );
		$panel.append( $( '<p>', { class: 'rwgc-popup-resolver__meta' } ).append(
			$( '<strong>' ).text( ( i18n.drawerDetected || 'Detected' ) + ':' ),
			' ',
			$( '<span>' ).text( detected )
		) );
		var expl = ( i18n.popupTargetStartExplanation || 'I could not find an exact popup called "%s". You can create it now, choose an existing popup, or remove this action.' )
			.replace( '%s', exactName );
		$panel.append( $( '<p>', { class: 'rwgc-popup-resolver__explanation' } ).text( expl ) );

		var $choices = $( '<div>', { class: 'rwgc-modal-actions rwgc-popup-resolver__choices' } );
		$choices.append( rwgcModalButton( i18n.popupCreateNew || 'Create new popup', 'primary', 'goto_create' ) );
		$choices.append( rwgcModalButton( i18n.popupChooseExisting || 'Choose existing popup', 'secondary', 'goto_choose' ) );
		$choices.append( rwgcModalButton( i18n.cardRemoveAction || 'Remove action', 'danger', 'goto_confirm_remove' ) );
		$panel.append( $choices );
		$panel.append( rwgcModalFooter( [], true ) );
	}

	function renderPopupResolverCreate( $panel, pr ) {
		if ( pr.createError ) {
			renderPopupResolverCreateError( $panel, pr );
			return;
		}

		$panel.append( $( '<h3>', { class: 'rwgc-resolution-drawer__title' } ).text( i18n.popupCreateTitle || 'Create popup' ) );

		var $nameRow = $( '<label>', { class: 'rwgc-popup-resolver__field' } );
		$nameRow.append( $( '<span>', { class: 'rwgc-popup-resolver__field-label' } ).text( i18n.popupNameLabel || 'Name' ) );
		$nameRow.append( $( '<input>', {
			type: 'text',
			class: 'rwgc-popup-resolver__input',
			value: pr.createName || '',
			'data-popup-field': 'create_name',
		} ) );
		$panel.append( $nameRow );

		var $statusRow = $( '<label>', { class: 'rwgc-popup-resolver__field' } );
		$statusRow.append( $( '<span>', { class: 'rwgc-popup-resolver__field-label' } ).text( i18n.popupStatusLabel || 'Status' ) );
		$statusRow.append( $( '<select>', { class: 'rwgc-popup-resolver__input', 'data-popup-field': 'create_status' } ).append(
			$( '<option>', { value: 'draft', text: i18n.popupStatusDraft || 'Draft', selected: ( pr.createStatus || 'draft' ) === 'draft' } )
		) );
		$panel.append( $statusRow );

		var attachLabel = ( i18n.popupAttachAction || 'Use this popup as the target for Action %d' ).replace( '%d', String( pr.card + 1 ) );
		var $attach = $( '<label>', { class: 'rwgc-popup-resolver__checkbox' } );
		$attach.append( $( '<input>', {
			type: 'checkbox',
			checked: pr.attachToAction !== false,
			'data-popup-field': 'attach_action',
		} ) );
		$attach.append( $( '<span>' ).text( attachLabel ) );
		$panel.append( $attach );

		var createBtn = rwgcModalButton(
			i18n.popupCreateButton || 'Create popup',
			'primary',
			'submit_create',
			{ disabled: pr.createLoading ? 'disabled' : null }
		);
		$panel.append( rwgcModalFooter( [
			createBtn,
			rwgcModalButton( i18n.popupBack || 'Back', 'secondary', 'goto_start' ),
		], true ) );
	}

	function renderPopupResolverCreateError( $panel, pr ) {
		var err = pr.createError || {};
		$panel.append( $( '<h3>', { class: 'rwgc-resolution-drawer__title' } ).text( i18n.popupCreateErrorTitle || 'Could not create popup' ) );
		var $box = $( '<div>', { class: 'rwgc-popup-resolver__error', role: 'alert' } );
		$box.append( $( '<p>', { class: 'rwgc-popup-resolver__error-label' } ).text( i18n.popupCreateErrorReason || 'Reason:' ) );
		$box.append( $( '<p>', { class: 'rwgc-popup-resolver__error-message' } ).text( err.message || ( i18n.popupCreateFailed || 'Could not create the popup.' ) ) );
		$panel.append( $box );
		$panel.append( rwgcModalFooter( [
			rwgcModalButton( i18n.popupTryAgain || 'Try again', 'primary', 'retry_create' ),
			rwgcModalButton( i18n.popupChooseExisting || 'Choose existing popup', 'secondary', 'goto_choose' ),
		], true ) );
	}

	function renderPopupResolverDuplicate( $panel, pr ) {
		$panel.append( $( '<h3>', { class: 'rwgc-resolution-drawer__title' } ).text( i18n.popupDuplicateTitle || 'Similar popup found' ) );
		$panel.append( $( '<p>', { class: 'rwgc-popup-resolver__explanation' } ).text(
			pr.duplicateMessage || i18n.popupDuplicateMessage || 'A similar popup already exists.'
		) );
		var $list = $( '<ul>', { class: 'rwgc-popup-resolver__duplicate-list' } );
		( pr.duplicateMatches || [] ).forEach( function ( row ) {
			$list.append( $( '<li>' ).text( row.label || '' ) );
		} );
		$panel.append( $list );
		$panel.append( rwgcModalFooter( [
			rwgcModalButton( i18n.popupUseExisting || 'Use existing', 'primary', 'use_duplicate' ),
			rwgcModalButton( i18n.popupCreateAnyway || 'Create new anyway', 'secondary', 'force_create' ),
		], true ) );
	}

	function renderPopupResolverChoosePanel() {
		var $panel = $( '#rwgc-resolution-drawer .rwgc-popup-resolver__choose-body' );
		if ( ! $panel.length || ! state.popupResolver ) {
			return;
		}
		var pr = state.popupResolver;
		$panel.empty();

		var $search = $( '<input>', {
			type: 'search',
			class: 'rwgc-popup-resolver__input rwgc-popup-search-input',
			placeholder: i18n.popupSearchPlaceholder || 'Search popups…',
			value: pr.searchQuery || '',
		} );
		$panel.append( $( '<div>', { class: 'rwgc-popup-resolver__search' } ).append(
			$( '<span>', { class: 'rwgc-popup-resolver__field-label' } ).text( i18n.popupSearchLabel || 'Search' ),
			$search
		) );

		if ( pr.searchLoading ) {
			$panel.append( $( '<p>', { class: 'rwgc-popup-resolver__loading' } ).text( i18n.popupSearchLoading || 'Searching popups…' ) );
			return;
		}

		var results = pr.searchResults || [];
		if ( ! results.length ) {
			var $empty = $( '<div>', { class: 'rwgc-popup-resolver__empty' } );
			$empty.append( $( '<p>' ).text( i18n.popupSearchEmpty || 'No matching popups found.' ) );
			$empty.append( rwgcModalButton(
				( i18n.popupCreateFromEmpty || 'Create new popup: %s' ).replace( '%s', popupCreateName( pr.raw ) ),
				'secondary',
				'goto_create'
			) );
			$panel.append( $empty );
			return;
		}

		var $list = $( '<div>', { class: 'rwgc-popup-resolver__list', role: 'radiogroup' } );
		results.forEach( function ( row ) {
			var id = String( row.id || '' );
			var $item = $( '<label>', { class: 'rwgc-popup-resolver__row' + ( pr.selectedPopupId === id ? ' is-selected' : '' ) } );
			$item.append( $( '<input>', {
				type: 'radio',
				name: 'rwgc-popup-choice',
				value: id,
				checked: pr.selectedPopupId === id,
			} ) );
			var $meta = $( '<span>', { class: 'rwgc-popup-resolver__row-body' } );
			$meta.append( $( '<span>', { class: 'rwgc-popup-resolver__row-title' } ).text( row.label || '' ) );
			var statusBits = [ row.status_label || row.status || '' ];
			if ( row.modified ) {
				statusBits.push( row.modified );
			}
			$meta.append( $( '<span>', { class: 'rwgc-popup-resolver__row-meta' } ).text( statusBits.filter( Boolean ).join( ' · ' ) ) );
			$item.append( $meta );
			$list.append( $item );
		} );
		$panel.append( $list );
	}

	function renderPopupResolverChoose( $panel, pr ) {
		$panel.append( $( '<h3>', { class: 'rwgc-resolution-drawer__title' } ).text( i18n.popupChooseTitle || 'Choose existing popup' ) );
		$panel.append( $( '<div>', { class: 'rwgc-popup-resolver__choose-body' } ) );
		renderPopupResolverChoosePanel();

		var useBtn = rwgcModalButton(
			i18n.popupUseSelected || 'Use selected popup',
			'primary',
			'submit_choose',
			{ disabled: pr.selectedPopupId ? null : 'disabled' }
		);
		$panel.append( rwgcModalFooter( [
			useBtn,
			rwgcModalButton( i18n.popupBack || 'Back', 'secondary', 'goto_start' ),
		], true ) );
	}

	function renderPopupResolverConfirmRemove( $panel, pr ) {
		$panel.append( $( '<h3>', { class: 'rwgc-resolution-drawer__title' } ).text( i18n.popupRemoveConfirmTitle || 'Remove action?' ) );
		var body = ( i18n.popupRemoveConfirmBody || 'This will remove the rule setup for %s.' )
			.replace( '%s', popupDetectedLabel( pr.raw ) );
		$panel.append( $( '<p>', { class: 'rwgc-popup-resolver__explanation' } ).text( body ) );
		$panel.append( rwgcModalFooter( [
			rwgcModalButton( i18n.cardRemoveAction || 'Remove action', 'danger', 'confirm_remove' ),
			rwgcModalButton( i18n.popupBack || 'Back', 'secondary', 'goto_start' ),
		], true ) );
	}

	function renderPopupResolver() {
		var pr = state.popupResolver;
		if ( ! pr ) {
			return;
		}
		var $drawer = ensureResolutionDrawer();
		var $panel = $drawer.find( '.rwgc-resolution-drawer__panel' );
		$panel.empty().addClass( 'rwgc-popup-resolver' );
		state.resolutionDrawer = null;

		if ( pr.view === 'create' ) {
			renderPopupResolverCreate( $panel, pr );
		} else if ( pr.view === 'choose' ) {
			renderPopupResolverChoose( $panel, pr );
		} else if ( pr.view === 'confirm_remove' ) {
			renderPopupResolverConfirmRemove( $panel, pr );
		} else if ( pr.view === 'duplicate' ) {
			renderPopupResolverDuplicate( $panel, pr );
		} else {
			renderPopupResolverStart( $panel, pr );
		}

		openResolutionDrawerShell();
	}

	function openResolutionDrawerShell() {
		var $drawer = ensureResolutionDrawer();
		$drawer.removeClass( 'rwgc-is-hidden' ).attr( 'aria-hidden', 'false' );
		$( 'body' ).addClass( 'rwgc-modal-open' );
		var $panel = $drawer.find( '.rwgc-resolution-drawer__panel' );
		if ( $panel.length ) {
			state.modalReturnFocus = document.activeElement;
			$panel.attr( 'tabindex', '-1' ).trigger( 'focus' );
		}
	}

	function submitCreatePopup( forceCreate ) {
		var pr = state.popupResolver;
		if ( ! pr || pr.createLoading ) {
			return;
		}
		var name = String( pr.createName || '' ).trim();
		if ( ! name ) {
			return;
		}
		if ( ! cfg.targetCreateUrl ) {
			setPopupCreateError( pr, {
				code: 'create_failed',
				message: i18n.popupCreateEndpointMissing || 'Popup create endpoint is not configured on this site.',
			} );
			return;
		}
		pr.createLoading = true;
		pr.createError = null;
		renderPopupResolver();

		var wantsAttach = pr.attachToAction !== false;
		var proposalId = proposalIdForRequest();
		var attachToAction = wantsAttach && !! proposalId;
		var payload = {
			target_type: 'popup',
			title: name,
			status: pr.createStatus || 'draft',
			proposal_id: attachToAction ? proposalId : '',
			action_id: actionIdForCard( pr.card ),
			attach_to_action: attachToAction,
			force_create: !! forceCreate,
		};

		$.ajax( {
			url: cfg.targetCreateUrl,
			method: 'POST',
			contentType: 'application/json',
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', cfg.restNonce || '' );
			},
			data: JSON.stringify( payload ),
		} ).done( function ( resp ) {
			if ( isPopupDuplicateResponse( resp ) ) {
				applyPopupDuplicateResponse( pr, resp );
				return;
			}
			if ( ! resp || ! resp.success ) {
				setPopupCreateError( pr, {
					code: ( resp && resp.code ) || 'create_failed',
					message: ( resp && resp.message ) || ( i18n.popupCreateFailed || 'Could not create the popup.' ),
					details: ( resp && resp.details ) || {},
				} );
				return;
			}
			var target = resp.target || {};
			var targetId = target.id || target.post_id;
			if ( ! targetId ) {
				setPopupCreateError( pr, {
					code: 'create_failed',
					message: i18n.popupCreateFailed || 'Could not create the popup.',
					details: { response: target },
				} );
				return;
			}
			target = {
				type: target.type || 'popup',
				id: targetId,
				label: target.label || name,
				status: target.status || 'valid',
				created_by_assistant: target.created_by_assistant !== false,
				created_status: target.created_status || pr.createStatus || 'draft',
				edit_url: target.edit_url || '',
			};
			if ( wantsAttach ) {
				applyPopupTargetResolution( pr.card, pr.raw, target, { showToast: true } );
			} else {
				closeResolutionDrawer();
				rerenderCards();
			}
		} ).fail( function ( xhr ) {
			var err = parseTargetCreateError( xhr );
			if ( err.code === 'duplicate_found' || ( err.matches && err.matches.length ) ) {
				applyPopupDuplicateResponse( pr, {
					success: false,
					code: 'duplicate_found',
					message: err.message,
					matches: err.matches,
				} );
				return;
			}
			setPopupCreateError( pr, err );
		} );
	}

	function submitChoosePopup() {
		var pr = state.popupResolver;
		if ( ! pr || ! pr.selectedPopupId ) {
			return;
		}
		var chosen = null;
		( pr.searchResults || [] ).forEach( function ( row ) {
			if ( String( row.id ) === String( pr.selectedPopupId ) ) {
				chosen = row;
			}
		} );
		if ( ! chosen ) {
			return;
		}
		applyPopupTargetResolution( pr.card, pr.raw, {
			id: chosen.id,
			label: chosen.label,
			type: 'popup',
			status: 'valid',
			edit_url: chosen.edit_url || '',
			created_by_assistant: false,
		} );
	}

	function handlePopupResolverAction( action ) {
		var pr = state.popupResolver;
		if ( ! pr ) {
			return;
		}
		if ( 'cancel' === action ) {
			closeResolutionDrawer();
		} else if ( 'goto_start' === action ) {
			pr.view = 'start';
			renderPopupResolver();
		} else if ( 'goto_create' === action ) {
			pr.view = 'create';
			pr.createError = null;
			pr.createName = pr.createName || popupCreateName( pr.raw );
			pr.createStatus = pr.createStatus || 'draft';
			renderPopupResolver();
		} else if ( 'goto_choose' === action ) {
			pr.view = 'choose';
			pr.createError = null;
			pr.selectedPopupId = '';
			pr.searchResults = [];
			renderPopupResolver();
			schedulePopupSearch( '' );
		} else if ( 'retry_create' === action ) {
			pr.createError = null;
			pr.view = 'create';
			renderPopupResolver();
		} else if ( 'goto_confirm_remove' === action ) {
			pr.view = 'confirm_remove';
			renderPopupResolver();
		} else if ( 'submit_create' === action ) {
			submitCreatePopup( false );
		} else if ( 'force_create' === action ) {
			submitCreatePopup( true );
		} else if ( 'use_duplicate' === action ) {
			var match = ( pr.duplicateMatches || [] )[ 0 ];
			if ( match ) {
				applyPopupTargetResolution( pr.card, pr.raw, {
					id: match.id,
					label: match.label,
					type: 'popup',
					status: 'valid',
					created_by_assistant: false,
				} );
			}
		} else if ( 'submit_choose' === action ) {
			submitChoosePopup();
		} else if ( 'confirm_remove' === action ) {
			removePopupAction( pr.card );
		}
	}

	function openPopupTargetResolver( idx, raw, initialView ) {
		var card = ( state.proposal && state.proposal.action_cards ) ? state.proposal.action_cards[ idx ] : null;
		if ( ! card ) {
			return;
		}
		var detected = raw || ( card.target && card.target.raw ) || ( card.target && card.target.label ) || '';
		state.popupResolver = {
			mode: 'popup',
			view: initialView || 'start',
			card: idx,
			raw: detected,
			selectedPopupId: '',
			searchQuery: '',
			searchResults: [],
			searchLoading: false,
			createName: popupCreateName( detected ),
			createStatus: 'draft',
			attachToAction: true,
			createLoading: false,
			createError: null,
		};
		if ( state.popupResolver.view === 'choose' ) {
			renderPopupResolver();
			schedulePopupSearch( '' );
			return;
		}
		renderPopupResolver();
	}

	function openFirstUnresolvedDrawer() {
		var proposal = state.proposal;
		if ( ! proposal || ! proposal.action_cards ) {
			return false;
		}
		var cards = proposal.action_cards;
		for ( var i = 0; i < cards.length; i++ ) {
			if ( isCardRemoved( i ) ) {
				continue;
			}
			var card = cards[ i ];
			var reqs = ( card && card.requiredResolutions ) || [];
			for ( var o = 0; o < RESOLVER_FIELD_ORDER.length; o++ ) {
				var fieldOrder = RESOLVER_FIELD_ORDER[ o ];
				for ( var r = 0; r < reqs.length; r++ ) {
					var req = reqs[ r ];
					if ( req.field !== fieldOrder || fieldResolution( i, req.field, req.raw ) ) {
						continue;
					}
					jumpToCard( i );
					if ( req.field === 'target' && card.target && card.target.type === 'popup' ) {
						openPopupTargetResolver( i, req.raw );
						return true;
					}
					if ( req.field === 'traffic_source' || ( req.options && req.options.length ) ) {
						openResolverForField( i, req.field, req.raw );
						return true;
					}
					if ( req.field === 'target' ) {
						$( '.rwgc-geo-card__picker[data-picker-card="' + i + '"]' ).removeClass( 'rwgc-is-hidden' );
						return true;
					}
					openResolverForField( i, req.field, req.raw );
					return true;
				}
			}
		}
		return false;
	}

	function ensureResolutionDrawer() {
		var $drawer = $( '#rwgc-resolution-drawer' );
		if ( ! $drawer.length ) {
			$drawer = $( '<div>', {
				id: 'rwgc-resolution-drawer',
				class: 'rwgc-modal-overlay rwgc-resolution-drawer rwgc-is-hidden',
				'aria-hidden': 'true',
			} );
			$drawer.append( $( '<div>', {
				class: 'rwgc-modal rwgc-resolution-drawer__panel',
				role: 'dialog',
				'aria-modal': 'true',
				tabindex: '-1',
			} ) );
			$( 'body' ).append( $drawer );
		}
		return $drawer;
	}

	function closeResolutionDrawer() {
		var $drawer = $( '#rwgc-resolution-drawer' );
		$drawer.addClass( 'rwgc-is-hidden' ).attr( 'aria-hidden', 'true' );
		$drawer.find( '.rwgc-resolution-drawer__panel' ).removeClass( 'rwgc-popup-resolver rwgc-google-ads-resolver rwgc-url-match-resolver' ).empty();
		$( 'body' ).removeClass( 'rwgc-modal-open' );
		if ( state.modalReturnFocus && state.modalReturnFocus.focus ) {
			try {
				state.modalReturnFocus.focus();
			} catch ( e ) {
				// Ignore focus restore errors.
			}
		}
		state.modalReturnFocus = null;
		state.resolutionDrawer = null;
		state.popupResolver = null;
		if ( state.popupSearchTimer ) {
			clearTimeout( state.popupSearchTimer );
			state.popupSearchTimer = null;
		}
	}

	function openResolutionDrawer( opts ) {
		opts = opts || {};
		var $drawer = ensureResolutionDrawer();
		var $panel = $drawer.find( '.rwgc-resolution-drawer__panel' );
		$panel.empty().removeClass( 'rwgc-google-ads-resolver rwgc-popup-resolver rwgc-url-match-resolver' );

		if ( opts.useMappingCards && opts.field === 'traffic_source' ) {
			renderGoogleAdsMappingDrawer( $panel, opts );
			openResolutionDrawerShell();
			return;
		}

		state.resolutionDrawer = {
			card: opts.card,
			field: opts.field,
			raw: opts.raw || '',
			selected: opts.recommendedKey || '',
		};

		$panel.append( $( '<h3>', { class: 'rwgc-resolution-drawer__title' } ).text( opts.title || ( i18n.resolveField || 'Resolve' ) ) );
		if ( opts.detected ) {
			$panel.append( $( '<p>', { class: 'rwgc-resolution-drawer__label' } ).html(
				'<strong>' + esc( i18n.drawerDetected || 'Detected' ) + ':</strong> ' + esc( opts.detected )
			) );
		}
		if ( opts.why ) {
			$panel.append( $( '<p>', { class: 'rwgc-resolution-drawer__why' } ).html(
				'<strong>' + esc( i18n.drawerWhy || 'Why this needs resolution' ) + ':</strong> ' + esc( opts.why )
			) );
		}
		if ( opts.recommendedLabel ) {
			$panel.append( $( '<p>', { class: 'rwgc-resolution-drawer__recommended' } ).html(
				'<strong>' + esc( i18n.drawerRecommended || 'Recommended' ) + ':</strong> ' + esc( opts.recommendedLabel )
			) );
		}

		var $opts = $( '<div>', { class: 'rwgc-resolution-drawer__options' } );
		( opts.options || [] ).forEach( function ( opt ) {
			var $btn = $( '<button>', {
				type: 'button',
				class: 'button rwgc-resolution-option' + ( opt.recommended ? ' is-recommended' : '' ) + ( opt.key === 'remove_google_ads_condition' ? ' rwgc-resolution-option--danger' : '' ),
				text: opt.label || opt.key,
				'data-option-key': opt.key || '',
			} );
			if ( opt.recommended ) {
				state.resolutionDrawer.selected = opt.key;
				$btn.addClass( 'is-selected' );
			}
			$opts.append( $btn );
		} );
		$panel.append( $opts );

		var $actions = $( '<div>', { class: 'rwgc-resolution-drawer__actions' } );
		$actions.append( $( '<button>', {
			type: 'button',
			class: 'button button-primary',
			text: opts.applyLabel || ( i18n.drawerApply || 'Apply mapping' ),
			'data-drawer-action': 'apply',
		} ) );
		$actions.append( $( '<button>', {
			type: 'button',
			class: 'button button-link',
			text: i18n.cancel || 'Cancel',
			'data-drawer-action': 'cancel',
		} ) );
		$panel.append( $actions );

		openResolutionDrawerShell();
	}

	function applyUrlMatchDrawer() {
		var drawer = state.resolutionDrawer;
		if ( ! drawer || drawer.mode !== 'url_match' ) {
			return;
		}
		var path = String( drawer.path || '' ).trim();
		if ( ! path ) {
			return;
		}
		var matchType = drawer.matchType || 'contains';
		var childLabel = drawer.urlChildLabel || ( drawer.urlChild ? drawer.urlChild.label : '' );
		var displayLabel = urlMatchLabel( matchType, path );
		state.cardResolutions[ fieldKey( drawer.card, 'url_match', childLabel ) ] = {
			kind: 'chosen',
			id: matchType,
			label: displayLabel,
			value: path,
		};
		if ( drawer.returnToGoogleAds && drawer.googleAdsOpts ) {
			var $drawer = ensureResolutionDrawer();
			var $panel = $drawer.find( '.rwgc-resolution-drawer__panel' );
			$panel.empty().removeClass( 'rwgc-url-match-resolver' ).addClass( 'rwgc-google-ads-resolver' );
			renderGoogleAdsMappingDrawer( $panel, drawer.googleAdsOpts );
			openResolutionDrawerShell();
			rerenderCards();
			return;
		}
		closeResolutionDrawer();
		rerenderCards();
	}

	function returnToGoogleAdsDrawer() {
		var drawer = state.resolutionDrawer;
		if ( ! drawer || ! drawer.googleAdsOpts ) {
			closeResolutionDrawer();
			return;
		}
		var $drawer = ensureResolutionDrawer();
		var $panel = $drawer.find( '.rwgc-resolution-drawer__panel' );
		$panel.empty().removeClass( 'rwgc-url-match-resolver' ).addClass( 'rwgc-google-ads-resolver' );
		renderGoogleAdsMappingDrawer( $panel, drawer.googleAdsOpts );
		openResolutionDrawerShell();
	}

	function applyResolutionDrawer() {
		var drawer = state.resolutionDrawer;
		if ( ! drawer || ! drawer.selected ) {
			return;
		}
		if ( drawer.selected === 'remove_action' ) {
			state.cardResolutions[ 'removed_' + cardKey( drawer.card ) ] = true;
			closeResolutionDrawer();
			rerenderCards();
			return;
		}
		var opt = null;
		var card = ( state.proposal && state.proposal.action_cards ) ? state.proposal.action_cards[ drawer.card ] : null;
		if ( card ) {
			resolutionOptionsForField( card, drawer.field, drawer.raw ).forEach( function ( row ) {
				if ( row.key === drawer.selected ) {
					opt = row;
				}
			} );
		}
		var kind = 'choose';
		if ( drawer.selected === 'remove_google_ads_condition' ) {
			kind = 'ignore';
		} else if ( drawer.selected === 'configure_google_ads_mapping' ) {
			kind = 'pick_manual';
		}
		var resolutionLabel = opt ? opt.label : drawer.selected;
		if ( drawer.field === 'traffic_source' && kind === 'choose' ) {
			resolutionLabel = googleAdsResolutionShortLabel( drawer.selected, resolutionLabel );
		}
		if ( kind === 'ignore' ) {
			state.cardResolutions[ fieldKey( drawer.card, drawer.field, drawer.raw ) ] = { kind: 'ignored' };
		} else if ( kind === 'pick_manual' ) {
			var picked = String( drawer.customMapping || '' ).trim();
			if ( ! picked ) {
				return;
			}
			state.cardResolutions[ fieldKey( drawer.card, drawer.field, drawer.raw ) ] = {
				kind: 'chosen',
				id: drawer.selected,
				label: picked,
			};
		} else {
			state.cardResolutions[ fieldKey( drawer.card, drawer.field, drawer.raw ) ] = {
				kind: 'chosen',
				id: drawer.selected,
				label: resolutionLabel,
			};
		}
		recordConditionLearning( drawer.card, drawer.field, drawer.raw, state.cardResolutions[ fieldKey( drawer.card, drawer.field, drawer.raw ) ] );
		closeResolutionDrawer();
		syncProposalPayload();
		rerenderCards();
	}

	function conditionOptionButton( idx, field, raw, opt ) {
		var kind = 'choose';
		var id = '';
		if ( opt.key === 'remove' || opt.key === 'any_audience' || opt.key === 'remove_google_ads_condition' ) {
			kind = 'ignore';
		} else if ( opt.picker || opt.key === 'choose_audiences' || opt.key === 'configure_google_ads_mapping' ) {
			kind = 'pick_manual';
		} else if ( opt.key === 'refresh' ) {
			kind = 'refresh';
		} else if ( opt.value && opt.value.type && opt.value.code ) {
			id = opt.value.type + ':' + opt.value.code;
		} else if ( opt.key ) {
			id = opt.key;
		}
		return $( '<button>', {
			type: 'button',
			class: 'button rwgc-resolution-option' + ( opt.recommended ? ' is-recommended' : '' ) + ( opt.key === 'remove' || opt.key === 'remove_google_ads_condition' ? ' rwgc-resolution-option--danger' : '' ),
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
		var resolutionMeta = conditionGroupResolution( row );
		var field = resolutionMeta.field;
		var resolution = field ? fieldResolution( idx, field, resolutionMeta.raw ) : null;
		var effStatus = effectiveRowStatus( row, idx );
		var resolved = effStatus === 'valid';

		var cardClass = 'rwgc-condition-card';
		cardClass += resolved ? ' rwgc-condition-card--valid' : ' rwgc-condition-card--needs-resolution rwgc-condition-card--warning';

		var $cc = $( '<div>', { class: cardClass } );

		var $head = $( '<div>', { class: 'rwgc-condition-card__head' } );
		$head.append( dashicon( row.icon ) );
		var $meta = $( '<div>', { class: 'rwgc-condition-card__meta' } );
		var typeLabel = conditionTypeDisplayLabel( row.type );
		if ( row.mode === 'exclude' ) {
			typeLabel += ' (' + ( i18n.cardExclude || 'Exclude' ) + ')';
		}
		$meta.append( $( '<span>', { class: 'rwgc-condition-card__type' } ).text( typeLabel ) );
		$head.append( $meta );

		var pillKind = resolved ? 'valid' : 'needs-resolution';
		var pillText = resolved ? ( i18n.statusValid || 'Valid' ) : ( i18n.statusNeedsResolution || 'Needs resolution' );
		$head.append( $( '<span>', { class: 'rwgc-status-pill rwgc-status-pill--' + pillKind } ).text( pillText ) );
		$cc.append( $head );

		var $body = $( '<div>', { class: 'rwgc-condition-card__summary' } );
		var valueText = row.label || row.raw || '';
		if ( resolution ) {
			valueText = resolution.kind === 'ignored'
				? ( i18n.cardIgnored || 'Removed' )
				: ( row.type === 'condition_group' && field === 'traffic_source'
					? ( row.label || valueText )
					: ( resolution.label || valueText ) );
		}
		$body.append( $( '<p>', { class: 'rwgc-condition-card__value' } ).text( valueText ) );
		if ( row.type === 'condition_group' && row.children && row.children.length ) {
			$body.append( renderConditionGroupChildren( row, idx ) );
		}
		$cc.append( $body );

		if ( ! resolved && field ) {
			var $footer = $( '<div>', { class: 'rwgc-condition-card__actions' } );
			var resolveLabel = field === 'traffic_source'
				? ( i18n.resolveGoogleAds || 'Resolve Google Ads mapping' )
				: ( i18n.resolveField || 'Resolve' );
			$footer.append( $( '<button>', {
				type: 'button',
				class: 'button button-secondary rwgc-condition-card__resolve',
				text: resolveLabel,
				'data-card-action': 'open_resolver',
				'data-card': idx,
				'data-field': field,
				'data-raw': resolutionMeta.raw,
			} ) );
			$cc.append( $footer );
		} else if ( resolution ) {
			$cc.append( $( '<div>', { class: 'rwgc-condition-card__actions' } ).append( $( '<button>', {
				type: 'button',
				class: 'button-link rwgc-geo-card__undo',
				text: i18n.cardUndo || 'Undo',
				'data-card-action': 'undo_field',
				'data-card': idx,
				'data-field': field,
				'data-raw': resolutionMeta.raw,
			} ) ) );
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
			var targetReq = ( card.requiredResolutions || [] ).find( function ( r ) {
				return r.field === 'target';
			} );
			var targetActions = targetReq && targetReq.actions && targetReq.actions.length
				? targetReq.actions.filter( function ( act ) {
					return act !== 'search_popups';
				} )
				: ( t.type === 'popup'
					? [ 'resolve_popup', 'remove_action' ]
					: [ 'choose_target', 'search_targets', 'remove_action' ] );
			$card.append( fieldBlock( idx, {
				field: 'target',
				label: i18n.cardTargetLabel || 'Target',
				value: targetValue,
				status: t.status,
				resolved: t.resolved ? t.resolved.name : '',
				suggestions: t.suggestions || [],
				actions: requiresField( card, 'target' ) ? targetActions : [],
				isPopup: t.type === 'popup',
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

		if ( card.confirmation_instruction && card.confirmation_instruction.requires_confirmation ) {
			var $conf = $( '<div>', { class: 'rwgc-action-card__section rwgc-action-card__confirmation' } );
			$conf.append( $( '<p>', { class: 'rwgc-action-card__section-label' } ).text( i18n.confirmationLabel || 'Confirmation' ) );
			$conf.append( $( '<p>', { class: 'rwgc-action-card__confirmation-detail' } ).text(
				card.confirmation_instruction.detail || ( i18n.confirmationDetail || 'Manual confirmation required before execution.' )
			) );
			$card.append( $conf );
		}

		$card.append( renderLogicSection( card, idx ) );

		var $rows = renderConditionRows( card, idx );
		if ( $rows ) {
			$card.append( $rows );
		}

		( card.warnings || [] ).forEach( function ( w ) {
			$card.append( $( '<p>', { class: 'rwgc-geo-card__warning' } ).text( w ) );
		} );

		var canCreate = remaining === 0
			&& responseCanExecute( state.proposal )
			&& ! waitingForSharedTarget( state.proposal, idx, card );
		var $footer = $( '<div>', { class: 'rwgc-action-card__footer' } );
		var $footerLeft = $( '<div>', { class: 'rwgc-action-card__footer-left' } );
		var $footerRight = $( '<div>', { class: 'rwgc-action-card__footer-right' } );
		$footerLeft.append( $( '<button>', {
			type: 'button',
			class: 'rwgc-link-button rwgc-link-button--danger',
			text: i18n.cardRemoveAction || 'Remove action',
			'data-card-action': 'remove_action',
			'data-card': idx,
		} ) );
		if ( canCreate ) {
			$footerRight.append( $( '<button>', {
				type: 'button',
				class: 'rwgc-button rwgc-button--secondary',
				text: i18n.editRule || 'Edit rule',
				'data-card-action': 'review_items',
				'data-card': idx,
			} ) );
			$footerRight.append( $( '<button>', {
				type: 'button',
				class: 'rwgc-button rwgc-button--primary',
				text: primaryCreateRuleLabel( state.proposal ),
				'data-card-action': 'create_setup',
				'data-card': idx,
			} ) );
		}
		$footer.append( $footerLeft, $footerRight );
		$card.append( $footer );

		return $card;
	}

	function renderActionCards( proposal, $plan ) {
		var cards = proposal.action_cards || [];

		var $head = $( '<header>', { class: 'rwgc-geo-review__head rwgc-action-review', id: 'rwgc-action-review' } );
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
		var tres = fieldResolution( idx, 'target', targetFieldRaw( card, idx ) );
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
			if ( remaining > 0 && ( needs.length || ready.length ) ) {
				var $hubLists = $( '<div>', { class: 'rwgc-geo-rail__hub-lists' } );
				if ( needs.length ) {
					var $needs = $( '<div>', { class: 'rwgc-geo-rail__hub-section rwgc-resolution-list' } );
					$needs.append( $( '<p>', { class: 'rwgc-geo-rail__hub-label' } ).text( i18n.hubNeedsResolution || 'Needs resolution' ) );
					var $needsUl = $( '<ul>', { class: 'rwgc-geo-rail__hub-items' } );
					needs.forEach( function ( label ) {
						$needsUl.append( $( '<li>' ).text( label ) );
					} );
					$needs.append( $needsUl );
					$hubLists.append( $needs );
				}
				if ( ready.length ) {
					var $ready = $( '<div>', { class: 'rwgc-geo-rail__hub-section rwgc-resolution-list' } );
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

		if ( ! invalidSplit && remaining === 0 && cards.length === 1 ) {
			var summary = hubReadyRuleSummary( proposal );
			if ( summary ) {
				var $summary = $( '<div>', { class: 'rwgc-geo-rail__rule-summary' } );
				if ( summary.target ) {
					$summary.append( $( '<p>', { class: 'rwgc-geo-rail__hub-label' } ).text( i18n.hubRule || 'Rule' ) );
					$summary.append( $( '<p>', { class: 'rwgc-geo-rail__rule-target' } ).text( summary.target ) );
				}
				if ( summary.include.length ) {
					$summary.append( $( '<p>', { class: 'rwgc-geo-rail__hub-label' } ).text( i18n.includeConditions || 'Include' ) );
					var $inc = $( '<ul>', { class: 'rwgc-geo-rail__hub-items' } );
					summary.include.forEach( function ( line ) {
						$inc.append( $( '<li>' ).text( line ) );
					} );
					$summary.append( $inc );
				}
				if ( summary.exclude.length ) {
					$summary.append( $( '<p>', { class: 'rwgc-geo-rail__hub-label' } ).text( i18n.cardExclude || 'Exclude' ) );
					var $exc = $( '<ul>', { class: 'rwgc-geo-rail__hub-items' } );
					summary.exclude.forEach( function ( line ) {
						$exc.append( $( '<li>' ).text( line ) );
					} );
					$summary.append( $exc );
				}
				$rail.append( $summary );
			}
		} else if ( ! invalidSplit && cards.length > 1 ) {
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
					var cardSummary = resolvedSummary( card, idx );
					if ( cardSummary ) {
						$row.append( $( '<span>', { class: 'rwgc-geo-rail__action-summary' } ).text( cardSummary ) );
					}
				}
				$list.append( $row );
			} );
			$rail.append( $list );
		} else {
			renderSharedTargets( proposal, $rail, true );
		}

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
		} else if ( responseCanExecute( proposal ) ) {
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
		recalculateClientActionState();
		if ( state.proposal ) {
			updateSetupPanel( state.proposal, setupStatusLabel( state.lastResponse, state.proposal ) );
		}
	}

	function recalculateClientActionState() {
		if ( ! state.proposal || ! state.proposal.action_cards ) {
			return;
		}
		var remaining = remainingResolutions( state.proposal );
		state.proposal.fields_needing_attention = remaining;
		state.proposal.requires_resolution = remaining > 0;
		state.proposal.can_execute = remaining === 0 && ! isInvalidCreateRuleSplit( state.proposal );
		state.proposal.action_cards.forEach( function ( card, idx ) {
			if ( isCardRemoved( idx ) ) {
				card.status = 'removed';
				card.can_execute = false;
				return;
			}
			var cardRemaining = remainingForCard( idx, card, state.proposal );
			card.status = cardRemaining > 0 ? 'needs_resolution' : 'ready';
			card.can_execute = cardRemaining === 0;
		} );
	}

	function collectPopupTargetResolution( card, idx ) {
		if ( card.uses_shared_target || ! card.target || card.target.type !== 'popup' ) {
			return null;
		}
		var resolved = popupTargetResolvedFromCard( card, idx );
		if ( ! resolved || ! resolved.res || resolved.res.kind === 'ignored' || ! resolved.res.id ) {
			return null;
		}
		var exportRaw = targetFieldRaw( card, idx ) || resolved.raw || '';
		return {
			card: idx,
			field: 'target',
			type: 'popup',
			raw: exportRaw,
			action: 'choose',
			id: resolved.res.id,
			label: resolved.res.label || '',
			value: resolved.res.id,
			status: 'valid',
		};
	}

	function executePayloadTargetMismatches( proposal ) {
		var mismatches = [];
		var collected = collectCardResolutions();
		( ( proposal && proposal.action_cards ) || [] ).forEach( function ( card, idx ) {
			if ( isCardRemoved( idx ) || ! card.target || card.target.type !== 'popup' ) {
				return;
			}
			var id = ( card.target.resolved && card.target.resolved.id ) || card.target.id || '';
			var visuallyResolved = ( card.target.status === 'matched' || card.target.status === 'valid' ) && !! id;
			if ( ! visuallyResolved ) {
				return;
			}
			var exported = collected.some( function ( row ) {
				return row.card === idx && row.field === 'target' && row.id;
			} );
			if ( ! exported ) {
				mismatches.push( {
					card: idx,
					field: 'target',
					raw: targetFieldRaw( card, idx ),
					label: i18n.hubNeedPopup || 'Popup target',
					selectedLabel: ( card.target.resolved && card.target.resolved.name ) || card.target.label || '',
				} );
			}
		} );
		return mismatches;
	}

	function showTargetExecuteMismatchMessage( items ) {
		var item = items && items.length ? items[ 0 ] : null;
		if ( ! item ) {
			return;
		}
		var $wrap = $( '<div>', { class: 'rwgc-targeting-assistant__execute-blocked rwgc-targeting-assistant__execute-mismatch' } );
		$wrap.append( $( '<p>' ).html( '<strong>' + esc( i18n.stateMismatchTitle || 'State mismatch' ) + '</strong>' ) );
		$wrap.append( $( '<p>' ).text( i18n.stateMismatchPopupBody || 'The popup target is selected in the UI but missing from the execute payload.' ) );
		$wrap.append( $( '<p>' ).html(
			'<strong>' + esc( i18n.selectedTargetLabel || 'Selected target:' ) + '</strong> '
			+ esc( item.selectedLabel || '—' )
		) );
		$wrap.append( $( '<p>' ).html(
			'<strong>' + esc( i18n.missingExecuteResolution || 'Missing execute resolution:' ) + '</strong> '
			+ esc( item.label || i18n.hubNeedPopup || 'Popup target' )
		) );
		var $actions = $( '<div>', { class: 'rwgc-targeting-assistant__execute-blocked-actions' } );
		$actions.append( $( '<button>', {
			type: 'button',
			class: 'button rwgc-geo-btn',
			text: i18n.resolvePopupTarget || 'Resolve popup target',
			'data-card-action': 'open_resolver',
			'data-card': item.card,
			'data-field': 'target',
			'data-raw': item.raw || '',
		} ) );
		$actions.append( $( '<button>', {
			type: 'button',
			class: 'button rwgc-geo-btn',
			text: i18n.showDebug || 'Show debug',
			'data-action': 'debug',
		} ) );
		$actions.append( $( '<button>', {
			type: 'button',
			class: 'button-link rwgc-geo-btn',
			text: i18n.cancel || 'Cancel',
			'data-action': 'cancel',
		} ) );
		$wrap.append( $actions );
		var $thread = $( '#rwgc-targeting-thread' );
		var $bubble = assistantBubble( '' );
		$bubble.find( '.rwgc-targeting-assistant__bubble-body' ).append( $wrap );
		$thread.append( $bubble );
		scrollThread();
	}

	function logExecutePayloadDebug( resolutions ) {
		if ( ! state.debug && ! state.executePayloadDebug ) {
			return;
		}
		console.log( '[Geo Assistant execute debug]', {
			proposalId: state.proposalId,
			cardResolutions: state.cardResolutions,
			collectedResolutions: resolutions,
			proposalCards: state.proposal && state.proposal.action_cards,
			actionCards: state.proposal && state.proposal.action_cards,
			payload: {
				proposal_id: state.proposalId,
				resolutions: resolutions,
			},
		} );
	}

	function collectCardResolutions() {
		var out = [];
		var seen = {};
		var cards = ( state.proposal && state.proposal.action_cards ) || [];
		cards.forEach( function ( card, idx ) {
			if ( isCardRemoved( idx ) ) {
				out.push( { card: idx, action: 'remove_action' } );
				return;
			}
			function pushResolution( field, preferredRaw ) {
				var match = findFieldResolution( idx, field, preferredRaw, card );
				if ( ! match || ! match.res ) {
					return;
				}
				var dedupe = idx + '|' + field + '|' + ( match.raw || '' );
				if ( seen[ dedupe ] ) {
					return;
				}
				seen[ dedupe ] = true;
				var row = {
					card: idx,
					field: field,
					raw: match.raw || preferredRaw || '',
					action: match.res.kind === 'ignored' ? 'ignore' : 'choose',
					id: match.res.id || '',
					label: match.res.label || '',
				};
				if ( field === 'target' && card.target && card.target.type === 'popup' && match.res.id ) {
					row.type = 'popup';
					row.value = match.res.id;
					row.status = 'valid';
				}
				out.push( row );
			}
			( ( card && card.requiredResolutions ) || [] ).forEach( function ( req ) {
				pushResolution( req.field, req.raw );
			} );
			( card.condition_rows || [] ).forEach( function ( row ) {
				if ( row.type === 'condition_group' ) {
					var meta = conditionGroupResolution( row );
					if ( meta.field ) {
						pushResolution( meta.field, meta.raw );
					}
					( row.children || [] ).forEach( function ( child ) {
						if ( child.type !== 'url' && child.type !== 'page_url' ) {
							return;
						}
						pushResolution( 'url_match', child.label || child.raw || '' );
					} );
					return;
				}
				var rowField = conditionResolutionField( row.type );
				if ( rowField ) {
					pushResolution( rowField, row.raw || row.label || '' );
				}
			} );
			if ( ! card.uses_shared_target && card.target && card.target.status
				&& 'matched' !== card.target.status && 'ignored' !== card.target.status ) {
				pushResolution( 'target', card.target.raw || card.target.label || '' );
			}
			var popupTargetRow = collectPopupTargetResolution( card, idx );
			if ( popupTargetRow ) {
				var popupDedupe = idx + '|target|' + popupTargetRow.raw;
				if ( ! seen[ popupDedupe ] ) {
					seen[ popupDedupe ] = true;
					out.push( popupTargetRow );
				}
			}
			cardResolutionEntries( idx ).forEach( function ( entry ) {
				pushResolution( entry.field, entry.raw );
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

		if ( 'open_resolver' === action ) {
			openResolverForField( idx, field, raw );
		} else if ( 'choose_condition' === action ) {
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
			syncProposalPayload();
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
			var card = ( state.proposal && state.proposal.action_cards ) ? state.proposal.action_cards[ idx ] : null;
			if ( field === 'target' && card && card.target && card.target.type === 'popup' ) {
				openPopupTargetResolver( idx, raw, 'confirm_remove' );
				return;
			}
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
			if ( 'resolve_items' === action && openFirstUnresolvedDrawer() ) {
				return;
			}
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
		syncProposalPayload();
		var targetMismatch = executePayloadTargetMismatches( state.proposal );
		if ( targetMismatch.length ) {
			showTargetExecuteMismatchMessage( targetMismatch );
			jumpToActionReview();
			return;
		}
		var unresolved = unresolvedExecuteItems( state.proposal );
		if ( unresolved.length ) {
			showExecuteBlockedMessage( unresolved );
			jumpToActionReview();
			openFirstUnresolvedDrawer();
			return;
		}
		if ( ! responseCanExecute( state.proposal ) ) {
			var mismatch = executePayloadTargetMismatches( state.proposal );
			if ( mismatch.length ) {
				showTargetExecuteMismatchMessage( mismatch );
			} else if ( state.debug ) {
				appendAssistant( esc( i18n.stateMismatchWarning || 'State mismatch: visible action is ready but execute payload still has unresolved fields.' ) );
			}
			jumpToActionReview();
			openFirstUnresolvedDrawer();
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
		if ( proposal && proposal.action_cards && proposal.action_cards.length ) {
			return remainingResolutions( proposal ) === 0;
		}
		if ( proposal && ( proposal.requires_resolution || proposal.interpretation_status === 'needs_resolution' ) ) {
			return false;
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
		var card = cards[ 0 ];
		var html = '';
		var t = card.target || {};
		html += '<p><strong>' + esc( i18n.cardTargetLabel || 'Target' ) + '</strong><br>' + esc( ( t.resolved && t.resolved.name ) || t.raw || '—' ) + '</p>';

		var include = [];
		var exclude = [];
		( card.condition_rows || [] ).forEach( function ( row ) {
			if ( row.is_note ) {
				return;
			}
			if ( row.mode === 'exclude' ) {
				if ( row.label ) {
					exclude.push( row.label );
				}
			} else if ( row.type === 'device' && row.label ) {
				include.push( ( row.label.toLowerCase().indexOf( 'visitor' ) >= 0 ) ? row.label : ( row.label + ' visitors' ) );
			} else if ( row.label ) {
				include.push( row.label );
			}
		} );
		if ( include.length ) {
			html += '<p><strong>' + esc( i18n.cardInclude || 'Include' ) + '</strong></p><ul class="rwgc-chat-breakdown">';
			include.forEach( function ( line ) {
				html += '<li>' + esc( line ) + '</li>';
			} );
			html += '</ul>';
		}
		if ( exclude.length ) {
			html += '<p><strong>' + esc( i18n.cardExclude || 'Exclude' ) + '</strong></p><ul class="rwgc-chat-breakdown">';
			exclude.forEach( function ( line ) {
				html += '<li>' + esc( line ) + '</li>';
			} );
			html += '</ul>';
		}

		var needs = hubNeedsLabels( proposal );
		if ( needs.length ) {
			html += '<p><strong>' + esc( i18n.hubNeedsResolution || 'Needs resolution' ) + '</strong></p><ul class="rwgc-chat-breakdown">';
			needs.forEach( function ( label ) {
				html += '<li>' + esc( label ) + '</li>';
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
			var line = ( i18n.foundRulePrefix || 'I found' ) + ' ' + count + ' ' +
				( count === 1 ? ( i18n.ruleWord || 'rule' ) : ( i18n.rulesWord || 'rules' ) ) + ' ' + ( i18n.toCreateWord || 'to create' ) + '.';
			if ( attention > 0 && count !== 1 ) {
				line = ( i18n.foundActionsPrefix || 'I found' ) + ' ' + count + ' ' +
					( count === 1 ? ( i18n.actionWord2 || 'action' ) : ( i18n.actionsWord2 || 'actions' ) ) + '. ' +
					attention + ' ' +
					( attention === 1 ? ( i18n.fieldNeedsAttention || 'field needs attention' ) : ( i18n.fieldsNeedAttention || 'fields need attention' ) ) +
					' ' + ( i18n.beforeCreate || 'before this can be created.' );
			}
			var cardHtml = sourceBadgeHtml( response.source );
			cardHtml += '<p><strong>' + esc( line ) + '</strong></p>';
			cardHtml += chatActionBreakdown( proposal );
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
		autoMatchPopupTargets( state.proposal );
		recalculateClientActionState();
		updateSetupPanel( state.proposal, setupStatusLabel( response, state.proposal ) );
		appendAssistant( formatProposalHtml( response ), proposalActions( state.proposalId, response ) );
		if ( state.proposal && state.proposal.action_cards && state.proposal.action_cards.length ) {
			window.setTimeout( function () {
				jumpToActionReview();
			}, 120 );
		}
	}

	function buildContext() {
		var ctx = { screen: 'targeting_assistant' };
		var popups = popupTargetRegistry();
		if ( popups.length ) {
			ctx.targets = { popups: popups };
		}
		return ctx;
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
		syncProposalPayload();
		var targetMismatch = executePayloadTargetMismatches( state.proposal );
		if ( targetMismatch.length ) {
			showTargetExecuteMismatchMessage( targetMismatch );
			jumpToActionReview();
			return;
		}
		var preflight = unresolvedExecuteItems( state.proposal );
		if ( preflight.length ) {
			showExecuteBlockedMessage( preflight );
			jumpToActionReview();
			openFirstUnresolvedDrawer();
			return;
		}
		if ( ! responseCanExecute( state.proposal ) ) {
			jumpToActionReview();
			openFirstUnresolvedDrawer();
			return;
		}
		if ( ! state.proposalId || ! cfg.executeUrl ) {
			goWorkflowFromProposal();
			return;
		}
		var resolutions = collectCardResolutions();
		logExecutePayloadDebug( resolutions );
		var $rail = $( '#rwgc-targeting-rail' );
		$rail.find( '.rwgc-geo-rail__cta .button-primary' ).prop( 'disabled', true ).addClass( 'is-busy' );
		apiPost( cfg.executeUrl, { proposal_id: state.proposalId, resolutions: resolutions } )
			.done( function ( response ) {
				recordLearningFeedback( 'executed', {
					correction: {
						resolved_fields: collectCardResolutions(),
						context: 'create_rule_journey',
					},
				} );
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
				var serverMsg = ( jqxhr.responseJSON && jqxhr.responseJSON.message ) || '';
				if ( data.requires_resolution && data.action_cards ) {
					if ( state.proposal ) {
						state.proposal.action_cards = data.action_cards;
						state.proposal.fields_needing_attention = data.fields_needing_attention || 0;
						state.proposal.requires_resolution = true;
						state.proposal.can_execute = false;
					}
					var blocked = [];
					if ( data.unresolved_details && data.unresolved_details.length ) {
						data.unresolved_details.forEach( function ( row, i ) {
							blocked.push( {
								card: typeof row.card === 'number' ? row.card : 0,
								field: row.key === 'google_ads_mapping' ? 'traffic_source' : ( row.key || row.field || '' ),
								raw: row.raw || '',
								key: row.key || row.field || '',
								label: row.label || '',
							} );
						} );
					} else if ( data.unresolved && data.unresolved.length ) {
						data.unresolved.forEach( function ( label ) {
							var field = /google ads/i.test( label ) ? 'traffic_source' : 'target';
							blocked.push( {
								card: 0,
								field: field,
								raw: trafficResolutionRaw( state.proposal.action_cards[ 0 ], 0 ) || '',
								key: field === 'traffic_source' ? 'google_ads_mapping' : field,
								label: label,
							} );
						} );
					}
					if ( blocked.length ) {
						showExecuteBlockedMessage( blocked, serverMsg );
					} else {
						appendAssistant( esc( serverMsg || i18n.cardResolveRemaining || 'Some fields still need resolving.' ) );
					}
					updateSetupPanel( state.proposal, i18n.statusNeedsResolution || 'Needs resolution' );
					return;
				}
				showExecuteFailedMessage( serverMsg, jqxhr );
				jumpToActionReview();
			} )
			.always( function () {
				$rail.find( '.rwgc-geo-rail__cta .button-primary' ).prop( 'disabled', false ).removeClass( 'is-busy' );
			} );
	}

	function renderExecutionSummary( result ) {
		var $plan = $( '#rwgc-targeting-setup-plan' );
		var $rail = $( '#rwgc-targeting-rail' );
		$( '#rwgc-targeting-setup-empty' ).addClass( 'rwgc-is-hidden' );
		$( '#rwgc-targeting-setup-hint' ).addClass( 'rwgc-is-hidden' );
		$plan.removeClass( 'rwgc-is-hidden' ).empty();

		var created = result.created_rules || [];
		var $wrap = $( '<div>', { class: 'rwgc-geo-result' } );
		$wrap.append( $( '<p>', { class: 'rwgc-geo-result__headline' } ).text( i18n.ruleCreated || 'Rule created.' ) );
		$wrap.append( $( '<p>', { class: 'rwgc-geo-result__message' } ).text( result.message || '' ) );

		created.forEach( function ( rule ) {
			var $row = $( '<div>', { class: 'rwgc-geo-result__row rwgc-geo-result__row--ok' } );
			if ( rule.edit_url ) {
				$row.append( $( '<a>', { href: rule.edit_url, target: '_blank', rel: 'noopener', class: 'rwgc-geo-result__rule-link' } ).text( rule.title || ( 'Rule #' + rule.id ) ) );
			} else {
				$row.append( document.createTextNode( rule.title || ( 'Rule #' + rule.id ) ) );
			}
			( rule.warnings || [] ).forEach( function ( w ) {
				$row.append( $( '<span>', { class: 'rwgc-geo-result__warn' } ).text( w ) );
			} );
			$wrap.append( $row );
		} );

		if ( created.length && state.proposal && state.proposal.action_cards && state.proposal.action_cards[ 0 ] ) {
			var card = state.proposal.action_cards[ 0 ];
			var createdLines = [];
			( card.condition_rows || [] ).forEach( function ( row ) {
				if ( row.is_note || ! row.label ) {
					return;
				}
				if ( row.mode === 'exclude' ) {
					createdLines.push( ( i18n.cardExclude || 'Exclude' ) + ' ' + row.label );
				} else {
					createdLines.push( row.label );
				}
			} );
			if ( createdLines.length ) {
				var $conds = $( '<ul>', { class: 'rwgc-geo-result__conditions' } );
				createdLines.forEach( function ( line ) {
					$conds.append( $( '<li>' ).text( line ) );
				} );
				$wrap.append( $( '<p>', { class: 'rwgc-geo-result__label' } ).text( i18n.createdConditions || 'Created conditions:' ) );
				$wrap.append( $conds );
			}
		}

		var $actions = $( '<div>', { class: 'rwgc-geo-result__actions' } );
		if ( created[ 0 ] && created[ 0 ].edit_url ) {
			$actions.append( $( '<a>', { href: created[ 0 ].edit_url, class: 'button button-primary', target: '_blank', rel: 'noopener' } ).text( i18n.viewRule || 'View rule' ) );
		}
		var targetRes = state.proposal && state.proposal.action_cards && state.proposal.action_cards[ 0 ]
			? fieldResolution( 0, 'target', ( state.proposal.action_cards[ 0 ].target || {} ).raw || '' )
			: null;
		if ( targetRes && targetRes.id ) {
			$actions.append( $( '<a>', {
				href: 'post.php?post=' + encodeURIComponent( targetRes.id ) + '&action=edit',
				class: 'button',
				target: '_blank',
				rel: 'noopener',
			} ).text( i18n.openTarget || 'Open target' ) );
		}
		$actions.append( $( '<button>', { type: 'button', class: 'button', text: i18n.createAnotherRule || 'Create another rule', 'data-action': 'cancel' } ) );
		$wrap.append( $actions );

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

		if ( created.length ) {
			$rail.empty();
			$rail.append( $( '<h3>', { class: 'rwgc-geo-rail__title' } ).text( i18n.resolutionHub || 'Resolution Hub' ) );
			$rail.append( $( '<p>', { class: 'rwgc-geo-rail__count is-ok' } ).text( '1 ' + ( i18n.actionCreated || 'action created' ) ) );
			if ( created[ 0 ].edit_url ) {
				$rail.append( $( '<p>', { class: 'rwgc-geo-rail__cta' } ).append(
					$( '<a>', { href: created[ 0 ].edit_url, class: 'button button-primary', target: '_blank', rel: 'noopener' } ).text( i18n.viewRule || 'View rule' )
				) );
			}
			$rail.removeClass( 'rwgc-is-hidden' );
		}

		$( '#rwgc-targeting-summary' ).removeClass( 'rwgc-is-hidden' );
		var chatHtml = '<p><strong>' + esc( i18n.ruleCreated || 'Rule created.' ) + '</strong></p>';
		if ( created[ 0 ] ) {
			chatHtml += '<p>' + esc( created[ 0 ].title || '' ) + '</p>';
		}
		appendAssistant( chatHtml );
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
		state.executePayloadDebug = true;
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

		$( 'body' ).on( 'click', '#rwgc-resolution-drawer.rwgc-modal-overlay', function ( e ) {
			if ( e.target === this ) {
				closeResolutionDrawer();
			}
		} ).on( 'click', '#rwgc-resolution-drawer [data-drawer-action]', function () {
			if ( state.popupResolver ) {
				return;
			}
			var action = $( this ).data( 'drawer-action' );
			if ( 'apply' === action ) {
				applyResolutionDrawer();
			} else if ( 'apply_url_match' === action ) {
				applyUrlMatchDrawer();
			} else if ( 'back_to_google_ads' === action ) {
				returnToGoogleAdsDrawer();
			} else if ( 'edit_url_match' === action ) {
				var drawer = state.resolutionDrawer;
				if ( ! drawer || ! drawer.conditionRow ) {
					return;
				}
				var childLabel = String( $( this ).data( 'url-child-label' ) || '' );
				var urlChild = null;
				validUrlChildrenFromRow( drawer.conditionRow ).forEach( function ( child ) {
					if ( ( child.label || child.raw || '' ) === childLabel ) {
						urlChild = child;
					}
				} );
				if ( ! urlChild ) {
					return;
				}
				var savedOpts = $.extend( {}, drawer.drawerOpts || {}, {
					preservedSelection: {
						selected: drawer.selected,
						customMapping: drawer.customMapping || '',
					},
				} );
				var $drawerEl = ensureResolutionDrawer();
				var $panel = $drawerEl.find( '.rwgc-resolution-drawer__panel' );
				$panel.empty().removeClass( 'rwgc-google-ads-resolver' ).addClass( 'rwgc-url-match-resolver' );
				renderUrlMatchDrawer( $panel, {
					card: drawer.card,
					urlChild: urlChild,
					returnToGoogleAds: true,
					googleAdsOpts: savedOpts,
				} );
				openResolutionDrawerShell();
			} else if ( 'remove_google_ads' === action ) {
				if ( ! state.resolutionDrawer ) {
					return;
				}
				state.resolutionDrawer.selected = 'remove_google_ads_condition';
				applyResolutionDrawer();
			} else if ( 'cancel' === action ) {
				closeResolutionDrawer();
			}
		} ).on( 'change', '#rwgc-resolution-drawer input[name="rwgc-mapping-choice"]', function () {
			var drawer = state.resolutionDrawer;
			if ( ! drawer ) {
				return;
			}
			var key = String( $( this ).val() || '' );
			drawer.selected = key;
			$( '#rwgc-resolution-drawer .rwgc-mapping-option' ).removeClass( 'rwgc-mapping-option--selected' );
			$( this ).closest( '.rwgc-mapping-option' ).addClass( 'rwgc-mapping-option--selected' );
			var $custom = $( '#rwgc-resolution-drawer .rwgc-mapping-custom' );
			if ( key === 'configure_google_ads_mapping' ) {
				$custom.removeClass( 'rwgc-is-hidden' );
			} else {
				$custom.addClass( 'rwgc-is-hidden' );
				drawer.customMapping = '';
				$custom.find( '.rwgc-mapping-custom__input' ).val( '' );
			}
			updateResolutionDrawerApplyButton();
		} ).on( 'change', '#rwgc-resolution-drawer input[name="rwgc-url-match-type"]', function () {
			var drawer = state.resolutionDrawer;
			if ( ! drawer || drawer.mode !== 'url_match' ) {
				return;
			}
			drawer.matchType = String( $( this ).val() || 'contains' );
			var $drawerEl = ensureResolutionDrawer();
			var $panel = $drawerEl.find( '.rwgc-resolution-drawer__panel' );
			$panel.empty().addClass( 'rwgc-url-match-resolver' );
			renderUrlMatchDrawer( $panel, {
				card: drawer.card,
				urlChild: drawer.urlChild,
				matchType: drawer.matchType,
				path: drawer.path,
				returnToGoogleAds: drawer.returnToGoogleAds,
				googleAdsOpts: drawer.googleAdsOpts,
			} );
			openResolutionDrawerShell();
		} ).on( 'input', '#rwgc-resolution-drawer [data-drawer-field="url_path"]', function () {
			if ( ! state.resolutionDrawer || state.resolutionDrawer.mode !== 'url_match' ) {
				return;
			}
			state.resolutionDrawer.path = String( $( this ).val() || '' );
		} ).on( 'input', '#rwgc-resolution-drawer [data-drawer-field="custom_mapping"]', function () {
			if ( ! state.resolutionDrawer ) {
				return;
			}
			state.resolutionDrawer.customMapping = String( $( this ).val() || '' );
			updateResolutionDrawerApplyButton();
		} ).on( 'click', '#rwgc-resolution-drawer [data-popup-action]', function () {
			handlePopupResolverAction( String( $( this ).data( 'popup-action' ) || '' ) );
		} ).on( 'input', '#rwgc-resolution-drawer .rwgc-popup-search-input', function () {
			schedulePopupSearch( String( $( this ).val() || '' ) );
		} ).on( 'change', '#rwgc-resolution-drawer [data-popup-field]', function () {
			var pr = state.popupResolver;
			if ( ! pr ) {
				return;
			}
			var field = String( $( this ).data( 'popup-field' ) || '' );
			if ( 'create_name' === field ) {
				pr.createName = String( $( this ).val() || '' );
			} else if ( 'create_status' === field ) {
				pr.createStatus = String( $( this ).val() || 'draft' );
			} else if ( 'attach_action' === field ) {
				pr.attachToAction = $( this ).is( ':checked' );
			}
		} ).on( 'change', '#rwgc-resolution-drawer input[name="rwgc-popup-choice"]', function () {
			var pr = state.popupResolver;
			if ( ! pr ) {
				return;
			}
			pr.selectedPopupId = String( $( this ).val() || '' );
			$( '#rwgc-resolution-drawer .rwgc-popup-resolver__row' ).removeClass( 'is-selected' );
			$( this ).closest( '.rwgc-popup-resolver__row' ).addClass( 'is-selected' );
			$( '#rwgc-resolution-drawer [data-popup-action="submit_choose"]' ).prop( 'disabled', ! pr.selectedPopupId );
		} ).on( 'click', '#rwgc-resolution-drawer [data-option-key]', function () {
			var key = String( $( this ).data( 'option-key' ) || '' );
			if ( ! state.resolutionDrawer ) {
				return;
			}
			state.resolutionDrawer.selected = key;
			$( '#rwgc-resolution-drawer .rwgc-resolution-option' ).removeClass( 'is-selected' );
			$( this ).addClass( 'is-selected' );
		} ).on( 'click', '[data-popup-toast-action="dismiss"]', function () {
			$( '#rwgc-popup-create-toast' ).addClass( 'rwgc-is-hidden' );
		} );

		$( document ).on( 'keydown.rwgcModal', function ( e ) {
			if ( 27 !== e.which && 'Escape' !== e.key ) {
				return;
			}
			var $drawer = $( '#rwgc-resolution-drawer' );
			if ( $drawer.length && ! $drawer.hasClass( 'rwgc-is-hidden' ) ) {
				e.preventDefault();
				closeResolutionDrawer();
			}
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
