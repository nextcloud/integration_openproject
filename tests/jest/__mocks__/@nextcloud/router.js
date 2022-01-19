/* jshint esversion: 8 */
const router = jest.createMockFromModule('@nextcloud/router')

router.generateUrl = jest.fn(function(url) {
	return 'http://localhost' + url
})

module.exports = router
