<?php

final class PhortunePaymentMethodQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $accountPHIDs;
  private $merchantPHIDs;
  private $statuses;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAccountPHIDs(array $phids) {
    $this->accountPHIDs = $phids;
    return $this;
  }

  public function withMerchantPHIDs(array $phids) {
    $this->merchantPHIDs = $phids;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  protected function loadPage() {
    $table = new PhortunePaymentMethod();
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

  protected function willFilterPage(array $methods) {
    $accounts = id(new PhortuneAccountQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(mpull($methods, 'getAccountPHID'))
      ->execute();
    $accounts = mpull($accounts, null, 'getPHID');

    foreach ($methods as $key => $method) {
      $account = idx($accounts, $method->getAccountPHID());
      if (!$account) {
        unset($methods[$key]);
        continue;
      }
      $method->attachAccount($account);
    }

    if (!$methods) {
      return $methods;
    }

    $merchants = id(new PhortuneMerchantQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(mpull($methods, 'getMerchantPHID'))
      ->execute();
    $merchants = mpull($merchants, null, 'getPHID');

    foreach ($methods as $key => $method) {
      $merchant = idx($merchants, $method->getMerchantPHID());
      if (!$merchant) {
        unset($methods[$key]);
        continue;
      }
      $method->attachMerchant($merchant);
    }

    if (!$methods) {
      return $methods;
    }

    $provider_configs = id(new PhortunePaymentProviderConfigQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(mpull($methods, 'getProviderPHID'))
      ->execute();
    $provider_configs = mpull($provider_configs, null, 'getPHID');

    foreach ($methods as $key => $method) {
      $provider_config = idx($provider_configs, $method->getProviderPHID());
      if (!$provider_config) {
        unset($methods[$key]);
        continue;
      }
      $method->attachProviderConfig($provider_config);
    }

    return $methods;
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

    if ($this->accountPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'accountPHID IN (%Ls)',
        $this->accountPHIDs);
    }

    if ($this->merchantPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'merchantPHID IN (%Ls)',
        $this->merchantPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status IN (%Ls)',
        $this->statuses);
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

}
