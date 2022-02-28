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
					#{{ workpackage.id }}
				</div>
				<div class="row__project">
					{{ workpackage.project }}
				</div>
			</div>
			<div class="row">
				<div class="row__type" :style="{'color':workpackage.typeCol}">
					{{ workpackage.typeTitle }}
				</div>
				<div class="row__subject">
					{{ workpackage.subject }}
				</div>
			</div>
			<div class="row">
				<div v-if="workpackage.assignee" class="assignee">
					<div class="assignee__avatar">
						<Avatar
							class="item-avatar"
							:size="25"
							:url="workpackage.picture"
							:user="workpackage.assignee"
							:display-name="workpackage.assignee" />
					</div>
					<div class="assignee__assignee">
						{{ workpackage.assignee }}
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>

export default {
	name: 'WorkPackage',
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
	flex-direction: column;
	justify-content: space-between;

	&__workPackage {
		margin-top: 8px;
		display: table;
		flex-direction: row;
		justify-content: space-between;
		align-items: center;

		.row {
			display: table-row;
			justify-content: space-between;

			&__project {
				display: table-cell;
				padding: 6px 6px 6px 12px;
				color: #878787;
			}

			&__id {
				display: table-cell;
				padding: 6px 6px 6px 12px;
				color: #878787;
			}

			&__type {
				display: table-cell;
				padding: 6px;
				text-transform: uppercase;
				font-size: 0.87rem;
			}

			&__status {
				display: table-cell;
				margin: 6px;
				width: 90px;
				height: 25px;
				text-align: center;
				font-size: 0.75rem;
				border-radius: 3px;

				&__title {
					mix-blend-mode: multiply;
				}
			}
		}

		.assignee {
			display: flex;
			flex-direction: row;
			justify-content: space-between;
			flex-wrap: wrap;
			position: absolute;
			right: 0px;

			&__avatar {
				padding: 6px;
			}

			&__assignee {
				padding: 6px;
				padding-right: 12px;
				font-size: 0.81rem;
				color: #0096FF;
				text-align: center;
			}

			&__subject {
				font-weight: bold;
				font-size: 14px;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
		}
	}
}

.empty-projects {
	.text-center {
		text-align: center;
	}

	.empty-icon {
		padding: 1vh 0;

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

.fill-height {
	height: 100%;
}
</style>
