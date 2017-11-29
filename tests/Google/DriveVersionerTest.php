<?php

namespace Partridge\Utils\tests\Google;

require_once __DIR__.'/../../vendor/autoload.php';

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStreamDirectory;
use Partridge\Utils\Google\DriveVersioner;

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
  protected $testName = 'postgresBackups';
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

  public function setUp()
  {
    // $this->client = $this->createMock(Google_Client);
    $driveClient = $this->getMockBuilder(\Google_Service_Drive::class)
  ->disableOriginalConstructor()
  ->getMock()
  ;
    $this->filesDriveClient = $this->getMockBuilder(\Google_Service_Drive_Resource_Files::class)
  ->disableOriginalConstructor()
  // ->setMethods(['listFiles', 'create'])
  ->getMock()
  ;
    $this->revisionsDriveClient = $this->getMockBuilder(\Google_Service_Drive_Resource_Revisions::class)
  ->disableOriginalConstructor()
  // ->setMethods(['list', 'get'])
  ->getMock()
  ;
    $driveClient->files = $this->filesDriveClient;
    $driveClient->revisions = $this->revisionsDriveClient;
    $this->driveClient = $driveClient;

    // Just sets up a vfs with uploadable file
    $this->root = vfsStream::setup('uploadDirectory', null, [
  'versionableFile_2017-01-01.txt' => '2017-01-01',
  'versionableFile_2017-01-02.txt' => '2017-01-02',
  ]);

    $this->subject = new DriveVersioner($this->driveClient, $this->driveRootId);
  }

  public function testFileNotExist()
  {
  }

  public function testClientAuthIncorrect()
  {
  }

  public function testCreatesDirectoryWhenNotExistent()
  {
    $this->filesDriveClient
  ->method('listFiles')
  ->willReturn(null)
  ;
    $this->filesDriveClient
  ->expects($this->atLeastOnce()) // create() for dir and versioned file
  ->method('create')
  ->with(
    $this->logicalAnd(
    $this->isInstanceOf(\Google_Service_Drive_DriveFile::class), // https://developers.google.com/drive/v3/web/folder#creating_a_folder
    $this->callback(function ($filesObject) {
      return $filesObject->mimeType == DriveVersioner::MIME_DIR;
    }),
    $this->callback(function ($filesObject) {
      return $filesObject->name == $this->testName;
    })
    )
  )
  ->willReturn(new \Google_Service_Drive_DriveFile())
  ;

    $this->subject->version($this->root->url().'/versionableFile_2017-01-01.txt', $this->testName);
  }

  public function testCreatesVersionedFileWhenNotExistent()
  {
    $mockDirectoryId = 'this-is-a-test-uuid-for-dir';
    $mockDirectoryDriveFile = new \Google_Service_Drive_DriveFile(); // the ns directory
    $mockDirectoryDriveFile->id = $mockDirectoryId;
    $mockDirectoryFileList = new \Google_Service_Drive_FileList();
    $mockDirectoryFileList->setFiles($mockDirectoryDriveFile);

    $this->filesDriveClient
    ->method('listFiles')
    ->will($this->onConsecutiveCalls($mockDirectoryFileList, new \Google_Service_Drive_FileList()))
  ;
    $this->filesDriveClient
    ->expects($this->once()) // create() for dir and versioned file
    ->method('create')
    ->withConsecutive(
    [],
    [
    $this->logicalAnd(
      $this->isInstanceOf(\Google_Service_Drive_DriveFile::class), // https://developers.google.com/drive/v3/web/folder#creating_a_folder
      $this->callback(function ($filesObject) {
        return $filesObject->parents == [$mockDirectoryId];
      }),
      $this->callback(function ($filesObject) {
        return $filesObject->name == "{$this->testName}";
      })
    ),
    ]
  );
    $this->subject->version($this->root->url().'/versionableFile_2017-01-01.txt', $this->testName);
  }

  public function testCreatesNewVersion()
  {
  }
}
