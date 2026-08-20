/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl } from '@nextcloud/router'

import APP_ID from '../constants/appID.js'

export default {
	validateOPInstance: generateUrl(`/apps/${APP_ID}/is-valid-op-instance`),
	adminConfig: generateUrl(`/apps/${APP_ID}/admin-config`),
	nextcloudOAuth: generateUrl(`/apps/${APP_ID}/nc-oauth`),
	projectFolderStatus: generateUrl(`/apps/${APP_ID}/project-folder-status`),
}
