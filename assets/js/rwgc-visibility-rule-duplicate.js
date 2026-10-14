/**
 * Duplicate and delete actions on the visibility rule editor.
 */
(function () {
	'use strict';

	var dup = document.getElementById('rwgc-duplicate-visibility-rule');
	if (dup) {
		dup.addEventListener('click', function () {
			var idInput = document.querySelector('#rwgc-visibility-rule-form [name="rwgc_rule_id"]');
			var titleInput = document.getElementById('rwgc_rule_title');
			if (idInput) {
				idInput.value = '0';
			}
			if (titleInput && titleInput.value.indexOf('(copy)') === -1) {
				titleInput.value = titleInput.value.trim() + ' (copy)';
			}
			var form = document.getElementById('rwgc-visibility-rule-form');
			if (form) {
				form.submit();
			}
		});
	}

	var del = document.querySelector('#rwgc-visibility-rule-form a.button-link-delete');
	if (del) {
		del.addEventListener('click', function (event) {
			var message = del.getAttribute('data-rwgc-confirm') || '';
			if (message && !window.confirm(message)) {
				event.preventDefault();
			}
		});
	}
}());
