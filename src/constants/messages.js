/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translate as t } from '@nextcloud/l10n'
import APP_ID from './appID.js'

export const messages = {
	appRequiredForOIDCMethod: t(APP_ID, 'This app is required to use the OIDC authentication method'),
	downloadAndEnableApp: t(APP_ID, 'Download and enable it'),
	featureNotAvailable: t(APP_ID, 'This feature is not available for this user account'),
	opConnectionUnauthorized: t(APP_ID, 'Unauthorized to connect to OpenProject'),
	opClientId: t(APP_ID, 'OpenProject client ID'),
	opClientIdHintText: t(APP_ID, 'You can get this value from your identity provider when you configure the client'),
	nextcloudHubProvider: t(APP_ID, 'Nextcloud Hub'),
	externalOIDCProvider: t(APP_ID, 'External Provider'),
}

export const messagesFmt = {
	appNotInstalled: (app) => t(APP_ID, 'The "{app}" app is not installed', { app }),
	appNotSupported: (app) => t(APP_ID, 'The "{app}" app is not supported', { app }),
	appNotEnabledOrSupported: (app) => t(APP_ID, 'The "{app}" app is not enabled or supported', { app }),
	minimumVersionRequired: (minimumAppVersion) => t(APP_ID, 'Requires app version "{minimumAppVersion}" or later', { minimumAppVersion }),
	configureOIDCProviders: (settingsLink) => t(APP_ID, 'You can configure OIDC providers in the {settingsLink}', { settingsLink }, null, { escape: false, sanitize: false }),
}
