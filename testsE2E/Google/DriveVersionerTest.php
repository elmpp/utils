<?php

namespace Partridge\Utils\TestsE2E\Google;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Partridge\Utils\Google\DriveVersioner;
use Symfony\Component\Console\Output\Output;
use Partridge\Utils\Google\GoogleClientSetup;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * NEVER EVER EVER RUN IN CI OR WHATEVER - THESE ARE FOR PROOFING AND SPEEDING OF WORK
 * AD-HOC. 
 * They will run off the .gitignore'd Fixtures directory which should have the respective
 * files for auth and the drive-root-id to a test directory
 */
class DriveVersionerTest extends TestCase {
    
    /**
     * @var DriveVersioner
     */
    protected $versioner;
    /**
     * @var Output
     */
    protected $output;

    public function setUp() {
        // Get the API client and construct the service object.
        $clientCredentialsDir = __DIR__ . '/Fixtures';
        $clientSetup = new GoogleClientSetup($clientCredentialsDir);
        $client = $clientSetup->getClient();
        $driveService = new \Google_Service_Drive($client);
        
        // n.b. the driveRootId will be for this test directory - http://bit.ly/2jIDTOv
        $this->versioner = new DriveVersioner($driveService, $clientSetup->getDriveRootId());
        $this->versioner->setOutput($this->output = new BufferedOutput);
    }

    /**
     * 
     * @return void
     */
    public function testCreatesMultiple() {
        $finder = new Finder;
        $finder->files()->name('/\.tar$/')->in(__DIR__ . '/Fixtures/versionables')->sortByName();
        try {
            foreach ($finder as $aFile) {
                list($ns, $discriminator) = explode('_', basename($aFile->getFilename(), ".php"));
                print("Uploading {$aFile->getRealPath()}");
                $this->versioner->version($aFile->getRealPath(), $ns, $discriminator);
            }
        }
        catch (\Exception $e) {
            var_dump($e);
        }

        print($this->output->fetch());
    }
}