<template>
	<div class="workpackage">
		<div class="workpackage__workPackage">
			<div class="row">
				<div class="row__status"
					:style="{'background-color':workpackage.statusCol}">
					<div class="row__status__title">
						{{ workpackage.statusTitle }}
					</div>
				</div>
				<div class="row__id">
					#{{ workpackage.id }} -
				</div>
				<div class="row__project">
					{{ workpackage.project }}
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
						<Avatar
							class="item-avatar"
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
		</div>
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
	display: flex;
	flex-direction: row;
	width: 100%;

	&__workPackage {
		display: flex;
		flex-direction: column;
		padding: 5px;

		.row {
			display: flex;
			flex-direction: row;
			padding: 3px;
			flex-wrap: wrap;

			&__status {
				padding: 5px;
				width: 98px;
				height: 20px;
				justify-content: center;
				display: flex;
				align-items: center;
				text-align: center;
				font-size: 0.75rem;
				border-radius: 2px;

				&__title {
					mix-blend-mode: multiply;
					font-size: 0.75rem;
					line-height: 14px;
					text-align: center;
				}
			}

			&__id {
				padding: 5px;
				color: #878787;
				font-size: 0.8rem;
				height: 20px;
				line-height: 14px;
			}

			&__project {
				padding: 5px 5px 5px 1px;
				color: #878787;
				height: 20px;
				line-height: 14px;
				font-size: 0.8rem;
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
}
</style>
