#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

if ($argc != 3 || !is_numeric($argv[1]) || !is_numeric($argv[2])) {
  echo "Usage: {$argv[0]} <diff_id_from> <diff_id_to>\n";
  exit(1);
}
list(, $from, $to) = $argv;

for ($diff_id = $from; $diff_id <= $to; $diff_id++) {
  echo "Processing $diff_id";
  $diff = id(new DifferentialDiff())->load($diff_id);
  if ($diff) {
    $diff->attachChangesets($diff->loadChangesets());
    $orig_copy = array();
    foreach ($diff->getChangesets() as $i => $changeset) {
      $orig_copy[$i] = idx((array)$changeset->getMetadata(), 'copy:lines');
      $changeset->attachHunks($changeset->loadHunks());
    }
    $diff->detectCopiedCode();
    foreach ($diff->getChangesets() as $i => $changeset) {
      if (idx($changeset->getMetadata(), 'copy:lines') || $orig_copy[$i]) {
        echo ".";
        $changeset->save();
      }
    }
  }
  echo "\n";
}

echo "Done.\n";
