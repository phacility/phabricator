<?php

final class PhabricatorRepositorySvnCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  protected function parseCommitWithRef(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit,
    DiffusionCommitRef $ref) {

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
