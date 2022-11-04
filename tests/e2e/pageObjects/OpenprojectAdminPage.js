//import {NextcloudAdminPage} from "./NextcloudAdminPage";

class OpenprojectAdminPage {
	constructor() {
		this.openProjectAvatarSelector = '//div[@title="OpenProject Admin"]'
		this.administratorSettingMenuItemSelector = '//a[contains(@class,"administration-menu-item ")]'
		this.fileStoragesSelector = '//a[@title="File storages"]'
		this.addNewStoragesSelector = '//a[@class="wp-inline-create--add-link"]'
		this.storageNameInputFieldSelector = '#storages_storage_name'
		this.hostUrlInputFieldSelector = '#storages_storage_host'
		this.continueSetupButtonSelector = '//button[text() = "Save and continue setup"]'
		this.copyClientIdButtonSelector = '//button[contains(@class,"client-id-copy-button")]'
		this.copyClientSecretButtonSelector = '//button[contains(@class,"secret-copy-button")]'
		this.doneContinueSetupButtonSelector = '//a[text() = "Done. Continue setup"]'
		this.oauthClientIdInputFieldSelectorOP ='#oauth_client_client_id'
		this.oauthClientSecretInputFieldSelectorOP ='#oauth_client_client_secret'
		this.saveAndCompleteSetupButtonSelector = '//button[text() = "Save and complete setup"]'
		this.openProjectClientId = ''
		this.openProjectClientSecret = ''
		//this.ncAdminPage = new NextcloudAdminPage()
	}

	async adminAddsFileStorageHost(name, host){
		await pageOP.click(this.openProjectAvatarSelector)
		await pageOP.click(this.administratorSettingMenuItemSelector)
		await pageOP.click(this.fileStoragesSelector)
		await pageOP.click(this.addNewStoragesSelector)
		await pageOP.fill(this.storageNameInputFieldSelector, name)
		await pageOP.fill(this.hostUrlInputFieldSelector, host)
		await pageOP.click(this.continueSetupButtonSelector)
	}
	 async copyOpenProjectOauthCreds(){
		 await pageOP.click(this.copyClientIdButtonSelector)
		 this.openProjectClientId = await pageOP.evaluate(() => navigator.clipboard.readText())
		 await pageOP.click(this.copyClientSecretButtonSelector)
		 this.openProjectClientSecret = await pageOP.evaluate(() => navigator.clipboard.readText())
		 await pageOP.click(this.doneContinueSetupButtonSelector)
	 }

	 async pasteNCOauthCreds(){
		 // await pageOP.fill(this.oauthClientIdInputFieldSelectorOP, this.ncAdminPage.nextcloudClientId)
		 // await pageOP.fill(this.oauthClientSecretInputFieldSelectorOP, this.ncAdminPage.nextcloudClientSecret)
		 await pageOP.click(this.saveAndCompleteSetupButtonSelector)
	 }
}

module.exports = { OpenprojectAdminPage };
