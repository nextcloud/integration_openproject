const {config} = require("../config");

class NextcloudLoginPage {
	constructor() {
		this.usernameSelector = '#user'
		this.passwordSelector = '#password'
		this.submitButtonSelector = '//button[@type="submit"]'
		this.skipReccomendedAppsSelector = '//span[contains(@class,"button-vue__text") and contains(text(),"Skip")]'
	}

	async userLogsInNextcloud(username, password) {
		await pageNC.goto(config.baseUrlNC)
		await pageNC.fill(this.usernameSelector, username)
		await pageNC.fill(this.passwordSelector,password)
		await pageNC.click(this.submitButtonSelector)
		await pageNC.waitForSelector(this.skipReccomendedAppsSelector)
		await pageNC.click(this.skipReccomendedAppsSelector)
	}
}
module.exports = { NextcloudLoginPage };
