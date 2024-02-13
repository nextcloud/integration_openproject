<?php

/**
 * @copyright Copyright (c) 2023 Swikriti Tripathi <swikriti@jankaritech.com>
 *
 * @author Swikriti Tripathi <swikriti@jankaritech.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\OpenProject\Reference;

use OC\Collaboration\Reference\ReferenceManager;
use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\OpenProjectAPIService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use OC_Util;

class WorkPackageReferenceProviderTest extends TestCase {
	protected function setUp(): void {
		if (version_compare(OC_Util::getVersionString(), '26') < 0) {
			$this->markTestSkipped('WorkPackageReferenceProvider is only available from nextcloud 26 so skip the tests on versions below');
		}
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
		$refrenceProvider = new WorkPackageReferenceProvider(
			$configMock,
			$this->createMock(IL10N::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(ReferenceManager::class),
			$this->createMock(OpenProjectAPIService::class),
		'testUser'
		);
		$result = $refrenceProvider->getWorkPackageIdFromUrl($refrenceText);

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
		$service->method('getWorkPackageInfo')->willReturn(['title' => 'title', 'description' => 'description', 'imageUrl' => 'some image url', 'entry' => []]);
		$refrenceProviderMock = $this->getMockBuilder(WorkPackageReferenceProvider::class)->setConstructorArgs([
			$configMock,
			$this->createMock(IL10N::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(ReferenceManager::class),
			$service,
			'testUser'
		])->onlyMethods(['getIsAdminConfigOk', 'matchReference'])->getMock();
		$referenceText = 'https://openproject.org/projects/123/work_packages/1111';
		$refrenceProviderMock->method('matchReference')->with($referenceText)->willReturn(true);
		$refrenceProviderMock->method('getIsAdminConfigOk')->willReturn(true);
		$result = $refrenceProviderMock->resolveReference("https://openproject.org/projects/123/work_packages/1111");
		$this->assertNotNull($result);
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
		$refrenceProviderMock = $this->getMockBuilder(WorkPackageReferenceProvider::class)->setConstructorArgs([
			$configMock,
			$this->createMock(IL10N::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(ReferenceManager::class),
			$service,
			'testUser'
		])->onlyMethods(['getIsAdminConfigOk', 'matchReference'])->getMock();
		$referenceText = 'https://openproject.org/projects/123/work_packages/1111';
		$refrenceProviderMock->method('matchReference')->with($referenceText)->willReturn(true);
		$refrenceProviderMock->method('getIsAdminConfigOk')->willReturn(true);
		$result = $refrenceProviderMock->resolveReference("https://openproject.org/projects/123/work_packages/1111");
		$this->assertNull($result);
	}
}
