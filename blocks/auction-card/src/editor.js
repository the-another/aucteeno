/**
 * Auction Card Block - Editor Script
 *
 * Minimal editor representation for the auction card block.
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
 * Edit component for Auction Card block.
 *
 * @return {JSX.Element} Block editor interface.
 */
function Edit() {
	const blockProps = useBlockProps( { className: 'aucteeno-auction-card' } );

	return (
		<div { ...blockProps }>
			<div className="aucteeno-card-placeholder">
				{ __( 'Auction Card (used in Auction Listing)', 'aucteeno' ) }
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
