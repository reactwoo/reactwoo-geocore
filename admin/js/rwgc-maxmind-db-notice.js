(function () {
	document.addEventListener('click', function (event) {
		var target = event.target;
		var button = target && target.closest ? target.closest('.rwgc-maxmind-db-notice .notice-dismiss') : null;
		if (!button || !window.rwgcMaxmindDbNotice) {
			return;
		}
		var body = new window.FormData();
		body.append('action', 'rwgc_dismiss_maxmind_db_notice');
		body.append('_ajax_nonce', window.rwgcMaxmindDbNotice.nonce);
		window.fetch(window.rwgcMaxmindDbNotice.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		});
	});
})();
