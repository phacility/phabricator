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

echo "Migrating project members to edges...\n";
foreach (new LiskMigrationIterator(new PhabricatorProject()) as $proj) {
  $id = $proj->getID();
  echo "Project {$id}: ";

  $members = queryfx_all(
    $proj->establishConnection('r'),
    'SELECT userPHID FROM %T WHERE projectPHID = %s',
    'project_affiliation',
    $proj->getPHID());

  if (!$members) {
    echo "-\n";
    continue;
  }

  $members = ipull($members, 'userPHID');

  $editor = new PhabricatorEdgeEditor();
  $editor->setSuppressEvents(true);
  foreach ($members as $user_phid) {
    $editor->addEdge(
      $proj->getPHID(),
      PhabricatorEdgeConfig::TYPE_PROJ_MEMBER,
      $user_phid);
  }
  $editor->save();
  echo "OKAY\n";
}

echo "Done.\n";
