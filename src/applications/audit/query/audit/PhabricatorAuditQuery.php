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

final class PhabricatorAuditQuery {

  private $offset;
  private $limit;

  private $auditorPHIDs;
  private $commitPHIDs;

  private $status     = 'status-any';
  const STATUS_ANY    = 'status-any';
  const STATUS_OPEN   = 'status-open';

  public function withCommitPHIDs(array $commit_phids) {
    $this->commitPHIDs = $commit_phids;
    return $this;
  }

  public function withAuditorPHIDs(array $auditor_phids) {
    $this->auditorPHIDs = $auditor_phids;
    return $this;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function execute() {
    $table = new PhabricatorOwnersPackageCommitRelationship();
    $conn_r = $table->establishConnection('r');

    $where = $this->buildWhereClause($conn_r);
    $limit = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q',
      $table->getTableName(),
      $where,
      $limit);

    $audits = $table->loadAllFromArray($data);
    return $audits;

  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->commitPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'commitPHID IN (%Ls)',
        $this->commitPHIDs);
    }

    if ($this->auditorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'packagePHID IN (%Ls)',
        $this->auditorPHIDs);
    }

    $status = $this->status;
    switch ($status) {
      case self::STATUS_OPEN:
        $where[] = qsprintf(
          $conn_r,
          'auditStatus in (%Ls)',
          array(
            PhabricatorAuditStatusConstants::AUDIT_REQUIRED,
            PhabricatorAuditStatusConstants::CONCERNED,
          ));
        break;
      case self::STATUS_ANY:
        break;
      default:
        throw new Exception("Unknown status '{$status}'!");
    }

    if ($where) {
      $where = 'WHERE ('.implode(') AND (', $where).')';
    } else {
      $where = '';
    }

    return $where;
  }

  private function buildLimitClause($conn_r) {
    if ($this->limit && $this->offset) {
      return qsprintf($conn_r, 'LIMIT %d, %d', $this->offset, $this->limit);
    } else if ($this->limit) {
      return qsprintf($conn_r, 'LIMIT %d', $this->limit);
    } else if ($this->offset) {
      return qsprintf($conn_r, 'LIMIT %d, %d', $this->offset, PHP_INT_MAX);
    } else {
      return '';
    }
  }

}
