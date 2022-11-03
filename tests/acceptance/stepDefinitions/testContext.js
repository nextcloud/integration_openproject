const {Given, When, Then} = require('@cucumber/cucumber')
// import expect for assertion
const { expect } = require("@playwright/test");
const { chromium } = require("playwright");

const url = 'http://localhost/nextcloud'
const opUrl = 'http://localhost:3000'
const username = '#user'
const password = '#password'
const submitButton = '//button[@type="submit"]'
const settingsMenu = '//div[@id="settings"]/div[@id="expand"]'
const adminSetting = '[data-id="admin_settings"]'
const openProjectTab = '//li//a//span[text()="OpenProject"]'
const title = '.settings-title'
const openProjectTitle = '//a[@title="Sign in"]'
const usernameSelector = '#username-pulldown'
const passwordSelector = '#password-pulldown'
const userSignUP = '#login-pulldown'
const openProjectAvatarSelector = '//div[@title="OpenProject Admin"]'
const administratorSettingMenuItemSelector = '//a[contains(@class,"administration-menu-item ")]'
const fileStoragesSelector = '//a[@title="File storages"]'
const addNewStoragesSelector = '//a[@class="wp-inline-create--add-link"]'
const storageNameInputFieldSelector = '#storages_storage_name'
const hostUrlInputFieldSelector = '#storages_storage_host'
const continueSetupButtonSelector = '//button[text() = "Save and continue setup"]'
const doneContinueSetupButtonSelector = '//a[text() = "Done. Continue setup"]'
const saveAndCompleteSetupButtonSelector = '//button[text() = "Save and complete setup"]'
const copyClientIdButtonSelector = '//button[contains(@class,"client-id-copy-button")]'
const copyClientSecretButtonSelector = '//button[contains(@class,"secret-copy-button")]'
const oauthClientIdInputFieldSelectorOP ='#oauth_client_client_id'
const oauthClientSecretInputFieldSelectorOP ='#oauth_client_client_secret'
const openProjectOauthInstanceInputFieldSelector = '#openproject-oauth-instance'
const saveOauthInstanceButtonSelector = '[data-test-id="submit-server-host-form-btn"]'
let openProjectClientId = ''
let openProjectClientSecret = ''

Given('administrator has logged in using the webUI', async function () {
	await page.goto(url);
	await page.fill(username, 'admin')
	await page.fill(password,'admin')
	await page.click(submitButton)
});


Given('the user has navigated to the openproject administrator settings', async function () {
	await page.click(settingsMenu)
	await page.click(adminSetting)
    await page.locator(openProjectTab).last().click()
});

// Given('openproject administrator has logged in to openproject using the webUI', async function () {
// 	const browser = await chromium.launch({
// 		headless: false,
// 		slowMo: 1000,
// 	});
// 	const contextOp = await browser.newContext()
// 	const pageOP = await contextOp.newPage()
// 	await pageOP.goto(opUrl)
// 	await pageOP.click(openProjectTitle)
//     await pageOP.fill(usernameSelector,'admin')
// 	await pageOP.fill(passwordSelector,'admin')
// 	await pageOP.click(userSignUP)
// 	await pageOP.close()
// 	await contextOp.close()
// 	await browser.close()
// });


Given('openproject administrator created file storage with following settings', async function (dataTable) {
	const browser = await chromium.launch({
		headless: false,
		slowMo: 1000,
	});
	const contextOp = await browser.newContext()
	await contextOp.grantPermissions(["clipboard-read"])
	const pageOP = await contextOp.newPage()
	await pageOP.goto(opUrl)
	await pageOP.click(openProjectTitle)
	await pageOP.fill(usernameSelector,'admin')
	await pageOP.fill(passwordSelector,'admin')
	await pageOP.click(userSignUP)
	await pageOP.click(openProjectAvatarSelector)
    await pageOP.click(administratorSettingMenuItemSelector)
	await pageOP.click(fileStoragesSelector)
	await pageOP.click(addNewStoragesSelector)
	for (const info of dataTable.hashes()) {
		await pageOP.fill(storageNameInputFieldSelector, info.name)
		await pageOP.fill(hostUrlInputFieldSelector, info.host)
	}
	await pageOP.click(continueSetupButtonSelector)
	await pageOP.click(copyClientIdButtonSelector)
	openProjectClientId = await pageOP.evaluate(() => navigator.clipboard.readText())
	await pageOP.click(copyClientSecretButtonSelector)
	openProjectClientSecret = await pageOP.evaluate(() => navigator.clipboard.readText())
	await pageOP.click(doneContinueSetupButtonSelector)
	await pageOP.fill(oauthClientIdInputFieldSelectorOP, "khfuiguiaghjighauigha")
	await pageOP.fill(oauthClientSecretInputFieldSelectorOP, "ahfshhanajghuhgjbvag")
	await pageOP.click(saveAndCompleteSetupButtonSelector)
	await pageOP.close()
	await contextOp.close()
	await browser.close()
});

