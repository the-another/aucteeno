/**
 * Aucteeno Field Location Block - Editor Script
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from '../block.json';
import './style.css';

// Common country names for editor preview
const COUNTRY_NAMES = {
	US: 'United States',
	CA: 'Canada',
	GB: 'United Kingdom',
	AU: 'Australia',
	DE: 'Germany',
	FR: 'France',
	IT: 'Italy',
	ES: 'Spain',
	NL: 'Netherlands',
	BE: 'Belgium',
	CH: 'Switzerland',
	AT: 'Austria',
	IE: 'Ireland',
	NZ: 'New Zealand',
	JP: 'Japan',
	CN: 'China',
	IN: 'India',
	BR: 'Brazil',
	MX: 'Mexico',
	AR: 'Argentina',
};

// Common US state names for editor preview
const US_STATES = {
	AL: 'Alabama',
	AK: 'Alaska',
	AZ: 'Arizona',
	AR: 'Arkansas',
	CA: 'California',
	CO: 'Colorado',
	CT: 'Connecticut',
	DE: 'Delaware',
	FL: 'Florida',
	GA: 'Georgia',
	HI: 'Hawaii',
	ID: 'Idaho',
	IL: 'Illinois',
	IN: 'Indiana',
	IA: 'Iowa',
	KS: 'Kansas',
	KY: 'Kentucky',
	LA: 'Louisiana',
	ME: 'Maine',
	MD: 'Maryland',
	MA: 'Massachusetts',
	MI: 'Michigan',
	MN: 'Minnesota',
	MS: 'Mississippi',
	MO: 'Missouri',
	MT: 'Montana',
	NE: 'Nebraska',
	NV: 'Nevada',
	NH: 'New Hampshire',
	NJ: 'New Jersey',
	NM: 'New Mexico',
	NY: 'New York',
	NC: 'North Carolina',
	ND: 'North Dakota',
	OH: 'Ohio',
	OK: 'Oklahoma',
	OR: 'Oregon',
	PA: 'Pennsylvania',
	RI: 'Rhode Island',
	SC: 'South Carolina',
	SD: 'South Dakota',
	TN: 'Tennessee',
	TX: 'Texas',
	UT: 'Utah',
	VT: 'Vermont',
	VA: 'Virginia',
	WA: 'Washington',
	WV: 'West Virginia',
	WI: 'Wisconsin',
	WY: 'Wyoming',
	DC: 'District of Columbia',
};

/**
 * Get subdivision name from code
 */
function getSubdivisionName( countryCode, subdivisionCode ) {
	if ( ! subdivisionCode ) {
		return '';
	}

	// Extract subdivision code from "COUNTRY:SUBDIVISION" format if needed
	let code = subdivisionCode;
	if ( subdivisionCode.includes( ':' ) ) {
		const parts = subdivisionCode.split( ':', 2 );
		code = parts[ 1 ] || '';
	}

	// For US states, use our mapping
	if ( countryCode === 'US' && US_STATES[ code ] ) {
		return US_STATES[ code ];
	}

	// For other countries, just return the code
	return code;
}

/**
 * Format location with smart display logic
 */
function formatSmartLocation( city, subdivision, countryCode ) {
	const parts = [];

	// Extract subdivision code from "COUNTRY:SUBDIVISION" format if needed
	let subdivisionCode = subdivision;
	if ( subdivision && subdivision.includes( ':' ) ) {
		const subParts = subdivision.split( ':', 2 );
		subdivisionCode = subParts[ 1 ] || '';
	}

	// Get human-readable names
	const countryName = COUNTRY_NAMES[ countryCode ] || countryCode;
	const subdivisionName = getSubdivisionName( countryCode, subdivisionCode );

	// Build location string based on available data
	if ( city ) {
		parts.push( city );
	}

	if ( subdivisionName ) {
		parts.push( subdivisionName );
		// Use country code when subdivision is present
		parts.push( countryCode );
	} else if ( countryName ) {
		// Use full country name when no subdivision
		parts.push( countryName );
	}

	return parts.join( ', ' );
}

/**
 * Format location based on selected format
 */
function formatLocation( format, city, subdivision, countryCode ) {
	const parts = [];

	switch ( format ) {
		case 'smart':
			return formatSmartLocation( city, subdivision, countryCode );

		case 'city_only':
			return city || '';

		case 'country_only':
			return COUNTRY_NAMES[ countryCode ] || countryCode || '';

		case 'city_subdivision':
			if ( city ) {
				parts.push( city );
			}
			if ( subdivision ) {
				const subdivisionName = getSubdivisionName( countryCode, subdivision );
				if ( subdivisionName ) {
					parts.push( subdivisionName );
				}
			}
			return parts.join( ', ' );

		case 'city_country':
			if ( city ) {
				parts.push( city );
			}
			if ( countryCode ) {
				parts.push( countryCode );
			}
			return parts.join( ', ' );

		default:
			return formatSmartLocation( city, subdivision, countryCode );
	}
}

function Edit( { attributes, setAttributes, context } ) {
	const { showIcon = true, format = 'smart', showLinks = false } = attributes;
	const itemData = context?.[ 'aucteeno/item' ] || {};

	const city = itemData.location_city || __( 'City', 'aucteeno' );
	const subdivision = itemData.location_subdivision || '';
	const country = itemData.location_country || 'US';

	const locationText = formatLocation( format, city, subdivision, country ) || `${ city }, ${ country }`;

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
							{ label: __( 'Smart (recommended)', 'aucteeno' ), value: 'smart' },
							{ label: __( 'City, Country', 'aucteeno' ), value: 'city_country' },
							{ label: __( 'City, State', 'aucteeno' ), value: 'city_subdivision' },
							{ label: __( 'City only', 'aucteeno' ), value: 'city_only' },
							{ label: __( 'Country only', 'aucteeno' ), value: 'country_only' },
						] }
						onChange={ ( value ) => setAttributes( { format: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show links to location terms', 'aucteeno' ) }
						checked={ showLinks }
						onChange={ ( value ) => setAttributes( { showLinks: value } ) }
						help={ __( 'Link country and state/subdivision to their taxonomy archive pages', 'aucteeno' ) }
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
