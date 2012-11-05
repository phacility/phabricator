<?php

abstract class PhabricatorRepositoryCommitParserWorker
  extends PhabricatorWorker {

  protected $commit;
  protected $repository;

  final public function doWork() {
    $commit_id = idx($this->getTaskData(), 'commitID');
    if (!$commit_id) {
      return;
    }

    $commit = id(new PhabricatorRepositoryCommit())->load($commit_id);

    if (!$commit) {
      // TODO: Communicate permanent failure?
      return;
    }

    $this->commit = $commit;

    $repository = id(new PhabricatorRepository())->load(
      $commit->getRepositoryID());

    if (!$repository) {
      return;
    }

    $this->repository = $repository;

    return $this->parseCommit($repository, $commit);
  }

  final protected function shouldQueueFollowupTasks() {
    return !idx($this->getTaskData(), 'only');
  }

  abstract protected function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit);

  /**
   * This method is kind of awkward here but both the SVN message and
   * change parsers use it.
   */
  protected function getSVNLogXMLObject($uri, $revision, $verbose = false) {

    if ($verbose) {
      $verbose = '--verbose';
    }

    list($xml) = $this->repository->execxRemoteCommand(
      "log --xml {$verbose} --limit 1 %s@%d",
      $uri,
      $revision);

    // Subversion may send us back commit messages which won't parse because
    // they have non UTF-8 garbage in them. Slam them into valid UTF-8.
    $xml = phutil_utf8ize($xml);

    return new SimpleXMLElement($xml);
  }

  protected function isBadCommit($full_commit_name) {
    $repository = new PhabricatorRepository();

    $bad_commit = queryfx_one(
      $repository->establishConnection('w'),
      'SELECT * FROM %T WHERE fullCommitName = %s',
      PhabricatorRepository::TABLE_BADCOMMIT,
      $full_commit_name);

    return (bool)$bad_commit;
  }

}
