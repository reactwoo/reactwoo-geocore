/**
 * Chat-style Targeting Assistant — conversation + natural-language composer.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.rwgcTargetingAssistant || { capabilities: {}, i18n: {}, pages: [], countries: [] };
	var i18n = cfg.i18n || {};
	var state = {
		journey: '',
		pageId: 0,
		pageTitle: '',
		type: '',
		typeLabel: '',
		country: '',
		countryLabel: '',
		device: '',
		deviceLabel: '',
		method: '',
		methodLabel: '',
		proposal: null,
	};

	function escapeHtml( value ) {
		return $( '<div>' ).text( value === undefined || value === null ? '' : String( value ) ).html();
	}

	function assistantBubble( text, allowHtml ) {
		var $bubble = $( '<div>', {
			class: 'rwgc-targeting-assistant__bubble rwgc-targeting-assistant__bubble--assistant',
		} );
		var $text = $( '<span>', { class: 'rwgc-targeting-assistant__text' } );
		if ( allowHtml ) {
			$text.html( text );
		} else {
			$text.text( text );
		}
		$bubble.append(
			$( '<span>', {
				class: 'rwgc-targeting-assistant__who',
				text: i18n.assistantName || 'Geo Assistant',
			} ),
			$text
		);
		return $bubble;
	}

	function userBubble( text ) {
		return $( '<div>', {
			class: 'rwgc-targeting-assistant__bubble rwgc-targeting-assistant__bubble--user',
			text: text,
		} );
	}

	function shortBadge( text, tone ) {
		return $( '<span>', { class: 'rwgc-geo-badge rwgc-geo-badge--' + ( tone || 'neutral' ), text: text } );
	}

	function choiceBtn( label, badge, locked, value, badgeTone ) {
		var $btn = $( '<button>', {
			type: 'button',
			class: 'rwgc-targeting-assistant__choice' + ( locked ? ' is-locked' : '' ),
			'data-value': value,
		} );
		$btn.append( $( '<span>', { class: 'rwgc-targeting-assistant__choice-label', text: label } ) );
		if ( badge ) {
			$btn.append( shortBadge( badge, badgeTone || ( locked ? 'locked' : 'success' ) ) );
		}
		return $btn;
	}

	function clearStep() {
		$( '#rwgc-targeting-step' ).empty();
	}

	function appendAssistant( text, $controls ) {
		var $thread = $( '#rwgc-targeting-thread' );
		$thread.append( assistantBubble( text ) );
		clearStep();
		if ( $controls && $controls.length ) {
			$( '#rwgc-targeting-step' ).append( $controls );
		}
		scrollThread();
	}

	function appendAssistantHtml( html, $controls ) {
		var $thread = $( '#rwgc-targeting-thread' );
		$thread.append( assistantBubble( html, true ) );
		clearStep();
		if ( $controls && $controls.length ) {
			$( '#rwgc-targeting-step' ).append( $controls );
		}
		scrollThread();
	}

	function appendUser( text ) {
		$( '#rwgc-targeting-thread' ).append( userBubble( text ) );
		clearStep();
		scrollThread();
	}

	function scrollThread() {
		var el = document.getElementById( 'rwgc-targeting-thread' );
		if ( el ) {
			el.scrollTop = el.scrollHeight;
		}
	}

	function setSummary( key, val ) {
		var $dd = $( '#rwgc-targeting-summary dd[data-key="' + key + '"]' );
		var hasVal = val && '—' !== val;
		$dd.text( hasVal ? val : '—' ).toggleClass( 'is-empty', ! hasVal );
		if ( hasVal ) {
			$( '#rwgc-targeting-setup-empty' ).addClass( 'rwgc-is-hidden' );
			$( '#rwgc-targeting-summary' ).removeClass( 'rwgc-is-hidden' );
		}
	}

	function updateStatus( text ) {
		setSummary( 'status', text || i18n.statusReady || 'Ready' );
	}

	function showLock( title, body ) {
		$( '#rwgc-targeting-lock-title' ).text( title );
		$( '#rwgc-targeting-lock-body' ).text( body );
		$( '#rwgc-targeting-lock-panel' ).removeClass( 'rwgc-is-hidden' ).prop( 'hidden', false );
	}

	function hideLock() {
		$( '#rwgc-targeting-lock-panel' ).addClass( 'rwgc-is-hidden' ).prop( 'hidden', true );
	}

	function populateComposerSelects() {
		var $page = $( '#rwgc-composer-page' );
		var $country = $( '#rwgc-composer-country' );
		if ( ! $page.length ) {
			return;
		}
		( cfg.pages || [] ).forEach( function ( p ) {
			$page.append( $( '<option>', { value: p.id, text: p.title } ) );
		} );
		( cfg.countries || [] ).forEach( function ( c ) {
			$country.append( $( '<option>', { value: c.code, text: c.name } ) );
		} );
	}

	function countryLabelFromCode( code ) {
		var found = ( cfg.countries || [] ).filter( function ( c ) {
			return c.code === code;
		} );
		return found.length ? found[0].name : code;
	}

	function goalChoices() {
		var $c = $( '<div>', { class: 'rwgc-targeting-assistant__choices' } );
		$c.append( choiceBtn( i18n.goalVariant || 'Show a different page', i18n.included || 'Included', false, 'variant', 'success' ) );
		$c.append( choiceBtn( i18n.goalRule || 'Show or hide content', i18n.included || 'Included', false, 'rule', 'success' ) );
		var exp = cfg.capabilities.experiences || {};
		var locked = 'not_installed' === exp.state || 'locked' === exp.state;
		$c.append( choiceBtn( i18n.goalExperience || 'Create an Experience', 'Optimise', locked, 'experience', locked ? 'locked' : 'neutral' ) );
		return $c;
	}

	function typeChoices() {
		var $c = $( '<div>', { class: 'rwgc-targeting-assistant__choices' } );
		$c.append( choiceBtn( i18n.country || 'Country', i18n.included || 'Included', false, 'country', 'success' ) );
		[ 'variant_type_audience', 'variant_type_campaign', 'variant_type_weather', 'variant_type_time' ].forEach( function ( id ) {
			var cap = cfg.capabilities[ id ] || {};
			var ok = 'included' === cap.state || 'available' === cap.state;
			$c.append( choiceBtn( cap.label || id, 'Pro', ! ok, id, ok ? 'success' : 'locked' ) );
		} );
		return $c;
	}

	function pageSelect( promptText ) {
		var $wrap = $( '<div>', { class: 'rwgc-targeting-assistant__control' } );
		var $sel = $( '<select>', { class: 'rwgc-targeting-assistant__select', id: 'rwgc-assistant-page' } );
		$sel.append( $( '<option>', { value: '', text: i18n.choosePage || 'Choose a page…' } ) );
		( cfg.pages || [] ).forEach( function ( p ) {
			$sel.append( $( '<option>', { value: p.id, text: p.title } ) );
		} );
		if ( state.pageId ) {
			$sel.val( String( state.pageId ) );
		}
		var $btn = $( '<button>', { type: 'button', class: 'button button-primary rwgc-geo-btn', text: i18n.continue || 'Continue' } );
		$btn.on( 'click', function () {
			var id = parseInt( $sel.val(), 10 );
			if ( ! id ) {
				return;
			}
			var title = $sel.find( 'option:selected' ).text();
			state.pageId = id;
			state.pageTitle = title;
			setSummary( 'page', title );
			appendUser( title );
			afterPageResolved();
		} );
		$wrap.append( $sel, $btn );
		appendAssistant( promptText || i18n.whichPage || 'Which page should we create a version for?', $wrap );
	}

	function countrySelect() {
		var $wrap = $( '<div>', { class: 'rwgc-targeting-assistant__control' } );
		var $sel = $( '<select>', { class: 'rwgc-targeting-assistant__select', id: 'rwgc-assistant-country' } );
		$sel.append( $( '<option>', { value: '', text: i18n.chooseCountry || 'Choose a country…' } ) );
		( cfg.countries || [] ).forEach( function ( c ) {
			$sel.append( $( '<option>', { value: c.code, text: c.name } ) );
		} );
		if ( state.country ) {
			$sel.val( state.country );
		}
		var $btn = $( '<button>', { type: 'button', class: 'button button-primary rwgc-geo-btn', text: i18n.continue || 'Continue' } );
		$btn.on( 'click', function () {
			var code = $sel.val();
			if ( ! code ) {
				return;
			}
			state.country = code;
			state.countryLabel = $sel.find( 'option:selected' ).text();
			setSummary( 'condition', state.countryLabel );
			appendUser( state.countryLabel );
			appendAssistant( i18n.havePage || 'Do you already have a page for this country?', methodChoices() );
		} );
		$wrap.append( $sel, $btn );
		return $wrap;
	}

	function methodChoices() {
		var $c = $( '<div>', { class: 'rwgc-targeting-assistant__choices' } );
		$c.append( choiceBtn( i18n.methodExisting || 'Use existing page', '', false, 'existing' ) );
		$c.append( choiceBtn( i18n.methodDuplicate || 'Duplicate original page', '', false, 'duplicate' ) );
		$c.append( choiceBtn( i18n.methodBlank || 'Create blank page', '', false, 'blank' ) );
		return $c;
	}

	function formatConditionChip( cond ) {
		if ( ! cond || ! cond.type ) {
			return '';
		}
		var type = String( cond.type ).replace( /_/g, ' ' );
		var op = String( cond.operator || 'in' );
		var val = cond.value;
		var valText = '';
		if ( Array.isArray( val ) ) {
			valText = val.join( ', ' );
		} else if ( val === true || val === '1' || val === 1 ) {
			valText = i18n.loggedInYes || 'Yes';
		} else if ( val === false || val === '0' || val === 0 ) {
			valText = i18n.loggedInNo || 'No';
		} else if ( val !== undefined && val !== null ) {
			valText = String( val );
		}
		var prefix = ( op === 'not_in' || op === 'is_not' || op === 'not_contains' ) ? ( i18n.notPrefix || 'NOT ' ) : '';
		return prefix + type.toUpperCase() + ( valText ? ': ' + valText : '' );
	}

	function conditionListHtml( proposal ) {
		if ( ! proposal || ! proposal.conditions || ! proposal.conditions.length ) {
			return '';
		}
		var match = proposal.condition_match || 'all';
		var joinLabel = match === 'any' ? ( i18n.logicOr || 'OR' ) : ( i18n.logicAnd || 'AND' );
		var chips = [];
		proposal.conditions.forEach( function ( cond, idx ) {
			if ( idx > 0 ) {
				chips.push( '<span class="rwgc-targeting-assistant__logic">' + escapeHtml( joinLabel ) + '</span>' );
			}
			chips.push( '<span class="rwgc-targeting-assistant__cond-chip">' + escapeHtml( formatConditionChip( cond ) ) + '</span>' );
		} );
		return '<div class="rwgc-targeting-assistant__conditions" aria-label="' + escapeHtml( i18n.conditionsLabel || 'Targeting conditions' ) + '">' + chips.join( '' ) + '</div>';
	}

	function proposalListHtml( proposal ) {
		var params = proposal.params || {};
		var rows = [];
		if ( proposal.summary ) {
			rows.push( '<li>' + escapeHtml( proposal.summary ) + '</li>' );
		}
		if ( proposal.compound && proposal.conditions && proposal.conditions.length ) {
			rows.push( '<li>' + conditionListHtml( proposal ) + '</li>' );
		}
		if ( proposal.intent ) {
			rows.push( '<li><strong>Intent:</strong> ' + escapeHtml( proposal.intent ) + '</li>' );
		}
		if ( proposal.matched_action ) {
			rows.push( '<li><strong>Action:</strong> ' + escapeHtml( proposal.matched_action ) + '</li>' );
		}
		if ( params.countries && params.countries.length ) {
			rows.push( '<li><strong>Countries:</strong> ' + params.countries.map( escapeHtml ).join( ', ' ) + '</li>' );
		}
		if ( params.device ) {
			rows.push( '<li><strong>' + escapeHtml( i18n.deviceLabel || 'Device' ) + ':</strong> ' + escapeHtml( params.device ) + '</li>' );
		}
		if ( proposal.confidence ) {
			rows.push( '<li><strong>Confidence:</strong> ' + Math.round( proposal.confidence * 100 ) + '%</li>' );
		}
		if ( proposal.warnings && proposal.warnings.length ) {
			proposal.warnings.forEach( function ( w ) {
				rows.push( '<li class="rwgc-targeting-assistant__warning">' + escapeHtml( w ) + '</li>' );
			} );
		}
		return '<ul class="rwgc-targeting-assistant__proposal">' + rows.join( '' ) + '</ul>';
	}

	function hasCompoundConditions( proposal ) {
		return proposal && ( proposal.compound || ( proposal.conditions && proposal.conditions.length > 1 ) );
	}

	function persistPortableProposal() {
		if ( ! state.proposal || ! state.proposal.portable_rule_set ) {
			return;
		}
		try {
			sessionStorage.setItem(
				'rwgc_targeting_assistant_portable',
				JSON.stringify( {
					portable_rule_set: state.proposal.portable_rule_set,
					page_id: state.pageId || 0,
				} )
			);
		} catch ( e ) {
			// Ignore quota / privacy mode errors.
		}
	}

	function proposalReviewActions() {
		var $wrap = $( '<div>', { class: 'rwgc-targeting-assistant__actions' } );
		var $primary = $( '<button>', { type: 'button', class: 'button button-primary rwgc-geo-btn', text: i18n.activate || 'Continue setup' } );
		$primary.on( 'click', continueFromProposal );
		$wrap.append( $primary );
		return $wrap;
	}

	function isVariantJourney( proposal, phrase ) {
		if ( hasCompoundConditions( proposal ) && isVariantJourneyPhrase( phrase ) ) {
			return true;
		}
		var action = proposal && proposal.matched_action ? proposal.matched_action : '';
		var intent = proposal && proposal.intent ? proposal.intent : '';
		var lower = ( phrase || '' ).toLowerCase();
		if ( action.indexOf( 'duplicate_rule' ) >= 0 || intent.indexOf( 'variant' ) >= 0 ) {
			return true;
		}
		if ( action.indexOf( 'country' ) >= 0 || action.indexOf( 'device' ) >= 0 || action.indexOf( 'weather' ) >= 0 ) {
			return isVariantJourneyPhrase( lower );
		}
		return false;
	}

	function isVariantJourneyPhrase( phrase ) {
		var lower = ( phrase || '' ).toLowerCase();
		return lower.indexOf( 'version' ) >= 0 || lower.indexOf( 'variant' ) >= 0 || lower.indexOf( 'different page' ) >= 0 || lower.indexOf( 'different version' ) >= 0;
	}

	function applyProposalToState( proposal, phrase ) {
		var params = proposal.params || {};
		state.proposal = proposal;

		if ( isVariantJourney( proposal, phrase ) ) {
			state.journey = 'variant';
			setSummary( 'goal', i18n.goalVariant || 'Show a different page' );
		} else if ( proposal.matched_action && proposal.matched_action.indexOf( 'rule' ) >= 0 ) {
			state.journey = 'rule';
			setSummary( 'goal', i18n.goalRule || 'Show or hide content' );
		}

		if ( params.countries && params.countries.length ) {
			state.country = String( params.countries[0] );
			state.countryLabel = countryLabelFromCode( state.country );
			state.type = 'country';
			state.typeLabel = i18n.country || 'Country';
			setSummary( 'type', state.typeLabel );
			setSummary( 'condition', state.countryLabel );
		}

		if ( params.device ) {
			state.device = String( params.device );
			state.deviceLabel = state.device.charAt( 0 ).toUpperCase() + state.device.slice( 1 );
		}

		if ( hasCompoundConditions( proposal ) ) {
			var labels = ( proposal.conditions || [] ).map( formatConditionChip ).filter( Boolean );
			var join = ( proposal.condition_match || 'all' ) === 'any' ? ' OR ' : ' AND ';
			setSummary( 'condition', labels.join( join ) );
			if ( ! isVariantJourney( proposal, phrase ) ) {
				state.journey = 'rule';
				setSummary( 'goal', i18n.goalRule || 'Show or hide content' );
			}
		} else if ( state.countryLabel || state.deviceLabel ) {
			var cond = state.countryLabel || state.deviceLabel;
			if ( state.countryLabel && state.deviceLabel ) {
				cond = state.countryLabel + ' · ' + state.deviceLabel;
			}
			setSummary( 'condition', cond );
		}

		if ( proposal.resolved_target && proposal.resolved_target.type === 'page' && proposal.resolved_target.id ) {
			state.pageId = parseInt( proposal.resolved_target.id, 10 );
			var match = ( cfg.pages || [] ).filter( function ( p ) {
				return parseInt( p.id, 10 ) === state.pageId;
			} );
			if ( match.length ) {
				state.pageTitle = match[0].title;
				setSummary( 'page', state.pageTitle );
			}
		}

		updateStatus( i18n.statusInProgress || 'In progress' );
	}

	function needsPageSelection( proposal ) {
		if ( state.pageId ) {
			return false;
		}
		var missing = proposal.missing_information || [];
		if ( missing.indexOf( 'target_context' ) >= 0 ) {
			return true;
		}
		return state.journey === 'variant';
	}

	function afterPageResolved() {
		if ( state.country && state.method ) {
			reviewStep();
			return;
		}
		if ( state.country ) {
			appendAssistant( i18n.havePage || 'Do you already have a page for this country?', methodChoices() );
			return;
		}
		appendAssistant( i18n.whoSees || 'Who should see this version?', typeChoices() );
	}

	function continueFromProposal() {
		if ( hasCompoundConditions( state.proposal ) && ! isVariantJourney( state.proposal, $( '#rwgc-targeting-phrase' ).val() ) ) {
			persistPortableProposal();
			goRules();
			return;
		}
		if ( needsPageSelection( state.proposal || {} ) ) {
			pageSelect( i18n.needPage || 'Which page should this apply to?' );
			return;
		}
		if ( state.journey === 'rule' ) {
			goRules();
			return;
		}
		if ( state.country && ! state.method ) {
			appendAssistant( i18n.havePage || 'Do you already have a page for this country?', methodChoices() );
			return;
		}
		if ( state.country && state.method ) {
			reviewStep();
			return;
		}
		goVariantWizard();
	}

	function reviewStep() {
		var summary = ( i18n.reviewTemplate || 'Visitors in %2$s will see a new page. Everyone else will see %1$s.' )
			.replace( '%1$s', state.pageTitle || '—' )
			.replace( '%2$s', state.countryLabel || state.typeLabel || state.deviceLabel || '—' );
		setSummary( 'destination', state.methodLabel );
		updateStatus( i18n.statusReady || 'Ready' );
		if ( state.methodLabel ) {
			appendUser( state.methodLabel );
		}
		appendAssistant( summary, reviewActions() );
	}

	function reviewActions() {
		var $wrap = $( '<div>', { class: 'rwgc-targeting-assistant__actions' } );
		var $primary = $( '<button>', { type: 'button', class: 'button button-primary rwgc-geo-btn', text: i18n.activate || 'Continue setup' } );
		$primary.on( 'click', goVariantWizard );
		var $secondary = $( '<a>', { class: 'rwgc-geo-link', href: '#', text: i18n.preview || 'Preview' } );
		$secondary.on( 'click', function ( e ) {
			e.preventDefault();
			goVariantWizard();
		} );
		$wrap.append( $primary, ' ', $secondary );
		return $wrap;
	}

	function goVariantWizard() {
		if ( hasCompoundConditions( state.proposal ) ) {
			persistPortableProposal();
		}
		var base = $( '#rwgc-targeting-assistant' ).data( 'variant-url' );
		var url = base;
		if ( state.pageId ) {
			url += ( url.indexOf( '?' ) >= 0 ? '&' : '?' ) + 'rwgc_master_page_id=' + state.pageId;
		}
		if ( hasCompoundConditions( state.proposal ) ) {
			url += '&rwgc_condition_type=create_rule';
		} else if ( state.country ) {
			url += '&rwgc_condition_type=countries';
		}
		if ( state.device ) {
			url += '&rwgc_assistant_device=' + encodeURIComponent( state.device );
		}
		window.location.href = url;
	}

	function goRules() {
		persistPortableProposal();
		window.location.href = $( '#rwgc-targeting-assistant' ).data( 'rules-url' );
	}

	function goExperiences() {
		var $root = $( '#rwgc-targeting-assistant' );
		if ( 'available' === $root.data( 'exp-state' ) || 'included' === $root.data( 'exp-state' ) ) {
			window.location.href = $root.data( 'experiences-url' );
			return;
		}
		showLock( i18n.expLockTitle || 'Experiences require Geo Optimise', i18n.expLockBody || '' );
	}

	function buildInterpretContext() {
		var context = {};
		var pageId = parseInt( $( '#rwgc-composer-page' ).val(), 10 );
		var country = $( '#rwgc-composer-country' ).val();
		var device = $( '#rwgc-composer-device' ).val();
		if ( pageId ) {
			context.page_id = pageId;
			context.target_type = 'page';
			context.target_id = pageId;
		}
		if ( country ) {
			context.country_override = country;
		}
		if ( device ) {
			context.device_override = device;
		}
		return context;
	}

	function interpretPhrase() {
		var phrase = $( '#rwgc-targeting-phrase' ).val();
		phrase = phrase ? String( phrase ).trim() : '';
		if ( ! phrase ) {
			window.alert( i18n.enterPhrase || 'Type what you want to target first.' );
			return;
		}
		if ( ! cfg.geoAiAvailable || ! cfg.restUrl ) {
			appendAssistant( i18n.geoAiRequired || 'Natural-language commands require ReactWoo Geo AI.', goalChoices() );
			return;
		}

		var $btn = $( '#rwgc-targeting-interpret-btn' );
		$btn.prop( 'disabled', true ).text( i18n.interpreting || 'Interpreting…' );

		$.ajax( {
			url: cfg.restUrl,
			method: 'POST',
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', cfg.restNonce );
			},
			contentType: 'application/json',
			data: JSON.stringify( {
				phrase: phrase,
				context: buildInterpretContext(),
			} ),
		} )
			.done( function ( proposal ) {
				appendUser( phrase );
				if ( ! proposal || ( ! proposal.matched_action && ! proposal.compound ) ) {
					var msg = ( proposal && proposal.summary ) ? proposal.summary : ( i18n.lowConfidence || 'Could not interpret that command.' );
					appendAssistant( msg, goalChoices() );
					return;
				}
				applyProposalToState( proposal, phrase );
				var intro = escapeHtml( i18n.proposalReady || 'Here is what I understood:' ) + proposalListHtml( proposal );
				if ( proposal.confidence && proposal.confidence < 0.7 ) {
					intro = escapeHtml( i18n.lowConfidence || '' ) + proposalListHtml( proposal );
				}
				appendAssistantHtml( intro, proposalReviewActions() );
			} )
			.fail( function ( xhr ) {
				var msg = i18n.geoAiRequired || 'Could not interpret that command.';
				if ( xhr.responseJSON && xhr.responseJSON.message ) {
					msg = xhr.responseJSON.message;
				}
				appendAssistant( msg, goalChoices() );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( i18n.interpret || 'Interpret' );
			} );
	}

	function start() {
		$( '#rwgc-targeting-thread' ).empty();
		clearStep();
		$( '#rwgc-targeting-phrase' ).val( '' );
		$( '#rwgc-composer-page, #rwgc-composer-country, #rwgc-composer-device' ).val( '' );
		state = {
			journey: '',
			pageId: 0,
			pageTitle: '',
			type: '',
			typeLabel: '',
			country: '',
			countryLabel: '',
			device: '',
			deviceLabel: '',
			method: '',
			methodLabel: '',
			proposal: null,
		};
		$( '#rwgc-targeting-setup-empty' ).removeClass( 'rwgc-is-hidden' );
		$( '#rwgc-targeting-summary' ).addClass( 'rwgc-is-hidden' );
		[ 'goal', 'page', 'type', 'condition', 'destination', 'status' ].forEach( function ( k ) {
			setSummary( k, '' );
		} );
		appendAssistant( i18n.opening || 'Describe what you want to target.', goalChoices() );
	}

	$( function () {
		var $root = $( '#rwgc-targeting-assistant' );
		if ( ! $root.length ) {
			return;
		}
		populateComposerSelects();
		start();

		$( '#rwgc-targeting-interpret-btn' ).on( 'click', interpretPhrase );
		$( '#rwgc-targeting-reset-btn' ).on( 'click', start );
		$( '#rwgc-targeting-phrase' ).on( 'keydown', function ( e ) {
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				interpretPhrase();
			}
		} );

		$root.on( 'click', '.rwgc-targeting-assistant__choice', function () {
			var val = $( this ).data( 'value' );
			var label = $( this ).find( '.rwgc-targeting-assistant__choice-label' ).text().trim();
			hideLock();

			if ( 'variant' === val ) {
				state.journey = 'variant';
				setSummary( 'goal', label );
				setSummary( 'type', '' );
				updateStatus( i18n.statusInProgress || 'In progress' );
				appendUser( label );
				pageSelect( i18n.whichPage || 'Which page should we create a version for?' );
				return;
			}
			if ( 'rule' === val ) {
				appendUser( label );
				setSummary( 'goal', label );
				goRules();
				return;
			}
			if ( 'experience' === val ) {
				appendUser( label );
				setSummary( 'goal', label );
				goExperiences();
				return;
			}
			if ( 'country' === val ) {
				state.type = 'country';
				state.typeLabel = label;
				setSummary( 'type', label );
				appendUser( label );
				appendAssistant( i18n.whichCountry || 'Which country should see the new page?', countrySelect() );
				return;
			}
			if ( 'existing' === val || 'duplicate' === val || 'blank' === val ) {
				state.method = val;
				state.methodLabel = label;
				reviewStep();
				return;
			}
			var cap = cfg.capabilities[ val ] || {};
			if ( 'included' !== cap.state && 'available' !== cap.state ) {
				appendUser( label );
				setSummary( 'type', cap.label || label );
				showLock( cap.label || '', cap.reason || '' );
				return;
			}
			state.type = val;
			state.typeLabel = cap.label || label;
			setSummary( 'type', state.typeLabel );
			appendUser( label );
			goVariantWizard();
		} );

		$( '#rwgc-targeting-lock-back' ).on( 'click', function () {
			hideLock();
			if ( 'variant' === state.journey && state.pageId ) {
				appendAssistant( i18n.whoSees || 'Who should see this version?', typeChoices() );
			} else {
				start();
			}
		} );
	} );
}( jQuery ) );
