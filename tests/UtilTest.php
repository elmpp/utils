<?php

namespace Partridge\Utils;

use Partridge\Utils\Util;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase
{

    public function testCreateDirIfNonExistent() {
        $root = vfsStream::setup(
            'var'
        );
        Util::createDirIfNonExistent($root->url() . '/Partridge');
        $this->assertTrue(is_dir($root->url().'/Partridge'));
    }
    public function testCreateDirIfNonExistentRecursive() {
        $root = vfsStream::setup(
            'var'
        );
        Util::createDirIfNonExistent($root->url() . '/Partridge/Auth', true);
        $this->assertTrue(is_dir($root->url().'/Partridge/Auth'));
    }
}
