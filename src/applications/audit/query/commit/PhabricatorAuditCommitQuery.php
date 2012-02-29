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

final class PhabricatorAuditCommitQuery {

  private $offset;
  private $limit;

  private $authorPHIDs;
  private $packagePHIDs;
  private $packageConstraint;

  private $needCommitData;

  private $status     = 'status-any';
  const STATUS_ANY    = 'status-any';
  const STATUS_OPEN   = 'status-open';

  public function withAuthorPHIDs(array $author_phids) {
    $this->authorPHIDs = $author_phids;
    return $this;
  }

  public function withPackagePHIDs(array $phids) {
    $this->packagePHIDs = $phids;
    return $this;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function needCommitData($need) {
    $this->needCommitData = $need;
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

    if ($this->packagePHIDs) {

      // TODO: This is an odd, awkward query plan because these rows aren't
      // on the same database as the commits. Once they're migrated we can
      // resolve this via JOIN.

      $table = new PhabricatorOwnersPackageCommitRelationship();
      $conn_r = $table->establishConnection('r');
      $phids = queryfx_all(
        $conn_r,
        'SELECT DISTINCT commitPHID FROM %T WHERE packagePHID IN (%Ls)
          ORDER BY id DESC %Q',
        $table->getTableName(),
        $this->packagePHIDs,
        $this->buildLimitClause($conn_r));
      $this->packageConstraint = ipull($phids, 'commitPHID');
      $this->limit = null;
      $this->offset = null;
    }

    $table = new PhabricatorRepositoryCommit();
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

    $commits = $table->loadAllFromArray($data);

    if ($this->needCommitData && $commits) {
      $data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
        'commitID in (%Ld)',
        mpull($commits, 'getID'));
      $data = mpull($data, null, 'getCommitID');
      foreach ($commits as $commit) {
        if (idx($data, $commit->getID())) {
          $commit->attachCommitData($data[$commit->getID()]);
        } else {
          $commit->attachCommitData(new PhabricatorRepositoryCommitData());
        }
      }
    }

    return $commits;

  }

  private function buildOrderClause($conn_r) {
    return 'ORDER BY epoch DESC';
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->authorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->packageConstraint !== null) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->packageConstraint);
    }

    $status = $this->status;
    switch ($status) {
      case self::STATUS_OPEN:
        $where[] = qsprintf(
          $conn_r,
          'auditStatus = %s',
          PhabricatorAuditCommitStatusConstants::CONCERN_RAISED);
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
