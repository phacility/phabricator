<?php

final class PhabricatorAuthFactorConfigQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $userPHIDs;
  private $factorProviderPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withUserPHIDs(array $user_phids) {
    $this->userPHIDs = $user_phids;
    return $this;
  }

  public function withFactorProviderPHIDs(array $provider_phids) {
    $this->factorProviderPHIDs = $provider_phids;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorAuthFactorConfig();
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

    if ($this->userPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->factorProviderPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'factorProviderPHID IN (%Ls)',
        $this->factorProviderPHIDs);
    }

    return $where;
  }

  protected function willFilterPage(array $configs) {
    $provider_phids = mpull($configs, 'getFactorProviderPHID');

    $providers = id(new PhabricatorAuthFactorProviderQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($provider_phids)
      ->execute();
    $providers = mpull($providers, null, 'getPHID');

    foreach ($configs as $key => $config) {
      $provider = idx($providers, $config->getFactorProviderPHID());

      if (!$provider) {
        unset($configs[$key]);
        $this->didRejectResult($config);
        continue;
      }

      $config->attachFactorProvider($provider);
    }

    return $configs;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
