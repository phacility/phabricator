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

class DifferentialRevision extends DifferentialDAO {

  protected $title;
  protected $status;

  protected $summary;
  protected $testPlan;
  protected $revertPlan;
  protected $blameRevision;

  protected $phid;
  protected $ownerPHID;

  protected $dateCommitted;

  protected $lineCount;

  private $related;
  private $forbidden;

  const RELATIONSHIP_TABLE    = 'differential_relationship';

  const RELATION_REVIEWER     = 'revw';
  const RELATION_SUBSCRIBED   = 'subd';

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID('DREV');
  }

  public function loadDiffs() {
    if (!$this->getID()) {
      return array();
    }
    return id(new DifferentialDiff())->loadAllWhere(
      'revisionID = %d',
      $this->getID());
  }

  public function loadRelationships() {
    if (!$this->getID()) {
      $this->relationships = array();
      $this->related = array();
      $this->forbidden = array();
      return;
    }

    $data = queryfx_all(
      $this->establishConnection('r'),
      'SELECT * FROM %T WHERE revisionID = %d ORDER BY sequence',
      self::RELATIONSHIP_TABLE,
      $this->getID());

    $related = array();
    $forbidden = array();

    foreach ($data as $row) {
      if ($row['forbidden']) {
        $forbidden[] = $row;
      } else {
        $related[] = $row;
      }
    }

    $this->related = igroup($related, 'relation');
    $this->forbidden = igroup($related, 'relation');
    $this->relationships = igroup($data, 'relation');

    return $this;
  }

  public function getReviewers() {
    return $this->getRelatedPHIDs(self::RELATION_REVIEWER);
  }

  public function getCCPHIDs() {
    return $this->getRelatedPHIDs(self::RELATION_SUBSCRIBED);
  }

  private function getRelatedPHIDs($relation) {
    if ($this->related === null) {
      throw new Exception("Must load relationships!");
    }

    $related = idx($this->related, $relation, array());

    return ipull($related, 'objectPHID');
  }

  public function getRawRelations($relation) {
    return idx($this->relationships, $relation, array());
  }

  public function writeRelatedPHIDs(
    $relation,
    $phids,
    $reason_phid,
    $forbidden) {

    $conn_w = $this->establishConnection('w');

    $sql = array();
    $phids = array_values($phids);
    foreach ($phids as $key => $phid) {
      $sql[] = qsprintf(
        $conn_w,
        '(%d, %s, %d, %s, %d)',
        $this->getRevisionID(),
        $phid,
        $key,
        $reason_phid,
        $forbidden);
    }

    $conn_w->openTransaction();
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE revisionID = %d AND relation = %s
          AND forbidden = %d
          AND relatedPHID NOT IN (%Ls)',
        self::RELATIONSHIP_TABLE,
        $this->getID(),
        $relation,
        $forbidden,
        $phids);
      queryfx(
        $conn_w,
        'INSERT INTO %T
          (revisionID, relatedPHID, sequence, reason_phid, forbidden)
        VALUES %Q
          ON DUPLICATE KEY UPDATE sequence = VALUES(sequence)',
        self::RELATIONSHIP_TABLE,
        implode(', ', $sql));
    $conn_w->saveTransaction();
  }

}
