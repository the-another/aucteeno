/**
 * Item Card Block - Editor Script
 *
 * Minimal editor representation for the item card block.
 *
 * @package Aucteeno
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import metadata from '../block.json';
import './editor.css';
import './style.css';

/**
 * Edit component for Item Card block.
 *
 * @return {JSX.Element} Block editor interface.
 */
function Edit() {
	const blockProps = useBlockProps( { className: 'aucteeno-item-card' } );

	return (
		<div { ...blockProps }>
			<div className="aucteeno-card-placeholder">
				{ __( 'Item Card (used in Items Listing)', 'aucteeno' ) }
			</div>
		</div>
	);
}

/**
 * Register the block.
 */
registerBlockType( metadata.name, {
	edit: Edit,
} );
