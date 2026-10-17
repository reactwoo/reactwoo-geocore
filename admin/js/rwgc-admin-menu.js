/**
 * Let the collapsed ReactWoo Geo menu item navigate.
 *
 * WordPress preventDefault()s a top-level item that has a submenu so the
 * flyout can open. That flyout is display:none while the sidebar is collapsed,
 * so the tap would otherwise do nothing.
 */
(function () {
	function stop(e) {
		var a = e.target && e.target.closest && e.target.closest('#toplevel_page_rwgc-dashboard > a');
		if (a && document.body.classList.contains('rwgc-admin-sidebar-collapsed')) {
			e.stopPropagation();
		}
	}
	window.addEventListener('click', stop, true);
	window.addEventListener('touchstart', stop, true);
})();
