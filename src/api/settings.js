import axios from '@nextcloud/axios'
import endpoints from './endpoints.js'

export function validateOPInstance(url) {
	return axios.post(endpoints.validateOPInstance, { url })
}

export function saveAdminConfig(configs) {
	return axios.put(endpoints.adminConfig, { values: configs })
}
