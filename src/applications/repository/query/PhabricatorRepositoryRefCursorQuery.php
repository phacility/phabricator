<?php

final class PhabricatorRepositoryRefCursorQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $repositoryPHIDs;
  private $refTypes;
  private $refNames;
  private $datasourceQuery;
  private $needPositions;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

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

  public function withDatasourceQuery($query) {
    $this->datasourceQuery = $query;
    return $this;
  }

  public function needPositions($need) {
    $this->needPositions = $need;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorRepositoryRefCursor();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
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
        $this->didRejectResult($ref);
        unset($refs[$key]);
        continue;
      }
      $ref->attachRepository($repository);
    }

    if (!$refs) {
      return $refs;
    }

    if ($this->needPositions) {
      $positions = id(new PhabricatorRepositoryRefPosition())->loadAllWhere(
        'cursorID IN (%Ld)',
        mpull($refs, 'getID'));
      $positions = mgroup($positions, 'getCursorID');

      foreach ($refs as $key => $ref) {
        $ref_positions = idx($positions, $ref->getID(), array());
        $ref->attachPositions($ref_positions);
      }
    }

    return $refs;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

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

    if ($this->repositoryPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    if ($this->refTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'refType IN (%Ls)',
        $this->refTypes);
    }

    if ($this->refNames !== null) {
      $name_hashes = array();
      foreach ($this->refNames as $name) {
        $name_hashes[] = PhabricatorHash::digestForIndex($name);
      }

      $where[] = qsprintf(
        $conn,
        'refNameHash IN (%Ls)',
        $name_hashes);
    }

    if (strlen($this->datasourceQuery)) {
      $where[] = qsprintf(
        $conn,
        'refNameRaw LIKE %>',
        $this->datasourceQuery);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
