import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RadioControl,
	RangeControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

import './editor.css';
import './style.css';

const DEBOUNCE_OPTIONS = [
	{ label: __( 'Instant (0 ms)', 'aucteeno' ), value: 'instant' },
	{ label: __( 'Fast (150 ms)', 'aucteeno' ), value: 'fast' },
	{ label: __( 'Normal (250 ms)', 'aucteeno' ), value: 'normal' },
	{ label: __( 'Relaxed (500 ms)', 'aucteeno' ), value: 'relaxed' },
];

function PagePicker( { label, value, onChange } ) {
	const pages = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'postType', 'page', {
				per_page: -1,
				status: 'publish',
			} ),
		[]
	);
	const options = [
		{ label: __( '— Not configured —', 'aucteeno' ), value: 0 },
		...( pages ?? [] ).map( ( p ) => ( {
			label: p.title?.rendered || `#${ p.id }`,
			value: p.id,
		} ) ),
	];
	return (
		<SelectControl
			label={ label }
			value={ value }
			options={ options }
			onChange={ ( v ) => onChange( parseInt( v, 10 ) || 0 ) }
		/>
	);
}

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'wp-block-aucteeno-search wp-block-aucteeno-search--editor',
	} );
	const placeholder = (
		attributes.placeholderTemplate || '%d items to search from'
	).replace( '%d', '12,345' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Search', 'aucteeno' ) } initialOpen>
					<RadioControl
						label={ __( 'Default type', 'aucteeno' ) }
						selected={ attributes.defaultType }
						options={ [
							{
								label: __( 'Items', 'aucteeno' ),
								value: 'items',
							},
							{
								label: __( 'Auctions', 'aucteeno' ),
								value: 'auctions',
							},
						] }
						onChange={ ( v ) =>
							setAttributes( { defaultType: v } )
						}
					/>
					<SelectControl
						label={ __( 'Debounce', 'aucteeno' ) }
						value={ attributes.debouncePreset }
						options={ DEBOUNCE_OPTIONS }
						onChange={ ( v ) =>
							setAttributes( { debouncePreset: v } )
						}
					/>
				</PanelBody>
				<PanelBody title={ __( 'Recent searches', 'aucteeno' ) }>
					<RangeControl
						label={ __(
							'Save delay after pause (sec)',
							'aucteeno'
						) }
						min={ 1 }
						max={ 60 }
						value={ attributes.recentSearchTimeoutSec }
						onChange={ ( v ) =>
							setAttributes( {
								recentSearchTimeoutSec: v,
							} )
						}
					/>
				</PanelBody>
				<PanelBody title={ __( 'Placeholder count', 'aucteeno' ) }>
					<RangeControl
						label={ __(
							'Cache duration (minutes; 0 = no cache)',
							'aucteeno'
						) }
						min={ 0 }
						max={ 60 }
						value={ attributes.countCacheMinutes }
						onChange={ ( v ) =>
							setAttributes( {
								countCacheMinutes: v,
							} )
						}
					/>
					<TextControl
						label={
							// translators: %d is replaced with the item count.
							__(
								'Placeholder text (use %d for the count)',
								'aucteeno'
							)
						}
						value={ attributes.placeholderTemplate }
						onChange={ ( v ) =>
							setAttributes( { placeholderTemplate: v } )
						}
					/>
				</PanelBody>
				<PanelBody title={ __( '"View all" pages', 'aucteeno' ) }>
					<PagePicker
						label={ __( 'Items page', 'aucteeno' ) }
						value={ attributes.viewAllItemsPageId }
						onChange={ ( v ) =>
							setAttributes( { viewAllItemsPageId: v } )
						}
					/>
					<PagePicker
						label={ __( 'Auctions page', 'aucteeno' ) }
						value={ attributes.viewAllAuctionsPageId }
						onChange={ ( v ) =>
							setAttributes( { viewAllAuctionsPageId: v } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<button
					type="button"
					className="wp-block-aucteeno-search__trigger"
					disabled
				>
					<svg
						className="wp-block-aucteeno-search__icon"
						aria-hidden="true"
						focusable="false"
						width="20"
						height="20"
						viewBox="0 0 24 24"
					>
						<path
							fill="currentColor"
							d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"
						/>
					</svg>
					<span className="wp-block-aucteeno-search__placeholder">
						{ placeholder }
					</span>
				</button>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
