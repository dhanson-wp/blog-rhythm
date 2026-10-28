/**
 * Jest config. wp-scripts 37 no longer supplies WordPress defaults for Jest,
 * so this is the minimum: Babel via the WordPress preset, jsdom, stylesheet
 * stubs, and transforming the ESM-only @wordpress/* packages.
 */
module.exports = {
	testEnvironment: 'jsdom',
	roots: [ '<rootDir>/src' ],
	setupFiles: [ '<rootDir>/tests/js/setup.js' ],
	testMatch: [ '**/test/**/*.test.js' ],
	transform: {
		'\\.m?[jt]sx?$': [
			'babel-jest',
			{ presets: [ '@wordpress/babel-preset-default' ] },
		],
	},
	transformIgnorePatterns: [ '/node_modules/(?!(@wordpress|@base-ui|@floating-ui|uuid)/)' ],
	moduleFileExtensions: [ 'js', 'mjs', 'json', 'jsx' ],
	moduleNameMapper: {
		'\\.(css|scss)$': '<rootDir>/tests/js/style-mock.js',
	},
};
