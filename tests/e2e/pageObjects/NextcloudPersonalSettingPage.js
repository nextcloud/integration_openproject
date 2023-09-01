/* global pageNC */

class NextcloudPersonalSettingPage {

	constructor() {
		this.openProjectTabSelector = '//li//a//span[text()="OpenProject"]'
		this.oauthConnectButtonSelector = '//button[contains(@class,"oauth-connect--button")]'
		this.opAuthorizeButtonSelector = '//input[@value="Authorize"]'
		this.openProjectDisconnectButtonSelector = '//button[contains(@class,"openproject-prefs--disconnect")]//span//span[@class="button-vue__text"]'
	}

	async connectToOpenProjectParsonalSettings() {
		await pageNC.locator(this.openProjectTabSelector).first().click()
		await pageNC.click(this.oauthConnectButtonSelector)

	}

	async authorizeApiOP() {
		await pageNC.click(this.opAuthorizeButtonSelector)
	}

	async isConnectedToOpenProject() {
		const text = await pageNC.locator(this.openProjectDisconnectButtonSelector).textContent()
		return text.trim()
	}

}

module.exports = { NextcloudPersonalSettingPage }
