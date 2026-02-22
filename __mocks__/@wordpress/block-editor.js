const React = require( 'react' );

const useBlockProps = ( props = {} ) => ( {
	...props,
	'data-testid': 'block-props',
} );

const InspectorControls = ( { children } ) =>
	React.createElement( 'div', { 'data-testid': 'inspector-controls' }, children );

const InnerBlocks = ( { children } ) =>
	React.createElement( 'div', { 'data-testid': 'inner-blocks' }, children );
InnerBlocks.Content = () =>
	React.createElement( 'div', { 'data-testid': 'inner-blocks-content' } );
InnerBlocks.ButtonBlockAppender = () =>
	React.createElement( 'button', { 'data-testid': 'block-appender' }, '+' );

const useInnerBlocksProps = ( props = {} ) => ( {
	...props,
	'data-testid': 'inner-blocks-props',
} );

module.exports = {
	useBlockProps,
	InspectorControls,
	InnerBlocks,
	useInnerBlocksProps,
};
