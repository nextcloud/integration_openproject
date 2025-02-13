/* jshint esversion: 8 */
/**
 * SPDX-FileCopyrightText: 2021-2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const router = jest.createMockFromModule('@nextcloud/router')

router.generateUrl = jest.fn(function(url) {
	return 'http://localhost' + url
})

module.exports = router
