/**
 * Aucteeno Field Bidding Status Block - Editor Script
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';

import metadata from '../block.json';

function Edit( { context } ) {
	const itemData = context?.[ 'aucteeno/item' ] || {};
	const biddingStatus = itemData.bidding_status || 10;

	const { label, statusClass } = useMemo( () => {
		const statusMap = {
			10: { label: __( 'Running', 'aucteeno' ), class: 'running' },
			20: { label: __( 'Upcoming', 'aucteeno' ), class: 'upcoming' },
			30: { label: __( 'Expired', 'aucteeno' ), class: 'expired' },
		};
		return statusMap[ biddingStatus ] || statusMap[ 10 ];
	}, [ biddingStatus ] );

	const blockProps = useBlockProps( {
		className: `aucteeno-field-bidding-status aucteeno-field-bidding-status--${ statusClass }`,
	} );

	return <span { ...blockProps }>{ label }</span>;
}

registerBlockType( metadata.name, { edit: Edit } );
