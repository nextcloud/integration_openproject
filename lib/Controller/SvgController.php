<?php

namespace OCA\OpenProject\Controller;

use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\IRequest;
use OC\Files\Filesystem;
use OCP\App\AppPathNotFoundException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
class SvgController extends Controller {

	/**
	 * @var IAppManager
	 */
	private $appManager;

	public function __construct(string $appName,
								IRequest $request,
								IAppManager $appManager){
		parent::__construct($appName, $request);
//		$this->config = $config;
//		$this->shareManager = $shareManager;
//		$this->userManager = $userManager;
//		$this->trans = $trans;
//		$this->billMapper = $billMapper;
//		$this->projectService = $projectService;
//		$this->activityManager = $activityManager;
//		$this->dbconnection = $dbconnection;
//		$this->root = $root;
//		$this->userId = $userId;
//		$this->initialStateService = $initialStateService;
		$this->appManager = $appManager;
	}



	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $fileName
	 * @param string $color
	 */
	public function getSvgFromApp(string $fileName, string $color = 'ffffff') {
		try {
			$appPath = $this->appManager->getAppPath(Application::APP_ID);
		} catch (AppPathNotFoundException $e) {
			return new NotFoundResponse();
		}

		$path = $appPath . "/img/$fileName.svg";
		return $this->getSvg($path, $color, $fileName);
	}

	private function getSvg(string $path, string $color, string $fileName) {
		if (!Filesystem::isValidPath($path)) {
			return new NotFoundResponse();
		}

		if (!file_exists($path)) {
			return new NotFoundResponse();
		}

		$svg = file_get_contents($path);

		if ($svg === null) {
			return new NotFoundResponse();
		}

		$svg = $this->colorizeSvg($svg, $color);

		$response = new DataDisplayResponse($svg, Http::STATUS_OK, ['Content-Type' => 'image/svg+xml']);

		// Set cache control
		$ttl = 31536000;
		$response->cacheFor($ttl);

		return $svg;
	}

	public function colorizeSvg(string $svg, string $color): string {
		if (!preg_match('/^[0-9a-f]{3,6}$/i', $color)) {
			// Prevent not-sane colors from being written into the SVG
			$color = '000';
		}

		// add fill (fill is not present on black elements)
		$fillRe = '/<((circle|rect|path)((?!fill)[a-z0-9 =".\-#():;,])+)\/>/mi';
		$svg = preg_replace($fillRe, '<$1 fill="#' . $color . '"/>', $svg);

		// replace any fill or stroke colors
		$svg = preg_replace('/stroke="#([a-z0-9]{3,6})"/mi', 'stroke="#' . $color . '"', $svg);
		$svg = preg_replace('/fill="#([a-z0-9]{3,6})"/mi', 'fill="#' . $color . '"', $svg);
		return $svg;
	}

}
