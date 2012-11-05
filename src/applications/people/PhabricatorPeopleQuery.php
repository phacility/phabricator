<?php

final class PhabricatorPeopleQuery extends PhabricatorOffsetPagedQuery {
  private $usernames;
  private $realnames;
  private $emails;
  private $phids;
  private $ids;

  private $needPrimaryEmail;

  public function withIds(array $ids) {
    $this->ids = $ids;
    return $this;
  }
  public function withPhids(array $phids) {
    $this->phids = $phids;
    return $this;
  }
  public function withEmails(array $emails) {
    $this->emails = $emails;
    return $this;
  }
  public function withRealnames(array $realnames) {
    $this->realnames = $realnames;
    return $this;
  }
  public function withUsernames(array $usernames) {
    $this->usernames = $usernames;
    return $this;
  }

  public function needPrimaryEmail($need) {
    $this->needPrimaryEmail = $need;
    return $this;
  }

  public function execute() {
    $table  = new PhabricatorUser();
    $conn_r = $table->establishConnection('r');

    $joins_clause = $this->buildJoinsClause($conn_r);
    $where_clause = $this->buildWhereClause($conn_r);
    $limit_clause = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T user %Q %Q %Q',
      $table->getTableName(),
      $joins_clause,
      $where_clause,
      $limit_clause);

    if ($this->needPrimaryEmail) {
      $table->putInSet(new LiskDAOSet());
    }

    $users = $table->loadAllFromArray($data);
    return $users;
  }

  private function buildJoinsClause($conn_r) {
    $joins = array();

    if ($this->emails) {
      $email_table = new PhabricatorUserEmail();
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T email ON email.userPHID = user.PHID',
        $email_table->getTableName());
    }

    $joins = implode(' ', $joins);
    return  $joins;
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->usernames) {
      $where[] = qsprintf($conn_r,
                          'user.userName IN (%Ls)',
                          $this->usernames);
    }
    if ($this->emails) {
      $where[] = qsprintf($conn_r,
                          'email.address IN (%Ls)',
                          $this->emails);
    }
    if ($this->realnames) {
      $where[] = qsprintf($conn_r,
                          'user.realName IN (%Ls)',
                          $this->realnames);
    }
    if ($this->phids) {
      $where[] = qsprintf($conn_r,
                          'user.phid IN (%Ls)',
                          $this->phids);
    }
    if ($this->ids) {
      $where[] = qsprintf($conn_r,
                          'user.id IN (%Ld)',
                          $this->ids);
    }

    return $this->formatWhereClause($where);
  }
}
