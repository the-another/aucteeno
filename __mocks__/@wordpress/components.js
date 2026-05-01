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

const RadioControl = ( { label } ) =>
	React.createElement( 'div', { 'data-testid': 'radio-control' }, label );

const __experimentalUnitControl = ( { label } ) =>
	React.createElement( 'div', { 'data-testid': 'unit-control' }, label );

const __experimentalNumberControl = ( { label } ) =>
	React.createElement( 'div', { 'data-testid': 'number-control' }, label );

module.exports = {
	PanelBody,
	ToggleControl,
	RadioControl,
	SelectControl,
	TextControl,
	RangeControl,
	Placeholder,
	Spinner,
	Notice,
	__experimentalUnitControl,
	__experimentalNumberControl,
};
