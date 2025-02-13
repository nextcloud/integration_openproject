/* jshint esversion: 8 */
/**
 * SPDX-FileCopyrightText: 2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const l10n = jest.createMockFromModule('@nextcloud/l10n')

l10n.translate = jest.fn(function(app, msg) {
	return msg
})

module.exports = l10n
