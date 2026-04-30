/**
 * Aucteeno Product CTA Block - Editor.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { applyFilters } from '@wordpress/hooks';
import {
	PanelBody,
	ToggleControl,
	TextControl,
	SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';
import './style.css';

function Edit( { attributes, setAttributes, name } ) {
	const {
		showBiddingButton,
		biddingButtonText,
		biddingButtonIcon,
		layout,
		buttonAlignment,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'aucteeno-product-cta-editor',
	} );

	const previewButtons = applyFilters(
		'aucteeno.productCta.previewButtons',
		[],
		attributes,
		name
	);

	const hasAnyButton = showBiddingButton || previewButtons.length > 0;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Bidding Button', 'aucteeno' ) }>
					<ToggleControl
						label={ __(
							'Show "View Bidding Page" button',
							'aucteeno'
						) }
						checked={ showBiddingButton }
						onChange={ ( value ) =>
							setAttributes( { showBiddingButton: value } )
						}
						help={ __(
							'Redirects to the external bidding page.',
							'aucteeno'
						) }
					/>
					{ showBiddingButton && (
						<>
							<TextControl
								label={ __( 'Button Text', 'aucteeno' ) }
								value={ biddingButtonText }
								onChange={ ( value ) =>
									setAttributes( {
										biddingButtonText: value,
									} )
								}
							/>
							<SelectControl
								label={ __( 'Icon Position', 'aucteeno' ) }
								value={ biddingButtonIcon }
								options={ [
									{
										label: __( 'No Icon', 'aucteeno' ),
										value: 'none',
									},
									{
										label: __( 'Before Text', 'aucteeno' ),
										value: 'before',
									},
									{
										label: __( 'After Text', 'aucteeno' ),
										value: 'after',
									},
								] }
								onChange={ ( value ) =>
									setAttributes( {
										biddingButtonIcon: value,
									} )
								}
							/>
						</>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Layout Settings', 'aucteeno' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Button Layout', 'aucteeno' ) }
						value={ layout }
						options={ [
							{
								label: __( 'Horizontal', 'aucteeno' ),
								value: 'horizontal',
							},
							{
								label: __( 'Vertical', 'aucteeno' ),
								value: 'vertical',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { layout: value } )
						}
					/>
					<SelectControl
						label={ __( 'Button Alignment', 'aucteeno' ) }
						value={ buttonAlignment }
						options={ [
							{ label: __( 'Left', 'aucteeno' ), value: 'left' },
							{
								label: __( 'Center', 'aucteeno' ),
								value: 'center',
							},
							{
								label: __( 'Right', 'aucteeno' ),
								value: 'right',
							},
							{
								label: __( 'Stretch', 'aucteeno' ),
								value: 'stretch',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { buttonAlignment: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div
					className={ `wp-block-aucteeno-product-cta__preview is-layout-${ layout } is-content-justification-${ buttonAlignment }` }
				>
					{ ! hasAnyButton && (
						<div className="wp-block-aucteeno-product-cta__notice">
							{ __(
								'⚠️ Please enable at least one button in the block settings.',
								'aucteeno'
							) }
						</div>
					) }

					{ hasAnyButton && (
						<div className="wp-block-aucteeno-product-cta__preview-buttons">
							{ showBiddingButton && (
								<button
									className={ `wp-block-aucteeno-product-cta__preview-button is-bidding${
										biddingButtonIcon !== 'none'
											? ' has-icon-' + biddingButtonIcon
											: ''
									}` }
									disabled
								>
									<span className="button-text">
										{ biddingButtonText }
									</span>
								</button>
							) }
							{ previewButtons }
						</div>
					) }

					<p className="wp-block-aucteeno-product-cta__preview-hint">
						{ __(
							'Button appearance will match your theme styling.',
							'aucteeno'
						) }
					</p>
				</div>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
} );
