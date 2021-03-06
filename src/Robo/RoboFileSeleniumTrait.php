<?php

namespace Partridge\Utils\Robo;

use Robo\Exception\TaskException;

/**
 * For running selenium tests/server etc
 */
trait RoboFileSeleniumTrait
{
    protected $seleniumJar;

    protected function setupSelenium() {
  //    $this->seleniumJar = getcwd() . '/devDependencies/selenium-standalone-3.0.1.jar';
        $this->seleniumJar = getcwd().'/devDependencies/selenium-standalone-2.5.3.jar';

        $seleniumVersion = basename($this->seleniumJar);

        if (!is_readable($this->seleniumJar)) {
            throw new TaskException(__CLASS__, 'Selenium jar expected at '.$this->seleniumJar);
        }
        $this->systemProcessGrep('[s]elenium.*.jar', true);
    }

  /**
   * Runs selenium server locally. If multiple nodes specified, bring
   * up a "hub" along with "nodes". This is termed a Selenium Grid.
   * http://www.seleniumhq.org/docs/07_selenium_grid.jsp
   * http://elementalselenium.com/tips/52-grid
   */
    public function seleniumRun($background = true, $nodes = 1) {
        $currentStopOnFail = \Robo\Result::$stopOnFail;
        $this->stopOnFail(false);                // https://github.com/consolidation/Robo/issues/562

        $this->setupSelenium();

        $runGrid = ($nodes > 1);
        $collection = $this->collectionBuilder();
        if ($runGrid) {
            $this->doSeleniumRun($collection, true, 0, $background);
            $this->doSeleniumRun($collection, false, $nodes, $background);
        } else {
            $this->doSeleniumRun($collection, false, 0, $background);
        }

        $collection->run();
        sleep(8);

        $this->stopOnFail($currentStopOnFail);
    }

    protected function doSeleniumRun($collection, $isHub = false, $browserInstances = 0, $background = false) {
        $collection
        ->taskExec('java -jar '.$this->seleniumJar)
        ->printOutput(true)
        ;

        if ($background) {
            $collection
            ->background(true)
            ->rawArg(' &> /tmp/selenium || :')   // robo always fails with backgrounding hence the "else noop"
  //        ->option(' -debug')
            ;
        }

        if ($isHub) {
            $this->yell('Starting selenium grid. Version '.basename($this->seleniumJar).' ( Console: http://localhost:4444/grid/console )');
            $collection->rawArg('-role hub');
            sleep(3);
        } elseif ($browserInstances) {
            $this->say("Starting grid node with browser instances ${browserInstances}");
            $collection->rawArg('-role node');
            $collection->rawArg('-hub http://localhost:4444/grid/register');
            $collection->rawArg("-browser browserName=firefox,maxInstances=${browserInstances} -browser browserName=phantomjs,maxInstances=${browserInstances} -browser browserName=chrome,maxInstances=${browserInstances} -log /var/log/selenium.log");
        } else {
            $this->yell('Starting selenium server. Version '.basename($this->seleniumJar).' ( Address: http://localhost:4444 )');
        }

        return $collection;
    }
}
