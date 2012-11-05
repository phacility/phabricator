#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$purge_changesets   = false;
$purge_differential = false;

$args = array_slice($argv, 1);
if (!$args) {
  usage("Specify which caches you want to purge.");
}

$changesets = array();
$len = count($args);
for ($ii = 0; $ii < $len; $ii++) {
  switch ($args[$ii]) {
    case '--all':
      $purge_changesets = true;
      $purge_differential = true;
      break;
    case '--changesets':
      $purge_changesets = true;
      while (isset($args[$ii + 1]) && (substr($args[$ii + 1], 0, 2) !== '--')) {
        $changeset = $args[++$ii];
        if (!is_numeric($changeset)) {
          return usage("Changeset argument '{$changeset}' ".
                       "is not a positive integer.");
        }
        $changesets[] = intval($changeset);
      }
      break;
    case '--differential':
      $purge_differential = true;
      break;
    case '--help':
      return help();
    default:
      return usage("Unrecognized argument '{$args[$ii]}'.");
  }
}

if ($purge_changesets) {
  $table = new DifferentialChangeset();
  if ($changesets) {
    echo "Purging changeset cache for changesets ".
         implode($changesets, ",")."\n";
    queryfx(
      $table->establishConnection('w'),
      'DELETE FROM %T WHERE id IN (%Ld)',
      DifferentialChangeset::TABLE_CACHE,
      $changesets);
  } else {
    echo "Purging changeset cache...\n";
    queryfx(
      $table->establishConnection('w'),
      'TRUNCATE TABLE %T',
      DifferentialChangeset::TABLE_CACHE);
  }
  echo "Done.\n";
}

if ($purge_differential) {
  echo "Purging Differential comment cache...\n";
  $table = new DifferentialComment();
  queryfx(
    $table->establishConnection('w'),
    'UPDATE %T SET cache = NULL',
    $table->getTableName());
  echo "Purging Differential inline comment cache...\n";
  $table = new DifferentialInlineComment();
  queryfx(
    $table->establishConnection('w'),
    'UPDATE %T SET cache = NULL',
    $table->getTableName());
  echo "Done.\n";
}

echo "Ok, caches purged.\n";

function usage($message) {
  echo "Usage Error: {$message}";
  echo "\n\n";
  echo "Run 'purge_cache.php --help' for detailed help.\n";
  exit(1);
}

function help() {
  $help = <<<EOHELP
**SUMMARY**

    **purge_cache.php**
        [--differential]
        [--changesets [changeset_id ...]]
    **purge_cache.php** --all
    **purge_cache.php** --help

    Purge various long-lived caches. Normally, Phabricator caches some data for
    a long time or indefinitely, but certain configuration changes might
    invalidate these caches. You can use this script to manually purge them.

    For instance, if you change display widths in Differential or configure
    syntax highlighting, you may want to purge the changeset cache (with
    "--changesets") so your changes are reflected in older diffs.

    If you change Remarkup rules, you may want to purge the Differential
    comment caches ("--differential") so older comments pick up the new rules.

    __--all__
        Purge all long-lived caches.

    __--changesets [changeset_id ...]__
        Purge Differential changeset render cache. If changeset_ids are present,
        the script will delete the cache for those changesets; otherwise it will
        delete the cache for all the changesets.

    __--differential__
        Purge Differential comment formatting cache.

    __--help__: show this help


EOHELP;
  echo phutil_console_format($help);
  exit(1);
}
