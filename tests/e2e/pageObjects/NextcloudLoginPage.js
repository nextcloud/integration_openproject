/* global pageNC */
const { config } = require('../config')

class NextcloudLoginPage {

	constructor() {
		this.usernameSelector = '#user'
		this.passwordSelector = '#password'
		this.submitButtonSelector = '//button[@type="submit"]'
	}

	async userLogsInNextcloud(username, password) {
		await pageNC.goto(config.baseUrlNC)
		await pageNC.fill(this.usernameSelector, username)
		await pageNC.fill(this.passwordSelector, password)
		await pageNC.click(this.submitButtonSelector)
	}

}
module.exports = { NextcloudLoginPage }
