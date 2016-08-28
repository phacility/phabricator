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

    if ($commit->isUnreachable()) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Commit "%s" (with internal ID "%s") is no longer reachable from '.
          'any branch, tag, or ref in this repository, so it will not be '.
          'imported. This usually means that the branch the commit was on '.
          'was deleted or overwritten.',
          $commit->getMonogram(),
          $commit_id));
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

  protected function getImportStepFlag() {
    return null;
  }

  final protected function shouldSkipImportStep() {
    // If this step has already been performed and this is a "natural" task
    // which was queued by the normal daemons, decline to do the work again.
    // This mitigates races if commits are rapidly deleted and revived.
    $flag = $this->getImportStepFlag();
    if (!$flag) {
      // This step doesn't have an associated flag.
      return false;
    }

    $commit = $this->commit;
    if (!$commit->isPartiallyImported($flag)) {
      // This commit doesn't have the flag set yet.
      return false;
    }


    if (!$this->shouldQueueFollowupTasks()) {
      // This task was queued by administrative tools, so do the work even
      // if it duplicates existing work.
      return false;
    }

    $this->log(
      "%s\n",
      pht(
        'Skipping import step; this step was previously completed for '.
        'this commit.'));

    return true;
  }

  abstract protected function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit);

  protected function loadCommitHint(PhabricatorRepositoryCommit $commit) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $repository = $commit->getRepository();

    return id(new DiffusionCommitHintQuery())
      ->setViewer($viewer)
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->withOldCommitIdentifiers(array($commit->getCommitIdentifier()))
      ->executeOne();
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
