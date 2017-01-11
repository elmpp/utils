<?php

namespace Partridge\Utils\Robo;

use Partridge\Utils\Util;
use Robo\Exception\TaskException;

trait RoboSymfonyTrait {

  public function cacheClear() {

    $coll = $this->collectionBuilder()
      ->taskCleanDir('var/cache')
      ->taskExec('bin/console cache:clear')
        ->option('env', 'prod')
        ->option('no-warmup')
        ->printed(true)
      ->taskExec('bin/console cache:clear')
        ->option('env', 'dev')
        ->option('no-warmup')
        ->printed(true)
      ->addCode(function() { $this->apcuClear(); })
      ->run()
    ;
  }

  public function apcuClear() {

    $this->say("Clearing APCU cache");
    apcu_clear_cache();
  }


}