<?php

namespace Partridge\Utils\Google;

use Partridge\Utils\Util;
use Symfony\Component\Console\Output\Output;
use Partridge\Utils\Google\DriveVersionerMessages;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Nice to haves:
 *  - List all versions for a file
 */
class DriveVersioner
{
    const MIME_DIR = 'application/vnd.google-apps.folder';
    const VERSIONED_FILENAME = 'versioned';

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

    /**
     * @var Output
     */
    protected $output;

    public function __construct(\Google_Service_Drive $client, $driveRootId) {
        $this->client = $client;
        $this->driveRootId = $driveRootId;

        $this->output = new ConsoleOutput;
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
    public function version(String $fileLoc, String $ns, String $discriminator): \Google_Service_Drive_DriveFile {
        if (!is_readable($fileLoc)) {
            $this->output(DriveVersionerMessages::VERSIONABLE_FILE_NOT_READABLE);
            throw new DriveVersionerException(DriveVersionerMessages::VERSIONABLE_FILE_NOT_READABLE."File not readable: " . $fileLoc);
        }

        if ($driveDir = $this->queryForDirectory($ns)) {
            $this->output(DriveVersionerMessages::DEBUG_NS_DIR_FOUND);
        } else {
            $driveDir = $this->createDirectory($ns);
            $this->output(DriveVersionerMessages::DEBUG_NS_DIR_CREATED);
        }

        if ($versionedFile = $this->queryForVersioned($driveDir)) {
            $this->output(DriveVersionerMessages::DEBUG_VERSIONED_FILE_FOUND);
        } else {
            $versionedFile = $this->createVersioned($ns, $discriminator, $driveDir, $fileLoc);
            $this->output(DriveVersionerMessages::DEBUG_VERSIONED_FILE_CREATED);
        }
        
        $this->createUpdate($ns, $discriminator, $versionedFile, $fileLoc);
        $this->output(DriveVersionerMessages::DEBUG_NEW_VERSION_CREATED);

        return $versionedFile;
    }

    /**
     * @param Output $output
     * @return self
     */
    public function setOutput(Output $output): self {
        $this->output = $output;
        return $this;
    }

    protected function queryForVersioned(\Google_Service_Drive_DriveFile $nsDir): ?\Google_Service_Drive_DriveFile {
        try {
            /** @var \Google_Service_Drive_FileList $fileList */
            $fileList = $this->client->files->listFiles([
            'q' => "'{$nsDir->id}' in parents and name = '" . self::VERSIONED_FILENAME . "'",
            ]);
        }
        catch (\Google_Exception $e) {
            $this->filterCommonExceptions($e);
        }
        
        if ($fileList && count($fileList->files) > 1) {
            throw new DriveVersionerException(DriveVersionerMessages::DUPLICATE_VERSIONED_FILE."Namespace: {$nsDir->name}");
        }
        
        return $fileList->files[0] ?? null;
    }
    
    protected function queryForDirectory(String $ns): ?\Google_Service_Drive_DriveFile {
        try {
            /** @var \Google_Service_Drive_FileList $fileList */
            $fileList = $this->client->files->listFiles([
                'q' => "'{$this->driveRootId}' in parents and name = '${ns}' and mimeType='".self::MIME_DIR."'", // http://bit.ly/2Bu19ro
            ]);
        }
        catch (\Google_Exception $e) {
            $this->filterCommonExceptions($e);
        }

        if ($fileList && count($fileList->files) > 1) {
            throw new DriveVersionerException(DriveVersionerMessages::DUPLICATE_NAMESPACE_DIRECTORY."Namespace: ${ns}");
        }

        return $fileList->files[0] ?? null;
    }

    /**
     * 
     *  - https://developers.google.com/drive/v3/reference/files/update
     * 
     * @throws DriveVersionerException
     *
     * @param string $ns
     * @param string $discriminator
     *
     * @return \Google_Service_Drive_DriveFile
     */
    protected function createUpdate(String $ns, String $discriminator, \Google_Service_Drive_DriveFile $alreadyVersioned, String $fileLoc): \Google_Service_Drive_DriveFile {
        $driveOpts = $this->doCreateVersionableServiceCallOpts($ns, $discriminator, $fileLoc);

        try {
            return $this->client->files->update(
                $alreadyVersioned->getId(),
                $alreadyVersioned,
                $driveOpts
            );
        } catch (\Google_Exception $e) {
            $this->filterCommonExceptions($e);
            throw new DriveVersionerException(DriveVersionerMessages::DRIVE_CANNOT_UPDATE_VERSIONED_FILE.$e->getMessage(), $e);
        }
    }
    
    /**
     * @throws DriveVersionerException
     *
     * @param string $ns
     * @param string $discriminator
     *
     * @return \Google_Service_Drive_DriveFile
   */
  protected function createVersioned(String $ns, String $discriminator, \Google_Service_Drive_DriveFile $nsDir, String $fileLoc): \Google_Service_Drive_DriveFile {
        $versioned = new \Google_Service_Drive_DriveFile();
      
        $versioned->name = self::VERSIONED_FILENAME;
        $versioned->parents = [$nsDir->getId()];
        $driveOpts = $this->doCreateVersionableServiceCallOpts($ns, $discriminator, $fileLoc);
        
        $this->output('CreateVersioned $opts: ' . Util::consolePrint($driveOpts));

        try {
            return $this->client->files->create(
                $versioned,
                $driveOpts
            );
        } catch (\Google_Exception $e) {
            $this->filterCommonExceptions($e);
            throw new DriveVersionerException(DriveVersionerMessages::DRIVE_CANNOT_CREATE_VERSIONED_FILE.$e->getMessage(), $e->getCode(), $e);
        }
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
        
        $dir->name = $ns;
        $dir->parents = [$this->driveRootId];
        
        try {
            return $this->client->files->create(
                $dir,
                [
                    'mimeType' => self::MIME_DIR,
                ]
            );
        } catch (\Google_Exception $e) {
            $this->filterCommonExceptions($e);
            if ($e->getCode() == 404) { // the rootDrive directory isn't found
                $this->output(DriveVersionerMessages::DRIVE_ROOT_NOT_FOUND);
                throw new DriveVersionerException(DriveVersionerMessages::DRIVE_ROOT_NOT_FOUND.$this->driveRootId, $e->getCode(), $e);
            }
            throw new DriveVersionerException(DriveVersionerMessages::DRIVE_CANNOT_CREATE_DIR.$e->getMessage(), $e->getCode(), $e);
        }
    }
    
    /**
     * @throws DriveVersionerException
     * @param \Google_Exception $e
     * @return void
     */
    protected function filterCommonExceptions(\Google_Exception $e): void {
        if ($e->getCode() == 403) {
            $this->output(DriveVersionerMessages::AUTHORISATION_FAIL);
            throw new DriveVersionerException(DriveVersionerMessages::AUTHORISATION_FAIL, $e->getCode(), $e);
        }
    }

    /**
     * @param String $ns
     * @param String $discriminator
     * @param String $fileLoc
     * @return array
     */
    protected function doCreateVersionableServiceCallOpts(String $ns, String $discriminator, String $fileLoc): array {
        return [
            'mimeType' => $this->getMimeType($fileLoc), 
            'uploadType' => 'multipart',
            'originalFilename' => basename($fileLoc),
            'keepRevisionForever' => true,
            'properties' => [
                'discriminator' => $discriminator,
                'id' => md5($ns . $discriminator)
            ]
        ];
    }

    protected function getMimeType(String $fileLoc): String {
        $ftype = 'application/octet-stream';
        $finfo = finfo_open(FILEINFO_MIME);
        if ($finfo !== FALSE) {
            $fres = finfo_file($finfo, $fileLoc);
            if (($fres !== FALSE) 
                && is_string($fres) 
                && (strlen($fres)>0)
            ) {
                $ftype = $fres;
            }
            finfo_close($finfo);
        }
        return $ftype;
    }
    
    protected function output(String $message): self {
        $this->output->writeln($message);
        return $this;
    }

}
