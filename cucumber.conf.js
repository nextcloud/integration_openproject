const { Before, BeforeAll, AfterAll, After, setDefaultTimeout } = require("@cucumber/cucumber");
const { chromium } = require("playwright");

setDefaultTimeout(60000)

BeforeAll(async function () {
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
	global.pageNC = await global.contextNC.newPage()
	global.contextOP = await global.browserOP.newContext()
	global.pageOP = await global.contextOP.newPage()
});

After(async function () {
	await global.pageNC.close();
	await global.contextNC.close();
	await global.pageOP.close();
	await global.contextOP.close();
});
