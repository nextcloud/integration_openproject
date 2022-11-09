const {Given} = require("@cucumber/cucumber");

const { NextcloudLoginPage } = require("../pageObjects/NextcloudLoginPage")
const { OpenprojectLoginPage } = require("../pageObjects/OpenprojectLoginPage")

const ncLoginPageObject = new NextcloudLoginPage()
const opLoginPageObject = new OpenprojectLoginPage()

Given('nextcloud administrator has logged in using the webUI', async function () {
	await ncLoginPageObject.userLogsInNextcloud('admin','admin')
});

Given('openproject administrator has logged in openproject using the webUI', async function () {
	await opLoginPageObject.userLogsInOpenproject('admin2','admin2')
});
