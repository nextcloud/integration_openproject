const { Given, When, Then } = require('@cucumber/cucumber')

const { NextcloudAdminPage } = require('../pageObjects/NextcloudAdminPage')
const { OpenprojectAdminPage } = require('../pageObjects/OpenprojectAdminPage')

const ncAdminPageObject = new NextcloudAdminPage()
const opAdminPageObject = new OpenprojectAdminPage()

let opClientId = ''
let opClientSecret = ''
let ncClientId = ''
let ncClientSecret = ''

Given('the administrator has navigated to the openproject tab in administrator settings', async function() {
	await ncAdminPageObject.adminNavigatesToAdminOPTab()
})

When('openproject administrator adds the nextcloud host with name {string} in file storage', async function(name) {
	await opAdminPageObject.adminAddsFileStorageHost(name)
})

When('nextcloud administrator adds the openproject host', async function() {
	await ncAdminPageObject.adminAddsOpenProjectHost()
})

When('openproject administrator copies the openproject oauth credentials', async function() {
	const values = await opAdminPageObject.copyOpenProjectOauthCreds()
	opClientId = values.client_id
	opClientSecret = values.client_secret
})

When('nextcloud administrator pastes the openproject oauth credentials', async function() {
	await ncAdminPageObject.adminSetsTheOpOauthCreds(opClientId, opClientSecret)
	opClientId = ''
	opClientSecret = ''
})

When('nextcloud administrator copies the nextcloud oauth credentials', async function() {
	const values = await ncAdminPageObject.adminCopiesTheNcOauthCreds()
	ncClientId = values.client_id
	ncClientSecret = values.client_secret
})

When('openproject administrator pastes the nextcloud oauth credentials', async function() {
	await opAdminPageObject.pasteNCOauthCreds(ncClientId, ncClientSecret)
	ncClientSecret = ''
	ncClientId = ''
})

Then('file storage {string} should be listed on the webUI of openproject', async function(name) {
	await opAdminPageObject.fileStorageShouldBeVisible(name)
})

Then('the oauth setting from should be completed on the webUI of nextcloud', async function() {
	await ncAdminPageObject.isDefaultPrefsVisible()
})
