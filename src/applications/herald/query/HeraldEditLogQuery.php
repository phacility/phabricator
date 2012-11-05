<?php

final class HeraldEditLogQuery extends PhabricatorOffsetPagedQuery {

  private $ruleIDs;

  public function withRuleIDs(array $rule_ids) {
    $this->ruleIDs = $rule_ids;
    return $this;
  }

  public function execute() {
    $table = new HeraldRuleEdit();
    $conn_r = $table->establishConnection('r');

    $where = $this->buildWhereClause($conn_r);
    $order = $this->buildOrderClause($conn_r);
    $limit = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT log.* FROM %T log %Q %Q %Q',
      $table->getTableName(),
      $where,
      $order,
      $limit);

    return $table->loadAllFromArray($data);
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->ruleIDs) {
      $where[] = qsprintf(
        $conn_r,
        'ruleID IN (%Ld)',
        $this->ruleIDs);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause($conn_r) {
    return 'ORDER BY id DESC';
  }

}
