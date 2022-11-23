const {config} = require("../config");

class NextcloudLoginPage {
	constructor() {
		this.usernameSelector = '#user'
		this.passwordSelector = '#password'
		this.submitButtonSelector = '//button[@type="submit"]'
		this.recommendedAppsSelector = '//div[@class="guest-box"]'
		this.skipReccomendedAppsSelector = '//span[contains(@class,"button-vue__text") and contains(text(),"Skip")]'
		this.backToNextcloudButtonSelector = '//a[contains(text(),"Back to Nextcloud")]'
	}

	async userLogsInNextcloud(username, password) {
		await pageNC.goto(config.baseUrlNC)
		await pageNC.fill(this.usernameSelector, username)
		await pageNC.fill(this.passwordSelector,password)
		await pageNC.click(this.submitButtonSelector)
		// await pageNC.waitForSelector(this.recommendedAppsSelector)
		// await pageNC.click(this.skipReccomendedAppsSelector)
		// await pageNC.click(this.backToNextcloudButtonSelector)
	}
}
module.exports = { NextcloudLoginPage };
