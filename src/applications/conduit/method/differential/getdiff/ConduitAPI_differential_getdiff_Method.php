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

/**
 * @group conduit
 */
class ConduitAPI_differential_getdiff_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Load the content of a diff from Differential.";
  }

  public function defineParamTypes() {
    return array(
      'revision_id' => 'optional id',
      'diff_id'     => 'optional id',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_REVISION'    => 'No such revision exists.',
      'ERR_BAD_DIFF'        => 'No such diff exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $diff = null;

    $revision_id = $request->getValue('revision_id');
    if ($revision_id) {
      $revision = id(new DifferentialRevision())->load($revision_id);
      if (!$revision) {
        throw new ConduitException('ERR_BAD_REVISION');
      }
      $diff = id(new DifferentialDiff())->loadOneWhere(
        'revisionID = %d ORDER BY id DESC LIMIT 1',
        $revision->getID());
    } else {
      $diff_id = $request->getValue('diff_id');
      if ($diff_id) {
        $diff = id(new DifferentialDiff())->load($diff_id);
      }
    }

    if (!$diff) {
      throw new ConduitException('ERR_BAD_DIFF');
    }

    $diff->attachChangesets($diff->loadChangesets());
    // TODO: We could batch this to improve performance.
    foreach ($diff->getChangesets() as $changeset) {
      $changeset->attachHunks($changeset->loadHunks());
    }

    return $this->createDiffDict($diff);
  }

  public static function createDiffDict(DifferentialDiff $diff) {
    $dict = array(
      'id' => $diff->getID(),
      'parent' => $diff->getParentRevisionID(),
      'revisionID' => $diff->getRevisionID(),
      'sourceControlBaseRevision' => $diff->getSourceControlBaseRevision(),
      'sourceControlPath' => $diff->getSourceControlPath(),
      'unitStatus' => $diff->getUnitStatus(),
      'lintStatus' => $diff->getLintStatus(),
      'changes' => array(),
      'properties' => array(),
    );

    foreach ($diff->getChangesets() as $changeset) {
      $hunks = array();
      foreach ($changeset->getHunks() as $hunk) {
        $hunks[] = array(
          'oldOffset' => $hunk->getOldOffset(),
          'newOffset' => $hunk->getNewOffset(),
          'oldLength' => $hunk->getOldLen(),
          'newLength' => $hunk->getNewLen(),
          'addLines'  => null,
          'delLines'  => null,
          'isMissingOldNewline' => null,
          'isMissingNewNewline' => null,
          'corpus'    => $hunk->getChanges(),
        );
      }
      $change = array(
        'metadata'      => $changeset->getMetadata(),
        'oldPath'       => $changeset->getOldFile(),
        'currentPath'   => $changeset->getFileName(),
        'awayPaths'     => $changeset->getAwayPaths(),
        'oldProperties' => $changeset->getOldProperties(),
        'newProperties' => $changeset->getNewProperties(),
        'type'          => $changeset->getChangeType(),
        'fileType'      => $changeset->getFileType(),
        'commitHash'    => null,
        'hunks'         => $hunks,
      );
      $dict['changes'][] = $change;
    }

    $properties = id(new DifferentialDiffProperty())->loadAllWhere(
      'diffID = %d',
      $diff->getID());
    foreach ($properties as $property) {
      $dict['properties'][$property->getName()] = $property->getData();
    }

    return $dict;
  }

}
