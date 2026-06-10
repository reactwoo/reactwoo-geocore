( function ( $ ) {
	'use strict';

	function getConditionType() {
		return $( 'input[name="rwgc_condition_type"]:checked' ).val() || 'countries';
	}

	function getContentMode() {
		return $( 'input[name="rwgc_content_mode"]:checked' ).val() || 'duplicate';
	}

	function togglePanels() {
		var condition = getConditionType();
		var mode = getContentMode();

		$( '.rwgc-exp-panel[data-rwgc-exp-panel]' ).hide();
		if ( 'countries' === condition ) {
			$( '.rwgc-exp-panel--countries' ).show();
		} else if ( 'saved_rule' === condition ) {
			$( '.rwgc-exp-panel--saved_rule' ).show();
		} else if ( 'create_rule' === condition ) {
			$( '.rwgc-exp-panel--create_rule' ).show();
		}

		if ( 'existing' === mode ) {
			$( '.rwgc-exp-panel--existing' ).show();
		}

		var isEveryone = 'everyone' === condition;
		$( '.rwgc-exp-everyone-note' ).toggle( isEveryone );
		$( '.rwgc-exp-mode--duplicate, .rwgc-exp-mode--blank, .rwgc-exp-mode--ai_adapt' ).toggle( ! isEveryone );
		$( '.rwgc-exp-submit' ).toggle( 'create_rule' !== condition );

		if ( isEveryone && -1 === $.inArray( getContentMode(), [ 'existing' ] ) ) {
			$( 'input[name="rwgc_content_mode"][value="existing"]' ).prop( 'checked', true );
		}
	}

	$( document ).on( 'change', 'input[name="rwgc_condition_type"], input[name="rwgc_content_mode"]', togglePanels );
	$( togglePanels );
}( jQuery ) );
