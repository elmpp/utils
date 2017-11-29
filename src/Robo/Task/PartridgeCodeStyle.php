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
   * @var String $binary
   */
  protected $binary = './vendor/bin/php-cs-fixer';
    /**
     * The directory to operate on
     * @var String $dir
     */
    protected $dir;
    /**
     * @var String
     */
    protected $configFile = "./.php_cs";
    /**
     * additional args
     * @var string
     */
    protected $args = '';

    public function __construct($dir = './')
    {
      $this->dir = $dir;
    }

    /**
     * Additional args
     * @param string $args
     * @return this
     */
    public function args(String $args): self {
      $this->args = $args;
      return $this;
    }

    public function run()
    {
        $command = "{$this->binary} --config={$this->configFile} fix {$this->dir} {$this->args}";

        // execute the command
        $exec = new Exec($command);

        return $exec->inflect($this)->printOutput(true)->run();
    }

}
