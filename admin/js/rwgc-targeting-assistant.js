/**
 * Chat-style Targeting Assistant (one question at a time).
 */
( function ( $ ) {
	'use strict';

	var caps = window.rwgcTargetingAssistant || { capabilities: {}, i18n: {} };
	var i18n = caps.i18n || {};
	var state = { journey: '', type: '' };

	function bubble( text, role ) {
		return $( '<div>', {
			class: 'rwgc-targeting-assistant__bubble' + ( 'user' === role ? ' rwgc-targeting-assistant__bubble--user' : '' ),
			text: text,
		} );
	}

	function choiceBtn( label, badge, locked, value ) {
		var $btn = $( '<button>', {
			type: 'button',
			class: 'rwgc-targeting-assistant__choice' + ( locked ? ' is-locked' : '' ),
			'data-value': value,
			text: label,
		} );
		if ( badge ) {
			$btn.append( $( '<span>', { class: 'rwgc-cap-badge', text: badge } ) );
		}
		return $btn;
	}

	function setSummary( key, val ) {
		$( '#rwgc-targeting-summary dd[data-key="' + key + '"]' ).text( val || '—' );
	}

	function showLock( title, body ) {
		$( '#rwgc-targeting-lock-title' ).text( title );
		$( '#rwgc-targeting-lock-body' ).text( body );
		$( '#rwgc-targeting-lock-panel' ).removeClass( 'rwgc-is-hidden' ).prop( 'hidden', false );
	}

	function hideLock() {
		$( '#rwgc-targeting-lock-panel' ).addClass( 'rwgc-is-hidden' ).prop( 'hidden', true );
	}

	function renderGoalStep() {
		var $thread = $( '#rwgc-targeting-thread' ).empty();
		var $step = $( '#rwgc-targeting-step' ).empty();
		$thread.append( bubble( i18n.opening || 'What would you like to do?' ) );
		var $choices = $( '<div>', { class: 'rwgc-targeting-assistant__choices' } );
		$choices.append( choiceBtn( i18n.goalVariant || 'Show a different page', i18n.included || 'Included', false, 'variant' ) );
		$choices.append( choiceBtn( i18n.goalRule || 'Show or hide content', i18n.included || 'Included', false, 'rule' ) );
		var expCap = caps.capabilities.experiences || {};
		$choices.append(
			choiceBtn(
				i18n.goalExperience || 'Create an Experience',
				expCap.badge || 'Geo Optimise',
				'not_installed' === expCap.state || 'locked' === expCap.state,
				'experience'
			)
		);
		$step.append( $choices );
	}

	function renderVariantTypeStep() {
		var $thread = $( '#rwgc-targeting-thread' );
		var $step = $( '#rwgc-targeting-step' ).empty();
		$thread.append( bubble( i18n.whoSees || 'Who should see this version?' ) );
		var $choices = $( '<div>', { class: 'rwgc-targeting-assistant__choices' } );
		$choices.append( choiceBtn( i18n.country || 'Country', i18n.included || 'Included', false, 'country' ) );
		[ 'variant_type_audience', 'variant_type_campaign', 'variant_type_weather', 'variant_type_time' ].forEach( function ( id ) {
			var c = caps.capabilities[ id ] || {};
			$choices.append( choiceBtn( c.label || id, c.badge || 'GeoCore Pro', 'included' !== c.state && 'available' !== c.state, id ) );
		} );
		$step.append( $choices );
	}

	function goVariantWizard() {
		var url = $( '#rwgc-targeting-assistant' ).data( 'variant-url' );
		if ( url ) {
			window.location.href = url;
		}
	}

	function goRules() {
		var url = $( '#rwgc-targeting-assistant' ).data( 'rules-url' );
		if ( url ) {
			window.location.href = url;
		}
	}

	function goExperiences() {
		var $root = $( '#rwgc-targeting-assistant' );
		var stateExp = $root.data( 'exp-state' );
		if ( 'available' === stateExp || 'included' === stateExp ) {
			window.location.href = $root.data( 'experiences-url' );
			return;
		}
		showLock(
			i18n.expLockTitle || 'Experiences require Geo Optimise',
			i18n.expLockBody || 'Use Experiences to split traffic, measure conversions, and choose winning versions.'
		);
	}

	$( function () {
		var $root = $( '#rwgc-targeting-assistant' );
		if ( ! $root.length ) {
			return;
		}
		renderGoalStep();

		$root.on( 'click', '.rwgc-targeting-assistant__choice', function () {
			var val = $( this ).data( 'value' );
			var label = $( this ).clone().children().remove().end().text().trim();
			hideLock();
			$( '#rwgc-targeting-thread' ).append( bubble( label, 'user' ) );

			if ( 'variant' === val ) {
				state.journey = 'variant';
				setSummary( 'goal', i18n.goalVariant || 'Show a different page' );
				renderVariantTypeStep();
				return;
			}
			if ( 'rule' === val ) {
				state.journey = 'rule';
				setSummary( 'goal', i18n.goalRule || 'Show or hide content' );
				setSummary( 'type', i18n.country || 'Country' );
				setSummary( 'availability', i18n.included || 'Included' );
				goRules();
				return;
			}
			if ( 'experience' === val ) {
				state.journey = 'experience';
				setSummary( 'goal', i18n.goalExperience || 'Create an Experience' );
				goExperiences();
				return;
			}
			if ( 'country' === val ) {
				setSummary( 'type', i18n.country || 'Country' );
				setSummary( 'availability', i18n.included || 'Included' );
				goVariantWizard();
				return;
			}
			var cap = caps.capabilities[ val ] || {};
			if ( 'included' !== cap.state && 'available' !== cap.state ) {
				setSummary( 'type', cap.label || val );
				setSummary( 'availability', cap.badge || '' );
				showLock( cap.label || '', cap.reason || '' );
				return;
			}
			setSummary( 'type', cap.label || val );
			setSummary( 'availability', cap.badge || '' );
			goVariantWizard();
		} );

		$( '#rwgc-targeting-lock-back' ).on( 'click', function () {
			hideLock();
			if ( 'variant' === state.journey ) {
				renderVariantTypeStep();
			} else {
				renderGoalStep();
			}
		} );
	} );
}( jQuery ) );
