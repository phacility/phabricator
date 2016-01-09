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

    $commit = id(new DiffusionCommitQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($commit_id))
      ->executeOne();
    if (!$commit) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Commit "%s" does not exist.', $commit_id));
    }

    $this->commit = $commit;

    return $commit;
  }

  final protected function doWork() {
    $commit = $this->loadCommit();
    $repository = $commit->getRepository();

    $this->repository = $repository;

    $this->parseCommit($repository, $this->commit);
  }

  final protected function shouldQueueFollowupTasks() {
    return !idx($this->getTaskData(), 'only');
  }

  abstract protected function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit);

  protected function isBadCommit(PhabricatorRepositoryCommit $commit) {
    $repository = new PhabricatorRepository();

    $bad_commit = queryfx_one(
      $repository->establishConnection('w'),
      'SELECT * FROM %T WHERE fullCommitName = %s',
      PhabricatorRepository::TABLE_BADCOMMIT,
      $commit->getMonogram());

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
