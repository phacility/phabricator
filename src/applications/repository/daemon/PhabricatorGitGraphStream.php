<?php

final class PhabricatorGitGraphStream {

  private $repository;
  private $iterator;

  private $parents        = array();
  private $dates          = array();

  public function __construct(
    PhabricatorRepository $repository,
    $start_commit) {

    $this->repository = $repository;

    $future = $repository->getLocalCommandFuture(
      "log --format=%s %s --",
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
    return $this->parents[$commit];
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

    throw new Exception("No such commit '{$commit}' in repository!");
  }

  private function isParsed($commit) {
    return isset($this->dates[$commit]);
  }

}
