<?php

final class PhabricatorRepositoryGitCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  protected function parseCommitWithRef(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit,
    DiffusionCommitRef $ref) {

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
