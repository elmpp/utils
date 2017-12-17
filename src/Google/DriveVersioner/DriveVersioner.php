<?php

namespace Partridge\Utils\Google\DriveVersioner;

use Partridge\Utils\Util;
use Partridge\Utils\ArrayUtil;
use Psr\SimpleCache\CacheInterface;
use Partridge\Utils\Traits\OutputtableTrait;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Cache\Simple\ArrayCache;
use Symfony\Component\Console\Output\ConsoleOutput;
use Partridge\Utils\Google\DriveVersioner\DriveVersionerMessages;

/**
 * Nice to haves:
 *  - List all versions for a file
 */
class DriveVersioner
{
    use OutputtableTrait;

    const MIME_DIR = 'application/vnd.google-apps.folder';
    const VERSIONED_FILENAME = 'versioned';
    const MODE_VERSION = 'VERSION';
    const MODE_REVISIONS = 'REVISIONS';

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
    /**
     * symfony cache
     * @var CacheInterface
     */
    protected $cache;

    /**
     * The current $ns
     * @var String
     */
    protected $ns;
    /**
     * The current $discriminator
     * @var String
     */
    protected $discriminator;
    /**
     * VERSION | LIST
     * @var String
     */
    protected $mode = self::MODE_VERSION;

    /**
     * 1 = silent
     * 2 = notice
     * 3 = all
     */
    protected $verbosity = 2;

    public function __construct(\Google_Service_Drive $client, $driveRootId) {
        $this->client = $client;
        $this->driveRootId = $driveRootId;

        $this->output = new ConsoleOutput;
        $this->cache = new ArrayCache;
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
        
        $this->ns = $ns;
        $this->discriminator = $discriminator;
        $this->mode = self::MODE_VERSION;

        if (!is_readable($fileLoc)) {
            $this->output(DriveVersionerMessages::VERSIONABLE_FILE_NOT_READABLE);
            throw new DriveVersionerException(DriveVersionerMessages::VERSIONABLE_FILE_NOT_READABLE."File not readable: " . $fileLoc);
        }

        if ($driveDir = $this->queryForDirectory()) {
            $this->output(DriveVersionerMessages::DEBUG_NS_DIR_FOUND);
        } else {
            $driveDir = $this->createDirectory();
            $this->output(DriveVersionerMessages::DEBUG_NS_DIR_CREATED);
        }

        if ($versionedFile = $this->queryForVersioned($driveDir)) {
            $this->output(DriveVersionerMessages::DEBUG_VERSIONED_FILE_FOUND);

            // need to check whether the versioned file already exists. Unfortunately, there is no query facility within revisions
            // we shall use the filename to determine whether it's been uploaded
            // @todo - perhaps combine with an md5 hash?
            $filename = basename($fileLoc);
            $revisionList = $this->queryForVersionList($versionedFile);
            $alreadyVersioned = array_filter($revisionList->getRevisions(), function (\Google_Service_Drive_Revision $item) use ($filename) {
                return $filename === $item->getOriginalFilename();
            });
            if (count($alreadyVersioned)) {
                $this->output(DriveVersionerMessages::DEBUG_VERSION_FILE_ALREADY_EXISTS);
                return $versionedFile;
            }

            $this->createUpdate($versionedFile, $driveDir, $fileLoc);
            $this->output(DriveVersionerMessages::DEBUG_NEW_VERSION_CREATED);
        } else {
            $versionedFile = $this->createVersioned($driveDir, $fileLoc);
            $this->output(DriveVersionerMessages::DEBUG_VERSIONED_FILE_CREATED);
        }

        return $versionedFile;
    }
  
    /**
   * Lists the versions of a versioned
   *
   * @param string $ns      The version namespace
   *
   * @throws DriveVersionerException
   * @return \Google_Service_Drive_FileList
   */
    public function list(String $ns): ?\Google_Service_Drive_RevisionList {
        
        $this->ns = $ns;
        $this->discriminator = '';
        $this->mode = self::MODE_REVISIONS;
        
        if ($cached = $this->checkCache($cacheKey = "list-{$this->ns}")) {
            return $cached;
        }

        if (!$driveDir = $this->queryForDirectory()) {
            $this->output(DriveVersionerMessages::DRIVE_CANNOT_LIST_VERSIONED_FILE);
            throw new DriveVersionerException(DriveVersionerMessages::DRIVE_CANNOT_LIST_VERSIONED_FILE);
        }
        $this->output(DriveVersionerMessages::DEBUG_NS_DIR_FOUND);
        
        if (!$versionedFile = $this->queryForVersioned($driveDir)) {
            $this->output(DriveVersionerMessages::DRIVE_CANNOT_LIST_VERSIONED_FILE);
            throw new DriveVersionerException(DriveVersionerMessages::DRIVE_CANNOT_LIST_VERSIONED_FILE);
        }
        $this->output(DriveVersionerMessages::DEBUG_VERSIONED_FILE_FOUND);
        $this->output(DriveVersionerMessages::DEBUG_LISTING_VERSIONS_FOR_VERSIONED.$versionedFile->getId());
        
        $versionList = $this->queryForVersionList($versionedFile);
        $this->cache->set($cacheKey, $versionList);
        return $versionList;
    }

    /**
     * Ensures that all revisions for a ns are in good order. Shouldn't really be
     * needed but don't wanna be losing data
     *
     * @param String $ns
     * @return void
     */
    public function updateAllRevisions(String $ns): void {

        $this->ns = $ns;
        $this->discriminator = '';
        $this->mode = self::MODE_REVISIONS;
        
        $allRevisions = $this->list($ns);
        if (!count($allRevisions)) { // no versioned or no revisions
            return;
        }
        $versioned = $this->cache->get("queryForVersioned-{$this->ns}"); // should be present
        foreach ($allRevisions as $aRevision) {
            $this->updateRevision($versioned, $aRevision);
        }
    }

    protected function updateRevision(\Google_Service_Drive_DriveFile $versioned, \Google_Service_Drive_Revision $revision): \Google_Service_Drive_Revision {
       
        $freshRevision = new \Google_Service_Drive_Revision;
        $freshRevision->setKeepForever(true);

        try {
            $this->output(DriveVersionerMessages::DEBUG_UPDATING_REVISION . $revision->getId());
            return $this->client->revisions->update( // https://drive.google.com/file/d/1S0_vwPL7fA1Qy4ulKDT2-I3e16pE-r72/view?usp=sharing
                $versioned->getId(),
                $revision->getId(),
                $freshRevision,
                [
                ]
            );
        } catch (\Google_Exception $e) {
            $this->filterCommonExceptions($e);
        }
    }

    /**
     * @param \Google_Service_Drive_DriveFile $versionedFile (the HEAD versioned file)
     * @return \Google_Service_Drive_RevisionList
     */
    protected function queryForVersionList(\Google_Service_Drive_DriveFile $versionedFile): \Google_Service_Drive_RevisionList {
        
        if ($cached = $this->checkCache($cacheKey = "queryForVersionList-{$this->ns}")) {
            return $cached;
        }

        try {
            /** @var \Google_Service_Drive_FileList $fileList */
            return $this->client->revisions->listRevisions(
                $versionedFile->getId(),
                [
                    'fields' => 'kind,revisions' // http://bit.ly/2ioGcpC
                ]
            );
        } catch (\Google_Exception $e) {
            $this->filterCommonExceptions($e);
        }

        $ret = $list->revisions;
        $this->cache->set($cacheKey, $ret);
        return $ret;
    }
    
    protected function queryForVersioned(\Google_Service_Drive_DriveFile $nsDir): ?\Google_Service_Drive_DriveFile {
        
        if ($cached = $this->checkCache($cacheKey = "queryForVersioned-{$this->ns}")) {
            return $cached;
        }
        
        try {
            /** @var \Google_Service_Drive_FileList $fileList */
            $fileList = $this->client->files->listFiles([
                'q' => "'{$nsDir->id}' in parents and trashed = false and name = '" . self::VERSIONED_FILENAME . "'",
                ]);
        } catch (\Google_Exception $e) {
            $this->filterCommonExceptions($e);
        }
            
        if ($fileList && count($fileList->files) > 1) {
            throw new DriveVersionerException(DriveVersionerMessages::DUPLICATE_VERSIONED_FILE."Namespace: {$nsDir->name}");
        }
        
        $ret = $fileList->files[0] ?? null;
        $this->cache->set($cacheKey, $ret);
        return $ret;
    }
    
    protected function queryForDirectory(): ?\Google_Service_Drive_DriveFile {
        
        if ($cached = $this->checkCache($cacheKey = "queryForDirectory-{$this->ns}")) {
            return $cached;
        }
        
        try {
            $q = "'{$this->driveRootId}' in parents and trashed = false and name = '{$this->ns}' and mimeType='".self::MIME_DIR."'"; // http://bit.ly/2Bu19ro
            // $this->output("queryForDirectory query: ${q}");

            /** @var \Google_Service_Drive_FileList $fileList */
            $fileList = $this->client->files->listFiles([
                'q' => $q
            ]);
        } catch (\Google_Exception $e) {
            $this->filterCommonExceptions($e);
        }

        if ($fileList && count($fileList->files) > 1) {
            throw new DriveVersionerException(DriveVersionerMessages::DUPLICATE_NAMESPACE_DIRECTORY."Namespace: ${ns}");
        }

        $ret = $fileList->files[0] ?? null;
        $this->cache->set($cacheKey, $ret);
        return $ret;
    }

    /**
     *
     *  - https://developers.google.com/drive/v3/reference/files/update
     *
     * @throws DriveVersionerException
     *
     * @return \Google_Service_Drive_DriveFile
     */
    protected function createUpdate(\Google_Service_Drive_DriveFile $alreadyVersioned, \Google_Service_Drive_DriveFile $nsDir, String $fileLoc): \Google_Service_Drive_DriveFile {
        
        $bodyFields = [
            'mimeType' => $this->getMimeType($fileLoc),
            'properties' => [
                'discriminator' => $this->discriminator,
                'id' => md5($this->ns . $this->discriminator)
            ],
            'originalFilename' => basename($fileLoc),
            'keepRevisionForever' => true,
            'uploadType' => 'multipart',
            'name' => self::VERSIONED_FILENAME,
        ];
        $opts = [
            'data' => file_get_contents($fileLoc),
        ];
        
        $this->outputServiceParams('CreateNewVersion body fields', $bodyFields);
        $this->outputServiceParams('CreateNewVersion opts', $opts);

        try {
            return $this->client->files->update(
                $alreadyVersioned->getId(),
                new \Google_Service_Drive_DriveFile($bodyFields),
                $opts
            );
        } catch (\Google_Exception $e) {
            $this->filterCommonExceptions($e);
            $this->output(DriveVersionerMessages::DRIVE_CANNOT_UPDATE_VERSIONED_FILE);
            throw new DriveVersionerException(DriveVersionerMessages::DRIVE_CANNOT_UPDATE_VERSIONED_FILE.$e->getMessage(), $e->getCode(), $e);
        }
    }
    
    /**
     *
     *  - https://developers.google.com/apis-explorer/#p/drive/v3/drive.files.create
     *  - https://developers.google.com/drive/v3/web/manage-uploads
     *
     * @throws DriveVersionerException
     *
     * @param string $ns
     * @param string $discriminator
     *
     * @return \Google_Service_Drive_DriveFile
     */
    protected function createVersioned(\Google_Service_Drive_DriveFile $nsDir, String $fileLoc): \Google_Service_Drive_DriveFile {
        $bodyFields = [
            'mimeType' => $this->getMimeType($fileLoc),
            'properties' => [
                'discriminator' => $this->discriminator,
                'id' => md5($this->ns . $this->discriminator)
            ],
            'originalFilename' => basename($fileLoc),
            'keepRevisionForever' => true,
            'uploadType' => 'multipart',
            'name' => self::VERSIONED_FILENAME,
            'parents' => [$nsDir->getId()],
        ];
        $opts = [
            'data' => file_get_contents($fileLoc),
        ];
        
        $this->outputServiceParams('CreateVersioned body fields', $bodyFields);
        $this->outputServiceParams('CreateVersioned opts', $opts);
        
        try {
            return $this->client->files->create(
                new \Google_Service_Drive_DriveFile($bodyFields),
                $opts
            );
        } catch (\Google_Exception $e) {
            $this->filterCommonExceptions($e);
            $this->output(DriveVersionerMessages::DRIVE_CANNOT_CREATE_VERSIONED_FILE);
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
    protected function createDirectory(): \Google_Service_Drive_DriveFile {
        $bodyFields = [
            'mimeType' => self::MIME_DIR,
            'name' => $this->ns,
            'parents' => [$this->driveRootId],
        ];
        $opts = [
        ];

        $this->outputServiceParams('CreateDirectory body fields', $bodyFields);
        $this->outputServiceParams('CreateDirectory opts', $opts);
        
        try {
            return $this->client->files->create(
                new \Google_Service_Drive_DriveFile($bodyFields),
                $opts
            );
        } catch (\Google_Exception $e) {
            $this->output(DriveVersionerMessages::DRIVE_CANNOT_CREATE_DIR);
            if ($e->getCode() == 404) { // the rootDrive directory isn't found
                $this->output(DriveVersionerMessages::DRIVE_ROOT_NOT_FOUND);
                throw new DriveVersionerException(DriveVersionerMessages::DRIVE_ROOT_NOT_FOUND.$this->driveRootId, $e->getCode(), $e);
            }
            $this->filterCommonExceptions($e);
            throw new DriveVersionerException(DriveVersionerMessages::DRIVE_CANNOT_CREATE_DIR.$e->getMessage(), $e->getCode(), $e);
        }
    }

    protected function checkCache(String $cacheKey) {
        if ($cached = $this->cache->get($cacheKey)) {
            $this->output(DriveVersionerMessages::DEBUG_CACHE_HIT . $cacheKey);
            return $cached;
        }
        return null;
    }
    
    /**
     * @throws DriveVersionerException
     * @param \Google_Exception $e
     * @return void
     */
    protected function filterCommonExceptions(\Google_Exception $e): void {
        if ($e instanceof \Google_Service_Exception) {
            $errors = $e->getErrors();
            if ($error = $errors[0] ?? null) {
                switch ($error['reason']) {
                    case 'insufficientFilePermissions':
                        $this->output(DriveVersionerMessages::AUTHORISATION_FAIL);
                        throw new DriveVersionerException(DriveVersionerMessages::AUTHORISATION_FAIL, $e->getCode(), $e);
                        break;
                    case 'parentNotAFolder':
                        $this->output(DriveVersionerMessages::PARENT_ROOT_NOT_FOUND);
                        throw new DriveVersionerException(DriveVersionerMessages::PARENT_ROOT_NOT_FOUND, $e->getCode(), $e);
                        break;
                    case 'fieldNotWritable':
                        $this->output(DriveVersionerMessages::FIELD_NOT_WRITEABLE);
                        throw new DriveVersionerException(DriveVersionerMessages::FIELD_NOT_WRITEABLE, $e->getCode(), $e);
                        break;
                    default:
                        var_dump("new google exception");
                        var_dump($e->getMessage());
                        Util::consolePrint($error);
                        die;
                }
            }
        } else {
            throw $e; // need to surface weird Google Exceptions here
        }
    }

    protected function getMimeType(String $fileLoc): String {
        $ftype = 'application/octet-stream';
        $finfo = finfo_open(FILEINFO_MIME);
        if ($finfo !== false) {
            $fres = finfo_file($finfo, $fileLoc);
            if (($fres !== false)
                && is_string($fres)
                && (strlen($fres)>0)
            ) {
                $ftype = $fres;
            }
            finfo_close($finfo);
        }
        return $ftype;
    }

    protected function outputServiceParams(String $prefix, array $fields): self {
        if (ArrayUtil::arrayPluck($fields, 'data')) {
            $fields['data'] = '--REDACTED--';
        }
        return $this->output("${prefix}: \n" . Util::consolePrint($fields), 3);
    }
    
    protected function output(String $message, Int $verbosity = 2): self {
        if ($this->verbosity >= $verbosity) {
            $this->output->writeln(" | {$this->mode} : {$this->ns} : {$this->discriminator} | $message");
        }
        return $this;
    }

    public function setVerbosity(Int $verbosity): self {
        $this->verbosity = $verbosity;
        return $this;
    }
}
