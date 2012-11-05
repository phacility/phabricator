<?php

final class PhabricatorAuditQuery {

  private $offset;
  private $limit;

  private $auditorPHIDs;
  private $commitPHIDs;

  private $needCommits;
  private $needCommitData;

  private $awaitingUser;

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

  public function withAwaitingUser(PhabricatorUser $user) {
    $this->awaitingUser = $user;
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

    $joins = $this->buildJoinClause($conn_r);
    $where = $this->buildWhereClause($conn_r);
    $order = $this->buildOrderClause($conn_r);
    $limit = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT req.* FROM %T req %Q %Q %Q %Q',
      $table->getTableName(),
      $joins,
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

  private function buildJoinClause($conn_r) {

    $joins = array();

    if ($this->awaitingUser) {
      // Join the request table on the awaiting user's requests, so we can
      // filter out package and project requests which the user has resigned
      // from.
      $joins[] = qsprintf(
        $conn_r,
        'LEFT JOIN %T awaiting ON req.commitPHID = awaiting.commitPHID AND
          awaiting.auditorPHID = %s',
        id(new PhabricatorRepositoryAuditRequest())->getTableName(),
        $this->awaitingUser->getPHID());

      // Join the commit table so we can get the commit author into the result
      // row and filter by it later.
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T commit ON req.commitPHID = commit.phid',
        id(new PhabricatorRepositoryCommit())->getTableName());
    }

    if ($joins) {
      return implode(' ', $joins);
    } else {
      return '';
    }
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->commitPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'req.commitPHID IN (%Ls)',
        $this->commitPHIDs);
    }

    if ($this->auditorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'req.auditorPHID IN (%Ls)',
        $this->auditorPHIDs);
    }

    if ($this->awaitingUser) {
      // Exclude package and project audits associated with commits where
      // the user is the author.
      $where[] = qsprintf(
        $conn_r,
        '(commit.authorPHID IS NULL OR commit.authorPHID != %s)
          OR (req.auditorPHID = %s)',
        $this->awaitingUser->getPHID(),
        $this->awaitingUser->getPHID());
    }

    $status = $this->status;
    switch ($status) {
      case self::STATUS_OPEN:
        $where[] = qsprintf(
          $conn_r,
          'req.auditStatus in (%Ls)',
          PhabricatorAuditStatusConstants::getOpenStatusConstants());
        if ($this->awaitingUser) {
          $where[] = qsprintf(
            $conn_r,
            'awaiting.auditStatus IS NULL OR awaiting.auditStatus != %s',
            PhabricatorAuditStatusConstants::RESIGNED);
        }
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
    return 'ORDER BY req.id DESC';
  }

}
