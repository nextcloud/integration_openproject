const fetch = require('node-fetch')
const {config} = require("../config")

const createAdmin = function () {
	const url = config.baseUrlOP + '/api/v3/users'
	console.log(url)
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
		console.log(response.data)
	}).catch(function(error) {
		console.log(error);
	}).then(function (response){
			console.log(response)
	}).catch(function(error) {
		console.log(error);
	});
}

module.exports = { createAdmin }
