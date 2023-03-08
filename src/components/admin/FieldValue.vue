<template>
	<div class="field-item">
		<b class="field-item-title">
			{{ title }}{{ isRequired ? '*' : '' }}:
		</b>

		<div class="field-item-value">
			{{ valueContent }}
		</div>

		<EyeIcon v-if="encryptValue && withInspection && !inspect"
			:size="16"
			class="field-item-inspect-btn"
			@click="toggleInspection" />
		<EyeOffIcon v-else-if="inspect"
			:size="16"
			class="field-item-inspect-off-btn"
			@click="toggleInspection" />
	</div>
</template>
<script>
import EyeIcon from 'vue-material-design-icons/Eye.vue'
import EyeOffIcon from 'vue-material-design-icons/EyeOff.vue'

export default {
	name: 'FieldValue',
	components: {
		EyeIcon, EyeOffIcon,
	},
	props: {
		title: {
			type: String,
			required: true,
		},
		value: {
			type: String,
			required: true,
		},
		isRequired: {
			type: Boolean,
			default: false,
		},
		encryptValue: {
			type: Boolean,
			default: false,
		},
		hideValue: {
			type: Boolean,
			default: false,
		},
		withInspection: {
			type: Boolean,
			default: false,
		},
	},
	data: () => ({
		inspect: false,
	}),
	computed: {
		encryptedValue() {
			return this.value.substring(0, 8) + '*'.repeat(15)
		},
		doNotShowValue() {
			return '*'.repeat(25)
		},
		valueContent() {
			return (this.encryptValue && !this.inspect) ? this.encryptedValue
				: (this.hideValue && !this.inspect) ? this.doNotShowValue
				: this.value
		},
	},
	methods: {
		toggleInspection() {
			this.inspect = !this.inspect
		},
	},
}
</script>
<style lang="scss">
.field-item {
	padding: 6px 0;
	display: flex;
	align-items: center;
	&-value {
		padding: 0 4px;
	}
}
</style>
