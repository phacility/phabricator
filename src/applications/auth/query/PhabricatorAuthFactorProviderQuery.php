<?php

final class PhabricatorAuthFactorProviderQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }
  public function newResultObject() {
    return new PhabricatorAuthFactorProvider();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
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
