const path = require('path')
const rootDir = path.resolve(__dirname, '../../../')

module.exports = {
	testMatch: ['**/tests/**/*.spec.{js,ts}'],
	moduleNameMapper: {
		'\\.(scss)$': '<rootDir>/tests/jest/stubs/empty.js',
	},
	preset: '@vue/cli-plugin-unit-jest/presets/no-babel',
	collectCoverage: true,
	collectCoverageFrom: ['./src/**'],
	coverageDirectory: '<rootDir>/coverage/jest/',
	coverageReporters: ['lcov', 'html', 'text']

}
