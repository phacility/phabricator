<?php

final class PhabricatorRepositoryArcanistProjectQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;

  private $needRepositories;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function needRepositories($need_repositories) {
    $this->needRepositories = $need_repositories;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorRepositoryArcanistProject();
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

  protected function willFilterPage(array $projects) {
    assert_instances_of($projects, 'PhabricatorRepositoryArcanistProject');

    if ($this->needRepositories) {
      $repository_ids = mpull($projects, 'getRepositoryID');
      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($this->getViewer())
        ->withIDs($repository_ids)
        ->execute();
      foreach ($projects as $project) {
        $repo = idx($repositories, $project->getRepositoryID());
        $project->attachRepository($repo);
      }
    }

    return $projects;
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

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }


  public function getQueryApplicationClass() {
    // TODO: Diffusion? Differential?
    return null;
  }

}
