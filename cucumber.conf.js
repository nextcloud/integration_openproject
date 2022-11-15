const { Before, BeforeAll, AfterAll, After, setDefaultTimeout } = require("@cucumber/cucumber");
const { chromium } = require("playwright");
const { NextcloudAdminPage } = require("./tests/e2e/pageObjects/NextcloudAdminPage")
const ncAdminPageObject = new NextcloudAdminPage()
const { OpenprojectAdminPage } = require("./tests/e2e/pageObjects/OpenprojectAdminPage")
const opAdminPageObject = new OpenprojectAdminPage()
const apiHelper = require('./tests/e2e/helpers/apiHelper')

setDefaultTimeout(60000)

BeforeAll(async function () {
	// await apiHelper.createAdmin()
	global.browserNC = await chromium.launch({
		headless: false,
		slowMo: 1000,
	});
	global.browserOP = await chromium.launch({
		headless: false,
		slowMo: 1000,
	});
});

AfterAll(async function () {
    await global.browserNC.close()
	await global.browserOP.close()
});

Before(async function () {
	global.contextNC = await global.browserNC.newContext()
	await contextNC.grantPermissions(['clipboard-read','clipboard-write']);
	global.pageNC = await global.contextNC.newPage()
	global.contextOP = await global.browserOP.newContext()
	await contextOP.grantPermissions(['clipboard-read','clipboard-write']);
	global.pageOP = await global.contextOP.newPage()
});

After(async function () {
	await ncAdminPageObject.resetNCOauthSetUP()
	await opAdminPageObject.deleteFileStorage()
	await global.pageNC.close();
	await global.contextNC.close();
	await global.pageOP.close();
	await global.contextOP.close();
});
