/**
 * Aucteeno Field Ends At Block - Editor Script
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';

import metadata from '../block.json';
import './style.css';
import { formatDatetime } from '../../shared/src/datetime-utils';

function Edit( { attributes, setAttributes, context } ) {
	const {
		showLabel = true,
		dateTimeFormat = 'wp_default',
		customFormat = '',
	} = attributes;

	const itemData = context?.[ 'aucteeno/item' ] || {};
	const timestamp = itemData.bidding_ends_at || 0;

	const displayValue = useMemo(
		() => formatDatetime( timestamp, dateTimeFormat, customFormat ),
		[ timestamp, dateTimeFormat, customFormat ]
	);

	const blockProps = useBlockProps( {
		className: 'aucteeno-field-ends-at',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Ends At Settings', 'aucteeno' ) }>
					<ToggleControl
						label={ __( 'Show label', 'aucteeno' ) }
						checked={ showLabel }
						onChange={ ( value ) =>
							setAttributes( { showLabel: value } )
						}
					/>
					<SelectControl
						label={ __( 'Date/Time Format', 'aucteeno' ) }
						value={ dateTimeFormat }
						options={ [
							{
								label: __( 'WordPress default', 'aucteeno' ),
								value: 'wp_default',
							},
							{
								label: __(
									'Long (weekday included)',
									'aucteeno'
								),
								value: 'long',
							},
							{
								label: __( 'Medium (abbreviated)', 'aucteeno' ),
								value: 'medium',
							},
							{
								label: __( 'Custom (PHP format)', 'aucteeno' ),
								value: 'custom',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { dateTimeFormat: value } )
						}
					/>
					{ dateTimeFormat === 'custom' && (
						<TextControl
							label={ __( 'Custom format', 'aucteeno' ) }
							help={ __(
								'PHP date() format string. Supported tokens: d j m n Y y H i A a.',
								'aucteeno'
							) }
							value={ customFormat }
							onChange={ ( value ) =>
								setAttributes( { customFormat: value } )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ showLabel && (
					<span className="aucteeno-field-ends-at__label">
						{ __( 'Ends', 'aucteeno' ) }
					</span>
				) }
				<time className="aucteeno-field-ends-at__value">
					{ displayValue || __( 'No end time', 'aucteeno' ) }
				</time>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
