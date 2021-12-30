<template>
	<div class="empty-projects">
		<div class="empty-icon">
			<img :src="flowSvg" alt="flow">
		</div>
		<div class="title text-center">
			{{ t('integration_openproject', "No OpenProject links yet") }}
		</div>
		<div class="subtitle text-center">
			{{ t('integration_openproject', "To add a link, use the search bar above to find the desired work package.") }}
		</div>
	</div>
</template>

<script>
export default {
	name: 'EmptyContent',
	computed: {
		flowSvg() {
			return require('../../../img/flow.svg')
		},
	},
}
</script>

<style scoped lang="scss">
.empty-projects {
	.text-center {
		text-align: center;
	}
	.empty-icon {
		padding-top: 10vh;
		padding-bottom: 1vh;
		display: flex;
		align-content: center;
		justify-content: center;
		.icon img {
			height: 50px;
			width: 50px;
		}
	}
	.title {
		font-size: 1.2rem;
		line-height: 1.4rem;
		font-weight: 600;
		padding-top: 4px;
		color: #333333;
	}
	.subtitle {
		font-size: .875rem;
		font-weight: 400;
		color: #6d6d6d;
		line-height: 1rem;
		padding: 8px 0;
	}
}
</style>
