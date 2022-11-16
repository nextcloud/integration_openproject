const {Given, When} = require("@cucumber/cucumber");

const { NextcloudLoginPage } = require("../pageObjects/NextcloudLoginPage")
const { OpenprojectLoginPage } = require("../pageObjects/OpenprojectLoginPage")
const { NextcloudPersonalSettingPage } = require("../pageObjects/NextcloudPersonalSettingPage")

const ncLoginPageObject = new NextcloudLoginPage()
const opLoginPageObject = new OpenprojectLoginPage()
const ncPersonalSettingsPageObject = new NextcloudPersonalSettingPage()

Given('nextcloud administrator has logged in using the webUI', async function () {
	await ncLoginPageObject.userLogsInNextcloud('admin','admin')
})

Given('openproject administrator has logged in openproject using the webUI', async function () {
	await opLoginPageObject.userLogsInOpenproject('admin2','admin2')
})

When('the user authorizes in open project with username {string} and password {string}', async function(username, password){
	await opLoginPageObject.fillUpLoginForm(username,password,true)
	await ncPersonalSettingsPageObject.authorizeApiOP()
})
