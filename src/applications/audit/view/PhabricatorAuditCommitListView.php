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

final class PhabricatorAuditCommitListView extends AphrontView {

  private $user;
  private $commits;
  private $handles;
  private $noDataString;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function setCommits(array $commits) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');
    $this->commits = $commits;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setAuthorityPHIDs(array $phids) {
    $this->authorityPHIDs = $phids;
    return $this;
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    foreach ($this->commits as $commit) {
      if ($commit->getAuthorPHID()) {
        $phids[$commit->getAuthorPHID()] = true;
      }
      $phids[$commit->getPHID()] = true;
      if ($commit->getAudits()) {
        foreach ($commit->getAudits() as $audit) {
          $phids[$audit->getActorPHID()] = true;
        }
      }
    }
    return array_keys($phids);
  }

  private function getHandle($phid) {
    $handle = idx($this->handles, $phid);
    if (!$handle) {
      throw new Exception("No handle for '{$phid}'!");
    }
    return $handle;
  }

  public function render() {
    $rows = array();
    foreach ($this->commits as $commit) {
      $commit_name = $this->getHandle($commit->getPHID())->renderLink();
      $author_name = null;
      if ($commit->getAuthorPHID()) {
        $author_name = $this->getHandle($commit->getAuthorPHID())->renderLink();
      }
      $auditors = array();
      if ($commit->getAudits()) {
        foreach ($commit->getAudits() as $audit) {
          $actor_phid = $audit->getActorPHID();
          $auditors[$actor_phid] = $this->getHandle($actor_phid)->renderLink();
        }
      }
      $rows[] = array(
        $commit_name,
        $author_name,
        phutil_escape_html($commit->getCommitData()->getSummary()),
        PhabricatorAuditCommitStatusConstants::getStatusName(
          $commit->getAuditStatus()),
        implode(', ', $auditors),
        phabricator_datetime($commit->getEpoch(), $this->user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Commit',
        'Author',
        'Summary',
        'Audit Status',
        'Auditors',
        'Date',
      ));
    $table->setColumnClasses(
      array(
        'n',
        '',
        'wide',
        '',
        '',
        '',
      ));

    if ($this->commits && reset($this->commits)->getAudits() === null) {
      $table->setColumnVisibility(
        array(
          true,
          true,
          true,
          true,
          false,
          true,
        ));
    }

    if ($this->noDataString) {
      $table->setNoDataString($this->noDataString);
    }

    return $table->render();
  }

}
