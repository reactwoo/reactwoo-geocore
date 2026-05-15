(function (wp) {
	const { registerBlockType } = wp.blocks;
	const { ComboboxControl, Button, SelectControl, TextareaControl, ToggleControl } = wp.components;
	const { useBlockProps, InspectorControls } = wp.blockEditor || wp.editor;
	const { Fragment, useState, useEffect, useRef } = wp.element;
	const { __ } = wp.i18n;

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
		const selected = Array.isArray(attrs.showCountries) ? attrs.showCountries : [];
		const portable = typeof attrs.portableTargeting === 'string' ? attrs.portableTargeting : '';
		const usePortable = !!attrs.usePortableTargeting;
		const [comboKey, setComboKey] = useState(0);

		useEffect(
			function () {
				if (!usePortable || !rbWrapRef.current || !window.ReactWooRuleBuilder) {
					return undefined;
				}
				var cancelled = false;
				var tries = 0;
				var id = setInterval(function () {
					if (cancelled || tries++ > 40) {
						clearInterval(id);
						return;
					}
					var ta = rbWrapRef.current && rbWrapRef.current.querySelector('textarea');
					if (ta && !ta.getAttribute('data-rwgc-rb-mounted')) {
						window.ReactWooRuleBuilder.mount({
							textarea: ta,
							getMode: function () {
								return attrsRef.current.mode || 'show';
							},
							onChange: function (json) {
								if (attrsRef.current.portableTargeting !== json) {
									setAttr('portableTargeting', json);
								}
							},
						});
						rbMountedRef.current = true;
						clearInterval(id);
					}
				}, 120);
				return function () {
					cancelled = true;
					clearInterval(id);
				};
			},
			[usePortable]
		);

		useEffect(
			function () {
				if (!usePortable || !rbMountedRef.current) {
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
			[portable, usePortable]
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
					wp.element.createElement(SelectControl, {
						label: __('Mode', 'reactwoo-geocore'),
						value: attrs.mode || 'show',
						options: [
							{
								label: __('Show in selected countries', 'reactwoo-geocore'),
								value: 'show',
							},
							{
								label: __('Hide in selected countries', 'reactwoo-geocore'),
								value: 'hide',
							},
						],
						onChange: function (v) {
							setAttr('mode', v);
						},
					}),
					wp.element.createElement(ToggleControl, {
						label: __('Use advanced visibility rules', 'reactwoo-geocore'),
						help: __(
							'When enabled, the rule builder below replaces the country list for this block.',
							'reactwoo-geocore'
						),
						checked: usePortable,
						onChange: function (v) {
							setAttr('usePortableTargeting', !!v);
						},
					}),
					usePortable
						? wp.element.createElement(
								'div',
								{ ref: rbWrapRef },
								wp.element.createElement(TextareaControl, {
									label: __('Visibility rules', 'reactwoo-geocore'),
									help: __(
										'Build conditions with the rule builder, or open advanced view to edit stored data directly.',
										'reactwoo-geocore'
									),
									value: portable,
									rows: 4,
									className: 'rwgc-geo-portable-textarea',
									onChange: function (v) {
										setAttr('portableTargeting', v || '');
									},
								})
						  )
						: wp.element.createElement(
								'p',
								{ className: 'components-base-control__help' },
								__(
									'Turn on advanced visibility rules to target by GA4 audiences, campaigns, UTM, device, and more.',
									'reactwoo-geocore'
								)
						  ),
					!usePortable
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
						: null
				)
			),
			wp.element.createElement(
				'div',
				blockProps,
				wp.element.createElement(
					'p',
					null,
					usePortable
						? __(
								'Geo Content — inner blocks render when advanced visibility rules match.',
								'reactwoo-geocore'
						  )
						: __(
								'Geo Content — inner blocks render when visitor country rules match.',
								'reactwoo-geocore'
						  )
				),
				props.children
			)
		);
	}

	registerBlockType('reactwoo-geocore/geo-content', {
		edit: GeoContentEdit,
		save: function () {
			return null;
		},
	});
})(window.wp);
