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

$project = id(new PhabricatorRepositoryArcanistProject())->loadOneWhere(
  'name = %s', $argv[1]);
if (!$project) {
  throw new Exception('No such arcanist project.');
}

$input = file_get_contents('php://stdin');
$normalized = array();
foreach (explode("\n", trim($input)) as $path) {
  // emulate the behavior of the symbol generation scripts
  $normalized[] = '/'.ltrim($path, './');
}
$paths = PhabricatorRepositoryCommitChangeParserWorker::lookupOrCreatePaths(
  $normalized);

$symbol = new PhabricatorRepositorySymbol();
$conn_w = $symbol->establishConnection('w');

foreach (array_chunk(array_values($paths), 128) as $chunk) {
  queryfx(
    $conn_w,
    'DELETE FROM %T WHERE arcanistProjectID = %d AND pathID IN (%Ld)',
    $symbol->getTableName(),
    $project->getID(),
    $chunk);
}
