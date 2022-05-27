<?php

final class AlmanacNetworkQuery
  extends AlmanacQuery {

  private $ids;
  private $phids;
  private $names;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function newResultObject() {
    return new AlmanacNetwork();
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function withNameNgrams($ngrams) {
    return $this->withNgramsConstraint(
      new AlmanacNetworkNameNgrams(),
      $ngrams);
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'network.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'network.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->names !== null) {
      $where[] = qsprintf(
        $conn,
        'network.name IN (%Ls)',
        $this->names);
    }

    return $where;
  }

  protected function getPrimaryTableAlias() {
    return 'network';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

}
