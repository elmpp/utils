#!/usr/local/bin/php
<?php

namespace Partridge\Utils\Google\Util;

require_once __DIR__.'/../../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Partridge\Utils\Google\DriveVersioner;
use Partridge\Utils\Google\GoogleClientSetup;

// don't commit to vcs afterwards though!
$clientCredentialsDir = __DIR__ . '/../../../testsE2E/Google/Fixtures';
$clientSetup = new GoogleClientSetup($clientCredentialsDir);
$clientSetup->recreateCredentials();

