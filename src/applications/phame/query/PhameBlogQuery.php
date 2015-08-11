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

  public function newResultObject() {
    return new PhameBlog();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ls)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->domain !== null) {
      $where[] = qsprintf(
        $conn,
        'domain = %s',
        $this->domain);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    // TODO: Can we set this without breaking public blogs?
    return null;
  }

}
