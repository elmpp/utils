<?php

namespace Partridge\Utils\Tests\Google;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Google\Cloud\Storage\Bucket;
use org\bovigo\vfs\vfsStreamDirectory;
use Google\Cloud\Storage\StorageObject;
use Google\Cloud\Storage\ObjectIterator;
use Partridge\Utils\Google\DriveVersioner\DriveVersioner;
use Google\Cloud\Core\Iterator\PageIterator;
use Partridge\Utils\Google\GCEToDriveBackup;
use Partridge\Utils\Tests\Traits\MockingTrait;
use Partridge\Utils\Google\DriveVersionerMessages;
use Symfony\Component\Console\Output\BufferedOutput;
use Google\Cloud\Tests\Unit\Core\Iterator\PageIteratorTest;

class GCEToDriveBackupTest extends TestCase {

    use MockingTrait;

    /**
     * @var VfsStreamDirectory
     */
    protected $root;

    /**
     * @var DriveVersioner
     */
    protected $driveVersionerMock;

    /**
     * @var Bucket
     */
    protected $cloudStorageBucketMock;

    /**
     * @var BufferedOutput
     */
    protected $output;

    /**
     * @var string
     */
    protected $testNs = 'partridge';
    

    public function setUp() {

        // Just sets up a vfs with 3 files that have been downloaded correctly
        $this->root = vfsStream::setup( //
            'tmpDir',
            null,
            [
            'versionableFile_2017-01-01.txt' => '2017-01-01',
            'versionableFile_2017-01-02.txt' => '2017-01-02',
            'versionableFile_2017-01-03.txt' => '2017-01-03',
            ]
        );

        $this->driveVersionerMock = $this->getMockBuilder(DriveVersioner::class)
        ->disableOriginalConstructor()
        ->getMock();
        $this->cloudStorageBucketMock = $this->getMockBuilder(Bucket::class)
        ->disableOriginalConstructor()
        ->getMock();

        $this->output = new BufferedOutput;
    }

    /**
     * @dataProvider dataProvider
     */
    public function testCorrect(array $meta, \Closure $selectFilter, \Closure $backupFilter, $expectedMessages = [], $dryRun = false) {

        $driveVersionerVersionWith = [];
        $cloudStorageBucketReturn = [];

        foreach ($meta as $aMeta) {
            $storageObjectMock = $this->getMockBuilder(StorageObject::CLASS)
            ->disableOriginalConstructor()
            ->getMock();
            $storageObjectMock->info = $aMeta;


            if ($aMeta['versionable']) {
                $driveVersionerVersionWith[] = [
                    $this->root->url() . '/' . $aMeta['filename'],
                    'versionableFile', // assume always be this when versionable
                    str_replace('versionableFile_', '', basename($aMeta['filename'], '.txt'))
                ];
            }
            
            $storageObjectMock
                ->expects($aMeta['deleteable'] ? $this->once() : $this->never())
                ->method('delete')
            ;

            $cloudStorageBucketReturn[] = $storageObjectMock;
        }

        if ($dryRun) {
            $this->driveVersionerMock
            ->expects($this->never())
            ->method('version')
            ;
        }
        else {
            $this->driveVersionerMock
            ->expects($this->exactly(count($driveVersionerVersionWith)))
            ->method('version')
            ->withConsecutive(new \PHPUnit_Framework_MockObject_Stub_ConsecutiveCalls($driveVersionerVersionWith))
            ;

            if ($aMeta['versionable']) {
                $storageObjectMock
                ->expects($this->once())
                ->method('downloadToFile')
                ->with($this->root->url() . '/' . $aMeta['filename'], [])
                ;
            }
        }

        $this->cloudStorageBucketMock
        ->expects($this->once())
        ->method('objects')
        ->willReturn($this->createObjectIteratorMock($cloudStorageBucketReturn))
        ;

        $subject = (new GCEToDriveBackup(
            $this->cloudStorageBucketMock, 
            $this->driveVersionerMock, 
            [
                'dry-run' => $dryRun,
                'tmp-dir' => $this->root->url(),
                'ns' => $this->testNs,
            ]
            ))
            ->setSelectFilter($selectFilter)
            ->setBackupFilter($backupFilter)
            ->setOutput($this->output)
            ->run()
        ;

        $this->assertOutputs(
            $expectedMessages
        );
    }

    public function testTmpDirNotWritable() {

    }
    public function testDiscriminatorRegexFail() {

    }
    
    public function dataProvider() {
        
        $standardSelectFilter = function(array $files) {
            return array_filter($files, function($file) {
                $info = $file->info;
                return (bool) preg_match('/^versionableFile_.*/', $info['filename']); // top level directory
            });
        };
        $standardBackupFilter = function(array $files) {
            return $files;
        };
        
        return [
            
            // nice easy scenario
            [
                // meta structure to build the mocks for the Google\Cloud\Storage\Bucket::Objects call
                [
                    [
                        'filename' => 'versionableFile_2017-01-01.txt', // the gcloud bucket filepath
                        'versionable' => true, // whether to expect it to be versioned
                        'deleteable' => true, // whether it should be deleted
                        ]
                    ],
                // the select filter
                $standardSelectFilter,
                // the backup filter
                $standardBackupFilter,
                // the messages expected
                [
                    GCEToDriveBackup::MESSAGE_DEBUG_DRIVE_BACKING_UP . "1",
                    GCEToDriveBackup::MESSAGE_DEBUG_GCE_DELETING . "1",
                ],
                // whether in dry run or not,
                false,
            ],
            
            // real mix of files in the bucket
            [
                [
                    [
                        'filename' => 'versionableFile_2017-01-01.txt',
                        'versionable' => true,
                        'deleteable' => true,
                    ],
                    [
                        'filename' => 'versionableFile_2017-01-02.txt',
                        'versionable' => true,
                        'deleteable' => true,
                    ],
                    [
                        'filename' => 'versionableFileWRONG_2017-01-03.txt',
                        'versionable' => false,
                        'deleteable' => false,
                    ],
                    [
                        'filename' => 'versionableFile_2017-01-03.txt',
                        'versionable' => true,
                        'deleteable' => true,
                    ],
                    [
                        'filename' => '/nestedfile/versionableFile_2017-01-01.txt',
                        'versionable' => false,
                        'deleteable' => false,
                    ],
                ],
                $standardSelectFilter,
                $standardBackupFilter,
                [
                    GCEToDriveBackup::MESSAGE_DEBUG_DRIVE_BACKING_UP . "3",
                    GCEToDriveBackup::MESSAGE_DEBUG_GCE_DELETING . "3",
                ],
                false,
            ],
            
            // lots of files but only backing up certain ones. (i.e. odd ones but always including latest)
            [
                [
                    [
                        'filename' => 'versionableFile_2017-01-01.txt',
                        'versionable' => true,
                        'deleteable' => true,
                    ],
                    [
                        'filename' => 'versionableFile_2017-01-02.txt',
                        'versionable' => false,
                        'deleteable' => true,
                    ],
                    [
                        'filename' => 'versionableFile_2017-01-03.txt',
                        'versionable' => true,
                        'deleteable' => true,
                    ],
                    [
                        'filename' => 'versionableFile_2017-01-04.txt',
                        'versionable' => false,
                        'deleteable' => true,
                    ],
                    [
                        'filename' => 'versionableFile_2017-01-05.txt',
                        'versionable' => true,
                        'deleteable' => true,
                    ],
                    [
                        'filename' => 'versionableFile_2017-01-06.txt',
                        'versionable' => true,
                        'deleteable' => true,
                    ],
                ],
                $standardSelectFilter,
                function(array $files) {
                    $filtered = array_filter($files, function($file, $index) use ($files) {
                        if ($index == (count($files) - 1)) {
                            return true; // always keep last
                        }
                        
                        $info = $file->info;
                        preg_match('/versionableFile_([^.]+)\..+$/', $info['filename'], $parts);
                        if (!$parts[1] ?? null) {
                            throw new \RuntimeException("Could not parse discriminator in backup filter");
                        }
                        return intval(substr($parts[1], -1)) & 1; // (test for odd & 1)
                    }, ARRAY_FILTER_USE_BOTH);
                    // var_dump($filtered); die;
                    return $filtered;
                },
                [
                    GCEToDriveBackup::MESSAGE_DEBUG_DRIVE_BACKING_UP . "4",
                    GCEToDriveBackup::MESSAGE_DEBUG_GCE_DELETING . "6",
                ],
                false,
            ],

            // dry run
            [
                [
                    [
                        'filename' => 'versionableFile_2017-01-01.txt',
                        'versionable' => false,
                        'deleteable' => false,
                    ]
                ],
                $standardSelectFilter,
                $standardBackupFilter,
                [
                    GCEToDriveBackup::MESSAGE_DEBUG_DRIVE_BACKING_UP . "1",
                    GCEToDriveBackup::MESSAGE_DEBUG_GCE_DELETING . "1",
                ],
                true,
            ],
        ];
    }

    /**
     * Checks all the messages outputted. Does a stristr
     * @param array $messages
     * @return void
     */
    protected function assertOutputs(array $messages): void {
        $allMessages = explode(PHP_EOL, $this->output->fetch());
        $nonOutputtedMessages = array_filter($messages, function ($item) use ($allMessages) {
            foreach ($allMessages as $aMessage) {
                if (false !== stristr($aMessage, $item)) return false;
            }
            return true;
        });
        $this->assertEquals([], $nonOutputtedMessages);
    }
    
    /**
     * Google Cloud object responses are wrapped in nested Iterators (PageIterator and ItemIterator) which can be tricky
     * to convincingly construct for test purposes
     * 
     *  - http://googlecloudplatform.github.io/google-cloud-php/#/docs/google-cloud/v0.47.0/storage/objectiterator
     *  - https://cloud.google.com/storage/docs/json_api/v1/objects/list#response
     * 
     * @see PageIteratorTest
     * @param array $objects
     */
    protected function createObjectIteratorMock(array $objects): ObjectIterator {

        $mock = $this->getMockBuilder(ObjectIterator::class)
        ->disableOriginalConstructor()
        ->getMock();
        $this->mockIterator($mock, $objects); // this will mock out the iterator behaviour based on the objects
        return $mock;
    }
}