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
		// Auction Listing
		'auction-listing/editor': path.resolve( __dirname, 'blocks/auction-listing/src/editor.js' ),
		'auction-listing/view': path.resolve( __dirname, 'blocks/auction-listing/src/view.js' ),

		// Items Listing
		'items-listing/editor': path.resolve( __dirname, 'blocks/items-listing/src/editor.js' ),
		'items-listing/view': path.resolve( __dirname, 'blocks/items-listing/src/view.js' ),

		// Auction Card
		'auction-card/editor': path.resolve( __dirname, 'blocks/auction-card/src/editor.js' ),

		// Item Card
		'item-card/editor': path.resolve( __dirname, 'blocks/item-card/src/editor.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'dist/blocks' ),
		filename: '[name].js',
		clean: true, // Safe to clean since dist/blocks is separate from source
	},
};
