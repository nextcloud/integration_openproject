/* global pageOP , pageNC */
const { config } = require('../config')

class OpenprojectLoginPage {

	constructor() {
		this.openProjectTitle = '//a[@title="Sign in"]'
		this.usernameSelector = '#username-pulldown'
		this.passwordSelector = '#password-pulldown'
		this.userSignUP = '#login-pulldown'
		this.quickAddMenuSelector = 'op-quick-add-menu--icon'
	}

	async userLogsInOpenproject(username, password) {
		await pageOP.goto(config.baseUrlOP)
		await this.fillUpLoginForm(username, password)
		// await  pageOP.waitForSelector(this.quickAddMenuSelector)
	}

	async fillUpLoginForm(username, password, nextcloud) {
		let page = null
		if (nextcloud) {
			page = pageNC
		} else page = pageOP
		await page.click(this.openProjectTitle)
		await page.fill(this.usernameSelector, username)
		await page.fill(this.passwordSelector, password)
		await page.click(this.userSignUP)
	}

}

module.exports = { OpenprojectLoginPage }
