<template>
	<h2 class="settings-title">
		<div class="icon-openproject" />
		<span>{{ title }}</span>
	</h2>
</template>

<script>

export default {
	name: 'SettingsTitle',
	computed: {
		title() {
			return t('integration_openproject', 'OpenProject integration')
		},
	},
}
</script>
<style lang="scss" scoped>
@import '../../../css/dashboard.css';

.settings-title {
	display: flex;
	align-items: center;
	.icon-openproject {
		height: 32px;
		background-size: cover;
	}
	span {
		padding-left: 10px;
	}
}

body[data-theme-dark-highcontrast], body[data-theme-dark], body.theme--dark {
	.settings-title {
		.icon-openproject {
			filter: none !important;
		}
	}
}
</style>
