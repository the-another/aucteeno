/**
 * Aucteeno Field Location Block - Editor Script
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from '../block.json';
import './style.css';
import { formatLocation } from './location-utils';

function Edit( { attributes, setAttributes, context } ) {
	const {
		showLabel = true,
		label = 'Location',
		showIcon = true,
		format = 'smart',
		showLinks = false,
		orientation = 'column',
	} = attributes;
	const itemData = context?.[ 'aucteeno/item' ] || {};

	const city = itemData.location_city || __( 'City', 'aucteeno' );
	const subdivision = itemData.location_subdivision || '';
	const country = itemData.location_country || 'US';

	const locationText =
		formatLocation( format, city, subdivision, country ) ||
		`${ city }, ${ country }`;

	const blockProps = useBlockProps( { className: `is-orientation-${ orientation }` } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Label Settings', 'aucteeno' ) }>
					<ToggleControl
						label={ __( 'Show label', 'aucteeno' ) }
						checked={ showLabel }
						onChange={ ( value ) =>
							setAttributes( { showLabel: value } )
						}
					/>
					{ showLabel && (
						<TextControl
							label={ __( 'Label text', 'aucteeno' ) }
							value={ label }
							onChange={ ( value ) =>
								setAttributes( { label: value } )
							}
						/>
					) }
				</PanelBody>
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
								label: __( 'City, State, Country', 'aucteeno' ),
								value: 'city_subdivision_country',
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
				<PanelBody title={ __( 'Layout', 'aucteeno' ) }>
					<SelectControl
						label={ __( 'Orientation', 'aucteeno' ) }
						value={ orientation }
						options={ [
							{ label: __( 'Stacked (column)', 'aucteeno' ), value: 'column' },
							{ label: __( 'Inline (row)', 'aucteeno' ), value: 'row' },
						] }
						onChange={ ( value ) =>
							setAttributes( { orientation: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<dl>
					{ showLabel && (
						<dt className="wp-block-aucteeno-field-location__label">
							{ label }
						</dt>
					) }
					<dd className="wp-block-aucteeno-field-location__value">
						{ showIcon && (
							<span className="wp-block-aucteeno-field-location__icon">📍</span>
						) }
						<span className="wp-block-aucteeno-field-location__part">
							{ locationText }
						</span>
					</dd>
				</dl>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
