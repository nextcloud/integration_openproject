<template>
	<input
		id="project-search"
		v-model="search"
		:placeholder="placeholder"
		type="text"
	>
</template>

<script>
export default {
	name: 'SearchInput',
	data: () => ({
		search: null,
	}),
	computed: {
		placeholder() {
			return t('integration_openproject', 'Search for a work package to create a relation')
		},
	},
	methods: {},
}
</script>

<style scoped lang="scss">
#project-search {
	font-size: 14px;
	width: 100%;
}
</style>
