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
	tokenExchangeFormLabel: t(APP_ID, 'Token Exchange'),
	enableTokenExchange: t(APP_ID, 'Enable token exchange'),
	opClientIdHintText: t(APP_ID, 'You can get this value from your identity provider when you configure the client'),
	nextcloudHubProvider: t(APP_ID, 'Nextcloud Hub'),
	externalOIDCProvider: t(APP_ID, 'External Provider'),
	tokenExchangeHintText: t(APP_ID, 'When enabled, the app will try to obtain a token for the given audience from the identity provider. If disabled, it will use the access token obtained during the login process.'),
	opRequiredVersionAndPlanHint: t(APP_ID, 'Requires OpenProject version 15.5 (or higher) and an active Corporate plan.'),
}

export const messagesFmt = {
	appNotInstalled: (app) => t(APP_ID, 'The "{app}" app is not installed', { app }),
	appNotSupported: (app) => t(APP_ID, 'The "{app}" app is not supported', { app }),
	appNotEnabledOrSupported: (app) => t(APP_ID, 'The "{app}" app is not enabled or supported', { app }),
	minimumVersionRequired: (minimumAppVersion) => t(APP_ID, 'Requires app version "{minimumAppVersion}" or later', { minimumAppVersion }),
	configureOIDCProviders: (settingsLink) => t(APP_ID, 'You can configure OIDC providers in the {settingsLink}', { settingsLink }, null, { escape: false, sanitize: false }),
}
