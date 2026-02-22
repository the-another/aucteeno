/**
 * Aucteeno Query Loop Block - Editor Script
 *
 * Editor interface for the query loop block with inner blocks support.
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
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
	Placeholder,
	Spinner,
	Notice,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import metadata from '../block.json';
import './editor.css';
import './style.css';

// Default template for query loop.
const TEMPLATE = [
	[
		'aucteeno/card',
		{},
		[
			[ 'aucteeno/field-image', {} ],
			[ 'aucteeno/field-title', { tagName: 'h3' } ],
		],
	],
	[ 'aucteeno/pagination', {} ],
];

// Allowed blocks inside query loop.
const ALLOWED_BLOCKS = [
	'aucteeno/card',
	'aucteeno/pagination',
	'core/group',
	'core/columns',
	'core/column',
	'core/row',
	'core/stack',
	'core/cover',
	'core/media-text',
];

/**
 * Placeholder item data for when no real items are available.
 */
const PLACEHOLDER_ITEM = {
	id: 0,
	title: __( 'Sample Auction', 'aucteeno' ),
	permalink: '#',
	image_url: '',
	bidding_status: 10,
	bidding_starts_at: Math.floor( Date.now() / 1000 ) - 3600,
	bidding_ends_at: Math.floor( Date.now() / 1000 ) + 86400,
	current_bid: 100,
	reserve_price: 500,
	user_id: 1,
};

/**
 * Edit component for Aucteeno Query Loop block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update attributes.
 * @param {Object}   props.context       Block context.
 * @return {JSX.Element} Block editor interface.
 */
function Edit( { attributes, setAttributes, context } ) {
	const [ items, setItems ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const {
		queryType = 'auctions',
		userId = 0,
		perPage = 12,
		columns = 4,
		displayLayout = 'grid',
		orderBy = 'ending_soon',
		infiniteScroll = false,
		updateUrl = true,
		gap = '1.5rem',
		locationCountry = '',
		locationSubdivision = '',
	} = attributes;

	// Determine effective userId from attribute or context.
	const effectiveUserId = userId || context?.userId || 0;

	// Fetch preview items from REST API.
	useEffect( () => {
		setIsLoading( true );
		setError( null );

		const endpoint =
			queryType === 'auctions'
				? '/aucteeno/v1/auctions'
				: '/aucteeno/v1/items';

		const params = new URLSearchParams( {
			page: '1',
			per_page: '6', // Fetch a few items for preview.
			sort: orderBy,
			format: 'json',
		} );

		if ( effectiveUserId ) {
			params.append( 'user_id', effectiveUserId.toString() );
		}

		apiFetch( {
			path: `${ endpoint }?${ params.toString() }`,
		} )
			.then( ( response ) => {
				const fetchedItems = response?.items || response || [];
				setItems( Array.isArray( fetchedItems ) ? fetchedItems : [] );
				setIsLoading( false );

				// Set preview item for inner blocks context.
				if ( fetchedItems.length > 0 ) {
					setAttributes( { previewItem: fetchedItems[ 0 ] } );
				}
			} )
			.catch( ( err ) => {
				// eslint-disable-next-line no-console
				console.error( 'Failed to fetch items:', err );
				setError(
					err.message || __( 'Failed to load items', 'aucteeno' )
				);
				setIsLoading( false );
			} );
	}, [ queryType, orderBy, effectiveUserId, setAttributes ] );

	// Get preview item for context.
	const previewItem = useMemo( () => {
		if ( items.length === 0 ) {
			return {
				...PLACEHOLDER_ITEM,
				title:
					queryType === 'auctions'
						? __( 'Sample Auction', 'aucteeno' )
						: __( 'Sample Item', 'aucteeno' ),
			};
		}
		return items[ 0 ] || PLACEHOLDER_ITEM;
	}, [ items, queryType ] );

	// Update the previewItem attribute so inner blocks can access it via context.
	useEffect( () => {
		setAttributes( { previewItem } );
	}, [ previewItem, setAttributes ] );

	// Flex styles for preview.
	const flexStyle = useMemo( () => {
		if ( displayLayout === 'grid' ) {
			return {
				display: 'flex',
				flexWrap: 'wrap',
				gap: gap || '1.5rem',
				'--gap': gap || '1.5rem',
			};
		}
		return {
			display: 'flex',
			flexDirection: 'column',
			gap: gap || '1.5rem',
			'--gap': gap || '1.5rem',
		};
	}, [ displayLayout, gap ] );

	const blockProps = useBlockProps( {
		className: `aucteeno-query-loop aucteeno-query-loop--${ displayLayout } aucteeno-query-loop--${ queryType }`,
	} );

	// Use inner blocks for the query structure.
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: `aucteeno-items-wrap aucteeno-items-${ displayLayout } aucteeno-items-columns-${ columns }`,
			style: flexStyle,
		},
		{
			template: TEMPLATE,
			allowedBlocks: ALLOWED_BLOCKS,
			templateLock: false,
			renderAppender: InnerBlocks.ButtonBlockAppender,
		}
	);

	/**
	 * Render the main content area based on loading/error state.
	 *
	 * @return {JSX.Element} Content for the current state.
	 */
	function renderContent() {
		if ( isLoading ) {
			return (
				<div { ...blockProps }>
					<Placeholder
						icon="grid-view"
						label={
							queryType === 'auctions'
								? __(
										'Aucteeno Query Loop (Auctions)',
										'aucteeno'
								  )
								: __(
										'Aucteeno Query Loop (Items)',
										'aucteeno'
								  )
						}
					>
						<Spinner />
						<span>{ __( 'Loading preview…', 'aucteeno' ) }</span>
					</Placeholder>
				</div>
			);
		}

		if ( error ) {
			return (
				<div { ...blockProps }>
					<Placeholder
						icon="grid-view"
						label={ __( 'Aucteeno Query Loop', 'aucteeno' ) }
					>
						<Notice status="warning" isDismissible={ false }>
							{ error }
						</Notice>
						<p>
							{ __(
								'Preview unavailable. The block will work on the frontend.',
								'aucteeno'
							) }
						</p>
					</Placeholder>
				</div>
			);
		}

		return (
			<div { ...blockProps }>
				{ items.length === 0 && (
					<Notice status="info" isDismissible={ false }>
						{ queryType === 'auctions'
							? __(
									'No auctions found. Showing placeholder.',
									'aucteeno'
							  )
							: __(
									'No items found. Showing placeholder.',
									'aucteeno'
							  ) }
					</Notice>
				) }
				<ul { ...innerBlocksProps } />
			</div>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Query Settings', 'aucteeno' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Query Type', 'aucteeno' ) }
						help={ __(
							'Choose whether to display auctions or items.',
							'aucteeno'
						) }
						value={ queryType }
						options={ [
							{
								label: __( 'Auctions', 'aucteeno' ),
								value: 'auctions',
							},
							{
								label: __( 'Items', 'aucteeno' ),
								value: 'items',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { queryType: value } )
						}
					/>
					<TextControl
						label={ __( 'User ID', 'aucteeno' ) }
						help={ __(
							'Filter by specific vendor/user ID. Leave empty to show all or use context.',
							'aucteeno'
						) }
						type="number"
						value={ userId || '' }
						onChange={ ( value ) =>
							setAttributes( {
								userId: value ? parseInt( value, 10 ) : 0,
							} )
						}
					/>
					<RangeControl
						label={ __( 'Items per Page', 'aucteeno' ) }
						help={ __(
							'Number of items to display per page.',
							'aucteeno'
						) }
						value={ perPage }
						onChange={ ( value ) =>
							setAttributes( { perPage: value } )
						}
						min={ 1 }
						max={ 50 }
					/>
					<SelectControl
						label={ __( 'Order By', 'aucteeno' ) }
						value={ orderBy }
						options={ [
							{
								label: __( 'Ending Soon', 'aucteeno' ),
								value: 'ending_soon',
							},
							{
								label: __( 'Newest First', 'aucteeno' ),
								value: 'newest',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { orderBy: value } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Location Filters', 'aucteeno' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __( 'Country Code', 'aucteeno' ) }
						help={ __(
							'Filter by country using 2-letter code (e.g., CA, US, GB). Leave empty for all countries.',
							'aucteeno'
						) }
						value={ locationCountry }
						onChange={ ( value ) =>
							setAttributes( {
								locationCountry: value.toUpperCase(),
								locationSubdivision: value
									? locationSubdivision
									: '',
							} )
						}
						placeholder="CA"
						maxLength={ 2 }
					/>
					{ locationCountry && (
						<TextControl
							label={ __( 'Subdivision Code', 'aucteeno' ) }
							help={ __(
								'Filter by subdivision using COUNTRY:CODE format (e.g., CA:ON, US:NY). Leave empty to show all subdivisions in the country.',
								'aucteeno'
							) }
							value={ locationSubdivision }
							onChange={ ( value ) =>
								setAttributes( {
									locationSubdivision: value.toUpperCase(),
								} )
							}
							placeholder={ `${ locationCountry }:ON` }
						/>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Layout Settings', 'aucteeno' ) }
					initialOpen={ true }
				>
					<ToggleControl
						label={ __( 'Enable Infinite Scroll', 'aucteeno' ) }
						help={ __(
							'Load more items automatically when scrolling. Hides pagination.',
							'aucteeno'
						) }
						checked={ infiniteScroll }
						onChange={ ( value ) =>
							setAttributes( { infiniteScroll: value } )
						}
					/>
					{ infiniteScroll && (
						<ToggleControl
							label={ __( 'Update URL on Scroll', 'aucteeno' ) }
							help={ __(
								'Update the browser URL as more items are loaded. Disable to keep the URL unchanged.',
								'aucteeno'
							) }
							checked={ updateUrl }
							onChange={ ( value ) =>
								setAttributes( { updateUrl: value } )
							}
						/>
					) }
					<UnitControl
						label={ __( 'Gap Between Cards', 'aucteeno' ) }
						help={ __( 'Spacing between cards', 'aucteeno' ) }
						value={ gap || '1.5rem' }
						onChange={ ( value ) =>
							setAttributes( { gap: value || '1.5rem' } )
						}
						units={ [
							{ value: 'px', label: 'px' },
							{ value: 'rem', label: 'rem' },
							{ value: 'em', label: 'em' },
						] }
						isUnitSelectTabbable
					/>
					<SelectControl
						label={ __( 'Display Layout', 'aucteeno' ) }
						value={ displayLayout }
						options={ [
							{
								label: __( 'Grid', 'aucteeno' ),
								value: 'grid',
							},
							{
								label: __( 'List', 'aucteeno' ),
								value: 'list',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { displayLayout: value } )
						}
					/>
				</PanelBody>

				{ effectiveUserId > 0 && (
					<PanelBody
						title={ __( 'Context Info', 'aucteeno' ) }
						initialOpen={ false }
					>
						<p>
							{ __( 'Filtering by User ID:', 'aucteeno' ) }{ ' ' }
							<strong>{ effectiveUserId }</strong>
							{ userId > 0
								? ` (${ __( 'from attribute', 'aucteeno' ) })`
								: ` (${ __( 'from context', 'aucteeno' ) })` }
						</p>
					</PanelBody>
				) }
			</InspectorControls>

			{ renderContent() }
		</>
	);
}

/**
 * Save component - returns null for server-side rendered block.
 *
 * @return {null} Always null.
 */
function Save() {
	return <InnerBlocks.Content />;
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: Save,
} );
