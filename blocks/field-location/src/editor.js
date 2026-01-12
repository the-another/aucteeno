/**
 * Aucteeno Field Location Block - Editor Script
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from '../block.json';
import './style.css';

function Edit( { attributes, setAttributes, context } ) {
	const { showIcon = true, format = 'city_country' } = attributes;
	const itemData = context?.[ 'aucteeno/item' ] || {};
	
	const city = itemData.location_city || __( 'City', 'aucteeno' );
	const country = itemData.location_country || 'US';
	const locationText = `${ city }, ${ country }`;

	const blockProps = useBlockProps( { className: 'aucteeno-field-location' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Location Settings', 'aucteeno' ) }>
					<ToggleControl
						label={ __( 'Show icon', 'aucteeno' ) }
						checked={ showIcon }
						onChange={ ( value ) => setAttributes( { showIcon: value } ) }
					/>
					<SelectControl
						label={ __( 'Format', 'aucteeno' ) }
						value={ format }
						options={ [
							{ label: __( 'City, Country', 'aucteeno' ), value: 'city_country' },
							{ label: __( 'City, State', 'aucteeno' ), value: 'city_subdivision' },
							{ label: __( 'City only', 'aucteeno' ), value: 'city_only' },
							{ label: __( 'Country only', 'aucteeno' ), value: 'country_only' },
						] }
						onChange={ ( value ) => setAttributes( { format: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ showIcon && <span className="aucteeno-field-location__icon">📍</span> }
				<span className="aucteeno-field-location__text">{ locationText }</span>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
