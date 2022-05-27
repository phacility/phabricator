<?php

final class PhabricatorAuthFactorProviderQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $statuses;
  private $providerFactorKeys;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withProviderFactorKeys(array $keys) {
    $this->providerFactorKeys = $keys;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorAuthFactorProvider();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status IN (%Ls)',
        $this->statuses);
    }

    if ($this->providerFactorKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'providerFactorKey IN (%Ls)',
        $this->providerFactorKeys);
    }

    return $where;
  }

  protected function willFilterPage(array $providers) {
    $map = PhabricatorAuthFactor::getAllFactors();
    foreach ($providers as $key => $provider) {
      $factor_key = $provider->getProviderFactorKey();
      $factor = idx($map, $factor_key);

      if (!$factor) {
        unset($providers[$key]);
        continue;
      }

      $provider->attachFactor($factor);
    }

    return $providers;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
