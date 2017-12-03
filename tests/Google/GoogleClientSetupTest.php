<?php

namespace Partridge\Utils\tests\Google;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Partridge\Utils\Google\GoogleClientSetup;

require_once __DIR__.'/../../vendor/autoload.php';

/**
 * Perhaps check it looks at the right place for keys etc
 */
class GoogleClientSetupTest extends TestCase
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
                        GoogleClientSetup::CREDENTIALS_FILENAME => file_get_contents(__DIR__.'/Fixtures/'.GoogleClientSetup::CREDENTIALS_FILENAME),
                        GoogleClientSetup::SECRETS_FILENAME => file_get_contents(__DIR__.'/Fixtures/'.GoogleClientSetup::SECRETS_FILENAME),
                        GoogleClientSetup::DRIVE_ROOT_ID_FILENAME => file_get_contents(__DIR__.'/Fixtures/'.GoogleClientSetup::DRIVE_ROOT_ID_FILENAME),
                    ]
                ]
            ]]
        );

        $subject = new GoogleClientSetup($root->url().$credentialsPath);

        $this->assertEquals($subject->getDriveRootId(), 'made-up-root-id', "Didn't pick up the driveRootId from the `root-drive-id.txt` file");

        $client = $subject->getClient();
        $this->assertInstanceOf(\Google_Client::CLASS, $client);

    }
}
