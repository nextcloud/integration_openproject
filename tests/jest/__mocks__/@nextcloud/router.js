/* jshint esversion: 8 */
/**
 * SPDX-FileCopyrightText: 2021-2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const router = jest.createMockFromModule('@nextcloud/router')

router.generateUrl = (path) => `http://localhost${path}`
router.generateOcsUrl = (path) => `http://localhost${path}`
router.imagePath = (path) => `http://localhost${path}`

module.exports = router
