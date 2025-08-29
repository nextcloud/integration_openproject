/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translate as t } from '@nextcloud/l10n'
import APP_ID from './appID.js'

export const messages = {
	installLatestVersionNow: t(APP_ID, 'Install latest version now'),
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
	opRequiredVersionAndPlanHint: t(APP_ID, 'Requires OpenProject version 16.0 (or higher) and an active Corporate plan.'),
}

export const messagesFmt = {
	appNotEnabledOrUnsupported: (app, version) => t(APP_ID, 'This feature requires version {version} (or higher) of "{app}" app. Please install or update the app.', { app, version }),
	configureOIDCProviders: (settingsLink) => t(APP_ID, 'You can configure OIDC providers in the {settingsLink}', { settingsLink }, null, { escape: false, sanitize: false }),
}
