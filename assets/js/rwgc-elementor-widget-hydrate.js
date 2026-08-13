/**
 * Load one widget's control stack when the inspector opens.
 *
 * Bulk get_widgets_config returns empty maps (LiteSpeed), so document-config
 * widgets arrive without controls or tabs. Elementor tabs_controls values are
 * plain strings (Controls_Manager::get_tabs), not objects.
 */
(function ($) {
	'use strict';

	var pending = {};
	var failed = {};
	var reopening = false;

	function defaultTabs() {
		return {
			content: 'Content',
			style: 'Style',
			advanced: 'Advanced',
			layout: 'Layout'
		};
	}

	function normalizeTabs(tabs) {
		if (!tabs || typeof tabs !== 'object' || Array.isArray(tabs)) {
			return defaultTabs();
		}
		var out = {};
		var fallback = defaultTabs();
		Object.keys(tabs).forEach(function (key) {
			var value = tabs[key];
			if (typeof value === 'string' && value) {
				out[key] = value;
			} else if (value && typeof value.title === 'string') {
				out[key] = value.title;
			} else {
				out[key] = fallback[key] || key;
			}
		});
		if (!out.content) {
			out.content = fallback.content;
		}
		return out;
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
		entry.tabs_controls = normalizeTabs(entry.tabs_controls);
	}

	function widgetTypeOf(model) {
		if (!model || typeof model.get !== 'function') {
			return '';
		}
		return model.get('widgetType') || '';
	}

	function controlCount(type) {
		var entry = (window.elementor && elementor.widgetsCache) ? elementor.widgetsCache[type] : null;
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
			elementor.widgetsCache[name].commonMerged = false;
		});
		elementor.addWidgetsCache(data);
		Object.keys(data).forEach(ensureEntry);
	}

	function reopen(model, view) {
		if (typeof $e === 'undefined' || typeof $e.run !== 'function') {
			return;
		}
		reopening = true;
		try {
			var editor = ($e.components && $e.components.get) ? $e.components.get('panel/editor') : null;
			if (editor) {
				editor.activeModelId = null;
			}
			$e.run('panel/editor/open', { model: model, view: view });
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
		if (!type || pending[type] || failed[type] || reopening) {
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

	function maybeHydrate(model, view) {
		var type = widgetTypeOf(model);
		if (!type) {
			return;
		}
		ensureEntry(type);
		if (hasUsableControls(type)) {
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
			ensureEntry(widgetTypeOf(model));
			var data = orig(model);
			if (data && typeof data === 'object') {
				data.tabs_controls = normalizeTabs(data.tabs_controls);
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
			var isOpen = ('panel/editor/open' === command && args && args.model);
			if (isOpen) {
				ensureEntry(widgetTypeOf(args.model));
			}
			var result = orig.apply($e, arguments);
			if (isOpen) {
				maybeHydrate(args.model, args.view);
			}
			return result;
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
		elementor.hooks.addAction('panel/open_editor/widget', function (panel, model, view) {
			maybeHydrate(model, view);
		});
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
