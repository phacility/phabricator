<?php

final class DiffusionCommitHintQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $repositoryPHIDs;
  private $oldCommitIdentifiers;

  private $commits;
  private $commitMap;

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

  public function withCommits(array $commits) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');

    $repository_phids = array();
    foreach ($commits as $commit) {
      $repository_phids[] = $commit->getRepository()->getPHID();
    }

    $this->repositoryPHIDs = $repository_phids;
    $this->oldCommitIdentifiers = mpull($commits, 'getCommitIdentifier');
    $this->commits = $commits;

    return $this;
  }

  public function getCommitMap() {
    if ($this->commitMap === null) {
      throw new PhutilInvalidStateException('execute');
    }

    return $this->commitMap;
  }

  public function newResultObject() {
    return new PhabricatorRepositoryCommitHint();
  }

  protected function willExecute() {
    $this->commitMap = array();
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
        'repositoryPHID IN (%Ls)',
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

  protected function didFilterPage(array $hints) {
    if ($this->commits) {
      $map = array();
      foreach ($this->commits as $commit) {
        $repository_phid = $commit->getRepository()->getPHID();
        $identifier = $commit->getCommitIdentifier();
        $map[$repository_phid][$identifier] = $commit->getPHID();
      }

      foreach ($hints as $hint) {
        $repository_phid = $hint->getRepositoryPHID();
        $identifier = $hint->getOldCommitIdentifier();
        if (isset($map[$repository_phid][$identifier])) {
          $commit_phid = $map[$repository_phid][$identifier];
          $this->commitMap[$commit_phid] = $hint;
        }
      }
    }

    return $hints;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
