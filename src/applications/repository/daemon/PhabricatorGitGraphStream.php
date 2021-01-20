<?php

final class PhabricatorGitGraphStream
  extends PhabricatorRepositoryGraphStream {

  private $repository;
  private $iterator;
  private $startCommit;

  private $parents        = array();
  private $dates          = array();

  public function __construct(
    PhabricatorRepository $repository,
    $start_commit = null) {

    $this->repository = $repository;
    $this->startCommit = $start_commit;

    if ($start_commit !== null) {
      $future = $repository->getLocalCommandFuture(
        'log %s %s --',
        '--format=%H%x01%P%x01%ct',
        gitsprintf('%s', $start_commit));
    } else {
      $future = $repository->getLocalCommandFuture(
        'log %s --all --',
        '--format=%H%x01%P%x01%ct');
    }

    $this->iterator = new LinesOfALargeExecFuture($future);
    $this->iterator->setDelimiter("\n");
    $this->iterator->rewind();
  }

  public function getParents($commit) {
    if (!isset($this->parents[$commit])) {
      $this->parseUntil($commit);
    }
    $parents = $this->parents[$commit];

    // NOTE: In Git, it is possible for a commit to list the same parent more
    // than once. See T5226. Discard duplicate parents.

    return array_unique($parents);
  }

  public function getCommitDate($commit) {
    if (!isset($this->dates[$commit])) {
      $this->parseUntil($commit);
    }
    return $this->dates[$commit];
  }

  private function parseUntil($commit) {
    if ($this->isParsed($commit)) {
      return;
    }

    $gitlog = $this->iterator;

    while ($gitlog->valid()) {
      $line = $gitlog->current();
      $gitlog->next();

      $line = trim($line);
      if (!strlen($line)) {
        break;
      }
      list($hash, $parents, $epoch) = explode("\1", $line);

      if ($parents) {
        $parents = explode(' ', $parents);
      } else {
        // First commit.
        $parents = array();
      }

      $this->dates[$hash] = $epoch;
      $this->parents[$hash] = $parents;

      if ($this->isParsed($commit)) {
        return;
      }
    }

    if ($this->startCommit !== null) {
      throw new Exception(
        pht(
          'Commit "%s" is not a reachable ancestor of "%s".',
          $commit,
          $this->startCommit));
    } else {
      throw new Exception(
        pht(
          'Commit "%s" is not a reachable ancestor of any ref.',
          $commit));
    }
  }

  private function isParsed($commit) {
    return isset($this->dates[$commit]);
  }

}
