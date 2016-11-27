<?php

namespace Partridge\Utils\Robo;


trait RoboFileTestTrait {



  protected function getTestSrcDir() {

    return $this->getCurrentProjectDir() . '/tests';
  }

  /**
   * Runs the unit tests
   */
  public function doTestUnit($path = null) {

    return $this->taskPhpUnit()
      ->files($path)
      ->printed(true)
      ->run()
    ;
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
}