<?php
/**
 * Nextcloud - OpenProject
 *
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Artur Neumann 2021
 */

namespace OCA\OpenProject\Service;

use PHPUnit\Framework\TestCase;

class OpenProjectAPIServiceTest extends TestCase {

	public function urlsDataProvider(): array {
		return [
			['http://127.0.0.1', true],
			['https://127.0.0.1', true],
			['https://127.0.0.1:443', true],
			['http://127.0.0.1:8080', true],
			['http://localhost', true],
			['http://localhost', true],
			['http://www.openproject.com', true],
			['http://www.openproject.it:3000', true],
			['https://www.openproject.it:8081', true],
			['https://www.openproject.it:8081/home', true],
			['ftp://localhost', false],
			['http://loca lhost', false],
			['https://loca lhost', false],
			['http://openproject.dev ', false],
			['http:/openproject.dev', false],
			['http//openproject.dev', false],
			['openproject.dev', false],
			['://openproject.dev', false],
		];
	}

	/**
	 * @dataProvider urlsDataProvider
	 */
	public function testValidateOpenProjectURL(string $url, bool $expected) {
		$result = OpenProjectAPIService::validateOpenProjectURL($url);
		$this->assertSame($expected, $result);
	}
}
