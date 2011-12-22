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

echo "Stripping remotes from repository default branches...\n";

$table = new PhabricatorRepository();
$conn_w = $table->establishConnection('w');

$repos = queryfx_all(
  $conn_w,
  'SELECT id, name, details FROM %T WHERE versionControlSystem = %s',
  $table->getTableName(),
  'git');

foreach ($repos as $repo) {
  $details = json_decode($repo['details'], true);

  $old = idx($details, 'default-branch', '');
  if (strpos($old, '/') === false) {
    continue;
  }

  $parts = explode('/', $old);
  $parts = array_filter($parts);
  $new = end($parts);

  $details['default-branch'] = $new;
  $new_details = json_encode($details);

  $id = $repo['id'];
  $name = $repo['name'];

  echo "Updating default branch for repository #{$id} '{$name}' from ".
       "'{$old}' to '{$new}' to remove the explicit remote.\n";
  queryfx(
    $conn_w,
    'UPDATE %T SET details = %s WHERE id = %d',
    $table->getTableName(),
    $new_details,
    $id);
}

echo "Done.\n";
