<?php

namespace Partridge\Utils\Robo;

use Partridge\Utils\Util;

/**
 * Segregates the robo tasks concerned with packaging and building of repo projects
 *
 */
trait RoboFileDbTrait {

  /**
   * Dumps the db configured for the env param supplied
   * @param string $env
   * @param string $filename
   */
  public function dbDump($filename = '', $env = 'test') {

    $this->taskExec('bin/console import:dbfixtures')
         ->option('env', $env)
         ->arg('dump')
         ->arg($filename)
         ->printed(true)
         ->run()
    ;
  }

  /**
   * Loads into the db for the env param supplied from file with name supplied (from fixtures_dir)
   * @param string $env
   * @param string $filename
   */
  public function dbLoad($filename = '', $env = 'test') {

    return $this->collectionBuilder()
                ->taskExec('bin/console doctrine:database:create')
                ->option('if-not-exists')
                ->option('env', $env)
                ->taskExec('bin/console import:dbfixtures')
                ->option('env', $env)
                ->arg('load')
                ->arg($filename)
                ->printed(true)
                ->run()
      ;
  }

  /**
   * Drops DB, recreates, loads schema, loads initial fixtures, runs Postgres scripts
   * @param string $env
   *
   * sym doctrine:database:drop --force
   * && sym doctrine:database:create
   * && sym doctrine:schema:create
   * && sym doctrine:fixtures:load --fixtures=src/ImporterBundle/DataFixtures/ORM/LoadStopWordData.php --append
   * && sym doctrine:schema:update --force
   * && sym doctrine:fixtures:load --append
   */
  public function dbRebuild($env = 'test') {

    $collection = $this->collectionBuilder();
    $collection
      ->taskExec('bin/console doctrine:database:create')
      ->option('env', $env)
      ->option('if-not-exists')
      // required as dropping db with other connections won't be allowed
      // actually disallows access to the database for other connections
      // which is necessary as local persistent terminals reconnect
      // and break further queries in this collection (probably cause it takes a while)
      ->taskExec('bin/console import:pgsql_users')
      ->option('env', $env)
      ->printed(true)
      ->taskExec('bin/console doctrine:database:drop')
      ->option('env', $env)
      ->option('if-exists')
      ->option('force')
      ->taskExec('bin/console doctrine:database:create')
      ->option('env', $env)
      ->taskExec('bin/console doctrine:schema:create')
      ->option('env', $env)
      ->taskExec('bin/console doctrine:schema:update')
      ->option('env', $env)
      ->option('force')
      ->taskExec('bin/console doctrine:fixtures:load')
      ->option('env', $env)
      ->option('append')
    ;

    $collection->run();

    $this->taskExec('bin/console import:pgsql_users') // Need to reallow access to be always afterwards in all cases
         ->option('env', $env)
         ->option('reinstate')
         ->printed(true)
         ->run()
    ;
  }

}