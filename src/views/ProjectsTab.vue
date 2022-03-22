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
			<WorkPackage
				v-for="(workpackage, index) in workpackages"
				:key="workpackage.id"
				:class="{ 'workpackage-seperator': index !== workpackages.length-1 }"
				:workpackage="workpackage"
				v-on:click.native="getClickedWPId(workpackage.id)">
				<template #settings>
					<div v-if="displaySettings && clickedWPId === workpackage.id" class="settings">
						<div class="settings__openproject">
									<span class="settings__openproject__openlink">
									<img class="logo" :src="appSvg" alt="app">
									{{ t('integration_openproject', 'Open in OpenProject') }}</span>
						</div>
						<div class="settings__delete">
									<span class="settings__delete__link">
									<img class="bin" :src="binSvg" alt="bin">
									{{ t('integration_openproject', 'Delete link') }}</span>
						</div>
					</div>
				</template>
			</WorkPackage>
		</div>
		<EmptyContent v-else
					  id="openproject-empty-content"
					  :state="state"
					  :request-url="requestUrl"/>
	</div>
</template>

<script>
import EmptyContent from '../components/tab/EmptyContent'
import WorkPackage from '../components/tab/WorkPackage'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import SearchInput from '../components/tab/SearchInput'
import { loadState } from '@nextcloud/initial-state'
import { workpackageHelper } from '../utils/workpackageHelper'

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
		count: 0,
		clickedWPId: 0,
	}),
	computed: {
		isLoading() {
			return this.state === 'loading'
		},
		appSvg() {
			return require('../../img/logo.svg')
		},
		binSvg() {
			return require('../../img/bin.svg')
		},
		displaySettings() {
			if (this.count % 2 == 0) {
				return false
			} else {
				return true
			}
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
			this.count = 0
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
		getClickedWPId(id) {
			if (id !== 0) {
				this.clickedWPId = id
				this.count = this.count + 1
			} else if (id === this.clickedWPId) {
				this.count = this.count + 1
			}
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

	.workpackage-seperator{
		border-bottom: 1px solid rgb(237 237 237);
	}
	#openproject-linked-workpackages {
		position: relative;
	}

	.settings {
		width: 250px;
		top: 0;
		height: fit-content;
		box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.25);
		background: #FFFFFF;
		border-radius: 3px;
		position: absolute;
		left: 150px;
		//z-index: 2;

		& __openproject {
			padding: 12px;
			padding-bottom: 0;

			.logo {
				padding-right: 8px;
			}
		}

		&__delete {
			padding: 12px;

			.bin {
				padding-right: 8px;
			}
		}
	}
}
</style>
