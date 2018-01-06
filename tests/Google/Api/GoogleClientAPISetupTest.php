<?php

namespace Partridge\Utils\Tests\Google\Api;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Partridge\Utils\Google\Api\GoogleClientAPISetup;

require_once __DIR__.'/../../../vendor/autoload.php';

/**
 * Perhaps check it looks at the right place for keys etc
 */
class GoogleClientAPISetupTest extends TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    protected $root;

    public function setUp() {
        $this->root = vfsStream::setup(
            'root',
            null,
            ['var' => [
                'partridge' => [
                    'auth' => [
                        // // should be a dummy credentials file that can be versioned ok
                        'api-credentials.json' => file_get_contents(__DIR__.'/../Fixtures/api-credentials.json'),
                        'api-secret.json' => file_get_contents(__DIR__.'/../Fixtures/api-secret.json'),
                    ]
                ]
            ]]
        );
    }

    public function testCredentialsPathNotExistent() {
        $this->expectException(\Exception::CLASS);
        $this->expectExceptionMessageRegExp('|^' . GoogleClientAPISetup::SETUP_CREDENTIALS_FILE_NOT_FOUND . '.*$|');
        $subject = new GoogleClientAPISetup(
            $this->root->url() . '/NON_EXISTENT/api-credentials.json', 
            $this->root->url() . '/var/partridge/auth/api-secret.json', 
            'made-up-gdrive-folder-id'
        );
        $client = $subject->getClient();
    }
    
    public function testClientSecretsPathNotExistent() {
        $this->expectException(\Exception::CLASS);
        $this->expectExceptionMessageRegExp('|^' . GoogleClientAPISetup::SETUP_CLIENT_SECRETS_FILE_NOT_FOUND . '.*$|');
        $subject = new GoogleClientAPISetup(
            $this->root->url() . '/var/partridge/auth/api-credentials.json', 
            $this->root->url() . '/NON_EXISTENT/api-secret.json', 
            'made-up-gdrive-folder-id'
        );
        $client = $subject->getClient();
    }
    
    public function testGetClient() {
        
        $subject = new GoogleClientAPISetup(
            $this->root->url() . '/var/partridge/auth/api-credentials.json', 
            $this->root->url() . '/var/partridge/auth/api-secret.json', 
            'made-up-gdrive-folder-id'
        );
        // $subject = new GoogleClientAPISetup($this->root->url() . '/var/partridge/auth/api-credentials.json', 'made-up-gdrive-folder-id');

        $client = $subject->getClient();
        $this->assertInstanceOf(\Google_Client::CLASS, $client);
    }
    
    public function testGetDriveClient() {
        
        $subject = new GoogleClientAPISetup(
            $this->root->url() . '/var/partridge/auth/api-credentials.json', 
            $this->root->url() . '/var/partridge/auth/api-secret.json', 
            'made-up-gdrive-folder-id'
        );
        
        $client = $subject->getDriveClient();
        $this->assertInstanceOf(\Google_Service_Drive::CLASS, $client);
    }
}
