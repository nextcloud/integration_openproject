<template>
	<div class="field-item">
		<b class="field-item-title">
			{{ t('integration_openproject', title) }}{{ isRequired ? '*' : '' }}:
		</b>

		<div class="field-item-value">
			{{ valueContent }}
		</div>

		<div v-if="encryptValue && withInspection"
			class="field-item-inspect-btn icon-toggle"
			:class="{ 'toggle-off': inspect }"
			@click="toggleInspection" />
	</div>
</template>
<script>
export default {
	name: 'FieldValue',
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
		valueContent() {
			return (this.encryptValue && !this.inspect)
				? this.encryptedValue
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
	&-inspect-btn {
		cursor: pointer;
		width: 20px;
		height: 20px;
	}
	.toggle-off {
		position: relative;
	}
	.toggle-off::after {
		content: '';
		position: absolute;
		left: 8px;
		top: -1px;
		height: 19px;
		width: 2px;
		background: grey;
		border-radius: 4px;
		border: 1px solid white;
		transform: rotate(130deg);
	}
}

body.theme--dark, body[data-theme-dark] {
	.toggle-off::after {
		border: 1px solid #171717;
	}
}

body[data-theme-dark-highcontrast] {
	.toggle-off::after {
		border: 1px solid #000;
	}
}
</style>
