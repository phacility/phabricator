<?php

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

$root = realpath(dirname(dirname(__FILE__)));
$library = "$root/Services";
$tests = "$root/tests";

$path = array($library, $tests, get_include_path());
set_include_path(implode(PATH_SEPARATOR, $path));

$vendorFilename = dirname(__FILE__) . '/../vendor/autoload.php';
if (file_exists($vendorFilename)) {
    /* composer install */
    require $vendorFilename;
} else {
    /* hope you have it installed somewhere. */
    require_once 'Mockery/Loader.php';
}
$loader = new \Mockery\Loader;
$loader->register();

require_once 'Twilio.php';

unset($root, $library, $tests, $path);

