<?php

final class PhabricatorProjectQuery {

  private $owners;
  private $members;

  private $limit;
  private $offset;

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  public function setOwners(array $owners) {
    $this->owners = $owners;
    return $this;
  }

  public function setMembers(array $members) {
    $this->members = $members;
    return $this;
  }

  public function execute() {
    $table = id(new PhabricatorProject());
    $conn_r = $table->establishConnection('r');

    $joins = $this->buildJoinsClause($conn_r);

    $limit = null;
    if ($this->limit) {
      $limit = qsprintf(
        $conn_r,
        'LIMIT %d, %d',
        $offset,
        $limit);
    } else if ($this->offset) {
      $limit = qsprintf(
        $conn_r,
        'LIMIT %d, %d',
        $offset,
        PHP_INT_MAX);
    }

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T p %Q %Q',
      $table->getTableName();
      $joins,
      $limit);
  }

  private function buildJoinsClause($conn_r) {
    $affil_table = new PhabricatorProjectAffiliation();

    $joins = array();
    if ($this->owners) {
      $joins[] = qsprintf(
        'JOIN %T owner ON owner.projectPHID = p.phid AND owner.isOwner = 1
          AND owner.userPHID in (%Ls)',
        $affil_table->getTableName(),
        $this->owners);
    }

    if ($this->members) {
      $joins[] = qsprintf(
        'JOIN %T member ON member.projectPHID = p.phid AND member.status != %s
          AND member.userPHID in (%Ls)',
        $affil_table->getTableName(),
        'former',
        $this->members);
    }

    return implode(' ', $joins);
  }

}