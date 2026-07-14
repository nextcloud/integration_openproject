/**
 * SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export function toMatchSerializedSnapshot(element) {
	element = element.replace(/ id="[^"]+"/g, ' id="__ID__"').replace(/ uid="[^"]+"/g, ' uid="__UID__"')
	expect(element).toMatchSnapshot()
}
