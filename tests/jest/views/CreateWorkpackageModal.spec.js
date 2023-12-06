/* jshint esversion: 8 */

import { createLocalVue, mount } from '@vue/test-utils'
import CreateWorkPackageModal from '../../../src/views/CreateWorkPackageModal.vue'
import * as initialState from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import availableProjectsResponse from '../fixtures/openprojectAvailableProjectResponse.json'
import availableProjectsOption from '../fixtures/availableProjectOptions.json'
import workpackageFormValidationProjectSelected from '../fixtures/workpackageFormValidationProjectSelectedResponse.json'
import workpackageFormValidationTypeChanged from '../fixtures/workpackageFormValidationTypeChanged.json'
import availableProjectAssignees from '../fixtures/availableProjectAssigneesResponse.json'
import workpackageCreatedResponse from '../fixtures/workPackageSuccessfulCreationResponse.json'
import requiredTypeResponse from '../fixtures/formValidationResponseRequiredType.json'

const localVue = createLocalVue()

// eslint-disable-next-line no-import-assign,import/namespace
initialState.loadState = jest.fn(() => {
	return {
		openproject_instance_url: 'https://openproject.example.com',
	}
})

describe('CreateWorkPackageModal.vue', () => {
	const createWorkPackageSelector = '.create-workpackage-modal'
	const projectSelectSelector = '[data-test-id="available-projects"]'
	const statusSelectSelector = '[data-test-id="available-statuses"]'
	const typeSelectSelector = '[data-test-id="available-types"]'
	const assigneesSelectSelector = '[data-test-id="available-assignees"]'
	const projectInputField = '#createWorkPackageInput'
	const projectOptionsSelector = '[data-test-id="available-projects"] .vs__dropdown-menu .vs__dropdown-option'
	const typeOptionsSelector = '[data-test-id="available-types"] .vs__dropdown-menu .vs__dropdown-option'
	const typeInputFieldSelector = '#createWorkPackageTypeInput'
	const statusInputFieldSelector = '#createWorkPackageStatusInput'
	const assigneeInputFieldSelector = '#createWorkPackageAssigneeInput'
	const createWorkpackageButtonSelector = '.create-workpackage-form--button--create'
	const validationErrorSelector = '.validation-error'
	const validationErrorProjectSelector = '.multiple-error-project'
	const validationErrorSubjectSelector = '.multiple-error-subject'
	const validationErrorTypeSelector = '.type-error'
	let wrapper = null

	beforeEach(() => {
		jest.spyOn(document, 'getElementById').mockReturnValue({
			blur: jest.fn(), // Mock the blur function
		})
	})
	afterEach(async () => {
		wrapper.destroy()
		jest.restoreAllMocks()
	})

	describe('workpackage creation form', () => {
		it('should display available projects in the project dropdown', async () => {
			const axiosSpy = jest.spyOn(axios, 'get')
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: availableProjectsResponse,
				}))
			wrapper = mountWrapper(true)
			expect(wrapper.find(createWorkPackageSelector).isVisible()).toBe(true)
			expect(axiosSpy).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/projects')
			await wrapper.find(projectInputField).setValue(' ')
			expect(wrapper.find(projectSelectSelector)).toMatchSnapshot()
			axiosSpy.mockRestore()
			jest.clearAllMocks()
		})
		it('should set the available types, status and assignee when a project is selected', async () => {
			const formValidationBody = {
				body: {
					_links: {
						assignee: {
							href: null,
							title: null,
						},
						project: {
							href: '/api/v3/projects/2',
							title: 'Scrum project',
						},
						status: {
							href: '/api/v3/statuses/1',
							title: 'New',
						},
						type: {
							href: '/api/v3/types/1',
							title: 'Task',
						},
					},
					description: {
						format: 'markdown',
						html: '',
						raw: '',
					},
					subject: null,
				},
			}

			jest.spyOn(axios, 'get')
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: availableProjectsResponse,
				}))
			const axiosSpy = jest.spyOn(axios, 'post')
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: workpackageFormValidationProjectSelected,
				}))
			const assigneeAxiosSpy = jest.spyOn(axios, 'get')
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: availableProjectAssignees,
				}))
			wrapper = mountWrapper(true, {
				noDropAvailableProjectDropDown: false,
			})
			wrapper.vm.mappedProjects = jest.fn(() => {
				return availableProjectsOption
			})
			const inputField = wrapper.find(projectInputField)
			await inputField.setValue('Scrum')
			await wrapper.find(projectOptionsSelector).trigger('click')
			await wrapper.vm.$nextTick()
			await wrapper.vm.$nextTick()
			expect(axiosSpy).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/projects/2/work-packages/form', formValidationBody)
			expect(assigneeAxiosSpy).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/projects/2/available-assignees')
			await wrapper.vm.$nextTick()
			await wrapper.find(typeInputFieldSelector).setValue(' ')
			await wrapper.vm.$nextTick()
			expect(wrapper.find(typeSelectSelector)).toMatchSnapshot()
			await wrapper.find(statusInputFieldSelector).setValue(' ')
			await wrapper.vm.$nextTick()
			expect(wrapper.find(statusSelectSelector)).toMatchSnapshot()
			await wrapper.find(assigneeInputFieldSelector).setValue(' ')
			await wrapper.vm.$nextTick()
			expect(wrapper.find(assigneesSelectSelector)).toMatchSnapshot()
			axiosSpy.mockRestore()
			assigneeAxiosSpy.mockRestore()
			jest.clearAllMocks()
		})

		it('should send the form validation request when type is changed', async () => {
			const formValidationBody = {
				body: {
					_links: {
						assignee: {
							href: null,
							title: null,
						},
						project: {
							href: '/api/v3/projects/2',
							title: 'Scrum project',
						},
						status: {
							href: '/api/v3/statuses/1',
							title: 'New',
						},
						type: {
							href: '/api/v3/types/2',
							title: 'Milestone',
						},
					},
					description: {
						format: 'markdown',
						html: '',
						raw: '',
					},
					subject: null,
				},
			}
			const allowedTypes = [
				{
					self: {
						href: '/api/v3/types/1',
						title: 'Task',
					},
					label: 'Task',
				},
				{
					self: {
						href: '/api/v3/types/2',
						title: 'Milestone',
					},
					label: 'Milestone',
				},
				{
					self: {
						href: '/api/v3/types/3',
						title: 'Phase',
					},
					label: 'Phase',
				},
				{
					self: {
						href: '/api/v3/types/5',
						title: 'Epic',
					},
					label: 'Epic',
				},
				{
					self: {
						href: '/api/v3/types/6',
						title: 'User story',
					},
					label: 'User story',
				},
				{
					self: {
						href: '/api/v3/types/7',
						title: 'Bug',
					},
					label: 'Bug',
				},
			]
			const availableStatusBefore = [
				{
					self: {
						href: '/api/v3/statuses/1',
						title: 'New',
					},
					label: 'New',
				},
				{
					self: {
						href: '/api/v3/statuses/7',
						title: 'In progress',
					},
					label: 'In progress',
				},
				{
					self: {
						href: '/api/v3/statuses/12',
						title: 'Closed',
					},
					label: 'Closed',
				},
				{
					self: {
						href: '/api/v3/statuses/13',
						title: 'On hold',
					},
					label: 'On hold',
				},
				{
					self: {
						href: '/api/v3/statuses/14',
						title: 'Rejected',
					},
					label: 'Rejected',
				},
			]
			const axiosSpy = jest.spyOn(axios, 'post')
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: workpackageFormValidationTypeChanged,
				}))
			wrapper = mountWrapper(true, {
				allowedStatues: availableStatusBefore,
				allowedTypes,
				project: {
					self: {
						href: '/api/v3/projects/2',
						title: 'Scrum project',
					},
					label: '',
					children: [],
				},
				projectId: 2,
			})
			wrapper.vm.mappedProjects = jest.fn(() => {
				return availableProjectsOption
			})
			await wrapper.find(typeInputFieldSelector).setValue('Milest')
			await wrapper.find(typeOptionsSelector).trigger('click')
			await wrapper.vm.$nextTick()
			expect(axiosSpy).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/projects/2/work-packages/form', formValidationBody)
			// one thing to note is the statues in snapshot should not match the statuses defined in variable availableStatusBefore
			await wrapper.find(statusInputFieldSelector).setValue(' ')
			expect(wrapper.find(statusSelectSelector)).toMatchSnapshot()
		})

		it.each([
			['should show error if the subject is empty',
				{
					project: {
						self: {
							href: '/api/v3/projects/2',
							title: 'Scrum project',
						},
						label: '',
						children: [],
					},
					projectId: 2,
					subject: null,
					data: "{\"_type\":\"Error\",\"errorIdentifier\":\"urn:openproject-org:api:v3:errors:PropertyConstraintViolation\",\"message\":\"Subject can't be blank.\",\"_embedded\":{\"details\":{\"attribute\":\"subject\"}}}",
					errorMessage: "Subject can't be blank.",
				}],
			['should show error if the project is empty',
				{
					project: {
						self: {
							href: null,
							title: null,
						},
						label: '',
						children: [],
					},
					projectId: null,
					subject: 'this is a workpackage',
					data: "{\"_type\":\"Error\",\"errorIdentifier\":\"urn:openproject-org:api:v3:errors:PropertyConstraintViolation\",\"message\":\"Project can't be blank.\",\"_embedded\":{\"details\":{\"attribute\":\"project\"}}}",
					errorMessage: "Project can't be blank.",
				}],
		])('%s', async (name, expectedErrorDetails) => {
			const createWorkpackageBody = {
				body: {
					_links: {
						type: {
							href: '/api/v3/types/1',
							title: 'Task',
						},
						status: {
							href: '/api/v3/statuses/1',
							title: 'New',
						},
						assignee: {
							href: null,
							title: null,
						},
						project: expectedErrorDetails.project.self,
					},
					subject: expectedErrorDetails.subject,
					description: {
						format: 'markdown',
						raw: '',
						html: '',
					},
				},
			}
			jest.spyOn(axios, 'get')
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: availableProjectsResponse,
				}))
			const axiosSpy = jest.spyOn(axios, 'post')
				.mockImplementationOnce(() => Promise.resolve({
					status: 422,
					data: expectedErrorDetails.data,
				}))
			wrapper = mountWrapper(true, {
				project: expectedErrorDetails.project,
				projectId: expectedErrorDetails.projectId,
				subject: expectedErrorDetails.subject,
			})
			await wrapper.find(createWorkpackageButtonSelector).trigger('click')
			await wrapper.vm.$nextTick()
			expect(axiosSpy).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/create/work-packages', createWorkpackageBody)
			const error = wrapper.find(validationErrorSelector)
			expect(error.isVisible()).toBe(true)
			expect(error.text()).toBe(expectedErrorDetails.errorMessage)
		})

		it('should show error if both project and subject are empty', async () => {
			const createWorkpackageBody = {
				body: {
					_links: {
						type: {
							href: '/api/v3/types/1',
							title: 'Task',
						},
						status: {
							href: '/api/v3/statuses/1',
							title: 'New',
						},
						assignee: {
							href: null,
							title: null,
						},
						project: {
							href: null,
							title: null,
						},
					},
					subject: null,
					description: {
						format: 'markdown',
						raw: '',
						html: '',
					},
				},
			}
			jest.spyOn(axios, 'get')
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: availableProjectsResponse,
				}))
			const axiosSpy = jest.spyOn(axios, 'post')
				.mockImplementationOnce(() => Promise.resolve({
					status: 422,
					data: "{\"_type\":\"Error\",\"errorIdentifier\":\"urn:openproject-org:api:v3:errors:MultipleErrors\",\"message\":\"Multiple field constraints have been violated.\",\"_embedded\":{\"errors\":[{\"_type\":\"Error\",\"errorIdentifier\":\"urn:openproject-org:api:v3:errors:PropertyConstraintViolation\",\"message\":\"Subject can't be blank.\",\"_embedded\":{\"details\":{\"attribute\":\"subject\"}}},{\"_type\":\"Error\",\"errorIdentifier\":\"urn:openproject-org:api:v3:errors:PropertyConstraintViolation\",\"message\":\"Project can't be blank.\",\"_embedded\":{\"details\":{\"attribute\":\"project\"}}}]}}",
				}))
			wrapper = mountWrapper(true)
			await wrapper.find(createWorkpackageButtonSelector).trigger('click')
			await wrapper.vm.$nextTick()
			expect(axiosSpy).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/create/work-packages', createWorkpackageBody)
			const projectError = wrapper.find(validationErrorProjectSelector)
			expect(projectError.isVisible()).toBe(true)
			expect(projectError.text()).toBe("Project can't be blank.")
			const subjectError = wrapper.find(validationErrorSubjectSelector)
			expect(subjectError.isVisible()).toBe(true)
			expect(subjectError.text()).toBe("Subject can't be blank.")
		})
	})

	it('should emit an event if work package creation is successful', async () => {
		const createWorkPackageBody = {
			body: {
				_links: {
					type: {
						href: '/api/v3/types/1',
						title: 'Task',
					},
					status: {
						href: '/api/v3/statuses/1',
						title: 'New',
					},
					assignee: {
						href: '/api/v3/users/15',
						title: 'Second Admin',
					},
					project: {
						href: '/api/v3/projects/2',
						title: 'Scrum project',
					},
				},
				subject: 'This is a workpackage',
				description: {
					format: 'markdown',
					raw: 'description for workpackage',
					html: '',
				},
			},
		}
		jest.spyOn(axios, 'get')
			.mockImplementationOnce(() => Promise.resolve({
				status: 200,
				data: availableProjectsResponse,
			}))
		const axiosSpy = jest.spyOn(axios, 'post')
			.mockImplementationOnce(() => Promise.resolve({
				status: 201,
				data: workpackageCreatedResponse,
			}))
		wrapper = mountWrapper(true, {
			project: {
				self: {
					href: '/api/v3/projects/2',
					title: 'Scrum project',
				},
				label: 'Scrum project',
				children: [],
			},
			assignee: {
				self: {
					href: '/api/v3/users/15',
					title: 'Second Admin',
				},
				label: 'Second Admin',
			},
			subject: 'This is a workpackage',
			description: {
				format: 'markdown',
				raw: 'description for workpackage',
				html: '',
			},
		})
		const emitSpy = jest.spyOn(wrapper.vm, '$emit')
		await wrapper.find(createWorkpackageButtonSelector).trigger('click')
		await wrapper.vm.$nextTick()
		expect(axiosSpy).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/create/work-packages', createWorkPackageBody)
		expect(emitSpy).toHaveBeenCalledWith('create-work-package', {
			openProjectEventName: 'work_package_creation_success',
			openProjectEventPayload: workpackageCreatedResponse,
		})
	})

	it('should display error when there is a required custom field', async () => {
		const bodyFormValidation = {
			body: {
				_links: {
					type: {
						href: '/api/v3/types/9',
						title: 'Required CF',
					},
					status: {
						href: '/api/v3/statuses/1',
						title: 'New',
					},
					assignee: {
						href: null,
						title: null,
					},
					project: {
						href: '/api/v3/projects/4',
						title: '[dev] Large',
					},
				},
				subject: 'This is a workpackage',
				description: {
					format: 'markdown',
					raw: '',
					html: '',
				},
			},
		}
		const allowedTypes = [
			{
				self: {
					href: '/api/v3/types/9',
					title: 'Required CF',
				},
				label: 'Required CF',
			},
		]
		jest.spyOn(axios, 'get')
			.mockImplementationOnce(() => Promise.resolve({
				status: 200,
				data: availableProjectsResponse,
			}))
		const axiosSpy = jest.spyOn(axios, 'post')
			.mockImplementationOnce(() => Promise.resolve({
				status: 200,
				data: requiredTypeResponse,
			}))

		wrapper = mountWrapper(true, {
			project: {
				self: {
					href: '/api/v3/projects/4',
					title: '[dev] Large',
				},
				label: '[dev] Large',
				children: [],
			},
			type: {
				self: {
					href: '/api/v3/types/9',
					title: 'Required CF',
				},
				label: 'Required CF',
			},
			subject: 'This is a workpackage',
			allowedTypes,
			projectId: 2,
			openProjectUrl: 'https://openproject.example.com',
		})
		await wrapper.find(typeInputFieldSelector).setValue('Required')
		await wrapper.find(typeOptionsSelector).trigger('click')
		await wrapper.vm.$nextTick()
		expect(axiosSpy).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/projects/2/work-packages/form', bodyFormValidation)
		const typeError = wrapper.find(validationErrorTypeSelector)
		expect(typeError.isVisible()).toBe(true)
		expect(typeError.text()).toBe('This type has mandatory fields which cannot be filled here. Please, create work packages of this type directly in {htmlLink}.')
	})

	it('should emit an event when the modal is closed', async () => {
		wrapper.vm.closeModal()
		expect(wrapper.emitted('close-create-work-package-modal')).toBeTruthy()
	})
})
function mountWrapper(showModal, data) {
	return mount(CreateWorkPackageModal, {
		localVue,
		mocks: {
			t: (app, msg) => msg,
			generateUrl() {
				return '/'
			},
		},
		data: () => ({
			...data,
		}),
		stubs: {
			NcModal: true,
		},
		propsData: {
			showModal,
		},
	})
}
