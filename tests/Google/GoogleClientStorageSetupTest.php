<?php

namespace Partridge\Utils\Tests\Google;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Partridge\Utils\Google\GoogleClientStorageSetup;

require_once __DIR__.'/../../vendor/autoload.php';

/**
 * Simple test for the google cloud client setup
 */
class GoogleClientStorageSetupTest extends TestCase
{
    public function testGetClient() {
        $credentialsPath = '/var/Partridge/auth';
        $root = vfsStream::setup(
            'root',
            null,
            ['var' => [
                'Partridge' => [
                    'auth' => [
                        // // should be a dummy credentials file that can be versioned ok
                        GoogleClientAPISetup::CREDENTIALS_FILENAME => file_get_contents(__DIR__.'/DriveVersioner/Fixtures/'.GoogleClientAPISetup::CREDENTIALS_FILENAME),
                        GoogleClientAPISetup::SECRETS_FILENAME => file_get_contents(__DIR__.'/DriveVersioner/Fixtures/'.GoogleClientAPISetup::SECRETS_FILENAME),
                        GoogleClientAPISetup::DRIVE_ROOT_ID_FILENAME => file_get_contents(__DIR__.'/DriveVersioner/Fixtures/'.GoogleClientAPISetup::DRIVE_ROOT_ID_FILENAME),
                    ]
                ]
            ]]
        );

        $subject = new GoogleClientAPISetup($root->url().$credentialsPath);

        $this->assertEquals($subject->getDriveRootId(), 'made-up-root-id', "Didn't pick up the driveRootId from the `root-drive-id.txt` file");

        $client = $subject->getClient();
        $this->assertInstanceOf(\Google_Client::CLASS, $client);
    }
}
