<template>
	<div class="projects-container">
		<div class="title">
			Open Projects
		</div>
		<div class="subtitle">
			Your project information is here
		</div>
	</div>
</template>

<script>
export default {
	name: 'ProjectsTab',
	data: () => ({
		fileInfo: null
	}),
	computed: {},
	created() {},
	methods: {
		async update(fileInfo) {
			this.fileInfo = fileInfo
		},
	},
}
</script>

<style scoped lang="scss">
.projects-container {
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
