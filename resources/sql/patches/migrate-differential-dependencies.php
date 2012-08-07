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

echo "Migrating differential dependencies to edges...\n";
foreach (new LiskMigrationIterator(new DifferentialRevision()) as $rev) {
  $id = $rev->getID();
  echo "Revision {$id}: ";

  $deps = $rev->getAttachedPHIDs(PhabricatorPHIDConstants::PHID_TYPE_DREV);
  if (!$deps) {
    echo "-\n";
    continue;
  }

  $editor = new PhabricatorEdgeEditor();
  $editor->setSuppressEvents(true);
  foreach ($deps as $dep) {
    $editor->addEdge(
      $rev->getPHID(),
      PhabricatorEdgeConfig::TYPE_DREV_DEPENDS_ON_DREV,
      $dep);
  }
  $editor->save();
  echo "OKAY\n";
}

echo "Done.\n";
