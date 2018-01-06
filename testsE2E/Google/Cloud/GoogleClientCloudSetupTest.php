<?php

namespace Partridge\Utils\TestsE2E\Google\Cloud;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\Output;
use Partridge\Utils\Google\Cloud\GoogleClientCloudSetup;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * NEVER EVER EVER RUN IN CI OR WHATEVER - THESE ARE FOR PROOFING AND SPEEDING OF WORK
 * AD-HOC. 
 * This just tests that the generated Cloud clients produced by this factory
 * will be authed correctly etc
 */
class GoogleClientCloudSetupTest extends TestCase {
    
    /**
     * @var GoogleClientCloudSetup
     */
    protected $subject;
    /**
     * @var Output
     */
    protected $output;

    protected $testBucket = 'partridge';

    public function setUp() {
        $projectId = file_get_contents(__DIR__ . '/../Fixtures/cloud-project-id.txt'); // - http://bit.ly/2D8cqPl
        $serviceAccountPath = __DIR__ . '/../Fixtures/cloud-service-account.json'; // - http://bit.ly/2BP7opy
        $this->subject = new GoogleClientCloudSetup($projectId, $serviceAccountPath);
    }

    /**
     * Essentially just checks whether the StorageBucket is authed correctly
     */
    public function testStorageBucketList() {
        $bucket = $this->subject->getStorageBucket($this->testBucket);
        $objects = $bucket->objects(); // http://bit.ly/2BOAsh4
        // var_dump($objects);
        $res = iterator_to_array($objects); // this would throw exception if not authed
        $this->assertNotNull($res);
    }
}