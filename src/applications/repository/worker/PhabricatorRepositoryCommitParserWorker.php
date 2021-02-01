<?php

abstract class PhabricatorRepositoryCommitParserWorker
  extends PhabricatorWorker {

  protected $commit;
  protected $repository;

  private function loadCommit() {
    if ($this->commit) {
      return $this->commit;
    }

    $viewer = $this->getViewer();
    $task_data = $this->getTaskData();

    $commit_query = id(new DiffusionCommitQuery())
      ->setViewer($viewer);

    $commit_phid = idx($task_data, 'commitPHID');

    // TODO: See T13591. This supports execution of legacy tasks and can
    // eventually be removed. Newer tasks use "commitPHID" instead of
    // "commitID".
    if (!$commit_phid) {
      $commit_id = idx($task_data, 'commitID');
      if ($commit_id) {
        $legacy_commit = id(clone $commit_query)
          ->withIDs(array($commit_id))
          ->executeOne();
        if ($legacy_commit) {
          $commit_phid = $legacy_commit->getPHID();
        }
      }
    }

    if (!$commit_phid) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Task data has no "commitPHID".'));
    }

    $commit = id(clone $commit_query)
      ->withPHIDs(array($commit_phid))
      ->executeOne();
    if (!$commit) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Commit "%s" does not exist.', $commit_phid));
    }

    if ($commit->isUnreachable()) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Commit "%s" (with PHID "%s") is no longer reachable from any '.
          'branch, tag, or ref in this repository, so it will not be '.
          'imported. This usually means that the branch the commit was on '.
          'was deleted or overwritten.',
          $commit->getMonogram(),
          $commit_phid));
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

  private function shouldQueueFollowupTasks() {
    return !idx($this->getTaskData(), 'only');
  }

  final protected function queueCommitTask($task_class) {
    if (!$this->shouldQueueFollowupTasks()) {
      return;
    }

    $commit = $this->loadCommit();
    $repository = $commit->getRepository();

    $data = array(
      'commitPHID' => $commit->getPHID(),
    );

    $task_data = $this->getTaskData();
    if (isset($task_data['via'])) {
      $data['via'] = $task_data['via'];
    }

    $options = array(
      // We queue followup tasks at default priority so that the queue finishes
      // work it has started before starting more work. If followups are queued
      // at the same priority level, we do all message parses first, then all
      // change parses, etc. This makes progress uneven. See T11677 for
      // discussion.
      'priority' => parent::PRIORITY_DEFAULT,

      'objectPHID' => $commit->getPHID(),
      'containerPHID' => $repository->getPHID(),
    );

    $this->queueTask($task_class, $data, $options);
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
      ->withPHIDs(array(idx($this->getTaskData(), 'commitPHID')))
      ->executeOne();
    if (!$commit) {
      return $suffix;
    }

    $link = DiffusionView::linkCommit(
      $commit->getRepository(),
      $commit->getCommitIdentifier());

    return array($link, $suffix);
  }

  final protected function loadCommitData(PhabricatorRepositoryCommit $commit) {
    if ($commit->hasCommitData()) {
      return $commit->getCommitData();
    }

    $commit_id = $commit->getID();

    $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $commit_id);
    if (!$data) {
      $data = id(new PhabricatorRepositoryCommitData())
        ->setCommitID($commit_id);
    }

    $commit->attachCommitData($data);

    return $data;
  }

  final public function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

}
