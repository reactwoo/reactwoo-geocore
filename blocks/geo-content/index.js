(function (wp) {
	const { registerBlockType } = wp.blocks;
	const { ComboboxControl, Button, Notice, SelectControl, TextareaControl, ToggleControl } = wp.components;
	const blockEditor = wp.blockEditor || wp.editor;
	const { useBlockProps, InspectorControls, InnerBlocks } = blockEditor;
	const { Fragment, useState, useEffect, useRef, useCallback } = wp.element;
	const { __ } = wp.i18n;

	function rwgcGeoContentAdvancedEnabled(config) {
		if (!config || typeof config !== 'object') {
			return false;
		}
		return !!(config.advancedTargeting || config.advanced_targeting);
	}

	function rwgcGeoContentMountRuleBuilder(node, env) {
		env = env || {};
		var builder = env.builder;
		if (!env.visibilityOn || !node || !builder || typeof builder.mount !== 'function') {
			return false;
		}
		var ta = typeof node.querySelector === 'function' ? node.querySelector('textarea') : null;
		if (!ta) {
			return false;
		}
		if (ta.getAttribute('data-rwgc-rb-mounted')) {
			return true;
		}
		builder.mount({
			textarea: ta,
			getMode:
				typeof env.getMode === 'function'
					? env.getMode
					: function () {
							return 'show_if';
					  },
			onChange: env.onChange,
		});
		return ta.getAttribute('data-rwgc-rb-mounted') === '1';
	}

	function rwgcGeoContentScheduleRuleBuilder(state) {
		if (!state || !state.isSelected) {
			return false;
		}
		return rwgcGeoContentMountRuleBuilder(state.node, state);
	}

	function GeoContentEdit(props) {
		const attrs = props.attributes;
		const setAttr = function (key, val) {
			const o = {};
			o[key] = val;
			props.setAttributes(o);
		};
		const attrsRef = useRef(attrs);
		attrsRef.current = attrs;
		const rbWrapRef = useRef(null);
		const rbMountedRef = useRef(false);

		const countryMap =
			typeof window !== 'undefined' && window.rwgcGeoCountryOptions
				? window.rwgcGeoCountryOptions
				: {};
		const codes = Object.keys(countryMap).sort();
		const comboOptions = codes.map(function (code) {
			return { label: countryMap[code] + ' (' + code + ')', value: code };
		});
		const countryOn =
			!!attrs.enableCountryTargeting ||
			(Array.isArray(attrs.showCountries) && attrs.showCountries.length > 0);
		const visibilityOn =
			!!attrs.enableVisibilityRules ||
			!!attrs.usePortableTargeting ||
			(typeof attrs.portableTargeting === 'string' && attrs.portableTargeting.trim() !== '');
		const selected = Array.isArray(attrs.showCountries) ? attrs.showCountries : [];
		const portable = typeof attrs.portableTargeting === 'string' ? attrs.portableTargeting : '';
		const advanced = rwgcGeoContentAdvancedEnabled(
			typeof window !== 'undefined' ? window.rwgcPortableTargetingAssist : null
		);
		const [comboKey, setComboKey] = useState(0);
		const ruleId = String(attrs.visibilityRuleLibrary || attrs.appliedVisibilityRuleId || '');
		const statusBoot =
			typeof window !== 'undefined' && window.rwgcVisibilityRuleStatus
				? window.rwgcVisibilityRuleStatus
				: { rules: {}, lookup: null };
		const [statusMap, setStatusMap] = useState(statusBoot.rules || {});
		const libraryRows =
			typeof window !== 'undefined' &&
			window.rwgcPortableTargetingAssist &&
			Array.isArray(window.rwgcPortableTargetingAssist.visibility_library)
				? window.rwgcPortableTargetingAssist.visibility_library
				: [];

		useEffect(
			function () {
				if (!ruleId || statusMap[ruleId] || !statusBoot.lookup || !window.fetch) {
					return undefined;
				}
				var cancelled = false;
				var body = new window.URLSearchParams();
				body.set('action', statusBoot.lookup.action || '');
				body.set('nonce', statusBoot.lookup.nonce || '');
				body.set('ids', ruleId);
				window
					.fetch(statusBoot.lookup.ajaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
						body: body.toString(),
					})
					.then(function (response) {
						return response.json();
					})
					.then(function (payload) {
						if (cancelled || !payload || !payload.success || !payload.data) {
							return;
						}
						setStatusMap(function (prev) {
							return Object.assign({}, prev, payload.data.rules || {});
						});
					})
					.catch(function () {
						if (cancelled) {
							return;
						}
						setStatusMap(function (prev) {
							if (prev[ruleId]) {
								return prev;
							}
							var next = Object.assign({}, prev);
							next[ruleId] = {
								id: ruleId,
								status: 'deleted',
								title: '',
								resolvable: false,
								page_variant: false,
							};
							return next;
						});
					});
				return function () {
					cancelled = true;
				};
			},
			[ruleId]
		);

		function applyLibraryRule(nextId) {
			props.setAttributes({
				visibilityRuleLibrary: nextId || '',
				appliedVisibilityRuleId: nextId || '',
			});
		}

		function ruleWarningText(row) {
			var key = row && row.page_variant ? 'variant' : visibilityMode === 'hide_if' ? 'hide_if' : 'show_if';
			if (row && row.messages && row.messages[key]) {
				return row.messages[key];
			}
			var who = __('The rule #%s was deleted or is unpublished.', 'reactwoo-geocore').replace('%s', ruleId);
			if (key === 'variant') {
				return who + ' ' + __('Visitors see the default page.', 'reactwoo-geocore');
			}
			if (key === 'hide_if') {
				return who + ' ' + __('This content is now never hidden.', 'reactwoo-geocore');
			}
			return who + ' ' + __('This content is now hidden for everyone.', 'reactwoo-geocore');
		}

		function ruleWarningElement() {
			if (!ruleId) {
				return null;
			}
			var row = statusMap[ruleId];
			if (!row || row.resolvable) {
				return null;
			}
			var options = [
				{
					label: __('Choose another saved rule', 'reactwoo-geocore'),
					value: '',
				},
			];
			libraryRows.forEach(function (item) {
				if (!item || !item.id) {
					return;
				}
				var itemStatus = statusMap[String(item.id)];
				if (itemStatus && !itemStatus.resolvable) {
					return;
				}
				options.push({
					label: item.title || __('Rule #', 'reactwoo-geocore') + item.id,
					value: String(item.id),
				});
			});
			if (
				!options.some(function (option) {
					return option.value === ruleId;
				})
			) {
				options.push({
					label: (row.title ? row.title : __('Rule #', 'reactwoo-geocore') + ruleId),
					value: ruleId,
				});
			}
			return wp.element.createElement(
				'div',
				{ className: 'rwgc-library-rule-status', style: { margin: '8px 0 12px' } },
				Notice
					? wp.element.createElement(
							Notice,
							{ status: 'warning', isDismissible: false },
							ruleWarningText(row)
					  )
					: wp.element.createElement('p', { role: 'alert' }, ruleWarningText(row)),
				wp.element.createElement(
					'p',
					{ className: 'components-base-control__help' },
					__(
						'Choose another saved rule, or clear this reference. It stays saved until you do.',
						'reactwoo-geocore'
					)
				),
				wp.element.createElement(SelectControl, {
					label: __('Apply saved visibility rule', 'reactwoo-geocore'),
					value: ruleId,
					options: options,
					onChange: function (value) {
						if (!value || value === ruleId) {
							return;
						}
						applyLibraryRule(value);
					},
				}),
				wp.element.createElement(
					Button,
					{
						variant: 'secondary',
						isSecondary: true,
						onClick: function () {
							applyLibraryRule('');
						},
					},
					__('Clear rule', 'reactwoo-geocore')
				)
			);
		}

		const countryMode =
			attrs.countryVisibilityMode === 'hide_if' || attrs.mode === 'hide'
				? 'hide_if'
				: 'show_if';
		const visibilityMode =
			attrs.visibilityRulesMode === 'hide_if' ? 'hide_if' : 'show_if';

		var ruleBuilderState = useCallback(
			function (node) {
				return {
					visibilityOn: visibilityOn,
					isSelected: !!props.isSelected,
					node: node || rbWrapRef.current,
					builder: typeof window !== 'undefined' ? window.ReactWooRuleBuilder : null,
					getMode: function () {
						var m = attrsRef.current.visibilityRulesMode || 'show_if';
						return m === 'hide_if' ? 'hide_if' : 'show_if';
					},
					onChange: function (json) {
						if (attrsRef.current.portableTargeting !== json) {
							setAttr('portableTargeting', json);
						}
					},
				};
			},
			[visibilityOn, props.isSelected]
		);

		var setRuleBuilderNode = useCallback(
			function (node) {
				rbWrapRef.current = node || null;
				if (rwgcGeoContentScheduleRuleBuilder(ruleBuilderState(node))) {
					rbMountedRef.current = true;
				}
			},
			[ruleBuilderState]
		);

		useEffect(
			function () {
				if (rwgcGeoContentScheduleRuleBuilder(ruleBuilderState(rbWrapRef.current))) {
					rbMountedRef.current = true;
				}
			},
			[props.isSelected, visibilityOn, ruleBuilderState]
		);

		useEffect(
			function () {
				if (!visibilityOn || !rbMountedRef.current) {
					return;
				}
				var ta = rbWrapRef.current && rbWrapRef.current.querySelector('textarea');
				if (!ta || !window.ReactWooRuleBuilder) {
					return;
				}
				if (typeof window.ReactWooRuleBuilder.setValue === 'function') {
					window.ReactWooRuleBuilder.setValue(ta, portable);
				} else if (ta.value !== portable) {
					ta.value = portable;
					if (window.jQuery) {
						window.jQuery(ta).trigger('input.rwgcRb');
					}
				}
			},
			[portable, visibilityOn]
		);

		function addCode(code) {
			if (!code) {
				return;
			}
			const upper = String(code).toUpperCase();
			if (selected.indexOf(upper) !== -1) {
				return;
			}
			setAttr('showCountries', selected.concat([upper]));
			setComboKey(function (k) {
				return k + 1;
			});
		}

		function removeCode(code) {
			setAttr(
				'showCountries',
				selected.filter(function (c) {
					return c !== code;
				})
			);
		}

		const blockProps = useBlockProps({ className: 'rwgc-geo-content-block' });

		return wp.element.createElement(
			Fragment,
			{},
			wp.element.createElement(
				InspectorControls,
				null,
				wp.element.createElement(
					'div',
					{ className: 'rwgc-panel', style: { padding: '12px' } },
					wp.element.createElement('hr', null),
					!advanced || !visibilityOn ? ruleWarningElement() : null,
					wp.element.createElement('p', { style: { fontWeight: 600, marginBottom: 4 } }, __('Country targeting', 'reactwoo-geocore')),
					wp.element.createElement(ToggleControl, {
						label: __('Enable country targeting', 'reactwoo-geocore'),
						checked: countryOn,
						onChange: function (v) {
							setAttr('enableCountryTargeting', !!v);
						},
					}),
					countryOn
						? wp.element.createElement(SelectControl, {
								label: __('Country visibility', 'reactwoo-geocore'),
								value: countryMode,
								options: [
									{ label: __('Show only when country matches', 'reactwoo-geocore'), value: 'show_if' },
									{ label: __('Hide when country matches', 'reactwoo-geocore'), value: 'hide_if' },
								],
								onChange: function (v) {
									setAttr('countryVisibilityMode', v);
								},
						  })
						: null,
					countryOn
						? wp.element.createElement(
								Fragment,
								null,
								wp.element.createElement(
									'p',
									{ className: 'components-base-control__help' },
									__(
										'Search the list and pick countries to add. No comma-separated typing.',
										'reactwoo-geocore'
									)
								),
								wp.element.createElement(ComboboxControl, {
									key: 'rwgc-combo-' + comboKey,
									label: __('Add country', 'reactwoo-geocore'),
									options: comboOptions,
									value: '',
									onChange: addCode,
								}),
								wp.element.createElement(
									'ul',
									{
										className: 'rwgc-selected-countries',
										style: { listStyle: 'none', paddingLeft: 0, marginTop: '12px' },
									},
									selected.map(function (code) {
										return wp.element.createElement(
											'li',
											{
												key: code,
												style: {
													display: 'flex',
													alignItems: 'center',
													gap: '8px',
													marginBottom: '6px',
													flexWrap: 'wrap',
												},
											},
											wp.element.createElement(
												'span',
												null,
												wp.element.createElement('strong', null, code),
												' — ',
												countryMap[code] || code
											),
											wp.element.createElement(
												Button,
												{
													isSmall: true,
													isDestructive: true,
													onClick: function () {
														removeCode(code);
													},
												},
												__('Remove', 'reactwoo-geocore')
											)
										);
									})
								)
						  )
						: null,
					advanced
						? wp.element.createElement(
								Fragment,
								null,
								wp.element.createElement('hr', null),
								wp.element.createElement('p', { style: { fontWeight: 600, marginBottom: 4 } }, __('Visibility rules', 'reactwoo-geocore')),
								wp.element.createElement(ToggleControl, {
									label: __('Enable visibility rules', 'reactwoo-geocore'),
									help: __('Independent of country targeting above.', 'reactwoo-geocore'),
									checked: visibilityOn,
									onChange: function (v) {
										setAttr('enableVisibilityRules', !!v);
										setAttr('usePortableTargeting', !!v);
									},
								}),
								visibilityOn
									? wp.element.createElement(SelectControl, {
											label: __('Visibility rules mode', 'reactwoo-geocore'),
											value: visibilityMode,
											options: [
												{ label: __('Show only when rules match', 'reactwoo-geocore'), value: 'show_if' },
												{ label: __('Hide when rules match', 'reactwoo-geocore'), value: 'hide_if' },
											],
											onChange: function (v) {
												setAttr('visibilityRulesMode', v);
											},
									  })
									: null,
								visibilityOn ? ruleWarningElement() : null,
								visibilityOn
									? wp.element.createElement(
											'div',
											{ ref: setRuleBuilderNode, className: 'rwgc-rb-mount-wrap' },
											wp.element.createElement(TextareaControl, {
												label: __('Visibility rules', 'reactwoo-geocore'),
												value: portable,
												rows: 4,
												className: 'rwgc-geo-portable-textarea',
												onChange: function (v) {
													setAttr('portableTargeting', v || '');
												},
											})
									  )
									: null
						  )
						: null
				)
			),
			wp.element.createElement(
				'div',
				blockProps,
				wp.element.createElement(
					'p',
					{ className: 'rwgc-geo-content-block__hint' },
					countryOn || visibilityOn
						? __('Geo Content — inner blocks use country and/or visibility rules above.', 'reactwoo-geocore')
						: __('Geo Content — enable country or visibility rules in the sidebar.', 'reactwoo-geocore')
				),
				InnerBlocks
					? wp.element.createElement(InnerBlocks, {
							templateLock: false,
							renderAppender: InnerBlocks.ButtonBlockAppender,
					  })
					: null
			)
		);
	}

	registerBlockType('reactwoo-geocore/geo-content', {
		edit: GeoContentEdit,
		save: function () {
			return InnerBlocks ? wp.element.createElement(InnerBlocks.Content, null) : null;
		},
		// Blocks saved before inner content used save() === null (self-closing comments).
		deprecated: [
			{
				save: function () {
					return null;
				},
			},
		],
	});
})(window.wp);
