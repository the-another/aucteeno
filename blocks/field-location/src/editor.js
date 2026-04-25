/**
 * Aucteeno Field Location Block - Editor Script
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from '../block.json';
import './style.css';
import { formatLocation } from './location-utils';

function Edit( { attributes, setAttributes, context } ) {
	const {
		showIcon = true,
		format = 'smart',
		showLinks = false,
		widthMode = 'grow',
		fixedWidth = '',
	} = attributes;
	const itemData = context?.[ 'aucteeno/item' ] || {};

	const city = itemData.location_city || __( 'City', 'aucteeno' );
	const subdivision = itemData.location_subdivision || '';
	const country = itemData.location_country || 'US';

	const locationText =
		formatLocation( format, city, subdivision, country ) ||
		`${ city }, ${ country }`;

	const blockProps = useBlockProps( {
		className: `is-width-${ widthMode }`,
		...( widthMode === 'fixed' && fixedWidth
			? { style: { width: fixedWidth } }
			: {} ),
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Location Settings', 'aucteeno' ) }>
					<ToggleControl
						label={ __( 'Show icon', 'aucteeno' ) }
						checked={ showIcon }
						onChange={ ( value ) =>
							setAttributes( { showIcon: value } )
						}
					/>
					<SelectControl
						label={ __( 'Format', 'aucteeno' ) }
						value={ format }
						options={ [
							{
								label: __( 'Smart (recommended)', 'aucteeno' ),
								value: 'smart',
							},
							{
								label: __( 'City, Country', 'aucteeno' ),
								value: 'city_country',
							},
							{
								label: __( 'City, State', 'aucteeno' ),
								value: 'city_subdivision',
							},
							{
								label: __( 'City only', 'aucteeno' ),
								value: 'city_only',
							},
							{
								label: __( 'Country only', 'aucteeno' ),
								value: 'country_only',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { format: value } )
						}
					/>
					<ToggleControl
						label={ __(
							'Show links to location terms',
							'aucteeno'
						) }
						checked={ showLinks }
						onChange={ ( value ) =>
							setAttributes( { showLinks: value } )
						}
						help={ __(
							'Link country and state/subdivision to their taxonomy archive pages',
							'aucteeno'
						) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Width Settings', 'aucteeno' ) }>
					<SelectControl
						label={ __( 'Width', 'aucteeno' ) }
						value={ widthMode }
						options={ [
							{
								label: __( 'Grow (fill available space)', 'aucteeno' ),
								value: 'grow',
							},
							{
								label: __( 'Fit (content width)', 'aucteeno' ),
								value: 'fit',
							},
							{
								label: __( 'Fixed', 'aucteeno' ),
								value: 'fixed',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { widthMode: value } )
						}
					/>
					{ widthMode === 'fixed' && (
						<UnitControl
							label={ __( 'Fixed width', 'aucteeno' ) }
							value={ fixedWidth }
							onChange={ ( value ) =>
								setAttributes( { fixedWidth: value || '' } )
							}
							units={ [
								{ value: 'px', label: 'px' },
								{ value: 'rem', label: 'rem' },
								{ value: 'em', label: 'em' },
								{ value: '%', label: '%' },
							] }
							isUnitSelectTabbable
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<p className="aucteeno-field-location">
					{ showIcon && (
						<span className="aucteeno-field-location__icon">📍</span>
					) }
					<span className="aucteeno-field-location__part">
						{ locationText }
					</span>
				</p>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
