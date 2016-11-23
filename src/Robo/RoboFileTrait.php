<?php

namespace Partridge\Utils\Robo;

use ImporterBundle\Util\Util;

trait RoboFileTrait {

  protected $registries = [];
  protected $imageNames = [
    // packaged (we makey)
    'api'        => 'api',
    'importer'   => 'importer',
    'frontend'   => 'frontend',
    // base
    'php-cli'    => 'php-cli',
    'php-apache' => 'php-apache',
    'php-apache-api' => 'php-apache-api',
    'node'       => 'node',
  ];


  protected function setupRegistries() {


    if (!isset($_SERVER['GCR_PROJECT_ID'])) {
      throw new \Exception("\"GCR_PROJECT_ID\" must be set in environment pointing at relevant GCR project");
    }
    $this->registries[self::REGISTRY_GCR]   = "eu.gcr.io/" . $_SERVER['GCR_PROJECT_ID'];
    $this->registries[self::REGISTRY_LOCAL] = "localhost:5000";
  }

  /**
   * Does a docker push
   * @param string $fullTag
   * @param string $registry
   */
  public function imagePush($fullTag) {

    $binary = (false !== stristr($fullTag, 'gcr.io')) ? 'gcloud docker' : 'docker';
    $this->taskExec("${binary} push $fullTag")
         ->printed(true)
         ->run()
    ;
  }

  /**
   * Does a docker pull
   * @param string $fullTag
   * @param string $registry
   */
  public function imagePull($fullTag) {

    $binary = (false !== stristr($fullTag, 'gcr.io')) ? 'gcloud docker' : 'docker';
    $this->taskExec("${binary} pull $fullTag")
         ->printed(true)
         ->run()
    ;
  }

  /**
   * Grabs a file from BitBucket using wget. Hides the authorisation a little also
   * @param $url
   * @param $targetLoc
   */
  protected function bitbucketDownload($hash, $targetLoc) {

    if (!is_file($targetLoc) || (ctype_alpha($hash))) { // only overwrite branch references
      $res = $this
        ->taskExec("git archive --remote=ssh://git@bitbucket.org/alanpartridge/api.git --format=tar.gz --output=${targetLoc} ${hash}")
        ->printed(true)
        ->run()
      ;
      if (!$res->wasSuccessful()) {
        $sshKey = $_SERVER['HOME'] . '/.ssh/id_rsa.pub';
        if (!is_readable($sshKey)) {
          $this->yell("Add a ${sshKey} key and authorise with bitbucket", 40, 'red');
        }
        else {
          $this->say("File ${sshKey} was found despite fetch failure");
        }
        exit(1);
      }
      return $res;
    }
  }

  /**
   * Selects the best registry for docker image actions. Will return localhost if looks like a dev machine
   * @return string
   */
  protected function getDefaultRegistry() {

    if ($this->isLocalDevMachine()) {
      return $this->registries[self::REGISTRY_LOCAL];
    }
    return $this->registries[self::REGISTRY_GCR];
  }

  /**
   * Includes cross-project can be confusing
   * @return string
   */
  protected function getCurrentProjectDir() {

    return substr(realpath($_SERVER["SCRIPT_FILENAME"]), 0, stripos(realpath($_SERVER["SCRIPT_FILENAME"]), '/robo'));
  }

  protected function isLocalDevMachine() {

    return Util::isLocalDevMachine();
  }

  protected function getCurrentGitBranch() {

    $currentBranch = $this->taskExec('git rev-parse --abbrev-ref HEAD')->run();
    $currentBranch = trim($currentBranch->getOutputData(), PHP_EOL);
    return $currentBranch;
  }


}