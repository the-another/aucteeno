module.exports = {
	__: ( s ) => s,
	_x: ( s ) => s,
	_n: ( single, plural, number ) => ( number === 1 ? single : plural ),
	sprintf: ( fmt, ...args ) => {
		let i = 0;
		return fmt.replace( /%[sd]/g, () => args[ i++ ] );
	},
};
