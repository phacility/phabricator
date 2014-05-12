<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This is the package.xml generator for Services_Twilio
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * Copyright 2014 Twilio.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @category  Services
 * @package   Services_Twilio
 * @author    Neuman Vong <neuman@twilio.com>
 * @copyright 2014 Twilio
 * @license   http://creativecommons.org/licenses/MIT/
 * @link      http://pear.php.net/package/Services_Twilio
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
require_once 'PEAR/PackageFileManager/File.php';
require_once 'PEAR/PackageFileManager2.php';
PEAR::setErrorHandling(PEAR_ERROR_DIE);

$api_version     = '3.12.4';
$api_state       = 'stable';

$release_version = '3.12.4';
$release_state   = 'stable';
$release_notes   = 'Add transcription link to recordings';

$description = <<<DESC
A SDK (or helper library, as we're calling them) for PHP developers to write
applications against Twilio's REST API and generate TwiML responses.
DESC;

$package = new PEAR_PackageFileManager2();

$package->setOptions(
    array(
        'filelistgenerator'       => 'file',
        'simpleoutput'            => true,
        'baseinstalldir'          => '/',
        'packagedirectory'        => './',
        'dir_roles'               => array(
            'Services'            => 'php',
            'Services/Twilio'     => 'php',
            'tests'               => 'test'
        ),
        'ignore'                  => array(
            'package.php',
            '*.tgz',
            'scratch/*',
            'vendor/*',
            'composer.*',
            'coverage/*',
            '.travis.yml',
            'venv/*',
        )
    )
);

$package->setPackage('Services_Twilio');
$package->setSummary('PHP helper library for Twilio');
$package->setDescription($description);
$package->setChannel('twilio.github.com/pear');
$package->setPackageType('php');
$package->setLicense(
    'MIT License',
    'http://creativecommons.org/licenses/MIT/'
);

$package->setNotes($release_notes);
$package->setReleaseVersion($release_version);
$package->setReleaseStability($release_state);
$package->setAPIVersion($api_version);
$package->setAPIStability($api_state);

$package->addMaintainer(
    'lead',
    'kevinburke',
    'Kevin Burke',
    'kevin@twilio.com'
);


$package->setPhpDep('5.2.1');

$package->addPackageDepWithChannel('optional', 'Mockery', 'pear.survivethedeepend.com');

$package->setPearInstallerDep('1.9.3');
$package->generateContents();
$package->addRelease();

if (isset($_GET['make'])
    || (isset($_SERVER['argv']) && @$_SERVER['argv'][1] == 'make')
) {
    $package->writePackageFile();
} else {
    $package->debugPackageFile();
}

