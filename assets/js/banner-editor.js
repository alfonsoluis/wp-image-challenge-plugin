/**
 * Bloque «Banner de retos» en el editor. Sin compilación: usa los globales de
 * WordPress. El contenido lo pinta el servidor (ServerSideRender) y los textos
 * del panel llegan traducidos desde PHP en window.concursosBanner.
 */
( function ( wp ) {
	if ( ! wp || ! wp.blocks ) {
		return;
	}

	var el = wp.element.createElement;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ServerSideRender = wp.serverSideRender;
	var t = window.concursosBanner || {};

	wp.blocks.registerBlockType( 'foncholuis/banner-retos', {
		edit: function ( props ) {
			var a = props.attributes;

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: t.ajustes },
						el( SelectControl, {
							label: t.modo,
							help: t.ayudaModo,
							value: a.modo,
							options: t.modos || [],
							onChange: function ( valor ) {
								props.setAttributes( { modo: valor } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( SelectControl, {
							label: t.estilo,
							value: a.estilo,
							options: t.estilos || [],
							onChange: function ( valor ) {
								props.setAttributes( { estilo: valor } );
							},
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el( ServerSideRender, { block: 'foncholuis/banner-retos', attributes: a } )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
