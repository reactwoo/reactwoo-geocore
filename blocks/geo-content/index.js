(function (wp) {
	const { registerBlockType } = wp.blocks;
	const { ComboboxControl, Button, SelectControl, TextareaControl } = wp.components;
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
		const portable = typeof attrs.portableTargeting === 'string' ? attrs.portableTargeting : '';
		const [comboKey, setComboKey] = useState(0);
		const [assistAudKey, setAssistAudKey] = useState(0);
		const [assistCmpKey, setAssistCmpKey] = useState(0);

		const assist =
			typeof window !== 'undefined' && window.rwgcPortableTargetingAssist
				? window.rwgcPortableTargetingAssist
				: { audiences: [], campaigns: [], pro: false };

		function buildPortableRule(type, token, mode) {
			const cond =
				type === 'audience'
					? { type: 'audience', operator: 'in', value: [String(token)] }
					: { type: 'campaign', operator: 'in', value: [String(token)] };
			return JSON.stringify(
				{
					schema_version: 1,
					enabled: true,
					mode: mode || 'show',
					match: 'any',
					rules: [
						{
							id: type === 'audience' ? 'rule_audience' : 'rule_campaign',
							label: '',
							match: 'all',
							conditions: [cond],
						},
					],
				},
				null,
				2
			);
		}

		function onInsertAudience(audienceId) {
			if (!audienceId) {
				return;
			}
			setAttr('portableTargeting', buildPortableRule('audience', audienceId, attrs.mode || 'show'));
			setAssistAudKey(function (k) {
				return k + 1;
			});
		}

		function onInsertCampaign(cmp) {
			if (!cmp) {
				return;
			}
			setAttr('portableTargeting', buildPortableRule('campaign', cmp, attrs.mode || 'show'));
			setAssistCmpKey(function (k) {
				return k + 1;
			});
		}

		const audienceAssistOptions = [
			{ label: __('— Pick synced audience —', 'reactwoo-geocore'), value: '' },
		].concat(
			(Array.isArray(assist.audiences) ? assist.audiences : []).map(function (a) {
				return {
					label: (a.name || a.id) + ' (' + (a.id || '') + ')',
					value: String(a.id || ''),
				};
			})
		);

		const campaignAssistOptions = [
			{ label: __('— Pick synced campaign —', 'reactwoo-geocore'), value: '' },
		].concat(
			(Array.isArray(assist.campaigns) ? assist.campaigns : []).map(function (c) {
				var tok = c.name && String(c.name) !== '' ? String(c.name) : String(c.id || '');
				return { label: tok, value: tok };
			})
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
					wp.element.createElement(TextareaControl, {
						label: __('Portable targeting (JSON)', 'reactwoo-geocore'),
						help: __(
							'Optional. When non-empty and valid, this overrides the country list below. Same schema as Geo Core portable rules (enabled, mode, match, rules).',
							'reactwoo-geocore'
						),
						value: portable,
						rows: 8,
						onChange: function (v) {
							setAttr('portableTargeting', v || '');
						},
					}),
					assist.pro && audienceAssistOptions.length > 1
						? wp.element.createElement(SelectControl, {
								key: 'rwgc-aud-assist-' + assistAudKey,
								label: __('Insert synced audience rule', 'reactwoo-geocore'),
								value: '',
								options: audienceAssistOptions,
								onChange: onInsertAudience,
						  })
						: null,
					assist.pro && campaignAssistOptions.length > 1
						? wp.element.createElement(SelectControl, {
								key: 'rwgc-cmp-assist-' + assistCmpKey,
								label: __('Insert synced Ads campaign rule', 'reactwoo-geocore'),
								value: '',
								options: campaignAssistOptions,
								onChange: onInsertCampaign,
						  })
						: null,
					assist.pro && audienceAssistOptions.length <= 1 && campaignAssistOptions.length <= 1
						? wp.element.createElement(
								'p',
								{ className: 'components-base-control__help' },
								__(
									'GeoCore Pro: sync audiences/campaigns under GeoCore Pro → Integrations to enable quick-insert here.',
									'reactwoo-geocore'
								)
						  )
						: null,
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
						'Geo Content — inner blocks render when rules match: portable JSON above overrides country rules when set.',
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
