<template>
	<div class="text-input">
		<div class="text-input-label">
			{{ translate(labelText) }}
		</div>
		<input :id="id"
			:value="value"
			:type="type"
			class="full-width"
			:placeholder="translate(placeHolder)"
			@input="$emit('input', $event.target.value)"
			@change="$emit('change', $event.target.value)"
			@focus="$emit('focus', $event)"
			@blur="$emit('blur', $event)">
		<div v-if="errorMessage || hintText" class="messages">
			<div v-if="hintText"
				class="text-input-hint"
				v-html="translate(hintText)" />
			<div v-if="errorMessage" class="text-input-error">
				{{ translate(errorMessage) }}
			</div>
		</div>
	</div>
</template>
<script>
export default {
	name: 'TextInput',
	props: {
		value: {
			required: true,
		},
		id: {
			type: String,
			required: true,
		},
		type: {
			type: String,
			default: 'text',
		},
		placeHolder: {
			default: '',
			type: String,
		},
		label: {
			required: true,
			type: String,
		},
		hintText: {
			default: false,
			type: [String, Boolean],
		},
		errorMessage: {
			default: false,
			type: [String, Boolean],
		},
		isRequired: {
			default: false,
			type: Boolean,
		},
	},
	computed: {
		labelText() {
			if (this.isRequired) {
				return `${this.label} *`
			} else return this.label
		},
	},
	methods: {
		translate(text) {
			return t('integration_openproject', text)
		},
	},
}
</script>
<style lang="scss" scoped>
.text-input {
	.full-width {
		width: 100%;
	}
	&-label {
		font-weight: 700;
		font-size: .875rem;
		line-height: 1.25rem;
		color: #333333;
	}
	&-hint {
		font-weight: 400;
		font-size: .75rem;
		line-height: 1rem;
	}
}

body[data-theme-dark-highcontrast], body[data-theme-dark], body.theme--dark {
	.text-input {
		&-label {
			color: #FFFFFF;
		}
	}
}
</style>
<style>
.text-input .link {
	color: #1a67a3 !important;
	font-style: italic;
}
</style>
