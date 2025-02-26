/**
 * SPDX-FileCopyrightText: 2021-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const path = require('path')
const rootDir = path.resolve(__dirname, '../../../')

module.exports = {
	testMatch: ['**/tests/**/*.spec.{js,ts}'],
	moduleNameMapper: {
		'\\.(scss)$': '<rootDir>/tests/jest/stubs/empty.js',
		'@nextcloud/l10n/gettext': require.resolve('@nextcloud/l10n/gettext'),
	},
	transform: {
		// process *.vue files with vue-jest
		'\\.vue$': '@vue/vue2-jest',
		'.+\\.(css|styl|less|sass|scss|jpg|jpeg|png|svg|gif|eot|otf|webp|ttf|woff|woff2|mp4|webm|wav|mp3|m4a|aac|oga|avif)$':
			'jest-transform-stub',
		'\\.c?js$': 'babel-jest',
	},
	testEnvironment: 'jest-environment-jsdom',
	collectCoverage: true,
	coverageProvider: 'v8',
	collectCoverageFrom: ['./src/**'],
	coverageDirectory: '<rootDir>/coverage/jest/',
	coverageReporters: ['lcov', 'html', 'text'],
	transformIgnorePatterns: ['node_modules/(?!@ckeditor)/.+\\.js$'],
	setupFiles: ['<rootDir>/tests/jest/global.mock.js'],
}
