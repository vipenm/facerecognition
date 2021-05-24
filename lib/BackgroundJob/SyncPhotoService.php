<?php
/**
 * @copyright Copyright (c) 2017, Matias De lellis <mati86dl@gmail.com>
 * @copyright Copyright (c) 2018, Branko Kokanovic <branko@kokanovic.org>
 *
 * @author Branko Kokanovic <branko@kokanovic.org>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\FaceRecognition\BackgroundJob;

use EnchantDictionary;
use OCP\IUser;

use OCA\FaceRecognition\AppInfo\Application;
use OCA\FaceRecognition\Helper\Requirements;

use OCA\FaceRecognition\BackgroundJob\Tasks\AddMissingImagesTask;
use OCA\FaceRecognition\BackgroundJob\Tasks\CheckCronTask;
use OCA\FaceRecognition\BackgroundJob\Tasks\CheckRequirementsTask;
use OCA\FaceRecognition\BackgroundJob\Tasks\CreateClustersTask;
use OCA\FaceRecognition\BackgroundJob\Tasks\DisabledUserRemovalTask;
use OCA\FaceRecognition\BackgroundJob\Tasks\EnumerateImagesMissingFacesTask;
use OCA\FaceRecognition\BackgroundJob\Tasks\ImageProcessingTask;
use OCA\FaceRecognition\BackgroundJob\Tasks\LockTask;
use OCA\FaceRecognition\BackgroundJob\Tasks\StaleImagesRemovalTask;
use OCA\FaceRecognition\BackgroundJob\Tasks\UnlockTask;
use OCA\FaceRecognition\Controller\PersonController;
use Symfony\Component\Console\Output\OutputInterface;
use PhotoserverSync\ImageManipulator;
use GuzzleHttp\Client;
use PhotoserverSync\Config\EnvironmentVariables;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/**
 * Background service. Both command and cron job are calling this service for long-running background operations.
 * Background processing for face recognition is comprised of several steps, called tasks. Each task is independent,
 * idempotent, DI-aware logic unit that yields. Since tasks are non-preemptive, they should yield from time to time,
 * so we son't end up working for more than given timeout.
 *
 * Tasks can be seen as normal sequential functions, but they are easier to work with,
 * reason about them and test them independently. Other than that, they are really glorified functions.
 */
class SyncPhotoService {

    /** @var Application $application */
	private $application;

	/** @var FaceRecognitionContext */
	private $context;

    /** @var ImageManipulator */
	private $image;

	private $client;

	public function __construct(Application $application, FaceRecognitionContext $context) {
		$this->application = $application;
		$this->context = $context;
        $this->image = new ImageManipulator();
		$this->client = new Client(['https://nextcloud.vipenmahay.com']);
		$this->config = new EnvironmentVariables();
	}

    public function execute()
    {
		$res = $this->client->get('persons', [
			'auth' => [
					$this->config->getUsername,
					$this->config->getPassword,
				]
		  ]);
		echo $res->getBody();

		// $res = $this->personController->index();
		// echo json_encode($res);
		// die;
		// get list of persons using PersonController::index and save names as array
		// get filenames of the persons using above array and passing it into PersonController::find
		// pass json returned into resizeAllImages() and compare file names - then add name to AWS metadata

        // $initialList = $this->image->findAllImages(realpath(".") . "/data/Nextcloud/sneek/files/");
		// if ($initialList) {
        //     $this->image->resizeAllImages($initialList);
		// }

		// move each image into new folder 'Synced'
    }

}