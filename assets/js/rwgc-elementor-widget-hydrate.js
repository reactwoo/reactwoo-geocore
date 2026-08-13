/**
 * Load one widget's control stack when the inspector opens.
 *
 * Bulk get_widgets_config returns empty maps (LiteSpeed). Document config
 * widgets often have no tabs_controls, and Elementor 4.2 setDefaultTab then
 * crashes on `.content` before panel/open_editor hooks run. Seed tabs, then
 * fetch that widget on its own request (immediately, never batched).
 */
(function ($) {
	'use strict';

	var pending = {};
	var failed = {};
	var reopening = false;

	function defaultTabs() {
		return {
			content: { title: 'Content' },
			style: { title: 'Style' },
			advanced: { title: 'Advanced' },
			layout: { title: 'Layout' }
		};
	}

	function ensureEntry(type) {
		if (!type || !window.elementor || !elementor.widgetsCache) {
			return;
		}
		if (!elementor.widgetsCache[type]) {
			elementor.widgetsCache[type] = {
				widget_type: type,
				controls: {},
				tabs_controls: defaultTabs()
			};
			return;
		}
		var entry = elementor.widgetsCache[type];
		if (!entry.controls || typeof entry.controls !== 'object') {
			entry.controls = {};
		}
		if (!entry.tabs_controls || typeof entry.tabs_controls !== 'object') {
			entry.tabs_controls = defaultTabs();
		}
		if (!entry.tabs_controls.content || typeof entry.tabs_controls.content.title !== 'string') {
			entry.tabs_controls.content = { title: 'Content' };
		}
	}

	function ensureModel(model) {
		if (!model || typeof model.get !== 'function') {
			return '';
		}
		var type = model.get('widgetType');
		if (type) {
			ensureEntry(type);
		}
		return type || '';
	}

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
			ensureEntry(name);
			if (elementor.widgetsCache[name]) {
				elementor.widgetsCache[name].commonMerged = false;
			}
		});
		elementor.addWidgetsCache(data);
		Object.keys(data).forEach(function (name) {
			ensureEntry(name);
		});
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

	function onOpen(panel, model, view) {
		if (reopening || !model || typeof model.get !== 'function') {
			return;
		}
		var type = ensureModel(model);
		if (!type || hasUsableControls(type)) {
			return;
		}
		hydrate(type, model, view);
	}

	function wrapGetElementData() {
		if (!elementor.getElementData || elementor.getElementData.__rwgcWrapped) {
			return;
		}
		var orig = elementor.getElementData.bind(elementor);
		elementor.getElementData = function (model) {
			ensureModel(model);
			var data = orig(model);
			if (data && (!data.tabs_controls || typeof data.tabs_controls !== 'object' || typeof (data.tabs_controls.content && data.tabs_controls.content.title) !== 'string')) {
				data.tabs_controls = data.tabs_controls && typeof data.tabs_controls === 'object' ? data.tabs_controls : defaultTabs();
				if (!data.tabs_controls.content || typeof data.tabs_controls.content.title !== 'string') {
					data.tabs_controls.content = { title: 'Content' };
				}
			}
			return data;
		};
		elementor.getElementData.__rwgcWrapped = true;
	}

	function wrapRun() {
		if (typeof $e === 'undefined' || typeof $e.run !== 'function' || $e.run.__rwgcWrapped) {
			return;
		}
		var orig = $e.run.bind($e);
		$e.run = function (command, args) {
			if (command === 'panel/editor/open' && args && args.model) {
				ensureModel(args.model);
			}
			return orig.apply($e, arguments);
		};
		$e.run.__rwgcWrapped = true;
	}

	function bind() {
		if (!window.elementor || !elementor.hooks || typeof elementor.hooks.addAction !== 'function') {
			return false;
		}
		if (!elementor.widgetsCache) {
			elementor.widgetsCache = {};
		}
		wrapGetElementData();
		wrapRun();
		elementor.hooks.addAction('panel/open_editor/widget', onOpen);
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
