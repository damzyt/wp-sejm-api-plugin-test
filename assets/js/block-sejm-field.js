( function( blocks, element, components, editor ) {
	var el = element.createElement;
	var registerBlockType = blocks.registerBlockType;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var InspectorControls = editor.InspectorControls;
	var PanelBody = components.PanelBody;

	registerBlockType( 'sejm-api/dane-posla', {
		title: 'Dane Posła (Sejm API)',
		icon: 'businessperson',
		category: 'widgets',
		attributes: {
			fieldKey: {
				type: 'string',
				default: 'club',
			},
		},
		edit: function( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var options = [
				{ label: 'Klub / Koło (ID)', value: 'club' },
				{ label: 'Okręg Wyborczy', value: 'districtName' },
				{ label: 'Numer Okręgu', value: 'districtNum' },
				{ label: 'Okręg (Num + Nazwa)', value: 'district_composite' },
				{ label: 'Województwo', value: 'voivodeship' },
				{ label: 'Data i Miejsce Urodzenia', value: 'birth_composite' },
				{ label: 'Data Urodzenia', value: 'birthDate' },
				{ label: 'Miejsce Urodzenia', value: 'birthLocation' },
				{ label: 'Wykształcenie', value: 'educationLevel' },
				{ label: 'Liczba Głosów', value: 'numberOfVotes' },
				{ label: 'Email', value: 'email' },
				{ label: 'Inne (wpisz ręcznie)', value: 'custom' }
			];

			function onChangeSelect( newValue ) {
				setAttributes( { fieldKey: newValue } );
			}

			function onChangeText( newValue ) {
				setAttributes( { fieldKey: newValue } );
			}
			
			return el(
				element.Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: 'Ustawienia Danych', initialOpen: true },
						el( SelectControl, {
							label: 'Wybierz pole',
							value: options.find( o => o.value === attributes.fieldKey ) ? attributes.fieldKey : 'custom',
							options: options,
							onChange: function( val ) {
								if ( val !== 'custom' ) {
									setAttributes( { fieldKey: val } );
								} else {
								}
							}
						} ),
						el( TextControl, {
							label: 'Klucz pola (API)',
							value: attributes.fieldKey,
							onChange: onChangeText,
							help: 'Wpisz klucz pola (meta key), np. club, districtName.'
						} )
					)
				),
				el(
					'div',
					{ className: 'sejm-api-block-preview', style: { padding: '10px', border: '1px dashed #ccc', background: '#f9f9f9' } },
					el( 'strong', {}, 'Dane Posła: ' ),
					el( 'code', {}, attributes.fieldKey ),
					el( 'div', { style: { fontSize: '0.8em', color: '#666' } }, '(Podgląd na żywo dostępny po zapisaniu lub w widoku witryny)' )
				)
			);
		},
		save: function() {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor );
