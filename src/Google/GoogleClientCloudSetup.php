<?php

namespace Partridge\Utils\Google;

use Partridge\Utils\Util;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Partridge\Utils\Google\DriveVersionerMessages;
use Partridge\Utils\Google\DriveVersionerException;

/**
 * Simple factory for creating out Cloud client instances. May be expanded in the future
 * for more cloud service clients.
 *  - relies upon correct credentials being available in the defined place
 *
 *  - https://github.com/GoogleCloudPlatform/php-docs-samples/blob/master/appengine/flexible/storage/app.php
 */
class GoogleClientCloudSetup
{

    const MESSAGE_MISSING_ENV = "Environment variable must be set. ";
    const ENV_PROJECT_ID = 'GOOGLE_PROJECT_ID';

    /**
     * @var String
     */
    protected $projectId;

    public function __construct(String $projectId) {
        $this->projectId = $projectId;
    }

    /**
     * Returns an Bucket instance
     * 
     *  - http://bit.ly/2kdkbLR
     */
    public function getStorageBucket(String $bucket): Bucket {
        
        $storageClient = new StorageClient([
            'projectId' => getenv('GOOGLE_PROJECT_ID'),
        ]);
        return $storageClient->bucket($bucket);
    }
}
