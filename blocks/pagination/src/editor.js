/**
 * Aucteeno Pagination Block - Editor Script
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from '../block.json';

function Edit( { attributes, setAttributes, context } ) {
	const { showPageNumbers = true, showPrevNext = true } = attributes;
	const queryData = context?.[ 'aucteeno/query' ] || {};
	const totalPages = queryData.totalPages || 5;
	const currentPage = queryData.currentPage || 1;

	const blockProps = useBlockProps( { className: 'aucteeno-pagination' } );

	// Generate preview pagination.
	const pageNumbers = [];
	for ( let i = 1; i <= Math.min( totalPages, 5 ); i++ ) {
		pageNumbers.push( i );
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Pagination Settings', 'aucteeno' ) }>
					<ToggleControl
						label={ __( 'Show page numbers', 'aucteeno' ) }
						checked={ showPageNumbers }
						onChange={ ( value ) =>
							setAttributes( { showPageNumbers: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show Previous/Next', 'aucteeno' ) }
						checked={ showPrevNext }
						onChange={ ( value ) =>
							setAttributes( { showPrevNext: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<nav
				{ ...blockProps }
				aria-label={ __( 'Pagination', 'aucteeno' ) }
			>
				<div className="aucteeno-pagination__inner">
					{ showPrevNext && currentPage > 1 && (
						<span className="aucteeno-pagination__prev">
							{ __( '← Previous', 'aucteeno' ) }
						</span>
					) }
					{ showPageNumbers &&
						pageNumbers.map( ( num ) => (
							<span
								key={ num }
								className={ `aucteeno-pagination__page${
									num === currentPage
										? ' aucteeno-pagination__page--current'
										: ''
								}` }
							>
								{ num }
							</span>
						) ) }
					{ showPrevNext && currentPage < totalPages && (
						<span className="aucteeno-pagination__next">
							{ __( 'Next →', 'aucteeno' ) }
						</span>
					) }
				</div>
			</nav>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
