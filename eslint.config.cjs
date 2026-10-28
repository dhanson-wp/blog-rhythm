/**
 * ESLint config: the `@wordpress/scripts` default, plus Jest for unit tests.
 *
 * The default `test-unit` preset targets Vitest, so it doesn't define the
 * Jest globals (`test`, `expect`, `jest`) these tests use.
 */
const globals = require( 'globals' );
const wpScriptsConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...wpScriptsConfig,
	{
		files: [
			'**/@(test|__tests__)/**/*.{js,jsx}',
			'**/*.@(test|spec).{js,jsx}',
			'tests/js/**/*.js',
		],
		languageOptions: {
			globals: { ...globals.browser, ...globals.jest, ...globals.node },
		},
		rules: {
			'jsdoc/check-tag-names': [
				'error',
				{ definedTags: [ 'jest-environment' ] },
			],
		},
	},
];
