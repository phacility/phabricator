<?php

final class PhabricatorRepositoryMirrorQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $repositoryPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withRepositoryPHIDs(array $repository_phids) {
    $this->repositoryPHIDs = $repository_phids;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorRepositoryMirror();
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

  protected function willFilterPage(array $mirrors) {
    assert_instances_of($mirrors, 'PhabricatorRepositoryMirror');

    $repository_phids = mpull($mirrors, 'getRepositoryPHID');
    if ($repository_phids) {
      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($repository_phids)
        ->execute();
      $repositories = mpull($repositories, null, 'getPHID');
    } else {
      $repositories = array();
    }

    foreach ($mirrors as $key => $mirror) {
      $phid = $mirror->getRepositoryPHID();
      if (empty($repositories[$phid])) {
        unset($mirrors[$key]);
        continue;
      }
      $mirror->attachRepository($repositories[$phid]);
    }

    return $mirrors;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->repositoryPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
