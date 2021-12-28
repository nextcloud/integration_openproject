<template>
	<div class="empty-projects">
		<div class="empty-icon">
			<div class="icon" />
		</div>
		<div class="title">
			No OpenProject links yet
		</div>
		<div class="subtitle">
			To add a link, use the search bar above to find the desired work package.
		</div>
	</div>
</template>

<script>
export default {
	name: 'EmptyContent',
}
</script>

<style scoped lang="scss">
.empty-projects {
	.empty-icon {
		padding-top: 10vh;
		padding-bottom: 1vh;
		display: flex;
		align-content: center;
		justify-content: center;
		.icon {
			height: 50px;
			width: 50px;
			background: brown;
		}
	}
	.title {
		text-align: center;
		font-size: 1.2rem;
		font-weight: 600;
	}
	.subtitle {
		text-align: center;
		font-size: .875rem;
		font-weight: 400;
		color: #6d6d6d;
	}
}
</style>
