<template>
	<div class="multiple-link-modal-container">
		<NcModal
			v-if="show"
			:can-close="canCloseModal"
			@close="closeRequestModal">
			<div class="multiple-link-modal-content">
				<LoadingIcon v-if="isLoading" class="loading-spinner" :size="60" />
				<div v-else-if="chunkingInformation !== null" class="link-progress-information-wrapper">
					<div v-if="getChunkStateInChunking" class="link-progress-information-failed">
						<div class="link-progress-information-failed--details">
							<div class="link-progress-information-failed--details--info">
								<AlertCircleOutline fill-color="#FF0000" :size="70" />
							</div>
							<div class="link-progress-information-failed--details--info">
								<p>Files selected: {{ getTotalNoOfFilesSelectedInChunking }}</p>
								<p>Files successfully linked: {{ getTotalNoOfFilesAlreadyLinkedInChunking }}</p>
								<p :style="{ color: '#FF0000' }">
									Files failed to linked: {{ getTotalNoOfFilesNotLinkedInChunking }}
								</p>
							</div>
							<div class="link-progress-information-failed--details--info">
								<NcButton
									data-test-id="reset-user-app-password"
									@click="relinkRemainingFilesToWorkPackage">
									<template #icon>
										<AutoRenewIcon :size="20" />
									</template>
									{{ t('integration_openproject', 'Retry linking remaining files') }}
								</NcButton>
							</div>
						</div>
					</div>
					<div v-else class="link-progress-information-success">
						<FileLinkIcon :size="50" />
						<div class="success-progress-information">
							<div class="success-progress-information--title">
								<p>{{ getTotalNoOfFilesAlreadyLinkedInChunking }} of {{ getTotalNoOfFilesSelectedInChunking }} files linked</p>
								<p>{{ getProgressValueOfMultipleFilesLinked }}%</p>
							</div>
							<div class="success-progress-information--progress-bar">
								<NcProgressBar :value="getProgressValueOfMultipleFilesLinked" size="medium" />
							</div>
						</div>
					</div>
				</div>
				<div v-else class="multiple-link-modal-inside-content">
					<h2>
						{{ t('integration-openproject', 'Link to work package') }}
					</h2>
					<SearchInput v-if="!!isAdminConfigOk && !!isStateOk"
						:linked-work-packages="alreadyLinkedWorkPackage"
						:file-info="fileInfos"
						:search-origin="searchOrigin"
						@get-chunked-informations="getChunkedInformations"
						@close="closeRequestModal" />
					<EmptyContent
						id="openproject-empty-content"
						:state="state"
						:is-multiple-workpackage-linking="true"
						:is-admin-config-ok="isAdminConfigOk" />
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import SearchInput from '../components/tab/SearchInput.vue'
import EmptyContent from '../components/tab/EmptyContent.vue'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import LoadingIcon from 'vue-material-design-icons/Loading.vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import AutoRenewIcon from 'vue-material-design-icons/Autorenew.vue'
import FileLinkIcon from 'vue-material-design-icons/FileLink.vue'
import NcProgressBar from '@nextcloud/vue/dist/Components/NcProgressBar.js'

import {
	checkOauthConnectionResult,
	STATE,
	WORKPACKAGES_SEARCH_ORIGIN,
} from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { workpackageHelper } from '../utils/workpackageHelper.js'

export default {
	name: 'LinkMultipleFilesModal',
	components: {
		EmptyContent,
		SearchInput,
		NcModal,
		LoadingIcon,
		NcProgressBar,
		AlertCircleOutline,
		NcButton,
		AutoRenewIcon,
		FileLinkIcon,
	},
	data() {
		return {
			show: false,
			state: STATE.LOADING,
			fileInfos: [],
			alreadyLinkedWorkPackage: [],
			isAdminConfigOk: loadState('integration_openproject', 'admin-config-status'),
			oauthConnectionErrorMessage: loadState('integration_openproject', 'oauth-connection-error-message'),
			oauthConnectionResult: loadState('integration_openproject', 'oauth-connection-result'),
			searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.LINK_MULTIPLE_FILES_MODAL,
			chunkingInformation: null,
		}
	},

	computed: {
		isStateOk() {
			return this.state === STATE.OK
		},
		isLoading() {
			return this.state === STATE.LOADING
		},
		getTotalNoOfFilesSelectedInChunking() {
			return this.chunkingInformation?.totalNoOfFilesSelected
		},
		getTotalNoOfFilesAlreadyLinkedInChunking() {
			return this.chunkingInformation?.totalFilesAlreadyLinked
		},
		getTotalNoOfFilesNotLinkedInChunking() {
			return this.chunkingInformation?.totalFilesNotLinked
		},
		getProgressValueOfMultipleFilesLinked() {
			const progressPercentage = parseInt((this.chunkingInformation?.totalFilesAlreadyLinked / this.chunkingInformation?.totalNoOfFilesSelected) * 100)
			if (progressPercentage === 100) {
				this.closeRequestModal()
				showSuccess(t('integration_openproject', 'All selected files has been linked to WorkPackage Successfully!!'))
			}
			return progressPercentage
		},
		getChunkStateInChunking() {
			return this.chunkingInformation?.isChunkingError
		},
		canCloseModal() {
			return this.chunkingInformation?.isChunkingError !== false
		},
	},

	mounted() {
		checkOauthConnectionResult(this.oauthConnectionResult, this.oauthConnectionErrorMessage)
	},
	methods: {
		async relinkRemainingFilesToWorkPackage() {
			this.chunkingInformation.isChunkingError = false
			const remainingFilesToChunk = this.chunkingInformation.remainingFileInformations
			const selectedWorkPackage = this.chunkingInformation.selectedWorkPackage
			const chunkedFilesInformations = workpackageHelper.chunkMultipleSelectedFilesInformation(remainingFilesToChunk)
			await workpackageHelper.linkMultipleFilesToWorkPackageWithChunking(chunkedFilesInformations, selectedWorkPackage, true, this)
			if (this.chunkingInformation?.totalFilesAlreadyLinked !== remainingFilesToChunk.length) {
				showError(
					t('integration_openproject', 'Failed to link selected files to a work package')
				)
			}
		},
		getChunkedInformations(data) {
			this.chunkingInformation = data
		},
		showModal() {
			this.show = true
		},
		closeDropDown() {
			this.show = false
		},
		async setFileInfos(fileInfos) {
			this.fileInfos = fileInfos
			if (this.isAdminConfigOk) {
				await this.fetchWorkpackagesForSingleFileSelected(this.fileInfos[0].id)
			} else {
				this.state = STATE.ERROR
			}
		},
		closeRequestModal() {
			this.fileInfos = []
			this.alreadyLinkedWorkPackage = []
			this.show = false
			this.chunkingInformation = null
		},
		async fetchWorkpackagesForSingleFileSelected(fileId) {
			this.state = STATE.LOADING
			const req = {}
			const url = generateUrl('/apps/integration_openproject/work-packages?fileId=' + fileId)
			try {
				const response = await axios.get(url, req)
				if (!Array.isArray(response.data)) {
					this.state = STATE.FAILED_FETCHING_WORKPACKAGES
				} else {
					if (this.fileInfos.length === 1 && response.data.length > 0) {
						for (let workPackage of response.data) {
							workPackage.fileId = fileId
							workPackage = await workpackageHelper.getAdditionalMetaData(workPackage, true)
							this.alreadyLinkedWorkPackage.push(workPackage)
						}
					}
					this.state = STATE.OK
				}
			} catch (error) {
				if (error.response && error.response.status === 401) {
					this.state = STATE.NO_TOKEN
				} else if (error.response && (error.response.status === 404 || error.response.status === 503)) {
					this.state = STATE.CONNECTION_ERROR
				} else if (error.response && error.response.status === 500) {
					this.state = STATE.ERROR
				} else {
					this.state = STATE.FAILED_FETCHING_WORKPACKAGES
				}
			}
		}
	},
}
</script>

<style scoped lang="scss">
h2 {
	padding-left: 15px;
}

.multiple-link-modal-content {
	padding: 20px 30px;
	height: 450px;
	display: flex;
	justify-content: center;
	align-items: center;
}

.multiple-link-modal-inside-content {
	width: 100%;
	height: 100%;
	display: flex;
	justify-content: center;
	flex-direction: column;
}

.link-progress-information-wrapper {
	width: 75%;
}

.link-progress-information-success {
	width: 100%;
	display: flex;
	justify-content: center;
	align-items: center;
}

.success-progress-information {
	width: 100%;
	height: 45px;
	margin-right: 10px;
	margin-left: 10px;
	&--title {
		width: 100%;
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
	&--progress-bar {
		margin-top: 11px;
	}
}

.link-progress-information-failed {
	width: 100%;
	height: 250px;
	display: flex;
	justify-content: center;
	align-items: center;

	&--details {
		text-align: center;
		&--info {
			padding: 13px;
		}
	}
}
</style>
