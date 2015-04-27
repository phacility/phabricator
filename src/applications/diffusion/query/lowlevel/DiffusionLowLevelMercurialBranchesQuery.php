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

    $specs = array();
    if ($this->contains !== null) {
      $specs['all'] = hgsprintf(
        '(descendants(%s) and head())',
        $this->contains);
      $specs['open'] = hgsprintf(
        '(descendants(%s) and head() and not closed())',
        $this->contains);
    } else {
      $specs['all'] = hgsprintf('head()');
      $specs['open'] = hgsprintf('head() and not closed()');
    }

    $futures = array();
    foreach ($specs as $key => $spec) {
      $futures[$key] = $repository->getLocalCommandFuture(
        'log --template %s --rev %s',
        '{node}\1{branch}\2',
        $spec);
    }

    $branches = array();
    $open = array();
    foreach (new FutureIterator($futures) as $key => $future) {
      list($stdout) = $future->resolvex();

      $lines = explode("\2", $stdout);
      $lines = array_filter($lines);
      foreach ($lines as $line) {
        list($node, $branch) = explode("\1", $line);
        $id = $node.'/'.$branch;
        if (empty($branches[$id])) {
          $branches[$id] = id(new DiffusionRepositoryRef())
            ->setShortName($branch)
            ->setCommitIdentifier($node);
        }

        if ($key == 'open') {
          $open[$id] = true;
        }
      }
    }

    foreach ($branches as $id => $branch) {
      $branch->setRawFields(
        array(
          'closed' => (empty($open[$id])),
        ));
    }

    return array_values($branches);
  }

}
