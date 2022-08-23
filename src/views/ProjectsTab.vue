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
	<div class="projects"
		:class="{'projects--empty': filterWorkpackagesByFileId.length === 0}">
		<SearchInput v-if="!!isAdminConfigOk && !isLoading"
			:file-info="fileInfo"
			:linked-work-packages="filterWorkpackagesByFileId"
			@saved="onSaved" />
		<LoadingIcon v-if="isLoading" class="loading-spinner" :size="60" />
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
					<Actions>
						<ActionButton class="linked-workpackages--workpackage--unlinkactionbutton"
							@click="unlink(workpackage.id, fileInfo.id)">
							{{ t('integration_openproject', 'Unlink Work Package') }}
							<template #icon>
								<LinkOffIcon :size="20" />
							</template>
						</ActionButton>
					</Actions>
				</div>
				<div :class="{ 'workpackage-seperator': index !== filterWorkpackagesByFileId.length-1 }" />
			</div>
		</div>
		<EmptyContent v-else
			id="openproject-empty-content"
			:state="state"
			:file-info="fileInfo"
			:is-admin-config-ok="isAdminConfigOk" />
	</div>
</template>

<script>
import EmptyContent from '../components/tab/EmptyContent'
import WorkPackage from '../components/tab/WorkPackage'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import SearchInput from '../components/tab/SearchInput'
import LinkOffIcon from 'vue-material-design-icons/LinkOff.vue'
import LoadingIcon from 'vue-material-design-icons/Loading.vue'

import axios from '@nextcloud/axios'

import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'
import { workpackageHelper } from '../utils/workpackageHelper'
import { STATE, checkOauthConnectionResult } from '../utils'

export default {
	name: 'ProjectsTab',
	components: {
		EmptyContent,
		SearchInput,
		WorkPackage,
		Actions,
		ActionButton,
		LoadingIcon,
		LinkOffIcon,
	},
	data: () => ({
		error: '',
		fileInfo: {},
		state: STATE.LOADING,
		workpackages: [],
		oauthConnectionErrorMessage: loadState('integration_openproject', 'oauth-connection-error-message'),
		oauthConnectionResult: loadState('integration_openproject', 'oauth-connection-result'),
		isAdminConfigOk: loadState('integration_openproject', 'admin-config-status'),
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
	mounted() {
		checkOauthConnectionResult(this.oauthConnectionResult, this.oauthConnectionErrorMessage)
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
			if (this.isAdminConfigOk) {
				// only fetch if we have a request url
				await this.fetchWorkpackages(this.fileInfo.id)
			} else {
				this.state = STATE.ERROR
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
				const topElementToScroll = this.$el.getElementsByClassName('existing-relations')[0]
				workpackage.classList.add('workpackage-transition')
				topElementToScroll.scrollIntoView({
					behavior: 'smooth',
				})
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
							if (fileId !== this.fileInfo.id) {
								break
							}
							workPackage.fileId = fileId
							// if the WP is already in the list, because the user switched quickly between files
							// and came back to a file that was selected before and so work-packages are already
							// in the list. In that case don't even try to fetch all the additional meta data
							if (!this.workpackageAlreadyInList(workPackage)) {
								workPackage = await workpackageHelper.getAdditionalMetaData(workPackage)
								// check again, the WP might have been added by an outstanding request
								// from another file or the file might have changed while fetching metadata
								if (
									!this.workpackageAlreadyInList(workPackage)
									&& workPackage.fileId === this.fileInfo.id
								) {
									this.workpackages.push(workPackage)
								}
							}
						}
					}
					// if the file was changed in between the state cannot be OK
					if (fileId === this.fileInfo.id) {
						this.state = STATE.OK
					}
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

	.linked-workpackages--workpackage {
		display: flex;
		position: relative;
		width: 100%;
		&--item {
			border: none;
		}
		&--unlinkactionbutton {
			margin: 4px;
			visibility: hidden;
		}
	}

	.linked-workpackages:hover {
		background-color: var(--color-background-dark);
	}

	.linked-workpackages:hover .linked-workpackages--workpackage--unlinkactionbutton {
		visibility: visible;
		cursor: pointer;
	}

	.workpackage-seperator {
		height: 0;
		margin: 0 10px;
		border-bottom: 1px solid rgb(237 237 237);
	}
}

.projects--empty {
	height: 100%;
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
</style>
