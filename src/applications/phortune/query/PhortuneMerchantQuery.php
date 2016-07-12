<?php

final class PhortuneMerchantQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $memberPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withMemberPHIDs(array $member_phids) {
    $this->memberPHIDs = $member_phids;
    return $this;
  }

  protected function loadPage() {
    $table = new PhortuneMerchant();
    $conn = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT m.* FROM %T m %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildJoinClause($conn),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    return $table->loadAllFromArray($rows);
  }

  protected function willFilterPage(array $merchants) {
    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(mpull($merchants, 'getPHID'))
      ->withEdgeTypes(array(PhortuneMerchantHasMemberEdgeType::EDGECONST));
    $query->execute();

    foreach ($merchants as $merchant) {
      $member_phids = $query->getDestinationPHIDs(array($merchant->getPHID()));
      $member_phids = array_reverse($member_phids);
      $merchant->attachMemberPHIDs($member_phids);
    }

    return $merchants;
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

    if ($this->memberPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'e.dst IN (%Ls)',
        $this->memberPHIDs);
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($where);
  }

  protected function buildJoinClause(AphrontDatabaseConnection $conn) {
    $joins = array();

    if ($this->memberPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T e ON m.phid = e.src AND e.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhortuneMerchantHasMemberEdgeType::EDGECONST);
    }

    return implode(' ', $joins);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

}
