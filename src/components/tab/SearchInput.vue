<template>
	<div id="search-bar">
		<Multiselect
			v-model="search"
			class="searchInput"
			:placeholder="placeholder"
			:options="searchResults"
			:user-select="true"
			label="displayName"
			track-by="multiselectKey"
			:internal-search="false"
			open-direction="below"
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
				{{ t('integration_openproject', 'Start typing to search') }}
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

export default {
	name: 'SearchInput',
	components: {
		Avatar,
		Multiselect,
	},
	data: () => ({
		search: null,
		state: 'ok',
		searchResults: [],
	}),
	computed: {
		placeholder() {
			return t('integration_openproject', 'Search for a work package to create a relation')
		},
		stateMessages() {
			if (this.state === 'no-token') {
				return t('integration_openproject', 'No OpenProject account connected')
			} else if (this.state === 'error') {
				return t('integration_openproject', 'Error connecting to OpenProject')
			} else if (this.state === 'loading') {
				return t('integration_openproject', 'Wait while we fetch work packages')
			}
			return ''
		},
	},
	watch: {
		search(value, oldValue) {
			// if the value in the search input field changes we need to reset the searchResults
			if (oldValue !== null) {
				if (value.length < oldValue.length && value.length <= 3) {
					this.searchResults = []
					this.state = 'ok'
				}
			}
		},
	},
	methods: {
		async makeSearchRequest(search) {
			this.search = search
			if (search <= 3) {
				this.searchResults = []
				return
			}
			const url = generateUrl('/apps/integration_openproject/work-packages')
			if (this.search.length > 3) {
				const req = {}
				req.params = {
					searchQuery: this.search,
				}
				this.state = 'loading'
				const response = await axios.get(url, req)
				if (response.status === 200) {
					this.state = 'ok'
					if (response.data.length === 0) {
						this.searchResults = []

					} else {
						this.processWorkPackages(response.data)
					}
				} else {
					this.checkStatusCode(response.status)
				}
			} else {
				this.state = 'ok'
			}
		},
		checkStatusCode(statusCode) {
			if (statusCode === 200) {
				this.state = 'ok'
			} else if (statusCode === 401) {
				this.state = 'no-token'
			} else {
				this.state = 'error'
			}
		},
		replaceHrefToGetId(href) {
			// this is a helper method replaces the string like this "/api/v3/types/3" to get id
			return href
				? href.replace(/.*\//, '')
				: null
		},
		async processWorkPackages(workPackages) {
			for (let i = 0; i < workPackages.length; i++) {
				const statusId = this.replaceHrefToGetId(workPackages[i]._links.status.href)
				const typeId = this.replaceHrefToGetId(workPackages[i]._links.type.href)
				const userId = this.replaceHrefToGetId(workPackages[i]._links.assignee.href)
				const userName = workPackages[i]._links.assignee.title
				const avatar = await this.getUserAvatar(userId, userName)
				const statusColor = await this.getWorkPackageColorAttributes('/apps/integration_openproject/statuses/', statusId)
				const typeColor = await this.getWorkPackageColorAttributes('/apps/integration_openproject/types/', typeId)
				const found = this.searchResults.some(el => el.id === workPackages[i].id)
				if (!found) {
					this.searchResults.push({
						id: workPackages[i].id,
						subject: workPackages[i].subject,
						project: workPackages[i]._links.project.title,
						statusTitle: workPackages[i]._links.status.title,
						typeTitle: workPackages[i]._links.type.title,
						assignee: userName,
						statusCol: statusColor,
						typeCol: typeColor,
						picture: avatar,
					})
				}
			}
		},
		async getWorkPackageColorAttributes(path, id) {
			const url = generateUrl(path + id)
			const req = {}
			this.state = 'loading'
			const response = await axios.get(url, req)
			if (response.status === 200) {
				this.state = 'ok'
				return response.data.color
			}
			this.checkStatusCode(response.status)
			return ''
		},
		async getUserAvatar(userId, userName) {
			const url = generateUrl('/apps/integration_openproject/avatar?')
				+ encodeURIComponent('userId') + '=' + userId + '&' + encodeURIComponent('userName') + '=' + userName
			return url
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
			right: 0px;

			&__avatar {
				padding: 6px;
			}

			&__assignee {
				padding: 6px;
				padding-right: 12px;
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
