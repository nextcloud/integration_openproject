const { Before, BeforeAll, AfterAll, After, setDefaultTimeout } = require('@cucumber/cucumber')
const { chromium } = require('playwright')
const { config } = require('./tests/e2e/config')
const {apiHelper} = require('./tests/e2e/helpers/apiHelper')
const { OpenprojectAdminPage } = require('./tests/e2e/pageObjects/OpenprojectAdminPage')

setDefaultTimeout(120000)

BeforeAll(async function() {
	// await apiHelper.createAdmin()
	global.browserNC = await chromium.launch({
		headless: false,
	})
	global.browserOP = await chromium.launch({
		headless: false,
	})
})

AfterAll(async function() {
	await global.browserNC.close()
	await global.browserOP.close()
})

Before(async function() {
	global.contextNC = await global.browserNC.newContext()
	await contextNC.grantPermissions(['clipboard-read', 'clipboard-write'])
	await contextNC.tracing.start({ screenshots: true, snapshots: true })
	global.pageNC = await global.contextNC.newPage()
	global.contextOP = await global.browserOP.newContext()
	await contextOP.grantPermissions(['clipboard-read', 'clipboard-write'])
	await contextOP.tracing.start({ screenshots: true, snapshots: true })
	global.pageOP = await global.contextOP.newPage()
})

After(async function() {
	await apiHelper.resetNextcloudOauthSettings()
	await apiHelper.deleteStorage()
	await global.pageNC.close()
	await contextNC.tracing.stop({ path: 'tests/e2e/report/traceNC.zip' })
	await global.contextNC.close()
	await global.pageOP.close()
	await contextOP.tracing.stop({ path: 'tests/e2e/report/traceOP.zip' })
	await global.contextOP.close()
})
