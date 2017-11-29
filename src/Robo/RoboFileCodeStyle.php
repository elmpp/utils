<?php

namespace Partridge\Utils\Robo;

/**
 * Probably just build this up to have common scenarios of linting
 * e.g. - https://github.com/M6Web/php-cs-fixer-config#makefile
 */
trait RoboFileCodeStyle {

  public function csFix(String $dir = './', $opts = ['v' => false]) {
    $this->doCall($dir, '', $opts);
  }
  
  public function csSniff(String $dir = './', $opts = ['v' => false]) {
    $this->doCall($dir, ' --dry-run --stop-on-violation --diff', $opts);
  }
  
  protected function doCall(String $dir, String $args, $opts) {
    if ($opts['v']) {
      $args .= ' --verbose';
    }
    $this->taskPartridgeCodeStyle($dir)
      ->args($args)
      ->run()
    ;
  }
}