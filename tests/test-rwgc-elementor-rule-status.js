/**
 * Elementor stale-rule notice must attach after the panel exists.
 *
 * The bridge used to observe #elementor-panel-inner at script load. Elementor
 * creates that node later, so selecting a widget never rendered the notice and
 * never kept a missing rule selected. These cases create the panel after the
 * script runs, then open it the way Elementor 3 and 4 do.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const src = fs.readFileSync(
	path.join(__dirname, '../assets/js/rwgc-elementor-library-bridge.js'),
	'utf8'
);

function assert(condition, message) {
	if (!condition) {
		throw new Error(message);
	}
}

function createHarness(libraryConfig) {
	const observers = [];
	let mutationCount = 0;
	const nodes = [];

	function El(tag) {
		this.nodeType = 1;
		this.tagName = String(tag || 'div').toUpperCase();
		this.children = [];
		this.parentNode = null;
		this.attrs = {};
		this.className = '';
		this.id = '';
		this.value = '';
		this.type = '';
		this.disabled = false;
		this.style = {};
		this._handlers = {};
		nodes.push(this);
	}

	El.prototype.setAttribute = function (name, value) {
		this.attrs[name] = String(value);
		if (name === 'id') {
			this.id = String(value);
		}
		if (name === 'class') {
			this.className = String(value);
		}
		if (name === 'value') {
			this.value = String(value);
		}
	};
	El.prototype.getAttribute = function (name) {
		if (!Object.prototype.hasOwnProperty.call(this.attrs, name)) {
			return null;
		}
		return this.attrs[name];
	};
	El.prototype.appendChild = function (child) {
		if (child.parentNode) {
			child.parentNode.removeChild(child);
		}
		child.parentNode = this;
		this.children.push(child);
		notify(this);
		return child;
	};
	El.prototype.removeChild = function (child) {
		const index = this.children.indexOf(child);
		if (index >= 0) {
			this.children.splice(index, 1);
			child.parentNode = null;
			notify(this);
		}
		return child;
	};
	El.prototype.insertBefore = function (child, before) {
		if (child.parentNode) {
			child.parentNode.removeChild(child);
		}
		child.parentNode = this;
		const index = before ? this.children.indexOf(before) : -1;
		if (index < 0) {
			this.children.push(child);
		} else {
			this.children.splice(index, 0, child);
		}
		notify(this);
		return child;
	};

	function notify(node) {
		if (!observers.length) {
			return;
		}
		mutationCount += 1;
		if (mutationCount > 40) {
			throw new Error('panel mutations did not settle (notice redraw loop)');
		}
		observers.forEach(function (observer) {
			if (observer.root && (observer.root === node || contains(observer.root, node))) {
				observer.callback();
			}
		});
	}

	function contains(root, node) {
		let current = node;
		while (current) {
			if (current === root) {
				return true;
			}
			current = current.parentNode;
		}
		return false;
	}

	function matches(el, selector) {
		selector = String(selector || '').trim();
		if (!selector || selector === '*') {
			return true;
		}
		let tag = '';
		let id = '';
		const classes = [];
		const attrs = [];
		const re = /([#.])?([a-zA-Z0-9_-]+)|\[([^\]]+)\]/g;
		let match;
		while ((match = re.exec(selector))) {
			if (match[3]) {
				const raw = match[3];
				const eq = raw.indexOf('=');
				if (eq === -1) {
					attrs.push([raw, null]);
				} else {
					attrs.push([raw.slice(0, eq), raw.slice(eq + 1).replace(/^["']|["']$/g, '')]);
				}
			} else if (match[1] === '#') {
				id = match[2];
			} else if (match[1] === '.') {
				classes.push(match[2]);
			} else if (match[2]) {
				tag = match[2].toUpperCase();
			}
		}
		if (tag && el.tagName !== tag) {
			return false;
		}
		if (id && el.id !== id) {
			return false;
		}
		const classList = String(el.className || '').split(/\s+/);
		for (let i = 0; i < classes.length; i++) {
			if (classList.indexOf(classes[i]) === -1) {
				return false;
			}
		}
		for (let a = 0; a < attrs.length; a++) {
			const actual = el.getAttribute(attrs[a][0]);
			if (attrs[a][1] === null) {
				if (actual === null) {
					return false;
				}
			} else if (actual !== attrs[a][1]) {
				return false;
			}
		}
		return true;
	}

	function walk(el, visit) {
		if (!el || !el.children) {
			return;
		}
		el.children.forEach(function (child) {
			visit(child);
			walk(child, visit);
		});
	}

	function queryAll(root, selector) {
		const groups = String(selector || '')
			.split(',')
			.map(function (part) {
				return part.trim();
			})
			.filter(Boolean);
		const found = [];
		groups.forEach(function (group) {
			const steps = group.split(/\s+/);
			let current = [root];
			steps.forEach(function (step) {
				const next = [];
				current.forEach(function (start) {
					walk(start, function (el) {
						if (matches(el, step)) {
							next.push(el);
						}
					});
				});
				current = next;
			});
			current.forEach(function (el) {
				if (found.indexOf(el) === -1) {
					found.push(el);
				}
			});
		});
		return found;
	}

	function findId(root, id) {
		let found = null;
		walk(root, function (el) {
			if (!found && el.id === id) {
				found = el;
			}
		});
		return found;
	}

	const document = {
		nodeType: 9,
		children: [],
		_handlers: {},
		getElementById: function (id) {
			return findId(document, id);
		},
	};
	document.body = new El('body');
	document.appendChild = El.prototype.appendChild.bind(document);
	document.appendChild(document.body);

	function parseHtml(html) {
		const tag = (/<([a-z0-9]+)/i.exec(html) || [])[1] || 'div';
		const el = new El(tag);
		const cls = /class="([^"]*)"/.exec(html);
		if (cls) {
			el.className = cls[1];
			el.attrs.class = cls[1];
		}
		const style = /style="([^"]*)"/.exec(html);
		if (style) {
			el.attrs.style = style[1];
		}
		const type = /type="([^"]*)"/.exec(html);
		if (type) {
			el.type = type[1];
			el.attrs.type = type[1];
		}
		const role = /role="([^"]*)"/.exec(html);
		if (role) {
			el.attrs.role = role[1];
		}
		return el;
	}

	function wrap(list) {
		const api = {
			jquery: true,
			length: list.length,
			_list: list,
			each: function (fn) {
				list.forEach(function (el, index) {
					fn.call(el, index, el);
				});
				return api;
			},
			find: function (selector) {
				const found = [];
				list.forEach(function (el) {
					queryAll(el, selector).forEach(function (match) {
						if (found.indexOf(match) === -1) {
							found.push(match);
						}
					});
				});
				return wrap(found);
			},
			first: function () {
				return wrap(list.slice(0, 1));
			},
			closest: function (selector) {
				const found = [];
				list.forEach(function (el) {
					let current = el;
					while (current && current.nodeType === 1) {
						if (matches(current, selector)) {
							found.push(current);
							break;
						}
						current = current.parentNode;
					}
				});
				return wrap(found);
			},
			next: function (selector) {
				const found = [];
				list.forEach(function (el) {
					if (!el.parentNode) {
						return;
					}
					const siblings = el.parentNode.children;
					const index = siblings.indexOf(el);
					const next = siblings[index + 1];
					if (next && (!selector || matches(next, selector))) {
						found.push(next);
					}
				});
				return wrap(found);
			},
			is: function (selector) {
				if (!list.length) {
					return false;
				}
				if (selector === ':checked') {
					return !!list[0].checked;
				}
				if (selector === 'select') {
					return list[0].tagName === 'SELECT';
				}
				return matches(list[0], selector);
			},
			val: function (value) {
				if (!list.length) {
					return value === undefined ? undefined : api;
				}
				const el = list[0];
				if (value === undefined) {
					if (el.tagName === 'SELECT') {
						let matched = false;
						function walkOptions(node) {
							(node.children || []).forEach(function (child) {
								if (child.tagName === 'OPTION' && child.value === el.value) {
									matched = true;
								}
								walkOptions(child);
							});
						}
						walkOptions(el);
						return matched ? el.value : null;
					}
					return el.value;
				}
				el.value = value == null ? '' : String(value);
				if (el.tagName === 'OPTION') {
					el.attrs.value = el.value;
				}
				return api;
			},
			attr: function (name, value) {
				if (value === undefined) {
					return list.length ? list[0].getAttribute(name) : undefined;
				}
				list.forEach(function (el) {
					el.setAttribute(name, value);
				});
				return api;
			},
			prop: function (name, value) {
				if (value === undefined) {
					return list.length ? list[0][name] : undefined;
				}
				list.forEach(function (el) {
					el[name] = value;
				});
				return api;
			},
			text: function (value) {
				if (value === undefined) {
					return list
						.map(function (el) {
							return el.textContent || '';
						})
						.join('');
				}
				list.forEach(function (el) {
					el.textContent = String(value);
				});
				return api;
			},
			css: function (name, value) {
				if (!list.length) {
					return api;
				}
				if (typeof name === 'object') {
					Object.keys(name).forEach(function (key) {
						list[0].style[key] = name[key];
					});
					return api;
				}
				if (value === undefined) {
					return list[0].style[name] || '';
				}
				list[0].style[name] = value;
				return api;
			},
			show: function () {
				list.forEach(function (el) {
					el.style.display = '';
				});
				return api;
			},
			hide: function () {
				list.forEach(function (el) {
					el.style.display = 'none';
				});
				return api;
			},
			empty: function () {
				list.forEach(function (el) {
					while (el.children.length) {
						el.removeChild(el.children[0]);
					}
				});
				return api;
			},
			append: function (child) {
				const nodesToAdd = child && child.jquery ? child._list : [child];
				list.forEach(function (el) {
					nodesToAdd.forEach(function (node) {
						el.appendChild(node);
					});
				});
				return api;
			},
			after: function (child) {
				const nodesToAdd = child && child.jquery ? child._list : [child];
				list.forEach(function (el) {
					if (!el.parentNode) {
						return;
					}
					const siblings = el.parentNode.children;
					const index = siblings.indexOf(el);
					const before = siblings[index + 1] || null;
					nodesToAdd.forEach(function (node) {
						el.parentNode.insertBefore(node, before);
					});
				});
				return api;
			},
			remove: function () {
				list.forEach(function (el) {
					if (el.parentNode) {
						el.parentNode.removeChild(el);
					}
				});
				return api;
			},
			on: function (event, fn) {
				list.forEach(function (el) {
					el._handlers[event] = el._handlers[event] || [];
					el._handlers[event].push(fn);
				});
				return api;
			},
			off: function (event) {
				list.forEach(function (el) {
					delete el._handlers[event];
				});
				return api;
			},
			trigger: function (event) {
				const name = String(event || '').split('.')[0];
				list.forEach(function (el) {
					Object.keys(el._handlers).forEach(function (key) {
						if (key.split('.')[0] === name) {
							el._handlers[key].forEach(function (fn) {
								fn.call(el, { preventDefault: function () {} });
							});
						}
					});
				});
				return api;
			},
			addClass: function () {
				return api;
			},
			data: function () {
				return undefined;
			},
			hasClass: function (name) {
				return list.some(function (el) {
					return String(el.className || '').split(/\s+/).indexOf(name) !== -1;
				});
			},
		};
		for (let i = 0; i < list.length; i++) {
			api[i] = list[i];
		}
		return api;
	}

	function $(selector) {
		if (selector && selector.jquery) {
			return selector;
		}
		if (typeof selector === 'string' && selector.charAt(0) === '<') {
			return wrap([parseHtml(selector)]);
		}
		if (selector && selector.nodeType) {
			return wrap([selector]);
		}
		if (typeof selector === 'string') {
			return wrap(queryAll(document, selector));
		}
		return wrap([]);
	}
	$.post = function () {
		return {
			done: function () {
				return this;
			},
			always: function (fn) {
				if (typeof fn === 'function') {
					fn();
				}
				return this;
			},
		};
	};

	function Emitter() {
		this._handlers = {};
	}
	Emitter.prototype.on = function (event, fn) {
		this._handlers[event] = this._handlers[event] || [];
		this._handlers[event].push(fn);
		return this;
	};
	Emitter.prototype.trigger = function (event) {
		const args = Array.prototype.slice.call(arguments, 1);
		(this._handlers[event] || []).forEach(function (fn) {
			fn.apply(null, args);
		});
	};

	const actions = {};
	const elementor = {
		hooks: {
			addAction: function (name, fn) {
				actions[name] = actions[name] || [];
				actions[name].push(fn);
			},
			doAction: function (name) {
				const args = Array.prototype.slice.call(arguments, 1);
				(actions[name] || []).forEach(function (fn) {
					fn.apply(null, args);
				});
			},
		},
		channels: { editor: new Emitter() },
		on: function (event, fn) {
			this._handlers = this._handlers || {};
			this._handlers[event] = this._handlers[event] || [];
			this._handlers[event].push(fn);
		},
		trigger: function (event) {
			const args = Array.prototype.slice.call(arguments, 1);
			((this._handlers || {})[event] || []).forEach(function (fn) {
				fn.apply(null, args);
			});
		},
	};

	const window = {
		elementor: elementor,
		rwgcElementorLibrary: libraryConfig,
		rwgcGeoCountryOptions: {},
		nodeType: 1,
		_handlers: {},
	};
	window.window = window;
	window.document = document;

	function MutationObserver(callback) {
		this.callback = callback;
		this.root = null;
	}
	MutationObserver.prototype.observe = function (root) {
		this.root = root;
		observers.push(this);
	};
	MutationObserver.prototype.disconnect = function () {};

	const context = {
		window: window,
		document: document,
		elementor: elementor,
		jQuery: $,
		$: $,
		MutationObserver: MutationObserver,
		WeakSet: WeakSet,
		setTimeout: setTimeout,
		clearTimeout: clearTimeout,
		console: console,
	};
	context.global = context;
	vm.createContext(context);
	vm.runInContext(src, context);

	function option(value, label) {
		const el = new El('option');
		el.value = value;
		el.attrs.value = value;
		el.textContent = label;
		return el;
	}

	function mountPanel(savedId, mode) {
		const shell = new El('div');
		shell.id = 'elementor-panel';
		document.body.appendChild(shell);
		const inner = new El('div');
		inner.id = 'elementor-panel-inner';
		shell.appendChild(inner);
		const control = new El('div');
		control.className = 'elementor-control elementor-control-rwgc_visibility_rule_library';
		inner.appendChild(control);
		const select = new El('select');
		select.attrs['data-setting'] = 'rwgc_visibility_rule_library';
		select.appendChild(option('', '— Choose saved visibility rule —'));
		select.appendChild(option('500', 'Published rule'));
		select.appendChild(option('13797', 'QA PR72 hide-if'));
		select.value = savedId === '13797' ? '13797' : '';
		control.appendChild(select);
		const appliedWrap = new El('div');
		appliedWrap.className = 'elementor-control-rwgc_applied_visibility_rule_id';
		const applied = new El('input');
		applied.value = savedId;
		appliedWrap.appendChild(applied);
		inner.appendChild(appliedWrap);
		const modeWrap = new El('div');
		modeWrap.className = 'elementor-control-rwgc_visibility_rules_mode';
		const modeSelect = new El('select');
		modeSelect.attrs['data-setting'] = 'rwgc_visibility_rules_mode';
		modeSelect.appendChild(option('show_if', 'Show'));
		modeSelect.appendChild(option('hide_if', 'Hide'));
		modeSelect.value = mode || 'show_if';
		modeWrap.appendChild(modeSelect);
		inner.appendChild(modeWrap);
		return { inner: inner, select: select };
	}

	return {
		window: window,
		document: document,
		elementor: elementor,
		actions: actions,
		observers: observers,
		mountPanel: mountPanel,
		query: function (selector) {
			return queryAll(document, selector);
		},
		mutations: function () {
			return mutationCount;
		},
		resetMutations: function () {
			mutationCount = 0;
		},
	};
}

function libraryConfig() {
	return {
		library: [
			{ id: '500', title: 'Published rule', status: 'publish', json: '{}' },
			{ id: '13797', title: 'QA PR72 hide-if', status: 'draft', json: '{}' },
		],
		countries: {},
		labels: {
			choosePlaceholder: '— Choose saved visibility rule —',
			compatibleGroup: 'Compatible rules',
			attentionGroup: 'Needs attention',
			unavailableGroup: 'Not available for this context',
			missingRuleOption: 'Rule #',
			unpublishedSuffix: ' (unpublished)',
			deletedSuffix: ' (deleted)',
			clearRule: 'Clear rule',
			pickAnother: 'Choose another saved rule above, or clear this reference.',
			missingShowIf: 'The rule #%s was deleted or is unpublished. This content is now hidden for everyone.',
			missingHideIf: 'The rule #%s was deleted or is unpublished. This content is now never hidden.',
			missingVariant: 'The rule #%s was deleted or is unpublished. Visitors see the default page.',
		},
		ruleStatuses: {
			500: {
				id: 500,
				status: 'published',
				title: 'Published rule',
				resolvable: true,
				page_variant: false,
			},
			13797: {
				id: 13797,
				status: 'draft',
				title: 'QA PR72 hide-if',
				resolvable: false,
				page_variant: false,
				messages: {
					hide_if: "The rule 'QA PR72 hide-if' was deleted or is unpublished. This content is now never hidden.",
				},
			},
			13799: {
				id: 13799,
				status: 'deleted',
				title: '',
				resolvable: false,
				page_variant: false,
				messages: {
					show_if: 'The rule #13799 was deleted or is unpublished. This content is now hidden for everyone.',
				},
			},
			13800: {
				id: 13800,
				status: 'trashed',
				title: 'QA variant',
				resolvable: false,
				page_variant: true,
				messages: {
					variant: "The rule 'QA variant' was deleted or is unpublished. Visitors see the default page.",
				},
			},
		},
		statusLookup: {},
	};
}

function wait(ms) {
	return new Promise(function (resolve) {
		setTimeout(resolve, ms);
	});
}

function noticeText(harness) {
	const notices = harness.query('.rwgc-library-rule-status [role="alert"]');
	assert(notices.length === 1, 'expected one stale-rule notice, found ' + notices.length);
	return notices[0].textContent;
}

function findOption(select, value) {
	let found = null;
	function walk(node) {
		(node.children || []).forEach(function (child) {
			if (child.tagName === 'OPTION' && child.value === value) {
				found = child;
			}
			walk(child);
		});
	}
	walk(select);
	return found;
}

async function main() {
	const deleted = createHarness(libraryConfig());
	assert(
		deleted.observers.length === 0,
		'observer must not attach before #elementor-panel exists'
	);
	assert(
		typeof deleted.actions['panel/open_editor/widget'] !== 'undefined',
		'widget panel hook is registered at init'
	);
	['section', 'column', 'container'].forEach(function (type) {
		assert(
			typeof deleted.actions['panel/open_editor/' + type] !== 'undefined',
			type + ' panel hook is registered at init'
		);
	});
	await wait(120);
	assert(deleted.query('.rwgc-library-rule-status').length === 0, 'no notice before the panel exists');
	assert(deleted.observers.length === 0, 'a missing panel must not be observed');

	const deletedPanel = deleted.mountPanel('13799', 'show_if');
	deleted.elementor.hooks.doAction('panel/open_editor/widget', { on: function () {} });
	await wait(200);
	assert(
		noticeText(deleted).indexOf('The rule #13799 was deleted or is unpublished') !== -1,
		'deleted rule notice renders after panel/open_editor/widget'
	);
	const deletedOption = findOption(deletedPanel.select, '13799');
	assert(deletedOption && deletedOption.getAttribute('data-rwgc-stale') === '1', 'deleted id stays selected');
	assert(deletedOption.textContent === 'Rule #13799 (deleted)', 'deleted option is labelled Rule #<id> (deleted)');
	assert(deletedPanel.select.value === '13799', 'select value remains the deleted rule id');
	const published = findOption(deletedPanel.select, '500');
	assert(published && published.getAttribute('data-rwgc-stale') !== '1', 'published rule stays a normal option');
	const draftAsNormal = findOption(deletedPanel.select, '13797');
	assert(
		!draftAsNormal || draftAsNormal.getAttribute('data-rwgc-stale') === '1',
		'draft rule is not a normal option while another rule is selected'
	);

	deleted.resetMutations();
	deleted.observers.forEach(function (observer) {
		observer.callback();
	});
	await wait(200);
	assert(deleted.mutations() < 15, 'redrawing an unchanged notice must not loop, mutations=' + deleted.mutations());
	assert(deleted.query('.rwgc-library-rule-status').length === 1, 'notice stays a single node');

	const draft = createHarness(libraryConfig());
	await wait(120);
	const draftPanel = draft.mountPanel('13797', 'hide_if');
	draft.elementor.channels.editor.trigger('section:activated', 'rwgc_visibility');
	await wait(200);
	assert(
		noticeText(draft).indexOf('never hidden') !== -1,
		'section:activated renders the hide-if notice after the panel is created'
	);
	const draftOption = findOption(draftPanel.select, '13797');
	assert(draftOption && draftOption.getAttribute('data-rwgc-stale') === '1', 'draft rule is only the stale option');
	assert(
		draftOption.textContent === 'QA PR72 hide-if (unpublished)',
		'draft option is labelled with (unpublished)'
	);
	assert(draftPanel.select.value === '13797', 'draft id stays selected');
	assert(findOption(draftPanel.select, '500'), 'published rule remains selectable');

	const variant = createHarness(libraryConfig());
	await wait(120);
	const variantPanel = variant.mountPanel('13800', 'show_if');
	variant.elementor.trigger('preview:loaded');
	await wait(200);
	assert(
		noticeText(variant).indexOf('Visitors see the default page') !== -1,
		'preview:loaded renders the page-variant notice'
	);
	const variantOption = findOption(variantPanel.select, '13800');
	assert(
		variantOption && variantOption.textContent === 'QA variant (unpublished)',
		'trashed variant is labelled unpublished, not dropped'
	);
	assert(variantPanel.select.value === '13800', 'trashed variant id stays selected');

	const publishedCase = createHarness(libraryConfig());
	await wait(120);
	const publishedPanel = publishedCase.mountPanel('500', 'show_if');
	publishedPanel.select.value = '500';
	publishedCase.elementor.hooks.doAction('panel/open_editor/section', { on: function () {} });
	await wait(200);
	assert(
		publishedCase.query('.rwgc-library-rule-status [role="alert"]').length === 0,
		'a published rule does not show the stale notice'
	);
	const publishedOption = findOption(publishedPanel.select, '500');
	assert(
		publishedOption && publishedOption.getAttribute('data-rwgc-stale') !== '1',
		'published rule is a normal selected option'
	);
	assert(publishedPanel.select.value === '500', 'published id stays selected');

	console.log('OK: elementor stale-rule notice attaches after the panel is created');
}

main().catch(function (err) {
	console.error(err && err.stack ? err.stack : err);
	process.exit(1);
});
