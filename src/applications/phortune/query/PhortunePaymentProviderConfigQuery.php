<?php

final class PhortunePaymentProviderConfigQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $merchantPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withMerchantPHIDs(array $phids) {
    $this->merchantPHIDs = $phids;
    return $this;
  }

  protected function loadPage() {
    $table = new PhortunePaymentProviderConfig();
    $conn = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    return $table->loadAllFromArray($rows);
  }

  protected function willFilterPage(array $provider_configs) {
    $merchant_phids = mpull($provider_configs, 'getMerchantPHID');
    $merchants = id(new PhortuneMerchantQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($merchant_phids)
      ->execute();
    $merchants = mpull($merchants, null, 'getPHID');

    foreach ($provider_configs as $key => $config) {
      $merchant = idx($merchants, $config->getMerchantPHID());
      if (!$merchant) {
        $this->didRejectResult($config);
        unset($provider_configs[$key]);
        continue;
      }
      $config->attachMerchant($merchant);
    }

    return $provider_configs;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

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

    if ($this->merchantPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'merchantPHID IN (%Ls)',
        $this->merchantPHIDs);
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($conn, $where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

}
