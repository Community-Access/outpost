( function ( blocks, element, blockEditor, components, ServerSideRender, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var ToggleControl = components.ToggleControl;

	var hashtagOptions = ( window.outpostBlockData && window.outpostBlockData.hashtagOptions ) || [
		{ label: __( 'Select a hashtag…', 'outpost' ), value: '' },
	];

	blocks.registerBlockType( 'outpost/feed', {
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
						{ title: __( 'Feed settings', 'outpost' ) },
						el( SelectControl, {
							label: __( 'Hashtag', 'outpost' ),
							value: attributes.tag,
							options: hashtagOptions,
							onChange: function ( value ) {
								setAttributes( { tag: value } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Number of posts', 'outpost' ),
							value: attributes.limit,
							onChange: function ( value ) {
								setAttributes( { limit: value } );
							},
							min: 1,
							max: 50,
						} ),
						el( ToggleControl, {
							label: __( 'Show subscribe form', 'outpost' ),
							checked: !! attributes.showSubscribe,
							onChange: function ( value ) {
								setAttributes( { showSubscribe: value } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					attributes.tag
						? el( ServerSideRender, {
							block: 'outpost/feed',
							attributes: attributes,
						} )
						: el(
							'p',
							null,
							__( 'Select a hashtag in the block settings to preview this feed.', 'outpost' )
						)
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
