<?php

final class PhabricatorGitGraphStream
  extends PhabricatorRepositoryGraphStream {

  private $repository;
  private $iterator;

  private $parents        = array();
  private $dates          = array();

  public function __construct(
    PhabricatorRepository $repository,
    $start_commit) {

    $this->repository = $repository;

    $future = $repository->getLocalCommandFuture(
      'log --format=%s %s --',
      '%H%x01%P%x01%ct',
      $start_commit);

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

    throw new Exception(
      pht(
        "No such commit '%s' in repository!",
        $commit));
  }

  private function isParsed($commit) {
    return isset($this->dates[$commit]);
  }

}
