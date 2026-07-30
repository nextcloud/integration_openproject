/**
 * SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const { recommendedJavascript: nextcloudConfig } = require('@nextcloud/eslint-config')
const { defineConfig } = require('eslint/config')

module.exports = defineConfig([
	...nextcloudConfig,
	{
		languageOptions: {
			globals: {
				appVersion: true,
			},
			parserOptions: {
				requireConfigFile: false,
			}
		},
		rules: {
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/tag-lines': 'off',
			'vue/first-attribute-linebreak': 'off',
			'import/extensions': 'off',
			'perfectionist/sort-imports': 'off',
			'perfectionist/sort-named-imports': 'off',
			'@stylistic/eol-last': 'off',
			'vue/attribute-hyphenation': 'off',
			'vue/new-line-between-multi-line-property': 'off',
			'no-console': 'off',
			'@stylistic/function-paren-newline': 'off',
			'no-unused-vars': 'off',
			'@stylistic/arrow-parens': 'off',
			'vue/padding-line-between-blocks': 'off',
			'@nextcloud/no-deprecated-library-props': 'off',
			'@nextcloud/no-deprecated-globals': 'off',
			'object-shorthand': 'off',
			'@stylistic/indent': 'off',
			'@stylistic/indent-binary-ops': 'off',
			'curly': 'off',
			'vue/comma-dangle': 'off',
			'vue/quote-props': 'off',
			'@stylistic/exp-list-style': 'off',
			'vue/no-deprecated-v-bind-sync': 'off',
			'vue/require-explicit-emits': 'off',
			'vue/comma-spacing': 'off',
			'vue/no-unused-refs': 'off',
			'@stylistic/padded-blocks': 'off',
			'vue/no-unused-properties': 'off',
			'vue/space-infix-ops': 'off',
			'vue/no-boolean-default': 'off',
			'vue/v-on-event-hyphenation': 'off',
			'antfu/top-level-function': 'off',
			'vue/no-deprecated-v-on-native-modifier': 'off',
			'no-useless-assignment': 'off',
			'vue/no-required-prop-with-default': 'off',
			'preserve-caught-error': 'off',
			'vue/custom-event-name-casing': 'off',
			'vue/multi-word-component-names': 'off',
			'vue/no-deprecated-destroyed-lifecycle': 'off',
		}
	}
])
