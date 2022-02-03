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
			<div v-for="wp in searchResults" :key="wp" class="workPackage-item">
				<div class="wp-info">
					<div class="wp-info__filter-wp">
						<div class="filter-project-type-status">
							<div class="filter-project-type-status__project">
								{{ wp.project }}
							</div>
							<div class="filter-project-type-status__type" :style="{'color':wp.typeCol}">
								{{ wp.typeTitle }}
							</div>
							<div class="filter-project-type-status__status" :style="{'background-color':wp.statusCol}">
								{{ wp.statusTitle }}
							</div>
						</div>
						<div class="filter-assignee">
							<div class="filter-assignee__avatar">
								<img class="userImage" :src="wp.picture" alt="Avatar">
							</div>
							<div class="filter-assignee__assignee">
								{{ wp.assignee }}
							</div>
						</div>
					</div>
					<div class="filter-wp-subject">
						<div class="filter-wp-subject__subject">
							{{ wp.subject }}
						</div>
					</div>
				</div>
			</div>
			<div class="create-new-wp">
				{{ "+ New work package in OpenProject" }}
			</div>
		</div>
		<div v-if="state === 'error'" class="title text-center">
			{{ t('integration_openproject', "Error connecting to OpenProject") }}
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'

export default {
	name: 'SearchInput',
	data: () => ({
		search: null,
		state: 'loading',
		searchResults: [],
	}),
	computed: {
		placeholder() {
			return t('integration_openproject', 'Search for a work package to create a relation')
		},
	},
	watch: {
		search(value, oldValue) {
			if (value.length < 3) {
				this.searchResults.length = 0
			}
		},
	},
	methods: {
		async makeSearchRequest(e) {
			const url = generateUrl('/apps/integration_openproject/work_packages')

			if (this.search.length >= 3) {
				const req = {}
				req.params = {
					searchQuery: this.search,
				}
				const response = await axios.get(url, req)
				if (Array.isArray(response.data)) {
					this.processWorkPackages(response.data)
					this.state = 'ok'
				}
				if (response.status === 400) {
					this.state = 'no-token'
				} else if (response.status === 401) {
					showError(t('integration_openproject', 'Failed to get OpenProject work packages'))
					this.state = 'error'
				} else {
					// there was an error in work package processing
					// eslint-disable-next-line
					console.debug('error')
				}

			}
		},
		async processWorkPackages(workPackages) {

			for (let i = 0; i < workPackages.length; i++) {
				const statusId = workPackages[i]._links.status.href
					? workPackages[i]._links.status.href.replace(/.*\//, '')
					: null
				const typeId = workPackages[i]._links.type.href
					? workPackages[i]._links.type.href.replace(/.*\//, '')
					: null
				const userId = workPackages[i]._links?.assignee?.href
					? workPackages[i]._links.assignee.href.replace(/.*\//, '')
					: workPackages[i]._links?.author?.href
						? workPackages[i]._links.author.href.replace(/.*\//, '')
						: null
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
			if (response.status === 400) {
				this.state = 'no-token'
			} else if (response.status === 401) {
				showError(t('integration_openproject', 'Failed to fetch status of work packages'))
				this.state = 'error'
			} else {
				// there was an error in work package processing
				// eslint-disable-next-line
				console.debug('error')
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
			if (response.status === 400) {
				this.state = 'no-token'
			} else if (response.status === 401) {
				showError(t('integration_openproject', 'Failed to fetch type of work packages'))
				this.state = 'error'
			} else {
				// there was an error in work package processing
				// eslint-disable-next-line
				console.debug('error')
			}
			return ''
		},
		async getUserAvatar(userId, userName) {
			const url = generateUrl('/apps/integration_openproject/avatar?') + encodeURIComponent('userId') + '=' + userId + '&' + encodeURIComponent('userName') + '=' + userName
			const req = {}
			const response = await axios.get(url, req)
			if (response.status === 200) {
				this.state = 'ok'
				return response.data
			}
			if (response.status === 400) {
				this.state = 'no-token'
			} else if (response.status === 401) {
				showError(t('integration_openproject', 'Failed to fetch user avatar'))
				this.state = 'error'
			} else {
				// there was an error in work package processing
				// eslint-disable-next-line
				console.debug('error')
			}
			return ''
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

			.userImage {
				padding: 6px;
				border-radius: 50%;
			}

			&__assignee {
				padding: 6px;
				font-size: 14px;
				color: #0096FF;
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
