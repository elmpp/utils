<?php

namespace Partridge\Utils\Robo;

trait RoboFileDockerTrait
{
  /**
   * Does a docker push
   *
   * @param string $fullTag
   * @param string $registry
   */
    public function imagePush($fullTag) {
      // LOOKS LIKE THIS HAS NOW CHANGED TO gloud docker -- push
      // https://cloud.google.com/container-registry/docs/pushing
        $binary = (false !== stristr($fullTag, 'gcr.io')) ? 'gcloud docker --' : 'docker';
        $this->taskExec("${binary} push $fullTag")
        ->printOutput(true)
        ->run()
        ;
    }

  /**
   * Does a docker pull
   *
   * @param string $fullTag
   * @param string $registry
   */
    public function imagePull($fullTag) {
        $binary = (false !== stristr($fullTag, 'gcr.io')) ? 'gcloud docker --' : 'docker';
        $this->taskExec("${binary} pull $fullTag")
        ->printOutput(true)
        ->run()
        ;
    }
}
