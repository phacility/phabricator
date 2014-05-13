<?php

final class DiffusionSvnRawDiffQuery extends DiffusionRawDiffQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $commit = $this->getAnchorCommit();
    $arc_root = phutil_get_library_root('arcanist');

    $against = $this->getAgainstCommit();
    if ($against === null) {
      $against = $commit - 1;
    }

    $future = $repository->getRemoteCommandFuture(
      'diff --diff-cmd %s -x -U%d -r %d:%d %s',
      $arc_root.'/../scripts/repository/binary_safe_diff.sh',
      $this->getLinesOfContext(),
      $against,
      $commit,
      $repository->getSubversionPathURI($drequest->getPath()));

    $this->configureFuture($future);

    list($raw_diff) = $future->resolvex();
    return $raw_diff;
  }

}
