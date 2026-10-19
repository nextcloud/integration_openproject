<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\AuditLog;

/**
 * Operations the OpenProject integration writes to the admin audit log, in the form `app.entity.action`
 *
 * The values are matched by log consumers (filters, alert rules, SIEM imports),
 * so changing one is a breaking change for them. Case names may be renamed.
 */
enum Operation: string {
	case IntegrationConfigured = 'integration_openproject.integration.configured';
	case IntegrationReset = 'integration_openproject.integration.reset';
	case OauthClientCreated = 'integration_openproject.oauth_client.created';
	case AppPasswordCreated = 'integration_openproject.app_password.created';
	case AppPasswordDeleted = 'integration_openproject.app_password.deleted';
	case ProjectFolderCreated = 'integration_openproject.project_folder.created';
	case ProjectFolderDeactivated = 'integration_openproject.project_folder.deactivated';
	case TermsOfServiceSigned = 'integration_openproject.terms_of_service.signed';

	/**
	 * Short description of when the operation is logged, used for the documentation
	 */
	public function description(): string {
		return match ($this) {
			self::IntegrationConfigured => 'An admin changed the OpenProject integration settings, such as the OpenProject URL or the OpenProject OAuth client',
			self::IntegrationReset => 'An admin reset the OpenProject integration, which disconnects all users from OpenProject',
			self::OauthClientCreated => 'An admin created or replaced the Nextcloud OAuth client used by OpenProject',
			self::AppPasswordCreated => 'An app password was created or replaced for the OpenProject system user',
			self::AppPasswordDeleted => 'The app password of the OpenProject system user was deleted',
			self::ProjectFolderCreated => 'The project folder setup created the OpenProject system user and groups, made the user their group admin and created the project groupfolder',
			self::ProjectFolderDeactivated => 'An admin deactivated the project folder setup',
			self::TermsOfServiceSigned => 'Terms of service were signed on behalf of the OpenProject system user, by an admin, during the project folder setup or automatically when the terms change',
		};
	}
}
