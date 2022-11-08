const {config} = require("../config")

class OpenprojectLoginPage {
	constructor() {
		this.openProjectTitle = '//a[@title="Sign in"]'
		this.usernameSelector = '#username-pulldown'
		this.passwordSelector = '#password-pulldown'
		this.userSignUP = '#login-pulldown'
	}

	async userLogsInOpenproject(username, password){
		await pageOP.goto(config.baseUrlOP)
		await pageOP.click(this.openProjectTitle)
		await pageOP.fill(this.usernameSelector, username)
		await pageOP.fill(this.passwordSelector, password)
		await pageOP.click(this.userSignUP)
	}
}

module.exports = { OpenprojectLoginPage }
