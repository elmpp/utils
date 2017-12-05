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

    protected $testNs = 'partridge';

    public function setUp() {
        // Get the API client and construct the service object.
        $clientCredentialsDir = __DIR__ . '/Fixtures';
        $clientSetup = new GoogleClientSetup($clientCredentialsDir);
        $client = $clientSetup->getClient();
        $driveService = new \Google_Service_Drive($client);
        
        // n.b. the driveRootId will be for this test directory - http://bit.ly/2jIDTOv
        $this->versioner = new DriveVersioner($driveService, $clientSetup->getDriveRootId());
        $this->versioner->setOutput($this->output = new BufferedOutput);
        $this->versioner->setVerbosity(2);
    }

    /**
     * Runs an E2E test on Drive, creating some tar file versioning. 
     * Relies on the driveRootId being present and its contents being already wiped
     * @return void
     */
    public function testCreatesMultiple() {
        try {
            $this->doVersioning();
            $revisionList = $this->doListing();
        }
        catch (\Exception $e) {
            var_dump($e);
            var_dump("Error encountered. Code: {$e->getCode()}\n{$e->getMessage()}");
        }

        $revisions = $revisionList->revisions;

        $this->assertRevisionValues($revisions[0]);
        $this->assertCount(3, $revisions, "Perhaps we didn't clear down the drive test dir?");

        print($this->output->fetch());
    }

    protected function assertRevisionValues(\Google_Service_Drive_Revision $revision) {
        $this->assertTrue($revision->keepForever, "Revisions should have been set to keep forever");
        $this->assertEquals($revision->originalFilename, "Google_Service_Drive_Revision.txt.tar");

    }

    protected function doListing(): ?\Google_Service_Drive_RevisionList {
        $revisionList = $this->versioner->list($this->testNs);
        return $revisionList;
    }
    
    protected function doVersioning() {
        
        $finder = new Finder;
        $finder->files()->name('/\.tar$/')->in(__DIR__ . '/Fixtures/versionables')->sortByName();
        
        $ids = [];
        foreach ($finder as $aFile) {
            list($ns, $discriminator) = explode('_', basename($aFile->getFilename(), ".txt.tar"));
            
            // print("Uploading {$aFile->getRealPath()} with ns: ${ns} and discriminator: ${discriminator}");
            $lastUpload = $this->versioner->version($aFile->getRealPath(), $this->testNs, $discriminator);
            $ids[] = $lastUpload->getId();
        }

        return; // not any need for anything
    }
}