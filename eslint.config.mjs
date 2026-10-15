import wpPlugin from '@wordpress/eslint-plugin';
import { createRequire } from 'node:module';
import globals from 'globals';

const require = createRequire( import.meta.url );

export default [
	{
		ignores: [ 'dist/**', 'vendor/**', 'node_modules/**' ],
	},
	...wpPlugin.configs[ 'recommended-with-formatting' ],
	{
		languageOptions: {
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [
						require.resolve( '@wordpress/babel-preset-default' ),
					],
				},
			},
			globals: {
				...globals.browser,
				jQuery: 'readonly',
				$: 'readonly',
				hanmoto: 'readonly',
				Chart: 'readonly',
			},
		},
		rules: {
			'no-alert': 'off',
			'object-shorthand': 'off',
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/no-undefined-types': 'off',
			'react/react-in-jsx-scope': 'off',
			'react/jsx-sort-props': 'off',
			'jsdoc/check-tag-names': 'off',
			'import/order': 'off',
			'no-prototype-builtins': 'off',
			'object-curly-newline': 'off',
			'object-property-newline': 'off',
			yoda: 'off',
			strict: 'off',
			'@wordpress/valid-sprintf': 'off',
			camelcase: 'off',
		},
	},
];
