<?php

namespace Partridge\Utils\Google;

class DriveVersionerMessages
{
    const AUTHORISATION_FAIL = 'Authorisation incorrect. See docs for directions on setting up the client. ';
    const DRIVE_CANNOT_CREATE_DIR = 'Unable to create namespace directory. ';
    const DRIVE_CANNOT_CREATE_VERSIONED_FILE = 'Unable to create versionable file. ';
    const DRIVE_CANNOT_UPDATE_VERSIONED_FILE = 'Unable to update versionable file. ';
    const DRIVE_ROOT_NOT_FOUND = "Unable to locate the root directory. This is required for use. Use Google Drive web interface to find ID of writable directory (ideally empty). ";
    const PARENT_ROOT_NOT_FOUND = "Unable to locate the parent directory. This is required for use. Use Google Drive web interface to find ID of writable directory (ideally empty). ";
    const VERSIONABLE_FILE_NOT_READABLE = 'The file to version is not readable. ';
    const DUPLICATE_NAMESPACE_DIRECTORY = 'Found multiple directories for the namespace. This is bad. ';
    const DUPLICATE_VERSIONED_FILE = 'Found multiple files for the versioned file. This is bad. ';
    const DRIVE_CANNOT_LIST_VERSIONED_FILE = 'The versions cannot be listed. This may be due to missing versioned file, namespace directory or incorrect root. ';

    const SETUP_CREDENTIALS_FILE_NOT_FOUND = 'Credential files were expected. See here for more info - http://bit.ly/2jHemoK. ';

    const DEBUG_DRIVE_ROOT_FOUND = 'Root drive was found. ';
    const DEBUG_NS_DIR_CREATED = 'Namespace directory has been created. ';
    const DEBUG_NS_DIR_FOUND = 'Namespace directory already existent. ';
    const DEBUG_VERSIONED_FILE_CREATED = 'The versioned file has been created. ';
    const DEBUG_VERSIONED_FILE_FOUND = 'The versioned file already existent. ';
    const DEBUG_VERSION_FILE_ALREADY_EXISTS = 'The version of that file has already been saved. ';
    const DEBUG_UPDATING_REVISION = 'Updating specific version. ';
    const DEBUG_LISTING_VERSIONS_FOR_VERSIONED = 'Querying version list for file. ';
    const DEBUG_NEW_VERSION_CREATED = 'New version of the versioned file has been created. ';
    const DEBUG_CACHE_HIT = 'Cache hit. ';
    
}
