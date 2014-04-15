<?php

final class PhabricatorRepositorySvnCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  public function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $ref = id(new DiffusionLowLevelCommitQuery())
      ->setRepository($repository)
      ->withIdentifier($commit->getCommitIdentifier())
      ->execute();

    $this->updateCommitData($ref);

    if ($this->shouldQueueFollowupTasks()) {
      $this->queueTask(
        'PhabricatorRepositorySvnCommitChangeParserWorker',
        array(
          'commitID' => $commit->getID(),
        ));
    }
  }

}
