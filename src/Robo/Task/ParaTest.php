<?php

namespace Partridge\Utils\Robo\Task;

use Robo\Task\Testing\PHPUnit;

/**
 * Runs PHPUnit tests in parallel
 *
 * ``` php
 * <?php
 * $this->taskParaTest()
 *  ->group('core')
 *  ->bootstrap('test/bootstrap.php')
 *  ->run()
 *
 * ?>
 * ```
 */
class ParaTest extends PHPUnit
{
    protected $command;

  /**
   * Directory of test files or single test file to run. Appended to
   * the command and arguments.
   *
   * @var string
   */
    protected $files = '';

  /**
   * ParaTest constructor.
   *
   * @param int $processes
   *
   * @throws \Robo\Exception\TaskException
   */
    public function __construct($processes = 5) {
  //    $this->command = $this->findExecutablePhar('paratest');
        $this->command = './vendor/bin/paratest';
        if (!$this->command) {
            throw new \Robo\Exception\TaskException(__CLASS__, "'Paratest' expected in vendor/bin/para");
        }
        $this->arg("-p ${processes}");
    }

  /**
   * Launches new process for each method of a testclass
   *
   * @param bool $paralleliseByMethod
   *
   * @return $this
   */
    public function functional($paralleliseByMethod = false) {
        $this->arg('-f');

        return $this;
    }

  /**
   * https://github.com/brianium/paratest#optimizing-speed
   *
   * @param bool $paralleliseByMethod
   *
   * @return $this
   */
    public function wrapperRunner($wrapperRunner = false) {
        if ($wrapperRunner) {
            $this->option('runner', 'wrapperRunner');
        }

        return $this;
    }

    public function getCommand() {
        return $this->command.$this->arguments.$this->files;
    }

    public function run() {
        $command = $this->getCommand();
        $this->printTaskInfo("Running ${command}", ['arguments' => $this->arguments]);

        return $this->executeCommand($this->getCommand());
    }
}
