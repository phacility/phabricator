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

/**
 * @group conduit
 */
class ConduitAPI_differential_getrevision_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Load the content of a revision from Differential.";
  }

  public function defineParamTypes() {
    return array(
      'revision_id' => 'required id',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_REVISION'    => 'No such revision exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $diff = null;

    $revision_id = $request->getValue('revision_id');
    $revision = id(new DifferentialRevision())->load($revision_id);
    if (!$revision) {
      throw new ConduitException('ERR_BAD_REVISION');
    }

    $revision->loadRelationships();
    $reviewer_phids = array_values($revision->getReviewers());

    $diffs = $revision->loadDiffs();

    $diff_dicts = array();
    foreach ($diffs as $diff) {
      $diff->attachChangesets($diff->loadChangesets());
      // TODO: We could batch this to improve performance.
      foreach ($diff->getChangesets() as $changeset) {
        $changeset->attachHunks($changeset->loadHunks());
      }
      $diff_dicts[] = $diff->getDiffDict();
    }

    $commit_dicts = array();
    $commit_phids = $revision->loadCommitPHIDs();
    $handles = id(new PhabricatorObjectHandleData($commit_phids))
      ->loadHandles();

    foreach ($commit_phids as $commit_phid) {
      $commit_dicts[] = array(
        'fullname'      => $handles[$commit_phid]->getFullName(),
        'dateCommitted' => $handles[$commit_phid]->getTimestamp(),
      );
    }

    $auxiliary_fields = $this->loadAuxiliaryFields($revision);

    $dict = array(
      'id' => $revision->getID(),
      'phid' => $revision->getPHID(),
      'authorPHID' => $revision->getAuthorPHID(),
      'uri' => PhabricatorEnv::getURI('/D'.$revision->getID()),
      'title' => $revision->getTitle(),
      'status' => $revision->getStatus(),
      'statusName'  =>
        ArcanistDifferentialRevisionStatus::getNameForRevisionStatus(
          $revision->getStatus()),
      'summary' => $revision->getSummary(),
      'testPlan' => $revision->getTestPlan(),
      'lineCount' => $revision->getLineCount(),
      'reviewerPHIDs' => $reviewer_phids,
      'diffs' => $diff_dicts,
      'commits' => $commit_dicts,
      'auxiliary' => $auxiliary_fields,
    );

    return $dict;
  }

  private function loadAuxiliaryFields(DifferentialRevision $revision) {
    $aux_fields = DifferentialFieldSelector::newSelector()
      ->getFieldSpecifications();
    foreach ($aux_fields as $key => $aux_field) {
      if (!$aux_field->shouldAppearOnConduitView()) {
        unset($aux_fields[$key]);
      }
    }

    $aux_fields = DifferentialAuxiliaryField::loadFromStorage(
      $revision,
      $aux_fields);

    return mpull($aux_fields, 'getValueForConduit', 'getKeyForConduit');
  }

}
