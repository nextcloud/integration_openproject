<template>
	<div id="search-bar">
		<div class="input-field">
			<input
				id="workpackages-search"
				v-model="search"
				:placeholder="placeholder"
				type="text"
				@keyup="makeSearchRequest">
		</div>
		<div v-if="searchResults.length>0" class="search-list">
			<div v-for="workPackage in searchResults" :key="workPackage.id" class="workPackage-item">
				<div class="wp-info">
					<div class="wp-info__filter-wp">
						<div class="filter-project-type-status">
							<div class="filter-project-type-status__project">
								{{ workPackage.project }}
							</div>
							<div class="filter-project-type-status__type" :style="{'color':workPackage.typeCol}">
								{{ workPackage.typeTitle }}
							</div>
							<div class="filter-project-type-status__status" :style="{'background-color':workPackage.statusCol}">
								{{ workPackage.statusTitle }}
							</div>
						</div>
						<div class="filter-assignee">
							<div class="filter-assignee__avatar">
								<Avatar
									class="item-avatar"
									:size="25"
									:url="workPackage.picture"
									:user="workPackage.assignee"
									:display-name="workPackage.assignee" />
							</div>
							<div class="filter-assignee__assignee">
								{{ workPackage.assignee }}
							</div>
						</div>
					</div>
					<div class="filter-wp-subject">
						<div class="filter-wp-subject__subject">
							{{ workPackage.subject }}
						</div>
					</div>
				</div>
			</div>
			<div class="create-new-wp">
				{{ t('integration_openproject', '+ New work package in OpenProject') }}
			</div>
		</div>
		<div v-if="state === 'loading'" class="icon-loading" />
		<div v-if="state === 'error'" class="error text-center">
			{{ t('integration_openproject', "Error connecting to OpenProject") }}
		</div>
		<div v-if="state === 'no-token'" class="noToken text-center">
			{{ t('integration_openproject', "No OpenProject account connected") }}
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'

export default {
	name: 'SearchInput',
	components: {
		Avatar,
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
	},
	watch: {
		search(value, oldValue) {
			if (oldValue !== null) {
				if (value.length < oldValue.length && value.length <= 3) {
					this.searchResults = []
				}
			}
		},
	},
	methods: {
		async makeSearchRequest(e) {
			const url = generateUrl('/apps/integration_openproject/work_packages')

			if (this.search.length > 3) {
				const req = {}
				req.params = {
					searchQuery: this.search,
				}
				const response = await axios.get(url, req)
				if (Array.isArray(response.data)) {
					this.processWorkPackages(response.data)
					this.state = 'ok'
				}
				if (response.status === 401) {
					this.state = 'no-token'
				} else if (response.status === 400) {
					showError(t('integration_openproject', 'Failed to get OpenProject work packages'))
					this.state = 'error'
				} else {
					this.state = 'error'
				}

			}
		},
		replaceHrefToGetId(href, href2 = null) {
			if (href2 !== null) {
				return href
					? href.replace(/.*\//, '')
					: href2
						? href2.replace(/.*\//, '')
						: null
			}
			return href
				? href.replace(/.*\//, '')
				: null
		},
		async processWorkPackages(workPackages) {
			this.state = 'loading'
			for (let i = 0; i < workPackages.length; i++) {
				const statusId = this.replaceHrefToGetId(workPackages[i]._links.status.href)
				const typeId = this.replaceHrefToGetId(workPackages[i]._links.type.href)
				const userId = this.replaceHrefToGetId(workPackages[i]._links?.assignee?.href, workPackages[i]._links?.author?.href)
				const userName = workPackages[i]._links?.assignee?.title
					? workPackages[i]._links.assignee.title
					: workPackages[i]._links?.author?.title
						? workPackages[i]._links.author.title
						: null
				const avatar = await this.getUserAvatar(userId, userName)
				const statusColor = await this.processWPStatus(statusId)
				const typeColor = await this.processWPType(typeId)
				const found = this.searchResults.some(el => el.id === workPackages[i].id)
				if (!found) {
					this.searchResults.push({
						id: workPackages[i].id,
						subject: workPackages[i].subject,
						project: workPackages[i]._links.project.title,
						statusTitle: workPackages[i]._links.status.title,
						typeTitle: workPackages[i]._links.type.title,
						assignee: workPackages[i]._links.assignee.title,
						statusCol: statusColor,
						typeCol: typeColor,
						picture: avatar,
					})
				}
			}
		},
		async processWPStatus(id) {
			const url = generateUrl('/apps/integration_openproject/statuses/' + id)
			const req = {}
			const response = await axios.get(url, req)
			if (response.status === 200) {
				this.state = 'ok'
				return response.data.color
			}
			if (response.status === 401) {
				this.state = 'no-token'
			} else if (response.status === 400) {
				showError(t('integration_openproject', 'Failed to fetch status of work packages'))
				this.state = 'error'
			} else {
				this.state = 'error'
			}
			return ''
		},
		async processWPType(id) {
			const url = generateUrl('/apps/integration_openproject/types/' + id)
			const req = {}
			const response = await axios.get(url, req)
			if (response.status === 200) {
				this.state = 'ok'
				return response.data.color
			}
			if (response.status === 401) {
				this.state = 'no-token'
			} else if (response.status === 400) {
				showError(t('integration_openproject', 'Failed to fetch type of work packages'))
				this.state = 'error'
			} else {
				this.state = 'error'
			}
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
#workpackages-search {
	font-size: 14px;
	width: 100%;
	border-radius: 3px 3px 0px 0px;
	margin: 0;
}

.workPackage-item {
	border: 1px solid var(--color-border-dark);
	border-top-style: none;
	width: 100%;
}

.create-new-wp {
	border: 1px solid var(--color-border-dark);
	border-top-style: none;
	width: 100%;
	height: 50px;
	color: #6d6d6d;
	line-height: 50px;
	text-align: center;
}

.wp-info {
	display: flex;
	flex-direction: column;
	justify-content: space-between;

	&__filter-wp {
		margin-top: 8px;
		display: flex;
		flex-direction: row;
		justify-content: space-between;
		align-items: center;

		.filter-project-type-status {
			display: flex;
			justify-content: space-between;

			&__project {
				padding: 6px 6px 6px 12px;
				color: #6d6d6d;
				font-size: 14px;
			}

			&__type {
				padding: 6px;
				text-transform: uppercase;
				font-size: 14px;
			}

			&__status {
				margin: 6px;
				width: 90px;
				height: 25px;
				text-align: center;
				font-size: 12px;
				border-radius: 3px;
			}
		}

		.filter-assignee {
			display: flex;
			flex-direction: row;
			justify-content: space-between;
			flex-wrap: wrap;

			&__avatar {
				padding: 6px;
			}

			&__assignee {
				padding: 6px;
				font-size: 13px;
				color: #0096FF;
				text-align: center;
			}
		}
	}

	.filter-wp-subject {
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
