<?php

final class DiffusionGitBlameQuery extends DiffusionBlameQuery {

  protected function newBlameFuture(DiffusionRequest $request, $path) {
    $repository = $request->getRepository();

    $commit = $request->getCommit();

    // NOTE: The "--root" flag suppresses the addition of the "^" boundary
    // commit marker. Without it, root commits render with a "^" before them,
    // and one fewer character of the commit hash.

    return $repository->getLocalCommandFuture(
      '--no-pager blame --root -s -l %s -- %s',
      gitsprintf('%s', $commit),
      $path);
  }

  protected function resolveBlameFuture(ExecFuture $future) {
    list($err, $stdout) = $future->resolve();

    if ($err) {
      return null;
    }

    $result = array();

    $lines = phutil_split_lines($stdout);
    foreach ($lines as $line) {
      list($commit) = explode(' ', $line, 2);
      $result[] = $commit;
    }

    return $result;
  }

}
