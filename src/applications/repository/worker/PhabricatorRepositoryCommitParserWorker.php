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
      return false;
    }

    $commit = id(new PhabricatorRepositoryCommit())->load($commit_id);

    if (!$commit) {
      // TODO: Communicate permanent failure?
      return false;
    }

    return $this->commit = $commit;
  }

  final public function doWork() {
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

    return $this->parseCommit($repository, $this->commit);
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

  public function renderForDisplay() {
    $suffix = parent::renderForDisplay();
    $commit = $this->loadCommit();
    if (!$commit) {
      return $suffix;
    }

    // TODO: (T603) This method should probably take a viewer.

    $repository = id(new PhabricatorRepository())
      ->load($commit->getRepositoryID());
    $link = DiffusionView::linkCommit($repository,
                                      $commit->getCommitIdentifier());
    return hsprintf('%s%s', $link, $suffix);
  }
}
