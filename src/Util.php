<?php

namespace Partridge\Utils;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * Cross-project, org utils
 */
class Util {

  public static function isLocalDevMachine() {

    return (false !== stristr(gethostname(), 'Matthews-iMac')
      || false !== stristr(gethostname(), 'matts-MBP')
      || false !== stristr(gethostname(), 'matts-MacBook-Pro.local')
      || false !== getenv('SYMFONY__IS_LOCAL_MACHINE')
    );
  }

  public static function isLiveMachine() {

    return (false !== getenv('SYMFONY__IS_LIVE_MACHINE'));
  }

  /**
   * Consults environment variables for a variable value
   * @param      $env
   * @param null $default
   * @return null|string
   */
  public static function getEnv($env, $default = null) {

    if (false === ($value = getenv($env))) {
      return $default;
    }
    return $value;
  }

  /**
   * Gives a console-printable view
   * @param mixed $var
   */
  public static function consolePrint($var) {

    $cloner = new VarCloner;
    $dumper = new CliDumper;
    $output = '';

    $dumper->dump(
      $cloner->cloneVar($var),
      function ($line, $depth) use (&$output) {
        // A negative depth means "end of dump"
        if ($depth >= 0) {
          // Adds a two spaces indentation to the line
          $output .= str_repeat('  ', $depth).$line."\n";
        }
      }
    );
    return $output;
  }
}