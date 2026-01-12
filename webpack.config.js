/**
 * Webpack Configuration for Aucteeno Blocks
 *
 * Uses @wordpress/scripts default configuration with custom entry points.
 * View scripts are output as ES modules for Interactivity API compatibility.
 *
 * @package Aucteeno
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const path = require( 'path' );

// Separate entry points for editor scripts (regular) and view scripts (ES modules).
const editorEntries = {
	// Query Loop block
	'query-loop/editor': path.resolve( __dirname, 'blocks/query-loop/src/editor.js' ),

	// Card block
	'card/editor': path.resolve( __dirname, 'blocks/card/src/editor.js' ),

	// Field blocks
	'field-image/editor': path.resolve( __dirname, 'blocks/field-image/src/editor.js' ),
	'field-title/editor': path.resolve( __dirname, 'blocks/field-title/src/editor.js' ),
	'field-countdown/editor': path.resolve( __dirname, 'blocks/field-countdown/src/editor.js' ),
	'field-location/editor': path.resolve( __dirname, 'blocks/field-location/src/editor.js' ),
	'field-current-bid/editor': path.resolve( __dirname, 'blocks/field-current-bid/src/editor.js' ),
	'field-reserve-price/editor': path.resolve( __dirname, 'blocks/field-reserve-price/src/editor.js' ),
	'field-lot-number/editor': path.resolve( __dirname, 'blocks/field-lot-number/src/editor.js' ),
	'field-bidding-status/editor': path.resolve( __dirname, 'blocks/field-bidding-status/src/editor.js' ),

	// Pagination block
	'pagination/editor': path.resolve( __dirname, 'blocks/pagination/src/editor.js' ),
};

const viewEntries = {
	'query-loop/view': path.resolve( __dirname, 'blocks/query-loop/src/view.js' ),
	'field-countdown/view': path.resolve( __dirname, 'blocks/field-countdown/src/view.js' ),
};

// Editor config - standard @wordpress/scripts config.
const editorConfig = {
	...defaultConfig,
	name: 'editor',
	entry: editorEntries,
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'dist/blocks' ),
		clean: false, // Don't clean - view config outputs here too.
	},
};

// View config - ES module output for Interactivity API.
const viewConfig = {
	...defaultConfig,
	name: 'view',
	entry: viewEntries,
	output: {
		path: path.resolve( __dirname, 'dist/blocks' ),
		filename: '[name].js',
		clean: false, // Let editor config handle cleaning.
		module: true,
		chunkFormat: 'module',
	},
	experiments: {
		outputModule: true,
	},
	plugins: [
		// Keep default plugins but replace dependency extraction for module output.
		...defaultConfig.plugins.filter(
			( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin(),
	],
	optimization: {
		...defaultConfig.optimization,
		splitChunks: false,
		runtimeChunk: false,
	},
};

module.exports = [ editorConfig, viewConfig ];
