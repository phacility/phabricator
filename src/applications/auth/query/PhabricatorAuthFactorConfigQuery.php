<?php

final class PhabricatorAuthFactorConfigQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $userPHIDs;
  private $factorProviderPHIDs;
  private $factorProviderStatuses;

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

  public function withFactorProviderStatuses(array $statuses) {
    $this->factorProviderStatuses = $statuses;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorAuthFactorConfig();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'config.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'config.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->userPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'config.userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->factorProviderPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'config.factorProviderPHID IN (%Ls)',
        $this->factorProviderPHIDs);
    }

    if ($this->factorProviderStatuses !== null) {
      $where[] = qsprintf(
        $conn,
        'provider.status IN (%Ls)',
        $this->factorProviderStatuses);
    }

    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->factorProviderStatuses !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %R provider ON config.factorProviderPHID = provider.phid',
        new PhabricatorAuthFactorProvider());
    }

    return $joins;
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

  protected function getPrimaryTableAlias() {
    return 'config';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
