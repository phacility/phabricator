<?php

final class PhabricatorRepositoryGitCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  public function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $ref = id(new DiffusionLowLevelGitCommitQuery())
      ->setRepository($repository)
      ->withIdentifier($commit->getCommitIdentifier())
      ->execute();

    $committer_name   = $ref->getCommitterName();
    $committer_email  = $ref->getCommitterEmail();
    $author_name      = $ref->getAuthorName();
    $author_email     = $ref->getAuthorEmail();
    $message          = $ref->getMessage();

    if (strlen($author_email)) {
      $author = "{$author_name} <{$author_email}>";
    } else {
      $author = "{$author_name}";
    }

    if (strlen($committer_email)) {
      $committer = "{$committer_name} <{$committer_email}>";
    } else {
      $committer = "{$committer_name}";
    }

    if ($committer == $author) {
      $committer = null;
    }

    $this->updateCommitData($author, $message, $committer);

    if ($this->shouldQueueFollowupTasks()) {
      PhabricatorWorker::scheduleTask(
        'PhabricatorRepositoryGitCommitChangeParserWorker',
        array(
          'commitID' => $commit->getID(),
        ));
    }
  }

  protected function getCommitHashes(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    list($stdout) = $repository->execxLocalCommand(
      'log -n 1 --format=%s %s --',
      '%T',
      $commit->getCommitIdentifier());

    $commit_hash = $commit->getCommitIdentifier();
    $tree_hash = trim($stdout);

    return array(
      array(ArcanistDifferentialRevisionHash::HASH_GIT_COMMIT,
            $commit_hash),
      array(ArcanistDifferentialRevisionHash::HASH_GIT_TREE,
            $tree_hash),
    );
  }

}
