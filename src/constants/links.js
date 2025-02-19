/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl } from '@nextcloud/router'

export const appLinks = {
	user_oidc: { installLink: generateUrl('/settings/apps/files/user_oidc') },
}
