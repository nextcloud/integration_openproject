/* jshint esversion: 8 */
const l10n = jest.createMockFromModule('@nextcloud/l10n')

l10n.translate = jest.fn(function(app, msg) {
	return msg
})

module.exports = l10n
