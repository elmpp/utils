<?php

namespace Partridge\Utils\TestsE2E\Google;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Output\Output;
use Partridge\Utils\Google\GoogleClientAPISetup;
use Symfony\Component\Console\Output\BufferedOutput;
use Partridge\Utils\Google\DriveVersioner\DriveVersioner;

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
        $clientCredentialsFilePath = __DIR__ . '/Fixtures';
        $clientSetup = new GoogleClientAPISetup(
            __DIR__ . '/Fixtures/api-credentials.json',
            __DIR__ . '/Fixtures/api-secret.json',
            file_get_contents(__DIR__ . '/Fixtures/api-root-drive-id.txt') // (/Partridge/backups/testDriveVersioner) - http://bit.ly/2D8cqPl
        );
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
            $this->versioner->clearCache(); // can't assert on the keepForever which has been newly revised
            $revisionList = $this->doGetListing();
        }
        catch (\Exception $e) {
            var_dump($e);
            var_dump("Error encountered. Code: {$e->getCode()}\n{$e->getMessage()}");
            die;
        }

        $revisions = $revisionList->revisions;
        print($this->output->fetch());

        $this->assertRevisionValues($revisions[0]);
        $this->assertCount(3, $revisions, "Perhaps we didn't clear down the drive test dir?");
    }

    protected function assertRevisionValues(\Google_Service_Drive_Revision $revision) {
        $this->assertTrue($revision->keepForever, "Revisions should have been set to keep forever");
        $this->assertEquals($revision->originalFilename, "partridge_2017-12-03.txt.tar.gz");
    }

    protected function doGetListing(): ?\Google_Service_Drive_RevisionList {
        $revisionList = $this->versioner->list($this->testNs);
        return $revisionList;
    }
    
    protected function doVersioning() {
        
        $finder = new Finder;
        $finder->files()->name('/\.tar.gz$/')->in(__DIR__ . '/Fixtures/versionables')->sortByName();
        
        $ids = [];
        foreach ($finder as $aFile) {
            list($ns, $discriminator) = explode('_', basename($aFile->getFilename(), ".txt.tar"));
            
            // print("Uploading {$aFile->getRealPath()} with ns: ${ns} and discriminator: ${discriminator}");
            $lastUpload = $this->versioner->version($aFile->getRealPath(), $this->testNs, $discriminator);
            $ids[] = $lastUpload->getId();
        }

        $this->versioner->updateAllRevisions($ns);
    }
}