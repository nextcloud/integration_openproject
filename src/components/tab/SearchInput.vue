<template>
	<div id="searchBar">
		<NcSelect ref="workPackageSelect"
			class="searchInput"
			:placeholder="placeholder"
			:options="filterSearchResultsByFileId"
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
					:workpackage="option" />
			</template>
			<template #no-options>
				{{ noOptionsText }}
			</template>
		</NcSelect>
		<div v-if="!isStateOk"
			class="stateMsg text-center">
			{{ stateMessages }}
		</div>
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
import { STATE } from '../../utils.js'

const SEARCH_CHAR_LIMIT = 1
const DEBOUNCE_THRESHOLD = 500

export default {
	name: 'SearchInput',
	components: {
		WorkPackage,
		NcSelect,
	},
	props: {
		fileInfo: {
			type: Object,
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
	},
	data: () => ({
		state: STATE.OK,
		searchResults: [],
		noOptionsText: t('integration_openproject', 'Start typing to search'),
		openprojectUrl: loadState('integration_openproject', 'openproject-url'),
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
				this.emptySearchInput()
			}
		},
	},
	methods: {
		emptySearchInput() {
			// FIXME: https://github.com/shentao/vue-multiselect/issues/633
			if (this.$refs.workPackageSelect?.$refs?.VueMultiselect?.search) {
				this.$refs.workPackageSelect.$refs.VueMultiselect.search = ''
			}
		},
		resetState() {
			this.searchResults = []
			this.state = STATE.OK
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
			if (this.isSmartPicker) {
				await this.debounceMakeSearchRequest(query)
			} else {
				await this.debounceMakeSearchRequest(query, this.fileInfo.id)
			}
		},
		async getFileLink(selectedOption) {
			return this.openprojectUrl + '/projects/' + selectedOption.projectId + '/work_packages/' + selectedOption.id
		},
		debounceMakeSearchRequest: debounce(function(...args) {
			if (args[0].length < SEARCH_CHAR_LIMIT) return
			return this.makeSearchRequest(...args)
		}, DEBOUNCE_THRESHOLD),
		async linkWorkPackageToFile(selectedOption) {
			if (this.isSmartPicker) {
				const link = await this.getFileLink(selectedOption)
				this.$emit('submit', link)
				return
			}
			const params = new URLSearchParams()
			params.append('workpackageId', selectedOption.id)
			params.append('fileId', this.fileInfo.id)
			params.append('fileName', this.fileInfo.name)
			const config = {
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
			}
			const url = generateUrl('/apps/integration_openproject/work-packages')

			try {
				await axios.post(url, params, config)
				this.$emit('saved', selectedOption)
				showSuccess(
					t('integration_openproject', 'Work package linked successfully!')
				)
				this.resetState()
				this.emptySearchInput()
			} catch (e) {
				showError(
					t('integration_openproject', 'Failed to link file to work package')
				)
			}
		},
		async makeSearchRequest(search, fileId = null) {
			this.state = STATE.LOADING
			const url = generateUrl('/apps/integration_openproject/work-packages')
			const req = {}
			req.params = {
				searchQuery: search,
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
						   if (this.isStateLoading) {
							   this.searchResults.push(workPackage)
							}
						} else {
							workPackage.fileId = fileId
							workPackage = await workpackageHelper.getAdditionalMetaData(workPackage)
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
}
</style>
