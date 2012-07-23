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

echo "Migrating task revisions to edges...\n";
foreach (new LiskMigrationIterator(new ManiphestTask()) as $task) {
  $id = $task->getID();
  echo "Task {$id}: ";

  $revs = $task->getAttachedPHIDs(PhabricatorPHIDConstants::PHID_TYPE_DREV);
  if (!$revs) {
    echo "-\n";
    continue;
  }

  $editor = new PhabricatorEdgeEditor();
  $editor->setSuppressEvents(true);
  foreach ($revs as $rev) {
    $editor->addEdge(
      $task->getPHID(),
      PhabricatorEdgeConfig::TYPE_TASK_HAS_RELATED_DREV,
      $rev);
  }
  $editor->save();
  echo "OKAY\n";
}

echo "Done.\n";
