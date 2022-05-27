<?php

final class PhabricatorAuthProviderConfigQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $providerClasses;
  private $isEnabled;

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withProviderClasses(array $classes) {
    $this->providerClasses = $classes;
    return $this;
  }

  public function withIsEnabled($is_enabled) {
    $this->isEnabled = $is_enabled;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorAuthProviderConfig();
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

    if ($this->providerClasses !== null) {
      $where[] = qsprintf(
        $conn,
        'providerClass IN (%Ls)',
        $this->providerClasses);
    }

    if ($this->isEnabled !== null) {
      $where[] = qsprintf(
        $conn,
        'isEnabled = %d',
        (int)$this->isEnabled);
    }

    return $where;
  }

  protected function willFilterPage(array $configs) {

    foreach ($configs as $key => $config) {
      $provider = $config->getProvider();
      if (!$provider) {
        unset($configs[$key]);
        continue;
      }
    }

    return $configs;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
