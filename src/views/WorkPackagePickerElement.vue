<!--
  - SPDX-FileCopyrightText: 2023 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div id="work-package-smart-picker" class="work-package-picker">
		<h2 class="work-package-picker__header">
			{{ t('integration_openproject', 'OpenProject work package picker') }}
		</h2>
		<SearchInput
			ref="linkPicker"
			:is-smart-picker="true"
			:file-info="fileInfo"
			:is-disabled="isLoading || !enableSearchInput || !isStateOk"
			:linked-work-packages="linkedWorkPackages"
			@submit="onSubmit" />
		<div id="openproject-empty-content">
			<NcLoadingIcon v-if="isLoading" class="loading-spinner" :size="90" />
			<EmptyContent
				v-else
				:state="state"
				:auth-method="adminConfigState.authMethod"
				:file-info="fileInfo"
				:is-smart-picker="true"
				:is-admin-config-ok="adminConfigState.isAdminConfigOk" />
		</div>
	</div>
</template>

<script>
import SearchInput from '../components/tab/SearchInput.vue'
import EmptyContent from '../components/tab/EmptyContent.vue'
import { AUTH_METHOD, STATE } from '../utils.js'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { NcLoadingIcon } from '@nextcloud/vue'

export default {
	name: 'WorkPackagePickerElement',

	components: {
		EmptyContent,
		SearchInput,
		NcLoadingIcon,
	},

	props: {
		providerId: {
			type: String,
			required: true,
		},
		accessible: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			fileInfo: {},
			linkedWorkPackages: [],
			state: STATE.LOADING,
			adminConfigState: loadState('integration_openproject', 'admin-config'),
		}
	},
	computed: {
		isStateOk() {
			return this.state === STATE.OK
		},
		isLoading() {
			return this.state === STATE.LOADING
		},
		enableSearchInput() {
			if (this.adminConfigState.authMethod === AUTH_METHOD.OAUTH2 && this.adminConfigState.isAdminConfigOk) {
				return true
			}
			if (this.adminConfigState.authMethod === AUTH_METHOD.OIDC && this.adminConfigState.isAdminConfigOk) {
				return true
			}
			return false
		},
	},
	mounted() {
		this.checkIfOpenProjectIsAvailable()
	},
	methods: {
		onSubmit(data) {
			this.$emit('submit', data)
		},
		async checkIfOpenProjectIsAvailable() {
			if (!this.adminConfigState.isAdminConfigOk) {
				this.state = STATE.ERROR
				return
			}
			const configurationUrl = generateUrl('/apps/integration_openproject/configuration')
			let response = null
			try {
				// send an axios request to fetch configuration to see if the connection is there
				response = await axios.get(configurationUrl)
				if (response.data) {
					this.state = STATE.OK
					if (this.$refs.linkPicker?.$refs?.workPackageSelect) {
						this.$nextTick(() => {
							document.getElementById(`${this.$refs.linkPicker?.$refs?.workPackageSelect?.inputId}`).focus()
						})
					}
				} else {
					this.state = STATE.ERROR
				}
			} catch (error) {
				document.activeElement?.blur()
				if (error.response && (error.response.status === 404 || error.response.status === 503)) {
					this.state = STATE.CONNECTION_ERROR
				} else if (error.response && error.response.status === 401) {
					this.state = STATE.NO_TOKEN
				} else {
					this.state = STATE.ERROR
				}
			}
		},
	},
}
</script>

<style scoped lang="scss">
.work-package-picker {
	width: 100%;
	display: flex;
	flex-direction: column;
	margin-top: 44px;
	h2 {
		display: flex;
		align-items: center;
		align-self: center;
	}
	#openproject-empty-content {
		height: 400px !important;
	}
	.loading-spinner {
		margin-top: 150px;
	}
}

</style>
