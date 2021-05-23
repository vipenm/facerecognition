<?php
/**
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
namespace OCA\FaceRecognition\Command;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use OCP\Files\IRootFolder;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IUserManager;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\FaceRecognition\BackgroundJob\BackgroundService;
use OCA\FaceRecognition\BackgroundJob\SyncPhotoService;
use PhotoserverSync\ImageManipulator;

class SyncPhotosCommand extends Command {

	/** @var BackgroundService */
	protected $backgroundService;

	/** @var IUserManager */
	protected $userManager;

	/** @var ImageManipulator */
	protected $image;

	protected $syncPhotoService;

	/**
	 * @param BackgroundService $backgroundService
	 * @param IUserManager $userManager
	 */
	public function __construct(SyncPhotoService $syncPhotoService) {
		parent::__construct();

		$this->syncPhotoService = $syncPhotoService;
		$this->image = new ImageManipulator();

		// $initialList = $image->findAllImages('./images/UploadedToPi');
		// if ($initialList) {
		// foreach ($initialList as $key => $value) {
		// 	$image->moveFiles($value, './images/ReadyForOptimisation');
		// }
		// }

		// // Resize images, upload to S3 and then move to Synced
		// $listOfImages = $image->findAllImages('./images/ReadyForOptimisation');
		// if ($listOfImages) {
		// 	$initialCount = count($listOfImages);
		// 	$list = $image->resizeAllImages($listOfImages);

		// 	if ($list) {
		// 		$finalCount = count($list);
		// 		foreach ($list as $key => $value) {
		// 		$image->moveFiles($value, './images/Synced');
		// 		}
		// 	}
		// }
	}

	protected function configure() {
		$this
			->setName('face:sync_photos')
			->setDescription('Run\'s the photo server sync');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->syncPhotoService->execute();

		return 0;
	}
}
