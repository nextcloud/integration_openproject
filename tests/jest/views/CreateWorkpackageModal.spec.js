/* jshint esversion: 8 */

import { mount, createLocalVue, shallowMount } from '@vue/test-utils'
import CreateWorkPackageModal from "../../../src/views/CreateWorkPackageModal.vue"
import * as initialState from "@nextcloud/initial-state";
import {STATE} from "../../../src/utils";

const localVue = createLocalVue()

initialState.loadState = jest.fn(() => {
    return {
        openproject_instance_url: "https://openproject.example.com",
    }
})

describe("CreateWorkPackageModal.vue",()=>{
    const createWorkPackageSelector = '.create-workpackage-modal'
    const iframeSelector = '#create-workpackage-iframe'
    let wrapper = null
    beforeEach(async () => {
        wrapper = mountWrapper()
    })
    it("should be displayed if props is not set to true",async () => {
        expect(wrapper.find(createWorkPackageSelector).isVisible()).toBe(true)
        const iframe = wrapper.find(iframeSelector)
        expect(iframe.attributes('src')).toBe("https://openproject.example.com/work_packages/new?iframe=true")
    })

    it("should emit an event with the data of the created work package", async () => {
        const iframe = wrapper.find(iframeSelector)
        await iframe.trigger('load')
        await localVue.nextTick()
        const workpackageData = {
                openProjectEventName: "work_package_creation_success",
                openProjectEventPayload: {
                    workPackageId: '1111'
            }
        }
        const event = new MessageEvent('message', {
            data: workpackageData,
            origin: "https://openproject.example.com",
        })

        window.dispatchEvent(event)
        expect(wrapper.emitted('create-work-package')).toBeTruthy()
        const emittedData = wrapper.emitted('create-work-package')[0][0]
        expect(emittedData).toEqual({
            openProjectEventName: "work_package_creation_success",
            openProjectEventPayload: {
                workPackageId: '1111'
            }
        })
    })

    it("should not emit an event if the opeproject host doesn't match", async () => {
        const iframe = wrapper.find(iframeSelector)
        await iframe.trigger('load')
        await localVue.nextTick()
        const workpackageData = {
            openProjectEventName: "work_package_creation_success",
            openProjectEventPayload: {
                workPackageId: '1111'
            }
        }
        const event = new MessageEvent('message', {
            data: workpackageData,
            origin: "https://openproject.local",
        })
        window.dispatchEvent(event)
        expect(wrapper.emitted('create-work-package')).toBeFalsy()
    })

    it("should emit an event when the modal is closed", async () => {
        wrapper.vm.closeModal()
        expect(wrapper.emitted('close-create-work-package-modal')).toBeTruthy()
    })
})
function mountWrapper() {
    return shallowMount(CreateWorkPackageModal, {
        localVue,
        attachTo: document.body,
        mocks: {
            t: (app, msg) => msg
        },
        data: () => ({
            state: STATE.LOADING,
            openprojectUrl: "https://openproject.example.com"
        }),
        propsData: {
            showModal: true
        },
    })
}
