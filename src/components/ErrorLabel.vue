<template>
	<p class="error">
		{{ error }}
	</p>
</template>

<script>
export default {
	name: 'ErrorLabel',
	props: {
		error: {
			type: String,
			required: true,
		},
	},
}
</script>

<style scoped lang="scss">
.error {
	color: var(--color-error);
}
</style>
