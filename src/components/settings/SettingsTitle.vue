<template>
	<h2 class="settings-title">
		<div class="icon-openproject" />
		<span>{{ title }}</span>
	</h2>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'SettingsTitle',
	computed: {
		title() {
			return t('integration_openproject', 'OpenProject Integration')
		},
	},
}
</script>
<style lang="scss">
@import '../../../css/dashboard.css';

.settings-title {
	display: flex;
	align-items: center;
	.icon-openproject {
		min-height: 32px;
		min-width: 32px;
		background-size: cover;
	}
	span {
		padding-left: 10px;
	}
}
</style>
