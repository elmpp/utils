<?php

namespace Partridge\Utils\Robo;

use Partridge\Utils\Util;
use Robo\Exception\TaskException;

trait RoboFileTrait
{
  /**
   * Includes when used cross-project can be confusing
   *
   * @return string
   */
    protected function getCurrentProjectDir() {
        return Util::getProjectRoot();
  //    return substr(realpath($_SERVER["SCRIPT_FILENAME"]), 0, stripos(realpath($_SERVER["SCRIPT_FILENAME"]), '/robo'));
    }

  /**
   * Sometimes we need to know which project we're in
   *
   * @return string
   */
    protected function getCurrentProjectName() {
        return basename(substr(realpath($_SERVER['SCRIPT_FILENAME']), 0, stripos(realpath($_SERVER['SCRIPT_FILENAME']), '/robo')));
    }

    protected function isLocalDevMachine() {
        return Util::isLocalDevMachine();
    }

    protected function getCurrentExecutingUser() {
        return exec('whoami');
    }

    protected function getCurrentGitBranch() {
        $currentBranch = system('git rev-parse --abbrev-ref HEAD');

        return $currentBranch;
    }

    protected function systemProcessGrep($egrep, $killOrIgnore = false) {
        $processId = system("ps -ef | egrep -i '${egrep}' | awk '{print $2}'");      // http://stackoverflow.com/a/3510850/2968327
        $processId = intval(trim($processId));

        if ($processId != 0) {
            if (is_null($killOrIgnore)) {
                return;
            }
            if ($killOrIgnore !== false) {
                $this->say("Found existing process for grep ${egrep}");
                $this->taskExec("kill $(ps -ef | egrep -i '${egrep}' | awk '{print $2}')")  // http://stackoverflow.com/a/3510850/2968327
                ->printOutput(true)
                ->run()
                ;
                sleep(2);
            } else {
                throw new TaskException($this, "Process grepped via '${egrep}' is already running at process(es) id: ${processId}");
            }
        }
    }
}
