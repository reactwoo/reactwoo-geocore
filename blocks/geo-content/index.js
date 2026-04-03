(function (wp) {
	const { registerBlockType } = wp.blocks;
	const { ComboboxControl, Button, SelectControl } = wp.components;
	const { useBlockProps, InspectorControls } = wp.blockEditor || wp.editor;
	const { Fragment, useState } = wp.element;
	const { __ } = wp.i18n;

	function GeoContentEdit(props) {
		const attrs = props.attributes;
		const setAttr = function (key, val) {
			const o = {};
			o[key] = val;
			props.setAttributes(o);
		};
		const countryMap =
			typeof window !== 'undefined' && window.rwgcGeoCountryOptions
				? window.rwgcGeoCountryOptions
				: {};
		const codes = Object.keys(countryMap).sort();
		const comboOptions = codes.map(function (code) {
			return { label: countryMap[code] + ' (' + code + ')', value: code };
		});
		const selected = Array.isArray(attrs.showCountries) ? attrs.showCountries : [];
		const [comboKey, setComboKey] = useState(0);

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
								wp.element.createElement(Button, {
									isSmall: true,
									isDestructive: true,
									onClick: function () {
										removeCode(code);
									},
								}, __('Remove', 'reactwoo-geocore'))
							);
						})
					)
				)
			),
			wp.element.createElement(
				'div',
				blockProps,
				wp.element.createElement(
					'p',
					null,
					__(
						'Geo Content — inner blocks render only when visitor country matches the rules above.',
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
