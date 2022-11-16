const fetch = require('node-fetch')
const {config} = require("../config")
const {throwError} = require("@vue/vue2-jest/lib/utils");

const createAdmin = function () {
	const url = config.baseUrlOP + '/api/v3/users'
	const data = {
		"login": "admin2",
		"password": "admin2",
		"firstName": "Second",
		"lastName": "Admin",
		"email": "admin@mail.com",
		"admin": true,
		"status": "active",
		"language": "en"
	}
	fetch(url, {
		method: 'POST',
		body: JSON.stringify(data),
		headers:{
			"Authorization": "Basic " + Buffer.from(config.openprojectBasicAuthUser + ":" + config.openprojectBasicAuthPass).toString('base64'),
			"Content-Type": "application/json"
		}}).then(function (response){
		console.log(response.status)
	}).catch(function(error) {
		new throwError("Cannot create the admin user" + error.msg)
	})
}

const resetNextcloudOauthSettings = function () {
	const url = config.baseUrlNC + '/apps/integration_openproject/admin-config'
	const data = {
		"values":
			{
				"client_id":null,
				"client_secret":null,
				"oauth_instance_url":null,
				"default_enable_navigation":false,
				"default_enable_notifications":false,
				"default_enable_unified_search":false
			}
	}
	fetch(url, {
		method: 'PUT',
		body: JSON.stringify(data),
		headers:{
			"Authorization": "Basic " + Buffer.from('admin' + ":" + 'admin').toString('base64'),
			"Content-Type": "application/json"
		}}).then(function (response){
	}).catch(function(error) {
		new throwError("Cannot reset the nextcloud settings" + error)
	})
}
module.exports = { createAdmin, resetNextcloudOauthSettings }
