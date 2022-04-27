import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export const workpackageHelper = {
	async getColorAttributes(path, id) {
		const url = generateUrl(path + id)
		let response
		try {
			response = await axios.get(url)
		} catch (e) {
			response = e.response
		}
		return (response.status === 200 && response.data?.color)
			? response.data.color
			: ''
	},
	replaceHrefToGetId(href) {
		// this is a helper method replaces the string like this "/api/v3/types/3" to get id
		return href
			? href.replace(/.*\//, '')
			: null
	},
	async getAdditionalMetaData(workPackage) {
		if (typeof workPackage._links.status.href !== 'string'
			|| workPackage._links.status.href === ''
			|| typeof workPackage._links.type.href !== 'string'
			|| workPackage._links.type.href === ''
			|| typeof workPackage.id !== 'number'
			|| typeof workPackage.subject !== 'string'
			|| workPackage.subject === ''
			|| typeof workPackage._links.project.title !== 'string'
			|| workPackage._links.project.title === ''
			|| typeof workPackage._links.status.title !== 'string'
			|| workPackage._links.status.title === ''
			|| typeof workPackage._links.type.title !== 'string'
			|| workPackage._links.type.title === ''
		) {
			throw new Error('missing data in workpackage object')
		}
		const statusId = this.replaceHrefToGetId(workPackage._links.status.href)
		const typeId = this.replaceHrefToGetId(workPackage._links.type.href)
		const userId = this.replaceHrefToGetId(workPackage._links.assignee.href)
		const projectId = this.replaceHrefToGetId(workPackage._links.project.href)
		const userName = workPackage._links.assignee.title
		const avatarUrl = generateUrl('/apps/integration_openproject/avatar?')
			+ encodeURIComponent('userId')
			+ '=' + userId
			+ '&' + encodeURIComponent('userName')
			+ '=' + userName
		const statusColor = await this.getColorAttributes('/apps/integration_openproject/statuses/', statusId)
		const typeColor = await this.getColorAttributes('/apps/integration_openproject/types/', typeId)
		return {
			id: workPackage.id,
			subject: workPackage.subject,
			project: workPackage._links.project.title,
			projectId,
			statusTitle: workPackage._links.status.title,
			typeTitle: workPackage._links.type.title,
			assignee: userName,
			statusCol: statusColor,
			typeCol: typeColor,
			picture: avatarUrl,
		}
	},
}
