/**
 * Webpack Configuration for Aucteeno Blocks
 *
 * Uses @wordpress/scripts default configuration with custom entry points
 * for each block's editor and view scripts.
 *
 * @package Aucteeno
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		// Query Loop block
		'query-loop/editor': path.resolve( __dirname, 'blocks/query-loop/src/editor.js' ),

		// Card block
		'card/editor': path.resolve( __dirname, 'blocks/card/src/editor.js' ),

		// Field blocks
		'field-image/editor': path.resolve( __dirname, 'blocks/field-image/src/editor.js' ),
		'field-title/editor': path.resolve( __dirname, 'blocks/field-title/src/editor.js' ),
		'field-countdown/editor': path.resolve( __dirname, 'blocks/field-countdown/src/editor.js' ),
		'field-countdown/view': path.resolve( __dirname, 'blocks/field-countdown/src/view.js' ),
		'field-location/editor': path.resolve( __dirname, 'blocks/field-location/src/editor.js' ),
		'field-current-bid/editor': path.resolve( __dirname, 'blocks/field-current-bid/src/editor.js' ),
		'field-reserve-price/editor': path.resolve( __dirname, 'blocks/field-reserve-price/src/editor.js' ),
		'field-lot-number/editor': path.resolve( __dirname, 'blocks/field-lot-number/src/editor.js' ),
		'field-bidding-status/editor': path.resolve( __dirname, 'blocks/field-bidding-status/src/editor.js' ),

		// Pagination block
		'pagination/editor': path.resolve( __dirname, 'blocks/pagination/src/editor.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'dist/blocks' ),
		filename: '[name].js',
		clean: true,
	},
};
