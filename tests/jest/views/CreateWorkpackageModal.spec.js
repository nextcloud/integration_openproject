/* jshint esversion: 8 */

import { createLocalVue, mount } from '@vue/test-utils'
import CreateWorkPackageModal from '../../../src/views/CreateWorkPackageModal.vue'
import axios from '@nextcloud/axios'
import availableProjectsResponse from '../fixtures/openprojectAvailableProjectResponse.json'
import availableProjectsResponseAfterSearch from '../fixtures/openprojectAvailableProjectResponseAfterSearch.json'
import availableProjectsOption from '../fixtures/availableProjectOptions.json'
import workpackageFormValidationProjectSelected from '../fixtures/workpackageFormValidationProjectSelectedResponse.json'
import workpackageFormValidationTypeChanged from '../fixtures/workpackageFormValidationTypeChanged.json'
import availableProjectAssignees from '../fixtures/availableProjectAssigneesResponse.json'
import workpackageCreatedResponse from '../fixtures/workPackageSuccessfulCreationResponse.json'
import requiredTypeResponse from '../fixtures/formValidationResponseRequiredType.json'

const localVue = createLocalVue()

jest.mock('@nextcloud/initial-state', () => {
	const originalModule = jest.requireActual('@nextcloud/initial-state')
	return {
		__esModule: true,
		...originalModule,
		default: jest.fn(),
		loadState: jest.fn(() => {
			return {
				openproject_instance_url: 'https://openproject.example.com',
			}
		}),
	}
})

describe('CreateWorkPackageModal.vue', () => {
	const createWorkPackageSelector = '.create-workpackage-modal'
	const projectSelectSelector = '[data-test-id="available-projects"]'
	const firstProjectSelectorSelector = '[data-test-id="available-projects"] [role="listbox"] > li'
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
			expect(axiosSpy).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/projects', {})
			await wrapper.find(projectInputField).setValue(' ')
			expect(wrapper.find(projectSelectSelector)).toMatchSnapshot()
			axiosSpy.mockRestore()
			jest.clearAllMocks()
		})

		describe('search projects with query', () => {
			let axiosSpy, inputField
			beforeEach(async () => {
				axiosSpy = jest.spyOn(axios, 'get')
					.mockImplementationOnce(() => Promise.resolve({
						status: 200,
						data: availableProjectsResponse,
					}))
				wrapper = mountWrapper(true)
				expect(wrapper.find(createWorkPackageSelector).isVisible()).toBe(true)
				expect(axiosSpy).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/projects', {})
				inputField = wrapper.find(projectInputField)
				await inputField.setValue('Sc')
			})
			it('should send a search query request when searched project is not found', async () => {
				await inputField.setValue('Scw')
				await new Promise(resolve => setTimeout(resolve, 500))
				expect(axiosSpy).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/projects',
					{
						params: {
							searchQuery: 'Scw',
						},
					},
				)
			})

			it('should send a search query request when searched project is not found', async () => {
				await inputField.setValue('Scw')
				await new Promise(resolve => setTimeout(resolve, 500))
				expect(wrapper.vm.isFetchingProjectsFromOpenProjectWithQuery).toBe(true)
				expect(axiosSpy).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/projects',
					{
						params: {
							searchQuery: 'Scw',
						},
					},
				)
			})
			it('should show "No matching work projects found!" when the searched project is not found', async () => {
				const axiosSpyWithSearchQuery = jest.spyOn(axios, 'get')
					.mockImplementationOnce(() => Promise.resolve({
						status: 200,
						data: [],
					}))
				await inputField.setValue('Scw')
				expect(wrapper.vm.isFetchingProjectsFromOpenProjectWithQuery).toBe(true)
				await new Promise(resolve => setTimeout(resolve, 500))
				expect(axiosSpyWithSearchQuery).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/projects',
					{
						params: {
							searchQuery: 'Scw',
						},
					},
				)
				const searchResult = wrapper.find(firstProjectSelectorSelector)
				expect(searchResult.text()).toBe('No matching work projects found!')
			})

			it('should fetch searched when project is not found in initial list', async () => {
				const axiosSpyWithSearchQuery = jest.spyOn(axios, 'get')
					.mockImplementationOnce(() => Promise.resolve({
						status: 200,
						data: availableProjectsResponseAfterSearch,
					}))
				const inputField = wrapper.find(projectInputField)
				await inputField.setValue('se')
				await new Promise(resolve => setTimeout(resolve, 500))
				expect(axiosSpyWithSearchQuery).toHaveBeenCalledWith('http://localhost/apps/integration_openproject/projects',
					{
						params: {
							searchQuery: 'se',
						},
					},
				)
				const searchResult = wrapper.find(firstProjectSelectorSelector)
				expect(searchResult.text()).toBe('searchedProject')
			})

			it('should always initially fetched projects when nothing searched', async () => {
				await inputField.setValue(' ')
				const searchResult = wrapper.findAll(projectOptionsSelector)
				// the initially fetched available project includes 7 openproject projects
				expect(searchResult.length).toBe(7)
			})
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
					subject: '',
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
					subject: '',
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
					subject: '',
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
				status: {
					label: 'New',
				},
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
					subject: '',
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
			wrapper = mountWrapper(true, {
				status: {
					label: 'New',
				},
			})
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

		it('should not change description template once edited (changed)', async () => {
			jest.spyOn(axios, 'get')
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: availableProjectsResponse,
				}))
			const axiosSpyWorkPackageValidationForm = jest.spyOn(axios, 'post')
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
						href: '/api/v3/types/1',
						title: 'Task',
					},
					label: 'Task',
				},
				status: {
					self: {
						href: '/api/v3/statuses/1',
						title: 'New',
					},
					label: 'New',
				},
				subject: 'This is a workpackage',
				description: {
					format: 'markdown',
					raw: 'New task template',
					html: '',
				},
				previousDescriptionTemplate: 'New task template',
				isDescriptionTemplateChanged: false,
			})
			// change the description template
			await wrapper.setData({
				description: {
					format: 'markdown',
					raw: 'New task template has been changed',
					html: '',
				},
			})
			// now switching to another project or validating form again should not change the value of the description since it was changed or edited
			wrapper.vm.validateWorkPackageForm(2, true, true)
			expect(axiosSpyWorkPackageValidationForm).toHaveBeenCalledTimes(1)
			expect(assigneeAxiosSpy).toHaveBeenCalledTimes(1)
			expect(wrapper.vm.description.raw).toBe('New task template has been changed')
		})

		it('should change description when template is not edited or (changed)', async () => {
			jest.spyOn(axios, 'get')
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: availableProjectsResponse,
				}))
			const axiosSpyWorkPackageValidationForm = jest.spyOn(axios, 'post')
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
						href: '/api/v3/types/1',
						title: 'Task',
					},
					label: 'Task',
				},
				status: {
					self: {
						href: '/api/v3/statuses/1',
						title: 'New',
					},
					label: 'New',
				},
				subject: 'This is a workpackage',
				description: {
					format: 'markdown',
					raw: 'Previous template',
					html: '',
				},
				previousDescriptionTemplate: 'Previous template',
				isDescriptionTemplateChanged: false,
			})
			wrapper.vm.validateWorkPackageForm(2, false, true)
			expect(axiosSpyWorkPackageValidationForm).toHaveBeenCalledTimes(1)
			expect(assigneeAxiosSpy).toHaveBeenCalledTimes(1)
			await wrapper.vm.$nextTick()
			expect(wrapper.vm.description.raw).toBe('Default New task template')
		})

		it('should empty the type if that type is not available for the selected project', async () => {
			jest.spyOn(axios, 'get')
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: availableProjectsResponse,
				}))
			const axiosSpyWorkPackageValidationForm = jest.spyOn(axios, 'post')
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
						href: '/api/v3/types/8',
						title: 'TypeNotInResponse',
					},
					label: 'TypeNotInResponse',
				},
			})
			// changing project
			wrapper.vm.validateWorkPackageForm(2, true, true)
			expect(axiosSpyWorkPackageValidationForm).toHaveBeenCalledTimes(1)
			expect(assigneeAxiosSpy).toHaveBeenCalledTimes(1)
			await wrapper.vm.$nextTick()
			expect(wrapper.vm.type.label).toBe('')
		})

		it('should empty the status if this status is not available for the selected type', async () => {
			jest.spyOn(axios, 'get')
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: availableProjectsResponse,
				}))
			const axiosSpyWorkPackageValidationForm = jest.spyOn(axios, 'post')
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
						href: '/api/v3/types/7',
						title: 'Bug',
					},
					label: 'Bug',
				},
				status: {
					self: {
						href: '/api/v3/status/8',
						title: 'StatusNotInResponse',
					},
					label: 'StatusNotInResponse',
				},
			})
			// changing project type
			wrapper.vm.validateWorkPackageForm(4, false, true)
			expect(axiosSpyWorkPackageValidationForm).toHaveBeenCalledTimes(1)
			expect(assigneeAxiosSpy).toHaveBeenCalledTimes(1)
			await wrapper.vm.$nextTick()
			expect(wrapper.vm.status.label).toBe('')
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
			type: {
				label: 'Task',
			},
			status: {
				label: 'New',
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

	it('should reset all values when the modal is closed', async () => {
		wrapper.vm.closeModal()
		expect(wrapper.vm.project.label).toBe(null)
		expect(wrapper.vm.type.label).toBe('')
		expect(wrapper.vm.status.label).toBe('')
		expect(wrapper.vm.subject).toBe('')
		expect(wrapper.vm.assignee.label).toBe(null)
	})

	it('should display an error when the project status is empty', async () => {
		jest.spyOn(axios, 'get')
			.mockImplementationOnce(() => Promise.resolve({
				status: 200,
				data: availableProjectsResponse,
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
			status: {
				self: {
					href: '/api/v3/statuses/1',
					title: 'New',
				},
				label: '',
			},
			subject: 'This is a workpackage',
			projectId: 2,
			openProjectUrl: 'https://openproject.example.com',
		})
		await wrapper.find(createWorkpackageButtonSelector).trigger('click')
		await wrapper.vm.$nextTick()
		await wrapper.vm.$nextTick()
		const error = wrapper.find(validationErrorSelector)
		expect(error.isVisible()).toBe(true)
		expect(error.text()).toBe('Status is not set to one of the allowed values.')
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
