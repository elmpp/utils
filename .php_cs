<?php

/**
 *  - https://github.com/M6Web/php-cs-fixer-config
 */
$config = new M6Web\CS\Config\Php71;

$config->getFinder()
    ->in([
        __DIR__.'/src'
    ]);

return $config;