<template>
	<div id="openproject_direct_download">
		<a :href="directDownloadLink" :download="state.fileName">
			<Button>
				<template #icon>
					<DownloadIcon />
				</template>
				{{ state.fileName }}
			</Button>
		</a>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import Button from '@nextcloud/vue/dist/Components/Button'
import DownloadIcon from 'vue-material-design-icons/Download'

export default {
	name: 'DirectDownload',

	components: {
		Button,
		DownloadIcon,
	},

	data() {
		return {
			state: loadState('integration_openproject', 'direct'),
		}
	},

	computed: {
		directDownloadLink() {
			return generateUrl('/remote.php/direct/{token}', { token: this.state.token }).replace('index.php/', '')
		},
	},

	mounted() {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
// nothing yet
#openproject_direct_download {
	width: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
}
</style>
