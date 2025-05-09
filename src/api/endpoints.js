/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl } from '@nextcloud/router'

export default {
	validateOPInstance: generateUrl('/apps/integration_openproject/is-valid-op-instance'),
	adminConfig: generateUrl('/apps/integration_openproject/admin-config'),
}
