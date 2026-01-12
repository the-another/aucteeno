/**
 * Aucteeno Field Lot Number Block - Editor Script
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from '../block.json';

function Edit( { attributes, setAttributes, context } ) {
	const { showLabel = true, prefix = 'Lot #' } = attributes;
	const itemData = context?.[ 'aucteeno/item' ] || {};
	const itemType = context?.[ 'aucteeno/itemType' ] || 'auctions';
	const lotNo = itemData.lot_no || '001';

	const blockProps = useBlockProps( { className: 'aucteeno-field-lot-number' } );

	// Only relevant for items.
	if ( itemType !== 'items' ) {
		return (
			<div { ...blockProps }>
				<em>{ __( 'Lot # (items only)', 'aucteeno' ) }</em>
			</div>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Lot Number Settings', 'aucteeno' ) }>
					<ToggleControl
						label={ __( 'Show prefix', 'aucteeno' ) }
						checked={ showLabel }
						onChange={ ( value ) => setAttributes( { showLabel: value } ) }
					/>
					{ showLabel && (
						<TextControl
							label={ __( 'Prefix', 'aucteeno' ) }
							value={ prefix }
							onChange={ ( value ) => setAttributes( { prefix: value } ) }
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ showLabel && prefix && <span className="aucteeno-field-lot-number__prefix">{ prefix }</span> }
				<span className="aucteeno-field-lot-number__value">{ lotNo }</span>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
