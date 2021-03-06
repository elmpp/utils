<?php

namespace Partridge\Utils\Robo;

use ImporterBundle\Util\ArrayUtil;
use Partridge\Utils\Util;

trait RoboFileTestTrait
{
    protected function getTestSrcDir() {
        return $this->getCurrentProjectDir().'/tests';
    }

  /**
   * Runs the unit tests. Relies upon a phpunit.xml being present with the available testSuites defined within
   */
    protected function doTestPhpUnit($testSuite, $opts = ['debug' => false, 'stop-on-fail' => false, 'results-output' => false, 'coverage-output' => false]) {
        $testSuite = ArrayUtil::arrayCast($testSuite);

      /** @var \Robo\Collection\CollectionBuilder $coll */
        $coll = $this->collectionBuilder();
        foreach ($testSuite as $aSuite) {

            $coll
            ->taskPhpUnit('./vendor/bin/phpunit')
            ->option('testsuite', $aSuite)
            ->printOutput(true)
            ;
            if (!Util::isLocalDevMachine()) {
                $coll->option('exclude-group', 'localonly');
            }
            if ($opts['stop-on-fail']) {
                $coll->option('stop-on-fail');
            }
            if ($opts['results-output']) {
                $coll->option('log-junit', 'shippable/testresults/junit.xml');
            }
            if ($opts['coverage-output']) {
                $coll->option('coverage-xml', 'shippable/codecoverage');
            }
            if ($opts['debug']) {
                $coll->option('debug');
            }
        }
        $res = $coll->run();
        $this->say("Total time: (s)/(m) " . round($res->getExecutionTime(), 2) . " / " . round(($res->getExecutionTime() / 60), 2));
        return $res->getExitCode();
    }

    public function testBootstrap($env = 'test') {
        $this->wiremockRun(true);
        sleep(3);

        $this
        ->taskExec('bin/console doctrine:database:create')
        ->option('env', $env)
        ->option('if-not-exists')
        ->printOutput(true)
        ->run()
        ;
    }

  /**
   * Runs the selenium tests
   * These realised as node [nightwatch] scripts
   */
    protected function doTestSelenium($projectName = null, $platform = 'local') {
      // the "partridge/testing" composer package should have been pulled in by composer
        $testingDir = $this->getCurrentProjectDir().'/vendor/partridge/testing';

        $res = $this->collectionBuilder()
        ->taskExec('npm install')
        ->dir($testingDir)
        ->printOutput(true)
        ->taskExec("npm run ${projectName}:${platform}")
        ->dir($testingDir)
        ->printOutput(true)
        ->run()
        ;

        return $res->getExitCode();
    }
}
