const path = require('path')
const rootDir = path.resolve(__dirname, '../../../')

module.exports = {
	testMatch: ['**/tests/**/*.spec.{js,ts}'],
	moduleNameMapper: {
		'\\.(scss)$': '<rootDir>/tests/jest/stubs/empty.js',
		'@nextcloud/l10n/gettext': require.resolve('@nextcloud/l10n/gettext'),
	},
	preset: '@vue/cli-plugin-unit-jest/presets/no-babel',
	collectCoverage: true,
	coverageProvider: 'v8',
	collectCoverageFrom: ['./src/**'],
	coverageDirectory: '<rootDir>/coverage/jest/',
	coverageReporters: ['lcov', 'html', 'text'],
	transformIgnorePatterns: [
		'node_modules/(?!(vue-material-design-icons|@nextcloud/vue-select)/)',
	],
	setupFiles: ["<rootDir>/tests/jest/global.mock.js"]
}
