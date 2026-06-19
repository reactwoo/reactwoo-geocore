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
		previewTimer: null,
		previewSeq: 0,
		sendSeq: 0,
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
		if ( response && response.status === 'needs_clarification' ) {
			return i18n.statusNeedsConfirmation || 'Needs confirmation';
		}
		if ( proposal && proposal.can_execute === false ) {
			return i18n.statusNeedsConfirmation || 'Needs confirmation';
		}
		return i18n.statusPending || 'Pending confirmation';
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

		var title = i18n.setupPlan || 'Targeting plan';
		if ( proposal.intent === 'create_geo_variant_plan' ) {
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
			choose_split: i18n.chooseSplit || 'Choose split',
			edit_manually: i18n.editManually || 'Edit manually',
		};
		return map[ key ] || fallback || key;
	}

	function proposalActions( proposalId, response ) {
		response = response || state.lastResponse || {};
		var proposal = ( response.proposal || state.proposal || {} );
		var actions = response.actions || [];
		var $wrap = $( '<div>', { class: 'rwgc-targeting-assistant__actions' } );
		var hasInferred = !!( response.inferred_plan || proposal.inferred_plan );
		var ready = responseCanExecute( proposal );

		if ( actions.length ) {
			actions.forEach( function ( row ) {
				var key = row.key || '';
				if ( 'confirm' === key && ! ready ) {
					return;
				}
				var primary = ( 'confirm' === key || ( 'use_split' === key && hasInferred ) );
				if ( 'use_split' === key && ! hasInferred ) {
					return;
				}
				if ( 'clarify' === key ) {
					key = hasInferred ? 'edit_split' : 'choose_split';
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
			if ( $wrap.children().length ) {
				$wrap.data( 'proposal-id', proposalId );
				return $wrap;
			}
		}
		if ( ! actions.length || ! $wrap.children().length ) {
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
		if ( ! proposal ) {
			return false;
		}
		if ( typeof proposal.can_execute === 'boolean' ) {
			return proposal.can_execute;
		}
		if ( typeof proposal.proposal_ready === 'boolean' ) {
			return proposal.proposal_ready;
		}
		return false;
	}

	function formatProposalHtml( response ) {
		var proposal = response.proposal || {};
		var message = response.message || proposal.summary || '';
		var html = '<p><strong>' + esc( message.split( '\n\n' )[0] || message ) + '</strong></p>';
		var inferred = response.inferred_plan || proposal.inferred_plan;
		if ( inferred && response.status === 'needs_clarification' ) {
			html += renderInferredPlanHtml( inferred );
		} else if ( inferred ) {
			html += renderInferredPlanHtml( inferred );
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
		if ( typeof response.can_execute === 'boolean' && state.proposal ) {
			state.proposal.can_execute = response.can_execute;
		}
		if ( response.inferred_plan && state.proposal ) {
			state.proposal.inferred_plan = response.inferred_plan;
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
			approved_by_user: outcome === 'executed' || outcome === 'accepted' || outcome === 'accepted_inferred_split' || outcome === 'accepted_ai_split',
			interpretation_source: extra.source || proposal.interpretation_source || ( state.lastResponse && state.lastResponse.source ) || '',
		};
		if ( extra.correction ) {
			payload.correction = extra.correction;
		}
		if ( proposal.inferred_plan || ( state.lastResponse && state.lastResponse.inferred_plan ) ) {
			payload.inferred_plan = extra.inferred_plan || proposal.inferred_plan || state.lastResponse.inferred_plan;
		}
		apiPost( cfg.learningEventUrl, payload );
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
			if ( response && response.success ) {
				recordLearningFeedback( 'accepted_inferred_split', {
					source: source,
					params: inferred,
					inferred_plan: inferred,
				} );
				applyInterpretResponse( response );
			} else {
				showExecutionError( executionErrorMessage( response ) );
			}
		} ).fail( function ( xhr ) {
			showExecutionError( executionErrorMessage( xhr ) );
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
			showExecutionError( i18n.proposalNotReady || i18n.executionFailed || 'This setup needs confirmation before it can be created.' );
			return;
		}
		if ( ! state.proposalId || ! cfg.executeUrl ) {
			showExecutionError( i18n.executionFailed || 'Setup could not be created. Please review the plan and try again.' );
			return;
		}
		apiPost( cfg.executeUrl, { proposal_id: state.proposalId } )
			.done( function ( response ) {
				if ( response && response.success === false ) {
					showExecutionError( executionErrorMessage( response ) );
					return;
				}
				recordLearningFeedback( 'executed' );
				var result = response && response.result ? response.result : {};
				if ( result.redirect_steps && result.redirect_steps.length ) {
					persistPortableAndGo( result.redirect_steps[0].url );
					return;
				}
				goWorkflowFromProposal();
				appendAssistant( esc( result.message || i18n.setupConfirmed || 'Setup confirmed.' ) );
				updateSetupPanel( state.proposal, i18n.statusConfirmed || 'Confirmed' );
			} )
			.fail( function ( xhr ) {
				showExecutionError( executionErrorMessage( xhr ) );
			} );
	}

	function executionErrorMessage( response ) {
		if ( response && response.responseJSON && response.responseJSON.message ) {
			return response.responseJSON.message;
		}
		if ( response && response.message ) {
			return response.message;
		}
		return i18n.executionFailed || 'Setup could not be created. Please review the plan and try again.';
	}

	function showExecutionError( message ) {
		appendAssistant( '<p>' + esc( message ) + '</p>' );
		updateSetupPanel( state.proposal, i18n.statusNeedsConfirmation || 'Needs confirmation' );
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

	function showEditPanel() {
		var proposal = state.proposal;
		if ( ! proposal ) {
			return;
		}
		var $fields = $( '#rwgc-targeting-edit-fields' ).empty();
		var $page = $( '<select>', { id: 'rwgc-edit-page', class: 'widefat' } );
		$page.append( $( '<option>', { value: '', text: i18n.choosePage || 'Choose a page…' } ) );
		( cfg.pages || [] ).forEach( function ( p ) {
			$page.append( $( '<option>', { value: p.id, text: p.title } ) );
		} );
		if ( proposal.resolved_target && proposal.resolved_target.id ) {
			$page.val( String( proposal.resolved_target.id ) );
		}
		$fields.append( $( '<label>' ).text( i18n.pageLabel || 'Page' ).attr( 'for', 'rwgc-edit-page' ), $page );
		$( '#rwgc-targeting-edit-panel' ).removeClass( 'rwgc-is-hidden' ).prop( 'hidden', false );
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
			} else if ( 'use_split' === action ) {
				confirmInferredSplit();
			} else if ( 'ask_ai' === action ) {
				askAiToCheck();
			} else if ( 'choose_split' === action || 'clarify' === action ) {
				askAiToCheck();
			} else if ( 'edit' === action || 'edit_split' === action || 'edit_manually' === action ) {
				showEditPanel();
			} else if ( 'debug' === action ) {
				showDebug();
			} else if ( 'cancel' === action ) {
				recordLearningFeedback( 'rejected' );
				start();
			}
		} );

		$( '#rwgc-targeting-debug-close, #rwgc-targeting-edit-cancel' ).on( 'click', function () {
			$( '#rwgc-targeting-debug-panel, #rwgc-targeting-edit-panel' ).addClass( 'rwgc-is-hidden' ).prop( 'hidden', true );
		} );

		$( '#rwgc-targeting-edit-save' ).on( 'click', function () {
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
