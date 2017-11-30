<?php

/**
 *  - https://github.com/M6Web/php-cs-fixer-config
 */
$config = new M6Web\CS\Config\Php71;

$rules = $config->getRules();
$config->setIndent('  ');

$config->getFinder()
    ->in([
        __DIR__.'/src'
    ])
    ->in([
        __DIR__.'/tests'
    ])
    ->exclude(['**/vendor'])
;

return $config;