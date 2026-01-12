/**
 * Aucteeno Field Current Bid Block - Editor Script
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from '../block.json';

function Edit( { attributes, setAttributes, context } ) {
	const { showLabel = true, label = '' } = attributes;
	const itemData = context?.[ 'aucteeno/item' ] || {};
	const currentBid = itemData.current_bid || 0;
	const displayLabel = label || __( 'Current Bid', 'aucteeno' );

	const blockProps = useBlockProps( { className: 'aucteeno-field-current-bid' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Bid Settings', 'aucteeno' ) }>
					<ToggleControl
						label={ __( 'Show label', 'aucteeno' ) }
						checked={ showLabel }
						onChange={ ( value ) => setAttributes( { showLabel: value } ) }
					/>
					{ showLabel && (
						<TextControl
							label={ __( 'Custom label', 'aucteeno' ) }
							value={ label }
							onChange={ ( value ) => setAttributes( { label: value } ) }
							placeholder={ __( 'Current Bid', 'aucteeno' ) }
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ showLabel && <span className="aucteeno-field-current-bid__label">{ displayLabel }</span> }
				<span className="aucteeno-field-current-bid__value">${ currentBid.toFixed( 2 ) }</span>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
