/* global pageOP */
const { expect } = require('@playwright/test')
const { config } = require('../config')

class OpenprojectAdminPage {

	constructor() {
		this.openProjectAvatarSelector = '//div[@title="Second Admin"]'
		this.administratorSettingMenuItemSelector = '//a[contains(@class,"administration-menu-item ")]'
		this.fileStoragesSelector = '//a[@title="File storages"]'
		this.addNewStoragesSelector = '//a[@class="wp-inline-create--add-link"]'
		this.storageNameInputFieldSelector = '#storages_storage_name'
		this.hostUrlInputFieldSelector = '#storages_storage_host'
		this.continueSetupButtonSelector = '//button[text() = "Save and continue setup"]'
		this.copyClientIdButtonSelector = '//button[contains(@class,"client-id-copy-button")]'
		this.copyClientSecretButtonSelector = '//button[contains(@class,"secret-copy-button")]'
		this.doneContinueSetupButtonSelector = '//a[text() = "Done. Continue setup"]'
		this.oauthClientIdInputFieldSelectorOP = '#oauth_client_client_id'
		this.oauthClientSecretInputFieldSelectorOP = '#oauth_client_client_secret'
		this.saveAndCompleteSetupButtonSelector = '//button[text() = "Save and complete setup"]'
		this.deleteFileStorageSelector = '//li[@class="toolbar-item"]/a//span[text()="Delete"]'
		this.fileStorageBreadcrumbSelector = '//div[@id="breadcrumb"]//li/a[text()="File storages"]'
		this.fileStorageNameSelector = '//td[@class="name"]/a'
		this.skipHintSelector = "//div[contains(@class,'enjoyhint_btn-transparent') and (text() = 'Skip')]"
	}

	async adminAddsFileStorageHost(name) {
		await pageOP.click(this.openProjectAvatarSelector)
		await pageOP.click(this.administratorSettingMenuItemSelector)
		await pageOP.click(this.skipHintSelector)
		await pageOP.click(this.fileStoragesSelector)
		await pageOP.click(this.addNewStoragesSelector)
		await pageOP.fill(this.storageNameInputFieldSelector, name)
		await pageOP.fill(this.hostUrlInputFieldSelector, config.baseUrlNC)
		await pageOP.click(this.continueSetupButtonSelector)
	}

	 async copyOpenProjectOauthCreds() {
		 await pageOP.click(this.copyClientIdButtonSelector)
		 const openProjectClientId = await pageOP.evaluate(() => navigator.clipboard.readText())
		 await pageOP.click(this.copyClientSecretButtonSelector)
		 const openProjectClientSecret = await pageOP.evaluate(() => navigator.clipboard.readText())
		 await pageOP.click(this.doneContinueSetupButtonSelector)
		 return { client_id: openProjectClientId, client_secret: openProjectClientSecret }
	 }

	 async pasteNCOauthCreds(ncClientId, ncClientSecret) {
		 await pageOP.fill(this.oauthClientIdInputFieldSelectorOP, ncClientId)
		 await pageOP.fill(this.oauthClientSecretInputFieldSelectorOP, ncClientSecret)
		 await pageOP.click(this.saveAndCompleteSetupButtonSelector)
	 }

	 async fileStorageShouldBeVisible(name) {
		await pageOP.click(this.fileStorageBreadcrumbSelector)
	    await expect(pageOP.locator(this.fileStorageNameSelector)).toHaveText(name)
	 }

	 async deleteFileStorage() {
		await pageOP.locator(this.fileStorageNameSelector).click()
		await pageOP.locator(this.deleteFileStorageSelector).click()
		 await pageOP.on('dialog', async (dialog) => {
			 dialog.accept()
		 })
	 }

}

module.exports = { OpenprojectAdminPage }
