import './bootstrap'

OCA.Files.fileActions.registerAction({
	name: 'open-project',
	displayName: t('integration_openproject', 'OpenProject'),
	mime: 'all',
	permissions: OC.PERMISSION_READ,
	iconClass: 'icon-openproject',
	actionHandler: (filename, context) => {
		console.log(filename, context)
	}
})
