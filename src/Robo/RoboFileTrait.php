<?php

namespace Partridge\Utils\Robo;

use ImporterBundle\Util\Util;

trait RoboFileTrait {

  /**
   * Includes when used cross-project can be confusing
   * @return string
   */
  protected function getCurrentProjectDir() {

    return substr(realpath($_SERVER["SCRIPT_FILENAME"]), 0, stripos(realpath($_SERVER["SCRIPT_FILENAME"]), '/robo'));
  }

  /**
   * Sometimes we need to know which project we're in
   * @return string
   */
  protected function getCurrentProjectName() {

    return basename(substr(realpath($_SERVER["SCRIPT_FILENAME"]), 0, stripos(realpath($_SERVER["SCRIPT_FILENAME"]), '/robo')));
  }

  protected function isLocalDevMachine() {

    return Util::isLocalDevMachine();
  }

  protected function getCurrentGitBranch() {

    $currentBranch = $this->taskExec('git rev-parse --abbrev-ref HEAD')->run();
    $currentBranch = trim($currentBranch->getOutputData(), PHP_EOL);
    return $currentBranch;
  }

  protected function systemProcessGrep($egrep, $kill = false) {

    $res = $this->taskExec("ps -ef | egrep -i '${egrep}' | awk '{print $2}'")  # http://stackoverflow.com/a/3510850/2968327
      ->run()
    ;
    $processId = intval(trim($res->getOutputData()));
//var_dump($processId); die;
    if ($processId != 0) {
      if ($kill) {
//        $this->say("Found existing process for grep ${egrep}. Killing process(es) id: ${processId}");
        $this->say("Found existing process for grep ${egrep}");
        $res = $this->taskExec("kill $(ps -ef | egrep -i '${egrep}' | awk '{print $2}')")  # http://stackoverflow.com/a/3510850/2968327
          ->printed(true)
          ->run()
        ;
//        $this->_exec("kill -9 ${processId}");
//        $this->taskExec("echo ${processId} | xargs kill -9")
//        $this->taskExec("echo ${processId} | xargs echo")
//        $this->taskExec("cat ${processId} | xargs kill -9")
//          ->printed(true)
//          ->run()
//        ;
      }
      else {
        throw new \Exception("Process grepped via '${egrep}' is already running at process(es) id: ${processId}");
      }
    }
  }


}