const { expect } = require("@playwright/test")

class NextcloudAdminPage {

	constructor() {
		this.settingsMenuSelector = '//div[@id="settings"]/div[@id="expand"]'
		this.adminSettingSelector = '[data-id="admin_settings"]'
		this.openProjectTabSelector = '//li//a//span[text()="OpenProject"]'
		this.openProjectOauthInstanceInputFieldSelector = '//div[@id="openproject-oauth-instance"]//div[@class="text-input-input-wrapper"]//input'
		this.saveOauthInstanceButtonSelector = '[data-test-id="submit-server-host-form-btn"]'
		this.openProjectOauthClientIdSelector = '//div[@id="openproject-oauth-client-id"]//div[@class="text-input-input-wrapper"]//input'
		this.openProjectOauthSecretSelector = '//div[@id="openproject-oauth-client-secret"]//div[@class="text-input-input-wrapper"]//input'
		this.submitOPOauthButtonSelector = '[data-test-id="submit-op-oauth-btn"]'
		this.copyNCOauthClientIdButtonSelector = '//div[@id="nextcloud-oauth-client-id"]//button'
		this.copyNCOauthSecretdButtonSelector = '//div[@id="nextcloud-oauth-client-secret"]//button'
		this.submitNCOauthButtonSelector = '[data-test-id="submit-nc-oauth-values-form-btn"]'
		this.resetAllAppSettingsSelector = '#reset-all-app-settings-btn'
		this.resetConfirmSelector = '//div[@class="oc-dialog"]//div[contains(@class,"oc-dialog-buttonrow")]//button[text() = "Yes, reset"]'
		this.defaultPreferenceSelector = '//div[@class="default-prefs"]'
	}

	async adminNavigatesToAdminOPTab() {
		await pageNC.click(this.settingsMenuSelector)
		await pageNC.click(this.adminSettingSelector)
		await pageNC.locator(this.openProjectTabSelector).last().click()
	}

	async adminAddsOpenProjectHost(host) {
		await pageNC.click(this.openProjectOauthInstanceInputFieldSelector)
		await pageNC.fill(this.openProjectOauthInstanceInputFieldSelector, host)
		await pageNC.click(this.saveOauthInstanceButtonSelector)
	}

	async adminSetsTheOpOauthCreds(opClientId, opClientSecret) {
		await pageNC.click(this.openProjectOauthClientIdSelector)
		await pageNC.fill(this.openProjectOauthClientIdSelector, opClientId)
		await pageNC.click(this.openProjectOauthSecretSelector)
		await pageNC.fill(this.openProjectOauthSecretSelector, opClientSecret)
		await pageNC.click(this.submitOPOauthButtonSelector)
	}

	async adminCopiesTheNcOauthCreds() {
		await pageNC.click(this.copyNCOauthClientIdButtonSelector)
		const nextcloudClientId = await pageNC.evaluate(() => navigator.clipboard.readText())
		await pageNC.click(this.copyNCOauthSecretdButtonSelector)
		const nextcloudClientSecret = await pageNC.evaluate(() => navigator.clipboard.readText())
		await pageNC.click(this.submitNCOauthButtonSelector)
		return { client_id:nextcloudClientId, client_secret:nextcloudClientSecret }
	}

    async isDefaultPrefsVisible() {
		await expect(pageNC.locator(this.defaultPreferenceSelector)).toBeVisible()
	}

	async resetNCOauthSetUP() {
		await pageNC.click(this.resetAllAppSettingsSelector)
		await pageNC.click(this.resetConfirmSelector)
	}

}

module.exports = { NextcloudAdminPage };
