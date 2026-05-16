/**
 * ESLint flat config pre celý projekt — preferuje @wordpress/eslint-plugin
 * defaults pre frontend JS / Gutenberg bloky v plugine.
 *
 * Aktivuje sa po `npm install` v koreňovom priečinku alebo v plugine.
 */

import wpPlugin from '@wordpress/eslint-plugin/configs/recommended.js';

export default [
	{
		ignores: [
			'**/node_modules/**',
			'**/vendor/**',
			'web/wp/**',
			'web/app/mu-plugins/**',
			'web/app/plugins/*/build/**',
			'web/app/themes/*/build/**',
			'**/dist/**',
		],
	},
	...wpPlugin,
];
