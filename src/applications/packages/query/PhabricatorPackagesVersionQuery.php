<?php

final class PhabricatorPackagesVersionQuery
  extends PhabricatorPackagesQuery {

  private $ids;
  private $phids;
  private $packagePHIDs;
  private $fullKeys;
  private $names;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withPackagePHIDs(array $phids) {
    $this->packagePHIDs = $phids;
    return $this;
  }

  public function withFullKeys(array $keys) {
    $this->fullKeys = $keys;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function withNameNgrams($ngrams) {
    return $this->withNgramsConstraint(
      new PhabricatorPackagesVersionNameNgrams(),
      $ngrams);
  }

  public function newResultObject() {
    return new PhabricatorPackagesVersion();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'v.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'v.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->packagePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'v.packagePHID IN (%Ls)',
        $this->packagePHIDs);
    }

    if ($this->names !== null) {
      $where[] = qsprintf(
        $conn,
        'v.name IN (%Ls)',
        $this->names);
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
    $join_package = ($this->fullKeys !== null) || $join_publisher;

    if ($join_package) {
      $package_table = new PhabricatorPackagesPackage();

      $joins[] = qsprintf(
        $conn,
        'JOIN %T p ON v.packagePHID = p.phid',
        $package_table->getTableName());
    }

    if ($join_publisher) {
      $publisher_table = new PhabricatorPackagesPublisher();

      $joins[] = qsprintf(
        $conn,
        'JOIN %T u ON u.phid = p.publisherPHID',
        $publisher_table->getTableName());
    }

    return $joins;
  }

  protected function willFilterPage(array $versions) {
    $package_phids = mpull($versions, 'getPackagePHID');

    $packages = id(new PhabricatorPackagesPackageQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($package_phids)
      ->execute();
    $packages = mpull($packages, null, 'getPHID');

    foreach ($versions as $key => $version) {
      $package = idx($packages, $version->getPackagePHID());

      if (!$package) {
        unset($versions[$key]);
        $this->didRejectResult($version);
        continue;
      }

      $version->attachPackage($package);
    }

    return $versions;
  }

  protected function getPrimaryTableAlias() {
    return 'v';
  }


}
