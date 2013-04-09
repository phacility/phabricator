<?php

final class PhortunePaymentMethodQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $accountPHIDs;

  const STATUS_ANY = 'status-any';
  const STATUS_OPEN = 'status-open';
  private $status = self::STATUS_ANY;

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

  public function withStatus($status) {
    $this->status = $status;
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
    if (!$methods) {
      return array();
    }

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

    return $methods;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->accountPHIDs) {
      $where[] = qsprintf(
        $conn,
        'accountPHID IN (%Ls)',
        $this->accountPHIDs);
    }

    switch ($this->status) {
      case self::STATUS_ANY;
        break;
      case self::STATUS_OPEN:
        $where[] = qsprintf(
          $conn,
          'status in (%Ls)',
          array(
            PhortunePaymentMethod::STATUS_ACTIVE,
            PhortunePaymentMethod::STATUS_FAILED,
          ));
        break;
      default:
        throw new Exception("Unknown status '{$this->status}'!");
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($where);
  }

}
