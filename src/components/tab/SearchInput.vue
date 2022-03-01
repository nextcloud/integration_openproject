<template>
	<div id="searchBar">
		<Multiselect
			class="searchInput"
			:placeholder="placeholder"
			:options="searchResults"
			:user-select="true"
			label="displayName"
			track-by="multiselectKey"
			:internal-search="false"
			open-direction="below"
			:loading="isStateLoading"
			:preselect-first="true"
			:preserve-search="true"
			@search-change="makeSearchRequest">
			<template #option="{option}">
				<div class="searchList">
					<div class="searchList__filterWorkPackage">
						<div class="filterProjectTypeStatus">
							<div class="filterProjectTypeStatus__project">
								{{ option.project }}
							</div>
							<div class="filterProjectTypeStatus__type" :style="{'color':option.typeCol}">
								{{ option.typeTitle }}
							</div>
							<div class="filterProjectTypeStatus__status"
								:style="{'background-color':option.statusCol}">
								<div class="filterProjectTypeStatus__status__title">
									{{ option.statusTitle }}
								</div>
							</div>
						</div>
						<div v-if="option.assignee" class="filterAssignee">
							<div class="filterAssignee__avatar">
								<Avatar
									class="item-avatar"
									:size="25"
									:url="option.picture"
									:user="option.assignee"
									:display-name="option.assignee" />
							</div>
							<div class="filterAssignee__assignee">
								{{ option.assignee }}
							</div>
						</div>
					</div>
					<div class="filterWorkpackageSubject">
						<div class="filterWorkpackageSubject__subject">
							{{ option.subject }}
						</div>
					</div>
				</div>
			</template>
			<template #noOptions>
				{{ translate('Start typing to search') }}
			</template>
		</Multiselect>
		<div v-if="state !== 'ok'"
			class="stateMsg text-center">
			{{ stateMessages }}
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

const STATE_OK = 'ok'
const STATE_ERROR = 'error'
const STATE_NO_TOKEN = 'no-token'
const STATE_LOADING = 'loading'
const SEARCH_CHAR_LIMIT = 3

export default {
	name: 'SearchInput',
	components: {
		Avatar,
		Multiselect,
	},
	data: () => ({
		state: STATE_OK,
		searchResults: [],
	}),
	computed: {
		isStateOk() {
			return this.state === STATE_OK
		},
		isStateLoading() {
			return this.state === STATE_LOADING
		},
		placeholder() {
			return this.translate('Search for a work package to create a relation')
		},
		stateMessages() {
			if (this.state === STATE_NO_TOKEN) {
				return this.translate('No OpenProject account connected')
			} else if (this.state === STATE_ERROR) {
				return this.translate('Error connecting to OpenProject')
			}
			return ''
		},
	},
	methods: {
		resetState() {
			this.searchResults = []
			this.state = STATE_OK
		},
		translate(key) {
			return t('integration_openproject', key)
		},
		checkForErrorCode(statusCode) {
			if (statusCode === 200) return
			if (statusCode === 401) {
				this.state = STATE_NO_TOKEN
			} else {
				this.state = STATE_ERROR
			}
		},
		replaceHrefToGetId(href) {
			// this is a helper method replaces the string like this "/api/v3/types/3" to get id
			return href
				? href.replace(/.*\//, '')
				: null
		},
		async makeSearchRequest(search) {
			if (search.length <= SEARCH_CHAR_LIMIT) {
				this.resetState()
				return
			}
			this.state = STATE_LOADING
			const url = generateUrl('/apps/integration_openproject/work-packages')
			const req = {}
			req.params = {
				searchQuery: search,
			}
			let response
			try {
				response = await axios.get(url, req)
			} catch (e) {
				response = e.response
			}
			this.checkForErrorCode(response.status)
			if (this.isStateOk) await this.processWorkPackages(response.data)
			if (this.isStateLoading) this.state = STATE_OK
		},
		async processWorkPackages(workPackages) {
			for (const workPackage of workPackages) {
				const statusId = this.replaceHrefToGetId(workPackage._links.status.href)
				const typeId = this.replaceHrefToGetId(workPackage._links.type.href)
				const userId = this.replaceHrefToGetId(workPackage._links.assignee.href)
				const userName = workPackage._links.assignee.title
				const avatarUrl = generateUrl('/apps/integration_openproject/avatar?')
					+ encodeURIComponent('userId')
					+ '=' + userId
					+ '&' + encodeURIComponent('userName')
					+ '=' + userName
				const statusColor = await this.getWorkPackageColorAttributes('/apps/integration_openproject/statuses/', statusId)
				const typeColor = await this.getWorkPackageColorAttributes('/apps/integration_openproject/types/', typeId)
				this.searchResults.push({
					id: workPackage.id,
					subject: workPackage.subject,
					project: workPackage._links.project.title,
					statusTitle: workPackage._links.status.title,
					typeTitle: workPackage._links.type.title,
					assignee: userName,
					statusCol: statusColor,
					typeCol: typeColor,
					picture: avatarUrl,
				})
			}
		},
		async getWorkPackageColorAttributes(path, id) {
			const url = generateUrl(path + id)
			let response
			try {
				response = await axios.get(url)
			} catch (e) {
				response = e.response
			}
			this.checkForErrorCode(response.status)
			return (response.status === 200 && response.data?.color)
				? response.data.color
				: ''
		},
	},
}
</script>
<style scoped lang="scss">
.searchInput {
	width: 100%;
}

.stateMsg {
	padding: 30px;
	text-align: center;
	color: #6d6d6d;
}

.searchList {
	display: flex;
	flex-direction: column;
	justify-content: space-between;

	&__filterWorkPackage {
		margin-top: 8px;
		display: flex;
		flex-direction: row;
		justify-content: space-between;
		align-items: center;

		.filterProjectTypeStatus {
			display: flex;
			justify-content: space-between;

			&__project {
				padding: 6px 6px 6px 12px;
				color: #6d6d6d;
				font-size: 0.87rem;
			}

			&__type {
				padding: 6px;
				text-transform: uppercase;
				font-size: 0.87rem;
			}

			&__status {
				margin: 6px;
				width: 90px;
				height: 25px;
				text-align: center;
				font-size: 0.75rem;
				border-radius: 3px;

				&__title {
					mix-blend-mode: multiply;
				}
			}
		}

		.filterAssignee {
			display: flex;
			flex-direction: row;
			justify-content: space-between;
			flex-wrap: wrap;
			position: absolute;
			right: 0;

			&__avatar {
				padding: 6px;
			}

			&__assignee {
				padding: 6px 12px 6px 6px;
				font-size: 0.81rem;
				color: #0096FF;
				text-align: center;
			}
		}
	}

	.filterWorkpackageSubject {
		margin: 12px;
		text-align: justify;

		&__subject {
			font-weight: bold;
			font-size: 14px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}
}
</style>
