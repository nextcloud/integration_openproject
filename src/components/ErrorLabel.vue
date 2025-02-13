<!--
  - SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
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
