<?php

abstract class PhabricatorRepositoryCommitParserWorker
  extends PhabricatorWorker {

  protected $commit;
  protected $repository;

  private function loadCommit() {
    if ($this->commit) {
      return $this->commit;
    }

    $commit_id = idx($this->getTaskData(), 'commitID');
    if (!$commit_id) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('No "%s" in task data.', 'commitID'));
    }

    $commit = id(new PhabricatorRepositoryCommit())->load($commit_id);

    if (!$commit) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Commit "%s" does not exist.', $commit_id));
    }

    return $this->commit = $commit;
  }

  final protected function doWork() {
    if (!$this->loadCommit()) {
      return;
    }

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($this->commit->getRepositoryID()))
      ->executeOne();
    if (!$repository) {
      return;
    }

    $this->repository = $repository;
    $this->parseCommit($repository, $this->commit);
  }

  final protected function shouldQueueFollowupTasks() {
    return !idx($this->getTaskData(), 'only');
  }

  abstract protected function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit);

  protected function isBadCommit($full_commit_name) {
    $repository = new PhabricatorRepository();

    $bad_commit = queryfx_one(
      $repository->establishConnection('w'),
      'SELECT * FROM %T WHERE fullCommitName = %s',
      PhabricatorRepository::TABLE_BADCOMMIT,
      $full_commit_name);

    return (bool)$bad_commit;
  }

  public function renderForDisplay(PhabricatorUser $viewer) {
    $suffix = parent::renderForDisplay($viewer);

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withIDs(array(idx($this->getTaskData(), 'commitID')))
      ->executeOne();
    if (!$commit) {
      return $suffix;
    }

    $link = DiffusionView::linkCommit(
      $commit->getRepository(),
      $commit->getCommitIdentifier());

    return array($link, $suffix);
  }
}
