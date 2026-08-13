/**
 * Load one widget's control stack after the Elements panel is ready.
 *
 * Must not join the boot `get_widgets_config` batch — that puts get_widget_types()
 * back on the LiteSpeed 503 path. Wait for panel/state-ready, then send immediately.
 */
(function ($) {
	'use strict';

	var pending = {};
	var failed = {};
	var reopening = false;
	var panelReady = false;
	var queued = null;

	function cacheEntry(type) {
		if (!window.elementor || !elementor.widgetsCache) {
			return null;
		}
		return elementor.widgetsCache[type] || null;
	}

	function controlCount(type) {
		var entry = cacheEntry(type);
		if (!entry || !entry.controls || typeof entry.controls !== 'object') {
			return 0;
		}
		return Object.keys(entry.controls).length;
	}

	function hasUsableControls(type) {
		return controlCount(type) > 0;
	}

	function editorPostId() {
		try {
			return elementor.config.document.id;
		} catch (e) {
			return 0;
		}
	}

	function applyCache(data) {
		if (!data || typeof data !== 'object' || !window.elementor || typeof elementor.addWidgetsCache !== 'function') {
			return;
		}
		Object.keys(data).forEach(function (name) {
			if (elementor.widgetsCache[name]) {
				elementor.widgetsCache[name].commonMerged = false;
				if (!elementor.widgetsCache[name].controls) {
					elementor.widgetsCache[name].controls = {};
				}
			}
		});
		elementor.addWidgetsCache(data);
	}

	function reopen(model, view) {
		if (typeof $e === 'undefined' || typeof $e.run !== 'function') {
			return;
		}
		reopening = true;
		try {
			var editor = $e.components && $e.components.get ? $e.components.get('panel/editor') : null;
			if (editor) {
				editor.activeModelId = null;
			}
			$e.run('panel/editor/open', {
				model: model,
				view: view
			});
		} catch (e) {
			if ($e.routes && typeof $e.routes.refreshContainer === 'function') {
				$e.routes.refreshContainer('panel');
			}
		}
		window.setTimeout(function () {
			reopening = false;
		}, 50);
	}

	function hydrate(type, model, view) {
		if (!type || pending[type] || failed[type]) {
			return;
		}
		if (typeof elementorCommon === 'undefined' || !elementorCommon.ajax || typeof elementorCommon.ajax.addRequest !== 'function') {
			return;
		}
		pending[type] = true;
		elementorCommon.ajax.addRequest(
			'rwgc_get_widget_config',
			{
				unique_id: 'rwgc-hydrate-' + type,
				data: {
					widget: type,
					editor_post_id: editorPostId()
				},
				success: function (data) {
					pending[type] = false;
					applyCache(data);
					if (hasUsableControls(type)) {
						reopen(model, view);
					} else {
						failed[type] = true;
					}
				},
				error: function () {
					pending[type] = false;
					failed[type] = true;
				}
			},
			true
		);
	}

	function flushQueue() {
		if (!queued) {
			return;
		}
		var item = queued;
		queued = null;
		if (!hasUsableControls(item.type)) {
			hydrate(item.type, item.model, item.view);
		}
	}

	function onPanelReady() {
		if (panelReady) {
			return;
		}
		panelReady = true;
		flushQueue();
	}

	function onOpen(panel, model, view) {
		if (reopening || !model || typeof model.get !== 'function') {
			return;
		}
		var type = model.get('widgetType');
		if (!type || hasUsableControls(type)) {
			return;
		}
		if (!panelReady) {
			queued = { type: type, model: model, view: view };
			return;
		}
		hydrate(type, model, view);
	}

	function attachStateReady() {
		if (typeof $e === 'undefined') {
			return false;
		}
		if ($e.hooks && typeof $e.hooks.registerAfter === 'function') {
			try {
				$e.hooks.registerAfter('panel/state-ready', onPanelReady);
				return true;
			} catch (e) {
				/* fall through */
			}
		}
		if (typeof $e.internal === 'function' && !$e.internal.__rwgcWrapped) {
			var orig = $e.internal.bind($e);
			$e.internal = function (command) {
				var ret = orig.apply($e, arguments);
				if (command === 'panel/state-ready') {
					onPanelReady();
				}
				return ret;
			};
			$e.internal.__rwgcWrapped = true;
			return true;
		}
		return false;
	}

	function bind() {
		if (!window.elementor || !elementor.hooks || typeof elementor.hooks.addAction !== 'function') {
			return false;
		}
		elementor.hooks.addAction('panel/open_editor/widget', onOpen);
		attachStateReady();
		return true;
	}

	function start() {
		if (bind()) {
			return;
		}
		$(window).on('elementor:init', bind);
	}

	$(start);
})(jQuery);
