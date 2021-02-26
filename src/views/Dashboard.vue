<template>
	<DashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="state === 'loading'">
		<template #empty-content>
			<EmptyContent
				v-if="emptyContentMessage"
				:icon="emptyContentIcon">
				<template #desc>
					{{ emptyContentMessage }}
					<div v-if="state === 'no-token' || state === 'error'" class="connect-button">
						<a class="button" :href="settingsUrl">
							{{ t('integration_openproject', 'Connect to OpenProject') }}
						</a>
					</div>
				</template>
			</EmptyContent>
		</template>
	</DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'
import moment from '@nextcloud/moment'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget, EmptyContent,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			openprojectUrl: null,
			notifications: [],
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/connected-accounts'),
			themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
		}
	},

	computed: {
		showMoreUrl() {
			return this.openprojectUrl + '/projects'
		},
		items() {
			return this.notifications.map((n) => {
				return {
					id: this.getUniqueKey(n),
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getAuthorAvatarUrl(n),
					avatarUsername: this.getAuthorShortName(n) + 'z',
					overlayIconUrl: this.getNotificationTypeImage(n),
					mainText: this.getTargetTitle(n),
					subText: this.getSubline(n),
				}
			})
		},
		lastDate() {
			const nbNotif = this.notifications.length
			return (nbNotif > 0) ? this.notifications[0].updatedAt : null
		},
		lastMoment() {
			return moment(this.lastDate)
		},
		emptyContentMessage() {
			if (this.state === 'no-token') {
				return t('integration_openproject', 'No OpenProject account connected')
			} else if (this.state === 'error') {
				return t('integration_openproject', 'Error connecting to OpenProject')
			} else if (this.state === 'ok') {
				return t('integration_openproject', 'No OpenProject notifications!')
			}
			return ''
		},
		emptyContentIcon() {
			if (this.state === 'no-token') {
				return 'icon-openproject'
			} else if (this.state === 'error') {
				return 'icon-close'
			} else if (this.state === 'ok') {
				return 'icon-checkmark'
			}
			return 'icon-checkmark'
		},
	},

	beforeMount() {
		this.launchLoop()
	},

	mounted() {
	},

	methods: {
		async launchLoop() {
			// get openproject URL first
			try {
				const response = await axios.get(generateUrl('/apps/integration_openproject/url'))
				this.openprojectUrl = response.data.replace(/\/+$/, '')
			} catch (error) {
				console.debug(error)
			}
			// then launch the loop
			this.fetchNotifications()
			this.loop = setInterval(() => this.fetchNotifications(), 60000)
		},
		fetchNotifications() {
			const req = {}
			if (this.lastDate) {
				req.params = {
					since: this.lastDate,
				}
			}
			axios.get(generateUrl('/apps/integration_openproject/notifications'), req).then((response) => {
				if (Array.isArray(response.data)) {
					this.processNotifications(response.data)
				}
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_openproject', 'Failed to get OpenProject notifications'))
					this.state = 'error'
				} else {
					// there was an error in notif processing
					console.debug(error)
				}
			})
		},
		processNotifications(newNotifications) {
			if (this.lastDate) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newNotifications.length && this.lastMoment.isBefore(newNotifications[i].updatedAt)) {
					i++
				}
				if (i > 0) {
					const toAdd = this.filter(newNotifications.slice(0, i))
					this.notifications = toAdd.concat(this.notifications)
				}
			} else {
				// first time we don't check the date
				this.notifications = this.filter(newNotifications)
			}
		},
		filter(notifications) {
			return notifications
		},
		getNotificationTarget(n) {
			const projectId = n._links?.project?.href
				? n._links.project.href.replace(/.*\//, '')
				: null
			return projectId
				? this.openprojectUrl + '/projects/' + projectId + '/work_packages/' + n.id
				: ''
		},
		getUniqueKey(n) {
			return n.id + ':' + n.updatedAt
		},
		getAuthorShortName(n) {
			return n._links?.assignee?.title
				? n._links.assignee.title
				: n._links?.author?.title
					? n._links.author.title
					: undefined
		},
		getAuthorFullName(n) {
			return n.firstname + ' ' + n.lastname
		},
		getAuthorAvatarUrl(n) {
			const userId = n._links?.assignee?.href
				? n._links.assignee.href.replace(/.*\//, '')
				: n._links?.author?.href
					? n._links.author.href.replace(/.*\//, '')
					: null
			const userName = n._links?.assignee?.title
				? n._links.assignee.title
				: n._links?.author?.title
					? n._links.author.title
					: null
			return userId
				? generateUrl('/apps/integration_openproject/avatar?') + encodeURIComponent('userId') + '=' + userId + '&' + encodeURIComponent('userName') + '=' + userName
				: ''
		},
		getNotificationProjectName(n) {
			return ''
		},
		getNotificationContent(n) {
			return ''
		},
		getNotificationTypeImage(n) {
			return generateUrl('/svg/core/actions/sound?color=' + this.themingColor)
		},
		getSubline(n) {
			const description = n.description?.raw
				? n.description.raw
				: ''
			const status = n._links?.status?.title
				? '[' + n._links.status.title + '] '
				: ''
			return status + description
		},
		getTargetTitle(n) {
			return n.subject
		},
		getFormattedDate(n) {
			return moment(n.updated_at).format('LLL')
		},
	},
}
</script>

<style scoped lang="scss">
::v-deep .connect-button {
	margin-top: 10px;
}
</style>
