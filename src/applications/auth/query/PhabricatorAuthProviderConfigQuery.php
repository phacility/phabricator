<?php

final class PhabricatorAuthProviderConfigQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $providerClasses;

  const STATUS_ALL = 'status:all';
  const STATUS_ENABLED = 'status:enabled';

  private $status = self::STATUS_ALL;

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function withProviderClasses(array $classes) {
    $this->providerClasses = $classes;
    return $this;
  }

  public static function getStatusOptions() {
    return array(
      self::STATUS_ALL      => pht('All Providers'),
      self::STATUS_ENABLED  => pht('Enabled Providers'),
    );
  }

  protected function loadPage() {
    $table = new PhabricatorAuthProviderConfig();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
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

    if ($this->providerClasses !== null) {
      $where[] = qsprintf(
        $conn,
        'providerClass IN (%Ls)',
        $this->providerClasses);
    }

    $status = $this->status;
    switch ($status) {
      case self::STATUS_ALL:
        break;
      case self::STATUS_ENABLED:
        $where[] = qsprintf(
          $conn,
          'isEnabled = 1');
        break;
      default:
        throw new Exception(pht("Unknown status '%s'!", $status));
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($conn, $where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
