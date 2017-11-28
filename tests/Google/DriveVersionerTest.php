<?php

namespace Partridge\Utils\Tests\Google;

require_once __DIR__ . '/../../vendor/autoload.php';

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStreamDirectory;
use Partridge\Utils\Google\DriveVersioner;

/**
 * Uploads a given file to Google Drive with versioning
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
 * 
 */
class DriveVersionerTest extends TestCase {

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

  public function setUp() {
    
    // $this->client = $this->createMock(Google_Client);
    $driveClient = $this->createMock(\Google_Service_Drive::CLASS);
    $this->filesDriveClient = $this->getMockBuilder(\Google_Service_Drive_Resource_Files::CLASS)
      ->disableOriginalConstructor()
      ->setMethods(['listFiles', 'create'])
      ->getMock()
      ;
      $this->revisionsDriveClient = $this->getMockBuilder(\Google_Service_Drive_Resource_Revisions::CLASS)
      ->disableOriginalConstructor()
      ->setMethods(['list', 'get'])
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

  public function testFileNotExist() {
  }
  public function testClientAuthIncorrect() {
  }
  public function testCreatesDirectoryWhenNotExistent() {
    
    $this->filesDriveClient
      ->method('listFiles')
      ->willReturn(null)
    ;
    $this->filesDriveClient
      ->expects($this->atLeastOnce()) // create() for dir and versioned file
      ->method('create')
      ->with(
        $this->isInstanceOf(Google_Service_Drive_DriveFile::CLASS), // https://developers.google.com/drive/v3/web/folder#creating_a_folder
        $this->callback(function($filesObject){
          return $filesObject->mimeType == DriveVersioner::MIME_DIR;
        }),
        $this->callback(function($filesObject){
          return $filesObject->name == $this->testName;
        })
      )
    ;

    $this->subject->version('/uploadDirectory/versionableFile_2017-01-01.txt', $this->testName);
  }
  public function testCreatesVersionedFileWhenNotExistent() {
    
    $mockDirectoryId = 'this-is-a-test-uuid-for-dir';
    $mockDirectoryDriveFile = new Google_Service_Drive_DriveFile;
    $mockDirectoryDriveFile->id = $mockDirectoryId;
    $mockDirectoryFileList = new Google_Service_Drive_FileList;
    $mockDirectoryFileList->setFiles($mockDirectoryDriveFile);

    $this->filesDriveClient
      ->method('listFiles')
      ->willReturn(null)
      ->expect($this->twice()) // create() for dir and versioned file
      ->method('create')
      ->will($this->onConsecutiveCalls($mockDirectoryFileList))
      ->withConsecutive([
        [],
        [
          $this->isInstanceOf(Google_Service_Drive_DriveFile), // https://developers.google.com/drive/v3/web/folder#creating_a_folder
          $this->callback(function($filesObject){
            return $filesObject->parents == [$mockDirectoryId];
          }),
          $this->callback(function($filesObject){
            return $filesObject->name == "{$this->testName}";
          })
        ]
    ]);
  }
  public function testCreatesNewVersion() {

  }

  
}