<?php
declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019-2020 Matias De lellis <mati86dl@gmail.com>
 *
 * @author Matias De lellis <mati86dl@gmail.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FaceRecognition\Service;

use OCP\Files\IRootFolder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\ITempManager;

use OCP\Files\IHomeStorage;
use OCP\Files\NotFoundException;
use OCP\Files\StorageNotAvailableException;

use OCA\Files_Sharing\External\Storage as SharingExternalStorage;

use OCA\FaceRecognition\Service\SettingsService;

class FileService {

	const NOMEDIA_FILE = ".nomedia";

	const NOIMAGE_FILE = ".noimage";

	const FACERECOGNITION_SETTINGS_FILE = ".facerecognition.json";

	/**  @var string|null */
	private $userId;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var ITempManager */
	private $tempManager;

	/** @var SettingsService */
	private $settingsService;

	public function __construct($userId,
	                            IRootFolder     $rootFolder,
	                            ITempManager    $tempManager,
	                            SettingsService $settingsService)
	{
		$this->userId          = $userId;
		$this->rootFolder      = $rootFolder;
		$this->tempManager     = $tempManager;
		$this->settingsService = $settingsService;
	}

	/**
	 * TODO: Describe exactly when necessary.
	 */
	public function setupFS(string $userId) {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($userId);

		$this->userId = $userId;
	}

	/**
	 * Get root user folder
	 * @param string $userId
	 * @return Folder
	 */
	public function getUserFolder($userId = null): Folder {
		return $this->rootFolder->getUserFolder($this->userId ?? $userId);
	}

	/**
	 * Get a Node from userFolder
	 * @param int $id the id of the Node
	 * @param string $userId
	 * @return Node | null
	 */
	public function getFileById($fileId, $userId = null): ?Node {
		$files = $this->rootFolder->getUserFolder($this->userId ?? $userId)->getById($fileId);
		if (count($files) === 0) {
			return null;
		}

		return $files[0];
	}

	/**
	 * Get a Node from userFolder
	 * @param string $fullpath the fullpath of the Node
	 * @param string $userId
	 * @return Node | null
	 */
	public function getFileByPath($fullpath, $userId = null): ?Node {
		$file = $this->rootFolder->getUserFolder($this->userId ?? $userId)->get($fullpath);
		return $file;
	}

	/**
	 * Checks if this file is located somewhere under .nomedia file and should be therefore ignored.
	 * Or with an .facerecognition.json setting file that disable tha analysis
	 *
	 * @param File $file File to search for
	 * @return bool True if file is located under .nomedia or .facerecognition.json that disabled
	 * analysis, false otherwise
	 */
	public function isUnderNoDetection(Node $node): bool {
		// If we detect .nomedia file anywhere on the path to root folder (id===null), bail out
		$parentNode = $node->getParent();
		while (($parentNode instanceof Folder) && ($parentNode->getId() !== null)) {
			$allowDetection = $this->getDescendantDetection($parentNode);
			if (!$allowDetection)
				return true;
			$parentNode = $parentNode->getParent();
		}
		return false;
	}

	/**
	 * Checks if this folder has .nomedia file an .facerecognition.json setting file that
	 * disable that analysis.
	 *
	 * @param Folder $folder Folder to search for
	 * @return bool true if folder dont have an .nomedia file or .facerecognition.json that disabled
	 * analysis, false otherwise
	 */
	public function getDescendantDetection(Folder $folder): bool {
		try {
			if ($folder->nodeExists(FileService::NOMEDIA_FILE) ||
			    $folder->nodeExists(FileService::NOIMAGE_FILE)) {
				return false;
			}

			if (!$folder->nodeExists(FileService::FACERECOGNITION_SETTINGS_FILE)) {
				return true;
			}

			$file = $folder->get(FileService::FACERECOGNITION_SETTINGS_FILE);
			if (!($file instanceof File)) // Maybe the node exists but it can be a folder.
				return true;

			$settings = json_decode($file->getContent(), true);
			if ($settings === null || !array_key_exists('detection', $settings)) {
				return true;
			}

			if ($settings['detection'] === 'off') {
				return false;
			}
		} catch (StorageNotAvailableException $e) {
			return false;
		}

		return true;
	}

	/**
	 * Set this folder to enable or disable the analysis using the .facerecognition.json file.
	 *
	 * @param Folder $folder Folder to enable/disable for
	 * @return bool true if the change is done. False if failed.
	 */
	public function setDescendantDetection(Folder $folder, bool $detection): bool {
		if ($folder->nodeExists(FileService::FACERECOGNITION_SETTINGS_FILE)) {
			$file = $folder->get(FileService::FACERECOGNITION_SETTINGS_FILE);
			if (!($file instanceof File)) // Maybe the node exists but it can be a folder.
				return false;

			$settings = json_decode($file->getContent(), true);
			if ($settings === null) // Invalid json
				return false;
		}
		else {
			$file = $folder->newFile(FileService::FACERECOGNITION_SETTINGS_FILE);
			$settings = array();
		}

		$settings['detection'] = $detection ? "on" : "off";
		$file->putContent(json_encode($settings));

		return true;
	}

	/**
	 * Returns if the file is inside a native home storage.
	 */
	public function isUserFile(Node $node): bool {
		return (($node->getMountPoint()->getMountType() === '') &&
		        ($node->getStorage()->instanceOfStorage(IHomeStorage::class)));
	}

	/**
	 * Returns if the file is inside a shared mount storage.
	 */
	public function isSharedFile(Node $node): bool {
		return ($node->getMountPoint()->getMountType() === 'shared');
	}

	/**
	 * Returns if the file is inside a external mount storage.
	 */
	public function isExternalFile(Node $node): bool {
		return ($node->getMountPoint()->getMountType() === 'external');
	}

	/**
	 * Returns if the file is inside a group folder storage.
	 */
	public function isGroupFile(Node $node): bool {
		return ($node->getMountPoint()->getMountType() === 'group');
	}

	/**
	 * Returns if the Node is allowed based on preferences.
	 */
	public function isAllowedNode(Node $node): bool {
		if ($this->isUserFile($node)) {
			return true;
		} else if ($this->isSharedFile($node)) {
			return $this->settingsService->getHandleSharedFiles();
		} else if ($this->isExternalFile($node)) {
			return $this->settingsService->getHandleExternalFiles();
		} else if ($this->isGroupFile($node)) {
			return $this->settingsService->getHandleGroupFiles();
		}
		return false;
	}

	/**
	 * Get a path to either the local file or temporary file
	 *
	 * @param File $file
	 * @param int $maxSize maximum size for temporary files
	 * @return string|null
	 */
	public function getLocalFile(File $file, int $maxSize = null): ?string {
		$useTempFile = $file->isEncrypted() || !$file->getStorage()->isLocal();
		if ($useTempFile) {
			$absPath = $this->tempManager->getTemporaryFile();

			$content = $file->fopen('r');
			if ($maxSize !== null) {
				$content = stream_get_contents($content, $maxSize);
			}
			file_put_contents($absPath, $content);

			return $absPath;
		} else {
			$localPath = $file->getStorage()->getLocalFile($file->getInternalPath());
			return ($localPath !== false) ? $localPath : null;
		}
	}

	/**
	 * Return all images from a given folder.
	 *
	 * TODO: It is inefficient since it copies the array recursively.
	 *
	 * @param Folder $folder Folder to get images from
	 * @return array List of all images and folders to continue recursive crawling
	 */
	public function getPicturesFromFolder(Folder $folder, $results = array()) {
		$nodes = $folder->getDirectoryListing();
		foreach ($nodes as $node) {
			if (!$this->isAllowedNode($node)) {
				continue;
			}
			if ($node instanceof Folder && $this->getDescendantDetection($node)) {
				$results = $this->getPicturesFromFolder($node, $results);
			}
			else if ($node instanceof File) {
				if ($this->settingsService->isAllowedMimetype($node->getMimeType())) {
					$results[] = $node;
				}
			}
		}
		return $results;
	}


	/**
	 * Download a file in a temporary folder
	 *
	 * @param string $fileUrl url to download.
	 * @return string temp file downloaded.
	 *
	 * @throws \Exception
	 */
	public function downloaldFile(string $fileUrl): string {
		$tempFolder = $this->tempManager->getTemporaryFolder('/facerecognition/');
		$tempFile = $tempFolder . basename($fileUrl);

		$fp = fopen($tempFile, 'w+');
		if ($fp === false) {
			throw new \Exception('Could not open the file to write: ' . $tempFile);
		}

		$ch = curl_init($fileUrl);
		if ($ch === false) {
			throw new \Exception('Curl error: unable to initialize curl');
		}

		curl_setopt_array($ch, [
			CURLOPT_FILE => $fp,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_USERAGENT => 'Nextcloud Facerecognition Service',
		]);

		if (curl_exec($ch) === false) {
			throw new \Exception('Curl error: ' . curl_error($ch));
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($httpCode !== 200) {
			$statusCodes = [
				400 => 'Bad request',
				401 => 'Unauthorized',
				403 => 'Forbidden',
				404 => 'Not Found',
				500 => 'Internal Server Error',
				502 => 'Bad Gateway',
				503 => 'Service Unavailable',
				504 => 'Gateway Timeout',
			];

			$message = 'Download failed';
			if(isset($statusCodes[$httpCode])) {
				$message .= ' - ' . $statusCodes[$httpCode] . ' (HTTP ' . $httpCode . ')';
			} else {
				$message .= ' - HTTP status code: ' . $httpCode;
			}

			$curlErrorMessage = curl_error($ch);
			if(!empty($curlErrorMessage)) {
				$message .= ' - curl error message: ' . $curlErrorMessage;
			}
			$message .= ' - URL: ' . htmlentities($fileUrl);

			throw new \Exception($message);
		}

		curl_close($ch);
		fclose($fp);

		return $tempFile;
	}

	/**
	 * Uncompressing the file with the bzip2-extension
	 *
	 * @param string $in
	 * @param string $out
	 *
	 * @throws \Exception
	 */
	public function bunzip2(string $inputFile, string $outputFile) {
		if (!file_exists ($inputFile) || !is_readable ($inputFile))
			throw new \Exception('The file ' . $inputFile . ' not exists or is not readable');

		if ((!file_exists($outputFile) && !is_writeable(dirname($outputFile))) ||
		    (file_exists($outputFile) && !is_writable($outputFile)))
			throw new \Exception('The file ' . $outputFile . ' exists or is not writable');

		$in_file = bzopen ($inputFile, "r");
		$out_file = fopen ($outputFile, "w");

		if ($out_file === false)
			throw new \Exception('Could not open the file to write: ' . $outputFile);

		while ($buffer = bzread ($in_file, 4096)) {
			if($buffer === false)
				throw new \Exception('Read problem: ' . bzerrstr($in_file));
			if(bzerrno($in_file) !== 0)
				throw new \Exception('Compression problem: '. bzerrstr($in_file));

			fwrite ($out_file, $buffer, 4096);
		}

		bzclose ($in_file);
		fclose ($out_file);
	}

	/**
	 * Create a temporary file and return the path
	 */
	public function getTemporaryFile(string $postFix = ''): string {
		return $this->tempManager->getTemporaryFile($postFix);
	}

	/**
	 * Remove any temporary file from the service.
	 */
	public function clean() {
		$this->tempManager->clean();
	}

}
