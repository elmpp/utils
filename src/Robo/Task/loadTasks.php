<?php

namespace Partridge\Utils\Robo\Task;

use Robo\Container\SimpleServiceProvider;

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
}