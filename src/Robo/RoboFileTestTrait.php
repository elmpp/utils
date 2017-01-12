<?php

namespace Partridge\Utils\Robo;


trait RoboFileTestTrait {



  protected function getTestSrcDir() {

    return $this->getCurrentProjectDir() . '/tests';
  }

  /**
   * Runs the unit tests
   */
  public function doTestUnit($path = null, $opts = ['debug' => false, 'stop-on-fail' => true]) {

    $task = $this->taskPhpUnit()
      ->files($path)
      ->printed(true)
    ;
    if ($opts['stop-on-fail']) {
      $task->option('stop-on-fail');
    }
    return $task->run();
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