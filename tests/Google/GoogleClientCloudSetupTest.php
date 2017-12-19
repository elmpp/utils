<?php

namespace Partridge\Utils\Tests\Google;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Partridge\Utils\Google\GoogleClientCloudSetup;

require_once __DIR__.'/../../vendor/autoload.php';

/**
 * Simple test for the google cloud client setup
 */
class GoogleClientCloudSetupTest extends TestCase
{
    // public function testWithoutProject() {
        
    //     $subject = new GoogleClientCloudSetup;

    //     $this->expectException(\RuntimeException::CLASS);
    //     $this->expectExceptionMessageRegExp('/^' . GoogleClientCloudSetup::MESSAGE_MISSING_ENV . '.*$/');
    //     $bucket = $subject->getStorageBucket('partridge');
    // }

    public function testGetStorageBucket() {
        $subject = new GoogleClientCloudSetup(file_get_contents(__DIR__.'/Fixtures/cloud-project-id.txt'));
        $bucket = $subject->getStorageBucket('partridge');
        $this->assertInstanceOf(Bucket::CLASS, $bucket);
    }
}
