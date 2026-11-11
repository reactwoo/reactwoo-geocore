( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.blockEditor || ! wp.element || ! wp.i18n ) {
		return;
	}

	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InnerBlocks = wp.blockEditor.InnerBlocks;
	var createElement = wp.element.createElement;
	var __ = wp.i18n.__;

	var RWGC_AB_VARIANT = 'reactwoo-geocore/ab-variant';

	/**
	 * Inner content only, same as the parent, so the arm wrapper cannot drift
	 * across WordPress versions.
	 */
	function saveAbVariantBlock() {
		return createElement( InnerBlocks.Content, null );
	}

	registerBlockType( RWGC_AB_VARIANT, {
		edit: function ( props ) {
			var variant = props.attributes && props.attributes.variant === 'b' ? 'b' : 'a';
			var blockProps = useBlockProps( {
				className: 'rwgc-ab-variant is-editor',
			} );

			return createElement(
				'div',
				blockProps,
				createElement(
					'p',
					{ className: 'rwgc-ab-variant__badge' },
					variant === 'b'
						? __( 'Variant B', 'reactwoo-geocore' )
						: __( 'Variant A', 'reactwoo-geocore' )
				),
				createElement( InnerBlocks, {
					templateLock: false,
					renderAppender: InnerBlocks.ButtonBlockAppender,
				} )
			);
		},
		save: saveAbVariantBlock,
	} );
} )( window.wp );
