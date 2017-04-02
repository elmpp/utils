<?php

namespace Partridge\Utils\Robo;

use ImporterBundle\Util\ArrayUtil;
use ImporterBundle\Util\FormatUtil;
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
  public function buildMergeDev($opts = ['no-composer' => false, 'quick' => false, 'super-quick' => false]) {

    $this->stopOnFail(true);

    if ($this->getCurrentGitBranch() != 'dirty') {
      throw new \Robo\Exception\TaskException(__CLASS__, "You must be on the branch 'dirty'");
    }


    $coll = $this->collectionBuilder();
    if (!$opts['no-composer']) {
      $coll->taskComposerUpdate()
         ->arg('partridge/utils') // makes sure composer.lock has latest proper utils and tests
//         ->arg('partridge/testing') // does not matter if out of sync
         ->printed(true)
      ;
    }
    if (is_callable([$this, 'doBuildMergeDev'])) {
      $coll->addCode( function() use ($opts) { $this->doBuildMergeDev($opts); });
    }

    if (!$opts['super-quick']) {
      if ($opts['quick']) {
        $coll->addCode( function() { $this->testQuick(); });
      }
      else {
        $coll->addCode( function() { $this->testAll(); });
      }
    }

    $result = $this->taskUtilsSemVer('.semver')
                   ->increment('patch')
                   ->run()
    ;
    if (!$result->wasSuccessful()) {
      throw new \Robo\Exception\TaskException(__CLASS__, "Bad semver file");
    }

    return $coll
      ->taskGitStack()
        ->stopOnFail(true)
        ->add('.semver')
        ->add('.semver.plain')
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
   * Hits up Shippable and triggers a build of the project specified.
   * Also can be used to trigger release builds
   * Ideal for calling after a deployment on GKE or wherever
   *
   * http://docs.shippable.com/api/overview/
   */
  public function shippableBuild($project, $globalEnvs = '{}', $opts = ['release' => false]) {

    $callShippable = function($projectId, $buildProject, $globalEnvs) {

      $url  = "https://api.shippable.com/projects/${projectId}/newBuild";
      $ch = curl_init();
      $this->say("URL: ${url}");

      $shippableApiToken = getenv('SYMFONY__SHIPPABLEAPITOKEN');
      if (!$shippableApiToken) {
        throw new \RuntimeException("Cannot trigger shippable build without token set as env variable at 'SYMFONY__SHIPPABLEAPITOKEN'");
      }

      $headers = [
        "Authorization: apiToken ${shippableApiToken}",
        "Content-Type: application/json",
      ];

      $globalEnvJson = "{\"globalEnv\": ${globalEnvs}}";

      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch,CURLOPT_URL, $url);
      curl_setopt($ch,CURLOPT_POST, 1);
      curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        $globalEnvJson
      );
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      $rawRes = @curl_exec($ch);
      if (!($res = json_decode($rawRes, true)) || !isset($res['runId'])) {
        throw new \RuntimeException("Could not json decode the response or imvalid key used. Raw: " . Util::consolePrint($rawRes));
      }

      $this->say("Shippable build triggered at https://app.shippable.com/bitbucket/alanpartridge/${buildProject}/runs/${res['runNumber']}/1/console");
      if ($globalEnvs) {
        $this->say("globalEnv: ${globalEnvJson}");
      }
      curl_close($ch);
    };


    // we'll be calling the "docker-images" shippable project with specific globalEnv targets
    if ($opts['release']) {
      $buildProject = 'docker-images';
      $projectId = $this->getShippableDetails($buildProject)['id'];
      if ($project == 'api') {
        $callShippable->__invoke($projectId, $buildProject, '{"partridge_target": "importer"}');
        $callShippable->__invoke($projectId, $buildProject, '{"partridge_target": "api"}');
      }
      // frontend etc
      else {
        $callShippable->__invoke($projectId, $buildProject, '{"partridge_target": "' . $project . '"}');
      }
    }
    // standard project build (can include docker-images which will result in all standard images being built
    else {
      $projectId = $this->getShippableDetails($project)['id'];
      $callShippable->__invoke($projectId, $project, $globalEnvs);
    }
  }

}