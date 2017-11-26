<?php

namespace Partridge\Utils\Tests\Google;

use PHPUnit\Framework\TestCase;
use Google\Auth\Cache\MemoryCacheItemPool;

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
   * @var Google_Client
   */
  protected $client;

  public function construct(MemoryCacheItemPool $client) {
    $this->client = $client;
  }

  
}