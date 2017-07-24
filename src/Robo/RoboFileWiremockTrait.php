<?php

namespace Partridge\Utils\Robo;

use Partridge\Utils\Util;
use Robo\Exception\TaskException;

/**
 * Wiremock is a proxy; invaluable for recording external service calls and allowing for their replaying later
 */
trait RoboFileWiremockTrait {


  /**
   * Used to abstract the potentially differing directory for wiremock data
   * @param null $dir
   * @return string
   */
  protected static function getWiremockDataDir($dir = null) {

    $dir = $dir ?: 'standard';
    return Util::getProjectRoot() . '/etc/wiremock/' . $dir;
  }
  
  protected static function getWiremockJarFile() {

    return Util::getProjectRoot() . '/devDependencies/wiremock.jar';
  }

  /**
   * kills wiremock if running locally
   */
  public function wiremockKill() {

    $this->systemProcessGrep('[w]iremock.jar', true);
  }

  /**
   * Renames [already unaltered] files in wiremock "mapping" directory
   */
  public function wiremockTag($tag, $dataDir = null) {

    $this->taskExec('for file in '  . self::getWiremockDataDir($dataDir) . '/mappings/mapping-*; do mv $file ${file//mapping-/' . $tag . '-} ; done > /dev/null')
         ->printOutput(true)
         ->run()
    ;
  }

  /**
   * ensures wiremock is up and running
   */
  public function wiremockRun($background = true, $dataDir = null) {

    $currentStopOnFail = \Robo\Result::$stopOnFail;
    $this->stopOnFail(false);                // https://github.com/consolidation/Robo/issues/562

    $coll = $this->collectionBuilder()
                 ->addCode( function(){ $this->wiremockSetup(); })
                 ->taskExec('java -jar ' . self::getWiremockJarFile())
                 ->option('root-dir', self::getWiremockDataDir($dataDir))
                 ->option('https-port', '8443')
                 ->option('verbose')
                 ->printOutput(true);
    ;
    if ($background) {
      $coll
        ->background()
        ->idleTimeout(2)
        ->rawArg('&> /tmp/wiremock || true')     // don't know why need to force the true now with robo but whatevs
      ;
    }
    @$coll->run();
    sleep(5); // required!!

    $this->stopOnFail($currentStopOnFail);
  }

  protected function wiremockSetup() {

    if (!is_readable(self::getWiremockJarFile())) {
      throw new TaskException("Wiremock jar expected at " . self::getWiremockJarFile());
    }
    $this->systemProcessGrep('[w]iremock.jar', true);
  }

  /**
   * Starts wiremock in record mode; will forward all requests to the host specified in $api
   */
  public function wiremockRecord($feedName = 'CORAL', $background = false, $dataDir = null) {

    $mapperFactory = new \ONC\Partridge\Import\Mapping\MapperFactory();
    $feed = \ONC\Partridge\Entity\Feed::createByName($feedName);
    $api = ($mapperFactory->getInstance($feed))->getFeedOptions('host');

    $this->wiremockSetup();
    $task = $this->taskExec('java -jar ' . self::getWiremockJarFile())
                 ->option('root-dir', self::getWiremockDataDir($dataDir))
                 ->option('record-mappings')
                 ->option('proxy-all', $api)
                 ->option('https-port', '8443')
                 ->option('verbose')
    ;

    if ($background) {
      $task
        ->background()
        ->idleTimeout(2)
        ->rawArg(' &> /tmp/wiremock')
      ;
      $this->say("wiremock output being sent to /tmp/wiremock");
    }
    $task
      ->printOutput(true)
      ->run()
      ;
  }

  /**
   * Clears entire wiremock directory
   */
  public function wiremockClear($dataDir = null) {

    $this->taskCleanDir(self::getWiremockDataDir($dataDir))->run();
  }

}