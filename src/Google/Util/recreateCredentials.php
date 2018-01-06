#!/usr/local/bin/php
<?php

namespace Partridge\Utils\Google\Util;

require_once __DIR__.'/../../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Partridge\Utils\Google\GoogleClientAPISetup;

// don't commit to vcs afterwards though!
$clientCredentialsDir = __DIR__ . '/../../../testsE2E/Google/Fixtures';
$apiCredentialsPath = "${clientCredentialsDir}/api-credentials.json";
$apiSecretsPath = "${clientCredentialsDir}/api-secret.json";
$apiRootDriveId = file_get_contents("${clientCredentialsDir}/api-root-drive-id.txt");
$clientSetup = new GoogleClientAPISetup($apiCredentialsPath, $apiSecretsPath, $apiRootDriveId);
$clientSetup->recreateCredentials();
