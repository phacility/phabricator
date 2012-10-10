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

final class PhabricatorRepositoryCommit extends PhabricatorRepositoryDAO {

  protected $repositoryID;
  protected $phid;
  protected $commitIdentifier;
  protected $epoch;
  protected $mailKey;
  protected $authorPHID;
  protected $auditStatus = PhabricatorAuditCommitStatusConstants::NONE;

  private $commitData;
  private $audits;
  private $isUnparsed;

  public function setIsUnparsed($is_unparsed) {
    $this->isUnparsed = $is_unparsed;
    return $this;
  }

  public function getIsUnparsed() {
    return $this->isUnparsed;
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID   => true,
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_CMIT);
  }

  public function loadCommitData() {
    if (!$this->getID()) {
      return null;
    }
    return id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $this->getID());
  }

  public function attachCommitData(PhabricatorRepositoryCommitData $data) {
    $this->commitData = $data;
    return $this;
  }

  public function getCommitData() {
    if (!$this->commitData) {
      throw new Exception("Attach commit data with attachCommitData() first!");
    }
    return $this->commitData;
  }

  public function attachAudits(array $audits) {
    assert_instances_of($audits, 'PhabricatorAuditComment');
    $this->audits = $audits;
    return $this;
  }

  public function getAudits() {
    return $this->audits;
  }

  public function save() {
    if (!$this->mailKey) {
      $this->mailKey = Filesystem::readRandomCharacters(20);
    }
    return parent::save();
  }

  public function delete() {
    $data = $this->loadCommitData();
    $this->openTransaction();

      if ($data) {
        $data->delete();
      }
      $result = parent::delete();

    $this->saveTransaction();
    return $result;
  }

  public function getDateCreated() {
    // This is primarily to make analysis of commits with the Fact engine work.
    return $this->getEpoch();
  }

  /**
   * Synchronize a commit's overall audit status with the individual audit
   * triggers.
   */
  public function updateAuditStatus(array $requests) {
    assert_instances_of($requests, 'PhabricatorRepositoryAuditRequest');

    $any_concern = false;
    $any_accept = false;
    $any_need = false;

    foreach ($requests as $request) {
      switch ($request->getAuditStatus()) {
        case PhabricatorAuditStatusConstants::AUDIT_REQUIRED:
          $any_need = true;
          break;
        case PhabricatorAuditStatusConstants::ACCEPTED:
          $any_accept = true;
          break;
        case PhabricatorAuditStatusConstants::CONCERNED:
          $any_concern = true;
          break;
      }
    }

    if ($any_concern) {
      $status = PhabricatorAuditCommitStatusConstants::CONCERN_RAISED;
    } else if ($any_accept) {
      if ($any_need) {
        $status = PhabricatorAuditCommitStatusConstants::PARTIALLY_AUDITED;
      } else {
        $status = PhabricatorAuditCommitStatusConstants::FULLY_AUDITED;
      }
    } else if ($any_need) {
      $status = PhabricatorAuditCommitStatusConstants::NEEDS_AUDIT;
    } else {
      $status = PhabricatorAuditCommitStatusConstants::NONE;
    }

    return $this->setAuditStatus($status);
  }

}
