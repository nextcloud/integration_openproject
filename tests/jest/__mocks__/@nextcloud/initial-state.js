/* jshint esversion: 8 */
const initialState = jest.createMockFromModule('@nextcloud/initial-state')

function loadState() {
	return {
		url: 'https://localhost',
		oauth_instance_url: 'https://localhost',
		client_id: '123',
		client_secret: '123',
	}
}

initialState.loadState = loadState

module.exports = initialState
