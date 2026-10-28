const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		widget: './src/widget/index.js',
		settings: './src/settings/index.js',
	},
};
