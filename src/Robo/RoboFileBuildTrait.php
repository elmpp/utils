<?php

namespace Partridge\Utils\Robo;

use Partridge\Utils\Robo\Task\loadTasks;
use Partridge\Utils\Util;

/**
 * Segregates the robo tasks concerned with packaging and building of repo projects
 *
 */
trait RoboFileBuildTrait {

  use loadTasks;

  /**
   * Dev task to merge Dirty branch into Dev and handle additional things such as versioning numbers
   *  - merges Dirty into Dev
   *  - bumps the semver patch number. Will be of the form MAJOR.MINOR.PATCH
   *  (to bump major/minor, a separate commit should be done beforehand)
   *
   */
  public function buildMergeDev($opts = ['no-composer' => false, 'quick' => false]) {

    if ($this->getCurrentGitBranch() != 'dirty') {
      throw new \Robo\Exception\TaskException(__CLASS__, "You must be on the branch 'dirty'");
    }

    $result = $this->taskUtilsSemVer('.semver')
                   ->increment('patch')
                   ->run()
    ;
    if (!$result->wasSuccessful()) {
      throw new \Robo\Exception\TaskException(__CLASS__, "Bad semver file");
    }

    $coll = $this->collectionBuilder();
    if (!$opts['no-composer']) {
      $coll->taskComposerUpdate()
         ->arg('partridge/utils') // makes sure composer.lock has latest proper utils and tests
         ->arg('partridge/testing')
         ->printed(true)
      ;
    }
    if ($opts['quick']) {
      $coll->addCode( function() { $this->testQuick(); });
    }
    else {
      $coll->addCode( function() { $this->testAll(); });
    }
    return $coll
      ->taskGitStack()
        ->stopOnFail(true)
        ->add('.semver')
        ->add('composer.lock')
        ->commit("Bumps .semver")
        ->push()
        ->checkout('dev')
        ->merge('dirty')
        ->push()
        ->checkout('dirty')
      ->run()
    ;
  }

  /**
   * Uses quicker method to create the image. Should not be used on CI server however
   * @param srting $imageKey (importer|api)
   * @param string $mainTag
   * @return mixed
   */
  protected function packageProject($packageType, $mainTag = 'latest-dev') {

    $packagerContainer = 'packager-' . $packageType;

    if ($packageType == 'api') {
      $baseImage        = $this->getDefaultRegistry() . '/' . $this->imageNames['php-apache-api'] . ':latest';
      $containerDocRoot = '/var/www/html';
      $containerCmd     = 'CMD ["apache2-foreground"]';
      $runAsUser        = 'www-data';
    }
    elseif ($packageType == 'importer') {
      $baseImage        = $this->getDefaultRegistry() . '/' . $this->imageNames['php-cli'] . ':latest';
      $containerDocRoot = '/opt/app';
      $containerCmd     = 'CMD ["php", "-a"]';
      $runAsUser        = 'root';
    }
    elseif ($packageType == 'frontend') {
      $baseImage        = $this->getDefaultRegistry() . '/' . $this->imageNames['node'] . ':latest';
      $containerDocRoot = '/var/www';
      $containerCmd     = 'CMD ["node"]';
      $runAsUser        = 'root';
    }

    $srcCodeLoc = $this->getCurrentProjectDir();
    $semVer     = (string) (new \Robo\Task\Development\SemVer($srcCodeLoc . "/.semver"));

    $this->say("Using base image: ${baseImage}");

    $registry   = $this->getDefaultRegistry() . '/' . $this->imageNames[$packageType];
    $registrySemver  = $registry . ':' . $semVer;
    $registryMain    = $registry . ':' . $mainTag;

    $collection = $this->getCollectionForPackaging(
      $packagerContainer,
      $baseImage,
      $srcCodeLoc,
      $containerDocRoot,
      $registrySemver,
      $registryMain,
      $runAsUser,
      $containerCmd
    );

    return $collection
      ->run()
      ->stopOnFail()
    ;
  }

  /**
   * Hits up Shippable and triggers a build of the testing repository for selenium stuff
   * Ideal for calling after a deployment on GKE or wherever
   *
   * http://docs.shippable.com/api/overview/
   */
  public function shippableBuildTesting() {

    $url  = "https://api.shippable.com/projects/58398d0183cb0511001841ab/newBuild";
    $ch = curl_init();

    $shippableApiToken = getenv('SYMFONY__SHIPPABLEAPITOKEN');
    if (!$shippableApiToken) {
      throw new \RuntimeException("Cannot trigger shippable build without token set as env variable at 'SYMFONY__SHIPPABLEAPITOKEN'");
    }

    $headers = [
      "Authorization: apiToken ${shippableApiToken}"
    ];

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch,CURLOPT_POSTFIELDS, []);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $rawRes = @curl_exec($ch);
    if (!$res = json_decode($rawRes, true) || !isset($res['runId'])) {
      throw new \RuntimeException("Could not json decode the response or imvalid key used. Raw: " . Util::consolePrint($rawRes));
    }

    $this->say("Shippable build triggered at https://app.shippable.com/runs/${res['runId']}/1/console");

    //close connection
    curl_close($ch);

  }

}