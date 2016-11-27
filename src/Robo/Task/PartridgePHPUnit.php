<?php
namespace Partridge\Utils\Robo\Task;
use Robo\Task\Testing\PHPUnit;


/**
 * Runs PHPUnit tests
 *
 * ``` php
 * <?php
 * $this->taskPHPUnit()
 *  ->group('core')
 *  ->bootstrap('test/bootstrap.php')
 *  ->run()
 *
 * ?>
 * ```
 */
class PartridgePHPUnit extends PHPUnit
{
    protected $dir;

    /**
     * Directory of test files or single test file to run.
     *
     * @param string A single test file or a directory containing test files.
     * @deprecated Use file() or dir() method instead
     * @return $this
     */
    public function files($files)
    {
      throw new \Robo\Exception\TaskException(__CLASS__, "Use dir()");
    }

    /**
     * Test the provided file.
     * @param string $file path to file to test
     * @return $this
     */
    public function file($file)
    {
      throw new \Robo\Exception\TaskException(__CLASS__, "Use dir()");
    }

    /**
     * Test all of the files in the provided directory.
     * @param string $dir path to directory to test
     * @return $this
     */
    public function dir($dir)
    {
      $this->dir = ' ' . $dir;
      return $this;
    }

    public function getCommand()
    {
        $phpunitOnly = preg_replace('/^php\s(.*)$/', '$1', $this->command);
var_dump($this->command, $phpunitOnly);
        return sprintf("%s%s%s", $phpunitOnly, $this->arguments, $this->dir);
    }

}
