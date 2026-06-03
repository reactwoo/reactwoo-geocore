( function ( wp, config ) {
	if ( ! wp || ! wp.plugins || ! wp.editPost || ! config ) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var el = wp.element.createElement;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var PanelRow = wp.components.PanelRow;
	var useEffect = wp.element.useEffect;

	function PostGeoPanel() {
		var meta = config.meta || {};
		var metaValues = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;

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

		var countryOptions = Object.keys( config.countries || {} ).map( function ( code ) {
			return { label: config.countries[ code ], value: code };
		} );

		useEffect(
			function () {
				if ( ! config.advancedTargeting || ! visibilityOn || ! window.ReactWooRuleBuilder ) {
					return;
				}
				var textarea = document.getElementById( 'rwgc-post-portable-targeting' );
				if ( ! textarea || textarea.getAttribute( 'data-rwgc-rb-mounted' ) ) {
					return;
				}
				window.ReactWooRuleBuilder.mount( {
					textarea: textarea,
					getMode: function () {
						return visibilityMode;
					},
					allowAllConditionTypes: true,
				} );
			},
			[ config.advancedTargeting, visibilityOn, visibilityMode ]
		);

		function updateMeta( key, value ) {
			var next = Object.assign( {}, metaValues );
			next[ key ] = value;
			editPost( { meta: next } );
		}

		var active = countryOn || visibilityOn;

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'rwgc-post-geo',
				title: 'Geo visibility',
				className: 'rwgc-post-geo-panel',
			},
			el( 'p', { className: 'description' }, 'Match Elementor document Geo Visibility: country and visibility rules are independent layers.' ),
			el( 'p', { style: { fontWeight: 600, marginBottom: 4 } }, 'Country targeting' ),
			el( ToggleControl, {
				label: 'Enable country targeting',
				checked: countryOn,
				onChange: function ( val ) {
					updateMeta( meta.countryEnabled, val ? 'yes' : '' );
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
						updateMeta( meta.countryMode, val );
					},
				} ),
			countryOn &&
				el( SelectControl, {
					label: 'Countries',
					value: countries,
					multiple: true,
					options: countryOptions,
					onChange: function ( val ) {
						updateMeta( meta.countries, val || [] );
					},
				} ),
			config.advancedTargeting &&
				el(
					PanelRow,
					null,
					el( 'p', { style: { fontWeight: 600, margin: '12px 0 4px' } }, 'Visibility rules' ),
					el( ToggleControl, {
						label: 'Enable visibility rules',
						checked: visibilityOn,
						onChange: function ( val ) {
							updateMeta( meta.visibilityEnabled, val ? 'yes' : '' );
							updateMeta( meta.usePortable, val ? 'yes' : '' );
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
								updateMeta( meta.visibilityMode, val );
							},
						} ),
					visibilityOn &&
						el( 'textarea', {
							id: 'rwgc-post-portable-targeting',
							className: 'rwgc-rb-textarea-hidden',
							style: { display: 'none' },
							value: portable,
							onChange: function ( e ) {
								updateMeta( meta.portable, e.target.value );
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
