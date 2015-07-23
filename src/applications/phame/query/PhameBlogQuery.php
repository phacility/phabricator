<?php

final class PhameBlogQuery extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $domain;
  private $needBloggers;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withDomain($domain) {
    $this->domain = $domain;
    return $this;
  }

  protected function loadPage() {
    $table  = new PhameBlog();
    $conn_r = $table->establishConnection('r');

    $where_clause = $this->buildWhereClause($conn_r);
    $order_clause = $this->buildOrderClause($conn_r);
    $limit_clause = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T b %Q %Q %Q',
      $table->getTableName(),
      $where_clause,
      $order_clause,
      $limit_clause);

    $blogs = $table->loadAllFromArray($data);

    return $blogs;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ls)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->domain) {
      $where[] = qsprintf(
        $conn_r,
        'domain = %s',
        $this->domain);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    // TODO: Can we set this without breaking public blogs?
    return null;
  }

}
