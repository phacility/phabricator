<?php

final class DiffusionCommitHintQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $repositoryPHIDs;
  private $oldCommitIdentifiers;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withRepositoryPHIDs(array $phids) {
    $this->repositoryPHIDs = $phids;
    return $this;
  }

  public function withOldCommitIdentifiers(array $identifiers) {
    $this->oldCommitIdentifiers = $identifiers;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorRepositoryCommitHint();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
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
        'reositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    if ($this->oldCommitIdentifiers !== null) {
      $where[] = qsprintf(
        $conn,
        'oldCommitIdentifier IN (%Ls)',
        $this->oldCommitIdentifiers);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
