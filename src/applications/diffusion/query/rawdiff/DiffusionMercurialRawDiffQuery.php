<?php

final class DiffusionMercurialRawDiffQuery extends DiffusionRawDiffQuery {

  protected function executeQuery() {
    $raw_diff = $this->executeRawDiffCommand();

    // the only legitimate case here is if we are looking at the first commit
    // in the repository. no parents means first commit.
    if (!$raw_diff) {
      $drequest = $this->getRequest();
      $parent_query =
        DiffusionCommitParentsQuery::newFromDiffusionRequest($drequest);
      $parents = $parent_query->loadParents();
      if ($parents === array()) {
        // mercurial likes the string null here
        $this->setAgainstCommit('null');
        $raw_diff = $this->executeRawDiffCommand();
      }
    }

    return $raw_diff;
  }


  protected function executeRawDiffCommand() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $commit = $drequest->getCommit();

    // If there's no path, get the entire raw diff.
    $path = nonempty($drequest->getPath(), '.');

    $against = $this->getAgainstCommit();
    if ($against === null) {
      $against = $commit.'^';
    }

    $future = $repository->getLocalCommandFuture(
      'diff -U %d --git --rev %s:%s -- %s',
      $this->getLinesOfContext(),
      $against,
      $commit,
      $path);

    if ($this->getTimeout()) {
      $future->setTimeout($this->getTimeout());
    }

    list($raw_diff) = $future->resolvex();

    return $raw_diff;
  }

}
