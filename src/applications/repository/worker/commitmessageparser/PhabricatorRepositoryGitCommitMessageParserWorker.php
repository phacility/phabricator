<?php

final class PhabricatorRepositoryGitCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  protected function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $ref = id(new DiffusionLowLevelCommitQuery())
      ->setRepository($repository)
      ->withIdentifier($commit->getCommitIdentifier())
      ->execute();

    $this->updateCommitData($ref);

    if ($this->shouldQueueFollowupTasks()) {
      $this->queueTask(
        'PhabricatorRepositoryGitCommitChangeParserWorker',
        array(
          'commitID' => $commit->getID(),
        ));
    }
  }

}
