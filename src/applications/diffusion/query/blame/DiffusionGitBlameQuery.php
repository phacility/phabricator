<?php

final class DiffusionGitBlameQuery extends DiffusionBlameQuery {

  protected function newBlameFuture(DiffusionRequest $request, $path) {
    $repository = $request->getRepository();

    $commit = $request->getCommit();

    return $repository->getLocalCommandFuture(
      '--no-pager blame -s -l %s -- %s',
      $commit,
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
