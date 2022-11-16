const {Given, When, Then} = require('@cucumber/cucumber')
const { expect } = require("@playwright/test")

const { NextcloudPersonalSettingPage } = require("../pageObjects/NextcloudPersonalSettingPage")

const ncPersonalSettingsPage = new NextcloudPersonalSettingPage()

When('administator connects to the openproject through the personal settings', async function () {
	await ncPersonalSettingsPage.connectToOpenProjectParsonalSettings()
})

Then('the user should be connected to the openproject', async function () {
	await ncPersonalSettingsPage.isConnectedToOpenProject()
})
