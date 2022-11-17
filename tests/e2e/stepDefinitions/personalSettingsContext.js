const {Given, When, Then} = require('@cucumber/cucumber')
const { expect } = require("@playwright/test")

const { NextcloudPersonalSettingPage } = require("../pageObjects/NextcloudPersonalSettingPage")

const ncPersonalSettingsPage = new NextcloudPersonalSettingPage()

When('administator connects to the openproject through the personal settings', async function () {
	await ncPersonalSettingsPage.connectToOpenProjectParsonalSettings()
})

Then('the button with {string} text should be displayed in the webUI', async function (expectedMessage) {
	const actualMessage = await ncPersonalSettingsPage.isConnectedToOpenProject()
	await expect(expectedMessage).toBe(actualMessage)
})
