<template>
	<div class="projects">
		<SearchInput />
		<EmptyContent />
	</div>
</template>

<script>
import EmptyContent from '../components/tab/EmptyContent'
import SearchInput from '../components/tab/SearchInput'

export default {
	name: 'ProjectsTab',
	components: {
		EmptyContent,
		SearchInput,
	},
	data: () => ({
		error: '',
		loading: false,
		fileInfo: null,
	}),
	computed: {},
	created() {},
	methods: {
		/**
		 * updates current resource
		 *
		 * @param {object} fileInfo file information
		 *
		 * @return void
		 */
		async update(fileInfo) {
			this.fileInfo = fileInfo
		},
		/**
		 * Reset the current view to its default state
		 *
		 * @return void
		 */
		resetState() {
			this.loading = false
			this.error = ''
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
