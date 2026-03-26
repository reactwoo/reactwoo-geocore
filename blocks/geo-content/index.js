(function (wp) {
	const { registerBlockType } = wp.blocks;
	const { SelectControl } = wp.components;
	const { useBlockProps, InspectorControls } = wp.blockEditor || wp.editor;
	const { Fragment } = wp.element;
	const { __ } = wp.i18n;

	registerBlockType('reactwoo-geocore/geo-content', {
		edit: function (props) {
			const attrs = props.attributes;
			const countryMap = (window && window.rwgcGeoCountryOptions) ? window.rwgcGeoCountryOptions : {};
			const countryOptions = Object.keys(countryMap).map(function (code) {
				return { label: countryMap[code] + ' (' + code + ')', value: code };
			});
			const setAttr = (key, value) => {
				const o = {};
				o[key] = value;
				props.setAttributes(o);
			};

			const blockProps = useBlockProps();

			return (
				Fragment,
				{},
				wp.element.createElement(
					InspectorControls,
					null,
					wp.element.createElement(
						'div',
						{ className: 'rwgc-panel' },
						wp.element.createElement(SelectControl, {
							label: __('Mode', 'reactwoo-geocore'),
							value: attrs.mode || 'show',
							options: [
								{ label: __('Show in selected countries', 'reactwoo-geocore'), value: 'show' },
								{ label: __('Hide in selected countries', 'reactwoo-geocore'), value: 'hide' }
							],
							onChange: function (v) { setAttr('mode', v); }
						}),
						wp.element.createElement(SelectControl, {
							label: __('Countries', 'reactwoo-geocore'),
							multiple: true,
							value: attrs.showCountries || [],
							options: countryOptions,
							onChange: function (v) {
								const list = Array.isArray(v) ? v : (v ? [v] : []);
								setAttr('showCountries', list);
							},
							help: __('Select one or more countries (ISO2).', 'reactwoo-geocore')
						})
					)
				),
				wp.element.createElement(
					'div',
					blockProps,
					wp.element.createElement('p', null, __('Geo Content – visible based on visitor country.', 'reactwoo-geocore')),
					props.children
				)
			);
		},
		save: function () {
			// Server-side rendered.
			return null;
		}
	});
})(window.wp);

