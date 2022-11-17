
exports.config = {
	baseUrlNC: process.env.NEXTCLOUD_HOST || 'http://localhost:8080',
	baseUrlOP: process.env.OPENPROJECT_HOST || 'http://localhost:8081',
	openprojectBasicAuthUser: process.env.OPENPROJECT_AUTHENTICATION_GLOBAL__BASIC__AUTH_USER || 'apiadmin',
	openprojectBasicAuthPass: process.env.OPENPROJECT_AUTHENTICATION_GLOBAL__BASIC__AUTH_PASSWORD || 'apiadmin',
	headless: process.env.HEADLESS === 'true',

}
