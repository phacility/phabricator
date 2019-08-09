<?php
/**
 * This file allows you to convert through the command line.
 * Usage:
 *   php -f convert.php [input file]
 */

if (count($argv) < 2) {
	throw new \InvalidArgumentException("Expected: php -f convert.php [input file]");
}

if (!file_exists($argv[1])) {
	throw new \InvalidArgumentException("'" . $argv[1] . "' does not exist");
}

$input = file_get_contents($argv[1]);

require_once(__DIR__ . "/src/Html2Text.php");
require_once(__DIR__ . "/src/Html2TextException.php");

echo \Soundasleep\Html2Text::convert($input);
