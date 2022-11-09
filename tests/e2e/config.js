
exports.config = {
	baseUrlNC: process.env.NEXTCLOUD_HOST,
	baseUrlOP: process.env.OPENPROJECT_HOST,
	openprojectBasicAuthUser: process.env.OPENPROJECT_AUTHENTICATION_GLOBAL__BASIC__AUTH_USER || 'apiadmin',
	openprojectBasicAuthPass: process.env.OPENPROJECT_AUTHENTICATION_GLOBAL__BASIC__AUTH_PASSWORD || 'apiadmin'

}
