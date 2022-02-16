<template>
	<h2>
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

.icon-openproject {
	background-size: cover;
	height: 23px;
	width: 23px;
}

h2 {
	display: flex;
	align-items: center;
	span {
		padding-left: 10px;
	}
}
</style>
