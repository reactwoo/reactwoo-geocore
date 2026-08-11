( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.blockEditor || ! wp.element || ! wp.components || ! wp.i18n ) {
		return;
	}

	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InnerBlocks = wp.blockEditor.InnerBlocks;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var SelectControl = wp.components.SelectControl;
	var Notice = wp.components.Notice;
	var useEffect = wp.element.useEffect;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;

	function newInstanceId() {
		return 'g_' + Math.random().toString( 36 ).slice( 2, 10 ) + Math.random().toString( 36 ).slice( 2, 6 );
	}

	registerBlockType( 'reactwoo/experience-slot', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			useEffect(
				function () {
					if ( ! attributes.instanceId ) {
						setAttributes( { instanceId: newInstanceId() } );
					}
				},
				[]
			);

			var blockProps = useBlockProps( {
				className: 'reactwoo-experience-slot is-editor',
			} );

			var mode = attributes.managementMode || 'local';
			var isManaged = mode === 'managed';

			return createElement(
				Fragment,
				null,
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{
							title: __( 'ReactWoo Experience Slot', 'reactwoo-geocore' ),
							initialOpen: true,
						},
						isManaged
							? createElement(
									Notice,
									{ status: 'warning', isDismissible: false },
									__( 'Managed mode is reserved for ReactWoo Cloud. Decisions stay local until Cloud is connected.', 'reactwoo-geocore' )
							  )
							: null,
						createElement( TextControl, {
							label: __( 'Slot name', 'reactwoo-geocore' ),
							value: attributes.slotName || '',
							onChange: function ( value ) {
								setAttributes( { slotName: value } );
							},
							help: __( 'Human label for this location (e.g. Homepage Hero).', 'reactwoo-geocore' ),
						} ),
						createElement( TextControl, {
							label: __( 'Slot ID', 'reactwoo-geocore' ),
							value: attributes.slotId || '',
							onChange: function () {},
							readOnly: true,
							help: __( 'Generated automatically on save. Duplicating this block creates a new Slot ID.', 'reactwoo-geocore' ),
						} ),
						createElement( SelectControl, {
							label: __( 'Cloud status', 'reactwoo-geocore' ),
							value: mode,
							options: [
								{ label: __( 'Local', 'reactwoo-geocore' ), value: 'local' },
								{ label: __( 'Managed', 'reactwoo-geocore' ), value: 'managed' },
							],
							onChange: function ( value ) {
								setAttributes( { managementMode: value } );
							},
						} )
					)
				),
				createElement(
					'div',
					blockProps,
					createElement(
						'div',
						{ className: 'reactwoo-experience-slot__badge' },
						attributes.slotName
							? attributes.slotName
							: __( 'Experience Slot', 'reactwoo-geocore' ),
						isManaged ? ' · ' + __( 'Managed', 'reactwoo-geocore' ) : ''
					),
					createElement( InnerBlocks, {
						templateLock: false,
						renderAppender: InnerBlocks.ButtonBlockAppender,
					} )
				)
			);
		},
		save: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps.save( {
				className: 'reactwoo-experience-slot',
				'data-reactwoo-slot': '1',
				'data-reactwoo-slot-id': attributes.slotId || undefined,
				'data-reactwoo-slot-mode': attributes.managementMode || 'local',
				'data-reactwoo-slot-instance': attributes.instanceId || undefined,
			} );
			return createElement( 'div', blockProps, createElement( InnerBlocks.Content, null ) );
		},
	} );
} )( window.wp );
