#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('load files as image macros');
$args->setSynopsis(<<<EOHELP
**add_macro.php** __image__ [--as __name__]
    Add an image macro. This can be useful for importing a large number
    of macros.
EOHELP
);
$args->parseStandardArguments();

$args->parse(
  array(
    array(
      'name'  => 'as',
      'param' => 'name',
      'help'  => 'Use a specific name instead of the first part of the image '.
                 'name.',
    ),
    array(
      'name'      => 'more',
      'wildcard'  => true,
    ),
  ));

$more = $args->getArg('more');
if (count($more) !== 1) {
  $args->printHelpAndExit();
}

$path = head($more);
$data = Filesystem::readFile($path);

$name = $args->getArg('as');
if ($name === null) {
  $name = head(explode('.', basename($path)));
}

$existing = id(new PhabricatorFileImageMacro())->loadOneWhere(
  'name = %s',
  $name);
if ($existing) {
  throw new Exception("A macro already exists with the name '{$name}'!");
}

$file = PhabricatorFile::newFromFileData(
  $data,
  array(
    'name' => basename($path),
  ));

$macro = id(new PhabricatorFileImageMacro())
  ->setFilePHID($file->getPHID())
  ->setName($name)
  ->save();

$id = $file->getID();

echo "Added macro '{$name}' (F{$id}).\n";
