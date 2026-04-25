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
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
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
		labelUpcoming = 'Bidding closes at',
		labelRunning = 'Bidding closes at',
		labelExpired = 'Bidding closed at',
		label = 'Bidding closes at',
		widthMode = 'grow',
		fixedWidth = '',
	} = attributes;

	const itemData = context?.[ 'aucteeno/item' ] || {};
	const timestamp = itemData.bidding_ends_at || 0;
	const startsAt = itemData.bidding_starts_at || 0;
	const endsAt = timestamp;

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

	const blockProps = useBlockProps( {
		className: `is-width-${ widthMode }`,
		...( widthMode === 'fixed' && fixedWidth
			? { style: { width: fixedWidth } }
			: {} ),
	} );

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
				<p className="aucteeno-field-ends-at">
					{ labelText && (
						<span className="aucteeno-field-ends-at__label">
							{ labelText }
						</span>
					) }
					<time className="aucteeno-field-ends-at__value">
						{ displayValue || __( 'No end time', 'aucteeno' ) }
					</time>
				</p>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
