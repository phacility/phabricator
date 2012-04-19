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

final class DifferentialRevisionStatusFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Revision Status:';
  }

  public function renderValueForRevisionView() {
    $revision = $this->getRevision();
    $diff = $this->getDiff();

    $status = $revision->getStatus();
    $info = null;

    if ($status == ArcanistDifferentialRevisionStatus::ACCEPTED) {
      switch ($diff->getSourceControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $next_step = '<tt>hg push</tt>';
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $next_step = '<tt>arc land</tt>';
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $next_step = '<tt>arc commit</tt>';
          break;
      }
      if ($next_step) {
        $info = ' &middot; Next step: '.$next_step;
      }

    } else if ($status == ArcanistDifferentialRevisionStatus::CLOSED) {
      $committed = $revision->getDateCommitted();
      $info = ' ('.phabricator_datetime($committed, $this->getUser()).')';
    }

    $status =
      ArcanistDifferentialRevisionStatus::getNameForRevisionStatus($status);
    return '<strong>'.$status.'</strong>'.$info;
  }

  public function shouldAppearOnRevisionList() {
    return true;
  }

  public function renderHeaderForRevisionList() {
    return 'Status';
  }

  public function renderValueForRevisionList(DifferentialRevision $revision) {
    return ArcanistDifferentialRevisionStatus::getNameForRevisionStatus(
      $revision->getStatus());
  }

}
