import { generateUrl } from '@nextcloud/router'

export default {
	validateOPInstance: generateUrl('/apps/integration_openproject/is-valid-op-instance'),
	adminConfig: generateUrl('/apps/integration_openproject/admin-config'),
}
