<?php

namespace Partridge\Utils\Robo\Task;

use Robo\Container\SimpleServiceProvider;
use Partridge\Utils\Robo\Task\Task;

trait loadTasks
{

  /**
   * http://robo.li/extending/
   * @param string $pathToSemVer
   * @return SemVer
   */
  protected function taskUtilsSemVer($pathToSemVer = '.semver')
  {
    return $this->task(UtilsSemVer::class, $pathToSemVer);
  }

  /**
   * @param string $pathToSemVer
   * @param int $processes
   * @param bool $wrapperRunner
   * @return ParaTest
   */
  protected function taskParaTest($processes = 5, $wrapperRunner = false)
  {
    return $this->task(ParaTest::class, $processes, $wrapperRunner);
  }
}