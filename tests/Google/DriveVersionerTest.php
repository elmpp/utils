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
        // ->setMethods(['listFiles', 'create'])
        ->getMock();
        $this->revisionsDriveClient = $this->getMockBuilder(\Google_Service_Drive_Resource_Revisions::class)
        ->disableOriginalConstructor()
        // ->setMethods(['list', 'get'])
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
            ]
        );

        $this->subject = new DriveVersioner($this->driveClient, $this->driveRootId);

        $this->subject->setOutput($this->output = new BufferedOutput);
    }

    public function testFileNotExist() {
        $this->expectException(DriveVersionerException::CLASS);
        $this->expectExceptionMessageRegExp('/^'.DriveVersionerMessages::VERSIONABLE_FILE_NOT_READABLE.'.*$/');
        $this->expectExceptionMessageRegExp('|.*not readable: '.$this->root->url().'/inexistentFile.txt'.'.*$|');
        
        try {
            $this->subject->version($this->root->url().'/inexistentFile.txt', $this->testNs, '2017-01-01');
        }
        catch (\Exception $e) {
            $this->assertOutputs([
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
            new \Google_Exception( // https://drive.google.com/file/d/1_-NbpKQiGWx4MAYmxlZXymy6kbc7rvbM/view?usp=sharing
                "The user does not have sufficient permissions for this file.",
                403
            )
        ));
        
        $this->expectException(DriveVersionerException::CLASS);
        $this->expectExceptionMessageRegExp('/^'.DriveVersionerMessages::AUTHORISATION_FAIL.'.*$/');
        
        try {
            $this->subject->version($this->root->url().'/versionableFile_2017-01-01.txt', $this->testNs, '2017-01-01');
        }
        catch (\Exception $e) {
            $this->assertOutputs([
                    DriveVersionerMessages::AUTHORISATION_FAIL,
                ],
                $this->testNs, 
                '2017-01-01'
            );
            throw $e;
        }
    }
    
    public function testRootDirNotExistent() {
        $this->filesDriveClient
        ->method('listFiles')
        ->willReturn(null);
        
        $this->filesDriveClient
        ->expects($this->once())
        ->method('create')
        ->will($this->throwException(
            new \Google_Exception( // https://drive.google.com/file/d/18bduPSEZoh151nRV24PDIQDYiOTlSzEo/view?usp=sharing
                "File not found: {$this->driveRootId}.",
                404
            )
        ));
        
        $this->expectException(DriveVersionerException::CLASS);
        $this->expectExceptionMessageRegExp('/^'.DriveVersionerMessages::DRIVE_ROOT_NOT_FOUND.'.*$/');
        $this->expectExceptionMessageRegExp("|.*{$this->driveRootId}.*$|");
        
        try {
            $this->subject->version($this->root->url().'/versionableFile_2017-01-01.txt', $this->testNs, '2017-01-01');
        }
        catch (\Exception $e) {
            $this->assertOutputs([
                    DriveVersionerMessages::DRIVE_ROOT_NOT_FOUND
                ],
                $this->testNs, 
                '2017-01-01'
            );
            throw $e;
        }
    }

    /**
     * Duplicate file versioning attempt. We want to waft past these.
     * Relies on the .properties.discriminator field to 
     */
    public function testDoesNotCreateNewVersionWithExistentDiscriminator() {
        
    }

    public function testCreatesDirectoryWhenNotExistent() {
        $this->filesDriveClient
        ->method('listFiles')
        ->willReturn(null);
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
                    return $opts['mimeType'] == DriveVersioner::MIME_DIR;
                }
            )
        )
        ->will($this->returnArgument(0));
        $this->filesDriveClient
          ->expects($this->once())
          ->method('update')
          ->willReturn(new \Google_Service_Drive_DriveFile)
        ;

        $this->subject->version($this->root->url().'/versionableFile_2017-01-01.txt', $this->testNs, '2017-01-01');
        $this->assertOutputs([
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
                $this->isInstanceOf(\Google_Service_Drive_DriveFile::class), // http://bit.ly/2iJrYDz
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
          ->expects($this->once())
          ->method('update')
          ->willReturn(new \Google_Service_Drive_DriveFile)
        ;

        $this->subject->version($this->root->url().'/versionableFile_2017-01-01.txt', $this->testNs, '2017-01-01');
        $this->assertOutputs([
                DriveVersionerMessages::DEBUG_NS_DIR_FOUND,
                DriveVersionerMessages::DEBUG_VERSIONED_FILE_CREATED,
            ],
            $this->testNs, 
            '2017-01-01'    
        );
      }
      
      public function testCreatesNewVersion() {
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
        $this->filesDriveClient
          ->expects($this->once())
          ->method('update')
          ->with(
            $mockVersionedFile->id, 
            $mockNewVersionedFile, 
            [
            //   'data' => '2017-01-02', // upload's second file's data
                'mimeType' => 'text/plain; charset=us-ascii', // should inspect the file's mimetype
                'uploadType' => 'multipart', // allows file + metadata in one
                'originalFilename' => 'versionableFile_2017-01-02.txt',
                'properties' => [
                  'discriminator' => '2017-01-02', // will record this in the public metadata
                  'id' => md5($this->testNs . '2017-01-02')
                ],
                'keepRevisionForever' => true, // http://bit.ly/2jGtAuu
            ]
          )
          ->will($this->returnArgument(1))
        ;

        $this->subject->version($this->root->url().'/versionableFile_2017-01-02.txt', $this->testNs, '2017-01-02');
        $this->assertOutputs([
                DriveVersionerMessages::DEBUG_NS_DIR_FOUND,
                DriveVersionerMessages::DEBUG_VERSIONED_FILE_FOUND,
                DriveVersionerMessages::DEBUG_NEW_VERSION_CREATED,
            ],
            $this->testNs, 
            '2017-01-02'
        );
    }

    protected function assertOutputs(array $messages, String $ns, String $discriminator): void {
        $allMessages = explode(PHP_EOL, $this->output->fetch());
        $nonOutputtedMessages = array_filter($messages, function($item) use ($allMessages, $ns, $discriminator) {
            $messageWithExtras = " | ${ns} : ${discriminator} | $item";
            return (FALSE === array_search($item, $allMessages));
        });
        $this->assertEquals([], $nonOutputtedMessages);
    }
}
