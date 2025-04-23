/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import endpoints from './endpoints.js'

export function validateOPInstance(url) {
	return axios.post(endpoints.validateOPInstance, { url })
}

export function saveAdminConfig(configs) {
	return axios.put(endpoints.adminConfig, { values: configs })
}
