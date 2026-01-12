/**
 * Aucteeno Field Title Block - Editor Script
 *
 * @package Aucteeno
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from '../block.json';

function Edit( { attributes, setAttributes, context } ) {
	const { tagName = 'h3', isLink = true } = attributes;
	const itemData = context?.[ 'aucteeno/item' ] || {};
	const title = itemData.title || __( 'Sample Title', 'aucteeno' );

	const blockProps = useBlockProps( {
		className: 'aucteeno-field-title',
	} );

	const TagName = tagName;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Title Settings', 'aucteeno' ) }>
					<SelectControl
						label={ __( 'HTML Tag', 'aucteeno' ) }
						value={ tagName }
						options={ [
							{ label: 'H1', value: 'h1' },
							{ label: 'H2', value: 'h2' },
							{ label: 'H3', value: 'h3' },
							{ label: 'H4', value: 'h4' },
							{ label: 'H5', value: 'h5' },
							{ label: 'H6', value: 'h6' },
							{ label: 'P', value: 'p' },
							{ label: 'Span', value: 'span' },
						] }
						onChange={ ( value ) => setAttributes( { tagName: value } ) }
					/>
					<ToggleControl
						label={ __( 'Link to item', 'aucteeno' ) }
						checked={ isLink }
						onChange={ ( value ) => setAttributes( { isLink: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<TagName { ...blockProps }>
				{ isLink ? <a href="#">{ title }</a> : title }
			</TagName>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
} );
