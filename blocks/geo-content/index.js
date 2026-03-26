(function (wp) {
	const { registerBlockType } = wp.blocks;
	const { TextControl, SelectControl } = wp.components;
	const { useBlockProps, InspectorControls } = wp.blockEditor || wp.editor;
	const { Fragment } = wp.element;
	const { __ } = wp.i18n;

	registerBlockType('reactwoo-geocore/geo-content', {
		edit: function (props) {
			const attrs = props.attributes;
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
						wp.element.createElement(TextControl, {
							label: __('Countries (comma-separated ISO2 codes, e.g. US,GB,ZA)', 'reactwoo-geocore'),
							value: (attrs.showCountries || []).join(','),
							onChange: function (v) {
								const list = (v || '').split(',').map(function (c) { return c.trim().toUpperCase(); }).filter(Boolean);
								setAttr('showCountries', list);
							}
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

