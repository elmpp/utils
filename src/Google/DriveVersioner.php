<?php

namespace Partridge\Utils\Google;

use Partridge\Utils\Google\DriveVersionerException;

/**
 * Nice to haves:
 *  - List all versions for a file
 * 
 */
class DriveVersioner {

  const MIME_DIR = 'application/vnd.google-apps.folder';

  /**
   * @var \Google_Service_Drive
   */
  protected $client;
  /**
   * The Drive ID for the root directory. Get this from the UI
   * @var string
   */
  protected $driveRootId;

  public function construct(\Google_Service_Drive $driveClient, $driveRootId) {
    $this->client = $client;
    $this->driveRootId = $driveRootId;
  }

  /**
   * Uploads a given file to Google Drive with versioning
   * 
   * The file's location will be based on:
   *  - in its own directory beneath a well-known directory
   *  - its directory name will be supplied
   * 
   * Each file version will have the following metadata:
   *  - date. This is supplied and not defined by its modified/uploaded date etc.
   * 
   *
   * @param String $fileLoc   Path to the file to be versioned
   * @param String $ns        The version namespace
   * @throws DriveVersionerException
   */
  public function version(String $fileLoc, String $ns): \Google_Service_Drive_DriveFile {
    if (!is_readable($fileLoc)) {
      throw new DriveVersionerException(DriveVersionerException::VERSIONABLE_FILE_NOT_READABLE);
    }
    if (!$driveDir = $this->queryForDirectory($ns)) {
      $driveDir = $this->createDirectory($ns);
    }
  }

  protected function queryForDirectory(String $ns): ?\Google_Service_Drive_DriveFile {
    return $this->client->files->listFiles([
      'q' => "'{$this->driveRootId}' in parents and name = '${ns}' and mimeType='" . self::MIME_DIR . "'" // http://bit.ly/2Bu19ro
    ]);
  }

  /**
   * @throws DriveVersionerException
   * @param String $ns
   * @return \Google_Service_Drive_DriveFile
   */
  protected function createDirectory(String $ns): \Google_Service_Drive_DriveFile {
    $dir = new \Google_Service_Drive_DriveFile;
    $dir->mimeType = self::MIME_DIR;
    $dir->name = $ns;
    $dir->parents = $this->driveRootId;
    try {
      return $this->client->files->create($dir);
    }
    catch (\Google_Exception $e) {
      throw new \RuntimeException(DriveVersionerException::DRIVE_CANNOT_CREATE_DIR . $e->getMessage(), $e);
    }
  }
}