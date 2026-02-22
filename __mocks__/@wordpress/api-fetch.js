const fn = jest.fn( () => Promise.resolve( [] ) );
module.exports = fn;
module.exports.default = fn;
