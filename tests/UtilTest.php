<?php

namespace Partridge\Utils;

use Partridge\Utils\Util;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase
{

    public function testCreateDirIfNonExistent() {
        $root = vfsStream::setup( // http://bit.ly/2m3pDRN
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
    
    /**
     * @dataProvider dataProviderGetProjectRoot
     */
    public function testGetProjectRoot(array $vfsStructure, String $realPathOfUtilClass) {
        $root = vfsStream::setup(
            'root',
            null,
            $vfsStructure
        );
        $this->assertEquals($root->url() . '/development/api', Util::getProjectRoot($root->url() . $realPathOfUtilClass));
    }

    public function dataProviderGetProjectRoot() {
        $utilProject = [
            'composer.json' => 'some data',
            'RoboFile.php' => 'some data',
            'Util.php' => 'the file normally found by dirname(__FILE__)',
        ];
        $apiProjectWithVendorComposer = [
            'composer.json' => 'some data',
            'RoboFile.php' => 'some data',
            'vendor' => [
                'util' => [
                    $utilProject
                ]
            ]
        ];
        $apiProjectWithSiblingDevelopmentProjectComposer = [
            'composer.json' => 'some data',
            'RoboFile.php' => 'some data',
            'vendor' => [
                // .. other projects but not util
            ],
            'composer.local.json' => 'this signifies the project as being the owner'
        ];
        
        return [
            [
                // the file system
                [
                    'development' => [
                        'api' => $apiProjectWithVendorComposer,
                        'util' => $utilProject,
                    ],
                ],
                // the reported realpath to the Util Class (settable for testing only)
                '/development/api/vendor/util/Util.php',
            ],
            [
                [
                    'development' => [
                        'api' => $apiProjectWithVendorComposer,
                        'util' => $utilProject,
                    ],
                ],
                '/development/api/vendor/util/Util.php',
            ],
            [
                [
                    'development' => [
                        'api' => $apiProjectWithSiblingDevelopmentProjectComposer,
                        'util' => $utilProject,
                    ],
                ],
                '/development/api/Robofile.php'
            ]
        ];
    }
}
