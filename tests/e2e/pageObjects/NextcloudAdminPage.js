//import {OpenprojectAdminPage} from "./OpenprojectAdminPage";

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
		this.nextcloudClientId = ''
		this.nextcloudClientSecret = ''
		//this.opAdminPage = new OpenprojectAdminPage()
	}

	async adminNavigatesToAdminOPTab(){
		await pageNC.click(this.settingsMenuSelector)
		await pageNC.click(this.adminSettingSelector)
		await pageNC.locator(this.openProjectTabSelector).last().click()
	}

	async adminAddsOpenProjectHost(host){
		await pageNC.click(this.openProjectOauthInstanceInputFieldSelector)
		await pageNC.fill(this.openProjectOauthInstanceInputFieldSelector, host)
		await pageNC.click(this.saveOauthInstanceButtonSelector)
	}

	async adminSetsTheOpOauthCreds(){
		// await pageNC.click(this.openProjectOauthClientIdSelector)
		// await pageNC.fill(this.openProjectOauthClientIdSelector,this.opAdminPage.openProjectClientId)
		// await pageNC.click(this.openProjectOauthSecretSelector)
		// await pageNC.fill(this.openProjectOauthSecretSelector,this.opAdminPage.openProjectClientSecret)
		await pageNC.click(this.submitOPOauthButtonSelector)
	}

	async adminCopiesTheNcOauthCreds(){
		await pageNC.click(this.copyNCOauthClientIdButtonSelector)
		this.nextcloudClientId = await pageNC.evaluate(() => navigator.clipboard.readText())
		await pageNC.click(this.copyNCOauthSecretdButtonSelector)
		this.nextcloudClientSecret = await pageNC.evaluate(() => navigator.clipboard.readText())
		await pageNC.click(this.submitNCOauthButtonSelector)
	}
}

module.exports = { NextcloudAdminPage };
