( function ( wp, config ) {
	if ( ! wp || ! wp.plugins || ! wp.editPost || ! config ) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var PanelRow = wp.components.PanelRow;

	function PostGeoPanel() {
		var meta = config.meta || {};
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );
		var metaValues = useSelect(
			function ( select ) {
				return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
			},
			[]
		);
		var editPost = useDispatch( 'core/editor' ).editPost;

		var enabled = metaValues[ meta.enabled ] === 'yes';
		var mode = metaValues[ meta.mode ] || 'show';
		var countries = Array.isArray( metaValues[ meta.countries ] ) ? metaValues[ meta.countries ] : [];
		var usePortable = metaValues[ meta.usePortable ] === 'yes';
		var portable = metaValues[ meta.portable ] || '';

		var countryOptions = Object.keys( config.countries || {} ).map( function ( code ) {
			return { label: config.countries[ code ], value: code };
		} );

		useEffect(
			function () {
				if ( ! config.advancedTargeting || ! usePortable || ! window.ReactWooRuleBuilder ) {
					return;
				}
				var textarea = document.getElementById( 'rwgc-post-portable-targeting' );
				if ( ! textarea || textarea.getAttribute( 'data-rwgc-rb-mounted' ) ) {
					return;
				}
				window.ReactWooRuleBuilder.mount( {
					textarea: textarea,
					getMode: function () {
						return mode === 'hide' ? 'hide' : 'show';
					},
					allowAllConditionTypes: true,
				} );
			},
			[ config.advancedTargeting, usePortable, mode ]
		);

		function updateMeta( key, value ) {
			var next = Object.assign( {}, metaValues );
			next[ key ] = value;
			editPost( { meta: next } );
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'rwgc-post-geo',
				title: 'Geo visibility',
				className: 'rwgc-post-geo-panel',
			},
			el( ToggleControl, {
				label: 'Enable geo visibility for this post',
				checked: enabled,
				onChange: function ( val ) {
					updateMeta( meta.enabled, val ? 'yes' : '' );
				},
			} ),
			enabled &&
				el( SelectControl, {
					label: 'Mode',
					value: mode,
					options: [
						{ label: 'Show for selected countries', value: 'show' },
						{ label: 'Hide for selected countries', value: 'hide' },
					],
					onChange: function ( val ) {
						updateMeta( meta.mode, val );
					},
				} ),
			enabled &&
				! usePortable &&
				el( SelectControl, {
					label: 'Countries',
					value: countries,
					multiple: true,
					options: countryOptions,
					onChange: function ( val ) {
						updateMeta( meta.countries, val || [] );
					},
				} ),
			enabled &&
				config.advancedTargeting &&
				el( ToggleControl, {
					label: 'Use visibility rule builder (GeoCore Pro)',
					checked: usePortable,
					onChange: function ( val ) {
						updateMeta( meta.usePortable, val ? 'yes' : '' );
					},
				} ),
			enabled &&
				config.advancedTargeting &&
				usePortable &&
				el(
					PanelRow,
					null,
					el( 'textarea', {
						id: 'rwgc-post-portable-targeting',
						className: 'rwgc-rb-textarea-hidden',
						style: { display: 'none' },
						value: portable,
						onChange: function ( e ) {
							updateMeta( meta.portable, e.target.value );
						},
					} )
				)
		);
	}

	registerPlugin( 'rwgc-post-geo', {
		render: PostGeoPanel,
		icon: 'location',
	} );
} )( window.wp, window.rwgcPostGeoEditor || {} );
