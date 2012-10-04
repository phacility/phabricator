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

  private $commitPHIDs;
  private $authorPHIDs;
  private $packagePHIDs;
  private $identifiers = array();

  private $needCommitData;
  private $needAudits;

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

  public function withCommitPHIDs(array $phids) {
    $this->commitPHIDs = $phids;
    return $this;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function withIdentifiers($repository_id, array $identifiers) {
    $this->identifiers[] = array($repository_id, $identifiers);
    return $this;
  }

  public function needCommitData($need) {
    $this->needCommitData = $need;
    return $this;
  }

  public function needAudits($need) {
    $this->needAudits = $need;
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

    $table = new PhabricatorRepositoryCommit();
    $conn_r = $table->establishConnection('r');

    $join  = $this->buildJoinClause($conn_r);
    $where = $this->buildWhereClause($conn_r);
    $order = $this->buildOrderClause($conn_r);
    $limit = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT c.* FROM %T c %Q %Q %Q %Q',
      $table->getTableName(),
      $join,
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

    if ($this->needAudits && $commits) {
      $audits = id(new PhabricatorAuditComment())->loadAllWhere(
        'targetPHID in (%Ls)',
        mpull($commits, 'getPHID'));
      $audits = mgroup($audits, 'getTargetPHID');
      foreach ($commits as $commit) {
        $commit->attachAudits(idx($audits, $commit->getPHID(), array()));
      }
    }

    return $commits;

  }

  private function buildOrderClause($conn_r) {
    return 'ORDER BY c.epoch DESC';
  }

  private function buildJoinClause($conn_r) {
    $join = array();

    if ($this->packagePHIDs) {
      $join[] = qsprintf(
        $conn_r,
        'JOIN %T req ON c.phid = req.commitPHID',
        id(new PhabricatorRepositoryAuditRequest())->getTableName());
    }

    if ($join) {
      $join = implode(' ', $join);
    } else {
      $join = '';
    }

    return $join;
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->commitPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'c.phid IN (%Ls)',
        $this->commitPHIDs);
    }

    if ($this->authorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'c.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->packagePHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'req.auditorPHID in (%Ls)',
        $this->packagePHIDs);
    }

    if ($this->identifiers) {
      $clauses = array();
      foreach ($this->identifiers as $spec) {
        list($repository_id, $identifiers) = $spec;
        if ($identifiers) {
          $clauses[] = qsprintf(
            $conn_r,
            'c.repositoryID = %d AND c.commitIdentifier IN (%Ls)',
            $repository_id,
            $identifiers);
        }
      }
      if ($clauses) {
        $where[] = '('.implode(') OR (', $clauses).')';
      }
    }

    $status = $this->status;
    switch ($status) {
      case self::STATUS_OPEN:
        $where[] = qsprintf(
          $conn_r,
          'c.auditStatus = %s',
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
