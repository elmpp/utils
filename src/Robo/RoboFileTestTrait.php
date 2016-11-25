<?php

namespace Partridge\Utils\Robo;


trait RoboFileTestTrait {



  protected function getTestSrcDir() {

    return $this->getCurrentProjectDir() . '/tests';
  }

  /**
   * Runs the integration tests
   */
  public function testIntegration() {

    $this->wiremockRun(true);
    sleep(3);
    $res = $this->taskPhpUnit()
                ->files($this->getTestSrcDir() . '/ONC/Test/Integration')
                ->printed(true)
                ->run()
    ;
    $this->wiremockKill();
    return $res;
  }

  /**
   * Runs the unit tests
   */
  public function testUnit() {

    return $this->taskPhpUnit()
                ->files($this->getTestSrcDir() . '/ONC/Test/Partridge')
                ->printed(true)
                ->run()
      ;
  }

  /**
   * Runs the selenium tests
   */
  public function testSelenium($parallel = 5) {

    return $this->collectionBuilder()
      ->addCode(function() { return $this->seleniumRun(true, 15); })
      ->taskParaTest($parallel)
        ->files($this->getTestSrcDir() . '/Selenium/BaseSeleniumTestCase.php')
        ->functional(true)
        ->wrapperRunner(true)
        ->printed(true)
      ->run()
    ;
  }

  /**
   * Runs Unit and Integration test
   */
  public function testAll($env = 'test') {

    $col = $this->collectionBuilder();
    $col
      ->taskExec('bin/console doctrine:database:create')
      ->option('env', $env)
      ->option('if-not-exists')
      ->printed(true)
      ->addCode(function(){ return $this->testSelenium(); })
      ->addCode(function(){ return $this->testUnit(); })
      ->addCode(function(){ return $this->testIntegration(); })
      ;
    return $col->run();
  }




}