<?php

final class PhabricatorRepositoryURIQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $repositoryPHIDs;
  private $repositories = array();

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

  public function withRepositories(array $repositories) {
    $repositories = mpull($repositories, null, 'getPHID');
    $this->withRepositoryPHIDs(array_keys($repositories));
    $this->repositories = $repositories;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorRepositoryURI();
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

    return $where;
  }

  protected function willFilterPage(array $uris) {
    $repositories = $this->repositories;

    $repository_phids = mpull($uris, 'getRepositoryPHID');
    $repository_phids = array_fuse($repository_phids);
    $repository_phids = array_diff_key($repository_phids, $repositories);

    if ($repository_phids) {
      $more_repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($repository_phids)
        ->execute();
      $repositories += mpull($more_repositories, null, 'getPHID');
    }

    foreach ($uris as $key => $uri) {
      $repository_phid = $uri->getRepositoryPHID();
      $repository = idx($repositories, $repository_phid);
      if (!$repository) {
        $this->didRejectResult($uri);
        unset($uris[$key]);
        continue;
      }
      $uri->attachRepository($repository);
    }

    return $uris;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
