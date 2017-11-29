<?php

use Partridge\Utils\Robo\Task\loadTasks;
use Partridge\Utils\Robo\RoboFileCodeStyle;

/**
 * This is project's console commands configuration for Robo task runner.
 * Examples - https://github.com/consolidation-org/Robo/blob/master/RoboFile.php
 * Examples - https://github.com/Codeception/Codeception/blob/master/RoboFile.php
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{
    use loadTasks;
    use RoboFileCodeStyle;
}
