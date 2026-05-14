/**
 * Elementor editor: mount the shared visibility rule builder on the portable textarea control.
 */
(function ($) {
	'use strict';

	$(function () {
		if (window.ReactWooRuleBuilder && typeof window.ReactWooRuleBuilder.mountElementor === 'function') {
			window.ReactWooRuleBuilder.mountElementor();
		}
	});
})(jQuery);
