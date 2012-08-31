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

final class DifferentialBranchFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Branch:';
  }

  public function renderValueForRevisionView() {
    $diff = $this->getManualDiff();

    $branch = $diff->getBranch();
    $bookmark = $diff->getBookmark();
    $has_branch = ($branch != '');
    $has_bookmark = ($bookmark != '');
    if ($has_branch && $has_bookmark) {
      $branch = "{$bookmark} bookmark on {$branch} branch";
    } else if ($has_bookmark) {
      $branch = "{$bookmark} bookmark";
    } else if (!$has_branch) {
      return null;
    }

    return phutil_escape_html($branch);
  }

  public function renderValueForMail($phase) {
    $status = $this->getRevision()->getStatus();

    if ($status != ArcanistDifferentialRevisionStatus::NEEDS_REVISION &&
        $status != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      return null;
    }

    $diff = $this->getRevision()->loadActiveDiff();
    if ($diff) {
      $branch = $diff->getBranch();
      if ($branch) {
        return "BRANCH\n  $branch";
      }
    }
  }

}
