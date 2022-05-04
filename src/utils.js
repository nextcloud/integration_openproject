let mytimer = 0
export function delay(callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(mytimer)
		mytimer = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

export const STATE = {
	OK: 'ok',
	ERROR: 'error',
	LOADING: 'loading',
	NO_TOKEN: 'no-token',
	CONNECTION_ERROR: 'connection-error',
	FAILED_FETCHING_WORKPACKAGES: 'failed-fetching-workpackages',
}
