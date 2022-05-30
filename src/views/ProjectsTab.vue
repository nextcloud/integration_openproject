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
		<SearchInput v-if="!!requestUrl && !isLoading"
			:file-info="fileInfo"
			:linked-work-packages="filterWorkpackagesByFileId"
			@saved="onSaved" />
		<div v-if="isLoading" class="icon-loading" />
		<div v-else-if="filterWorkpackagesByFileId.length > 0" id="openproject-linked-workpackages">
			<div class="existing-relations">
				{{ t('integration_openproject', 'Existing relations:') }}
			</div>
			<div v-for="(workpackage, index) in filterWorkpackagesByFileId"
				:key="workpackage.id"
				class="linked-workpackages">
				<div class="linked-workpackages--workpackage">
					<WorkPackage :id="'workpackage-'+ workpackage.id"
						:workpackage="workpackage"
						class="linked-workpackages--workpackage--item"
						@click.native="routeToTheWorkPackage(workpackage.id, workpackage.projectId)" />
					<div class="linked-workpackages--workpackage--unlink icon-noConnection"
						@click="unlink(workpackage.id, fileInfo.id)" />
				</div>
				<div :class="{ 'workpackage-seperator': index !== filterWorkpackagesByFileId.length-1 }" />
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
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import SearchInput from '../components/tab/SearchInput'
import { loadState } from '@nextcloud/initial-state'
import { workpackageHelper } from '../utils/workpackageHelper'
import { STATE } from '../utils'

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
		state: STATE.LOADING,
		workpackages: [],
		requestUrl: loadState('integration_openproject', 'request-url'),
		color: null,
	}),
	computed: {
		isLoading() {
			return this.state === STATE.LOADING
		},
		filterWorkpackagesByFileId() {
			return this.workpackages.filter(wp => {
				if (wp.fileId === undefined || wp.fileId === '') {
					console.error('work-package data does not contain a fileId')
					return false
				}
				return wp.fileId === this.fileInfo.id
			})
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
			this.state = STATE.LOADING
			if (this.requestUrl) {
				// only fetch if we have a request url
				await this.fetchWorkpackages(this.fileInfo.id)
			} else {
				this.state = STATE.CONNECTION_ERROR
			}
		},
		checkForErrorCode(statusCode) {
			if (statusCode === 200 || statusCode === 204) return
			if (statusCode === 401) {
				this.state = STATE.NO_TOKEN
			} else {
				this.state = STATE.ERROR
			}
		},
		/**
		 * Reset the current view to its default state
		 */
		resetState() {
			this.error = ''
			this.state = STATE.LOADING
		},
		onSaved(data) {
			this.workpackages.unshift(data)
			this.$nextTick(() => {
				const workpackage = document.getElementById('workpackage-' + data.id)
				workpackage.classList.add('workpackage-transition')
				setTimeout(() => {
					workpackage.classList.remove('workpackage-transition')
				}, 3000)
			})
		},
		async routeToTheWorkPackage(workpackageId, projectId) {
			let response
			let openprojectUrl
			try {
				response = await axios.get(generateUrl('/apps/integration_openproject/url'))
			} catch (e) {
				response = e.response
			}
			this.checkForErrorCode(response.status)
			if (response.status === 200) {
				openprojectUrl = response.data.replace(/\/+$/, '')
				const workpackageUrl = openprojectUrl + '/projects/' + projectId + '/work_packages/' + workpackageId
				window.open(workpackageUrl)
			}
		},
		unlink(workpackageId, fileId) {
			OC.dialogs.confirmDestructive(
				t('integration_openproject',
					'Are you sure you want to unlink the work package?'
				),
				t('integration_openproject', 'Confirm unlink'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('integration_openproject', 'Unlink'),
					confirmClasses: 'error',
					cancel: t('integration_openproject', 'Cancel'),
				},
				(result) => {
					if (result) {
						this.unlinkWorkPackage(workpackageId, fileId).then((response) => {
							this.workpackages = this.workpackages.filter(workpackage => workpackage.id !== workpackageId)
							showSuccess(t('integration_openproject', 'Work package unlinked'))
						}).catch((error) => {
							showError(
								t('integration_openproject', 'Failed to unlink work package')
							)
							this.checkForErrorCode(error.response.status)
						})
					}
				},
				true
			)
		},
		async unlinkWorkPackage(workpackageId, fileId) {
			let response
			try {
				response = await axios.get(generateUrl(`/apps/integration_openproject/work-packages/${workpackageId}/file-links`))
			} catch (e) {
				response = e.response
			}
			if (response.status === 200) {
				const id = this.processLink(response.data, fileId)
				const url = generateUrl('/apps/integration_openproject/file-links/' + id)
				response = await axios.delete(url)
			} else {
				this.checkForErrorCode(response.status)
				throw new Error('could not fetch the delete link of work-package')
			}
			return response
		},
		processLink(data, fileId) {
			let linkId
			for (const element of data) {
				if (parseInt(element.originData.id) === fileId) {
					linkId = element.id
					break
				}
			}
			return linkId
		},
		workpackageAlreadyInList(workPackage) {
			return this.workpackages.some(
				elem => elem.id === workPackage.id && elem.fileId === workPackage.fileId,
			)
		},
		async fetchWorkpackages(fileId) {
			const req = {}
			const url = generateUrl('/apps/integration_openproject/work-packages?fileId=' + fileId)
			try {
				const response = await axios.get(url, req)
				if (!Array.isArray(response.data)) {
					this.state = STATE.FAILED_FETCHING_WORKPACKAGES
				} else {
					// empty data means there are no workpackages linked
					if (response.data.length > 0) {
						for (let workPackage of response.data) {
							workPackage.fileId = fileId
							// if the WP is already in the list, because the user switched quickly between files
							// don't even try to fetch all the additional meta data
							if (!this.workpackageAlreadyInList(workPackage)) {
								workPackage = await workpackageHelper.getAdditionalMetaData(workPackage)
								// check again, the WP might have been added by an outstanding request
								// from another file
								if (!this.workpackageAlreadyInList(workPackage)) {
									this.workpackages.push(workPackage)
								}
							}
						}
					}
					this.state = STATE.OK
				}
			} catch (error) {
				if (error.response && error.response.status === 401) {
					this.state = STATE.NO_TOKEN
				} else if (error.response && error.response.status === 404) {
					this.state = STATE.CONNECTION_ERROR
				} else if (error.response && error.response.status === 500) {
					this.state = STATE.ERROR
				} else {
					this.state = STATE.FAILED_FETCHING_WORKPACKAGES
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

	.linked-workpackages--workpackage {
		display: flex;
		position: relative;
		width: 100%;
		&--item {
			border: none;
		}
		&--unlink {
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
		margin: 0 10px;
		border-bottom: 1px solid rgb(237 237 237);
	}
}

@keyframes fade-in {
	0% {
		opacity: 0;
		background-color: var(--color-background-dark);
	}

	100% {
		opacity: 1;
	}
}

.workpackage-transition {
	animation: fade-in 3s 1;
}

body.theme--dark, body[data-theme-dark], body[data-theme-dark-highcontrast] {
	.linked-workpackages--workpackage--unlink {
		filter: invert(100%);
	}
}
</style>
