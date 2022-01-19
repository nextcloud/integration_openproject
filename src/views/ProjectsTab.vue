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
		<div :class="{ 'icon-loading': state === 'loading' }">
			<EmptyContent v-if="state !== 'loading'" id="openproject-empty-content" :state="state" />
		</div>
	</div>
</template>

<script>
import EmptyContent from '../components/tab/EmptyContent'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'ProjectsTab',
	components: {
		EmptyContent,
	},
	data: () => ({
		error: '',
		fileInfo: null,
		state: 'loading',
	}),
	computed: {},
	created() {},
	methods: {
		/**
		 * updates current resource
		 *
		 * @param {object} fileInfo file information
		 */
		async update(fileInfo) {
			this.fileInfo = fileInfo
			await this.fetchWorkpackages(this.fileInfo.id)
		},
		/**
		 * Reset the current view to its default state
		 */
		resetState() {
			this.error = ''
			this.state = 'loading'
		},

		async fetchWorkpackages(fileId) {
			const req = {}
			const url = generateUrl('/apps/integration_openproject/workpackages/' + fileId)
			try {
				const response = await axios.get(url, req)
				if (!Array.isArray(response.data)) {
					this.state = 'error'
				} else {
					this.state = 'ok'
				}
			} catch (error) {
				if (error.response && error.response.status === 401) {
					this.state = 'no-token'
				} else {
					this.state = 'error'
				}
			}
		},
	},
}
</script>

<style scoped lang="scss">
.projects {
	height: 100% !important;
	text-align: center;
	.title {
		font-size:  2rem;
		font-weight: 600;
		padding-bottom: 0;
	}
	.subtitle {
		padding-top: 0;
		font-size: 1.2rem;
	}
}
</style>
