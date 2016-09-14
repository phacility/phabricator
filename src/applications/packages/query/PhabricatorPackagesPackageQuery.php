<?php

final class PhabricatorPackagesPackageQuery
  extends PhabricatorPackagesQuery {

  private $ids;
  private $phids;
  private $publisherPHIDs;
  private $packageKeys;
  private $fullKeys;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withPublisherPHIDs(array $phids) {
    $this->publisherPHIDs = $phids;
    return $this;
  }

  public function withPackageKeys(array $keys) {
    $this->packageKeys = $keys;
    return $this;
  }

  public function withFullKeys(array $keys) {
    $this->fullKeys = $keys;
    return $this;
  }

  public function withNameNgrams($ngrams) {
    return $this->withNgramsConstraint(
      new PhabricatorPackagesPackageNameNgrams(),
      $ngrams);
  }

  public function newResultObject() {
    return new PhabricatorPackagesPackage();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'p.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'p.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->publisherPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'p.publisherPHID IN (%Ls)',
        $this->publisherPHIDs);
    }

    if ($this->packageKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'p.packageKey IN (%Ls)',
        $this->packageKeys);
    }

    if ($this->fullKeys !== null) {
      $parts = $this->buildFullKeyClauseParts($conn, $this->fullKeys);
      $where[] = qsprintf($conn, '%Q', $parts);
    }

    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    $join_publisher = ($this->fullKeys !== null);
    if ($join_publisher) {
      $publisher_table = new PhabricatorPackagesPublisher();

      $joins[] = qsprintf(
        $conn,
        'JOIN %T u ON u.phid = p.publisherPHID',
        $publisher_table->getTableName());
    }

    return $joins;
  }

  protected function willFilterPage(array $packages) {
    $publisher_phids = mpull($packages, 'getPublisherPHID');

    $publishers = id(new PhabricatorPackagesPublisherQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($publisher_phids)
      ->execute();
    $publishers = mpull($publishers, null, 'getPHID');

    foreach ($packages as $key => $package) {
      $publisher = idx($publishers, $package->getPublisherPHID());

      if (!$publisher) {
        unset($packages[$key]);
        $this->didRejectResult($package);
        continue;
      }

      $package->attachPublisher($publisher);
    }

    return $packages;
  }

  protected function getPrimaryTableAlias() {
    return 'p';
  }

}
