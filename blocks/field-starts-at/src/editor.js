/**
 * Aucteeno Field Starts At Block - Editor Script
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
		respectBiddingStatus = true,
		labelUpcoming = 'Bidding opens at',
		labelRunning = 'Bidding opened at',
		labelExpired = 'Bidding opened at',
		label = 'Bidding opens at',
		orientation = 'column',
	} = attributes;

	const itemData = context?.[ 'aucteeno/item' ] || {};
	const timestamp = itemData.bidding_starts_at || 0;
	const startsAt = timestamp;
	const endsAt = itemData.bidding_ends_at || 0;

	const now = Math.floor( Date.now() / 1000 );
	let currentState;
	if ( now < startsAt ) {
		currentState = 'upcoming';
	} else if ( endsAt > 0 && now >= endsAt ) {
		currentState = 'expired';
	} else {
		currentState = 'running';
	}

	const displayValue = useMemo(
		() => formatDatetime( timestamp, dateTimeFormat, customFormat ),
		[ timestamp, dateTimeFormat, customFormat ]
	);

	const labelText = useMemo( () => {
		if ( ! showLabel ) {
			return null;
		}
		if ( respectBiddingStatus ) {
			const map = {
				upcoming: labelUpcoming,
				running: labelRunning,
				expired: labelExpired,
			};
			return map[ currentState ] ?? labelUpcoming;
		}
		return label;
	}, [
		showLabel,
		respectBiddingStatus,
		currentState,
		labelUpcoming,
		labelRunning,
		labelExpired,
		label,
	] );

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
						<>
							<ToggleControl
								label={ __(
									'Respect bidding status',
									'aucteeno'
								) }
								help={ __(
									'Show different label text depending on whether bidding is upcoming, running, or expired.',
									'aucteeno'
								) }
								checked={ respectBiddingStatus }
								onChange={ ( value ) =>
									setAttributes( {
										respectBiddingStatus: value,
									} )
								}
							/>
							{ respectBiddingStatus ? (
								<>
									<TextControl
										label={ __(
											'Upcoming label',
											'aucteeno'
										) }
										value={ labelUpcoming }
										onChange={ ( value ) =>
											setAttributes( {
												labelUpcoming: value,
											} )
										}
									/>
									<TextControl
										label={ __(
											'Running label',
											'aucteeno'
										) }
										value={ labelRunning }
										onChange={ ( value ) =>
											setAttributes( {
												labelRunning: value,
											} )
										}
									/>
									<TextControl
										label={ __(
											'Expired label',
											'aucteeno'
										) }
										value={ labelExpired }
										onChange={ ( value ) =>
											setAttributes( {
												labelExpired: value,
											} )
										}
									/>
								</>
							) : (
								<TextControl
									label={ __( 'Label', 'aucteeno' ) }
									value={ label }
									onChange={ ( value ) =>
										setAttributes( { label: value } )
									}
								/>
							) }
						</>
					) }
				</PanelBody>
				<PanelBody title={ __( 'Format Settings', 'aucteeno' ) }>
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
					{ labelText && (
						<dt className="wp-block-aucteeno-field-starts-at__label">
							{ labelText }
						</dt>
					) }
					<dd className="wp-block-aucteeno-field-starts-at__value">
						<time>
							{ displayValue || __( 'No start time', 'aucteeno' ) }
						</time>
					</dd>
				</dl>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
