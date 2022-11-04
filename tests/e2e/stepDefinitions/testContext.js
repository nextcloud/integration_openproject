const {Given, When, Then} = require('@cucumber/cucumber')
const { expect } = require("@playwright/test");
import OpenProjectLoginPage from "../pageObjects/OpenprojectLoginPage"
import NextcloudLoginPage from "../pageObjects/NextcloudLoginPage"
import NextcloudAdminPage from  "../pageObjects/NextcloudAdminPage"
import OpenprojectAdminPage from "../pageObjects/OpenprojectAdminPage"
// const { OpenProjectLoginPage } = require()
// const { NextcloudLoginPage } = require("")
// const { NextcloudAdminPage } = require();
// const { OpenprojectAdminPage } = require()

const ncLoginPageObject = new NextcloudLoginPage()
const ncAdminPageObject = new NextcloudAdminPage()
const opAdminPageObject = new OpenprojectAdminPage()
const opLoginPageObject = new OpenProjectLoginPage()

Given('nextcloud administrator has logged in using the webUI', async function () {
	await ncLoginPageObject.userLogsInNextcloud('admin','admin')
});

Given('openproject administrator has logged in openproject using the webUI', async function () {
	await opLoginPageObject.userLogsInOpenproject('admin','admin')
});


Given('the administrator has navigated to the openproject tab in administrator settings', async function () {
	await ncAdminPageObject.adminNavigatesToAdminOPTab()
});

When('openproject administrator adds file storage with following settings', async function (dataTable) {
	for (const info of dataTable.hashes()) {
		await opAdminPageObject.adminAddsFileStorageHost(info.name, info.host)
	}
})


When('nextcloud administrator adds following openproject host', async function (dataTable){
	for (const info of dataTable.hashes()) {
		await ncAdminPageObject.adminAddsOpenProjectHost(info.host)
	}
})

When('openproject administrator copies the openproject oauth credintials', async function() {
	await opAdminPageObject.copyOpenProjectOauthCreds()
})

When('nextcloud administrator pastes the openproject oauth credintials', async function(){
  await ncAdminPageObject.adminSetsTheOpOauthCreds()
})

When('nextcloud administrator copies the nextcloud oauth credintials',async function(){
	await ncAdminPageObject.adminCopiesTheNcOauthCreds()
})

When('openproject administrator pastes the nextcloud oauth credintials', async function(){
	await opAdminPageObject.pasteNCOauthCreds()
})
