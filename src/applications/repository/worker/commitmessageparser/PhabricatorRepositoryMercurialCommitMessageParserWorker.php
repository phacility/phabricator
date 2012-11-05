<?php

final class PhabricatorRepositoryMercurialCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  public function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    list($stdout) = $repository->execxLocalCommand(
      'log --template %s --rev %s',
      '{author}\\n{desc}',
      $commit->getCommitIdentifier());

    list($author, $message) = explode("\n", $stdout, 2);

    $author = phutil_utf8ize($author);
    $message = phutil_utf8ize($message);
    $message = trim($message);

    $this->updateCommitData($author, $message);

    if ($this->shouldQueueFollowupTasks()) {
      PhabricatorWorker::scheduleTask(
        'PhabricatorRepositoryMercurialCommitChangeParserWorker',
        array(
          'commitID' => $commit->getID(),
        ));
    }
  }

  protected function getCommitHashes(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $commit_hash = $commit->getCommitIdentifier();

    return array(
      array(ArcanistDifferentialRevisionHash::HASH_MERCURIAL_COMMIT,
            $commit_hash),
    );
  }

}
