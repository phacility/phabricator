#!/usr/bin/env php
<?php

/*
 * Copyright 2011 Facebook, Inc.
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

phutil_require_module('phutil', 'symbols');
PhutilSymbolLoader::loadClass('PhabricatorRepository');
PhutilSymbolLoader::loadClass('PhabricatorRepositoryCommit');

$commit = new PhabricatorRepositoryCommit();

$conn_w = id(new PhabricatorRepository())->establishConnection('w');
$sizes = queryfx_all(
  $conn_w,
  'SELECT repositoryID, count(*) N FROM %T GROUP BY repositoryID',
  $commit->getTableName());
$sizes = ipull($sizes, 'N', 'repositoryID');

$maxes = queryfx_all(
  $conn_w,
  'SELECT repositoryID, max(epoch) maxEpoch FROM %T GROUP BY repositoryID',
  $commit->getTableName());
$maxes = ipull($maxes, 'maxEpoch', 'repositoryID');


$repository_ids = array_keys($sizes + $maxes);

echo "Updating ".count($repository_ids)." repositories";

foreach ($repository_ids as $repository_id) {
  $last_commit = queryfx_one(
    $conn_w,
    'SELECT id FROM %T WHERE repositoryID = %d AND epoch = %d LIMIT 1',
    $commit->getTableName(),
    $repository_id,
    idx($maxes, $repository_id, 0));
  if ($last_commit) {
    $last_commit = $last_commit['id'];
  } else {
    $last_commit = 0;
  }
  queryfx(
    $conn_w,
    'INSERT INTO %T (repositoryID, lastCommitID, size, epoch)
      VALUES (%d, %d, %d, %d) ON DUPLICATE KEY UPDATE
        lastCommitID = VALUES(lastCommitID),
        size = VALUES(size),
        epoch = VALUES(epoch)',
    PhabricatorRepository::TABLE_SUMMARY,
    $repository_id,
    $last_commit,
    idx($sizes, $repository_id, 0),
    idx($maxes, $repository_id, 0));
  echo ".";
}
echo "\ndone.\n";
