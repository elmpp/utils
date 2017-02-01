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
      ->taskExec('bin/console cache:clear')
        ->option('env', 'test')
        ->option('no-warmup')
        ->printed(true)
      ->addCode(function() { $this->apcuClear(); })
      ->run()
    ;
  }

  public function symfonyFixPerms() {

    $this->stopOnFail(true);
    $projectDir = $this->getCurrentProjectDir();

    $coll = $this->collectionBuilder();
    $coll
      ->taskCleanDir(["${projectDir}/var/cache", "${projectDir}/var/logs"])
      ->taskFilesystemStack()
        ->chmod("${projectDir}/var/cache", 0774, 0002, true) // assumes www-data is in current/root user's groups
        ->chmod("${projectDir}/var/logs", 0774, 0002,  true)
      ->run()
    ;
  }

  public function apcuClear() {

    $this->say("Clearing APCU cache");
    apcu_clear_cache();
  }


}