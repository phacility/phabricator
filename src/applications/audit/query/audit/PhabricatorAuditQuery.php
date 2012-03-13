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

  private $needCommits;
  private $needCommitData;

  private $status     = 'status-any';
  const STATUS_ANY    = 'status-any';
  const STATUS_OPEN   = 'status-open';

  private $commits;

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

  public function needCommits($need) {
    $this->needCommits = $need;
    return $this;
  }

  public function needCommitData($need) {
    $this->needCommitData = $need;
    return $this;
  }

  public function execute() {
    $table = new PhabricatorRepositoryAuditRequest();
    $conn_r = $table->establishConnection('r');

    $where = $this->buildWhereClause($conn_r);
    $order = $this->buildOrderClause($conn_r);
    $limit = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $where,
      $order,
      $limit);

    $audits = $table->loadAllFromArray($data);

    if ($this->needCommits || $this->needCommitData) {
      $phids = mpull($audits, 'getCommitPHID', 'getCommitPHID');
      if ($phids) {
        $cquery = new PhabricatorAuditCommitQuery();
        $cquery->needCommitData($this->needCommitData);
        $cquery->withCommitPHIDs(array_keys($phids));
        $commits = $cquery->execute();
      } else {
        $commits = array();
      }
      $this->commits = $commits;
    }

    return $audits;
  }

  public function getCommits() {
    if ($this->commits === null) {
      throw new Exception(
        "Call needCommits() or needCommitData() and then execute() the query ".
        "before calling getCommits()!");
    }

    return $this->commits;
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
        'auditorPHID IN (%Ls)',
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
            PhabricatorAuditStatusConstants::AUDIT_REQUESTED,
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

  private function buildOrderClause($conn_r) {
    return 'ORDER BY id DESC';
  }

}
