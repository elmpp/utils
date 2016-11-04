<?php

namespace Partridge\Utils\Robo\Task;

use Robo\Container\SimpleServiceProvider;

trait loadTasks
{

  /**
   * http://robo.li/extending/
   * @return mixed
   */
  protected function taskUtilSemVer() {

    return $this->task(__FUNCTION__);
  }
}