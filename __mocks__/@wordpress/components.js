const React = require( 'react' );

const PanelBody = ( { title, children } ) =>
	React.createElement( 'div', { 'data-testid': 'panel-body' }, children );

const ToggleControl = ( { label } ) =>
	React.createElement( 'div', { 'data-testid': 'toggle-control' }, label );

const SelectControl = ( { label } ) =>
	React.createElement( 'div', { 'data-testid': 'select-control' }, label );

const TextControl = ( { label } ) =>
	React.createElement( 'div', { 'data-testid': 'text-control' }, label );

const RangeControl = ( { label } ) =>
	React.createElement( 'div', { 'data-testid': 'range-control' }, label );

const Placeholder = ( { children } ) =>
	React.createElement( 'div', { 'data-testid': 'placeholder' }, children );

const Spinner = () =>
	React.createElement( 'div', { 'data-testid': 'spinner' } );

const Notice = ( { children } ) =>
	React.createElement( 'div', { 'data-testid': 'notice' }, children );

const __experimentalUnitControl = ( { label } ) =>
	React.createElement( 'div', { 'data-testid': 'unit-control' }, label );

module.exports = {
	PanelBody,
	ToggleControl,
	SelectControl,
	TextControl,
	RangeControl,
	Placeholder,
	Spinner,
	Notice,
	__experimentalUnitControl,
};
