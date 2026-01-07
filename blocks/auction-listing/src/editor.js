/**
 * Auction Listing Block - Editor Script
 *
 * Editor interface for the auction listing block with live preview.
 *
 * @package Aucteeno
 */

import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
	TextControl,
	Placeholder,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from '../block.json';
import './editor.css';

/**
 * Edit component for Auction Listing block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update attributes.
 * @return {JSX.Element} Block editor interface.
 */
function Edit( { attributes, setAttributes } ) {
	const {
		perPage,
		sort,
		locationFilterEnabled,
		columnsDesktop,
		columnsTablet,
		columnsMobile,
		showSourceLabel,
		showImage,
		emptyStateText,
	} = attributes;

	const blockProps = useBlockProps( { className: 'aucteeno-auction-listing' } );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Display Settings', 'aucteeno' ) }
					initialOpen={ true }
				>
					<RangeControl
						label={ __( 'Items per page', 'aucteeno' ) }
						value={ perPage }
						onChange={ ( value ) =>
							setAttributes( { perPage: value } )
						}
						min={ 4 }
						max={ 50 }
					/>
					<SelectControl
						label={ __( 'Sort order', 'aucteeno' ) }
						value={ sort }
						options={ [
							{
								label: __( 'Ending Soon', 'aucteeno' ),
								value: 'ending_soon',
							},
							{
								label: __( 'Newest First', 'aucteeno' ),
								value: 'newest',
							},
							{
								label: __( 'Title A-Z', 'aucteeno' ),
								value: 'title_asc',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { sort: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show location filter', 'aucteeno' ) }
						checked={ locationFilterEnabled }
						onChange={ ( value ) =>
							setAttributes( { locationFilterEnabled: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show source label', 'aucteeno' ) }
						checked={ showSourceLabel }
						onChange={ ( value ) =>
							setAttributes( { showSourceLabel: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show image', 'aucteeno' ) }
						checked={ showImage }
						onChange={ ( value ) =>
							setAttributes( { showImage: value } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Grid Settings', 'aucteeno' ) }
					initialOpen={ false }
				>
					<RangeControl
						label={ __( 'Columns (Desktop)', 'aucteeno' ) }
						value={ columnsDesktop }
						onChange={ ( value ) =>
							setAttributes( { columnsDesktop: value } )
						}
						min={ 1 }
						max={ 6 }
					/>
					<RangeControl
						label={ __( 'Columns (Tablet)', 'aucteeno' ) }
						value={ columnsTablet }
						onChange={ ( value ) =>
							setAttributes( { columnsTablet: value } )
						}
						min={ 1 }
						max={ 4 }
					/>
					<RangeControl
						label={ __( 'Columns (Mobile)', 'aucteeno' ) }
						value={ columnsMobile }
						onChange={ ( value ) =>
							setAttributes( { columnsMobile: value } )
						}
						min={ 1 }
						max={ 2 }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Empty State', 'aucteeno' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __( 'Empty state message', 'aucteeno' ) }
						value={ emptyStateText }
						onChange={ ( value ) =>
							setAttributes( { emptyStateText: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
					LoadingResponsePlaceholder={ () => (
						<Placeholder
							icon="grid-view"
							label={ __( 'Auction Listing', 'aucteeno' ) }
						>
							<Spinner />
						</Placeholder>
					) }
					EmptyResponsePlaceholder={ () => (
						<Placeholder
							icon="grid-view"
							label={ __( 'Auction Listing', 'aucteeno' ) }
						>
							{ emptyStateText || __( 'No auctions found.', 'aucteeno' ) }
						</Placeholder>
					) }
				/>
			</div>
		</>
	);
}

/**
 * Register the block.
 */
registerBlockType( metadata.name, {
	edit: Edit,
} );
