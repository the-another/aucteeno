/**
 * Aucteeno Field Reserve Price Block - Editor Script
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from '../block.json';

function Edit( { attributes, setAttributes, context } ) {
	const { showLabel = true, hideIfZero = true } = attributes;
	const itemData = context?.[ 'aucteeno/item' ] || {};
	const reservePrice = itemData.reserve_price || 500;

	const blockProps = useBlockProps( { className: 'aucteeno-field-reserve-price' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Reserve Price Settings', 'aucteeno' ) }>
					<ToggleControl
						label={ __( 'Show label', 'aucteeno' ) }
						checked={ showLabel }
						onChange={ ( value ) => setAttributes( { showLabel: value } ) }
					/>
					<ToggleControl
						label={ __( 'Hide if zero', 'aucteeno' ) }
						checked={ hideIfZero }
						onChange={ ( value ) => setAttributes( { hideIfZero: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ showLabel && <span className="aucteeno-field-reserve-price__label">{ __( 'Reserve', 'aucteeno' ) }</span> }
				<span className="aucteeno-field-reserve-price__value">${ reservePrice.toFixed( 2 ) }</span>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
