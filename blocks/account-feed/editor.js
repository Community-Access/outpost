( function ( blocks, element, blockEditor, components, ServerSideRender, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var RangeControl = components.RangeControl;

	blocks.registerBlockType( 'outpost/account-feed', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Account feed settings', 'outpost' ) },
						el( RangeControl, {
							label: __( 'Number of posts', 'outpost' ),
							value: attributes.limit,
							onChange: function ( value ) {
								setAttributes( { limit: value } );
							},
							min: 1,
							max: 50,
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'outpost/account-feed',
						attributes: attributes,
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender,
	window.wp.i18n
);
