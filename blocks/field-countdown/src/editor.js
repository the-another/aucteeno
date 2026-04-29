/**
 * Aucteeno Field Countdown Block - Editor Script
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
import { useMemo } from '@wordpress/element';

import metadata from '../block.json';
import './style.css';

/**
 * Format countdown display with smart scaling.
 *
 * Timestamps are UTC-based (Unix timestamps). Date objects automatically convert to local timezone.
 *
 * @param {number} diff      Difference in seconds.
 * @param {number} timestamp Unix timestamp (UTC-based).
 * @return {string} Formatted display value in user's local timezone.
 */
function formatCountdown( diff, timestamp ) {
	if ( diff <= 0 ) {
		return __( 'Ended', 'aucteeno' );
	}
	if ( diff < 60 ) {
		return `${ Math.max( 0, Math.floor( diff ) ) }s`;
	}
	if ( diff < 3600 ) {
		return `${ Math.floor( diff / 60 ) }m`;
	}
	if ( diff < 86400 ) {
		return `${ Math.floor( diff / 3600 ) }h`;
	}
	if ( diff < 604800 ) {
		return `${ Math.floor( diff / 86400 ) }d`;
	}
	// More than 1 week - show date in user's local timezone.
	return new Date( timestamp * 1000 ).toLocaleDateString();
}

function Edit( { attributes, setAttributes, context } ) {
	const {
		showLabel = true,
		dateFormat = 'default',
		targetDate = 'auto',
		respectBiddingStatus = true,
		label: singleLabel = 'Bidding ends in',
		labelUpcomingTime = 'Bidding starts in',
		labelUpcomingDate = 'Bidding starts on',
		labelRunningTime = 'Bidding ends in',
		labelRunningDate = 'Bidding ends on',
		labelExpired = 'Bidding ended',
	} = attributes;
	const itemData = context?.[ 'aucteeno/item' ] || {};

	const biddingStatus = itemData.bidding_status || 10;
	const biddingStarts = itemData.bidding_starts_at || 0;
	const biddingEnds = itemData.bidding_ends_at || 0;

	const isUpcoming = biddingStatus === 20;
	const autoTimestamp = isUpcoming ? biddingStarts : biddingEnds;
	const timestamp =
		targetDate === 'starts_at'
			? biddingStarts
			: targetDate === 'ends_at'
			? biddingEnds
			: autoTimestamp;

	const { label, displayValue, statusClass } = useMemo( () => {
		// Get current UTC timestamp - Date.now() returns milliseconds since Unix epoch (UTC)
		// Calculations use UTC to ensure consistency regardless of user's timezone
		const now = Math.floor( Date.now() / 1000 );
		const diff = timestamp - now;

		let cls;
		if ( isUpcoming ) {
			cls = 'upcoming';
		} else if ( biddingStatus === 30 ) {
			cls = 'expired';
		} else {
			cls = 'running';
		}

		let effectiveState = cls;
		if ( targetDate === 'starts_at' ) {
			effectiveState = now < biddingStarts ? 'upcoming' : 'expired';
		} else if ( targetDate === 'ends_at' ) {
			effectiveState = now < biddingEnds ? 'running' : 'expired';
		}

		let lbl;
		if ( ! respectBiddingStatus ) {
			lbl = singleLabel;
		} else if ( effectiveState === 'expired' ) {
			lbl = labelExpired;
		} else if ( effectiveState === 'upcoming' ) {
			lbl = labelUpcomingTime;
		} else {
			lbl = labelRunningTime;
		}

		return {
			label: lbl,
			displayValue: formatCountdown( diff, timestamp ),
			statusClass: cls,
		};
	}, [
		timestamp,
		isUpcoming,
		biddingStatus,
		biddingStarts,
		biddingEnds,
		targetDate,
		respectBiddingStatus,
		singleLabel,
		labelUpcomingTime,
		labelRunningTime,
		labelExpired,
	] );

	const blockProps = useBlockProps( {
		className: `aucteeno-field-countdown aucteeno-field-countdown--${ statusClass }`,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Countdown Settings', 'aucteeno' ) }>
					<ToggleControl
						label={ __( 'Show label', 'aucteeno' ) }
						checked={ showLabel }
						onChange={ ( value ) =>
							setAttributes( { showLabel: value } )
						}
					/>
					<SelectControl
						label={ __( 'Target Date', 'aucteeno' ) }
						value={ targetDate }
						options={ [
							{
								label: __(
									'Auto (start before bidding, end during/after)',
									'aucteeno'
								),
								value: 'auto',
							},
							{
								label: __( 'Always count to start', 'aucteeno' ),
								value: 'starts_at',
							},
							{
								label: __( 'Always count to end', 'aucteeno' ),
								value: 'ends_at',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { targetDate: value } )
						}
					/>
					<SelectControl
						label={ __( 'Date Format', 'aucteeno' ) }
						value={ dateFormat }
						options={ [
							{
								label: __( 'WordPress Default', 'aucteeno' ),
								value: 'default',
							},
							{
								label: __(
									'MM/DD/YYYY (01/27/2026)',
									'aucteeno'
								),
								value: 'mdy',
							},
							{
								label: __(
									'DD/MM/YYYY (27/01/2026)',
									'aucteeno'
								),
								value: 'dmy',
							},
							{
								label: __(
									'YYYY-MM-DD (2026-01-27)',
									'aucteeno'
								),
								value: 'ymd',
							},
							{
								label: __(
									'Month D, YYYY (January 27, 2026)',
									'aucteeno'
								),
								value: 'long',
							},
							{
								label: __(
									'D Month YYYY (27 January 2026)',
									'aucteeno'
								),
								value: 'long_eu',
							},
							{
								label: __(
									'Day, Month Dth YYYY (Wednesday, January 28th 2026)',
									'aucteeno'
								),
								value: 'full',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { dateFormat: value } )
						}
						help={ __(
							'Format to display when showing dates (more than 1 week)',
							'aucteeno'
						) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Labels', 'aucteeno' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __(
							'Vary label by bidding status',
							'aucteeno'
						) }
						help={ __(
							'When off, a single label is used in every state.',
							'aucteeno'
						) }
						checked={ respectBiddingStatus }
						onChange={ ( value ) =>
							setAttributes( {
								respectBiddingStatus: value,
							} )
						}
					/>
					{ ! respectBiddingStatus && (
						<TextControl
							label={ __( 'Label', 'aucteeno' ) }
							value={ singleLabel }
							onChange={ ( value ) =>
								setAttributes( { label: value } )
							}
						/>
					) }
					{ respectBiddingStatus && (
						<>
							<TextControl
								label={ __(
									'Upcoming — time interval',
									'aucteeno'
								) }
								value={ labelUpcomingTime }
								onChange={ ( value ) =>
									setAttributes( {
										labelUpcomingTime: value,
									} )
								}
							/>
							<TextControl
								label={ __(
									'Upcoming — date',
									'aucteeno'
								) }
								value={ labelUpcomingDate }
								onChange={ ( value ) =>
									setAttributes( {
										labelUpcomingDate: value,
									} )
								}
							/>
							<TextControl
								label={ __(
									'Running — time interval',
									'aucteeno'
								) }
								value={ labelRunningTime }
								onChange={ ( value ) =>
									setAttributes( {
										labelRunningTime: value,
									} )
								}
							/>
							<TextControl
								label={ __(
									'Running — date',
									'aucteeno'
								) }
								value={ labelRunningDate }
								onChange={ ( value ) =>
									setAttributes( {
										labelRunningDate: value,
									} )
								}
							/>
							<TextControl
								label={ __( 'Expired', 'aucteeno' ) }
								value={ labelExpired }
								onChange={ ( value ) =>
									setAttributes( {
										labelExpired: value,
									} )
								}
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ showLabel && (
					<span className="aucteeno-field-countdown__label">
						{ label }
					</span>
				) }
				<span className="aucteeno-field-countdown__value">
					{ displayValue }
				</span>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
} );
