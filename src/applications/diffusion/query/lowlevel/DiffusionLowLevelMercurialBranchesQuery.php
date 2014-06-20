<?php

/**
 * Execute and parse a low-level Mercurial branches query using `hg branches`.
 */
final class DiffusionLowLevelMercurialBranchesQuery
  extends DiffusionLowLevelQuery {

  private $contains;

  public function withContainsCommit($commit) {
    $this->contains = $commit;
    return $this;
  }

  protected function executeQuery() {
    $repository = $this->getRepository();

    if ($this->contains !== null) {
      $spec = hgsprintf('(descendants(%s) and head())', $this->contains);
    } else {
      $spec = hgsprintf('head()');
    }

    list($stdout) = $repository->execxLocalCommand(
      'log --template %s --rev %s',
      '{node}\1{branch}\2',
      $spec);

    $branches = array();

    $lines = explode("\2", $stdout);
    $lines = array_filter($lines);
    foreach ($lines as $line) {
      list($node, $branch) = explode("\1", $line);
      $branches[] = id(new DiffusionRepositoryRef())
        ->setShortName($branch)
        ->setCommitIdentifier($node);
    }

    return $branches;
  }

}
