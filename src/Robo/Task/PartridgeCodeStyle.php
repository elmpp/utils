<?php

namespace Partridge\Utils\Robo\Task;

use Robo\Task\BaseTask;
use Robo\Task\Base\Exec;

/**
 * Runs php-cs-fixer
 * Assumes version 2, installed via composer
 *  - http://cs.sensiolabs.org/
 *
 *
 * ``` php
 * <?php
 * $this->taskPartridgeCodeStyle()
 *  ->dir('./')
 *  ->run()
 *
 * ?>
 * ```
 */
class PartridgeCodeStyle extends BaseTask
{
  /**
   * @var string
   */
    protected $binary = './vendor/bin/php-cs-fixer';
  /**
   * The directory to operate on
   *
   * @var string
   */
    protected $dir;
  /**
   * @var string
   */
    protected $configFile = './.codesniffer.xml';
  /**
   * Lint/Fix
   * @var String
   */
    protected $mode = 'lint';
  /**
   * additional args
   *
   * @var string
   */
    protected $args = '';

    public function __construct($dir = './') {
        $this->dir = $dir;
    }

  /**
   * Additional args
   *
   * @param string $args
   *
   * @return $this
   */
    public function args(String $args): self {
        $this->args = $args;

        return $this;
    }

    public function mode(String $mode): self {
        $this->mode = $mode;
        return $this;
    }


    public function run() {
      // $command = "{$this->binary} --config={$this->configFile} fix {$this->dir} {$this->args}";
      // $command = "./vendor/bin/phpcbf --standard={$this->configFile} {$this->dir}";
      // $installCmd = "./vendor/bin/phpcs --config-set installed_paths vendor/escapestudios/symfony2-coding-standard";

        switch ($this->mode) {
            case 'lint':
              // please note this will pick up the phpcs.xml file "Standard"
                $command = "vendor/bin/phpcs {$this->dir} {$this->args}"; // https://github.com/djoos/Symfony-coding-standard";
                break;
            case 'fix':
                $command = "vendor/bin/phpcbf {$this->dir} {$this->args}";
                break;
            default:
                throw new \RuntimeException("Unknown mode {$this->mode}");
        }

      // execute the command
        $exec = new Exec($command);

        return $exec->inflect($this)->printOutput(true)->run();
    }
}
