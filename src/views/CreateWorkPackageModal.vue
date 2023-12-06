<template>
	<NcModal v-if="openModal"
		class="create-workpackage-modal"
		:can-close="true"
		:out-transition="true"
		@close="closeModal">
		<div>
			<h2 class="create-workpackage-modal--title">
				{{ t('integration_openproject', 'Create and link a new work package') }}
			</h2>
			<div class="create-workpackage-form-wrapper">
				<div class="create-workpackage-form--label">
					{{ t('integration_openproject', 'Project *') }}
				</div>
				<NcSelect ref="createWorkPackageProjectInput"
					class="create-workpackage-form--select"
					input-id="createWorkPackageInput"
					data-test-id="available-projects"
					:placeholder="t('integration_openproject', 'Select a project')"
					:options="mappedNodes"
					:filterable="true"
					:close-on-select="true"
					:clear-search-on-blur="() => false"
					:append-to-body="false"
					:value="project.label"
					:no-drop="noDropAvailableProjectDropDown"
					@option:selected="onSelectProject">
					<template #option="{ label, relation, counter }">
						<span v-if="relation === 'child'" :style="{paddingLeft: counter + 'em' }" />
						<span>{{ label }}</span>
					</template>
					<template #no-options>
						{{ t('integration_openproject', 'Please link a project to this nextcloud storage') }}
					</template>
				</NcSelect>
				<p v-if="error.error && error.attribute === 'project'" class="validation-error">
					{{ error.message }}
				</p>
				<p v-else-if="error.error && error.multipleErrors.project" class="validation-error multiple-error-project">
					{{ error.multipleErrors.project }}
				</p>
				<div class="create-workpackage-form--label">
					{{ t('integration_openproject', 'Subject *') }}
				</div>
				<NcInputField :value="subject"
					class="create-workpackage-form--subject"
					input-class="workpackage-subject"
					:placeholder="t('integration_openproject', 'Work package subject')"
					:class="{'subject-error': error}"
					type="text"
					@update:value="onSubjectChange" />
				<p v-if="error.error && error.attribute === 'subject'" class="validation-error">
					{{ error.message }}
				</p>
				<p v-else-if="error.error && error.multipleErrors.subject" class="validation-error multiple-error-subject">
					{{ error.multipleErrors.subject }}
				</p>
				<div class="create-workpackage-form--type-status-container">
					<div class="create-workpackage-form--type">
						<div class="create-workpackage-form--label">
							{{ t('integration_openproject', 'Type *') }}
						</div>
						<NcSelect class="create-workpackage-form--select"
							data-test-id="available-types"
							input-id="createWorkPackageTypeInput"
							:options="allowedTypes"
							:filterable="true"
							:close-on-select="true"
							:clear-search-on-blur="() => false"
							:append-to-body="false"
							:value="type.label"
							@option:selected="onSelectType">
							<template #option="option">
								{{ option.label }}
							</template>
							<template #no-options>
								{{ t('integration_openproject', 'Please select a project') }}
							</template>
						</NcSelect>
						<p v-if="customTypeError" class="validation-error type-error" v-html="sanitizedRequiredCustomTypeValidationErrorMessage" /> <!-- eslint-disable-line vue/no-v-html -->
					</div>
					<div class="create-workpackage-form--status">
						<div class="create-workpackage-form--label">
							{{ t('integration_openproject', 'Status *') }}
						</div>
						<NcSelect class="create-workpackage-form--select"
							data-test-id="available-statuses"
							input-id="createWorkPackageStatusInput"
							:options="allowedStatues"
							:filterable="true"
							:close-on-select="true"
							:clear-search-on-blur="() => false"
							:append-to-body="false"
							:value="status.label"
							@option:selected="onSelectStatus">
							<template #option="option">
								{{ option.label }}
							</template>
							<template #no-options>
								{{ t('integration_openproject', 'Please select a project') }}
							</template>
						</NcSelect>
					</div>
				</div>
				<div class="create-workpackage-form--label">
					{{ t('integration_openproject', 'Assignee') }}
				</div>
				<NcSelect class="create-workpackage-form--select"
					data-test-id="available-assignees"
					input-id="createWorkPackageAssigneeInput"
					:placeholder="t('integration_openproject', 'Select a user or group')"
					:options="availableAssignees"
					:filterable="true"
					:close-on-select="true"
					:clear-search-on-blur="() => false"
					:append-to-body="false"
					:value="assignee.label"
					@option:selected="onSelectAssignee">
					<template #option="option">
						{{ option.label }}
					</template>
					<template #no-options>
						{{ t('integration_openproject', 'Please select a project') }}
					</template>
				</NcSelect>
				<div class="create-workpackage-form--label">
					{{ t('integration_openproject', 'Description') }}
				</div>
				<textarea v-model="description.raw" class="create-workpackage-form--description" :placeholder="t('integration_openproject', 'Work package description')" />
				<div class="create-workpackage-form--button">
					<NcButton class="create-workpackage-form--button--cancel" @click="closeModal">
						{{ t("integration_openproject", "Cancel") }}
					</NcButton>
					<NcButton class="create-workpackage-form--button--create" :disabled="error.error || customTypeError" @click="createWorkpackage">
						{{ t("integration_openproject", "Create") }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>
<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcInputField from '@nextcloud/vue/dist/Components/NcInputField.js'
import dompurify from 'dompurify'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'

const DEFAULT_TYPE_VALUE = {
	self: {
		href: '/api/v3/types/1',
		title: 'Task',
	},
	label: 'Task',
}

const DEFAULT_STATUS_VALUE = {
	self: {
		href: '/api/v3/statuses/1',
		title: 'New',
	},
	label: 'New',
}

const DEFAULT_ASSIGNEE_VALUE = {
	self: {
		href: null,
		title: null,
	},
	label: null,
}

const DEFAULT_PROJECT_VALUE = {
	self: {
		href: null,
		title: null,
	},
	label: null,
	children: [],
}

const DEFAULT_DESCRIPTION_VALUE = {
	format: 'markdown',
	raw: '',
	html: '',
}

const DEFAULT_ERROR_VALUE = {
	error: false,
	attribute: null,
	message: null,
	multipleErrors: {
		project: null,
		subject: null,
	},
}

export default {
	name: 'CreateWorkPackageModal',
	components: {
		NcButton,
		NcSelect,
		NcModal,
		NcInputField,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
	},
	data: () => ({
		openProjectUrl: loadState('integration_openproject', 'openproject-url'),
		availableProjects: [],
		allowedTypes: [],
		allowedStatues: [],
		availableAssignees: [],
		project: DEFAULT_PROJECT_VALUE,
		subject: null,
		type: DEFAULT_TYPE_VALUE,
		status: DEFAULT_STATUS_VALUE,
		assignee: DEFAULT_ASSIGNEE_VALUE,
		projectId: null,
		description: DEFAULT_DESCRIPTION_VALUE,
		error: DEFAULT_ERROR_VALUE,
		customTypeError: false,
		// when the modal opens the dropdown for selecting project gains focus automatically
		// this is a workaround to prevent that by setting the dropdown to noDrop at the beginning
		noDropAvailableProjectDropDown: true,
	}),
	computed: {
		openModal() {
			this.searchForProjects()
			return this.showModal
		},
		getBodyForRequest() {
			return {
				body: {
					_links: {
						type: this.type.self,
						status: this.status.self,
						assignee: this.assignee.self,
						project: this.project.self,
					},
					subject: this.subject,
					description: this.description,
				},
			}
		},
		mappedNodes() {
			return this.mappedProjects()
		},
		sanitizedRequiredCustomTypeValidationErrorMessage() {
			// get the last number from the href i.e `/api/v3/types/1`, which is the type id
			const typeID = parseInt(this.type.self.href.match(/\d+$/)[0], 10)
			const createWorkpackageFullUrl = this.openProjectUrl.replace(/\/+$/, '') + '/projects/' + this.project.identifier + `/work_packages/new?type=${typeID}`
			const htmlLink = `<a class="openProjectUrl" href=${createWorkpackageFullUrl} target="_blank" title="OpenProject">OpenProject</a>`
			const message = t(
				'integration_openproject',
				'This type has mandatory fields which cannot be filled here. Please, create work packages of this type directly in {htmlLink}.',
				{ htmlLink },
				null,
				{ escape: false, sanitize: false }
			)
			return dompurify.sanitize(message, { ADD_ATTR: ['target'] })
		},
	},
	methods: {
		mappedProjects() {
			const mappedNodes = []
			let counter = 0
			this.availableProjects.sort((projectOne, projectTwo) => projectOne.identifier.localeCompare(projectTwo.identifier))
			function mapNode(node, relation) {
				mappedNodes.push({ ...node, relation, counter })
				if (node.children && node.children.length > 0) {
					counter += 2 // Increment the counter for child elements
					node.children.forEach(child => {
						mapNode(child, 'child')
					})
					counter -= 2 // Decrement the counter after processing children to go back to the parent's counter value
				}
			}
			this.availableProjects.forEach(node => {
				mapNode(node, 'parent')
			})
			// when the modal opens the dropdown for selecting project gains focus automatically
			// this is a workaround to prevent that by bluring the focus and the enabling the dropDown that was
			// disabled initially in data
			if (this.$refs?.createWorkPackageProjectInput) {
				document.getElementById(`${this.$refs?.createWorkPackageProjectInput?.inputId}`).blur()
				this.noDropAvailableProjectDropDown = false
			}
			return mappedNodes
		},
		closeModal() {
			this.$emit('close-create-work-package-modal')
			this.resetData()
		},
		resetData() {
			this.allowedTypes = []
			this.allowedStatues = []
			this.availableAssignees = []
			this.project = DEFAULT_PROJECT_VALUE
			this.type = DEFAULT_TYPE_VALUE
			this.status = DEFAULT_STATUS_VALUE
			this.assignee = DEFAULT_ASSIGNEE_VALUE
			this.description = DEFAULT_DESCRIPTION_VALUE
			this.subject = null
			this.projectId = null
			this.error = DEFAULT_ERROR_VALUE
			this.availableProjects = []
			this.noDropAvailableProjectDropDown = true
		},
		async searchForProjects() {
			const url = generateUrl('/apps/integration_openproject/projects')
			try {
				const response = await axios.get(url)
				await this.processProjects(response.data)
			} catch (e) {
				console.error('Couldn\'t fetch openproject projects')
			}
		},
		async processProjects(projects) {
			this.availableProjects = []
			for (const index in projects) {
				const project = {}
				project.label = projects[index].name
				project.id = projects[index].id
				project.identifier = projects[index].identifier
				project.self = projects[index]._links.self
				project.parent = projects[index]._links.parent
				project.children = []
				this.availableProjects[index] = project
			}
			this.buildNestedStructure()
		},
		buildNestedStructure() {
			const childId = []

			for (const projectId in this.availableProjects) {
				const project = this.availableProjects[projectId]
				if (project.parent.href !== null) {
					const parentProjectId = project.parent.href.match(/\/(\d+)$/)[1]
					if (project.children.length <= 0) {
						this.availableProjects[parentProjectId].children.push(project)
						childId.push(project.id)
					}
				}
			}
			for (let i = 0; i < childId.length; i++) {
				delete this.availableProjects[childId[i]]
			}
			this.availableProjects = this.availableProjects.filter(item => item !== undefined)
		},
		onSubjectChange(value) {
			if (this.error.error) {
				this.error = DEFAULT_ERROR_VALUE
			}
			this.subject = value
		},
		async onSelectProject(selectedOption) {
			this.project = selectedOption
			this.projectId = selectedOption.id
			if (this.error.error) {
				this.error = DEFAULT_ERROR_VALUE
			}
			// set the allowed values for both type and status when project selection changes
			await this.validateWorkPackageForm(this.projectId, true, true)
		},
		async onSelectType(selectedOption) {
			if (this.customTypeError) {
				this.customTypeError = false
			}
			this.type = selectedOption
			// set the allowed values for status when type selection changes
			await this.validateWorkPackageForm(this.projectId, false, true)
		},
		onSelectStatus(selectedOption) {
			this.status = selectedOption
		},
		onSelectAssignee(selectedOption) {
			this.assignee = selectedOption
		},
		async validateWorkPackageForm(id, setAllowedType = false, setAllowedStatus = false) {
			const url = generateUrl(`/apps/integration_openproject/projects/${id}/work-packages/form`)
			const body = this.getBodyForRequest
			try {
				const response = await axios.post(url, body)
				if (setAllowedType && setAllowedStatus) {
					// when project is changed set all the values to default and
					// set new allowed values for types, status, assignee to display as the option in dropdown
					this.type = DEFAULT_TYPE_VALUE
					this.status = DEFAULT_STATUS_VALUE
					this.assignee = DEFAULT_ASSIGNEE_VALUE
					this.allowedTypes = []
					this.allowedStatues = []
					this.allowedTypes.push(...this.setAllowedValues(response.data.schema.type._embedded.allowedValues))
					this.allowedStatues.push(...this.setAllowedValues(response.data.schema.status._embedded.allowedValues))
					await this.setAvailableAssigneesForProject(id)
				} else if (setAllowedStatus) {
					// when only type changes then reset status only
					this.status = DEFAULT_STATUS_VALUE
					this.allowedStatues = []
					this.allowedStatues.push(...this.setAllowedValues(response.data.schema.status._embedded.allowedValues))
				}
				if (response.data.validationErrors) {
					const validationErrors = response.data.validationErrors
					for (const errors in validationErrors) {
						if (errors.startsWith('customField')) {
							this.customTypeError = true
							return
						}
						if (errors.startsWith('type')) {
							if (validationErrors[errors].message === 'Type is not set to one of the allowed values.') {
								this.type = DEFAULT_TYPE_VALUE
							}
						}
					}
				} else {
					// if there's no validation error then only set type and status
					this.type.self = response.data.payload._links.type
					this.type.label = response.data.payload._links.type.title
					this.status.self = response.data.payload._links.status
					this.status.label = response.data.payload._links.status.title
				}
				// set the value to form from payload of the forms endpoint
				this.subject = response.data.payload.subject
				this.description = response.data.payload.description
			} catch (e) {
				console.error('Form validation failed')
			}
		},
		setAllowedValues(allowedValuesList) {
			const allowedValues = []
			for (const index in allowedValuesList) {
				const values = {}
				values.self = allowedValuesList[index]._links.self
				// set label to title as the NC select looks for label for filtering options
				values.label = values.self.title
				allowedValues.push(values)
			}
			return allowedValues
		},
		async setAvailableAssigneesForProject(projectId) {
			this.availableAssignees = []
			let response
			const url = generateUrl(`/apps/integration_openproject/projects/${projectId}/available-assignees`)
			try {
				response = await axios.get(url)
			} catch (e) {
				console.error('Cannot fetch available assignees')
			}
			const assignees = response.data
			for (const index in assignees) {
				const assignee = {}
				assignee.self = assignees[index]._links.self
				// set label to title as the NC select looks for label for filtering options
				assignee.label = assignee.self.title
				this.availableAssignees.push(assignee)
			}
		},
		async createWorkpackage() {
			const url = generateUrl('/apps/integration_openproject/create/work-packages')
			const body = this.getBodyForRequest
			let response = null
			const eventData = {}
			try {
				response = await axios.post(url, body)
			} catch (e) {
				response = e.response
			}
			if (response.status === 201) {
				eventData.openProjectEventName = 'work_package_creation_success'
				eventData.openProjectEventPayload = response.data
				this.$emit('create-work-package', eventData)
				this.resetData()
			} else {
				if (response.status === 422) {
					const error = JSON.parse(response.data)
					let attribute = null
					let message = null
					const multipleErrors = {}
					if (error.errorIdentifier === 'urn:openproject-org:api:v3:errors:PropertyConstraintViolation') {
						attribute = error._embedded.details.attribute
						message = error.message
					} else if (error.errorIdentifier === 'urn:openproject-org:api:v3:errors:MultipleErrors') {
						const errors = error._embedded.errors
						for (const err of errors) {
							if ((err._embedded.details.attribute === 'subject')) {
								multipleErrors.subject = err.message
							} else if ((err._embedded.details.attribute === 'project')) {
								multipleErrors.project = err.message
							}
						}
					}
					this.error = {
						error: true,
						multipleErrors,
						attribute,
						message,
					}
				} else {
					eventData.openProjectEventName = 'work_package_creation_cancellation'
					this.$emit('create-work-package', eventData)
					this.resetData()
				}
			}
		},
	},
}
</script>
<style lang="scss">
.create-workpackage-modal {
	&--title {
		margin: 10px;
		text-align: left;
		padding: 10px;
		font-weight: 700;
		font-size: 1.125rem;
		line-height: 1.5rem;
	}
}

.options {
	padding-left: 0;
}

.parent {
	padding-left: 10px;
}

.create-workpackage-form-wrapper {
	margin: 20px;
}

.select {
	width: 100% !important;
}

.validation-error {
	margin-top: 10px;
	color: var(--color-error) !important;
}

.type-error {
	align-self: flex-start;
}

.subject-error {
	border-color: var(--color-error) !important;
}

.create-workpackage-form {
	top: 157px;
	left: 434px;
	gap: 16px;

	&--label {
		margin: 15px 0;
		font-weight: 400;
		letter-spacing: 0;
		text-align: left;
		font-size: .875rem;
		line-height: 1rem;
	}
	&--select {
		margin: 15px 0;
		width: 100%;
		position: sticky;
		position: -webkit-sticky; /* Safari */
	}
	&--subject {
		width: 100%;
	}
	&--description {
		width: 100%;
		height: 178px;
	}
	&--type-status-container {
		display: flex;
		flex-flow: row wrap;
		justify-content: space-between;
		width: 100%;
	}
	&--type {
		width: 50%;
		display: flex;
		flex-flow: row wrap;
	}
	&--button {
		width: 100%;
		display: flex;
		flex-direction: row;
		justify-content: flex-end;
		gap: 16px;
		margin: 30px 0;

		&--create {
			background-color: var(--color-primary-element) !important;
			color: var(--color-primary-element-text) !important;
		}
		&--cancel {
			background-color: var(--color-background-dark) !important;
			color: var(--color-main-text) !important;
			border: 1px solid var(--color-border-dark) !important;
		}
	}
}

.workpackage-subject {
	min-height: 44px !important;
}

.openProjectUrl {
	color: var(--color-primary) !important;
}

.create-workpackage-form--button---create:hover, .create-workpackage-form--button--cancel:hover {
	background-color: var(--color-background-hover) !important;
}

@media (max-width: 800px) {
	.create-workpackage-form--status, .create-workpackage-form--type {
		flex: 100%;
	}
}
</style>
