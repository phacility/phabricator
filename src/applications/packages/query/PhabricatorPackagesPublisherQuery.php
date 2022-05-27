<?php

final class PhabricatorPackagesPublisherQuery
  extends PhabricatorPackagesQuery {

  private $ids;
  private $phids;
  private $publisherKeys;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withPublisherKeys(array $keys) {
    $this->publisherKeys = $keys;
    return $this;
  }

  public function withNameNgrams($ngrams) {
    return $this->withNgramsConstraint(
      new PhabricatorPackagesPublisherNameNgrams(),
      $ngrams);
  }

  public function newResultObject() {
    return new PhabricatorPackagesPublisher();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'u.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'u.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->publisherKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'u.publisherKey IN (%Ls)',
        $this->publisherKeys);
    }

    return $where;
  }

  protected function getPrimaryTableAlias() {
    return 'u';
  }

}
