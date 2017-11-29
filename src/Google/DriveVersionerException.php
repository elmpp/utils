<?php

namespace Partridge\Utils\Google;

class DriveVersionerException extends \RuntimeException
{
  const DRIVE_CANNOT_CREATE_DIR = 'Unable to create namespace directory. ';
  const VERSIONABLE_FILE_NOT_READABLE = 'The file to version is not readable. ';
  const DUPLICATE_NAMESPACE_DIRECTORY = 'Found multiple directories for the namespace. This is bad. ';
}
