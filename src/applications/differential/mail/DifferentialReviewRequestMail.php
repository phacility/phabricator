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

abstract class DifferentialReviewRequestMail extends DifferentialMail {

  protected $comments;

  public function setComments($comments) {
    $this->comments = $comments;
    return $this;
  }

  public function getComments() {
    return $this->comments;
  }

  public function __construct(
    DifferentialRevision $revision,
    PhabricatorObjectHandle $actor,
    array $changesets) {
    assert_instances_of($changesets, 'DifferentialChangeset');

    $this->setRevision($revision);
    $this->setActorHandle($actor);
    $this->setChangesets($changesets);
  }

  protected function renderReviewersLine() {
    $reviewers = $this->getRevision()->getReviewers();
    $handles = id(new PhabricatorObjectHandleData($reviewers))->loadHandles();
    return 'Reviewers: '.$this->renderHandleList($handles, $reviewers);
  }

  protected function renderReviewRequestBody() {
    $revision = $this->getRevision();

    $body = array();
    if ($this->isFirstMailToRecipients()) {
      if ($revision->getSummary() != '') {
        $body[] = $this->formatText($revision->getSummary());
        $body[] = null;
      }

      if ($revision->getTestPlan() != '') {
        $body[] = 'TEST PLAN';
        $body[] = $this->formatText($revision->getTestPlan());
        $body[] = null;
      }
    } else {
      if (strlen($this->getComments())) {
        $body[] = $this->formatText($this->getComments());
        $body[] = null;
      }
    }

    $body[] = $this->renderRevisionDetailLink();
    $body[] = null;

    $task_phids = $this->getManiphestTaskPHIDs();
    if ($task_phids) {
      $handles = id(new PhabricatorObjectHandleData($task_phids))
        ->loadHandles();
      $body[] = 'MANIPHEST TASKS';
      foreach ($handles as $handle) {
        $body[] = '  '.PhabricatorEnv::getProductionURI($handle->getURI());
      }
      $body[] = null;
    }

    $changesets = $this->getChangesets();
    if ($changesets) {
      $body[] = 'AFFECTED FILES';
      foreach ($changesets as $changeset) {
        $body[] = '  '.$changeset->getFilename();
      }
      $body[] = null;
    }

    $inline_key = 'metamta.differential.inline-patches';
    $inline_max_length = PhabricatorEnv::getEnvConfig($inline_key);
    if ($inline_max_length) {
      $patch = $this->buildPatch();
      if (count(explode("\n", $patch)) <= $inline_max_length) {
        $body[] = 'CHANGE DETAILS';
        $body[] = $patch;
      }
    }

    return implode("\n", $body);
  }

  protected function buildAttachments() {
    $attachments = array();

    if (PhabricatorEnv::getEnvConfig('metamta.differential.attach-patches')) {

      $revision = $this->getRevision();
      $revision_id = $revision->getID();

      $diffs = $revision->loadDiffs();
      $diff_number = count($diffs);

      $attachments[] = new PhabricatorMetaMTAAttachment(
        $this->buildPatch(),
        "D{$revision_id}.{$diff_number}.patch",
        'text/x-patch; charset=utf-8'
      );
    }

    return $attachments;
  }

  public function loadFileByPHID($phid) {
    $file = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s',
      $phid);
    if (!$file) {
      return null;
    }
    return $file->loadFileData();
  }

  private function buildPatch() {
    $revision = $this->getRevision();
    $revision_id = $revision->getID();

    $diffs = $revision->loadDiffs();
    $diff_number = count($diffs);
    $diff = array_pop($diffs);

    $diff->attachChangesets($diff->loadChangesets());
    // TODO: We could batch this to improve performance.
    foreach ($diff->getChangesets() as $changeset) {
      $changeset->attachHunks($changeset->loadHunks());
    }
    $diff_dict = $diff->getDiffDict();

    $changes = array();
    foreach ($diff_dict['changes'] as $changedict) {
      $changes[] = ArcanistDiffChange::newFromDictionary($changedict);
    }
    $bundle = ArcanistBundle::newFromChanges($changes);

    $bundle->setLoadFileDataCallback(array($this, 'loadFileByPHID'));

    $format = PhabricatorEnv::getEnvConfig('metamta.differential.patch-format');
    switch ($format) {
      case 'git':
        return $bundle->toGitPatch();
        break;
      case 'unified':
      default:
        return $bundle->toUnifiedDiff();
        break;
    }
  }

}
