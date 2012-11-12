<?php

final class PhabricatorRepositorySvnCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  public function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $uri = $repository->getDetail('remote-uri');

    $log = $this->getSVNLogXMLObject(
      $uri,
      $commit->getCommitIdentifier(),
      $verbose = false);

    $entry = $log->logentry[0];

    $author = (string)$entry->author;
    $message = (string)$entry->msg;

    $this->updateCommitData($author, $message);

    if ($this->shouldQueueFollowupTasks()) {
      PhabricatorWorker::scheduleTask(
        'PhabricatorRepositorySvnCommitChangeParserWorker',
        array(
          'commitID' => $commit->getID(),
        ));
    }
  }

  protected function getCommitHashes(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {
    return array();
  }

}
