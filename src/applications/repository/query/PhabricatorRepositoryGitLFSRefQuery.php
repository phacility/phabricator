<?php

final class PhabricatorRepositoryGitLFSRefQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $repositoryPHIDs;
  private $objectHashes;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withRepositoryPHIDs(array $phids) {
    $this->repositoryPHIDs = $phids;
    return $this;
  }

  public function withObjectHashes(array $hashes) {
    $this->objectHashes = $hashes;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorRepositoryGitLFSRef();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->repositoryPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    if ($this->objectHashes !== null) {
      $where[] = qsprintf(
        $conn,
        'objectHash IN (%Ls)',
        $this->objectHashes);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
