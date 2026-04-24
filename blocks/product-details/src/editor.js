/**
 * Aucteeno Product Details Block - Editor Script
 *
 * Provides aucteeno/item + aucteeno/itemType context to inner blocks for
 * the single-product editor experience. When productId === 0 the block
 * stands in for "current post" on the frontend; the editor always shows
 * static PREVIEW_ITEM mock data so nested field blocks can render.
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
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';

import metadata from '../block.json';
import './editor.css';

// Fixed reference timestamp keeps previews stable across refreshes.
// 2026-06-05 12:00:00 UTC.
const PREVIEW_REFERENCE = 1780660800;

const PREVIEW_ITEM = {
	id: 0,
	title: __( 'Preview auction title', 'aucteeno' ),
	permalink: '#',
	image_url: '',
	image_id: 0,
	user_id: 0,
	bidding_status: 20,
	bidding_starts_at: PREVIEW_REFERENCE + 86400,
	bidding_ends_at: PREVIEW_REFERENCE + 7 * 86400,
	location_country: 'US',
	location_subdivision: 'CA',
	location_city: 'San Francisco',
	current_bid: 0,
	reserve_price: 0,
};

const ALLOWED_BLOCKS = [
	'aucteeno/field-image',
	'aucteeno/field-title',
	'aucteeno/field-countdown',
	'aucteeno/field-location',
	'aucteeno/field-current-bid',
	'aucteeno/field-reserve-price',
	'aucteeno/field-lot-number',
	'aucteeno/field-bidding-status',
	'aucteeno/field-starts-at',
	'aucteeno/field-ends-at',
	'core/group',
	'core/columns',
	'core/column',
	'core/row',
	'core/stack',
	'core/cover',
	'core/spacer',
	'core/heading',
	'core/paragraph',
];

function Edit( { attributes, setAttributes } ) {
	const { productId = 0 } = attributes;

	// Feed preview data into providesContext via attributes so inner blocks
	// receive it through the editor's context propagation.
	useEffect( () => {
		setAttributes( {
			previewItem: PREVIEW_ITEM,
			itemType: 'auctions',
		} );
		// Run once on mount — PREVIEW_ITEM is a module-scope constant.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const blockProps = useBlockProps( {
		className: 'aucteeno-product-details',
	} );

	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED_BLOCKS,
		templateLock: false,
		renderAppender: InnerBlocks.ButtonBlockAppender,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Product Details Settings', 'aucteeno' ) }
					initialOpen={ true }
				>
					<NumberControl
						label={ __( 'Product ID', 'aucteeno' ) }
						help={ __(
							'Leave as 0 to use the current post on single-product pages.',
							'aucteeno'
						) }
						value={ productId }
						onChange={ ( value ) =>
							setAttributes( {
								productId: parseInt( value, 10 ) || 0,
							} )
						}
						min={ 0 }
						step={ 1 }
					/>
					{ productId > 0 && (
						<p className="aucteeno-product-details__preview-note">
							{ __( 'Using product #', 'aucteeno' ) }
							{ productId }
						</p>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps } />
		</>
	);
}

function Save() {
	return <InnerBlocks.Content />;
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: Save,
} );
