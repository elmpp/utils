<?php

namespace Partridge\Utils\tests\Google;

require_once __DIR__.'/../../vendor/autoload.php';

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStreamDirectory;
use Partridge\Utils\Google\DriveVersioner;
use Symfony\Component\Console\Output\Output;
use Partridge\Utils\Google\DriveVersionerMessages;
use Partridge\Utils\Google\DriveVersionerException;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Uploads a given file to Google Drive with versioning.
 *
 * The file's location will be based on:
 *  - in its own directory beneath a well-known directory
 *  - its directory name will be supplied
 *
 * Each file version will have the following metadata:
 *  - date. This is supplied and not defined by its modified/uploaded date etc.
 *
 * Nice to haves:
 *  - List all versions for a file
 */
class DriveVersionerTest extends TestCase
{
    /**
     * @var string
     */
    protected $testNs = 'postgresBackups';
    /**
     * @var string
     */
    protected $driveRootId = '12345678';

    /**
     * @var \Google_Service_Drive_Resource_Files
     */
    protected $filesDriveClient;
    /**
     * @var \Google_Service_Drive_Resource_Revisions
     */
    protected $revisionsDriveClient;
    /**
     * @var DriveVersioner
     */
    protected $subject;
    /**
     * @var \Google_Service_Drive
     */
    protected $driveClient;
    /**
     * @var vfsStreamDirectory
     */
    protected $root;
    /**
     * @var Output
     */
    protected $output;

    public function setUp() {
        $driveClient = $this->getMockBuilder(\Google_Service_Drive::class)
        ->disableOriginalConstructor()
        ->getMock();
        $this->filesDriveClient = $this->getMockBuilder(\Google_Service_Drive_Resource_Files::class)
        ->disableOriginalConstructor()
        ->getMock();
        $this->revisionsDriveClient = $this->getMockBuilder(\Google_Service_Drive_Resource_Revisions::class)
        ->disableOriginalConstructor()
        ->getMock();
        $driveClient->files = $this->filesDriveClient;
        $driveClient->revisions = $this->revisionsDriveClient;
        $this->driveClient = $driveClient;

        // Just sets up a vfs with uploadable file
        $this->root = vfsStream::setup( //
            'uploadDirectory',
            null,
            [
            'versionableFile_2017-01-01.txt' => '2017-01-01',
            'versionableFile_2017-01-02.txt' => '2017-01-02',
            'versionableFile_2017-01-03.txt' => '2017-01-03',
            ]
        );

        $this->subject = new DriveVersioner($this->driveClient, $this->driveRootId);

        $this->subject->setOutput($this->output = new BufferedOutput);
    }

    /**
     * Drive does not listen to our "keepRevisionForever" request when calling files.update
     * We will have to offer a way to update the revisions for a versionable.
     * @return void
     */
    public function testUpdateRevisions() {
        
        $this->createMocksUpToVersionList();

        $revisionTest = function($item) {
            return $item instanceof \Google_Service_Drive_Revision
                && $item->getKeepForever() === true
                // non-writeables
                && $item->getId() === null
                && $item->getKind() === null
                && $item->getMimeType === null
                // etc etc (just ensure is fresh Revision object, basically)
            ;
        };
        
        $this->revisionsDriveClient
        ->expects($this->once()) // ensure we cache the revision listings for a file
        ->method('listRevisions')
        ;

        $this->revisionsDriveClient
        ->expects($this->exactly(2)) // ensure we cache the revision listings for a file
        ->method('update')
        ->withConsecutive(
            [
                'test-versioned-id',
                'revision-id-1',
                $this->callback(
                    $revisionTest
                ),
                [
                ],
            ],
            [
                'test-versioned-id',
                'revision-id-2',
                $this->callback(
                    $revisionTest
                ),
                [
                ],
            ]
        )
        ->will(
            $this->returnArgument(2)
        )
        ;

        $this->subject->updateAllRevisions($this->testNs);

        $this->assertOutputs(
            [
            DriveVersionerMessages::DEBUG_UPDATING_REVISION . "revision-id-1",
            DriveVersionerMessages::DEBUG_UPDATING_REVISION . "revision-id-2",
            ],
            $this->testNs,
            '',
            DriveVersioner::MODE_REVISIONS
        );
    }

    protected function createMocksUpToVersionList() {
        $mockNsDir = new \Google_Service_Drive_DriveFile([
            'id' => 'test-dir-id'
        ]); // the ns directory
        $mockNsDirList = new \Google_Service_Drive_FileList();
        $mockNsDirList->setFiles([$mockNsDir]);
        $mockVersionedFile = new \Google_Service_Drive_DriveFile([
            'id' => 'test-versioned-id'
        ]);
        $mockListForVersioned = new \Google_Service_Drive_FileList();
        $mockListForVersioned->setFiles([$mockVersionedFile]);

        $mockVersionListList = new \Google_Service_Drive_RevisionList;
        $mockVersions = [
            $this->createMockVersion(
                [
                    'id' => 'revision-id-1',
                    'originalFilename' => 'versionableFile_2017-01-01.txt',
                    ]
            ),
                $this->createMockVersion(
                    [
                    'id' => 'revision-id-2',
                    'originalFilename' => 'versionableFile_2017-01-02.txt',
                    ]
                ),
        ];
        $mockVersionListList->setRevisions($mockVersions);
    
        $this->filesDriveClient
        ->method('listFiles')
        ->will($this->onConsecutiveCalls(
            $mockNsDirList,
            $mockListForVersioned
        ));
        $this->revisionsDriveClient
        ->method('listRevisions')
        ->willReturn(
            $mockVersionListList
        );
    }

    /**
     * Duplicate file versioning attempt. We want to waft past these.
     * Relies on the .properties.discriminator field to
     */
    public function testDoesNotCreateNewVersionWithExistentDiscriminator() {
        
        $this->createMocksUpToVersionList();

        $mockDirectoryId = 'this-is-a-test-uuid-for-dir';
        $mockNamespaceDir = new \Google_Service_Drive_DriveFile(); // the ns directory
        $mockNamespaceDir->id = $mockDirectoryId;
        $mockNamespaceDir->parents = [$this->driveRootId];
        $mockNamespaceDirList = new \Google_Service_Drive_FileList();
        $mockNamespaceDirList->setFiles([$mockNamespaceDir]);
        
        $mockVersionedFile = new \Google_Service_Drive_DriveFile();
        $mockVersionedFile->id = 'this-is-a-test-uuid-for-versioned-file';
        $mockVersionedFile->name = DriveVersioner::VERSIONED_FILENAME;
        $mockVersionedFile->parents = [$mockDirectoryId];
        $mockVersionedFileList = new \Google_Service_Drive_FileList();
        $mockVersionedFileList->setFiles([$mockVersionedFile]);

        $mockNewVersionedFile = clone $mockVersionedFile;
  
        $this->filesDriveClient
          ->method('listFiles')
          ->will($this->onConsecutiveCalls($mockNamespaceDirList, $mockVersionedFileList));
        ;

        $this->filesDriveClient
          ->expects($this->never())
          ->method('update')
        ;

        $this->subject->version($this->root->url().'/versionableFile_2017-01-01.txt', $this->testNs, '2017-01-01'); // matches response of versionList
        $this->assertOutputs(
            [
                DriveVersionerMessages::DEBUG_NS_DIR_FOUND,
                DriveVersionerMessages::DEBUG_VERSIONED_FILE_FOUND,
                DriveVersionerMessages::DEBUG_VERSION_FILE_ALREADY_EXISTS,
            ],
            $this->testNs,
            '2017-01-01'
        );
    }
    
    public function testListVersions() {
        $mockNsDir = new \Google_Service_Drive_DriveFile([
            'id' => 'test-dir-id'
        ]); // the ns directory
        $mockNsDirList = new \Google_Service_Drive_FileList();
        $mockNsDirList->setFiles([$mockNsDir]);
        $mockVersionedFile = new \Google_Service_Drive_DriveFile([
            'id' => 'test-versioned-id'
        ]);
        $mockListForVersioned = new \Google_Service_Drive_FileList();
        $mockListForVersioned->setFiles([$mockVersionedFile]);

        $mockVersionListList = new \Google_Service_Drive_RevisionList;
        $mockVersions = [
            $this->createMockVersion(['originalFilename' => 'versionableFile_2017-01-01.txt']),
            $this->createMockVersion(['originalFilename' => 'versionableFile_2017-01-02.txt']),
        ];
        $mockVersionListList->setRevisions($mockVersions);
    
        $this->filesDriveClient
        ->method('listFiles')
        ->will($this->onConsecutiveCalls(
            $mockNsDirList,
            $mockListForVersioned
        ));
        $this->revisionsDriveClient
        ->expects($this->once())
        ->method('listRevisions')
        ->with(
            'test-versioned-id',
            $this->callback(
                // http://bit.ly/2ioGcpC
                function ($opts) {
                    $fields = $opts['fields'] ?? null;
                    if (!($opts['fields'] ?? null)) {
                        return false;
                    }
                    $fields = explode(',', $opts['fields']);
                    return false !== array_search('kind', $fields)
                        && false !== array_search('revisions', $fields)
                    ;
                }
            )
        )
        ->willReturn(
            $mockVersionListList
        );
        
        $revisions = $this->subject->list($this->testNs);
        $this->assertCount(2, $revisions->revisions);
        $this->assertTrue($revisions->revisions[0]->keepForever);

        $this->assertOutputs(
            [
                DriveVersionerMessages::DEBUG_VERSIONED_FILE_FOUND,
            ],
            $this->testNs,
            '',
            DriveVersioner::MODE_REVISIONS
        );
    }
    public function testListVersionsFileNotExist() {
        $mockNsDir = new \Google_Service_Drive_DriveFile([
            'id' => 'mock-ns-dir-id'
        ]); // the ns directory
        $mockNsDirList = new \Google_Service_Drive_FileList();
        $mockNsDirList->setFiles([$mockNsDir]);
        $mockEmptyListForVersioned = new \Google_Service_Drive_FileList();

        $this->filesDriveClient
        ->method('listFiles')
        ->will($this->onConsecutiveCalls(
            $mockNsDirList,
            $mockEmptyListForVersioned
        ));

        $this->expectException(DriveVersionerException::CLASS);
        $this->expectExceptionMessageRegExp('/^'.DriveVersionerMessages::DRIVE_CANNOT_LIST_VERSIONED_FILE.'.*$/');
        
        try {
            $this->subject->list($this->testNs);
        } catch (\Exception $e) {
            $this->assertOutputs(
                [
                    DriveVersionerMessages::DRIVE_CANNOT_LIST_VERSIONED_FILE,
                ],
                $this->testNs,
                '',
                DriveVersioner::MODE_REVISIONS
            );
            throw $e;
        }
    }

    public function testFileNotExist() {
        $this->expectException(DriveVersionerException::CLASS);
        $this->expectExceptionMessageRegExp('/^'.DriveVersionerMessages::VERSIONABLE_FILE_NOT_READABLE.'.*$/');
        $this->expectExceptionMessageRegExp('|.*not readable: '.$this->root->url().'/inexistentFile.txt'.'.*$|');
        
        try {
            $this->subject->version($this->root->url().'/inexistentFile.txt', $this->testNs, '2017-01-01');
        } catch (\Exception $e) {
            $this->assertOutputs(
                [
                    DriveVersionerMessages::VERSIONABLE_FILE_NOT_READABLE,
                ],
                $this->testNs,
                '2017-01-01'
            );
            throw $e;
        }
    }
    
    public function testClientAuthIncorrect() {
        $this->filesDriveClient
        ->method('listFiles')
        ->will($this->throwException(
            $this->createGoogleServiceException(
                "The user does not have sufficient permissions for this file.",
                403,
                'insufficientFilePermissions'
            )
        ));
        
        $this->expectException(DriveVersionerException::CLASS);
        $this->expectExceptionMessageRegExp('/^'.DriveVersionerMessages::AUTHORISATION_FAIL.'.*$/');
        
        try {
            $this->subject->version($this->root->url().'/versionableFile_2017-01-01.txt', $this->testNs, '2017-01-01');
        } catch (\Exception $e) {
            $this->assertOutputs(
                [
                    DriveVersionerMessages::AUTHORISATION_FAIL,
                ],
                $this->testNs,
                '2017-01-01'
            );
            throw $e;
        }
    }

    public function testRootDirNonExistent() {
        $mockNsDirList = new \Google_Service_Drive_FileList();

        $this->filesDriveClient
        ->method('listFiles')
        ->willReturn($mockNsDirList);
        
        $this->filesDriveClient
        ->expects($this->once())
        ->method('create')
        ->will(
            $this->throwException(
                $this->createGoogleServiceException(
                    "File not found: {$this->driveRootId}.",
                    404,
                    'parentNotAFolder'
                )
            )
        );
        
        $this->expectException(DriveVersionerException::CLASS);
        $this->expectExceptionMessageRegExp('/^'.DriveVersionerMessages::DRIVE_ROOT_NOT_FOUND.'.*$/');
        $this->expectExceptionMessageRegExp("|.*{$this->driveRootId}.*$|");
        
        try {
            $this->subject->version($this->root->url().'/versionableFile_2017-01-01.txt', $this->testNs, '2017-01-01');
        } catch (\Exception $e) {
            $this->assertOutputs(
                [
                    DriveVersionerMessages::DRIVE_ROOT_NOT_FOUND
                ],
                $this->testNs,
                '2017-01-01'
            );
            throw $e;
        }
    }

    public function testCreatesDirectoryWhenNotExistent() {
        $mockNsDirList = new \Google_Service_Drive_FileList();

        $this->filesDriveClient
        ->method('listFiles')
        ->willReturn($mockNsDirList);
        $this->filesDriveClient
        ->expects($this->atLeastOnce()) // create() for dir and versioned file
        ->method('create')
        ->withConsecutive(
            $this->logicalAnd(
                $this->isInstanceOf(\Google_Service_Drive_DriveFile::class), // https://developers.google.com/drive/v3/web/folder#creating_a_folder
                $this->callback(
                    function ($dirObject) {
                        return $dirObject->name == $this->testNs
                            && count($dirObject->parents) === 1
                            && $dirObject->parents[0] == $this->driveRootId;
                    }
                )
            ),
            $this->callback( // the $opts when create()
                function ($opts) {
                    return $opts['mimeType'] == DriveVersioner::MIME_DIR
                    ;
                }
            )
        )
        ->will($this->returnArgument(0));
        $this->filesDriveClient
          ->expects($this->never())
          ->method('update')
          ->willReturn(new \Google_Service_Drive_DriveFile)
        ;

        $this->subject->version($this->root->url().'/versionableFile_2017-01-01.txt', $this->testNs, '2017-01-01');
        $this->assertOutputs(
            [
                DriveVersionerMessages::DEBUG_NS_DIR_CREATED,
            ],
            $this->testNs,
            '2017-01-01'
        );
    }

    public function testCreatesVersionedFileWhenNotExistent() {
        $mockDirectoryId = 'this-is-a-test-uuid-for-dir';
        $mockNamespaceDir = new \Google_Service_Drive_DriveFile(); // the ns directory
        $mockNamespaceDir->id = $mockDirectoryId;
        $mockNamespaceDirList = new \Google_Service_Drive_FileList();
        $mockNamespaceDirList->setFiles([$mockNamespaceDir]);

        $this->filesDriveClient
        ->method('listFiles')
        ->will($this->onConsecutiveCalls($mockNamespaceDirList, new \Google_Service_Drive_FileList()));
        $this->filesDriveClient
        ->expects($this->once()) // create() for dir and versioned file
        ->method('create')
        ->willReturn(new \Google_Service_Drive_DriveFile())
        ->with(
            $this->logicalAnd(
                $this->callback(
                    function ($filesObject) {
                        return ($filesObject instanceof \Google_Service_Drive_DriveFile) // http://bit.ly/2iJrYDz
                            // && $filesObject-keepRevisionForever == true
                        ;
                    }
                ),
                $this->callback(
                    function ($filesObject) use ($mockDirectoryId) {
                        return
                            substr($filesObject->mimeType, 0, 10) == 'text/plain' // could have charset on there
                            && count($filesObject->properties) === 2
                            && $filesObject->properties['discriminator'] == '2017-01-01'
                            && $filesObject->properties['id'] == md5($this->testNs . '2017-01-01')
                            && $filesObject->originalFilename == 'versionableFile_2017-01-01.txt'
                            && $filesObject->keepRevisionForever == true
                            && $filesObject->uploadType == 'multipart'
                            && $filesObject->name == DriveVersioner::VERSIONED_FILENAME
                            && count($filesObject->parents) === 1
                            && $filesObject->parents[0] == $mockDirectoryId
                        ;
                    }
                )
            ),
            $this->callback( // the $opts when create()
                function ($opts) {
                    return $opts['data'] == '2017-01-01';
                }
            )
        );
        $this->filesDriveClient
          ->expects($this->never())
          ->method('update')
          ->willReturn(new \Google_Service_Drive_DriveFile)
        ;

        $this->subject->version($this->root->url().'/versionableFile_2017-01-01.txt', $this->testNs, '2017-01-01');
        $this->assertOutputs(
            [
                DriveVersionerMessages::DEBUG_NS_DIR_FOUND,
                DriveVersionerMessages::DEBUG_VERSIONED_FILE_CREATED,
            ],
            $this->testNs,
            '2017-01-01'
        );
    }
      
    public function testCreatesNewVersion() {

        $this->createMocksUpToVersionList();

        $this->filesDriveClient
        ->expects($this->once())
        ->method('update')
        ->with(
            'test-versioned-id',
            $this->callback(
                function ($filesObject) {
                    return
                      substr($filesObject->mimeType, 0, 10) == 'text/plain' // could have charset on there
                      && count($filesObject->properties) === 2
                      && $filesObject->properties['discriminator'] == '2017-01-03'
                      && $filesObject->properties['id'] == md5($this->testNs . '2017-01-03')
                      && $filesObject->originalFilename == 'versionableFile_2017-01-03.txt'
                      && $filesObject->keepRevisionForever == true
                      && $filesObject->uploadType == 'multipart'
                      && $filesObject->name == DriveVersioner::VERSIONED_FILENAME
                    ;
                }
            ),
            [
              'data' => '2017-01-03', // uploads file's data
            ]
        )
        ->will($this->returnArgument(1))
        ;

        $this->subject->version($this->root->url().'/versionableFile_2017-01-03.txt', $this->testNs, '2017-01-03');
        $this->assertOutputs(
            [
              DriveVersionerMessages::DEBUG_NS_DIR_FOUND,
              DriveVersionerMessages::DEBUG_VERSIONED_FILE_FOUND,
              DriveVersionerMessages::DEBUG_NEW_VERSION_CREATED,
            ],
            $this->testNs,
            '2017-01-03'
        );
    }

    /**
     *  - // https://drive.google.com/file/d/1pFr9QhtZtWYgcjxWD5NgCUKZ42Hq6D42/view?usp=sharing
     * @param array $fields
     * @return \Google_Service_Drive_Revision
     */
    protected function createMockVersion(array $fields = []): \Google_Service_Drive_Revision {
        $withDefaults = array_replace_recursive(
            [
                'kind' => 'drive#revision',
                'id' => '0B8xNn0n8vI8YS2prU00vRnJvNWoxWmdNc25WVkFCcWk5' . rand(0, 100000),
                'mimeType' => 'application/x-tar',
                'modifiedTime' => '2017-12-04T20:19:52.122Z',
                'keepForever' => true,
                'published' => false,
                'lastModifyingUser' =>
                array (
                  'kind' => 'drive#user',
                  'displayName' => 'Matthew Penrice',
                  'photoLink' => 'https://lh5.googleusercontent.com/-MrIWaGeRiL8/AAAAAAAAAAI/AAAAAAAAAW8/5o73TOjw_Y4/s64/photo.jpg',
                  'me' => true,
                  'permissionId' => '16986143399669889879',
                  'emailAddress' => 'matthew.penrice@gmail.com',
                ),
                'originalFilename' => 'partridge_2017-12-03.txt.tar',
                'md5Checksum' => '6a623242d5a6c07f2736d3d9a268d8a8',
                'size' => '2048',
            ],
            $fields
        );
        return new \Google_Service_Drive_Revision($withDefaults);
    }

    protected function createGoogleServiceException($message, $code, $reason): \Google_Service_Exception {
        $errors = [
            [
                'reason' => $reason,
                'message' => $message,
                'domain' => 'global'
            ]
        ];
        return new \Google_Service_Exception($message, $code, null, $errors); // https://drive.google.com/file/d/1_-NbpKQiGWx4MAYmxlZXymy6kbc7rvbM/view
    }

    protected function assertOutputs(array $messages, String $ns, String $discriminator, $mode = DriveVersioner::MODE_VERSION): void {
        $allMessages = explode(PHP_EOL, $this->output->fetch());
        $nonOutputtedMessages = array_filter($messages, function ($item) use ($allMessages, $ns, $discriminator, $mode) {
            $messageWithExtras = " | ${mode} : ${ns} : ${discriminator} | $item";
            return (false === array_search($messageWithExtras, $allMessages));
        });
        $this->assertEquals([], $nonOutputtedMessages);
    }
}
