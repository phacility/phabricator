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

final class DifferentialCommitsFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function getRequiredHandlePHIDsForRevisionView() {
    return $this->getCommitPHIDs();
  }

  public function renderLabelForRevisionView() {
    return 'Commits:';
  }

  public function renderValueForRevisionView() {
    $commit_phids = $this->getCommitPHIDs();
    if (!$commit_phids) {
      return null;
    }

    $links = array();
    foreach ($commit_phids as $commit_phid) {
      $links[] = $this->getHandle($commit_phid)->renderLink();
    }

    return implode('<br />', $links);
  }

  private function getCommitPHIDs() {
    $revision = $this->getRevision();
    return $revision->getCommitPHIDs();
  }

  public function renderValueForMail($phase) {
    $revision = $this->getRevision();

    if ($revision->getStatus() != ArcanistDifferentialRevisionStatus::CLOSED) {
      return null;
    }

    $phids = $revision->loadCommitPHIDs();
    if (!$phids) {
      return null;
    }

    $body = array();
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    if (count($handles) == 1) {
      $body[] = "COMMIT";
    } else {
      // This is unlikely to ever happen since we'll send this mail the
      // first time we discover a commit, but it's not impossible if data
      // was migrated, etc.
      $body[] = "COMMITS";
    }

    foreach ($handles as $handle) {
      $body[] = '  '.PhabricatorEnv::getProductionURI($handle->getURI());
    }

    return implode("\n", $body);
  }

}
