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
	var useEffect = wp.element.useEffect;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;

	var RWGC_AB_BLOCK = 'reactwoo-geocore/ab-test';
	var RWGC_AB_VARIANT = 'reactwoo-geocore/ab-variant';

	/**
	 * Exactly two arms. templateLock "all" keeps this pair in place.
	 * Each variant's own InnerBlocks stay unlocked so the arm content can change.
	 */
	var RWGC_AB_TEMPLATE = [
		[
			RWGC_AB_VARIANT,
			{ variant: 'a', lock: { remove: true, move: true } },
			[ [ 'core/paragraph', { content: 'Variant A' } ] ],
		],
		[
			RWGC_AB_VARIANT,
			{ variant: 'b', lock: { remove: true, move: true } },
			[ [ 'core/paragraph', { content: 'Variant B' } ] ],
		],
	];

	function newInstanceId() {
		var chars = 'abcdef0123456789';
		var out = 'g_';
		var i;
		for ( i = 0; i < 12; i++ ) {
			out += chars.charAt( Math.floor( Math.random() * chars.length ) );
		}
		return out;
	}

	/**
	 * Saved markup is the inner blocks only. Attributes live in the block
	 * comment, so a later server sync can change the test id without invalidating
	 * the block on WordPress 6.2+.
	 */
	function saveAbTestBlock() {
		return createElement( InnerBlocks.Content, null );
	}

	registerBlockType( RWGC_AB_BLOCK, {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var goalType = attributes.goalType === 'url' ? 'url' : 'click';

			useEffect(
				function () {
					if ( ! attributes.instanceId ) {
						setAttributes( { instanceId: newInstanceId() } );
					}
				},
				[]
			);

			var blockProps = useBlockProps( {
				className: 'rwgc-ab-test is-editor',
			} );
			var label = attributes.testName ? attributes.testName : __( 'A/B Test', 'reactwoo-geocore' );

			return createElement(
				Fragment,
				null,
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{
							title: __( 'A/B test', 'reactwoo-geocore' ),
							initialOpen: true,
						},
						createElement( TextControl, {
							label: __( 'Test name', 'reactwoo-geocore' ),
							value: attributes.testName || '',
							onChange: function ( value ) {
								setAttributes( { testName: value } );
							},
							help: __( 'Shown in the A/B tests list.', 'reactwoo-geocore' ),
						} ),
						createElement( TextControl, {
							label: __( 'Test ID', 'reactwoo-geocore' ),
							value: attributes.testId ? String( attributes.testId ) : '',
							onChange: function () {},
							readOnly: true,
							help: __( 'Created on save. Duplicating this page creates a new test.', 'reactwoo-geocore' ),
						} ),
						createElement( SelectControl, {
							label: __( 'Goal', 'reactwoo-geocore' ),
							value: goalType,
							options: [
								{ label: __( 'Click', 'reactwoo-geocore' ), value: 'click' },
								{ label: __( 'URL visit', 'reactwoo-geocore' ), value: 'url' },
							],
							onChange: function ( value ) {
								setAttributes( { goalType: value === 'url' ? 'url' : 'click' } );
							},
							help: __( 'Stored with the test. One goal per test.', 'reactwoo-geocore' ),
						} ),
						goalType === 'click'
							? createElement( TextControl, {
									label: __( 'Click selector', 'reactwoo-geocore' ),
									value: attributes.goalSelector || '',
									onChange: function ( value ) {
										setAttributes( { goalSelector: value } );
									},
									help: __( 'CSS selector for the element inside the variant, for example a.buy.', 'reactwoo-geocore' ),
							  } )
							: createElement( TextControl, {
									label: __( 'Goal URL', 'reactwoo-geocore' ),
									value: attributes.goalUrl || '',
									type: 'url',
									onChange: function ( value ) {
										setAttributes( { goalUrl: value } );
									},
									help: __( 'A visit to this URL is the goal.', 'reactwoo-geocore' ),
							  } )
					)
				),
				createElement(
					'div',
					blockProps,
					createElement(
						'p',
						{ className: 'rwgc-ab-test__badge' },
						label
					),
					createElement( InnerBlocks, {
						template: RWGC_AB_TEMPLATE,
						templateLock: 'all',
						allowedBlocks: [ RWGC_AB_VARIANT ],
						renderAppender: false,
					} )
				)
			);
		},
		save: saveAbTestBlock,
	} );
} )( window.wp );
