const {config} = require("../config");

class NextcloudLoginPage {
	constructor() {
		this.usernameSelector = '#user'
		this.passwordSelector = '#password'
		this.submitButtonSelector = '//button[@type="submit"]'
		this.hintSkipButtonSelector = '//div[contains(@class,"enjoyhint_btn-transparent") and (text() = "Skip")]'
	}

	async userLogsInNextcloud(username, password) {
		await pageNC.goto(config.baseUrlNC)
		await pageNC.fill(this.usernameSelector, username)
		await pageNC.fill(this.passwordSelector,password)
		await pageNC.click(this.submitButtonSelector)
	}
}
module.exports = { NextcloudLoginPage };
