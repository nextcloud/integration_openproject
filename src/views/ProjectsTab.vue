<!--
  - @copyright Copyright (c) 2022 Kiran Parajuli <kiran@jankaritech.com>
  -
  - @author Kiran Parajuli <kiran@jankaritech.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="projects">
		<SearchInput v-if="!!requestUrl"
			:file-info="fileInfo"
			:linked-work-packages="workpackages"
			@saved="onSaved" />
		<div v-if="isLoading" class="icon-loading" />
		<div v-else-if="workpackages.length > 0" id="openproject-linked-workpackages">
			<div class="existing-relations">
				{{ t('integration_openproject', 'Existing relations:') }}
			</div>
			<div v-for="(workpackage, index) in workpackages"
				:key="workpackage.id"
				class="linked-workpackages">
				<div class="linked-workpackages--workpackage">
					<WorkPackage :workpackage="workpackage" />
					<div class="linked-workpackages--workpackage--unlink icon-noConnection" />
				</div>
				<div :class="{ 'workpackage-seperator': index !== workpackages.length-1 }" />
			</div>
		</div>
		<EmptyContent v-else
			id="openproject-empty-content"
			:state="state"
			:request-url="requestUrl" />
	</div>
</template>

<script>
import EmptyContent from '../components/tab/EmptyContent'
import WorkPackage from '../components/tab/WorkPackage'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import SearchInput from '../components/tab/SearchInput'
import { loadState } from '@nextcloud/initial-state'
import { workpackageHelper } from '../utils/workpackageHelper'

const STATE_ERROR = 'error'
const STATE_NO_TOKEN = 'no-token'

export default {
	name: 'ProjectsTab',
	components: {
		EmptyContent,
		SearchInput,
		WorkPackage,
	},
	data: () => ({
		error: '',
		fileInfo: {},
		state: 'loading',
		workpackages: [],
		requestUrl: loadState('integration_openproject', 'request-url'),
	}),
	computed: {
		isLoading() {
			return this.state === 'loading'
		},
		unlinkSvg() {
			return require('../../img/noConnection.svg')
		},
	},
	methods: {
		/**
		 * updates current resource
		 *
		 * @param {object} fileInfo file information
		 */
		async update(fileInfo) {
			this.fileInfo = fileInfo
			this.workpackages = []
			this.state = 'loading'
			await this.fetchWorkpackages(this.fileInfo.id)
		},
		checkForErrorCode(statusCode) {
			if (statusCode === 200 || statusCode === 204) return
			if (statusCode === 401) {
				this.state = STATE_NO_TOKEN
			} else {
				this.state = STATE_ERROR
			}
		},
		/**
		 * Reset the current view to its default state
		 */
		resetState() {
			this.error = ''
			this.state = 'loading'
		},
		onSaved(data) {
			this.workpackages.push(data)
		},
		async getWorkPackageUrl(workpackageId, projectId) {
			let response
			let openprojectUrl
			try {
				response = await axios.get(generateUrl('/apps/integration_openproject/url'))
			} catch (e) {
				response = e.response
			}
			this.checkForErrorCode(response.status)
			if (response.status === 200) { openprojectUrl = response.data.replace(/\/+$/, '') }
			const workpackageUrl = openprojectUrl + '/projects/' + projectId + '/work_packages/' + workpackageId
			window.open(workpackageUrl)
		},
		async deleteWorkPackageLink(workpackageId, fileId) {
			let response = await axios.get(generateUrl('/apps/integration_openproject/work_packages?workpackageId=' + workpackageId))
			this.checkForErrorCode(response.status)
			let id
			if (response.status === 200) {
				id = this.processLink(response.data, fileId)
			}
			const url = generateUrl('/apps/integration_openproject/file_links/' + id)
			response = await axios.delete(url)
			this.checkForErrorCode(response.status)
			if (response.status === 200) {
				this.workpackages = this.workpackages.filter(workpackage => workpackage.id !== workpackageId)
			}
		},
		processLink(data, fileId) {
			let linkId
			for (const element of data) {
				if (parseInt(element.originData.id) === fileId) {
					linkId = element.id
				}
			}
			return linkId
		},
		async fetchWorkpackages(fileId) {
			const req = {}
			const url = generateUrl('/apps/integration_openproject/work-packages?fileId=' + fileId)
			try {
				const response = await axios.get(url, req)
				if (!Array.isArray(response.data)) {
					this.state = 'failed-fetching-workpackages'
				} else {
					// empty data means there are no workpackages linked
					if (response.data.length > 0) {
						for (let workPackage of response.data) {
							workPackage = await workpackageHelper.getAdditionalMetaData(workPackage)
							this.workpackages.push(workPackage)
						}
					}
					this.state = 'ok'
				}
			} catch (error) {
				if (error.response && error.response.status === 401) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 404) {
					this.state = 'connection-error'
				} else if (error.response && error.response.status === 500) {
					this.state = 'error'
				} else {
					this.state = 'failed-fetching-workpackages'
				}
			}
		},
	},
}
</script>

<style scoped lang="scss">
.projects {
	height: 100% !important;
	width: 100%;

	.existing-relations {
		text-align: left;
		font-weight: bold;
		font-size: 0.8rem;
		padding: 12px;
	}

	.center-content {
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.title {
		font-size: 2rem;
		font-weight: 600;
		padding-bottom: 0;
	}

	.subtitle {
		padding-top: 0;
		font-size: 1.2rem;
	}

	.icon-loading:after {
		top: 140%;
	}

	.linked-workpackages--workpackage{
		display: flex;
		position: relative;
		width: 100%;
		&--unlink{
			position: absolute;
			top: 12px;
			right: 14px;
			height: 15px;
			width: 18px;
			align-items: center;
			filter: contrast(0) brightness(0);
			visibility: hidden;
		}
	}

	.linked-workpackages:hover {
		background-color: var(--color-background-dark);
	}

	.linked-workpackages:hover .linked-workpackages--workpackage--unlink {
		visibility: visible;
		cursor: pointer;
	}

	.workpackage-seperator {
		height: 0;
		margin: 0px 10px;
		border-bottom: 1px solid rgb(237 237 237);
	}
}

body.theme--dark .linked-workpackages--workpackage--unlink{
	filter: invert(100%);
}
</style>
