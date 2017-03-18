<?php

namespace Partridge\Utils\Robo;


use ImporterBundle\Util\ArrayUtil;
use Partridge\Utils\Util;

trait RoboFileTestTrait {



  protected function getTestSrcDir() {

    return $this->getCurrentProjectDir() . '/tests';
  }

  /**
   * Runs the unit tests
   */
  public function doTestUnit($dir = null, $opts = ['debug' => false, 'stop-on-fail' => true, 'results-output' => false, 'coverage-output' => false]) {

    $dir = ArrayUtil::arrayCast($dir);

    /** @var \Robo\Collection\CollectionBuilder $coll */
    $coll = $this->collectionBuilder();
    foreach ($dir as $aDir) {
      $coll
        ->taskPhpUnit()
          ->files($aDir)
          ->printed(true)
      ;
      $coll->option('exclude-group', 'apiLiveDataCall');
      if (!Util::isLocalDevMachine()) {
        $coll->option('exclude-group', 'localonly');
      }
      if ($opts['stop-on-fail']) {
        $coll->option('stop-on-fail');
      }
      if ($opts['results-output']) {
        $coll->arg('log-junit', 'shippable/testresults/junit.xml');
      }
      if ($opts['coverage-output']) {
        $coll->arg('coverage-xml', 'shippable/codecoverage');
      }
    }
    return $coll->run();
  }

  /**
   * Runs the selenium tests
   * These realised as node [nightwatch] scripts
   */
  protected function doTestSelenium($projectName = null, $platform = 'local') {

    // the "partridge/testing" composer package should have been pulled in by composer
    $testingDir = $this->getCurrentProjectDir() . '/vendor/partridge/testing';

    $res = $this->collectionBuilder()
      ->taskExec("npm install")
        ->dir($testingDir)
        ->printed(true)
      ->taskExec("npm run ${projectName}:${platform}")
        ->dir($testingDir)
        ->printed(true)
      ->run()
    ;

    return $res;
  }

  /**
   * Runs the selenium tests
   * These realised as node [nightwatch] scripts
   */
  protected function doTestSeleniumQuick($platform = 'local') {

    // the "partridge/testing" composer package should have been pulled in by composer
    $testingDir = $this->getCurrentProjectDir() . '/vendor/partridge/testing';

    $res = $this->collectionBuilder()
      ->taskExec("npm install")
        ->dir($testingDir)
        ->printed(true)
      ->taskExec("npm run quick:${platform}")
        ->dir($testingDir)
        ->printed(true)
      ->run()
    ;

    return $res;
  }
}