#!/usr/bin/env php
<?php

/*
 * Copyright 2012 Facebook, Inc.
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
 */

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
