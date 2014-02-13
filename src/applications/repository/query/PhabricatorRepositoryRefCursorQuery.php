<?php

final class PhabricatorRepositoryRefCursorQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $repositoryPHIDs;
  private $refTypes;

  public function withRepositoryPHIDs(array $phids) {
    $this->repositoryPHIDs = $phids;
    return $this;
  }

  public function withRefTypes(array $types) {
    $this->refTypes = $types;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorRepositoryRefCursor();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T r %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  public function willFilterPage(array $refs) {
    $repository_phids = mpull($refs, 'getRepositoryPHID');

    $repositories = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($repository_phids)
      ->execute();
    $repositories = mpull($repositories, null, 'getPHID');

    foreach ($refs as $key => $ref) {
      $repository = idx($repositories, $ref->getRepositoryPHID());
      if (!$repository) {
        unset($refs[$key]);
        continue;
      }
      $ref->attachRepository($repository);
    }

    return $refs;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->repositoryPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    if ($this->refTypes) {
      $where[] = qsprintf(
        $conn_r,
        'refType IN (%Ls)',
        $this->refTypes);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }


  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationDiffusion';
  }

}
