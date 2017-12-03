<?php

namespace Partridge\Utils\Google;

use Partridge\Utils\Util;
use Partridge\Utils\Google\DriveVersionerMessages;
use Partridge\Utils\Google\DriveVersionerException;

/**
 * Simple factory for creating out Google_Client instance. May be expanded in the future
 * for more service clients.
 *  - relies upon correct credentials being available
 * 
 * 
 *  - https://developers.google.com/drive/v3/web/quickstart/php#step_2_install_the_google_client_library
 */
class GoogleClientSetup {

    const CREDENTIALS_FILENAME = 'drive-versioner-credentials.json';
    const SECRETS_FILENAME = 'drive-versioner-secret.json';
    const DRIVE_ROOT_ID_FILENAME = 'root-drive-id.txt';

    protected $applicationName = 'Drive API PHP Quickstart';
    protected $credentialsPath;
    protected $clientSecretPath;
    protected $driveRootId;
    protected $scopes;

    public function __construct(String $credentialsDir) {
        
        $this->credentialsPath = $credentialsDir . '/' . self::CREDENTIALS_FILENAME;
        $this->clientSecretPath = $credentialsDir . '/' . self::SECRETS_FILENAME;
        $this->driveRootId = file_get_contents($credentialsDir . '/' . self::DRIVE_ROOT_ID_FILENAME);

        // If modifying these scopes, delete your previously saved credentials
        // at ~/.credentials/drive-php-quickstart.json
        $this->scopes = implode(' ', [\Google_Service_Drive::DRIVE]); // full access to my google drive
        if (php_sapi_name() != 'cli') {
            throw new Exception('This application must be run on the command line.');
        }
        // Util::createDirIfNonExistent(dirname($credentialsDir));
    }

    /**
     * Returns an authorized API client.
     * @return \Google_Client the authorized client object
     */
    public function getClient() {
        $client = new \Google_Client();
        $client->setApplicationName($this->applicationName);
        $client->setScopes($this->scopes);
        $client->setAuthConfig($this->clientSecretPath);
        $client->setAccessType('offline');
    
        // Load previously authorized credentials from a file.
        if (file_exists($this->credentialsPath)) {
            $accessToken = json_decode(file_get_contents($this->credentialsPath), true);
        } else {
            throw new DriveVersionerException(DriveVersionerMessages::SETUP_CREDENTIALS_FILE_NOT_FOUND."Attempted path: {$this->credentialsPath}");
        }
        $client->setAccessToken($accessToken);
    
        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($this->credentialsPath, json_encode($client->getAccessToken()));
        }
        return $client;
    }

    public function recreateCredentials() {
        $client = new \Google_Client();
        $client->setApplicationName($this->applicationName);
        $client->setScopes($this->scopes);
        $client->setAuthConfig($this->clientSecretPath);
        $client->setAccessType('offline');

        $this->promptUserForCredentialsAndStore($client);
    }

    public function getDriveRootId(): String {
        return $this->driveRootId;
    }

    /**
     * @param \Google_Client $client
     * @return String
     */
    protected function promptUserForCredentialsAndStore(\Google_Client $client) {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));
    
        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
    
        // Store the credentials to disk.
        if(!file_exists(dirname($this->credentialsPath))) {
            mkdir(dirname($this->credentialsPath), 0700, true);
        }
        file_put_contents($this->credentialsPath, json_encode($accessToken));
        printf("Credentials saved to %s\n", $this->credentialsPath);
        return $accessToken;
    }
}
