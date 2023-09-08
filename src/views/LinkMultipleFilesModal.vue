<template>
	<div class="multiple-link-modal-container">
		<NcModal
			v-if="show"
			@close="closeRequestModal">
			<div class="multiple-link-modal-content">
				<LoadingIcon v-if="isLoading" class="loading-spinner" :size="60" />
				<div v-else class="multiple-link-modal-inside-content">
					<h2>
						{{ t('integration-openproject', 'Link to work package') }}
					</h2>
					<SearchInput v-if="!!isAdminConfigOk && !!isStateOk"
						:linked-work-packages="alreadyLinkedWorkPackage"
						:file-info="fileInfos"
						:search-origin="searchOrigin"
						@saved="onSaved" />
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
import { WORKPACKAGES_SEARCH_ORIGIN, STATE } from '../utils.js'
import { workpackageHelper } from '../utils/workpackageHelper.js'

export default {
	name: 'LinkMultipleFilesModal',
	components: {
		EmptyContent,
		SearchInput,
		NcModal,
		LoadingIcon,
	},
	data() {
		return {
			show: false,
			state: STATE.LOADING,
			fileInfos: [],
			alreadyLinkedWorkPackage: [],
			isAdminConfigOk: loadState('integration_openproject', 'admin-config-status'),
			searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.LINK_MULTIPLE_FILES_MODAL,
		}
	},

	computed: {
		isStateOk() {
			return this.state === STATE.OK
		},
		isLoading() {
			return this.state === STATE.LOADING
		},
	},
	methods: {
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
		},
		onSaved() {
			this.closeRequestModal()
		},
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
</style>
