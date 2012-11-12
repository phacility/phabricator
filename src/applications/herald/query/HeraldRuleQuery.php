<?php

final class HeraldRuleQuery extends PhabricatorOffsetPagedQuery {

  private $authorPHIDs;
  private $ruleTypes;
  private $contentTypes;

  public function withAuthorPHIDs(array $author_phids) {
    $this->authorPHIDs = $author_phids;
    return $this;
  }

  public function withRuleTypes(array $types) {
    $this->ruleTypes = $types;
    return $this;
  }

  public function withContentTypes(array $types) {
    $this->contentTypes = $types;
    return $this;
  }

  public function execute() {
    $table = new HeraldRule();
    $conn_r = $table->establishConnection('r');

    $where = $this->buildWhereClause($conn_r);
    $order = $this->buildOrderClause($conn_r);
    $limit = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT rule.* FROM %T rule %Q %Q %Q',
      $table->getTableName(),
      $where,
      $order,
      $limit);

    return $table->loadAllFromArray($data);
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->authorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'rule.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->ruleTypes) {
      $where[] = qsprintf(
        $conn_r,
        'rule.ruleType IN (%Ls)',
        $this->ruleTypes);
    }

    if ($this->contentTypes) {
      $where[] = qsprintf(
        $conn_r,
        'rule.contentType IN (%Ls)',
        $this->contentTypes);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause($conn_r) {
    return 'ORDER BY id DESC';
  }

}
