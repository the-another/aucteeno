/**
 * Items Listing Block - Editor Script
 *
 * Editor interface for the items listing block with live preview.
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
 * Edit component for Items Listing block.
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
		auctionId,
		columnsDesktop,
		columnsTablet,
		columnsMobile,
		emptyStateText,
	} = attributes;

	const blockProps = useBlockProps( { className: 'aucteeno-items-listing' } );

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
							{
								label: __( 'Price Low to High', 'aucteeno' ),
								value: 'price_asc',
							},
							{
								label: __( 'Price High to Low', 'aucteeno' ),
								value: 'price_desc',
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
					<TextControl
						label={ __( 'Filter by Auction ID', 'aucteeno' ) }
						help={ __(
							'Leave empty to show all items, or enter an auction ID to filter.',
							'aucteeno'
						) }
						value={ auctionId }
						onChange={ ( value ) =>
							setAttributes( { auctionId: value } )
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
							icon="screenoptions"
							label={ __( 'Items Listing', 'aucteeno' ) }
						>
							<Spinner />
						</Placeholder>
					) }
					EmptyResponsePlaceholder={ () => (
						<Placeholder
							icon="screenoptions"
							label={ __( 'Items Listing', 'aucteeno' ) }
						>
							{ emptyStateText || __( 'No items found.', 'aucteeno' ) }
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
