<!--
  - @copyright Copyright (c) 2023 Swikriti Tripathi <swikriti@jakaritech.com>
  -
  - @author 2023 Swikriti Tripathi <swikriti@jakaritech.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div class="work-package-picker">
		<h2 class="work-package-picker__header">
			{{ t("integration_openproject", "OpenProject work package picker") }}
		</h2>
		<SearchInput
			ref="linkPicker"
			:is-smart-picker="true"
			:file-info="fileInfo"
			:linked-work-packages="linkedWorkPackages"
			@submit="onSubmit" />
		<EmptyContent
			id="openproject-empty-content"
			:state="state"
			:file-info="fileInfo"
			:is-smart-picker="true"
			:is-admin-config-ok="isAdminConfigOk" />
		<iframe
			id="inlineFrameExample"
			title="Inline Frame Example"
			height="1000px"
			src="https://openproject.local/work_packages/new?iframe=true" />
	</div>
</template>

<script>
import SearchInput from '../components/tab/SearchInput.vue'
import EmptyContent from '../components/tab/EmptyContent.vue'
import { STATE } from '../utils.js'
import { loadState } from '@nextcloud/initial-state'

function handler(event) {
	if (event.origin !== 'https://openproject.local') return

	console.log('Event', event.data)
	if (
		event.data.openProjectEventName === 'work_package_creation_cancellation'
	) {
		alert('Canceled')
	}
	if (event.data.openProjectEventName === 'work_package_creation_success') {
		alert(
			'Saved. WorkPackage ID: '
        + event.data.openProjectEventPayload.workPackageId
        + ' '
        + event.data.openProjectEventPayload.workPackageUrl
		)
	}
}
export default {
	name: 'WorkPackagePickerElement',

	components: {
		EmptyContent,
		SearchInput,
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

	data: () => ({
		fileInfo: {},
		linkedWorkPackages: [],
		state: STATE.OK,
		isAdminConfigOk: loadState(
			'integration_openproject',
			'admin-config-status'
		),
	}),
	mounted() {
		if (this.$refs.linkPicker?.$refs?.workPackageSelect) {
			document
				.getElementById(
					`${this.$refs.linkPicker?.$refs?.workPackageSelect?.inputId}`
				)
				.focus()
		}
		window.addEventListener('message', handler, false)
	},
	methods: {
		onSubmit(data) {
			this.$emit('submit', data)
		},
	},
}
</script>

<style scoped lang="scss">
.work-package-picker {
	width: 100%;
	height: 200%;
	display: flex;
	flex-direction: column;
	margin-top: 44px;
	h2 {
		display: flex;
		align-items: center;
		align-self: center;
	}
}

iframe {
	border: 5px solid red;
}
</style>
