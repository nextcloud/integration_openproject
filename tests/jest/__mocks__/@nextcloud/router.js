/* jshint esversion: 8 */
const router = jest.createMockFromModule('@nextcloud/router')

router.generateUrl = jest.fn(function() {
	return '/'
})

module.exports = router
