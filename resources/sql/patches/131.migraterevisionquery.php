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

$table = new DifferentialRevision();
$conn_w = $table->establishConnection('w');

echo "Migrating revisions";
do {
  $revisions = id(new DifferentialRevision())
    ->loadAllWhere('branchName IS NULL LIMIT 1000');

  foreach ($revisions as $revision) {
    echo ".";

    $diff = $revision->loadActiveDiff();
    if (!$diff) {
      continue;
    }

    $branch_name = $diff->getBranch();
    $arc_project_phid = $diff->getArcanistProjectPHID();

    queryfx(
      $conn_w,
      'UPDATE %T SET branchName = %s, arcanistProjectPHID = %s WHERE id = %d',
      $table->getTableName(),
      $branch_name,
      $arc_project_phid,
      $revision->getID());
  }
} while (count($revisions) == 1000);
echo "\nDone.\n";
