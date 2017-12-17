<?php

namespace Partridge\Utils\Traits;

use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

trait OutputtableTrait {

  /**
   * @var OutputInterface
   */
  protected $output;

  
    /**
   * @param Output $output
   * @return self
   */
  public function setOutput(OutputInterface $output): self {
    $this->output = $output;
    return $this;
  }
  
  /**
 * @param String $message
 * @return self
 */
  protected function output($message): self {

    if ($this->output) {
      $this->output->writeln($message);
    }
    return $this;
  }
}