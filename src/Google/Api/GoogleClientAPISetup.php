<?php

namespace Partridge\Utils\Google\Api;

use Partridge\Utils\Util;

/**
 * Simple factory for creating out Google_Client instance. May be expanded in the future
 * for more service clients.
 *  - relies upon correct credentials being available
 *
 *
 *  - https://developers.google.com/drive/v3/web/quickstart/php#step_2_install_the_google_client_library
 */
class GoogleClientAPISetup
{
    const SETUP_CREDENTIALS_FILE_NOT_FOUND = "The required credentials file is not found. Please see the ../Util/recreateCredentials.php script for more info. ";
    const SETUP_CLIENT_SECRETS_FILE_NOT_FOUND = "The secrets file was not found. This is the file that is downloaded from the GCE admin area. See here - http://bit.ly/2D13niN . ";

    protected $applicationName = 'Drive API PHP Quickstart';
    protected $credentialsPath;
    protected $clientSecretPath;
    protected $driveRootId;
    protected $scopes;

    public function __construct(String $credentialsPath, String $oAuthSecretsPath, String $rootDriveId) {
        
        $this->credentialsPath = $credentialsPath;
        $this->clientSecretPath = $oAuthSecretsPath; // http://bit.ly/2D13niN
        $this->driveRootId = $rootDriveId;

        // If modifying these scopes, delete your previously saved credentials
        // at ~/.credentials/drive-php-quickstart.json
        $this->scopes = implode(' ', [\Google_Service_Drive::DRIVE]); // full access to my google drive
        if (php_sapi_name() != 'cli') {
            throw new \Exception('This application must be run on the command line.');
        }
        // Util::createDirIfNonExistent(dirname($credentialsDir));
    }

    protected function doCheckFileDependencies() {

        // Load previously authorized credentials from a file.
        if (!file_exists($this->credentialsPath)) {
            throw new \Exception(self::SETUP_CREDENTIALS_FILE_NOT_FOUND."Attempted path: {$this->credentialsPath}");
        }
        
        if (!file_exists($this->clientSecretPath)) {
            throw new \Exception(self::SETUP_CLIENT_SECRETS_FILE_NOT_FOUND."Attempted path: {$this->clientSecretPath}");
        }
    }

    /**
     * Returns an authorized API client.
     * @return \Google_Client the authorized client object
     */
    public function getClient(): \Google_Client {
        
        $this->doCheckFileDependencies();
        
        $client = new \Google_Client();
        $client->setApplicationName($this->applicationName);
        $client->setScopes($this->scopes);
        $client->setAuthConfig($this->clientSecretPath);
        $client->setAccessType('offline');
    
        $client->setAccessToken(json_decode(file_get_contents($this->credentialsPath), true));
    
        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($this->credentialsPath, json_encode($client->getAccessToken()));
        }
        return $client;
    }

    public function getDriveClient() {
        $client = $this->getClient();
        return new \Google_Service_Drive($client);
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
        if (!file_exists(dirname($this->credentialsPath))) {
            mkdir(dirname($this->credentialsPath), 0700, true);
        }
        file_put_contents($this->credentialsPath, json_encode($accessToken));
        printf("Credentials saved to %s\n", $this->credentialsPath);
        return $accessToken;
    }
}
