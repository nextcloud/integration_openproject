<template>
	<div id="searchBar">
		<Multiselect ref="workPackageMultiSelect"
			class="searchInput"
			:placeholder="placeholder"
			:options="filterSearchResultsByFileId"
			:user-select="true"
			label="displayName"
			track-by="id"
			:internal-search="false"
			open-direction="below"
			:loading="isStateLoading"
			:preselect-first="true"
			:preserve-search="true"
			@search-change="asyncFind"
			@change="linkWorkPackageToFile">
			<template #option="{option}">
				<WorkPackage :key="option.id"
					:workpackage="option" />
			</template>
			<template #noOptions>
				{{ noOptionsText }}
			</template>
		</Multiselect>
		<div v-if="!isStateOk"
			class="stateMsg text-center">
			{{ stateMessages }}
		</div>
	</div>
</template>

<script>
import debounce from 'lodash/debounce'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import WorkPackage from './WorkPackage'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { workpackageHelper } from '../../utils/workpackageHelper'
import { STATE } from '../../utils'

const SEARCH_CHAR_LIMIT = 1
const DEBOUNCE_THRESHOLD = 500

export default {
	name: 'SearchInput',
	components: {
		Multiselect,
		WorkPackage,
	},
	props: {
		fileInfo: {
			type: Object,
			required: true,
		},
		linkedWorkPackages: {
			type: Array,
			required: true,
		},
	},
	data: () => ({
		state: STATE.OK,
		searchResults: [],
		noOptionsText: t('integration_openproject', 'Start typing to search'),
		placeholder: t('integration_openproject', 'Search for a work package to create a relation'),
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
		filterSearchResultsByFileId() {
			return this.searchResults.filter(wp => {
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
			if (this.$refs.workPackageMultiSelect?.$refs?.VueMultiselect?.search) {
				this.$refs.workPackageMultiSelect.$refs.VueMultiselect.search = ''
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
			await this.debounceMakeSearchRequest(query, this.fileInfo.id)
		},
		debounceMakeSearchRequest: debounce(function(...args) {
			if (args[0].length < SEARCH_CHAR_LIMIT) return
			return this.makeSearchRequest(...args)
		}, DEBOUNCE_THRESHOLD),
		async linkWorkPackageToFile(selectedOption) {
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
		async makeSearchRequest(search, fileId) {
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
						workPackage.fileId = fileId
						workPackage = await workpackageHelper.getAdditionalMetaData(workPackage)
						const alreadyLinked = this.linkedWorkPackages.some(el => el.id === workPackage.id)
						const alreadyInSearchResults = this.searchResults.some(el => el.id === workPackage.id)
						// check the state again, it might have changed in between
						if (!alreadyInSearchResults && !alreadyLinked && this.isStateLoading) {
							this.searchResults.push(workPackage)
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
	.multiselect {
		.multiselect__content-wrapper {
			.multiselect__content {
				.multiselect__element {
					span {
						padding: 0 !important;
					}
				}
			}
		}
	}
}
</style>
