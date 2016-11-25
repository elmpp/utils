<?php

namespace Partridge\Utils\Robo;
use Robo\Exception\TaskException;


/**
 * For running selenium tests/server etc
 */
trait RoboFileSeleniumTrait {

  protected $seleniumJar;

  protected function setupSelenium() {

//    $this->seleniumJar = getcwd() . '/devDependencies/selenium-standalone-3.0.1.jar';
    $this->seleniumJar = getcwd() . '/devDependencies/selenium-standalone-2.5.3.jar';

    $seleniumVersion = basename($this->seleniumJar);

    if (!is_readable($this->seleniumJar)) {
      throw new TaskException("Selenium jar expected at " . $this->seleniumJar);
    }
    $this->systemProcessGrep('[s]elenium.*.jar', true);

    if (false !== strripos($seleniumVersion, '2.5')) {
      $this->yell("Firefox v46 or earlier requred if running locally via selenium builder!", 40, 'red');
    }
  }

  /**
   * Runs selenium server locally. If multiple nodes specified, bring
   * up a "hub" along with "nodes". This is termed a Selenium Grid.
   * http://www.seleniumhq.org/docs/07_selenium_grid.jsp
   * http://elementalselenium.com/tips/52-grid
   */
  public function seleniumRun($background = false, $nodes = 1) {

    $this->setupSelenium();

    $runGrid = ($nodes > 1);
    $collection = $this->collectionBuilder();
    if ($runGrid) {
      $this->doSeleniumRun($collection, true, 0, $background);
      $this->doSeleniumRun($collection, false, $nodes, $background);
//      for ($i=1; $i<=$nodes; $i++) {
//        $this->doSeleniumRun($collection, false, $i, $background);
//      }
    }
    else {
      $this->doSeleniumRun($collection, false, 0, $background);
    }

    $collection->run();
    sleep(2);
  }

  public function testMe($background = false) {
    $task = $this->taskExec("while true; do
  echo \"0\"
  sleep 1
done > /tmp/yestest");
    if ($background) {
      $task->background(true);
    }
    $task->run();
  }

  protected function doSeleniumRun($collection, $isHub = false, $node = 0, $background = false) {

    $collection
      ->taskExec('java -jar ' . $this->seleniumJar)
      ->printed(true)
    ;

    if ($background) {
      $collection
        ->background(true)
//        ->idleTimeout(30)
        ->arg(' &> /tmp/selenium')
//        ->option(' -debug')
      ;
    }

    if ($isHub) {
      $this->yell("Starting selenium grid. Version " . basename($this->seleniumJar) . " ( Console: http://localhost:4444/grid/console )");
      $collection->arg('-role hub');
      sleep(3);
    }
    elseif ($node) {
      $this->say("Starting grid node with browser instances ${node}");
      $collection->arg('-role node');
      $collection->arg('-hub http://localhost:4444/grid/register');
      $collection->arg("-browser browserName=firefox,maxInstances=${node},platform=MAC");
    }
//    elseif ($node) {
//      $this->say("Starting grid node number ${node}");
//      $collection->arg('-role node');
//      $collection->arg('-hub http://localhost:4444/grid/register');
//    }
    else {
      $this->yell("Starting selenium server. Version " . basename($this->seleniumJar) . " ( Address: http://localhost:4444 )");
    }

    return $collection;

//    $collection->run();

//    sleep(5);
  }
}