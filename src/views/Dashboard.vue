<template>
	<NcDashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="isLoading"
		@markAsRead="onMarkAsRead">
		<template #empty-content>
			<NcEmptyContent v-if="emptyContentMessage"
				:description="!!isAdminConfigOk ? emptyContentMessage : undefined">
				<template #icon>
					<CheckBoldIcon v-if="isStateOk" :size="70" />
					<LinkOffIcon v-else :size="70" />
				</template>
				<template #action>
					<div v-if="showOauthConnect" class="connect-button">
						<OAuthConnectButton :is-admin-config-ok="isAdminConfigOk" />
					</div>
				</template>
			</NcEmptyContent>
		</template>
	</NcDashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import LinkOffIcon from 'vue-material-design-icons/LinkOff.vue'
import { generateUrl } from '@nextcloud/router'
import NcDashboardWidget from '@nextcloud/vue/dist/Components/NcDashboardWidget.js'
import { showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import { loadState } from '@nextcloud/initial-state'
import OAuthConnectButton from '../components/OAuthConnectButton.vue'
import { checkOauthConnectionResult, STATE } from '../utils.js'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'Dashboard',

	components: {
		NcDashboardWidget, NcEmptyContent, OAuthConnectButton, CheckBoldIcon, LinkOffIcon,
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
			notifications: {},
			loop: null,
			state: STATE.LOADING,
			oauthConnectionErrorMessage: loadState('integration_openproject', 'oauth-connection-error-message'),
			oauthConnectionResult: loadState('integration_openproject', 'oauth-connection-result'),
			isAdminConfigOk: loadState('integration_openproject', 'admin-config-status'),
			settingsUrl: generateUrl('/settings/user/openproject'),
			themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
			windowVisibility: true,
			itemMenu: {
				markAsRead: {
					text: t('integration_openproject', 'Mark as read'),
					icon: 'icon-checkmark',
				},
			},
		}
	},
	computed: {
		isStateOk() {
			return this.state === STATE.OK
		},
		isLoading() {
			return this.state === STATE.LOADING
		},
		showMoreUrl() {
			return this.openprojectUrl + '/notifications'
		},
		items() {
			const notifications = []
			for (const key in this.notifications) {
				const n = this.notifications[key]
				notifications.push({
					id: n.wpId,
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getAuthorAvatarUrl(n),
					avatarUsername: this.getAuthorShortName(n) + 'z',
					mainText: this.getTargetTitle(n),
					subText: this.getSubline(n),
					overlayIconUrl: '',
				})
			}
			return notifications
		},
		emptyContentMessage() {
			if (this.state === STATE.NO_TOKEN) {
				return t('integration_openproject', 'No connection with OpenProject')
			} else if (this.state === STATE.CONNECTION_ERROR) {
				return t('integration_openproject', 'Error connecting to OpenProject')
			} else if (this.state === STATE.OK) {
				return t('integration_openproject', 'No OpenProject notifications!')
			}
			return 'Cannot connect to OpenProject'
		},
		showOauthConnect() {
			return [STATE.NO_TOKEN, STATE.ERROR].includes(this.state)
		},
	},
	watch: {
		windowVisibility(newValue) {
			if (newValue) {
				this.launchLoop()
			} else {
				this.stopLoop()
			}
		},
	},
	mounted() {
		checkOauthConnectionResult(this.oauthConnectionResult, this.oauthConnectionErrorMessage)
	},

	beforeDestroy() {
		document.removeEventListener('visibilitychange', this.changeWindowVisibility)
	},

	beforeMount() {
		this.launchLoop()
		document.addEventListener('visibilitychange', this.changeWindowVisibility)
	},

	methods: {
		changeWindowVisibility() {
			this.windowVisibility = !document.hidden
		},
		stopLoop() {
			clearInterval(this.loop)
		},
		async launchLoop() {
			if (!this.isAdminConfigOk) {
				this.state = STATE.ERROR
				return
			}
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
			const notificationsUrl = generateUrl('/apps/integration_openproject/notifications')
			axios.get(notificationsUrl).then((response) => {
				const notifications = {}
				if (Array.isArray(response.data)) {
					for (let i = 0; i < response.data.length; i++) {
						const n = response.data[i]
						const wpId = n._links.resource.href.replace(/.*\//, '')
						if (notifications[wpId] === undefined) {
							notifications[wpId] = {
								wpId,
								resourceTitle: n._links.resource.title,
								projectTitle: n._links.project.title,
								count: 1,
							}
						} else {
							notifications[wpId].count++
						}
						if (!(notifications[wpId].reasons instanceof Set)) {
							notifications[wpId].reasons = new Set()
						}
						notifications[wpId].reasons.add(n.reason)

						const userId = n._links?.actor?.href
							? n._links.actor.href.replace(/.*\//, '')
							: null
						const title = n._links?.actor?.title
							? n._links.actor.title
							: null
						if (notifications[wpId].mostRecentActor === undefined && userId !== null) {
							notifications[wpId].mostRecentActor = {
								title,
								id: userId,
								createdAt: n.createdAt,
							}
						} else if (userId !== null && userId !== notifications[wpId].mostRecentActor.id) {
							if (Date.parse(n.createdAt) > Date.parse(notifications[wpId].mostRecentActor.createdAt)) {
								notifications[wpId].mostRecentActor = {
									title,
									id: userId,
									createdAt: n.createdAt,
								}
							}
						}

					}
					this.state = STATE.OK
					this.notifications = notifications
				} else {
					this.state = STATE.ERROR
					console.debug('notifications API returned invalid data')
				}
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 404) {
					this.state = STATE.CONNECTION_ERROR
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_openproject', 'Failed to get OpenProject notifications'))
					this.state = STATE.NO_TOKEN
				} else {
					// there was an error in notif processing
					this.state = STATE.ERROR
					console.debug(error)
				}
			})
		},
		getNotificationTarget(n) {
			return this.openprojectUrl + '/notifications/details/' + n.wpId + '/activity/'
		},
		getAuthorShortName(n) {
			return n.mostRecentActor?.title
				? n.mostRecentActor.title
				: undefined
		},
		getAuthorAvatarUrl(n) {
			const url = generateUrl('/apps/integration_openproject/avatar?')
			return n.mostRecentActor?.id
				? url + encodeURIComponent('userId') + '=' + n.mostRecentActor.id + '&' + encodeURIComponent('userName') + '=' + n.mostRecentActor.title
				: url + encodeURIComponent('userName') + '='
		},
		getSubline(n) {
			let reasonsString = ''
			n.reasons.forEach((value) => {
				// rewrite the values that come from the API to be displayed
				// the same as they are in OP
				switch (value) {
				case 'dateAlert':
					value = t('integration_openproject', 'Date alert')
					break
				case 'assigned':
					value = t('integration_openproject', 'assignee')
					break
				case 'responsible':
					value = t('integration_openproject', 'accountable')
					break
				case 'watched':
					value = t('integration_openproject', 'watcher')
					break
				case 'commented':
					value = t('integration_openproject', 'commented')
					break
				case 'mentioned':
					value = t('integration_openproject', 'mentioned')
					break
				}
				reasonsString = reasonsString + ', ' + value
			})
			return n.projectTitle + ' - ' + reasonsString.replace(/^, /, '')
		},
		getTargetTitle(n) {
			return '(' + n.count + ') ' + n.resourceTitle
		},
		onMarkAsRead(item) {
			const url = generateUrl(
				'/apps/integration_openproject/work-packages/' + item.id + '/notifications'
			)
			axios.delete(url).then((response) => {
				showSuccess(
					t('integration_openproject', 'Notifications associated with Work package marked as read')
				)
				this.fetchNotifications()
			}).catch((error) => {
				showError(
					t('integration_openproject', 'Failed to mark notifications as read')
				)
				console.debug(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
::v-deep .connect-button {
	margin-top: 10px;
}
</style>
