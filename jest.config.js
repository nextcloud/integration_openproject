const path = require('path')
const rootDir = path.resolve(__dirname, '../../../')

const ignorePatterns = [
	'vue-material-design-icons',
	'@nextcloud/vue',
]

module.exports = {
	testMatch: ['**/tests/**/*.spec.{js,ts}'],
	moduleNameMapper: {
		'\\.(css|scss)$': '<rootDir>/tests/jest/stubs/empty.js',
		'@nextcloud/l10n/gettext': require.resolve('@nextcloud/l10n/gettext')
	},
	transform: {
		// process `*.js` files with `babel-jest`
		'^.+\\.c?js$': 'babel-jest',
		'^.+\\.vue$': '@vue/vue2-jest'
	},
	collectCoverage: true,
	testEnvironment: 'jest-environment-jsdom',
	coverageProvider: 'v8',
	collectCoverageFrom: ['./src/**'],
	coverageDirectory: '<rootDir>/coverage/jest/',
	coverageReporters: ['lcov', 'html', 'text'],
	transformIgnorePatterns: [
		'node_modules/(?!(' + ignorePatterns.join('|') + ')/)',
	],
	setupFiles: ["<rootDir>/tests/jest/global.mock.js"]
}
