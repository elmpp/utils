<?php

namespace Partridge\Utils\Robo;

/**
 * Probably just build this up to have common scenarios of linting
 * e.g. - https://github.com/M6Web/php-cs-fixer-config#makefile
 *  - http://cs.sensiolabs.org/#using-php-cs-fixer-on-ci
 */
trait RoboFileCodeStyle
{
    public function csFix(String $dir = '', $opts = ['v' => false]) {
        $this->doCall($dir, 'fix', $opts);
    }

    public function csSniff(String $dir = '', $opts = ['v' => false]) {
        $this->doCall($dir, 'lint', $opts);
    }

    protected function doCall(String $dir, String $mode, $opts) {
        $args = '';
        if ($opts['v']) {
            $args .= ' -vvv'; // http://bit.ly/2ApUctZ
        }
        $this->taskPartridgeCodeStyle($dir)
        ->mode($mode)
        ->run()
        ;
    }
}
