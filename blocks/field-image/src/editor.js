/**
 * Aucteeno Field Image Block - Editor Script
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from '../block.json';
import './style.css';

function Edit( { attributes, setAttributes, context } ) {
	const { isLink = true, aspectRatio = '4/3' } = attributes;
	const itemData = context?.[ 'aucteeno/item' ] || {};
	const imageUrl = itemData.image_url || '';
	const title = itemData.title || __( 'Sample Image', 'aucteeno' );

	const blockProps = useBlockProps( {
		className: 'aucteeno-field-image',
	} );

	const style = { aspectRatio };

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Image Settings', 'aucteeno' ) }>
					<ToggleControl
						label={ __( 'Link to item', 'aucteeno' ) }
						checked={ isLink }
						onChange={ ( value ) =>
							setAttributes( { isLink: value } )
						}
					/>
					<SelectControl
						label={ __( 'Aspect Ratio', 'aucteeno' ) }
						value={ aspectRatio }
						options={ [
							{ label: '4:3', value: '4/3' },
							{ label: '16:9', value: '16/9' },
							{ label: '1:1', value: '1/1' },
							{ label: '3:2', value: '3/2' },
						] }
						onChange={ ( value ) =>
							setAttributes( { aspectRatio: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="aucteeno-field-image__wrapper" style={ style }>
					{ imageUrl ? (
						<img
							className="aucteeno-field-image__img"
							src={ imageUrl }
							alt={ title }
						/>
					) : (
						<div className="aucteeno-field-image__placeholder">
							{ __( 'No image', 'aucteeno' ) }
						</div>
					) }
				</div>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
} );
