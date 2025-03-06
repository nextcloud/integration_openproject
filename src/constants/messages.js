/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translate as t } from '@nextcloud/l10n'
import APP_ID from './appID.js'

export const messages = {
	appRequiredForOIDCMethod: t(APP_ID, 'This app is required to use the OIDC authorization method'),
	downloadAndEnableApp: t(APP_ID, 'Download and enable it'),
	featureNotAvailable: t(APP_ID, 'This feature is not available for this user account'),
	opConnectionUnauthorized: t(APP_ID, 'Unauthorized to connect to OpenProject'),
	opClientId: t(APP_ID, 'OpenProject client ID'),
}

export const messagesFmt = {
	appNotInstalled: (app) => t(APP_ID, 'The "{app}" app is not installed', { app }),
	appNotSupported: (app) => t(APP_ID, 'The "{app}" app is not supported', { app }),
	minimumVersionRequired: (minimumAppVersion) => t(APP_ID, 'Requires app version "{minimumAppVersion}" or later', { minimumAppVersion }),
}
