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

echo "Updating old commit authors...\n";

$table = new PhabricatorRepositoryCommit();
$conn = $table->establishConnection('w');
$data = new PhabricatorRepositoryCommitData();
$commits = queryfx_all(
  $conn,
  'SELECT c.id id, c.authorPHID authorPHID, d.commitDetails details
    FROM %T c JOIN %T d ON d.commitID = c.id
    WHERE c.authorPHID IS NULL',
  $table->getTableName(),
  $data->getTableName());

foreach ($commits as $commit) {
  $id = $commit['id'];
  $details = json_decode($commit['details'], true);
  $author_phid = idx($details, 'authorPHID');
  if ($author_phid) {
    queryfx(
      $conn,
      'UPDATE %T SET authorPHID = %s WHERE id = %d',
      $table->getTableName(),
      $author_phid,
      $id);
    echo "#{$id}\n";
  }
}

echo "Done.\n";


echo "Updating old commit mailKeys...\n";

$table = new PhabricatorRepositoryCommit();
$conn = $table->establishConnection('w');
$commits = queryfx_all(
  $conn,
  'SELECT id FROM %T WHERE mailKey = %s',
  $table->getTableName(),
  '');

foreach ($commits as $commit) {
  $id = $commit['id'];
  queryfx(
    $conn,
    'UPDATE %T SET mailKey = %s WHERE id = %d',
    $table->getTableName(),
    Filesystem::readRandomCharacters(20),
    $id);
  echo "#{$id}\n";
}

echo "Done.\n";
