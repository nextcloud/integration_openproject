<template>
	<DashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="isLoading">
		<template #empty-content>
			<EmptyContent v-if="emptyContentMessage">
				<template #icon>
					<CheckBoldIcon v-if="isStateOk" :size="70" />
					<LinkOffIcon v-else :size="70" />
				</template>
				<template #desc>
					<div v-if="!!isAdminConfigOk">
						{{ emptyContentMessage }}
					</div>
					<div v-if="showOauthConnect" class="connect-button">
						<OAuthConnectButton :is-admin-config-ok="isAdminConfigOk" />
					</div>
				</template>
			</EmptyContent>
		</template>
	</DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import CheckBoldIcon from 'vue-material-design-icons/CheckBold.vue'
import LinkOffIcon from 'vue-material-design-icons/LinkOff.vue'
import { generateUrl } from '@nextcloud/router'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'
import moment from '@nextcloud/moment'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import { loadState } from '@nextcloud/initial-state'
import OAuthConnectButton from '../components/OAuthConnectButton'
import { checkOauthConnectionResult, STATE } from '../utils'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget, EmptyContent, OAuthConnectButton, CheckBoldIcon, LinkOffIcon,
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
			moreActorsIconUrl: loadState('integration_openproject', 'more-actors-icon-url'),
			settingsUrl: generateUrl('/settings/user/openproject'),
			themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
			windowVisibility: true,
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
					overlayIconUrl: this.getOverlayIconUrl(n),
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
			axios.get(generateUrl('/apps/integration_openproject/notifications')).then((response) => {
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
								moreActors: false,
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
						if (notifications[wpId].mostRecentActor === undefined) {
							notifications[wpId].mostRecentActor = {
								title: n._links.actor.title,
								id: userId,
								createdAt: n.createdAt,
							}
						} else if (userId !== notifications[wpId].mostRecentActor.id) {
							notifications[wpId].moreActors = true
							if (Date.parse(n.createdAt) > Date.parse(notifications[wpId].mostRecentActor.createdAt)) {
								notifications[wpId].mostRecentActor = {
									title: n._links.actor.title,
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
		getUniqueKey(n) {
			return n.id + ':' + n.updatedAt
		},
		getAuthorShortName(n) {
			return n.mostRecentActor.title
				? n.mostRecentActor.title
				: undefined
		},
		getAuthorFullName(n) {
			return n.firstname + ' ' + n.lastname
		},
		getAuthorAvatarUrl(n) {
			return n.mostRecentActor.id
				? generateUrl('/apps/integration_openproject/avatar?') + encodeURIComponent('userId') + '=' + n.mostRecentActor.id + '&' + encodeURIComponent('userName') + '=' + n.mostRecentActor.title
				: ''
		},
		getOverlayIconUrl(n) {
			if (n.moreActors === true) {
				return this.moreActorsIconUrl
			}
			return ''
		},
		getNotificationProjectName(n) {
			return ''
		},
		getNotificationContent(n) {
			return ''
		},
		getSubline(n) {
			let reasonsString = ''
			n.reasons.forEach((value) => {
				reasonsString = reasonsString + ',' + t('integration_openproject', value)
			})
			return n.projectTitle + ' - ' + reasonsString.replace(/^,/, '')
		},
		getTargetTitle(n) {
			return '(' + n.count + ') ' + n.resourceTitle
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
