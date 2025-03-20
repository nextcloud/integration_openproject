<!--
  - SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<p class="error" :disabled="disabled">
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
		disabled: {
			type: Boolean,
			default: false,
		},
	},
}
</script>

<style scoped lang="scss">
.error {
	color: var(--color-error);
}

.error[disabled] {
	color: var(--color-text-maxcontrast);
}
</style>
