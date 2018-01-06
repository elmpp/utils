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
 *  Authentication to the api is done with a service account. Relies on there being 
 * the json file at the predefined place
 * - http://bit.ly/2BQ95TR
 * 
 *  - https://github.com/GoogleCloudPlatform/php-docs-samples/blob/master/appengine/flexible/storage/app.php
 */
class GoogleClientCloudSetup
{

    /**
     * @var String
     */
    protected $projectId;

    /**
     * @var String
     */
    protected $serviceAccountJsonPath;

    public function __construct(String $projectId, String $serviceAccountJsonPath) {
        $this->projectId = $projectId;
        $this->serviceAccountJsonPath = $serviceAccountJsonPath;

        putenv("GOOGLE_APPLICATION_CREDENTIALS=${serviceAccountJsonPath}"); // http://bit.ly/2BP7opy
    }

    /**
     * Returns a Bucket instance
     * 
     *  - http://bit.ly/2kdkbLR
     */
    public function getStorageBucket(String $bucket): Bucket {
        
        $storageClient = new StorageClient([ // 
            'projectId' => $this->projectId,
        ]);
        return $storageClient->bucket($bucket);
    }
}
