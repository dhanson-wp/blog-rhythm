const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	// The settings bundle is admin-only and loads on one screen; the size
	// hint is noise here, so keep the build output clean.
	performance: { hints: false },
	entry: {
		widget: './src/widget/index.js',
		settings: './src/settings/index.js',
	},
};
