<?php

namespace Partridge\Utils\Google;

use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageObject;
use Google\Cloud\Storage\ObjectIterator;
use Partridge\Utils\Google\DriveVersioner\DriveVersioner;
use Partridge\Utils\Traits\OutputtableTrait;

/**
 * Will migrate folder of files from a GCE Bucket to Google Drive using our DriveVersioner
 *  - should optionally take a closure that will receive all files found within the bucket and return those that are to be considered
 *  of interest (buckets may have many differing types of file). These will be the ones that ultimately would be deleted from GCE (select filter)
 *  - another filter will enable the found files from above to be further filtered before being backed up to Drive (backup filter)
 *  - delete backed up files (at end of successful run only)
 *  - have dry run
 */
class GCEToDriveBackup {

    use OutputtableTrait;

    const MESSAGE_DISCRIMINATOR_REGEX_INCORRECT = 'Cannot get discriminator from filename using supplied regex. ';
    const MESSAGE_DEBUG_GCE_DELETING = 'Deleting this many files on GCE. ';
    const MESSAGE_DEBUG_DRIVE_BACKING_UP = 'Backing up this many files on Drive. ';

    /**
     * Regex that can parse a standardised format
     * @var string
     */
    const PARSE_REGEX = '/^(.*\/)?(?:(.+)_)([^.]+)(\..*$|($))/';

    /**
     * @var String
     */
    protected $tmpDir;

    /**
     * Allows filtering of the files within the bucket to be marked as correct type
     * @var \Closure
     */
    protected $selectFilter;
    
    /**
     * Allows filtering of the files of correct type to denote which should be backed up
     * @var \Closure
     */
    protected $backupFilter;

    /**
     * The Google StorageClient instance. Should already be authed and set to the correct bucket
     * @var Bucket
     */
    protected $cloudStorageBucket;
    
    /**
     * @var DriveVersioner
     */
    protected $driveVersioner;

    /**
     * @var []
     */
    protected $options;

    
    public function __construct(Bucket $cloudStorageBucket, DriveVersioner $driveVersioner, array $options) {
        $this->cloudStorageBucket = $cloudStorageBucket;
        $this->driveVersioner = $driveVersioner;
        $this->options = array_merge([
            'dry-run' => false,
            'tmp-dir' => '/tmp',
            'discriminator-regex' => '/^(?:[^_])*_([^.]+)\..+$/',
        ], $options);
        if (!isset($options['ns'])) {
            throw new \Exception("The NS must be specified");
        }
    }

    public function setSelectFilter(\Closure $filter): self {
        $this->selectFilter = $filter;
        return $this;
    }
    public function setBackupFilter(\Closure $filter): self {
        $this->backupFilter = $filter;
        return $this;
    }
    
    /**
     * throws \RuntimeException
     * @return void
     */
    public function run() {

        $selectedFiles = $this->doGetSelectFiles();
        $backupable    = $this->doGetBackupFiles($selectedFiles);

        $this->output(self::MESSAGE_DEBUG_DRIVE_BACKING_UP . count($backupable));
        $this->output(self::MESSAGE_DEBUG_GCE_DELETING . count($selectedFiles));
        
        if (!$this->options['dry-run']) {
            $this->doBackup($backupable);
            $this->doDelete($selectedFiles); // keep last
        }
    }

    protected function doBackup(array $files) {
        $downloader = function(StorageObject $file) {
            $tmpLocation = $this->options['tmp-dir'] . '/' . basename($file->info['filename']);
            $file->downloadToFile($tmpLocation);
            return $tmpLocation;
        };
        $backuper = function(String $path) {
            // discriminator will be gleaned from the filename based on the regex option
            preg_match($this->options['discriminator-regex'], basename($path), $parts);
            if (!$parts[1] ?? null) {
                throw new \RuntimeException(self::MESSAGE_DISCRIMINATOR_REGEX_INCORRECT . "Filename: " . basename($path) . ", Regex: {$this->options['discriminator-regex']}");
            }
            $this->driveVersioner->version($path, $this->options['ns'], $parts[1]);
        };
        
        /** @var $aFile StorageObject */
        foreach ($files as $aFile) {
            $tmpLocation = $downloader->__invoke($aFile);
            $backuper->__invoke($tmpLocation);
        }
    }
    
    protected function doDelete(array $files) {
        /** @var $aFile StorageObject */
        foreach ($files as $aFile) {
            $aFile->delete();
        }
    }

    public static function filepathParser(String $path): array {
        preg_match(self::PARSE_REGEX, $path, $matches);
        $matches = array_slice($matches, 1, 4);
        return $matches;
    }

    /**
     * Finds all files within the bucket and runs through the select filter
     */
    protected function doGetSelectFiles(): array {

        return $this->selectFilter->__invoke(iterator_to_array($this->cloudStorageBucket->objects()));
    }
    
    /**
     * Finds all files within the bucket and runs through the backup filter
     * 
     * @see Google\Cloud\Storage\Bucket::Objects
     * @return []
     */
    protected function doGetBackupFiles(array $selectedFiles): array {
        
        return $this->backupFilter->__invoke($selectedFiles);
    }
}
