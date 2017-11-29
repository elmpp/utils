<?php

namespace Partridge\Utils;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * Cross-project, org utils
 */
class Util
{
  public static function isLocalDevMachine()
  {
    return false !== stristr(gethostname(), 'Matthews-iMac')
    || false !== stristr(gethostname(), 'matts-MBP')
    || false !== stristr(gethostname(), 'matts-MacBook-Pro.local')
    || false !== getenv('SYMFONY__IS_LOCAL_MACHINE')
  ;
  }

  public static function isLiveMachine()
  {
    return false !== getenv('SYMFONY__IS_LIVE_MACHINE');
  }

  /**
   * Consults environment variables for a variable value
   *
   * @param      $env
   * @param null $default
   *
   * @return null|string
   */
  public static function getEnv($env, $default = null)
  {
    if (false === ($value = getenv($env))) {
      return $default;
    }

    return $value;
  }

  /**
   * Attempts to find the project root without use of Parameters/Container
   *  - assumes a project with a base composer.json file
   *  - assumes a project with a base phpunit.xml.dist file
   *
   * @return string
   */
  public static function getProjectRoot()
  {
    $currentDir = realpath(dirname(__FILE__));

    while (!in_array($currentDir, ['/', '.'])) {
      if (
    is_file("${currentDir}/composer.json")
    && is_file("${currentDir}/RoboFile.php")
    ) {
        return $currentDir;
      }
      $currentDir = dirname($currentDir);
    }
    throw new \Exception('unable to derive the projectRoot successfully');
  }

  /**
   * Gives a console-printable view
   *
   * @param mixed $var
   */
  public static function consolePrint($var)
  {
    $cloner = new VarCloner();
    $dumper = new CliDumper();
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
