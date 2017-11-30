<?php

namespace Partridge\Utils\Google;

/**
 * Nice to haves:
 *  - List all versions for a file
 */
class DriveVersioner
{
    const MIME_DIR = 'application/vnd.google-apps.folder';

  /**
   * @var \Google_Service_Drive
   */
    protected $client;
  /**
   * The Drive ID for the root directory. Get this from the UI
   *
   * @var string
   */
    protected $driveRootId;

    public function __construct(\Google_Service_Drive $client, $driveRootId) {
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
   * @param string $fileLoc Path to the file to be versioned
   * @param string $ns      The version namespace
   *
   * @throws DriveVersionerException
   */
    public function version(String $fileLoc, String $ns): \Google_Service_Drive_DriveFile {
        if (!is_readable($fileLoc)) {
            throw new DriveVersionerException(DriveVersionerException::VERSIONABLE_FILE_NOT_READABLE);
        }
        if (!$driveDir = $this->queryForDirectory($ns)) {
            $driveDir = $this->createDirectory($ns);
        }

        return $driveDir;
    }

    protected function queryForDirectory(String $ns): ?\Google_Service_Drive_DriveFile {
      /** @var \Google_Service_Drive_FileList $fileList */
        $fileList = $this->client->files->listFiles([
        'q' => "'{$this->driveRootId}' in parents and name = '${ns}' and mimeType='".self::MIME_DIR."'", // http://bit.ly/2Bu19ro
        ]);
        if (count($fileList->files) > 1) {
            throw new DriveVersionerException(DriveVersionerException::DUPLICATE_NAMESPACE_DIRECTORY."Namespace: ${ns}");
        }

        return $fileList->files[0] ?? null;
    }

  /**
   * @throws DriveVersionerException
   *
   * @param string $ns
   *
   * @return \Google_Service_Drive_DriveFile
   */
    protected function createDirectory(String $ns): \Google_Service_Drive_DriveFile {
        $dir = new \Google_Service_Drive_DriveFile();
        $dir->mimeType = self::MIME_DIR;
        $dir->name = $ns;
        $dir->parents = $this->driveRootId;
        try {
            return $this->client->files->create($dir);
        } catch (\Google_Exception $e) {
            throw new \RuntimeException(DriveVersionerException::DRIVE_CANNOT_CREATE_DIR.$e->getMessage(), $e);
        }
    }
}
