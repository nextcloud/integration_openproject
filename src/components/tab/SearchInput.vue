<template>
	<div id="searchBar">
		<NcSelect ref="workPackageSelect"
			class="searchInput"
			input-id="searchInput"
			:placeholder="placeholder"
			:options="setOptionForSearch"
			:user-select="true"
			:append-to-body="false"
			label="displayName"
			:loading="isStateLoading"
			:filterable="false"
			:clear-search-on-blur="() => false"
			@search="asyncFind"
			@option:selected="linkWorkPackageToFile">
			<template #option="option">
				<WorkPackage :key="option.id"
					:workpackage="option"
					:is-smart-picker="isSmartPicker" />
			</template>
			<template #no-options>
				{{ noOptionsText }}
			</template>
			<template v-if="!isSmartPicker" #list-footer>
				<li class="create-workpackage-footer-option" @click="openIframe()">
					<Plus :size="20" fill-color="var(--color-primary)" />
					<span class="create-workpackage-footer-option--label">{{ t('integration_openproject', 'Create and link a new work package') }}</span>
				</li>
			</template>
		</NcSelect>
		<div v-if="!isStateOk"
			class="stateMsg text-center">
			{{ stateMessages }}
		</div>
		<div v-if="!!isStateOk && !isSmartPicker" class="create-workpackage">
			<NcActions>
				<NcActionButton class="create-workpackage--button" @click="openIframe()">
					<template #icon>
						<Plus :size="20" />
					</template>
				</NcActionButton>
			</NcActions>
			<span class="create-workpackage--label">{{ t('integration_openproject', 'Create and link a new work package') }}</span>
		</div>
		<CreateWorkPackageModal v-if="!isSmartPicker"
			:show-modal="iframeVisible"
			@createWorkPackage="handelCreateWorkPackageEvent"
			@closeCreateWorkPackageModal="handelCloseCreateWorkPackageModalEvent" />
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import debounce from 'lodash/debounce.js'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import WorkPackage from './WorkPackage.vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'
import { workpackageHelper } from '../../utils/workpackageHelper.js'
import { STATE, WORKPACKAGES_SEARCH_ORIGIN } from '../../utils.js'
import Plus from 'vue-material-design-icons/Plus.vue'
import CreateWorkPackageModal from '../../views/CreateWorkPackageModal.vue'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'

const SEARCH_CHAR_LIMIT = 1
const DEBOUNCE_THRESHOLD = 500

export default {
	name: 'SearchInput',
	components: {
		NcActions,
		NcActionButton,
		CreateWorkPackageModal,
		Plus,
		WorkPackage,
		NcSelect,
	},
	props: {
		fileInfo: {
			type: [Object, Array],
			default: null,
		},
		linkedWorkPackages: {
			type: Array,
			default: null,
		},
		isSmartPicker: {
			type: Boolean,
			default: false,
		},
		searchOrigin: {
			type: String,
			required: false,
			default: null,
		},
	},
	data: () => ({
		state: STATE.OK,
		searchResults: [],
		noOptionsText: t('integration_openproject', 'Start typing to search'),
		openprojectUrl: loadState('integration_openproject', 'openproject-url'),
		iframeVisible: false,
		newWorkpackageCreated: false,
		workpackageData: [], // only for newly created workpackages
	}),
	computed: {
		isStateOk() {
			return this.state === STATE.OK
		},
		isStateLoading() {
			return this.state === STATE.LOADING
		},
		stateMessages() {
			if (this.state === STATE.NO_TOKEN) {
				return t('integration_openproject', 'No OpenProject account connected')
			} else if (this.state === STATE.ERROR) {
				return t('integration_openproject', 'Error connecting to OpenProject')
			}
			return ''
		},
		setOptionForSearch() {
			if (this.searchOrigin === WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB || this.isSmartPicker) {
				return this.filterSearchResultsByFileId
			}
			return this.searchResults
		},
		placeholder() {
			if (this.isSmartPicker) {
				return t('integration_openproject', 'Search for work packages')
			} else {
				return t('integration_openproject', 'Search for a work package to create a relation')
			}
		},
		filterSearchResultsByFileId() {
			return this.searchResults.filter(wp => {
				if (this.isSmartPicker) {
					return wp.id
				}
				if (wp.fileId === undefined || wp.fileId === '') {
					console.error('work-package data does not contain a fileId')
					return false
				}
				return wp.fileId === this.fileInfo.id
			})
		},
	},
	watch: {
		fileInfo(oldFile, newFile) {
			if (oldFile.id !== newFile.id) {
				this.resetState()
			}
		},
	},
	methods: {
		async handelCreateWorkPackageEvent(data) {
			this.searchResults = []
			this.iframeVisible = false
			if (
				data.openProjectEventName === 'work_package_creation_cancellation'
			) {
				showError(t('integration_openproject', 'Work package creation was not successfull.'))
			}
			if (data.openProjectEventName === 'work_package_creation_success') {
				showSuccess(t('integration_openproject', 'Work package created successfully.'))
				const workPackageId = parseInt(data.openProjectEventPayload.workPackageId)
				if (this.searchOrigin === WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB) {
					this.newWorkpackageCreated = true
					// search by work package id when searched from the project tab to update the
					// existing linked work package automatically
					await this.makeSearchRequest(null, this.fileInfo.id, workPackageId)
					await this.linkWorkPackageToFile(this.searchResults[0])
				} else if (this.searchOrigin === WORKPACKAGES_SEARCH_ORIGIN.LINK_MULTIPLE_FILES_MODAL) {
					const workpackageInfo = {
						id: workPackageId,
					}
					await this.linkWorkPackageToFile(workpackageInfo)
				}
			}
		},
		handelCloseCreateWorkPackageModalEvent() {
			this.iframeVisible = false
		},
		openIframe() {
			this.iframeVisible = true
		},
		resetState() {
			this.searchResults = []
			this.state = STATE.OK
			this.newWorkpackageCreated = false
		},
		checkForErrorCode(statusCode) {
			if (statusCode === 200) return
			if (statusCode === 401) {
				this.state = STATE.NO_TOKEN
			} else {
				this.state = STATE.ERROR
			}
		},
		async asyncFind(query) {
			this.resetState()
			if (this.searchOrigin === WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB) {
				await this.debounceMakeSearchRequest(query, this.fileInfo.id, null)
			} else {
				// we do not need to provide a file id in case of searching through link multiple files to work package and through smart picker
				await this.debounceMakeSearchRequest(query, null, null)
			}
		},
		async getWorkPackageLink(selectedOption) {
			return this.openprojectUrl.replace(/^\/+|\/+$/g, '') + '/wp/' + selectedOption.id
		},
		debounceMakeSearchRequest: debounce(function(...args) {
			if (args[0].length < SEARCH_CHAR_LIMIT) return
			return this.makeSearchRequest(...args)
		}, DEBOUNCE_THRESHOLD),
		async linkWorkPackageToFile(selectedOption) {
			if (this.isSmartPicker) {
				const link = await this.getWorkPackageLink(selectedOption)
				this.$emit('submit', link)
				return
			}
			// since we can link multiple files now we send file information required in an array (whether it's only one value or multiple)
			let fileInfoForBody = []
			let successMessage
			if (this.searchOrigin === WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB) {
				fileInfoForBody.push(this.fileInfo)
				successMessage = t('integration_openproject', 'Link to work package created successfully!')
			} else if (this.searchOrigin === WORKPACKAGES_SEARCH_ORIGIN.LINK_MULTIPLE_FILES_MODAL) {
				fileInfoForBody = this.fileInfo
				successMessage = t('integration_openproject', 'Links to work package created successfully for selected files!')
			}

			const config = {
				headers: {
					'Content-Type': 'application/json',
				},
			}

			const body = {
				values: {
					workpackageId: selectedOption.id,
					fileinfo: fileInfoForBody,
				},
			}
			const url = generateUrl('/apps/integration_openproject/work-packages')
			try {
				await axios.post(url, body, config)
				this.$emit('saved', selectedOption)
				showSuccess(successMessage)
				this.resetState()
			} catch (e) {
				if (parseInt(e.response.status) === 422) {
					showError(
						t('integration_openproject', 'Failed to link file to work package. Storage is not linked to project.')
					)
				} else {
					showError(
						t('integration_openproject', 'Failed to link file to work package')
					)
				}
			}
		},
		async makeSearchRequest(search, fileId, workpackageId) {
			this.state = STATE.LOADING
			const url = generateUrl('/apps/integration_openproject/work-packages')
			const isSmartPicker = this.isSmartPicker
			const req = {}
			req.params = {
				searchQuery: search,
				isSmartPicker,
				workpackageId,
			}
			let response
			try {
				response = await axios.get(url, req)
			} catch (e) {
				response = e.response
			}
			this.checkForErrorCode(response.status)
			if (response.status === 200) await this.processWorkPackages(response.data, fileId)
			if (this.isStateLoading) this.state = STATE.OK
		},
		async processWorkPackages(workPackages, fileId) {
			for (let workPackage of workPackages) {
				try {
					if (this.isStateLoading) {
						if (this.isSmartPicker) {
						   workPackage = await workpackageHelper.getAdditionalMetaData(workPackage)
							this.searchResults.push(workPackage)
						} else {
							workPackage.fileId = fileId
							workPackage = await workpackageHelper.getAdditionalMetaData(workPackage)
							if (this.newWorkpackageCreated) {
								this.searchResults.push(workPackage)
							}
							const alreadyLinked = this.linkedWorkPackages.some(el => el.id === workPackage.id)
							const alreadyInSearchResults = this.searchResults.some(el => el.id === workPackage.id)
							// check the state again, it might have changed in between
							if (!alreadyInSearchResults && !alreadyLinked && this.isStateLoading) {
								this.searchResults.push(workPackage)
							}
						}
					}
				} catch (e) {
					console.error('could not process work package data')
				}
			}
		},
	},
}
</script>
<style lang="scss">
#searchBar {
	padding: 10px;
	position: sticky;
	position: -webkit-sticky; /* Safari */
	z-index: 1;
	top: 0;
	background: var(--color-main-background);
	.searchInput {
		width: 100%;
	}
	.stateMsg {
		padding: 30px;
		text-align: center;
	}
	.vs__dropdown-option {
		padding: 0 !important;
	}

	.create-workpackage-footer-option {
		margin-top: 5px;
		width: 100%;
		border-top: 1px solid var(--color-background-dark);
		padding: 20px 10px 20px 10px;
		display: flex;
		flex-direction: row;
		justify-content: center;
		align-items: center;
		position: sticky;
		position: -webkit-sticky; /* Safari */
		z-index: 1;
		bottom: 0;
		background: var(--color-main-background);
		overflow-y: hidden;
		&--label {
			color: var(--color-primary);
			padding-left: 5px;
			font-size: 14px;
			font-weight: 400;
			line-height: 16px;
			letter-spacing: 0;
			text-align: left;

		}
	}
	.create-workpackage {
		margin-top: 10px;
		display: flex;
		align-items: center;
		&--button {
			background-color: var(--color-background-dark)
		}
		&--label {
			padding-left: 5px;
			font-size: 1rem;
			line-height: 1.4rem;
			font-weight: 400;
			text-align: left;
		}
	}

	.create-workpackage-footer-option:hover {
		background-color: var(--color-background-dark);
		cursor: pointer;
	}
}
</style>
