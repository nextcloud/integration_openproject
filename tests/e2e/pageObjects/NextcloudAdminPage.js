/* global pageNC */
const { expect } = require('@playwright/test')
const { config } = require('../config')

class NextcloudAdminPage {

	constructor() {
		this.settingsMenuSelector = '//div[@id="settings"]/div[@class="menutoggle"]'
		this.settingsMenuOpenSelector = 'div#settings.openedMenu'
		this.adminSettingSelector = '//li[@data-id="admin_settings"]'
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
		this.errorMessage='.text-input-error-message'
	}

	async adminNavigatesToAdminOPTab() {
		await pageNC.goto(config.baseUrlNC + '/index.php/settings/user/security')
		await pageNC.screenshot({ path: 'report/screenshot.png', fullPage: true });
		await pageNC.waitForSelector('#openproject_prefs',10000)
		// await pageNC.waitForTimeout(10000)
		// await pageNC.waitForSelector(this.settingsMenuSelector)
		// await pageNC.locator(this.settingsMenuSelector).click()
		// await pageNC.waitForSelector(this.settingsMenuOpenSelector)
		// await pageNC.locator(this.adminSettingSelector).click()
		// await pageNC.waitForSelector('#security-warning',{state:"visible", timeout:10000})
		// await pageNC.waitForSelector(this.openProjectTabSelector,{state:"visible", timeout:10000})
		// await Promise.all([
		// 	// Waits for the next navigation.
		// 	// It is important to call waitForNavigation before click to set up waiting.
		// 	pageNC.waitForNavigation(),
		// 	// Triggers a navigation after a timeout.
		// 	pageNC.locator(this.openProjectTabSelector).last().click()
		// ]);
	}

	async adminAddsOpenProjectHost() {
		await pageNC.waitForTimeout(20000)
		await pageNC.waitForSelector('#app-content',10000)
		await pageNC.waitForSelector('#openproject_prefs',10000)
		await pageNC.waitForSelector('//h2[@class="settings-title"]//span[text()="OpenProject integration"]',{state:"visible",timeout:50000})
		await pageNC.waitForSelector(this.openProjectOauthInstanceInputFieldSelector,{state:"visible",timeout:60000})
		await pageNC.click(this.openProjectOauthInstanceInputFieldSelector)
		await pageNC.fill(this.openProjectOauthInstanceInputFieldSelector, config.baseUrlOP)
		await pageNC.click(this.saveOauthInstanceButtonSelector)
		await expect(pageNC.locator(this.errorMessage)).not.toBeVisible()
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
		return { client_id: nextcloudClientId, client_secret: nextcloudClientSecret }
	}

	async isDefaultPrefsVisible() {
		await expect(pageNC.locator(this.defaultPreferenceSelector)).toBeVisible()
	}

	async resetNCOauthSetUP() {
		await pageNC.click(this.resetAllAppSettingsSelector)
		await pageNC.click(this.resetConfirmSelector)
	}

}

module.exports = { NextcloudAdminPage }
