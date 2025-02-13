<?php

/**
 * SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenProject\Reference;

use OC\Collaboration\Reference\ReferenceManager;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WorkPackageReferenceProviderTest extends TestCase {
	/**
	 *
	 * @param array<string> $onlyMethods
	 * @param MockObject|null $configMock
	 * @param IL10N|null $iL10N
	 * @param IURLGenerator|null $iURLGenerator
	 * @param ReferenceManager|null $refrenceMangager
	 * @param OpenProjectAPIService|null $openProjectAPIService
	 * @param string|null $userId
	 *
	 * @return WorkPackageReferenceProvider|MockObject
	 */
	public function getWorkReferenceProviderMock(
		array $onlyMethods = ['request'],
		$configMock = null,
		$iL10N = null,
		$iURLGenerator = null,
		$refrenceMangager = null,
		$openProjectAPIService = null,
		$userId = null
	): WorkPackageReferenceProvider|MockObject {
		if ($configMock === null) {
			$configMock = $this->createMock(IConfig::class);
		}
		if ($iL10N === null) {
			$iL10N = $this->createMock(IL10N::class);
		}
		if ($iURLGenerator === null) {
			$iURLGenerator = $this->createMock(IURLGenerator::class);
		}
		if ($refrenceMangager === null) {
			$refrenceMangager = $this->createMock(ReferenceManager::class);
		}
		if ($openProjectAPIService === null) {
			$openProjectAPIService = $this->getMockBuilder(OpenProjectAPIService::class)->disableOriginalConstructor()->getMock();
		}
		if ($userId === null) {
			$userId = 'testUser';
		}
		return $this->getMockBuilder(WorkPackageReferenceProvider::class)
			->setConstructorArgs(
				[
					$configMock,
					$iL10N,
					$iURLGenerator,
					$refrenceMangager,
					$openProjectAPIService,
					$userId
				])
			->onlyMethods($onlyMethods)
			->getMock();
	}

	/**
	 * @return array<mixed>
	 */
	public function getWorkPackageIdFromUrlDataProvider() {
		return[
			['https://openproject.org/projects/123/work_packages/1111'],
			['https://openproject.org/wp/1111'],
			['https://openproject.org/projects/123/work_packages/details/1111'],
			['https://openproject.org/work_packages/details/1111'],
			['https://openproject.org/work_packages/1111'],
			['https://openproject.org/work_packages/details/1111/overview'],
			['https://openproject.org/projects/wielands-playground/boards/290/details/1111/overview'],
			['https://openproject.org/projects/wielands-playground/calendars/new/details/1111/overview?cdate=2023-08-01&cview=dayGridMonth'],
			['https://openproject.org/projects/wielands-playground/calendars/519/details/1111/overview?cdate=2023-08-01&cview=dayGridMonth'],
			['https://openproject.org/projects/blabla/bcf/details/1111/overview?'],
			['https://openproject.org/notifications/details/1111/activity'],
			['https://openproject.org/projects/openproject/team_planners/12454/details/1111/overview?cdate=2023-08-14&cview=resourceTimelineWorkWeek']
		];
	}
	/**
	 * @dataProvider getWorkPackageIdFromUrlDataProvider
	 * @param string $refrenceText
	 * @return void
	 */
	public function testGetWorkPackageIdFromUrl(string $refrenceText) {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getAppValue')->with(Application::APP_ID, 'openproject_instance_url')
			->willReturn("https://openproject.org");
		$workPackageRefrenceProviderMock = $this->getWorkReferenceProviderMock([], $configMock);
		$result = $workPackageRefrenceProviderMock->getWorkPackageIdFromUrl($refrenceText);
		$this->assertSame(1111, $result);
	}

	/**
	 * @return void
	 */
	public function testResolveReferenceWithExistentWorkPackage() {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getAppValue')->with(Application::APP_ID, 'openproject_instance_url')
			->willReturn("https://openproject.org");
		$service = $this->getMockBuilder(OpenProjectAPIService::class)->disableOriginalConstructor()->getMock();
		$service->method('getWorkPackageInfo')->willReturn(['title' => 'title', 'description' => 'description', 'imageUrl' => 'http://imageURL', 'entry' => []]);
		$workPackageRefrenceProviderMock = $this->getWorkReferenceProviderMock(
			['getIsAdminConfigOk', 'matchReference'],
			$configMock,
			null,
			null,
			null,
			$service,
			null
		);
		$referenceText = 'https://openproject.org/projects/123/work_packages/1111';
		$workPackageRefrenceProviderMock->method('getIsAdminConfigOk')->willReturn(true);
		$workPackageRefrenceProviderMock->method('matchReference')->with($referenceText)->willReturn(true);
		$result = $workPackageRefrenceProviderMock->resolveReference($referenceText);
		$this->assertSame('title', $result->getTitle());
		$this->assertSame('description', $result->getDescription());
		$this->assertSame('http://imageURL', $result->getImageUrl());
		$this->assertSame([], $result->getRichObject());
	}

	/**
	 * @return void
	 */
	public function testResolveReferenceWithNonExistentWorkPackage() {
		$configMock = $this->getMockBuilder(IConfig::class)->getMock();
		$configMock->method('getAppValue')->with(Application::APP_ID, 'openproject_instance_url')
			->willReturn("https://openproject.org");
		$service = $this->getMockBuilder(OpenProjectAPIService::class)->disableOriginalConstructor()->getMock();
		$service->method('getWorkPackageInfo')->willReturn(null);
		$workPackageRefrenceProviderMock = $this->getWorkReferenceProviderMock(
			['getIsAdminConfigOk', 'matchReference'],
			$configMock,
			null,
			null,
			null,
			$service,
			null
		);
		$referenceText = 'https://openproject.org/projects/123/work_packages/1111';
		$workPackageRefrenceProviderMock->method('getIsAdminConfigOk')->willReturn(true);
		$workPackageRefrenceProviderMock->method('matchReference')->with($referenceText)->willReturn(true);
		$result = $workPackageRefrenceProviderMock->resolveReference($referenceText);
		$this->assertNull($result);
	}
}
