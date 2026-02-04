( function( blocks, element, components, editor ) {
	var el = element.createElement;
	var registerBlockType = blocks.registerBlockType;
	var SelectControl = components.SelectControl;
	var InspectorControls = editor.InspectorControls;
	var PanelBody = components.PanelBody;

	registerBlockType( 'sejm-api/dane-klubu', {
		title: 'Dane Klubu (Sejm API)',
		icon: 'groups',
		category: 'widgets',
		attributes: {
			fieldKey: {
				type: 'string',
				default: 'name',
			},
		},
		edit: function( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var options = [
				{ label: 'Nazwa Klubu', value: 'name' },
				{ label: 'Logo Klubu', value: 'logo' },
				{ label: 'Email', value: 'contact_email' },
				{ label: 'Telefon', value: 'contact_phone' },
				{ label: 'Fax', value: 'contact_fax' },
				{ label: 'Liczba Członków', value: 'members_count' },
			];

			return el(
				element.Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: 'Ustawienia Klubu', initialOpen: true },
						el( SelectControl, {
							label: 'Wybierz pole',
							value: attributes.fieldKey,
							options: options,
							onChange: function( val ) {
								setAttributes( { fieldKey: val } );
							}
						} )
					)
				),
				el(
					'div',
					{ className: 'sejm-api-block-preview', style: { padding: '10px', border: '1px dashed #2271b1', background: '#f0f6fc' } },
					el( 'strong', {}, 'Dane Klubu: ' ),
					el( 'code', {}, attributes.fieldKey ),
					el( 'div', { style: { fontSize: '0.8em', color: '#666' } }, '(Widoczne tyko jeśli przypisano klub)' )
				)
			);
		},
		save: function() {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor );
