<?php

namespace Partridge\Utils\Robo;

use Partridge\Utils\Robo\Task\loadTasks;

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
  public function buildMergeDev() {

    if ($this->getCurrentGitBranch() != 'dirty') {
      throw new \Exception("You must be on the branch 'dirty'");
    }

    $result = $this->taskUtilsSemVer('.semver')
                   ->increment('patch')
                   ->run()
    ;
//    $result = $this->taskSemVer('.semver')
//                   ->increment('patch')
//                   ->run()
//    ;
    if (!$result->wasSuccessful()) {
      throw new \Exception("Bad semver file");
    }

    return $this->taskGitStack()
                ->stopOnFail(true)
                ->add('.semver')
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
      $baseImage        = $this->getDefaultRegistry() . '/' . $this->imageNames['php-apache'] . ':latest';
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
}