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
			'import/extensions': 'off'
		}
	}
])
