/**
 * Chat-style Targeting Assistant — conversation builds down the page.
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
		method: '',
		methodLabel: '',
	};

	function assistantBubble( text ) {
		var $bubble = $( '<div>', {
			class: 'rwgc-targeting-assistant__bubble rwgc-targeting-assistant__bubble--assistant',
		} );

		$bubble.append( $( '<span>', {
			class: 'rwgc-targeting-assistant__who',
			text: i18n.assistantName || 'Geo Assistant',
		} ) );
		$bubble.append( $( '<span>', {
			class: 'rwgc-targeting-assistant__text',
			text: text,
		} ) );

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

	function pageSelect() {
		var $wrap = $( '<div>', { class: 'rwgc-targeting-assistant__control' } );
		var $sel = $( '<select>', { class: 'rwgc-targeting-assistant__select', id: 'rwgc-assistant-page' } );
		$sel.append( $( '<option>', { value: '', text: i18n.choosePage || 'Choose a page…' } ) );
		( cfg.pages || [] ).forEach( function ( p ) {
			$sel.append( $( '<option>', { value: p.id, text: p.title } ) );
		} );
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
			appendAssistant( i18n.whoSees || 'Who should see this version?', typeChoices() );
		} );
		$wrap.append( $sel, $btn );
		return $wrap;
	}

	function countrySelect() {
		var $wrap = $( '<div>', { class: 'rwgc-targeting-assistant__control' } );
		var $sel = $( '<select>', { class: 'rwgc-targeting-assistant__select', id: 'rwgc-assistant-country' } );
		$sel.append( $( '<option>', { value: '', text: i18n.chooseCountry || 'Choose a country…' } ) );
		( cfg.countries || [] ).forEach( function ( c ) {
			$sel.append( $( '<option>', { value: c.code, text: c.name } ) );
		} );
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

	function reviewStep() {
		var summary = ( i18n.reviewTemplate || 'Visitors in %2$s will see a new page. Everyone else will see %1$s.' )
			.replace( '%1$s', state.pageTitle )
			.replace( '%2$s', state.countryLabel || state.typeLabel );
		setSummary( 'destination', state.methodLabel );
		updateStatus( i18n.statusReady || 'Ready' );
		appendUser( state.methodLabel );
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
		var base = $( '#rwgc-targeting-assistant' ).data( 'variant-url' );
		var url = base;
		if ( state.pageId ) {
			url += ( url.indexOf( '?' ) >= 0 ? '&' : '?' ) + 'rwgc_master_page_id=' + state.pageId;
		}
		if ( state.country ) {
			url += '&rwgc_condition_type=countries';
		}
		window.location.href = url;
	}

	function goRules() {
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

	function start() {
		$( '#rwgc-targeting-thread' ).empty();
		clearStep();
		state = { journey: '', pageId: 0, pageTitle: '', type: '', typeLabel: '', country: '', countryLabel: '', method: '', methodLabel: '' };
		$( '#rwgc-targeting-setup-empty' ).removeClass( 'rwgc-is-hidden' );
		$( '#rwgc-targeting-summary' ).addClass( 'rwgc-is-hidden' );
		[ 'goal', 'page', 'type', 'condition', 'destination', 'status' ].forEach( function ( k ) {
			setSummary( k, '' );
		} );
		appendAssistant( i18n.opening || 'Hi, what would you like to target?', goalChoices() );
	}

	$( function () {
		var $root = $( '#rwgc-targeting-assistant' );
		if ( ! $root.length ) {
			return;
		}
		start();

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
				appendAssistant( i18n.whichPage || 'Great. Which page should we create a version for?', pageSelect() );
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
				setSummary( 'availability', i18n.included || 'Included' );
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
				setSummary( 'availability', 'Pro' );
				showLock( cap.label || '', cap.reason || '' );
				return;
			}
			state.type = val;
			state.typeLabel = cap.label || label;
			setSummary( 'type', state.typeLabel );
			setSummary( 'availability', 'Pro' );
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
