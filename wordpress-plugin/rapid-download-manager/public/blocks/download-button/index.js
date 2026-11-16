( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'rapid-download-manager/download-button', {
		title: __( 'Download Button', 'rapid-download-manager' ),
		description: __( 'A signed, tracked download button powered by Rapid Download Manager.', 'rapid-download-manager' ),
		icon: 'download',
		category: 'widgets',
		attributes: {
			downloadId: { type: 'number', default: 0 },
			text: { type: 'string', default: __( 'Download', 'rapid-download-manager' ) },
		},

		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				element.Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Download Settings', 'rapid-download-manager' ) },
						el( TextControl, {
							label: __( 'Download post ID', 'rapid-download-manager' ),
							type: 'number',
							value: attributes.downloadId,
							onChange: function ( value ) {
								setAttributes( { downloadId: parseInt( value, 10 ) || 0 } );
							},
						} ),
						el( TextControl, {
							label: __( 'Button text', 'rapid-download-manager' ),
							value: attributes.text,
							onChange: function ( value ) {
								setAttributes( { text: value } );
							},
						} )
					)
				),
				attributes.downloadId
					? el( ServerSideRender, {
							block: 'rapid-download-manager/download-button',
							attributes: attributes,
					  } )
					: el(
							'p',
							{ style: { border: '1px dashed #ccc', padding: '12px' } },
							__( 'Set a Download post ID in the block settings.', 'rapid-download-manager' )
					  )
			);
		},

		save: function () {
			// Server-rendered via render_callback.
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
