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

final class PhabricatorAuditListView extends AphrontView {

  private $audits;
  private $handles;
  private $authorityPHIDs = array();
  private $noDataString;

  public function setAudits(array $audits) {
    $this->audits = $audits;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setAuthorityPHIDs(array $phids) {
    $this->authorityPHIDs = $phids;
    return $this;
  }

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function getNoDataString() {
    return $this->noDataString;
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    foreach ($this->audits as $audit) {
      $phids[$audit->getCommitPHID()] = true;
      $phids[$audit->getPackagePHID()] = true;
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

    $authority = array_fill_keys($this->authorityPHIDs, true);

    $rowc = array();

    $last = null;
    $rows = array();
    foreach ($this->audits as $audit) {
      $commit_phid = $audit->getCommitPHID();
      if ($last == $commit_phid) {
        $commit_name = null;
      } else {
        $commit_name = $this->getHandle($commit_phid)->renderLink();
        $last = $commit_phid;
      }

      $reasons = $audit->getAuditReasons();
      foreach ($reasons as $key => $reason) {
        $reasons[$key] = phutil_escape_html($reason);
      }
      $reasons = implode('<br />', $reasons);

      $status_code = $audit->getAuditStatus();
      $status = PhabricatorAuditStatusConstants::getStatusName($status_code);

      $auditor_handle = $this->getHandle($audit->getPackagePHID());
      $rows[] = array(
        $commit_name,
        $auditor_handle->renderLink(),
        phutil_escape_html($status),
        $reasons,
      );

      if (empty($authority[$audit->getPackagePHID()])) {
        $rowc[] = null;
      } else {
        $rowc[] = 'highlighted';
      }
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Commit',
        'Auditor',
        'Status',
        'Details',
      ));
    $table->setColumnClasses(
      array(
        'pri',
        '',
        '',
        'wide',
      ));
    $table->setRowClasses($rowc);

    if ($this->noDataString) {
      $table->setNoDataString($this->noDataString);
    }

    return $table->render();
  }

}
