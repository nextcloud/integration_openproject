class NextcloudLoginPage {
	constructor() {
		this.ncUrl = 'http://localhost/nextcloud/master'
		this.usernameSelector = '#user'
		this.passwordSelector = '#password'
		this.submitButtonSelector = '//button[@type="submit"]'
	}

	async userLogsInNextcloud(username, password){
		await pageNC.goto(this.ncUrl);
		await pageNC.fill(this.usernameSelector, username)
		await pageNC.fill(this.passwordSelector,password)
		await pageNC.click(this.submitButtonSelector)
	}
}
module.exports = { NextcloudLoginPage };
