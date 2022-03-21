<template>
	<div id="searchBar">
		<Multiselect id="search-input"
			class="searchInput"
			:placeholder="placeholder"
			:options="searchResults"
			:user-select="true"
			label="displayName"
			track-by="id"
			:internal-search="false"
			open-direction="below"
			:loading="isStateLoading"
			:preselect-first="true"
			:preserve-search="true"
			@search-change="makeSearchRequest"
			@change="linkWorkPackageToFile">
			<template #option="{option}">
				<WorkPackage :key="option.id"
					:workpackage="option" />
			</template>
			<template #noOptions>
				{{ translate('Start typing to search') }}
			</template>
		</Multiselect>
		<div v-if="state !== 'ok'"
			class="stateMsg text-center">
			{{ stateMessages }}
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import WorkPackage from './WorkPackage'
import { showError } from '@nextcloud/dialogs'
import { workpackageHelper } from '../../utils/workpackageHelper'

const STATE_OK = 'ok'
const STATE_ERROR = 'error'
const STATE_NO_TOKEN = 'no-token'
const STATE_LOADING = 'loading'
const SEARCH_CHAR_LIMIT = 3

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
	},
	data: () => ({
		state: STATE_OK,
		searchResults: [],
		selectedId: [],
	}),
	computed: {
		isStateOk() {
			return this.state === STATE_OK
		},
		isStateLoading() {
			return this.state === STATE_LOADING
		},
		placeholder() {
			return this.translate('Search for a work package to create a relation')
		},
		stateMessages() {
			if (this.state === STATE_NO_TOKEN) {
				return this.translate('No OpenProject account connected')
			} else if (this.state === STATE_ERROR) {
				return this.translate('Error connecting to OpenProject')
			}
			return ''
		},
	},
	watch: {
		fileInfo(oldFile, newFile) {
			if (oldFile.id !== newFile.id) {
				this.selectedId = []
				this.resetState()
				document.getElementById('search-input').value = ''
			}
		},
	},
	methods: {
		resetState() {
			this.searchResults = []
			this.state = STATE_OK
		},
		translate(key) {
			return t('integration_openproject', key)
		},
		checkForErrorCode(statusCode) {
			if (statusCode === 200) return
			if (statusCode === 401) {
				this.state = STATE_NO_TOKEN
			} else {
				this.state = STATE_ERROR
			}
		},
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
				this.selectedId.push({
					id: selectedOption.id,
				})
			} catch (e) {
				showError(
					this.translate('Failed to link file to work-package')
				)
			}
		},
		async makeSearchRequest(search) {
			if (search.length <= SEARCH_CHAR_LIMIT) {
				this.resetState()
				return
			}
			this.state = STATE_LOADING
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
			if (response.status === 200) await this.processWorkPackages(response.data)
			if (this.isStateLoading) this.state = STATE_OK
		},
		async processWorkPackages(workPackages) {
			for (let workPackage of workPackages) {
				try {
					workPackage = await workpackageHelper.getAdditionalMetaData(workPackage)
					const selectedIdFound = this.selectedId.some(el => el.id === workPackage.id)
					const workpackageIdFound = this.searchResults.some(el => el.id === workPackage.id)
					if (!workpackageIdFound && !selectedIdFound) {
						this.searchResults.push(workPackage)
					}
				} catch (e) {
					console.error('could not process workpackage data')
				}
			}
		},
	},
}
</script>
<style scoped lang="scss">
.searchInput {
	width: 100%;
}

.stateMsg {
	padding: 30px;
	text-align: center;
	color: #6d6d6d;
}

</style>
