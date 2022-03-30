<template>
	<div class="workpackage">
		<div class="row">
			<div class="row__status"
				:style="{'background-color':workpackage.statusCol}">
				<div class="row__status__title">
					{{ workpackage.statusTitle }}
				</div>
			</div>
			<div class="row__workpackage">
				#{{ workpackage.id }} - {{ workpackage.project }}
			</div>
		</div>
		<div class="row">
			<div class="row__subject">
				<span class="row__subject__type" :style="{'color':workpackage.typeCol}">
					{{ workpackage.typeTitle }}
				</span>
				{{ workpackage.subject }}
			</div>
		</div>
		<div class="row">
			<div v-if="workpackage.assignee" class="row__assignee">
				<div class="row__assignee__avatar">
					<Avatar class="item-avatar"
						:size="23"
						:url="workpackage.picture"
						:user="workpackage.assignee"
						:display-name="workpackage.assignee" />
				</div>
				<div class="row__assignee__assignee">
					{{ workpackage.assignee }}
				</div>
			</div>
		</div>
		<slot name = "seperator"/>
	</div>
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar'

export default {
	name: 'WorkPackage',
	components: {
		Avatar,
	},
	props: {
		workpackage: {
			type: Object,
			required: true,
		},
	},
}
</script>

<style scoped lang="scss">
.workpackage {
	width: 100%;
	padding: 8px;

	.row {
		display: flex;
		padding: 3px;
		flex-wrap: wrap;

		&__status {
			padding: 5px 10px;
			width: fit-content;
			height: 20px;
			justify-content: center;
			display: flex;
			align-items: center;
			text-align: center;
			font-size: 0.75rem;
			border-radius: 2px;
			margin-right: 4px;

			&__title {
				mix-blend-mode: multiply;
				font-size: 0.75rem;
				line-height: 14px;
				text-align: center;
			}
		}

		&__workpackage {
			color: #878787;
			font-size: 0.8rem;
			height: 20px;
			line-height: 20px;
		}

		&__subject {
			font-size: 0.875rem;
			text-align: justify;
			white-space: normal;
			text-justify: inter-word;
			display: -webkit-box;
			-webkit-line-clamp: 2;
			-webkit-box-orient: vertical;
			overflow: hidden;
			text-overflow: ellipsis;

			&__type {
				font-size: 0.75rem;
				font-weight: bold;
				text-transform: uppercase;
				margin-right: 4px;
			}
		}

		&__assignee {
			display: flex;
			flex-direction: row;
			padding: 5px;

			&__assignee {
				font-size: 0.81rem;
				color: #878787;
				text-align: center;
				padding-left: 5px;
			}
		}
	}
}
</style>
