#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('permanently destroy a Differential Revision');
$args->setSynopsis(<<<EOHELP
**destroy_revision.php** __D123__
  Permanently destroy the specified Differential Revision (for example,
  because it contains secrets that the world is not ready to know).

  Normally, you can just "Abandon" unwanted revisions, but in dire
  circumstances this script can be used to completely destroy a
  revision. Destroying a revision may cause some glitches in
  linked objects.

  The revision is utterly destroyed and can not be recovered unless you
  have backups.
EOHELP
);
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'      => 'revision',
      'wildcard'  => true,
    ),
  ));

$revisions = $args->getArg('revision');
if (count($revisions) != 1) {
  $args->printHelpAndExit();
}

$id = trim(strtolower(head($revisions)), 'd ');
$revision = id(new DifferentialRevision())->load($id);

if (!$revision) {
  throw new Exception("No revision '{$id}' exists!");
}

$title = $revision->getTitle();
$ok = phutil_console_confirm("Really destroy 'D{$id}: {$title}' forever?");
if (!$ok) {
  throw new Exception("User aborted workflow.");
}

$revision->delete();
echo "OK, destroyed revision.\n";

