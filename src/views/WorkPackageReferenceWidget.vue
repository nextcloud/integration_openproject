<!--
  - @copyright Copyright (c) 2023 Julien Veyssier <julien-nc@posteo.net>
  -
  - @author 2023 Julien Veyssier <julien-nc@posteo.net>
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
	<div id="workpackage-link-previews" class="work-package-reference">
		<div v-if="isError">
			<h3 class="error-title">
				<CloseIcon :size="20" class="icon" />
				<span>{{ t('integration_openproject', 'OpenProject API error') }}</span>
			</h3>
			<a :href="settingsUrl" class="settings-link external" target="_blank">
				<OpenInNewIcon :size="20" class="icon" />
				{{ t('integration_openproject', 'OpenProject settings') }}
			</a>
		</div>
		<WorkPackage v-if="workpackage"
			:id="'workpackage-'+ richObject.id"
			class="work-package-reference__link-preview"
			:workpackage="workpackage"
			:is-link-previews="true" />
	</div>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import { generateUrl } from '@nextcloud/router'
import WorkPackage from '../components/tab/WorkPackage.vue'
import { workpackageHelper } from '../utils/workpackageHelper.js'

export default {
	name: 'WorkPackageReferenceWidget',

	components: {
		OpenInNewIcon,
		CloseIcon,
		WorkPackage,
	},

	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: null,
		},
		accessible: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			settingsUrl: generateUrl('/settings/user/openproject'),
			workpackage: null,
		}
	},

	computed: {
		isError() {
			return !!this.richObject.error
		},
	},

	mounted() {
		this.processWorkpackages()
	},

	methods: {
		async processWorkpackages() {
			this.workpackage = await workpackageHelper.getAdditionalMetaData(this.richObject)
		},
	},
}
</script>

<style scoped lang="scss">
.work-package-reference {
	width: 100%;
	white-space: normal;
	&__link-preview {
		border-bottom: none;
	}

	a {
		padding: 0 !important;
		color: var(--color-main-text) !important;
		text-decoration: unset !important;
	}

	.error-title {
		display: flex;
		align-items: center;
		font-weight: bold;
		margin-top: 0;
		.icon {
			margin-right: 8px;
		}
	}

	.settings-link {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}

	.spacer {
		flex-grow: 1;
	}
}
</style>
