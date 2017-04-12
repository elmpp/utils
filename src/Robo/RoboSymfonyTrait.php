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

  /**
   * Should be run as root before dropping down into www-data
   */
  public function symfonyFixPerms() {

//    $this->stopOnFail(true);
    $projectDir = $this->getCurrentProjectDir();

    $coll = $this->collectionBuilder();
    $coll
      ->taskCleanDir(["${projectDir}/var/cache", "${projectDir}/var/logs", "${projectDir}/var/sessions"])
      ->taskFilesystemStack()
        ->chmod("${projectDir}/var/cache", 0774, 0002, true) // assumes www-data is in current/root user's groups
        ->chmod("${projectDir}/var/logs", 0774, 0002,  true)
        ->chmod("${projectDir}/etc/cachePerm", 0774, 0002,  true)
    ;

    if (!Util::isLocalDevMachine()) {
      $coll
        ->chmod("/var/partridge/assets", 0775, 0000)
        ->chmod("/tmp/", 0774, 0000,  true)
        ->chown("/tmp",                           "www-data", true)
        ->chown("${projectDir}/var/cache",        "www-data", true)
        ->chown("${projectDir}/var/logs",         "www-data", true)
        ->chown("${projectDir}/etc/cachePerm",    "www-data", true)
        ->chgrp("${projectDir}/var/cache",        "www-data", true)
        ->chgrp("${projectDir}/var/logs",         "www-data", true)
        ->chgrp("${projectDir}/etc/cachePerm",    "www-data", true)
      ;
    }
    $coll->run();
  }

  public function apcuClear() {

    $this->say("Clearing APCU cache");
    apcu_clear_cache();
  }


}