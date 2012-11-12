<?php

final class DiffusionSvnRawDiffQuery extends DiffusionRawDiffQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $commit = $drequest->getCommit();
    $arc_root = phutil_get_library_root('arcanist');

    $against = $this->getAgainstCommit();
    if ($against === null) {
      $against = $commit - 1;
    }

    $future = $repository->getRemoteCommandFuture(
      'diff --diff-cmd %s -x -U%d -r %d:%d %s%s@',
      $arc_root.'/../scripts/repository/binary_safe_diff.sh',
      $this->getLinesOfContext(),
      $against,
      $commit,
      $repository->getRemoteURI(),
      $drequest->getPath());

    if ($this->getTimeout()) {
      $future->setTimeout($this->getTimeout());
    }

    list($raw_diff) = $future->resolvex();
    return $raw_diff;
  }

}
