/**
 * Aucteeno Card Block - Editor Script
 *
 * Editor interface for the card block with inner blocks support.
 *
 * @package Aucteeno
 */

import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	InspectorControls,
	InnerBlocks,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	RangeControl,
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';

import metadata from '../block.json';
import './editor.css';
import './style.css';

// Default template for card - image and title.
const TEMPLATE = [
	[ 'aucteeno/field-image', {} ],
	[ 'aucteeno/field-title', { tagName: 'h3' } ],
];

// Allowed blocks inside card.
const ALLOWED_BLOCKS = [
	'aucteeno/field-image',
	'aucteeno/field-title',
	'aucteeno/field-countdown',
	'aucteeno/field-location',
	'aucteeno/field-current-bid',
	'aucteeno/field-reserve-price',
	'aucteeno/field-lot-number',
	'aucteeno/field-bidding-status',
	'core/group',
	'core/columns',
	'core/spacer',
];

/**
 * Edit component for Aucteeno Card block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update attributes.
 * @param {Object}   props.context       Block context.
 * @return {JSX.Element} Block editor interface.
 */
function Edit( { attributes, setAttributes, context } ) {
	const {
		useImageAsBackground = false,
		backgroundOverlay = 0.5,
		cardWidth = '20rem',
	} = attributes;

	// Get item data from context.
	const itemData = context?.[ 'aucteeno/item' ] || null;
	const itemType = context?.[ 'aucteeno/itemType' ] || 'auctions';

	// Determine bidding status class.
	const statusClass = useMemo( () => {
		const statusMap = {
			10: 'running',
			20: 'upcoming',
			30: 'expired',
		};
		const status = itemData?.bidding_status || 10;
		return statusMap[ status ] || 'running';
	}, [ itemData ] );

	// Build class name.
	const className = useMemo( () => {
		const classes = [
			'aucteeno-card',
			`aucteeno-card--${ statusClass }`,
			`aucteeno-card--${ itemType === 'auctions' ? 'auction' : 'item' }`,
		];
		if ( useImageAsBackground ) {
			classes.push( 'aucteeno-card--has-background' );
		}
		return classes.join( ' ' );
	}, [ statusClass, itemType, useImageAsBackground ] );

	// Build background style.
	const backgroundStyle = useMemo( () => {
		const style = {
			'--card-width': cardWidth || '20rem',
		};

		if ( useImageAsBackground && itemData?.image_url ) {
			style.backgroundImage = `linear-gradient(rgba(0, 0, 0, ${ backgroundOverlay }), rgba(0, 0, 0, ${ backgroundOverlay })), url(${ itemData.image_url })`;
			style.backgroundSize = 'cover';
			style.backgroundPosition = 'center';
			style.backgroundRepeat = 'no-repeat';
		}

		return style;
	}, [ useImageAsBackground, backgroundOverlay, itemData, cardWidth ] );

	const blockProps = useBlockProps( {
		className,
		style: backgroundStyle,
	} );

	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: TEMPLATE,
		allowedBlocks: ALLOWED_BLOCKS,
		templateLock: false,
		renderAppender: InnerBlocks.ButtonBlockAppender,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Card Settings', 'aucteeno' ) }
					initialOpen={ true }
				>
					<UnitControl
						label={ __( 'Card Width', 'aucteeno' ) }
						help={ __(
							'Fixed width for this card',
							'aucteeno'
						) }
						value={ cardWidth || '20rem' }
						onChange={ ( value ) =>
							setAttributes( { cardWidth: value || '20rem' } )
						}
						units={ [
							{ value: 'px', label: 'px' },
							{ value: 'rem', label: 'rem' },
							{ value: 'em', label: 'em' },
							{ value: '%', label: '%' },
						] }
						isUnitSelectTabbable
					/>
					<ToggleControl
						label={ __( 'Use image as background', 'aucteeno' ) }
						help={ __(
							'Display the featured image as a card background.',
							'aucteeno'
						) }
						checked={ useImageAsBackground }
						onChange={ ( value ) =>
							setAttributes( { useImageAsBackground: value } )
						}
					/>
					{ useImageAsBackground && (
						<RangeControl
							label={ __( 'Background overlay', 'aucteeno' ) }
							help={ __(
								'Darkness of the overlay on the background image.',
								'aucteeno'
							) }
							value={ backgroundOverlay }
							onChange={ ( value ) =>
								setAttributes( { backgroundOverlay: value } )
							}
							min={ 0 }
							max={ 1 }
							step={ 0.1 }
						/>
					) }
				</PanelBody>

				{ itemData && (
					<PanelBody
						title={ __( 'Preview Data', 'aucteeno' ) }
						initialOpen={ false }
					>
						<p>
							<strong>{ __( 'ID:', 'aucteeno' ) }</strong>{ ' ' }
							{ itemData.id || __( 'N/A', 'aucteeno' ) }
						</p>
						<p>
							<strong>{ __( 'Title:', 'aucteeno' ) }</strong>{ ' ' }
							{ itemData.title || __( 'N/A', 'aucteeno' ) }
						</p>
						<p>
							<strong>{ __( 'Status:', 'aucteeno' ) }</strong>{ ' ' }
							{ statusClass }
						</p>
						<p>
							<strong>{ __( 'Type:', 'aucteeno' ) }</strong>{ ' ' }
							{ itemType }
						</p>
					</PanelBody>
				) }
			</InspectorControls>

			<article { ...innerBlocksProps } />
		</>
	);
}

/**
 * Save component - returns inner blocks content.
 *
 * @return {JSX.Element} Inner blocks content.
 */
function Save() {
	return <InnerBlocks.Content />;
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: Save,
} );
