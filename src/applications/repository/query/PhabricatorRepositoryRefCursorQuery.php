<?php

final class PhabricatorRepositoryRefCursorQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $repositoryPHIDs;
  private $refTypes;
  private $refNames;

  public function withRepositoryPHIDs(array $phids) {
    $this->repositoryPHIDs = $phids;
    return $this;
  }

  public function withRefTypes(array $types) {
    $this->refTypes = $types;
    return $this;
  }

  public function withRefNames(array $names) {
    $this->refNames = $names;
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

  protected function willFilterPage(array $refs) {
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

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->repositoryPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    if ($this->refTypes !== null) {
      $where[] = qsprintf(
        $conn_r,
        'refType IN (%Ls)',
        $this->refTypes);
    }

    if ($this->refNames !== null) {
      $name_hashes = array();
      foreach ($this->refNames as $name) {
        $name_hashes[] = PhabricatorHash::digestForIndex($name);
      }

      $where[] = qsprintf(
        $conn_r,
        'refNameHash IN (%Ls)',
        $name_hashes);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
