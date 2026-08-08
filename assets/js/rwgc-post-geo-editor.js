( function ( wp, config ) {
	if ( ! wp || ! wp.plugins || ! wp.editPost || ! config ) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var useState = wp.element.useState;
	var useRef = wp.element.useRef;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var ComboboxControl = wp.components.ComboboxControl;
	var Button = wp.components.Button;
	var useEffect = wp.element.useEffect;

	function PostGeoPanel() {
		var meta = config.meta || {};
		var metaValues = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;
		var metaValuesRef = useRef( metaValues );
		metaValuesRef.current = metaValues;
		var rbMountedRef = useRef( false );

		var countryOn = metaValues[ meta.countryEnabled ] === 'yes';
		var visibilityOn = metaValues[ meta.visibilityEnabled ] === 'yes';
		var legacyOn = metaValues[ meta.enabled ] === 'yes';

		if ( ! countryOn && ! visibilityOn && legacyOn ) {
			if ( metaValues[ meta.usePortable ] === 'yes' ) {
				visibilityOn = true;
			} else {
				countryOn = true;
			}
		}

		var countryMode =
			metaValues[ meta.countryMode ] === 'hide_if' || metaValues[ meta.countryMode ] === 'hide'
				? 'hide_if'
				: 'show_if';
		var visibilityMode =
			metaValues[ meta.visibilityMode ] === 'hide_if' ? 'hide_if' : 'show_if';
		var countries = Array.isArray( metaValues[ meta.countries ] ) ? metaValues[ meta.countries ] : [];
		var portable = metaValues[ meta.portable ] || '';
		var visibilityModeRef = useRef( visibilityMode );
		visibilityModeRef.current = visibilityMode;

		var countryOptions = Object.keys( config.countries || {} ).map( function ( code ) {
			return {
				label: ( config.countries[ code ] || code ) + ' (' + code + ')',
				value: code,
			};
		} );

		var comboOptions = countryOptions.filter( function ( opt ) {
			return countries.indexOf( opt.value ) === -1;
		} );

		var [ comboKey, setComboKey ] = useState( 0 );

		function updateMeta( patch ) {
			var next = Object.assign( {}, metaValuesRef.current, patch );
			metaValuesRef.current = next;
			editPost( { meta: next } );
		}

		function addCountry( code ) {
			if ( ! code || countries.indexOf( code ) !== -1 ) {
				return;
			}
			updateMeta(
				( function () {
					var o = {};
					o[ meta.countries ] = countries.concat( [ code ] );
					return o;
				} )()
			);
			setComboKey( comboKey + 1 );
		}

		function removeCountry( code ) {
			updateMeta(
				( function () {
					var o = {};
					o[ meta.countries ] = countries.filter( function ( c ) {
						return c !== code;
					} );
					return o;
				} )()
			);
		}

		useEffect(
			function () {
				if ( ! config.advancedTargeting || ! visibilityOn || ! window.ReactWooRuleBuilder ) {
					return undefined;
				}
				var textarea = document.getElementById( 'rwgc-post-portable-targeting' );
				if ( ! textarea || textarea.getAttribute( 'data-rwgc-rb-mounted' ) ) {
					return undefined;
				}
				window.ReactWooRuleBuilder.mount( {
					textarea: textarea,
					getMode: function () {
						return visibilityModeRef.current === 'hide_if' ? 'hide_if' : 'show_if';
					},
					onChange: function ( json ) {
						if ( metaValuesRef.current[ meta.portable ] === json ) {
							return;
						}
						var patch = {};
						patch[ meta.portable ] = typeof json === 'string' ? json : '';
						updateMeta( patch );
					},
					allowAllConditionTypes: true,
				} );
				rbMountedRef.current = true;
				return undefined;
			},
			[ config.advancedTargeting, visibilityOn ]
		);

		useEffect(
			function () {
				if ( ! visibilityOn || ! rbMountedRef.current || ! window.ReactWooRuleBuilder ) {
					return;
				}
				var textarea = document.getElementById( 'rwgc-post-portable-targeting' );
				if ( ! textarea ) {
					return;
				}
				if ( typeof window.ReactWooRuleBuilder.setValue === 'function' ) {
					window.ReactWooRuleBuilder.setValue( textarea, portable );
				}
			},
			[ portable, visibilityOn ]
		);

		var active = countryOn || visibilityOn;

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'rwgc-post-geo',
				title: 'Geo visibility',
				className: 'rwgc-post-geo-panel',
			},
			el( 'p', { className: 'description' }, 'Match Elementor page Geo Visibility: search and add countries; visibility rules are a separate layer.' ),
			el( 'p', { style: { fontWeight: 600, marginBottom: 4 } }, 'Country targeting' ),
			el( ToggleControl, {
				label: 'Enable country targeting',
				checked: countryOn,
				onChange: function ( val ) {
					var patch = {};
					patch[ meta.countryEnabled ] = val ? 'yes' : '';
					updateMeta( patch );
				},
			} ),
			countryOn &&
				el( SelectControl, {
					label: 'Country visibility',
					value: countryMode,
					options: [
						{ label: 'Show only when country matches', value: 'show_if' },
						{ label: 'Hide when country matches', value: 'hide_if' },
					],
					onChange: function ( val ) {
						var patch = {};
						patch[ meta.countryMode ] = val;
						updateMeta( patch );
					},
				} ),
			countryOn &&
				el(
					Fragment,
					null,
					el( 'p', { className: 'components-base-control__help' }, 'Search the list and pick countries to add.' ),
					el( ComboboxControl, {
						key: 'rwgc-post-combo-' + comboKey,
						label: 'Add country',
						options: comboOptions,
						value: null,
						onChange: addCountry,
					} ),
					countries.length > 0 &&
						el(
							'ul',
							{
								className: 'rwgc-selected-countries',
								style: { listStyle: 'none', paddingLeft: 0, marginTop: '8px' },
							},
							countries.map( function ( code ) {
								return el(
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
									el( 'span', null, el( 'strong', null, code ), ' — ', config.countries[ code ] || code ),
									el( Button, {
										isSmall: true,
										isDestructive: true,
										onClick: function () {
											removeCountry( code );
										},
									}, 'Remove' )
								);
							} )
						)
				),
			config.advancedTargeting &&
				el(
					Fragment,
					null,
					el( 'p', { style: { fontWeight: 600, margin: '12px 0 4px' } }, 'Visibility rules' ),
					el( ToggleControl, {
						label: 'Enable visibility rules',
						checked: visibilityOn,
						onChange: function ( val ) {
							var patch = {};
							patch[ meta.visibilityEnabled ] = val ? 'yes' : '';
							patch[ meta.usePortable ] = val ? 'yes' : '';
							updateMeta( patch );
						},
					} ),
					visibilityOn &&
						el( SelectControl, {
							label: 'Visibility rules mode',
							value: visibilityMode,
							options: [
								{ label: 'Show only when rules match', value: 'show_if' },
								{ label: 'Hide when rules match', value: 'hide_if' },
							],
							onChange: function ( val ) {
								var patch = {};
								patch[ meta.visibilityMode ] = val;
								updateMeta( patch );
							},
						} ),
					visibilityOn &&
						el( 'textarea', {
							id: 'rwgc-post-portable-targeting',
							className: 'rwgc-rb-textarea-hidden',
							style: { display: 'none' },
							value: portable,
							onChange: function ( e ) {
								var patch = {};
								patch[ meta.portable ] = e.target.value;
								updateMeta( patch );
							},
						} )
				),
			active &&
				el(
					'p',
					{ className: 'description', style: { marginTop: '8px' } },
					'When both layers are on, the visitor must pass country rules and visibility rules (AND).'
				)
		);
	}

	registerPlugin( 'rwgc-post-geo', {
		render: PostGeoPanel,
		icon: 'location',
	} );
} )( window.wp, window.rwgcPostGeoEditor || {} );
